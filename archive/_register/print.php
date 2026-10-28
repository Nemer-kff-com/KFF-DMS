<?php
require_once __DIR__ . '/../../config/auth.php';
$user = require_archive_section($kind);
ensure_archive_schema();
$id = (int) ($_GET['id'] ?? 0);
$def = archive_register_def($kind);
$row = $def ? archive_register_get($kind, $id) : null;
if (!$def || !$row) {
    exit(__('archive.not_found'));
}
$folder = archive_register_guess_folder($def, $row);
$files = archive_register_folder_files($folder);
$groups = archive_register_form_groups($def);
$backUrl = archive_register_url($kind, 'view', ['id' => $id]);
$cssVer = (int) (@filemtime(__DIR__ . '/../../assets/css/app.css') ?: 90);
$jsVer = (int) (@filemtime(__DIR__ . '/../../assets/js/app.js') ?: 24);
$typeLabel = __($def['label']);
$printRef = archive_register_display_no((string) ($row['archive_no'] ?? ''));
$subject = archive_register_title($def, $row);
$printDocRef = $printRef;
$printToolbarRef = $printRef;
$printPageTitle = $printRef !== '' ? $printRef : $subject;
$printHtmlLang = dms_lang_code();
$printHtmlDir = dms_page_dir();

$gregCol = (string) ($def['date_greg_col'] ?? '');
$hijriCol = (string) ($def['date_col'] ?? '');
$dateParts = ($gregCol !== '' || $hijriCol !== '')
    ? archive_register_date_pair_parts([
        'greg_key' => $gregCol !== '' ? $gregCol : 'APPLICATION_DATE_GREG',
        'key' => $hijriCol !== '' ? $hijriCol : 'APPLICATION_DATE_HIJRI',
    ], $row)
    : ['greg' => '', 'hijri' => ''];
$printGregDate = trim((string) ($dateParts['greg'] ?? ''));
$printHijriDate = trim((string) ($dateParts['hijri'] ?? ''));

$metaRows = [];
foreach ($groups as $fields) {
    $hasCityId = false;
    foreach ($fields as $xf) {
        if ((string) ($xf['key'] ?? '') === 'CITY_ID') {
            $hasCityId = true;
            break;
        }
    }
    foreach ($fields as $f) {
        if ($hasCityId && (string) ($f['key'] ?? '') === 'CITY_AR') {
            continue;
        }
        $isCountry = ($f['type'] ?? '') === 'lookup' && ($f['lookup'] ?? '') === 'countries';
        $isDate = ($f['type'] ?? '') === 'date' || ($f['type'] ?? '') === 'date_pair';
        if ((string) ($f['key'] ?? '') === 'CITY_ID') {
            $disp = archive_register_city_label($row);
        } elseif ($isCountry) {
            $disp = archive_lookup_label('countries', archive_register_row_val($row, (string) ($f['key'] ?? '')));
        } elseif ($isDate) {
            $disp = archive_register_field_display($f, $row);
        } else {
            $disp = archive_register_field_display($f, $row);
        }
        $disp = trim((string) $disp);
        if ($disp !== '') {
            $metaRows[] = [__($f['label']), $disp];
        }
    }
}

$fileNames = [];
foreach ($files as $i => $path) {
    $name = trim(archive_register_file_display_name($row, $files, (int) $i));
    if ($name !== '') {
        $fileNames[] = $name;
    }
}
$fileNamesDir = 'ltr';
$printFooterItem = $row;
require __DIR__ . '/../../includes/print_simple_sheet.php';
