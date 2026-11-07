<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/correspondence.php';

$user = require_login();
if (!can_edit_correspondence($user) && !user_can('templates.edit', $user)
    && !user_can('outgoing.bulk', $user) && !user_can('outgoing.create', $user) && !user_can('outgoing.edit', $user)) {
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
$deptId = (int) ($_POST['issuing_department_id'] ?? 0);
if ($deptId <= 0) {
    $deptId = (int) ($_POST['department_id'] ?? 0);
}
$backUrl = base_url('admin/letter_templates.php' . ($templateId > 0 ? '?id=' . $templateId : ''));
$referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
$host = (string) ($_SERVER['HTTP_HOST'] ?? '');
if ($host !== '' && $referer !== '' && str_contains($referer, $host) && !str_contains($referer, 'letter_email_preview.php')) {
    $backUrl = $referer;
}

$post = $_POST;
if ($body !== '') {
    if ($lang === 'en') {
        $post['letter_body_en'] = $body;
        $post['body'] = $body;
    } else {
        $post['letter_body'] = $body;
        $post['body'] = $body;
    }
}

$hasRecipients = function_exists('correspondence_preview_entity_id') && correspondence_preview_entity_id($post) > 0;
$batchIdPosted = (int) ($post['batch_id'] ?? 0);
$corrIdPosted = (int) ($post['correspondence_id'] ?? $post['corr_id'] ?? 0);
$useFormRecipient = $hasRecipients || $batchIdPosted > 0 || $corrIdPosted > 0;

if ($useFormRecipient && function_exists('letter_preview_form_row')) {
    $row = letter_preview_form_row($post, $user, $lang, $deptId);
} else {
    $items = letter_template_preview_items($templateId, $lang, $body, $deptId, $user);
    $row = $items[0] ?? (function_exists('letter_preview_sample_row') ? letter_preview_sample_row($lang, $deptId) : []);
    $draftOverlay = function_exists('correspondence_outgoing_draft_row')
        ? correspondence_outgoing_draft_row($post, $user, false)
        : [];
    if (is_array($draftOverlay)) {
        foreach (['letter_link_label', 'letter_link_url', 'rsvp_required', 'rsvp_allow_remote', 'subject', 'email_letter_attach'] as $key) {
            if (array_key_exists($key, $draftOverlay) && $draftOverlay[$key] !== null && $draftOverlay[$key] !== '') {
                $row[$key] = $draftOverlay[$key];
            }
        }
        if (array_key_exists('rsvp_required', $draftOverlay)) {
            $row['rsvp_required'] = (int) $draftOverlay['rsvp_required'];
        }
    }
}
$row['intend_electronic'] = 1;
$wantOfficial = !empty($post['email_letter_attach']) || (int) ($row['email_letter_attach'] ?? 0) === 1;
if ($wantOfficial) {
    $row['email_letter_attach'] = 1;
}
if ($lang === 'en') {
    $row['letter_body_en'] = $body !== '' ? $body : (string) ($row['letter_body_en'] ?? '');
} else {
    $row['letter_body'] = $body !== '' ? $body : (string) ($row['letter_body'] ?? '');
}
if (function_exists('letter_preview_ensure_letter_link')) {
    $row = letter_preview_ensure_letter_link($row, $post, $batchIdPosted);
}

$view = dms_corr_email_message_view($row);
$to = trim((string) ($view['to'] ?? ''));
$recipient = trim((string) ($view['recipient'] ?? ''));
$subject = trim((string) ($view['subject'] ?? ''));
$title = __('tpl.preview_email');
$cssVer = (int) (@filemtime(__DIR__ . '/../assets/css/app.css') ?: 1);

$officialUrl = '';
$officialName = '';
$previewAttachments = [];
$corrIdForAttach = (int) ($row['id'] ?? $corrIdPosted);
if ($corrIdForAttach > 0 && function_exists('dms_corr_email_preview_attachments')) {
    $attachPack = dms_corr_email_preview_attachments($row);
    $previewAttachments = is_array($attachPack['attachments'] ?? null) ? $attachPack['attachments'] : [];
    $officialUrl = trim((string) ($attachPack['official_url'] ?? ''));
    $officialName = trim((string) ($attachPack['official_name'] ?? ''));
} elseif ($batchIdPosted > 0 && function_exists('outgoing_batch_items_unscoped') && function_exists('dms_corr_email_preview_attachments')) {
    foreach (outgoing_batch_items_unscoped($batchIdPosted) as $item) {
        if (!is_array($item) || (int) ($item['id'] ?? 0) <= 0) {
            continue;
        }
        if (function_exists('correspondence_is_cancelled') && correspondence_is_cancelled($item)) {
            continue;
        }
        $attachPack = dms_corr_email_preview_attachments(array_merge($item, [
            'intend_electronic' => 1,
            'email_letter_attach' => $wantOfficial ? 1 : (int) ($item['email_letter_attach'] ?? 0),
        ]));
        $previewAttachments = is_array($attachPack['attachments'] ?? null) ? $attachPack['attachments'] : [];
        $officialUrl = trim((string) ($attachPack['official_url'] ?? ''));
        $officialName = trim((string) ($attachPack['official_name'] ?? ''));
        $corrIdForAttach = (int) $item['id'];
        break;
    }
}
if ($wantOfficial) {
    if ($officialName === '') {
        $ref = function_exists('format_ref_number') ? format_ref_number($row['ref_number'] ?? '') : (string) ($row['ref_number'] ?? '');
        $safe = trim((string) preg_replace('/[^\p{L}\p{N}\-_]+/u', '-', $ref), '-');
        $officialName = ($safe !== '' ? $safe : 'letter') . '.pdf';
    }
    if ($officialUrl === '' && $corrIdForAttach > 0) {
        $officialUrl = base_url('outgoing/email_official_preview.php?id=' . $corrIdForAttach . '&preview=1');
    }
    $hasOfficialInList = false;
    foreach ($previewAttachments as $att) {
        if (is_array($att) && ($att['kind'] ?? '') === 'official_letter') {
            $hasOfficialInList = true;
            break;
        }
    }
    if (!$hasOfficialInList && $officialName !== '') {
        array_unshift($previewAttachments, [
            'name' => $officialName,
            'mime' => 'application/pdf',
            'kind' => 'official_letter',
            'url' => $officialUrl,
            'id' => 0,
        ]);
    }
}
?>
<!DOCTYPE html>
<html lang="<?= e(dms_lang_code()) ?>" dir="<?= e(dms_page_dir()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?></title>
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
            <div class="print-toolbar-title"><?= e($title) ?></div>
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
            <div class="border rounded p-3 bg-white"><?= $view['html'] ?></div>
            <?php
            $emailPreviewAttachments = $previewAttachments;
            $emailPreviewOfficialUrl = $officialUrl;
            require __DIR__ . '/../includes/email_preview_attachments_list.php';
            ?>
        </div>
    </div>
</div>
</body>
</html>
