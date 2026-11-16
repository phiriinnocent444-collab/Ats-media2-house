<?php
// Shared helpers: DB connection, transactions, JSON I/O, errors, admin guard.
$CONFIG = require __DIR__ . '/config.php';

class HttpError extends RuntimeException {
  public int $http;
  public function __construct(string $msg, int $http) { parent::__construct($msg); $this->http = $http; }
}

function str_len(string $s): int {
  return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s);
}

function xampp_seed_defaults(PDO $pdo): void {
  if ((int) $pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn() === 0) {
    $pdo->prepare('INSERT INTO admin_users (username, password_hash) VALUES (?, ?)')
        ->execute(['admin', password_hash('ats2026', PASSWORD_DEFAULT)]);
  }
  if ((int) $pdo->query('SELECT COUNT(*) FROM gallery')->fetchColumn() === 0) {
    $adminId = (int) $pdo->query("SELECT admin_id FROM admin_users WHERE username = 'admin'")->fetchColumn();
    $photos = [
      ['Kabulonga Evening Reception', 'wedding_reception.jpg'],
      ['Lusaka Executive Portrait', 'corporate_portrait.jpg'],
      ['Annual Gala & Banquet', 'annual_gala.jpg'],
      ['UNZA Graduation Memories', 'unza_graduation.jpg'],
    ];
    $st = $pdo->prepare('INSERT INTO gallery (title, filename, thumb, uploaded_by) VALUES (?,?,?,?)');
    foreach ($photos as [$title, $file]) {
      if (is_file(__DIR__ . '/../uploads/gallery/' . $file)) {
        $st->execute([$title, $file, $file, $adminId ?: null]);
      }
    }
  }
}

function xampp_install_schema(array $c): void {
  $port = (int) ($c['port'] ?? 3306);
  $serverPdo = new PDO("mysql:host={$c['host']};port={$port};charset=utf8mb4", $c['user'], $c['pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  ]);
  $dbName = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $c['name']) ?: 'ats_media_house';
  $serverPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
  $serverPdo->exec("USE `{$dbName}`");
  $schemaFile = __DIR__ . '/../database/schema.sql';
  if (is_file($schemaFile)) {
    $sql = file_get_contents($schemaFile) ?: '';
    $lines = preg_split('/\R/', $sql);
    $cleanLines = array_filter($lines, fn($l) => !preg_match('/^\s*--/', $l));
    $cleanSql = implode("\n", $cleanLines);
    foreach (explode(';', $cleanSql) as $stmt) {
      $stmt = trim($stmt);
      if ($stmt === '' || stripos($stmt, 'CREATE DATABASE') === 0 || stripos($stmt, 'USE ') === 0) continue;
      $serverPdo->exec($stmt);
    }
  }
  xampp_seed_defaults($serverPdo);
}

function db(): PDO {
  global $CONFIG;
  static $pdo = null;
  if ($pdo === null) {
    $c = $CONFIG['db'];
    $port = (int) ($c['port'] ?? 3306);
    $dsn = "mysql:host={$c['host']};port={$port};dbname={$c['name']};charset=utf8mb4";
    $opts = [
      PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES   => false,
      // Strict mode: bad data is rejected instead of silently truncated (Consistency).
      PDO::MYSQL_ATTR_INIT_COMMAND => "SET SESSION sql_mode='STRICT_ALL_TABLES,NO_ENGINE_SUBSTITUTION'",
    ];
    try {
      $pdo = new PDO($dsn, $c['user'], $c['pass'], $opts);
      if (!$pdo->query("SHOW TABLES LIKE 'services'")->fetchColumn()) {
        xampp_install_schema($c);
      }
    } catch (PDOException $e) {
      if ((int) ($e->errorInfo[1] ?? 0) === 1049) {
        xampp_install_schema($c);
        $pdo = new PDO($dsn, $c['user'], $c['pass'], $opts);
      } else {
        throw $e;
      }
    }
  }
  return $pdo;
}

/** ATOMICITY: run $fn inside one transaction. Any exception => ROLLBACK, success => COMMIT. */
function with_tx(callable $fn) {
  $pdo = db();
  if ($pdo->inTransaction()) return $fn($pdo);   // nested call: join the caller's transaction (all-or-nothing for the whole group)
  $pdo->beginTransaction();
  try {
    $r = $fn($pdo);
    $pdo->commit();
    return $r;
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $e;
  }
}

/** Wraps an endpoint: turns exceptions into clean JSON errors. */
function handle(callable $fn): void {
  try { $fn(); }
  catch (HttpError $e) { json_out(['error' => $e->getMessage()], $e->http); }
  catch (PDOException $e) {
    $n = (int) ($e->errorInfo[1] ?? 0);
    if ($n === 1062) json_out(['error' => 'Duplicate record.'], 409);
    if ($n === 1213 || $n === 1205) json_out(['error' => 'System busy, please retry.'], 503);
    if ($n === 1451 || $n === 1452) json_out(['error' => 'Operation would break a relationship.'], 409);
    error_log($e->getMessage()); json_out(['error' => 'Database error.'], 500);
  }
  catch (Throwable $e) { error_log($e->getMessage()); json_out(['error' => 'Server error.'], 500); }
}

function json_out($data, int $code = 200): void {
  http_response_code($code);
  header('Content-Type: application/json');
  echo json_encode($data);
  exit;
}

function json_in(): array {
  $d = json_decode(file_get_contents('php://input'), true);
  return is_array($d) ? $d : [];
}

function new_id(string $prefix): string {
  return $prefix . (int) round(microtime(true) * 1000) . random_int(100, 999);
}

// 0972971151 / 972971151 / +260972971151 all become 972971151
function normalize_msisdn(string $phone): string {
  $d = preg_replace('/\D/', '', $phone);
  if (strpos($d, '260') === 0) $d = substr($d, 3);
  if (strpos($d, '0') === 0)   $d = substr($d, 1);
  return $d;
}

function require_admin(): void {
  global $CONFIG;
  $want = (string) ($CONFIG['admin_token'] ?? '');
  $got  = (string) ($_SERVER['HTTP_X_ADMIN_TOKEN'] ?? '');
  if ($want === '' || $want === 'change_me_to_a_long_random_string' || !hash_equals($want, $got)) {
    throw new HttpError('Admin token required.', 401);
  }
}
