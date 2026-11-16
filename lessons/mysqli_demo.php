<?php
// UNIT 5: PHP interface to MySQL (the classic mysqli extension).  The app itself uses PDO (api/helpers.php) – same ideas.
require __DIR__ . '/../api/config.php';                       // returns the config array
$c = (require __DIR__ . '/../api/config.php')['db'];

// 5.2 Connecting to MySQL
$db = new mysqli($c['host'], $c['user'], $c['pass'], $c['name']);
if ($db->connect_errno) die('Connection failed: ' . htmlspecialchars($db->connect_error));
$db->set_charset('utf8mb4');

// 5.3 Queries (a prepared statement protects against SQL injection)
$min = 1000;
$stmt = $db->prepare('SELECT label, price FROM services WHERE price >= ? AND is_active = 1 ORDER BY price DESC');
$stmt->bind_param('d', $min);
$stmt->execute();
$result = $stmt->get_result();
echo '<h1>Packages from K' . $min . '</h1><ul>';
while ($row = $result->fetch_assoc()) echo '<li>' . htmlspecialchars($row['label']) . ' – K' . number_format($row['price']) . '</li>';
echo '</ul>';

// 5.4 Updating records (affected_rows tells you if anything changed)
$stmt = $db->prepare('UPDATE services SET price = price WHERE code = ?');   // harmless demo update
$code = 'wedding'; $stmt->bind_param('s', $code); $stmt->execute();
echo '<p>Rows matched by UPDATE: ' . $stmt->affected_rows . '</p>';
$db->close();
