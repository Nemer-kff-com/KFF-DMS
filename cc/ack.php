<?php
/**
 * تأكيد «اطّلعت» على معاملة وصلت للمستخدم للاطلاع — POST
 */
require_once __DIR__ . '/../config/auth.php';

$user = require_login();
verify_csrf();

$id = (int) ($_POST['id'] ?? 0);
$corr = $id > 0 ? get_correspondence($id) : null;
$folder = $corr ? correspondence_type_folder((string) $corr['type']) : 'cc';

try {
    if (!$corr) {
        throw new InvalidArgumentException(__('corr.not_found'));
    }
    corr_cc_acknowledge($id, $user);
    flash('success', __('cc.acked_ok'));
} catch (Throwable $e) {
    flash('danger', $e->getMessage());
}

redirect(base_url($corr ? $folder . '/view.php?id=' . $id : 'cc/index.php'));
