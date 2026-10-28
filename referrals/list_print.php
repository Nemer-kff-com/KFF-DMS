<?php
/**
 * طباعة قائمة الإحالات حسب الفلتر الحالي.
 */
require_once __DIR__ . '/../config/auth.php';
$user = require_login();

$statusFilter = trim((string) ($_GET['status'] ?? ''));
$printLimit = 2000;
$total = referrals_list_count($statusFilter, $user);
$rows = referrals_list($statusFilter, $user, min($printLimit + 1, max($total, 1)), 0);
$truncated = count($rows) > $printLimit;
if ($truncated) {
    $rows = array_slice($rows, 0, $printLimit);
}

$period = $statusFilter !== ''
    ? (__('corr.status') . ': ' . status_label($statusFilter))
    : __('common.all');

$backQuery = $statusFilter !== '' ? ('?status=' . rawurlencode($statusFilter)) : '';
$cssVer = @filemtime(__DIR__ . '/../assets/css/app.css') ?: time();
?>
<!DOCTYPE html>
<html lang="<?= e(dms_lang_code()) ?>" dir="<?= e(dms_page_dir()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(__('ref.report_title')) ?></title>
    <link rel="icon" href="<?= e(base_url('assets/img/favicon.ico')) ?>">
    <link href="<?= e(dms_bootstrap_css()) ?>" rel="stylesheet">
    <link href="<?= e(dms_bootstrap_icons_css()) ?>" rel="stylesheet">
    <link href="<?= e(base_url('assets/css/app.css')) ?>?v=<?= (int) $cssVer ?>" rel="stylesheet">
    <link href="<?= e(dms_print_fast_css()) ?>" rel="stylesheet">
    <style>
      @page { size: A4 landscape; margin: 0; }
    </style>
</head>
<body class="print-page print-report">
<div class="print-toolbar no-print">
    <div class="print-toolbar-title"><?= e(__('ref.report_title')) ?></div>
    <div class="d-flex flex-wrap gap-2">
        <button type="button" class="btn btn-kff btn-sm" onclick="window.print()">
            <i class="bi bi-printer me-1"></i><?= e(__('action.print')) ?>
        </button>
        <a class="btn btn-outline-secondary btn-sm" href="<?= e(base_url('referrals/index.php' . $backQuery)) ?>"><?= e(__('action.back')) ?></a>
    </div>
</div>

<div class="print-sheet print-sheet-wide print-report-sheet">
    <div class="print-report-body">
        <?php render_print_logo('print'); ?>

        <div class="print-meta-row print-meta-row-center">
            <div class="text-center w-100">
                <h1 class="print-doc-title"><?= e(__('ref.report_title')) ?></h1>
                <div class="print-doc-ref print-report-period"><?= e($period) ?></div>
            </div>
        </div>

        <table class="print-report-stats">
            <tr>
                <th><?= e(__('common.total')) ?></th>
                <td><?= (int) $total ?></td>
                <th><?= e(__('action.print')) ?></th>
                <td><?= (int) count($rows) ?></td>
            </tr>
        </table>

        <div class="print-report-section"><?= e(__('ref.report_details')) ?></div>
        <table class="print-report-table">
            <thead>
                <tr>
                    <th><?= e(__('common.serial')) ?></th>
                    <th><?= e(__('corr.number')) ?></th>
                    <th><?= e(__('corr.type')) ?></th>
                    <th><?= e(__('corr.subject')) ?></th>
                    <th><?= e(__('ref.from')) ?></th>
                    <th><?= e(__('ref.to')) ?></th>
                    <th><?= e(__('ref.by')) ?></th>
                    <th><?= e(__('ref.date')) ?></th>
                    <th><?= e(__('corr.status')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="9" class="text-center"><?= e(__('ref.no_items')) ?></td></tr>
            <?php endif; ?>
            <?php $seq = 0; foreach ($rows as $r): $seq++; ?>
                <tr>
                    <td class="c-seq"><?= (int) $seq ?></td>
                    <td class="c-ref" dir="ltr"><?= e((string) ($r['ref_number'] ?? '')) ?></td>
                    <td><?= e(type_label((string) ($r['type'] ?? ''))) ?></td>
                    <td><?= e((string) ($r['subject'] ?? '')) ?></td>
                    <td><?php render_referral_party($r, 'from'); ?></td>
                    <td><?php render_referral_party($r, 'to'); ?></td>
                    <td><?= e(user_display_name($r, 'from_user_name', 'from_user_name_en')) ?></td>
                    <td><?= e(format_datetime($r['created_at'] ?? null)) ?></td>
                    <td><?= e(status_label((string) ($r['status'] ?? ''))) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($truncated): ?>
                <tr>
                    <td colspan="9" class="text-center">
                        <?= e(str_replace(':count', (string) $printLimit, __('reports.print_truncated'))) ?>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php render_print_page_footer(); ?>
</div>
<script src="<?= e(dms_bootstrap_js()) ?>"></script>
<script src="<?= e(base_url('assets/js/app.js')) ?>?v=<?= (int) (@filemtime(__DIR__ . '/../assets/js/app.js') ?: 24) ?>"></script>
<script>
</script>
</body>
</html>
