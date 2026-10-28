<?php
/**
 * حقول أرشيف إضافية وقوائم مرجعية مستقلة بالبادئة tblarchive_.
 */

function archive_ensure_extra_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        if (function_exists('dms_db_column_exists') && !dms_db_column_exists('tblarchive_items', 'extra_json')) {
            db()->exec("ALTER TABLE `tblarchive_items` ADD COLUMN `extra_json` TEXT NULL AFTER `notes`");
        }
        archive_catalog_rename_short_tables();
        foreach (archive_catalog_defs() as $def) {
            $table = $def['table'];
            if (!preg_match('/^tblarchive_[a-z0-9_]+$/', $table)) {
                continue;
            }
            db()->exec(
                "CREATE TABLE IF NOT EXISTS `{$table}` (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    legacy_id INT DEFAULT NULL,
                    name_ar VARCHAR(255) NOT NULL,
                    name_en VARCHAR(255) DEFAULT NULL,
                    sort_order INT NOT NULL DEFAULT 0,
                    is_active TINYINT(1) NOT NULL DEFAULT 1,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_active (is_active, sort_order),
                    INDEX idx_legacy (legacy_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }
        archive_seed_catalog_tables();
        archive_sync_countries_from_scholarship();
        archive_drop_unified_lookups_table();
        archive_backfill_extra_from_notes();
    } catch (Throwable $e) {
        $done = false;
    }
}

function archive_catalog_rename_short_tables(): void
{
    $map = [
        'tblcountry' => 'tblarchive_country',
        'tblarchive_country' => 'tblarchive_countries',
        'tblcity' => 'tblarchive_city',
        'tbluniversity' => 'tblarchive_university',
        'tbldegree' => 'tblarchive_degree',
        'tblscholarship_types' => 'tblarchive_scholarship_types',
        'tblproject_types' => 'tblarchive_project_types',
        'tblproject_status' => 'tblarchive_project_status',
        'tblcurrency' => 'tblarchive_currency',
        'tblspecialities' => 'tblarchive_specialities',
        'tblgender' => 'tblarchive_gender',
        'tblregion' => 'tblarchive_region',
        'tblcontinent' => 'tblarchive_continent',
        'tblprovince' => 'tblarchive_province',
        'tblclassifications' => 'tblarchive_classifications',
        'tblrejection_reasons' => 'tblarchive_rejection_reasons',
        'tblfiscal_years' => 'tblarchive_fiscal_years',
        'tblgrade_level' => 'tblarchive_grade_level',
    ];
    foreach ($map as $old => $new) {
        $oldOk = function_exists('dms_db_table_exists') && dms_db_table_exists($old);
        $newOk = function_exists('dms_db_table_exists') && dms_db_table_exists($new);
        if ($oldOk && !$newOk) {
            try {
                db()->exec('RENAME TABLE `' . $old . '` TO `' . $new . '`');
                if (function_exists('dms_db_tables_map')) {
                    dms_db_tables_map(true);
                }
            } catch (Throwable $e) {
                // التسمية الجديدة تُنشأ لاحقاً
            }
        }
    }
}

/**
 * جداول القوائم من النظام القديم — الاسم = tblarchive_ + اسم الجدول القديم.
 *
 * @return array<string,array{table:string,legacy:string,sql:string,label:string,icon:string,admin:bool,aliases?:list<string>}>
 */
