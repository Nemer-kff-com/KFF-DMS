<?php
require_once __DIR__ . '/../config/auth.php';
$user = require_login();
if (!user_can('circulars.issue', $user)) {
    flash('danger', __('auth.forbidden'));
    redirect(base_url('circulars/index.php'));
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(base_url('circulars/index.php'));
}
verify_csrf();
$id = (int) ($_POST['id'] ?? 0);
$item = get_circular($id);
if (!$item || !user_can_issue_circular($item, $user)) {
    flash('danger', __('circular.not_found'));
    redirect(base_url('circulars/index.php'));
}
try {
    issue_circular($id, $user);
    flash('success', __('circular.issued'));
} catch (Throwable $e) {
    flash('danger', $e->getMessage());
}
redirect(base_url('circulars/view.php?id=' . $id));
