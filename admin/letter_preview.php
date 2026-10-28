<?php
require_once __DIR__ . '/../config/auth.php';

$user = require_login();
header('Content-Type: application/json; charset=utf-8');

if (!can_edit_correspondence($user)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => __('auth.forbidden')], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method'], JSON_UNESCAPED_UNICODE);
    exit;
}

verify_csrf();
$body = (string) ($_POST['body'] ?? '');
$lang = strtolower(trim((string) ($_POST['lang'] ?? 'ar'))) === 'en' ? 'en' : 'ar';

$prepId = (int) ($_POST['department_id'] ?? 0);
$issuingId = (int) ($_POST['issuing_department_id'] ?? 0);
$deptId = $issuingId > 0 ? $issuingId : $prepId;

$row = null;
if (function_exists('correspondence_outgoing_draft_row')) {
    $row = correspondence_outgoing_draft_row($_POST, $user, false);
}

echo json_encode([
    'ok' => true,
    'html' => letter_preview_html($body, $lang, $deptId, $row),
], JSON_UNESCAPED_UNICODE);