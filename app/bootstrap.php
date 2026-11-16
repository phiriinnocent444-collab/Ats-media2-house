<?php
// Shared application bootstrap: loads DB/transaction helpers, functions, autoloader, and iframe-safe sessions.
date_default_timezone_set('Africa/Lusaka');
ob_start();
require_once __DIR__ . '/../api/booking_lib.php';
require_once __DIR__ . '/functions.php';

spl_autoload_register(function (string $class) {
  foreach (['core', 'models', 'controllers'] as $dir) {
    $f = __DIR__ . "/$dir/$class.php";
    if (is_file($f)) { require $f; return; }
  }
});

// Adaptive session cookies: SameSite=None; Secure; Partitioned in HTTPS preview iframes, SameSite=Lax on HTTP XAMPP.
$https = is_https_request();
session_set_cookie_params([
  'lifetime' => 0,
  'path'     => '/',
  'httponly' => true,
  'secure'   => $https,
  'samesite' => $https ? 'None' : 'Lax',
]);
ini_set('session.use_strict_mode', '1');

if ($https) {
  header_register_callback(function () {
    if (headers_sent()) return;
    $cookies = [];
    $updated = false;
    foreach (headers_list() as $hdr) {
      if (stripos($hdr, 'Set-Cookie:') === 0) {
        $val = trim(substr($hdr, strlen('Set-Cookie:')));
        if (stripos($val, 'SameSite=None') !== false && stripos($val, 'Partitioned') === false) {
          $val .= '; Partitioned';
          $updated = true;
        }
        $cookies[] = $val;
      }
    }
    if ($updated && $cookies) {
      header_remove('Set-Cookie');
      foreach ($cookies as $c) {
        header('Set-Cookie: ' . $c, false);
      }
    }
  });
}

session_start();
admin_cookieless_bootstrap();
