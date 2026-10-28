<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/correspondence.php';
require_once __DIR__ . '/../config/correspondence_send.php';

$user = require_login();
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    redirect(base_url('outgoing/index.php'));
}
verify_csrf();

$channel = strtolower(trim((string) ($_POST['channel'] ?? 'email')));
if (!in_array($channel, ['email', 'whatsapp', 'delivery'], true)) {
    $channel = 'email';
}
$batchId = (int) ($_POST['batch_id'] ?? 0);
$corrId = (int) ($_POST['correspondence_id'] ?? $_POST['id'] ?? 0);
$returnBatch = (int) ($_POST['return_batch'] ?? 0);
$fromBatch = $batchId > 0 || $returnBatch > 0;
$channelNeed = $channel === 'delivery' ? 'outgoing.receipt' : 'outgoing.send';
if (!user_can($channelNeed, $user) && !($fromBatch && user_can('outgoing.bulk', $user))) {
    flash('danger', __('auth.forbidden'));
    redirect($fromBatch && $batchId > 0
        ? base_url('outgoing/batch.php?id=' . $batchId)
        : base_url('outgoing/index.php'));
}

if ($batchId > 0) {
    $batch = get_outgoing_batch($batchId);
    $items = $batch ? outgoing_batch_items($batchId, $user) : [];
    if (!$batch || !$items) {
        flash('danger', __('corr.not_found'));
        redirect(base_url('outgoing/index.php'));
    }
    $selectedIds = array_values(array_unique(array_filter(
        array_map('intval', (array) ($_POST['ids'] ?? [])),
        static fn(int $rowId): bool => $rowId > 0
    )));
    if ($selectedIds === []) {
        flash('warning', __('send.none_selected'));
        redirect(base_url('outgoing/batch.php?id=' . $batchId));
    }
    $summary = dms_send_outgoing_batch_channel($batchId, $channel, $user, $selectedIds);
    if ((int) ($summary['total'] ?? 0) === 0) {
        flash('warning', __('send.none_selected'));
        redirect(base_url('outgoing/batch.php?id=' . $batchId));
    }
    dms_send_flash_from_batch($summary);
    redirect(base_url('outgoing/batch.php?id=' . $batchId));
}

$corr = $corrId ? get_correspondence($corrId) : null;
if (!$corr || ($corr['type'] ?? '') !== 'outgoing' || !user_can_access_correspondence($corr, $user)) {
    flash('danger', __('corr.not_found'));
    redirect(base_url('outgoing/index.php'));
}

$result = dms_send_outgoing_channel($corr, $channel, $user);
dms_send_flash_from_result($result);
if ($returnBatch > 0 && (int) ($corr['batch_id'] ?? 0) === $returnBatch) {
    redirect(base_url('outgoing/batch.php?id=' . $returnBatch));
}
redirect(base_url('outgoing/view.php?id=' . $corrId));
