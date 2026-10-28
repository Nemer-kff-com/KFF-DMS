<?php
/**
 * تفويض مؤقت (إجازة): إسناد و/أو مسودة لمستخدم آخر بفترة، دون نقل القسم الرئيسي.
 */

function ensure_user_coverage_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        db()->exec(
            "CREATE TABLE IF NOT EXISTS `tbluser_coverage` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `grantor_user_id` INT UNSIGNED NOT NULL,
                `grantee_user_id` INT UNSIGNED NOT NULL,
                `starts_on` DATE NOT NULL,
                `ends_on` DATE NOT NULL,
                `revoked_at` DATETIME NULL DEFAULT NULL,
                `revoked_by` INT UNSIGNED NULL DEFAULT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_cov_grantor` (`grantor_user_id`, `revoked_at`),
                KEY `idx_cov_grantee` (`grantee_user_id`, `revoked_at`, `starts_on`, `ends_on`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        db()->exec(
            "CREATE TABLE IF NOT EXISTS `tbluser_coverage_items` (
                `coverage_id` INT UNSIGNED NOT NULL,
                `department_id` INT UNSIGNED NOT NULL,
                `grant_assign` TINYINT(1) NOT NULL DEFAULT 0,
                `grant_draft` TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`coverage_id`, `department_id`),
                KEY `idx_cov_item_dept` (`department_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    } catch (Throwable $e) {
        error_log('DMS user coverage schema: ' . $e->getMessage());
    }
}

function user_coverage_today(): string
{
    return date('Y-m-d');
}

function user_coverage_is_active_row(array $row, ?string $today = null): bool
{
    if (!empty($row['revoked_at'])) {
        return false;
    }
    $today = $today ?: user_coverage_today();
    $start = (string) ($row['starts_on'] ?? '');
    $end = (string) ($row['ends_on'] ?? '');
    return $start !== '' && $end !== '' && $start <= $today && $end >= $today;
}

function user_coverage_primary_department_id(int $userId): int
{
    if ($userId <= 0) {
        return 0;
    }
    try {
        $st = db()->prepare('SELECT id, department_id FROM `tblusers` WHERE id = ? LIMIT 1');
        $st->execute([$userId]);
        $row = $st->fetch() ?: null;
    } catch (Throwable $e) {
        return 0;
    }
    if (!$row) {
        return 0;
    }
    if (function_exists('user_primary_department_id')) {
        return user_primary_department_id($row);
    }
    return (int) ($row['department_id'] ?? 0);
}

/** القسم الرئيسي فقط — لا سلسلة تفويض. @return list<int> */
function user_coverage_grantable_assign_ids(int $userId): array
{
    $primary = user_coverage_primary_department_id($userId);
    return $primary > 0 ? [$primary] : [];
}

/** المسودة للقسم الرئيسي، مستقلة عن مكتب التوقيع. @return list<int> */
function user_coverage_grantable_draft_ids(int $userId): array
{
    return user_coverage_grantable_assign_ids($userId);
}

function user_coverage_user_is_admin(array $user): bool
{
    return ($user['role'] ?? '') === 'admin';
}

function user_coverage_can_grant(int $userId): bool
{
    return user_coverage_grantable_assign_ids($userId) !== []
        || user_coverage_grantable_draft_ids($userId) !== [];
}

/**
 * @return list<int>
 */
function user_coverage_active_department_ids(int $granteeId, string $flag): array
{
    ensure_user_coverage_schema();
    if ($granteeId <= 0 || !in_array($flag, ['grant_assign', 'grant_draft'], true)) {
        return [];
    }
    try {
        $st = db()->prepare(
            "SELECT DISTINCT i.department_id
             FROM `tbluser_coverage` c
             INNER JOIN `tbluser_coverage_items` i ON i.coverage_id = c.id
             INNER JOIN `tbldepartments` d ON d.id = i.department_id AND d.is_active = 1
             INNER JOIN `tblusers` u ON u.id = c.grantor_user_id AND u.is_active = 1
             WHERE c.grantee_user_id = ?
               AND c.revoked_at IS NULL
               AND c.starts_on <= CURDATE()
               AND c.ends_on >= CURDATE()
               AND i.`{$flag}` = 1"
        );
        $st->execute([$granteeId]);
        return array_values(array_unique(array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: [])));
    } catch (Throwable $e) {
        return [];
    }
}

