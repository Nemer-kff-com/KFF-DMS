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
$corrId = (int) ($_POST['correspondence_id'] ?? 0);
$toDept = (int) ($_POST['to_department_id'] ?? 0);
$notes = trim($_POST['notes'] ?? '') ?: null;
$return = dms_safe_return_path($_POST['return'] ?? '', 'referrals/index.php');
$corr = get_correspondence($corrId);
if (!$corr || $toDept <= 0 || !user_can_access_correspondence($corr, $user)) {
    flash('danger', __('ref.incomplete'));
    redirect(base_url($return));
}
if (correspondence_is_cancelled($corr)) {
    flash('danger', __('cancel.locked'));
    redirect(base_url($return));
}
if (correspondence_is_closed($corr)) {
    flash('danger', __('reopen.refer_blocked'));
    redirect(base_url($return));
}
if (function_exists('corr_approval_is_held') && corr_approval_is_held($corr)) {
    flash('danger', corr_held_text($corr, 'refer_blocked'));
    redirect(base_url($return));
}

$stmt = db()->prepare(
    "INSERT INTO `tblreferrals` (correspondence_id, from_department_id, to_department_id, from_user_id, notes, status)
     VALUES (?, ?, ?, ?, ?, 'pending')"
);
$stmt->execute([
    $corrId,
    function_exists('user_primary_department_id') ? (user_primary_department_id($user) ?: $corr['department_id']) : ($user['department_id'] ?: $corr['department_id']),
    $toDept,
    (int) $user['id'],
    $notes,
]);
$referralId = (int) db()->lastInsertId();
db()->prepare("UPDATE `tblcorrespondence` SET status = 'referred', department_id = ? WHERE id = ?")
    ->execute([$toDept, $corrId]);
log_activity((int)$user['id'], $corrId, 'refer', 'إحالة إلى قسم #' . $toDept);
if (function_exists('close_request_cancel_on_referral')) {
    close_request_cancel_on_referral($corr, $toDept, $user);
}
if (function_exists('dms_notify_referral')) {
    dms_notify_referral($referralId, $corr, $toDept, user_actor_name($user), $user);
}
flash('success', __('ref.done'));
redirect(base_url($return));