function archive_catalog_defs(): array
{
    return [
        'countries' => [
            'table' => 'tblarchive_countries',
            'legacy' => 'tblcountries',
            'sql' => 'SELECT COUNTRYID_PK lid, COUNTRY_NAME_AR ar, COUNTRY_NAME_EN en FROM country ORDER BY COUNTRY_NAME_AR',
            'label' => 'archive.cat.countries',
            'icon' => 'bi-globe',
            'admin' => true,
            'aliases' => ['country'],
        ],
        'city' => [
            'table' => 'tblarchive_city',
            'legacy' => 'city',
            'sql' => 'SELECT DKEY lid, TITLE_AR ar, TITLE_EN en FROM city ORDER BY TITLE_AR',
            'label' => 'archive.cat.city',
            'icon' => 'bi-geo-alt',
            'admin' => true,
        ],
        'university' => [
            'table' => 'tblarchive_university',
            'legacy' => 'university',
            'sql' => 'SELECT DKEY lid, NAME_AR ar, NAME_EN en FROM university ORDER BY NAME_AR',
            'label' => 'archive.cat.university',
            'icon' => 'bi-mortarboard',
            'admin' => true,
        ],
        'degree' => [
            'table' => 'tblarchive_degree',
            'legacy' => 'degree',
            'sql' => 'SELECT DKEY lid, TITLE_AR ar, TITLE_EN en FROM degree ORDER BY DKEY',
            'label' => 'archive.cat.degree',
            'icon' => 'bi-award',
            'admin' => true,
        ],
        'scholarship_types' => [
            'table' => 'tblarchive_scholarship_types',
            'legacy' => 'scholarship_types',
            'sql' => 'SELECT DKEY lid, TITLE_AR ar, TITLE_EN en FROM scholarship_types ORDER BY DKEY',
            'label' => 'archive.cat.scholarship_types',
            'icon' => 'bi-journal-bookmark',
            'admin' => true,
            'aliases' => ['scholarship_type'],
        ],
        'project_types' => [
            'table' => 'tblarchive_project_types',
            'legacy' => 'project_types',
            'sql' => 'SELECT DKEY lid, TYPE_AR ar, TYPE_EN en FROM project_types ORDER BY DKEY',
            'label' => 'archive.cat.project_types',
            'icon' => 'bi-building',
            'admin' => true,
            'aliases' => ['project_type'],
        ],
        'project_status' => [
            'table' => 'tblarchive_project_status',
            'legacy' => 'project_status',
            'sql' => 'SELECT DKEY lid, STATUS_AR ar, STATUS_EN en FROM project_status ORDER BY DKEY',
            'label' => 'archive.cat.project_status',
            'icon' => 'bi-clipboard-check',
            'admin' => true,
        ],
        'currency' => [
            'table' => 'tblarchive_currency',
            'legacy' => 'currency',
            'sql' => 'SELECT DKEY lid, CURREnCY_NAME_AR ar, CURRENCY_NAME_EN en FROM currency ORDER BY DKEY',
            'label' => 'archive.cat.currency',
            'icon' => 'bi-cash-coin',
            'admin' => true,
        ],
        'specialities' => [
            'table' => 'tblarchive_specialities',
            'legacy' => 'specialities',
            'sql' => 'SELECT DKEY lid, TITLE_AR ar, TITLE_EN en FROM specialities ORDER BY TITLE_AR',
            'label' => 'archive.cat.specialities',
            'icon' => 'bi-book',
            'admin' => true,
            'aliases' => ['specialization'],
        ],
        'gender' => [
            'table' => 'tblarchive_gender',
            'legacy' => 'gender',
            'sql' => 'SELECT DKEY lid, NAME_AR ar, NAME_EN en FROM gender ORDER BY DKEY',
            'label' => 'archive.cat.gender',
            'icon' => 'bi-person',
            'admin' => true,
        ],
        'region' => [
            'table' => 'tblarchive_region',
            'legacy' => 'region',
            'sql' => 'SELECT DKEY lid, NAME_AR ar, NAME_EN en FROM region ORDER BY NAME_AR',
            'label' => 'archive.cat.region',
            'icon' => 'bi-map',
            'admin' => true,
        ],
        'continent' => [
            'table' => 'tblarchive_continent',
            'legacy' => 'continent',
            'sql' => 'SELECT CONTINENTID_PK lid, CONTINENT_NAME_AR ar, CONTINENT_NAME_EN en FROM continent ORDER BY CONTINENT_NAME_AR',
            'label' => 'archive.cat.continent',
            'icon' => 'bi-globe2',
            'admin' => true,
        ],
        'province' => [
            'table' => 'tblarchive_province',
            'legacy' => 'province',
            'sql' => 'SELECT PROVINCEID_PK lid, PROVINCE_AR ar, PROVINCE_EN en FROM province ORDER BY PROVINCE_AR',
            'label' => 'archive.cat.province',
            'icon' => 'bi-pin-map',
            'admin' => true,
        ],
        'classifications' => [
            'table' => 'tblarchive_classifications',
            'legacy' => 'classifications',
            'sql' => 'SELECT DKEY lid, TITLE_AR ar, TITLE_EN en FROM classifications ORDER BY TITLE_AR',
            'label' => 'archive.cat.classifications',
            'icon' => 'bi-collection',
            'admin' => true,
        ],
        'rejection_reasons' => [
            'table' => 'tblarchive_rejection_reasons',
            'legacy' => 'rejection_reasons',
            'sql' => 'SELECT DKEY lid, REJECTION_TYPE_AR ar, REJECTION_TYPE_EN en FROM rejection_reasons ORDER BY DKEY',
            'label' => 'archive.cat.rejection_reasons',
            'icon' => 'bi-x-octagon',
            'admin' => true,
        ],
        'fiscal_years' => [
            'table' => 'tblarchive_fiscal_years',
            'legacy' => 'fiscal_years',
            'sql' => 'SELECT DKEY lid, FISCAL_YEAR_HIJRI ar, FISCAL_YEAR_GREG en FROM fiscal_years ORDER BY DKEY',
            'label' => 'archive.cat.fiscal_years',
            'icon' => 'bi-calendar3',
            'admin' => true,
        ],
        'grade_level' => [
            'table' => 'tblarchive_grade_level',
            'legacy' => 'grade_level',
            'sql' => 'SELECT DKEY lid, LEVEL_AR ar, LEVEL_EN en FROM grade_level ORDER BY DKEY',
            'label' => 'archive.cat.grade_level',
            'icon' => 'bi-bar-chart',
            'admin' => true,
        ],
        'educational_destination' => [
            'table' => 'tblarchive_educational_destination',
            'legacy' => 'educational_destination',
            'sql' => 'SELECT DKEY lid, DESTINATION_AR ar, DESTINATION_EN en FROM educational_destination ORDER BY DKEY',
            'label' => 'archive.cat.educational_destination',
            'icon' => 'bi-geo',
            'admin' => true,
        ],
        'branch' => [
            'table' => 'tblarchive_branch',
            'legacy' => 'branch',
            'sql' => 'SELECT BRANCHID_PK lid, BRANCH_AR ar, BRANCH_EN en FROM branch ORDER BY `ORDER`',
            'label' => 'archive.cat.branch',
            'icon' => 'bi-diagram-2',
            'admin' => true,
        ],
        'center' => [
            'table' => 'tblarchive_center',
            'legacy' => 'center',
            'sql' => 'SELECT CENTERID_PK lid, CENTER_AR ar, CENTER_EN en FROM center ORDER BY `ORDER`',
            'label' => 'archive.cat.center',
            'icon' => 'bi-building-gear',
            'admin' => true,
        ],
        'section' => [
            'table' => 'tblarchive_section',
            'legacy' => 'section',
            'sql' => 'SELECT SECTIONID_PK lid, SECTION_AR ar, SECTION_EN en FROM section ORDER BY `ORDER`',
            'label' => 'archive.cat.section',
            'icon' => 'bi-layout-three-columns',
            'admin' => true,
        ],
        'document_type' => [
            'table' => 'tblarchive_document_type',
            'legacy' => 'document_type',
            'sql' => 'SELECT ID lid, NAME_AR ar, NAME_EN en FROM document_type ORDER BY ID',
            'label' => 'archive.cat.document_type',
            'icon' => 'bi-file-earmark-text',
            'admin' => true,
        ],
        'document_category' => [
            'table' => 'tblarchive_document_category',
            'legacy' => 'document_category',
            'sql' => 'SELECT DKEY lid, NAME_AR ar, NAME_EN en FROM document_category ORDER BY DKEY',
            'label' => 'archive.cat.document_category',
            'icon' => 'bi-folder2',
            'admin' => true,
        ],
        'document_form' => [
            'table' => 'tblarchive_document_form',
            'legacy' => 'document_form',
            'sql' => 'SELECT DKEY lid, NAME_AR ar, NAME_EN en FROM document_form ORDER BY DKEY',
            'label' => 'archive.cat.document_form',
            'icon' => 'bi-ui-radios',
            'admin' => true,
        ],
        'receipt_warehouse_type' => [
            'table' => 'tblarchive_receipt_warehouse_type',
            'legacy' => 'receipt_warhouse_type',
            'sql' => 'SELECT DKEY lid, TYPE ar, TYPE en FROM receipt_warhouse_type ORDER BY DKEY',
            'label' => 'archive.cat.receipt_warehouse_type',
            'icon' => 'bi-box-seam',
            'admin' => true,
        ],
        'transfer_custody_category' => [
            'table' => 'tblarchive_transfer_custody_category',
            'legacy' => 'transfer_custody_category',
            'sql' => 'SELECT KID lid, REQUESTCATEGORY ar, REQUESTCATEGORY en FROM transfer_custody_category ORDER BY KID',
            'label' => 'archive.cat.transfer_custody_category',
            'icon' => 'bi-arrow-left-right',
            'admin' => true,
        ],
        'soft_files_status' => [
            'table' => 'tblarchive_soft_files_status',
            'legacy' => 'soft_files_status',
            'sql' => 'SELECT DKEY lid, NAME_AR ar, NAME_EN en FROM soft_files_status ORDER BY DKEY',
            'label' => 'archive.cat.soft_files_status',
            'icon' => 'bi-hourglass-split',
            'admin' => true,
        ],
        'chevening_universities' => [
            'table' => 'tblarchive_chevening_universities',
            'legacy' => 'chevening_universities',
            'sql' => 'SELECT DKEY lid, NAME_AR ar, NAME_EN en FROM chevening_universities ORDER BY NAME_AR',
            'label' => 'archive.cat.chevening_universities',
            'icon' => 'bi-mortarboard-fill',
            'admin' => true,
        ],
        'file_category_types' => [
            'table' => 'tblarchive_file_category_types',
            'legacy' => 'file_category_types',
            'sql' => 'SELECT DKEY lid, FILECATEGORY ar, FILECATEGORY en FROM file_category_types ORDER BY DKEY',
            'label' => 'archive.cat.file_category_types',
            'icon' => 'bi-files',
            'admin' => true,
        ],
        'budget_category_type' => [
            'table' => 'tblarchive_budget_category_type',
            'legacy' => 'budget_category_type',
            'sql' => 'SELECT DKEY lid, BUDGETCATEGORY ar, BUDGETCATEGORY en FROM budget_category_type ORDER BY DKEY',
            'label' => 'archive.cat.budget_category_type',
            'icon' => 'bi-wallet2',
            'admin' => true,
        ],
        'hr_category_types' => [
            'table' => 'tblarchive_hr_category_types',
            'legacy' => 'hr_category_types',
            'sql' => 'SELECT DKEY lid, CATEGROYTYPE ar, CATEGROYTYPE en FROM hr_category_types ORDER BY DKEY',
            'label' => 'archive.cat.hr_category_types',
            'icon' => 'bi-people',
            'admin' => true,
        ],
        'files_dep_type' => [
            'table' => 'tblarchive_files_dep_type',
            'legacy' => 'files_dep_type',
            'sql' => 'SELECT DKEY lid, TYPE ar, TYPE en FROM files_dep_type ORDER BY DKEY',
            'label' => 'archive.cat.files_dep_type',
            'icon' => 'bi-folder',
            'admin' => true,
        ],
        'category_type' => [
            'table' => 'tblarchive_category_type',
            'legacy' => 'category_type',
            'sql' => 'SELECT ID lid, CATEGORY_NAME_AR ar, CATEGORY_NAME_EN en FROM category_type ORDER BY ID',
            'label' => 'archive.cat.category_type',
            'icon' => 'bi-tag',
            'admin' => true,
        ],
        'document_category_types' => [
            'table' => 'tblarchive_document_category_types',
            'legacy' => 'documet_category_types',
            'sql' => 'SELECT DKEY lid, DOCUMENTCATEGORY ar, DOCUMENTCATEGORY en FROM documet_category_types ORDER BY DKEY',
            'label' => 'archive.cat.document_category_types',
            'icon' => 'bi-tags',
            'admin' => true,
        ],
        'administrative_preacher_type' => [
            'table' => 'tblarchive_administrative_preacher_type',
            'legacy' => 'administrative_preacher_type',
            'sql' => 'SELECT DKEY lid, TYPE ar, TYPE en FROM administrative_preacher_type ORDER BY DKEY',
            'label' => 'archive.cat.administrative_preacher_type',
            'icon' => 'bi-person-badge',
            'admin' => true,
        ],
        'preacher_files_type' => [
            'table' => 'tblarchive_preacher_files_type',
            'legacy' => 'preacher_files_type',
            'sql' => 'SELECT DKEY lid, TYPE ar, TYPE en FROM preacher_files_type ORDER BY DKEY',
            'label' => 'archive.cat.preacher_files_type',
            'icon' => 'bi-file-person',
            'admin' => true,
        ],
        'competition_type' => [
            'table' => 'tblarchive_competition_type',
            'legacy' => 'competitiontype',
            'sql' => 'SELECT DKEY lid, TYPE ar, TYPE en FROM competitiontype ORDER BY DKEY',
            'label' => 'archive.cat.competition_type',
            'icon' => 'bi-trophy',
            'admin' => true,
        ],
        'intervention_type' => [
            'table' => 'tblarchive_intervention_type',
            'legacy' => 'interventiontype',
            'sql' => 'SELECT INTERVENTIONTYPEID lid, INTERVENTIONTYPE ar, INTERVENTIONTYPE en FROM interventiontype ORDER BY INTERVENTIONTYPEID',
            'label' => 'archive.cat.intervention_type',
            'icon' => 'bi-heart',
            'admin' => true,
        ],
    ];
}

