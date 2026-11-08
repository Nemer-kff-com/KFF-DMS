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
$waOn = (int) (dms_get_whatsapp_settings()['is_active'] ?? 0) === 1;
$sheetDate = format_date($items[0]['corr_date'] ?? $batch['created_at'] ?? null);
$itemIds = array_map(static fn(array $row): int => (int) $row['id'], $items);
$lastMap = function_exists('dms_corr_last_deliveries_map')
    ? dms_corr_last_deliveries_map($itemIds)
    : [];

$batchTotal = count($items);
$emailSent = 0;
$emailPending = 0;
$anySent = 0;
$deliverySent = 0;
$electronicCount = 0;
$paperCount = 0;
foreach ($items as $row) {
    $last = $lastMap[(int) $row['id']] ?? ['email' => null, 'whatsapp' => null, 'delivery' => null];
    $hasEmail = dms_corr_email_address($row) !== '';
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
        if (($last['email']['status'] ?? '') === 'sent') {
            $emailSent++;
        } else {
            $emailPending++;
        }
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
    @page {
      size: A4 landscape;
      margin: 8mm;
    }
    @media print {
      @page {
        size: A4 landscape;
        margin: 8mm;
      }
      html, body {
        height: auto !important;
        min-height: 0 !important;
        margin: 0 !important;
        padding: 0 !important;
        overflow: visible !important;
        background: #fff !important;
      }
      body.print-recipients-page .print-sheet,
      body.print-recipients-page .print-sheet-recipients {
        display: block !important;
        width: 100% !important;
        min-height: 0 !important;
        height: auto !important;
        margin: 0 !important;
        padding: 0 !important;
        page-break-after: auto !important;
      }
      body.print-recipients-page .print-recipients-closing {
        page-break-inside: avoid !important;
        break-inside: avoid !important;
      }
      body.print-recipients-page .print-recipients-summary {
        page-break-after: avoid !important;
        break-after: avoid !important;
      }
      body.print-recipients-page .print-page-footer,
      body.print-recipients-page .print-contact {
        position: static !important;
        margin-top: 0 !important;
        page-break-before: avoid !important;
        break-before: avoid !important;
      }
      body.print-recipients-page .print-recipients-table tr {
        page-break-inside: auto !important;
        break-inside: auto !important;
      }
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

    <table class="table table-bordered print-recipients-table<?= $waOn ? '' : ' no-wa' ?>">
        <thead>
            <tr>
                <th class="print-col-num">#</th>
                <th class="print-col-ref"><?= e(__('corr.number')) ?></th>
                <th class="print-col-party"><?= e(__('corr.party_to')) ?></th>
                <th class="print-col-mode"><?= e(__('bulk.delivery_mode')) ?></th>
                <th class="print-col-email"><?= e(__('entities.email')) ?></th>
                <?php if ($waOn): ?>
                <th class="print-col-wa"><?= e(__('send.whatsapp')) ?></th>
                <?php endif; ?>
                <th class="print-col-status"><?= e(__('corr.status')) ?></th>
                <th class="print-col-receipt"><?= e(__('bulk.receipt_data')) ?></th>
                <?php if ($rsvpOn): ?>
                <th class="print-col-rsvp"><?= e(__('rsvp.status')) ?></th>
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
                    <?php if ($prefix === '' && $person === '' && $honorific === ''): ?>
                        <span class="text-muted">—</span>
                    <?php else: ?>
                        <?php if ($prefix !== ''): ?><div class="print-party-prefix"><?= e($prefix) ?></div><?php endif; ?>
                        <div class="print-party-name">
                            <?php if ($person !== ''): ?><?= e($person) ?><?php endif; ?>
                            <?php if ($honorific !== ''): ?>
                                <span class="print-honorific"><?= $person !== '' ? ' ' : '' ?><?= e($honorific) ?></span>
                            <?php endif; ?>
                            <?php if ($person === '' && $honorific === '' && $prefix !== ''): ?>—<?php endif; ?>
                        </div>
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
                <?php if ($waOn): ?>
                <td class="print-col-wa">
                    <?php if ($mobile !== ''): ?>
                        <div dir="ltr">+<?= e($mobile) ?></div>
                        <div class="print-channel-status"><?= e(dms_delivery_status_label($last['whatsapp'] ?? null)) ?></div>
                    <?php else: ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <?php endif; ?>
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
                        <div class="print-cell-line">
                            <?php if ($receiptName !== ''): ?>
                                <span class="print-receipt-name"><?= e($receiptName) ?></span>
                            <?php elseif ($receiptPhone !== ''): ?>
                                <span dir="ltr"><?= e($receiptPhone) ?></span>
                            <?php elseif ($receiptDate !== ''): ?>
                                <span dir="ltr"><?= e(format_date($receiptDate)) ?></span>
                            <?php else: ?>
                                <span class="print-receipt-slip"><?= e(__('receipt.slip_attached')) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="print-cell-line print-cell-line-muted">
                            <?php
                            $receiptLine2 = [];
                            if ($receiptName !== '' && $receiptPhone !== '') {
                                $receiptLine2[] = '<span dir="ltr">' . e($receiptPhone) . '</span>';
                            }
                            if ($receiptDate !== '' && !($receiptName === '' && $receiptPhone === '' && $receiptFile === '')) {
                                $receiptLine2[] = '<span dir="ltr">' . e(format_date($receiptDate)) . '</span>';
                            }
                            if ($receiptFile !== '' && !($receiptName === '' && $receiptPhone === '' && $receiptDate === '')) {
                                $receiptLine2[] = '<span class="print-receipt-slip">' . e(__('receipt.slip_attached')) . '</span>';
                            }
                            echo $receiptLine2 !== [] ? implode('<span class="print-receipt-sep">·</span>', $receiptLine2) : '&nbsp;';
                            ?>
                        </div>
                    <?php else: ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <?php if ($rsvpOn): ?>
                <td class="print-col-rsvp">
                    <div class="print-cell-line print-rsvp-response"><?= e(rsvp_response_label($r['rsvp_response'] ?? null, $r['rsvp_mode'] ?? null)) ?></div>
                    <?php
                    $rsvpNote = trim((string) ($r['rsvp_note'] ?? ''));
                    $rsvpAt = !empty($r['rsvp_at']) ? format_datetime((string) $r['rsvp_at']) : '';
                    ?>
                    <div class="print-cell-line print-cell-line-muted">
                        <?php if ($rsvpNote !== '' || $rsvpAt !== ''): ?>
                            <?php if ($rsvpNote !== ''): ?><span class="print-rsvp-note"><?= e($rsvpNote) ?></span><?php endif; ?>
                            <?php if ($rsvpNote !== '' && $rsvpAt !== ''): ?><span class="print-receipt-sep">·</span><?php endif; ?>
                            <?php if ($rsvpAt !== ''): ?><span dir="ltr"><?= e($rsvpAt) ?></span><?php endif; ?>
                        <?php else: ?>
                            &nbsp;
                        <?php endif; ?>
                    </div>
                </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="print-recipients-closing">
        <div class="print-recipients-summary<?= $rsvpOn ? ' has-rsvp' : '' ?>">
            <div class="print-recipients-summary-total">
                <span class="print-recipients-summary-total-label"><?= e(__('bulk.summary')) ?></span>
                <strong class="print-recipients-summary-total-num"><?= (int) $batchTotal ?></strong>
            </div>
            <div class="print-recipients-summary-grid">
                <section class="print-recipients-summary-block" aria-label="<?= e(__('bulk.delivery_mode')) ?>">
                    <h3><?= e(__('bulk.delivery_mode')) ?></h3>
                    <dl>
                        <div><dt><?= e(__('bulk.delivery_electronic')) ?></dt><dd><?= (int) $electronicCount ?></dd></div>
                        <div><dt><?= e(__('bulk.delivery_paper')) ?></dt><dd><?= (int) $paperCount ?></dd></div>
                    </dl>
                </section>
                <section class="print-recipients-summary-block" aria-label="<?= e(__('common.status')) ?>">
                    <h3><?= e(__('common.status')) ?></h3>
                    <dl>
                        <div><dt><?= e(__('send.stat_sent')) ?></dt><dd class="is-ok"><?= (int) $anySent ?></dd></div>
                        <div><dt><?= e(__('send.stat_unsent')) ?></dt><dd<?= $batchUnsent > 0 ? ' class="is-warn"' : '' ?>><?= (int) $batchUnsent ?></dd></div>
                        <div><dt><?= e(__('send.email')) ?></dt><dd><?= (int) $emailSent ?></dd></div>
                        <div><dt><?= e(__('send.stat_unsent')) ?> — <?= e(__('send.email')) ?></dt><dd><?= (int) $emailPending ?></dd></div>
                        <div><dt><?= e(__('send.delivery')) ?></dt><dd><?= (int) $deliverySent ?></dd></div>
                    </dl>
                </section>
                <?php if ($rsvpOn): ?>
                <section class="print-recipients-summary-block" aria-label="<?= e(__('rsvp.replies')) ?>">
                    <h3><?= e(__('rsvp.replies')) ?></h3>
                    <dl>
                        <div><dt><?= e(__('rsvp.mode_in_person_short')) ?></dt><dd class="is-ok"><?= (int) ($rsvpSummary['in_person'] ?? 0) ?></dd></div>
                        <div><dt><?= e(__('rsvp.mode_remote')) ?></dt><dd class="is-ok"><?= (int) ($rsvpSummary['remote'] ?? 0) ?></dd></div>
                        <div><dt><?= e(__('rsvp.declined')) ?></dt><dd class="is-danger"><?= (int) ($rsvpSummary['declined'] ?? 0) ?></dd></div>
                        <div><dt><?= e(__('rsvp.pending')) ?></dt><dd class="is-muted"><?= (int) ($rsvpSummary['pending'] ?? 0) ?></dd></div>
                    </dl>
                </section>
                <?php endif; ?>
            </div>
        </div>
        <?php render_print_page_footer(); ?>
    </div>
</div>
<script src="<?= e(dms_bootstrap_js()) ?>"></script>
<script src="<?= e(base_url('assets/js/app.js')) ?>?v=<?= (int) $jsVer ?>"></script>
</body>
</html>
