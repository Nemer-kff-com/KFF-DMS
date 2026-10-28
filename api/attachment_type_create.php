<?php
/**
 * إنشاء نوع مرفق (JSON) — للنافذة المنبثقة.
 */
require_once __DIR__ . '/../config/auth.php';

$user = require_permission('lookups.manage');
header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => __('auth.forbidden')], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    verify_csrf();
    $row = attachment_type_create(
        (string) ($_POST['name_ar'] ?? ''),
        (string) ($_POST['name_en'] ?? ''),
        (string) ($_POST['code'] ?? '')
    );
    echo json_encode([
        'ok' => true,
        'item' => [
            'id' => (int) $row['id'],
            'label' => bilingual_label($row),
            'name_ar' => $row['name_ar'],
            'name_en' => $row['name_en'],
            'sort_order' => (int) $row['sort_order'],
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
