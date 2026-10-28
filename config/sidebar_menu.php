<?php
/**
 * القائمة الجانبية — المصدر الوحيد: tblsidebar_menu
 */
declare(strict_types=1);

function dms_sidebar_menu_clear_cache(): void
{
    unset($GLOBALS['_dms_sidebar_menu_rows'], $GLOBALS['_dms_sidebar_menu_by_key'], $GLOBALS['_dms_sidebar_menu_by_id']);
}

function ensure_sidebar_menu_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $exists = dms_db_table_exists('tblsidebar_menu');
        if (!$exists) {
            db()->exec(
                "CREATE TABLE `tblsidebar_menu` (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    menu_key VARCHAR(80) NOT NULL,
                    parent_id INT UNSIGNED NULL,
                    item_type ENUM('link','group') NOT NULL DEFAULT 'link',
                    sort_order INT NOT NULL DEFAULT 0,
                    is_visible TINYINT(1) NOT NULL DEFAULT 1,
                    label_ar VARCHAR(150) NULL,
                    label_en VARCHAR(150) NULL,
                    icon VARCHAR(80) NULL,
                    href VARCHAR(255) NULL,
                    active_rule VARCHAR(100) NULL,
                    scopes VARCHAR(120) NOT NULL DEFAULT 'all',
                    is_system TINYINT(1) NOT NULL DEFAULT 1,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_sidebar_menu_key (menu_key),
                    KEY idx_sidebar_parent_sort (parent_id, sort_order),
                    CONSTRAINT fk_sidebar_parent FOREIGN KEY (parent_id) REFERENCES `tblsidebar_menu`(id) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }
    } catch (Throwable $e) {
        // قاعدة غير جاهزة
    }
}

function dms_sidebar_menu_seed_path(): string
{
    return dirname(__DIR__) . '/sql/sidebar_menu_seed.sql';
}

function dms_sidebar_menu_run_seed_sql(): void
{
    $path = dms_sidebar_menu_seed_path();
    if (!is_file($path)) {
        return;
    }
    $sql = (string) file_get_contents($path);
    foreach (preg_split('/;\s*\n/', $sql) as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '' || str_starts_with($stmt, '--')) {
            continue;
        }
        db()->exec($stmt);
    }
}

function dms_sidebar_menu_bootstrap(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    ensure_sidebar_menu_schema();
    try {
        dms_sidebar_menu_run_seed_sql();
        dms_sidebar_menu_sync_system_items();
    } catch (Throwable $e) {
        // ignore
    }
}

