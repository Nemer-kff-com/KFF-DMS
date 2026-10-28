<?php
require_once __DIR__ . '/../config/auth.php';
$user = require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(base_url('circulars/index.php'));
}
verify_csrf();
$id = (int) ($_POST['id'] ?? 0);
$item = $id ? get_circular($id) : null;
if (!$item || !user_can_delete_circular($item, $user)) {
    flash('danger', __('auth.forbidden'));
    redirect(base_url('circulars/index.php'));
}
if (delete_circular($id)) {
    log_activity((int) $user['id'], null, 'circular_delete', 'حذف تعميم ' . ($item['ref_number'] ?? ('#' . $id)), null, $id);
    flash('success', __('circular.deleted'));
} else {
    flash('danger', __('circular.not_found'));
}
redirect(base_url('circulars/index.php'));
