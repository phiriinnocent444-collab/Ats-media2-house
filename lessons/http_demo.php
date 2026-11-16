<?php
// UNIT 6.1 HTTP headers + UNIT 8.2 using HTTP in PHP
require __DIR__ . '/../app/core/HttpClient.php';
header('X-Powered-Lesson: unit-8');                          // we can send our own response header
echo '<h1>HTTP in PHP</h1><h2>This request</h2><pre>';
echo 'Method: ' . $_SERVER['REQUEST_METHOD'] . "\nURI: " . htmlspecialchars($_SERVER['REQUEST_URI']) . "\nUser-Agent: " . htmlspecialchars($_SERVER['HTTP_USER_AGENT'] ?? '') . "\n</pre>";

$self = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['SCRIPT_NAME'])) . '/api/health.php';
$r = HttpClient::get($self);                                 // PHP acting as an HTTP client (cURL)
echo '<h2>PHP calling our own API with cURL</h2><p>' . htmlspecialchars($self) . ' → status ' . $r['status'] . '</p><pre>' . htmlspecialchars($r['body'] ?: $r['error']) . '</pre>';
