<?php
require_once __DIR__ . '/../../config/auth.php';
$user = require_archive_section($kind);
ensure_archive_schema();
$id = (int) ($_GET['id'] ?? 0);
$n = (int) ($_GET['n'] ?? 0);
$def = archive_register_def($kind);
$row = $def ? archive_register_get($kind, $id) : null;
if (!$def || !$row) {
    http_response_code(404);
    exit;
}
$folder = archive_register_guess_folder($def, $row);
$files = archive_register_folder_files($folder);
$path = $files[$n] ?? '';
if ($path === '' || !archive_register_file_is_allowed($path)) {
    http_response_code(404);
    exit;
}
if (function_exists('user_can_view_register_files') && !user_can_view_register_files($kind, $row, $user)) {
    http_response_code(403);
    exit(__('conf.archive_file_locked'));
}
$mime = null;
if (class_exists('finfo')) {
    $detected = (string) (new finfo(FILEINFO_MIME_TYPE))->file($path);
    if ($detected !== '') {
        $mime = $detected;
    }
}
dms_stream_file($path, archive_register_file_display_name($row, $files, $n), $mime);
