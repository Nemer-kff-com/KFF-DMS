<?php
require_once __DIR__ . '/../config/auth.php';
$user = require_login();
$id = (int) ($_GET['id'] ?? 0);
$corr = get_correspondence($id);
if (!$corr || $corr['type'] !== 'outgoing' || !user_can_access_correspondence($corr, $user)) {
    exit(__('common.not_found'));
}
$forceForm = trim((string) ($_GET['mode'] ?? '')) === 'form';
$isLetterPreview = (string) ($_GET['preview'] ?? '') === '1';
$hasLetter = function_exists('correspondence_letter_text')
    && correspondence_letter_text($corr) !== '';
$useLetterPrint = !$forceForm && $hasLetter;
$batchId = (int) ($corr['batch_id'] ?? 0);
$printBackUrl = $batchId > 0
    ? base_url('outgoing/batch.php?id=' . $batchId)
    : base_url('outgoing/view.php?id=' . $id);
if (!$useLetterPrint && function_exists('outgoing_can_issue') && !outgoing_can_issue($corr)) {
    $msg = function_exists('outgoing_approval_block_reason') ? outgoing_approval_block_reason($corr) : __('issue_appr.blocked_pending');
    flash('warning', $msg !== '' ? $msg : __('issue_appr.blocked_pending'));
    redirect($printBackUrl);
}

if (function_exists('outgoing_require_print_perm')) {
    // معاينة الخطاب (preview=1): قراءة فقط لكل من يصل للمعاملة — بلا شرط مرحلة الاتصالات
    $skipPrintGate = $isLetterPreview && $useLetterPrint;
    // الخطاب الرسمي أو نموذج التسليم: في with_comms يشترط صلاحية المرحلة + الإسناد
    if (!$skipPrintGate && (!$useLetterPrint || (function_exists('outgoing_is_comms_print_stage') && outgoing_is_comms_print_stage($corr)))) {
        outgoing_require_print_perm(
            $useLetterPrint ? 'outgoing.print_form' : corr_print_perm('outgoing', 'form'),
            $user,
            $printBackUrl,
            $corr
        );
    }
}

if ($useLetterPrint) {
    $letterPrintItems = [$corr];
    $letterPrintTitle = ($isLetterPreview ? __('tpl.preview_letter') : __('bulk.print_letter'))
        . ' — ' . format_ref_number($corr['ref_number'] ?? $id);
    $letterPrintBackUrl = $printBackUrl;
    require __DIR__ . '/../includes/print_outgoing_letters.php';
    return;
}

$qrContent = dms_corr_qr_content($corr);
$documents = get_documents($id);
require __DIR__ . '/../includes/print_correspondence.php';
