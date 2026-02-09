<?php
declare(strict_types=1);

/**
 * thumb.php
 * Esempio: /thumb.php?src=assets/img/portfolio/newsatletica.png&w=1400&q=75
 */

$baseDir  = realpath(__DIR__);
$cacheDir = $baseDir . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'thumbs';

$srcRel = (string)($_GET['src'] ?? '');
$wReq   = (int)($_GET['w'] ?? 1400);
$qReq   = (int)($_GET['q'] ?? 75);

$w = max(200, min(2400, $wReq));
$q = max(40,  min(92,   $qReq));

$srcRel = ltrim($srcRel, "/\\");
if ($srcRel === '' || strpos($srcRel, "\0") !== false) {
  http_response_code(400);
  exit('Bad src');
}

// limita alle immagini dentro assets/img (sicurezza)
if (!preg_match('~^assets/img/~i', $srcRel)) {
  http_response_code(403);
  exit('Forbidden');
}

$srcPath = realpath($baseDir . DIRECTORY_SEPARATOR . $srcRel);
if (!$srcPath || !is_file($srcPath)) {
  http_response_code(404);
  exit('Not found');
}
if (strpos($srcPath, $baseDir) !== 0) {
  http_response_code(403);
  exit('Forbidden');
}

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
    exit('Unsupported mime: ' . $mime);
}
if (!$srcImg) {
  http_response_code(500);
  exit('Decode error (GD)');
}

if ($origW <= $w) {
  $newW = $origW;
  $newH = $origH;
} else {
  $ratio = $w / $origW;
  $newW  = $w;
  $newH  = (int)round($origH * $ratio);
}

$dstImg = imagecreatetruecolor($newW, $newH);

// Gestione trasparenza: se PNG mantieni alpha, se no sfondo bianco
if ($mime === 'image/png' || $mime === 'image/webp') {
  imagealphablending($dstImg, false);
  imagesavealpha($dstImg, true);
  $transparent = imagecolorallocatealpha($dstImg, 0, 0, 0, 127);
  imagefilledrectangle($dstImg, 0, 0, $newW, $newH, $transparent);
} else {
  $white = imagecolorallocate($dstImg, 255, 255, 255);
  imagefilledrectangle($dstImg, 0, 0, $newW, $newH, $white);
}

imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $newW, $newH, $origW, $origH);

// prepara cache dir
if (!is_dir($cacheDir)) {
  @mkdir($cacheDir, 0775, true);
}
if (!is_dir($cacheDir) || !is_writable($cacheDir)) {
  // niente cache: servi al volo senza salvare
  serveDynamic($dstImg, $q);
  imagedestroy($srcImg);
  imagedestroy($dstImg);
  exit;
}

// cache key include mtime
$mtime = (int)filemtime($srcPath);
$cacheKey = sha1($srcRel . '|' . $mtime . '|w=' . $w . '|q=' . $q);

// se supporta webp -> usa webp, altrimenti jpeg
$canWebp = function_exists('imagewebp');

$ext = $canWebp ? 'webp' : 'jpg';
$cachePath = $cacheDir . DIRECTORY_SEPARATOR . $cacheKey . '.' . $ext;

// se già in cache
if (is_file($cachePath) && filesize($cachePath) > 2048) {
  serveFile($cachePath, $ext);
  imagedestroy($srcImg);
  imagedestroy($dstImg);
  exit;
}

// salva cache
$ok = false;
if ($canWebp) {
  $ok = @imagewebp($dstImg, $cachePath, $q);
  // se fallisce, fallback jpeg
  if (!$ok) {
    $ext = 'jpg';
    $cachePath = $cacheDir . DIRECTORY_SEPARATOR . $cacheKey . '.jpg';
    $ok = @imagejpeg($dstImg, $cachePath, $q);
  }
} else {
  $ok = @imagejpeg($dstImg, $cachePath, $q);
}

imagedestroy($srcImg);
imagedestroy($dstImg);

if (!$ok || !is_file($cachePath) || filesize($cachePath) < 1024) {
  http_response_code(500);
  exit('Encode error (write/cache)');
}

serveFile($cachePath, $ext);
exit;

/* ------------------------- */

function serveDynamic($gdImg, int $q): void {
  // Se non posso scrivere cache, servo direttamente
  if (function_exists('imagewebp')) {
    header('Content-Type: image/webp');
    header('Cache-Control: no-store');
    imagewebp($gdImg, null, $q);
  } else {
    header('Content-Type: image/jpeg');
    header('Cache-Control: no-store');
    imagejpeg($gdImg, null, $q);
  }
}

function serveFile(string $path, string $ext): void {
  $mime = ($ext === 'webp') ? 'image/webp' : 'image/jpeg';

  $etag = '"' . md5_file($path) . '"';
  header('Content-Type: ' . $mime);
  header('Cache-Control: public, max-age=31536000, immutable');
  header('ETag: ' . $etag);

  if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
    http_response_code(304);
    return;
  }

  header('Content-Length: ' . filesize($path));
  readfile($path);
}
