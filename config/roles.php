<?php
/**
 * أدوار (أنواع) المستخدمين.
 * كل دور يرث سلوك أحد الأدوار الأساسية في الكود (base_role)، ويُحفظ في tblusers.role
 * حتى تبقى فحوص الصلاحيات الحالية كما هي؛ tblusers.role_id يحدد الدور المُسنَد فعلاً.
 */

/** @return array<string,array{name_ar:string,name_en:string,description:string,sort:int}> */
function dms_system_roles_seed(): array
{
    return [
        'admin' => [
            'name_ar' => 'مدير النظام',
            'name_en' => 'System admin',
            'description' => 'كل الصلاحيات: المستخدمون والأقسام والإعدادات وكل المعاملات.',
            'sort' => 10,
        ],
        'communications' => [
            'name_ar' => 'الاتصالات الإدارية',
            'name_en' => 'Administrative Communications',
            'description' => 'تسجيل الوارد والصادر والداخلي لأي قسم، والاطلاع على كل الأقسام.',
            'sort' => 20,
        ],
        'archive' => [
            'name_ar' => 'مشرف الأرشيف',
            'name_en' => 'Archive supervisor',
            'description' => 'تسجيل المعاملات لأي قسم، والحذف، وإعدادات الأرشيف والمسميات، والاطلاع على كل الأقسام.',
            'sort' => 30,
        ],
        'department' => [
            'name_ar' => 'مشرف القسم',
            'name_en' => 'Department supervisor',
            'description' => 'إنشاء المعاملات ومتابعتها ضمن أقسامه فقط.',
            'sort' => 40,
        ],
        'viewer' => [
            'name_ar' => 'مشاهد',
            'name_en' => 'Viewer',
            'description' => 'اطلاع فقط على كل الأقسام دون إنشاء أو تعديل.',
            'sort' => 70,
        ],
    ];
}

function ensure_roles_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        db()->exec(
            "CREATE TABLE IF NOT EXISTS `tblroles` (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(40) NOT NULL,
                base_role VARCHAR(20) NOT NULL,
                name_ar VARCHAR(100) NOT NULL,
                name_en VARCHAR(100) NULL DEFAULT NULL,
                description VARCHAR(500) NULL DEFAULT NULL,
                is_system TINYINT(1) NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                sort_order INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_roles_code (code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $ins = db()->prepare(
            'INSERT IGNORE INTO `tblroles` (code, base_role, name_ar, name_en, description, is_system, is_active, sort_order)
             VALUES (?, ?, ?, ?, ?, 1, 1, ?)'
        );
        foreach (dms_system_roles_seed() as $code => $r) {
            $ins->execute([$code, $code, $r['name_ar'], $r['name_en'], $r['description'], $r['sort']]);
        }
        dms_ensure_employee_system_role();
        dms_keep_viewer_role_last();
        if (!dms_db_column_exists('tblusers', 'role_id')) {
            db()->exec('ALTER TABLE `tblusers` ADD COLUMN `role_id` INT UNSIGNED NULL DEFAULT NULL AFTER `role`');
        }
        db()->exec(
            'UPDATE `tblusers` u
             JOIN `tblroles` r ON r.code = u.role AND r.is_system = 1
             SET u.role_id = r.id
             WHERE u.role_id IS NULL'
        );
    } catch (Throwable $e) {
        $done = false;
        error_log('DMS roles schema: ' . $e->getMessage());
    }
}

/** وصف دور الموظف النظامي. */
function dms_employee_role_description(): string
{
    return 'يطّلع على معاملات الأقسام المسندة إليه ويطبعها. الإنشاء والتعديل والإرسال والإقفال وغيرها حسب الصلاحيات الممنوحة للدور أو استثناءات المستخدم.';
}

/**
 * دور الموظف أساسي في النظام. أساسه نطاق الأقسام، وصلاحياته تُختار يدوياً ولا تُنسخ من مشرف القسم.
 */
function dms_ensure_employee_system_role(): void
{
    $desc = dms_employee_role_description();
    $stmt = db()->prepare('SELECT id FROM `tblroles` WHERE code = ? LIMIT 1');
    $stmt->execute(['employee']);
    $id = (int) $stmt->fetchColumn();
    if ($id > 0) {
        db()->prepare('UPDATE `tblroles` SET is_system = 1, is_active = 1, description = ? WHERE id = ?')
            ->execute([$desc, $id]);
        return;
    }
    db()->prepare(
        'INSERT INTO `tblroles` (code, base_role, name_ar, name_en, description, is_system, is_active, sort_order)
         VALUES (?, ?, ?, ?, ?, 1, 1, ?)'
    )->execute([
        'employee',
        'department',
        'موظف',
        'Employee',
        $desc,
        60,
    ]);
}

