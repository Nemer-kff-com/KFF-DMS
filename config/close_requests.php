<?php
/**
 * طلب إقفال الوارد والمعاملة الداخلية: من ليس صاحب القرار يرسل طلباً،
 * وصاحب القرار (قسم آخر إحالة مفتوحة، وإلا القسم الحالي للمعاملة) يوافق فيُقفل، أو يرفض، أو يحيل فيُلغى الطلب.
 * مدير النظام يُقفل مباشرة بسبب إلزامي إن لم يكن من قسم صاحب القرار.
 */

declare(strict_types=1);

function ensure_close_requests_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        db()->exec(
            "CREATE TABLE IF NOT EXISTS `tblclose_requests` (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                correspondence_id INT UNSIGNED NOT NULL,
                requested_by INT UNSIGNED NOT NULL,
                decider_department_id INT UNSIGNED NULL DEFAULT NULL,
                payload TEXT NOT NULL COMMENT 'بيانات الإقفال المقترحة (JSON)',
                status ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
                decided_by INT UNSIGNED NULL DEFAULT NULL,
                decided_at DATETIME NULL DEFAULT NULL,
                decision_note VARCHAR(500) NULL DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_close_req_corr (correspondence_id, status),
                CONSTRAINT fk_close_req_corr FOREIGN KEY (correspondence_id) REFERENCES `tblcorrespondence`(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    } catch (Throwable $e) {
        // قاعدة غير جاهزة أثناء التثبيت
    }
}

function close_request_applies(array $corr): bool
{
    return in_array($corr['type'] ?? '', ['incoming', 'internal'], true);
}

/** قسم صاحب القرار: آخر إحالة مفتوحة، وإلا القسم الحالي للمعاملة (يتبع آخر إحالة) */
function close_decider_department_id(array $corr): int
{
    $corrId = (int) ($corr['id'] ?? 0);
    if ($corrId > 0) {
        try {
            $stmt = db()->prepare(
                "SELECT to_department_id FROM `tblreferrals`
                 WHERE correspondence_id = ? AND status IN ('pending', 'received')
                 ORDER BY id DESC LIMIT 1"
            );
            $stmt->execute([$corrId]);
            $dept = (int) ($stmt->fetchColumn() ?: 0);
            if ($dept > 0) {
                return $dept;
            }
        } catch (Throwable $e) {
            // يُكمل بالقسم الحالي
        }
    }
    $dept = (int) ($corr['department_id'] ?? 0);
    if ($dept <= 0 && ($corr['type'] ?? '') === 'internal' && function_exists('internal_target_department_id')) {
        $dept = internal_target_department_id($corr);
    }
    return $dept;
}

function close_user_is_decider(array $user, array $corr): bool
{
    $dept = close_decider_department_id($corr);
    return $dept > 0 && user_can('correspondence.close', $user)
        && in_array($dept, array_map('intval', user_department_ids($user)), true);
}

/** يُقفل مباشرة: الصادر كما كان، ولوارد/داخلية: صاحب القرار أو مدير النظام (أو لا يوجد قسم صاحب قرار) */
function close_user_can_close_direct(array $user, array $corr): bool
{
    if (!close_request_applies($corr) || ($user['role'] ?? '') === 'admin') {
        return true;
    }
    return close_decider_department_id($corr) <= 0 || close_user_is_decider($user, $corr);
}

function close_user_needs_admin_reason(array $user, array $corr): bool
{
    return close_request_applies($corr) && ($user['role'] ?? '') === 'admin'
        && close_decider_department_id($corr) > 0 && !close_user_is_decider($user, $corr);
}

function close_department_name(int $deptId): string
{
    if ($deptId <= 0) {
        return '';
    }
    try {
        $stmt = db()->prepare('SELECT name, name_en FROM `tbldepartments` WHERE id = ? LIMIT 1');
        $stmt->execute([$deptId]);
        $row = $stmt->fetch();
        return $row ? department_display_name($row) : '';
    } catch (Throwable $e) {
        return '';
    }
}

/** @return array<string,mixed>|null */
function close_request_pending(int $corrId): ?array
{
    ensure_close_requests_schema();
    try {
        $stmt = db()->prepare(
            "SELECT r.*, " . user_name_sql('u', 'requested_by_name') . "
             FROM `tblclose_requests` r
             LEFT JOIN `tblusers` u ON u.id = r.requested_by
             WHERE r.correspondence_id = ? AND r.status = 'pending'
             ORDER BY r.id DESC LIMIT 1"
        );
        $stmt->execute([$corrId]);
        $row = $stmt->fetch();
    } catch (Throwable $e) {
        return null;
    }
    if (!$row) {
        return null;
    }
    $row['data'] = json_decode((string) $row['payload'], true) ?: [];
    return $row;
}

