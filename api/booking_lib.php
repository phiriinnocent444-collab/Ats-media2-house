<?php
// Booking business logic shared by the JSON API (bookings.php) and the PHP MVC app (app/).
// Every multi-step change runs inside with_tx() so it is atomic.
require_once __DIR__ . '/helpers.php';

const BOOKING_SQL = 'SELECT b.booking_id AS id, c.full_name AS fullName, c.phone, c.email,
  s.code AS service, s.label AS serviceLabel, b.amount_due AS amount, b.event_date AS eventDate,
  b.notes, b.status, b.created_at AS createdAt
  FROM bookings b JOIN customers c ON c.customer_id = b.customer_id JOIN services s ON s.service_id = b.service_id';

function find_booking(string $id): array {
  $st = db()->prepare(BOOKING_SQL . ' WHERE b.booking_id = ?');
  $st->execute([$id]);
  $r = $st->fetch();
  if (!$r) throw new HttpError('Booking not found.', 404);
  return $r;
}

function booking_input(array $in): array {
  $name  = trim((string) ($in['fullName']  ?? ''));
  $phone = normalize_msisdn((string) ($in['phone'] ?? ''));
  $code  = trim((string) ($in['service']   ?? ''));
  $date  = trim((string) ($in['eventDate'] ?? ''));
  $email = trim((string) ($in['email']     ?? ''));
  $notes = trim((string) ($in['notes']     ?? ''));
  if ($name === '' || $phone === '' || $code === '' || $date === '') throw new HttpError('fullName, phone, service and eventDate are required.', 400);
  if (str_len($name) > 120) throw new HttpError('Name too long.', 400);
  if (!preg_match('/^\d{9}$/', $phone)) throw new HttpError('Invalid Zambian phone number.', 400);
  if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new HttpError('Invalid email.', 400);
  $d = DateTime::createFromFormat('Y-m-d', $date);
  if (!$d || $d->format('Y-m-d') !== $date) throw new HttpError('Invalid date.', 400);
  if ($date < date('Y-m-d')) throw new HttpError('Event date cannot be in the past.', 400);
  return ['name' => $name, 'phone' => $phone, 'service' => $code, 'date' => $date, 'email' => $email ?: null, 'notes' => $notes ?: null];
}

function service_by_code(PDO $pdo, string $code): array {
  $st = $pdo->prepare('SELECT service_id, price FROM services WHERE code = ? AND is_active = 1 LOCK IN SHARE MODE');
  $st->execute([$code]);
  $s = $st->fetch();
  if (!$s) throw new HttpError('Unknown or inactive service package.', 400);
  return $s;   // price comes from the DB, never from the client
}

function upsert_customer(PDO $pdo, string $name, string $phone, ?string $email): int {
  $pdo->prepare('INSERT INTO customers (full_name, phone, email) VALUES (?,?,?)
                 ON DUPLICATE KEY UPDATE full_name = VALUES(full_name), email = COALESCE(VALUES(email), email),
                 customer_id = LAST_INSERT_ID(customer_id)')->execute([$name, $phone, $email]);
  return (int) $pdo->lastInsertId();
}

function lock_booking(PDO $pdo, string $id): array {
  $st = $pdo->prepare('SELECT booking_id, status FROM bookings WHERE booking_id = ? FOR UPDATE');
  $st->execute([$id]);
  $b = $st->fetch();
  if (!$b) throw new HttpError('Booking not found.', 404);
  return $b;
}

function count_payments(PDO $pdo, string $id, bool $pendingOnly): int {
  $st = $pdo->prepare('SELECT COUNT(*) FROM payments WHERE booking_id = ?' . ($pendingOnly ? " AND status = 'PENDING'" : ''));
  $st->execute([$id]);
  return (int) $st->fetchColumn();
}

/** CREATE: customer upsert + booking insert in ONE transaction. Returns [booking_id, wasCreated]. */
function create_booking(array $f): array {
  return with_tx(function (PDO $pdo) use ($f) {
    $svc = service_by_code($pdo, $f['service']);
    $cid = upsert_customer($pdo, $f['name'], $f['phone'], $f['email']);
    $st = $pdo->prepare("SELECT booking_id, status FROM bookings
                         WHERE customer_id = ? AND service_id = ? AND event_date = ? AND status <> 'cancelled' FOR UPDATE");
    $st->execute([$cid, $svc['service_id'], $f['date']]);
    if ($dup = $st->fetch()) {
      if ($dup['status'] === 'paid') throw new HttpError('You already have a paid booking for this package on that date.', 409);
      return [$dup['booking_id'], false];          // idempotent retry: reuse the unpaid booking
    }
    $bid = new_id('BK');
    $pdo->prepare('INSERT INTO bookings (booking_id, customer_id, service_id, amount_due, event_date, notes) VALUES (?,?,?,?,?,?)')
        ->execute([$bid, $cid, $svc['service_id'], $svc['price'], $f['date'], $f['notes']]);
    return [$bid, true];
  });
}

/** UPDATE an unpaid booking (row-locked). */
function update_booking(string $id, array $f): void {
  with_tx(function (PDO $pdo) use ($id, $f) {
    $b = lock_booking($pdo, $id);
    if ($b['status'] !== 'pending_payment') throw new HttpError('Only unpaid bookings can be edited.', 409);
    if (count_payments($pdo, $id, true) > 0) throw new HttpError('A payment is in progress for this booking.', 409);
    $svc = service_by_code($pdo, $f['service']);
    $cid = upsert_customer($pdo, $f['name'], $f['phone'], $f['email']);
    $pdo->prepare('UPDATE bookings SET customer_id=?, service_id=?, amount_due=?, event_date=?, notes=? WHERE booking_id=?')
        ->execute([$cid, $svc['service_id'], $svc['price'], $f['date'], $f['notes'], $id]);
  });
}

/** DELETE: hard-delete if no payment history, otherwise mark cancelled. Returns 'deleted' | 'cancelled'. */
function delete_booking(string $id): string {
  return with_tx(function (PDO $pdo) use ($id) {
    $b = lock_booking($pdo, $id);
    if ($b['status'] === 'paid') throw new HttpError('Paid bookings cannot be deleted (refund first).', 409);
    if (count_payments($pdo, $id, true) > 0) throw new HttpError('A payment is in progress for this booking.', 409);
    if (count_payments($pdo, $id, false) > 0) {
      $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE booking_id = ?")->execute([$id]);
      return 'cancelled';
    }
    $pdo->prepare('DELETE FROM bookings WHERE booking_id = ?')->execute([$id]);
    return 'deleted';
  });
}
