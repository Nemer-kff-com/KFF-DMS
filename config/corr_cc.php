<?php
/**
 * «للاطلاع» في الوارد والمعاملة الداخلية: مستخدمون يطّلعون على المعاملة (قراءة فقط)
 * ولا تصلهم إلا بعد المراجعة والاعتماد النهائي، ثم يؤكدون «اطّلعت» فيُشعَر المنشئ.
 */

declare(strict_types=1);

function ensure_corr_cc_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        db()->exec(
            "CREATE TABLE IF NOT EXISTS `tblcorr_cc` (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                correspondence_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                added_by INT UNSIGNED NULL DEFAULT NULL,
                status ENUM('pending','released','cancelled') NOT NULL DEFAULT 'pending',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                released_at DATETIME NULL DEFAULT NULL,
                seen_at DATETIME NULL DEFAULT NULL,
                UNIQUE KEY uq_corr_cc (correspondence_id, user_id),
                KEY idx_corr_cc_user (user_id, status, seen_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    } catch (Throwable $e) {
        return;
    }
    corr_cc_purge_orphans();
    corr_cc_ensure_foreign_keys();
}

function corr_cc_purge_orphans(): void
{
    try {
        db()->exec(
            "DELETE cc FROM `tblcorr_cc` cc
             LEFT JOIN `tblcorrespondence` c ON c.id = cc.correspondence_id
             WHERE c.id IS NULL"
        );
        db()->exec(
            "DELETE cc FROM `tblcorr_cc` cc
             LEFT JOIN `tblusers` u ON u.id = cc.user_id
             WHERE u.id IS NULL"
        );
    } catch (Throwable $e) {
        // الجداول غير جاهزة بعد
    }
}

