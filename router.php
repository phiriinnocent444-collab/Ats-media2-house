<?php
// Pure PHP router for the built-in PHP web server (port 3000)
// Supports both root (/) and XAMPP-style subfolder (/ats-media-house/) routing.
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');

if ($uri === '/ats-media-house') {
  header('Location: /ats-media-house/');
  exit;
}

if (strpos($uri, '/ats-media-house/') === 0) {
  $sub = substr($uri, strlen('/ats-media-house'));
  $uri = $sub === '' ? '/' : $sub;
}

if ($uri === '/' || $uri === '/index.html') {
  $_SERVER['SCRIPT_NAME'] = (strpos($_SERVER['REQUEST_URI'], '/ats-media-house/') === 0 ? '/ats-media-house' : '') . '/index.php';
  require __DIR__ . '/index.php';
  return true;
}

$target = __DIR__ . $uri;
if ($uri !== '/' && is_file($target)) {
  if (strtolower(pathinfo($target, PATHINFO_EXTENSION)) === 'php') {
    $prefix = strpos($_SERVER['REQUEST_URI'], '/ats-media-house/') === 0 ? '/ats-media-house' : '';
    $_SERVER['SCRIPT_NAME'] = $prefix . $uri;
    require $target;
    return true;
  }
  if (strpos($_SERVER['REQUEST_URI'], '/ats-media-house/') === 0) {
    $ext = strtolower(pathinfo($target, PATHINFO_EXTENSION));
    $mimes = [
      'css'  => 'text/css; charset=UTF-8',
      'js'   => 'application/javascript; charset=UTF-8',
      'json' => 'application/json; charset=UTF-8',
      'jpg'  => 'image/jpeg',
      'jpeg' => 'image/jpeg',
      'png'  => 'image/png',
      'webp' => 'image/webp',
      'html' => 'text/html; charset=UTF-8',
    ];
    header('Content-Type: ' . ($mimes[$ext] ?? 'application/octet-stream'));
    readfile($target);
    return true;
  }
  return false;
}

http_response_code(404);
echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>404 | ATS media house</title><link rel="stylesheet" href="/css/style.css"></head><body><main class="section"><div class="frame"><h1>404 Not found</h1><p><a class="btn btn-outline" href="/">Return home</a></p></div></main></body></html>';
return true;
