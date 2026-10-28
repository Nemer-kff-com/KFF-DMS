<?php
/**
 * طباعة وارد/داخلي. يتطلب: $corrType, $printHeldFn, $printHeldMsg
 */
require_once __DIR__ . '/../config/auth.php';
$user = require_login();
$corrType = (string) ($corrType ?? '');
$id = (int) ($_GET['id'] ?? 0);
$corr = get_correspondence($id);
if (!$corr || ($corr['type'] ?? '') !== $corrType || !user_can_access_correspondence($corr, $user)) {
    exit(__('common.not_found'));
}
$printHeldFn = (string) ($printHeldFn ?? '');
if ($printHeldFn !== '' && function_exists($printHeldFn) && $printHeldFn($corr)) {
    flash('warning', __((string) ($printHeldMsg ?? 'corr.not_found')));
    redirect(base_url($corrType . '/view.php?id=' . $id));
}
if (function_exists('corr_print_perm') && function_exists('outgoing_require_print_perm')) {
    outgoing_require_print_perm(corr_print_perm($corrType, 'form'), $user, base_url($corrType . '/view.php?id=' . $id));
}
$qrContent = dms_corr_qr_content($corr);
$documents = get_documents($id);
require __DIR__ . '/print_correspondence.php';
