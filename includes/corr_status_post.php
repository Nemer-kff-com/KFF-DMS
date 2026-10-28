<?php
/**
 * تحديث حالة معاملة (POST). يتطلب: $corrType, $statusPerm
 * اختياري: $statusRedirectQuery
 */
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/correspondence.php';
$user = require_login();
$corrType = (string) ($corrType ?? '');
$statusPerm = (string) ($statusPerm ?? '');
$listUrl = base_url($corrType . '/index.php');
if ($statusPerm === '' || !user_can($statusPerm, $user)) {
    flash('danger', __('auth.forbidden'));
    redirect($listUrl);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect($listUrl);
}
verify_csrf();
$id = (int) ($_POST['id'] ?? 0);
$status = $_POST['status'] ?? '';
try {
    $corr = get_correspondence($id);
    if (!$corr || ($corr['type'] ?? '') !== $corrType) {
        throw new RuntimeException(__('corr.invalid_request'));
    }
    update_correspondence_status($id, $status, $user);
    flash('success', __('corr.status_updated'));
} catch (Throwable $e) {
    flash('danger', $e->getMessage());
}
$q = (string) ($statusRedirectQuery ?? '');
redirect(base_url($corrType . '/view.php?id=' . $id . $q));
