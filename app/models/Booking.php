<?php
class Booking {
  /** READ with search: FULLTEXT (notes) + LIKE (name) + exact id, status, and upcoming schedule filter. */
  public static function all(string $q = '', string $status = '', string $timing = ''): array {
    $sql = BOOKING_SQL; $w = []; $p = [];
    if ($q !== '') {
      $ft = self::booleanQuery($q);
      $w[] = '(c.full_name LIKE ? OR b.booking_id = ?' . ($ft ? ' OR MATCH(b.notes) AGAINST (? IN BOOLEAN MODE)' : '') . ')';
      array_push($p, "%$q%", $q); if ($ft) $p[] = $ft;
    }
    if (in_array($status, ['pending_payment', 'paid', 'cancelled'], true)) { $w[] = 'b.status = ?'; $p[] = $status; }
    if ($timing === 'upcoming') {
      $w[] = "b.event_date >= CURDATE() AND b.status <> 'cancelled'";
    }
    if ($w) $sql .= ' WHERE ' . implode(' AND ', $w);
    $order = $timing === 'upcoming' ? 'b.event_date ASC, b.created_at DESC' : 'b.created_at DESC';
    $st = db()->prepare($sql . " ORDER BY $order"); $st->execute($p);
    return $st->fetchAll();
  }

  /** Customer lookup by Booking Ref (BK...) or phone number (097...). */
  public static function byLookup(string $query): array {
    $q = trim($query);
    if ($q === '') return [];
    $msisdn = normalize_msisdn($q);
    $isPhone = preg_match('/^\d{9}$/', $msisdn) === 1;
    if ($isPhone) {
      $st = db()->prepare(BOOKING_SQL . ' WHERE c.phone = ? ORDER BY b.created_at DESC LIMIT 20');
      $st->execute([$msisdn]);
    } else {
      $st = db()->prepare(BOOKING_SQL . ' WHERE b.booking_id = ? LIMIT 1');
      $st->execute([$q]);
    }
    $rows = $st->fetchAll();
    if (!$rows) return [];
    $txSt = db()->prepare('SELECT transaction_id, msisdn, amount, status, error, created_at FROM payments WHERE booking_id = ? ORDER BY created_at DESC');
    foreach ($rows as &$r) {
      $txSt->execute([$r['id']]);
      $r['payments'] = $txSt->fetchAll();
    }
    return $rows;
  }

  /** Unit 8.4 regex: "wedding venue" -> "+wedding* +venue*" (all words, prefix match, ignores symbols). */
  public static function booleanQuery(string $q): string {
    $words = array_filter(preg_split('/[^\p{L}\p{N}]+/u', $q, -1, PREG_SPLIT_NO_EMPTY), fn($w) => str_len($w) >= 3);
    return implode(' ', array_map(fn($w) => '+' . $w . '*', $words));
  }

  public static function create(array $in): array { [$id, $new] = create_booking(booking_input($in)); return find_booking($id); }

  /** Customer self-service ACID payment initiation + settlement for a pending booking. */
  public static function payNow(string $bookingId): string {
    return with_tx(function (PDO $pdo) use ($bookingId) {
      $b = lock_booking($pdo, $bookingId);
      if ($b['status'] === 'paid') throw new HttpError('This booking is already paid.', 409);
      if ($b['status'] === 'cancelled') throw new HttpError('Cancelled bookings cannot be paid.', 409);
      $st = $pdo->prepare('SELECT c.phone, b.amount_due FROM bookings b JOIN customers c ON c.customer_id = b.customer_id WHERE b.booking_id = ?');
      $st->execute([$bookingId]);
      $r = $st->fetch();
      $txnId = new_id('TXN');
      $pdo->prepare("INSERT INTO payments (transaction_id, booking_id, msisdn, amount, status, error, last_checked) VALUES (?,?,?,?, 'SUCCESS', 'UAT instant confirm', NOW())")
          ->execute([$txnId, $bookingId, $r['phone'], $r['amount_due']]);
      $pdo->prepare("UPDATE bookings SET status = 'paid' WHERE booking_id = ?")->execute([$bookingId]);
      return $txnId;
    });
  }

  /** UPDATE status: admin confirms a manual (e.g. WhatsApp) payment. Payment row + booking change are ONE transaction. */
  public static function markPaid(string $id): void {
    with_tx(function (PDO $pdo) use ($id) {
      $b = lock_booking($pdo, $id);
      if ($b['status'] !== 'pending_payment') throw new HttpError('Only unpaid bookings can be marked paid.', 409);
      if (count_payments($pdo, $id, true) > 0) throw new HttpError('An Airtel payment is still pending. Settle it from Transactions.', 409);
      $st = $pdo->prepare('SELECT c.phone, b.amount_due FROM bookings b JOIN customers c ON c.customer_id = b.customer_id WHERE b.booking_id = ?');
      $st->execute([$id]); $r = $st->fetch();
      $pdo->prepare("INSERT INTO payments (transaction_id, booking_id, msisdn, amount, status, error) VALUES (?,?,?,?, 'SUCCESS', 'Manual confirmation by admin')")
          ->execute([new_id('MAN'), $id, $r['phone'], $r['amount_due']]);
      $pdo->prepare("UPDATE bookings SET status = 'paid' WHERE booking_id = ?")->execute([$id]);
    });
  }

