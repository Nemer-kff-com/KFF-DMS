<?php
/**
 * إقفال المعاملات (وارد / صادر) — نموذج الإجراء والنتيجة
 */

declare(strict_types=1);

function correspondence_close_result_codes(): array
{
    return ['completed', 'executed', 'for_archive'];
}

function correspondence_close_result_label(string $code): string
{
    $key = 'close.result_' . $code;
    $label = __($key);
    return $label !== $key ? $label : $code;
}

function correspondence_close_attach_flags(): array
{
    return ['decision', 'letter', 'proof', 'other'];
}

/** @return array<string,string> flagKey => doc_kind (للتوافق مع رفع قديم لكل نوع) */
function correspondence_close_doc_kinds(): array
{
    return [
        'decision' => 'close_decision',
        'letter' => 'close_letter',
        'proof' => 'close_proof',
        'other' => 'close_other',
    ];
}

/** نوع مرفق الإقفال الموحّد في الرفع الحالي */
function correspondence_close_doc_kind(): string
{
    return 'close_final';
}

/** كل قيم doc_kind الخاصة بالإقفال (الحالي + القديم) */
function correspondence_close_doc_kind_values(): array
{
    $kinds = array_values(correspondence_close_doc_kinds());
    $kinds[] = correspondence_close_doc_kind();
    return array_values(array_unique($kinds));
}

function correspondence_close_doc_kind_label(string $docKind): string
{
    if ($docKind === correspondence_close_doc_kind() || $docKind === 'close_final') {
        return __('close.attach_section');
    }
    foreach (correspondence_close_doc_kinds() as $flag => $kind) {
        if ($kind === $docKind) {
            return __('close.attach_' . $flag);
        }
    }
    return $docKind;
}

function correspondence_is_closed(?array $corr): bool
{
    return ($corr['status'] ?? '') === 'closed';
}

function correspondence_has_closure_data(?array $corr): bool
{
    if (!$corr) {
        return false;
    }
    return trim((string) ($corr['close_action'] ?? '')) !== ''
        || !empty($corr['closed_at'])
        || correspondence_is_closed($corr);
}

function ensure_correspondence_closure_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    if (function_exists('ensure_correspondence_status_schema')) {
        ensure_correspondence_status_schema();
    }
    if (function_exists('ensure_documents_doc_kind_schema')) {
        ensure_documents_doc_kind_schema();
    }
    try {
        $cols = dms_db_column_map(DmsDbTable::CORRESPONDENCE);
        $alters = [];
        if (empty($cols['close_action'])) {
            $alters[] = "ADD COLUMN `close_action` TEXT NULL COMMENT 'الإجراء المتخذ' AFTER `notes`";
        }
        if (empty($cols['close_decision_no'])) {
            $alters[] = "ADD COLUMN `close_decision_no` VARCHAR(100) NULL DEFAULT NULL COMMENT 'رقم القرار' AFTER `close_action`";
        }
        if (empty($cols['close_decision_date'])) {
            $alters[] = "ADD COLUMN `close_decision_date` DATE NULL DEFAULT NULL COMMENT 'تاريخ القرار' AFTER `close_decision_no`";
        }
        if (empty($cols['close_result'])) {
            $alters[] = "ADD COLUMN `close_result` ENUM('completed','executed','for_archive') NULL DEFAULT NULL COMMENT 'النتيجة النهائية' AFTER `close_decision_date`";
        }
        if (empty($cols['close_attach_decision'])) {
            $alters[] = "ADD COLUMN `close_attach_decision` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'مرفق: القرار' AFTER `close_result`";
        }
        if (empty($cols['close_attach_letter'])) {
            $alters[] = "ADD COLUMN `close_attach_letter` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'مرفق: خطاب نهائي' AFTER `close_attach_decision`";
        }
        if (empty($cols['close_attach_proof'])) {
            $alters[] = "ADD COLUMN `close_attach_proof` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'مرفق: إثبات تنفيذ' AFTER `close_attach_letter`";
        }
        if (empty($cols['close_attach_other'])) {
            $alters[] = "ADD COLUMN `close_attach_other` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'مرفق: أخرى' AFTER `close_attach_proof`";
        }
        if (empty($cols['close_attach_other_text'])) {
            $alters[] = "ADD COLUMN `close_attach_other_text` VARCHAR(255) NULL DEFAULT NULL COMMENT 'وصف مرفق آخر' AFTER `close_attach_other`";
        }
        if (empty($cols['close_notes'])) {
            $alters[] = "ADD COLUMN `close_notes` TEXT NULL COMMENT 'ملاحظات الإقفال' AFTER `close_attach_other_text`";
        }
        if (empty($cols['closed_at'])) {
            $alters[] = "ADD COLUMN `closed_at` DATETIME NULL DEFAULT NULL COMMENT 'وقت الإقفال' AFTER `close_notes`";
        }
        if (empty($cols['closed_by'])) {
            $alters[] = "ADD COLUMN `closed_by` INT UNSIGNED NULL DEFAULT NULL COMMENT 'من أقفل' AFTER `closed_at`";
        }
        if ($alters) {
            db()->exec('ALTER TABLE `' . DmsDbTable::CORRESPONDENCE . '` ' . implode(', ', $alters));
        }
    } catch (Throwable $e) {
        // قاعدة غير جاهزة أثناء التثبيت
    }
}