/** إضافة/تصحيح عناصر نظامية (بـ menu_key) دون الاعتماد على parent_id ثابت */
function dms_sidebar_menu_sync_system_items(): void
{
    $items = [
        [
            'menu_key' => 'import_data',
            'parent_key' => 'group_settings',
            'sort_order' => 125,
            'label_ar' => 'استيراد البيانات',
            'label_en' => 'Data import',
            'icon' => 'bi-file-earmark-arrow-up',
            'href' => 'admin/import.php',
            'active_rule' => 'file:import.php',
            'scopes' => 'admin',
        ],
        [
            'menu_key' => 'roles',
            'parent_key' => 'group_settings',
            'sort_order' => 45,
            'label_ar' => 'أدوار المستخدمين',
            'label_en' => 'User roles',
            'icon' => 'bi-person-badge',
            'href' => 'admin/roles.php',
            'active_rule' => 'admin:roles',
            'scopes' => 'admin',
        ],
        [
            'menu_key' => 'permissions',
            'parent_key' => 'group_settings',
            'sort_order' => 46,
            'label_ar' => 'الصلاحيات',
            'label_en' => 'Permissions',
            'icon' => 'bi-shield-check',
            'href' => 'admin/permissions.php',
            'active_rule' => 'file:permissions.php',
            'scopes' => 'admin',
        ],
        [
            'menu_key' => 'reset_all',
            'parent_key' => 'group_settings',
            'sort_order' => 195,
            'label_ar' => 'إعادة ضبط النظام',
            'label_en' => 'System reset',
            'icon' => 'bi-arrow-counterclockwise',
            'href' => 'admin/reset_all.php',
            'active_rule' => 'file:reset_all.php',
            'scopes' => 'admin',
        ],
        [
            'menu_key' => 'corr_lookups',
            'parent_key' => 'group_settings',
            'sort_order' => 90,
            'label_ar' => 'مسميات القوائم',
            'label_en' => 'List labels',
            'icon' => 'bi-ui-checks',
            'href' => 'admin/lookups.php',
            'active_rule' => 'admin:lookups',
            'scopes' => 'admin,archive_settings',
        ],
        [
            'menu_key' => 'couriers',
            'parent_key' => 'group_settings',
            'sort_order' => 65,
            'label_ar' => 'المراسلون',
            'label_en' => 'Couriers',
            'icon' => 'bi-person-badge',
            'href' => 'admin/couriers.php',
            'active_rule' => 'admin:couriers',
            'scopes' => 'admin,archive_settings',
        ],
        [
            'menu_key' => 'dept_couriers',
            'parent_key' => '',
            'sort_order' => 65,
            'label_ar' => 'المراسلون',
            'label_en' => 'Couriers',
            'icon' => 'bi-person-badge',
            'href' => 'admin/couriers.php',
            'active_rule' => 'admin:couriers',
            'scopes' => 'department_only',
        ],
        [
            'menu_key' => 'circulars',
            'parent_key' => 'group_transactions',
            'sort_order' => 25,
            'label_ar' => 'التعاميم',
            'label_en' => 'Circulars',
            'icon' => 'bi-megaphone',
            'href' => 'circulars/index.php',
            'active_rule' => 'dir:circulars',
            'scopes' => 'all',
            'insert_only' => true,
        ],
        [
            'menu_key' => 'internal',
            'parent_key' => 'group_transactions',
            'sort_order' => 22,
            'label_ar' => 'الداخلي',
            'label_en' => 'Internal',
            'icon' => 'bi-building-gear',
            'href' => 'internal/index.php',
            'active_rule' => 'dir:internal',
            'scopes' => 'all',
            'insert_only' => true,
        ],
        [
            'menu_key' => 'corr_cc',
            'parent_key' => 'group_transactions',
            'sort_order' => 23,
            'label_ar' => 'للاطلاع',
            'label_en' => 'For information',
            'icon' => 'bi-eye',
            'href' => 'cc/index.php',
            'active_rule' => 'dir:cc',
            'scopes' => 'all',
            'insert_only' => true,
        ],
        [
            'menu_key' => 'reports',
            'parent_key' => 'group_transactions',
            'sort_order' => 35,
            'label_ar' => 'التقارير',
            'label_en' => 'Reports',
            'icon' => 'bi-bar-chart',
            'href' => 'reports/index.php',
            'active_rule' => 'dir:reports',
            'scopes' => 'all',
        ],
        [
            'menu_key' => 'archive_hub',
            'parent_key' => 'archive',
            'sort_order' => 10,
            'label_ar' => 'الرئيسية',
            'label_en' => 'Home',
            'icon' => 'bi-house-door',
            'href' => 'archive/index.php',
            'active_rule' => 'archive/index.php',
            'scopes' => 'all',
        ],
        [
            'menu_key' => 'archive_scholarships',
            'parent_key' => 'archive',
            'sort_order' => 20,
            'label_ar' => 'المنح الدراسية',
            'label_en' => 'Scholarships',
            'icon' => 'bi-mortarboard',
            'href' => 'archive/scholarships/index.php',
            'active_rule' => 'dir:scholarships',
            'scopes' => 'all',
        ],
        [
            'menu_key' => 'archive_projects',
            'parent_key' => 'archive',
            'sort_order' => 30,
            'label_ar' => 'المشاريع',
            'label_en' => 'Projects',
            'icon' => 'bi-building',
            'href' => 'archive/projects/index.php',
            'active_rule' => 'dir:projects',
            'scopes' => 'all',
        ],
        [
            'menu_key' => 'archive_finance',
            'parent_key' => 'archive',
            'sort_order' => 40,
            'label_ar' => 'المالية',
            'label_en' => 'Finance',
            'icon' => 'bi-cash-stack',
            'href' => 'archive/finance/index.php',
            'active_rule' => 'dir:finance',
            'scopes' => 'all',
        ],
        [
            'menu_key' => 'archive_general',
            'parent_key' => 'archive',
            'sort_order' => 50,
            'label_ar' => 'وثائق عامة',
            'label_en' => 'General documents',
            'icon' => 'bi-archive',
            'href' => 'archive/general/index.php',
            'active_rule' => 'dir:general',
            'scopes' => 'all',
        ],
    ];

    // بند واحد فقط — كل المسميات داخل lookups.php بالتبويبات
    db()->exec(
        "DELETE FROM `tblsidebar_menu`
         WHERE menu_key IN (
             'categories','priorities','confidentiality','statuses',
             'archive_types','attachment_types','documents'
         )"
    );

    db()->exec(
        "UPDATE `tblsidebar_menu`
         SET item_type = 'group', href = NULL, active_rule = NULL, parent_id = NULL, sort_order = 45
         WHERE menu_key = 'archive'"
    );

    foreach ($items as $item) {
        $parentId = null;
        if (!empty($item['parent_key'])) {
            $parentStmt = db()->prepare('SELECT id FROM `tblsidebar_menu` WHERE menu_key = ? LIMIT 1');
            $parentStmt->execute([$item['parent_key']]);
            $parentId = (int) $parentStmt->fetchColumn();
            if ($parentId <= 0) {
                continue;
            }
        }

        $existsStmt = db()->prepare('SELECT id FROM `tblsidebar_menu` WHERE menu_key = ? LIMIT 1');
        $existsStmt->execute([$item['menu_key']]);
        $existingId = (int) $existsStmt->fetchColumn();

        if ($existingId > 0) {
            if (!empty($item['insert_only'])) {
                continue;
            }
            // لا نعيد كتابة التسميات/الظهور/النطاق — المدير يعدّلها من الواجهة
            db()->prepare(
                'UPDATE `tblsidebar_menu`
                 SET parent_id = ?, sort_order = ?, href = ?, active_rule = ?, is_system = 1
                 WHERE menu_key = ?'
            )->execute([
                $parentId,
                (int) $item['sort_order'],
                $item['href'],
                $item['active_rule'],
                $item['menu_key'],
            ]);
            continue;
        }

        db()->prepare(
            'INSERT INTO `tblsidebar_menu`
             (menu_key, parent_id, item_type, sort_order, is_visible, label_ar, label_en, icon, href, active_rule, scopes, is_system)
             VALUES (?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?, 1)'
        )->execute([
            $item['menu_key'],
            $parentId,
            'link',
            (int) $item['sort_order'],
            $item['label_ar'],
            $item['label_en'],
            $item['icon'],
            $item['href'],
            $item['active_rule'],
            $item['scopes'],
        ]);
    }

    db()->prepare(
        'UPDATE `tblsidebar_menu` SET label_ar = ?, label_en = ?, icon = ? WHERE menu_key = ?'
    )->execute(['الرئيسية', 'Home', 'bi-house-door', 'dashboard']);

    // البريد وواتساب تبويبان داخل صفحة الإعدادات
    $hideStmt = db()->prepare('UPDATE `tblsidebar_menu` SET is_visible = 0 WHERE menu_key = ?');
    foreach (['search', 'email_settings', 'whatsapp_settings'] as $hiddenKey) {
        $hideStmt->execute([$hiddenKey]);
    }
    db()->prepare(
        'UPDATE `tblsidebar_menu` SET active_rule = ? WHERE menu_key = ?'
    )->execute(['file:settings.php,email_settings.php,whatsapp_settings.php', 'settings']);
    db()->prepare(
        "UPDATE `tblsidebar_menu` SET label_ar = ?, label_en = ?
         WHERE menu_key = 'settings' AND label_ar IN ('الإعدادات', 'إعدادات الجهة')"
    )->execute(['الإعدادات العامة', 'General settings']);
    db()->prepare(
        "UPDATE `tblsidebar_menu` SET label_ar = ?, label_en = ?
         WHERE menu_key = 'archive' AND label_ar IN ('أرشيف الوثائق')"
    )->execute(['الأرشيف', 'Archive']);
    db()->prepare(
        "UPDATE `tblsidebar_menu` SET label_ar = ?, label_en = ?, icon = ?
         WHERE menu_key = 'archive_hub'"
    )->execute(['الرئيسية', 'Home', 'bi-house-door']);
    db()->exec(
        "UPDATE `tblsidebar_menu`
         SET item_type = 'group', href = NULL, active_rule = NULL, parent_id = NULL, sort_order = 45
         WHERE menu_key = 'archive'"
    );

    dms_sidebar_menu_clear_cache();
}

