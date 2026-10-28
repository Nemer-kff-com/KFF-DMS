<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/label_print.php';
$user = require_login();
$id = (int) ($_GET['id'] ?? 0);
$corr = get_correspondence($id);
if (!$corr || $corr['type'] !== 'outgoing' || !user_can_access_correspondence($corr, $user)) {
    exit(__('common.not_found'));
}
if (correspondence_is_cancelled($corr)) {
    flash('danger', __('cancel.locked'));
    redirect(base_url('outgoing/view.php?id=' . $id));
}
if (function_exists('outgoing_can_issue') && !outgoing_can_issue($corr)) {
    $msg = function_exists('outgoing_approval_block_reason') ? outgoing_approval_block_reason($corr) : __('issue_appr.blocked_pending');
    flash('warning', $msg !== '' ? $msg : __('issue_appr.blocked_pending'));
    redirect(base_url('outgoing/view.php?id=' . $id));
}
if (function_exists('correspondence_intend_electronic') && correspondence_intend_electronic($corr)) {
    flash('warning', __('out_prep.print_not_electronic'));
    redirect(base_url('outgoing/view.php?id=' . $id));
}
if (function_exists('outgoing_require_print_perm')) {
    outgoing_require_print_perm(corr_print_perm('outgoing', 'envelope'), $user, base_url('outgoing/view.php?id=' . $id));
}

$labelCorrList = [$corr];
$labelBackUrl = base_url('outgoing/view.php?id=' . $id);
$labelTitle = __('label.print') . ' — ' . $corr['ref_number'];
$labelFormAction = base_url('outgoing/label.php');
$labelHidden = ['id' => $id];
$labelAllowCopies = true;
require __DIR__ . '/../includes/print_envelope_labels.php';
