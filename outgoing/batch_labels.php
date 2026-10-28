<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/correspondence.php';
require_once __DIR__ . '/../config/label_print.php';
$user = require_login();
require_outgoing_bulk($user);
ensure_entity_groups_schema();

$id = (int) ($_GET['id'] ?? 0);
$batch = $id ? get_outgoing_batch($id) : null;
$items = $id ? outgoing_batch_items($id, $user) : [];
$items = array_values(array_filter($items, static function (array $row): bool {
    if (correspondence_is_cancelled($row)) {
        return false;
    }
    return !(function_exists('correspondence_intend_electronic') && correspondence_intend_electronic($row));
}));
if (!$batch) {
    flash('danger', __('corr.not_found'));
    redirect(base_url('outgoing/index.php'));
}
if (!$items) {
    flash('warning', __('cancel.batch_print_empty'));
    redirect(base_url('outgoing/batch.php?id=' . $id));
}
if (function_exists('outgoing_batch_can_issue') && !outgoing_batch_can_issue($items)) {
    $sum = function_exists('outgoing_batch_approval_summary') ? outgoing_batch_approval_summary($items) : [];
    $msg = (string) ($sum['block_reason'] ?? '');
    flash('warning', $msg !== '' ? $msg : __('issue_appr.batch_blocked_pending'));
    redirect(base_url('outgoing/batch.php?id=' . $id));
}
if (function_exists('outgoing_require_print_perm')) {
    outgoing_require_print_perm(corr_print_perm('outgoing', 'envelope'), $user, base_url('outgoing/batch.php?id=' . $id));
}

$labelCorrList = $items;
$labelBackUrl = base_url('outgoing/batch.php?id=' . $id);
$labelTitle = __('bulk.print_labels') . ' — ' . $batch['subject'];
$labelFormAction = base_url('outgoing/batch_labels.php');
$labelHidden = ['id' => $id];
$labelAllowCopies = false;
require __DIR__ . '/../includes/print_envelope_labels.php';