function close_request_submit(int $corrId, array $post, array $user): void
{
    ensure_close_requests_schema();
    ensure_correspondence_closure_schema();
    $corr = get_correspondence($corrId);
    if (!$corr || !user_can_access_correspondence($corr, $user)) {
        throw new RuntimeException(__('corr.not_found'));
    }
    if (!user_can('correspondence.close', $user)) {
        throw new RuntimeException(__('auth.forbidden'));
    }
    if (correspondence_is_cancelled($corr)) {
        throw new RuntimeException(__('cancel.locked'));
    }
    if (correspondence_is_closed($corr)) {
        throw new RuntimeException(__('close_req.already_closed'));
    }
    if (function_exists('corr_approval_is_held') && corr_approval_is_held($corr)) {
        throw new RuntimeException(corr_held_text($corr, 'close_blocked'));
    }
    if (!close_request_applies($corr) || close_user_can_close_direct($user, $corr)) {
        throw new RuntimeException(__('close_req.use_direct'));
    }
    if (close_request_pending($corrId)) {
        throw new RuntimeException(__('close_req.already_pending'));
    }
    $data = correspondence_closure_from_request($post);
    if (mb_strlen($data['close_action']) < 3) {
        throw new InvalidArgumentException(__('close.action_required'));
    }
    if ($data['close_result'] === '') {
        throw new InvalidArgumentException(__('close.result_required'));
    }
    if ($data['close_attach_other'] && trim((string) $data['close_attach_other_text']) === '') {
        throw new InvalidArgumentException(__('close.other_text_required'));
    }
    $deciderDept = close_decider_department_id($corr);
    db()->prepare(
        "INSERT INTO `tblclose_requests` (correspondence_id, requested_by, decider_department_id, payload)
         VALUES (?, ?, ?, ?)"
    )->execute([$corrId, (int) $user['id'], $deciderDept ?: null, json_encode($data, JSON_UNESCAPED_UNICODE)]);

    $deptName = close_department_name($deciderDept);
    log_activity((int) $user['id'], $corrId, 'close_request',
        correspondence_close_result_label($data['close_result']) . ($deptName !== '' ? ' — ' . $deptName : ''));
    close_request_notify_decider($corr, $user, $data, $deciderDept);
}

function close_request_notify_decider(array $corr, array $actor, array $data, int $deptId): void
{
    if (!function_exists('dms_notify_users')) {
        return;
    }
    $actorId = (int) ($actor['id'] ?? 0);
    $ids = $deptId > 0 ? dms_notification_recipient_ids($deptId, [$actorId]) : [];
    if ($ids === []) {
        try {
            $ids = array_map('intval', db()->query("SELECT id FROM `tblusers` WHERE role = 'admin' AND is_active = 1")->fetchAll(PDO::FETCH_COLUMN) ?: []);
            $ids = array_values(array_diff($ids, [$actorId]));
        } catch (Throwable $e) {
            $ids = [];
        }
    }
    if ($ids === []) {
        return;
    }
    $corrId = (int) $corr['id'];
    $ref = format_ref_number($corr['ref_number'] ?? '');
    $subject = trim((string) ($corr['subject'] ?? ''));
    $actorName = trim(user_actor_name($actor));
    $result = correspondence_close_result_label((string) ($data['close_result'] ?? ''));
    dms_notify_users($ids, [
        'type' => 'close_request',
        'title' => __('close_req.notify_title'),
        'body' => implode(' — ', array_filter([$ref, $subject, $actorName])),
        'link' => correspondence_folder_for_type((string) ($corr['type'] ?? 'incoming')) . '/view.php?id=' . $corrId,
        'correspondence_id' => $corrId,
        'department_id' => $deptId ?: null,
        'dedupe_key' => 'close_req:' . $corrId . ':' . date('YmdHis'),
        'email' => true,
        'email_intro' => __('close_req.notify_title'),
        'email_rows' => [
            ['label' => __('notify.closed_row_ref'), 'value' => $ref, 'ltr' => true],
            ['label' => __('notify.closed_row_subject'), 'value' => $subject],
            ['label' => __('close_req.row_by'), 'value' => $actorName],
            ['label' => __('close.result_section'), 'value' => $result],
            ['label' => __('close.action_section'), 'value' => mb_substr(trim((string) ($data['close_action'] ?? '')), 0, 500)],
            ['label' => __('notify.closed_row_next'), 'value' => __('close_req.notify_next')],
        ],
    ]);
}

/** @return array<string,mixed> */
function close_request_load_pending_by_id(int $reqId): array
{
    ensure_close_requests_schema();
    $stmt = db()->prepare("SELECT * FROM `tblclose_requests` WHERE id = ? AND status = 'pending'");
    $stmt->execute([$reqId]);
    $req = $stmt->fetch();
    if (!$req) {
        throw new RuntimeException(__('close_req.not_pending'));
    }
    return $req;
}

