<?php
class AdminUser {
  public static function find(string $username): ?array {
    $st = db()->prepare('SELECT admin_id, username, password_hash FROM admin_users WHERE username = ?');
    $st->execute([$username]);
    return $st->fetch() ?: null;
  }
}