/** @return list<array<string,mixed>> */
function dms_sidebar_menu_all_rows(): array
{
    if (isset($GLOBALS['_dms_sidebar_menu_rows']) && is_array($GLOBALS['_dms_sidebar_menu_rows'])) {
        return $GLOBALS['_dms_sidebar_menu_rows'];
    }
    dms_sidebar_menu_bootstrap();
    $rows = db()->query(
        'SELECT * FROM `tblsidebar_menu` ORDER BY (parent_id IS NULL) DESC, parent_id, sort_order, id'
    )->fetchAll(PDO::FETCH_ASSOC);
    $byKey = [];
    foreach ($rows as $row) {
        $byKey[(string) $row['menu_key']] = $row;
    }
    $GLOBALS['_dms_sidebar_menu_rows'] = $rows;
    $GLOBALS['_dms_sidebar_menu_by_key'] = $byKey;
    return $rows;
}

/** @return array<string,mixed>|null */
function dms_sidebar_menu_row_by_key(string $menuKey): ?array
{
    dms_sidebar_menu_all_rows();
    return $GLOBALS['_dms_sidebar_menu_by_key'][$menuKey] ?? null;
}

function dms_sidebar_menu_row_label(array $row, ?string $lang = null): string
{
    $lang = $lang ?? dms_lang_code();
    if ($lang === 'en' && !empty($row['label_en'])) {
        return (string) $row['label_en'];
    }
    if (!empty($row['label_ar'])) {
        return (string) $row['label_ar'];
    }
    if (!empty($row['label_en'])) {
        return (string) $row['label_en'];
    }
    return (string) $row['menu_key'];
}

function dms_sidebar_menu_item_label(string $menuKey): string
{
    $row = dms_sidebar_menu_row_by_key($menuKey);
    return $row ? dms_sidebar_menu_row_label($row) : $menuKey;
}

function dms_sidebar_menu_row_icon(array $row): string
{
    $icon = trim((string) ($row['icon'] ?? ''));
    return $icon !== '' ? $icon : 'bi-circle';
}

/** @return list<string> */
function dms_sidebar_menu_row_scopes(array $row): array
{
    $raw = trim((string) ($row['scopes'] ?? 'all'));
    return array_values(array_filter(array_map('trim', explode(',', $raw))));
}

