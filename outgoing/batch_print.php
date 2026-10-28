<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/correspondence.php';
$user = require_login();
require_outgoing_bulk($user);
ensure_entity_groups_schema();

$id = (int) ($_GET['id'] ?? 0);
$batch = $id ? get_outgoing_batch($id) : null;
$items = $id ? outgoing_batch_items($id, $user) : [];
$items = array_values(array_filter($items, static fn(array $row): bool => !correspondence_is_cancelled($row)));
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

$letterPrintItems = $items;
$letterPrintTitle = __('bulk.print_letters') . ' — ' . (string) ($batch['subject'] ?? '');
$letterPrintBackUrl = base_url('outgoing/batch.php?id=' . $id);
require __DIR__ . '/../includes/print_outgoing_letters.php';
