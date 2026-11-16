<?php
// Bookings JSON API: full CRUD. Create is public (booking form); Read/Update/Delete are admin-only.
require_once __DIR__ . '/booking_lib.php';

handle(function () {
  $method = $_SERVER['REQUEST_METHOD'];
  $id = trim((string) ($_GET['id'] ?? ''));

  if ($method === 'GET') {                                   // READ
    require_admin();
    if ($id !== '') json_out(find_booking($id));
    json_out(db()->query(BOOKING_SQL . ' ORDER BY b.created_at DESC')->fetchAll());
  }
  if ($method === 'POST') {                                  // CREATE
    [$newId, $created] = create_booking(booking_input(json_in()));
    json_out(find_booking($newId), $created ? 201 : 200);
  }
  if ($method === 'PUT') {                                   // UPDATE
    require_admin();
    if ($id === '') throw new HttpError('id is required.', 400);
    update_booking($id, booking_input(json_in()));
    json_out(find_booking($id));
  }
  if ($method === 'DELETE') {                                // DELETE
    require_admin();
    if ($id === '') throw new HttpError('id is required.', 400);
    json_out(['id' => $id, 'result' => delete_booking($id)]);
  }
  throw new HttpError('Method not allowed.', 405);
});