/**
 * @return array{close_action:string,close_decision_no:string,close_decision_date:?string,close_result:string,close_attach_decision:int,close_attach_letter:int,close_attach_proof:int,close_attach_other:int,close_attach_other_text:string,close_notes:string}
 */
function correspondence_closure_from_request(array $post): array
{
    $action = trim((string) ($post['close_action'] ?? ''));
    $decisionNo = trim((string) ($post['close_decision_no'] ?? ''));
    $decisionDate = trim((string) ($post['close_decision_date'] ?? ''));
    $result = trim((string) ($post['close_result'] ?? ''));
    $otherText = trim((string) ($post['close_attach_other_text'] ?? ''));
    $notes = trim((string) ($post['close_notes'] ?? ''));

    if ($decisionDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $decisionDate)) {
        $decisionDate = null;
    }
    if (!in_array($result, correspondence_close_result_codes(), true)) {
        $result = '';
    }

    $attachOther = !empty($post['close_attach_other']) ? 1 : 0;
    if ($attachOther === 0) {
        $otherText = '';
    }

    return [
        'close_action' => mb_substr($action, 0, 5000),
        'close_decision_no' => mb_substr($decisionNo, 0, 100),
        'close_decision_date' => $decisionDate,
        'close_result' => $result,
        'close_attach_decision' => !empty($post['close_attach_decision']) ? 1 : 0,
        'close_attach_letter' => !empty($post['close_attach_letter']) ? 1 : 0,
        'close_attach_proof' => !empty($post['close_attach_proof']) ? 1 : 0,
        'close_attach_other' => $attachOther,
        'close_attach_other_text' => mb_substr($otherText, 0, 255),
        'close_notes' => mb_substr($notes, 0, 2000),
    ];
}

/**
 * تطبيع حقول رفع متعددة من $_FILES.
 * @return list<array>
 */
function correspondence_normalize_upload_files(array $fileField): array
{
    if ($fileField === []) {
        return [];
    }
    if (!isset($fileField['name']) || !is_array($fileField['name'])) {
        return [$fileField];
    }
    $out = [];
    foreach ($fileField['name'] as $i => $name) {
        $out[] = [
            'name' => $name,
            'type' => $fileField['type'][$i] ?? '',
            'tmp_name' => $fileField['tmp_name'][$i] ?? '',
            'error' => $fileField['error'][$i] ?? UPLOAD_ERR_NO_FILE,
            'size' => $fileField['size'][$i] ?? 0,
        ];
    }
    return $out;
}

/**
 * رفع ملفات المرفقات النهائية من حقل واحد.
 * @return array{uploaded:int}
 */
function correspondence_upload_close_files(int $corrId, array $files, array $user): array
{
    ensure_documents_doc_kind_schema();
    $uploaded = 0;
    $items = correspondence_normalize_upload_files($files['close_files'] ?? []);
    $docKind = correspondence_close_doc_kind();
    foreach ($items as $file) {
        $row = upload_document($file, $corrId, (int) $user['id'], $docKind);
        if ($row) {
            $uploaded++;
        }
    }
    return ['uploaded' => $uploaded];
}

