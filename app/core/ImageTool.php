<?php
// Unit 6.3 file uploads, 6.4 image management, 8.3 GD image processing
class ImageTool {
  const MAX_BYTES = 3145728;   // 3 MB
  private static array $types = ['image/jpeg' => 'imagecreatefromjpeg', 'image/png' => 'imagecreatefrompng', 'image/webp' => 'imagecreatefromwebp'];

  /** Validates an uploaded file, saves a watermarked full-size copy + a thumbnail. Returns [filename, thumbname]. */
  public static function process(array $f): array {
    if (!function_exists('imagejpeg')) throw new RuntimeException('PHP GD extension is not enabled. In XAMPP, open php/php.ini, uncomment extension=gd, and restart Apache.');
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new RuntimeException('Upload failed (error code ' . ($f['error'] ?? '?') . ').');
    if ($f['size'] > self::MAX_BYTES) throw new RuntimeException('Image is larger than 3 MB.');
    if (!is_uploaded_file($f['tmp_name'])) throw new RuntimeException('Not an uploaded file.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);          // real type, not the file extension
    if (!isset(self::$types[$mime]) || !getimagesize($f['tmp_name'])) throw new RuntimeException('Only JPG, PNG or WEBP images are allowed.');

    $src = (self::$types[$mime])($f['tmp_name']);
    $name = bin2hex(random_bytes(8)) . '.jpg';                              // random name: no overwrite, no path tricks
    $base = __DIR__ . '/../../uploads/';
    foreach (['gallery', 'thumbs'] as $dir) {
      if (!is_dir($base . $dir)) mkdir($base . $dir, 0755, true);
    }
    $full = self::resize($src, 1600);
    $col = imagecolorallocate($full, 255, 255, 255);
    imagestring($full, 4, 12, imagesy($full) - 24, 'ATS media house', $col); // GD watermark
    imagejpeg($full, $base . 'gallery/' . $name, 85);
    imagejpeg(self::resize($src, 400), $base . 'thumbs/' . $name, 80);
    return [$name, $name];
  }

  private static function resize(GdImage $img, int $maxW): GdImage {
    return imagesx($img) > $maxW ? imagescale($img, $maxW) : $img;           // keeps aspect ratio
  }

  public static function remove(string $name): void {
    foreach (['gallery', 'thumbs'] as $d) { $p = __DIR__ . "/../../uploads/$d/" . basename($name); if (is_file($p)) unlink($p); }
  }
}
