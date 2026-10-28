<?php
require_once __DIR__ . '/../config/auth.php';

header('Content-Type: application/json; charset=utf-8');

$user = require_login();
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

if (!function_exists('correspondence_outgoing_draft_row')) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => __('common.error')], JSON_UNESCAPED_UNICODE);
    exit;
}

$draft = correspondence_outgoing_draft_row($_POST, $user);
$preview = dms_corr_email_preview($draft);
if (empty($preview['ok'])) {
    http_response_code(422);
    echo json_encode([
        'ok' => false,
        'error' => (string) ($preview['error'] ?? __('send.err_no_email')),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'ok' => true,
    'to' => (string) ($preview['to'] ?? ''),
    'recipient' => (string) ($preview['recipient'] ?? ''),
    'subject' => (string) ($preview['subject'] ?? ''),
    'html' => (string) ($preview['html'] ?? ''),
    'ref' => format_ref_number($draft['ref_number'] ?? ''),
], JSON_UNESCAPED_UNICODE);