/** @return list<array> */
function get_closure_documents(int $corrId): array
{
    ensure_documents_doc_kind_schema();
    $kinds = correspondence_close_doc_kind_values();
    $placeholders = implode(',', array_fill(0, count($kinds), '?'));
    $stmt = db()->prepare(
        "SELECT d.*, " . user_name_sql('u', 'uploader_name') . "
         FROM `" . DmsDbTable::DOCUMENTS . "` d
         LEFT JOIN `" . DmsDbTable::USERS . "` u ON u.id = d.uploaded_by
         WHERE d.correspondence_id = ? AND d.doc_kind IN ($placeholders)
         ORDER BY d.uploaded_at DESC"
    );
    $stmt->execute(array_merge([$corrId], $kinds));
    return $stmt->fetchAll();
}

/**
 * إقفال أو تحديث بيانات الإقفال ثم ضبط الحالة إلى closed.
 */
function close_correspondence(int $id, array $post, array $user, array $files = []): void
{
    if (!user_can('correspondence.close', $user)) {
        throw new RuntimeException(__('auth.forbidden'));
    }
    ensure_correspondence_closure_schema();
    $corr = get_correspondence($id);
    if (!$corr || !user_can_access_correspondence($corr, $user)) {
        throw new RuntimeException(__('corr.not_found'));
    }
    if (correspondence_is_cancelled($corr)) {
        throw new RuntimeException(__('cancel.locked'));
    }
    if (function_exists('corr_approval_is_held') && corr_approval_is_held($corr)) {
        throw new RuntimeException(corr_held_text($corr, 'close_blocked'));
    }
    if (($corr['type'] ?? '') === 'outgoing' && function_exists('outgoing_can_issue') && !outgoing_can_issue($corr)
        && !correspondence_is_closed($corr)) {
        throw new RuntimeException(__('issue_appr.close_blocked'));
    }
    $adminReason = '';
    if (!correspondence_is_closed($corr) && function_exists('close_user_can_close_direct')) {
        if (!close_user_can_close_direct($user, $corr)) {
            throw new RuntimeException(__('close_req.use_request'));
        }
        if (close_user_needs_admin_reason($user, $corr)) {
            $adminReason = trim((string) ($post['admin_close_reason'] ?? ''));
            if (mb_strlen($adminReason) < 5) {
                throw new InvalidArgumentException(__('close_req.admin_reason_required'));
            }
            $adminReason = mb_substr($adminReason, 0, 500);
        }
    }

    $data = correspondence_closure_from_request($post);
    $uploadInfo = correspondence_upload_close_files($id, $files, $user);

    if (mb_strlen($data['close_action']) < 3) {
        throw new InvalidArgumentException(__('close.action_required'));
    }
    if ($data['close_result'] === '') {
        throw new InvalidArgumentException(__('close.result_required'));
    }
    if ($data['close_attach_other'] && trim((string) $data['close_attach_other_text']) === '') {
        throw new InvalidArgumentException(__('close.other_text_required'));
    }

    $wasClosed = correspondence_is_closed($corr);
    $closedBy = !empty($corr['closed_by']) ? (int) $corr['closed_by'] : (int) $user['id'];
    $keepClosedAt = $wasClosed && !empty($corr['closed_at']);

    $sql = "UPDATE `" . DmsDbTable::CORRESPONDENCE . "`
         SET status = 'closed',
             close_action = ?,
             close_decision_no = ?,
             close_decision_date = ?,
             close_result = ?,
             close_attach_decision = ?,
             close_attach_letter = ?,
             close_attach_proof = ?,
             close_attach_other = ?,
             close_attach_other_text = ?,
             close_notes = ?,
             closed_by = ?,
             closed_at = " . ($keepClosedAt ? 'closed_at' : 'NOW()') . "
         WHERE id = ?";
    db()->prepare($sql)->execute([
        $data['close_action'],
        $data['close_decision_no'] !== '' ? $data['close_decision_no'] : null,
        $data['close_decision_date'],
        $data['close_result'],
        $data['close_attach_decision'],
        $data['close_attach_letter'],
        $data['close_attach_proof'],
        $data['close_attach_other'],
        $data['close_attach_other_text'] !== '' ? $data['close_attach_other_text'] : null,
        $data['close_notes'] !== '' ? $data['close_notes'] : null,
        $closedBy,
        $id,
    ]);

    $detail = correspondence_close_result_label($data['close_result']);
    if ($data['close_decision_no'] !== '') {
        $detail .= ' — ' . $data['close_decision_no'];
    }
    if ($uploadInfo['uploaded'] > 0) {
        $detail .= ' — ' . str_replace('{n}', (string) $uploadInfo['uploaded'], __('close.files_uploaded_n'));
    }
    if ($adminReason !== '') {
        $detail .= ' — ' . __('close_req.admin_reason') . ': ' . $adminReason;
    }
    log_activity(
        (int) $user['id'],
        $id,
        $wasClosed ? 'close_update' : 'close',
        $detail
    );

    if (!$wasClosed) {
        if (function_exists('close_request_resolve_on_close')) {
            close_request_resolve_on_close($corr, $user);
        }
        dms_notify_correspondence_closed(array_merge($corr, $data), $user);
    }
}

