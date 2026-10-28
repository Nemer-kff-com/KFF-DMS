<?php
/**
 * المعاملات الداخلية بين الأقسام (من قسم → إلى قسم)
 */

function ensure_internal_correspondence_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $pdo = db();
        // توسيع نوع المعاملة
        try {
            $pdo->exec(
                "ALTER TABLE `tblcorrespondence`
                 MODIFY COLUMN `type` ENUM('incoming','outgoing','internal') NOT NULL"
            );
        } catch (Throwable $e) {
            // قد يكون موسّعاً مسبقاً
        }
        ensure_sequences_schema();
        try {
            $pdo->exec(
                "ALTER TABLE `tblsequences`
                 MODIFY COLUMN `seq_type` " . (function_exists('dms_sequences_type_enum_sql')
                    ? dms_sequences_type_enum_sql()
                    : "ENUM('incoming','outgoing','internal','circular','archive') NOT NULL")
            );
        } catch (Throwable $e) {
            // تجاهل
        }
        if (!dms_db_column_exists('tblcorrespondence', 'internal_to_department_id')) {
            $pdo->exec(
                "ALTER TABLE `tblcorrespondence`
                 ADD COLUMN `internal_to_department_id` INT UNSIGNED NULL DEFAULT NULL
                 COMMENT 'الجهة المستلمة للمعاملة الداخلية — تُحال إليها بعد الاعتماد'"
            );
        }
        if (function_exists('ensure_settings_schema')) {
            ensure_settings_schema();
        }
        $stmt = $pdo->prepare('SELECT 1 FROM `tblsettings` WHERE setting_key = ? LIMIT 1');
        $stmt->execute(['internal_prefix']);
        if (!$stmt->fetchColumn()) {
            $pdo->prepare(
                'INSERT INTO `tblsettings` (setting_key, setting_value) VALUES (?, ?)'
            )->execute(['internal_prefix', '']);
        }
    } catch (Throwable $e) {
        $done = false;
    }
}

/**
 * إنشاء معاملة داخلية + إحالة تلقائية للقسم المستلم.
 *
 * @param array<string,mixed> $data
 * @param array<string,mixed>|null $file
 * @param array<string,mixed> $user
 */
