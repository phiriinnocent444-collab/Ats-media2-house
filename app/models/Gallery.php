<?php
class Gallery {
  public static function all(): array { return db()->query('SELECT photo_id, title, filename, thumb, created_at FROM gallery ORDER BY created_at DESC')->fetchAll(); }
  public static function add(string $title, string $file, string $thumb, int $adminId): void {
    db()->prepare('INSERT INTO gallery (title, filename, thumb, uploaded_by) VALUES (?,?,?,?)')->execute([$title, $file, $thumb, $adminId]);
  }
  public static function remove(int $id): void {
    $st = db()->prepare('SELECT filename FROM gallery WHERE photo_id = ?'); $st->execute([$id]);
    if ($f = $st->fetchColumn()) { db()->prepare('DELETE FROM gallery WHERE photo_id = ?')->execute([$id]); ImageTool::remove($f); }
  }
}
