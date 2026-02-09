<?php
declare(strict_types=1);

/**
 * thumb.php
 * Esempio: /thumb.php?src=assets/img/portfolio/tutto.png&w=1400&q=75
 */

$baseDir = realpath(__DIR__); // root progetto (dove sta thumb.php)
$cacheDir = $baseDir . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'thumbs';

$srcRel = (string)($_GET['src'] ?? '');
$wReq   = (int)($_GET['w'] ?? 1400);
$qReq   = (int)($_GET['q'] ?? 75);

// limiti sicurezza
$w = max(200, min(2400, $wReq));
$q = max(40,  min(92,   $qReq));

// valida src
$srcRel = ltrim($srcRel, "/\\");
if ($srcRel === '' || str_contains($srcRel, "\0")) {
  http_response_code(400);
  exit('Bad src');
}

// solo immagini in assets/img (evita path traversal)
if (!preg_match('~^assets/img/~i', $srcRel)) {
  http_response_code(403);
  exit('Forbidden');
}

$srcPath = realpath($baseDir . DIRECTORY_SEPARATOR . $srcRel);
if (!$srcPath || !is_file($srcPath)) {
  http_response_code(404);
  exit('Not found');
}

// impedisce uscita dalla root
if (strpos($srcPath, $baseDir) !== 0) {
  http_response_code(403);
  exit('Forbidden');
}

// hash cache in base a file + mtime + params
$mtime = (int)filemtime($srcPath);
$cacheKey = sha1($srcRel . '|' . $mtime . '|w=' . $w . '|q=' . $q);
$cachePath = $cacheDir . DIRECTORY_SEPARATOR . $cacheKey . '.webp';

// se già in cache, servi
if (is_file($cachePath) && filesize($cachePath) > 2048) {
  serveWebp($cachePath);
  exit;
}

// carica sorgente
$info = @getimagesize($srcPath);
if (!$info) {
  http_response_code(415);
  exit('Unsupported image');
}

[$origW, $origH] = $info;
$mime = $info['mime'] ?? '';

$srcImg = null;
switch ($mime) {
  case 'image/jpeg':
    $srcImg = @imagecreatefromjpeg($srcPath);
    break;
  case 'image/png':
    $srcImg = @imagecreatefrompng($srcPath);
    break;
  case 'image/webp':
    if (function_exists('imagecreatefromwebp')) {
      $srcImg = @imagecreatefromwebp($srcPath);
    }
    break;
  default:
    http_response_code(415);
    exit('Unsupported mime');
}
if (!$srcImg) {
  http_response_code(500);
  exit('Decode error');
}

// calcolo nuove dimensioni (ridimensiono per larghezza)
if ($origW <= $w) {
  $newW = $origW;
  $newH = $origH;
} else {
  $ratio = $w / $origW;
  $newW = $w;
  $newH = (int)round($origH * $ratio);
}

// crea canvas
$dstImg = imagecreatetruecolor($newW, $newH);

// gestisci trasparenza (PNG/WebP)
imagealphablending($dstImg, false);
imagesavealpha($dstImg, true);
$transparent = imagecolorallocatealpha($dstImg, 0, 0, 0, 127);
imagefilledrectangle($dstImg, 0, 0, $newW, $newH, $transparent);

// resample
imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $newW, $newH, $origW, $origH);

// crea cache dir
if (!is_dir($cacheDir)) {
  @mkdir($cacheDir, 0775, true);
}

// salva in WebP (serve GD con webp attivo)
if (!function_exists('imagewebp')) {
  imagedestroy($srcImg);
  imagedestroy($dstImg);
  http_response_code(500);
  exit('GD WebP not available');
}

@imagewebp($dstImg, $cachePath, $q);

imagedestroy($srcImg);
imagedestroy($dstImg);

// servi file generato
if (!is_file($cachePath) || filesize($cachePath) < 1024) {
  http_response_code(500);
  exit('Encode error');
}

serveWebp($cachePath);
exit;

/* ------------------------- */
function serveWebp(string $path): void {
  $etag = '"' . md5_file($path) . '"';
  header('Content-Type: image/webp');
  header('Cache-Control: public, max-age=31536000, immutable');
  header('ETag: ' . $etag);

  // 304
  if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
    http_response_code(304);
    return;
  }

  header('Content-Length: ' . filesize($path));
  readfile($path);
}
