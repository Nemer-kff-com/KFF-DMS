<?php
/**
 * خدمة صور وثائق المساعدة من doc/images فقط.
 */
require_once __DIR__ . '/../config/auth.php';

$user = require_login();
if (!function_exists('dms_user_is_system_admin') || !dms_user_is_system_admin($user)) {
    http_response_code(403);
    exit('Forbidden');
}

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
if (function_exists('dms_stream_file')) {
    dms_stream_file($realFile, basename($realFile), $mime);
}
header('Content-Type: ' . $mime);
header('Cache-Control: private, max-age=86400');
header('X-Content-Type-Options: nosniff');
if (function_exists('dms_send_favicon_http_headers')) {
    dms_send_favicon_http_headers();
}
readfile($realFile);
exit;
