<?php
/**
 * تصدير قائمة التعاميم إلى Excel حسب الفلاتر الحالية.
 */
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/export_excel.php';

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

$exportLimit = 20000;
$rows = circular_list($filters, $user, $exportLimit, 0);

$filterBits = [];
if ($filters['from'] !== '' || $filters['to'] !== '') {
    $filterBits[] = __('common.from') . ' ' . format_date($filters['from'] ?: null)
        . ' — ' . __('common.to') . ' ' . format_date($filters['to'] ?: null);
}
if ($filters['status'] !== '') {
    $filterBits[] = __('common.status') . ': ' . circular_status_label($filters['status']);
}
$period = $filterBits !== [] ? implode(' · ', $filterBits) : __('common.all');

$headers = [
    __('common.serial'),
    __('circular.ref'),
    __('circular.subject'),
    __('circular.date'),
    __('corr.department'),
    __('circular.audience'),
    __('common.status'),
    __('circular.files'),
];
$colspan = count($headers);

dms_export_excel_open(__('circular.excel_title') . '-' . date('Ymd'));
dms_export_excel_title_row(dms_org_display_name() . ' — ' . __('circular.excel_title'), $colspan);
dms_export_excel_subtitle_row($period, $colspan);
dms_export_excel_header_row($headers);

$n = 0;
foreach ($rows as $r) {
    $n++;
    echo '<tr>';
    dms_export_excel_cell((string) $n);
    dms_export_excel_cell((string) ($r['ref_number'] ?? ''));
    dms_export_excel_cell((string) ($r['subject'] ?? ''));
    dms_export_excel_cell(format_date($r['circular_date'] ?? null));
    dms_export_excel_cell((string) (department_display_name($r, 'department_name', 'department_name_en') ?: __('common.none')));
    dms_export_excel_cell(circular_audience_label((string) ($r['audience'] ?? '')));
    dms_export_excel_cell(circular_status_label((string) ($r['status'] ?? '')));
    dms_export_excel_cell((string) (int) ($r['files_count'] ?? 0), 'col-num');
    echo '</tr>';
    if ($n % 200 === 0) {
        flush();
    }
}

echo '<tr><td class="col-text" colspan="' . $colspan . '"><strong>'
    . dms_export_excel_escape(__('common.total') . ': ' . $n)
    . '</strong></td></tr>';
dms_export_excel_end();
exit;
