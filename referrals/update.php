<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/correspondence.php';
$user = require_login();
if (!user_can('referrals.manage', $user)) {
    flash('danger', __('auth.forbidden'));
    redirect(base_url('referrals/index.php'));
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(base_url('referrals/index.php'));
}
verify_csrf();
$id = (int) ($_POST['id'] ?? 0);
$status = $_POST['status'] ?? '';
$return = dms_safe_return_path($_POST['return'] ?? '', 'referrals/index.php');
$allowed = ['pending', 'received', 'completed', 'returned'];
if (!in_array($status, $allowed, true)) {
    flash('danger', __('ref.invalid_status'));
    redirect(base_url($return));
}
$stmt = db()->prepare("SELECT * FROM `tblreferrals` WHERE id = ?");
$stmt->execute([$id]);
$ref = $stmt->fetch();
if (!$ref) {
    flash('danger', __('ref.not_found'));
    redirect(base_url($return));
}
$corr = get_correspondence((int) $ref['correspondence_id']);
if (!$corr || !user_can_access_correspondence($corr, $user)) {
    flash('danger', __('auth.forbidden'));
    redirect(base_url($return));
}
if (correspondence_is_cancelled($corr)) {
    flash('danger', __('cancel.locked'));
    redirect(base_url($return));
}
db()->prepare("UPDATE `tblreferrals` SET status = ? WHERE id = ?")->execute([$status, $id]);
if ($status === 'received' || $status === 'completed') {
    db()->prepare("UPDATE `tblcorrespondence` SET status = 'in_progress' WHERE id = ? AND status <> 'closed'")->execute([(int)$ref['correspondence_id']]);
}
log_activity((int)$user['id'], (int)$ref['correspondence_id'], 'referral_status', 'تحديث إحالة إلى ' . status_label($status));
flash('success', __('ref.updated'));
redirect(base_url($return));
