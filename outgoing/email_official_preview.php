<?php
/**
 * معاينة الخطاب الرسمي كمرفق البريد — نفس تنسيق معاينة/طباعة الخطاب (PDF).
 */
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/correspondence.php';

$user = require_login();
$id = (int) ($_GET['id'] ?? 0);
$corr = $id > 0 ? get_correspondence($id) : null;

if (!$corr || ($corr['type'] ?? '') !== 'outgoing' || !user_can_access_correspondence($corr, $user)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo __('common.not_found');
    exit;
}

$redacted = function_exists('dms_corr_is_confidential_row') && dms_corr_is_confidential_row($corr)
    && function_exists('dms_conf_external_notify_redacted') && dms_conf_external_notify_redacted();
$electronic = function_exists('correspondence_intend_electronic') && correspondence_intend_electronic($corr);
$attachOn = (int) ($corr['email_letter_attach'] ?? 0) === 1;
// السماح بالمعاينة للمستخدم المخوّل حتى لو لم يُحفظ خيار المرفق بعد (من النموذج)
$forcePreview = (string) ($_GET['preview'] ?? '') === '1'
    && (
        can_edit_correspondence($user)
        || user_can('outgoing.edit', $user)
        || user_can('outgoing.create', $user)
        || user_can('outgoing.bulk', $user)
        || user_can('outgoing.send', $user)
    );
if ($redacted || !$electronic || (!$attachOn && !$forcePreview)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo __('common.not_found');
    exit;
}

$body = trim(function_exists('correspondence_letter_text') ? correspondence_letter_text($corr) : '');
if ($body === '') {
    http_response_code(422);
    header('Content-Type: text/plain; charset=utf-8');
    echo __('send.email_preview_official_fail');
    exit;
}

if (function_exists('letter_preview_ensure_letter_link')) {
    $corr = letter_preview_ensure_letter_link($corr, $corr, (int) ($corr['batch_id'] ?? 0));
}
$token = strtolower(trim((string) ($corr['qr_token'] ?? '')));
if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
    $corr['qr_token'] = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
}

$letterPrintItems = [$corr];
$letterPrintTitle = __('send.email_preview_official') . ' — ' . format_ref_number($corr['ref_number'] ?? $id);
$letterPrintBackUrl = base_url('outgoing/email_preview_view.php?id=' . $id);
$referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
$host = (string) ($_SERVER['HTTP_HOST'] ?? '');
if ($host !== '' && $referer !== '' && str_contains($referer, $host)
    && !str_contains($referer, 'email_official_preview.php')) {
    $letterPrintBackUrl = $referer;
}

require __DIR__ . '/../includes/print_outgoing_letters.php';
