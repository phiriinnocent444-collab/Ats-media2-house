<?php
// XAMPP Pre-flight Check & One-Click Database Setup (HTML + PHP + MySQL)
require_once __DIR__ . '/app/bootstrap.php';

$setupMessage = null;
$setupOk = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  try {
    global $CONFIG;
    xampp_install_schema($CONFIG['db']);
    $pdo = db();
    xampp_seed_defaults($pdo);
    $setupOk = true;
    $setupMessage = 'Database "ats_media_house" initialized and default studio data verified.';
  } catch (Throwable $e) {
    $setupMessage = 'Database initialization error: ' . $e->getMessage();
  }
}

$checks = [];
$checks[] = [
  'name'   => 'PHP Version',
  'status' => PHP_VERSION_ID >= 80000,
  'detail' => 'PHP ' . PHP_VERSION . ' (requires 8.0+)',
];
foreach (['pdo_mysql' => 'PDO MySQL', 'gd' => 'GD Image Processing', 'curl' => 'cURL (Live Airtel)', 'mbstring' => 'Multibyte String', 'fileinfo' => 'Fileinfo MIME Check'] as $ext => $label) {
  $loaded = extension_loaded($ext);
  $checks[] = [
    'name'   => $label,
    'status' => $loaded,
    'detail' => $loaded ? 'Enabled' : ($ext === 'curl' ? 'Optional (UAT sandbox active without cURL)' : 'Check xampp/php/php.ini'),
  ];
}
foreach (['uploads/gallery' => 'Gallery uploads folder', 'uploads/thumbs' => 'Thumbnails folder', 'storage' => 'Mail log folder'] as $dir => $label) {
  $full = __DIR__ . '/' . $dir;
  $ok = is_dir($full) && is_writable($full);
  $checks[] = [
    'name'   => $label,
    'status' => $ok,
    'detail' => $ok ? 'Writable' : 'Make folder writable',
  ];
}

$dbConnected = false;
$dbDetail = '';
try {
  $pdo = db();
  $svcCount = (int) $pdo->query('SELECT COUNT(*) FROM services')->fetchColumn();
  $galCount = (int) $pdo->query('SELECT COUNT(*) FROM gallery')->fetchColumn();
  $bkCount  = (int) $pdo->query('SELECT COUNT(*) FROM bookings')->fetchColumn();
  $dbConnected = true;
  $dbDetail = "Connected (InnoDB) — {$svcCount} services, {$galCount} gallery photos, {$bkCount} bookings";
} catch (Throwable $e) {
  $dbDetail = $e->getMessage();
}
$checks[] = [
  'name'   => 'MySQL / MariaDB (' . ($CONFIG['db']['name'] ?? 'ats_media_house') . ')',
  'status' => $dbConnected,
  'detail' => $dbDetail,
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>XAMPP Setup | ATS media house</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,500;0,700;1,500&family=Work+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
</head>
<body class="app">
  <header class="site-header">
    <div class="frame">
      <a class="brand" href="index.php">
        <span class="brand-mark" aria-hidden="true">◎</span>
        <span class="brand-text">ATS <em>media house</em></span>
      </a>
      <nav aria-label="Primary">
        <a href="index.php">Home</a>
        <a href="app/index.php?r=page/book">Book</a>
        <a href="app/index.php?r=page/gallery">Gallery</a>
        <a href="app/index.php?r=auth/login">Admin</a>
      </nav>
    </div>
  </header>

  <main class="section">
    <div class="frame">
      <p class="eyebrow">XAMPP Environment</p>
      <h2>Studio setup &amp; diagnostics</h2>
      <p class="section-lede">Place this folder inside <code>htdocs/ats-media-house/</code>, start Apache and MySQL in XAMPP Control Panel, then run or verify setup below.</p>

      <?php if ($setupMessage): ?>
        <p class="flash <?= $setupOk ? 'ok' : 'err' ?>" role="status"><?= h($setupMessage) ?></p>
      <?php endif; ?>

      <div class="scroll">
        <table class="table">
          <tr><th>Component</th><th>State</th><th>Details</th></tr>
          <?php foreach ($checks as $c): ?>
            <tr>
              <td><strong><?= h($c['name']) ?></strong></td>
              <td><span class="status-label <?= $c['status'] ? 'paid' : 'cancelled' ?>"><?= $c['status'] ? 'ready' : 'check' ?></span></td>
              <td><?= h($c['detail']) ?></td>
            </tr>
          <?php endforeach; ?>
        </table>
      </div>

      <div style="display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
        <form method="post" action="setup.php" class="inline">
          <?= csrf_field() ?>
          <button type="submit" class="btn btn-solid">Initialize / Verify Database</button>
        </form>
        <a href="index.php" class="btn btn-outline">Open Studio Website</a>
        <a href="app/index.php?r=auth/login" class="btn btn-ghost">Admin Portal</a>
      </div>
    </div>
  </main>
</body>
</html>