function dms_sidebar_menu_row_href(array $row): string
{
    return trim((string) ($row['href'] ?? ''));
}

function dms_sidebar_menu_row_active_rule(array $row): string
{
    return trim((string) ($row['active_rule'] ?? ''));
}

function dms_sidebar_menu_log_change(?int $userId, string $action, string $details): void
{
    if ($userId === null || $userId <= 0 || !function_exists('log_activity')) {
        return;
    }
    log_activity($userId, null, $action, $details);
}

function dms_sidebar_menu_user_scopes(?array $user): array
{
    if (!$user) {
        return [];
    }
    $scopes = ['all'];
    $role = (string) ($user['role'] ?? '');
    if ($role !== '' && in_array($role, dms_sidebar_menu_preview_roles(), true)) {
        $scopes[] = $role;
    }
    if ($role === 'department') {
        $scopes[] = 'department_only';
    }
    if (function_exists('can_manage_users') && can_manage_users($user)) {
        $scopes[] = 'admin';
    } elseif (function_exists('can_delete') && can_delete($user)) {
        $scopes[] = 'archive_settings';
    }
    return array_values(array_unique($scopes));
}

/**
 * تحويل نطاقات العنصر المخزّنة إلى أدوار قابلة للتعديل في الواجهة.
 *
 * @param list<string> $scopes
 * @return list<string>
 */
function dms_sidebar_menu_roles_from_scopes(array $scopes): array
{
    $allRoles = dms_sidebar_menu_preview_roles();
    if ($scopes === [] || in_array('all', $scopes, true)) {
        return $allRoles;
    }
    $roles = [];
    foreach ($scopes as $scope) {
        if (in_array($scope, $allRoles, true)) {
            $roles[] = $scope;
            continue;
        }
        if ($scope === 'archive_settings') {
            $roles[] = 'archive';
        } elseif ($scope === 'department_only') {
            $roles[] = 'department';
        }
    }
    return array_values(array_unique($roles));
}

/**
 * تحويل اختيار الأدوار من النموذج إلى قيمة عمود scopes.
 *
 * @param list<string>|array<int|string,mixed> $roles
 */
function dms_sidebar_menu_scopes_from_roles(array $roles): string
{
    $allowed = dms_sidebar_menu_preview_roles();
    $picked = [];
    foreach ($allowed as $role) {
        if (in_array($role, $roles, true)) {
            $picked[] = $role;
        }
    }
    if ($picked === []) {
        throw new InvalidArgumentException(__('sidebar_menu.roles_required'));
    }
    if (count($picked) === count($allowed)) {
        return 'all';
    }
    return implode(',', $picked);
}

/** الصلاحية المربوطة برابط العنصر (إن وُجدت) */
function dms_sidebar_menu_row_perm(array $row): ?string
{
    return function_exists('dms_permission_for_href') ? dms_permission_for_href((string) ($row['href'] ?? '')) : null;
}

/** العنصر ظاهر بسبب صلاحية تحلّ محل نطاقي «مدير النظام/إعدادات الأرشيف» */
function dms_sidebar_menu_perm_grants(array $row, ?array $user): bool
{
    $perm = dms_sidebar_menu_row_perm($row);
    if ($perm === null || !$user || !user_can($perm, $user)) {
        return false;
    }
    return array_diff(dms_sidebar_menu_row_scopes($row), ['admin', 'archive_settings']) === [];
}

function dms_sidebar_menu_item_allowed(array $row, ?array $user): bool
{
    $menuKey = (string) ($row['menu_key'] ?? '');
    if ($menuKey === 'archive' || $menuKey === 'archive_hub') {
        return function_exists('user_can_view_archive_module') && user_can_view_archive_module($user);
    }
    $perm = dms_sidebar_menu_row_perm($row);
    if ($perm !== null && !user_can($perm, $user)) {
        return false;
    }
    if (dms_sidebar_menu_perm_grants($row, $user)) {
        return true;
    }
    $scopes = dms_sidebar_menu_user_scopes($user);
    foreach (dms_sidebar_menu_row_scopes($row) as $scope) {
        if (in_array($scope, $scopes, true)) {
            return true;
        }
    }
    return false;
}

/** هل يظهر عنصر القائمة للمستخدم (مرئي + نطاق الدور)؟ */
function dms_sidebar_menu_key_visible(string $menuKey, ?array $user = null): bool
{
    $row = dms_sidebar_menu_row_by_key($menuKey);
    if (!$row || !(int) ($row['is_visible'] ?? 1)) {
        return false;
    }
    return dms_sidebar_menu_item_allowed($row, $user);
}

/** @return list<string> */
function dms_sidebar_menu_preview_roles(): array
{
    return function_exists('dms_user_roles') ? dms_user_roles() : ['admin', 'communications', 'archive', 'department', 'viewer'];
}

/** @return array{role:string} */
function dms_sidebar_menu_stub_user(string $role): array
{
    return ['role' => $role];
}

/** @return array<int, list<array<string,mixed>>> */
function dms_sidebar_menu_by_parent_index(): array
{
    $byParent = [];
    foreach (dms_sidebar_menu_all_rows() as $row) {
        $pid = $row['parent_id'] === null ? 0 : (int) $row['parent_id'];
        $byParent[$pid][] = $row;
    }
    return $byParent;
}

