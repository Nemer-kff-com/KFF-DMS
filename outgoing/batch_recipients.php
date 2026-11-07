<?php
/**
 * كشف نتائج الدفعة — للعرض والطباعة (ملخص الدفعة + كل المعاملات)
 */
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/correspondence.php';
$user = require_login();
require_outgoing_batch_view($user);
ensure_entity_groups_schema();
ensure_corr_rsvp_schema();

$id = (int) ($_GET['id'] ?? 0);
$batch = $id ? get_outgoing_batch($id) : null;
$items = $id ? outgoing_batch_items($id, $user) : [];
if (!$batch || !$items) {
    flash('danger', __('corr.not_found'));
    redirect(base_url('outgoing/index.php'));
}

$rsvpOn = !empty($batch['requires_rsvp']) || correspondence_rsvp_required($items[0] ?? []);
$sheetDate = format_date($items[0]['corr_date'] ?? $batch['created_at'] ?? null);
$itemIds = array_map(static fn(array $row): int => (int) $row['id'], $items);
$lastMap = function_exists('dms_corr_last_deliveries_map')
    ? dms_corr_last_deliveries_map($itemIds)
    : [];

$batchTotal = count($items);
$emailCount = 0;
$mobileCount = 0;
$emailSent = 0;
$emailPending = 0;
$anySent = 0;
$deliverySent = 0;
$electronicCount = 0;
$paperCount = 0;
foreach ($items as $row) {
    $last = $lastMap[(int) $row['id']] ?? ['email' => null, 'whatsapp' => null, 'delivery' => null];
    $hasEmail = dms_corr_email_address($row) !== '';
    $hasMobile = dms_corr_mobile_number($row) !== '';
    $rowCancelled = correspondence_is_cancelled($row);
    $rowElectronic = function_exists('correspondence_intend_electronic') && correspondence_intend_electronic($row);
    $rowPaper = function_exists('correspondence_intend_paper') && correspondence_intend_paper($row);
    if (!$rowCancelled) {
        if ($rowElectronic) {
            $electronicCount++;
        }
        if ($rowPaper) {
            $paperCount++;
        }
    }
    if ($hasEmail) {
        $emailCount++;
        if (($last['email']['status'] ?? '') === 'sent') {
            $emailSent++;
        } else {
            $emailPending++;
        }
    }
    if ($hasMobile) {
        $mobileCount++;
    }
    $sent = (($last['email']['status'] ?? '') === 'sent')
        || (($last['whatsapp']['status'] ?? '') === 'sent')
        || (($last['delivery']['status'] ?? '') === 'sent');
    if ($sent) {
        $anySent++;
    }
    if (($last['delivery']['status'] ?? '') === 'sent') {
        $deliverySent++;
    }
}
$batchUnsent = $batchTotal - (int) $anySent;
$rsvpSummary = function_exists('batch_rsvp_summary') ? batch_rsvp_summary($items) : [
    'in_person' => 0,
    'remote' => 0,
    'declined' => 0,
    'pending' => 0,
];

$cssVer = @filemtime(__DIR__ . '/../assets/css/app.css') ?: time();
$jsVer = @filemtime(__DIR__ . '/../assets/js/app.js') ?: 24;
?>
<!DOCTYPE html>
<html lang="<?= e(dms_lang_code()) ?>" dir="<?= e(dms_page_dir()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(__('bulk.results_sheet')) ?> — <?= e((string) $batch['subject']) ?></title>
    <?= function_exists('dms_favicon_link_html') ? dms_favicon_link_html() : '' ?>
    <link href="<?= e(dms_bootstrap_css()) ?>" rel="stylesheet">
    <link href="<?= e(dms_bootstrap_icons_css()) ?>" rel="stylesheet">
    <link href="<?= e(base_url('assets/css/app.css')) ?>?v=<?= (int) $cssVer ?>" rel="stylesheet">
    <link href="<?= e(dms_print_fast_css()) ?>" rel="stylesheet">
    <style>
    @page recipients { size: A4 landscape; margin: 10mm; }
    @media print {
      @page recipients { size: A4 landscape; margin: 10mm; }
    }
    </style>
</head>
<body class="print-page print-recipients-page">
<div class="print-toolbar print-label-toolbar no-print">
    <div class="print-label-toolbar-main">
        <div class="print-label-toolbar-top">
            <div class="print-label-toolbar-actions">
                <?= dms_print_icon() ?>
                <?= dms_back_icon(base_url('outgoing/batch.php?id=' . $id)) ?>
            </div>
            <div class="print-toolbar-title">
                <?= e(__('bulk.results_sheet')) ?>
                <span class="text-muted">(<?= (int) $batchTotal ?>)</span>
            </div>
            <div class="print-label-toolbar-actions print-label-toolbar-actions-ghost" aria-hidden="true">
                <?= dms_print_icon_ghost() ?>
                <?= dms_back_icon() ?>
            </div>
        </div>
    </div>
</div>

