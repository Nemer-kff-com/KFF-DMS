<?php
require_once __DIR__ . '/../config/auth.php';
$user = require_login();
$id = (int) ($_GET['id'] ?? 0);
$corr = get_correspondence($id);
if (!$corr || $corr['type'] !== 'outgoing' || !user_can_access_correspondence($corr, $user)) {
    exit(__('common.not_found'));
}
if (function_exists('outgoing_can_issue') && !outgoing_can_issue($corr)) {
    $msg = function_exists('outgoing_approval_block_reason') ? outgoing_approval_block_reason($corr) : __('issue_appr.blocked_pending');
    flash('warning', $msg !== '' ? $msg : __('issue_appr.blocked_pending'));
    redirect(base_url('outgoing/view.php?id=' . $id));
}

$forceForm = trim((string) ($_GET['mode'] ?? '')) === 'form';
$hasLetter = function_exists('correspondence_letter_text')
    && correspondence_letter_text($corr) !== '';
$useLetterPrint = !$forceForm && $hasLetter;

if (!$useLetterPrint && function_exists('outgoing_require_print_perm')) {
    outgoing_require_print_perm(corr_print_perm('outgoing', 'form'), $user, base_url('outgoing/view.php?id=' . $id));
}

if ($useLetterPrint) {
    $letterPrintItems = [$corr];
    $letterPrintTitle = __('bulk.print_letter') . ' — ' . format_ref_number($corr['ref_number'] ?? $id);
    $letterPrintBackUrl = base_url('outgoing/view.php?id=' . $id);
    require __DIR__ . '/../includes/print_outgoing_letters.php';
    return;
}

$qrContent = dms_corr_qr_content($corr);
$documents = get_documents($id);
require __DIR__ . '/../includes/print_correspondence.php';
