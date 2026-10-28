<?php
/**
 * طباعة ورقة التعميم — نفس تنسيق وارد/صادر (بدون QR أو أنموذج استلام).
 */
require_once __DIR__ . '/../config/auth.php';
$user = require_login();
$id = (int) ($_GET['id'] ?? 0);
$item = get_circular($id);
if (!$item || !user_can_access_circular($item, $user)) {
    exit(__('circular.not_found'));
}
if (function_exists('outgoing_require_print_perm')) {
    outgoing_require_print_perm('circulars.print_form', $user, base_url('circulars/view.php?id=' . $id));
}
$files = get_circular_files($id);
$targetDepts = get_circular_departments($id);
$backUrl = base_url('circulars/view.php?id=' . $id);
$cssVer = (int) (@filemtime(__DIR__ . '/../assets/css/app.css') ?: 90);
$jsVer = (int) (@filemtime(__DIR__ . '/../assets/js/app.js') ?: 23);
$typeLabel = __('circular.print_type');
$headerDate = trim((string) ($item['circular_date'] ?? ''));
$printHijriDate = $headerDate !== '' ? format_hijri_date($headerDate) : '';
$printGregDate = $headerDate !== '' ? date('Y/m/d', strtotime($headerDate)) : '';
$printToolbarRef = (string) ($item['ref_number'] ?? '');
$printDocRef = format_ref_caption('circular', $item['ref_number'] ?? '');
$printPageTitle = $printToolbarRef;
$subject = (string) ($item['subject'] ?? '');

$fromSnap = circular_officer_display_row($item);
$fromDept = circular_has_officer_snapshot($item)
    ? department_display_name($fromSnap, 'department_name', 'department_name_en')
    : department_display_name($item, 'department_name', 'department_name_en');
$fromOrg = function_exists('print_org_work_name') ? print_org_work_name() : '';
$toNames = [];
if (($item['audience'] ?? '') === 'departments') {
    foreach ($targetDepts as $d) {
        $name = department_display_name($d);
        if ($name !== '') {
            $toNames[] = $name;
        }
    }
} else {
    $allLabel = circular_audience_label('all');
    if ($allLabel !== '') {
        $toNames[] = $allLabel;
    }
}

$metaRows = [
    [__('common.status'), circular_status_label((string) ($item['status'] ?? ''))],
];
$metaRows = array_values(array_filter(
    $metaRows,
    static fn(array $row): bool => trim((string) $row[1]) !== ''
));
$fileNames = [];
foreach ($files as $f) {
    $name = trim((string) ($f['file_name'] ?? ''));
    if ($name !== '') {
        $fileNames[] = $name;
    }
}
$notes = trim((string) ($item['notes'] ?? ''));
$printFooterItem = $item;
require __DIR__ . '/../includes/print_simple_sheet.php';
