<?php
require_once __DIR__ . '/../config/auth.php';

$user = require_login();
header('Content-Type: application/json; charset=utf-8');

if (!user_can('circulars.create', $user) && !user_can('circulars.edit', $user)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => __('auth.forbidden')], JSON_UNESCAPED_UNICODE);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => __('circular.preview_fail')], JSON_UNESCAPED_UNICODE);
    exit;
}

verify_csrf();

$item = [
    'id' => 0,
    'status' => 'draft',
    'content_mode' => 'electronic',
    'ref_number' => '',
    'circular_date' => trim((string) ($_POST['circular_date'] ?? '')),
    'subject' => trim((string) ($_POST['subject'] ?? '')),
    'department_id' => (int) ($_POST['department_id'] ?? 0),
    'audience' => (($_POST['audience'] ?? '') === 'departments') ? 'departments' : 'all',
    'letter_body' => (string) ($_POST['letter_body'] ?? ''),
    'letter_body_en' => (string) ($_POST['letter_body_en'] ?? ''),
];
if (!circular_has_letter($item)) {
    echo json_encode(['ok' => false, 'error' => __('circular.preview_empty')], JSON_UNESCAPED_UNICODE);
    exit;
}

$deptRows = [];
if ($item['audience'] === 'departments') {
    $ids = circular_normalize_department_ids(
        is_array($_POST['audience_department_ids'] ?? null) ? $_POST['audience_department_ids'] : []
    );
    if ($ids) {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $stmt = db()->prepare("SELECT id, name, name_en FROM `tbldepartments` WHERE id IN ({$ph}) ORDER BY name");
        $stmt->execute($ids);
        $deptRows = $stmt->fetchAll();
    }
}

echo json_encode([
    'ok' => true,
    'html' => circular_letter_preview_html($item, $deptRows),
], JSON_UNESCAPED_UNICODE);
