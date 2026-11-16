<?php
// Starts an Airtel Money collection (live API when credentials configured, or UAT sandbox simulation).
require_once __DIR__ . '/airtel.php';

handle(function () {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new HttpError('Method not allowed.', 405);
  $in        = json_in();
  $bookingId = trim((string) ($in['bookingId'] ?? ''));
  $msisdn    = normalize_msisdn((string) ($in['phone'] ?? ''));
  if ($bookingId === '' || $msisdn === '') throw new HttpError('bookingId and phone are required.', 400);
  if (!preg_match('/^\d{9}$/', $msisdn)) throw new HttpError('Invalid Airtel number.', 400);

  [$amount, $txnId] = with_tx(function (PDO $pdo) use ($bookingId, $msisdn) {
    $st = $pdo->prepare('SELECT amount_due, status FROM bookings WHERE booking_id = ? FOR UPDATE');
    $st->execute([$bookingId]);
    $b = $st->fetch();
    if (!$b) throw new HttpError('Unknown booking.', 404);
    if ($b['status'] !== 'pending_payment') throw new HttpError('This booking is not awaiting payment.', 409);

    $p = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE booking_id = ? AND status = 'PENDING' AND created_at > NOW() - INTERVAL 5 MINUTE");
    $p->execute([$bookingId]);
    if ((int) $p->fetchColumn() > 0) throw new HttpError('A payment prompt was just sent. Please wait for it to finish.', 409);

    $txn = new_id('TXN');
    $pdo->prepare("INSERT INTO payments (transaction_id, booking_id, msisdn, amount, status) VALUES (?,?,?,?, 'PENDING')")
        ->execute([$txn, $bookingId, $msisdn, $b['amount_due']]);
    return [(float) $b['amount_due'], $txn];
  });

  global $CONFIG;
  $cid = (string) ($CONFIG['airtel']['client_id'] ?? '');
  $sec = (string) ($CONFIG['airtel']['client_secret'] ?? '');
  $isLive = $cid !== '' && $sec !== '' && $cid !== 'your_client_id_here' && $sec !== 'your_client_secret_here';

  if (!$isLive) {
    db()->prepare('UPDATE payments SET airtel_response = ? WHERE transaction_id = ?')
      ->execute([json_encode(['mode' => 'uat_sandbox', 'msisdn' => $msisdn]), $txnId]);
    json_out([
      'transactionId' => $txnId,
      'status'        => 'PENDING',
      'message'       => "UAT payment prompt sent to 0{$msisdn}. Waiting for PIN confirmation.",
    ]);
  }

  $resp = null; $fail = null;
  try {
    $resp = airtel_request_payment($msisdn, $amount, $txnId);
    if (($resp['status']['success'] ?? true) === false) {
      throw new RuntimeException('Airtel rejected the request: ' . json_encode($resp['status']));
    }
  } catch (Throwable $e) {
    $fail = $e->getMessage();
  }

  if ($fail !== null) {
    db()->prepare("UPDATE payments SET status = 'FAILED', error = ? WHERE transaction_id = ?")->execute([$fail, $txnId]);
    json_out([
      'transactionId' => $txnId,
      'status'        => 'FAILED',
      'error'         => 'Could not reach Airtel Money. Check client_id / client_secret in api/config.php.',
    ], 502);
  }

  db()->prepare('UPDATE payments SET airtel_response = ? WHERE transaction_id = ?')->execute([json_encode($resp), $txnId]);
  json_out([
    'transactionId' => $txnId,
    'status'        => 'PENDING',
    'message'       => "A payment prompt has been sent to the customer's Airtel Money line.",
  ]);
});
