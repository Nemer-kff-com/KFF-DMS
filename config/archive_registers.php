<?php
/**
 * سجلات الأرشيف المنفصلة كما في النظام القديم (منح / مشاريع / مالية) وملفات G:\archives.
 */

function archive_disk_default_root(): string
{
    if (defined('PHP_OS_FAMILY') && PHP_OS_FAMILY === 'Windows') {
        return 'G:/archives';
    }
    return rtrim(str_replace('\\', '/', dirname(__DIR__)), '/') . '/storage/archives';
}

function archive_disk_root(): string
{
    $configured = '';
    if (function_exists('setting')) {
        try {
            $configured = function_exists('dms_storage_normalize_root')
                ? dms_storage_normalize_root((string) setting('archive_disk_root', ''))
                : rtrim(str_replace('\\', '/', trim((string) setting('archive_disk_root', ''))), '/');
        } catch (Throwable $e) {
            $configured = '';
        }
    }
    $root = $configured !== '' ? $configured : archive_disk_default_root();
    if (function_exists('dms_storage_validate_root')) {
        $check = dms_storage_validate_root($root, true);
        if ($check['ok'] && $check['path'] !== '') {
            return str_replace('/', DIRECTORY_SEPARATOR, $check['path']);
        }
    }
    return str_replace('/', DIRECTORY_SEPARATOR, $root);
}

/**
 * @return array{ok:bool,path:string,error:string}
 */
function archive_disk_save_root(string $path): array
{
    $path = function_exists('dms_storage_normalize_root')
        ? dms_storage_normalize_root($path)
        : rtrim(str_replace('\\', '/', trim($path)), '/');
    if ($path === '') {
        if (function_exists('set_setting')) {
            set_setting('archive_disk_root', '', 'جذر تخزين مرفقات الأرشيف (فارغ = المسار الافتراضي)');
        }
        $root = archive_disk_default_root();
        $check = function_exists('dms_storage_validate_root')
            ? dms_storage_validate_root($root, true)
            : ['ok' => is_dir($root) || @mkdir($root, 0755, true), 'path' => $root, 'error' => ''];
        if ($check['ok']) {
            archive_disk_ensure_kind_roots();
        }
        return $check['ok']
            ? ['ok' => true, 'path' => $check['path'], 'error' => '']
            : $check;
    }
    $check = function_exists('dms_storage_validate_root')
        ? dms_storage_validate_root($path, true)
        : ['ok' => false, 'path' => $path, 'error' => __('settings.storage_invalid')];
    if (!$check['ok']) {
        return $check;
    }
    if (function_exists('set_setting')) {
        set_setting('archive_disk_root', $check['path'], 'جذر تخزين مرفقات الأرشيف (فارغ = المسار الافتراضي)');
    }
    archive_disk_ensure_kind_roots();
    return ['ok' => true, 'path' => $check['path'], 'error' => ''];
}

/**
 * @return array<string,string> مفتاح منطقي => مسار المجلد على القرص
 */
function archive_disk_folders(): array
{
    $r = archive_disk_root();
    return [
        'scholarships' => $r . DIRECTORY_SEPARATOR . 'scholarships',
        'projects' => $r . DIRECTORY_SEPARATOR . 'projects',
        'finance' => $r . DIRECTORY_SEPARATOR . 'finance',
        'general' => $r . DIRECTORY_SEPARATOR . 'general',
        'excluded' => $r . DIRECTORY_SEPARATOR . 'excluded',
    ];
}

function archive_kind_folder_key(string $kind): string
{
    $kind = strtolower(trim($kind));
    if (in_array($kind, ['scholarships', 'projects', 'finance'], true)) {
        return $kind;
    }
    return 'general';
}

function archive_file_stem(?string $archiveNo, int $id = 0): string
{
    $disp = function_exists('archive_register_display_no')
        ? archive_register_display_no($archiveNo)
        : trim((string) $archiveNo);
    $slug = function_exists('correspondence_attachment_basename')
        ? correspondence_attachment_basename($disp)
        : trim((string) (preg_replace('/[^\p{L}\p{N}\-_]+/u', '-', $disp) ?? ''));
    $slug = trim($slug, '-_');
    return $slug !== '' ? $slug : ('arch-' . max(0, $id));
}

function archive_file_numbered_name(string $stem, int $index, int $total, string $ext): string
{
    $ext = strtolower(ltrim($ext, '.'));
    $suffix = $total > 1 ? '-' . ($index + 1) : '';
    return $stem . $suffix . ($ext !== '' ? '.' . $ext : '');
}

function archive_disk_record_dir(string $kindKey, string $stem): string
{
    $folders = archive_disk_folders();
    $base = (string) ($folders[$kindKey] ?? $folders['general']);
    return rtrim($base, "\\/") . DIRECTORY_SEPARATOR . $stem;
}

function archive_disk_ensure_kind_roots(): void
{
    foreach (['scholarships', 'projects', 'finance', 'general'] as $key) {
        $dir = archive_disk_folders()[$key] ?? '';
        if ($dir === '' || is_dir($dir)) {
            continue;
        }
        @mkdir($dir, 0755, true);
    }
}

function archive_disk_relative_path(string $kindKey, string $stem, string $fileName): string
{
    return $kindKey . '/' . $stem . '/' . $fileName;
}

function archive_disk_resolve_stored(string $stored): string
{
    $stored = trim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $stored));
    if ($stored === '') {
        return '';
    }
    $stored = preg_replace('#(^|[\\\\/])other([\\\\/])#i', '$1general$2', $stored) ?? $stored;
    $root = rtrim(archive_disk_root(), "\\/");
    $full = $stored;
    if (!preg_match('/^[A-Za-z]:\\\\/', $stored) && !str_starts_with($stored, $root)) {
        $full = $root . DIRECTORY_SEPARATOR . ltrim($stored, "\\/");
    }
    $real = realpath($full);
    if ($real === false || !is_file($real)) {
        return '';
    }
    return function_exists('archive_register_file_is_allowed') && archive_register_file_is_allowed($real) ? $real : '';
}

function archive_remap_legacy_path(?string $stored): string
{
    $p = trim((string) $stored);
    if ($p === '') {
        return '';
    }
    $p = str_replace(['/', '\\\\'], '\\', $p);
    while (str_contains($p, '\\\\')) {
        $p = str_replace('\\\\', '\\', $p);
    }
    $map = [
        'Scholarships_With_Number' => 'scholarships',
        'Scholarships_New' => 'scholarships',
        'scholarships-numbered' => 'scholarships',
        'scholarships-new' => 'scholarships',
        'Projects_With_Number' => 'projects',
        'Projects_New' => 'projects',
        'projects-numbered' => 'projects',
        'projects-new' => 'projects',
        'Excluded_items' => 'excluded',
    ];
    foreach ($map as $old => $new) {
        if (preg_match('/\\\\' . preg_quote($old, '/') . '\\\\([^\\\\]+)\s*$/i', $p, $m)) {
            return archive_disk_folders()[$new] . DIRECTORY_SEPARATOR . trim($m[1]);
        }
    }
    return $p;
}

/**
 * @return array<string,array<string,mixed>>
 */