function archive_catalog_def(string $kind): ?array
{
    $kind = trim($kind);
    if (str_starts_with($kind, 'catalog_')) {
        $kind = substr($kind, 8);
    }
    $defs = archive_catalog_defs();
    if (isset($defs[$kind])) {
        return $defs[$kind] + ['key' => $kind];
    }
    foreach ($defs as $key => $def) {
        if (in_array($kind, $def['aliases'] ?? [], true) || $kind === ($def['table'] ?? '')) {
            return $def + ['key' => $key];
        }
    }
    return null;
}

function archive_catalog_table(string $kind): ?string
{
    $def = archive_catalog_def($kind);
    $table = (string) ($def['table'] ?? '');
    return preg_match('/^tblarchive_[a-z0-9_]+$/', $table) ? $table : null;
}

/**
 * @return list<array{key:string,label:string,type:string,types:list<string>,col:string,lookup?:string,rows?:int}>
 */
function archive_extra_field_defs(): array
{
    return [
        ['key' => 'student_name', 'label' => 'archive.xf.student_name', 'type' => 'text', 'types' => ['scholarship'], 'col' => 'col-md-6'],
        ['key' => 'student_name_en', 'label' => 'archive.xf.student_name_en', 'type' => 'text', 'types' => ['scholarship'], 'col' => 'col-md-6'],
        ['key' => 'country', 'label' => 'archive.xf.country', 'type' => 'lookup', 'lookup' => 'country', 'types' => ['scholarship', 'project'], 'col' => 'col-md-4'],
        ['key' => 'university', 'label' => 'archive.xf.university', 'type' => 'lookup', 'lookup' => 'university', 'types' => ['scholarship'], 'col' => 'col-md-4'],
        ['key' => 'degree', 'label' => 'archive.xf.degree', 'type' => 'lookup', 'lookup' => 'degree', 'types' => ['scholarship'], 'col' => 'col-md-4'],
        ['key' => 'specialization', 'label' => 'archive.xf.specialization', 'type' => 'lookup', 'lookup' => 'specialities', 'types' => ['scholarship'], 'col' => 'col-md-6'],
        ['key' => 'scholarship_type', 'label' => 'archive.xf.scholarship_type', 'type' => 'lookup', 'lookup' => 'scholarship_types', 'types' => ['scholarship'], 'col' => 'col-md-6'],
        ['key' => 'gender', 'label' => 'archive.xf.gender', 'type' => 'lookup', 'lookup' => 'gender', 'types' => ['scholarship'], 'col' => 'col-md-4'],
        ['key' => 'educational_destination', 'label' => 'archive.xf.educational_destination', 'type' => 'lookup', 'lookup' => 'educational_destination', 'types' => ['scholarship'], 'col' => 'col-md-4'],
        ['key' => 'duration_from', 'label' => 'archive.xf.duration_from', 'type' => 'text', 'types' => ['scholarship'], 'col' => 'col-md-4'],
        ['key' => 'duration_to', 'label' => 'archive.xf.duration_to', 'type' => 'text', 'types' => ['scholarship'], 'col' => 'col-md-4'],
        ['key' => 'project_type', 'label' => 'archive.xf.project_type', 'type' => 'lookup', 'lookup' => 'project_types', 'types' => ['project'], 'col' => 'col-md-4'],
        ['key' => 'city', 'label' => 'archive.xf.city', 'type' => 'lookup', 'lookup' => 'city', 'types' => ['project'], 'col' => 'col-md-4'],
        ['key' => 'project_status', 'label' => 'archive.xf.project_status', 'type' => 'lookup', 'lookup' => 'project_status', 'types' => ['project'], 'col' => 'col-md-4'],
        ['key' => 'beneficiaries', 'label' => 'archive.xf.beneficiaries', 'type' => 'text', 'types' => ['project'], 'col' => 'col-md-4'],
        ['key' => 'project_cost', 'label' => 'archive.xf.project_cost', 'type' => 'text', 'types' => ['project'], 'col' => 'col-md-4'],
        ['key' => 'description', 'label' => 'archive.xf.description', 'type' => 'textarea', 'types' => ['project'], 'col' => 'col-12', 'rows' => 3],
        ['key' => 'hijri_date', 'label' => 'archive.xf.hijri_date', 'type' => 'text', 'types' => ['scholarship', 'project', 'journal_entry', 'receipt'], 'col' => 'col-md-4'],
        ['key' => 'amount', 'label' => 'archive.xf.amount', 'type' => 'text', 'types' => ['scholarship', 'project', 'journal_entry', 'receipt', 'check'], 'col' => 'col-md-4'],
        ['key' => 'currency', 'label' => 'archive.xf.currency', 'type' => 'lookup', 'lookup' => 'currency', 'types' => ['scholarship', 'project', 'journal_entry', 'receipt', 'check'], 'col' => 'col-md-4'],
    ];
}

