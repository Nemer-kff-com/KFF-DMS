<?php
/**
 * صلاحيات الأدوار — القائمة معرّفة هنا لأن كل صلاحية تحتاج فحصاً في الكود.
 * defaults: الأدوار الأساسية التي تملكها اليوم (تُعبّأ منها الأدوار أول مرة فلا يتغير السلوك).
 * enforced: هل يعتمد عليها النظام فعلاً؛ غير المطبّقة تُحفظ فقط حتى تُربط بصفحاتها.
 * bases: الأدوار الأساسية التي يجوز منحها لها (الافتراضي defaults) — لأن فحوصاً داخلية في تلك الصفحات ما زالت بالدور الأساسي؛
 * ['admin'] وحدها = خاصة بمدير النظام ولا تُمنح لغيره.
 */

/** @return array<string,array{label:string,icon:string,items:array<string,array{ar:string,en:string,defaults:list<string>,enforced:bool,bases:list<string>}>}> */
function dms_permission_catalog(): array
{
    $edit = ['admin', 'communications', 'archive', 'department'];
    $printRoles = ['admin', 'communications', 'archive', 'department', 'viewer'];
    $adminArchive = ['admin', 'archive'];
    $admin = ['admin'];
    $p = static fn(string $ar, string $en, array $defaults, bool $enforced = false, ?array $bases = null): array
        => ['ar' => $ar, 'en' => $en, 'defaults' => $defaults, 'enforced' => $enforced, 'bases' => $bases ?? $defaults];

    return [
        'outgoing' => ['label' => 'perm.group_outgoing', 'icon' => 'bi-send', 'items' => [
            'outgoing.create' => $p('صادر جديد', 'New outgoing', $edit, true),
            'outgoing.bulk' => $p('إصدار جماعي', 'Bulk issue', $edit, true),
            'outgoing.edit' => $p('تعديل الصادر', 'Edit outgoing', $edit, true),
            'outgoing.cancel' => $p('إلغاء الصادر', 'Cancel outgoing', $edit, true),
            'outgoing.send' => $p('الإرسال بالبريد وواتساب', 'Send by email & WhatsApp', $edit, true),
            'outgoing.receipt' => $p('التسليم وحالة الصادر', 'Delivery & outgoing status', $edit, true),
            'outgoing.print_form' => $p('طباعة نموذج التسليم', 'Print delivery form', ['admin', 'communications'], true, ['admin', 'communications', 'archive', 'department', 'viewer']),
            'outgoing.print_envelope' => $p('طباعة ملصق الظرف', 'Print envelope label', ['admin', 'communications'], true, ['admin', 'communications', 'archive', 'department', 'viewer']),
            'outgoing.print_thermal' => $p('طباعة الملصق الحراري', 'Print thermal sticker', ['admin', 'communications'], true, ['admin', 'communications', 'archive', 'department', 'viewer']),
            'view.confidential.outgoing' => $p('الاطلاع على المرفقات السرية', 'View confidential attachments', [], true, $printRoles),
        ]],
        'incoming' => ['label' => 'perm.group_incoming', 'icon' => 'bi-inbox', 'items' => [
            'incoming.create' => $p('وارد جديد', 'New incoming', $edit, true),
            'incoming.edit' => $p('تعديل الوارد وحالته', 'Edit incoming & status', $edit, true),
            'incoming.print_form' => $p('طباعة نموذج الاستلام', 'Print receipt form', $printRoles, true, $printRoles),
            'incoming.print_envelope' => $p('طباعة ملصق الظرف', 'Print envelope label', $printRoles, true, $printRoles),
            'incoming.print_thermal' => $p('طباعة الملصق الحراري', 'Print thermal sticker', $printRoles, true, $printRoles),
            'view.confidential.incoming' => $p('الاطلاع على المرفقات السرية', 'View confidential attachments', [], true, $printRoles),
        ]],
        'internal' => ['label' => 'perm.group_internal', 'icon' => 'bi-building-gear', 'items' => [
            'internal.create' => $p('داخلي جديد', 'New internal', $edit, true),
            'internal.edit' => $p('تغيير حالة الداخلي', 'Change internal status', $edit, true),
            'internal.print_form' => $p('طباعة نموذج التسليم', 'Print delivery form', $printRoles, true, $printRoles),
            'internal.print_envelope' => $p('طباعة ملصق الظرف', 'Print envelope label', $printRoles, true, $printRoles),
            'internal.print_thermal' => $p('طباعة الملصق الحراري', 'Print thermal sticker', $printRoles, true, $printRoles),
            'view.confidential.internal' => $p('الاطلاع على المرفقات السرية', 'View confidential attachments', [], true, $printRoles),
        ]],
        'workflow' => ['label' => 'perm.group_workflow', 'icon' => 'bi-diagram-3', 'items' => [
            'referrals.manage' => $p('الإحالة واستلامها', 'Refer & receive referrals', $edit, true),
            'correspondence.close' => $p('إقفال المعاملات وإعادة فتحها', 'Close & reopen items', $edit, true),
            'comments.add' => $p('إضافة الملاحظات', 'Add notes', $edit, true),
            'documents.upload' => $p('رفع المرفقات', 'Upload attachments', $edit, true),
            'documents.delete' => $p('حذف المرفقات', 'Delete attachments', $adminArchive, true),
            'corr.any_department' => $p('التسجيل لأي قسم', 'Register for any department', ['admin', 'communications', 'archive'], true),
            'view.all_departments' => $p('الاطلاع على كل الأقسام', 'View all departments', ['admin', 'communications', 'archive', 'viewer'], true),
            'mail.draft' => $p('الإرسال من برنامج البريد (Outlook)', 'Send from mail app (Outlook)', [], true, ['admin', 'communications', 'archive', 'department', 'viewer']),
        ]],
        'circulars' => ['label' => 'perm.group_circulars', 'icon' => 'bi-megaphone', 'items' => [
            'circulars.create' => $p('تعميم جديد', 'New circular', $adminArchive, true, $edit),
            'circulars.issue' => $p('إصدار التعميم', 'Issue circular', $adminArchive, true, $edit),
            'circulars.edit' => $p('تعديل التعاميم ومرفقاتها', 'Edit circulars & files', $adminArchive, true, $edit),
            'circulars.print_form' => $p('طباعة التعميم', 'Print circular', $printRoles, true, $printRoles),
            'circulars.print_thermal' => $p('طباعة الملصق الحراري', 'Print thermal sticker', $printRoles, true, $printRoles),
        ]],
        'archive' => ['label' => 'perm.group_archive', 'icon' => 'bi-archive', 'items' => [
            'archive.scholarships.view' => $p('المنح — عرض', 'Scholarships — view', ['admin', 'communications', 'archive', 'department', 'viewer'], true),
            'archive.scholarships.create' => $p('المنح — إضافة', 'Scholarships — add', $edit, true),
            'archive.scholarships.edit' => $p('المنح — تعديل', 'Scholarships — edit', $edit, true),
            'archive.scholarships.delete' => $p('المنح — حذف', 'Scholarships — delete', $adminArchive, true, $edit),
            'archive.projects.view' => $p('المشاريع — عرض', 'Projects — view', ['admin', 'communications', 'archive', 'department', 'viewer'], true),
            'archive.projects.create' => $p('المشاريع — إضافة', 'Projects — add', $edit, true),
            'archive.projects.edit' => $p('المشاريع — تعديل', 'Projects — edit', $edit, true),
            'archive.projects.delete' => $p('المشاريع — حذف', 'Projects — delete', $adminArchive, true, $edit),
            'archive.finance.view' => $p('المالية — عرض', 'Finance — view', ['admin', 'communications', 'archive', 'department', 'viewer'], true),
            'archive.finance.create' => $p('المالية — إضافة', 'Finance — add', $edit, true),
            'archive.finance.edit' => $p('المالية — تعديل', 'Finance — edit', $edit, true),
            'archive.finance.delete' => $p('المالية — حذف', 'Finance — delete', $adminArchive, true, $edit),
            'archive.general.view' => $p('الأرشيف العام — عرض', 'General archive — view', ['admin', 'communications', 'archive', 'department', 'viewer'], true),
            'archive.general.create' => $p('الأرشيف العام — إضافة', 'General archive — add', $edit, true),
            'archive.general.edit' => $p('الأرشيف العام — تعديل', 'General archive — edit', $edit, true),
            'archive.general.delete' => $p('الأرشيف العام — حذف', 'General archive — delete', $adminArchive, true, $edit),
            'view.confidential.scholarships' => $p('أرشيف المنح — الاطلاع على المرفقات السرية', 'Scholarships archive — view confidential attachments', [], true, $printRoles),
            'view.confidential.projects' => $p('أرشيف المشاريع — الاطلاع على المرفقات السرية', 'Projects archive — view confidential attachments', [], true, $printRoles),
            'view.confidential.finance' => $p('أرشيف المالية — الاطلاع على المرفقات السرية', 'Finance archive — view confidential attachments', [], true, $printRoles),
            'view.confidential.general' => $p('أرشيف الوثائق العامة — الاطلاع على المرفقات السرية', 'General archive — view confidential attachments', [], true, $printRoles),
        ]],
        'reference' => ['label' => 'perm.group_reference', 'icon' => 'bi-journal-bookmark', 'items' => [
            'templates.edit' => $p('تعديل القوالب', 'Edit templates', $edit, true),
            'entities.manage' => $p('إدارة الجهات', 'Manage contacts', ['admin', 'archive', 'department'], true, ['admin', 'communications', 'archive', 'department']),
            'entity_groups.manage' => $p('مجموعات الجهات', 'Contact groups', ['admin', 'archive', 'department'], true, ['admin', 'communications', 'archive', 'department']),
            'lookups.manage' => $p('مسميات القوائم', 'List labels', $adminArchive, true, $edit),
            'import.data' => $p('استيراد البيانات', 'Data import', $adminArchive, true, $edit),
        ]],
        'admin' => ['label' => 'perm.group_admin', 'icon' => 'bi-shield-lock', 'items' => [
            'admin.users' => $p('المستخدمون والأدوار والصلاحيات', 'Users, roles & permissions', $admin, true),
            'admin.departments' => $p('الأقسام', 'Departments', $admin, true, $edit),
            'admin.settings' => $p('الإعدادات العامة والبريد وواتساب', 'Settings, email & WhatsApp', $admin, true, $edit),
            'admin.sidebar' => $p('القائمة الجانبية', 'Sidebar menu', $admin, true, $edit),
            'admin.logs' => $p('السجلات والجلسات', 'Logs & sessions', $admin, true, $edit),
            'admin.backup' => $p('النسخ الاحتياطي', 'Backup', $admin, true),
            'admin.reset' => $p('إعادة ضبط النظام', 'System reset', $admin, true),
        ]],
    ];
}