/** @param array<int, list<array<string,mixed>>> $byParent */
function dms_sidebar_menu_effective_for_role(array $row, string $role, array $byParent): bool
{
    $user = dms_sidebar_menu_stub_user($role);
    if (!(int) ($row['is_visible'] ?? 1)) {
        return false;
    }
    if (($row['item_type'] ?? '') === 'group') {
        $groupOk = dms_sidebar_menu_item_allowed($row, $user);
        foreach ($byParent[(int) $row['id']] ?? [] as $child) {
            if (($groupOk || dms_sidebar_menu_perm_grants($child, $user))
                && dms_sidebar_menu_effective_for_role($child, $role, $byParent)) {
                return true;
            }
        }
        return false;
    }
    if (!dms_sidebar_menu_item_allowed($row, $user)) {
        return false;
    }
    return dms_sidebar_menu_row_href($row) !== '';
}

/** @param array<int, list<array<string,mixed>>> $byParent
 *  @return array<string,bool> */
function dms_sidebar_menu_role_visibility_map(array $row, array $byParent): array
{
    $map = [];
    foreach (dms_sidebar_menu_preview_roles() as $role) {
        $map[$role] = dms_sidebar_menu_effective_for_role($row, $role, $byParent);
    }
    return $map;
}

/** @return array<string,array{icon:string,tone:string}> */
function dms_sidebar_menu_role_meta(): array
{
    return [
        'admin' => ['icon' => 'bi-shield-check', 'tone' => 'admin'],
        'communications' => ['icon' => 'bi-envelope-paper', 'tone' => 'communications'],
        'archive' => ['icon' => 'bi-archive', 'tone' => 'archive'],
        'department' => ['icon' => 'bi-building', 'tone' => 'department'],
        'viewer' => ['icon' => 'bi-eye', 'tone' => 'viewer'],
    ];
}

function dms_sidebar_menu_scope_label(string $scope): string
{
    $key = 'sidebar_menu.scope_' . $scope;
    $label = __($key);
    return $label !== $key ? $label : $scope;
}

/** @param array{current:string,dirName:string,here:string} $ctx */
function dms_sidebar_menu_is_active(string $rule, array $ctx): bool
{
    if ($rule === '') {
        return false;
    }
    if (str_starts_with($rule, 'dir:')) {
        return $ctx['dirName'] === substr($rule, 4);
    }
    if (str_starts_with($rule, 'start:')) {
        return str_starts_with($ctx['here'], substr($rule, 6));
    }
    if (str_starts_with($rule, 'file:')) {
        return in_array($ctx['current'], array_map('trim', explode(',', substr($rule, 5))), true);
    }
    if (str_starts_with($rule, 'admin:')) {
        $base = substr($rule, 6);
        if ($base === 'lookups') {
            return in_array($ctx['current'], [
                'lookups.php',
                'categories_create.php', 'categories_edit.php',
                'priorities_edit.php',
                'confidentiality_edit.php',
                'statuses_edit.php',
                'archive_types_create.php', 'archive_types_edit.php',
                'attachment_types_create.php', 'attachment_types_edit.php',
            ], true);
        }
        return in_array($ctx['current'], [$base . '.php', $base . '_create.php', $base . '_edit.php'], true);
    }
    return $ctx['here'] === $rule || $ctx['current'] === $rule || $ctx['dirName'] === $rule;
}

/**
 * @param array{current:string,dirName:string,here:string} $ctx
 * @return list<array<string,mixed>>
 */
function dms_sidebar_menu_build(?array $user, array $ctx): array
{
    $rows = dms_sidebar_menu_all_rows();
    $byParent = [];
    foreach ($rows as $row) {
        $pid = $row['parent_id'] === null ? 0 : (int) $row['parent_id'];
        $byParent[$pid][] = $row;
    }
    $out = [];
    $seenHrefs = [];
    foreach ($byParent[0] ?? [] as $row) {
        if (!(int) ($row['is_visible'] ?? 1)) {
            continue;
        }
        if (($row['item_type'] ?? '') === 'group') {
            $groupOk = dms_sidebar_menu_item_allowed($row, $user);
            $items = [];
            foreach ($byParent[(int) $row['id']] ?? [] as $child) {
                if (!(int) ($child['is_visible'] ?? 1) || !dms_sidebar_menu_item_allowed($child, $user)) {
                    continue;
                }
                if (!$groupOk && !dms_sidebar_menu_perm_grants($child, $user)) {
                    continue;
                }
                $href = dms_sidebar_menu_row_href($child);
                if ($href === '' || isset($seenHrefs[$href])) {
                    continue;
                }
                $seenHrefs[$href] = true;
                $items[] = [
                    'href' => base_url($href),
                    'label' => dms_sidebar_menu_row_label($child),
                    'icon' => dms_sidebar_menu_row_icon($child),
                    'active' => dms_sidebar_menu_is_active(dms_sidebar_menu_row_active_rule($child), $ctx),
                    'badge' => dms_sidebar_menu_badge((string) $child['menu_key'], $user),
                ];
            }
            if ($items === []) {
                continue;
            }
            $active = false;
            foreach ($items as $it) {
                if (!empty($it['active'])) {
                    $active = true;
                    break;
                }
            }
            $out[] = [
                'type' => 'group',
                'label' => dms_sidebar_menu_row_label($row),
                'icon' => dms_sidebar_menu_row_icon($row),
                'active' => $active,
                'items' => $items,
            ];
            continue;
        }
        if (!dms_sidebar_menu_item_allowed($row, $user)) {
            continue;
        }
        $href = dms_sidebar_menu_row_href($row);
        if ($href === '' || isset($seenHrefs[$href])) {
            continue;
        }
        $seenHrefs[$href] = true;
        $out[] = [
            'type' => 'link',
            'href' => base_url($href),
            'label' => dms_sidebar_menu_row_label($row),
            'icon' => dms_sidebar_menu_row_icon($row),
            'active' => dms_sidebar_menu_is_active(dms_sidebar_menu_row_active_rule($row), $ctx),
            'badge' => dms_sidebar_menu_badge((string) $row['menu_key'], $user),
        ];
    }
    return $out;
}