function archive_register_defs(): array
{
    return [
        'scholarships' => [
            'table' => 'tblarchive_scholarships',
            'legacy' => 'scholarships',
            'icon' => 'bi-mortarboard',
            'label' => 'archive.reg.scholarships',
            'sub' => 'archive.reg.scholarships_sub',
            'search_ph' => 'archive.reg.search.scholarships',
            'add_label' => 'archive.reg.add.scholarships',
            'date_col' => 'APPLICATION_DATE_HIJRI',
            'filters' => [
                ['name' => 'gender', 'col' => 'GENDER', 'label' => 'archive.xf.gender', 'lookup' => 'gender'],
                ['name' => 'nationality_id', 'col' => 'NATIONALITY_ID', 'label' => 'archive.xf.nationality', 'lookup' => 'countries'],
                ['name' => 'scholarship_type', 'col' => 'SCHOLARSHIPS_TYPE', 'label' => 'archive.xf.scholarship_type', 'lookup' => 'scholarship_types'],
                ['name' => 'degree_id', 'col' => 'DEGREE_ID', 'label' => 'archive.xf.degree', 'lookup' => 'degree'],
                ['name' => 'specialization', 'col' => 'SPECIALIZATION', 'label' => 'archive.xf.specialization', 'lookup' => 'specialities'],
                ['name' => 'country_id', 'col' => 'COUNTRY_ID', 'label' => 'archive.xf.study_country', 'lookup' => 'countries'],
            ],
            'title_cols' => ['SUBJECT_AR', 'STUDENT_NAME_AR'],
            'search_cols' => ['archive_no', 'STUDENT_NAME_AR', 'STUDENT_NAME_EN', 'SUBJECT_AR', 'SUBJECT_EN', 'ARCHIVE_NUM', 'NOTES_AR'],
            'list_cols' => [
                ['key' => 'archive_no', 'label' => 'archive.reg.no', 'type' => 'ref', 'ltr' => true],
                ['key' => 'ARCHIVE_NUM', 'label' => 'archive.reg.archive_num', 'ltr' => true],
                ['key' => 'STUDENT_NAME_AR', 'label' => 'archive.xf.student_name'],
                ['key' => 'GENDER', 'label' => 'archive.xf.gender', 'type' => 'lookup', 'lookup' => 'gender'],
                ['key' => 'NATIONALITY_ID', 'label' => 'archive.xf.nationality', 'type' => 'country'],
                ['key' => 'SCHOLARSHIPS_TYPE', 'label' => 'archive.xf.scholarship_type', 'type' => 'lookup', 'lookup' => 'scholarship_types'],
                ['key' => 'DEGREE_ID', 'label' => 'archive.xf.degree', 'type' => 'lookup', 'lookup' => 'degree'],
                ['key' => 'SPECIALIZATION', 'label' => 'archive.xf.specialization', 'type' => 'lookup', 'lookup' => 'specialities'],
                ['key' => 'COUNTRY_ID', 'label' => 'archive.xf.study_country', 'type' => 'country'],
                ['key' => 'APPLICATION_DATE_HIJRI', 'greg_key' => 'APPLICATION_DATE_GREG', 'label' => 'common.date', 'type' => 'date_pair', 'ltr' => true],
            ],
            'folder_hint_col' => 'ARCHIVE_NUM',
            'folder_kinds' => ['scholarships'],
            'lookups' => [
                'NATIONALITY_ID' => 'countries',
                'COUNTRY_ID' => 'countries',
                'UNIVERSITY_ID' => 'university',
                'DEGREE_ID' => 'degree',
                'SPECIALIZATION' => 'specialities',
                'SCHOLARSHIPS_TYPE' => 'scholarship_types',
                'GENDER' => 'gender',
                'CURRENCY' => 'currency',
            ],
            'form' => [
                ['key' => 'archive_no', 'label' => 'archive.reg.no', 'type' => 'readonly', 'col' => 'col-md-3', 'ltr' => true, 'section' => 'archive.reg.sec.record'],
                ['key' => 'ARCHIVE_NUM', 'label' => 'archive.reg.archive_num', 'type' => 'text', 'col' => 'col-md-3', 'ltr' => true, 'section' => 'archive.reg.sec.record'],
                ['key' => 'APPLICATION_DATE_GREG', 'label' => 'corr.date_g_title', 'type' => 'date', 'col' => 'col-md-3', 'ltr' => true, 'pair' => 'APPLICATION_DATE_HIJRI', 'section' => 'archive.reg.sec.record'],
                ['key' => 'APPLICATION_DATE_HIJRI', 'label' => 'corr.date_h_title', 'type' => 'date', 'col' => 'col-md-3', 'ltr' => true, 'pair' => 'APPLICATION_DATE_GREG', 'section' => 'archive.reg.sec.record'],
                ['key' => 'STUDENT_NAME_AR', 'label' => 'archive.xf.student_name', 'type' => 'text', 'col' => 'col-md-6', 'required' => true, 'section' => 'archive.reg.sec.student'],
                ['key' => 'STUDENT_NAME_EN', 'label' => 'archive.xf.student_name_en', 'type' => 'text', 'col' => 'col-md-6', 'ltr' => true, 'section' => 'archive.reg.sec.student'],
                ['key' => 'GENDER', 'label' => 'archive.xf.gender', 'type' => 'lookup', 'lookup' => 'gender', 'col' => 'col-md-6', 'section' => 'archive.reg.sec.student'],
                ['key' => 'NATIONALITY_ID', 'label' => 'archive.xf.nationality', 'type' => 'lookup', 'lookup' => 'countries', 'col' => 'col-md-6', 'section' => 'archive.reg.sec.student'],
                ['key' => 'SUBJECT_AR', 'label' => 'archive.doc_title', 'type' => 'text', 'col' => 'col-md-6', 'section' => 'archive.reg.sec.study'],
                ['key' => 'SUBJECT_EN', 'label' => 'archive.doc_title_en', 'type' => 'text', 'col' => 'col-md-6', 'ltr' => true, 'section' => 'archive.reg.sec.study'],
                ['key' => 'COUNTRY_ID', 'label' => 'archive.xf.study_country', 'type' => 'lookup', 'lookup' => 'countries', 'col' => 'col-md-4', 'section' => 'archive.reg.sec.study'],
                ['key' => 'UNIVERSITY_ID', 'label' => 'archive.xf.university', 'type' => 'lookup', 'lookup' => 'university', 'col' => 'col-md-4', 'section' => 'archive.reg.sec.study'],
                ['key' => 'DEGREE_ID', 'label' => 'archive.xf.degree', 'type' => 'lookup', 'lookup' => 'degree', 'col' => 'col-md-4', 'section' => 'archive.reg.sec.study'],
                ['key' => 'SPECIALIZATION', 'label' => 'archive.xf.specialization', 'type' => 'lookup', 'lookup' => 'specialities', 'col' => 'col-12', 'section' => 'archive.reg.sec.study'],
                ['key' => 'SCHOLARSHIPS_TYPE', 'label' => 'archive.xf.scholarship_type', 'type' => 'lookup', 'lookup' => 'scholarship_types', 'col' => 'col-md-6', 'section' => 'archive.reg.sec.grant'],
                ['key' => 'AMOUNT', 'label' => 'archive.xf.amount', 'type' => 'text', 'col' => 'col-md-3', 'ltr' => true, 'section' => 'archive.reg.sec.grant'],
                ['key' => 'CURRENCY', 'label' => 'archive.xf.currency', 'type' => 'lookup', 'lookup' => 'currency', 'col' => 'col-md-3', 'section' => 'archive.reg.sec.grant'],
                ['key' => 'DURATION_FROM_HIJRI', 'label' => 'archive.xf.duration_from', 'type' => 'text', 'col' => 'col-md-6', 'ltr' => true, 'section' => 'archive.reg.sec.grant'],
                ['key' => 'DURATION_TO_HIJRI', 'label' => 'archive.xf.duration_to', 'type' => 'text', 'col' => 'col-md-6', 'ltr' => true, 'section' => 'archive.reg.sec.grant'],
                ['key' => 'NOTES_AR', 'label' => 'corr.notes', 'type' => 'textarea', 'col' => 'col-12', 'section' => 'archive.reg.sec.notes'],
            ],
            'columns' => archive_register_scholarship_columns(),
        ],
        'projects' => [
            'table' => 'tblarchive_projects',
            'legacy' => 'projects',
            'icon' => 'bi-building',
            'label' => 'archive.reg.projects',
            'sub' => 'archive.reg.projects_sub',
            'search_ph' => 'archive.reg.search.projects',
            'add_label' => 'archive.reg.add.projects',
            'date_col' => 'DATE_HIJRI',
            'date_greg_col' => 'DATE_GREG',
            'filters' => [
                ['name' => 'project_type', 'col' => 'PROJECT_TYPE', 'label' => 'archive.xf.project_type', 'lookup' => 'project_types'],
                ['name' => 'project_status', 'col' => 'PROJECT_STATUS', 'label' => 'archive.xf.project_status', 'lookup' => 'project_status'],
                ['name' => 'country_id', 'col' => 'COUNTRY_ID', 'label' => 'archive.xf.country', 'lookup' => 'countries'],
                ['name' => 'region_id', 'col' => 'REGION_ID', 'label' => 'archive.xf.region', 'lookup' => 'region'],
                ['name' => 'city_ar', 'col' => 'CITY_AR', 'id_col' => 'CITY_ID', 'label' => 'archive.xf.city', 'type' => 'text'],
            ],
            'title_cols' => ['PROJECT_NAME_AR', 'PROJECT_NUMBER'],
            'search_cols' => ['archive_no', 'PROJECT_NAME_AR', 'PROJECT_NAME_EN', 'PROJECT_NUMBER', 'ARCHIVE_NUMBER', 'CITY_AR', 'OPERATORS_PROJECT_AR'],
            'list_cols' => [
                ['key' => 'archive_no', 'label' => 'archive.reg.no', 'type' => 'ref', 'ltr' => true],
                ['key' => 'ARCHIVE_NUMBER', 'label' => 'archive.reg.archive_num', 'ltr' => true],
                ['key' => 'PROJECT_NUMBER', 'label' => 'archive.reg.project_number', 'ltr' => true],
                ['key' => 'PROJECT_NAME_AR', 'label' => 'archive.xf.project_name'],
                ['key' => 'PROJECT_TYPE', 'label' => 'archive.xf.project_type', 'type' => 'lookup', 'lookup' => 'project_types'],
                ['key' => 'PROJECT_STATUS', 'label' => 'archive.xf.project_status', 'type' => 'lookup', 'lookup' => 'project_status'],
                ['key' => 'COUNTRY_ID', 'label' => 'archive.xf.country', 'type' => 'country'],
                ['key' => 'REGION_ID', 'label' => 'archive.xf.region', 'type' => 'lookup', 'lookup' => 'region'],
                ['key' => 'CITY_ID', 'text_key' => 'CITY_AR', 'label' => 'archive.xf.city', 'type' => 'city'],
                ['key' => 'DATE_HIJRI', 'greg_key' => 'DATE_GREG', 'label' => 'common.date', 'type' => 'date_pair', 'ltr' => true],
            ],
            'folder_hint_col' => 'ARCHIVE_NUMBER',
            'folder_kinds' => ['projects'],
            'lookups' => [
                'PROJECT_TYPE' => 'project_types',
                'PROJECT_STATUS' => 'project_status',
                'COUNTRY_ID' => 'countries',
                'CITY_ID' => 'city',
                'REGION_ID' => 'region',
                'PROJECT_COST_CURRENCY' => 'currency',
            ],
            'form' => [
                ['key' => 'archive_no', 'label' => 'archive.reg.no', 'type' => 'readonly', 'col' => 'col-md-3', 'ltr' => true, 'section' => 'archive.reg.sec.record'],
                ['key' => 'ARCHIVE_NUMBER', 'label' => 'archive.reg.archive_num', 'type' => 'text', 'col' => 'col-md-3', 'ltr' => true, 'section' => 'archive.reg.sec.record'],
                ['key' => 'DATE_GREG', 'label' => 'corr.date_g_title', 'type' => 'date', 'col' => 'col-md-3', 'ltr' => true, 'pair' => 'DATE_HIJRI', 'section' => 'archive.reg.sec.record'],
                ['key' => 'DATE_HIJRI', 'label' => 'corr.date_h_title', 'type' => 'date', 'col' => 'col-md-3', 'ltr' => true, 'pair' => 'DATE_GREG', 'section' => 'archive.reg.sec.record'],
                ['key' => 'PROJECT_NUMBER', 'label' => 'archive.reg.project_number', 'type' => 'text', 'col' => 'col-md-4', 'ltr' => true, 'section' => 'archive.reg.sec.project'],
                ['key' => 'PROJECT_TYPE', 'label' => 'archive.xf.project_type', 'type' => 'lookup', 'lookup' => 'project_types', 'col' => 'col-md-4', 'section' => 'archive.reg.sec.project'],
                ['key' => 'PROJECT_STATUS', 'label' => 'archive.xf.project_status', 'type' => 'lookup', 'lookup' => 'project_status', 'col' => 'col-md-4', 'section' => 'archive.reg.sec.project'],
                ['key' => 'PROJECT_NAME_AR', 'label' => 'archive.xf.project_name', 'type' => 'text', 'col' => 'col-md-6', 'required' => true, 'section' => 'archive.reg.sec.project'],
                ['key' => 'PROJECT_NAME_EN', 'label' => 'archive.xf.project_name_en', 'type' => 'text', 'col' => 'col-md-6', 'ltr' => true, 'section' => 'archive.reg.sec.project'],
                ['key' => 'COUNTRY_ID', 'label' => 'archive.xf.country', 'type' => 'lookup', 'lookup' => 'countries', 'col' => 'col-md-4', 'section' => 'archive.reg.sec.place'],
                ['key' => 'CITY_ID', 'label' => 'archive.xf.city', 'type' => 'lookup', 'lookup' => 'city', 'col' => 'col-md-4', 'section' => 'archive.reg.sec.place'],
                ['key' => 'CITY_AR', 'label' => 'archive.xf.city_text', 'type' => 'text', 'col' => 'col-md-4', 'section' => 'archive.reg.sec.place'],
                ['key' => 'REGION_ID', 'label' => 'archive.xf.region', 'type' => 'lookup', 'lookup' => 'region', 'col' => 'col-md-4', 'section' => 'archive.reg.sec.place'],
                ['key' => 'OPERATORS_PROJECT_AR', 'label' => 'archive.party', 'type' => 'text', 'col' => 'col-md-4', 'section' => 'archive.reg.sec.place'],
                ['key' => 'BENEFICIARIES_NUMBER_AR', 'label' => 'archive.xf.beneficiaries', 'type' => 'text', 'col' => 'col-md-4', 'section' => 'archive.reg.sec.place'],
                ['key' => 'PROJECT_COST', 'label' => 'archive.xf.project_cost', 'type' => 'text', 'col' => 'col-md-4', 'ltr' => true, 'section' => 'archive.reg.sec.place'],
                ['key' => 'PROJECT_DESCRIPTION_AR', 'label' => 'archive.xf.description', 'type' => 'textarea', 'col' => 'col-12', 'section' => 'archive.reg.sec.notes'],
                ['key' => 'NOTES_AR', 'label' => 'corr.notes', 'type' => 'textarea', 'col' => 'col-12', 'section' => 'archive.reg.sec.notes'],
            ],
            'columns' => archive_register_project_columns(),
        ],
        'finance' => [
            'table' => 'tblarchive_finance',
            'legacy' => 'purchasing_document',
            'icon' => 'bi-cash-stack',
            'label' => 'archive.reg.finance',
            'sub' => 'archive.reg.finance_sub',
            'search_ph' => 'archive.reg.search.finance',
            'add_label' => 'archive.reg.add.finance',
            'title_cols' => ['SUBJECT', 'KAIDNUMBER'],
            'search_cols' => ['archive_no', 'SUBJECT', 'KAIDNUMBER', 'COMPANYNAME', 'YEAR', 'SADERNUMBER'],
            'list_cols' => [
                ['key' => 'archive_no', 'label' => 'archive.reg.no', 'type' => 'ref', 'ltr' => true],
                ['key' => 'KAIDNUMBER', 'label' => 'archive.reg.kaid', 'ltr' => true],
                ['key' => 'YEAR', 'label' => 'archive.doc_year', 'ltr' => true],
                ['key' => 'COMPANYNAME', 'label' => 'archive.party'],
                ['key' => 'SUBJECT', 'label' => 'archive.doc_title'],
            ],
            'folder_hint_col' => '',
            'folder_kinds' => ['finance'],
            'lookups' => [],
            'form' => [
                ['key' => 'archive_no', 'label' => 'archive.reg.no', 'type' => 'readonly', 'col' => 'col-md-3', 'ltr' => true, 'section' => 'archive.reg.sec.record'],
                ['key' => 'KAIDNUMBER', 'label' => 'archive.reg.kaid', 'type' => 'text', 'col' => 'col-md-3', 'required' => true, 'ltr' => true, 'section' => 'archive.reg.sec.record'],
                ['key' => 'YEAR', 'label' => 'archive.doc_year', 'type' => 'text', 'col' => 'col-md-3', 'ltr' => true, 'section' => 'archive.reg.sec.record'],
                ['key' => 'HIJRIDATE', 'label' => 'corr.date_h_title', 'type' => 'date', 'col' => 'col-md-3', 'ltr' => true, 'section' => 'archive.reg.sec.record'],
                ['key' => 'COMPANYNAME', 'label' => 'archive.party', 'type' => 'text', 'col' => 'col-md-4', 'section' => 'archive.reg.sec.party'],
                ['key' => 'SADERNUMBER', 'label' => 'archive.reg.sader', 'type' => 'text', 'col' => 'col-md-4', 'ltr' => true, 'section' => 'archive.reg.sec.party'],
                ['key' => 'TYPE', 'label' => 'archive.doc_type', 'type' => 'text', 'col' => 'col-md-4', 'ltr' => true, 'section' => 'archive.reg.sec.party'],
                ['key' => 'SUBJECT', 'label' => 'archive.doc_title', 'type' => 'textarea', 'col' => 'col-12', 'required' => true, 'section' => 'archive.reg.sec.party'],
            ],
            'columns' => [
                'archive_no' => 'VARCHAR(40) NULL',
                'DKEY' => 'INT NULL',
                'KAIDNUMBER' => 'TEXT NULL',
                'COMPANYNAME' => 'TEXT NULL',
                'YEAR' => 'TEXT NULL',
                'SUBJECT' => 'TEXT NULL',
                'TYPE' => 'INT NULL',
                'ORGANIZATION_TREE_ID' => 'INT NULL',
                'ORGANIZATION_CHART_ID' => 'INT NULL',
                'SADERNUMBER' => 'TEXT NULL',
                'HIJRIDATE' => 'TEXT NULL',
                'IS_INTERNAL' => 'INT NULL',
                'file_folder' => 'VARCHAR(500) NULL',
            ],
        ],
    ];
}

function archive_register_scholarship_columns(): array
{
    return [
        'archive_no' => 'VARCHAR(40) NULL',
        'APPLICATION_DATE_HIJRI' => 'TEXT NULL', 'RESPONSE_DATE_HIJRI' => 'TEXT NULL',
        'STUDENT_NAME_AR' => 'TEXT NULL', 'STUDENT_NAME_EN' => 'TEXT NULL',
        'NATIONALITY_ID' => 'INT NULL', 'SUBJECT_AR' => 'TEXT NULL', 'SUBJECT_EN' => 'TEXT NULL',
        'CLASSIFICATION_ID' => 'INT NULL', 'DIRECTOR_NOTES_EN' => 'TEXT NULL', 'DIRECTOR_NOTES_AR' => 'TEXT NULL',
        'DEGREE_ID' => 'INT NULL', 'SPECIALIZATION' => 'INT NULL', 'COUNTRY_ID' => 'INT NULL',
        'UNIVERSITY_ID' => 'INT NULL', 'SCHOLARSHIPS_TYPE' => 'INT NULL', 'ARCHIVED' => 'TEXT NULL',
        'NOTIFICATION' => 'INT NULL', 'REJECTION_REASON' => 'TEXT NULL',
        'CREATED_BY' => 'INT NULL', 'CREATED_DATE' => 'VARCHAR(20) NULL',
        'UPDATED_BY' => 'INT NULL', 'UPDATED_DATE' => 'VARCHAR(20) NULL',
        'DKEY' => 'INT NULL', 'FILE_PATH' => 'TEXT NULL',
        'ORGANIZATION_CHART_ID' => 'INT NULL', 'ORGANIZATION_TREE_ID' => 'INT NULL',
        'APPLICATION_DATE_GREG' => 'TEXT NULL', 'RESPONSE_DATE_GREG' => 'TEXT NULL',
        'FILE_PATH_1' => 'TEXT NULL', 'CURRENT_DEGREE' => 'INT NULL',
        'NOTES_AR' => 'TEXT NULL', 'NOTES_EN' => 'TEXT NULL', 'ARCHIVE_NUM' => 'INT NULL',
        'STATUS' => 'TEXT NULL', 'V_COMMENTS' => 'TEXT NULL', 'P_COMMENTS' => 'TEXT NULL',
        'GENDER' => 'INT NULL', 'IS_UPLOADED' => 'INT NULL', 'CHEVENING' => 'INT NULL',
        'File_Path_New' => 'TEXT NULL', 'AMOUNT' => 'DECIMAL(18,2) NULL', 'CURRENCY' => 'INT NULL',
        'DURATION_FROM_HIJRI' => 'TEXT NULL', 'DURATION_FROM_GREG' => 'TEXT NULL',
        'DURATION_TO_HIJRI' => 'TEXT NULL', 'DURATION_TO_GREG' => 'TEXT NULL',
        'GRADUATION_DATE_HIJRI' => 'TEXT NULL', 'GRADUATION_DATE_GREG' => 'TEXT NULL',
        'file_folder' => 'VARCHAR(500) NULL',
        'original_file_names' => 'TEXT NULL',
        'original_folder_name' => 'VARCHAR(255) NULL',
        'legacy_source' => 'VARCHAR(40) NULL',
        'legacy_dkey' => 'INT NULL',
    ];
}

