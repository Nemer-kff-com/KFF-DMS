<?php
require_once __DIR__ . '/../../config/auth.php';
$user = require_archive_section($kind);
ensure_archive_schema();
$def = archive_register_def($kind);
if (!$def) {
    flash('danger', __('common.not_found'));
    redirect(base_url('archive/index.php'));
}
$filters = archive_register_request_filters($def);
$printLimit = 2000;
$list = archive_register_list($kind, $filters, 1, $printLimit + 1);
$total = (int) $list['total'];
$rows = $list['rows'];
$truncated = count($rows) > $printLimit;
if ($truncated) {
    $rows = array_slice($rows, 0, $printLimit);
}
$period = archive_register_filter_summary($def, $filters);
$backQuery = archive_register_filter_query($filters);
$listCols = $def['list_cols'] ?? [];
$showFiles = $kind !== 'scholarships';
$colspan = count($listCols) + 1 + ($showFiles ? 1 : 0);
$cssVer = @filemtime(__DIR__ . '/../../assets/css/app.css') ?: time();
$title = __($def['label']);
?>
<!DOCTYPE html>
<html lang="<?= e(dms_lang_code()) ?>" dir="<?= e(dms_page_dir()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?></title>
    <link rel="icon" href="<?= e(base_url('assets/img/favicon.ico')) ?>">
    <link href="<?= e(dms_bootstrap_css()) ?>" rel="stylesheet">
    <link href="<?= e(dms_bootstrap_icons_css()) ?>" rel="stylesheet">
    <link href="<?= e(base_url('assets/css/app.css')) ?>?v=<?= (int) $cssVer ?>" rel="stylesheet">
    <link href="<?= e(dms_print_fast_css()) ?>" rel="stylesheet">
    <style>
      @page { size: A4 portrait; margin: 0; }
    </style>
</head>
<body class="print-page print-report print-register print-register-<?= e($kind) ?>">
<div class="print-toolbar no-print">
    <div class="print-toolbar-title"><?= e($title) ?></div>
    <div class="d-flex flex-wrap gap-2">
        <button type="button" class="btn btn-kff btn-sm" onclick="window.print()">
            <i class="bi bi-printer me-1"></i><?= e(__('action.print')) ?>
        </button>
        <a class="btn btn-outline-secondary btn-sm" href="<?= e(archive_register_url($kind, 'list') . ($backQuery !== '' ? '?' . $backQuery : '')) ?>">
            <i class="bi <?= dms_is_rtl() ? 'bi-arrow-right' : 'bi-arrow-left' ?> me-1"></i><?= e(__('action.back')) ?>
        </a>
    </div>
</div>
<div class="print-sheet print-report-sheet">
    <div class="print-report-body">
        <?php render_print_logo('print'); ?>
        <div class="print-meta-row print-meta-row-center">
            <div class="text-center w-100">
                <h1 class="print-doc-title"><?= e($title) ?></h1>
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
        <div class="print-report-section"><?= e(__('archive.report_details')) ?></div>
        <table class="print-report-table">
            <thead>
                <tr>
                    <th><?= e(__('common.serial')) ?></th>
                    <?php foreach ($listCols as $col): ?>
                        <th><?= e(__($col['label'])) ?></th>
                    <?php endforeach; ?>
                    <?php if ($showFiles): ?>
                        <th><?= e(__('archive.files')) ?></th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="<?= (int) $colspan ?>" class="text-center"><?= e(__('archive.no_items')) ?></td></tr>
            <?php endif; ?>
            <?php $seq = 0; foreach ($rows as $r): $seq++; ?>
                <tr>
                    <td class="c-seq"><?= (int) $seq ?></td>
                    <?php foreach ($listCols as $col): ?>
                        <?php
                        $txt = archive_register_list_cell_text($col, $r);
                        $cls = [];
                        if (($col['key'] ?? '') === 'archive_no' || ($col['key'] ?? '') === 'ARCHIVE_NUM' || ($col['key'] ?? '') === 'ARCHIVE_NUMBER' || ($col['key'] ?? '') === 'KAIDNUMBER') {
                            $cls[] = 'c-ref';
                        }
                        if (($col['type'] ?? '') === 'hijri' || ($col['type'] ?? '') === 'date_pair') {
                            $cls[] = 'c-date';
                        }
                        $isPair = ($col['type'] ?? '') === 'date_pair';
                        ?>
                        <td class="<?= e(implode(' ', $cls)) ?>"<?= !$isPair && (!empty($col['ltr']) || ($col['type'] ?? '') === 'hijri') ? ' dir="ltr"' : '' ?>><?php
                            if ($isPair) {
                                echo archive_register_date_pair_html($col, $r);
                            } else {
                                echo $txt !== '' ? e($txt) : '';
                            }
                        ?></td>
                    <?php endforeach; ?>
                    <?php if ($showFiles): ?>
                        <td class="c-seq"><?= (int) count(archive_register_folder_files(archive_register_guess_folder($def, $r))) ?></td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            <?php if ($truncated): ?>
                <tr>
                    <td colspan="<?= (int) $colspan ?>" class="text-center">
                        <?= e(str_replace(':count', (string) $printLimit, __('archive.print_truncated'))) ?>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
            <tfoot class="print-table-footer-space">
                <tr>
                    <td colspan="<?= (int) $colspan ?>"></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php render_print_page_footer(); ?>
</div>
<script src="<?= e(dms_bootstrap_js()) ?>"></script>
<script src="<?= e(base_url('assets/js/app.js')) ?>?v=<?= (int) (@filemtime(__DIR__ . '/../../assets/js/app.js') ?: 24) ?>"></script>
<script>
</script>
</body>
</html>
