<?php
/**
 * جهة التوقيع / موجهة من في الصادر — منفصلة عن القسم المعدّ
 */

declare(strict_types=1);

function ensure_departments_signing_office_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    if (function_exists('ensure_departments_schema')) {
        ensure_departments_schema();
    }
    try {
        $cols = dms_db_column_map(DmsDbTable::DEPARTMENTS);
        if (empty($cols['is_signing_office'])) {
            db()->exec(
                "ALTER TABLE `" . DmsDbTable::DEPARTMENTS . "`
                 ADD COLUMN `is_signing_office` TINYINT(1) NOT NULL DEFAULT 0
                 COMMENT 'يمكن اختياره كجهة توقيع (موجهة من) لصادر أقسام أخرى'
                 AFTER `is_active`"
            );
        }
    } catch (Throwable $e) {
        // تجاهل
    }
    ensure_department_signing_access_schema();
}

function ensure_department_signing_access_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        if (!dms_db_table_exists(DmsDbTable::DEPARTMENT_SIGNING_ACCESS)) {
            db()->exec(
                "CREATE TABLE `" . DmsDbTable::DEPARTMENT_SIGNING_ACCESS . "` (
                    `signing_department_id` INT UNSIGNED NOT NULL COMMENT 'مكتب التوقيع',
                    `preparing_department_id` INT UNSIGNED NOT NULL COMMENT 'القسم المعدّ المسموح له',
                    PRIMARY KEY (`signing_department_id`, `preparing_department_id`),
                    KEY `idx_signing_access_prep` (`preparing_department_id`),
                    CONSTRAINT `fk_signing_access_office`
                        FOREIGN KEY (`signing_department_id`) REFERENCES `" . DmsDbTable::DEPARTMENTS . "` (`id`) ON DELETE CASCADE,
                    CONSTRAINT `fk_signing_access_prep`
                        FOREIGN KEY (`preparing_department_id`) REFERENCES `" . DmsDbTable::DEPARTMENTS . "` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }
    } catch (Throwable $e) {
        // تجاهل أثناء التثبيت
    }
}

function ensure_correspondence_issuing_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    ensure_departments_signing_office_schema();
    if (function_exists('ensure_correspondence_party_snapshot_schema')) {
        ensure_correspondence_party_snapshot_schema();
    }
    try {
        $cols = dms_db_column_map(DmsDbTable::CORRESPONDENCE);
        if (empty($cols['issuing_department_id'])) {
            db()->exec(
                "ALTER TABLE `" . DmsDbTable::CORRESPONDENCE . "`
                 ADD COLUMN `issuing_department_id` INT UNSIGNED NULL DEFAULT NULL
                 COMMENT 'جهة التوقيع / موجهة من (صادر فقط؛ NULL = نفس القسم المعدّ)'
                 AFTER `department_id`"
            );
        }
    } catch (Throwable $e) {
        // تجاهل
    }
}

