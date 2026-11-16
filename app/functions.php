<?php
// Shared helpers: escaping, formatting, URLs, iframe-safe cookies, HMAC+session CSRF, mail log.

function h($s): string { return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function money($n): string { return 'K' . number_format((float) $n, 2); }
function nice_date(string $d): string { return date('D, j M Y', strtotime($d)); }

/** Days from today until $date (negative = in the past). */
function days_until(string $date): int {
  return (int) (new DateTime('today'))->diff(new DateTime($date))->format('%r%a');
}

function admin_active_token(): string {
  return (string) ($GLOBALS['_ats_admin_token'] ?? '');
}

function admin_token_create(int $id, string $name): string {
  $ts = (string) time();
  $payload = $ts . '.' . $id . '.' . rtrim(strtr(base64_encode($name), '+/', '-_'), '=');
  $sig = hash_hmac('sha256', $payload, csrf_secret());
  return $payload . '.' . $sig;
}

function admin_token_verify(string $token): ?array {
  $parts = explode('.', trim($token));
  if (count($parts) !== 4 || !ctype_digit($parts[0]) || !ctype_digit($parts[1])) return null;
  $ts = (int) $parts[0];
  if ($ts > time() + 60 || (time() - $ts) > 28800) return null;
  $payload = $parts[0] . '.' . $parts[1] . '.' . $parts[2];
  $expected = hash_hmac('sha256', $payload, csrf_secret());
  if (!hash_equals($expected, $parts[3])) return null;
  $b64 = strtr($parts[2], '-_', '+/');
  $pad = strlen($b64) % 4;
  if ($pad > 0) $b64 .= str_repeat('=', 4 - $pad);
  $name = base64_decode($b64, true);
  if ($name === false || $name === '') return null;
  return ['id' => (int) $parts[1], 'name' => $name];
}

function admin_cookieless_bootstrap(): void {
  $tok = trim((string) ($_POST['_at'] ?? $_GET['_at'] ?? ''));
  if ($tok === '') return;
  $admin = admin_token_verify($tok);
  if (!$admin) return;
  if (empty($_SESSION['admin'])) {
    $_SESSION['admin'] = $admin;
  }
  $GLOBALS['_ats_admin_token'] = $tok;
}

function admin_token_field(): string {
  $tok = admin_active_token();
  return $tok !== '' ? '<input type="hidden" name="_at" value="' . h($tok) . '">' : '';
}

function url(string $route = '', array $q = []): string {
  $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
  $tok = admin_active_token();
  if ($tok !== '' && !isset($q['_at']) && (strpos($route, 'admin/') === 0 || $route === 'auth/logout')) {
    $q['_at'] = $tok;
  }
  return $dir . '/index.php?r=' . $route . ($q ? '&' . http_build_query($q) : '');
}

/** Unit 6.1 HTTP headers: redirect, then stop the script. */
function redirect(string $to) { header('Location: ' . $to); exit; }

function flash_storage_file(string $tok): string {
  return __DIR__ . '/../storage/.flash_' . substr(hash_hmac('sha256', $tok, csrf_secret()), 0, 24) . '.json';
}

function flash(?string $msg = null, string $type = 'ok'): ?array {
  $tok = admin_active_token();
  if ($msg !== null) {
    $_SESSION['flash'] = [$type, $msg];
    if ($tok !== '') {
      $dir = __DIR__ . '/../storage';
      if (!is_dir($dir)) @mkdir($dir, 0755, true);
      @file_put_contents(flash_storage_file($tok), json_encode([$type, $msg]), LOCK_EX);
    }
    return null;
  }
  $f = $_SESSION['flash'] ?? null;
  unset($_SESSION['flash']);
  if ($tok !== '') {
    $file = flash_storage_file($tok);
    if (is_file($file)) {
      $raw = @file_get_contents($file);
      @unlink($file);
      if (!$f && $raw !== false) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded) && count($decoded) === 2) $f = $decoded;
      }
    }
  }
  return $f;
}

function is_https_request(): bool {
  if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') return true;
  if (stripos((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''), 'https') !== false) return true;
  if (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '')) === 'on') return true;
  if (stripos((string) ($_SERVER['HTTP_FORWARDED'] ?? ''), 'proto=https') !== false) return true;
  if (stripos((string) ($_SERVER['HTTP_ORIGIN'] ?? ''), 'https://') === 0) return true;
  if (stripos((string) ($_SERVER['HTTP_REFERER'] ?? ''), 'https://') === 0) return true;
  return false;
}

