<?php
/**
 * ملصق ظرف لوارد/داخلي. يتطلب: $corrType, $labelHeldFn, $labelHeldMsg
 */
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/label_print.php';
$user = require_login();
$corrType = (string) ($corrType ?? '');
$id = (int) ($_GET['id'] ?? 0);
$corr = get_correspondence($id);
if (!$corr || ($corr['type'] ?? '') !== $corrType || !user_can_access_correspondence($corr, $user)) {
    exit(__('common.not_found'));
}
$labelHeldFn = (string) ($labelHeldFn ?? '');
if ($labelHeldFn !== '' && function_exists($labelHeldFn) && $labelHeldFn($corr)) {
    flash('warning', __((string) ($labelHeldMsg ?? 'corr.not_found')));
    redirect(base_url($corrType . '/view.php?id=' . $id));
}
if (function_exists('corr_print_perm') && function_exists('outgoing_require_print_perm')) {
    outgoing_require_print_perm(corr_print_perm($corrType, 'envelope'), $user, base_url($corrType . '/view.php?id=' . $id));
}

$labelCorrList = [$corr];
$labelBackUrl = base_url($corrType . '/view.php?id=' . $id);
$labelTitle = __('label.print') . ' — ' . $corr['ref_number'];
$labelFormAction = base_url($corrType . '/label.php');
$labelHidden = ['id' => $id];
$labelAllowCopies = true;
require __DIR__ . '/print_envelope_labels.php';
