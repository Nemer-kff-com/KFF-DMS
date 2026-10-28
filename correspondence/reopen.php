<?php
require_once __DIR__ . '/../config/auth.php';
$user = require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(base_url('index.php'));
}
verify_csrf();
$id = (int) ($_POST['id'] ?? 0);
$corr = get_correspondence($id);
if (!$corr || !user_can_access_correspondence($corr, $user)) {
    flash('danger', __('corr.not_found'));
    redirect(base_url('index.php'));
}
try {
    reopen_correspondence($id, (string) ($_POST['reopen_reason'] ?? ''), $user);
    flash('success', __('reopen.done'));
} catch (Throwable $e) {
    flash('danger', $e->getMessage());
}
redirect(base_url(correspondence_folder_for_type((string) ($corr['type'] ?? 'incoming')) . '/view.php?id=' . $id));
