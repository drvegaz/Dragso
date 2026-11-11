<?php
// Bilduppladdning: kontrollerar filtyp, roterar efter EXIF, skalar ner och sparar som WebP (eller JPEG).
declare(strict_types=1);

const UPLOAD_MAX_BYTES = 20 * 1024 * 1024;
const UPLOAD_MAX_WIDTH = 2400;

function handle_upload(?array $file): string {
  return "uploads/cms/" . save_image($file, UPLOAD_DIR);
}

// Sparar en uppladdad bild i $dir och returnerar filnamnet.
function save_image(?array $file, string $dir, int $maxWidth = UPLOAD_MAX_WIDTH): string {
  if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name']))
    throw new InvalidArgumentException('Ingen bild kom fram. Försök igen.');
  if ($file['size'] > UPLOAD_MAX_BYTES) throw new InvalidArgumentException('Bilden är för stor (max 20 MB).');

  $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
  $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
  if (!$ext) throw new InvalidArgumentException('Endast JPG, PNG och WebP stöds.');
  $size = getimagesize($file['tmp_name']);
  if (!$size || $size[0] < 1 || $size[0] * $size[1] > 60_000_000) throw new InvalidArgumentException('Bilden kunde inte läsas.');

  if (!is_dir($dir)) mkdir($dir, 0775, true);
  $base = date('Ymd') . '-' . bin2hex(random_bytes(6));

  if (!extension_loaded('gd')) {
    $name = "$base.$ext";
    if (!move_uploaded_file($file['tmp_name'], $dir . "/$name")) throw new RuntimeException('move failed');
    return $name;
  }

  @ini_set('memory_limit', '512M');
  $img = match ($mime) {
    'image/jpeg' => imagecreatefromjpeg($file['tmp_name']),
    'image/png' => imagecreatefrompng($file['tmp_name']),
    'image/webp' => imagecreatefromwebp($file['tmp_name']),
  };
  if (!$img) throw new InvalidArgumentException('Bilden kunde inte läsas.');

  if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
    $o = (@exif_read_data($file['tmp_name']) ?: [])['Orientation'] ?? 1;
    $rot = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
    if ($rot) $img = imagerotate($img, $rot, 0);
  }

  $w = imagesx($img);
  if ($w > $maxWidth) $img = imagescale($img, $maxWidth, -1, IMG_BICUBIC);

  if (function_exists('imagewebp')) {
    imagepalettetotruecolor($img);
    imagealphablending($img, false);
    imagesavealpha($img, true);
    $name = "$base.webp";
    $ok = imagewebp($img, $dir . "/$name", 82);
  } else {
    $name = "$base.jpg";
    $ok = imagejpeg($img, $dir . "/$name", 85);
  }
  if (!$ok) throw new RuntimeException('Kunde inte spara bilden.');
  return $name;
}
