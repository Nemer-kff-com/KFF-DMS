<?php
require_once __DIR__ . '/../../config/auth.php';
$user = require_archive_section($kind);
ensure_archive_schema();
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    redirect(base_url('archive/index.php'));
}
verify_csrf();
$id = (int) ($_POST['id'] ?? 0);
if (!user_can_archive_section($kind, 'delete', $user) || !archive_register_def($kind) || $id < 1) {
    flash('danger', __('auth.no_permission'));
    redirect(base_url('archive/index.php'));
}
archive_register_delete($kind, $id);
flash('success', __('archive.deleted'));
redirect(archive_register_url($kind, 'list'));