function save_internal_correspondence(array $data, ?array $file, array $user): int
{
    if (!user_can('internal.create', $user)) {
        throw new RuntimeException(__('auth.forbidden'));
    }
    ensure_internal_correspondence_schema();
    if (function_exists('ensure_correspondence_receipt_schema')) {
        ensure_correspondence_receipt_schema();
    }

    $subject = trim((string) ($data['subject'] ?? ''));
    $notes = trim((string) ($data['notes'] ?? '')) ?: null;
    $corrDate = trim((string) ($data['corr_date'] ?? '')) ?: date('Y-m-d');
    $replyDue = trim((string) ($data['reply_due'] ?? '')) ?: null;
    $priority = trim((string) ($data['priority'] ?? 'normal')) ?: 'normal';
    $confidentiality = trim((string) ($data['confidentiality'] ?? 'normal')) ?: 'normal';
    $status = 'new';
    $categoryId = (int) ($data['category_id'] ?? 0);
    if ($categoryId > 0 && !in_array($categoryId, array_map('intval', array_column(categories_list(), 'id')), true)) {
        $categoryId = 0;
    }

    $fromDept = (int) ($data['from_department_id'] ?? 0);
    $toDept = (int) ($data['to_department_id'] ?? 0);

    if (!can_choose_correspondence_department($user)) {
        $fromDept = (int) (function_exists('resolve_assigned_department_id')
            ? resolve_assigned_department_id($user, $fromDept, false)
            : ($user['department_id'] ?? 0));
    }
    if ($fromDept <= 0) {
        $fromDept = function_exists('user_primary_department_id')
            ? (int) user_primary_department_id($user)
            : (int) ($user['department_id'] ?? 0);
    }

    if ($subject === '') {
        throw new InvalidArgumentException(__('internal.subject_required'));
    }
    if ($fromDept <= 0) {
        throw new InvalidArgumentException(__('corr.department_account_required'));
    }
    if ($toDept <= 0) {
        throw new InvalidArgumentException(__('internal.to_dept_required'));
    }
    if ($fromDept === $toDept) {
        throw new InvalidArgumentException(__('internal.same_dept'));
    }
    $ccIds = function_exists('corr_cc_ids_from_request') ? corr_cc_ids_from_request($data, (int) $user['id']) : [];
    if ($ccIds !== []) {
        corr_cc_assert_allowed('internal', $ccIds);
    }

    $uploadFiles = function_exists('dms_normalize_upload_files')
        ? dms_normalize_upload_files(is_array($file) ? $file : null)
        : [];
    // نفس أسلوب الوارد/الصادر: التحقق من كل مرفق قبل إنشاء السجل
    if ($uploadFiles !== [] && function_exists('dms_validate_document_upload')) {
        foreach ($uploadFiles as $uploadFile) {
            dms_validate_document_upload($uploadFile);
        }
    }

    $departments = departments_list();
    $fromRow = null;
    $toRow = null;
    foreach ($departments as $d) {
        if ((int) $d['id'] === $fromDept) {
            $fromRow = $d;
        }
        if ((int) $d['id'] === $toDept) {
            $toRow = $d;
        }
    }
    if (!$fromRow || !$toRow) {
        throw new InvalidArgumentException(__('entities.invalid_department'));
    }
    if (!can_choose_correspondence_department($user)
        && function_exists('user_can_access_department')
        && !user_can_access_department($fromDept, $user)
    ) {
        throw new RuntimeException(__('auth.forbidden'));
    }

    $fromName = department_display_name($fromRow);
    $toName = department_display_name($toRow);
    $party = $fromName . ' → ' . $toName;

    if (function_exists('ensure_correspondence_party_snapshot_schema')) {
        ensure_correspondence_party_snapshot_schema();
    }
    // لقطة ثابتة لمديري الأقسام (ومن→إلى) وقت الإنشاء — لا تتأثر بتعديل بطاقة القسم لاحقاً
    $fromSnap = function_exists('correspondence_capture_from_snapshot')
        ? correspondence_capture_from_snapshot($fromDept)
        : [
            'snap_from_prefix' => null,
            'snap_from_person' => null,
            'snap_from_job' => null,
            'snap_from_org' => null,
            'snap_from_signature' => null,
        ];
    $toSnap = internal_to_party_snapshot($toRow, $toName);

    $allowedPriority = array_values(array_filter(array_map(
        static fn(array $row): string => trim((string) ($row['code'] ?? '')),
        function_exists('priority_levels_list') ? priority_levels_list(false) : []
    ))) ?: ['normal', 'urgent'];
    $allowedConf = array_values(array_filter(array_map(
        static fn(array $row): string => trim((string) ($row['code'] ?? '')),
        function_exists('confidentiality_levels_list') ? confidentiality_levels_list(false) : []
    ))) ?: ['normal', 'confidential'];
    if (!in_array($priority, $allowedPriority, true)) {
        $priority = 'normal';
    }
    if (!in_array($confidentiality, $allowedConf, true)) {
        $confidentiality = 'normal';
    }

    $ref = next_ref_number('internal', $fromDept);
    $token = generate_qr_token();
    $pdo = db();
    $pdo->prepare(
        "INSERT INTO `tblcorrespondence`
        (type, ref_number, subject, party_name, entity_id,
         snap_title_prefix, snap_person_name, snap_job_title, snap_honorific, snap_org_name,
         snap_from_prefix, snap_from_person, snap_from_job, snap_from_org, snap_from_signature,
         external_ref, corr_date, reply_due,
         status, priority, confidentiality, category_id, notes, qr_token, linked_id,
         department_id, issuing_department_id, internal_to_department_id, created_by, rsvp_required)
        VALUES ('internal', ?, ?, ?, NULL,
         ?, ?, ?, ?, ?,
         ?, ?, ?, ?, ?,
         NULL, ?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, ?, ?, 0)"
    )->execute([
        $ref,
        $subject,
        $party,
        $toSnap['snap_title_prefix'],
        $toSnap['snap_person_name'],
        $toSnap['snap_job_title'],
        $toSnap['snap_honorific'],
        $toSnap['snap_org_name'],
        $fromSnap['snap_from_prefix'],
        $fromSnap['snap_from_person'],
        $fromSnap['snap_from_job'],
        $fromSnap['snap_from_org'],
        $fromSnap['snap_from_signature'],
        $corrDate,
        $replyDue,
        $status,
        $priority,
        $confidentiality,
        $categoryId > 0 ? $categoryId : null,
        $notes,
        $token,
        $fromDept,
        $fromDept,
        $toDept,
        (int) $user['id'],
    ]);
    $corrId = (int) $pdo->lastInsertId();
    log_activity((int) $user['id'], $corrId, 'create', 'إنشاء معاملة داخلية رقم ' . $ref);

    if ($ccIds !== []) {
        corr_cc_set_pending($corrId, $ccIds, (int) $user['id']);
    }
    if (!internal_approval_hold_on_create($corrId, $user)) {
        internal_route_to_department($corrId, $user, $toDept, $notes);
        if ($ccIds !== [] && function_exists('corr_cc_release')) {
            corr_cc_release($corrId, $user);
        }
    }

    if ($uploadFiles !== [] && function_exists('upload_document')) {
        foreach ($uploadFiles as $uploadFile) {
            upload_document($uploadFile, $corrId, (int) $user['id']);
        }
        log_activity((int) $user['id'], $corrId, 'upload', 'رفع مرفق');
    }

    if (function_exists('save_correspondence_enclosures')) {
        save_correspondence_enclosures($corrId, correspondence_enclosures_from_request($data));
    }

    return $corrId;
}