function corr_cc_fk_exists(string $name): bool
{
    try {
        $st = db()->prepare(
            "SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE()
               AND TABLE_NAME = 'tblcorr_cc'
               AND CONSTRAINT_NAME = ?
               AND CONSTRAINT_TYPE = 'FOREIGN KEY'
             LIMIT 1"
        );
        $st->execute([$name]);
        return (bool) $st->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function corr_cc_ensure_foreign_keys(): void
{
    try {
        if (!corr_cc_fk_exists('fk_corr_cc_corr')) {
            db()->exec(
                "ALTER TABLE `tblcorr_cc`
                 ADD CONSTRAINT `fk_corr_cc_corr`
                 FOREIGN KEY (`correspondence_id`) REFERENCES `tblcorrespondence` (`id`) ON DELETE CASCADE"
            );
        }
        if (!corr_cc_fk_exists('fk_corr_cc_user')) {
            db()->exec(
                "ALTER TABLE `tblcorr_cc`
                 ADD CONSTRAINT `fk_corr_cc_user`
                 FOREIGN KEY (`user_id`) REFERENCES `tblusers` (`id`) ON DELETE CASCADE"
            );
        }
    } catch (Throwable $e) {
        // قد تفشل إن وُجدت قيود بأسماء أخرى
    }
}

/** الأنواع التي تدعم «للاطلاع» */
function corr_cc_types(): array
{
    return ['incoming', 'internal'];
}

/** تصل الأسماء بعد الاعتماد النهائي حين يكون مسار هذا النوع مضبوطاً */
function corr_cc_waits_for_approval(string $type): bool
{
    if (!in_array($type, corr_cc_types(), true) || !function_exists('appr_with')) {
        return false;
    }
    return (bool) appr_with($type, static fn (): bool => outgoing_approval_is_configured());
}

/** الداخلية متاحة دائماً. الوارد يبقى مرتبطاً بمسار الاعتماد لأنه لا يُرسل إلا بعده. */
function corr_cc_enabled_for(string $type): bool
{
    if (!in_array($type, corr_cc_types(), true)) {
        return false;
    }
    if ($type === 'internal') {
        return true;
    }
    return corr_cc_waits_for_approval($type);
}

/** @return list<array{id:int,name:string,dept_id:int,dept:string,job:string,photo:string}> */
function corr_cc_user_options(int $excludeUserId = 0): array
{
    $sql = "SELECT u.id, u.name_ar, u.name_en, u.job_title, u.job_title_en, u.department_id, %s
                   d.name AS dept_name, d.name_en AS dept_name_en
            FROM `tblusers` u
            LEFT JOIN `tbldepartments` d ON d.id = u.department_id
            WHERE u.is_active = 1
            ORDER BY d.sort_order IS NULL, d.sort_order, d.name, u.name_ar, u.name_en, u.id";
    try {
        $rows = db()->query(sprintf($sql, 'u.photo_path,'))->fetchAll() ?: [];
    } catch (Throwable $e) {
        try {
            $rows = db()->query(sprintf($sql, ''))->fetchAll() ?: [];
        } catch (Throwable $e2) {
            return [];
        }
    }
    $out = [];
    foreach ($rows as $r) {
        $id = (int) $r['id'];
        if ($id <= 0 || $id === $excludeUserId) {
            continue;
        }
        $en = function_exists('dms_lang_code') && dms_lang_code() === 'en';
        $job = trim((string) ($en && !empty($r['job_title_en']) ? $r['job_title_en'] : ($r['job_title'] ?? '')));
        $out[] = [
            'id' => $id,
            'name' => user_display_name($r),
            'dept_id' => (int) ($r['department_id'] ?? 0),
            'dept' => department_display_name($r, 'dept_name', 'dept_name_en'),
            'job' => $job,
            'photo' => function_exists('user_photo_url') ? user_photo_url($r) : '',
        ];
    }
    return $out;
}

/**
 * معرفات «للاطلاع» من الطلب (cc_users[]) — مستخدمون نشطون فقط، دون المنشئ.
 *
 * @return list<int>
 */
function corr_cc_ids_from_request(array $data, int $excludeUserId = 0): array
{
    $raw = $data['cc_users'] ?? [];
    if (!is_array($raw)) {
        return [];
    }
    $ids = array_values(array_unique(array_filter(
        array_map('intval', $raw),
        static fn (int $id): bool => $id > 0 && $id !== $excludeUserId
    )));
    if ($ids === []) {
        return [];
    }
    $ids = array_slice($ids, 0, 100);
    $in = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare("SELECT id FROM `tblusers` WHERE is_active = 1 AND id IN ({$in})");
    $stmt->execute($ids);
    $valid = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    return array_values(array_filter($ids, static fn (int $id): bool => in_array($id, $valid, true)));
}

/** يرمي استثناء إن طُلب «للاطلاع» والاعتماد غير مضبوط لهذا النوع */
function corr_cc_assert_allowed(string $type, array $ids): void
{
    if ($ids !== [] && !corr_cc_enabled_for($type)) {
        throw new InvalidArgumentException(__('cc.requires_approval'));
    }
}

/**
 * استبدال قائمة «للاطلاع» المعلّقة (قبل الاعتماد). المُفرَج عنهم لا يُمسّون.
 *
 * @param list<int> $ids
 * @return bool true إن تغيّرت القائمة
 */
function corr_cc_set_pending(int $corrId, array $ids, int $byUserId): bool
{
    ensure_corr_cc_schema();
    if ($corrId <= 0) {
        return false;
    }
    $pdo = db();
    $stmt = $pdo->prepare("SELECT user_id, status FROM `tblcorr_cc` WHERE correspondence_id = ?");
    $stmt->execute([$corrId]);
    $current = [];
    foreach ($stmt->fetchAll() ?: [] as $r) {
        $current[(int) $r['user_id']] = (string) $r['status'];
    }
    $changed = false;
    foreach ($current as $uid => $status) {
        if ($status !== 'released' && !in_array($uid, $ids, true)) {
            $pdo->prepare("DELETE FROM `tblcorr_cc` WHERE correspondence_id = ? AND user_id = ? AND status <> 'released'")
                ->execute([$corrId, $uid]);
            $changed = true;
        }
    }
    $ins = $pdo->prepare(
        "INSERT INTO `tblcorr_cc` (correspondence_id, user_id, added_by, status)
         VALUES (?, ?, ?, 'pending')
         ON DUPLICATE KEY UPDATE status = IF(status = 'released', status, 'pending'), added_by = VALUES(added_by)"
    );
    foreach ($ids as $uid) {
        if (($current[$uid] ?? '') === 'pending' || ($current[$uid] ?? '') === 'released') {
            continue;
        }
        $ins->execute([$corrId, $uid, $byUserId ?: null]);
        $changed = true;
    }
    unset($GLOBALS['_dms_corr_cc_pending'][$corrId]);
    if ($changed) {
        $names = array_map(static fn (array $r): string => $r['name'], corr_cc_list($corrId));
        log_activity($byUserId ?: null, $corrId, 'cc_set', __('activity.action.cc_set') . ($names ? ': ' . implode('، ', $names) : ''));
    }
    return $changed;
}

/** @return list<array{user_id:int,name:string,dept:string,status:string,released_at:?string,seen_at:?string}> */
function corr_cc_list(int $corrId): array
{
    ensure_corr_cc_schema();
    try {
        $stmt = db()->prepare(
            "SELECT cc.user_id, cc.status, cc.released_at, cc.seen_at,
                    u.name_ar, u.name_en, d.name AS dept_name, d.name_en AS dept_name_en
             FROM `tblcorr_cc` cc
             JOIN `tblusers` u ON u.id = cc.user_id
             LEFT JOIN `tbldepartments` d ON d.id = u.department_id
             WHERE cc.correspondence_id = ? AND cc.status <> 'cancelled'
             ORDER BY u.name_ar, u.name_en, cc.id"
        );
        $stmt->execute([$corrId]);
        $rows = $stmt->fetchAll() ?: [];
    } catch (Throwable $e) {
        return [];
    }
    return array_map(static fn (array $r): array => [
        'user_id' => (int) $r['user_id'],
        'name' => user_display_name($r),
        'dept' => department_display_name($r, 'dept_name', 'dept_name_en'),
        'status' => (string) $r['status'],
        'released_at' => $r['released_at'] ?: null,
        'seen_at' => $r['seen_at'] ?: null,
    ], $rows);
}

/** @return list<int> */
function corr_cc_pending_ids(int $corrId): array
{
    return array_values(array_map(
        static fn (array $r): int => $r['user_id'],
        array_filter(corr_cc_list($corrId), static fn (array $r): bool => $r['status'] === 'pending')
    ));
}

function corr_cc_has_pending(int $corrId): bool
{
    if ($corrId <= 0) {
        return false;
    }
    if (!isset($GLOBALS['_dms_corr_cc_pending'][$corrId])) {
        ensure_corr_cc_schema();
        try {
            $stmt = db()->prepare("SELECT 1 FROM `tblcorr_cc` WHERE correspondence_id = ? AND status = 'pending' LIMIT 1");
            $stmt->execute([$corrId]);
            $GLOBALS['_dms_corr_cc_pending'][$corrId] = (bool) $stmt->fetchColumn();
        } catch (Throwable $e) {
            $GLOBALS['_dms_corr_cc_pending'][$corrId] = false;
        }
    }
    return $GLOBALS['_dms_corr_cc_pending'][$corrId];
}

/** إرسال «للاطلاع» بعد الاعتماد النهائي وإشعار المستلمين */
function corr_cc_release(int $corrId, array $actor): void
{
    $ids = corr_cc_pending_ids($corrId);
    if ($ids === []) {
        return;
    }
    db()->prepare(
        "UPDATE `tblcorr_cc` SET status = 'released', released_at = ? WHERE correspondence_id = ? AND status = 'pending'"
    )->execute([date('Y-m-d H:i:s'), $corrId]);
    unset($GLOBALS['_dms_corr_cc_pending'][$corrId]);

    $corr = get_correspondence($corrId);
    if (!$corr) {
        return;
    }
    log_activity((int) ($actor['id'] ?? 0) ?: null, $corrId, 'cc_release', __('activity.action.cc_release'));
    if (!function_exists('dms_notify_users')) {
        return;
    }
    $ref = format_ref_number($corr['ref_number'] ?? $corrId);
    $subjectLine = trim((string) ($corr['subject'] ?? ''));
    $folder = function_exists('correspondence_type_folder')
        ? correspondence_type_folder((string) $corr['type'])
        : (string) $corr['type'];
    dms_notify_users($ids, [
        'type' => 'corr_cc',
        'title' => __('cc.notify_title'),
        'body' => trim($ref . ($subjectLine !== '' ? ' — ' . $subjectLine : '')),
        'link' => $folder . '/view.php?id=' . $corrId,
        'correspondence_id' => $corrId,
        'dedupe_key' => 'corr_cc_' . $corrId,
        'email' => true,
        'email_intro' => __('cc.email_intro'),
        'email_details' => trim($ref . ($subjectLine !== '' ? ' — ' . $subjectLine : '')),
        'email_rows' => function_exists('outgoing_approval_email_rows') ? outgoing_approval_email_rows($corr) : [],
        'email_cta' => __('notify.email_view_correspondence'),
    ]);
}

/** إلغاء «للاطلاع» المعلّق (مثل حفظ الوارد للعلم أو تعذّر الحجز) */
function corr_cc_cancel(int $corrId, ?array $actor = null): void
{
    ensure_corr_cc_schema();
    $stmt = db()->prepare("UPDATE `tblcorr_cc` SET status = 'cancelled' WHERE correspondence_id = ? AND status = 'pending'");
    $stmt->execute([$corrId]);
    unset($GLOBALS['_dms_corr_cc_pending'][$corrId]);
    if ($stmt->rowCount() > 0) {
        log_activity($actor ? ((int) ($actor['id'] ?? 0) ?: null) : null, $corrId, 'cc_cancel', __('activity.action.cc_cancel'));
    }
}

/** سطر «للاطلاع» المُرسَل لهذا المستخدم، أو null */
function corr_cc_user_row(int $corrId, int $userId): ?array
{
    if ($corrId <= 0 || $userId <= 0) {
        return null;
    }
    ensure_corr_cc_schema();
    try {
        $stmt = db()->prepare(
            "SELECT * FROM `tblcorr_cc` WHERE correspondence_id = ? AND user_id = ? AND status = 'released' LIMIT 1"
        );
        $stmt->execute([$corrId, $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

/** عرض قراءة فقط: المعاملة وصلت للمستخدم «للاطلاع» بعد الاعتماد */
function corr_cc_user_can_view(?array $corr, ?array $user): bool
{
    if (!$corr || !$user || !in_array((string) ($corr['type'] ?? ''), corr_cc_types(), true)) {
        return false;
    }
    return corr_cc_user_row((int) $corr['id'], (int) $user['id']) !== null;
}

/** تعديل قائمة «للاطلاع» بعد الإعادة: المنشئ فقط (والمدير) ضمن صلاحية إعادة الإرسال */
function corr_cc_user_can_edit(array $user, array $corr): bool
{
    if (!in_array((string) ($corr['type'] ?? ''), corr_cc_types(), true)) {
        return false;
    }
    $uid = (int) ($user['id'] ?? 0);
    if ($uid !== (int) ($corr['created_by'] ?? 0) && ($user['role'] ?? '') !== 'admin') {
        return false;
    }
    return function_exists('outgoing_user_can_request_approval')
        && (bool) appr_with((string) $corr['type'], static fn (): bool => outgoing_user_can_request_approval($user, $corr));
}

/** تأكيد «اطّلعت» وإشعار المنشئ. @return bool true إن سُجّل الآن */
function corr_cc_acknowledge(int $corrId, array $user): bool
{
    $row = corr_cc_user_row($corrId, (int) $user['id']);
    if (!$row) {
        throw new RuntimeException(__('auth.forbidden'));
    }
    if (!empty($row['seen_at'])) {
        return false;
    }
    $stmt = db()->prepare(
        "UPDATE `tblcorr_cc` SET seen_at = ? WHERE id = ? AND seen_at IS NULL"
    );
    $stmt->execute([date('Y-m-d H:i:s'), (int) $row['id']]);
    if ($stmt->rowCount() === 0) {
        return false;
    }
    $uid = (int) $user['id'];
    log_activity($uid, $corrId, 'cc_ack', __('activity.action.cc_ack'));

    $corr = get_correspondence($corrId);
    if ($corr && function_exists('dms_notify_users')) {
        $notifyIds = array_values(array_unique(array_filter([
            (int) ($corr['created_by'] ?? 0),
            (int) ($corr['issue_approval_requested_by'] ?? 0),
        ], static fn (int $id): bool => $id > 0 && $id !== $uid)));
        if ($notifyIds !== []) {
            $ref = format_ref_number($corr['ref_number'] ?? $corrId);
            $folder = function_exists('correspondence_type_folder')
                ? correspondence_type_folder((string) $corr['type'])
                : (string) $corr['type'];
            dms_notify_users($notifyIds, [
                'type' => 'corr_cc_ack',
                'title' => __('cc.notify_ack_title'),
                'body' => str_replace([':name', ':ref'], [user_actor_name($user), $ref], __('cc.notify_ack_body')),
                'link' => $folder . '/view.php?id=' . $corrId,
                'correspondence_id' => $corrId,
                'dedupe_key' => 'corr_cc_ack_' . $corrId . '_' . $uid,
            ]);
        }
    }
    return true;
}

function corr_cc_unread_count(int $userId): int
{
    if ($userId <= 0) {
        return 0;
    }
    ensure_corr_cc_schema();
    try {
        $stmt = db()->prepare(
            "SELECT COUNT(*) FROM `tblcorr_cc` WHERE user_id = ? AND status = 'released' AND seen_at IS NULL"
        );
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

/**
 * صندوق «للاطلاع» للمستخدم.
 *
 * @param 'unseen'|'seen'|'' $filter
 * @return array{rows:list<array<string,mixed>>,pagination:array,total:int}
 */
function corr_cc_inbox_page(int $userId, string $filter = '', int $page = 1): array
{
    ensure_corr_cc_schema();
    $where = "cc.user_id = ? AND cc.status = 'released'";
    $params = [$userId];
    if ($filter === 'unseen') {
        $where .= ' AND cc.seen_at IS NULL';
    } elseif ($filter === 'seen') {
        $where .= ' AND cc.seen_at IS NOT NULL';
    }
    try {
        $cnt = db()->prepare("SELECT COUNT(*) FROM `tblcorr_cc` cc WHERE {$where}");
        $cnt->execute($params);
        $total = (int) $cnt->fetchColumn();
        $pagination = dms_pagination_meta($total, $page, dms_list_per_page());
        $stmt = db()->prepare(
            "SELECT c.id, c.type, c.ref_number, c.subject, c.party_name, c.corr_date, c.status, c.cancel_kind,
                    cc.released_at, cc.seen_at,
                    u.name_ar AS creator_name, u.name_en AS creator_name_en
             FROM `tblcorr_cc` cc
             JOIN `tblcorrespondence` c ON c.id = cc.correspondence_id
             LEFT JOIN `tblusers` u ON u.id = c.created_by
             WHERE {$where}
             ORDER BY (cc.seen_at IS NULL) DESC, cc.released_at DESC, cc.id DESC
             LIMIT " . (int) $pagination['per_page'] . ' OFFSET ' . (int) $pagination['offset']
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll() ?: [];
    } catch (Throwable $e) {
        $total = 0;
        $pagination = dms_pagination_meta(0, 1, dms_list_per_page());
        $rows = [];
    }
    return ['rows' => $rows, 'pagination' => $pagination, 'total' => $total];
}
