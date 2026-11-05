<?php
/**
 * تحويل مختصر لرابط الخطاب (مكان الاجتماع وغيرها) من رمز QR: /l/{token}
 */
declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/helpers.php';
require_once __DIR__ . '/config/security.php';
require_once __DIR__ . '/config/correspondence.php';
dms_apply_runtime_security();
dms_send_security_headers();

$token = strtolower(trim((string) ($_GET['token'] ?? '')));
if ($token === '' || !preg_match('/^[a-f0-9]{32}$/', $token)) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'invalid';
    exit;
}

ensure_letter_link_schema();

$stmt = db()->prepare(
    'SELECT id, letter_link_url, status FROM `tblcorrespondence` WHERE qr_token = ? LIMIT 1'
);
$stmt->execute([$token]);
$row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
$url = trim((string) ($row['letter_link_url'] ?? ''));
if (!$row || $url === '' || !preg_match('#^https?://#i', $url)
    || strtolower((string) ($row['status'] ?? '')) === 'cancelled'
) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'not_found';
    exit;
}

header('Cache-Control: no-store');
if (function_exists('dms_send_favicon_http_headers')) {
    dms_send_favicon_http_headers();
}
header('Location: ' . $url, true, 302);
exit;
