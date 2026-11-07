<?php
/**
 * خدمة صور وثائق المساعدة من doc/images فقط — لأي مستخدم مسجّل.
 * يُرسل بايتات الصورة مباشرة (لا صفحة معاينة) حتى تعمل داخل <img>.
 */
require_once __DIR__ . '/../config/auth.php';

require_login();

$rel = str_replace('\\', '/', trim((string) ($_GET['f'] ?? '')));
$rel = ltrim($rel, '/');
if ($rel === '' || str_contains($rel, '..') || !preg_match('#^images/[a-zA-Z0-9._/-]+\.(png|jpe?g|gif|webp|svg)$#i', $rel)) {
    http_response_code(404);
    exit('Not found');
}

$root = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'doc';
$path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
$realRoot = realpath($root);
$realFile = realpath($path);
if ($realRoot === false || $realFile === false || !str_starts_with($realFile, $realRoot) || !is_file($realFile)) {
    http_response_code(404);
    exit('Not found');
}

$ext = strtolower(pathinfo($realFile, PATHINFO_EXTENSION));
$mime = match ($ext) {
    'png' => 'image/png',
    'jpg', 'jpeg' => 'image/jpeg',
    'gif' => 'image/gif',
    'webp' => 'image/webp',
    'svg' => 'image/svg+xml',
    default => 'application/octet-stream',
};

header('Cache-Control: private, max-age=86400');
header('X-Content-Type-Options: nosniff');
if (function_exists('dms_send_favicon_http_headers')) {
    dms_send_favicon_http_headers();
}

if (function_exists('dms_send_file_bytes')) {
    dms_send_file_bytes($realFile, basename($realFile), $mime);
    exit;
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($realFile));
readfile($realFile);
exit;
