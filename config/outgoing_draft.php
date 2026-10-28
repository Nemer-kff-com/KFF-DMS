<?php
/**
 * مسودة الصادر: حفظ دون رقم، ثم موافقة صاحب الشأن في «موجهة من»، ثم مسار الاعتماد الحالي.
 */

function ensure_outgoing_prep_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $cols = [
            'outgoing_prep_status' => "VARCHAR(20) NULL DEFAULT NULL COMMENT 'draft|pending_signer' AFTER intend_electronic",
            'outgoing_prep_note' => 'TEXT NULL DEFAULT NULL AFTER outgoing_prep_status',
            'outgoing_prep_decided_by' => 'INT UNSIGNED NULL DEFAULT NULL AFTER outgoing_prep_note',
            'outgoing_prep_decided_at' => 'DATETIME NULL DEFAULT NULL AFTER outgoing_prep_decided_by',
            'outgoing_prep_root_id' => 'INT UNSIGNED NULL DEFAULT NULL COMMENT \'أول مسودة في سلسلة المعاملة\' AFTER outgoing_prep_decided_at',
        ];
        foreach ($cols as $name => $ddl) {
            if (!dms_db_column_exists('tblcorrespondence', $name)) {
                db()->exec('ALTER TABLE `tblcorrespondence` ADD COLUMN `' . $name . '` ' . $ddl);
            }
        }
        if (function_exists('dms_ensure_index')) {
            dms_ensure_index('tblcorrespondence', 'idx_corr_out_prep', '(`type`, `outgoing_prep_status`)');
            dms_ensure_index('tblcorrespondence', 'idx_corr_out_prep_root', '(`outgoing_prep_root_id`)');
        }
        ensure_outgoing_prep_authority_schema();
        if (dms_db_column_exists('tblcorrespondence', 'outgoing_prep_root_id')) {
            outgoing_prep_backfill_returned_families();
        }
    } catch (Throwable $e) {
        error_log('DMS outgoing prep schema: ' . $e->getMessage());
    }
}