function close_request_reject(int $reqId, string $note, array $user): int
{
    $req = close_request_load_pending_by_id($reqId);
    $corrId = (int) $req['correspondence_id'];
    $corr = get_correspondence($corrId);
    if (!$corr || !user_can_access_correspondence($corr, $user) || !close_user_can_close_direct($user, $corr)) {
        throw new RuntimeException(__('auth.forbidden'));
    }
    $note = trim($note);
    if (mb_strlen($note) < 3) {
        throw new InvalidArgumentException(__('close_req.reject_note_required'));
    }
    close_request_finish($req, 'rejected', $user, mb_substr($note, 0, 500));
    log_activity((int) $user['id'], $corrId, 'close_request_reject', $note);
    close_request_notify_requester($req, $corr, $user, 'rejected', $note);
    return $corrId;
}

function close_request_withdraw(int $reqId, array $user): int
{
    $req = close_request_load_pending_by_id($reqId);
    $corrId = (int) $req['correspondence_id'];
    if ((int) $req['requested_by'] !== (int) $user['id'] && ($user['role'] ?? '') !== 'admin') {
        throw new RuntimeException(__('auth.forbidden'));
    }
    close_request_finish($req, 'cancelled', $user, __('close_req.withdrawn_note'));
    log_activity((int) $user['id'], $corrId, 'close_request_cancel', __('close_req.withdrawn_note'));
    return $corrId;
}

/** عند إقفال المعاملة: أي طلب مفتوح يُعتبر موافقاً عليه */
function close_request_resolve_on_close(array $corr, array $user): void
{
    $req = close_request_pending((int) $corr['id']);
    if (!$req) {
        return;
    }
    close_request_finish($req, 'approved', $user, null);
    $requester = (int) $req['requested_by'];
    if ($requester !== (int) ($corr['created_by'] ?? 0)) {
        close_request_notify_requester($req, $corr, $user, 'approved', '');
    }
}

/** عند إحالة المعاملة: يُلغى الطلب المفتوح ويُشعَر مقدّمه */
function close_request_cancel_on_referral(array $corr, int $toDept, array $user): void
{
    $req = close_request_pending((int) $corr['id']);
    if (!$req) {
        return;
    }
    $note = str_replace(':dept', close_department_name($toDept), __('close_req.cancelled_referral'));
    close_request_finish($req, 'cancelled', $user, $note);
    log_activity((int) $user['id'], (int) $corr['id'], 'close_request_cancel', $note);
    close_request_notify_requester($req, $corr, $user, 'cancelled', $note);
}

function close_request_finish(array $req, string $status, array $user, ?string $note): void
{
    db()->prepare(
        "UPDATE `tblclose_requests` SET status = ?, decided_by = ?, decided_at = NOW(), decision_note = ?
         WHERE id = ? AND status = 'pending'"
    )->execute([$status, (int) $user['id'], $note, (int) $req['id']]);
}

function close_request_notify_requester(array $req, array $corr, array $actor, string $outcome, string $note): void
{
    $requester = (int) $req['requested_by'];
    if ($requester <= 0 || $requester === (int) ($actor['id'] ?? 0) || !function_exists('dms_notify_users')) {
        return;
    }
    $corrId = (int) $corr['id'];
    $ref = format_ref_number($corr['ref_number'] ?? '');
    $subject = trim((string) ($corr['subject'] ?? ''));
    $actorName = trim(user_actor_name($actor));
    $title = __('close_req.notify_' . $outcome . '_title');
    $rows = [
        ['label' => __('notify.closed_row_ref'), 'value' => $ref, 'ltr' => true],
        ['label' => __('notify.closed_row_subject'), 'value' => $subject],
        ['label' => __('close_req.row_decided_by'), 'value' => $actorName],
    ];
    if ($note !== '') {
        $rows[] = ['label' => __('close_req.row_note'), 'value' => $note];
    }
    dms_notify_users([$requester], [
        'type' => 'close_request_decided',
        'title' => $title,
        'body' => implode(' — ', array_filter([$ref, $subject, $actorName, $note])),
        'link' => correspondence_folder_for_type((string) ($corr['type'] ?? 'incoming')) . '/view.php?id=' . $corrId,
        'correspondence_id' => $corrId,
        'department_id' => (int) ($corr['department_id'] ?? 0) ?: null,
        'dedupe_key' => 'close_req_' . $outcome . ':' . (int) $req['id'],
        'email' => $outcome !== 'approved',
        'email_intro' => $title,
        'email_rows' => $rows,
    ]);
}