/** يبقى دور المشاهد آخر الأدوار في القوائم. */
function dms_keep_viewer_role_last(): void
{
    $maxOther = (int) db()->query("SELECT COALESCE(MAX(sort_order), 0) FROM `tblroles` WHERE code <> 'viewer'")->fetchColumn();
    db()->prepare('UPDATE `tblroles` SET sort_order = ? WHERE code = ? AND sort_order <= ?')
        ->execute([$maxOther + 10, 'viewer', $maxOther]);
}

/** @return list<array<string,mixed>> */
function dms_roles_list(bool $activeOnly = false): array
{
    ensure_roles_schema();
    try {
        $sql = 'SELECT * FROM `tblroles`' . ($activeOnly ? ' WHERE is_active = 1' : '') . " ORDER BY (code = 'viewer'), sort_order, id";
        return db()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

/** @return array<int,array<string,mixed>> */
function dms_roles_by_id(bool $refresh = false): array
{
    static $map = null;
    if ($map === null || $refresh) {
        $map = [];
        foreach (dms_roles_list() as $row) {
            $map[(int) $row['id']] = $row;
        }
    }
    return $map;
}

function dms_role_get(int $id): ?array
{
    return dms_roles_by_id()[$id] ?? null;
}

function dms_system_role_id(string $code): int
{
    foreach (dms_roles_by_id() as $id => $row) {
        if ((int) $row['is_system'] === 1 && (string) $row['code'] === $code) {
            return $id;
        }
    }
    return 0;
}

function dms_role_display_name(array $role): string
{
    $en = trim((string) ($role['name_en'] ?? ''));
    if (function_exists('dms_lang_code') && dms_lang_code() === 'en' && $en !== '') {
        return $en;
    }
    return trim((string) ($role['name_ar'] ?? '')) ?: $en;
}

/** اسم دور المستخدم المُسنَد، وإلا اسم الدور الأساسي */
function user_role_label(array $user): string
{
    $roleId = (int) ($user['role_id'] ?? 0);
    if ($roleId > 0 && ($role = dms_role_get($roleId))) {
        return dms_role_display_name($role);
    }
    return role_label((string) ($user['role'] ?? ''));
}

/** رقم الدور المُسنَد للمستخدم (مع الرجوع للدور الأساسي للسجلات القديمة) */
function user_role_id(array $user): int
{
    $roleId = (int) ($user['role_id'] ?? 0);
    if ($roleId > 0 && dms_role_get($roleId)) {
        return $roleId;
    }
    return dms_system_role_id((string) ($user['role'] ?? ''));
}

/** @return array<int,int> role_id => عدد المستخدمين */
function dms_roles_user_counts(): array
{
    ensure_roles_schema();
    $out = [];
    try {
        foreach (db()->query('SELECT role_id, COUNT(*) AS n FROM `tblusers` WHERE role_id IS NOT NULL GROUP BY role_id') as $row) {
            $out[(int) $row['role_id']] = (int) $row['n'];
        }
    } catch (Throwable $e) {
        // تجاهل
    }
    return $out;
}

/**
 * الأدوار المتاحة للاختيار في نموذج المستخدم: النشطة + الدور الحالي حتى لو عُطّل.
 *
 * @return list<array<string,mixed>>
 */
function dms_roles_for_user_form(int $currentRoleId = 0): array
{
    $out = [];
    foreach (dms_roles_list() as $row) {
        if ((int) $row['is_active'] === 1 || (int) $row['id'] === $currentRoleId) {
            $out[] = $row;
        }
    }
    return $out;
}

/** إسناد قسم إجباري لدور «مشرف القسم» النظامي فقط. */
function dms_role_requires_assigned_department(?array $choice): bool
{
    if (!$choice) {
        return false;
    }
    $role = dms_role_get((int) ($choice['id'] ?? 0));
    return is_array($role)
        && (int) ($role['is_system'] ?? 0) === 1
        && (string) ($role['code'] ?? '') === 'department';
}

/**
 * يتحقق من الدور المختار في نموذج المستخدم.
 *
 * @return array{id:int,base_role:string}|null
 */
function dms_role_resolve_choice(int $roleId, int $currentRoleId = 0): ?array
{
    $role = $roleId > 0 ? dms_role_get($roleId) : null;
    if (!$role) {
        return null;
    }
    if ((int) $role['is_active'] !== 1 && $roleId !== $currentRoleId) {
        return null;
    }
    $base = (string) $role['base_role'];
    if (!in_array($base, dms_user_roles(), true)) {
        return null;
    }
    return ['id' => $roleId, 'base_role' => $base];
}

/**
 * حفظ دور جديد أو تعديل دور.
 *
 * @param array{name_ar?:string,name_en?:string,description?:string,base_role?:string,is_active?:int|bool,sort_order?:int|string} $data
 */
function dms_role_save(array $data, ?int $id, array $actor): int
{
    ensure_roles_schema();
    $existing = $id ? dms_role_get($id) : null;
    if ($id && !$existing) {
        throw new InvalidArgumentException(__('roles.not_found'));
    }
    $nameAr = mb_substr(trim((string) ($data['name_ar'] ?? '')), 0, 100);
    $nameEn = mb_substr(trim((string) ($data['name_en'] ?? '')), 0, 100);
    $description = mb_substr(trim((string) ($data['description'] ?? '')), 0, 500);
    $isSystem = $existing && (int) $existing['is_system'] === 1;
    $baseRole = $existing ? (string) $existing['base_role'] : 'department';
    $isActive = $isSystem ? 1 : (!empty($data['is_active']) ? 1 : 0);
    $sort = (int) ($data['sort_order'] ?? 0);

    if ($nameAr === '') {
        throw new InvalidArgumentException(__('roles.name_required'));
    }
    if (!in_array($baseRole, dms_user_roles(), true)) {
        throw new InvalidArgumentException(__('roles.base_required'));
    }
    $dup = db()->prepare('SELECT id FROM `tblroles` WHERE name_ar = ? AND id <> ? LIMIT 1');
    $dup->execute([$nameAr, (int) $id]);
    if ($dup->fetchColumn()) {
        throw new InvalidArgumentException(__('roles.name_taken'));
    }

    if ($existing) {
        db()->prepare(
            'UPDATE `tblroles` SET name_ar = ?, name_en = ?, description = ?, base_role = ?, is_active = ?, sort_order = ? WHERE id = ?'
        )->execute([$nameAr, $nameEn !== '' ? $nameEn : null, $description !== '' ? $description : null, $baseRole, $isActive, $sort, $id]);
        if ((string) $existing['base_role'] !== $baseRole) {
            db()->prepare('UPDATE `tblusers` SET role = ? WHERE role_id = ?')->execute([$baseRole, $id]);
        }
        $roleId = (int) $id;
        log_activity((int) $actor['id'], null, 'role_update', str_replace(':name', $nameAr, __('roles.log_update')));
    } else {
        $code = 'r_' . bin2hex(random_bytes(5));
        if ($sort <= 0) {
            $sort = (int) db()->query("SELECT COALESCE(MAX(sort_order), 0) FROM `tblroles` WHERE code <> 'viewer'")->fetchColumn() + 10;
        }
        db()->prepare(
            'INSERT INTO `tblroles` (code, base_role, name_ar, name_en, description, is_system, is_active, sort_order)
             VALUES (?, ?, ?, ?, ?, 0, ?, ?)'
        )->execute([$code, $baseRole, $nameAr, $nameEn !== '' ? $nameEn : null, $description !== '' ? $description : null, $isActive, $sort]);
        $roleId = (int) db()->lastInsertId();
        log_activity((int) $actor['id'], null, 'role_create', str_replace(':name', $nameAr, __('roles.log_create')));
    }
    dms_roles_by_id(true);
    return $roleId;
}

function dms_role_delete(int $id, array $actor): void
{
    $role = dms_role_get($id);
    if (!$role) {
        throw new InvalidArgumentException(__('roles.not_found'));
    }
    if ((int) $role['is_system'] === 1) {
        throw new InvalidArgumentException(__('roles.cannot_delete_system'));
    }
    if ((dms_roles_user_counts()[$id] ?? 0) > 0) {
        throw new InvalidArgumentException(__('roles.cannot_delete_used'));
    }
    db()->prepare('DELETE FROM `tblroles` WHERE id = ? AND is_system = 0')->execute([$id]);
    if (function_exists('ensure_permissions_schema')) {
        ensure_permissions_schema();
        db()->prepare('DELETE FROM `tblrole_permissions` WHERE role_id = ?')->execute([$id]);
    }
    dms_roles_by_id(true);
    log_activity((int) $actor['id'], null, 'role_delete', str_replace(':name', (string) $role['name_ar'], __('roles.log_delete')));
}