function archive_register_project_columns(): array
{
    return [
        'archive_no' => 'VARCHAR(40) NULL',
        'DKEY' => 'INT NULL', 'ARCHIVE_NUMBER' => 'INT NULL', 'PROJECT_NUMBER' => 'TEXT NULL',
        'DATE_HIJRI' => 'TEXT NULL', 'DATE_GREG' => 'TEXT NULL',
        'PROJECT_DURATION' => 'INT NULL', 'PROJECT_NAME_AR' => 'TEXT NULL', 'PROJECT_NAME_EN' => 'TEXT NULL',
        'PROJECT_TYPE' => 'INT NULL', 'PROJECT_SUBTYPE' => 'TEXT NULL', 'PROJECT_SUBTYPE_EN' => 'TEXT NULL',
        'PROJECT_STATUS' => 'INT NULL', 'LOCATION_ID' => 'INT NULL', 'COUNTRY_ID' => 'INT NULL', 'CITY_ID' => 'INT NULL',
        'CITY_AR' => 'TEXT NULL', 'CITY_EN' => 'TEXT NULL',
        'OPERATORS_PROJECT_AR' => 'TEXT NULL', 'OPERATORS_PROJECT_EN' => 'TEXT NULL',
        'ALMZAKON_AR' => 'TEXT NULL', 'ALMZAKON_EN' => 'TEXT NULL',
        'PROJECT_DESCRIPTION_AR' => 'TEXT NULL', 'PROJECT_DESCRIPTION_EN' => 'TEXT NULL',
        'PROJECT_PURPOSE_AR' => 'TEXT NULL', 'PROJECT_PURPOSE_EN' => 'TEXT NULL',
        'BENEFICIARIES_NUMBER_AR' => 'TEXT NULL', 'BENEFICIARIES_NUMBER_EN' => 'TEXT NULL',
        'PROJECT_COST' => 'TEXT NULL', 'PROJECT_COST_CURRENCY' => 'INT NULL',
        'ANNUAL_ADMINISTRATION_COSTS' => 'TEXT NULL', 'ANNUAL_ADMINISTRATION_COSTS_CURRENCY' => 'INT NULL',
        'AMOUNT_REQUIRED' => 'TEXT NULL', 'REQUESTED_AMOUNT_CURRENCY' => 'INT NULL',
        'AMOUNT_REQUIRED_CURRENCY' => 'INT NULL', 'NOTES_AR' => 'TEXT NULL', 'NOTES_EN' => 'TEXT NULL',
        'CREATED_BY' => 'INT NULL', 'CREATED_DATE' => 'TEXT NULL', 'UPDATED_BY' => 'INT NULL', 'UPDATED_DATE' => 'TEXT NULL',
        'ORGANIZATION_CHART_ID' => 'INT NULL', 'ORGANIZATION_TREE_ID' => 'INT NULL',
        'REQUESTED_AMOUNT_INT' => 'DECIMAL(19,0) NULL', 'PROJECT_COST_INT' => 'DECIMAL(19,0) NULL',
        'ANNUAL_ADMINISTRATION_COSTS_INT' => 'DECIMAL(19,0) NULL', 'AMOUNT_REQUIRED_INT' => 'DECIMAL(18,2) NULL',
        'ACCEPTANCE_REJECTION_DATE_HIJRI' => 'TEXT NULL', 'ACCEPTANCE_REJECTION_DATE_GREG' => 'TEXT NULL',
        'REGION_ID' => 'INT NULL', 'OTHER_CONTRIBUTE_AR' => 'TEXT NULL', 'OTHER_CONTRIBUTE_EN' => 'TEXT NULL',
        'STATUS' => 'TEXT NULL', 'V_COMMENTS' => 'TEXT NULL', 'P_COMMENTS' => 'TEXT NULL',
        'IS_UPLOADED' => 'INT NULL', 'OLD_REGION' => 'INT NULL', 'File_Path_New' => 'TEXT NULL',
        'TYPE' => 'INT NULL', 'AMOUNT_REQUIRED_INT_NEW' => 'DECIMAL(18,2) NULL',
        'file_folder' => 'VARCHAR(500) NULL',
        'original_file_names' => 'TEXT NULL',
        'original_folder_name' => 'VARCHAR(255) NULL',
    ];
}

function archive_register_def(string $kind): ?array
{
    $defs = archive_register_defs();
    if (!isset($defs[$kind])) {
        return null;
    }
    return $defs[$kind] + ['key' => $kind];
}

/** @return array<string,list<array<string,mixed>>> */
function archive_register_form_groups(array $def): array
{
    $groups = [];
    foreach ($def['form'] ?? [] as $f) {
        if (in_array((string) ($f['key'] ?? ''), ['file_folder', 'original_file_names', 'original_folder_name'], true)) {
            continue;
        }
        $sec = (string) ($f['section'] ?? 'archive.section_basic');
        $groups[$sec][] = $f;
    }
    return $groups;
}

function archive_register_row_val(array $row, string $key): string
{
    if ($key === '') {
        return '';
    }
    if (array_key_exists($key, $row)) {
        return trim((string) ($row[$key] ?? ''));
    }
    $want = strtolower($key);
    foreach ($row as $k => $v) {
        if (strtolower((string) $k) === $want) {
            return trim((string) ($v ?? ''));
        }
    }
    return '';
}

function archive_register_field_display(array $f, array $row): string
{
    $val = archive_register_row_val($row, (string) ($f['key'] ?? ''));
    if ($val === '') {
        return '';
    }
    if (($f['type'] ?? '') === 'lookup' && !empty($f['lookup'])) {
        return archive_lookup_label((string) $f['lookup'], $val);
    }
    if (($f['key'] ?? '') === 'archive_no') {
        return archive_register_display_no($val);
    }
    if (($f['type'] ?? '') === 'hijri' || str_contains(strtoupper((string) ($f['key'] ?? '')), 'HIJRI') || str_contains(strtoupper((string) ($f['key'] ?? '')), 'DATE')) {
        $formatted = archive_format_compact_date($val);
        if ($formatted !== '') {
            return $formatted;
        }
    }
    return $val;
}

function archive_format_compact_date(string $raw): string
{
    $raw = trim(str_replace(['هـ', 'ه'], '', $raw));
    $raw = preg_replace('/\s+/u', '', $raw) ?? $raw;
    if ($raw === '') {
        return '';
    }
    if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $raw, $m)) {
        $y = (int) $m[1];
        $mo = (int) $m[2];
        $d = (int) $m[3];
        if ($mo >= 1 && $mo <= 12 && $d >= 1 && $d <= 31) {
            return sprintf('%04d/%02d/%02d', $y, $mo, $d);
        }
    }
    if (preg_match('/^(\d{4})[\/\-\.](\d{1,2})[\/\-\.](\d{1,2})$/', $raw, $m)) {
        return sprintf('%04d/%02d/%02d', (int) $m[1], (int) $m[2], (int) $m[3]);
    }
    return $raw;
}

/** @return array{id:int,name_ar:string,name_en:?string,iso2:string}|null */
function archive_country_row(int $id): ?array
{
    static $map = null;
    if ($map === null) {
        $map = [];
        foreach (archive_lookups('countries', false) as $row) {
            $cid = (int) ($row['id'] ?? 0);
            if ($cid > 0) {
                $map[$cid] = $row;
            }
            $legacy = (int) ($row['legacy_id'] ?? 0);
            if ($legacy > 0 && !isset($map[$legacy])) {
                $map[$legacy] = $row;
            }
        }
    }
    return $id > 0 ? ($map[$id] ?? null) : null;
}

function archive_iso2_flag_html(string $iso2): string
{
    $iso2 = strtoupper(preg_replace('/[^A-Za-z]/', '', $iso2) ?? '');
    if (strlen($iso2) !== 2) {
        return '';
    }
    $lc = strtolower($iso2);
    $cp1 = 0x1F1E6 + ord($iso2[0]) - 65;
    $cp2 = 0x1F1E6 + ord($iso2[1]) - 65;
    $emoji = (function_exists('mb_chr') ? mb_chr($cp1, 'UTF-8') : '') . (function_exists('mb_chr') ? mb_chr($cp2, 'UTF-8') : '');
    return '<img class="archive-iso-flag" src="https://flagcdn.com/w40/' . e($lc) . '.png" alt="' . e($iso2) . '" width="20" height="15" loading="lazy">'
        . ($emoji !== '' ? '<span class="visually-hidden">' . e($emoji) . '</span>' : '');
}

function archive_country_cell_html(int $id): string
{
    $row = archive_country_row($id);
    if (!$row) {
        return '';
    }
    $label = bilingual_label([
        'name_ar' => (string) ($row['name_ar'] ?? ''),
        'name_en' => (string) ($row['name_en'] ?? ''),
        'name' => (string) ($row['name_ar'] ?? ''),
    ]);
    if ($label === '') {
        return '';
    }
    $flag = archive_iso2_flag_html((string) ($row['iso2'] ?? ''));
    return '<span class="archive-country-cell">' . $flag . '<span>' . e($label) . '</span></span>';
}

/**
 * @return array<string,array{list:string,view:string,form:string,save:string,delete:string,file:string,print:string,list_print:string,export:string}>
 */
function archive_register_pages(): array
{
    return [
        'scholarships' => [
            'list' => 'archive/scholarships/index.php',
            'view' => 'archive/scholarships/view.php',
            'form' => 'archive/scholarships/create.php',
            'save' => 'archive/scholarships/save.php',
            'delete' => 'archive/scholarships/delete.php',
            'file' => 'archive/scholarships/file.php',
            'file_delete' => 'archive/scholarships/file_delete.php',
            'print' => 'archive/scholarships/print.php',
            'list_print' => 'archive/scholarships/list_print.php',
            'export' => 'archive/scholarships/export.php',
        ],
        'projects' => [
            'list' => 'archive/projects/index.php',
            'view' => 'archive/projects/view.php',
            'form' => 'archive/projects/create.php',
            'save' => 'archive/projects/save.php',
            'delete' => 'archive/projects/delete.php',
            'file' => 'archive/projects/file.php',
            'file_delete' => 'archive/projects/file_delete.php',
            'print' => 'archive/projects/print.php',
            'list_print' => 'archive/projects/list_print.php',
            'export' => 'archive/projects/export.php',
        ],
        'finance' => [
            'list' => 'archive/finance/index.php',
            'view' => 'archive/finance/view.php',
            'form' => 'archive/finance/create.php',
            'save' => 'archive/finance/save.php',
            'delete' => 'archive/finance/delete.php',
            'file' => 'archive/finance/file.php',
            'file_delete' => 'archive/finance/file_delete.php',
            'print' => 'archive/finance/print.php',
            'list_print' => 'archive/finance/list_print.php',
            'export' => 'archive/finance/export.php',
        ],
    ];
}

function archive_register_url(string $kind, string $page, array $query = []): string
{
    $path = archive_register_pages()[$kind][$page] ?? '';
    if ($path === '') {
        return base_url('archive/index.php');
    }
    $qs = http_build_query(array_filter($query, static fn($v) => $v !== '' && $v !== null));
    return base_url($path) . ($qs !== '' ? '?' . $qs : '');
}

