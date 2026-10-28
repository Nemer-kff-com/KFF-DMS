<?php
/**
 * إسناد المستخدم إلى قسم أو أكثر.
 * tblusers.department_id = قسم الموظف (الرئيسي) ولا يمنح الإسناد.
 * tbluser_departments = الأقسام المسندة فقط.
 */

function ensure_user_departments_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $pdo = db();
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `tbluser_departments` (
                user_id INT UNSIGNED NOT NULL,
                department_id INT UNSIGNED NOT NULL,
                is_primary TINYINT(1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (user_id, department_id),
                INDEX idx_ud_dept (department_id),
                INDEX idx_ud_primary (user_id, is_primary)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        try {
            $pdo->exec(
                'ALTER TABLE `tbluser_departments`
                 ADD CONSTRAINT `fk_ud_user` FOREIGN KEY (`user_id`) REFERENCES `tblusers`(`id`) ON DELETE CASCADE'
            );
        } catch (Throwable $e) {
            // القيد موجود أو القاعدة غير جاهزة
        }
        try {
            $pdo->exec(
                'ALTER TABLE `tbluser_departments`
                 ADD CONSTRAINT `fk_ud_dept` FOREIGN KEY (`department_id`) REFERENCES `tbldepartments`(`id`) ON DELETE CASCADE'
            );
        } catch (Throwable $e) {
            // القيد موجود
        }
    } catch (Throwable $e) {
        error_log('DMS user departments schema: ' . $e->getMessage());
    }
}

/** @return list<int> */
function dms_normalize_id_list(array $ids): array
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

/**
 * @param list<int> $ids
 * @return array{0:string,1:list<int>}
 */
function dms_sql_in(string $column, array $ids): array
{
    $ids = dms_normalize_id_list($ids);
    if (!$ids) {
        return ['1=0', []];
    }
    $ph = implode(',', array_fill(0, count($ids), '?'));
    return ["{$column} IN ({$ph})", $ids];
}

/** هل يُقيَّد المستخدم بأقسام مسندة (وليس مديراً/أرشيفاً/ مشاهداً عاماً)؟ */
function user_needs_department_scope(?array $user = null): bool
{
    $user = $user ?? (function_exists('current_user') ? current_user() : null);
    if (!$user) {
        return false;
    }
    if (function_exists('can_view_all_departments') && can_view_all_departments($user)) {
        return false;
    }
    return true;
}

/** @return list<int> */
function user_department_ids(?array $user = null): array
{
    $user = $user ?? (function_exists('current_user') ? current_user() : null);
    if (!$user) {
        return [];
    }
    if (isset($user['department_ids']) && is_array($user['department_ids'])) {
        return dms_normalize_id_list($user['department_ids']);
    }
    $userId = (int) ($user['id'] ?? 0);
    $loaded = [];
    if ($userId > 0) {
        $loaded = user_departments_load($userId);
        if (function_exists('user_coverage_assign_department_ids')) {
            $loaded = array_values(array_unique(array_merge(
                $loaded,
                user_coverage_assign_department_ids($userId)
            )));
        }
        return $loaded;
    }
    $primary = (int) ($user['department_id'] ?? 0);
    return $primary > 0 ? [$primary] : [];
}

function user_primary_department_id(?array $user = null): int
{
    $user = $user ?? (function_exists('current_user') ? current_user() : null);
    if (!$user) {
        return 0;
    }
    $primary = (int) ($user['department_id'] ?? 0);
    if ($primary > 0) {
        return $primary;
    }
    $permanent = user_departments_load((int) ($user['id'] ?? 0));
    return $permanent ? (int) $permanent[0] : 0;
}

function user_can_access_department(int $departmentId, ?array $user = null): bool
{
    if ($departmentId <= 0) {
        return false;
    }
    $user = $user ?? (function_exists('current_user') ? current_user() : null);
    if (!$user) {
        return false;
    }
    if (!user_needs_department_scope($user)) {
        return true;
    }
    return in_array($departmentId, user_department_ids($user), true);
}

function user_has_assigned_departments(?array $user = null): bool
{
    return user_department_ids($user) !== [];
}

