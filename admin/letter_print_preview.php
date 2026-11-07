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
if ($host !== '' && $referer !== '' && str_contains($referer, $host) && !str_contains($referer, 'letter_print_preview.php')) {
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
    $row['intend_paper'] = 1;
    if ($lang === 'en') {
        $row['letter_body_en'] = $body;
    } else {
        $row['letter_body'] = $body;
    }
    $letterPrintItems = [$row];
} else {
    $letterPrintItems = letter_template_preview_items($templateId, $lang, $body, $deptId, $user);
    $draftOverlay = function_exists('correspondence_outgoing_draft_row')
        ? correspondence_outgoing_draft_row($post, $user, false)
        : [];
    if ($letterPrintItems === [] && is_array($draftOverlay) && $draftOverlay !== []) {
        $draftOverlay['intend_paper'] = 1;
        $letterPrintItems = [$draftOverlay];
    }
    foreach ($letterPrintItems as &$previewItem) {
        if (!is_array($previewItem) || !is_array($draftOverlay)) {
            continue;
        }
        foreach (['letter_link_label', 'letter_link_url', 'rsvp_required', 'rsvp_allow_remote', 'subject', 'email_letter_attach'] as $key) {
            if (array_key_exists($key, $draftOverlay) && $draftOverlay[$key] !== null && $draftOverlay[$key] !== '') {
                $previewItem[$key] = $draftOverlay[$key];
            }
        }
        if (array_key_exists('rsvp_required', $draftOverlay)) {
            $previewItem['rsvp_required'] = (int) $draftOverlay['rsvp_required'];
        }
    }
    unset($previewItem);
}

$batchToken = '';
$batchLinkLabel = '';
$batchLinkUrl = '';
$batchRsvp = null;
$batchRsvpRemote = null;
if ($batchIdPosted > 0) {
    $bst = db()->prepare(
        'SELECT letter_link_label, letter_link_url, requires_rsvp, rsvp_allow_remote
         FROM `tbloutgoing_batches` WHERE id = ? LIMIT 1'
    );
    $bst->execute([$batchIdPosted]);
    $brow = $bst->fetch(PDO::FETCH_ASSOC) ?: [];
    $batchLinkLabel = trim((string) ($brow['letter_link_label'] ?? ''));
    $batchLinkUrl = trim((string) ($brow['letter_link_url'] ?? ''));
    if (isset($brow['requires_rsvp'])) {
        $batchRsvp = (int) $brow['requires_rsvp'];
        $batchRsvpRemote = (int) ($brow['rsvp_allow_remote'] ?? 1);
    }
    $tst = db()->prepare(
        "SELECT qr_token FROM `tblcorrespondence`
         WHERE batch_id = ? AND type = 'outgoing' AND status <> 'cancelled'
           AND qr_token IS NOT NULL AND qr_token <> ''
         ORDER BY id LIMIT 1"
    );
    $tst->execute([$batchIdPosted]);
    $batchToken = strtolower(trim((string) $tst->fetchColumn()));
    if ($batchToken !== '' && !preg_match('/^[a-f0-9]{32}$/', $batchToken)) {
        $batchToken = '';
    }
}
foreach ($letterPrintItems as &$previewItem) {
    if (!is_array($previewItem)) {
        continue;
    }
    if (trim((string) ($previewItem['letter_link_url'] ?? '')) === '' && $batchLinkUrl !== '') {
        $previewItem['letter_link_label'] = $batchLinkLabel !== '' ? $batchLinkLabel : null;
        $previewItem['letter_link_url'] = $batchLinkUrl;
    }
    if (!isset($_POST['requires_rsvp']) && !isset($_POST['rsvp_required']) && $batchRsvp !== null) {
        $previewItem['rsvp_required'] = $batchRsvp;
        $previewItem['rsvp_allow_remote'] = $batchRsvpRemote ?? 1;
    }
    $itemToken = strtolower(trim((string) ($previewItem['qr_token'] ?? '')));
    if (!preg_match('/^[a-f0-9]{32}$/', $itemToken)) {
        $previewItem['qr_token'] = $batchToken !== '' ? $batchToken : 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    }
}
unset($previewItem);
$letterPrintTitle = __('tpl.preview_letter');
$letterPrintBackUrl = $backUrl;
require __DIR__ . '/../includes/print_outgoing_letters.php';