function archive_city_name_norm(string $s): string
{
    $s = function_exists('archive_clip_lookup') ? archive_clip_lookup($s) : trim(preg_replace('/\s+/u', ' ', $s) ?? $s);
    $s = strtr($s, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ة' => 'ه', 'ى' => 'ي']);
    $s = trim($s, " \t\n\r\0\x0B-ـ,،.");
    return function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
}

function archive_city_name_usable(string $norm, string $display): bool
{
    if ($norm === '' || mb_strlen($norm) < 2 || mb_strlen($display) > 80) {
        return false;
    }
    if (preg_match('/^\d+$/u', $norm)) {
        return false;
    }
    $skip = ['غير محدد', 'غير معروف', 'بدون', '-', 'n/a', 'na', 'none', 'null', '0'];
    foreach ($skip as $bad) {
        if ($norm === archive_city_name_norm($bad)) {
            return false;
        }
    }
    return true;
}

function archive_projects_sync_text_cities(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    if (!dms_db_table_exists('tblarchive_projects') || !dms_db_table_exists('tblarchive_city')) {
        return;
    }
    try {
        $pending = (int) db()->query(
            "SELECT COUNT(*) FROM `tblarchive_projects`
             WHERE (CITY_ID IS NULL OR CITY_ID = 0)
               AND CITY_AR IS NOT NULL AND TRIM(CITY_AR) <> ''"
        )->fetchColumn();
        if ($pending < 1) {
            return;
        }
        $map = [];
        $cities = db()->query('SELECT id, name_ar, name_en FROM `tblarchive_city`')->fetchAll() ?: [];
        foreach ($cities as $city) {
            $id = (int) ($city['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            foreach (['name_ar', 'name_en'] as $k) {
                $n = archive_city_name_norm((string) ($city[$k] ?? ''));
                if ($n !== '' && !isset($map[$n])) {
                    $map[$n] = $id;
                }
            }
        }
        $groups = [];
        $st = db()->query(
            'SELECT CITY_AR, CITY_EN, COUNTRY_ID, COUNT(*) AS c
             FROM `tblarchive_projects`
             WHERE CITY_AR IS NOT NULL AND TRIM(CITY_AR) <> \'\'
             GROUP BY CITY_AR, CITY_EN, COUNTRY_ID'
        );
        foreach ($st as $row) {
            $ar = function_exists('archive_clip_lookup') ? archive_clip_lookup((string) ($row['CITY_AR'] ?? '')) : trim((string) ($row['CITY_AR'] ?? ''));
            $en = function_exists('archive_clip_lookup') ? archive_clip_lookup((string) ($row['CITY_EN'] ?? '')) : trim((string) ($row['CITY_EN'] ?? ''));
            $nAr = archive_city_name_norm($ar);
            if (!archive_city_name_usable($nAr, $ar)) {
                continue;
            }
            $nEn = archive_city_name_norm($en);
            $cnt = (int) ($row['c'] ?? 0);
            $country = (int) ($row['COUNTRY_ID'] ?? 0);
            if (!isset($groups[$nAr])) {
                $groups[$nAr] = ['ar' => $ar, 'en' => $en, 'en_n' => $nEn, 'c' => 0, 'country' => 0, 'country_n' => 0];
            }
            $g =& $groups[$nAr];
            $g['c'] += $cnt;
            if ($cnt > 0 && mb_strlen($ar) <= mb_strlen($g['ar'])) {
                $g['ar'] = $ar;
            }
            if ($nEn !== '' && ($g['en'] === '' || $cnt >= (int) ($g['c'] - $cnt))) {
                $g['en'] = $en;
                $g['en_n'] = $nEn;
            }
            if ($country > 0) {
                if ($g['country'] === 0 || $g['country'] === $country) {
                    $g['country'] = $country;
                    $g['country_n']++;
                } else {
                    $g['country'] = 0;
                }
            }
            unset($g);
        }
        $maxSort = (int) db()->query('SELECT COALESCE(MAX(sort_order), 0) FROM `tblarchive_city`')->fetchColumn();
        $hasCountry = dms_db_column_exists('tblarchive_city', 'country_id');
        $ins = $hasCountry
            ? db()->prepare('INSERT INTO `tblarchive_city` (legacy_id, name_ar, name_en, sort_order, is_active, country_id) VALUES (NULL, ?, ?, ?, 1, ?)')
            : db()->prepare('INSERT INTO `tblarchive_city` (legacy_id, name_ar, name_en, sort_order, is_active) VALUES (NULL, ?, ?, ?, 1)');
        foreach ($groups as $nAr => $g) {
            $id = $map[$nAr] ?? 0;
            if ($id < 1 && $g['en_n'] !== '') {
                $id = $map[$g['en_n']] ?? 0;
            }
            if ($id > 0) {
                $map[$nAr] = $id;
                if ($g['en_n'] !== '') {
                    $map[$g['en_n']] = $id;
                }
                continue;
            }
            $maxSort += 10;
            $country = $g['country'] > 0 ? $g['country'] : null;
            if ($hasCountry) {
                $ins->execute([$g['ar'], $g['en'] !== '' ? $g['en'] : null, $maxSort, $country]);
            } else {
                $ins->execute([$g['ar'], $g['en'] !== '' ? $g['en'] : null, $maxSort]);
            }
            $id = (int) db()->lastInsertId();
            if ($id > 0) {
                $map[$nAr] = $id;
                if ($g['en_n'] !== '') {
                    $map[$g['en_n']] = $id;
                }
            }
        }
        $upd = db()->prepare('UPDATE `tblarchive_projects` SET CITY_ID = ? WHERE id = ?');
        $rows = db()->query(
            'SELECT id, CITY_AR, CITY_EN FROM `tblarchive_projects` WHERE CITY_ID IS NULL OR CITY_ID = 0'
        );
        foreach ($rows as $row) {
            $nAr = archive_city_name_norm((string) ($row['CITY_AR'] ?? ''));
            $nEn = archive_city_name_norm((string) ($row['CITY_EN'] ?? ''));
            $id = ($nAr !== '' ? ($map[$nAr] ?? 0) : 0) ?: ($nEn !== '' ? ($map[$nEn] ?? 0) : 0);
            if ($id > 0) {
                $upd->execute([$id, (int) $row['id']]);
            }
        }
    } catch (Throwable $e) {
        $done = false;
    }
}

function archive_cities_dedupe(): void
{
    static $done = false;
    if ($done || !dms_db_table_exists('tblarchive_city')) {
        return;
    }
    $done = true;
    try {
        $rows = db()->query(
            'SELECT id, legacy_id, name_ar, name_en, country_id FROM `tblarchive_city` ORDER BY id'
        )->fetchAll() ?: [];
        if (count($rows) < 2) {
            return;
        }
        $byId = [];
        foreach ($rows as $row) {
            $byId[(int) $row['id']] = $row;
        }
        $parent = [];
        $find = static function (int $x) use (&$parent, &$find): int {
            if (!isset($parent[$x]) || $parent[$x] === $x) {
                $parent[$x] = $x;
                return $x;
            }
            return $parent[$x] = $find($parent[$x]);
        };
        $union = static function (int $a, int $b) use (&$parent, $find): void {
            $ra = $find($a);
            $rb = $find($b);
            if ($ra !== $rb) {
                $parent[$rb] = $ra;
            }
        };
        $groupsAr = [];
        $groupsEn = [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $parent[$id] = $id;
            $na = archive_city_name_norm((string) $row['name_ar']);
            $ne = archive_city_name_norm((string) $row['name_en']);
            if ($na !== '') {
                $groupsAr[$na][] = $id;
            }
            if ($ne !== '' && mb_strlen($ne) >= 4) {
                $groupsEn[$ne][] = $id;
            }
        }
        foreach ($groupsAr as $ids) {
            $first = (int) $ids[0];
            for ($i = 1, $n = count($ids); $i < $n; $i++) {
                $union($first, (int) $ids[$i]);
            }
        }
        foreach ($groupsEn as $ids) {
            $countries = [];
            foreach ($ids as $id) {
                $c = (int) ($byId[$id]['country_id'] ?? 0);
                if ($c > 0) {
                    $countries[$c] = true;
                }
            }
            if (count($countries) > 1) {
                continue;
            }
            $first = (int) $ids[0];
            for ($i = 1, $n = count($ids); $i < $n; $i++) {
                $union($first, (int) $ids[$i]);
            }
        }
        $clusters = [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $clusters[$find($id)][] = $id;
        }
        $updCity = db()->prepare(
            'UPDATE `tblarchive_city` SET name_ar = ?, name_en = ?, country_id = ? WHERE id = ?'
        );
        $updProj = dms_db_table_exists('tblarchive_projects') && dms_db_column_exists('tblarchive_projects', 'CITY_ID')
            ? db()->prepare('UPDATE `tblarchive_projects` SET CITY_ID = ? WHERE CITY_ID = ?')
            : null;
        $del = db()->prepare('DELETE FROM `tblarchive_city` WHERE id = ?');
        $merged = 0;
        foreach ($clusters as $ids) {
            $ids = array_values(array_unique(array_map('intval', $ids)));
            if (count($ids) < 2) {
                continue;
            }
            usort($ids, static function (int $a, int $b) use ($byId): int {
                $la = (int) ($byId[$a]['legacy_id'] ?? 0) > 0 ? 0 : 1;
                $lb = (int) ($byId[$b]['legacy_id'] ?? 0) > 0 ? 0 : 1;
                if ($la !== $lb) {
                    return $la <=> $lb;
                }
                return $a <=> $b;
            });
            $keep = $ids[0];
            $keepAr = trim((string) ($byId[$keep]['name_ar'] ?? ''));
            $keepEn = trim((string) ($byId[$keep]['name_en'] ?? ''));
            $keepCo = (int) ($byId[$keep]['country_id'] ?? 0);
            foreach ($ids as $id) {
                $ar = trim((string) ($byId[$id]['name_ar'] ?? ''), " \t-ـ/");
                $en = trim((string) ($byId[$id]['name_en'] ?? ''));
                $co = (int) ($byId[$id]['country_id'] ?? 0);
                if ($ar !== '' && (mb_strlen($ar) < mb_strlen($keepAr) || preg_match('/[-\\/]$/u', $keepAr))) {
                    $keepAr = $ar;
                }
                if ($keepEn === '' && $en !== '') {
                    $keepEn = $en;
                }
                if ($keepCo < 1 && $co > 0) {
                    $keepCo = $co;
                }
            }
            $updCity->execute([$keepAr, $keepEn !== '' ? $keepEn : null, $keepCo > 0 ? $keepCo : null, $keep]);
            for ($i = 1, $n = count($ids); $i < $n; $i++) {
                $drop = $ids[$i];
                if ($updProj) {
                    $updProj->execute([$keep, $drop]);
                }
                $del->execute([$drop]);
                $merged++;
            }
        }
        unset($merged);
    } catch (Throwable $e) {
        $done = false;
    }
}

function archive_projects_backfill_country(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    if (!dms_db_table_exists('tblarchive_projects') || !dms_db_column_exists('tblarchive_projects', 'COUNTRY_ID')) {
        return;
    }
    try {
        $empty = (int) db()->query(
            'SELECT COUNT(*) FROM `tblarchive_projects` WHERE COUNTRY_ID IS NULL OR COUNTRY_ID = 0'
        )->fetchColumn();
        if ($empty < 1) {
            return;
        }
        if (dms_db_table_exists('tblarchive_city') && dms_db_column_exists('tblarchive_city', 'country_id')) {
            db()->exec(
                'UPDATE `tblarchive_projects` p
                 INNER JOIN `tblarchive_city` c ON c.id = p.CITY_ID AND c.country_id IS NOT NULL AND c.country_id > 0
                 SET p.COUNTRY_ID = c.country_id
                 WHERE p.COUNTRY_ID IS NULL OR p.COUNTRY_ID = 0'
            );
            if (dms_db_column_exists('tblarchive_city', 'legacy_id')) {
                db()->exec(
                    'UPDATE `tblarchive_projects` p
                     INNER JOIN `tblarchive_city` c ON c.legacy_id = p.CITY_ID AND c.country_id IS NOT NULL AND c.country_id > 0
                     SET p.COUNTRY_ID = c.country_id
                     WHERE p.COUNTRY_ID IS NULL OR p.COUNTRY_ID = 0'
                );
            }
            $cities = db()->query(
                'SELECT name_ar, name_en, country_id FROM `tblarchive_city` WHERE country_id IS NOT NULL AND country_id > 0'
            )->fetchAll() ?: [];
            $byName = [];
            foreach ($cities as $city) {
                $cid = (int) ($city['country_id'] ?? 0);
                if ($cid < 1) {
                    continue;
                }
                foreach (['name_ar', 'name_en'] as $k) {
                    $n = trim(preg_replace('/\s+/u', ' ', (string) ($city[$k] ?? '')) ?? '');
                    if ($n !== '') {
                        $byName[mb_strtolower($n)] = $cid;
                    }
                }
            }
            if ($byName !== []) {
                $st = db()->query(
                    'SELECT id, CITY_AR, CITY_EN FROM `tblarchive_projects` WHERE COUNTRY_ID IS NULL OR COUNTRY_ID = 0'
                );
                $upd = db()->prepare('UPDATE `tblarchive_projects` SET COUNTRY_ID = ? WHERE id = ?');
                foreach ($st as $row) {
                    $cid = 0;
                    foreach (['CITY_AR', 'CITY_EN'] as $k) {
                        $n = trim(preg_replace('/\s+/u', ' ', (string) ($row[$k] ?? '')) ?? '');
                        if ($n !== '' && isset($byName[mb_strtolower($n)])) {
                            $cid = $byName[mb_strtolower($n)];
                            break;
                        }
                    }
                    if ($cid > 0) {
                        $upd->execute([$cid, (int) $row['id']]);
                    }
                }
            }
        }
    } catch (Throwable $e) {
        $done = false;
    }
}

function archive_projects_remap_legacy_lookups(): void
{
    static $done = false;
    if ($done || !dms_db_table_exists('tblarchive_projects')) {
        return;
    }
    $done = true;
    try {
        if (dms_db_column_exists('tblarchive_projects', 'REGION_ID') && dms_db_table_exists('tblarchive_region')) {
            $need = (int) db()->query(
                'SELECT COUNT(*) FROM `tblarchive_projects` WHERE REGION_ID IN (10, 11)'
            )->fetchColumn();
            if ($need > 0) {
                db()->exec(
                    'UPDATE `tblarchive_projects` p
                     INNER JOIN `tblarchive_region` r ON r.legacy_id = p.REGION_ID
                     SET p.REGION_ID = r.id'
                );
            }
        }
        if (dms_db_column_exists('tblarchive_projects', 'PROJECT_TYPE') && dms_db_table_exists('tblarchive_project_types')) {
            $need = (int) db()->query(
                'SELECT COUNT(*) FROM `tblarchive_projects` WHERE PROJECT_TYPE IN (13, 14, 20, 26, 27)'
            )->fetchColumn();
            if ($need > 0) {
                db()->exec(
                    'UPDATE `tblarchive_projects` p
                     INNER JOIN `tblarchive_project_types` t ON t.legacy_id = p.PROJECT_TYPE
                     SET p.PROJECT_TYPE = t.id'
                );
            }
        }
    } catch (Throwable $e) {
        $done = false;
    }
}

function archive_ensure_register_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        if (dms_db_table_exists('tblarchive_purchasing_document') && !dms_db_table_exists('tblarchive_finance')) {
            db()->exec('RENAME TABLE `tblarchive_purchasing_document` TO `tblarchive_finance`');
            dms_db_tables_map(true);
        }
        foreach (archive_register_defs() as $def) {
            $table = $def['table'];
            if (!preg_match('/^tblarchive_[a-z0-9_]+$/', $table)) {
                continue;
            }
            $cols = ['id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY'];
            foreach ($def['columns'] as $name => $ddl) {
                if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $name)) {
                    continue;
                }
                $cols[] = '`' . $name . '` ' . $ddl;
            }
            $cols[] = 'created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP';
            $cols[] = 'updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP';
            db()->exec(
                'CREATE TABLE IF NOT EXISTS `' . $table . '` (' . implode(', ', $cols) . ')
                 ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
            if ($table === 'tblarchive_projects') {
                if (dms_db_column_exists($table, 'PROJECT_STARTING_DATE_GREG') && !dms_db_column_exists($table, 'DATE_GREG')) {
                    db()->exec('ALTER TABLE `tblarchive_projects` CHANGE `PROJECT_STARTING_DATE_GREG` `DATE_GREG` TEXT NULL');
                }
                if (dms_db_column_exists($table, 'PROJECT_STARTING_DATE_HIJRI') && !dms_db_column_exists($table, 'DATE_HIJRI')) {
                    db()->exec('ALTER TABLE `tblarchive_projects` CHANGE `PROJECT_STARTING_DATE_HIJRI` `DATE_HIJRI` TEXT NULL');
                }
            }
            if (!dms_db_column_exists($table, 'archive_no')) {
                db()->exec("ALTER TABLE `{$table}` ADD COLUMN `archive_no` VARCHAR(40) NULL AFTER `id`");
            }
            foreach ($def['columns'] as $name => $ddl) {
                if ($name === 'archive_no' || !preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $name)) {
                    continue;
                }
                if (!dms_db_column_exists($table, $name)) {
                    db()->exec("ALTER TABLE `{$table}` ADD COLUMN `{$name}` {$ddl}");
                }
            }
            if (!dms_db_column_exists($table, 'file_folder')) {
                db()->exec("ALTER TABLE `{$table}` ADD COLUMN `file_folder` VARCHAR(500) NULL");
            }
        }
        archive_projects_backfill_country();
        archive_projects_sync_text_cities();
        archive_cities_dedupe();
        archive_projects_remap_legacy_lookups();
        archive_register_backfill_numbers();
        archive_disk_ensure_kind_roots();
        archive_registers_rename_legacy_files_once();
        archive_registers_rename_legacy_folders_once();
    } catch (Throwable $e) {
        $done = false;
    }
}

