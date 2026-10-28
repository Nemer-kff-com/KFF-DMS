<?php
require_once __DIR__ . '/../../config/auth.php';
$user = require_archive_section('general', 'delete');
if (!user_can_archive_section('general', 'delete', $user)) {
    flash('danger', __('documents.delete_forbidden'));
    redirect(base_url('archive/general/index.php'));
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(base_url('archive/general/index.php'));
}
verify_csrf();
$fileId = (int) ($_POST['id'] ?? 0);
$archiveId = (int) ($_POST['archive_id'] ?? 0);
$item = get_archive_item($archiveId);
if (!$item || !user_can_access_archive($item, $user)) {
    flash('danger', __('archive.not_found'));
    redirect(base_url('archive/general/index.php'));
}
$deleted = delete_archive_file($fileId);
if (!$deleted) {
    flash('danger', __('documents.not_found'));
} else {
    log_activity((int) $user['id'], null, 'delete_doc', (string) ($deleted['file_name'] ?? ''), $archiveId);
    flash('success', __('documents.deleted'));
}
redirect(base_url('archive/general/view.php?id=' . $archiveId));
