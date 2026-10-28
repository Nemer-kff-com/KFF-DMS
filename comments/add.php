<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/correspondence.php';
$user = require_login();
if (!user_can('comments.add', $user)) {
    flash('danger', __('auth.forbidden'));
    redirect(base_url('index.php'));
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(base_url('index.php'));
}
verify_csrf();
$corrId = (int) ($_POST['correspondence_id'] ?? 0);
$body = trim($_POST['body'] ?? '');
$return = dms_safe_return_path($_POST['return'] ?? '', 'index.php');
$corr = get_correspondence($corrId);
if (!$corr || !user_can_access_correspondence($corr, $user)) {
    flash('danger', __('corr.not_found'));
    redirect(base_url($return));
}
if (function_exists('corr_approval_is_held') && corr_approval_is_held($corr)) {
    flash('danger', __('corr.comment_before_send'));
    redirect(base_url($return));
}
try {
    add_comment($corrId, (int) $user['id'], $body);
    flash('success', __('corr.comment_added'));
} catch (Throwable $e) {
    flash('danger', $e->getMessage());
}
redirect(base_url($return));
