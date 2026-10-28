<?php
/**
 * API مساعد لرابط QR — يُرجع JSON مختصر لصلاحية الرمز فقط
 * الاستخدام: /api/qr.php?token=...
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/security.php';
dms_apply_runtime_security();
dms_send_security_headers();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$token = trim($_GET['token'] ?? '');
if ($token === '' || !preg_match('/^[a-f0-9]{32}$/', $token)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid'], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt = db()->prepare(
    'SELECT id, ref_number, type, status, qr_token FROM `tblcorrespondence` WHERE qr_token = ? LIMIT 1'
);
$stmt->execute([$token]);
$row = $stmt->fetch();
if (!$row) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'not_found'], JSON_UNESCAPED_UNICODE);
    exit;
}

$cancelled = strtolower((string) ($row['status'] ?? '')) === 'cancelled';
echo json_encode([
    'ok' => true,
    'valid' => !$cancelled,
    'ref_number' => $row['ref_number'],
    'type' => $row['type'],
    'cancelled' => $cancelled,
], JSON_UNESCAPED_UNICODE);
