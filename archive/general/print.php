<?php
/**
 * طباعة وثيقة أرشيف — نفس تنسيق وارد/صادر/تعميم (بدون QR أو أنموذج استلام).
 */
require_once __DIR__ . '/../../config/auth.php';
$user = require_archive_section('general');
$id = (int) ($_GET['id'] ?? 0);
$item = get_archive_item($id);
if (!$item || !user_can_access_archive($item, $user)) {
    exit(__('archive.not_found'));
}
$files = get_archive_files($id);
$backUrl = base_url('archive/general/view.php?id=' . $id);
$cssVer = (int) (@filemtime(__DIR__ . '/../../assets/css/app.css') ?: 90);
$jsVer = (int) (@filemtime(__DIR__ . '/../../assets/js/app.js') ?: 23);
$typeLabel = __('archive.print_type');
$printRef = archive_print_ref($item);
$headerDate = trim((string) ($item['doc_date'] ?? ''));
$printHijriDate = $headerDate !== '' ? format_hijri_date($headerDate) : '';
$printGregDate = $headerDate !== '' ? date('Y/m/d', strtotime($headerDate)) : '';
$printDocRef = $printRef;
$printToolbarRef = $printRef;
$subject = archive_title($item);
$printPageTitle = $printRef !== '' ? $printRef : $subject;

$fromDept = department_display_name($item, 'department_name', 'department_name_en');
$fromOrg = function_exists('print_org_work_name') ? print_org_work_name() : '';
$party = trim((string) ($item['party_name'] ?? ''));
$toNames = $party !== '' ? [$party] : [];

$metaRows = [
    [__('archive.number'), trim((string) ($item['archive_no'] ?? ''))],
    [__('archive.doc_type'), archive_doc_type_label((string) ($item['doc_type'] ?? ''))],
    [__('corr.category'), category_display_name(['name' => $item['category_name'] ?? '', 'name_en' => $item['category_name_en'] ?? ''])],
    [__('corr.confidentiality'), confidentiality_label((string) ($item['confidentiality'] ?? ''))],
    [__('archive.location'), archive_location_label($item)],
    [__('archive.keywords'), trim((string) ($item['keywords'] ?? ''))],
    [__('archive.linked_corr'), trim((string) ($item['linked_ref'] ?? ''))],
];
foreach (archive_extra_display_rows($item) as $xfRow) {
    $metaRows[] = [$xfRow['label'], $xfRow['value']];
}
$metaRows = array_values(array_filter(
    $metaRows,
    static fn(array $row): bool => trim((string) $row[1]) !== ''
));
$fileNames = [];
foreach ($files as $f) {
    $name = trim(archive_item_file_display_name($item, $f, $files));
    if ($name !== '') {
        $fileNames[] = $name;
    }
}
$fileNamesDir = 'ltr';
$notes = trim((string) ($item['notes'] ?? ''));
$printFooterItem = $item;
require __DIR__ . '/../../includes/print_simple_sheet.php';