/** إعادة الفتح لمن أقفلها أو منشئها أو مدير النظام */
function correspondence_user_can_reopen(array $user, array $corr): bool
{
    if (!correspondence_is_closed($corr) || correspondence_is_cancelled($corr)
        || !user_can('correspondence.close', $user) || !user_can_access_correspondence($corr, $user)) {
        return false;
    }
    $uid = (int) ($user['id'] ?? 0);
    return ($user['role'] ?? '') === 'admin'
        || ($uid > 0 && ($uid === (int) ($corr['closed_by'] ?? 0) || $uid === (int) ($corr['created_by'] ?? 0)));
}

/** الحالة بعد إعادة الفتح: الصادر يستعيد «تم التسليم» أو «تم الإرسال» إن سبق، وإلا «قيد الإجراء» */
function correspondence_reopen_status(array $corr): string
{
    if (($corr['type'] ?? '') !== 'outgoing') {
        return 'in_progress';
    }
    if (correspondence_has_receipt($corr)) {
        return 'delivered';
    }
    $last = function_exists('dms_corr_last_deliveries') ? dms_corr_last_deliveries((int) $corr['id']) : [];
    foreach (['delivery' => 'delivered', 'email' => 'sent', 'whatsapp' => 'sent'] as $channel => $status) {
        if (($last[$channel]['status'] ?? '') === 'sent') {
            return $status;
        }
    }
    return 'in_progress';
}

function reopen_correspondence(int $id, string $reason, array $user): string
{
    ensure_correspondence_closure_schema();
    $corr = get_correspondence($id);
    if (!$corr || !user_can_access_correspondence($corr, $user)) {
        throw new RuntimeException(__('corr.not_found'));
    }
    if (!correspondence_is_closed($corr)) {
        throw new RuntimeException(__('reopen.not_closed'));
    }
    if (!correspondence_user_can_reopen($user, $corr)) {
        throw new RuntimeException(__('reopen.forbidden'));
    }
    $reason = trim($reason);
    if (mb_strlen($reason) < 5) {
        throw new InvalidArgumentException(__('reopen.reason_required'));
    }
    $reason = mb_substr($reason, 0, 500);
    $newStatus = correspondence_reopen_status($corr);
    $stmt = db()->prepare(
        "UPDATE `" . DmsDbTable::CORRESPONDENCE . "`
         SET status = ?, closed_at = NULL, closed_by = NULL
         WHERE id = ? AND status = 'closed'"
    );
    $stmt->execute([$newStatus, $id]);
    if ($stmt->rowCount() === 0) {
        throw new RuntimeException(__('reopen.not_closed'));
    }
    log_activity((int) $user['id'], $id, 'reopen', status_label($newStatus) . ' — ' . $reason);
    dms_notify_correspondence_reopened($corr, $user, $reason);
    return $newStatus;
}

