<?php
require_once __DIR__ . '/helpers.php';
try {
  $bad = db()->query("SELECT table_name FROM information_schema.tables
                      WHERE table_schema = DATABASE() AND engine <> 'InnoDB'")->fetchAll(PDO::FETCH_COLUMN);
  if ($bad) json_out(['status' => 'error', 'detail' => 'Non-InnoDB tables (no transactions): ' . implode(', ', $bad)], 500);
  json_out(['status' => 'ok', 'service' => 'ATS media house backend', 'database' => 'connected', 'engine' => 'InnoDB']);
} catch (Throwable $e) {
  json_out(['status' => 'error', 'database' => 'not connected', 'detail' => $e->getMessage()], 500);
}
