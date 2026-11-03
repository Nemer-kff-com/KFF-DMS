<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/correspondence.php';

$user = require_login();
if (!can_edit_correspondence($user) && !user_can('templates.edit', $user)) {
    flash('danger', __('auth.forbidden'));
    redirect(base_url('admin/letter_templates.php'));
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    redirect(base_url('admin/letter_templates.php'));
}
verify_csrf();

$lang = strtolower(trim((string) ($_POST['lang'] ?? 'ar'))) === 'en' ? 'en' : 'ar';
$body = (string) ($_POST['body'] ?? '');
$templateId = (int) ($_POST['template_id'] ?? 0);
$deptId = (int) ($_POST['department_id'] ?? 0);
$backUrl = base_url('admin/letter_templates.php' . ($templateId > 0 ? '?id=' . $templateId : ''));

$items = letter_template_preview_items($templateId, $lang, $body, $deptId, $user);
$row = $items[0] ?? [];
$row['intend_electronic'] = 1;
$view = dms_corr_email_message_view($row);
$to = trim((string) ($view['to'] ?? ''));
$recipient = trim((string) ($view['recipient'] ?? ''));
$subject = trim((string) ($view['subject'] ?? ''));
$title = __('tpl.preview_email');
$cssVer = (int) (@filemtime(__DIR__ . '/../assets/css/app.css') ?: 1);
?>
<!DOCTYPE html>
<html lang="<?= e(dms_lang_code()) ?>" dir="<?= e(dms_page_dir()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?></title>
    <link rel="icon" href="<?= e(base_url('assets/img/favicon.ico')) ?>">
    <link href="<?= e(dms_bootstrap_css()) ?>" rel="stylesheet">
    <link href="<?= e(dms_bootstrap_icons_css()) ?>" rel="stylesheet">
    <link href="<?= e(base_url('assets/css/app.css')) ?>?v=<?= $cssVer ?>" rel="stylesheet">
</head>
<body class="print-page">
<div class="print-toolbar print-label-toolbar no-print">
    <div class="print-label-toolbar-main">
        <div class="print-label-toolbar-top">
            <div class="print-label-toolbar-actions">
                <a class="btn btn-outline-secondary btn-sm" href="<?= e($backUrl) ?>">
                    <i class="bi <?= dms_is_rtl() ? 'bi-arrow-right' : 'bi-arrow-left' ?> me-1"></i><?= e(__('action.back')) ?>
                </a>
            </div>
            <div class="print-toolbar-title"><?= e($title) ?></div>
            <div class="print-label-toolbar-actions print-label-toolbar-actions-ghost" aria-hidden="true">
                <a class="btn btn-outline-secondary btn-sm" tabindex="-1"><?= e(__('action.back')) ?></a>
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
            <div class="border rounded p-3 bg-white"><?= $view['html'] ?></div>
        </div>
    </div>
</div>
</body>
</html>
