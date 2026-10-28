<?php
/**
 * تحميل مرفق بمعاملة عبر رمز موقّع مؤقت (لـ Meta/Twilio Media URL).
 * لا يتطلب جلسة مستخدم — يعتمد على HMAC + انتهاء صلاحية.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

$token = trim((string) ($_GET['t'] ?? ''));
if ($token === '') {
    http_response_code(400);
    exit('Bad request');
}

$docId = dms_document_verify_signed_token($token);
if ($docId === null) {
    http_response_code(403);
    exit('Forbidden');
}

try {
    $stmt = db()->prepare(
        'SELECT file_path, file_name FROM `tbldocuments` WHERE id = ? LIMIT 1'
    );
    $stmt->execute([$docId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    http_response_code(500);
    exit('Error');
}

if (!$row) {
    http_response_code(404);
    exit('Not found');
}

$relative = (string) ($row['file_path'] ?? '');
$name = (string) ($row['file_name'] ?? 'document');
if ($relative === '' || !dms_is_safe_upload_path($relative)) {
    http_response_code(404);
    exit('Not found');
}

header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');
dms_stream_upload($relative, $name !== '' ? $name : 'document');
