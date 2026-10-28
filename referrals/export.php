<?php
/**
 * تصدير قائمة الإحالات إلى Excel حسب الفلتر الحالي.
 */
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/export_excel.php';

$user = require_login();
$statusFilter = trim((string) ($_GET['status'] ?? ''));
$rows = referrals_list($statusFilter, $user);

$period = $statusFilter !== ''
    ? (__('corr.status') . ': ' . status_label($statusFilter))
    : __('common.all');

$headers = [
    __('common.serial'),
    __('corr.number'),
    __('corr.type'),
    __('corr.subject'),
    __('ref.from'),
    __('ref.to'),
    __('ref.by'),
    __('ref.date'),
    __('corr.status'),
];
$colspan = count($headers);

dms_export_excel_open(__('ref.excel_title') . '-' . date('Ymd'));
dms_export_excel_title_row(dms_org_display_name() . ' — ' . __('ref.excel_title'), $colspan);
dms_export_excel_subtitle_row($period, $colspan);
dms_export_excel_header_row($headers);

$n = 0;
foreach ($rows as $r) {
    $n++;
    echo '<tr>';
    dms_export_excel_cell((string) $n);
    dms_export_excel_cell((string) ($r['ref_number'] ?? ''));
    dms_export_excel_cell(type_label((string) ($r['type'] ?? '')));
    dms_export_excel_cell((string) ($r['subject'] ?? ''));
    dms_export_excel_cell(officer_with_office_text(referral_party_officer_label($r, 'from'), referral_party_subtitle($r, 'from')));
    dms_export_excel_cell(officer_with_office_text(referral_party_officer_label($r, 'to'), referral_party_subtitle($r, 'to')));
    dms_export_excel_cell(user_display_name($r, 'from_user_name', 'from_user_name_en'));
    dms_export_excel_cell(format_datetime($r['created_at'] ?? null));
    dms_export_excel_cell(status_label((string) ($r['status'] ?? '')));
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
