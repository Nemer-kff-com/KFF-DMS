<?php
require_once __DIR__ . '/../../config/auth.php';
$user = require_archive_section('general', 'delete');
if (!user_can_archive_section('general', 'delete', $user)) {
    flash('danger', __('auth.forbidden'));
    redirect(base_url('archive/general/index.php'));
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(base_url('archive/general/index.php'));
}
verify_csrf();
$id = (int) ($_POST['id'] ?? 0);
$item = $id ? get_archive_item($id) : null;
if (!$item || !user_can_access_archive($item, $user)) {
    flash('danger', __('archive.not_found'));
    redirect(base_url('archive/general/index.php'));
}
log_activity((int) $user['id'], null, 'archive_delete', archive_title($item), $id);
if (delete_archive_item($id)) {
    flash('success', __('archive.deleted'));
} else {
    flash('danger', __('archive.not_found'));
}
redirect(base_url('archive/general/index.php'));
