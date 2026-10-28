<?php
/**
 * طباعة قائمة الأقسام — A4 عمودي
 */
require_once __DIR__ . '/../config/auth.php';
$user = require_permission('admin.departments');
ensure_departments_schema();

$departments = departments_list(false);
$isSystemAdmin = can_manage_users($user);
$cssVer = @filemtime(__DIR__ . '/../assets/css/app.css') ?: time();
$colCount = $isSystemAdmin ? 5 : 4;
?>
<!DOCTYPE html>
<html lang="<?= e(dms_lang_code()) ?>" dir="<?= e(dms_page_dir()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(__('departments.print_title')) ?></title>
    <link rel="icon" href="<?= e(base_url('assets/img/favicon.ico')) ?>">
    <link href="<?= e(dms_bootstrap_css()) ?>" rel="stylesheet">
    <link href="<?= e(dms_bootstrap_icons_css()) ?>" rel="stylesheet">
    <link href="<?= e(base_url('assets/css/app.css')) ?>?v=<?= (int) $cssVer ?>" rel="stylesheet">
    <link href="<?= e(dms_print_fast_css()) ?>" rel="stylesheet">
    <style>
      @page { size: A4 portrait; margin: 0; }
    </style>
</head>
<body class="print-page print-report print-departments-page">
<div class="print-toolbar no-print">
    <div class="print-toolbar-title"><?= e(__('departments.print_title')) ?></div>
    <div class="d-flex flex-wrap gap-2">
        <button type="button" class="btn btn-kff btn-sm" onclick="window.print()">
            <i class="bi bi-printer me-1"></i><?= e(__('action.print')) ?>
        </button>
        <a class="btn btn-outline-secondary btn-sm" href="<?= e(base_url('admin/departments.php')) ?>">
            <i class="bi <?= dms_is_rtl() ? 'bi-arrow-right' : 'bi-arrow-left' ?> me-1"></i><?= e(__('action.back')) ?>
        </a>
    </div>
</div>

<div class="print-sheet print-report-sheet print-departments-sheet">
    <div class="print-report-body">
        <?php render_print_logo('print'); ?>

        <div class="print-meta-row print-meta-row-center">
            <div class="text-center w-100">
                <h1 class="print-doc-title"><?= e(__('departments.print_title')) ?></h1>
                <div class="print-doc-ref print-report-period">
                    <?= e(str_replace(':count', (string) count($departments), __('departments.print_count'))) ?>
                    · <?= e(format_datetime(date('Y-m-d H:i:s'))) ?>
                </div>
            </div>
        </div>

        <table class="print-report-table print-departments-table">
            <colgroup>
                <col class="c-num">
                <?php if ($isSystemAdmin): ?>
                <col class="c-sort">
                <?php endif; ?>
                <col class="c-name">
                <col class="c-receipt">
                <col class="c-status">
            </colgroup>
            <thead>
                <tr>
                    <th>#</th>
                    <?php if ($isSystemAdmin): ?>
                    <th><?= e(__('departments.sort')) ?></th>
                    <?php endif; ?>
                    <th><?= e(__('departments.col_name')) ?></th>
                    <th><?= e(__('departments.col_officer')) ?></th>
                    <th><?= e(__('common.status')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$departments): ?>
                <tr>
                    <td colspan="<?= (int) $colCount ?>" class="text-center"><?= e(__('common.no_data')) ?></td>
                </tr>
            <?php endif; ?>
            <?php foreach ($departments as $i => $d):
                $titlePrefix = trim((string) ($d['title_prefix'] ?? ''));
                $directedFrom = trim((string) ($d['directed_from'] ?? ''));
                $jobTitle = trim((string) ($d['job_title'] ?? ''));
                $hasReceipt = $titlePrefix !== '' || $directedFrom !== '' || $jobTitle !== '';
            ?>
                <tr>
                    <td class="c-num"><?= (int) ($i + 1) ?></td>
                    <?php if ($isSystemAdmin): ?>
                    <td class="c-sort"><?= (int) ($d['sort_order'] ?? 0) ?></td>
                    <?php endif; ?>
                    <td class="c-name">
                        <div class="print-entities-primary"><?= e((string) ($d['name'] ?? '')) ?></div>
                        <?php if (!empty($d['name_en'])): ?>
                            <div class="print-entities-sub" dir="ltr"><?= e((string) $d['name_en']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="c-receipt">
                        <?php if ($hasReceipt): ?>
                            <?php if ($titlePrefix !== ''): ?>
                                <div class="print-entities-sub"><?= e($titlePrefix) ?></div>
                            <?php endif; ?>
                            <?php if ($directedFrom !== ''): ?>
                                <div class="print-entities-primary"><?= e($directedFrom) ?></div>
                            <?php endif; ?>
                            <?php if ($jobTitle !== ''): ?>
                                <div class="print-entities-sub"><?= e($jobTitle) ?></div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td class="c-status">
                        <div><?= e((int) ($d['is_active'] ?? 1) === 1 ? __('common.active') : __('common.inactive')) ?></div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php render_print_page_footer(); ?>
</div>
<script>
</script>
</body>
</html>