/** @return list<int> */
function user_departments_load(int $userId): array
{
    if ($userId <= 0) {
        return [];
    }
    ensure_user_departments_schema();
    try {
        $stmt = db()->prepare(
            'SELECT department_id FROM `tbluser_departments` WHERE user_id = ? ORDER BY is_primary DESC, department_id'
        );
        $stmt->execute([$userId]);
        return dms_normalize_id_list($stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    } catch (Throwable $e) {
        return [];
    }
}

function attach_user_department_scope(array $user): array
{
    $ids = user_departments_load((int) ($user['id'] ?? 0));
    if (function_exists('user_coverage_assign_department_ids')) {
        $ids = array_values(array_unique(array_merge(
            $ids,
            user_coverage_assign_department_ids((int) ($user['id'] ?? 0))
        )));
    }
    $user['department_ids'] = $ids;
    if ((int) ($user['department_id'] ?? 0) <= 0 && $ids) {
        $user['department_id'] = $ids[0];
    }
    return $user;
}

function save_user_departments(int $userId, array $departmentIds, ?int $primaryId = null): void
{
    ensure_user_departments_schema();
    $ids = dms_normalize_id_list($departmentIds);
    $primaryId = (int) ($primaryId ?? 0);
    if ($primaryId <= 0) {
        $primaryId = $ids[0] ?? 0;
    }
    $pdo = db();
    $pdo->prepare('DELETE FROM `tbluser_departments` WHERE user_id = ?')->execute([$userId]);
    if ($ids) {
        $ins = $pdo->prepare(
            'INSERT INTO `tbluser_departments` (user_id, department_id, is_primary) VALUES (?, ?, ?)'
        );
        foreach ($ids as $did) {
            $ins->execute([$userId, $did, $did === $primaryId ? 1 : 0]);
        }
    }
    $pdo->prepare('UPDATE `tblusers` SET department_id = ? WHERE id = ?')
        ->execute([$primaryId > 0 ? $primaryId : null, $userId]);
    if ($primaryId > 0 && in_array($primaryId, $ids, true) && function_exists('department_apply_missing_principal')) {
        department_apply_missing_principal($primaryId);
    }
}

/**
 * @return array{ids:list<int>,primary:?int}
 */
function user_departments_from_request(array $post, array $departments): array
{
    $allowed = [];
    foreach ($departments as $row) {
        $id = (int) ($row['id'] ?? 0);
        if ($id > 0) {
            $allowed[$id] = true;
        }
    }
    $ids = [];
    foreach ((array) ($post['department_ids'] ?? []) as $raw) {
        $id = (int) $raw;
        if ($id > 0 && isset($allowed[$id])) {
            $ids[$id] = $id;
        }
    }
    $ids = array_values($ids);
    $primary = (int) ($post['primary_department_id'] ?? $post['department_id'] ?? 0);
    if ($primary > 0 && !isset($allowed[$primary])) {
        $primary = 0;
    }
    if ($primary <= 0 && $ids) {
        $primary = $ids[0];
    }
    return ['ids' => $ids, 'primary' => $primary > 0 ? $primary : null];
}

/**
 * القسم المراد حفظه على السجل: المختار إن كان مسموحاً، وإلا الأساسي.
 */
function resolve_assigned_department_id(?array $user, $postedId, bool $canChooseAll = false): ?int
{
    $postedId = (int) $postedId;
    if ($canChooseAll) {
        return $postedId > 0 ? $postedId : null;
    }
    $ids = user_department_ids($user);
    if ($postedId > 0 && in_array($postedId, $ids, true)) {
        return $postedId;
    }
    $primary = user_primary_department_id($user);
    return $primary > 0 ? $primary : null;
}

/**
 * أقسام يظهرها المستخدم في القوائم (الكل أو المسندة فقط).
 *
 * @param list<array> $allDepartments
 * @return list<array>
 */
function departments_visible_to_user(?array $user, array $allDepartments): array
{
    if (!$user || !user_needs_department_scope($user)) {
        return $allDepartments;
    }
    $allowed = array_fill_keys(user_department_ids($user), true);
    if (!$allowed) {
        return [];
    }
    $out = [];
    foreach ($allDepartments as $row) {
        if (isset($allowed[(int) ($row['id'] ?? 0)])) {
            $out[] = $row;
        }
    }
    return $out;
}

/**
 * @param list<int> $userIds
 * @return array<int,list<array{id:int,name:string,name_en:string,is_primary:int}>>
 */
function users_departments_map(array $userIds): array
{
    $userIds = dms_normalize_id_list($userIds);
    if (!$userIds) {
        return [];
    }
    ensure_user_departments_schema();
    [$in, $params] = dms_sql_in('ud.user_id', $userIds);
    try {
        $stmt = db()->prepare(
            "SELECT ud.user_id, ud.department_id, ud.is_primary, d.name, d.name_en
             FROM `tbluser_departments` ud
             INNER JOIN `tbldepartments` d ON d.id = ud.department_id
             WHERE {$in}
             ORDER BY ud.is_primary DESC, d.sort_order, d.name, d.id"
        );
        $stmt->execute($params);
        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $uid = (int) $row['user_id'];
            $map[$uid][] = [
                'id' => (int) $row['department_id'],
                'name' => (string) ($row['name'] ?? ''),
                'name_en' => (string) ($row['name_en'] ?? ''),
                'is_primary' => (int) ($row['is_primary'] ?? 0),
            ];
        }
        return $map;
    } catch (Throwable $e) {
        return [];
    }
}