/** لقطة «موجهة إلى» للجهة المستلمة */
function internal_to_party_snapshot(array $toRow, string $toName): array
{
    if (function_exists('correspondence_capture_department_party_snapshot')) {
        return correspondence_capture_department_party_snapshot($toRow, $toName);
    }
    return [
        'snap_title_prefix' => trim((string) ($toRow['title_prefix'] ?? '')) ?: null,
        'snap_person_name' => trim((string) ($toRow['directed_from'] ?? '')) ?: null,
        'snap_job_title' => trim((string) ($toRow['job_title'] ?? '')) ?: null,
        'snap_honorific' => null,
        'snap_org_name' => $toName !== '' ? $toName : null,
    ];
}

/** الجهة المستلمة للمعاملة الداخلية (قبل الاعتماد تُحفظ منفصلة عن القسم الحالي) */
function internal_target_department_id(array $corr): int
{
    return (int) ($corr['internal_to_department_id'] ?? 0) ?: (int) ($corr['department_id'] ?? 0);
}

function internal_approval_is_held(array $corr): bool
{
    return ($corr['type'] ?? '') === 'internal'
        && function_exists('incoming_approval_held_statuses')
        && in_array(trim((string) ($corr['issue_approval_status'] ?? '')), incoming_approval_held_statuses(), true);
}

/**
 * إحالة المعاملة الداخلية إلى الجهة المستلمة وإشعارها
 * (القسم المعدّ يبقى في issuing_department_id للصلاحيات).
 */
function internal_route_to_department(int $corrId, array $user, int $toDept, ?string $notes): void
{
    $corr = get_correspondence($corrId);
    if (!$corr || ($corr['type'] ?? '') !== 'internal' || $toDept <= 0) {
        return;
    }
    $fromDept = (int) ($corr['issuing_department_id'] ?? 0) ?: (int) ($corr['department_id'] ?? 0);
    $pdo = db();
    $pdo->prepare(
        "INSERT INTO `tblreferrals` (correspondence_id, from_department_id, to_department_id, from_user_id, notes, status)
         VALUES (?, ?, ?, ?, ?, 'pending')"
    )->execute([$corrId, $fromDept, $toDept, (int) $user['id'], $notes]);
    $referralId = (int) $pdo->lastInsertId();
    $pdo->prepare("UPDATE `tblcorrespondence` SET status = IF(status = 'new', 'referred', status), department_id = ? WHERE id = ?")
        ->execute([$toDept, $corrId]);
    $toName = '';
    foreach (departments_list() as $d) {
        if ((int) $d['id'] === $toDept) {
            $toName = department_display_name($d);
            break;
        }
    }
    log_activity((int) $user['id'], $corrId, 'refer', 'إحالة تلقائية إلى ' . $toName);

    $fresh = get_correspondence($corrId);
    if ($fresh && function_exists('dms_notify_referral')) {
        dms_notify_referral($referralId, $fresh, $toDept, user_actor_name($user), $user);
    }
}