/** @return array<string,array{ar:string,en:string,defaults:list<string>,enforced:bool,group:string}> */
function dms_permission_items(): array
{
    static $flat = null;
    if ($flat === null) {
        $flat = [];
        foreach (dms_permission_catalog() as $groupKey => $group) {
            foreach ($group['items'] as $code => $item) {
                $flat[$code] = $item + ['group' => $groupKey];
            }
        }
    }
    return $flat;
}

function dms_permission_label(string $code): string
{
    $item = dms_permission_items()[$code] ?? null;
    if (!$item) {
        return $code;
    }
    return (function_exists('dms_lang_code') && dms_lang_code() === 'en') ? $item['en'] : $item['ar'];
}

function ensure_permissions_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    ensure_roles_schema();
    try {
        db()->exec(
            "CREATE TABLE IF NOT EXISTS `tblrole_permissions` (
                role_id INT UNSIGNED NOT NULL,
                perm_code VARCHAR(60) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (role_id, perm_code),
                KEY idx_role_perm_code (perm_code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        dms_permissions_seed_new_codes();
        dms_permissions_split_view_confidential();
        db()->exec("DELETE FROM `tblrole_permissions` WHERE perm_code = 'outgoing.print_dispatch'");
        ensure_user_permissions_schema();
        db()->exec("DELETE FROM `tbluser_permissions` WHERE perm_code = 'outgoing.print_dispatch'");
    } catch (Throwable $e) {
        $done = false;
        error_log('DMS permissions schema: ' . $e->getMessage());
    }
}

/**
 * يمنح كل صلاحية جديدة في القائمة للأدوار التي يملكها دورها الأساسي اليوم — مرة واحدة لكل صلاحية،
 * حتى لا تُعاد صلاحية نزعها المدير لاحقاً.
 */
function dms_permissions_seed_new_codes(): void
{
    $seeded = json_decode((string) setting('perm_catalog_seeded', '[]'), true);
    $seeded = is_array($seeded) ? $seeded : [];
    $new = array_diff(array_keys(dms_permission_items()), $seeded);
    if ($new === []) {
        return;
    }
    $roles = db()->query('SELECT id, code, base_role, is_system FROM `tblroles`')->fetchAll(PDO::FETCH_ASSOC);
    $ins = db()->prepare('INSERT IGNORE INTO `tblrole_permissions` (role_id, perm_code) VALUES (?, ?)');
    $have = [];
    try {
        foreach (db()->query('SELECT role_id, perm_code FROM `tblrole_permissions`') as $row) {
            $have[(int) $row['role_id']][(string) $row['perm_code']] = true;
        }
    } catch (Throwable $e) {
        $have = [];
    }
    foreach ($new as $code) {
        $defaults = dms_permission_items()[$code]['defaults'];
        $legacy = preg_match('/^archive\.(?:scholarships|projects|finance|general)\.(view|create|edit|delete)$/', $code, $m)
            ? 'archive.' . $m[1]
            : '';
        foreach ($roles as $role) {
            if ((int) ($role['is_system'] ?? 0) !== 1 || (string) ($role['code'] ?? '') === 'employee') {
                continue;
            }
            $roleId = (int) $role['id'];
            $byBase = in_array((string) $role['base_role'], $defaults, true);
            if ($legacy !== '') {
                $hadOld = !empty($have[$roleId]['archive.view'])
                    || !empty($have[$roleId]['archive.create'])
                    || !empty($have[$roleId]['archive.edit'])
                    || !empty($have[$roleId]['archive.delete']);
                if ($hadOld) {
                    if (!empty($have[$roleId][$legacy])) {
                        $ins->execute([$roleId, $code]);
                    }
                    continue;
                }
            }
            if ($byBase) {
                $ins->execute([$roleId, $code]);
            }
        }
    }
    set_setting('perm_catalog_seeded', json_encode(array_values(array_merge($seeded, $new))), 'صلاحيات عُبّئت للأدوار (داخلي)');
}

/** @return list<string> */
function dms_view_confidential_perm_codes(): array
{
    return [
        'view.confidential.outgoing',
        'view.confidential.incoming',
        'view.confidential.internal',
        'view.confidential.scholarships',
        'view.confidential.projects',
        'view.confidential.finance',
        'view.confidential.general',
    ];
}

/** ينقل صلاحية السرية الموحّدة إلى صلاحية لكل بند، مرة واحدة. */
function dms_permissions_split_view_confidential(): void
{
    if ((string) setting('perm_view_confidential_split', '') === '1') {
        return;
    }
    $codes = dms_view_confidential_perm_codes();
    $ins = db()->prepare('INSERT IGNORE INTO `tblrole_permissions` (role_id, perm_code) VALUES (?, ?)');
    $roles = db()->query("SELECT role_id FROM `tblrole_permissions` WHERE perm_code = 'view.confidential'")->fetchAll(PDO::FETCH_COLUMN) ?: [];
    foreach ($roles as $roleId) {
        foreach ($codes as $code) {
            $ins->execute([(int) $roleId, $code]);
        }
    }
    db()->exec("DELETE FROM `tblrole_permissions` WHERE perm_code = 'view.confidential'");
    if (function_exists('ensure_user_permissions_schema')) {
        ensure_user_permissions_schema();
        $userIns = db()->prepare('INSERT IGNORE INTO `tbluser_permissions` (user_id, perm_code, effect, created_by) VALUES (?, ?, ?, NULL)');
        $overrides = db()->query("SELECT user_id, effect FROM `tbluser_permissions` WHERE perm_code = 'view.confidential'")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($overrides as $row) {
            foreach ($codes as $code) {
                $userIns->execute([(int) $row['user_id'], $code, (string) $row['effect']]);
            }
        }
        db()->exec("DELETE FROM `tbluser_permissions` WHERE perm_code = 'view.confidential'");
    }
    set_setting('perm_view_confidential_split', '1', 'فصل صلاحية المرفقات السرية لكل بند (داخلي)');
}

/** يعبّئ صلاحيات دور جديد من دوره الأساسي */
function dms_role_permissions_seed_from_base(int $roleId, string $baseRole): void
{
    ensure_permissions_schema();
    $ins = db()->prepare('INSERT IGNORE INTO `tblrole_permissions` (role_id, perm_code) VALUES (?, ?)');
    foreach (dms_permission_items() as $code => $item) {
        if (in_array($baseRole, $item['defaults'], true)) {
            $ins->execute([$roleId, $code]);
        }
    }
}

/** @return array<int,array<string,true>> role_id => [perm_code => true] */
function dms_role_permissions_map(bool $refresh = false): array
{
    static $map = null;
    if ($map !== null && !$refresh) {
        return $map;
    }
    ensure_permissions_schema();
    $map = [];
    try {
        foreach (db()->query('SELECT role_id, perm_code FROM `tblrole_permissions`') as $row) {
            $map[(int) $row['role_id']][(string) $row['perm_code']] = true;
        }
    } catch (Throwable $e) {
        // تجاهل
    }
    return $map;
}

/** يحفظ صلاحيات دور واحد دون المساس بباقي الأدوار. @param list<mixed> $codes */
function dms_role_permissions_save_one(int $roleId, array $codes, array $actor): int
{
    ensure_permissions_schema();
    $role = function_exists('dms_role_get') ? dms_role_get($roleId) : null;
    if (!$role || dms_role_is_admin($role)) {
        return 0;
    }
    $base = (string) ($role['base_role'] ?? '');
    $valid = dms_permission_items();
    $want = [];
    foreach ($codes as $code) {
        $code = (string) $code;
        if (isset($valid[$code]) && dms_role_can_grant_perm($role, $code)) {
            $want[$code] = true;
        }
    }
    $current = dms_role_permissions_map(true)[$roleId] ?? [];
    $del = db()->prepare('DELETE FROM `tblrole_permissions` WHERE role_id = ? AND perm_code = ?');
    $ins = db()->prepare('INSERT IGNORE INTO `tblrole_permissions` (role_id, perm_code) VALUES (?, ?)');
    $added = [];
    $removed = [];
    foreach ($valid as $code => $item) {
        if (!dms_role_can_grant_perm($role, $code)) {
            continue;
        }
        $has = isset($current[$code]);
        $need = isset($want[$code]);
        if ($has === $need) {
            continue;
        }
        if ($need) {
            $ins->execute([$roleId, $code]);
            $added[] = $code;
        } else {
            $del->execute([$roleId, $code]);
            $removed[] = $code;
        }
    }
    dms_role_permissions_map(true);
    $count = count($added) + count($removed);
    if ($count > 0 && function_exists('log_activity')) {
        $parts = [];
        if ($added) {
            $parts[] = '+ ' . implode('، ', array_map('dms_permission_label', $added));
        }
        if ($removed) {
            $parts[] = '− ' . implode('، ', array_map('dms_permission_label', $removed));
        }
        log_activity(
            (int) ($actor['id'] ?? 0),
            null,
            'role_permissions',
            str_replace(
                [':role', ':changes'],
                [function_exists('dms_role_display_name') ? dms_role_display_name($role) : (string) $roleId, implode(' | ', $parts)],
                __('perm.log_update')
            )
        );
    }
    return $count;
}

function dms_role_is_admin(array $role): bool
{
    return (string) ($role['base_role'] ?? '') === 'admin' && (int) ($role['is_system'] ?? 0) === 1;
}

/** الدور المضاف يُمنح أي صلاحية ما عدا صلاحيات مدير النظام. الأدوار النظامية تبقى على أساسها. */
function dms_role_can_grant_perm(array $role, string $code): bool
{
    if (dms_role_is_admin($role)) {
        return true;
    }
    if ((int) ($role['is_system'] ?? 0) !== 1) {
        return !dms_permission_admin_only($code);
    }
    return dms_permission_allowed_for_base($code, (string) ($role['base_role'] ?? ''));
}

function dms_user_perm_applicable(array $user, string $code): bool
{
    if ((string) ($user['role'] ?? '') === 'admin') {
        return true;
    }
    $roleId = function_exists('user_role_id') ? user_role_id($user) : 0;
    $role = ($roleId > 0 && function_exists('dms_role_get')) ? dms_role_get($roleId) : null;
    if (is_array($role)) {
        return dms_role_can_grant_perm($role, $code);
    }
    return dms_permission_allowed_for_base($code, (string) ($user['role'] ?? ''));
}

/** هل يمكن منح الصلاحية لدور بهذا الأساس؟ */
function dms_permission_allowed_for_base(string $code, string $baseRole): bool
{
    $item = dms_permission_items()[$code] ?? null;
    if (!$item) {
        return false;
    }
    return !$item['enforced'] || $baseRole === 'admin' || in_array($baseRole, $item['bases'], true);
}

/** صلاحية خاصة بمدير النظام لا تُمنح لغيره */
function dms_permission_admin_only(string $code): bool
{
    $item = dms_permission_items()[$code] ?? null;
    return $item !== null && $item['enforced'] && $item['bases'] === ['admin'];
}

/** يوقف الصفحة لمن لا يملك الصلاحية */
function require_permission(string $perm, string $redirectPath = 'index.php'): array
{
    $user = require_login();
    if (!user_can($perm, $user)) {
        log_security('UNAUTHORIZED_ACCESS', 'WARNING', 'perm=' . $perm . ' page=' . ($_SERVER['REQUEST_URI'] ?? ''), (int) $user['id']);
        flash('danger', __('auth.no_permission'));
        redirect(base_url($redirectPath));
    }
    return $user;
}

/**
 * تنبيهات لمدير النظام بعد حفظ تركيبة مستخدم ناقصة أو مربكة.
 * لا تمنع الحفظ — للتذكير فقط.
 *
 * @param array{
 *   id?:int,
 *   role:string,
 *   role_id:int,
 *   department_id?:int|null,
 *   assign_ids?:list<int>,
 *   principal_ids?:list<int>
 * } $snapshot
 * @return list<string>
 */
function dms_user_setup_warnings(array $snapshot): array
{
    $warnings = [];
    $userId = (int) ($snapshot['id'] ?? 0);
    $roleId = (int) ($snapshot['role_id'] ?? 0);
    $base = (string) ($snapshot['role'] ?? '');
    $primaryId = (int) ($snapshot['department_id'] ?? 0);
    $assignIds = function_exists('dms_normalize_id_list')
        ? dms_normalize_id_list((array) ($snapshot['assign_ids'] ?? []))
        : array_values(array_unique(array_filter(array_map('intval', (array) ($snapshot['assign_ids'] ?? [])))));
    $principalIds = function_exists('dms_normalize_id_list')
        ? dms_normalize_id_list((array) ($snapshot['principal_ids'] ?? []))
        : array_values(array_unique(array_filter(array_map('intval', (array) ($snapshot['principal_ids'] ?? [])))));

    $role = $roleId > 0 && function_exists('dms_role_get') ? dms_role_get($roleId) : null;
    $roleCode = is_array($role) ? (string) ($role['code'] ?? '') : '';

    $probe = [
        'id' => $userId,
        'role' => $base,
        'role_id' => $roleId,
        'department_id' => $primaryId > 0 ? $primaryId : null,
        'department_ids' => $assignIds,
    ];

    if ($roleCode === 'employee') {
        $hasAny = false;
        foreach (array_keys(dms_permission_items()) as $code) {
            if (user_can($code, $probe)) {
                $hasAny = true;
                break;
            }
        }
        if (!$hasAny) {
            $warnings[] = __('users.warn_employee_no_perms');
        }
    }

    $seesAll = $base === 'admin'
        || (function_exists('can_view_all_departments') && can_view_all_departments($probe));
    if (!$seesAll) {
        $hasAssign = $assignIds !== [];
        $createPerms = ['outgoing.create', 'incoming.create', 'internal.create'];
        foreach ($createPerms as $perm) {
            if (user_can($perm, $probe) && !$hasAssign) {
                $warnings[] = __('users.warn_create_without_assign');
                break;
            }
        }
        if ($principalIds !== [] && !$hasAssign) {
            $warnings[] = __('users.warn_principal_without_assign');
        }
        if ($primaryId > 0 && !$hasAssign && $principalIds === []) {
            $warnings[] = __('users.warn_primary_only');
        }
    }

    return $warnings;
}

/** رسالة فلاش: نجاح + تنبيهات إن وُجدت. @param list<string> $warnings */
function dms_user_flash_saved(string $successMsg, array $warnings): void
{
    if ($warnings === []) {
        flash('success', $successMsg);
        return;
    }
    flash(
        'warning',
        $successMsg . "\n\n" . __('users.warn_prefix') . "\n• " . implode("\n• ", $warnings)
    );
}

/**
 * هل يملك المستخدم الصلاحية؟ مدير النظام يملكها دائماً.
 * الترتيب: الدور الأساسي يسمح بها ← استثناء المستخدم (منح/منع) ← صلاحيات دوره.
 */
function user_can(string $perm, ?array $user = null): bool
{
    $user = $user ?? (function_exists('current_user') ? current_user() : null);
    if (!$user) {
        return false;
    }
    $base = (string) ($user['role'] ?? '');
    if ($base === 'admin') {
        return true;
    }
    $assigned = function_exists('user_role_id') ? user_role_id($user) : 0;
    $assignedRole = ($assigned > 0 && function_exists('dms_role_get')) ? dms_role_get($assigned) : null;
    if (is_array($assignedRole) && (int) ($assignedRole['is_system'] ?? 0) !== 1) {
        if (dms_permission_admin_only($perm)) {
            return false;
        }
    } elseif (!dms_permission_allowed_for_base($perm, $base)) {
        return false;
    }
    $override = !empty($user['id']) ? (dms_user_permission_overrides((int) $user['id'])[$perm] ?? null) : null;
    if ($override !== null) {
        return $override === 'grant';
    }
    $roleId = user_role_id($user);
    return $roleId > 0 && isset(dms_role_permissions_map()[$roleId][$perm]);
}

/**
 * حالة عنصر مرتبط بصلاحية في الواجهة:
 * allow = متاح، deny = يظهر معطّلاً مع تلميح، hide = لا ينطبق على دوره الأساسي فيُخفى.
 */
function dms_perm_ui(string $perm, ?array $user = null): string
{
    $user = $user ?? (function_exists('current_user') ? current_user() : null);
    if (user_can($perm, $user)) {
        return 'allow';
    }
    return $user && dms_user_perm_applicable($user, $perm) ? 'deny' : 'hide';
}

function dms_perm_denied_tip(string $label = ''): string
{
    return $label !== '' ? $label . ' — ' . __('perm.denied_tip') : __('perm.denied_tip');
}

/** غلاف تلميح لعنصر معطّل لعدم الصلاحية (العنصر المعطّل لا يُطلق أحداث المؤشر) */
function dms_perm_denied_wrap(string $innerHtml, string $label = '', string $wrapClass = 'd-inline-block'): string
{
    return '<span class="' . e($wrapClass) . '" tabindex="0" data-bs-toggle="tooltip" data-bs-placement="top" title="'
        . e(dms_perm_denied_tip($label)) . '">' . $innerHtml . '</span>';
}

function dms_perm_denied_btn(string $label, string $icon = '', string $class = 'btn-outline-secondary', string $wrapClass = 'd-inline-block'): string
{
    $iconHtml = $icon !== '' ? '<i class="bi ' . e($icon) . ' me-1" aria-hidden="true"></i>' : '';
    return dms_perm_denied_wrap(
        '<button type="button" class="btn ' . e($class) . '" disabled aria-disabled="true">' . $iconHtml . e($label) . '</button>',
        $label,
        $wrapClass
    );
}

/** بديل btn_icon_add حين لا يملك المستخدم الصلاحية */
function dms_perm_denied_icon_btn(string $label, string $icon = 'bi-plus-lg'): string
{
    return dms_perm_denied_wrap(
        '<button type="button" class="btn btn-icon btn-kff" disabled aria-disabled="true" aria-label="' . e(dms_perm_denied_tip($label)) . '">'
        . '<i class="bi ' . e($icon) . '" aria-hidden="true"></i></button>',
        $label
    );
}

/** زر إضافة في رأس القائمة حسب الصلاحية: متاح / معطّل / لا شيء */
function dms_perm_add_btn(string $perm, string $href, string $label, string $icon = 'bi-plus-lg', ?array $user = null): string
{
    $state = dms_perm_ui($perm, $user);
    if ($state === 'allow') {
        return btn_icon_add($href, $label, $icon);
    }
    return $state === 'deny' ? dms_perm_denied_icon_btn($label, $icon) : '';
}

function ensure_user_permissions_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        db()->exec(
            "CREATE TABLE IF NOT EXISTS `tbluser_permissions` (
                user_id INT UNSIGNED NOT NULL,
                perm_code VARCHAR(60) NOT NULL,
                effect ENUM('grant','deny') NOT NULL,
                created_by INT UNSIGNED NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (user_id, perm_code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        dms_user_permissions_migrate_mail_draft();
    } catch (Throwable $e) {
        $done = false;
        error_log('DMS user permissions schema: ' . $e->getMessage());
    }
}

/** «السماح بالإرسال بالبريد» كان حقلاً في المستخدم؛ يُنقل مرة واحدة إلى استثناء «منح» */
function dms_user_permissions_migrate_mail_draft(): void
{
    if ((string) setting('perm_mail_draft_migrated', '') === '1') {
        return;
    }
    if (in_array('can_mail_draft', dms_db_column_names('tblusers'), true)) {
        db()->exec(
            "INSERT IGNORE INTO `tbluser_permissions` (user_id, perm_code, effect)
             SELECT id, 'mail.draft', 'grant' FROM `tblusers` WHERE can_mail_draft = 1 AND role <> 'admin'"
        );
    }
    set_setting('perm_mail_draft_migrated', '1', 'نقل «السماح بالإرسال بالبريد» إلى استثناءات المستخدمين (داخلي)');
}

/** @return array<int,array<string,string>> user_id => [perm_code => grant|deny] */
function dms_user_permission_overrides_all(bool $refresh = false): array
{
    static $all = null;
    if ($all !== null && !$refresh) {
        return $all;
    }
    ensure_user_permissions_schema();
    $all = [];
    try {
        foreach (db()->query('SELECT user_id, perm_code, effect FROM `tbluser_permissions`') as $row) {
            $all[(int) $row['user_id']][(string) $row['perm_code']] = (string) $row['effect'];
        }
    } catch (Throwable $e) {
        // تجاهل
    }
    return $all;
}

/** @return array<string,string> perm_code => grant|deny */
function dms_user_permission_overrides(int $userId): array
{
    return dms_user_permission_overrides_all()[$userId] ?? [];
}

/** عدد الاستثناءات الفعّالة (التي يسمح بها دوره الأساسي) */
function dms_user_permission_override_count(array $user): int
{
    $base = (string) ($user['role'] ?? '');
    if ($base === 'admin') {
        return 0;
    }
    $n = 0;
    foreach (array_keys(dms_user_permission_overrides((int) ($user['id'] ?? 0))) as $code) {
        if (dms_user_perm_applicable($user, $code)) {
            $n++;
        }
    }
    return $n;
}

/**
 * حفظ استثناءات مستخدم من صفحته.
 *
 * @param array<string,mixed> $posted perm_code => role|grant|deny
 * @return int عدد الصلاحيات التي تغيّرت
 */
function dms_user_permissions_save(array $target, array $posted, array $actor): int
{
    ensure_user_permissions_schema();
    $uid = (int) $target['id'];
    $base = (string) ($target['role'] ?? '');
    if ($uid <= 0 || $base === 'admin') {
        return 0;
    }
    $current = dms_user_permission_overrides_all(true)[$uid] ?? [];
    $upsert = db()->prepare(
        'INSERT INTO `tbluser_permissions` (user_id, perm_code, effect, created_by) VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE effect = VALUES(effect), created_by = VALUES(created_by), created_at = CURRENT_TIMESTAMP'
    );
    $del = db()->prepare('DELETE FROM `tbluser_permissions` WHERE user_id = ? AND perm_code = ?');
    $changes = ['grant' => [], 'deny' => [], 'role' => []];
    foreach (dms_permission_items() as $code => $item) {
        if (!dms_user_perm_applicable($target, $code)) {
            continue;
        }
        $want = (string) ($posted[$code] ?? 'role');
        $want = in_array($want, ['grant', 'deny'], true) ? $want : 'role';
        $have = $current[$code] ?? 'role';
        if ($want === $have) {
            continue;
        }
        if ($want === 'role') {
            $del->execute([$uid, $code]);
        } else {
            $upsert->execute([$uid, $code, $want, (int) $actor['id']]);
        }
        $changes[$want][] = $code;
    }
    $count = count($changes['grant']) + count($changes['deny']) + count($changes['role']);
    if ($count === 0) {
        return 0;
    }
    $parts = [];
    foreach (['grant' => 'perm.ov_grant', 'deny' => 'perm.ov_deny', 'role' => 'perm.ov_role'] as $kind => $labelKey) {
        if ($changes[$kind]) {
            $parts[] = __($labelKey) . ': ' . implode('، ', array_map('dms_permission_label', $changes[$kind]));
        }
    }
    log_activity(
        (int) $actor['id'],
        null,
        'user_permissions_update',
        str_replace([':user', ':changes'], [user_display_name($target), implode(' | ', $parts)], __('perm.user_log'))
    );
    dms_user_permission_overrides_all(true);
    return $count;
}

function dms_user_permissions_delete_for(int $userId): void
{
    ensure_user_permissions_schema();
    db()->prepare('DELETE FROM `tbluser_permissions` WHERE user_id = ?')->execute([$userId]);
}

/** الصلاحية المطلوبة لرابط في القائمة الجانبية (إن وُجدت) */
function dms_permission_for_href(string $href): ?string
{
    static $map = [
        'outgoing/create.php' => 'outgoing.create',
        'outgoing/bulk.php' => 'outgoing.bulk',
        'outgoing/batch_print.php' => 'outgoing.bulk',
        'outgoing/batch_corr_print.php' => 'outgoing.bulk',
        'outgoing/batch_labels.php' => 'outgoing.bulk',
        'outgoing/batch_thermal.php' => 'outgoing.bulk',
        'outgoing/batch_word.php' => 'outgoing.bulk',
        'outgoing/batch_csv.php' => 'outgoing.bulk',
        'archive/general/index.php' => 'archive.general.view',
        'archive/general/view.php' => 'archive.general.view',
        'archive/general/create.php' => 'archive.general.create',
        'archive/general/edit.php' => 'archive.general.edit',
        'archive/general/delete.php' => 'archive.general.delete',
        'archive/general/print.php' => 'archive.general.view',
        'archive/general/list_print.php' => 'archive.general.view',
        'archive/general/upload.php' => 'archive.general.edit',
        'archive/general/file_delete.php' => 'archive.general.delete',
        'archive/scholarships/index.php' => 'archive.scholarships.view',
        'archive/scholarships/view.php' => 'archive.scholarships.view',
        'archive/scholarships/create.php' => 'archive.scholarships.view',
        'archive/scholarships/form.php' => 'archive.scholarships.view',
        'archive/scholarships/save.php' => 'archive.scholarships.view',
        'archive/scholarships/delete.php' => 'archive.scholarships.delete',
        'archive/scholarships/file.php' => 'archive.scholarships.view',
        'archive/scholarships/file_delete.php' => 'archive.scholarships.edit',
        'archive/scholarships/print.php' => 'archive.scholarships.view',
        'archive/scholarships/list_print.php' => 'archive.scholarships.view',
        'archive/scholarships/export.php' => 'archive.scholarships.view',
        'archive/projects/index.php' => 'archive.projects.view',
        'archive/projects/view.php' => 'archive.projects.view',
        'archive/projects/create.php' => 'archive.projects.view',
        'archive/projects/form.php' => 'archive.projects.view',
        'archive/projects/save.php' => 'archive.projects.view',
        'archive/projects/delete.php' => 'archive.projects.delete',
        'archive/projects/file.php' => 'archive.projects.view',
        'archive/projects/file_delete.php' => 'archive.projects.edit',
        'archive/projects/print.php' => 'archive.projects.view',
        'archive/projects/list_print.php' => 'archive.projects.view',
        'archive/projects/export.php' => 'archive.projects.view',
        'archive/finance/index.php' => 'archive.finance.view',
        'archive/finance/view.php' => 'archive.finance.view',
        'archive/finance/create.php' => 'archive.finance.view',
        'archive/finance/form.php' => 'archive.finance.view',
        'archive/finance/save.php' => 'archive.finance.view',
        'archive/finance/delete.php' => 'archive.finance.delete',
        'archive/finance/file.php' => 'archive.finance.view',
        'archive/finance/file_delete.php' => 'archive.finance.edit',
        'archive/finance/print.php' => 'archive.finance.view',
        'archive/finance/list_print.php' => 'archive.finance.view',
        'archive/finance/export.php' => 'archive.finance.view',
        'incoming/create.php' => 'incoming.create',
        'internal/create.php' => 'internal.create',
        'circulars/create.php' => 'circulars.create',
        'admin/letter_templates.php' => 'templates.edit',
        'admin/entities.php' => 'entities.manage',
        'admin/couriers.php' => 'entities.manage',
        'admin/entity_groups.php' => 'entity_groups.manage',
        'admin/lookups.php' => 'lookups.manage',
        'admin/import.php' => 'import.data',
        'admin/users.php' => 'admin.users',
        'admin/roles.php' => 'admin.users',
        'admin/permissions.php' => 'admin.users',
        'admin/departments.php' => 'admin.departments',
        'admin/settings.php' => 'admin.settings',
        'admin/email_settings.php' => 'admin.settings',
        'admin/whatsapp_settings.php' => 'admin.settings',
        'admin/sidebar_menu.php' => 'admin.sidebar',
        'admin/activity_log.php' => 'admin.logs',
        'admin/security_log.php' => 'admin.logs',
        'admin/active_sessions.php' => 'admin.logs',
        'admin/backup.php' => 'admin.backup',
        'admin/reset_all.php' => 'admin.reset',
    ];
    $path = ltrim((string) parse_url($href, PHP_URL_PATH), '/');
    return $map[$path] ?? null;
}

function require_outgoing_bulk(?array $user = null): void
{
    $user = $user ?? (function_exists('current_user') ? current_user() : null);
    if (user_can('outgoing.bulk', $user)) {
        return;
    }
    flash('danger', __('auth.forbidden'));
    redirect(base_url('outgoing/index.php'));
}

/** فتح قائمة الدفعات وصفحة الدفعة: إصدار جماعي، أو مراجع/معتمد يحتاج البتّ */
function user_can_view_outgoing_batch_pages(?array $user = null): bool
{
    $user = $user ?? (function_exists('current_user') ? current_user() : null);
    if (user_can('outgoing.bulk', $user)) {
        return true;
    }
    if (function_exists('outgoing_user_may_see_review_queue') && outgoing_user_may_see_review_queue($user)) {
        return true;
    }
    if (function_exists('outgoing_user_may_see_approval_queue') && outgoing_user_may_see_approval_queue($user)) {
        return true;
    }
    return false;
}

function require_outgoing_batch_view(?array $user = null): void
{
    $user = $user ?? (function_exists('current_user') ? current_user() : null);
    if (user_can_view_outgoing_batch_pages($user)) {
        return;
    }
    flash('danger', __('auth.forbidden'));
    redirect(base_url('outgoing/index.php'));
}

/**
 * حفظ مصفوفة الصلاحيات من الصفحة (عدا مدير النظام).
 *
 * @param array<int|string,mixed> $posted role_id => list<perm_code>
 * @return int عدد الأدوار التي تغيّرت
 */
function dms_role_permissions_save(array $posted, array $actor): int
{
    ensure_permissions_schema();
    $valid = dms_permission_items();
    $current = dms_role_permissions_map(true);
    $changedRoles = 0;
    $del = db()->prepare('DELETE FROM `tblrole_permissions` WHERE role_id = ? AND perm_code = ?');
    $ins = db()->prepare('INSERT IGNORE INTO `tblrole_permissions` (role_id, perm_code) VALUES (?, ?)');

    foreach (dms_roles_list() as $role) {
        if (dms_role_is_admin($role)) {
            continue;
        }
        $roleId = (int) $role['id'];
        $want = [];
        foreach ((array) ($posted[$roleId] ?? []) as $code) {
            $code = (string) $code;
            if (isset($valid[$code]) && dms_role_can_grant_perm($role, $code)) {
                $want[$code] = true;
            }
        }
        $have = $current[$roleId] ?? [];
        $added = array_keys(array_diff_key($want, $have));
        $removed = array_keys(array_diff_key($have, $want));
        $removed = array_values(array_filter(
            $removed,
            static fn(string $c): bool => isset($valid[$c]) && dms_role_can_grant_perm($role, $c)
        ));
        if ($added === [] && $removed === []) {
            continue;
        }
        foreach ($added as $code) {
            $ins->execute([$roleId, $code]);
        }
        foreach ($removed as $code) {
            $del->execute([$roleId, $code]);
        }
        $changedRoles++;
        $parts = [];
        if ($added) {
            $parts[] = '+ ' . implode('، ', array_map('dms_permission_label', $added));
        }
        if ($removed) {
            $parts[] = '− ' . implode('، ', array_map('dms_permission_label', $removed));
        }
        log_activity(
            (int) $actor['id'],
            null,
            'permissions_update',
            str_replace([':role', ':changes'], [(string) $role['name_ar'], implode(' | ', $parts)], __('perm.log_update'))
        );
    }
    dms_role_permissions_map(true);
    return $changedRoles;
}
