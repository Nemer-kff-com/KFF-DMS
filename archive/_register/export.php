<?php
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/export_excel.php';
$user = require_archive_section($kind);
ensure_archive_schema();
$def = archive_register_def($kind);
if (!$def) {
    flash('danger', __('common.not_found'));
    redirect(base_url('archive/index.php'));
}
$filters = archive_register_request_filters($def);
$exportLimit = 20000;
$list = archive_register_list($kind, $filters, 1, $exportLimit);
$rows = $list['rows'];
$period = archive_register_filter_summary($def, $filters);
$listCols = $def['list_cols'] ?? [];
$headers = [__('common.serial')];
foreach ($listCols as $col) {
    $headers[] = __($col['label']);
}
$headers[] = __('archive.files');
$colspan = count($headers);
$filename = __($def['label']) . '-' . date('Ymd');
dms_export_excel_open($filename);
dms_export_excel_title_row(dms_org_display_name() . ' — ' . __($def['label']), $colspan);
dms_export_excel_subtitle_row($period, $colspan);
dms_export_excel_header_row($headers);
$n = 0;
foreach ($rows as $r) {
    $n++;
    echo '<tr>';
    dms_export_excel_cell((string) $n, 'col-num');
    foreach ($listCols as $col) {
        dms_export_excel_cell(archive_register_list_cell_text($col, $r));
    }
    dms_export_excel_cell((string) count(archive_register_folder_files(archive_register_guess_folder($def, $r))), 'col-num');
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
