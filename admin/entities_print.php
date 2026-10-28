<?php
/**
 * طباعة قائمة جهات المراسلة — حسب صلاحيات المستخدم
 */
require_once __DIR__ . '/../config/auth.php';
$user = require_permission('entities.manage');
ensure_entities_schema();

$canManageAllEntities = function_exists('user_manages_all_entities') && user_manages_all_entities($user);
if (!$canManageAllEntities && !(function_exists('user_has_assigned_departments') && user_has_assigned_departments($user))) {
    flash('danger', __('entities.department_required'));
    redirect(base_url('index.php'));
}

$listFilters = entities_list_filters_from_request($user);
$rows = entities_list(false, $user, null, [
    'filters' => $listFilters,
    'with_stats' => true,
]);

$scopeLabel = $canManageAllEntities
    ? __('entities.print_scope_all')
    : str_replace(':department', (string) ($user['department_name'] ?? __('common.none')), __('entities.print_scope_department'));
if (($listFilters['scope'] ?? '') === 'public') {
    $scopeLabel = __('entities.filter_scope_public');
} elseif (($listFilters['scope'] ?? '') === 'department') {
    $scopeLabel = __('entities.filter_scope_department');
}
if (!empty($listFilters['department_id']) && $canManageAllEntities) {
    foreach (departments_list() as $department) {
        if ((int) $department['id'] === (int) $listFilters['department_id']) {
            $scopeLabel = department_display_name($department);
            break;
        }
    }
}

$cssVer = @filemtime(__DIR__ . '/../assets/css/app.css') ?: time();

$entityPrintContact = static function (array $row): string {
    $parts = [];
    if (!empty($row['mobile'])) {
        $parts[] = __('entities.mobile') . ': ' . $row['mobile'];
    }
    if (!empty($row['email'])) {
        $parts[] = __('entities.email') . ': ' . $row['email'];
    }
    if (!empty($row['phone']) && ($row['phone'] ?? '') !== ($row['mobile'] ?? '')) {
        $parts[] = __('entities.phone_landline') . ': ' . $row['phone'];
    }
    return implode("\n", $parts);
};

$entityPrintScope = static function (array $row): string {
    if (empty($row['department_id'])) {
        return __('entities.scope_public');
    }
    return __('entities.scope_department') . ': ' . department_display_name($row, 'department_name', 'department_name_en');
};
?>
<!DOCTYPE html>
<html lang="<?= e(dms_lang_code()) ?>" dir="<?= e(dms_page_dir()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(__('entities.print_title')) ?></title>
    <link rel="icon" href="<?= e(base_url('assets/img/favicon.ico')) ?>">
    <link href="<?= e(dms_bootstrap_css()) ?>" rel="stylesheet">
    <link href="<?= e(dms_bootstrap_icons_css()) ?>" rel="stylesheet">
    <link href="<?= e(base_url('assets/css/app.css')) ?>?v=<?= (int) $cssVer ?>" rel="stylesheet">
    <link href="<?= e(dms_print_fast_css()) ?>" rel="stylesheet">
    <style>
      @page { size: A4 landscape; margin: 0; }
    </style>
</head>
<body class="print-page print-report print-entities-page">
<div class="print-toolbar no-print">
    <div class="print-toolbar-title"><?= e(__('entities.print_title')) ?></div>
    <div class="d-flex flex-wrap gap-2">
        <button type="button" class="btn btn-kff btn-sm" onclick="window.print()">
            <i class="bi bi-printer me-1"></i><?= e(__('action.print')) ?>
        </button>
        <a class="btn btn-outline-secondary btn-sm" href="<?= e(base_url('admin/entities.php')) ?>">
            <i class="bi <?= dms_is_rtl() ? 'bi-arrow-right' : 'bi-arrow-left' ?> me-1"></i><?= e(__('action.back')) ?>
        </a>
    </div>
</div>

<div class="print-sheet print-sheet-wide print-report-sheet print-entities-sheet">
    <div class="print-report-body">
        <?php render_print_logo('print'); ?>

        <div class="print-meta-row print-meta-row-center">
            <div class="text-center w-100">
                <h1 class="print-doc-title"><?= e(__('entities.print_title')) ?></h1>
                <div class="print-doc-ref print-report-period">
                    <?= e($scopeLabel) ?>
                    · <?= e(str_replace(':count', (string) count($rows), __('entities.print_count'))) ?>
                    · <?= e(format_datetime(date('Y-m-d H:i:s'))) ?>
                </div>
            </div>
        </div>

        <table class="print-report-table print-entities-table">
            <colgroup>
                <col class="c-num">
                <col class="c-prefix">
                <col class="c-name">
                <col class="c-work">
                <col class="c-scope">
                <col class="c-contact">
                <col class="c-address">
                <col class="c-notes">
                <col class="c-status">
            </colgroup>
            <thead>
                <tr>
                    <th>#</th>
                    <th><?= e(__('entities.title_prefix')) ?></th>
                    <th><?= e(__('entities.directed_to')) ?></th>
                    <th><?= e(__('entities.section_basic')) ?></th>
                    <th><?= e(__('entities.scope')) ?></th>
                    <th><?= e(__('entities.contact')) ?></th>
                    <th><?= e(__('entities.address')) ?></th>
                    <th><?= e(__('entities.notes')) ?></th>
                    <th><?= e(__('common.status')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="9" class="text-center"><?= e(__('common.no_data')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $i => $r):
                $contact = $entityPrintContact($r);
            ?>
                <tr>
                    <td class="c-num"><?= (int) ($i + 1) ?></td>
                    <td class="c-prefix"><?= e((string) ($r['title_prefix'] ?? '')) ?></td>
                    <td class="c-name">
                        <?php if (!empty($r['directed_to'])): ?>
                            <div class="print-entities-primary"><?= e((string) $r['directed_to']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($r['honorific'])): ?>
                            <div class="print-entities-sub"><?= e((string) $r['honorific']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="c-work">
                        <div class="print-entities-primary"><?= e($r['name_ar']) ?></div>
                        <?php if (!empty($r['job_title'])): ?>
                            <div class="print-entities-sub"><?= e($r['job_title']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="c-scope"><?= e($entityPrintScope($r)) ?></td>
                    <td class="c-contact" dir="ltr"><?= $contact !== '' ? nl2br(e($contact)) : '' ?></td>
                    <td class="c-address"><?= e((string) ($r['address'] ?? '')) ?></td>
                    <td class="c-notes"><?= e((string) ($r['notes'] ?? '')) ?></td>
                    <td class="c-status"><?= e((int) ($r['is_active'] ?? 1) === 1 ? __('common.active') : __('common.inactive')) ?></td>
                </tr>
            <?php endforeach; ?>
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