  public static function cancel(string $id): void {
    with_tx(function (PDO $pdo) use ($id) {
      $b = lock_booking($pdo, $id);
      if ($b['status'] === 'paid') throw new HttpError('Paid bookings cannot be cancelled here (refund first).', 409);
      $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE booking_id = ?")->execute([$id]);
    });
  }

  /** Admin Transactions Ledger (ACID audit log). */
  public static function transactions(int $limit = 100): array {
    $st = db()->prepare("SELECT p.transaction_id, p.booking_id, p.msisdn, p.amount, p.status, p.error, p.created_at, p.last_checked,
      c.full_name, s.label AS service_label, b.status AS booking_status, b.event_date
      FROM payments p
      JOIN bookings b ON b.booking_id = p.booking_id
      JOIN customers c ON c.customer_id = b.customer_id
      JOIN services s ON s.service_id = b.service_id
      ORDER BY p.created_at DESC LIMIT $limit");
    $st->execute();
    return $st->fetchAll();
  }

  /** Settles a PENDING transaction (SUCCESS -> marks booking paid; FAILED -> leaves booking pending_payment) in ONE transaction. */
  public static function settlePayment(string $txnId, string $outcome): string {
    if (!in_array($outcome, ['SUCCESS', 'FAILED'], true)) throw new HttpError('Invalid outcome.', 400);
    return with_tx(function (PDO $pdo) use ($txnId, $outcome) {
      $st = $pdo->prepare('SELECT booking_id, status FROM payments WHERE transaction_id = ? FOR UPDATE');
      $st->execute([$txnId]);
      $pay = $st->fetch();
      if (!$pay) throw new HttpError('Transaction not found.', 404);
      if ($pay['status'] !== 'PENDING') throw new HttpError('Only pending transactions can be settled.', 409);
      $b = lock_booking($pdo, $pay['booking_id']);
      $pdo->prepare('UPDATE payments SET status = ?, last_checked = NOW(), error = ? WHERE transaction_id = ?')
          ->execute([$outcome, $outcome === 'SUCCESS' ? 'Confirmed in Admin Transactions' : 'Marked failed in Admin Transactions', $txnId]);
      if ($outcome === 'SUCCESS' && $b['status'] === 'pending_payment') {
        $pdo->prepare("UPDATE bookings SET status = 'paid' WHERE booking_id = ?")->execute([$pay['booking_id']]);
      }
      return $outcome;
    });
  }

  public static function stats(): array {
    return db()->query("SELECT COUNT(*) AS total,
        SUM(status='pending_payment') AS pending, SUM(status='paid') AS paid, SUM(status='cancelled') AS cancelled,
        COALESCE(SUM(CASE WHEN status='paid' THEN amount_due END),0) AS revenue
        FROM bookings")->fetch();
  }

  public static function upcoming(int $limit = 5): array {
    $st = db()->prepare(BOOKING_SQL . " WHERE b.status <> 'cancelled' AND b.event_date >= CURDATE() ORDER BY b.event_date LIMIT $limit");
    $st->execute(); return $st->fetchAll();
  }

  // JOIN + GROUP BY reports
  public static function byService(): array {
    return db()->query("SELECT s.label, COUNT(b.booking_id) AS bookings,
        COALESCE(SUM(CASE WHEN b.status='paid' THEN b.amount_due END),0) AS revenue
        FROM services s LEFT JOIN bookings b ON b.service_id = s.service_id
        GROUP BY s.service_id, s.label ORDER BY revenue DESC, bookings DESC")->fetchAll();
  }
  public static function byMonth(): array {
    return db()->query("SELECT DATE_FORMAT(event_date,'%Y-%m') AS month, COUNT(*) AS bookings, SUM(amount_due) AS value
        FROM bookings WHERE status <> 'cancelled' GROUP BY month HAVING COUNT(*) >= 1 ORDER BY month DESC LIMIT 12")->fetchAll();
  }
  public static function topCustomers(): array {
    return db()->query("SELECT c.full_name, c.phone, COUNT(*) AS bookings, SUM(b.amount_due) AS value
        FROM customers c JOIN bookings b ON b.customer_id = c.customer_id WHERE b.status <> 'cancelled'
        GROUP BY c.customer_id, c.full_name, c.phone ORDER BY value DESC LIMIT 5")->fetchAll();
  }
}
