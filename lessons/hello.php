<?php
// UNIT 1: hardware/software of websites + "Hello, World" in PHP.
// Browser (client) --HTTP request--> Apache (web server software) --runs--> PHP --queries--> MySQL
// Everything below is produced on the SERVER; the browser only receives plain HTML.
echo '<h1>Hello, World!</h1>';
?>
<h2>My production environment</h2>
<ul>
  <li>Server software: <?= htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? 'unknown') ?></li>
  <li>PHP version: <?= PHP_VERSION ?> on <?= PHP_OS ?></li>
  <li>Server time: <?= date('l, j F Y H:i:s') ?> (<?= date_default_timezone_get() ?>)</li>
  <li>Required extensions: <?php foreach (['pdo_mysql', 'mysqli', 'curl', 'gd', 'mbstring'] as $e) echo $e . ' ' . (extension_loaded($e) ? '✔' : '✘') . ' &nbsp; '; ?></li>
</ul>
<p>Next: <a href="unit2_basics.php">Unit 2 – PHP basics</a> · <a href="../api/health.php">Backend health check</a></p>