/** أقسام معلّمة كمكاتب توقيع */
function departments_signing_offices(bool $activeOnly = true): array
{
    ensure_departments_signing_office_schema();
    $sql = 'SELECT * FROM `' . DmsDbTable::DEPARTMENTS . '` WHERE is_signing_office = 1';
    if ($activeOnly) {
        $sql .= ' AND is_active = 1';
    }
    $sql .= ' ORDER BY sort_order, name, id';
    try {
        return db()->query($sql)->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * معرّفات الأقسام المعدّة المسموح لها باستخدام مكتب التوقيع.
 * @return list<int>
 */
function department_signing_allowed_preparing_ids(int $signingDepartmentId): array
{
    ensure_department_signing_access_schema();
    if ($signingDepartmentId <= 0) {
        return [];
    }
    try {
        $stmt = db()->prepare(
            'SELECT preparing_department_id FROM `' . DmsDbTable::DEPARTMENT_SIGNING_ACCESS . '`
             WHERE signing_department_id = ? ORDER BY preparing_department_id'
        );
        $stmt->execute([$signingDepartmentId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * هل القسم المعدّ مسموح له باستخدام مكتب التوقيع؟
 * القائمة الفارغة = لا يُسمح لأي قسم آخر (يجب تحديد الأقسام صراحة).
 */
function department_signing_allows_preparing(int $signingDepartmentId, int $preparingDepartmentId): bool
{
    if ($signingDepartmentId <= 0 || $preparingDepartmentId <= 0) {
        return false;
    }
    if ($signingDepartmentId === $preparingDepartmentId) {
        return true;
    }
    $allowed = department_signing_allowed_preparing_ids($signingDepartmentId);
    return in_array($preparingDepartmentId, $allowed, true);
}

/**
 * حفظ قائمة الأقسام المسموح لها باستخدام مكتب التوقيع.
 * @param list<int|string> $preparingIds
 */
function department_signing_access_save(int $signingDepartmentId, array $preparingIds): void
{
    ensure_department_signing_access_schema();
    if ($signingDepartmentId <= 0) {
        return;
    }
    $clean = [];
    foreach ($preparingIds as $id) {
        $id = (int) $id;
        if ($id > 0 && $id !== $signingDepartmentId) {
            $clean[$id] = $id;
        }
    }
    $pdo = db();
    $pdo->prepare(
        'DELETE FROM `' . DmsDbTable::DEPARTMENT_SIGNING_ACCESS . '` WHERE signing_department_id = ?'
    )->execute([$signingDepartmentId]);
    if ($clean === []) {
        return;
    }
    $ins = $pdo->prepare(
        'INSERT INTO `' . DmsDbTable::DEPARTMENT_SIGNING_ACCESS . '`
         (signing_department_id, preparing_department_id) VALUES (?, ?)'
    );
    foreach ($clean as $prepId) {
        $ins->execute([$signingDepartmentId, $prepId]);
    }
}

/**
 * معرّف جهة التوقيع الفعلي (موجهة من).
 * NULL أو 0 في issuing = نفس القسم المعدّ.
 */
function correspondence_effective_issuing_department_id(?int $preparingDeptId, ?int $issuingDeptId): ?int
{
    $issuing = (int) ($issuingDeptId ?? 0);
    if ($issuing > 0) {
        return $issuing;
    }
    $prep = (int) ($preparingDeptId ?? 0);
    return $prep > 0 ? $prep : null;
}

function correspondence_issuing_from_row(?array $corr): ?int
{
    if (!$corr) {
        return null;
    }
    return correspondence_effective_issuing_department_id(
        (int) ($corr['department_id'] ?? 0) ?: null,
        (int) ($corr['issuing_department_id'] ?? 0) ?: null
    );
}

function department_exists_id(int $id): bool
{
    if ($id <= 0) {
        return false;
    }
    try {
        $stmt = db()->prepare('SELECT id FROM `' . DmsDbTable::DEPARTMENTS . '` WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return (bool) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function department_is_signing_office(int $departmentId): bool
{
    static $ids = null;
    if ($ids === null) {
        $ids = array_map(static fn (array $d): int => (int) ($d['id'] ?? 0), departments_signing_offices(true));
    }
    return $departmentId > 0 && in_array($departmentId, $ids, true);
}

/**
 * القسم الذي يُحفظ عليه الصادر عند اختيار «موجهة من».
 * الإسناد يُصدر المعاملة على القسم المعيَّن نفسه.
 * @return int 0 = غير مسموح
 */
function outgoing_sign_from_prep_for(?array $user, int $chosenId): int
{
    if ($chosenId <= 0 || !$user || !department_exists_id($chosenId)) {
        return 0;
    }
    $own = function_exists('user_department_ids') ? user_department_ids($user) : [];
    if (in_array($chosenId, $own, true)) {
        return $chosenId;
    }
    if (function_exists('can_choose_correspondence_department') && can_choose_correspondence_department($user)) {
        return $chosenId;
    }
    return 0;
}

/**
 * خيارات حقل «جهة التوقيع (موجهة من)» مع القسم المعدّ لكل خيار.
 * @return list<array{id:int,label:string,office:string,prep:int,sort:int}>
 */
function outgoing_sign_from_options(?array $user, array $allDepartments, ?array $corr = null): array
{
    ensure_departments_signing_office_schema();
    $canAll = function_exists('can_choose_correspondence_department') && can_choose_correspondence_department($user);
    $pool = $canAll
        ? array_filter($allDepartments, static fn (array $d): bool => (int) ($d['is_active'] ?? 1) === 1)
        : (function_exists('departments_visible_to_user') ? departments_visible_to_user($user, $allDepartments) : []);
    $rows = [];
    foreach ($pool as $d) {
        $rows[(int) $d['id']] = $d;
    }
    $out = [];
    foreach ($rows as $id => $d) {
        $prep = outgoing_sign_from_prep_for($user, $id);
        if ($prep <= 0) {
            continue;
        }
        $out[$id] = [
            'id' => $id,
            'label' => function_exists('department_person_option_label') ? department_person_option_label($d) : department_display_name($d),
            'office' => department_display_name($d),
            'prep' => $prep,
            'sort' => (int) ($d['sort_order'] ?? 0),
            '_row' => $d,
        ];
    }
    // تعديل صادر: تبقى جهته الحالية متاحة بقسمها المعدّ حتى لو لم تكن ضمن خيارات المستخدم
    if ($corr) {
        $effective = (int) (correspondence_issuing_from_row($corr) ?? 0);
        $existingDept = (int) ($corr['department_id'] ?? 0);
        if ($effective > 0 && $existingDept > 0) {
            $row = null;
            foreach ($allDepartments as $d) {
                if ((int) ($d['id'] ?? 0) === $effective) {
                    $row = $d;
                    break;
                }
            }
            $out[$effective] = [
                'id' => $effective,
                'label' => $row
                    ? (function_exists('department_person_option_label') ? department_person_option_label($row) : department_display_name($row))
                    : ($out[$effective]['label'] ?? (string) $effective),
                'office' => $row ? department_display_name($row) : ($out[$effective]['office'] ?? ''),
                'prep' => $existingDept,
                'sort' => (int) ($row['sort_order'] ?? 0),
                '_row' => $row ?? ($out[$effective]['_row'] ?? []),
            ];
        }
    }
    foreach ($out as $id => $item) {
        unset($out[$id]['_row']);
    }
    $list = array_values($out);
    usort($list, static fn (array $a, array $b): int => [$a['sort'], $a['label']] <=> [$b['sort'], $b['label']]);
    return $list;
}

/**
 * تحويل جهة التوقيع المختارة إلى (القسم المعدّ، جهة التوقيع أو null إن كانت نفسه).
 * @return array{0:int,1:?int}
 */
function outgoing_resolve_sign_from(?array $user, int $chosenId, ?array $existing = null): array
{
    ensure_correspondence_issuing_schema();
    if ($existing) {
        $exDept = (int) ($existing['department_id'] ?? 0);
        $exEffective = (int) (correspondence_issuing_from_row($existing) ?? 0);
        if ($exDept > 0 && ($chosenId <= 0 || $chosenId === $exEffective)) {
            return [$exDept, ($exEffective > 0 && $exEffective !== $exDept) ? $exEffective : null];
        }
    }
    if ($chosenId <= 0) {
        $primary = function_exists('user_primary_department_id') ? user_primary_department_id($user) : 0;
        if ($primary > 0) {
            return [$primary, null];
        }
        throw new InvalidArgumentException(__('corr.issuing_required'));
    }
    $prep = outgoing_sign_from_prep_for($user, $chosenId);
    if ($prep <= 0) {
        throw new InvalidArgumentException(__('corr.issuing_not_allowed'));
    }
    return [$prep, $prep === $chosenId ? null : $chosenId];
}