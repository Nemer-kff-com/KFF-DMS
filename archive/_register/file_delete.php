<?php
require_once __DIR__ . '/../../config/auth.php';
$user = require_archive_section($kind, 'edit');
ensure_archive_schema();
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    redirect(archive_register_url($kind, 'list'));
}
verify_csrf();
$id = (int) ($_POST['id'] ?? 0);
$n = (int) ($_POST['n'] ?? -1);
$back = (string) ($_POST['back'] ?? 'view');
$to = $id > 0
    ? archive_register_url($kind, $back === 'form' ? 'form' : 'view', ['id' => $id])
    : archive_register_url($kind, 'list');
if ($id < 1 || $n < 0 || !archive_register_get($kind, $id)) {
    flash('danger', __('archive.not_found'));
    redirect($to);
}
if (!archive_register_delete_file($kind, $id, $n)) {
    flash('danger', __('documents.not_found'));
    redirect($to);
}
log_activity((int) ($user['id'] ?? 0), null, 'delete_doc', (string) $n, $id);
flash('success', __('documents.deleted'));
redirect($to);