/** @return list<int> */
function user_coverage_assign_department_ids(int $userId): array
{
    return user_coverage_active_department_ids($userId, 'grant_assign');
}

/** @return list<int> */
function user_coverage_draft_office_ids(int $userId): array
{
    return user_coverage_active_department_ids($userId, 'grant_draft');
}

/** المفوَّض النشط بمسودة لهذا القسم (واحد). 0 إن لم يوجد. */
function user_coverage_active_draft_grantee_id(int $deptId): int
{
    ensure_user_coverage_schema();
    if ($deptId <= 0) {
        return 0;
    }
    try {
        $st = db()->prepare(
            "SELECT c.grantee_user_id
             FROM `tbluser_coverage` c
             INNER JOIN `tbluser_coverage_items` i ON i.coverage_id = c.id
             INNER JOIN `tblusers` u ON u.id = c.grantee_user_id AND u.is_active = 1
             WHERE i.department_id = ?
               AND i.grant_draft = 1
               AND c.revoked_at IS NULL
               AND c.starts_on <= CURDATE()
               AND c.ends_on >= CURDATE()
             ORDER BY c.starts_on DESC, c.id DESC
             LIMIT 1"
        );
        $st->execute([$deptId]);
        return (int) $st->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

/**
 * @return list<array>
 */
function user_coverage_load_items(int $coverageId): array
{
    if ($coverageId <= 0) {
        return [];
    }
    try {
        $st = db()->prepare(
            'SELECT department_id, grant_assign, grant_draft FROM `tbluser_coverage_items` WHERE coverage_id = ? ORDER BY department_id'
        );
        $st->execute([$coverageId]);
        return $st->fetchAll() ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * @return list<array>
 */
function user_coverage_list_for(int $userId, string $role): array
{
    ensure_user_coverage_schema();
    if ($userId <= 0 || !in_array($role, ['grantor', 'grantee'], true)) {
        return [];
    }
    $col = $role === 'grantor' ? 'grantor_user_id' : 'grantee_user_id';
    try {
        $st = db()->prepare(
            "SELECT * FROM `tbluser_coverage` WHERE `{$col}` = ? ORDER BY created_at DESC, id DESC"
        );
        $st->execute([$userId]);
        $rows = $st->fetchAll() ?: [];
    } catch (Throwable $e) {
        return [];
    }
    foreach ($rows as &$row) {
        $row['items'] = user_coverage_load_items((int) $row['id']);
        $row['status'] = user_coverage_status($row);
    }
    unset($row);
    return $rows;
}

function user_coverage_status(array $row): string
{
    if (!empty($row['revoked_at'])) {
        return 'revoked';
    }
    $today = user_coverage_today();
    $start = (string) ($row['starts_on'] ?? '');
    $end = (string) ($row['ends_on'] ?? '');
    if ($end !== '' && $end < $today) {
        return 'ended';
    }
    if ($start !== '' && $start > $today) {
        return 'scheduled';
    }
    return 'active';
}

function user_coverage_find(int $id): ?array
{
    ensure_user_coverage_schema();
    if ($id <= 0) {
        return null;
    }
    try {
        $st = db()->prepare('SELECT * FROM `tbluser_coverage` WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch() ?: null;
        if (!$row) {
            return null;
        }
        $row['items'] = user_coverage_load_items((int) $row['id']);
        $row['status'] = user_coverage_status($row);
        return $row;
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * @param list<int> $assignIds
 * @param list<int> $draftIds
 */
function user_coverage_create(int $grantorId, int $granteeId, string $startsOn, string $endsOn, array $assignIds, array $draftIds): int
{
    ensure_user_coverage_schema();
    if ($grantorId <= 0 || $granteeId <= 0 || $grantorId === $granteeId) {
        throw new InvalidArgumentException(__('coverage.invalid_user'));
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startsOn) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endsOn)) {
        throw new InvalidArgumentException(__('coverage.invalid_dates'));
    }
    if ($endsOn < $startsOn) {
        throw new InvalidArgumentException(__('coverage.dates_order'));
    }
    if ($endsOn < user_coverage_today()) {
        throw new InvalidArgumentException(__('coverage.ended_already'));
    }

    $st = db()->prepare('SELECT id, is_active, role FROM `tblusers` WHERE id = ? LIMIT 1');
    $st->execute([$granteeId]);
    $target = $st->fetch() ?: null;
    if (!$target || (int) ($target['is_active'] ?? 0) !== 1 || user_coverage_user_is_admin($target)) {
        throw new InvalidArgumentException(__('coverage.invalid_user'));
    }

    $canAssign = array_fill_keys(user_coverage_grantable_assign_ids($grantorId), true);
    $canDraft = array_fill_keys(user_coverage_grantable_draft_ids($grantorId), true);
    $items = [];
    foreach ($assignIds as $id) {
        $id = (int) $id;
        if ($id > 0 && isset($canAssign[$id])) {
            $items[$id]['assign'] = true;
        }
    }
    foreach ($draftIds as $id) {
        $id = (int) $id;
        if ($id > 0 && isset($canDraft[$id])) {
            $items[$id]['draft'] = true;
        }
    }
    if ($items === []) {
        throw new InvalidArgumentException(__('coverage.need_flag'));
    }

    db()->prepare(
        'INSERT INTO `tbluser_coverage` (grantor_user_id, grantee_user_id, starts_on, ends_on)
         VALUES (?, ?, ?, ?)'
    )->execute([$grantorId, $granteeId, $startsOn, $endsOn]);
    $cid = (int) db()->lastInsertId();
    $ins = db()->prepare(
        'INSERT INTO `tbluser_coverage_items` (coverage_id, department_id, grant_assign, grant_draft)
         VALUES (?, ?, ?, ?)'
    );
    foreach ($items as $deptId => $flags) {
        $ins->execute([
            $cid,
            (int) $deptId,
            empty($flags['assign']) ? 0 : 1,
            empty($flags['draft']) ? 0 : 1,
        ]);
    }
    user_coverage_notify($cid, 'granted');
    return $cid;
}

function user_coverage_revoke(int $coverageId, int $actorId): void
{
    ensure_user_coverage_schema();
    $row = user_coverage_find($coverageId);
    if (!$row) {
        throw new InvalidArgumentException(__('coverage.not_found'));
    }
    if ((int) $row['grantor_user_id'] !== $actorId) {
        throw new InvalidArgumentException(__('coverage.cannot_revoke'));
    }
    if (!empty($row['revoked_at'])) {
        return;
    }
    db()->prepare('UPDATE `tbluser_coverage` SET revoked_at = NOW(), revoked_by = ? WHERE id = ?')
        ->execute([$actorId > 0 ? $actorId : null, $coverageId]);
    user_coverage_notify($coverageId, 'revoked');
}

function user_coverage_notify(int $coverageId, string $kind): void
{
    if (!function_exists('dms_notify_users')) {
        return;
    }
    $row = user_coverage_find($coverageId);
    if (!$row) {
        return;
    }
    $grantee = (int) ($row['grantee_user_id'] ?? 0);
    if ($grantee <= 0) {
        return;
    }
    $flags = [];
    foreach ((array) ($row['items'] ?? []) as $item) {
        if (!empty($item['grant_assign'])) {
            $flags['assign'] = __('users.dept_col_assign');
        }
        if (!empty($item['grant_draft'])) {
            $flags['draft'] = __('users.dept_col_draft');
        }
    }
    $body = str_replace(
        [':from', ':to', ':flags'],
        [(string) ($row['starts_on'] ?? ''), (string) ($row['ends_on'] ?? ''), implode(' · ', array_values($flags))],
        $kind === 'revoked' ? __('coverage.notify_revoked_body') : __('coverage.notify_granted_body')
    );
    $title = $kind === 'revoked' ? __('coverage.notify_revoked_title') : __('coverage.notify_granted_title');
    dms_notify_users([$grantee], [
        'type' => 'user_coverage',
        'title' => $title,
        'body' => $body,
        'link' => 'auth/profile.php',
        'dedupe_key' => 'coverage-' . $kind . '-' . $coverageId,
        'email' => true,
        'email_intro' => $title,
        'email_details' => $body,
        'email_cta' => __('coverage.open_profile'),
    ]);
}
