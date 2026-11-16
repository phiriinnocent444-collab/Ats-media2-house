<?php
// Polls Airtel (or settles UAT sandbox prompt) and updates payment + booking in ONE ACID transaction.
require_once __DIR__ . '/airtel.php';

handle(function () {
  $id = trim((string) ($_GET['id'] ?? ''));
  $st = db()->prepare('SELECT status, airtel_response, TIMESTAMPDIFF(SECOND, created_at, NOW()) AS age_sec FROM payments WHERE transaction_id = ?');
  $st->execute([$id]);
  $p = $st->fetch();
  if (!$p) throw new HttpError('Unknown transaction id.', 404);
  if ($p['status'] !== 'PENDING') json_out(['transactionId' => $id, 'status' => $p['status']]);

  global $CONFIG;
  $cid = (string) ($CONFIG['airtel']['client_id'] ?? '');
  $sec = (string) ($CONFIG['airtel']['client_secret'] ?? '');
  $isLive = $cid !== '' && $sec !== '' && $cid !== 'your_client_id_here' && $sec !== 'your_client_secret_here';

  $new = 'PENDING';
  if (!$isLive) {
    if ((int) $p['age_sec'] >= 1) {
      $new = 'SUCCESS';
    }
  } else {
    try {
      $r   = airtel_check_status($id);
      $raw = (string) ($r['data']['transaction']['status'] ?? '');
      $new = ['TS' => 'SUCCESS', 'SUCCESS' => 'SUCCESS', 'TF' => 'FAILED', 'TE' => 'FAILED', 'FAILED' => 'FAILED'][$raw] ?? 'PENDING';
    } catch (Throwable $e) {
      json_out(['transactionId' => $id, 'status' => 'PENDING']);
    }
  }

  if ($new === 'PENDING') {
    json_out(['transactionId' => $id, 'status' => 'PENDING']);
  }

  $final = with_tx(function (PDO $pdo) use ($id, $new) {
    $s = $pdo->prepare('SELECT booking_id, status FROM payments WHERE transaction_id = ? FOR UPDATE');
    $s->execute([$id]);
    $pay = $s->fetch();
    if ($pay['status'] !== 'PENDING') return $pay['status'];
    $pdo->prepare('UPDATE payments SET status = ?, last_checked = NOW() WHERE transaction_id = ?')->execute([$new, $id]);
    if ($new === 'SUCCESS') {
      $pdo->prepare("UPDATE bookings SET status = 'paid' WHERE booking_id = ? AND status = 'pending_payment'")->execute([$pay['booking_id']]);
    }
    return $new;
  });

  json_out(['transactionId' => $id, 'status' => $final]);
});
