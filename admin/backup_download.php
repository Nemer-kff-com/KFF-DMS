<?php
require_once __DIR__ . '/../config/auth.php';

$user = require_permission('admin.backup');

$encoded = (string) ($_GET['file'] ?? '');
$csrf = (string) ($_GET['csrf_token'] ?? '');
if ($csrf === '' || !hash_equals(csrf_token(), $csrf)) {
    http_response_code(403);
    exit('Access denied.');
}

require_once __DIR__ . '/../config/backup/maint.php';

$settingsFile = dirname(__DIR__) . '/tools/backup-maintenance/ui_settings.json';
$envPaths = dms_backup_maint_env_paths();
$settings = dms_backup_maint_load($settingsFile);
$allowedDirs = dms_backup_maint_allowed_backup_dirs($envPaths, $settings);

$real = dms_backup_maint_allowed_file_path($encoded, $allowedDirs, ['sql', 'zip']);
if ($real === null) {
    http_response_code(404);
    exit('Backup file not found.');
}

$ext = strtolower((string) pathinfo($real, PATHINFO_EXTENSION));

header('Content-Description: File Transfer');
header('Content-Type: ' . ($ext === 'zip' ? 'application/zip' : 'application/sql'));
header('Content-Disposition: attachment; filename="' . basename($real) . '"');
header('Content-Length: ' . (string) filesize($real));
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
readfile($real);
exit;
