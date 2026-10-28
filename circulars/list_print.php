<?php
/**
 * طباعة قائمة التعاميم حسب الفلاتر الحالية.
 */
require_once __DIR__ . '/../config/auth.php';
$user = require_login();

$filters = [
    'q' => trim((string) ($_GET['q'] ?? '')),
    'status' => (string) ($_GET['status'] ?? ''),
    'from' => (string) ($_GET['from'] ?? ''),
    'to' => (string) ($_GET['to'] ?? ''),
    'hijri_from' => trim((string) ($_GET['hijri_from'] ?? '')),
    'hijri_to' => trim((string) ($_GET['hijri_to'] ?? '')),
    'department_id' => $_GET['department_id'] ?? '',
];

$printLimit = 2000;
$total = circular_list_count($filters, $user);
$rows = circular_list($filters, $user, min($printLimit + 1, max($total, 1)), 0);
$truncated = count($rows) > $printLimit;
if ($truncated) {
    $rows = array_slice($rows, 0, $printLimit);
}

$filterBits = [];
if ($filters['from'] !== '' || $filters['to'] !== '') {
    $filterBits[] = __('common.from') . ' ' . format_date($filters['from'] ?: null)
        . ' — ' . __('common.to') . ' ' . format_date($filters['to'] ?: null);
}
if ($filters['status'] !== '') {
    $filterBits[] = __('common.status') . ': ' . circular_status_label($filters['status']);
}
if ((int) $filters['department_id'] > 0) {
    foreach (departments_list() as $d) {
        if ((int) $d['id'] === (int) $filters['department_id']) {
            $filterBits[] = __('corr.department') . ': ' . department_display_name($d);
            break;
        }
    }
}
if ($filters['q'] !== '') {
    $filterBits[] = __('action.search') . ': ' . $filters['q'];
}
$period = $filterBits !== [] ? implode(' · ', $filterBits) : __('common.all');

$backQuery = http_build_query(array_filter([
    'q' => $filters['q'],
    'status' => $filters['status'],
    'from' => $filters['from'],
    'to' => $filters['to'],
    'hijri_from' => $filters['hijri_from'],
    'hijri_to' => $filters['hijri_to'],
    'department_id' => $filters['department_id'],
], static fn($v) => $v !== '' && $v !== null));

$cssVer = @filemtime(__DIR__ . '/../assets/css/app.css') ?: time();
?>
<!DOCTYPE html>
<html lang="<?= e(dms_lang_code()) ?>" dir="<?= e(dms_page_dir()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(__('circular.report_title')) ?></title>
    <link rel="icon" href="<?= e(base_url('assets/img/favicon.ico')) ?>">
    <link href="<?= e(dms_bootstrap_css()) ?>" rel="stylesheet">
    <link href="<?= e(dms_bootstrap_icons_css()) ?>" rel="stylesheet">
    <link href="<?= e(base_url('assets/css/app.css')) ?>?v=<?= (int) $cssVer ?>" rel="stylesheet">
    <link href="<?= e(dms_print_fast_css()) ?>" rel="stylesheet">
    <style>
      @page { size: A4 portrait; margin: 0; }
    </style>
</head>
<body class="print-page print-report">
<div class="print-toolbar no-print">
    <div class="print-toolbar-title"><?= e(__('circular.report_title')) ?></div>
    <div class="d-flex flex-wrap gap-2">
        <button type="button" class="btn btn-kff btn-sm" onclick="window.print()">
            <i class="bi bi-printer me-1"></i><?= e(__('action.print')) ?>
        </button>
        <a class="btn btn-outline-secondary btn-sm" href="<?= e(base_url('circulars/index.php' . ($backQuery !== '' ? '?' . $backQuery : ''))) ?>"><?= e(__('action.back')) ?></a>
    </div>
</div>

<div class="print-sheet print-sheet-wide print-report-sheet">
    <div class="print-report-body">
        <?php render_print_logo('print'); ?>

        <div class="print-meta-row print-meta-row-center">
            <div class="text-center w-100">
                <h1 class="print-doc-title"><?= e(__('circular.report_title')) ?></h1>
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

        <div class="print-report-section"><?= e(__('circular.report_details')) ?></div>
        <table class="print-report-table">
            <thead>
                <tr>
                    <th><?= e(__('common.serial')) ?></th>
                    <th><?= e(__('circular.ref')) ?></th>
                    <th><?= e(__('circular.subject')) ?></th>
                    <th><?= e(__('circular.date')) ?></th>
                    <th><?= e(__('corr.department')) ?></th>
                    <th><?= e(__('circular.audience')) ?></th>
                    <th><?= e(__('common.status')) ?></th>
                    <th><?= e(__('circular.files')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="8" class="text-center"><?= e(__('circular.no_items')) ?></td></tr>
            <?php endif; ?>
            <?php $seq = 0; foreach ($rows as $r): $seq++; ?>
                <tr>
                    <td class="c-seq"><?= (int) $seq ?></td>
                    <td class="c-ref" dir="ltr"><?= e((string) ($r['ref_number'] ?? '')) ?></td>
                    <td><?= e((string) ($r['subject'] ?? '')) ?></td>
                    <td><?= e(format_date($r['circular_date'] ?? null)) ?></td>
                    <td><?= e(department_display_name($r, 'department_name', 'department_name_en') ?: __('common.none')) ?></td>
                    <td><?= e(circular_audience_label((string) ($r['audience'] ?? ''))) ?></td>
                    <td><?= e(circular_status_label((string) ($r['status'] ?? ''))) ?></td>
                    <td class="c-seq"><?= (int) ($r['files_count'] ?? 0) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($truncated): ?>
                <tr>
                    <td colspan="8" class="text-center">
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
