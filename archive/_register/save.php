<?php
require_once __DIR__ . '/../../config/auth.php';
$user = require_archive_section($kind);
ensure_archive_schema();
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !archive_register_def($kind)) {
    redirect(base_url('archive/index.php'));
}
verify_csrf();
$id = (int) ($_POST['id'] ?? 0);
if ($id > 0 && !user_can_archive_section($kind, 'edit', $user)) {
    flash('danger', __('auth.no_permission'));
    redirect(archive_register_url($kind, 'list'));
}
if ($id < 1 && !user_can_archive_section($kind, 'create', $user)) {
    flash('danger', __('auth.no_permission'));
    redirect(archive_register_url($kind, 'list'));
}
try {
    $newId = archive_register_save($kind, $id, $_POST);
    try {
        archive_register_store_uploads($kind, $newId, archive_collect_request_uploads(), (int) ($user['id'] ?? 0));
    } catch (Throwable $up) {
        flash('warning', $up->getMessage());
        redirect(archive_register_url($kind, 'view', ['id' => $newId]));
    }
    flash('success', $id > 0 ? __('archive.updated') : __('archive.created'));
    redirect(archive_register_url($kind, 'view', ['id' => $newId]));
} catch (Throwable $e) {
    flash('danger', $e->getMessage());
    redirect(archive_register_url($kind, 'form', $id > 0 ? ['id' => $id] : []));
}