/** عدّاد يظهر بجانب بند القائمة (0 = لا شيء) */
function dms_sidebar_menu_badge(string $menuKey, ?array $user): int
{
    if ($menuKey === 'corr_cc' && $user && function_exists('corr_cc_unread_count')) {
        return corr_cc_unread_count((int) $user['id']);
    }
    return 0;
}

/** @return list<array<string,mixed>> */
function dms_sidebar_menu_admin_rows(): array
{
    $rows = dms_sidebar_menu_all_rows();
    $byParent = dms_sidebar_menu_by_parent_index();
    $out = [];
    foreach ($rows as $row) {
        if ($row['parent_id'] !== null) {
            continue;
        }
        $out[] = dms_sidebar_menu_admin_row_payload($row, 0, null, $byParent);
        if (($row['item_type'] ?? '') === 'group') {
            foreach ($rows as $child) {
                if ((int) ($child['parent_id'] ?? 0) === (int) $row['id']) {
                    $out[] = dms_sidebar_menu_admin_row_payload($child, 1, (string) $row['menu_key'], $byParent);
                }
            }
        }
    }
    return $out;
}

/** @param array<int, list<array<string,mixed>>> $byParent
 *  @return array<string,mixed> */
function dms_sidebar_menu_admin_row_payload(array $row, int $depth, ?string $groupKey, array $byParent): array
{
    return [
        'id' => (string) $row['menu_key'],
        'db_id' => (int) $row['id'],
        'label' => dms_sidebar_menu_row_label($row),
        'label_ar' => (string) ($row['label_ar'] ?? ''),
        'label_en' => (string) ($row['label_en'] ?? ''),
        'icon' => dms_sidebar_menu_row_icon($row),
        'depth' => $depth,
        'visible' => (bool) (int) ($row['is_visible'] ?? 1),
        'group' => $groupKey,
        'item_type' => (string) ($row['item_type'] ?? 'link'),
        'is_system' => (bool) (int) ($row['is_system'] ?? 1),
        'scopes' => dms_sidebar_menu_row_scopes($row),
        'roles' => dms_sidebar_menu_roles_from_scopes(dms_sidebar_menu_row_scopes($row)),
        'role_visibility' => dms_sidebar_menu_role_visibility_map($row, $byParent),
    ];
}

/** @return list<array{id:string,label:string}> */
function dms_sidebar_menu_group_options(): array
{
    $opts = [];
    foreach (dms_sidebar_menu_all_rows() as $row) {
        if (($row['item_type'] ?? '') === 'group' && $row['parent_id'] === null) {
            $opts[] = ['id' => (string) $row['menu_key'], 'label' => dms_sidebar_menu_row_label($row)];
        }
    }
    return $opts;
}

