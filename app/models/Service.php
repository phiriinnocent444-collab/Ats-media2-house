<?php
class Service {
  public static function active(): array { return db()->query('SELECT service_id AS id, code, label, price FROM services WHERE is_active = 1 ORDER BY price DESC')->fetchAll(); }
  public static function all(): array    { return db()->query('SELECT service_id AS id, code, label, price, is_active FROM services ORDER BY service_id')->fetchAll(); }

  /** CREATE (id = null) or UPDATE inside ACID transaction. Returns an error string or null. */
  public static function save(?int $id, array $in): ?string {
    $code = strtolower(trim((string) ($in['code'] ?? ''))); $label = trim((string) ($in['label'] ?? '')); $price = $in['price'] ?? '';
    if (!preg_match('/^[a-z0-9_]{2,20}$/', $code)) return 'Code: 2-20 characters, a-z, 0-9 or _.';
    if ($label === '' || str_len($label) > 60)   return 'Label is required (max 60 characters).';
    if (!is_numeric($price) || $price <= 0)        return 'Price must be a positive number.';
    $active = empty($in['is_active']) ? 0 : 1;
    try {
      with_tx(function (PDO $pdo) use ($id, $code, $label, $price, $active) {
        if ($id) {
          $pdo->prepare('UPDATE services SET code=?, label=?, price=?, is_active=? WHERE service_id=?')
              ->execute([$code, $label, round((float) $price, 2), $active, $id]);
        } else {
          $pdo->prepare('INSERT INTO services (code,label,price,is_active) VALUES (?,?,?,?)')
              ->execute([$code, $label, round((float) $price, 2), $active]);
        }
      });
    } catch (PDOException $e) {
      return ((int) ($e->errorInfo[1] ?? 0)) === 1062 ? 'That code already exists.' : 'Database error.';
    }
    return null;
  }

  /** DELETE inside ACID transaction: hard-delete if unused, deactivate if referenced by bookings. */
  public static function delete(int $id): string {
    return with_tx(function (PDO $pdo) use ($id) {
      $st = $pdo->prepare('SELECT service_id FROM services WHERE service_id = ? FOR UPDATE');
      $st->execute([$id]);
      if (!$st->fetch()) throw new HttpError('Service package not found.', 404);
      $cnt = $pdo->prepare('SELECT COUNT(*) FROM bookings WHERE service_id = ?');
      $cnt->execute([$id]);
      if ((int) $cnt->fetchColumn() > 0) {
        $pdo->prepare('UPDATE services SET is_active = 0 WHERE service_id = ?')->execute([$id]);
        return 'deactivated (has existing bookings)';
      }
      $pdo->prepare('DELETE FROM services WHERE service_id = ?')->execute([$id]);
      return 'deleted';
    });
  }
}