function ensure_outgoing_prep_authority_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        if (function_exists('dms_db_column_exists') && !dms_db_column_exists('tbldepartments', 'principal_user_id')) {
            db()->exec(
                "ALTER TABLE `tbldepartments`
                 ADD COLUMN `principal_user_id` INT UNSIGNED NULL DEFAULT NULL
                 COMMENT 'صاحب الشأن'
                 AFTER `job_title`"
            );
        }
        db()->exec(
            "CREATE TABLE IF NOT EXISTS `tbloutgoing_prep_delegates` (
                `department_id` INT UNSIGNED NOT NULL,
                `user_id` INT UNSIGNED NOT NULL,
                `granted_by` INT UNSIGNED NULL DEFAULT NULL,
                `granted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`department_id`, `user_id`),
                KEY `idx_prep_del_user` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    } catch (Throwable $e) {
        error_log('DMS outgoing prep authority schema: ' . $e->getMessage());
    }
}

function outgoing_prep_status_of(array $corr): string
{
    ensure_outgoing_prep_schema();

    return trim((string) ($corr['outgoing_prep_status'] ?? ''));
}

function outgoing_prep_enabled(): bool
{
    return setting('outgoing_prep_enabled', '1') === '1';
}

function outgoing_prep_is_open(array $corr): bool
{
    if (($corr['type'] ?? '') !== 'outgoing') {
        return false;
    }
    return in_array(outgoing_prep_status_of($corr), ['draft', 'pending_signer'], true);
}

function outgoing_prep_is_superseded(array $corr): bool
{
    return ($corr['type'] ?? '') === 'outgoing' && outgoing_prep_status_of($corr) === 'superseded';
}

function outgoing_prep_is_draft(array $corr): bool
{
    return ($corr['type'] ?? '') === 'outgoing' && outgoing_prep_status_of($corr) === 'draft';
}

function outgoing_prep_is_pending_signer(array $corr): bool
{
    return ($corr['type'] ?? '') === 'outgoing' && outgoing_prep_status_of($corr) === 'pending_signer';
}

/** مسودة أُرسلت لصاحب الشأن ثم أُعيدت للمُعدّ. */
function outgoing_prep_was_returned(array $corr): bool
{
    if (!outgoing_prep_is_draft($corr)) {
        return false;
    }
    if (trim((string) ($corr['outgoing_prep_note'] ?? '')) !== '') {
        return true;
    }

    return (int) ($corr['outgoing_prep_decided_by'] ?? 0) > 0;
}

function outgoing_prep_family_id(array $corr): int
{
    $root = (int) ($corr['outgoing_prep_root_id'] ?? 0);

    return $root > 0 ? $root : (int) ($corr['id'] ?? 0);
}

function outgoing_prep_latest_id(array $corr): int
{
    $root = outgoing_prep_family_id($corr);
    if ($root <= 0) {
        return 0;
    }
    $st = db()->prepare(
        "SELECT id FROM `tblcorrespondence`
         WHERE type = 'outgoing'
           AND (id = ? OR outgoing_prep_root_id = ?)
           AND (outgoing_prep_status IS NULL OR outgoing_prep_status <> 'superseded')
         ORDER BY id DESC
         LIMIT 1"
    );
    $st->execute([$root, $root]);
    $id = (int) $st->fetchColumn();

    return $id > 0 ? $id : $root;
}

/** @return list<array<string,mixed>> */
function outgoing_prep_previous_rows(array $corr): array
{
    $root = outgoing_prep_family_id($corr);
    $self = (int) ($corr['id'] ?? 0);
    if ($root <= 0) {
        return [];
    }
    $st = db()->prepare(
        "SELECT id, ref_number, subject, outgoing_prep_status, outgoing_prep_note, outgoing_prep_decided_at, created_at
         FROM `tblcorrespondence`
         WHERE type = 'outgoing'
           AND outgoing_prep_status = 'superseded'
           AND (id = ? OR outgoing_prep_root_id = ?)
           AND id <> ?
         ORDER BY id"
    );
    $st->execute([$root, $root, $self]);
    $rows = $st->fetchAll();

    return is_array($rows) ? $rows : [];
}

function outgoing_user_can_view_superseded_prep(?array $user, array $corr): bool
{
    $user = $user ?? (function_exists('current_user') ? current_user() : null);
    if (!$user) {
        return false;
    }
    if (($user['role'] ?? '') === 'admin') {
        return true;
    }
    if (outgoing_user_is_prep_creator($user, $corr)) {
        return true;
    }
    $decidedBy = (int) ($corr['outgoing_prep_decided_by'] ?? 0);
    if ($decidedBy > 0 && $decidedBy === (int) ($user['id'] ?? 0)) {
        return true;
    }

    return outgoing_user_is_prep_signer($user, $corr);
}

function outgoing_prep_active_sql(string $alias = 'c'): string
{
    $a = preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $alias) ? $alias : 'c';
    return "{$a}.outgoing_prep_status IN ('draft','pending_signer')";
}

function outgoing_prep_exclude_sql(string $alias = 'c'): string
{
    $a = preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $alias) ? $alias : 'c';
    return "({$a}.outgoing_prep_status IS NULL OR {$a}.outgoing_prep_status NOT IN ('draft','pending_signer','superseded'))";
}

function outgoing_issuing_department_id(array $corr): int
{
    $id = (int) ($corr['issuing_department_id'] ?? 0);
    return $id > 0 ? $id : (int) ($corr['department_id'] ?? 0);
}

function department_principal_user_id(int $deptId): int
{
    ensure_outgoing_prep_authority_schema();
    if ($deptId <= 0) {
        return 0;
    }
    try {
        $st = db()->prepare('SELECT principal_user_id FROM `tbldepartments` WHERE id = ? LIMIT 1');
        $st->execute([$deptId]);
        return (int) $st->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

/** @return list<int> */
function outgoing_prep_delegate_ids(int $deptId): array
{
    ensure_outgoing_prep_authority_schema();
    if ($deptId <= 0) {
        return [];
    }
    try {
        $st = db()->prepare(
            'SELECT d.user_id FROM `tbloutgoing_prep_delegates` d
             INNER JOIN `tblusers` u ON u.id = d.user_id AND u.is_active = 1
             WHERE d.department_id = ?
             ORDER BY d.user_id'
        );
        $st->execute([$deptId]);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
    } catch (Throwable $e) {
        return [];
    }
}

/** @return list<int> */
function outgoing_prep_principal_office_ids(int $userId): array
{
    ensure_outgoing_prep_authority_schema();
    if ($userId <= 0) {
        return [];
    }
    try {
        $st = db()->prepare(
            'SELECT id FROM `tbldepartments` WHERE principal_user_id = ? AND is_active = 1 ORDER BY sort_order, id'
        );
        $st->execute([$userId]);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
    } catch (Throwable $e) {
        return [];
    }
}

/** @return list<int> */
function outgoing_prep_user_delegate_office_ids(int $userId): array
{
    ensure_outgoing_prep_authority_schema();
    if ($userId <= 0) {
        return [];
    }
    try {
        $st = db()->prepare(
            'SELECT department_id FROM `tbloutgoing_prep_delegates` WHERE user_id = ? ORDER BY department_id'
        );
        $st->execute([$userId]);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * @param list<int> $userIds
 * @return array<int, list<array{id:int,name:string,name_en:string}>>
 */
function outgoing_prep_users_delegate_map(array $userIds): array
{
    $userIds = function_exists('dms_normalize_id_list') ? dms_normalize_id_list($userIds) : array_values(array_unique(array_filter(array_map('intval', $userIds))));
    if (!$userIds) {
        return [];
    }
    ensure_outgoing_prep_authority_schema();
    try {
        [$in, $params] = dms_sql_in('pd.user_id', $userIds);
        $stmt = db()->prepare(
            "SELECT pd.user_id, pd.department_id, d.name, d.name_en
             FROM `tbloutgoing_prep_delegates` pd
             INNER JOIN `tbldepartments` d ON d.id = pd.department_id
             WHERE {$in}
             ORDER BY d.sort_order, d.name, d.id"
        );
        $stmt->execute($params);
        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $uid = (int) $row['user_id'];
            $map[$uid][] = [
                'id' => (int) $row['department_id'],
                'name' => (string) ($row['name'] ?? ''),
                'name_en' => (string) ($row['name_en'] ?? ''),
            ];
        }
        return $map;
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * @param list<int> $userIds
 * @return array<int, list<array{id:int,name:string,name_en:string}>>
 */
function outgoing_prep_users_principal_map(array $userIds): array
{
    $userIds = function_exists('dms_normalize_id_list') ? dms_normalize_id_list($userIds) : array_values(array_unique(array_filter(array_map('intval', $userIds))));
    if (!$userIds) {
        return [];
    }
    ensure_outgoing_prep_authority_schema();
    try {
        [$in, $params] = dms_sql_in('d.principal_user_id', $userIds);
        $stmt = db()->prepare(
            "SELECT d.principal_user_id AS user_id, d.id AS department_id, d.name, d.name_en
             FROM `tbldepartments` d
             WHERE {$in} AND d.is_active = 1
             ORDER BY d.sort_order, d.name, d.id"
        );
        $stmt->execute($params);
        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $uid = (int) $row['user_id'];
            $map[$uid][] = [
                'id' => (int) $row['department_id'],
                'name' => (string) ($row['name'] ?? ''),
                'name_en' => (string) ($row['name_en'] ?? ''),
            ];
        }
        return $map;
    } catch (Throwable $e) {
        return [];
    }
}

/** مستلم المسودة لهذا المكتب: مفوَّض الإجازة، وإلا صاحب الشأن، وإلا أول مخوَّل بمسودة. */
function outgoing_prep_office_designated_user_id(int $deptId): int
{
    if ($deptId <= 0) {
        return 0;
    }
    if (function_exists('user_coverage_active_draft_grantee_id')) {
        $cover = user_coverage_active_draft_grantee_id($deptId);
        if ($cover > 0) {
            return $cover;
        }
    }
    $principal = department_principal_user_id($deptId);
    if ($principal > 0) {
        return $principal;
    }
    $delegates = outgoing_prep_delegate_ids($deptId);
    return $delegates[0] ?? 0;
}

function outgoing_prep_designated_signer_id(array $corr): int
{
    return outgoing_prep_office_designated_user_id(outgoing_issuing_department_id($corr));
}

/** مكاتب يُوجَّه إليها هذا المستخدم كمستلم وحيد للمسودة. @return list<int> */
function outgoing_prep_user_signer_office_ids(?array $user): array
{
    $uid = (int) ($user['id'] ?? 0);
    if ($uid <= 0) {
        return [];
    }
    $ids = [];
    foreach (outgoing_prep_principal_office_ids($uid) as $oid) {
        if (outgoing_prep_office_designated_user_id($oid) === $uid) {
            $ids[] = $oid;
        }
    }
    if (function_exists('user_coverage_draft_office_ids')) {
        $ids = array_merge($ids, user_coverage_draft_office_ids($uid));
    }
    foreach (outgoing_prep_user_delegate_office_ids($uid) as $oid) {
        if (outgoing_prep_office_designated_user_id($oid) === $uid) {
            $ids[] = $oid;
        }
    }
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    sort($ids);
    return $ids;
}

function outgoing_prep_save_delegates(int $deptId, array $userIds, int $grantedBy = 0): void
{
    ensure_outgoing_prep_authority_schema();
    if ($deptId <= 0) {
        return;
    }
    $principal = department_principal_user_id($deptId);
    $ids = [];
    foreach ($userIds as $id) {
        $id = (int) $id;
        if ($id > 0 && $id !== $principal) {
            $ids[$id] = $id;
        }
    }
    $ids = array_values($ids);
    db()->prepare('DELETE FROM `tbloutgoing_prep_delegates` WHERE department_id = ?')->execute([$deptId]);
    if ($ids === []) {
        return;
    }
    $ins = db()->prepare(
        'INSERT INTO `tbloutgoing_prep_delegates` (department_id, user_id, granted_by, granted_at)
         VALUES (?, ?, ?, NOW())'
    );
    $by = $grantedBy > 0 ? $grantedBy : null;
    foreach ($ids as $id) {
        $ins->execute([$deptId, $id, $by]);
    }
}

function outgoing_prep_replace_user_delegate_offices(int $userId, array $officeIds, int $grantedBy = 0): void
{
    ensure_outgoing_prep_authority_schema();
    if ($userId <= 0) {
        return;
    }
    $wanted = [];
    foreach ($officeIds as $id) {
        $id = (int) $id;
        if ($id > 0 && department_principal_user_id($id) !== $userId) {
            $wanted[$id] = $id;
        }
    }
    db()->prepare('DELETE FROM `tbloutgoing_prep_delegates` WHERE user_id = ?')->execute([$userId]);
    if ($wanted === []) {
        return;
    }
    $ins = db()->prepare(
        'INSERT INTO `tbloutgoing_prep_delegates` (department_id, user_id, granted_by, granted_at)
         VALUES (?, ?, ?, NOW())'
    );
    $by = $grantedBy > 0 ? $grantedBy : null;
    foreach ($wanted as $deptId) {
        $ins->execute([$deptId, $userId, $by]);
    }
}

/** يجعل هذا المستخدم صاحب الشأن في المكاتب المحددة، ويزيلها عما سواها. @param list<int> $officeIds */
function user_apply_principal_offices(int $userId, array $officeIds): void
{
    ensure_outgoing_prep_authority_schema();
    if ($userId <= 0) {
        return;
    }
    $wanted = [];
    foreach ($officeIds as $id) {
        $id = (int) $id;
        if ($id > 0) {
            $wanted[$id] = $id;
        }
    }
    $pdo = db();
    $cur = $pdo->prepare('SELECT id FROM `tbldepartments` WHERE principal_user_id = ?');
    $cur->execute([$userId]);
    $current = array_map('intval', $cur->fetchAll(PDO::FETCH_COLUMN) ?: []);
    $drop = array_values(array_diff($current, $wanted));
    if ($drop !== []) {
        $marks = implode(',', array_fill(0, count($drop), '?'));
        $pdo->prepare(
            "UPDATE `tbldepartments` SET principal_user_id = NULL WHERE principal_user_id = ? AND id IN ($marks)"
        )->execute(array_merge([$userId], $drop));
    }
    if ($wanted !== []) {
        $set = $pdo->prepare('UPDATE `tbldepartments` SET principal_user_id = ? WHERE id = ?');
        foreach ($wanted as $deptId) {
            $set->execute([$userId, $deptId]);
        }
    }
    outgoing_prep_sync_principal_display_name($userId);
}

/** المستخدم النشط الوحيد الذي قسمه الرئيسي هو هذا القسم، وإلا 0. */
function department_sole_home_user_id(int $deptId): int
{
    if ($deptId <= 0) {
        return 0;
    }
    if (function_exists('ensure_user_departments_schema')) {
        ensure_user_departments_schema();
    }
    try {
        $st = db()->prepare(
            'SELECT DISTINCT u.id
             FROM `tblusers` u
             LEFT JOIN `tbluser_departments` ud
               ON ud.user_id = u.id AND ud.department_id = ? AND ud.is_primary = 1
             WHERE u.is_active = 1
               AND (u.department_id = ? OR ud.user_id IS NOT NULL)
             ORDER BY u.id'
        );
        $st->execute([$deptId, $deptId]);
        $ids = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
    } catch (Throwable $e) {
        return 0;
    }
    return count($ids) === 1 ? $ids[0] : 0;
}

/** إن لم يُحدَّد صاحب الشأن، يُؤخذ من المستخدم المعيَّن رئيسياً على القسم. */
function department_apply_missing_principal(int $deptId): int
{
    if ($deptId <= 0) {
        return 0;
    }
    ensure_outgoing_prep_authority_schema();
    try {
        $cur = db()->prepare('SELECT principal_user_id FROM `tbldepartments` WHERE id = ? LIMIT 1');
        $cur->execute([$deptId]);
        $existing = (int) ($cur->fetchColumn() ?: 0);
    } catch (Throwable $e) {
        return 0;
    }
    if ($existing > 0) {
        return $existing;
    }
    $uid = department_sole_home_user_id($deptId);
    if ($uid <= 0) {
        return 0;
    }
    db()->prepare(
        'UPDATE `tbldepartments` SET principal_user_id = ? WHERE id = ? AND (principal_user_id IS NULL OR principal_user_id = 0)'
    )->execute([$uid, $deptId]);
    outgoing_prep_sync_principal_display_name($uid);
    return $uid;
}

function departments_apply_missing_principals(): void
{
    ensure_outgoing_prep_authority_schema();
    try {
        $ids = db()->query(
            'SELECT id FROM `tbldepartments` WHERE principal_user_id IS NULL OR principal_user_id = 0'
        )->fetchAll(PDO::FETCH_COLUMN) ?: [];
    } catch (Throwable $e) {
        return;
    }
    foreach ($ids as $id) {
        department_apply_missing_principal((int) $id);
    }
}

/**
 * حفظ «الإصدار باسم المكتب» لمكاتب هذا المستخدم فقط.
 * بدون مكاتب محددة لا يظهر المكتب لغيره.
 *
 * @param list<int> $enabledOfficeIds
 * @param array<int|string, list<int|string>> $accessByOffice
 */
function user_apply_signing_for_principal(int $userId, array $enabledOfficeIds, array $accessByOffice): void
{
    if ($userId <= 0 || !function_exists('department_signing_access_save')) {
        return;
    }
    ensure_departments_signing_office_schema();
    $pdo = db();
    $cur = $pdo->prepare('SELECT id FROM `tbldepartments` WHERE principal_user_id = ?');
    $cur->execute([$userId]);
    $owned = array_map('intval', $cur->fetchAll(PDO::FETCH_COLUMN) ?: []);
    $enabled = [];
    foreach ($enabledOfficeIds as $id) {
        $id = (int) $id;
        if ($id > 0) {
            $enabled[$id] = $id;
        }
    }
    $upd = $pdo->prepare('UPDATE `tbldepartments` SET is_signing_office = ? WHERE id = ? AND principal_user_id = ?');
    foreach ($owned as $deptId) {
        $on = isset($enabled[$deptId]);
        $upd->execute([$on ? 1 : 0, $deptId, $userId]);
        $prep = [];
        if ($on && isset($accessByOffice[$deptId]) && is_array($accessByOffice[$deptId])) {
            $prep = $accessByOffice[$deptId];
        }
        department_signing_access_save($deptId, $on ? $prep : []);
    }
}

function outgoing_prep_sync_principal_display_name(int $userId): void
{
    if ($userId <= 0 || !function_exists('user_display_name')) {
        return;
    }
    ensure_outgoing_prep_authority_schema();
    try {
        $st = db()->prepare('SELECT * FROM `tblusers` WHERE id = ? LIMIT 1');
        $st->execute([$userId]);
        $row = $st->fetch() ?: null;
        if (!$row) {
            return;
        }
        $name = trim((string) ($row['name_ar'] ?? ''));
        if ($name === '') {
            $name = trim((string) ($row['name_en'] ?? ''));
        }
        $prefix = trim((string) ($row['title_prefix'] ?? ''));
        $job = trim((string) ($row['job_title'] ?? ''));
        if ($name !== '') {
            db()->prepare(
                'UPDATE `tbldepartments` SET directed_from = ?, title_prefix = ?, job_title = ? WHERE principal_user_id = ?'
            )->execute([
                $name,
                $prefix !== '' ? $prefix : null,
                $job !== '' ? $job : null,
                $userId,
            ]);
            return;
        }
        db()->prepare(
            'UPDATE `tbldepartments` SET title_prefix = ?, job_title = ? WHERE principal_user_id = ?'
        )->execute([
            $prefix !== '' ? $prefix : null,
            $job !== '' ? $job : null,
            $userId,
        ]);
    } catch (Throwable $e) {
        error_log('DMS prep principal name sync: ' . $e->getMessage());
    }
}

/**
 * @return array{principal_user_id:int,title_prefix:?string,directed_from:?string,job_title:?string}
 */
function outgoing_prep_principal_print_fields(int $principalId, string $jobTitle, string $legacyName, string $legacyTitle = ''): array
{
    $principalId = max(0, $principalId);
    $jobTitle = trim($jobTitle);
    $legacyName = trim($legacyName);
    $legacyTitle = trim($legacyTitle);
    $name = $legacyName;
    $title = $legacyTitle;
    if ($principalId > 0) {
        try {
            $st = db()->prepare('SELECT * FROM `tblusers` WHERE id = ? LIMIT 1');
            $st->execute([$principalId]);
            $row = $st->fetch() ?: null;
            if ($row) {
                $fromUser = trim((string) ($row['name_ar'] ?? ''));
                if ($fromUser === '') {
                    $fromUser = trim((string) ($row['name_en'] ?? ''));
                }
                if ($fromUser !== '') {
                    $name = $fromUser;
                }
                $title = trim((string) ($row['title_prefix'] ?? ''));
                $jobTitle = trim((string) ($row['job_title'] ?? ''));
            }
        } catch (Throwable $e) {
            // الاسم اليدوي إن تعذر الحساب
        }
    }
    return [
        'principal_user_id' => $principalId,
        'title_prefix' => $title !== '' ? $title : null,
        'directed_from' => $name !== '' ? $name : null,
        'job_title' => $jobTitle !== '' ? $jobTitle : null,
    ];
}

/** @return list<int> */
function outgoing_prep_signer_ids(array $corr): array
{
    ensure_outgoing_prep_authority_schema();
    $id = outgoing_prep_designated_signer_id($corr);
    if ($id <= 0) {
        return [];
    }
    if (function_exists('outgoing_active_user_ids')) {
        return outgoing_active_user_ids([$id]);
    }
    return [$id];
}

function outgoing_user_is_prep_signer(?array $user, array $corr): bool
{
    $uid = (int) ($user['id'] ?? 0);
    if ($uid <= 0) {
        return false;
    }
    return in_array($uid, outgoing_prep_signer_ids($corr), true);
}

function outgoing_user_is_prep_creator(?array $user, array $corr): bool
{
    $uid = (int) ($user['id'] ?? 0);
    return $uid > 0 && $uid === (int) ($corr['created_by'] ?? 0);
}

function outgoing_user_can_access_prep(?array $user, array $corr): bool
{
    if (!outgoing_prep_is_open($corr)) {
        return true;
    }
    $user = $user ?? (function_exists('current_user') ? current_user() : null);
    if (!$user) {
        return false;
    }
    if (($user['role'] ?? '') === 'admin') {
        return true;
    }
    if (outgoing_user_is_prep_creator($user, $corr)) {
        return true;
    }
    if (!outgoing_user_is_prep_signer($user, $corr)) {
        return false;
    }
    if (outgoing_prep_is_pending_signer($corr)) {
        return true;
    }
    $decidedBy = (int) ($corr['outgoing_prep_decided_by'] ?? 0);
    return outgoing_prep_is_draft($corr) && $decidedBy > 0 && $decidedBy === (int) ($user['id'] ?? 0);
}

function outgoing_user_can_prep_submit(?array $user, array $corr): bool
{
    if (!outgoing_prep_is_draft($corr) || correspondence_is_cancelled($corr)) {
        return false;
    }
    $user = $user ?? (function_exists('current_user') ? current_user() : null);
    if (!$user) {
        return false;
    }
    if (!user_can('outgoing.edit', $user) && !user_can('outgoing.create', $user)) {
        return false;
    }
    return ($user['role'] ?? '') === 'admin' || outgoing_user_is_prep_creator($user, $corr);
}

function outgoing_user_can_prep_decide(?array $user, array $corr): bool
{
    if (!outgoing_prep_is_pending_signer($corr) || correspondence_is_cancelled($corr)) {
        return false;
    }
    $user = $user ?? (function_exists('current_user') ? current_user() : null);
    if (!$user) {
        return false;
    }
    return ($user['role'] ?? '') === 'admin' || outgoing_user_is_prep_signer($user, $corr);
}

function outgoing_user_may_see_prep_signer_queue(?array $user): bool
{
    $user = $user ?? (function_exists('current_user') ? current_user() : null);
    if (!$user) {
        return false;
    }
    if (($user['role'] ?? '') === 'admin') {
        return true;
    }
    return outgoing_prep_user_signer_office_ids($user) !== [];
}

/** رقم صادر رسمي ظاهر: بعد موافقة المراجع ليطبع المعد الملصق، أو بعد الاعتماد إن لم تكن هناك مراجعة. */
function outgoing_show_official_ref(array $corr): bool
{
    if (($corr['type'] ?? '') !== 'outgoing') {
        return true;
    }
    if (outgoing_prep_is_open($corr) || outgoing_prep_is_superseded($corr)) {
        return false;
    }
    if (function_exists('outgoing_can_issue') && outgoing_can_issue($corr)) {
        return true;
    }

    return outgoing_can_print_thermal($corr);
}

/** الملصق الحراري بعد اكتمال الاعتماد، وتطبعه الاتصالات الإدارية مع ملصق الظرف ونموذج التسليم. */
function outgoing_can_print_thermal(array $corr): bool
{
    if (($corr['type'] ?? '') !== 'outgoing') {
        return true;
    }
    if (function_exists('correspondence_is_cancelled') && correspondence_is_cancelled($corr)) {
        return false;
    }
    if (outgoing_prep_is_open($corr) || outgoing_prep_is_superseded($corr)) {
        return false;
    }
    $ref = trim((string) ($corr['ref_number'] ?? ''));
    if ($ref === '' || str_starts_with($ref, 'OD-') || str_starts_with($ref, 'ODTMP-')) {
        return false;
    }

    return function_exists('outgoing_can_issue') && outgoing_can_issue($corr);
}

function outgoing_qr_hidden_message(): string
{
    return __('out_prep.qr_hidden_dispatch');
}

function outgoing_thermal_wait_message(bool $short = false): string
{
    return $short ? __('out_prep.thermal_after_approval_short') : __('out_prep.thermal_after_approval');
}

/** صدر بلا مرحلة مسودة: مرفقات التسجيل لا تُقفل كمرفقات صاحب الشأن. */
function outgoing_skipped_draft_stage(array $corr): bool
{
    return ($corr['type'] ?? '') === 'outgoing'
        && !outgoing_prep_is_open($corr)
        && !outgoing_prep_is_superseded($corr)
        && (int) ($corr['outgoing_prep_decided_by'] ?? 0) <= 0;
}

/** بعد موافقة صاحب الشأن تُقفل مرفقات المسودة. */
function outgoing_draft_files_locked(array $corr): bool
{
    if (($corr['type'] ?? '') !== 'outgoing') {
        return false;
    }
    if (outgoing_prep_is_open($corr) || outgoing_prep_is_superseded($corr)) {
        return false;
    }
    if (outgoing_skipped_draft_stage($corr)) {
        return false;
    }
    if (outgoing_can_print_thermal($corr)) {
        return true;
    }
    $ref = trim((string) ($corr['ref_number'] ?? ''));

    return function_exists('correspondence_is_cancelled')
        && correspondence_is_cancelled($corr)
        && $ref !== ''
        && !str_starts_with($ref, 'OD-')
        && !str_starts_with($ref, 'ODTMP-');
}

function outgoing_draft_file_kind(): string
{
    return 'draft_approved';
}

function outgoing_issued_copy_kind(): string
{
    return 'issued_copy';
}

/** @return list<string> */
function outgoing_attachment_kind_values(): array
{
    return [outgoing_draft_file_kind(), outgoing_issued_copy_kind()];
}

function outgoing_needs_issued_copy(array $corr): bool
{
    $status = function_exists('outgoing_approval_status_of') ? outgoing_approval_status_of($corr) : '';
    if ($status !== 'pending_copy' || ($corr['type'] ?? '') !== 'outgoing') {
        return false;
    }

    return !(function_exists('correspondence_intend_electronic') && correspondence_intend_electronic($corr));
}

/** الطباعة التي تحمل الرقم تكون بعد الاعتماد لدى الاتصالات الإدارية، فلا يُحبس الصادر عند المعد بانتظارها. */
function outgoing_review_pass_waits_for_copy(array $corr): bool
{
    return false;
}

function outgoing_has_any_attachment(int $corrId): bool
{
    if ($corrId <= 0) {
        return false;
    }
    $st = db()->prepare('SELECT 1 FROM `tbldocuments` WHERE correspondence_id = ? LIMIT 1');
    $st->execute([$corrId]);

    return (bool) $st->fetchColumn();
}

/** الصادر الورقي لا يُرسل للمراجعة قبل رفع مرفق. */
function outgoing_request_missing_attachment(array $corr): bool
{
    if (($corr['type'] ?? '') !== 'outgoing') {
        return false;
    }
    if (function_exists('correspondence_intend_electronic') && correspondence_intend_electronic($corr)) {
        return false;
    }
    if (function_exists('outgoing_prep_is_open') && outgoing_prep_is_open($corr)) {
        return false;
    }
    if (function_exists('outgoing_prep_is_superseded') && outgoing_prep_is_superseded($corr)) {
        return false;
    }
    $status = function_exists('outgoing_approval_status_of') ? outgoing_approval_status_of($corr) : '';
    if (!in_array($status, ['', 'returned', 'rejected'], true)) {
        return false;
    }

    return !outgoing_has_any_attachment((int) ($corr['id'] ?? 0));
}

function outgoing_has_issued_copy(int $corrId): bool
{
    if ($corrId <= 0) {
        return false;
    }
    if (function_exists('ensure_documents_doc_kind_schema')) {
        ensure_documents_doc_kind_schema();
    }
    $st = db()->prepare(
        'SELECT 1 FROM `tbldocuments` WHERE correspondence_id = ? AND doc_kind = ? LIMIT 1'
    );
    $st->execute([$corrId, outgoing_issued_copy_kind()]);

    return (bool) $st->fetchColumn();
}

function outgoing_issued_copy_mutable(array $corr): bool
{
    if (!outgoing_needs_issued_copy($corr)) {
        return false;
    }
    $status = function_exists('outgoing_approval_status_of') ? outgoing_approval_status_of($corr) : '';
    if ($status === 'approved') {
        return false;
    }
    if ($status === 'pending_copy') {
        return true;
    }
    $hasCopy = outgoing_has_issued_copy((int) ($corr['id'] ?? 0));
    if (!$hasCopy) {
        return true;
    }
    if ($status === 'pending_review') {
        return false;
    }
    if (in_array($status, ['pending_sg', 'pending_deputy', 'pending_alternate'], true)) {
        return false;
    }
    if (function_exists('outgoing_reviewer_has_passed') && outgoing_reviewer_has_passed($corr)) {
        return false;
    }

    return true;
}

function outgoing_user_can_manage_issued_copy(?array $user, array $corr): bool
{
    $user = $user ?? (function_exists('current_user') ? current_user() : null);
    if (!$user) {
        return false;
    }
    if (($user['role'] ?? '') === 'admin') {
        return true;
    }

    return outgoing_user_is_prep_creator($user, $corr);
}

function outgoing_stamp_draft_files(int $corrId): void
{
    if ($corrId <= 0) {
        return;
    }
    if (function_exists('ensure_documents_doc_kind_schema')) {
        ensure_documents_doc_kind_schema();
    }
    db()->prepare(
        "UPDATE `tbldocuments`
         SET doc_kind = ?
         WHERE correspondence_id = ? AND (doc_kind IS NULL OR doc_kind = '')"
    )->execute([outgoing_draft_file_kind(), $corrId]);
}

function outgoing_ensure_draft_files_stamped(array $corr): void
{
    if (!outgoing_draft_files_locked($corr)) {
        return;
    }
    outgoing_stamp_draft_files((int) ($corr['id'] ?? 0));
}

function outgoing_require_issued_copy(array $corr): void
{
    if (!outgoing_needs_issued_copy($corr)) {
        return;
    }
    outgoing_ensure_draft_files_stamped($corr);
    if (outgoing_has_issued_copy((int) ($corr['id'] ?? 0))) {
        return;
    }
    throw new RuntimeException(__('out_prep.issued_copy_required'));
}

function outgoing_prep_list_label(array $corr): string
{
    $st = outgoing_prep_status_of($corr);
    if ($st === 'draft') {
        return __('out_prep.list_draft');
    }
    if ($st === 'pending_signer') {
        return __('out_prep.list_pending_signer');
    }
    if ($st === 'superseded') {
        return __('out_prep.list_superseded');
    }
    if (($corr['type'] ?? '') === 'outgoing' && function_exists('outgoing_can_issue') && !outgoing_can_issue($corr)) {
        return __('out_prep.list_pending_issue');
    }
    return format_ref_number((string) ($corr['ref_number'] ?? ''));
}

function correspondence_ref_display(array $row): string
{
    if (($row['type'] ?? '') === 'outgoing' && !outgoing_show_official_ref($row)) {
        return outgoing_prep_list_label($row);
    }
    return format_ref_number((string) ($row['ref_number'] ?? ''));
}

function correspondence_ref_caption_safe(array $corr): string
{
    if (($corr['type'] ?? '') === 'outgoing' && !outgoing_show_official_ref($corr)) {
        return outgoing_prep_list_label($corr);
    }
    return format_ref_caption((string) ($corr['type'] ?? ''), $corr['ref_number'] ?? '');
}

function outgoing_prep_email_rows(array $corr): array
{
    $rows = [];
    $subject = trim((string) ($corr['subject'] ?? ''));
    if ($subject !== '') {
        $rows[] = ['label' => __('corr.subject'), 'value' => $subject];
    }
    $party = trim((string) ($corr['party_name'] ?? ''));
    if ($party !== '') {
        $rows[] = ['label' => __('corr.party_to'), 'value' => $party];
    }
    $from = '';
    if (function_exists('department_display_name')) {
        $from = department_display_name($corr, 'issuing_department_name', 'issuing_department_name_en');
    }
    if ($from === '') {
        $from = trim((string) ($corr['issuing_department_name'] ?? ''));
    }
    if ($from !== '') {
        $rows[] = ['label' => __('corr.issuing_department'), 'value' => $from];
    }
    $note = trim((string) ($corr['outgoing_prep_note'] ?? ''));
    if ($note !== '') {
        $rows[] = ['label' => __('out_prep.note'), 'value' => $note];
    }
    return $rows;
}

function outgoing_prep_notify(array $userIds, array $corr, string $titleKey, string $bodyKey, string $emailIntroKey, string $dedupe, int $excludeUserId = 0): void
{
    if (!function_exists('dms_notify_users')) {
        return;
    }
    $ids = [];
    foreach ($userIds as $uid) {
        $uid = (int) $uid;
        if ($uid > 0 && $uid !== $excludeUserId) {
            $ids[] = $uid;
        }
    }
    $ids = array_values(array_unique($ids));
    if ($ids === []) {
        return;
    }
    $id = (int) ($corr['id'] ?? 0);
    $subject = trim((string) ($corr['subject'] ?? ''));
    $body = str_replace(':subject', $subject, __($bodyKey));
    dms_notify_users($ids, [
        'type' => 'outgoing_prep',
        'title' => __($titleKey),
        'body' => $body,
        'link' => 'outgoing/view.php?id=' . $id,
        'correspondence_id' => $id,
        'department_id' => outgoing_issuing_department_id($corr) ?: null,
        'dedupe_key' => $dedupe,
        'email' => true,
        'email_intro' => __($emailIntroKey),
        'email_details' => $body,
        'email_rows' => outgoing_prep_email_rows($corr),
        'email_cta' => __('notify.email_view_correspondence'),
    ]);
}

function outgoing_prep_assign_official_ref(int $id, array $corr): string
{
    $deptId = (int) ($corr['department_id'] ?? 0) ?: null;
    $ref = next_ref_number('outgoing', $deptId);
    $token = trim((string) ($corr['qr_token'] ?? ''));
    if ($token === '') {
        $token = generate_qr_token();
    }
    db()->prepare(
        "UPDATE `tblcorrespondence`
         SET ref_number = ?, qr_token = ?,
             outgoing_prep_status = NULL,
             outgoing_prep_note = NULL
         WHERE id = ? AND type = 'outgoing'"
    )->execute([$ref, $token, $id]);

    outgoing_ensure_draft_files_stamped(array_merge($corr, [
        'id' => $id,
        'ref_number' => $ref,
        'outgoing_prep_status' => null,
    ]));

    return $ref;
}

function outgoing_prep_after_convert(int $id, array $user, array $corrBefore): void
{
    $linkedId = (int) ($corrBefore['linked_id'] ?? 0);
    if ($linkedId > 0) {
        db()->prepare("UPDATE `tblcorrespondence` SET linked_id = ? WHERE id = ? AND linked_id IS NULL")
            ->execute([$id, $linkedId]);
        db()->prepare("UPDATE `tblcorrespondence` SET status = 'replied' WHERE id = ? AND type = 'incoming' AND status NOT IN ('closed', 'cancelled')")
            ->execute([$linkedId]);
    }

    $fresh = get_correspondence($id);
    $holdForCopy = is_array($fresh)
        && function_exists('outgoing_needs_issued_copy')
        && outgoing_needs_issued_copy($fresh);
    if ($holdForCopy) {
        return;
    }
    try {
        if (function_exists('outgoing_issue_flow_active')
            && outgoing_issue_flow_active()
            && function_exists('outgoing_approval_request')
        ) {
            outgoing_approval_request($id, $user);
        } elseif (function_exists('outgoing_release_to_communications')) {
            outgoing_release_to_communications($id);
        }
    } catch (Throwable $e) {
        error_log('DMS outgoing prep convert approval: ' . $e->getMessage());
    }
}

function outgoing_prep_converted_message(int $id): string
{
    $corr = get_correspondence($id);
    if ($corr
        && function_exists('outgoing_needs_issued_copy')
        && outgoing_needs_issued_copy($corr)
        && !outgoing_has_issued_copy($id)
    ) {
        return __('out_prep.converted_need_copy');
    }

    return __('out_prep.converted_ok');
}

/** @return 'pending'|'converted' */
function outgoing_prep_submit(int $id, array $user): string
{
    ensure_outgoing_prep_schema();
    $corr = get_correspondence($id);
    if (!$corr || !outgoing_user_can_prep_submit($user, $corr)) {
        throw new RuntimeException(__('out_prep.submit_denied'));
    }
    if (outgoing_issuing_department_id($corr) <= 0) {
        throw new RuntimeException(__('out_prep.need_issuing'));
    }

    $uid = (int) ($user['id'] ?? 0);
    $signers = outgoing_prep_signer_ids($corr);
    if ($signers === []) {
        throw new RuntimeException(__('out_prep.no_signer'));
    }
    if (outgoing_user_is_prep_signer($user, $corr)) {
        return outgoing_prep_convert($id, $user, '');
    }

    db()->prepare(
        "UPDATE `tblcorrespondence`
         SET outgoing_prep_status = 'pending_signer',
             outgoing_prep_note = NULL,
             outgoing_prep_decided_by = NULL,
             outgoing_prep_decided_at = NULL
         WHERE id = ? AND type = 'outgoing'"
    )->execute([$id]);
    log_activity($uid, $id, 'outgoing_prep', __('out_prep.log_submit'));
    outgoing_prep_notify(
        $signers,
        $corr,
        'out_prep.notify_submit_title',
        'out_prep.notify_submit_body',
        'out_prep.email_submit_intro',
        'out_prep_s:' . $id . ':' . time(),
        $uid
    );

    return 'pending';
}

/** @return 'converted' */
function outgoing_prep_convert(int $id, array $user, string $note = ''): string
{
    ensure_outgoing_prep_schema();
    $corr = get_correspondence($id);
    if (!$corr || ($corr['type'] ?? '') !== 'outgoing' || !outgoing_prep_is_open($corr)) {
        throw new RuntimeException(__('out_prep.convert_denied'));
    }
    $uid = (int) ($user['id'] ?? 0);
    $ref = outgoing_prep_assign_official_ref($id, $corr);
    db()->prepare(
        "UPDATE `tblcorrespondence`
         SET outgoing_prep_decided_by = ?, outgoing_prep_decided_at = NOW(), outgoing_prep_note = ?
         WHERE id = ?"
    )->execute([$uid ?: null, $note !== '' ? $note : null, $id]);
    log_activity($uid, $id, 'outgoing_prep', __('out_prep.log_convert') . ' ' . $ref);
    outgoing_prep_after_convert($id, $user, $corr);

    return 'converted';
}

function outgoing_prep_approve(int $id, array $user, string $note = ''): string
{
    $corr = get_correspondence($id);
    if (!$corr || !outgoing_user_can_prep_decide($user, $corr)) {
        throw new RuntimeException(__('out_prep.decide_denied'));
    }
    $result = outgoing_prep_convert($id, $user, $note);
    $creator = (int) ($corr['created_by'] ?? 0);
    $fresh = get_correspondence($id) ?: $corr;
    $needCopy = function_exists('outgoing_needs_issued_copy')
        && outgoing_needs_issued_copy($fresh)
        && !outgoing_has_issued_copy($id);
    if ($creator > 0 && $creator !== (int) ($user['id'] ?? 0)) {
        outgoing_prep_notify(
            [$creator],
            $fresh,
            'out_prep.notify_approved_title',
            $needCopy ? 'out_prep.notify_approved_body_copy' : 'out_prep.notify_approved_body',
            $needCopy ? 'out_prep.email_approved_intro_copy' : 'out_prep.email_approved_intro',
            'out_prep_a:' . $id . ':' . time(),
            (int) ($user['id'] ?? 0)
        );
    }

    return $result;
}

function outgoing_prep_return(int $id, array $user, string $note): void
{
    ensure_outgoing_prep_schema();
    $note = trim($note);
    if ($note === '') {
        throw new RuntimeException(__('out_prep.return_note_required'));
    }
    $corr = get_correspondence($id);
    if (!$corr || !outgoing_user_can_prep_decide($user, $corr)) {
        throw new RuntimeException(__('out_prep.decide_denied'));
    }
    $uid = (int) ($user['id'] ?? 0);
    $newId = outgoing_prep_lock_and_spawn($id, $uid, $note);
    log_activity($uid, $id, 'outgoing_prep', __('out_prep.log_return') . ' — ' . $note);
    log_activity($uid, $newId, 'outgoing_prep', __('out_prep.log_spawn'));
    $creator = (int) ($corr['created_by'] ?? 0);
    $fresh = get_correspondence($newId);
    if ($creator > 0 && $fresh) {
        outgoing_prep_notify(
            [$creator],
            $fresh,
            'out_prep.notify_return_title',
            'out_prep.notify_return_body',
            'out_prep.email_return_intro',
            'out_prep_r:' . $newId . ':' . time(),
            $uid
        );
    }
}

/**
 * يقفل المسودة الحالية ويفتح مسودة جديدة لنفس المعاملة.
 * المسودة السابقة تبقى للعرض، والتعديل على الأخيرة فقط.
 */
function outgoing_prep_lock_and_spawn(int $sourceId, int $actorId, string $note): int
{
    $pdo = db();
    $ownTx = !$pdo->inTransaction();
    if ($ownTx) {
        $pdo->beginTransaction();
    }
    try {
        $pdo->prepare(
            "UPDATE `tblcorrespondence`
             SET outgoing_prep_root_id = IFNULL(outgoing_prep_root_id, id)
             WHERE id = ? AND type = 'outgoing'"
        )->execute([$sourceId]);
        $st = $pdo->prepare("SELECT * FROM `tblcorrespondence` WHERE id = ? AND type = 'outgoing' LIMIT 1");
        $st->execute([$sourceId]);
        $src = $st->fetch(PDO::FETCH_ASSOC);
        if (!$src || !in_array((string) ($src['outgoing_prep_status'] ?? ''), ['draft', 'pending_signer'], true)) {
            throw new RuntimeException(__('out_prep.decide_denied'));
        }
        $root = (int) ($src['outgoing_prep_root_id'] ?? 0);
        $ref = outgoing_prep_placeholder_ref();
        $token = generate_qr_token();
        $pdo->prepare(
            "INSERT INTO `tblcorrespondence` (
                type, ref_number, subject, party_name, entity_id,
                snap_title_prefix, snap_person_name, snap_job_title, snap_honorific, snap_org_name,
                snap_from_prefix, snap_from_person, snap_from_job, snap_from_org, snap_from_signature,
                external_ref, external_date, corr_date, reply_due,
                status, priority, confidentiality, category_id, notes, letter_body, letter_body_en,
                qr_token, linked_id, department_id, issuing_department_id, created_by,
                rsvp_required, intend_electronic, courier_name, courier_phone, courier_id,
                outgoing_prep_status, outgoing_prep_note, outgoing_prep_decided_by, outgoing_prep_decided_at,
                outgoing_prep_root_id
            ) VALUES (
                'outgoing', ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                'draft', ?, ?, NOW(),
                ?
            )"
        )->execute([
            $ref,
            $src['subject'],
            $src['party_name'],
            $src['entity_id'],
            $src['snap_title_prefix'],
            $src['snap_person_name'],
            $src['snap_job_title'],
            $src['snap_honorific'],
            $src['snap_org_name'],
            $src['snap_from_prefix'],
            $src['snap_from_person'],
            $src['snap_from_job'],
            $src['snap_from_org'],
            $src['snap_from_signature'],
            $src['external_ref'],
            $src['external_date'],
            $src['corr_date'],
            $src['reply_due'],
            $src['status'] !== '' ? $src['status'] : 'new',
            $src['priority'],
            $src['confidentiality'],
            $src['category_id'],
            $src['notes'],
            $src['letter_body'],
            $src['letter_body_en'],
            $token,
            $src['linked_id'],
            $src['department_id'],
            $src['issuing_department_id'],
            $src['created_by'],
            $src['rsvp_required'],
            $src['intend_electronic'],
            $src['courier_name'],
            $src['courier_phone'],
            $src['courier_id'],
            $note,
            $actorId > 0 ? $actorId : null,
            $root,
        ]);
        $newId = (int) $pdo->lastInsertId();
        if ($newId <= 0) {
            throw new RuntimeException(__('out_prep.decide_denied'));
        }
        outgoing_prep_bind_id_ref($newId);
        $pdo->prepare(
            "INSERT INTO `tbldocuments`
                (correspondence_id, file_name, file_path, file_type, file_size, doc_kind, uploaded_by, uploaded_at)
             SELECT ?, file_name, file_path, file_type, file_size, doc_kind, uploaded_by, uploaded_at
             FROM `tbldocuments` WHERE correspondence_id = ?"
        )->execute([$newId, $sourceId]);
        $pdo->prepare(
            "INSERT INTO `tblcorr_attachments` (correspondence_id, attachment_type_id, quantity)
             SELECT ?, attachment_type_id, quantity
             FROM `tblcorr_attachments` WHERE correspondence_id = ?"
        )->execute([$newId, $sourceId]);
        $locked = $pdo->prepare(
            "UPDATE `tblcorrespondence`
             SET outgoing_prep_status = 'superseded',
                 outgoing_prep_note = ?,
                 outgoing_prep_decided_by = ?,
                 outgoing_prep_decided_at = NOW()
             WHERE id = ? AND type = 'outgoing'
               AND outgoing_prep_status IN ('draft', 'pending_signer')"
        );
        $locked->execute([$note, $actorId > 0 ? $actorId : null, $sourceId]);
        if ($locked->rowCount() < 1) {
            throw new RuntimeException(__('out_prep.decide_denied'));
        }
        if ($ownTx) {
            $pdo->commit();
        }

        return $newId;
    } catch (Throwable $e) {
        if ($ownTx && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/** المسودات المعادة قبل فصل السلسلة: تُقفل وتُفتح بعدها مسودة أخيرة واحدة. */
function outgoing_prep_backfill_returned_families(): void
{
    if (!function_exists('setting') || setting('outgoing_prep_family_backfill', '') === '1') {
        return;
    }
    if (function_exists('set_setting')) {
        set_setting('outgoing_prep_family_backfill', '1');
    }
    $ids = db()->query(
        "SELECT id FROM `tblcorrespondence`
         WHERE type = 'outgoing' AND outgoing_prep_status = 'draft'
           AND (
                outgoing_prep_decided_by IS NOT NULL
                OR (outgoing_prep_note IS NOT NULL AND outgoing_prep_note <> '')
           )"
    )->fetchAll(PDO::FETCH_COLUMN);
    foreach ($ids as $oldId) {
        $oldId = (int) $oldId;
        if ($oldId <= 0) {
            continue;
        }
        $st = db()->prepare(
            'SELECT outgoing_prep_note, outgoing_prep_decided_by FROM `tblcorrespondence` WHERE id = ? LIMIT 1'
        );
        $st->execute([$oldId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            continue;
        }
        $note = trim((string) ($row['outgoing_prep_note'] ?? ''));
        if ($note === '') {
            $note = '—';
        }
        $actor = (int) ($row['outgoing_prep_decided_by'] ?? 0);
        try {
            $newId = outgoing_prep_lock_and_spawn($oldId, $actor, $note);
        } catch (Throwable $e) {
            error_log('DMS outgoing prep family backfill ' . $oldId . ': ' . $e->getMessage());
            continue;
        }
        $spawnDetail = function_exists('__') ? __('out_prep.log_spawn') : 'فتح مسودة جديدة بعد إعادة المسودة السابقة';
        log_activity($actor > 0 ? $actor : null, $newId, 'outgoing_prep', $spawnDetail);
        $n = db()->prepare(
            "SELECT id FROM `tblnotifications`
             WHERE link = ? AND title LIKE '%أُعيدت%'
             ORDER BY id DESC LIMIT 1"
        );
        $n->execute(['outgoing/view.php?id=' . $oldId]);
        $nid = (int) $n->fetchColumn();
        if ($nid > 0) {
            db()->prepare('UPDATE `tblnotifications` SET link = ?, correspondence_id = ? WHERE id = ?')
                ->execute(['outgoing/view.php?id=' . $newId, $newId, $nid]);
        }
    }
}

function outgoing_prep_placeholder_ref(): string
{
    return 'ODTMP-' . strtoupper(bin2hex(random_bytes(6)));
}

function outgoing_prep_bind_id_ref(int $id): void
{
    if ($id <= 0) {
        return;
    }
    db()->prepare("UPDATE `tblcorrespondence` SET ref_number = ? WHERE id = ? AND type = 'outgoing'")
        ->execute(['OD-' . $id, $id]);
}