<div class="print-sheet print-sheet-recipients">
    <?php render_print_logo('print'); ?>

    <h1 class="print-recipients-title"><?= e(__('bulk.results_sheet')) ?></h1>
    <div class="print-recipients-meta">
        <div class="print-recipients-meta-subject"><strong><?= e(__('corr.subject')) ?>:</strong> <?= e((string) $batch['subject']) ?></div>
        <div class="print-recipients-meta-date">
            <strong><?= e(__('corr.date')) ?>:</strong> <span dir="ltr"><?= e($sheetDate) ?></span>
        </div>
    </div>

    <table class="table table-bordered print-recipients-table">
        <thead>
            <tr>
                <th class="print-col-num">#</th>
                <th><?= e(__('corr.number')) ?></th>
                <th><?= e(__('corr.party_to')) ?></th>
                <th><?= e(__('bulk.delivery_mode')) ?></th>
                <th><?= e(__('entities.email')) ?></th>
                <th><?= e(__('send.whatsapp')) ?></th>
                <th><?= e(__('corr.status')) ?></th>
                <th><?= e(__('bulk.receipt_data')) ?></th>
                <?php if ($rsvpOn): ?>
                <th><?= e(__('rsvp.status')) ?></th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($items as $i => $r): ?>
            <?php
            $last = $lastMap[(int) $r['id']] ?? ['email' => null, 'whatsapp' => null, 'delivery' => null];
            $email = dms_corr_email_address($r);
            $mobile = dms_corr_mobile_number($r);
            $party = correspondence_archived_party($r);
            $prefix = trim((string) ($party['prefix'] ?? ''));
            $person = trim((string) ($party['person'] ?? ''));
            $honorific = trim((string) ($party['honorific'] ?? ''));
            $mode = function_exists('correspondence_delivery_mode')
                ? correspondence_delivery_mode($r)
                : 'paper';
            $modeLabel = match ($mode) {
                'electronic' => __('bulk.delivery_electronic'),
                'both' => __('bulk.delivery_both'),
                default => __('bulk.delivery_paper'),
            };
            $receiptName = trim((string) ($r['receipt_name'] ?? ''));
            $receiptPhone = trim((string) ($r['receipt_phone'] ?? ''));
            $receiptDate = trim((string) ($r['receipt_date'] ?? ''));
            $receiptFile = trim((string) ($r['receipt_file_path'] ?? ''));
            $hasReceipt = $receiptName !== '' || $receiptPhone !== '' || $receiptDate !== '' || $receiptFile !== '';
            $rowCancelled = correspondence_is_cancelled($r);
            ?>
            <tr<?= $rowCancelled ? ' class="is-cancelled-row"' : '' ?>>
                <td class="print-col-num"><?= $i + 1 ?></td>
                <td class="print-col-ref" dir="ltr"><?= e((string) $r['ref_number']) ?></td>
                <td class="print-col-party">
                    <?php if ($prefix !== ''): ?><div class="print-party-prefix"><?= e($prefix) ?></div><?php endif; ?>
                    <?php if ($person !== '' || $honorific !== ''): ?>
                        <div class="print-party-name">
                            <?php if ($person !== ''): ?><?= e($person) ?><?php endif; ?>
                            <?php if ($honorific !== ''): ?>
                                <span class="print-honorific"><?= $person !== '' ? ' ' : '' ?><?= e($honorific) ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($prefix === '' && $person === '' && $honorific === ''): ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <td class="print-col-mode"><?= e($modeLabel) ?></td>
                <td class="print-col-email">
                    <?php if ($email !== ''): ?>
                        <div dir="ltr"><?= e($email) ?></div>
                        <div class="print-channel-status"><?= e(dms_delivery_status_label($last['email'] ?? null)) ?></div>
                    <?php else: ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <td class="print-col-wa">
                    <?php if ($mobile !== ''): ?>
                        <div dir="ltr">+<?= e($mobile) ?></div>
                        <div class="print-channel-status"><?= e(dms_delivery_status_label($last['whatsapp'] ?? null)) ?></div>
                    <?php else: ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <td class="print-col-status">
                    <?php
                    $statusCode = (string) ($r['status'] ?? '');
                    $cancelKind = (string) ($r['cancel_kind'] ?? '');
                    $statusText = ($statusCode === 'cancelled' && $cancelKind !== '' && function_exists('correspondence_cancel_kind_label'))
                        ? correspondence_cancel_kind_label($cancelKind)
                        : status_label($statusCode);
                    ?>
                    <?= status_badge($statusCode, $cancelKind !== '' ? $cancelKind : null) ?>
                    <div class="print-status-text"><?= e($statusText) ?></div>
                </td>
                <td class="print-col-receipt">
                    <?php if ($hasReceipt): ?>
                        <?php if ($receiptName !== ''): ?><div><?= e($receiptName) ?></div><?php endif; ?>
                        <?php if ($receiptPhone !== ''): ?><div dir="ltr"><?= e($receiptPhone) ?></div><?php endif; ?>
                        <?php if ($receiptDate !== ''): ?><div class="text-muted" dir="ltr"><?= e(format_date($receiptDate)) ?></div><?php endif; ?>
                        <?php if ($receiptFile !== ''): ?><div class="print-receipt-slip"><?= e(__('receipt.slip_attached')) ?></div><?php endif; ?>
                    <?php else: ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <?php if ($rsvpOn): ?>
                <td class="print-col-rsvp">
                    <div><?= e(rsvp_response_label($r['rsvp_response'] ?? null, $r['rsvp_mode'] ?? null)) ?></div>
                    <?php $rsvpNote = trim((string) ($r['rsvp_note'] ?? '')); ?>
                    <?php if ($rsvpNote !== ''): ?><div class="print-rsvp-note"><?= e($rsvpNote) ?></div><?php endif; ?>
                    <?php if (!empty($r['rsvp_at'])): ?>
                        <div class="text-muted" dir="ltr"><?= e(format_datetime($r['rsvp_at'])) ?></div>
                    <?php endif; ?>
                </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="print-recipients-summary batch-summary-panel">
        <div class="batch-summary-bar">
            <div class="batch-summary-head">
                <div class="batch-summary-metric">
                    <span class="batch-summary-title"><?= e(__('bulk.summary')) ?></span>
                    <span class="batch-summary-total">
                        <span class="batch-summary-total-num"><?= (int) $batchTotal ?></span>
                    </span>
                </div>
            </div>
            <div class="batch-summary-groups">
                <div class="batch-summary-group" role="group" aria-label="<?= e(__('bulk.delivery_mode')) ?>">
                    <span class="batch-summary-group-label"><?= e(__('bulk.delivery_mode')) ?></span>
                    <div class="batch-chips">
                        <span class="batch-chip is-electronic"><?= e(__('bulk.delivery_electronic')) ?> <b><?= (int) $electronicCount ?></b></span>
                        <span class="batch-chip is-paper"><?= e(__('bulk.delivery_paper')) ?> <b><?= (int) $paperCount ?></b></span>
                    </div>
                </div>
                <div class="batch-summary-group" role="group" aria-label="<?= e(__('bulk.recipients')) ?>">
                    <span class="batch-summary-group-label"><?= e(__('bulk.recipients')) ?></span>
                    <div class="batch-chips">
                        <span class="batch-chip"><?= e(__('entities.email')) ?> <b><?= (int) $emailCount ?>/<?= (int) $batchTotal ?></b></span>
                        <span class="batch-chip"><?= e(__('entities.mobile')) ?> <b><?= (int) $mobileCount ?>/<?= (int) $batchTotal ?></b></span>
                    </div>
                </div>
                <div class="batch-summary-group" role="group" aria-label="<?= e(__('common.status')) ?>">
                    <span class="batch-summary-group-label"><?= e(__('common.status')) ?></span>
                    <div class="batch-chips">
                        <span class="batch-chip is-ok"><?= e(__('send.stat_sent')) ?> <b><?= (int) $anySent ?></b></span>
                        <span class="batch-chip<?= $batchUnsent > 0 ? ' is-warn' : '' ?>"><?= e(__('send.stat_unsent')) ?> <b><?= (int) $batchUnsent ?></b></span>
                        <span class="batch-chip"><?= e(__('send.email')) ?> <b><?= (int) $emailSent ?></b> · <?= e(__('send.stat_unsent')) ?> <?= (int) $emailPending ?></span>
                        <span class="batch-chip"><?= e(__('send.delivery')) ?> <b><?= (int) $deliverySent ?></b></span>
                    </div>
                </div>
                <?php if ($rsvpOn): ?>
                <div class="batch-summary-group" role="group" aria-label="<?= e(__('rsvp.replies')) ?>">
                    <span class="batch-summary-group-label"><?= e(__('rsvp.replies')) ?></span>
                    <div class="batch-chips">
                        <span class="batch-chip is-ok"><?= e(__('rsvp.mode_in_person_short')) ?> <b><?= (int) ($rsvpSummary['in_person'] ?? 0) ?></b></span>
                        <span class="batch-chip is-ok"><?= e(__('rsvp.mode_remote')) ?> <b><?= (int) ($rsvpSummary['remote'] ?? 0) ?></b></span>
                        <span class="batch-chip is-danger"><?= e(__('rsvp.declined')) ?> <b><?= (int) ($rsvpSummary['declined'] ?? 0) ?></b></span>
                        <span class="batch-chip is-muted"><?= e(__('rsvp.pending')) ?> <b><?= (int) ($rsvpSummary['pending'] ?? 0) ?></b></span>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php render_print_page_footer(); ?>
</div>
<script src="<?= e(dms_bootstrap_js()) ?>"></script>
<script src="<?= e(base_url('assets/js/app.js')) ?>?v=<?= (int) $jsVer ?>"></script>
</body>
</html>