/** إشعار المنشئ ومن أقفلها (عدا من أعاد الفتح) */
function dms_notify_correspondence_reopened(array $corr, array $actor, string $reason): void
{
    $corrId = (int) ($corr['id'] ?? 0);
    $actorId = (int) ($actor['id'] ?? 0);
    $ids = array_values(array_unique(array_filter(
        [(int) ($corr['created_by'] ?? 0), (int) ($corr['closed_by'] ?? 0)],
        static fn(int $uid): bool => $uid > 0 && $uid !== $actorId
    )));
    if ($corrId <= 0 || $ids === [] || !function_exists('dms_notify_users')) {
        return;
    }
    $ref = format_ref_number($corr['ref_number'] ?? '');
    $subject = trim((string) ($corr['subject'] ?? ''));
    $actorName = trim(user_actor_name($actor));
    dms_notify_users($ids, [
        'type' => 'corr_reopened',
        'title' => __('notify.reopened_title'),
        'body' => implode(' — ', array_filter([$ref, $subject, $actorName, $reason])),
        'link' => correspondence_folder_for_type((string) ($corr['type'] ?? 'incoming')) . '/view.php?id=' . $corrId,
        'correspondence_id' => $corrId,
        'department_id' => (int) ($corr['department_id'] ?? 0),
        'dedupe_key' => 'reopened:' . $corrId . ':' . date('YmdHis'),
        'email' => true,
        'email_intro' => __('notify.reopened_title'),
        'email_rows' => [
            ['label' => __('notify.closed_row_ref'), 'value' => $ref, 'ltr' => true],
            ['label' => __('notify.closed_row_subject'), 'value' => $subject],
            ['label' => __('notify.reopened_row_by'), 'value' => $actorName],
            ['label' => __('notify.reopened_row_reason'), 'value' => $reason],
        ],
    ]);
}

/** إشعار منشئ المعاملة بإقفالها من غيره ليتخذ اللازم (حفظ بالأرشيف ونحوه) */
function dms_notify_correspondence_closed(array $corr, array $actor): void
{
    $corrId = (int) ($corr['id'] ?? 0);
    $creatorId = (int) ($corr['created_by'] ?? 0);
    if ($corrId <= 0 || $creatorId <= 0 || $creatorId === (int) ($actor['id'] ?? 0) || !function_exists('dms_notify_users')) {
        return;
    }
    $ref = format_ref_number($corr['ref_number'] ?? '');
    $subject = trim((string) ($corr['subject'] ?? ''));
    $result = correspondence_close_result_label((string) ($corr['close_result'] ?? ''));
    $actorName = trim(user_actor_name($actor));
    $link = correspondence_folder_for_type((string) ($corr['type'] ?? 'incoming')) . '/view.php?id=' . $corrId;
    $rows = [
        ['label' => __('notify.closed_row_ref'), 'value' => $ref, 'ltr' => true],
        ['label' => __('notify.closed_row_subject'), 'value' => $subject],
        ['label' => __('close.result_section'), 'value' => $result],
        ['label' => __('close.action_section'), 'value' => mb_substr(trim((string) ($corr['close_action'] ?? '')), 0, 500)],
        ['label' => __('notify.closed_row_by'), 'value' => $actorName],
        ['label' => __('notify.closed_row_next'), 'value' => __('notify.closed_next')],
    ];
    dms_notify_users([$creatorId], [
        'type' => 'corr_closed',
        'title' => __('notify.closed_title'),
        'body' => implode(' — ', array_filter([$ref, $subject, $result, $actorName])),
        'link' => $link,
        'correspondence_id' => $corrId,
        'department_id' => (int) ($corr['department_id'] ?? 0),
        'dedupe_key' => 'closed:' . $corrId . ':' . date('YmdHis'),
        'email' => true,
        'email_intro' => __('notify.closed_title'),
        'email_rows' => $rows,
    ]);
}

function correspondence_folder_for_type(string $type): string
{
    return function_exists('correspondence_type_folder')
        ? correspondence_type_folder($type)
        : ($type === 'outgoing' ? 'outgoing' : ($type === 'internal' ? 'internal' : 'incoming'));
}
