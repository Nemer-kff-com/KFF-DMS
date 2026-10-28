<?php
/**
 * جزء ورقة طباعة المعاملة — يتطلب: $corr, $qrDomId
 * اختياري: $dirOrder (to|from), $directionTableId, $typeLabel
 */
$isOutgoing = ($corr['type'] ?? '') === 'outgoing';
$isInternal = ($corr['type'] ?? '') === 'internal';
if (!isset($typeLabel)) {
    $typeLabel = $isInternal
        ? __('print.type_internal')
        : ($isOutgoing ? __('print.type_outgoing') : __('print.type_incoming'));
}
$dirOrder = (($dirOrder ?? 'to') === 'from') ? 'from' : 'to';
$directionTableId = (string) ($directionTableId ?? 'printDirection');
$qrDomId = (string) ($qrDomId ?? 'qrcode');
$isCancelled = correspondence_is_cancelled($corr);
$cancelMark = $isCancelled ? correspondence_cancel_kind_label((string) ($corr['cancel_kind'] ?? 'void')) : '';
?>
<div class="print-sheet<?= $isCancelled ? ' is-cancelled-sheet' : '' ?>" dir="rtl"<?= $isCancelled ? ' data-cancel-mark="' . e($cancelMark) . '"' : '' ?>>
    <div class="print-header">
            <div class="print-qr-block" dir="rtl">
                <?php render_print_qr_box($qrDomId, $corr); ?>
                <div class="print-doc-ref"><?= e(format_ref_caption((string) ($corr['type'] ?? ''), $corr['ref_number'] ?? '')) ?></div>
            <?php if (!empty($corr['corr_date'])): ?>
            <?php $printHijriDate = format_hijri_date((string) $corr['corr_date']); ?>
            <?php if ($printHijriDate !== ''): ?>
            <div class="print-doc-date-hijri">
                <span><?= e($printHijriDate) ?></span>
                <span class="print-doc-date-mark">هـ</span>
            </div>
            <?php endif; ?>
            <div class="print-doc-date-value">
                <span><?= e(date('Y/m/d', strtotime((string) $corr['corr_date']))) ?></span>
                <span class="print-doc-date-mark">م</span>
            </div>
            <?php endif; ?>
        </div>
        <?php render_print_logo('print'); ?>
        <div class="print-doc-type"><?= e($typeLabel) ?></div>
    </div>

    <table class="table table-bordered print-fields print-fields-subject">
        <tr>
            <th><?= e(__('corr.subject')) ?></th>
            <td class="print-subject-cell"><?= e($corr['subject']) ?></td>
        </tr>
    </table>

    <?php render_print_direction($corr, $dirOrder === 'from', $directionTableId); ?>

    <?php
    $priorityCode = strtolower(trim((string) ($corr['priority'] ?? '')));
    $confidentialityCode = strtolower(trim((string) ($corr['confidentiality'] ?? '')));
    $printFlags = [];
    if ($confidentialityCode !== '' && $confidentialityCode !== 'normal') {
        $printFlags[] = confidentiality_label($confidentialityCode);
    }
    if ($priorityCode !== '' && $priorityCode !== 'normal') {
        $printFlags[] = priority_label($priorityCode);
    }
    $linkedRef = trim((string) ($corr['linked_ref'] ?? ''));
    if ($linkedRef === '' && !empty($corr['linked_id']) && function_exists('get_correspondence')) {
        $linkedCorr = get_correspondence((int) $corr['linked_id']);
        $linkedRef = trim((string) ($linkedCorr['ref_number'] ?? ''));
    }
    $externalDate = corr_external_date_value($corr['external_date'] ?? '');
    $metaRows = [
        [__('corr.external_ref'), trim((string) ($corr['external_ref'] ?? ''))],
        [$isOutgoing ? __('corr.date') : __('corr.external_date'), $externalDate !== null ? format_date($externalDate) : ''],
        [__('corr.reply_due'), !empty($corr['reply_due']) ? format_date($corr['reply_due']) : ''],
        [$isOutgoing ? __('corr.link_in') : __('corr.link_out'), $linkedRef],
    ];
    $metaRows = array_values(array_filter(
        $metaRows,
        static fn(array $row): bool => trim((string) $row[1]) !== ''
    ));
    ?>
    <?php if ($printFlags || $metaRows): ?>
    <table class="table table-bordered print-fields print-fields-grid">
        <?php if ($printFlags): ?>
        <tr>
            <td class="print-corr-flags-cell" colspan="4">
                <?php foreach ($printFlags as $flag): ?>
                    <span class="print-corr-flag"><?= e($flag) ?></span>
                <?php endforeach; ?>
            </td>
        </tr>
        <?php endif; ?>
        <?php foreach (array_chunk($metaRows, 2) as $pair): ?>
        <tr>
            <?php foreach ($pair as [$label, $value]): ?>
            <th><?= e($label) ?></th><td><?= e($value) ?></td>
            <?php endforeach; ?>
            <?php if (count($pair) === 1): ?>
            <th class="print-cell-blank"></th><td class="print-cell-blank"></td>
            <?php endif; ?>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>

    <?php
    $enclosures = correspondence_enclosures((int) $corr['id']);
    if ($enclosures):
        $enclosureText = implode('، ', array_map('correspondence_enclosure_label', $enclosures));
    ?>
    <table class="table table-bordered print-fields print-fields-subject">
        <tr>
            <th><?= e(__('corr.enclosures')) ?></th>
            <td><?= e($enclosureText) ?></td>
        </tr>
    </table>
    <?php endif; ?>

    <?php render_print_receipt($corr); ?>
    <?php render_print_page_footer($corr); ?>
</div>
