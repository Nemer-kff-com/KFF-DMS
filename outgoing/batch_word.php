<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/correspondence.php';
require_once __DIR__ . '/../config/letter_docx.php';
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

letter_docx_send($items, $batch);
