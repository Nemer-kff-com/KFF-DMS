<?php
require_once __DIR__ . '/../config/auth.php';

$user = require_login();
header('Content-Type: application/json; charset=utf-8');

if (!can_edit_correspondence($user) && !user_can('templates.edit', $user)) {
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
    $row = correspondence_outgoing_draft_row($_POST, $user, true);
}
if (!is_array($row)) {
    $row = letter_preview_sample_row($lang, $deptId);
}
if ((string) ($_POST['omit_parties'] ?? '') === '1') {
    foreach ([
        'department_id', 'issuing_department_id', 'entity_id',
        'snap_title_prefix', 'snap_person_name', 'snap_honorific', 'snap_job_title', 'snap_org_name',
        'snap_from_prefix', 'snap_from_person', 'snap_from_job', 'snap_from_org', 'snap_from_signature',
        'party_name', 'entity_name', 'entity_name_en', 'entity_directed_to', 'entity_title_prefix',
        'entity_job_title', 'entity_honorific', 'entity_email',
    ] as $key) {
        $row[$key] = '';
    }
}

echo json_encode([
    'ok' => true,
    'html' => letter_preview_html($body, $lang, $deptId, $row),
], JSON_UNESCAPED_UNICODE);