/** Sets a cookie that works both in XAMPP HTTP localhost and cross-site HTTPS preview iframes. */
function app_set_cookie(string $name, string $value, int $expires = 0): void {
  $https = is_https_request();
  $parts = [
    $name . '=' . rawurlencode($value),
    'Path=/',
    'HttpOnly',
    'SameSite=' . ($https ? 'None' : 'Lax'),
  ];
  if ($expires > 0) {
    $parts[] = 'Expires=' . gmdate('D, d M Y H:i:s \G\M\T', $expires);
    $parts[] = 'Max-Age=' . max(0, $expires - time());
  }
  if ($https) {
    $parts[] = 'Secure';
    $parts[] = 'Partitioned';
  }
  header('Set-Cookie: ' . implode('; ', $parts), false);
  $_COOKIE[$name] = $value;
}

function csrf_secret(): string {
  static $secret = null;
  if ($secret !== null) return $secret;
  $storageDir = __DIR__ . '/../storage';
  $file = $storageDir . '/.csrf_secret';
  if (is_file($file)) {
    $val = trim((string) @file_get_contents($file));
    if (strlen($val) >= 32) return $secret = $val;
  }
  $generated = bin2hex(random_bytes(32));
  if (!is_dir($storageDir)) @mkdir($storageDir, 0755, true);
  if (@file_put_contents($file, $generated, LOCK_EX) !== false) {
    @chmod($file, 0600);
    return $secret = $generated;
  }
  return $secret = hash('sha256', __FILE__ . '|' . php_uname('n'));
}

function csrf_token(): string {
  $existing = (string) ($_SESSION['csrf'] ?? '');
  if ($existing !== '' && strpos($existing, '.') !== false) {
    return $existing;
  }
  $ts = (string) time();
  $nonce = bin2hex(random_bytes(12));
  $sig = hash_hmac('sha256', $ts . '.' . $nonce, csrf_secret());
  $token = $ts . '.' . $nonce . '.' . $sig;
  $_SESSION['csrf'] = $token;
  return $token;
}

// Dual-layer CSRF check: validates against $_SESSION['csrf'] OR a non-expired HMAC-signed token.
function csrf_field(): string {
  return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">' . admin_token_field();
}

function csrf_origin_ok(): bool {
  $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
  if ($origin === '' || $origin === 'null') return true;
  $originHost = strtolower((string) parse_url($origin, PHP_URL_HOST));
  $serverHost = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? ''));
  $serverHost = preg_replace('/:\d+$/', '', $serverHost);
  return $originHost !== '' && $serverHost !== '' && $originHost === $serverHost;
}

function csrf_verify(): bool {
  $posted = trim((string) ($_POST['csrf'] ?? ''));
  $sessionToken = (string) ($_SESSION['csrf'] ?? '');

  if ($posted !== '' && $sessionToken !== '' && hash_equals($sessionToken, $posted)) {
    return true;
  }

  $parts = explode('.', $posted);
  if (count($parts) === 3 && ctype_digit($parts[0]) && $parts[1] !== '' && $parts[2] !== '') {
    $ts = (int) $parts[0];
    $expectedSig = hash_hmac('sha256', $parts[0] . '.' . $parts[1], csrf_secret());
    $notExpired = ($ts <= time() + 60) && ((time() - $ts) <= 43200);
    if ($notExpired && hash_equals($expectedSig, $parts[2]) && csrf_origin_ok()) {
      $_SESSION['csrf'] = $posted;
      return true;
    }
  }

  unset($_SESSION['csrf']);
  return false;
}

function csrf_check(): void {
  if (csrf_verify()) return;
  http_response_code(400);
  $back = h((string) ($_SERVER['REQUEST_URI'] ?? '../index.php'));
  exit('<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Session refreshed | ATS media house</title><style>body{font-family:system-ui,sans-serif;background:#ECE6D6;color:#14171C;padding:48px 20px;margin:0}main{max-width:460px;margin:0 auto;background:#F7F3E8;border:1px solid #C9C0AD;padding:28px}a{display:inline-block;margin-top:14px;padding:10px 16px;background:#1F2B4D;color:#fff;text-decoration:none;font-weight:600}</style></head><body><main><h1>Form session expired</h1><p>Your form security token expired or could not be verified. Please reload the page to receive a fresh token and submit again.</p><a href="' . $back . '">Reload form</a></main></body></html>');
}

/** Unit 6.1 e-mail. XAMPP has no mail server by default, so every message is also written to storage/mail.log. */
function send_mail(string $to, string $subject, string $body): bool {
  $storageDir = __DIR__ . '/../storage';
  if (!is_dir($storageDir)) @mkdir($storageDir, 0755, true);
  $line = '[' . date('Y-m-d H:i:s') . "] TO: $to | $subject\n$body\n---\n";
  @file_put_contents($storageDir . '/mail.log', $line, FILE_APPEND | LOCK_EX);
  $headers = "From: ATS media house <noreply@localhost>\r\nContent-Type: text/plain; charset=UTF-8";
  return @mail($to, $subject, $body, $headers);
}
