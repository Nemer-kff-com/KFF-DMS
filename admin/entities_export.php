<?php
/**
 * تصدير قائمة جهات المراسلة — CSV / Excel
 */
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/export_excel.php';

$user = require_permission('entities.manage');
ensure_entities_schema();

$canManageAllEntities = function_exists('user_manages_all_entities') && user_manages_all_entities($user);
if (!$canManageAllEntities && !(function_exists('user_has_assigned_departments') && user_has_assigned_departments($user))) {
    flash('danger', __('entities.department_required'));
    redirect(base_url('index.php'));
}

$format = strtolower(trim((string) ($_GET['format'] ?? 'csv')));
if (!in_array($format, ['csv', 'excel'], true)) {
    $format = 'csv';
}

$listFilters = entities_list_filters_from_request($user);
$rows = entities_list(false, $user, null, [
    'filters' => $listFilters,
    'with_stats' => true,
]);

$entityScopeLabel = static function (array $row): string {
    if (empty($row['department_id'])) {
        return __('entities.scope_public');
    }
    return __('entities.scope_department') . ': ' . department_display_name($row, 'department_name', 'department_name_en');
};

$headers = [
    __('entities.title_prefix'),
    __('entities.directed_to'),
    __('entities.honorific'),
    __('entities.section_basic'),
    __('entities.job_title'),
    __('entities.scope'),
    __('entities.mobile'),
    __('entities.email'),
    __('entities.phone_landline'),
    __('entities.address'),
    __('entities.location'),
    __('entities.notes'),
    __('entities.corr_count'),
    __('entities.last_corr'),
    __('common.status'),
];

$buildRow = static function (array $row) use ($entityScopeLabel): array {
    return [
        (string) ($row['title_prefix'] ?? ''),
        (string) ($row['directed_to'] ?? ''),
        (string) ($row['honorific'] ?? ''),
        (string) ($row['name_ar'] ?? ''),
        (string) ($row['job_title'] ?? ''),
        $entityScopeLabel($row),
        (string) ($row['mobile'] ?? ''),
        (string) ($row['email'] ?? ''),
        (string) ($row['phone'] ?? ''),
        (string) ($row['address'] ?? ''),
        (string) ($row['location'] ?? ''),
        (string) ($row['notes'] ?? ''),
        (string) (int) ($row['corr_count'] ?? 0),
        !empty($row['last_corr_date']) ? format_date($row['last_corr_date']) : '',
        (int) ($row['is_active'] ?? 1) === 1 ? __('common.active') : __('common.inactive'),
    ];
};

$filenameBase = 'entities-' . date('Ymd-His');

if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filenameBase . '.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, $headers);
    foreach ($rows as $row) {
        fputcsv($out, $buildRow($row));
    }
    fclose($out);
    exit;
}

$colspan = count($headers);
dms_export_excel_open($filenameBase, false);
dms_export_excel_title_row(__('entities.export_title'), $colspan);
dms_export_excel_subtitle_row(
    str_replace(':count', (string) count($rows), __('entities.print_count')) . ' — ' . format_datetime(date('Y-m-d H:i:s')),
    $colspan
);
dms_export_excel_header_row($headers);
foreach ($rows as $row) {
    echo '<tr>';
    foreach ($buildRow($row) as $cell) {
        dms_export_excel_cell((string) $cell);
    }
    echo '</tr>';
}
dms_export_excel_end();
exit;
