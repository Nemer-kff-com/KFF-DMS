<?php
/**
 * سجل التعاميم الداخلية — مستقل عن الصادر/الوارد وترقيمهما.
 */

function ensure_circulars_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $pdo = db();
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `tblcirculars` (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                ref_number VARCHAR(80) NOT NULL,
                circular_date DATE NOT NULL,
                subject VARCHAR(500) NOT NULL,
                department_id INT UNSIGNED NOT NULL,
                audience ENUM('all','departments') NOT NULL DEFAULT 'all',
                status ENUM('draft','issued') NOT NULL DEFAULT 'draft',
                notes TEXT DEFAULT NULL,
                created_by INT UNSIGNED DEFAULT NULL,
                issued_at DATETIME DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_circular_ref (ref_number),
                INDEX idx_circ_date (circular_date),
                INDEX idx_circ_dept (department_id),
                INDEX idx_circ_status (status),
                CONSTRAINT fk_circ_dept FOREIGN KEY (department_id) REFERENCES `tbldepartments`(id) ON DELETE RESTRICT,
                CONSTRAINT fk_circ_user FOREIGN KEY (created_by) REFERENCES `tblusers`(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `tblcircular_departments` (
                circular_id INT UNSIGNED NOT NULL,
                department_id INT UNSIGNED NOT NULL,
                PRIMARY KEY (circular_id, department_id),
                CONSTRAINT fk_circd_circ FOREIGN KEY (circular_id) REFERENCES `tblcirculars`(id) ON DELETE CASCADE,
                CONSTRAINT fk_circd_dept FOREIGN KEY (department_id) REFERENCES `tbldepartments`(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `tblcircular_files` (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                circular_id INT UNSIGNED NOT NULL,
                file_name VARCHAR(255) NOT NULL,
                file_path VARCHAR(500) NOT NULL,
                file_type VARCHAR(50) DEFAULT NULL,
                file_size INT UNSIGNED DEFAULT 0,
                uploaded_by INT UNSIGNED DEFAULT NULL,
                uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_circf_item FOREIGN KEY (circular_id) REFERENCES `tblcirculars`(id) ON DELETE CASCADE,
                CONSTRAINT fk_circf_user FOREIGN KEY (uploaded_by) REFERENCES `tblusers`(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `tblcircular_sequences` (
                seq_year SMALLINT UNSIGNED NOT NULL,
                last_number INT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (seq_year)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        dms_db_tables_map(true);
        $circularCols = [
            'content_mode' => "ENUM('file','electronic') NOT NULL DEFAULT 'file'",
            'letter_body' => 'TEXT NULL DEFAULT NULL',
            'letter_body_en' => 'TEXT NULL DEFAULT NULL',
            'snap_from_prefix' => 'VARCHAR(120) NULL DEFAULT NULL',
            'snap_from_person' => 'VARCHAR(255) NULL DEFAULT NULL',
            'snap_from_job' => 'VARCHAR(255) NULL DEFAULT NULL',
            'snap_from_org' => 'VARCHAR(255) NULL DEFAULT NULL',
            'snap_from_signature' => 'VARCHAR(255) NULL DEFAULT NULL',
        ];
        foreach ($circularCols as $col => $def) {
            if (!dms_db_column_exists('tblcirculars', $col)) {
                $pdo->exec('ALTER TABLE `tblcirculars` ADD COLUMN `' . $col . '` ' . $def);
            }
        }
        if (function_exists('circular_migrate_seq_to_sequences')) {
            circular_migrate_seq_to_sequences();
        }
    } catch (Throwable $e) {
        $done = false;
    }
}

function can_edit_circulars(?array $user = null): bool
{
    $user = $user ?? current_user();
    return $user && user_can('circulars.edit', $user);
}

function circular_prefix(): string
{
    return corr_sanitize_prefix((string) setting('circular_prefix', ''));
}

function circular_migrate_seq_to_sequences(): void
{
    if (function_exists('ensure_sequences_schema')) {
        ensure_sequences_schema();
    }
    if (!function_exists('dms_db_table_exists') || !dms_db_table_exists('tblsequences') || !dms_db_table_exists('tblcircular_sequences')) {
        return;
    }
    try {
        $rows = db()->query('SELECT seq_year, last_number FROM `tblcircular_sequences`')->fetchAll();
        $ins = db()->prepare(
            'INSERT INTO `tblsequences` (seq_type, seq_year, seq_scope, last_number)
             VALUES (\'circular\', ?, \'\', ?)
             ON DUPLICATE KEY UPDATE last_number = GREATEST(last_number, VALUES(last_number))'
        );
        foreach ($rows as $row) {
            $ins->execute([(int) ($row['seq_year'] ?? 0), (int) ($row['last_number'] ?? 0)]);
        }
    } catch (Throwable $e) {
        // النوع غير مضاف بعد إلى ENUM
    }
}

function circular_max_used_sequence(int $year, string $prefix = ''): int
{
    $max = 0;
    try {
        $rows = db()->query('SELECT ref_number FROM `tblcirculars` WHERE ref_number IS NOT NULL AND ref_number <> ""')->fetchAll(PDO::FETCH_COLUMN);
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

function next_circular_ref(): string
{
    ensure_circulars_schema();
    if (function_exists('ensure_sequences_schema')) {
        ensure_sequences_schema();
    }
    circular_migrate_seq_to_sequences();
    $year = function_exists('corr_numbering_year') ? corr_numbering_year() : corr_numbering_hijri_year();
    $prefix = circular_prefix();
    $pdo = db();
    $ownTx = !$pdo->inTransaction();
    if ($ownTx) {
        $pdo->beginTransaction();
    }
    try {
        $stmt = $pdo->prepare(
            "SELECT last_number FROM `tblsequences` WHERE seq_type = 'circular' AND seq_year = ? AND seq_scope = '' FOR UPDATE"
        );
        $stmt->execute([$year]);
        $last = $stmt->fetchColumn();
        if ($last === false) {
            $pdo->prepare(
                "INSERT INTO `tblsequences` (seq_type, seq_year, seq_scope, last_number) VALUES ('circular', ?, '', 0)"
            )->execute([$year]);
            $last = 0;
        }
        $seq = max((int) $last, circular_max_used_sequence($year, $prefix));
        $ref = '';
        $guard = 0;
        do {
            $guard++;
            $seq++;
            $ref = corr_build_ref_number($prefix, $year, $seq);
            $chk = $pdo->prepare('SELECT id FROM `tblcirculars` WHERE ref_number = ? LIMIT 1');
            $chk->execute([$ref]);
        } while ($chk->fetchColumn() && $guard < 500);
        if ($guard >= 500) {
            throw new RuntimeException(__('circular.number_fail'));
        }
        $pdo->prepare(
            "UPDATE `tblsequences` SET last_number = ? WHERE seq_type = 'circular' AND seq_year = ? AND seq_scope = ''"
        )->execute([$seq, $year]);
        if ($ownTx) {
            $pdo->commit();
        }
        return $ref;
    } catch (Throwable $e) {
        if ($ownTx && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function circular_status_label(string $status): string
{
    $key = 'circular.status.' . $status;
    $t = __($key);
    return $t !== $key ? $t : $status;
}

function circular_status_badge(string $status): string
{
    $color = $status === 'issued' ? 'success' : 'secondary';
    return '<span class="badge text-bg-' . $color . '">' . e(circular_status_label($status)) . '</span>';
}

function circular_audience_label(string $audience): string
{
    $key = 'circular.audience.' . $audience;
    $t = __($key);
    return $t !== $key ? $t : $audience;
}

/**
 * @return array{where:list<string>,params:list<mixed>}
 */
function circular_list_where(array $filters = [], ?array $user = null): array
{
    ensure_circulars_schema();
    $user = $user ?? current_user();
    $where = ['1=1'];
    $params = [];

    if (function_exists('user_needs_department_scope') && user_needs_department_scope($user)) {
        $ids = function_exists('user_department_ids') ? user_department_ids($user) : [];
        $issuedAll = "(c.status = 'issued' AND c.audience = 'all')";
        if (!$ids) {
            $where[] = $issuedAll;
        } else {
            [$ownSql, $ownParams] = dms_sql_in('c.department_id', $ids);
            [$tgtSql, $tgtParams] = dms_sql_in('cd.department_id', $ids);
            $where[] = "({$ownSql} OR (c.status = 'issued' AND c.audience = 'all') OR (c.status = 'issued' AND EXISTS (
                SELECT 1 FROM `tblcircular_departments` cd
                WHERE cd.circular_id = c.id AND {$tgtSql}
            )))";
            $params = array_merge($params, $ownParams, $tgtParams);
        }
    }

    if (!empty($filters['q'])) {
        $where[] = '(c.subject LIKE ? OR c.ref_number LIKE ? OR c.notes LIKE ?)';
        $q = '%' . $filters['q'] . '%';
        $params[] = $q;
        $params[] = $q;
        $params[] = $q;
    }
    if (!empty($filters['status']) && in_array($filters['status'], ['draft', 'issued'], true)) {
        $where[] = 'c.status = ?';
        $params[] = $filters['status'];
    }
    if (function_exists('correspondence_normalize_date_filters')) {
        $filters = correspondence_normalize_date_filters($filters);
    }
    if (!empty($filters['from'])) {
        $where[] = 'c.circular_date >= ?';
        $params[] = $filters['from'];
    }
    if (!empty($filters['to'])) {
        $where[] = 'c.circular_date <= ?';
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
            $where[] = 'c.department_id = ?';
            $params[] = $filterDept;
        }
    }

    return ['where' => $where, 'params' => $params];
}

function circular_list_count(array $filters = [], ?array $user = null): int
{
    $built = circular_list_where($filters, $user);
    $sql = 'SELECT COUNT(*) FROM `tblcirculars` c WHERE ' . implode(' AND ', $built['where']);
    $stmt = db()->prepare($sql);
    $stmt->execute($built['params']);

    return (int) $stmt->fetchColumn();
}

function circular_list(array $filters = [], ?array $user = null, ?int $limit = null, ?int $offset = null): array
{
    ensure_circulars_schema();
    $built = circular_list_where($filters, $user);

    $sql = "SELECT c.*, d.name AS department_name, d.name_en AS department_name_en,
                   d.title_prefix AS department_title_prefix, d.directed_from AS department_directed_from,
                   " . user_name_sql('u', 'creator_name') . ",
                   (SELECT COUNT(*) FROM `tblcircular_files` f WHERE f.circular_id = c.id) AS files_count
            FROM `tblcirculars` c
            LEFT JOIN `tbldepartments` d ON d.id = c.department_id
            LEFT JOIN `tblusers` u ON u.id = c.created_by
            WHERE " . implode(' AND ', $built['where']) . "
            ORDER BY c.circular_date DESC, c.id DESC";

    if ($limit !== null) {
        $sql .= ' LIMIT ' . max(1, (int) $limit) . ' OFFSET ' . max(0, (int) ($offset ?? 0));
    } else {
        $sql .= ' LIMIT 500';
    }

    $stmt = db()->prepare($sql);
    $stmt->execute($built['params']);

    return array_map('correspondence_apply_ref_display', $stmt->fetchAll());
}

/**
 * @return array{rows:list<array<string,mixed>>,pagination:array<string,mixed>,total:int}
 */
function circular_list_page(array $filters = [], ?array $user = null, int $page = 1, ?int $perPage = null): array
{
    $perPage = $perPage ?? dms_list_per_page();
    $total = circular_list_count($filters, $user);
    $pagination = dms_pagination_meta($total, $page, $perPage);
    $rows = circular_list($filters, $user, $pagination['per_page'], $pagination['offset']);

    return [
        'rows' => $rows,
        'pagination' => $pagination,
        'total' => $total,
    ];
}

function get_circular(int $id): ?array
{
    ensure_circulars_schema();
    $stmt = db()->prepare(
        "SELECT c.*, d.name AS department_name, d.name_en AS department_name_en,
                d.title_prefix AS department_title_prefix, d.directed_from AS department_directed_from,
                " . user_name_sql('u', 'creator_name') . "
         FROM `tblcirculars` c
         LEFT JOIN `tbldepartments` d ON d.id = c.department_id
         LEFT JOIN `tblusers` u ON u.id = c.created_by
         WHERE c.id = ?"
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    return correspondence_apply_ref_display($row);
}

function circular_align_attachment_names(int $circularId): void
{
    static $done = [];
    if ($circularId <= 0 || isset($done[$circularId])) {
        return;
    }
    $done[$circularId] = true;
    ensure_circulars_schema();
    try {
        $refStmt = db()->prepare('SELECT ref_number FROM `tblcirculars` WHERE id = ?');
        $refStmt->execute([$circularId]);
        $ref = (string) ($refStmt->fetchColumn() ?: '');
    } catch (Throwable $e) {
        return;
    }
    $stem = function_exists('correspondence_attachment_basename')
        ? correspondence_attachment_basename($ref)
        : '';
    if ($stem === '' || $stem === 'corr') {
        $stem = 'circular-' . $circularId;
    }
    $files = db()->prepare(
        'SELECT id, file_name, file_path FROM `tblcircular_files` WHERE circular_id = ? ORDER BY id'
    );
    $files->execute([$circularId]);
    $rows = $files->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $used = [];
    foreach ($rows as $row) {
        $ext = strtolower((string) pathinfo((string) ($row['file_name'] ?? ''), PATHINFO_EXTENSION));
        if ($ext === '') {
            $ext = strtolower((string) pathinfo((string) ($row['file_path'] ?? ''), PATHINFO_EXTENSION));
        }
        if ($ext === '') {
            continue;
        }
        $n = 1;
        do {
            $name = $n === 1 ? $stem . '.' . $ext : $stem . '_' . $n . '.' . $ext;
            $n++;
        } while (isset($used[strtolower($name)]) && $n < 200);
        $used[strtolower($name)] = true;
        $stored = (string) ($row['file_path'] ?? '');
        $full = function_exists('dms_storage_disk_path') ? dms_storage_disk_path($stored) : '';
        $currentName = (string) ($row['file_name'] ?? '');
        if ($full !== '' && is_file($full)) {
            $dir = dirname($full);
            $target = $dir . DIRECTORY_SEPARATOR . $name;
            $sameFile = is_file($target) && realpath($target) === realpath($full);
            if (!$sameFile && is_file($target)) {
                continue;
            }
            if (!$sameFile && !@rename($full, $target)) {
                continue;
            }
            $rel = str_replace('\\', '/', function_exists('dms_storage_relative') ? dms_storage_relative($stored) : $stored);
            $relDir = trim(str_replace('\\', '/', dirname($rel)), '.');
            $newRel = dms_storage_db_path($relDir . '/' . $name);
            if ($currentName !== $name || $stored !== $newRel) {
                db()->prepare('UPDATE `tblcircular_files` SET file_name = ?, file_path = ? WHERE id = ?')
                    ->execute([$name, $newRel, (int) $row['id']]);
            }
            continue;
        }
        if ($currentName !== $name) {
            db()->prepare('UPDATE `tblcircular_files` SET file_name = ? WHERE id = ?')
                ->execute([$name, (int) $row['id']]);
        }
    }
}

function circular_link_activity_rows(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    if (!function_exists('dms_db_column_exists') || !dms_db_column_exists('tblactivity_log', 'circular_id')) {
        $done = false;
        return;
    }
    if (function_exists('setting') && setting('circular_activity_linked', '') === '1') {
        return;
    }
    try {
        db()->exec(
            "UPDATE `tblactivity_log` a
             INNER JOIN `tblcirculars` c ON (
                (a.action = 'circular_create' AND a.details = CONCAT('إضافة تعميم ', c.ref_number))
                OR (a.action = 'circular_issue' AND (a.details = CONCAT('إصدار تعميم ', c.ref_number) OR a.details = CONCAT('إصدار تعميم #', c.id)))
                OR (a.action = 'circular_update' AND a.details = CONCAT('تحديث تعميم #', c.id))
                OR (a.action = 'circular_delete' AND (a.details = CONCAT('حذف تعميم ', c.ref_number) OR a.details = CONCAT('حذف تعميم #', c.id)))
             )
             SET a.circular_id = c.id
             WHERE a.circular_id IS NULL"
        );
        if (function_exists('set_setting')) {
            set_setting('circular_activity_linked', '1', 'ربط سجل التعاميم');
        }
    } catch (Throwable $e) {
        $done = false;
    }
}

function get_circular_activity(int $circularId, int $limit = 40): array
{
    $limit = max(1, min(100, $limit));
    if (function_exists('ensure_security_schema')) {
        ensure_security_schema();
    }
    circular_link_activity_rows();
    if (!function_exists('dms_db_column_exists') || !dms_db_column_exists('tblactivity_log', 'circular_id')) {
        return [];
    }
    try {
        $stmt = db()->prepare(
            "SELECT a.*, " . user_name_sql('u', 'user_name') . "
             FROM `tblactivity_log` a
             LEFT JOIN `tblusers` u ON u.id = a.user_id
             WHERE a.circular_id = ?
             ORDER BY a.id DESC
             LIMIT {$limit}"
        );
        $stmt->execute([$circularId]);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function get_circular_files(int $circularId): array
{
    ensure_circulars_schema();
    circular_align_attachment_names($circularId);
    $stmt = db()->prepare(
        "SELECT f.*, " . user_name_sql('u', 'uploader_name') . "
         FROM `tblcircular_files` f
         LEFT JOIN `tblusers` u ON u.id = f.uploaded_by
         WHERE f.circular_id = ?
         ORDER BY f.id DESC"
    );
    $stmt->execute([$circularId]);
    return $stmt->fetchAll();
}

/** @return list<int> */
function get_circular_department_ids(int $circularId): array
{
    ensure_circulars_schema();
    $stmt = db()->prepare(
        'SELECT department_id FROM `tblcircular_departments` WHERE circular_id = ? ORDER BY department_id'
    );
    $stmt->execute([$circularId]);
    $ids = [];
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
        $ids[] = (int) $id;
    }
    return $ids;
}

function get_circular_departments(int $circularId): array
{
    ensure_circulars_schema();
    $stmt = db()->prepare(
        "SELECT d.*
         FROM `tblcircular_departments` cd
         JOIN `tbldepartments` d ON d.id = cd.department_id
         WHERE cd.circular_id = ?
         ORDER BY d.name"
    );
    $stmt->execute([$circularId]);
    return $stmt->fetchAll();
}

function user_can_access_circular(array $item, ?array $user = null): bool
{
    $user = $user ?? current_user();
    if (!$user) {
        return false;
    }
    if (!function_exists('user_needs_department_scope') || !user_needs_department_scope($user)) {
        return true;
    }
    $deptId = (int) ($item['department_id'] ?? 0);
    if ($deptId > 0 && user_can_access_department($deptId, $user)) {
        return true;
    }
    if (($item['status'] ?? '') !== 'issued') {
        return false;
    }
    if (($item['audience'] ?? '') === 'all') {
        return true;
    }
    $userDepts = user_department_ids($user);
    if (!$userDepts) {
        return false;
    }
    $targets = get_circular_department_ids((int) $item['id']);
    return (bool) array_intersect($userDepts, $targets);
}

function user_can_edit_circular(array $item, ?array $user = null): bool
{
    $user = $user ?? current_user();
    return can_edit_circulars($user) && circular_user_in_scope($item, $user);
}

function user_can_issue_circular(array $item, ?array $user = null): bool
{
    $user = $user ?? current_user();
    return $user && user_can('circulars.issue', $user) && circular_user_in_scope($item, $user);
}

/** التعميم ضمن أقسام المستخدم (لمن نطاقه قسمه) */
function circular_user_in_scope(array $item, ?array $user): bool
{
    if (!$user || !user_can_access_circular($item, $user)) {
        return false;
    }
    if (!function_exists('user_needs_department_scope') || !user_needs_department_scope($user)) {
        return true;
    }
    $deptId = (int) ($item['department_id'] ?? 0);
    return $deptId > 0 && user_can_access_department($deptId, $user);
}

function user_can_delete_circular(array $item, ?array $user = null): bool
{
    $user = $user ?? current_user();
    if (!user_can_access_circular($item, $user)) {
        return false;
    }
    if (($item['status'] ?? '') === 'draft') {
        return can_edit_circulars($user);
    }
    return can_delete($user);
}

/** @return list<int> */
function circular_normalize_department_ids(array $ids): array
{
    $out = [];
    foreach ($ids as $id) {
        $id = (int) $id;
        if ($id > 0) {
            $out[$id] = $id;
        }
    }
    return array_values($out);
}

function circular_is_electronic(array $item): bool
{
    return ($item['content_mode'] ?? '') === 'electronic';
}

function circular_has_letter(array $item): bool
{
    return trim((string) ($item['letter_body'] ?? '')) !== ''
        || trim((string) ($item['letter_body_en'] ?? '')) !== '';
}

function circular_letter_lang(array $item, ?string $prefer = null): string
{
    $prefer = $prefer === 'en' ? 'en' : ($prefer === 'ar' ? 'ar' : '');
    $ar = trim((string) ($item['letter_body'] ?? ''));
    $en = trim((string) ($item['letter_body_en'] ?? ''));
    if ($prefer === 'en' && $en !== '') {
        return 'en';
    }
    if ($prefer === 'ar' && $ar !== '') {
        return 'ar';
    }
    if ($ar !== '') {
        return 'ar';
    }
    if ($en !== '') {
        return 'en';
    }

    return 'ar';
}

/** لقطة صاحب الشأن وقت الإصدار، حتى لا يتغيّر الخطاب لاحقاً. */
function circular_officer_snapshot(int $deptId): array
{
    $empty = [
        'snap_from_prefix' => null,
        'snap_from_person' => null,
        'snap_from_job' => null,
        'snap_from_org' => null,
        'snap_from_signature' => null,
    ];
    if ($deptId <= 0 || !function_exists('admin_row')) {
        return $empty;
    }
    $dept = admin_row('tbldepartments', $deptId);
    if (!is_array($dept)) {
        return $empty;
    }
    $officer = function_exists('department_officer_fields') ? department_officer_fields($dept) : [
        'title_prefix' => trim((string) ($dept['title_prefix'] ?? '')),
        'directed_from' => trim((string) ($dept['directed_from'] ?? '')),
        'job_title' => trim((string) ($dept['job_title'] ?? '')),
    ];
    $org = function_exists('department_display_name') ? department_display_name($dept) : trim((string) ($dept['name'] ?? ''));
    $sign = function_exists('department_principal_signature_path') ? department_principal_signature_path($dept) : '';
    $prefix = trim((string) ($officer['title_prefix'] ?? ''));
    $person = trim((string) ($officer['directed_from'] ?? ''));
    $job = trim((string) ($officer['job_title'] ?? ''));

    return [
        'snap_from_prefix' => $prefix !== '' ? $prefix : null,
        'snap_from_person' => $person !== '' ? $person : null,
        'snap_from_job' => $job !== '' ? $job : null,
        'snap_from_org' => $org !== '' ? $org : null,
        'snap_from_signature' => $sign !== '' ? $sign : null,
    ];
}

function circular_has_officer_snapshot(array $item): bool
{
    foreach (['snap_from_person', 'snap_from_prefix', 'snap_from_job', 'snap_from_org', 'snap_from_signature'] as $key) {
        if (trim((string) ($item[$key] ?? '')) !== '') {
            return true;
        }
    }

    return false;
}

/** بعد الإصدار تُعرض اللقطة بدل بيانات القسم الحالية. */
function circular_officer_display_row(array $row): array
{
    if (!circular_has_officer_snapshot($row)) {
        return $row;
    }
    $row['department_title_prefix'] = (string) ($row['snap_from_prefix'] ?? '');
    $row['department_directed_from'] = (string) ($row['snap_from_person'] ?? '');
    $org = trim((string) ($row['snap_from_org'] ?? ''));
    if ($org !== '') {
        $row['department_name'] = $org;
        $row['department_name_en'] = $org;
    }

    return $row;
}

function circular_as_letter_row(array $item): array
{
    $row = $item;
    $row['type'] = 'circular';
    $row['corr_date'] = (string) ($item['circular_date'] ?? ($item['corr_date'] ?? ''));
    if (($item['status'] ?? '') === 'issued' && circular_has_officer_snapshot($item)) {
        $row['department_id'] = 0;
        $row['issuing_department_id'] = 0;
    } else {
        $row['issuing_department_id'] = (int) ($item['department_id'] ?? 0);
    }

    return $row;
}

function circular_audience_line(array $item, array $departmentRows = []): string
{
    if (($item['audience'] ?? '') !== 'departments') {
        return circular_audience_label('all');
    }
    $names = [];
    foreach ($departmentRows as $d) {
        if (!is_array($d)) {
            continue;
        }
        $name = department_display_name($d);
        if ($name !== '') {
            $names[] = $name;
        }
    }

    return $names ? implode('، ', $names) : circular_audience_label('departments');
}

function circular_letter_body_html(array $item, ?string $lang = null): string
{
    $lang = circular_letter_lang($item, $lang);
    $row = circular_as_letter_row($item);
    if (!function_exists('correspondence_letter_text') || !function_exists('render_letter_body_html')) {
        return '';
    }
    $text = correspondence_letter_text($row, $lang);
    if ($text === '') {
        return '';
    }
    return render_letter_body_html($text, false, $row);
}

function circular_letter_preview_html(array $item, array $departmentRows = [], ?string $lang = null): string
{
    $lang = circular_letter_lang($item, $lang);
    $isEn = $lang === 'en';
    $body = circular_letter_body_html($item, $lang);
    $audience = circular_audience_line($item, $departmentRows);
    $ref = trim((string) ($item['ref_number'] ?? ''));
    $number = $ref !== '' && function_exists('format_ref_number') ? format_ref_number($ref) : $ref;
    $date = function_exists('format_date') ? format_date($item['circular_date'] ?? null) : (string) ($item['circular_date'] ?? '');
    $subject = trim((string) ($item['subject'] ?? ''));
    $dir = $isEn ? 'ltr' : 'rtl';
    $fontClass = function_exists('letter_font_css_class') && function_exists('letter_body_font_code')
        ? letter_font_css_class(letter_body_font_code($body))
        : '';
    $toClass = mb_strlen($audience) > 48 ? 'circular-letter-to-many' : 'letter-to-name';

    $html = '<div class="letter-preview-inner ' . e($fontClass) . '" dir="' . e($dir) . '">';
    $html .= '<div class="letter-preview-head">';
    if ($number !== '') {
        $html .= '<div class="letter-preview-head-row"><strong>' . e(__('circular.ref')) . ': <span dir="ltr">' . e($number) . '</span></strong></div>';
    }
    if ($date !== '') {
        $html .= '<div class="letter-preview-head-row"><strong>' . e(__('circular.date')) . ': ' . e($date) . '</strong></div>';
    }
    $html .= '</div>';
    if ($subject !== '') {
        $html .= '<h2 class="letter-preview-subject">' . e($subject) . '</h2>';
    }
    if ($audience !== '') {
        $html .= '<div class="letter-to"><div class="letter-to-label">' . e(__('circular.letter_to')) . '</div>';
        $html .= '<div class="' . e($toClass) . '">' . e($audience) . '</div></div>';
    }
    if ($body !== '') {
        $html .= '<div class="letter-body">' . $body . '</div>';
    }
    $html .= '</div>';

    return $html;
}

function circular_sync_departments(int $circularId, string $audience, array $departmentIds): void
{
    db()->prepare('DELETE FROM `tblcircular_departments` WHERE circular_id = ?')->execute([$circularId]);
    if ($audience !== 'departments') {
        return;
    }
    $ins = db()->prepare(
        'INSERT INTO `tblcircular_departments` (circular_id, department_id) VALUES (?, ?)'
    );
    foreach ($departmentIds as $deptId) {
        $ins->execute([$circularId, $deptId]);
    }
}

function save_circular(array $data, array $user, ?int $id = null): int
{
    if (!user_can($id ? 'circulars.edit' : 'circulars.create', $user)) {
        throw new RuntimeException(__('auth.forbidden'));
    }
    ensure_circulars_schema();
    $subject = trim((string) ($data['subject'] ?? ''));
    if ($subject === '') {
        throw new InvalidArgumentException(__('circular.subject_required'));
    }
    $date = trim((string) ($data['circular_date'] ?? ''));
    if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        throw new InvalidArgumentException(__('circular.date_required'));
    }
    $deptId = (int) ($data['department_id'] ?? 0);
    if ($deptId <= 0) {
        throw new InvalidArgumentException(__('circular.department_required'));
    }
    if (function_exists('user_can_access_department') && !user_can_access_department($deptId, $user) && !can_choose_correspondence_department($user)) {
        throw new InvalidArgumentException(__('circular.department_forbidden'));
    }
    if (user_needs_department_scope($user) && !user_can_access_department($deptId, $user)) {
        throw new InvalidArgumentException(__('circular.department_forbidden'));
    }
    $chkDept = db()->prepare('SELECT id FROM `tbldepartments` WHERE id = ?');
    $chkDept->execute([$deptId]);
    if (!$chkDept->fetchColumn()) {
        throw new InvalidArgumentException(__('circular.department_required'));
    }

    $audience = (($data['audience'] ?? 'all') === 'departments') ? 'departments' : 'all';
    $targetIds = circular_normalize_department_ids(
        is_array($data['audience_department_ids'] ?? null) ? $data['audience_department_ids'] : []
    );
    if ($audience === 'departments') {
        if (!$targetIds) {
            throw new InvalidArgumentException(__('circular.audience_depts_required'));
        }
        $ph = implode(',', array_fill(0, count($targetIds), '?'));
        $stmt = db()->prepare("SELECT id FROM `tbldepartments` WHERE id IN ({$ph})");
        $stmt->execute($targetIds);
        $targetIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        if (!$targetIds) {
            throw new InvalidArgumentException(__('circular.audience_depts_required'));
        }
    } else {
        $targetIds = [];
    }
    $notes = trim((string) ($data['notes'] ?? '')) ?: null;
    $contentMode = (($data['content_mode'] ?? '') === 'electronic') ? 'electronic' : 'file';
    $letterBody = trim((string) ($data['letter_body'] ?? ''));
    $letterBodyEn = trim((string) ($data['letter_body_en'] ?? ''));
    $letterBody = $letterBody !== '' ? $letterBody : null;
    $letterBodyEn = $letterBodyEn !== '' ? $letterBodyEn : null;

    if ($id) {
        $existing = get_circular($id);
        if (!$existing || !user_can_edit_circular($existing, $user)) {
            throw new RuntimeException(__('circular.not_found'));
        }
        if (($existing['status'] ?? '') === 'issued') {
            db()->prepare('UPDATE `tblcirculars` SET notes = ? WHERE id = ?')->execute([$notes, $id]);
            log_activity((int) $user['id'], null, 'circular_update', 'تحديث تعميم #' . $id, null, $id);
            return $id;
        }
        db()->prepare(
            'UPDATE `tblcirculars` SET circular_date=?, subject=?, department_id=?, audience=?, notes=?, content_mode=?, letter_body=?, letter_body_en=? WHERE id=?'
        )->execute([$date, $subject, $deptId, $audience, $notes, $contentMode, $letterBody, $letterBodyEn, $id]);
        circular_sync_departments($id, $audience, $targetIds);
        log_activity((int) $user['id'], null, 'circular_update', 'تحديث تعميم #' . $id, null, $id);
        return $id;
    }

    $ref = next_circular_ref();
    db()->prepare(
        'INSERT INTO `tblcirculars`
         (ref_number, circular_date, subject, department_id, audience, status, notes, created_by, content_mode, letter_body, letter_body_en)
         VALUES (?,?,?,?,?,?,?,?,?,?,?)'
    )->execute([$ref, $date, $subject, $deptId, $audience, 'draft', $notes, (int) $user['id'], $contentMode, $letterBody, $letterBodyEn]);
    $newId = (int) db()->lastInsertId();
    circular_sync_departments($newId, $audience, $targetIds);
    log_activity((int) $user['id'], null, 'circular_create', 'إضافة تعميم ' . $ref, null, $newId);
    return $newId;
}

function issue_circular(int $id, array $user): void
{
    $item = get_circular($id);
    if (!$item || !circular_user_in_scope($item, $user)) {
        throw new RuntimeException(__('circular.not_found'));
    }
    if (!user_can_issue_circular($item, $user)) {
        throw new RuntimeException(__('auth.forbidden'));
    }
    if (($item['status'] ?? '') === 'issued') {
        return;
    }
    if (circular_is_electronic($item)) {
        if (!circular_has_letter($item)) {
            throw new InvalidArgumentException(__('circular.issue_need_letter'));
        }
        $snap = circular_officer_snapshot((int) ($item['department_id'] ?? 0));
        db()->prepare(
            "UPDATE `tblcirculars`
             SET status = 'issued', issued_at = COALESCE(issued_at, NOW()),
                 snap_from_prefix = ?, snap_from_person = ?, snap_from_job = ?, snap_from_org = ?, snap_from_signature = ?
             WHERE id = ?"
        )->execute([
            $snap['snap_from_prefix'],
            $snap['snap_from_person'],
            $snap['snap_from_job'],
            $snap['snap_from_org'],
            $snap['snap_from_signature'],
            $id,
        ]);
    } else {
        $files = get_circular_files($id);
        if (!$files) {
            throw new InvalidArgumentException(__('circular.issue_need_file'));
        }
        db()->prepare(
            "UPDATE `tblcirculars` SET status = 'issued', issued_at = COALESCE(issued_at, NOW()) WHERE id = ?"
        )->execute([$id]);
    }
    log_activity((int) $user['id'], null, 'circular_issue', 'إصدار تعميم ' . ($item['ref_number'] ?? ('#' . $id)), null, $id);
    $item['status'] = 'issued';
    circular_notify_issued($item, $user);
}

/** مستخدمو الجهة الذين يظهر لهم التعميم بعد الإصدار، دون مُصدِره. */
function circular_issue_recipient_ids(array $item, int $excludeUserId = 0): array
{
    $item['status'] = 'issued';
    try {
        $rows = db()->query(
            'SELECT id, role, role_id, department_id FROM `tblusers` WHERE is_active = 1'
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
    $ids = [];
    foreach ($rows as $row) {
        $uid = (int) ($row['id'] ?? 0);
        if ($uid <= 0 || $uid === $excludeUserId) {
            continue;
        }
        if (user_can_access_circular($item, $row)) {
            $ids[] = $uid;
        }
    }

    return $ids;
}

function circular_notify_issued(array $item, array $actor): void
{
    $circularId = (int) ($item['id'] ?? 0);
    if ($circularId <= 0 || !function_exists('dms_notify_users')) {
        return;
    }
    $ids = circular_issue_recipient_ids($item, (int) ($actor['id'] ?? 0));
    if ($ids === []) {
        return;
    }
    $ref = function_exists('format_ref_number')
        ? format_ref_number((string) ($item['ref_number'] ?? ''))
        : (string) ($item['ref_number'] ?? '');
    $subject = trim((string) ($item['subject'] ?? ''));
    $body = trim($ref . ($ref !== '' && $subject !== '' ? ' — ' : '') . $subject);
    if (function_exists('mb_substr')) {
        $body = mb_substr($body, 0, 500);
    }
    dms_notify_users($ids, [
        'type' => 'circular_issued',
        'title' => __('notify.circular_title'),
        'body' => $body,
        'link' => 'circulars/view.php?id=' . $circularId,
        'department_id' => (int) ($item['department_id'] ?? 0),
        'dedupe_key' => 'circular:' . $circularId,
    ]);
}

function upload_circular_file(array $file, int $circularId, int $userId): ?array
{
    ensure_circulars_schema();
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException(__('circular.upload_fail'));
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
        throw new RuntimeException(__('circular.upload_type'));
    }
    if ($file['size'] > dms_document_upload_max_bytes()) {
        throw new RuntimeException(__('circular.upload_size'));
    }

    $ext = $allowed[$mime];
    circular_align_attachment_names($circularId);
    $ref = '';
    try {
        $refStmt = db()->prepare('SELECT ref_number FROM `tblcirculars` WHERE id = ?');
        $refStmt->execute([$circularId]);
        $ref = (string) ($refStmt->fetchColumn() ?: '');
    } catch (Throwable $e) {
        $ref = '';
    }
    $stem = function_exists('correspondence_attachment_basename')
        ? correspondence_attachment_basename($ref)
        : '';
    if ($stem === '' || $stem === 'corr') {
        $stem = 'circular-' . $circularId;
    }
    $relDir = 'circulars/' . date('Y/m');
    $dir = dms_storage_root() . '/' . $relDir;
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException(__('circular.upload_dir'));
    }
    $safeName = correspondence_attachment_pick_name($dir, $stem, $ext);
    $dest = $dir . DIRECTORY_SEPARATOR . $safeName;
    if (!dms_move_uploaded_file((string) $file['tmp_name'], $dest)) {
        throw new RuntimeException(__('circular.upload_save'));
    }

    $relative = dms_storage_db_path($relDir . '/' . $safeName);
    db()->prepare(
        'INSERT INTO `tblcircular_files` (circular_id, file_name, file_path, file_type, file_size, uploaded_by)
         VALUES (?,?,?,?,?,?)'
    )->execute([$circularId, $safeName, $relative, $mime, (int) $file['size'], $userId]);
    log_activity($userId > 0 ? $userId : null, null, 'upload', $safeName, null, $circularId);

    return [
        'id' => (int) db()->lastInsertId(),
        'file_name' => $safeName,
        'file_path' => $relative,
    ];
}

/** @return list<array<string,mixed>> */
function circular_files_from_request(): array
{
    $out = [];
    if (!empty($_FILES['attachments']['name']) && is_array($_FILES['attachments']['name'])) {
        $count = count($_FILES['attachments']['name']);
        for ($i = 0; $i < $count; $i++) {
            if (($_FILES['attachments']['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $out[] = [
                'name' => $_FILES['attachments']['name'][$i],
                'type' => $_FILES['attachments']['type'][$i],
                'tmp_name' => $_FILES['attachments']['tmp_name'][$i],
                'error' => $_FILES['attachments']['error'][$i],
                'size' => $_FILES['attachments']['size'][$i],
            ];
        }
    } elseif (!empty($_FILES['attachment']['name'])) {
        $out[] = $_FILES['attachment'];
    }
    return $out;
}

function circular_upload_from_request(int $circularId, int $userId): int
{
    $uploaded = 0;
    foreach (circular_files_from_request() as $one) {
        if (upload_circular_file($one, $circularId, $userId)) {
            $uploaded++;
        }
    }
    return $uploaded;
}

function delete_circular_file(int $fileId): ?array
{
    ensure_circulars_schema();
    $stmt = db()->prepare('SELECT * FROM `tblcircular_files` WHERE id = ?');
    $stmt->execute([$fileId]);
    $file = $stmt->fetch();
    if (!$file) {
        return null;
    }
    $path = dms_storage_disk_path((string) $file['file_path']);
    if ($path !== '' && is_file($path)) {
        @unlink($path);
    }
    db()->prepare('DELETE FROM `tblcircular_files` WHERE id = ?')->execute([$fileId]);
    return $file;
}

function delete_circular(int $id): bool
{
    ensure_circulars_schema();
    $files = get_circular_files($id);
    foreach ($files as $f) {
        $path = dms_storage_disk_path((string) $f['file_path']);
        if ($path !== '' && is_file($path)) {
            @unlink($path);
        }
    }
    $stmt = db()->prepare('DELETE FROM `tblcirculars` WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->rowCount() > 0;
}