function dms_sidebar_menu_move(string $menuKey, string $direction, ?int $userId = null): bool
{
    $row = dms_sidebar_menu_row_by_key($menuKey);
    if (!$row) {
        return false;
    }
    $parentId = $row['parent_id'];
    if ($parentId === null) {
        $siblings = db()->query('SELECT * FROM `tblsidebar_menu` WHERE parent_id IS NULL ORDER BY sort_order, id')->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = db()->prepare('SELECT * FROM `tblsidebar_menu` WHERE parent_id = ? ORDER BY sort_order, id');
        $stmt->execute([(int) $parentId]);
        $siblings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    $idx = null;
    foreach ($siblings as $i => $sib) {
        if ((string) $sib['menu_key'] === $menuKey) {
            $idx = $i;
            break;
        }
    }
    if ($idx === null) {
        return false;
    }
    $swap = $direction === 'up' ? $idx - 1 : $idx + 1;
    if ($swap < 0 || $swap >= count($siblings)) {
        return false;
    }
    $upd = db()->prepare('UPDATE `tblsidebar_menu` SET sort_order = ? WHERE id = ?');
    $upd->execute([(int) $siblings[$swap]['sort_order'], (int) $siblings[$idx]['id']]);
    $upd->execute([(int) $siblings[$idx]['sort_order'], (int) $siblings[$swap]['id']]);
    dms_sidebar_menu_clear_cache();
    $label = dms_sidebar_menu_item_label($menuKey);
    $details = $direction === 'up'
        ? str_replace(':item', $label, __('sidebar_menu.log_move_up'))
        : str_replace(':item', $label, __('sidebar_menu.log_move_down'));
    dms_sidebar_menu_log_change($userId, 'sidebar_menu_update', $details);
    return true;
}

function dms_sidebar_menu_set_visible(string $menuKey, bool $visible, ?int $userId = null, bool $log = true): bool
{
    if (!dms_sidebar_menu_row_by_key($menuKey)) {
        return false;
    }
    db()->prepare('UPDATE `tblsidebar_menu` SET is_visible = ? WHERE menu_key = ?')
        ->execute([$visible ? 1 : 0, $menuKey]);
    dms_sidebar_menu_clear_cache();
    if ($log) {
        $label = dms_sidebar_menu_item_label($menuKey);
        $details = $visible
            ? str_replace(':item', $label, __('sidebar_menu.log_show_one'))
            : str_replace(':item', $label, __('sidebar_menu.log_hide_one'));
        dms_sidebar_menu_log_change($userId, 'sidebar_menu_update', $details);
    }
    return true;
}

/** @param list<string> $menuKeys */
function dms_sidebar_menu_bulk_visible(array $menuKeys, bool $visible, ?int $userId = null): void
{
    $labels = [];
    foreach ($menuKeys as $menuKey) {
        if (is_string($menuKey) && $menuKey !== '' && dms_sidebar_menu_set_visible($menuKey, $visible, null, false)) {
            $labels[] = dms_sidebar_menu_item_label($menuKey);
        }
    }
    if ($labels !== []) {
        $details = $visible
            ? str_replace(':items', implode('، ', $labels), __('sidebar_menu.log_show'))
            : str_replace(':items', implode('، ', $labels), __('sidebar_menu.log_hide'));
        dms_sidebar_menu_log_change($userId, 'sidebar_menu_update', $details);
    }
}

function dms_sidebar_menu_reset(?int $userId = null): void
{
    db()->exec('DELETE FROM `tblsidebar_menu`');
    dms_sidebar_menu_clear_cache();
    dms_sidebar_menu_run_seed_sql();
    dms_sidebar_menu_clear_cache();
    dms_sidebar_menu_log_change($userId, 'sidebar_menu_reset', __('sidebar_menu.log_reset'));
}

function dms_sidebar_menu_validate_icon(string $icon): string
{
    $icon = trim($icon);
    if ($icon === '') {
        return '';
    }
    if (!preg_match('/^bi-[a-z0-9-]+$/', $icon)) {
        throw new InvalidArgumentException(__('sidebar_menu.icon_invalid'));
    }
    return $icon;
}

function dms_sidebar_menu_update_item(string $menuKey, array $data, ?int $userId = null): bool
{
    if (!dms_sidebar_menu_row_by_key($menuKey)) {
        return false;
    }
    $labelAr = trim((string) ($data['label_ar'] ?? ''));
    $labelEn = trim((string) ($data['label_en'] ?? ''));
    $icon = dms_sidebar_menu_validate_icon((string) ($data['icon'] ?? ''));
    if ($labelAr === '') {
        throw new InvalidArgumentException(__('sidebar_menu.label_ar_required'));
    }
    $scopes = null;
    if (array_key_exists('roles', $data) || array_key_exists('scopes', $data)) {
        if (array_key_exists('roles', $data)) {
            $roles = $data['roles'];
            if (!is_array($roles)) {
                $roles = [];
            }
            $scopes = dms_sidebar_menu_scopes_from_roles($roles);
        } else {
            $scopes = trim((string) $data['scopes']);
            if ($scopes === '') {
                throw new InvalidArgumentException(__('sidebar_menu.roles_required'));
            }
        }
    }
    if ($scopes !== null) {
        db()->prepare(
            'UPDATE `tblsidebar_menu` SET label_ar = ?, label_en = ?, icon = ?, scopes = ? WHERE menu_key = ?'
        )->execute([
            $labelAr,
            $labelEn !== '' ? $labelEn : null,
            $icon !== '' ? $icon : 'bi-circle',
            $scopes,
            $menuKey,
        ]);
    } else {
        db()->prepare(
            'UPDATE `tblsidebar_menu` SET label_ar = ?, label_en = ?, icon = ? WHERE menu_key = ?'
        )->execute([
            $labelAr,
            $labelEn !== '' ? $labelEn : null,
            $icon !== '' ? $icon : 'bi-circle',
            $menuKey,
        ]);
    }
    dms_sidebar_menu_clear_cache();
    dms_sidebar_menu_log_change(
        $userId,
        'sidebar_menu_update',
        str_replace(':item', $labelAr, __('sidebar_menu.log_edit'))
    );
    return true;
}

function dms_sidebar_menu_create_group(array $data, ?int $userId = null): string
{
    $labelAr = trim((string) ($data['label_ar'] ?? ''));
    $labelEn = trim((string) ($data['label_en'] ?? ''));
    if ($labelAr === '') {
        throw new InvalidArgumentException(__('sidebar_menu.group_name_required'));
    }
    $icon = dms_sidebar_menu_validate_icon((string) ($data['icon'] ?? 'bi-folder2'));
    if ($icon === '') {
        $icon = 'bi-folder2';
    }
    $menuKey = 'group_custom_' . bin2hex(random_bytes(4));
    $maxSort = (int) db()->query('SELECT COALESCE(MAX(sort_order), 0) FROM `tblsidebar_menu` WHERE parent_id IS NULL')->fetchColumn();
    db()->prepare(
        'INSERT INTO `tblsidebar_menu`
        (menu_key, parent_id, item_type, sort_order, is_visible, label_ar, label_en, icon, scopes, is_system)
        VALUES (?, NULL, ?, ?, 1, ?, ?, ?, ?, 0)'
    )->execute([$menuKey, 'group', $maxSort + 10, $labelAr, $labelEn !== '' ? $labelEn : null, $icon, 'all']);
    dms_sidebar_menu_clear_cache();
    dms_sidebar_menu_log_change(
        $userId,
        'sidebar_menu_update',
        str_replace(':item', $labelAr, __('sidebar_menu.log_group_create'))
    );
    return $menuKey;
}

function dms_sidebar_menu_move_to_parent(string $menuKey, ?string $parentMenuKey, ?int $userId = null): bool
{
    $row = dms_sidebar_menu_row_by_key($menuKey);
    if (!$row || ($row['item_type'] ?? '') !== 'link') {
        return false;
    }
    $parentId = null;
    $parent = null;
    if ($parentMenuKey !== null && $parentMenuKey !== '') {
        $parent = dms_sidebar_menu_row_by_key($parentMenuKey);
        if (!$parent || ($parent['item_type'] ?? '') !== 'group') {
            throw new InvalidArgumentException(__('sidebar_menu.invalid_group'));
        }
        $parentId = (int) $parent['id'];
    }
    if ($parentId === null) {
        $maxSort = (int) db()->query('SELECT COALESCE(MAX(sort_order), 0) FROM `tblsidebar_menu` WHERE parent_id IS NULL')->fetchColumn();
    } else {
        $stmt = db()->prepare('SELECT COALESCE(MAX(sort_order), 0) FROM `tblsidebar_menu` WHERE parent_id = ?');
        $stmt->execute([$parentId]);
        $maxSort = (int) $stmt->fetchColumn();
    }
    db()->prepare('UPDATE `tblsidebar_menu` SET parent_id = ?, sort_order = ? WHERE menu_key = ?')
        ->execute([$parentId, $maxSort + 10, $menuKey]);
    dms_sidebar_menu_clear_cache();
    $label = dms_sidebar_menu_item_label($menuKey);
    $target = $parentId === null ? __('sidebar_menu.top_level') : dms_sidebar_menu_item_label($parent);
    dms_sidebar_menu_log_change(
        $userId,
        'sidebar_menu_update',
        str_replace([':item', ':target'], [$label, $target], __('sidebar_menu.log_move_to'))
    );
    return true;
}

function dms_sidebar_menu_delete_group(string $menuKey, ?int $userId = null): bool
{
    $row = dms_sidebar_menu_row_by_key($menuKey);
    if (!$row || ($row['item_type'] ?? '') !== 'group' || (int) ($row['is_system'] ?? 1) === 1) {
        return false;
    }
    $stmt = db()->prepare('SELECT COUNT(*) FROM `tblsidebar_menu` WHERE parent_id = ?');
    $stmt->execute([(int) $row['id']]);
    if ((int) $stmt->fetchColumn() > 0) {
        throw new InvalidArgumentException(__('sidebar_menu.group_not_empty'));
    }
    $label = dms_sidebar_menu_row_label($row);
    db()->prepare('DELETE FROM `tblsidebar_menu` WHERE menu_key = ?')->execute([$menuKey]);
    dms_sidebar_menu_clear_cache();
    dms_sidebar_menu_log_change($userId, 'sidebar_menu_update', str_replace(':item', $label, __('sidebar_menu.log_group_delete')));
    return true;
}

/** @return array{label_ar:string,label_en:string,icon:string,roles:list<string>} */
function dms_sidebar_menu_edit_defaults(string $menuKey): array
{
    $row = dms_sidebar_menu_row_by_key($menuKey);
    if (!$row) {
        return ['label_ar' => '', 'label_en' => '', 'icon' => 'bi-circle', 'roles' => dms_sidebar_menu_preview_roles()];
    }
    return [
        'label_ar' => (string) ($row['label_ar'] ?? ''),
        'label_en' => (string) ($row['label_en'] ?? ''),
        'icon' => dms_sidebar_menu_row_icon($row),
        'roles' => dms_sidebar_menu_roles_from_scopes(dms_sidebar_menu_row_scopes($row)),
    ];
}
