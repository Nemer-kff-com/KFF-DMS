<?php
require_once __DIR__ . '/../config/auth.php';
$user = require_login();
$id = (int) ($_GET['id'] ?? 0);
$dirOrder = (($_GET['dir'] ?? '') === 'from') ? 'from' : 'to';
$corr = $id > 0 ? get_correspondence($id) : null;
if (!$corr || !user_can_access_correspondence($corr, $user)) {
    exit(__('common.not_found'));
}
$viewUrl = base_url(correspondence_folder_for_type((string) $corr['type']) . '/view.php?id=' . $id);
if (!mail_draft_user_allowed($user) || !user_can_view_corr_files($corr, $user)) {
    flash('danger', __('auth.forbidden'));
    redirect($viewUrl);
}
$blocked = mail_draft_block_reason($corr);
if ($blocked !== '') {
    flash('warning', $blocked);
    redirect($viewUrl);
}
$files = mail_draft_files($corr);
$bytes = mail_draft_files_bytes($files);
if ($bytes > MAIL_DRAFT_MAX_BYTES) {
    flash('warning', str_replace(
        [':size', ':max'],
        [(string) round($bytes / 1048576, 1), (string) round(MAIL_DRAFT_MAX_BYTES / 1048576)],
        __('mail_draft.too_large')
    ));
    redirect($viewUrl);
}

$draft = mail_draft_build($corr, $dirOrder);
log_activity((int) $user['id'], $id, 'mail_draft', str_replace(':n', (string) count($files), __('mail_draft.log_detail')));

header('X-Content-Type-Options: nosniff');
header('Content-Type: message/rfc822');
header('Content-Length: ' . strlen($draft['content']));
header('Content-Disposition: attachment; filename="' . mail_draft_filename_ascii($corr) . '"; filename*=UTF-8\'\'' . rawurlencode($draft['filename']));
header('Cache-Control: private, no-store');
echo $draft['content'];
