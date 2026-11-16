<?php
// Service packages: full CRUD. GET (active only) is public; everything else is admin-only.
require_once __DIR__ . '/helpers.php';

function svc_input(array $in): array {
  $code  = strtolower(trim((string) ($in['code'] ?? '')));
  $label = trim((string) ($in['label'] ?? ''));
  $price = $in['price'] ?? null;
  if (!preg_match('/^[a-z0-9_]{2,20}$/', $code) || $label === '' || str_len($label) > 60 || !is_numeric($price) || $price <= 0 || $price > 1000000) {
    throw new HttpError('code (a-z, 0-9, _), label and a positive price are required.', 400);
  }
  $active = array_key_exists('isActive', $in) ? (int) (bool) $in['isActive'] : 1;
  return [$code, $label, round((float) $price, 2), $active];
}

handle(function () {
  $method = $_SERVER['REQUEST_METHOD'];
  $id = (int) ($_GET['id'] ?? 0);
  $cols = 'service_id AS id, code, label, price, is_active AS isActive';

  if ($method === 'GET') {
    if (isset($_GET['all'])) { require_admin(); json_out(db()->query("SELECT $cols FROM services ORDER BY service_id")->fetchAll()); }
    json_out(db()->query("SELECT $cols FROM services WHERE is_active = 1 ORDER BY price DESC")->fetchAll());
  }

  require_admin();

  if ($method === 'POST') {
    [$code, $label, $price, $active] = svc_input(json_in());
    db()->prepare('INSERT INTO services (code, label, price, is_active) VALUES (?,?,?,?)')->execute([$code, $label, $price, $active]);
    json_out(['id' => (int) db()->lastInsertId(), 'code' => $code, 'label' => $label, 'price' => $price, 'isActive' => $active], 201);
  }

  if ($id <= 0) throw new HttpError('id is required.', 400);

  if ($method === 'PUT') {
    [$code, $label, $price, $active] = svc_input(json_in());
    $st = db()->prepare('UPDATE services SET code=?, label=?, price=?, is_active=? WHERE service_id=?');
    $st->execute([$code, $label, $price, $active, $id]);
    json_out(['id' => $id, 'code' => $code, 'label' => $label, 'price' => $price, 'isActive' => $active]);
  }

  if ($method === 'DELETE') {
    $result = with_tx(function (PDO $pdo) use ($id) {
      $st = $pdo->prepare('SELECT service_id FROM services WHERE service_id = ? FOR UPDATE');
      $st->execute([$id]);
      if (!$st->fetch()) throw new HttpError('Service not found.', 404);
      $n = $pdo->prepare('SELECT COUNT(*) FROM bookings WHERE service_id = ?');
      $n->execute([$id]);
      if ((int) $n->fetchColumn() > 0) {                    // referenced: retire, don't destroy history
        $pdo->prepare('UPDATE services SET is_active = 0 WHERE service_id = ?')->execute([$id]);
        return 'deactivated';
      }
      $pdo->prepare('DELETE FROM services WHERE service_id = ?')->execute([$id]);
      return 'deleted';
    });
    json_out(['id' => $id, 'result' => $result]);
  }

  throw new HttpError('Method not allowed.', 405);
});
