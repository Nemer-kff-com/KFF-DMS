<?php
/**
 * طباعة قائمة أدوار المستخدمين — A4 عمودي
 */
require_once __DIR__ . '/../config/auth.php';
$user = require_permission('admin.users');
ensure_roles_schema();

$roles = dms_roles_list();
$counts = dms_roles_user_counts();
$cssVer = @filemtime(__DIR__ . '/../assets/css/app.css') ?: time();
?>
<!DOCTYPE html>
<html lang="<?= e(dms_lang_code()) ?>" dir="<?= e(dms_page_dir()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(__('roles.print_title')) ?></title>
    <?= function_exists('dms_favicon_link_html') ? dms_favicon_link_html() : '' ?>
    <link href="<?= e(dms_bootstrap_css()) ?>" rel="stylesheet">
    <link href="<?= e(dms_bootstrap_icons_css()) ?>" rel="stylesheet">
    <link href="<?= e(base_url('assets/css/app.css')) ?>?v=<?= (int) $cssVer ?>" rel="stylesheet">
    <link href="<?= e(dms_print_fast_css()) ?>" rel="stylesheet">
    <style>
      @page { size: A4 portrait; margin: 0; }
    </style>
</head>
<body class="print-page print-report print-roles-page">
<div class="print-toolbar no-print">
    <div class="print-toolbar-title"><?= e(__('roles.print_title')) ?></div>
    <div class="d-flex flex-wrap gap-2">
        <?= dms_print_icon() ?>
        <?= dms_back_icon(base_url('admin/roles.php')) ?>
    </div>
</div>

<div class="print-sheet print-report-sheet">
    <div class="print-report-body">
        <?php render_print_logo('print'); ?>

        <div class="print-meta-row print-meta-row-center">
            <div class="text-center w-100">
                <h1 class="print-doc-title"><?= e(__('roles.print_title')) ?></h1>
                <div class="print-doc-ref print-report-period">
                    <?= e(str_replace(':count', (string) count($roles), __('roles.print_count'))) ?>
                    · <?= e(format_datetime(date('Y-m-d H:i:s'))) ?>
                </div>
            </div>
        </div>

        <table class="print-report-table print-roles-table">
            <colgroup>
                <col class="c-num">
                <col class="c-name">
                <col class="c-users">
                <col class="c-system">
                <col class="c-status">
            </colgroup>
            <thead>
                <tr>
                    <th class="c-num">#</th>
                    <th class="c-name"><?= e(__('roles.name_col')) ?></th>
                    <th class="c-users"><?= e(__('roles.users_col')) ?></th>
                    <th class="c-system"><?= e(__('roles.system')) ?></th>
                    <th class="c-status"><?= e(__('common.status')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$roles): ?>
                <tr>
                    <td colspan="5" class="text-center"><?= e(__('common.no_data')) ?></td>
                </tr>
            <?php endif; ?>
            <?php foreach ($roles as $i => $r):
                $rid = (int) ($r['id'] ?? 0);
                $display = dms_role_display_name($r);
                $otherName = dms_lang_code() === 'en'
                    ? trim((string) ($r['name_ar'] ?? ''))
                    : trim((string) ($r['name_en'] ?? ''));
                $desc = trim((string) ($r['description'] ?? ''));
                $n = (int) ($counts[$rid] ?? 0);
                $isSystem = (int) ($r['is_system'] ?? 0) === 1;
                $isActive = (int) ($r['is_active'] ?? 1) === 1;
            ?>
                <tr>
                    <td class="c-num"><?= (int) ($i + 1) ?></td>
                    <td class="c-name">
                        <div class="print-entities-primary"><?= e($display) ?></div>
                        <?php if ($otherName !== '' && $otherName !== $display): ?>
                            <div class="print-entities-sub"<?= dms_lang_code() === 'ar' ? ' dir="ltr"' : '' ?>><?= e($otherName) ?></div>
                        <?php endif; ?>
                        <?php if ($desc !== ''): ?>
                            <div class="print-entities-sub"><?= e($desc) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="c-users"><?= $n ?></td>
                    <td class="c-system"><?= e($isSystem ? __('common.yes') : __('common.no')) ?></td>
                    <td class="c-status"><?= e($isActive ? __('common.active') : __('common.inactive')) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php render_print_page_footer(); ?>
</div>
</body>
</html>