/**
 * عند إنشاء معاملة داخلية: إن كان اعتماد الداخلية مفعّلاً وهناك مراجع أو معتمد مؤهَّل
 * تُحجز بدل الإحالة. @return bool true = حُجزت (لا تُحال الآن)
 */
function internal_approval_hold_on_create(int $corrId, array $user): bool
{
    if (!function_exists('outgoing_approval_start')) {
        return false;
    }
    ensure_outgoing_approval_schema();
    $corr = get_correspondence($corrId);
    if (!$corr || ($corr['type'] ?? '') !== 'internal') {
        return false;
    }
    return appr_with('internal', static function () use ($corr, $user): bool {
        if (!outgoing_approval_is_configured()) {
            return false;
        }
        try {
            outgoing_approval_start($corr, $user);
            return true;
        } catch (RuntimeException $e) {
            // لا مراجع ولا معتمد مؤهَّل — تُحال مباشرة كما لو كان الاعتماد معطّلاً
            return false;
        }
    });
}

/**
 * تغيير الجهة المستلمة أثناء الحجز (من المنشئ عند الإعادة). يعيد true إن تغيّرت فعلاً.
 */
function internal_approval_set_target(int $corrId, array $user, int $newToDept): bool
{
    $corr = get_correspondence($corrId);
    if (!$corr || ($corr['type'] ?? '') !== 'internal' || !internal_approval_is_held($corr)) {
        return false;
    }
    $current = internal_target_department_id($corr);
    if ($newToDept <= 0 || $newToDept === $current) {
        return false;
    }
    $fromDept = (int) ($corr['issuing_department_id'] ?? 0);
    if ($newToDept === $fromDept) {
        throw new InvalidArgumentException(__('internal.same_dept'));
    }
    $fromRow = null;
    $toRow = null;
    foreach (departments_list() as $d) {
        if ((int) $d['id'] === $fromDept) {
            $fromRow = $d;
        }
        if ((int) $d['id'] === $newToDept) {
            $toRow = $d;
        }
    }
    if (!$toRow) {
        throw new InvalidArgumentException(__('internal.to_dept_required'));
    }
    $toName = department_display_name($toRow);
    $party = ($fromRow ? department_display_name($fromRow) : '') . ' → ' . $toName;
    $snap = internal_to_party_snapshot($toRow, $toName);
    db()->prepare(
        "UPDATE `tblcorrespondence` SET internal_to_department_id = ?, party_name = ?,
            snap_title_prefix = ?, snap_person_name = ?, snap_job_title = ?, snap_honorific = ?, snap_org_name = ?
         WHERE id = ? AND type = 'internal'"
    )->execute([
        $newToDept,
        $party,
        $snap['snap_title_prefix'],
        $snap['snap_person_name'],
        $snap['snap_job_title'],
        $snap['snap_honorific'],
        $snap['snap_org_name'],
        $corrId,
    ]);
    log_activity((int) $user['id'], $corrId, 'internal_route_change', __('activity.action.internal_route_change') . ': ' . $toName);
    return true;
}

/**
 * قرار معتمد المعاملة الداخلية: اعتماد (تُحال للجهة المستلمة) أو إعادة للمنشئ بسبب.
 *
 * @param 'approve'|'return' $decision
 */