/**
 * @return list<string>
 */
function archive_extra_keys(): array
{
    return array_column(archive_extra_field_defs(), 'key');
}

/**
 * @return array<string,string>
 */
function archive_decode_extra(mixed $json): array
{
    if (is_array($json)) {
        $arr = $json;
    } else {
        $raw = trim((string) $json);
        if ($raw === '') {
            return [];
        }
        $arr = json_decode($raw, true);
        if (!is_array($arr)) {
            return [];
        }
    }
    $out = [];
    $allowed = array_fill_keys(archive_extra_keys(), true);
    foreach ($arr as $k => $v) {
        $k = (string) $k;
        if (!isset($allowed[$k])) {
            continue;
        }
        $val = is_scalar($v) ? trim((string) $v) : '';
        if ($val !== '') {
            $out[$k] = $val;
        }
    }
    return $out;
}

/**
 * @param array<string,mixed> $item
 * @return array<string,string>
 */
function archive_item_extra(array $item): array
{
    if (isset($item['_extra']) && is_array($item['_extra'])) {
        return $item['_extra'];
    }
    return archive_decode_extra($item['extra_json'] ?? '');
}

/**
 * @param array<string,mixed> $post
 * @return array<string,string>
 */
function archive_extra_from_post(array $post): array
{
    $src = $post['extra'] ?? [];
    if (!is_array($src)) {
        $src = [];
    }
    return archive_decode_extra($src);
}

