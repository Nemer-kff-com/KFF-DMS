<?php
/**
 * كشف المستلمين — للعرض والطباعة
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

$cssVer = @filemtime(__DIR__ . '/../assets/css/app.css') ?: time();
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
<div class="print-toolbar no-print">
    <div class="print-toolbar-title"><?= e(__('bulk.results_sheet')) ?></div>
    <div class="d-flex flex-wrap gap-2 align-items-center">
        <?= dms_print_icon() ?>
        <?= dms_back_icon(base_url('outgoing/batch.php?id=' . $id)) ?>
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
        <div class="print-recipients-meta-count"><strong><?= e(__('bulk.recipients')) ?>:</strong> <?= count($items) ?></div>
    </div>

    <table class="table table-bordered print-recipients-table print-batch-results-table">
        <thead>
            <tr>
                <th><?= e(__('corr.number')) ?></th>
                <th><?= e(__('corr.party_to')) ?></th>
                <th><?= e(__('entities.section_basic')) ?></th>
                <th><?= e(__('bulk.delivery_mode')) ?></th>
                <th><?= e(__('send.email')) ?></th>
                <th><?= e(__('send.whatsapp')) ?></th>
                <th><?= e(__('corr.status')) ?></th>
                <th><?= e(__('rsvp.title')) ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($items as $r): ?>
            <?php
            $last = $lastMap[(int) $r['id']] ?? ['email' => null, 'whatsapp' => null];
            $email = dms_corr_email_address($r);
            $mobile = dms_corr_mobile_number($r);
            $party = correspondence_archived_party($r);
            $prefix = trim((string) ($party['prefix'] ?? ''));
            $person = trim((string) ($party['person'] ?? ''));
            $honorific = trim((string) ($party['honorific'] ?? ''));
            $job = trim((string) ($party['job'] ?? ''));
            $org = trim((string) ($party['org'] ?? ''));
            if ($org !== '' && $person !== '' && $org === $person) {
                $org = '';
            }
            ?>
            <tr>
                <td dir="ltr"><?= e((string) $r['ref_number']) ?></td>
                <td>
                    <?php if ($prefix !== ''): ?><div class="small text-muted"><?= e($prefix) ?></div><?php endif; ?>
                    <?php if ($person !== '' || $honorific !== ''): ?>
                        <div>
                            <?php if ($person !== ''): ?><?= e($person) ?><?php endif; ?>
                            <?php if ($honorific !== ''): ?><span class="small text-muted"><?= $person !== '' ? ' ' : '' ?><?= e($honorific) ?></span><?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($prefix === '' && $person === '' && $honorific === ''): ?>
                        <span class="text-muted"><?= e(__('common.none')) ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($job !== ''): ?><div class="small text-muted"><?= e($job) ?></div><?php endif; ?>
                    <?php if ($org !== ''): ?><div><?= e($org) ?></div><?php endif; ?>
                    <?php if ($job === '' && $org === ''): ?>
                        <span class="text-muted"><?= e(__('common.none')) ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php
                    $mode = function_exists('correspondence_delivery_mode')
                        ? correspondence_delivery_mode($r)
                        : 'paper';
                    $modeLabel = match ($mode) {
                        'electronic' => __('bulk.delivery_electronic'),
                        'both' => __('bulk.delivery_both'),
                        default => __('bulk.delivery_paper'),
                    };
                    ?>
                    <?= e($modeLabel) ?>
                    <?php if ($mode !== 'electronic'): ?>
                        <?php $receiptName = trim((string) ($r['receipt_name'] ?? '')); ?>
                        <?php $receiptPhone = trim((string) ($r['receipt_phone'] ?? '')); ?>
                        <?php if ($receiptName !== '' || $receiptPhone !== '' || !empty($r['receipt_file_path'])): ?>
                            <?php if ($receiptName !== ''): ?><div class="small"><?= e($receiptName) ?></div><?php endif; ?>
                            <?php if ($receiptPhone !== ''): ?><div class="small" dir="ltr"><?= e($receiptPhone) ?></div><?php endif; ?>
                            <?php if (!empty($r['receipt_date'])): ?>
                                <div class="small text-muted" dir="ltr"><?= e(format_date((string) $r['receipt_date'])) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($r['receipt_file_path'])): ?>
                                <div class="small"><?= e(__('receipt.slip_attached')) ?></div>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($mode !== 'paper'): ?>
                        <div dir="ltr"><?= e($email !== '' ? $email : '') ?></div>
                        <div class="small text-muted"><?= e(dms_delivery_status_label($last['email'] ?? null)) ?></div>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($mode !== 'paper'): ?>
                        <div dir="ltr"><?= e($mobile !== '' ? ('+' . $mobile) : '') ?></div>
                        <div class="small text-muted"><?= e(dms_delivery_status_label($last['whatsapp'] ?? null)) ?></div>
                    <?php endif; ?>
                </td>
                <td><?= status_badge((string) ($r['status'] ?? ''), $r['cancel_kind'] ?? null) ?></td>
                <td>
                    <?php if ($rsvpOn): ?>
                        <div><?= e(rsvp_response_label($r['rsvp_response'] ?? null, $r['rsvp_mode'] ?? null)) ?></div>
                        <?php $rsvpNote = trim((string) ($r['rsvp_note'] ?? '')); ?>
                        <?php if ($rsvpNote !== ''): ?><div class="small"><?= e($rsvpNote) ?></div><?php endif; ?>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php render_print_page_footer(); ?>
</div>
<script src="<?= e(dms_bootstrap_js()) ?>"></script>
<script src="<?= e(base_url('assets/js/app.js')) ?>?v=<?= (int) (@filemtime(__DIR__ . '/../assets/js/app.js') ?: 24) ?>"></script>
</body>
</html>
