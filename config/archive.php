<?php
/**
 * أرشيف الوثائق المستقل — خطابات قديمة، إيصالات، شيكات، منح… إلخ
 */

require_once __DIR__ . '/archive_extra.php';
require_once __DIR__ . '/archive_registers.php';

function ensure_archive_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        db()->exec(
            "CREATE TABLE IF NOT EXISTS `tblarchive_items` (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                archive_no VARCHAR(40) DEFAULT NULL,
                title VARCHAR(255) NOT NULL,
                title_en VARCHAR(255) DEFAULT NULL,
                doc_type VARCHAR(40) NOT NULL DEFAULT 'other',
                doc_date DATE DEFAULT NULL,
                party_name VARCHAR(255) DEFAULT NULL,
                old_ref VARCHAR(120) DEFAULT NULL,
                loc_room VARCHAR(80) DEFAULT NULL,
                loc_cabinet VARCHAR(80) DEFAULT NULL,
                loc_shelf VARCHAR(80) DEFAULT NULL,
                loc_box VARCHAR(80) DEFAULT NULL,
                location VARCHAR(255) DEFAULT NULL,
                department_id INT UNSIGNED DEFAULT NULL,
                category_id INT UNSIGNED DEFAULT NULL,
                confidentiality ENUM('normal','confidential','secret','top_secret') NOT NULL DEFAULT 'normal',
                keywords VARCHAR(500) DEFAULT NULL,
                notes TEXT DEFAULT NULL,
                correspondence_id INT UNSIGNED DEFAULT NULL,
                created_by INT UNSIGNED DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_arch_no (archive_no),
                INDEX idx_arch_type (doc_type),
                INDEX idx_arch_date (doc_date),
                INDEX idx_arch_dept (department_id),
                INDEX idx_arch_cat (category_id),
                CONSTRAINT fk_arch_dept FOREIGN KEY (department_id) REFERENCES `tbldepartments`(id) ON DELETE SET NULL,
                CONSTRAINT fk_arch_cat FOREIGN KEY (category_id) REFERENCES `tblcategories`(id) ON DELETE SET NULL,
                CONSTRAINT fk_arch_corr FOREIGN KEY (correspondence_id) REFERENCES `tblcorrespondence`(id) ON DELETE SET NULL,
                CONSTRAINT fk_arch_user FOREIGN KEY (created_by) REFERENCES `tblusers`(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        if (function_exists('dms_db_column_exists') && !dms_db_column_exists('tblarchive_items', 'category_id')) {
            db()->exec('ALTER TABLE `tblarchive_items` ADD COLUMN `category_id` INT UNSIGNED DEFAULT NULL AFTER `department_id`');
            try {
                db()->exec('ALTER TABLE `tblarchive_items` ADD INDEX `idx_arch_cat` (`category_id`)');
            } catch (Throwable $e) {
                // الفهرس موجود
            }
            try {
                db()->exec(
                    'ALTER TABLE `tblarchive_items` ADD CONSTRAINT `fk_arch_cat` FOREIGN KEY (`category_id`) REFERENCES `tblcategories`(`id`) ON DELETE SET NULL'
                );
            } catch (Throwable $e) {
                // القيد موجود أو جدول التصنيفات غير جاهز
            }
        }
        db()->exec(
            "CREATE TABLE IF NOT EXISTS `tblarchive_files` (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                archive_id INT UNSIGNED NOT NULL,
                file_name VARCHAR(255) NOT NULL,
                file_path VARCHAR(500) NOT NULL,
                file_type VARCHAR(50) DEFAULT NULL,
                file_size INT UNSIGNED DEFAULT 0,
                uploaded_by INT UNSIGNED DEFAULT NULL,
                uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_archf_item FOREIGN KEY (archive_id) REFERENCES `tblarchive_items`(id) ON DELETE CASCADE,
                CONSTRAINT fk_archf_user FOREIGN KEY (uploaded_by) REFERENCES `tblusers`(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        archive_migrate_record_columns();
        archive_ensure_extra_schema();
        if (function_exists('archive_ensure_register_schema')) {
            archive_ensure_register_schema();
        }
    } catch (Throwable $e) {
        // قاعدة غير جاهزة
    }
}

function archive_migrate_record_columns(): void
{
    $adds = [
        'archive_no' => 'VARCHAR(40) NULL AFTER `id`',
        'loc_room' => 'VARCHAR(80) NULL AFTER `old_ref`',
        'loc_cabinet' => 'VARCHAR(80) NULL AFTER `loc_room`',
        'loc_shelf' => 'VARCHAR(80) NULL AFTER `loc_cabinet`',
        'loc_box' => 'VARCHAR(80) NULL AFTER `loc_shelf`',
        'location' => 'VARCHAR(255) NULL AFTER `loc_box`',
        'doc_year' => 'VARCHAR(10) NULL AFTER `doc_date`',
        'extra_json' => 'TEXT NULL AFTER `notes`',
    ];
    foreach ($adds as $col => $ddl) {
        if (function_exists('dms_db_column_exists') && dms_db_column_exists('tblarchive_items', $col)) {
            continue;
        }
        try {
            db()->exec("ALTER TABLE `tblarchive_items` ADD COLUMN `{$col}` {$ddl}");
        } catch (Throwable $e) {
            // العمود موجود
        }
    }
    db()->exec(
        "CREATE TABLE IF NOT EXISTS `tblarchive_seq` (
            seq_year SMALLINT UNSIGNED NOT NULL PRIMARY KEY,
            last_number INT UNSIGNED NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    try {
        db()->exec("UPDATE `tblarchive_items` SET archive_no = NULL WHERE archive_no = ''");
    } catch (Throwable $e) {
        // العمود غير جاهز
    }
    archive_backfill_numbers();
    archive_backfill_location();
    archive_prefix_empty_default_once();
    archive_migrate_seq_to_sequences();
    try {
        db()->exec('ALTER TABLE `tblarchive_items` ADD UNIQUE KEY `uq_arch_no` (`archive_no`)');
    } catch (Throwable $e) {
        // الفهرس موجود
    }
    try {
        db()->exec('ALTER TABLE `tblarchive_items` ADD INDEX `idx_arch_year` (`doc_year`)');
    } catch (Throwable $e) {
        // الفهرس موجود
    }
}

function archive_normalize_year(string $year): ?string
{
    $year = trim($year);
    if ($year === '') {
        return null;
    }
    if (!preg_match('/^\d{3,4}$/', $year)) {
        return null;
    }
    return $year;
}

/**
 * @return list<string>
 */
function archive_years_list(?array $user = null): array
{
    ensure_archive_schema();
    try {
        $rows = db()->query(
            "SELECT DISTINCT doc_year FROM `tblarchive_items`
             WHERE doc_year IS NOT NULL AND doc_year <> ''
             ORDER BY doc_year DESC"
        )->fetchAll(PDO::FETCH_COLUMN);
        return array_values(array_filter(array_map('strval', $rows)));
    } catch (Throwable $e) {
        return [];
    }
}

function archive_prefix_empty_default_once(): void
{
    if ((string) setting('archive_prefix_empty_default', '') === '1') {
        return;
    }
    if ((string) setting('archive_prefix', '') === 'أر') {
        set_setting('archive_prefix', '');
    }
    set_setting('archive_prefix_empty_default', '1');
}

function archive_seq_scope(string $prefix = ''): string
{
    $prefix = function_exists('corr_sanitize_prefix') ? corr_sanitize_prefix($prefix) : trim($prefix);
    return $prefix !== '' ? mb_substr($prefix, 0, 40) : '';
}

function archive_migrate_seq_to_sequences(): void
{
    if (function_exists('ensure_sequences_schema')) {
        ensure_sequences_schema();
    }
    if (!function_exists('dms_db_table_exists') || !dms_db_table_exists('tblsequences')) {
        return;
    }
    if (!dms_db_table_exists('tblarchive_seq')) {
        return;
    }
    try {
        $rows = db()->query('SELECT seq_year, last_number FROM `tblarchive_seq`')->fetchAll();
        $ins = db()->prepare(
            'INSERT INTO `tblsequences` (seq_type, seq_year, seq_scope, last_number)
             VALUES (\'archive\', ?, \'\', ?)
             ON DUPLICATE KEY UPDATE last_number = GREATEST(last_number, VALUES(last_number))'
        );
        foreach ($rows as $row) {
            $ins->execute([(int) ($row['seq_year'] ?? 0), (int) ($row['last_number'] ?? 0)]);
        }
    } catch (Throwable $e) {
        // الجدول أو عمود النوع غير جاهز
    }
}

function archive_prefix(): string
{
    archive_prefix_empty_default_once();
    return function_exists('corr_sanitize_prefix')
        ? corr_sanitize_prefix((string) setting('archive_prefix', ''))
        : trim((string) setting('archive_prefix', ''));
}

function archive_format_no(string $prefix, int $year, int $seq): string
{
    if (function_exists('corr_build_ref_number')) {
        return corr_build_ref_number($prefix, $year, $seq);
    }
    $yy = sprintf('%02d', abs($year) % 100);
    $pad = sprintf('%04d', $seq);
    $prefix = trim($prefix);
    return $prefix !== '' ? ($prefix . '/' . $yy . '/' . $pad) : ($yy . '/' . $pad);
}

/** أعلى تسلسل مستخدم في الأرشيف فقط — لا يشمل الصادر/الوارد/الداخلي/التعميم */
function archive_max_used_sequence(int $year, string $prefix = ''): int
{
    $max = 0;
    try {
        $rows = db()->query('SELECT archive_no FROM `tblarchive_items` WHERE archive_no IS NOT NULL AND archive_no <> ""')->fetchAll(PDO::FETCH_COLUMN);
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

function next_archive_no(): string
{
    ensure_archive_schema();
    $year = function_exists('corr_numbering_year') ? corr_numbering_year() : (function_exists('corr_numbering_hijri_year') ? corr_numbering_hijri_year() : ((int) date('Y') - 579));
    $prefix = archive_prefix();
    $scope = archive_seq_scope($prefix);
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
            $pdo->prepare(
                "INSERT INTO `tblsequences` (seq_type, seq_year, seq_scope, last_number) VALUES ('archive', ?, ?, 0)"
            )->execute([$year, $scope]);
            $last = 0;
        }
        $usedMax = archive_max_used_sequence($year, $prefix);
        $seq = max((int) $last, $usedMax);
        $no = '';
        $guard = 0;
        do {
            $guard++;
            $seq++;
            $no = archive_format_no($prefix, $year, $seq);
            $chk = $pdo->prepare('SELECT id FROM `tblarchive_items` WHERE archive_no = ? LIMIT 1');
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

function archive_backfill_numbers(): void
{
    if (!function_exists('dms_db_column_exists') || !dms_db_column_exists('tblarchive_items', 'archive_no')) {
        return;
    }
    try {
        $rows = db()->query("SELECT id FROM `tblarchive_items` WHERE archive_no IS NULL OR archive_no = '' ORDER BY id ASC")->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) {
        return;
    }
    foreach ($rows as $id) {
        $id = (int) $id;
        if ($id <= 0) {
            continue;
        }
        try {
            $no = next_archive_no();
            db()->prepare('UPDATE `tblarchive_items` SET archive_no = ? WHERE id = ?')->execute([$no, $id]);
        } catch (Throwable $e) {
            break;
        }
    }
}

function archive_backfill_location(): void
{
    if (!function_exists('dms_db_column_exists') || !dms_db_column_exists('tblarchive_items', 'location')) {
        return;
    }
    try {
        db()->exec(
            "UPDATE `tblarchive_items`
             SET location = NULLIF(TRIM(BOTH ' / ' FROM CONCAT_WS(' / ',
                NULLIF(TRIM(loc_room), ''),
                NULLIF(TRIM(loc_cabinet), ''),
                NULLIF(TRIM(loc_shelf), ''),
                NULLIF(TRIM(loc_box), '')
             )), '')
             WHERE (location IS NULL OR location = '')
               AND (
                    NULLIF(TRIM(IFNULL(loc_room, '')), '') IS NOT NULL
                 OR NULLIF(TRIM(IFNULL(loc_cabinet, '')), '') IS NOT NULL
                 OR NULLIF(TRIM(IFNULL(loc_shelf, '')), '') IS NOT NULL
                 OR NULLIF(TRIM(IFNULL(loc_box, '')), '') IS NOT NULL
               )"
        );
    } catch (Throwable $e) {
        // أعمدة المكان القديمة غير موجودة
    }
}

function archive_location_label(array $item): string
{
    $one = trim((string) ($item['location'] ?? ''));
    if ($one !== '') {
        return $one;
    }
    $parts = [];
    foreach (['loc_room', 'loc_cabinet', 'loc_shelf', 'loc_box'] as $key) {
        $val = trim((string) ($item[$key] ?? ''));
        if ($val !== '') {
            $parts[] = $val;
        }
    }
    return implode(' / ', $parts);
}

function archive_section_keys(): array
{
    return ['scholarships', 'projects', 'finance', 'general'];
}

function archive_section_key(string $kind): string
{
    $kind = strtolower(trim($kind));
    return in_array($kind, archive_section_keys(), true) ? $kind : 'general';
}

function archive_section_perm(string $kind, string $action = 'view'): string
{
    $action = strtolower(trim($action));
    if (!in_array($action, ['view', 'create', 'edit', 'delete'], true)) {
        $action = 'view';
    }
    return 'archive.' . archive_section_key($kind) . '.' . $action;
}

function user_can_archive_section(string $kind, string $action = 'view', ?array $user = null): bool
{
    $user = $user ?? current_user();
    return $user && user_can(archive_section_perm($kind, $action), $user);
}

function user_can_view_archive_module(?array $user = null): bool
{
    $user = $user ?? current_user();
    if (!$user) {
        return false;
    }
    if (user_can('archive.view', $user)) {
        return true;
    }
    foreach (archive_section_keys() as $kind) {
        if (user_can_archive_section($kind, 'view', $user)) {
            return true;
        }
    }
    return false;
}

function require_archive_view(?array $user = null): array
{
    $user = $user ?? require_login();
    if (!user_can_view_archive_module($user)) {
        flash('danger', __('auth.forbidden'));
        redirect(base_url('index.php'));
    }
    return $user;
}

function archive_general_url(string $page = 'list', array $query = []): string
{
    $pages = [
        'list' => 'archive/general/index.php',
        'view' => 'archive/general/view.php',
        'create' => 'archive/general/create.php',
        'edit' => 'archive/general/edit.php',
        'delete' => 'archive/general/delete.php',
        'print' => 'archive/general/print.php',
        'list_print' => 'archive/general/list_print.php',
        'upload' => 'archive/general/upload.php',
        'file_delete' => 'archive/general/file_delete.php',
    ];
    $path = $pages[$page] ?? $pages['list'];
    $qs = http_build_query(array_filter($query, static fn($v) => $v !== '' && $v !== null));
    return base_url($path) . ($qs !== '' ? '?' . $qs : '');
}

function require_archive_section(string $kind, string $action = 'view', ?string $fallback = null): array
{
    $user = require_login();
    $kind = archive_section_key($kind);
    if (!user_can_archive_section($kind, 'view', $user)) {
        flash('danger', __('auth.forbidden'));
        redirect(base_url($fallback ?? (user_can_view_archive_module($user) ? 'archive/index.php' : 'index.php')));
    }
    if ($action !== 'view' && !user_can_archive_section($kind, $action, $user)) {
        flash('danger', __('auth.forbidden'));
        $list = $kind === 'general' ? archive_general_url('list') : archive_register_url($kind, 'list');
        redirect($list);
    }
    return $user;
}

function archive_title(array $row): string
{
    if (function_exists('dms_lang_code') && dms_lang_code() === 'en') {
        $en = trim((string) ($row['title_en'] ?? ''));
        if ($en !== '') {
            return $en;
        }
    }
    return (string) ($row['title'] ?? '');
}

function can_edit_archive(?array $user = null): bool
{
    $user = $user ?? current_user();
    return $user && user_can_archive_section('general', 'edit', $user);
}

function user_can_access_archive(array $item, ?array $user = null): bool
{
    $user = $user ?? current_user();
    if (!$user || !user_can_archive_section('general', 'view', $user)) {
        return false;
    }
    if (!function_exists('user_needs_department_scope') || !user_needs_department_scope($user)) {
        return true;
    }
    $uid = (int) ($user['id'] ?? 0);
    if ($uid > 0 && $uid === (int) ($item['created_by'] ?? 0)) {
        return true;
    }
    $deptId = (int) ($item['department_id'] ?? 0);
    return $deptId > 0 && function_exists('user_can_access_department') && user_can_access_department($deptId, $user);
}

function user_can_edit_archive(array $item, ?array $user = null): bool
{
    $user = $user ?? current_user();
    return can_edit_archive($user) && user_can_access_archive($item, $user);
}

/** ملف عنصر أرشيف سري: من يملك صلاحية سرية هذا الأرشيف (والمدير)، ومن أضاف العنصر، ومن رفع الملف */
function user_can_view_archive_file(array $item, array $file, ?array $user = null): bool
{
    if (!confidentiality_is_restricted($item['confidentiality'] ?? '')) {
        return true;
    }
    $user = $user ?? current_user();
    if (!$user) {
        return false;
    }
    if (user_can('view.confidential.general', $user)) {
        return true;
    }
    $uid = (int) ($user['id'] ?? 0);
    return $uid > 0 && ($uid === (int) ($item['created_by'] ?? 0) || $uid === (int) ($file['uploaded_by'] ?? 0));
}

/** ملفات سجل أرشيف (منح / مشاريع / مالية) عندما تكون الوثيقة معلّمة سرية. */
function user_can_view_register_files(string $kind, array $row, ?array $user = null): bool
{
    $level = (string) ($row['confidentiality'] ?? $row['CONFIDENTIALITY'] ?? '');
    if (!function_exists('confidentiality_is_restricted') || !confidentiality_is_restricted($level)) {
        return true;
    }
    $user = $user ?? (function_exists('current_user') ? current_user() : null);
    if (!$user) {
        return false;
    }
    $perm = function_exists('view_confidential_perm_for_type') ? view_confidential_perm_for_type($kind) : '';
    if ($perm !== '' && user_can($perm, $user)) {
        return true;
    }
    $uid = (int) ($user['id'] ?? 0);

    return $uid > 0 && $uid === (int) ($row['created_by'] ?? $row['CREATED_BY'] ?? 0);
}

function archive_print_ref(array $item): string
{
    $no = trim((string) ($item['archive_no'] ?? ''));
    if ($no !== '') {
        return $no;
    }
    $old = trim((string) ($item['old_ref'] ?? ''));
    if ($old !== '') {
        return $old;
    }
    $id = (int) ($item['id'] ?? 0);
    return $id > 0 ? '#' . $id : '';
}

/**
 * @return array{where:list<string>,params:list<mixed>}
 */
function archive_list_where(array $filters = [], ?array $user = null): array
{
    ensure_archive_schema();
    $user = $user ?? current_user();
    $where = ['1=1'];
    $params = [];

    if (function_exists('user_needs_department_scope') && user_needs_department_scope($user)) {
        $ids = function_exists('user_department_ids') ? user_department_ids($user) : [];
        $uid = (int) ($user['id'] ?? 0);
        if (!$ids) {
            $where[] = 'a.created_by = ?';
            $params[] = $uid;
        } else {
            [$inSql, $inParams] = dms_sql_in('a.department_id', $ids);
            $where[] = "({$inSql} OR a.created_by = ?)";
            $params = array_merge($params, $inParams, [$uid]);
        }
    }

    if (!empty($filters['q'])) {
        $where[] = '(a.archive_no LIKE ? OR a.title LIKE ? OR a.title_en LIKE ? OR a.party_name LIKE ? OR a.old_ref LIKE ? OR a.keywords LIKE ? OR a.notes LIKE ? OR a.location LIKE ? OR a.loc_room LIKE ? OR a.loc_cabinet LIKE ? OR a.loc_shelf LIKE ? OR a.loc_box LIKE ? OR cat.name LIKE ? OR cat.name_en LIKE ?)';
        $q = '%' . $filters['q'] . '%';
        for ($i = 0; $i < 14; $i++) {
            $params[] = $q;
        }
    }
    if (!empty($filters['doc_year'])) {
        $where[] = 'a.doc_year = ?';
        $params[] = archive_normalize_year((string) $filters['doc_year']) ?: (string) $filters['doc_year'];
    }
    if (!empty($filters['doc_type'])) {
        $where[] = 'a.doc_type = ?';
        $params[] = $filters['doc_type'];
    }
    if (!empty($filters['category_id'])) {
        $where[] = 'a.category_id = ?';
        $params[] = (int) $filters['category_id'];
    }
    if (function_exists('correspondence_normalize_date_filters')) {
        $filters = correspondence_normalize_date_filters($filters);
    }
    if (!empty($filters['from'])) {
        $where[] = 'a.doc_date >= ?';
        $params[] = $filters['from'];
    }
    if (!empty($filters['to'])) {
        $where[] = 'a.doc_date <= ?';
        $params[] = $filters['to'];
    }
    if (!empty($filters['department_id'])) {
        $filterDept = (int) $filters['department_id'];
        if (
            $filterDept > 0
            && (
                can_view_all_departments($user)
                || (function_exists('user_can_access_department') && user_can_access_department($filterDept, $user))
            )
        ) {
            $where[] = 'a.department_id = ?';
            $params[] = $filterDept;
        }
    }
    if (!empty($filters['confidentiality'])) {
        $where[] = 'a.confidentiality = ?';
        $params[] = $filters['confidentiality'];
    }

    return ['where' => $where, 'params' => $params];
}

function archive_list_count(array $filters = [], ?array $user = null): int
{
    $built = archive_list_where($filters, $user);
    $sql = 'SELECT COUNT(*) FROM `tblarchive_items` a
            LEFT JOIN `tblcategories` cat ON cat.id = a.category_id
            WHERE ' . implode(' AND ', $built['where']);
    $stmt = db()->prepare($sql);
    $stmt->execute($built['params']);

    return (int) $stmt->fetchColumn();
}

function archive_list(array $filters = [], ?array $user = null, ?int $limit = null, ?int $offset = null): array
{
    ensure_archive_schema();
    $built = archive_list_where($filters, $user);

    $sql = "SELECT a.*, d.name AS department_name, d.name_en AS department_name_en,
                   d.title_prefix AS department_title_prefix, d.directed_from AS department_directed_from,
                   cat.name AS category_name, cat.name_en AS category_name_en,
                   " . user_name_sql('u', 'creator_name') . ",
                   (SELECT COUNT(*) FROM `tblarchive_files` f WHERE f.archive_id = a.id) AS files_count
            FROM `tblarchive_items` a
            LEFT JOIN `tbldepartments` d ON d.id = a.department_id
            LEFT JOIN `tblcategories` cat ON cat.id = a.category_id
            LEFT JOIN `tblusers` u ON u.id = a.created_by
            WHERE " . implode(' AND ', $built['where']) . "
            ORDER BY COALESCE(a.doc_date, a.created_at) DESC, a.id DESC";

    if ($limit !== null) {
        $sql .= ' LIMIT ' . max(1, (int) $limit) . ' OFFSET ' . max(0, (int) ($offset ?? 0));
    } else {
        $sql .= ' LIMIT 500';
    }

    $stmt = db()->prepare($sql);
    $stmt->execute($built['params']);

    return $stmt->fetchAll();
}

/**
 * @return array{rows:list<array<string,mixed>>,pagination:array<string,mixed>,total:int}
 */
function archive_list_page(array $filters = [], ?array $user = null, int $page = 1, ?int $perPage = null): array
{
    $perPage = $perPage ?? dms_list_per_page();
    $total = archive_list_count($filters, $user);
    $pagination = dms_pagination_meta($total, $page, $perPage);
    $rows = archive_list($filters, $user, $pagination['per_page'], $pagination['offset']);

    return [
        'rows' => $rows,
        'pagination' => $pagination,
        'total' => $total,
    ];
}

function get_archive_item(int $id): ?array
{
    ensure_archive_schema();
    $stmt = db()->prepare(
        "SELECT a.*, d.name AS department_name, d.name_en AS department_name_en,
                d.title_prefix AS department_title_prefix, d.directed_from AS department_directed_from,
                cat.name AS category_name, cat.name_en AS category_name_en,
                " . user_name_sql('u', 'creator_name') . ",
                c.ref_number AS linked_ref, c.type AS linked_type
         FROM `tblarchive_items` a
         LEFT JOIN `tbldepartments` d ON d.id = a.department_id
         LEFT JOIN `tblcategories` cat ON cat.id = a.category_id
         LEFT JOIN `tblusers` u ON u.id = a.created_by
         LEFT JOIN `tblcorrespondence` c ON c.id = a.correspondence_id
         WHERE a.id = ?"
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    $row['_extra'] = archive_item_extra($row);
    return correspondence_apply_ref_display($row);
}

function archive_file_is_image(array $file): bool
{
    $mime = strtolower((string) ($file['file_type'] ?? ''));
    $name = strtolower((string) ($file['file_name'] ?? ''));
    return str_starts_with($mime, 'image/') || (bool) preg_match('/\.(jpe?g|png|gif|webp)$/', $name);
}

function archive_file_is_previewable(array $file): bool
{
    $mime = strtolower((string) ($file['file_type'] ?? ''));
    $name = strtolower((string) ($file['file_name'] ?? ''));
    return archive_file_is_image($file)
        || str_contains($mime, 'pdf')
        || str_ends_with($name, '.pdf');
}

function get_archive_files(int $archiveId): array
{
    ensure_archive_schema();
    $stmt = db()->prepare(
        "SELECT f.*, " . user_name_sql('u', 'uploader_name') . "
         FROM `tblarchive_files` f
         LEFT JOIN `tblusers` u ON u.id = f.uploaded_by
         WHERE f.archive_id = ?
         ORDER BY f.id DESC"
    );
    $stmt->execute([$archiveId]);
    return $stmt->fetchAll();
}

function archive_item_file_display_name(array $item, array $file, array $allFiles): string
{
    $ordered = $allFiles;
    usort($ordered, static fn(array $a, array $b): int => ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0)));
    $index = 0;
    $fid = (int) ($file['id'] ?? 0);
    foreach ($ordered as $i => $row) {
        if ((int) ($row['id'] ?? 0) === $fid) {
            $index = (int) $i;
            break;
        }
    }
    $src = (string) ($file['file_name'] ?? $file['file_path'] ?? '');
    $ext = strtolower((string) pathinfo($src, PATHINFO_EXTENSION));
    $stem = function_exists('archive_file_stem')
        ? archive_file_stem((string) ($item['archive_no'] ?? ''), (int) ($item['id'] ?? 0))
        : 'arch';
    return archive_file_numbered_name($stem, $index, count($ordered), $ext);
}

function archive_stored_file_disk_path(string $stored): string
{
    $disk = function_exists('archive_disk_resolve_stored') ? archive_disk_resolve_stored($stored) : '';
    if ($disk !== '') {
        return $disk;
    }
    $app = dms_storage_disk_path($stored);
    return ($app !== '' && is_file($app)) ? $app : '';
}

function save_archive_item(array $data, array $user, ?int $id = null): int
{
    if (!user_can_archive_section('general', $id ? 'edit' : 'create', $user)) {
        throw new RuntimeException(__('auth.forbidden'));
    }
    ensure_archive_schema();
    $title = trim($data['title'] ?? '');
    if ($title === '') {
        throw new InvalidArgumentException(__('archive.title_required'));
    }
    $titleEn = trim($data['title_en'] ?? '') ?: null;
    $docType = $data['doc_type'] ?? 'other';
    $allowedTypes = archive_doc_types(); // نشطة
    if (!in_array($docType, $allowedTypes, true)) {
        // السماح بالنوع الحالي عند التعديل حتى لو عُطّل
        $chkType = db()->prepare('SELECT code FROM `tblarchive_doc_types` WHERE code = ?');
        $chkType->execute([$docType]);
        if (!$chkType->fetchColumn()) {
            $docType = 'other';
        }
    }
    $docDate = trim($data['doc_date'] ?? '') ?: null;
    $docYear = archive_normalize_year((string) ($data['doc_year'] ?? ''));
    if ($docYear === null && $docDate) {
        $docYear = substr($docDate, 0, 4);
    }
    $party = trim($data['party_name'] ?? '') ?: null;
    $oldRef = trim($data['old_ref'] ?? '') ?: null;
    $deptId = (int) ($data['department_id'] ?? 0) ?: null;
    $conf = $data['confidentiality'] ?? 'normal';
    $allowedConf = array_values(array_filter(array_map(
        static fn(array $row): string => trim((string) ($row['code'] ?? '')),
        function_exists('confidentiality_levels_list') ? confidentiality_levels_list(false) : []
    ))) ?: ['normal', 'confidential'];
    if (!in_array($conf, $allowedConf, true)) {
        // الإبقاء على القيمة الحالية عند التعديل إن كانت قديمة ومُزالة من القائمة
        if ($id) {
            $prev = db()->prepare('SELECT confidentiality FROM `tblarchive_items` WHERE id = ?');
            $prev->execute([(int) $id]);
            $prevConf = (string) ($prev->fetchColumn() ?: '');
            if ($prevConf === '' || $conf !== $prevConf) {
                $conf = 'normal';
            }
        } else {
            $conf = 'normal';
        }
    }
    $keywords = trim($data['keywords'] ?? '') ?: null;
    $notes = trim($data['notes'] ?? '') ?: null;
    $corrId = (int) ($data['correspondence_id'] ?? 0) ?: null;
    $categoryId = (int) ($data['category_id'] ?? 0) ?: null;
    $location = trim((string) ($data['location'] ?? '')) ?: null;
    if ($categoryId) {
        $chkCat = db()->prepare('SELECT id FROM `tblcategories` WHERE id = ?');
        $chkCat->execute([$categoryId]);
        if (!$chkCat->fetchColumn()) {
            $categoryId = null;
        }
    }
    if ($corrId) {
        $linked = function_exists('get_correspondence') ? get_correspondence($corrId) : null;
        if (!$linked || (function_exists('user_can_access_correspondence') && !user_can_access_correspondence($linked, $user))) {
            $corrId = null;
        }
    }
    $extraJson = archive_extra_encode(archive_extra_from_post($data));
    $hasExtra = function_exists('dms_db_column_exists') && dms_db_column_exists('tblarchive_items', 'extra_json');

    if ($id) {
        if ($hasExtra) {
            db()->prepare(
                "UPDATE `tblarchive_items` SET title=?, title_en=?, doc_type=?, doc_date=?, doc_year=?, party_name=?,
                 old_ref=?, location=?, department_id=?, category_id=?, confidentiality=?, keywords=?, notes=?, extra_json=?, correspondence_id=?
                 WHERE id=?"
            )->execute([
                $title, $titleEn, $docType, $docDate, $docYear, $party, $oldRef, $location,
                $deptId, $categoryId, $conf, $keywords, $notes, $extraJson, $corrId, $id,
            ]);
        } else {
            db()->prepare(
                "UPDATE `tblarchive_items` SET title=?, title_en=?, doc_type=?, doc_date=?, doc_year=?, party_name=?,
                 old_ref=?, location=?, department_id=?, category_id=?, confidentiality=?, keywords=?, notes=?, correspondence_id=?
                 WHERE id=?"
            )->execute([
                $title, $titleEn, $docType, $docDate, $docYear, $party, $oldRef, $location,
                $deptId, $categoryId, $conf, $keywords, $notes, $corrId, $id,
            ]);
        }
        log_activity((int) $user['id'], null, 'archive_update', '', $id);
        return $id;
    }

    $archiveNo = next_archive_no();
    if ($hasExtra) {
        db()->prepare(
            "INSERT INTO `tblarchive_items`
             (archive_no, title, title_en, doc_type, doc_date, doc_year, party_name, old_ref, location, department_id, category_id, confidentiality, keywords, notes, extra_json, correspondence_id, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
        )->execute([
            $archiveNo, $title, $titleEn, $docType, $docDate, $docYear, $party, $oldRef, $location,
            $deptId, $categoryId, $conf, $keywords, $notes, $extraJson, $corrId, (int) $user['id'],
        ]);
    } else {
        db()->prepare(
            "INSERT INTO `tblarchive_items`
             (archive_no, title, title_en, doc_type, doc_date, doc_year, party_name, old_ref, location, department_id, category_id, confidentiality, keywords, notes, correspondence_id, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
        )->execute([
            $archiveNo, $title, $titleEn, $docType, $docDate, $docYear, $party, $oldRef, $location,
            $deptId, $categoryId, $conf, $keywords, $notes, $corrId, (int) $user['id'],
        ]);
    }
    $newId = (int) db()->lastInsertId();
    log_activity((int) $user['id'], null, 'archive_create', '', $newId);
    return $newId;
}

/**
 * @return array{rows:list<array<string,mixed>>,truncated:bool}
 */
function archive_correspondence_search(?array $user, string $q, int $limit = 25): array
{
    $user = $user ?? current_user();
    $q = mb_substr(trim($q), 0, 100, 'UTF-8');
    $limit = max(1, min(50, $limit));
    $fetch = $limit + 1;
    $sql = "SELECT c.id, c.ref_number, c.subject, c.corr_date, c.party_name, c.status, c.type,
                   c.snap_person_name, c.snap_org_name, c.snap_title_prefix, c.snap_honorific
            FROM `tblcorrespondence` c
            WHERE 1=1";
    $params = [];
    if (function_exists('department_scope_sql')) {
        [$scopeSql, $scopeParams] = department_scope_sql($user, 'c');
        if ($scopeSql !== '') {
            $sql .= ' AND ' . $scopeSql;
            $params = array_merge($params, $scopeParams);
        }
    }
    if ($q !== '') {
        $likeRaw = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q);
        $like = '%' . $likeRaw . '%';
        $sql .= ' AND (c.ref_number LIKE ? OR c.subject LIKE ? OR c.party_name LIKE ? OR c.external_ref LIKE ?
            OR c.snap_person_name LIKE ? OR c.snap_org_name LIKE ?)';
        array_push($params, $like, $like, $like, $like, $like, $like);
    }
    $sql .= ' ORDER BY c.id DESC LIMIT ' . $fetch;
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    $truncated = count($rows) > $limit;
    if ($truncated) {
        $rows = array_slice($rows, 0, $limit);
    }
    $out = [];
    foreach ($rows as $row) {
        $row = function_exists('correspondence_apply_ref_display') ? correspondence_apply_ref_display($row) : $row;
        $out[] = [
            'id' => (int) $row['id'],
            'ref' => function_exists('format_ref_number') ? format_ref_number($row['ref_number'] ?? '') : (string) ($row['ref_number'] ?? ''),
            'subject' => (string) ($row['subject'] ?? ''),
            'party' => function_exists('correspondence_party_display_name')
                ? correspondence_party_display_name($row)
                : (string) ($row['party_name'] ?? ''),
            'date' => function_exists('format_date') ? format_date($row['corr_date'] ?? null) : (string) ($row['corr_date'] ?? ''),
            'type' => function_exists('type_label') ? type_label((string) ($row['type'] ?? '')) : (string) ($row['type'] ?? ''),
            'type_code' => (string) ($row['type'] ?? ''),
        ];
    }
    return ['rows' => $out, 'truncated' => $truncated];
}

function upload_archive_file(array $file, int $archiveId, int $userId): ?array
{
    ensure_archive_schema();
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException(__('archive.upload_fail'));
    }

    $allowed = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!isset($allowed[$mime])) {
        throw new RuntimeException(__('archive.upload_type'));
    }
    if ($file['size'] > dms_document_upload_max_bytes()) {
        throw new RuntimeException(__('archive.upload_size'));
    }

    $ext = $allowed[$mime];
    $item = get_archive_item($archiveId);
    $stem = function_exists('archive_file_stem')
        ? archive_file_stem((string) ($item['archive_no'] ?? ''), $archiveId)
        : ('arch-' . $archiveId);
    archive_disk_ensure_kind_roots();
    $dir = archive_disk_record_dir('general', $stem);
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException(__('archive.upload_dir'));
    }
    $n = count(function_exists('archive_register_folder_files') ? archive_register_folder_files($dir) : []) + 1;
    do {
        $safeName = $n === 1 ? $stem . '.' . $ext : $stem . '-' . $n . '.' . $ext;
        $dest = $dir . DIRECTORY_SEPARATOR . $safeName;
        $n++;
    } while (is_file($dest) && $n < 500);
    if (is_file($dest)) {
        throw new RuntimeException(__('archive.upload_save'));
    }
    if (!dms_move_uploaded_file((string) $file['tmp_name'], $dest)) {
        throw new RuntimeException(__('archive.upload_save'));
    }

    $relative = archive_disk_relative_path('general', $stem, $safeName);
    db()->prepare(
        "INSERT INTO `tblarchive_files` (archive_id, file_name, file_path, file_type, file_size, uploaded_by)
         VALUES (?,?,?,?,?,?)"
    )->execute([$archiveId, $safeName, $relative, $mime, (int) $file['size'], $userId]);

    log_activity($userId, null, 'upload', $safeName, $archiveId);

    return [
        'id' => (int) db()->lastInsertId(),
        'file_name' => $safeName,
        'file_path' => $relative,
    ];
}

function delete_archive_file(int $fileId): ?array
{
    ensure_archive_schema();
    $stmt = db()->prepare("SELECT * FROM `tblarchive_files` WHERE id = ?");
    $stmt->execute([$fileId]);
    $file = $stmt->fetch();
    if (!$file) {
        return null;
    }
    $path = archive_stored_file_disk_path((string) $file['file_path']);
    if ($path !== '' && is_file($path)) {
        @unlink($path);
    }
    db()->prepare("DELETE FROM `tblarchive_files` WHERE id = ?")->execute([$fileId]);
    return $file;
}

function delete_archive_item(int $id): bool
{
    ensure_archive_schema();
    $files = get_archive_files($id);
    foreach ($files as $f) {
        $path = archive_stored_file_disk_path((string) $f['file_path']);
        if ($path !== '' && is_file($path)) {
            @unlink($path);
        }
    }
    $stmt = db()->prepare("DELETE FROM `tblarchive_items` WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->rowCount() > 0;
}

function get_archive_activity(int $archiveId, int $limit = 40, ?array $item = null): array
{
    $limit = max(1, min(100, $limit));
    if (function_exists('ensure_security_schema')) {
        ensure_security_schema();
    }
    $rows = [];
    if (function_exists('dms_db_column_exists') && dms_db_column_exists('tblactivity_log', 'archive_id')) {
        try {
            $stmt = db()->prepare(
                "SELECT a.*, " . user_name_sql('u', 'user_name') . "
                 FROM `tblactivity_log` a
                 LEFT JOIN `tblusers` u ON u.id = a.user_id
                 WHERE a.archive_id = ?
                 ORDER BY a.id DESC
                 LIMIT {$limit}"
            );
            $stmt->execute([$archiveId]);
            $rows = $stmt->fetchAll();
        } catch (Throwable $e) {
            $rows = [];
        }
    }

    $hasCreate = false;
    foreach ($rows as $row) {
        if (($row['action'] ?? '') === 'archive_create') {
            $hasCreate = true;
            break;
        }
    }
    if (!$hasCreate && $item) {
        $rows[] = [
            'action' => 'archive_create',
            'details' => '',
            'created_at' => $item['created_at'] ?? null,
            'user_id' => $item['created_by'] ?? null,
            'user_name' => $item['creator_name'] ?? '',
            'user_name_en' => $item['creator_name_en'] ?? '',
        ];
    }

    return $rows;
}