function archive_register_seq_scope(string $kind): string
{
    return 'reg-' . preg_replace('/[^a-z0-9_]/', '', $kind);
}

/** رقم السجل: سنة من 4 خانات / تسلسل 4+ حتى لا يسبق 2006 عام 1998 */
function archive_register_format_no(string $prefix, int $year, int $seq): string
{
    $pad = function_exists('corr_seq_pad') ? corr_seq_pad($seq) : sprintf('%04d', max(0, $seq));
    $prefix = function_exists('corr_sanitize_prefix') ? corr_sanitize_prefix($prefix) : trim($prefix);
    $mode = function_exists('corr_numbering_year_mode') ? corr_numbering_year_mode() : 'hijri';
    if ($mode === 'none' || $year <= 0) {
        $core = $pad;
    } else {
        $core = sprintf('%04d', $year) . '/' . $pad;
    }
    return $prefix !== '' ? ($prefix . '/' . $core) : $core;
}

function archive_register_display_no(?string $raw): string
{
    $raw = trim((string) $raw);
    if ($raw === '') {
        return '';
    }
    $prefix = '';
    $yearPart = '';
    $seqPart = '';
    if (preg_match('~^([^/]+)/(\d{2,4})/(\d+)$~u', $raw, $m)) {
        $prefix = function_exists('corr_sanitize_prefix') ? corr_sanitize_prefix($m[1]) : trim($m[1]);
        $yearPart = $m[2];
        $seqPart = $m[3];
    } elseif (preg_match('~^(\d{2,4})/(\d+)$~u', $raw, $m)) {
        $yearPart = $m[1];
        $seqPart = $m[2];
    } elseif (preg_match('~^(\d+)$~u', $raw, $m)) {
        $seqPart = $m[1];
    } else {
        return $raw;
    }
    $core = function_exists('corr_seq_pad') ? corr_seq_pad((int) $seqPart) : sprintf('%04d', (int) $seqPart);
    if ($yearPart !== '') {
        $year = (int) $yearPart;
        $yearDisp = strlen($yearPart) >= 4 ? sprintf('%04d', $year) : sprintf('%02d', abs($year) % 100);
        $core = $yearDisp . '/' . $core;
    }
    if ($prefix !== '') {
        $core = $prefix . '/' . $core;
    }
    return $core;
}

function archive_register_date_raw(array $row, array $keys): string
{
    foreach ($keys as $key) {
        $v = trim((string) ($row[$key] ?? ''));
        $v = str_replace(['هـ', 'ه', '\\'], ['', '', '/'], $v);
        $v = preg_replace('/\s+/u', '', $v) ?? $v;
        if ($v !== '' && $v !== '0') {
            return $v;
        }
    }
    return '';
}

function archive_register_parse_greg(string $raw): string
{
    $raw = trim($raw);
    if ($raw === '') {
        return '';
    }
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $raw, $m) && (int) $m[1] >= 1700) {
        return sprintf('%04d-%02d-%02d', (int) $m[1], (int) $m[2], (int) $m[3]);
    }
    if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $raw, $m) && (int) $m[1] >= 1700) {
        return sprintf('%04d-%02d-%02d', (int) $m[1], (int) $m[2], (int) $m[3]);
    }
    if (preg_match('/^\d{4}$/', $raw) && (int) $raw >= 1700) {
        return $raw . '-01-01';
    }
    return function_exists('dms_parse_gregorian_ymd') ? dms_parse_gregorian_ymd($raw) : '';
}

/** @return array{greg:string,hijri_y:int,sort:string} */
function archive_register_row_date_info(string $kind, array $row): array
{
    $gregRaw = '';
    $hijriRaw = '';
    if ($kind === 'scholarships') {
        $gregRaw = archive_register_date_raw($row, ['APPLICATION_DATE_GREG']);
        $hijriRaw = archive_register_date_raw($row, ['APPLICATION_DATE_HIJRI']);
    } elseif ($kind === 'projects') {
        $gregRaw = archive_register_date_raw($row, ['DATE_GREG', 'PROJECT_STARTING_DATE_GREG']);
        $hijriRaw = archive_register_date_raw($row, ['DATE_HIJRI', 'PROJECT_STARTING_DATE_HIJRI']);
    } else {
        $hijriRaw = archive_register_date_raw($row, ['HIJRIDATE']);
        $gregRaw = archive_register_date_raw($row, ['YEAR']);
    }
    $createdRaw = archive_register_date_raw($row, ['CREATED_DATE', 'created_at', 'UPDATED_DATE']);
    $greg = archive_register_parse_greg($gregRaw);
    $hijriY = 0;
    $hijriSort = '99999999';
    $h = preg_replace('/[^\d\/\-.]/u', '', $hijriRaw) ?? $hijriRaw;
    if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $h, $m) && (int) $m[1] >= 1300 && (int) $m[1] < 1700) {
        $hijriY = (int) $m[1];
        $hijriSort = $m[1] . $m[2] . $m[3];
        if ($greg === '' && function_exists('hijri_to_gregorian_date')) {
            $greg = hijri_to_gregorian_date($m[1] . '/' . $m[2] . '/' . $m[3]);
        }
    } elseif (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})$/', $h, $m) && (int) $m[3] >= 1300 && (int) $m[3] < 1700) {
        $hijriY = (int) $m[3];
        $hijriSort = sprintf('%04d%02d%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        if ($greg === '' && function_exists('hijri_to_gregorian_date')) {
            $greg = hijri_to_gregorian_date($h);
        }
    } elseif (preg_match('/^(\d{4})[\/\-.](\d{1,2})[\/\-.](\d{1,2})$/', $h, $m) && (int) $m[1] >= 1300 && (int) $m[1] < 1700) {
        $hijriY = (int) $m[1];
        $hijriSort = sprintf('%04d%02d%02d', (int) $m[1], (int) $m[2], (int) $m[3]);
        if ($greg === '' && function_exists('hijri_to_gregorian_date')) {
            $greg = hijri_to_gregorian_date($h);
        }
    }
    if ($greg === '') {
        $greg = archive_register_parse_greg($createdRaw);
    }
    if ($hijriY < 1 && $greg !== '' && function_exists('gregorian_to_hijri_parts')) {
        $p = gregorian_to_hijri_parts((int) substr($greg, 0, 4), (int) substr($greg, 5, 2), (int) substr($greg, 8, 2));
        if ($p) {
            $hijriY = (int) $p[0];
            if ($hijriSort === '99999999') {
                $hijriSort = sprintf('%04d%02d%02d', (int) $p[0], (int) $p[1], (int) $p[2]);
            }
        }
    }
    $gregSort = $greg !== '' ? str_replace('-', '', $greg) : '99999999';
    $sort = $gregSort . '-' . $hijriSort . '-' . str_pad((string) (int) ($row['id'] ?? 0), 10, '0', STR_PAD_LEFT);
    return ['greg' => $greg, 'hijri_y' => $hijriY, 'sort' => $sort];
}

function archive_register_row_year_from_info(array $info): int
{
    $mode = function_exists('corr_numbering_year_mode') ? corr_numbering_year_mode() : 'hijri';
    if ($mode === 'none') {
        return 0;
    }
    if ($mode === 'gregorian') {
        if ($info['greg'] !== '') {
            return (int) substr($info['greg'], 0, 4);
        }
        return $info['hijri_y'] > 0 ? $info['hijri_y'] : 0;
    }
    if ($info['hijri_y'] > 0) {
        return $info['hijri_y'];
    }
    if ($info['greg'] !== '' && function_exists('corr_numbering_year')) {
        return corr_numbering_year($info['greg']);
    }
    return 0;
}

function archive_register_row_year(string $kind, array $row): int
{
    return archive_register_row_year_from_info(archive_register_row_date_info($kind, $row));
}

function archive_register_max_used_sequence(string $kind, int $year, string $prefix = ''): int
{
    $def = archive_register_def($kind);
    if (!$def || !dms_db_column_exists($def['table'], 'archive_no')) {
        return 0;
    }
    $max = 0;
    try {
        $rows = db()->query('SELECT archive_no FROM `' . $def['table'] . '` WHERE archive_no IS NOT NULL AND archive_no <> ""')->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) {
        return 0;
    }
    foreach ($rows as $ref) {
        $seq = function_exists('corr_ref_extract_seq_for_year')
            ? corr_ref_extract_seq_for_year((string) $ref, $prefix, $year)
            : null;
        if ($seq !== null) {
            $max = max($max, $seq);
        }
    }
    return $max;
}