function internal_approval_decide(int $corrId, array $user, string $decision, string $note = ''): void
{
    ensure_outgoing_approval_schema();
    $corr = get_correspondence($corrId);
    if (!$corr || ($corr['type'] ?? '') !== 'internal') {
        throw new RuntimeException(__('corr.not_found'));
    }
    if (!in_array($decision, ['approve', 'return'], true)) {
        throw new InvalidArgumentException(__('profile.invalid_action'));
    }
    appr_with('internal', static function () use ($corrId, $corr, $user, $decision, $note): void {
        if (!outgoing_user_can_decide_approval($user, $corr)) {
            throw new RuntimeException(__('auth.forbidden'));
        }
        outgoing_approval_escalate_if_due($corrId);
        $corr = get_correspondence($corrId) ?: $corr;
        if (!outgoing_user_can_decide_approval($user, $corr)) {
            throw new RuntimeException(__('auth.forbidden'));
        }

        $note = trim($note);
        if ($decision === 'return' && $note === '') {
            throw new InvalidArgumentException(__('int_issue_appr.return_note_required'));
        }
        $isOverride = appr_is_admin_decide_override($user, $corr);
        $isAlternate = !$isOverride && outgoing_user_is_alternate_approver($user, $corr);
        if ($isAlternate && $note === '') {
            throw new InvalidArgumentException(appr__('issue_appr.alternate_reason_required'));
        }
        if ($isAlternate) {
            $prefix = outgoing_appr_text('issue_appr.alternate_reason_prefix');
            if ($prefix !== '' && !str_starts_with($note, $prefix)) {
                $note = $prefix . ' ' . $note;
            }
        }
        if (mb_strlen($note) > 500) {
            $note = mb_substr($note, 0, 500);
        }

        if ($isOverride) {
            $note = appr_admin_override_note($note);
        }
        $uid = (int) $user['id'];
        $status = $decision === 'return' ? 'returned' : 'approved';
        $stmt = db()->prepare(
            "UPDATE `tblcorrespondence` SET
                issue_approval_status = ?,
                issue_approval_decided_at = ?,
                issue_approval_decided_by = ?,
                issue_approval_note = ?,
                issue_approval_assignee_id = NULL
             WHERE id = ? AND issue_approval_status IN ('pending_sg','pending_deputy','pending_alternate')"
        );
        $stmt->execute([$status, date('Y-m-d H:i:s'), $uid, $note !== '' ? $note : null, $corrId]);
        if ($stmt->rowCount() === 0) {
            throw new RuntimeException(__('auth.forbidden'));
        }

        $action = 'internal_approval_' . $decision;
        log_activity($uid, $corrId, $action, __('activity.action.' . $action) . ($note !== '' ? ': ' . $note : ''));

        if ($decision === 'approve') {
            $referralNote = $note !== '' ? $note : (trim((string) ($corr['notes'] ?? '')) ?: null);
            internal_route_to_department($corrId, $user, internal_target_department_id($corr), $referralNote);
            if (function_exists('corr_cc_release')) {
                corr_cc_release($corrId, $user);
            }
        }

        $fresh = get_correspondence($corrId) ?: $corr;
        $notifyIds = array_values(array_unique(array_filter([
            (int) ($corr['created_by'] ?? 0),
            (int) ($corr['issue_approval_requested_by'] ?? 0),
        ], static fn (int $id): bool => $id > 0 && $id !== $uid)));
        if ($notifyIds !== [] && function_exists('dms_notify_users')) {
            $ref = format_ref_number($corr['ref_number'] ?? $corrId);
            $subjectLine = trim((string) ($corr['subject'] ?? ''));
            $key = $decision === 'return' ? 'returned' : 'approved';
            dms_notify_users($notifyIds, [
                'type' => 'outgoing_issue_approval',
                'title' => __('int_issue_appr.notify_' . $key . '_title'),
                'body' => str_replace(':ref', $ref, __('int_issue_appr.notify_' . $key . '_body')),
                'link' => 'internal/view.php?id=' . $corrId,
                'correspondence_id' => $corrId,
                'department_id' => (int) ($corr['issuing_department_id'] ?? 0) ?: null,
                'dedupe_key' => 'int_appr_dec_' . $corrId . '_' . $key . '_' . time(),
                'email' => true,
                'email_intro' => __('int_issue_appr.email_' . $key . '_intro'),
                'email_details' => trim($ref . ($subjectLine !== '' ? ' — ' . $subjectLine : '') . ($note !== '' ? ' — ' . $note : '')),
                'email_rows' => outgoing_approval_email_rows($fresh),
                'email_cta' => __('notify.email_view_correspondence'),
            ]);
        }
    });
}
