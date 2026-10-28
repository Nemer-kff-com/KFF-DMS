<?php
require_once __DIR__ . '/../config/auth.php';

header('Content-Type: application/json; charset=utf-8');

$user = require_login();
$id = (int) ($_GET['id'] ?? 0);
$corr = $id > 0 ? get_correspondence($id) : null;

if (!$corr || ($corr['type'] ?? '') !== 'outgoing' || !user_can_access_correspondence($corr, $user)) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => __('common.not_found')], JSON_UNESCAPED_UNICODE);
    exit;
}

$preview = dms_corr_email_preview($corr);
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
    'ref' => format_ref_number($corr['ref_number'] ?? $id),
], JSON_UNESCAPED_UNICODE);
