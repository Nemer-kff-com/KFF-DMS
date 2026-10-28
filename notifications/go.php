<?php
require_once __DIR__ . '/../config/auth.php';
$user = require_login();
$id = (int) ($_GET['id'] ?? 0);
$row = $id ? dms_notification_get($id, $user) : null;
if (!$row) {
    flash('danger', __('notify.not_found'));
    redirect(base_url('notifications/index.php'));
}
dms_notification_mark_read($id, $user);
$link = dms_safe_return_path((string) ($row['link'] ?? ''), 'notifications/index.php');
redirect(base_url($link));
