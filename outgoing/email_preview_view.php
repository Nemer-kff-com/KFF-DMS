<?php
/**
 * معاينة بريد كاملة (نص + مرفقات قابلة للفتح) — مثل البريد العادي دون فتح المرفقات تلقائياً.
 */
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/correspondence.php';

$user = require_login();
$id = (int) ($_GET['id'] ?? 0);
$corr = $id > 0 ? get_correspondence($id) : null;

if (!$corr || ($corr['type'] ?? '') !== 'outgoing' || !user_can_access_correspondence($corr, $user)) {
    flash('danger', __('common.not_found'));
    redirect(base_url('outgoing/index.php'));
}

if (!function_exists('correspondence_intend_electronic') || !correspondence_intend_electronic($corr)) {
    flash('danger', __('send.skip_not_electronic'));
    $batchId = (int) ($corr['batch_id'] ?? 0);
    redirect(base_url($batchId > 0 ? ('outgoing/batch.php?id=' . $batchId) : ('outgoing/view.php?id=' . $id)));
}

$preview = dms_corr_email_preview($corr);
if (empty($preview['ok'])) {
    flash('danger', (string) ($preview['error'] ?? __('send.email_preview_fail')));
    $batchId = (int) ($corr['batch_id'] ?? 0);
    redirect(base_url($batchId > 0 ? ('outgoing/batch.php?id=' . $batchId) : ('outgoing/view.php?id=' . $id)));
}

$to = trim((string) ($preview['to'] ?? ''));
$recipient = trim((string) ($preview['recipient'] ?? ''));
$subject = trim((string) ($preview['subject'] ?? ''));
$html = (string) ($preview['html'] ?? '');
$attachments = is_array($preview['attachments'] ?? null) ? $preview['attachments'] : [];
$officialUrl = trim((string) ($preview['official_url'] ?? ''));
$officialName = trim((string) ($preview['official_name'] ?? ''));
if ($officialUrl === '' && ((int) ($corr['email_letter_attach'] ?? 0) === 1 || $officialName !== '')) {
    $officialUrl = base_url('outgoing/email_official_preview.php?id=' . $id . '&preview=1');
}

$batchId = (int) ($corr['batch_id'] ?? 0);
$backUrl = $batchId > 0
    ? base_url('outgoing/batch.php?id=' . $batchId)
    : base_url('outgoing/view.php?id=' . $id);
$title = __('tpl.preview_email');
$cssVer = (int) (@filemtime(__DIR__ . '/../assets/css/app.css') ?: 1);
$ref = format_ref_number($corr['ref_number'] ?? $id);
?>
<!DOCTYPE html>
<html lang="<?= e(dms_lang_code()) ?>" dir="<?= e(dms_page_dir()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?><?= $ref !== '' ? ' — ' . e($ref) : '' ?></title>
    <?= function_exists('dms_favicon_link_html') ? dms_favicon_link_html() : '' ?>
    <link href="<?= e(dms_bootstrap_css()) ?>" rel="stylesheet">
    <link href="<?= e(dms_bootstrap_icons_css()) ?>" rel="stylesheet">
    <link href="<?= e(base_url('assets/css/app.css')) ?>?v=<?= $cssVer ?>" rel="stylesheet">
</head>
<body class="print-page">
<div class="print-toolbar print-label-toolbar no-print">
    <div class="print-label-toolbar-main">
        <div class="print-label-toolbar-top">
            <div class="print-label-toolbar-actions">
                <?= dms_back_icon($backUrl) ?>
            </div>
            <div class="print-toolbar-title">
                <?= e($title) ?>
                <?php if ($ref !== ''): ?>
                    <span class="text-muted fw-normal small">— <?= e($ref) ?></span>
                <?php endif; ?>
            </div>
            <div class="print-label-toolbar-actions print-label-toolbar-actions-ghost" aria-hidden="true">
                <?= dms_back_icon() ?>
            </div>
        </div>
    </div>
</div>
<div class="container py-4" style="max-width:760px">
    <div class="card-panel">
        <div class="card-body">
            <div class="mb-3">
                <div class="small text-muted"><?= e(__('send.email_preview_recipient')) ?></div>
                <div><?= e($recipient !== '' ? $recipient : __('common.none')) ?></div>
            </div>
            <div class="mb-3">
                <div class="small text-muted"><?= e(__('send.email_preview_to')) ?></div>
                <div dir="ltr"><?= e($to !== '' ? $to : __('common.none')) ?></div>
            </div>
            <div class="mb-3">
                <div class="small text-muted"><?= e(__('send.email_preview_subject')) ?></div>
                <div class="fw-semibold"><?= e($subject !== '' ? $subject : __('common.none')) ?></div>
            </div>
            <div class="small text-muted mb-2"><?= e(__('send.email_preview_body')) ?></div>
            <div class="border rounded p-3 bg-white"><?= $html ?></div>
            <?php
            $emailPreviewAttachments = $attachments;
            $emailPreviewOfficialUrl = $officialUrl;
            require __DIR__ . '/../includes/email_preview_attachments_list.php';
            ?>
        </div>
    </div>
</div>
</body>
</html>
