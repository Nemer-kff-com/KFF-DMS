<?php
/**
 * مجموعات جهات المراسلة (مثل مجلس الأمناء) والصادر الجماعي
 */

function ensure_entity_groups_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    try {
        $pdo = db();
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `tblentity_groups` (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                department_id INT UNSIGNED DEFAULT NULL COMMENT 'NULL = مجموعة عامة',
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_entity_groups_department (department_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `tblentity_group_members` (
                group_id INT UNSIGNED NOT NULL,
                entity_id INT UNSIGNED NOT NULL,
                PRIMARY KEY (group_id, entity_id),
                INDEX idx_egm_entity (entity_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `tbloutgoing_batches` (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                subject VARCHAR(500) NOT NULL,
                letter_body TEXT DEFAULT NULL,
                letter_body_en TEXT DEFAULT NULL,
                entity_group_id INT UNSIGNED DEFAULT NULL,
                created_by INT UNSIGNED NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_out_batches_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $batchCols = dms_db_column_map('tbloutgoing_batches');
        if ($batchCols && empty($batchCols['letter_body_en'])) {
            $pdo->exec(
                'ALTER TABLE `tbloutgoing_batches` ADD COLUMN `letter_body_en` TEXT NULL DEFAULT NULL AFTER `letter_body`'
            );
        }

        $corrCols = dms_db_column_map('tblcorrespondence');
        if (empty($corrCols['batch_id'])) {
            $pdo->exec('ALTER TABLE `tblcorrespondence` ADD COLUMN `batch_id` INT UNSIGNED NULL DEFAULT NULL AFTER `linked_id`');
            $pdo->exec('ALTER TABLE `tblcorrespondence` ADD INDEX `idx_corr_batch` (`batch_id`)');
        }

        $addFk = static function (string $sql) use ($pdo): void {
            try {
                $pdo->exec($sql);
            } catch (Throwable $e) {
                // قاعدة قديمة أو قيد موجود مسبقاً
            }
        };
        $addFk(
            'ALTER TABLE `tblentity_group_members`
             ADD CONSTRAINT `fk_egm_group` FOREIGN KEY (`group_id`) REFERENCES `tblentity_groups`(`id`) ON DELETE CASCADE'
        );
        $addFk(
            'ALTER TABLE `tblentity_group_members`
             ADD CONSTRAINT `fk_egm_entity` FOREIGN KEY (`entity_id`) REFERENCES `tblentities`(`id`) ON DELETE CASCADE'
        );
        $addFk(
            'ALTER TABLE `tbloutgoing_batches`
             ADD CONSTRAINT `fk_out_batch_group` FOREIGN KEY (`entity_group_id`) REFERENCES `tblentity_groups`(`id`) ON DELETE SET NULL'
        );
        $addFk(
            'ALTER TABLE `tblcorrespondence`
             ADD CONSTRAINT `fk_corr_batch` FOREIGN KEY (`batch_id`) REFERENCES `tbloutgoing_batches`(`id`) ON DELETE SET NULL'
        );
    } catch (Throwable $e) {
        error_log('DMS entity groups schema migration failed: ' . $e->getMessage());
    }
}

function entity_groups_list(bool $activeOnly = true, ?array $user = null): array
{
    ensure_entity_groups_schema();
    $user = $user ?? (function_exists('current_user') ? current_user() : null);
    $where = [];
    $params = [];
    if ($activeOnly) {
        $where[] = 'g.is_active = 1';
    }
    if ($user && ($user['role'] ?? '') === 'department' && function_exists('user_department_ids') && user_department_ids($user)) {
        [$inSql, $inParams] = dms_sql_in('g.department_id', user_department_ids($user));
        $where[] = '(g.department_id IS NULL OR ' . $inSql . ')';
        $params = array_merge($params, $inParams);
    }
    $sql = "SELECT g.*, d.name AS department_name, d.name_en AS department_name_en,
                   (SELECT COUNT(*) FROM `tblentity_group_members` m WHERE m.group_id = g.id) AS member_count
            FROM `tblentity_groups` g
            LEFT JOIN `tbldepartments` d ON d.id = g.department_id";
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY g.department_id IS NOT NULL, g.name';
    try {
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function get_entity_group(int $id): ?array
{
    ensure_entity_groups_schema();
    $stmt = db()->prepare(
        "SELECT g.*, d.name AS department_name, d.name_en AS department_name_en
         FROM `tblentity_groups` g
         LEFT JOIN `tbldepartments` d ON d.id = g.department_id
         WHERE g.id = ?"
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function entity_group_member_ids(int $groupId): array
{
    ensure_entity_groups_schema();
    $stmt = db()->prepare('SELECT entity_id FROM `tblentity_group_members` WHERE group_id = ? ORDER BY entity_id');
    $stmt->execute([$groupId]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function entity_group_members(int $groupId, ?array $user = null): array
{
    $ids = entity_group_member_ids($groupId);
    if (!$ids) {
        return [];
    }
    $entities = entities_list(true, $user);
    $byId = [];
    foreach ($entities as $row) {
        $byId[(int) $row['id']] = $row;
    }
    $out = [];
    foreach ($ids as $id) {
        if (isset($byId[$id])) {
            $out[] = $byId[$id];
        }
    }
    return $out;
}

function user_can_manage_entity_group(?array $group, ?array $user = null): bool
{
    if (!$group) {
        return false;
    }
    $user = $user ?? (function_exists('current_user') ? current_user() : null);
    if (!$user) {
        return false;
    }
    if (in_array($user['role'] ?? '', ['admin', 'archive'], true)) {
        return true;
    }
    if (($user['role'] ?? '') === 'communications' && function_exists('user_can') && user_can('entity_groups.manage', $user)) {
        return true;
    }
    return ($user['role'] ?? '') === 'department'
        && function_exists('user_can')
        && user_can('entity_groups.manage', $user)
        && function_exists('user_can_access_department')
        && user_can_access_department((int) ($group['department_id'] ?? 0), $user);
}

function user_can_use_entity_group(?array $group, ?array $user = null): bool
{
    if (!$group || !(int) ($group['is_active'] ?? 1)) {
        return false;
    }
    $user = $user ?? (function_exists('current_user') ? current_user() : null);
    if (!$user) {
        return false;
    }
    if (in_array($user['role'] ?? '', ['admin', 'archive'], true)) {
        return true;
    }
    if (($user['role'] ?? '') === 'communications' && function_exists('user_can') && user_can('entity_groups.manage', $user)) {
        return true;
    }
    if (($user['role'] ?? '') !== 'department' || !function_exists('user_department_ids') || !user_department_ids($user)) {
        return false;
    }
    return empty($group['department_id'])
        || in_array((int) $group['department_id'], user_department_ids($user), true);
}

function save_entity_group_members(int $groupId, array $entityIds, array $user): void
{
    $allowed = [];
    foreach (entities_list(true, $user) as $row) {
        $allowed[(int) $row['id']] = true;
    }
    $ids = [];
    foreach ($entityIds as $id) {
        $id = (int) $id;
        if ($id > 0 && isset($allowed[$id])) {
            $ids[$id] = $id;
        }
    }
    db()->prepare('DELETE FROM `tblentity_group_members` WHERE group_id = ?')->execute([$groupId]);
    if (!$ids) {
        return;
    }
    $stmt = db()->prepare('INSERT INTO `tblentity_group_members` (group_id, entity_id) VALUES (?, ?)');
    foreach ($ids as $id) {
        $stmt->execute([$groupId, $id]);
    }
}

function letter_name_is_english(string $text): bool
{
    return (bool) preg_match('/[a-zA-Z]/', $text);
}

function correspondence_letter_person_name(array $row): string
{
    foreach (['snap_person_name', 'entity_directed_to', 'directed_to', 'party_name'] as $key) {
        $value = trim((string) ($row[$key] ?? ''));
        if ($value !== '') {
            return $value;
        }
    }

    return '';
}

function correspondence_letter_channel_lang(array $corr): string
{
    if (!letter_name_is_english(correspondence_letter_person_name($corr))) {
        return 'ar';
    }
    $en = trim((string) ($corr['letter_body_en'] ?? ''));
    if ($en === '') {
        $en = trim((string) ($corr['batch_letter_body_en'] ?? ''));
    }
    $ar = trim((string) ($corr['letter_body'] ?? ''));
    if ($ar === '') {
        $ar = trim((string) ($corr['batch_letter_body'] ?? ''));
    }
    if ($en !== '' || $ar === '') {
        return 'en';
    }

    return 'ar';
}

function correspondence_letter_raw(array $corr, ?string $lang = null): string
{
    $lang = $lang ?? correspondence_letter_channel_lang($corr);
    if ($lang === 'en') {
        $en = trim((string) ($corr['letter_body_en'] ?? ''));
        if ($en === '') {
            $en = trim((string) ($corr['batch_letter_body_en'] ?? ''));
        }
        if ($en !== '') {
            return $en;
        }
    }
    $ar = trim((string) ($corr['letter_body'] ?? ''));
    if ($ar === '') {
        $ar = trim((string) ($corr['batch_letter_body'] ?? ''));
    }

    return $ar;
}

function apply_letter_placeholders(string $body, array $row, string $lang = 'ar'): string
{
    $lang = $lang === 'en' ? 'en' : 'ar';
    $isCorr = ($row['type'] ?? '') === 'incoming' || ($row['type'] ?? '') === 'outgoing';
    $party = $isCorr ? correspondence_archived_party($row) : [
        'person' => entity_recipient_name($row),
        'prefix' => entity_title_prefix_value($row),
        'honorific' => entity_honorific_value($row),
        'job' => trim((string) ($row['entity_job_title'] ?? $row['job_title'] ?? '')),
        'org' => function_exists('bilingual_label')
            ? bilingual_label([
                'name_ar' => (string) ($row['entity_name'] ?? $row['name_ar'] ?? ''),
                'name_en' => (string) ($row['entity_name_en'] ?? $row['name_en'] ?? ''),
            ], 'name_ar', 'name_en')
            : trim((string) ($row['entity_name'] ?? $row['name_ar'] ?? '')),
    ];
    $name = $party['person'];
    $title = $party['prefix'];
    $honorific = $party['honorific'];
    $job = $party['job'];
    $workplace = trim((string) ($party['org'] ?? ''));
    if ($lang === 'en') {
        $workplaceEn = trim((string) ($row['entity_name_en'] ?? $row['name_en'] ?? ''));
        if ($workplaceEn !== '') {
            $workplace = $workplaceEn;
        }
    }
    $hijriDate = '';
    if (function_exists('format_hijri_date')) {
        $hijriDate = format_hijri_date($row['corr_date'] ?? null);
        if ($hijriDate !== '') {
            $hijriDate .= $lang === 'en' ? ' AH' : ' هـ';
        }
    }
    $map = [
        '{name}' => $name,
        '{title}' => $title,
        '{title_prefix}' => $title,
        '{directed_to}' => $name,
        '{honorific}' => $honorific,
        '{honored_name}' => format_entity_honored_name($row),
        '{job}' => $job,
        '{job_title}' => $job,
        '{number}' => (function_exists('outgoing_show_official_ref') && !outgoing_show_official_ref($row))
            ? ''
            : format_ref_number($row['ref_number'] ?? ''),
        '{date}' => function_exists('format_date')
            ? format_date($row['corr_date'] ?? null)
            : (string) ($row['corr_date'] ?? ''),
        '{hijri}' => $hijriDate,
        '{hijri_date}' => $hijriDate,
        '{date_h}' => $hijriDate,
        '{subject}' => (string) ($row['subject'] ?? ''),
        '{org}' => $workplace,
        '{org_name}' => $workplace,
        '{workplace}' => $workplace,
        '{from_name}' => '',
        '{from_job}' => '',
    ];
    if (function_exists('letter_signature_context')) {
        $signer = letter_signature_context($row);
        $map['{from_name}'] = trim((string) ($signer['name'] ?? ''));
        $map['{from_job}'] = trim(implode(' ', array_map('strval', $signer['titles'] ?? [])));
    }
    return strtr($body, $map);
}

function correspondence_letter_text(array $corr, ?string $lang = null): string
{
    $lang = $lang ?? correspondence_letter_channel_lang($corr);
    $raw = correspondence_letter_raw($corr, $lang);
    if ($raw === '') {
        return '';
    }
    $en = trim((string) ($corr['letter_body_en'] ?? ''));
    if ($en === '') {
        $en = trim((string) ($corr['batch_letter_body_en'] ?? ''));
    }
    $used = ($lang === 'en' && $en !== '') ? 'en' : 'ar';

    return apply_letter_placeholders($raw, $corr, $used);
}

function get_outgoing_batch(int $id): ?array
{
    ensure_entity_groups_schema();
    $stmt = db()->prepare(
        "SELECT b.*, g.name AS group_name, " . user_name_sql('u', 'creator_name') . "
         FROM `tbloutgoing_batches` b
         LEFT JOIN `tblentity_groups` g ON g.id = b.entity_group_id
         LEFT JOIN `tblusers` u ON u.id = b.created_by
         WHERE b.id = ?"
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * شرط إضافي: معاملات لها علاقة اعتماد بالمستخدم الحالي (معتمد / معيَّن / طالب / من بَتّ).
 * يُستخدم مع department_scope لإظهار الدفعات للمعتمدين خارج قسمهم.
 *
 * @return array{0:string,1:list<mixed>}
 */
function outgoing_approval_visibility_sql(?array $user, string $alias = 'c'): array
{
    $uid = (int) ($user['id'] ?? 0);
    if ($uid <= 0 || !function_exists('outgoing_approval_sg_user_id')) {
        return ['', []];
    }
    $parts = [
        "{$alias}.issue_approval_assignee_id = ?",
        "{$alias}.issue_approval_requested_by = ?",
        "{$alias}.issue_approval_decided_by = ?",
    ];
    $params = [$uid, $uid, $uid];

    $parts[] = "{$alias}.issue_review_by = ?";
    $params[] = $uid;

    if (function_exists('outgoing_user_is_reviewer') && outgoing_user_is_reviewer($user)) {
        $parts[] = "({$alias}.issue_approval_status = 'pending_review' AND IFNULL({$alias}.issue_approval_assignee_id, 0) = 0)";
    }
    $reviewPrimary = function_exists('outgoing_review_primary_user_id') ? (int) outgoing_review_primary_user_id() : 0;
    $reviewAlt = function_exists('outgoing_review_alternate_user_id') ? (int) outgoing_review_alternate_user_id() : 0;
    if (($reviewPrimary > 0 && $uid === $reviewPrimary) || ($reviewAlt > 0 && $uid === $reviewAlt)) {
        $parts[] = "{$alias}.issue_approval_status = 'pending_review'";
    }

    $sg = (int) outgoing_approval_sg_user_id();
    $deputy = (int) outgoing_approval_deputy_user_id();
    $alt = (int) outgoing_approval_alternate_user_id();

    if ($sg > 0 && $uid === $sg) {
        $parts[] = "{$alias}.issue_approval_status IN ('pending_sg','pending_deputy','pending_alternate','approved','rejected')";
    }
    if ($deputy > 0 && $uid === $deputy) {
        $parts[] = "{$alias}.issue_approval_status IN ('pending_deputy','pending_alternate','approved','rejected')";
    }
    if ($alt > 0 && $uid === $alt) {
        $parts[] = "{$alias}.issue_approval_status IN ('pending_alternate','approved','rejected')";
    }

    return ['(' . implode(' OR ', $parts) . ')', $params];
}

/**
 * دفعات الصادر الجماعي الظاهرة للمستخدم (حسب نطاق الإدارة أو دور الاعتماد).
 *
 * @return list<array<string,mixed>>
 */
function outgoing_batches_list(?array $user = null, int $limit = 50): array
{
    ensure_entity_groups_schema();
    if (function_exists('ensure_corr_rsvp_schema')) {
        ensure_corr_rsvp_schema();
    }
    $user = $user ?? (function_exists('current_user') ? current_user() : null);
    $limit = max(1, min(100, $limit));

    [$scopeSql, $scopeParams] = department_scope_sql($user, 'c');
    [$apprSql, $apprParams] = outgoing_approval_visibility_sql($user, 'c');

    $visibleParts = [];
    $visibleParams = [];
    if ($scopeSql !== '') {
        $visibleParts[] = $scopeSql;
        foreach ($scopeParams as $p) {
            $visibleParams[] = $p;
        }
    }
    if ($apprSql !== '') {
        $visibleParts[] = $apprSql;
        foreach ($apprParams as $p) {
            $visibleParams[] = $p;
        }
    }
    if ($visibleParts !== []) {
        $visibleSql = '(' . implode(' OR ', $visibleParts) . ')';
        $scopeAnd = ' AND ' . $visibleSql;
    } else {
        $scopeAnd = '';
        $visibleParams = [];
    }

    $sql = "SELECT b.id, b.subject, b.created_at, b.requires_rsvp, b.entity_group_id,
                   g.name AS group_name,
                   " . user_name_sql('u', 'creator_name') . ",
                   (
                       SELECT COUNT(*)
                       FROM `tblcorrespondence` c
                       WHERE c.batch_id = b.id AND c.type = 'outgoing'{$scopeAnd}
                   ) AS item_count,
                   (
                       SELECT MIN(c.corr_date)
                       FROM `tblcorrespondence` c
                       WHERE c.batch_id = b.id AND c.type = 'outgoing'{$scopeAnd}
                   ) AS corr_date
            FROM `tbloutgoing_batches` b
            LEFT JOIN `tblentity_groups` g ON g.id = b.entity_group_id
            LEFT JOIN `tblusers` u ON u.id = b.created_by
            WHERE EXISTS (
                SELECT 1
                FROM `tblcorrespondence` c
                WHERE c.batch_id = b.id AND c.type = 'outgoing'{$scopeAnd}
            )
            ORDER BY b.id DESC
            LIMIT " . $limit;

    $params = [];
    // ثلاثة مواضع لنفس شرط الظهور
    foreach ([$visibleParams, $visibleParams, $visibleParams] as $chunk) {
        foreach ($chunk as $p) {
            $params[] = $p;
        }
    }
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function outgoing_batch_items(int $batchId, ?array $user = null): array
{
    ensure_entity_groups_schema();
    ensure_entities_schema();
    $user = $user ?? (function_exists('current_user') ? current_user() : null);
    $sql = "SELECT c.*, d.name AS department_name, d.name_en AS department_name_en,
                   idpt.name AS issuing_department_name, idpt.name_en AS issuing_department_name_en,
                   e.name_ar AS entity_name, e.name_en AS entity_name_en,
                   e.directed_to AS entity_directed_to, e.title_prefix AS entity_title_prefix,
                   e.job_title AS entity_job_title,
                   e.honorific AS entity_honorific, e.mobile AS entity_mobile,
                   e.phone AS entity_phone, e.email AS entity_email,
                   b.letter_body AS batch_letter_body,
                   b.letter_body_en AS batch_letter_body_en,
                   b.requires_rsvp AS batch_requires_rsvp
            FROM `tblcorrespondence` c
            LEFT JOIN `tbldepartments` d ON d.id = c.department_id
            LEFT JOIN `tbldepartments` idpt ON idpt.id = c.issuing_department_id
            LEFT JOIN `tblentities` e ON e.id = c.entity_id
            LEFT JOIN `tbloutgoing_batches` b ON b.id = c.batch_id
            WHERE c.batch_id = ? AND c.type = 'outgoing'
            ORDER BY c.id";
    $stmt = db()->prepare($sql);
    $stmt->execute([$batchId]);
    $rows = array_map('correspondence_apply_ref_display', $stmt->fetchAll());

    // نفس منطق صفحة العرض: نطاق القسم أو صلاحية اعتماد الإصدار
    if ($user && function_exists('user_can_access_correspondence')) {
        $rows = array_values(array_filter(
            $rows,
            static fn(array $row): bool => user_can_access_correspondence($row, $user)
        ));
    }

    return $rows;
}