function archive_extra_encode(array $extra): ?string
{
    $extra = archive_decode_extra($extra);
    if ($extra === []) {
        return null;
    }
    return json_encode($extra, JSON_UNESCAPED_UNICODE);
}

/**
 * @return list<array{id:int,name_ar:string,name_en:?string}>
 */
function archive_lookups(string $kind, bool $activeOnly = true): array
{
    archive_ensure_extra_schema();
    $table = archive_catalog_table($kind);
    if ($table === null) {
        return [];
    }
    try {
        $sql = "SELECT * FROM `{$table}`";
        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY sort_order ASC, name_ar ASC';
        return db()->query($sql)->fetchAll() ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function archive_lookup_row(string $kind, string $stored): ?array
{
    $stored = trim($stored);
    if ($stored === '') {
        return null;
    }
    $rows = archive_lookups($kind, false);
    foreach ($rows as $row) {
        if ((string) ($row['id'] ?? '') === $stored) {
            return $row;
        }
    }
    foreach ($rows as $row) {
        $legacy = trim((string) ($row['legacy_id'] ?? ''));
        if ($legacy !== '' && $legacy === $stored) {
            return $row;
        }
    }
    foreach ($rows as $row) {
        $ar = trim((string) ($row['name_ar'] ?? ''));
        $en = trim((string) ($row['name_en'] ?? ''));
        if ($stored === $ar || ($en !== '' && $stored === $en)) {
            return $row;
        }
    }
    return null;
}

function archive_lookup_label(string $kind, string $stored): string
{
    $row = archive_lookup_row($kind, $stored);
    if (!$row) {
        return trim($stored);
    }
    $ar = trim((string) ($row['name_ar'] ?? ''));
    $en = trim((string) ($row['name_en'] ?? ''));
    if (function_exists('bilingual_label')) {
        return bilingual_label(['name_ar' => $ar, 'name_en' => $en, 'name' => $ar]);
    }
    return $ar !== '' ? $ar : $en;
}

/**
 * @return list<array{label:string,value:string}>
 */
function archive_extra_display_rows(array $item): array
{
    $extra = archive_item_extra($item);
    $type = (string) ($item['doc_type'] ?? '');
    $rows = [];
    foreach (archive_extra_field_defs() as $def) {
        if (!in_array($type, $def['types'], true)) {
            continue;
        }
        $val = trim((string) ($extra[$def['key']] ?? ''));
        if ($val === '') {
            continue;
        }
        if (($def['type'] ?? '') === 'lookup' && !empty($def['lookup'])) {
            $val = archive_lookup_label((string) $def['lookup'], $val);
        }
        $rows[] = ['label' => __($def['label']), 'value' => $val];
    }
    return $rows;
}

function archive_clip_lookup(?string $v, int $max = 255): string
{
    $v = trim((string) $v);
    $v = preg_replace('/\s+/u', ' ', $v) ?? $v;
    if ($v === '' || $v === 'NULL') {
        return '';
    }
    if (function_exists('mb_substr')) {
        return mb_substr($v, 0, $max, 'UTF-8');
    }
    return substr($v, 0, $max);
}

function archive_country_norm(string $s): string
{
    $s = archive_clip_lookup($s);
    $s = strtr($s, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ة' => 'ه', 'ى' => 'ي', 'ؤ' => 'و', 'ئ' => 'ي']);
    $s = str_ireplace(['the ', 'kingdom of ', 'state of ', 'republic of '], '', $s);
    $s = str_replace(['المملكة العربية ', 'المملكه العربية ', 'المملكة ', 'دولة ', 'جمهورية '], '', $s);
    $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
    return function_exists('mb_strtolower') ? mb_strtolower(trim($s), 'UTF-8') : strtolower(trim($s));
}

function archive_country_resolve_id(string $stored, array $newByNorm, array $newById, array $oldById): string
{
    $stored = archive_clip_lookup($stored);
    if ($stored === '') {
        return '';
    }
    if (ctype_digit($stored) && isset($oldById[(int) $stored])) {
        $fromOld = archive_clip_lookup($oldById[(int) $stored]['name_ar'] ?: $oldById[(int) $stored]['name_en']);
        if ($fromOld !== '') {
            $stored = $fromOld;
        }
    }
    $norm = archive_country_norm($stored);
    if ($norm !== '' && isset($newByNorm[$norm])) {
        return (string) $newByNorm[$norm];
    }
    foreach ($newByNorm as $n => $id) {
        if ($n === '' || $norm === '') {
            continue;
        }
        if ((function_exists('mb_strlen') ? mb_strlen($n, 'UTF-8') : strlen($n)) < 4) {
            continue;
        }
        if (str_contains($norm, $n) || str_contains($n, $norm)) {
            return (string) $id;
        }
    }
    if (ctype_digit($stored) && isset($newById[(int) $stored])) {
        return $stored;
    }
    return $stored;
}

function archive_sync_countries_from_scholarship(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $sch = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=scholarship;charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        $src = $sch->query(
            'SELECT country_id, country_ar, country_en, iso2, region_id FROM tblcountries ORDER BY country_id'
        )->fetchAll();
    } catch (Throwable $e) {
        return;
    }
    if ($src === []) {
        return;
    }

    if (!function_exists('dms_db_table_exists') || !dms_db_table_exists('tblarchive_countries')) {
        return;
    }
    try {
        if (!dms_db_column_exists('tblarchive_countries', 'iso2')) {
            db()->exec("ALTER TABLE `tblarchive_countries` ADD COLUMN `iso2` VARCHAR(8) NULL AFTER `name_en`");
        }
        if (!dms_db_column_exists('tblarchive_countries', 'region_id')) {
            db()->exec("ALTER TABLE `tblarchive_countries` ADD COLUMN `region_id` VARCHAR(8) NULL AFTER `iso2`");
        }
        if (dms_db_table_exists('tblarchive_city') && !dms_db_column_exists('tblarchive_city', 'country_id')) {
            db()->exec("ALTER TABLE `tblarchive_city` ADD COLUMN `country_id` INT UNSIGNED NULL AFTER `legacy_id`");
            db()->exec("ALTER TABLE `tblarchive_city` ADD INDEX `idx_country` (`country_id`)");
        }
    } catch (Throwable $e) {
        // الأعمدة قد تكون موجودة
    }

    $oldById = [];
    foreach (['tblarchive_country', 'tblarchive_countries'] as $oldTable) {
        if (!dms_db_table_exists($oldTable)) {
            continue;
        }
        try {
            foreach (db()->query("SELECT id, name_ar, name_en FROM `{$oldTable}`")->fetchAll() as $row) {
                $oldById[(int) $row['id']] = [
                    'name_ar' => archive_clip_lookup($row['name_ar'] ?? ''),
                    'name_en' => archive_clip_lookup($row['name_en'] ?? ''),
                ];
            }
        } catch (Throwable $e) {
            // تجاهل
        }
        if ($oldById !== []) {
            break;
        }
    }

    $countryCount = 0;
    try {
        $countryCount = (int) db()->query('SELECT COUNT(*) FROM `tblarchive_countries`')->fetchColumn();
    } catch (Throwable $e) {
        $countryCount = 0;
    }
    $needReplace = $countryCount === 0;
    if ($needReplace) {
        db()->exec('DELETE FROM `tblarchive_countries`');
        $ins = db()->prepare(
            "INSERT INTO `tblarchive_countries` (id, legacy_id, name_ar, name_en, iso2, region_id, sort_order, is_active)
             VALUES (?,?,?,?,?,?,?,1)"
        );
        $i = 0;
        foreach ($src as $row) {
            $id = (int) ($row['country_id'] ?? 0);
            $ar = archive_clip_lookup($row['country_ar'] ?? '');
            if ($id < 1 || $ar === '') {
                continue;
            }
            $i++;
            $ins->execute([
                $id,
                $id,
                $ar,
                archive_clip_lookup($row['country_en'] ?? '') ?: null,
                strtoupper(archive_clip_lookup($row['iso2'] ?? '')) ?: null,
                archive_clip_lookup($row['region_id'] ?? '') ?: null,
                $i * 10,
            ]);
        }
        try {
            db()->exec('ALTER TABLE `tblarchive_countries` AUTO_INCREMENT = 200');
        } catch (Throwable $e) {
            // تجاهل
        }
    }

    $newByNorm = [];
    $newById = [];
    foreach (db()->query('SELECT id, name_ar, name_en, iso2 FROM `tblarchive_countries`')->fetchAll() as $row) {
        $id = (int) $row['id'];
        $newById[$id] = ['iso_ok' => strtoupper((string) ($row['iso2'] ?? '')) === 'SA' || $id > 0];
        foreach ([$row['name_ar'] ?? '', $row['name_en'] ?? ''] as $label) {
            $n = archive_country_norm((string) $label);
            if ($n !== '') {
                $newByNorm[$n] = $id;
            }
        }
    }
    $newByNorm[archive_country_norm('المملكة العربية السعودية')] = 2;
    $newByNorm[archive_country_norm('Saudi Arabia')] = 2;

    try {
        $items = db()->query(
            "SELECT id, extra_json FROM `tblarchive_items`
             WHERE extra_json IS NOT NULL AND extra_json <> '' AND extra_json LIKE '%country%'"
        )->fetchAll();
        $upd = db()->prepare('UPDATE `tblarchive_items` SET extra_json = ? WHERE id = ?');
        foreach ($items as $item) {
            $extra = archive_decode_extra($item['extra_json'] ?? '');
            $prev = (string) ($extra['country'] ?? '');
            if ($prev === '') {
                continue;
            }
            $next = archive_country_resolve_id($prev, $newByNorm, $newById, $oldById);
            if ($next === '' || $next === $prev) {
                continue;
            }
            $extra['country'] = $next;
            $json = archive_extra_encode($extra);
            if ($json) {
                $upd->execute([$json, (int) $item['id']]);
            }
        }
    } catch (Throwable $e) {
        // تجاهل
    }

    if (dms_db_table_exists('tblarchive_city') && dms_db_column_exists('tblarchive_city', 'country_id')) {
        try {
            $legacy = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=kff_ea_legacy;charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
            );
            $cityMap = $legacy->query(
                "SELECT c.TITLE_AR city_ar, co.COUNTRY_NAME_AR country_ar, co.COUNTRY_NAME_EN country_en
                 FROM city c
                 LEFT JOIN country co ON co.COUNTRYID_PK = c.COUNTRY_ID"
            )->fetchAll();
            $findCity = db()->prepare('SELECT id FROM `tblarchive_city` WHERE name_ar = ? LIMIT 1');
            $setCity = db()->prepare('UPDATE `tblarchive_city` SET country_id = ? WHERE id = ? AND (country_id IS NULL OR country_id = 0)');
            foreach ($cityMap as $row) {
                $cityName = archive_clip_lookup($row['city_ar'] ?? '');
                $cid = archive_country_resolve_id(
                    archive_clip_lookup($row['country_ar'] ?? '') ?: archive_clip_lookup($row['country_en'] ?? ''),
                    $newByNorm,
                    $newById,
                    $oldById
                );
                if ($cityName === '' || !ctype_digit($cid)) {
                    continue;
                }
                $findCity->execute([$cityName]);
                $cityId = (int) $findCity->fetchColumn();
                if ($cityId > 0) {
                    $setCity->execute([(int) $cid, $cityId]);
                }
            }
        } catch (Throwable $e) {
            // لا يوجد ربط مدن
        }
    }

    if (dms_db_table_exists('tblarchive_country')) {
        try {
            db()->exec('DROP TABLE `tblarchive_country`');
            if (function_exists('dms_db_tables_map')) {
                dms_db_tables_map(true);
            }
        } catch (Throwable $e) {
            // يبقى الاسم القديم إن تعذر الحذف
        }
    }
}

function archive_catalog_merge_named_rows(string $table, PDO $legacy, string $sql): void
{
    if (!preg_match('/^tblarchive_[a-z0-9_]+$/', $table)) {
        return;
    }
    try {
        $exists = db()->prepare("SELECT id FROM `{$table}` WHERE name_ar = ? LIMIT 1");
        $max = (int) db()->query("SELECT COALESCE(MAX(sort_order), 0) FROM `{$table}`")->fetchColumn();
        $ins = db()->prepare(
            "INSERT INTO `{$table}` (legacy_id, name_ar, name_en, sort_order, is_active) VALUES (?,?,?,?,1)"
        );
        foreach ($legacy->query($sql)->fetchAll() as $row) {
            $ar = archive_clip_lookup($row['ar'] ?? '');
            if ($ar === '') {
                continue;
            }
            $exists->execute([$ar]);
            if ($exists->fetchColumn()) {
                continue;
            }
            $max += 10;
            $lid = isset($row['lid']) && $row['lid'] !== '' ? (int) $row['lid'] : null;
            $ins->execute([$lid, $ar, archive_clip_lookup($row['en'] ?? '') ?: null, $max]);
        }
    } catch (Throwable $e) {
        // تجاهل
    }
}

function archive_seed_catalog_tables(): void
{
    $legacy = null;
    try {
        $legacy = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=kff_ea_legacy;charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    } catch (Throwable $e) {
        $legacy = null;
    }

    foreach (archive_catalog_defs() as $kind => $def) {
        $table = $def['table'];
        try {
            $n = (int) db()->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
        } catch (Throwable $e) {
            continue;
        }
        if ($n > 0) {
            continue;
        }
        if (in_array($kind, ['countries', 'country'], true)) {
            continue;
        }
        $copied = false;
        if ($legacy) {
            try {
                $rows = $legacy->query($def['sql'])->fetchAll();
                $ins = db()->prepare(
                    "INSERT INTO `{$table}` (legacy_id, name_ar, name_en, sort_order, is_active) VALUES (?,?,?,?,1)"
                );
                $i = 0;
                foreach ($rows as $row) {
                    $ar = archive_clip_lookup($row['ar'] ?? '');
                    if ($ar === '') {
                        continue;
                    }
                    $en = archive_clip_lookup($row['en'] ?? '') ?: null;
                    $lid = isset($row['lid']) && $row['lid'] !== '' ? (int) $row['lid'] : null;
                    $i++;
                    $ins->execute([$lid, $ar, $en, $i * 10]);
                    $copied = true;
                }
            } catch (Throwable $e) {
                $copied = false;
            }
        }
        if ($copied) {
            continue;
        }
        if (function_exists('dms_db_table_exists') && dms_db_table_exists('tblarchive_lookups')) {
            try {
                $st = db()->prepare(
                    'SELECT name_ar, name_en, sort_order FROM `tblarchive_lookups` WHERE kind IN (?, ?) ORDER BY sort_order'
                );
                $st->execute([$kind, $def['aliases'][0] ?? $kind]);
                $ins = db()->prepare(
                    "INSERT INTO `{$table}` (name_ar, name_en, sort_order, is_active) VALUES (?,?,?,1)"
                );
                foreach ($st->fetchAll() as $row) {
                    $ar = archive_clip_lookup($row['name_ar'] ?? '');
                    if ($ar === '') {
                        continue;
                    }
                    $ins->execute([
                        $ar,
                        archive_clip_lookup($row['name_en'] ?? '') ?: null,
                        (int) ($row['sort_order'] ?? 0),
                    ]);
                }
            } catch (Throwable $e) {
                // تجاهل
            }
        }
    }
    if ($legacy) {
        archive_catalog_merge_named_rows(
            'tblarchive_university',
            $legacy,
            'SELECT DKEY lid, NAME_AR ar, NAME_EN en FROM universities ORDER BY NAME_AR'
        );
    }
}

function archive_drop_unified_lookups_table(): void
{
    if (!function_exists('dms_db_table_exists') || !dms_db_table_exists('tblarchive_lookups')) {
        return;
    }
    $ready = true;
    foreach (['tblarchive_countries', 'tblarchive_degree', 'tblarchive_university'] as $need) {
        try {
            if ((int) db()->query("SELECT COUNT(*) FROM `{$need}`")->fetchColumn() < 1) {
                $ready = false;
                break;
            }
        } catch (Throwable $e) {
            $ready = false;
            break;
        }
    }
    if (!$ready) {
        return;
    }
    try {
        db()->exec('DROP TABLE `tblarchive_lookups`');
        if (function_exists('dms_db_tables_map')) {
            dms_db_tables_map(true);
        }
    } catch (Throwable $e) {
        // يبقى الجدول القديم إن تعذر الحذف
    }
}

function archive_backfill_extra_from_notes(): void
{
    if (!function_exists('dms_db_column_exists') || !dms_db_column_exists('tblarchive_items', 'extra_json')) {
        return;
    }
    try {
        $rows = db()->query(
            "SELECT id, doc_type, title, party_name, notes FROM `tblarchive_items`
             WHERE (extra_json IS NULL OR extra_json = '' OR extra_json = '{}')
               AND notes IS NOT NULL AND notes <> ''
             LIMIT 500"
        )->fetchAll();
    } catch (Throwable $e) {
        return;
    }
    if (!$rows) {
        return;
    }
    $upd = db()->prepare('UPDATE `tblarchive_items` SET extra_json = ? WHERE id = ?');
    foreach ($rows as $row) {
        $extra = [];
        $notes = (string) ($row['notes'] ?? '');
        $type = (string) ($row['doc_type'] ?? '');
        if (preg_match('/اسم الطالب:\s*(.+)/u', $notes, $m)) {
            $extra['student_name'] = archive_clip_lookup($m[1]);
        } elseif ($type === 'scholarship' && preg_match('/منحة دراسية\s*[—-]\s*(.+)$/u', (string) ($row['title'] ?? ''), $m)) {
            $extra['student_name'] = archive_clip_lookup($m[1]);
        }
        if (preg_match('/الدولة:\s*(.+)/u', $notes, $m)) {
            $extra['country'] = archive_clip_lookup($m[1]);
        }
        if (preg_match('/الجامعة:\s*(.+)/u', $notes, $m)) {
            $extra['university'] = archive_clip_lookup($m[1]);
        }
        if (preg_match('/المدينة:\s*(.+)/u', $notes, $m)) {
            $extra['city'] = archive_clip_lookup($m[1]);
        }
        if (preg_match('/التاريخ الهجري:\s*(.+)/u', $notes, $m)) {
            $extra['hijri_date'] = archive_clip_lookup($m[1]);
        }
        if ($type === 'scholarship' && empty($extra['university'])) {
            $party = archive_clip_lookup($row['party_name'] ?? '');
            if ($party !== '') {
                $extra['university'] = $party;
            }
        }
        $json = archive_extra_encode($extra);
        if ($json) {
            $upd->execute([$json, (int) $row['id']]);
        }
    }
}