function next_archive_register_no(string $kind): string
{
    $def = archive_register_def($kind);
    if (!$def) {
        throw new RuntimeException(__('archive.number_fail'));
    }
    $year = function_exists('corr_numbering_hijri_year')
        ? corr_numbering_hijri_year()
        : (function_exists('corr_numbering_year') ? corr_numbering_year() : ((int) date('Y') - 579));
    $prefix = function_exists('archive_prefix') ? archive_prefix() : '';
    $scope = archive_register_seq_scope($kind);
    if (function_exists('ensure_sequences_schema')) {
        ensure_sequences_schema();
    }
    $pdo = db();
    $ownTx = !$pdo->inTransaction();
    if ($ownTx) {
        $pdo->beginTransaction();
    }
    try {
        $stmt = $pdo->prepare(
            "SELECT last_number FROM `tblsequences` WHERE seq_type = 'archive' AND seq_year = ? AND seq_scope = ? FOR UPDATE"
        );
        $stmt->execute([$year, $scope]);
        $last = $stmt->fetchColumn();
        if ($last === false) {
            $scopeMax = $pdo->prepare(
                "SELECT MAX(last_number) FROM `tblsequences` WHERE seq_type = 'archive' AND seq_scope = ?"
            );
            $scopeMax->execute([$scope]);
            $seed = (int) $scopeMax->fetchColumn();
            $pdo->prepare(
                "INSERT INTO `tblsequences` (seq_type, seq_year, seq_scope, last_number) VALUES ('archive', ?, ?, ?)"
            )->execute([$year, $scope, $seed]);
            $last = $seed;
        }
        $usedMax = archive_register_max_used_sequence($kind, $year, $prefix);
        $seq = max((int) $last, $usedMax);
        $no = '';
        $guard = 0;
        do {
            $guard++;
            $seq++;
            $yy = sprintf('%02d', abs($year) % 100);
            $pad = function_exists('corr_seq_pad') ? corr_seq_pad($seq) : sprintf('%04d', $seq);
            $no = $prefix !== '' ? ($prefix . '/' . $yy . '/' . $pad) : ($yy . '/' . $pad);
            $chk = $pdo->prepare('SELECT id FROM `' . $def['table'] . '` WHERE archive_no = ? LIMIT 1');
            $chk->execute([$no]);
        } while ($chk->fetchColumn() && $guard < 500);
        if ($guard >= 500) {
            throw new RuntimeException(__('archive.number_fail'));
        }
        $pdo->prepare(
            "UPDATE `tblsequences` SET last_number = ? WHERE seq_type = 'archive' AND seq_year = ? AND seq_scope = ?"
        )->execute([$seq, $year, $scope]);
        if ($ownTx) {
            $pdo->commit();
        }
        return $no;
    } catch (Throwable $e) {
        if ($ownTx && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function archive_register_backfill_numbers(bool $force = false): void
{
    if (!function_exists('ensure_sequences_schema')) {
        return;
    }
    ensure_sequences_schema();
    $prefix = function_exists('archive_prefix') ? archive_prefix() : '';
    $pdo = db();
    foreach (array_keys(archive_register_defs()) as $kind) {
        $def = archive_register_def($kind);
        if (!$def || !dms_db_column_exists($def['table'], 'archive_no')) {
            continue;
        }
        $table = $def['table'];
        $scope = archive_register_seq_scope($kind);
        try {
            if ($force) {
                $pdo->exec('UPDATE `' . $table . '` SET archive_no = NULL');
                $pdo->prepare("DELETE FROM `tblsequences` WHERE seq_type = 'archive' AND seq_scope = ?")->execute([$scope]);
                $sql = 'SELECT * FROM `' . $table . '`';
            } else {
                $sql = 'SELECT * FROM `' . $table . '` WHERE archive_no IS NULL OR TRIM(archive_no) = \'\'';
            }
            $rows = $pdo->query($sql)->fetchAll() ?: [];
        } catch (Throwable $e) {
            continue;
        }
        if ($rows === []) {
            continue;
        }
        foreach ($rows as $i => $row) {
            $info = archive_register_row_date_info($kind, $row);
            $rows[$i]['_sort'] = $info['sort'];
            $rows[$i]['_year'] = archive_register_row_year_from_info($info);
        }
        usort($rows, static fn(array $a, array $b): int => $a['_sort'] <=> $b['_sort']);
        $seq = 0;
        if (!$force) {
            try {
                $existing = $pdo->query(
                    'SELECT archive_no FROM `' . $table . '` WHERE archive_no IS NOT NULL AND TRIM(archive_no) <> \'\''
                )->fetchAll(PDO::FETCH_COLUMN) ?: [];
                foreach ($existing as $ref) {
                    if (preg_match('/(\d+)$/', (string) $ref, $m)) {
                        $seq = max($seq, (int) $m[1]);
                    }
                }
            } catch (Throwable $e) {
                $seq = 0;
            }
        }
        $upd = $pdo->prepare('UPDATE `' . $table . '` SET archive_no = ? WHERE id = ?');
        $pdo->beginTransaction();
        try {
            foreach ($rows as $row) {
                $seq++;
                $year = (int) $row['_year'];
                $no = archive_register_format_no($prefix, $year, $seq);
                $upd->execute([$no, (int) $row['id']]);
            }
            $curYear = function_exists('corr_numbering_year') ? corr_numbering_year() : 0;
            $ins = $pdo->prepare(
                'INSERT INTO `tblsequences` (seq_type, seq_year, seq_scope, last_number)
                 VALUES (\'archive\', ?, ?, ?)
                 ON DUPLICATE KEY UPDATE last_number = GREATEST(last_number, VALUES(last_number))'
            );
            $ins->execute([(int) $curYear, $scope, $seq]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        try {
            $pdo->exec('ALTER TABLE `' . $table . '` ADD UNIQUE KEY `uq_reg_archive_no` (`archive_no`)');
        } catch (Throwable $e) {
            // موجود مسبقاً
        }
    }
}

function archive_register_guess_folder(array $def, array $row): string
{
    $existing = trim((string) ($row['file_folder'] ?? ''));
    if ($existing !== '' && is_dir($existing)) {
        return $existing;
    }
    $fromLegacy = archive_remap_legacy_path((string) ($row['File_Path_New'] ?? $row['FILE_PATH'] ?? ''));
    if ($fromLegacy !== '' && is_dir($fromLegacy)) {
        return $fromLegacy;
    }
    $hintCol = (string) ($def['folder_hint_col'] ?? '');
    $num = $hintCol !== '' ? trim((string) ($row[$hintCol] ?? '')) : '';
    if ($num !== '' && ctype_digit($num)) {
        foreach ($def['folder_kinds'] ?? [] as $kind) {
            $dir = (archive_disk_folders()[$kind] ?? '') . DIRECTORY_SEPARATOR . $num;
            if ($dir !== DIRECTORY_SEPARATOR . $num && is_dir($dir)) {
                return $dir;
            }
        }
    }
    $kindKey = archive_kind_folder_key((string) ($def['key'] ?? ''));
    $stem = archive_file_stem((string) ($row['archive_no'] ?? ''), (int) ($row['id'] ?? 0));
    $numbered = archive_disk_record_dir($kindKey, $stem);
    if ($stem !== '' && is_dir($numbered)) {
        return $numbered;
    }
    return is_dir($fromLegacy) ? $fromLegacy : $fromLegacy;
}

/**
 * @return list<string>
 */
function archive_register_folder_files(string $dir): array
{
    $out = [];
    if ($dir === '' || !is_dir($dir)) {
        return $out;
    }
    $items = @scandir($dir);
    if (!is_array($items)) {
        return $out;
    }
    foreach ($items as $name) {
        if ($name === '.' || $name === '..') {
            continue;
        }
        $full = $dir . DIRECTORY_SEPARATOR . $name;
        if (str_starts_with($name, '.__dms_ren_')) {
            continue;
        }
        if (is_file($full)) {
            $out[] = $full;
        }
    }
    sort($out);
    return $out;
}

function archive_register_file_display_name(array $row, array $paths, int $index): string
{
    $path = (string) ($paths[$index] ?? '');
    $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
    $stem = archive_file_stem((string) ($row['archive_no'] ?? ''), (int) ($row['id'] ?? 0));
    return archive_file_numbered_name($stem, $index, count($paths), $ext);
}

/** @return list<string> */
function archive_register_original_names(array $row): array
{
    $raw = trim((string) ($row['original_file_names'] ?? ''));
    if ($raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [];
    }
    $out = [];
    foreach ($decoded as $item) {
        if (is_string($item)) {
            $name = trim($item);
        } elseif (is_array($item)) {
            $name = trim((string) ($item['old'] ?? $item['name'] ?? ''));
        } else {
            $name = '';
        }
        if ($name !== '') {
            $out[] = $name;
        }
    }
    return $out;
}

function archive_registers_rename_legacy_files_once(): void
{
    if (trim((string) setting('archive_files_renamed_v1', '')) === '1') {
        return;
    }
    $stats = archive_registers_rename_legacy_files(['scholarships', 'projects']);
    if ((int) ($stats['errors'] ?? 1) === 0) {
        set_setting('archive_files_renamed_v1', '1', 'إعادة تسمية مرفقات المنح والمشاريع حسب رقم الأرشيف');
    }
}

/**
 * يحفظ الأسماء القديمة ثم يعيد تسمية الملفات حسب رقم الأرشيف داخل مجلد السجل الحالي.
 *
 * @param list<string> $kinds
 * @return array{rows:int,renamed:int,skipped:int,errors:int}
 */
function archive_registers_rename_legacy_files(array $kinds): array
{
    $stats = ['rows' => 0, 'renamed' => 0, 'skipped' => 0, 'errors' => 0];
    foreach ($kinds as $kind) {
        $def = archive_register_def($kind);
        if (!$def || !dms_db_column_exists($def['table'], 'original_file_names')) {
            continue;
        }
        try {
            $rows = db()->query('SELECT * FROM `' . $def['table'] . '`')->fetchAll() ?: [];
        } catch (Throwable $e) {
            $stats['errors']++;
            continue;
        }
        $upd = db()->prepare('UPDATE `' . $def['table'] . '` SET original_file_names = ? WHERE id = ?');
        foreach ($rows as $row) {
            $dir = archive_register_guess_folder($def, $row);
            $paths = archive_register_folder_files($dir);
            if ($paths === []) {
                $stats['skipped']++;
                continue;
            }
            $stats['rows']++;
            $oldNames = archive_register_original_names($row);
            if ($oldNames === []) {
                $oldNames = array_map('basename', $paths);
                $upd->execute([json_encode($oldNames, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), (int) $row['id']]);
            }
            $total = count($paths);
            $stem = archive_file_stem((string) ($row['archive_no'] ?? ''), (int) ($row['id'] ?? 0));
            $plan = [];
            foreach ($paths as $i => $path) {
                $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
                $target = archive_file_numbered_name($stem, (int) $i, $total, $ext);
                $plan[] = ['from' => $path, 'to' => $dir . DIRECTORY_SEPARATOR . $target, 'name' => $target];
            }
            $already = true;
            foreach ($plan as $step) {
                if (basename($step['from']) !== $step['name']) {
                    $already = false;
                    break;
                }
            }
            if ($already) {
                $stats['skipped']++;
                continue;
            }
            $temps = [];
            foreach ($plan as $i => $step) {
                if (basename($step['from']) === $step['name'] && is_file($step['from'])) {
                    $temps[$i] = $step['from'];
                    continue;
                }
                $ext = strtolower((string) pathinfo($step['from'], PATHINFO_EXTENSION));
                $tmp = $dir . DIRECTORY_SEPARATOR . '.__dms_ren_' . $i . '_' . bin2hex(random_bytes(3))
                    . ($ext !== '' ? '.' . $ext : '');
                if (!@rename($step['from'], $tmp)) {
                    $stats['errors']++;
                    continue 2;
                }
                $temps[$i] = $tmp;
            }
            foreach ($plan as $i => $step) {
                $tmp = $temps[$i] ?? '';
                if ($tmp === '' || $tmp === $step['to']) {
                    continue;
                }
                if (is_file($step['to']) && realpath($tmp) !== realpath($step['to'])) {
                    $stats['errors']++;
                    continue 2;
                }
                if (!@rename($tmp, $step['to'])) {
                    $stats['errors']++;
                    continue 2;
                }
                $stats['renamed']++;
            }
        }
    }
    return $stats;
}

function archive_disk_paths_equal(string $a, string $b): bool
{
    $ra = realpath($a);
    $rb = realpath($b);
    if ($ra !== false && $rb !== false) {
        return strtolower($ra) === strtolower($rb);
    }
    $na = strtolower(rtrim(str_replace('/', '\\', $a), '\\'));
    $nb = strtolower(rtrim(str_replace('/', '\\', $b), '\\'));
    return $na !== '' && $na === $nb;
}

function archive_disk_dir_is_empty(string $dir): bool
{
    $items = @scandir($dir);
    if (!is_array($items)) {
        return false;
    }
    foreach ($items as $name) {
        if ($name !== '.' && $name !== '..') {
            return false;
        }
    }
    return true;
}

function archive_disk_merge_record_dir(string $from, string $to): bool
{
    if (!is_dir($from)) {
        return true;
    }
    if (!is_dir($to) && !@mkdir($to, 0755, true) && !is_dir($to)) {
        return false;
    }
    if (archive_disk_paths_equal($from, $to)) {
        return true;
    }
    $items = @scandir($from);
    if (!is_array($items)) {
        return false;
    }
    foreach ($items as $name) {
        if ($name === '.' || $name === '..') {
            continue;
        }
        $src = $from . DIRECTORY_SEPARATOR . $name;
        $dst = $to . DIRECTORY_SEPARATOR . $name;
        if (is_dir($src)) {
            if (!archive_disk_merge_record_dir($src, $dst)) {
                return false;
            }
            continue;
        }
        if (!is_file($src)) {
            continue;
        }
        if (is_file($dst)) {
            $sameSize = @filesize($src) === @filesize($dst);
            if ($sameSize) {
                @unlink($src);
                continue;
            }
            return false;
        }
        if (!@rename($src, $dst)) {
            return false;
        }
    }
    return archive_disk_dir_is_empty($from) && @rmdir($from);
}

function archive_registers_rename_legacy_folders_once(): void
{
    if (trim((string) setting('archive_folders_renamed_v1', '')) === '1') {
        return;
    }
    $stats = archive_registers_rename_legacy_folders(['scholarships', 'projects']);
    if ((int) ($stats['errors'] ?? 1) === 0) {
        set_setting('archive_folders_renamed_v1', '1', 'إعادة تسمية مجلدات المنح والمشاريع حسب رقم الأرشيف');
    }
}

/**
 * يحفظ اسم المجلد القديم ثم يعيد تسمية مجلد السجل حسب رقم الأرشيف.
 *
 * @param list<string> $kinds
 * @return array{rows:int,renamed:int,merged:int,skipped:int,errors:int}
 */
function archive_registers_rename_legacy_folders(array $kinds): array
{
    $stats = ['rows' => 0, 'renamed' => 0, 'merged' => 0, 'skipped' => 0, 'errors' => 0];
    foreach ($kinds as $kind) {
        $def = archive_register_def($kind);
        if (!$def || !dms_db_column_exists($def['table'], 'file_folder')) {
            continue;
        }
        $hasOrig = dms_db_column_exists($def['table'], 'original_folder_name');
        try {
            $rows = db()->query('SELECT * FROM `' . $def['table'] . '`')->fetchAll() ?: [];
        } catch (Throwable $e) {
            $stats['errors']++;
            continue;
        }
        $updFolder = db()->prepare('UPDATE `' . $def['table'] . '` SET `file_folder` = ? WHERE id = ?');
        $updBoth = $hasOrig
            ? db()->prepare('UPDATE `' . $def['table'] . '` SET `file_folder` = ?, `original_folder_name` = ? WHERE id = ?')
            : null;
        $kindKey = archive_kind_folder_key((string) ($def['key'] ?? $kind));
        $kindRoot = rtrim((string) (archive_disk_folders()[$kindKey] ?? ''), "\\/");
        foreach ($rows as $row) {
            $dir = archive_register_guess_folder($def, $row);
            $stem = archive_file_stem((string) ($row['archive_no'] ?? ''), (int) ($row['id'] ?? 0));
            if ($dir === '' || !is_dir($dir) || $stem === '' || $kindRoot === '') {
                $stats['skipped']++;
                continue;
            }
            $target = archive_disk_record_dir($kindKey, $stem);
            $oldBase = basename(rtrim($dir, "\\/"));
            $orig = $hasOrig ? trim((string) ($row['original_folder_name'] ?? '')) : '';
            if ($orig === '' && $oldBase !== $stem) {
                $orig = $oldBase;
            }
            $persist = static function (string $path) use ($updFolder, $updBoth, $orig, $row): void {
                if ($updBoth && $orig !== '') {
                    $updBoth->execute([$path, $orig, (int) $row['id']]);
                    return;
                }
                $updFolder->execute([$path, (int) $row['id']]);
            };
            if (archive_disk_paths_equal($dir, $target) || $oldBase === $stem) {
                $persist($target);
                $stats['skipped']++;
                continue;
            }
            $fromReal = realpath($dir);
            $rootReal = realpath($kindRoot);
            if ($fromReal === false || $rootReal === false) {
                $stats['errors']++;
                continue;
            }
            $rootN = strtolower(rtrim(str_replace('/', '\\', $rootReal), '\\')) . '\\';
            $fromN = strtolower(str_replace('/', '\\', $fromReal)) . '\\';
            if (!str_starts_with($fromN, $rootN) || strtolower($fromReal) === strtolower($rootReal)) {
                $stats['errors']++;
                continue;
            }
            $stats['rows']++;
            if (is_dir($target) && !archive_disk_paths_equal($dir, $target)) {
                if (archive_disk_dir_is_empty($target)) {
                    @rmdir($target);
                }
            }
            $ok = false;
            if (is_dir($target) && !archive_disk_paths_equal($dir, $target)) {
                $ok = archive_disk_merge_record_dir($dir, $target);
                if ($ok) {
                    $stats['merged']++;
                }
            } else {
                $parent = dirname($target);
                if (!is_dir($parent) && !@mkdir($parent, 0755, true) && !is_dir($parent)) {
                    $stats['errors']++;
                    continue;
                }
                $ok = @rename($dir, $target);
                if ($ok) {
                    $stats['renamed']++;
                }
            }
            if (!$ok) {
                $stats['errors']++;
                continue;
            }
            $persist($target);
        }
    }
    return $stats;
}

function archive_collect_request_uploads(string $field = 'attachments'): array
{
    if (empty($_FILES[$field]) || !is_array($_FILES[$field])) {
        return [];
    }
    $bag = $_FILES[$field];
    if (!is_array($bag['name'] ?? null)) {
        return [$bag];
    }
    $out = [];
    foreach ($bag['name'] as $i => $name) {
        $out[] = [
            'name' => (string) $name,
            'type' => (string) ($bag['type'][$i] ?? ''),
            'tmp_name' => (string) ($bag['tmp_name'][$i] ?? ''),
            'error' => (int) ($bag['error'][$i] ?? UPLOAD_ERR_NO_FILE),
            'size' => (int) ($bag['size'][$i] ?? 0),
        ];
    }
    return $out;
}

function archive_upload_ext_map(): array
{
    return [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];
}

function archive_validate_upload_file(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException(__('archive.upload_fail'));
    }
    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new RuntimeException(__('archive.upload_fail'));
    }
    $allowed = archive_upload_ext_map();
    $mime = class_exists('finfo') ? (string) (new finfo(FILEINFO_MIME_TYPE))->file($tmp) : (string) ($file['type'] ?? '');
    if (!isset($allowed[$mime])) {
        throw new RuntimeException(__('archive.upload_type'));
    }
    if ((int) ($file['size'] ?? 0) > dms_document_upload_max_bytes()) {
        throw new RuntimeException(__('archive.upload_size'));
    }
    return $allowed[$mime];
}

function archive_register_set_file_folder(string $kind, int $id, string $dir): void
{
    $def = archive_register_def($kind);
    if (!$def || $id < 1 || $dir === '') {
        return;
    }
    db()->prepare('UPDATE `' . $def['table'] . '` SET `file_folder` = ? WHERE id = ?')->execute([$dir, $id]);
}

function archive_register_store_uploads(string $kind, int $id, array $filesBag, int $userId): int
{
    $row = archive_register_get($kind, $id);
    if (!$row) {
        return 0;
    }
    archive_disk_ensure_kind_roots();
    $stem = archive_file_stem((string) ($row['archive_no'] ?? ''), $id);
    $key = archive_kind_folder_key($kind);
    $dir = archive_disk_record_dir($key, $stem);
    $saved = 0;
    foreach ($filesBag as $file) {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $ext = archive_validate_upload_file($file);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException(__('archive.upload_dir'));
        }
        $n = count(archive_register_folder_files($dir)) + 1;
        do {
            $name = $n === 1 ? ($stem . '.' . $ext) : ($stem . '-' . $n . '.' . $ext);
            $dest = $dir . DIRECTORY_SEPARATOR . $name;
            $n++;
        } while (is_file($dest) && $n < 500);
        if (is_file($dest)) {
            throw new RuntimeException(__('archive.upload_save'));
        }
        $mover = function_exists('dms_move_uploaded_file') ? 'dms_move_uploaded_file' : 'move_uploaded_file';
        if (!$mover((string) $file['tmp_name'], $dest)) {
            throw new RuntimeException(__('archive.upload_save'));
        }
        $saved++;
        if ($userId > 0 && function_exists('log_activity')) {
            log_activity($userId, null, 'upload', $name, $id);
        }
    }
    if ($saved > 0) {
        archive_register_set_file_folder($kind, $id, $dir);
    }
    return $saved;
}

function archive_register_delete_file(string $kind, int $id, int $index): bool
{
    $def = archive_register_def($kind);
    $row = $def ? archive_register_get($kind, $id) : null;
    if (!$def || !$row || $index < 0) {
        return false;
    }
    $files = archive_register_folder_files(archive_register_guess_folder($def, $row));
    $path = (string) ($files[$index] ?? '');
    if ($path === '' || !archive_register_file_is_allowed($path) || !@unlink($path)) {
        return false;
    }
    if (!dms_db_column_exists($def['table'], 'original_file_names')) {
        return true;
    }
    $orig = archive_register_original_names($row);
    if ($orig === [] || !array_key_exists($index, $orig)) {
        return true;
    }
    array_splice($orig, $index, 1);
    db()->prepare('UPDATE `' . $def['table'] . '` SET original_file_names = ? WHERE id = ?')
        ->execute([json_encode(array_values($orig), JSON_UNESCAPED_UNICODE), $id]);
    return true;
}

function archive_register_file_is_allowed(string $path): bool
{
    $root = realpath(archive_disk_root());
    $real = realpath($path);
    if ($root === false || $real === false || !is_file($real)) {
        return false;
    }
    $rootN = strtolower(rtrim(str_replace('/', '\\', $root), '\\')) . '\\';
    $realN = strtolower(str_replace('/', '\\', $real));
    return str_starts_with($realN, $rootN);
}

function archive_register_title(array $def, array $row): string
{
    foreach ($def['title_cols'] ?? [] as $col) {
        $v = trim((string) ($row[$col] ?? ''));
        if ($v !== '') {
            return $v;
        }
    }
    return '#' . (int) ($row['id'] ?? 0);
}

function archive_register_count(string $kind): int
{
    $def = archive_register_def($kind);
    if (!$def) {
        return 0;
    }
    try {
        return (int) db()->query('SELECT COUNT(*) FROM `' . $def['table'] . '`')->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

function archive_register_files_count(string $kind): int
{
    $def = archive_register_def($kind);
    if (!$def) {
        return 0;
    }
    try {
        return (int) db()->query(
            'SELECT COUNT(*) FROM `' . $def['table'] . '`
             WHERE file_folder IS NOT NULL AND TRIM(file_folder) <> \'\''
        )->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

function archive_register_get(string $kind, int $id): ?array
{
    $def = archive_register_def($kind);
    if (!$def || $id < 1) {
        return null;
    }
    $st = db()->prepare('SELECT * FROM `' . $def['table'] . '` WHERE id = ? LIMIT 1');
    $st->execute([$id]);
    $row = $st->fetch();
    return $row ?: null;
}

function archive_register_request_filters(array $def): array
{
    $filters = [
        'q' => trim((string) ($_GET['q'] ?? '')),
        'from' => (string) ($_GET['from'] ?? ''),
        'to' => (string) ($_GET['to'] ?? ''),
        'hijri_from' => trim((string) ($_GET['hijri_from'] ?? '')),
        'hijri_to' => trim((string) ($_GET['hijri_to'] ?? '')),
    ];
    foreach ($def['filters'] ?? [] as $flt) {
        $name = (string) ($flt['name'] ?? '');
        if ($name !== '') {
            $filters[$name] = trim((string) ($_GET[$name] ?? ''));
        }
    }
    if (function_exists('correspondence_normalize_date_filters')) {
        $filters = correspondence_normalize_date_filters($filters);
    }
    return $filters;
}

/** @return list<int> */
function archive_lookup_filter_ids(string $lookup, int $selectedId): array
{
    if ($selectedId < 1) {
        return [];
    }
    $ids = [$selectedId];
    foreach (archive_lookups($lookup, false) as $row) {
        if ((int) ($row['id'] ?? 0) !== $selectedId) {
            continue;
        }
        $legacy = (int) ($row['legacy_id'] ?? 0);
        if ($legacy > 0) {
            $ids[] = $legacy;
        }
        break;
    }
    return array_values(array_unique($ids));
}

function archive_register_compact_sql(string $col): string
{
    return "REPLACE(REPLACE(REPLACE(TRIM(`{$col}`), '/', ''), '-', ''), '.', '')";
}

/**
 * @return array{0:string,1:list<mixed>}
 */
function archive_register_where(array $def, array $filters): array
{
    $where = '1=1';
    $params = [];
    $q = trim((string) ($filters['q'] ?? ''));
    if ($q !== '') {
        $parts = [];
        foreach ($def['search_cols'] as $col) {
            $parts[] = '`' . $col . '` LIKE ?';
            $params[] = '%' . $q . '%';
        }
        if ($parts) {
            $where .= ' AND (' . implode(' OR ', $parts) . ')';
        }
    }
    foreach ($def['filters'] ?? [] as $flt) {
        $name = (string) ($flt['name'] ?? '');
        $col = (string) ($flt['col'] ?? '');
        if ($name === '' || $col === '' || !preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $col)) {
            continue;
        }
        $ftype = (string) ($flt['type'] ?? '');
        if ($ftype === 'text') {
            $text = trim((string) ($filters[$name] ?? ''));
            if ($text === '') {
                continue;
            }
            $idCol = (string) ($flt['id_col'] ?? '');
            if ($idCol !== '' && preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $idCol)) {
                $ids = [];
                foreach (archive_lookups('city', false) as $opt) {
                    $ar = (string) ($opt['name_ar'] ?? '');
                    $en = (string) ($opt['name_en'] ?? '');
                    if (mb_stripos($ar, $text) !== false || ($en !== '' && mb_stripos($en, $text) !== false)) {
                        $ids[] = (int) ($opt['id'] ?? 0);
                        $legacy = (int) ($opt['legacy_id'] ?? 0);
                        if ($legacy > 0) {
                            $ids[] = $legacy;
                        }
                    }
                }
                $ids = array_values(array_filter(array_unique($ids)));
                if ($ids !== []) {
                    $ph = implode(',', array_fill(0, count($ids), '?'));
                    $where .= ' AND (`' . $col . '` LIKE ? OR `' . $idCol . '` IN (' . $ph . '))';
                    $params[] = '%' . $text . '%';
                    foreach ($ids as $id) {
                        $params[] = $id;
                    }
                } else {
                    $where .= ' AND `' . $col . '` LIKE ?';
                    $params[] = '%' . $text . '%';
                }
            } else {
                $where .= ' AND `' . $col . '` LIKE ?';
                $params[] = '%' . $text . '%';
            }
            continue;
        }
        $lookup = (string) ($flt['lookup'] ?? '');
        $selected = (int) ($filters[$name] ?? 0);
        if ($selected < 1) {
            continue;
        }
        $ids = $lookup !== '' ? archive_lookup_filter_ids($lookup, $selected) : [$selected];
        if ($ids === []) {
            continue;
        }
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $where .= ' AND `' . $col . '` IN (' . $ph . ')';
        foreach ($ids as $id) {
            $params[] = $id;
        }
    }
    $dateCol = (string) ($def['date_col'] ?? '');
    $dateGregCol = (string) ($def['date_greg_col'] ?? '');
    if ($dateCol !== '' && preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $dateCol)) {
        $from = trim((string) ($filters['from'] ?? ''));
        $to = trim((string) ($filters['to'] ?? ''));
        $gregOk = $dateGregCol !== '' && preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $dateGregCol);
        $hExpr = archive_register_compact_sql($dateCol);
        $gExpr = $gregOk ? archive_register_compact_sql($dateGregCol) : '';
        $hFrom = '';
        $hTo = '';
        $gFrom = '';
        $gTo = '';
        if ($from !== '') {
            $hFrom = str_replace('/', '', archive_format_compact_date(format_hijri_date($from) ?: (string) ($filters['hijri_from'] ?? '')));
            $gFrom = preg_replace('/\D+/', '', $from) ?? '';
        }
        if ($to !== '') {
            $hTo = str_replace('/', '', archive_format_compact_date(format_hijri_date($to) ?: (string) ($filters['hijri_to'] ?? '')));
            $gTo = preg_replace('/\D+/', '', $to) ?? '';
        }
        $hOk = ($hFrom === '' || strlen($hFrom) === 8) && ($hTo === '' || strlen($hTo) === 8) && ($hFrom !== '' || $hTo !== '');
        $gOk = $gregOk && ($gFrom === '' || strlen($gFrom) === 8) && ($gTo === '' || strlen($gTo) === 8) && ($gFrom !== '' || $gTo !== '');
        $parts = [];
        if ($hOk) {
            $hPart = '1=1';
            $hParams = [];
            if (strlen($hFrom) === 8) {
                $hPart .= ' AND ' . $hExpr . ' >= ?';
                $hParams[] = $hFrom;
            }
            if (strlen($hTo) === 8) {
                $hPart .= ' AND ' . $hExpr . ' <= ?';
                $hParams[] = $hTo;
            }
            $parts[] = ['sql' => '(' . $hPart . ')', 'params' => $hParams];
        }
        if ($gOk) {
            $gPart = '1=1';
            $gParams = [];
            if (strlen($gFrom) === 8) {
                $gPart .= ' AND ' . $gExpr . ' >= ?';
                $gParams[] = $gFrom;
            }
            if (strlen($gTo) === 8) {
                $gPart .= ' AND ' . $gExpr . ' <= ?';
                $gParams[] = $gTo;
            }
            $parts[] = ['sql' => '(' . $gPart . ')', 'params' => $gParams];
        }
        if (count($parts) === 1) {
            $where .= ' AND ' . $parts[0]['sql'];
            foreach ($parts[0]['params'] as $p) {
                $params[] = $p;
            }
        } elseif (count($parts) === 2) {
            $where .= ' AND (' . $parts[0]['sql'] . ' OR ' . $parts[1]['sql'] . ')';
            foreach ($parts[0]['params'] as $p) {
                $params[] = $p;
            }
            foreach ($parts[1]['params'] as $p) {
                $params[] = $p;
            }
        }
    }
    return [$where, $params];
}

function archive_register_list(string $kind, string|array $qOrFilters, int $page, int $perPage = 30): array
{
    $def = archive_register_def($kind);
    if (!$def) {
        return ['rows' => [], 'total' => 0, 'pagination' => ['page' => 1, 'pages' => 1, 'total' => 0]];
    }
    $filters = is_array($qOrFilters) ? $qOrFilters : ['q' => (string) $qOrFilters];
    $page = max(1, $page);
    $perPage = $perPage > 100 ? max(1, min(20000, $perPage)) : max(10, min(100, $perPage));
    [$where, $params] = archive_register_where($def, $filters);
    $cnt = db()->prepare('SELECT COUNT(*) FROM `' . $def['table'] . '` WHERE ' . $where);
    $cnt->execute($params);
    $total = (int) $cnt->fetchColumn();
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min($page, $pages);
    $off = ($page - 1) * $perPage;
    $order = dms_db_column_exists($def['table'], 'archive_no')
        ? '(`archive_no` IS NULL OR TRIM(`archive_no`) = \'\'), `archive_no` ASC, id ASC'
        : 'id DESC';
    $sql = 'SELECT * FROM `' . $def['table'] . '` WHERE ' . $where . ' ORDER BY ' . $order . ' LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $off;
    $st = db()->prepare($sql);
    $st->execute($params);
    return [
        'rows' => $st->fetchAll() ?: [],
        'total' => $total,
        'pagination' => ['page' => $page, 'pages' => $pages, 'total' => $total, 'per_page' => $perPage],
    ];
}

/** @return array{greg:string,hijri:string} */
function archive_register_date_pair_parts(array $col, array $row): array
{
    $gregKey = (string) ($col['greg_key'] ?? 'APPLICATION_DATE_GREG');
    $hijriKey = (string) ($col['key'] ?? 'APPLICATION_DATE_HIJRI');
    return [
        'greg' => archive_format_compact_date(archive_register_row_val($row, $gregKey)),
        'hijri' => archive_format_compact_date(archive_register_row_val($row, $hijriKey)),
    ];
}

function archive_register_date_pair_html(array $col, array $row): string
{
    $p = archive_register_date_pair_parts($col, $row);
    if ($p['greg'] === '' && $p['hijri'] === '') {
        return '';
    }
    $html = '<span class="archive-date-cell">';
    if ($p['greg'] !== '') {
        $html .= '<span class="archive-date-greg" dir="ltr">' . e($p['greg']) . '</span>';
    }
    if ($p['hijri'] !== '') {
        $html .= '<span class="archive-date-hijri" dir="ltr">' . e($p['hijri']) . '</span>';
    }
    $html .= '</span>';
    return $html;
}

function archive_register_city_label(array $row): string
{
    $id = archive_register_row_val($row, 'CITY_ID');
    if ($id !== '' && $id !== '0') {
        $hit = function_exists('archive_lookup_row') ? archive_lookup_row('city', $id) : null;
        if ($hit) {
            $label = archive_lookup_label('city', $id);
            if ($label !== '' && $label !== $id) {
                return $label;
            }
        }
    }
    $text = archive_register_row_val($row, 'CITY_AR');
    $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    return $text;
}

function archive_register_list_cell_text(array $col, array $row): string
{
    $raw = trim((string) ($row[$col['key']] ?? ''));
    $colType = (string) ($col['type'] ?? '');
    if ($colType === 'city') {
        return archive_register_city_label($row);
    }
    if ($colType === 'ref' || ($col['key'] ?? '') === 'archive_no') {
        return archive_register_display_no($raw);
    }
    if ($colType === 'date_pair') {
        $p = archive_register_date_pair_parts($col, $row);
        return trim($p['greg'] . ($p['greg'] !== '' && $p['hijri'] !== '' ? "\n" : '') . $p['hijri']);
    }
    if ($colType === 'hijri') {
        return archive_format_compact_date($raw);
    }
    if ($colType === 'country' || ($col['lookup'] ?? '') === 'countries') {
        return archive_lookup_label('countries', $raw);
    }
    if ($colType === 'lookup' && !empty($col['lookup'])) {
        return archive_lookup_label((string) $col['lookup'], $raw);
    }
    return $raw;
}

function archive_register_filter_query(array $filters): string
{
    $clean = [];
    foreach ($filters as $k => $v) {
        if (!is_scalar($v) || $v === '' || $v === null) {
            continue;
        }
        $clean[$k] = $v;
    }
    return http_build_query($clean);
}

function archive_register_filter_summary(array $def, array $filters): string
{
    $bits = [];
    if (trim((string) ($filters['from'] ?? '')) !== '' || trim((string) ($filters['to'] ?? '')) !== '') {
        $bits[] = __('common.from') . ' ' . format_date(($filters['from'] ?? '') ?: null)
            . ' — ' . __('common.to') . ' ' . format_date(($filters['to'] ?? '') ?: null);
    }
    foreach ($def['filters'] ?? [] as $flt) {
        $name = (string) ($flt['name'] ?? '');
        $lookup = (string) ($flt['lookup'] ?? '');
        if ($name === '') {
            continue;
        }
        if ((string) ($flt['type'] ?? '') === 'text') {
            $text = trim((string) ($filters[$name] ?? ''));
            if ($text !== '') {
                $bits[] = __($flt['label']) . ': ' . $text;
            }
            continue;
        }
        $id = (int) ($filters[$name] ?? 0);
        if ($lookup === '' || $id < 1) {
            continue;
        }
        $label = archive_lookup_label($lookup, (string) $id);
        if ($label !== '') {
            $bits[] = __($flt['label']) . ': ' . $label;
        }
    }
    $q = trim((string) ($filters['q'] ?? ''));
    if ($q !== '') {
        $bits[] = __('action.search') . ': ' . $q;
    }
    return $bits !== [] ? implode(' · ', $bits) : __('common.all');
}

function archive_register_save(string $kind, int $id, array $post): int
{
    $def = archive_register_def($kind);
    if (!$def) {
        throw new InvalidArgumentException(__('common.not_found'));
    }
    $table = $def['table'];
    $data = [];
    foreach (array_keys($def['columns']) as $col) {
        if ($col === 'archive_no') {
            continue;
        }
        if (!array_key_exists($col, $post)) {
            continue;
        }
        $val = $post[$col];
        if (is_array($val)) {
            continue;
        }
        $val = trim((string) $val);
        $data[$col] = $val === '' ? null : $val;
    }
    if ($kind === 'projects') {
        $cityId = trim((string) ($data['CITY_ID'] ?? ''));
        $cityAr = trim((string) ($data['CITY_AR'] ?? ''));
        if ($cityId !== '' && $cityId !== '0' && $cityAr === '') {
            $cityName = archive_lookup_label('city', $cityId);
            if ($cityName !== '' && $cityName !== $cityId) {
                $data['CITY_AR'] = $cityName;
            }
        }
    }
    if ($data === []) {
        throw new InvalidArgumentException(__('common.name_required'));
    }
    if ($id > 0) {
        $existing = archive_register_get($kind, $id);
        if ($existing && trim((string) ($existing['archive_no'] ?? '')) === '') {
            $data['archive_no'] = next_archive_register_no($kind);
        }
        $set = [];
        $params = [];
        foreach ($data as $col => $val) {
            $set[] = '`' . $col . '` = ?';
            $params[] = $val;
        }
        $params[] = $id;
        db()->prepare('UPDATE `' . $table . '` SET ' . implode(',', $set) . ' WHERE id = ?')->execute($params);
        return $id;
    }
    $data['archive_no'] = next_archive_register_no($kind);
    $cols = array_keys($data);
    $ph = implode(',', array_fill(0, count($cols), '?'));
    $quoted = implode(',', array_map(static fn(string $c): string => '`' . $c . '`', $cols));
    db()->prepare('INSERT INTO `' . $table . '` (' . $quoted . ') VALUES (' . $ph . ')')->execute(array_values($data));
    return (int) db()->lastInsertId();
}

function archive_register_delete(string $kind, int $id): void
{
    $def = archive_register_def($kind);
    if (!$def || $id < 1) {
        return;
    }
    db()->prepare('DELETE FROM `' . $def['table'] . '` WHERE id = ?')->execute([$id]);
}

function archive_hub_kinds(): array
{
    $out = ['all' => 'common.all'];
    foreach (archive_register_defs() as $kind => $def) {
        if (function_exists('user_can_archive_section') && !user_can_archive_section($kind, 'view')) {
            continue;
        }
        $out[$kind] = (string) ($def['label'] ?? $kind);
    }
    if (!function_exists('user_can_archive_section') || user_can_archive_section('general', 'view')) {
        $out['general'] = 'archive.general';
    }
    return $out;
}

function archive_hub_register_rows(string $kind, string $q, int $limit): array
{
    $def = archive_register_def($kind);
    if (!$def) {
        return [];
    }
    $limit = max(1, min(40, $limit));
    [$where, $params] = archive_register_where($def, ['q' => $q]);
    try {
        $st = db()->prepare(
            'SELECT * FROM `' . $def['table'] . '` WHERE ' . $where . ' ORDER BY id DESC LIMIT ' . $limit
        );
        $st->execute($params);
        return $st->fetchAll() ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function archive_hub_row_from_register(string $kind, array $row): array
{
    $def = archive_register_def($kind) ?? [];
    $gregKey = 'APPLICATION_DATE_GREG';
    $hijriKey = 'APPLICATION_DATE_HIJRI';
    if ($kind === 'projects') {
        $gregKey = 'DATE_GREG';
        $hijriKey = 'DATE_HIJRI';
    } elseif ($kind === 'finance') {
        $gregKey = 'YEAR';
        $hijriKey = 'HIJRIDATE';
    }
    $info = archive_register_row_date_info($kind, $row);
    $party = trim((string) ($row['STUDENT_NAME_AR'] ?? $row['OPERATORS_PROJECT_AR'] ?? $row['COMPANYNAME'] ?? ''));
    return [
        'kind' => $kind,
        'kind_label' => __((string) ($def['label'] ?? '')),
        'icon' => (string) ($def['icon'] ?? 'bi-archive'),
        'id' => (int) ($row['id'] ?? 0),
        'archive_no' => archive_register_display_no((string) ($row['archive_no'] ?? '')),
        'title' => archive_register_title($def, $row),
        'party' => $party,
        'date_html' => archive_register_date_pair_html(['greg_key' => $gregKey, 'key' => $hijriKey], $row),
        'view_url' => archive_register_url($kind, 'view', ['id' => (int) ($row['id'] ?? 0)]),
        'sort' => (string) ($info['sort'] ?? ''),
    ];
}

function archive_hub_row_from_general(array $row): array
{
    $greg = trim((string) ($row['doc_date'] ?? ''));
    $dateHtml = '';
    if ($greg !== '') {
        $dateHtml = '<span class="archive-date-cell"><span class="archive-date-greg" dir="ltr">'
            . e(function_exists('format_date') ? format_date($greg) : $greg)
            . '</span></span>';
    }
    $sortDate = preg_match('/^\d{4}-\d{2}-\d{2}/', $greg) ? str_replace('-', '', substr($greg, 0, 10)) : '00000000';
    return [
        'kind' => 'general',
        'kind_label' => __('archive.general'),
        'icon' => 'bi-archive',
        'id' => (int) ($row['id'] ?? 0),
        'archive_no' => trim((string) ($row['archive_no'] ?? '')),
        'title' => function_exists('archive_title') ? archive_title($row) : trim((string) ($row['title'] ?? '')),
        'party' => trim((string) ($row['party_name'] ?? '')),
        'date_html' => $dateHtml,
        'view_url' => function_exists('archive_general_url')
            ? archive_general_url('view', ['id' => (int) ($row['id'] ?? 0)])
            : base_url('archive/general/view.php?id=' . (int) ($row['id'] ?? 0)),
        'sort' => $sortDate . '-00000000-' . str_pad((string) (int) ($row['id'] ?? 0), 10, '0', STR_PAD_LEFT),
    ];
}

/**
 * @return list<array<string,mixed>>
 */
function archive_hub_feed(?array $user, string $q, string $kindFilter, int $limit = 12): array
{
    $q = trim($q);
    $limit = max(4, min(40, $limit));
    $allKinds = array_keys(archive_register_defs());
    $allKinds[] = 'general';
    $kinds = $allKinds;
    if ($kindFilter !== '' && $kindFilter !== 'all' && in_array($kindFilter, $allKinds, true)) {
        $kinds = [$kindFilter];
    }
    $per = $q === '' ? max(3, (int) ceil($limit / max(1, count($kinds)))) : min(20, $limit);
    $buckets = [];
    foreach ($kinds as $kind) {
        if (function_exists('user_can_archive_section') && !user_can_archive_section($kind, 'view', $user)) {
            continue;
        }
        $bucket = [];
        if ($kind === 'general') {
            try {
                $list = function_exists('archive_list') ? archive_list(['q' => $q], $user, $per, 0) : [];
            } catch (Throwable $e) {
                $list = [];
            }
            foreach ($list as $row) {
                $bucket[] = archive_hub_row_from_general($row);
            }
        } else {
            foreach (archive_hub_register_rows($kind, $q, $per) as $row) {
                $bucket[] = archive_hub_row_from_register($kind, $row);
            }
        }
        $buckets[] = $bucket;
    }
    $out = [];
    if ($q === '' && count($buckets) > 1) {
        $added = true;
        while ($added && count($out) < $limit) {
            $added = false;
            foreach ($buckets as $i => $bucket) {
                if ($bucket === []) {
                    continue;
                }
                $out[] = array_shift($buckets[$i]);
                $added = true;
                if (count($out) >= $limit) {
                    break;
                }
            }
        }
        return $out;
    }
    foreach ($buckets as $bucket) {
        foreach ($bucket as $row) {
            $out[] = $row;
        }
    }
    usort($out, static fn(array $a, array $b): int => strcmp((string) ($b['sort'] ?? ''), (string) ($a['sort'] ?? '')));
    return array_slice($out, 0, $limit);
}

function archive_register_search(string $q, int $limit = 20): array
{
    $q = trim($q);
    if ($q === '' || mb_strlen($q) < 2) {
        return [];
    }
    $out = [];
    foreach (archive_register_defs() as $kind => $def) {
        if (function_exists('user_can_archive_section') && !user_can_archive_section($kind, 'view')) {
            continue;
        }
        $list = archive_register_list($kind, $q, 1, min(10, $limit));
        foreach ($list['rows'] as $row) {
            $out[] = [
                'kind' => $kind,
                'id' => (int) $row['id'],
                'archive_no' => (string) ($row['archive_no'] ?? $row['ARCHIVE_NUM'] ?? $row['ARCHIVE_NUMBER'] ?? $row['KAIDNUMBER'] ?? ''),
                'title' => archive_register_title($def, $row),
                'party_name' => (string) ($row['STUDENT_NAME_AR'] ?? $row['COMPANYNAME'] ?? $row['OPERATORS_PROJECT_AR'] ?? ''),
                'view_url' => archive_register_url($kind, 'view', ['id' => (int) $row['id']]),
            ];
            if (count($out) >= $limit) {
                return $out;
            }
        }
    }
    return $out;
}

function archive_legacy_register_pdo(): ?PDO
{
    try {
        return new PDO(
            'mysql:host=' . DB_HOST . ';dbname=kff_ea_legacy;charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    } catch (Throwable $e) {
        return null;
    }
}

function archive_import_legacy_registers(bool $force = false): array
{
    archive_ensure_register_schema();
    $stats = [];
    $legacy = archive_legacy_register_pdo();
    if (!$legacy) {
        return ['error' => 'no_legacy'];
    }
    foreach (archive_register_defs() as $kind => $def) {
        $table = $def['table'];
        try {
            $n = (int) db()->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
        } catch (Throwable $e) {
            $n = 0;
        }
        if ($n > 0 && !$force) {
            $stats[$kind] = $n;
            continue;
        }
        $src = $def['legacy'];
        $rows = $legacy->query('SELECT * FROM `' . $src . '`')->fetchAll();
        $colNames = array_keys($def['columns']);
        $insCols = array_values(array_filter(
            $colNames,
            static fn(string $c): bool => !in_array($c, ['file_folder', 'original_file_names', 'original_folder_name'], true)
        ));
        $quoted = implode(',', array_map(static fn(string $c): string => '`' . $c . '`', $insCols));
        $quoted .= ',`file_folder`';
        $ph = implode(',', array_fill(0, count($insCols) + 1, '?'));
        $ins = db()->prepare('INSERT INTO `' . $table . '` (' . $quoted . ') VALUES (' . $ph . ')');
        $ok = 0;
        foreach ($rows as $row) {
            $params = [];
            foreach ($insCols as $c) {
                $params[] = array_key_exists($c, $row) ? $row[$c] : null;
            }
            $folder = archive_register_guess_folder($def, $row + ['file_folder' => '']);
            $params[] = $folder !== '' ? $folder : null;
            $ins->execute($params);
            $ok++;
        }
        $stats[$kind] = $ok;
    }
    set_setting('archive_registers_imported', '1', 'استيراد سجلات الأرشيف القديم');
    try {
        $ids = db()->prepare('SELECT id FROM `tblarchive_items` WHERE keywords = ?');
        $ids->execute(['legacy-ea-kff']);
        $delIds = $ids->fetchAll(PDO::FETCH_COLUMN);
        if ($delIds) {
            $in = implode(',', array_map('intval', $delIds));
            db()->exec('DELETE FROM `tblarchive_files` WHERE archive_id IN (' . $in . ')');
            db()->exec('DELETE FROM `tblarchive_items` WHERE id IN (' . $in . ')');
        }
    } catch (Throwable $e) {
        // عيّنة العرض قد تكون غير موجودة
    }
    return $stats;
}
