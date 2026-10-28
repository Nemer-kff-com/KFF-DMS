<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/i18n.php';
require_once __DIR__ . '/../config/lookups.php';
require_once __DIR__ . '/../config/archive.php';
ensure_archive_schema();
$force = in_array('--force', $argv ?? [], true);
fwrite(STDOUT, "استيراد سجلات الأرشيف القديم...\n");
$stats = archive_import_legacy_registers($force);
if (isset($stats['error'])) {
    fwrite(STDERR, "تعذر الاتصال بقاعدة kff_ea_legacy.\n");
    exit(1);
}
foreach ($stats as $kind => $n) {
    fwrite(STDOUT, $kind . ': ' . $n . "\n");
}
fwrite(STDOUT, "تم.\n");
