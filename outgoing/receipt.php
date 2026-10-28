<?php
/**
 * تسجيل / تحديث بيانات استلام الصادر + صورة سند التسليم
 */
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/correspondence.php';
require_once __DIR__ . '/../config/correspondence_send.php';

$user = require_login();
if (!user_can('outgoing.receipt', $user)) {
    flash('danger', __('auth.forbidden'));
    redirect(base_url('outgoing/index.php'));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(base_url('outgoing/index.php'));
}

verify_csrf();
$id = (int) ($_POST['correspondence_id'] ?? 0);
$action = $_POST['action'] ?? 'save';
$return = 'outgoing/view.php?id=' . $id;

$corr = get_correspondence($id);
if (!$corr || $corr['type'] !== 'outgoing' || !user_can_access_correspondence($corr, $user)) {
    flash('danger', __('corr.not_found'));
    redirect(base_url('outgoing/index.php'));
}

try {
    correspondence_assert_mutable($corr);
    if ($action === 'remove_file') {
        clear_outgoing_receipt_file($id);
        log_activity((int) $user['id'], $id, 'receipt_file_remove', 'حذف صورة سند التسليم');
        flash('success', __('receipt.file_removed'));
    } else {
        save_outgoing_receipt($id, $_POST, $_FILES['receipt_file'] ?? null, (int) $user['id']);
        $last = dms_corr_last_deliveries($id);
        if (($last['delivery']['status'] ?? '') !== 'sent') {
            dms_mark_delivery(get_correspondence($id) ?: $corr, $user);
        }
        log_activity((int) $user['id'], $id, 'receipt_save', __('activity.action.receipt_save'));
        flash('success', __('receipt.saved'));
    }
} catch (Throwable $e) {
    flash('danger', $e->getMessage());
}

redirect(base_url($return));
