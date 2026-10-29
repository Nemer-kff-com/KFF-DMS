<?php
/**
 * منطق مشترك لمعاملات الوارد والصادر
 */
require_once __DIR__ . '/couriers.php';

function correspondence_open_statuses(): array
{
    return ['new', 'in_progress', 'referred', 'awaiting_reply', 'sent'];
}

function correspondence_done_statuses(): array
{
    return ['replied', 'delivered', 'closed'];
}

/**
 * @param string $type incoming|outgoing|all
 * @return array{where:list<string>,params:list<mixed>}
 */
function correspondence_list_where(string $type, array $filters = [], ?array $user = null): array
{
    $user = $user ?? current_user();
    $where = [];
    $params = [];

    if ($type === 'incoming' || $type === 'outgoing' || $type === 'internal') {
        $where[] = 'c.type = ?';
        $params[] = $type;
    }

    $action = trim((string) ($filters['action'] ?? ''));
    $prep = trim((string) ($filters['prep'] ?? ''));
    $skipDeptScope = ($action === 'issue_approval'
            && function_exists('outgoing_user_may_see_approval_queue')
            && outgoing_user_may_see_approval_queue($user))
        || ($action === 'issue_review'
            && function_exists('outgoing_user_may_see_review_queue')
            && outgoing_user_may_see_review_queue($user))
        || ($prep === 'mine')
        || ($prep === 'signer'
            && function_exists('outgoing_user_may_see_prep_signer_queue')
            && outgoing_user_may_see_prep_signer_queue($user));

    if (!$skipDeptScope) {
        [$scopeSql, $scopeParams] = department_scope_sql($user, 'c');
        if ($scopeSql !== '') {
            $where[] = $scopeSql;
            foreach ($scopeParams as $p) {
                $params[] = $p;
            }
        }
    }

    if (!empty($filters['q'])) {
        if (function_exists('ensure_correspondence_party_snapshot_schema')) {
            ensure_correspondence_party_snapshot_schema();
        }
        $where[] = '(c.ref_number LIKE ? OR c.subject LIKE ? OR c.party_name LIKE ? OR c.external_ref LIKE ?
            OR c.snap_person_name LIKE ? OR c.snap_org_name LIKE ?)';
        $q = '%' . $filters['q'] . '%';
        $params[] = $q;
        $params[] = $q;
        $params[] = $q;
        $params[] = $q;
        $params[] = $q;
        $params[] = $q;
    }

    $statusGroup = trim((string) ($filters['status_group'] ?? ''));
    if (!empty($filters['status'])) {
        $where[] = 'c.status = ?';
        $params[] = $filters['status'];
    } elseif ($statusGroup === 'open') {
        $statuses = correspondence_open_statuses();
        $where[] = 'c.status IN (' . implode(',', array_fill(0, count($statuses), '?')) . ')';
        foreach ($statuses as $st) {
            $params[] = $st;
        }
    } elseif ($statusGroup === 'done') {
        $statuses = correspondence_done_statuses();
        $where[] = 'c.status IN (' . implode(',', array_fill(0, count($statuses), '?')) . ')';
        foreach ($statuses as $st) {
            $params[] = $st;
        }
    }

    $filters = correspondence_normalize_date_filters($filters);
    if (!empty($filters['from'])) {
        $where[] = 'c.corr_date >= ?';
        $params[] = $filters['from'];
    }
    if (!empty($filters['to'])) {
        $where[] = 'c.corr_date <= ?';
        $params[] = $filters['to'];
    }
    if (!empty($filters['department_id'])) {
        $filterDept = (int) $filters['department_id'];
        if (
            $filterDept > 0
            && (
                (function_exists('can_view_all_departments') && can_view_all_departments($user))
                || (function_exists('user_can_access_department') && user_can_access_department($filterDept, $user))
            )
        ) {
            // الداخلي: القسم المستلم (موجهة إلى)
            if (($type ?? '') === 'internal'
                && function_exists('dms_db_column_exists')
                && dms_db_column_exists('tblcorrespondence', 'internal_to_department_id')
            ) {
                $where[] = 'IFNULL(c.internal_to_department_id, c.department_id) = ?';
            } else {
                $where[] = 'c.department_id = ?';
            }
            $params[] = $filterDept;
        }
    }
    if (!empty($filters['issuing_department_id'])) {
        $filterIssuing = (int) $filters['issuing_department_id'];
        if (
            $filterIssuing > 0
            && (
                (function_exists('can_view_all_departments') && can_view_all_departments($user))
                || (function_exists('user_can_access_department') && user_can_access_department($filterIssuing, $user))
            )
        ) {
            // الداخلي/الصادر: جهة التوجيه (موجه من)
            $where[] = 'IFNULL(c.issuing_department_id, c.department_id) = ?';
            $params[] = $filterIssuing;
        }
    }
    if (!empty($filters['entity_id'])) {
        $filterEntity = (int) $filters['entity_id'];
        if ($filterEntity > 0) {
            $where[] = 'c.entity_id = ?';
            $params[] = $filterEntity;
        }
    }
    if (!empty($filters['category_id'])) {
        $where[] = 'c.category_id = ?';
        $params[] = (int) $filters['category_id'];
    }
    if (!empty($filters['confidentiality'])) {
        $where[] = 'c.confidentiality = ?';
        $params[] = $filters['confidentiality'];
    }
    if (!empty($filters['receipt'])) {
        $where[] = "c.type = 'outgoing'";
        if (function_exists('ensure_corr_electronic_schema')) {
            ensure_corr_electronic_schema();
        }
        if ($filters['receipt'] === 'missing') {
            $where[] = "(c.receipt_name IS NULL OR c.receipt_name = '')";
            $where[] = "c.status <> 'delivered'";
            $where[] = "c.status <> 'cancelled'";
            $where[] = 'IFNULL(c.intend_electronic, 0) = 0';
        } elseif ($filters['receipt'] === 'recorded') {
            $where[] = "(c.receipt_name IS NOT NULL AND c.receipt_name <> '')";
        } elseif ($filters['receipt'] === 'overdue_missing') {
            $days = outgoing_receipt_alert_days();
            $where[] = "(c.receipt_name IS NULL OR c.receipt_name = '')";
            $where[] = "c.status <> 'cancelled'";
            $where[] = "c.status <> 'delivered'";
            $where[] = "c.status <> 'closed'";
            $where[] = 'IFNULL(c.intend_electronic, 0) = 0';
            $where[] = 'c.corr_date <= DATE_SUB(CURDATE(), INTERVAL ? DAY)';
            $params[] = $days;
        }
    }

    $channel = trim((string) ($filters['channel'] ?? ''));
    if ($channel !== '' && ($type === 'outgoing' || $type === '')) {
        if (function_exists('ensure_corr_electronic_schema')) {
            ensure_corr_electronic_schema();
        }
        $where[] = "c.type = 'outgoing'";
        if ($channel === 'electronic') {
            $where[] = 'IFNULL(c.intend_electronic, 0) = 1';
        } elseif ($channel === 'paper') {
            $where[] = 'IFNULL(c.intend_electronic, 0) = 0';
        }
    }

    $issueAppr = trim((string) ($filters['issue_approval'] ?? ''));
    if ($issueAppr !== '' && ($type === 'outgoing' || $type === '') && function_exists('outgoing_approval_badge')) {
        if (function_exists('ensure_outgoing_approval_schema')) {
            ensure_outgoing_approval_schema();
        }
        $where[] = "c.type = 'outgoing'";
        if ($issueAppr === 'pending') {
            $where[] = "c.issue_approval_status IN ('pending_review','pending_copy','pending_sg','pending_deputy','pending_alternate')";
        } elseif ($issueAppr === 'pending_review' || $issueAppr === 'returned') {
            $where[] = 'c.issue_approval_status = ?';
            $params[] = $issueAppr;
        } elseif ($issueAppr === 'approved') {
            $where[] = "c.issue_approval_status = 'approved'";
        } elseif ($issueAppr === 'rejected') {
            $where[] = "c.issue_approval_status = 'rejected'";
        } elseif ($issueAppr === 'none') {
            $where[] = "(c.issue_approval_status IS NULL OR c.issue_approval_status = '')";
        } elseif (in_array($issueAppr, ['pending_sg', 'pending_deputy', 'pending_alternate'], true)) {
            $where[] = 'c.issue_approval_status = ?';
            $params[] = $issueAppr;
        }
    }

    if (!empty($filters['overdue'])) {
        $where[] = 'c.reply_due IS NOT NULL';
        $where[] = 'c.reply_due < CURDATE()';
        $where[] = 'c.status NOT IN (' . correspondence_settled_sql_in() . ')';
    }

    if ($action !== '' && function_exists('correspondence_action_sql') && in_array($action, correspondence_action_kinds($user), true)) {
        foreach (correspondence_action_sql($action, $user) as $part) {
            $where[] = $part;
        }
    }

    if (!empty($filters['exclude_id'])) {
        $where[] = 'c.id <> ?';
        $params[] = (int) $filters['exclude_id'];
    }

    if ($type === 'outgoing' && function_exists('outgoing_prep_active_sql')) {
        if ($prep === 'mine') {
            $where[] = outgoing_prep_active_sql('c');
            $where[] = 'c.created_by = ?';
            $params[] = (int) ($user['id'] ?? 0);
        } elseif ($prep === 'signer') {
            $where[] = "c.outgoing_prep_status = 'pending_signer'";
            if (($user['role'] ?? '') !== 'admin') {
                $officeIds = function_exists('outgoing_prep_user_signer_office_ids')
                    ? outgoing_prep_user_signer_office_ids($user)
                    : [];
                if ($officeIds === []) {
                    $where[] = '1=0';
                } else {
                    [$inSql, $inParams] = dms_sql_in('IFNULL(c.issuing_department_id, c.department_id)', $officeIds);
                    $where[] = $inSql;
                    foreach ($inParams as $p) {
                        $params[] = $p;
                    }
                }
            }
        } elseif ($prep !== 'all') {
            $where[] = outgoing_prep_exclude_sql('c');
        }
    }

    if (!$where) {
        $where[] = '1=1';
    }

    return ['where' => $where, 'params' => $params];
}

function correspondence_list_count(string $type, array $filters = [], ?array $user = null): int
{
    $built = correspondence_list_where($type, $filters, $user);
    $sql = 'SELECT COUNT(*) FROM `tblcorrespondence` c WHERE ' . implode(' AND ', $built['where']);
    $stmt = db()->prepare($sql);
    $stmt->execute($built['params']);

    return (int) $stmt->fetchColumn();
}

/**
 * @param string $type incoming|outgoing|all
 */
function correspondence_list(string $type, array $filters = [], ?array $user = null, ?int $limit = null, ?int $offset = null): array
{
    $built = correspondence_list_where($type, $filters, $user);

    $sql = "SELECT c.*, d.name AS department_name, d.name_en AS department_name_en,
                   d.directed_from AS department_directed_from,
                   d.title_prefix AS department_title_prefix,
                   d.job_title AS department_job_title,
                   cat.name AS category_name,
                   idpt.name AS issuing_department_name, idpt.name_en AS issuing_department_name_en,
                   idpt.directed_from AS issuing_directed_from,
                   idpt.title_prefix AS issuing_title_prefix,
                   idpt.job_title AS issuing_job_title,
                   l.ref_number AS linked_ref, l.type AS linked_type,
                   e.email AS entity_email,
                   e.name_ar AS entity_name,
                   e.name_en AS entity_name_en,
                   e.directed_to AS entity_directed_to,
                   e.title_prefix AS entity_title_prefix,
                   e.job_title AS entity_job_title,
                   e.honorific AS entity_honorific,
                   " . (function_exists('user_name_sql') ? user_name_sql('iadu', 'issue_approval_decided_by_name') . ',' : '') . "
                   (SELECT COUNT(*) FROM `tbldocuments` doc WHERE doc.correspondence_id = c.id) AS documents_count,
                   (SELECT o.ref_number FROM `tblcorrespondence` o
                    WHERE o.type = 'outgoing' AND o.status <> 'cancelled'
                      AND (o.outgoing_prep_status IS NULL OR o.outgoing_prep_status NOT IN ('draft','pending_signer','superseded'))
                      AND (o.id = c.linked_id OR o.linked_id = c.id)
                    ORDER BY o.id DESC LIMIT 1) AS reply_ref
            FROM `tblcorrespondence` c
            LEFT JOIN `tbldepartments` d ON d.id = c.department_id
            LEFT JOIN `tbldepartments` idpt ON idpt.id = IFNULL(c.issuing_department_id, c.department_id)
            LEFT JOIN `tblcategories` cat ON cat.id = c.category_id
            LEFT JOIN `tblcorrespondence` l ON l.id = c.linked_id
            LEFT JOIN `tblentities` e ON e.id = c.entity_id
            LEFT JOIN `tblusers` iadu ON iadu.id = c.issue_approval_decided_by
            WHERE " . implode(' AND ', $built['where']) . "
            ORDER BY c.id DESC";

    if ($limit !== null) {
        $sql .= ' LIMIT ' . max(1, (int) $limit) . ' OFFSET ' . max(0, (int) ($offset ?? 0));
    }

    $stmt = db()->prepare($sql);
    $stmt->execute($built['params']);

    return array_map('correspondence_apply_ref_display', $stmt->fetchAll());
}

/**
 * @return array{rows:list<array<string,mixed>>,pagination:array<string,mixed>,total:int}
 */
function correspondence_list_page(string $type, array $filters = [], ?array $user = null, int $page = 1, ?int $perPage = null): array
{
    $perPage = $perPage ?? dms_list_per_page();
    $total = correspondence_list_count($type, $filters, $user);
    $pagination = dms_pagination_meta($total, $page, $perPage);
    $rows = correspondence_list($type, $filters, $user, $pagination['per_page'], $pagination['offset']);

    return [
        'rows' => $rows,
        'pagination' => $pagination,
        'total' => $total,
    ];
}

function save_correspondence(string $type, array $data, ?array $file, array $user, ?int $id = null): int
{
    ensure_entities_schema();
    ensure_correspondence_party_snapshot_schema();
    if (function_exists('ensure_correspondence_issuing_schema')) {
        ensure_correspondence_issuing_schema();
    }
    if (function_exists('ensure_corr_rsvp_schema')) {
        ensure_corr_rsvp_schema();
    }
    if (function_exists('ensure_corr_electronic_schema')) {
        ensure_corr_electronic_schema();
    }
    ensure_corr_external_date_schema();
    if (function_exists('ensure_corr_letter_schema')) {
        ensure_corr_letter_schema();
    }
    if (function_exists('ensure_outgoing_prep_schema')) {
        ensure_outgoing_prep_schema();
    }
    if (function_exists('ensure_outgoing_courier_schema')) {
        ensure_outgoing_courier_schema();
    }
    $subject = trim($data['subject'] ?? '');
    $entityId = (int) ($data['entity_id'] ?? 0) ?: null;
    $party = trim($data['party_name'] ?? '');
    $existing = $id ? get_correspondence($id) : null;
    if (!user_can($type . ($id ? '.edit' : '.create'), $user)) {
        throw new RuntimeException(__('auth.forbidden'));
    }
    if ($existing && correspondence_is_cancelled($existing)) {
        throw new RuntimeException(__('cancel.locked'));
    }
    if ($existing && function_exists('corr_assert_can_edit_in_cycle')) {
        corr_assert_can_edit_in_cycle($user, $existing);
    }
    if ($existing && function_exists('incoming_assert_can_edit_registration')) {
        incoming_assert_can_edit_registration($user, $existing);
    }
    $ccIds = [];
    if (!$id && $type === 'incoming' && function_exists('corr_cc_ids_from_request')) {
        $ccIds = corr_cc_ids_from_request($data, (int) $user['id']);
        corr_cc_assert_allowed('incoming', $ccIds);
    }
    $entity = null;
    if ($entityId) {
        $entity = get_entity($entityId);
        $preservingHistoricalEntity = $existing
            && (int) ($existing['entity_id'] ?? 0) === $entityId;
        if (!$entity || (!user_can_select_entity($entity, $user) && !$preservingHistoricalEntity)) {
            throw new RuntimeException(__('entities.not_available'));
        }
        if ($preservingHistoricalEntity) {
            $party = trim((string) ($existing['party_name'] ?? $party));
            if ($party === '') {
                $party = $type === 'outgoing'
                    ? entity_recipient_name($entity)
                    : (string) $entity['name_ar'];
            }
        } else {
            // الصادر يُسجَّل باسم الشخص المستلم، والوارد باسم جهة العمل المرسِلة
            if ($type === 'outgoing') {
                $party = entity_recipient_name($entity);
            } else {
                $party = trim((string) ($entity['name_ar'] ?? ''));
                if ($party === '') {
                    $party = trim((string) ($entity['name_en'] ?? ''));
                }
                if ($party === '') {
                    $party = entity_recipient_name($entity);
                }
            }
            if ($party === '') {
                $party = trim((string) ($entity['name_ar'] ?? $entity['name_en'] ?? ''));
            }
        }
    }
    $externalRef = trim($data['external_ref'] ?? '') ?: null;
    $externalDate = corr_external_date_value($data['external_date'] ?? '');
    $corrDate = $data['corr_date'] ?? date('Y-m-d');
    $replyDue = trim($data['reply_due'] ?? '') ?: null;
    $status = $data['status'] ?? 'new';
    if ($type === 'incoming' && !$id) {
        $status = 'new';
    }
    if ($type === 'outgoing' && (!$id || !is_array($existing) || !function_exists('outgoing_can_issue') || !outgoing_can_issue($existing))) {
        $status = 'new';
    }
    $priority = $data['priority'] ?? 'normal';
    $confidentiality = $data['confidentiality'] ?? 'normal';
    $categoryId = (int) ($data['category_id'] ?? 0) ?: null;
    $notes = trim($data['notes'] ?? '') ?: null;
    $rsvpRequired = ($type === 'outgoing' && !empty($data['rsvp_required'])) ? 1 : 0;
    $intendElectronic = ($type === 'outgoing' && !empty($data['intend_electronic'])) ? 1 : 0;
    $postedCourierId = $type === 'outgoing' ? (int) ($data['courier_id'] ?? 0) : 0;
    $courierName = $type === 'outgoing' ? (trim((string) ($data['courier_name'] ?? '')) ?: null) : null;
    $courierPhone = $type === 'outgoing' ? (trim((string) ($data['courier_phone'] ?? '')) ?: null) : null;
    if ($intendElectronic === 1) {
        $courierName = null;
        $courierPhone = null;
        $postedCourierId = 0;
    }
    if ($type === 'outgoing' && function_exists('correspondence_resolve_courier_snapshot')) {
        $snapCourier = correspondence_resolve_courier_snapshot(
            $id ? $existing : null,
            $postedCourierId,
            $courierName,
            $courierPhone
        );
        $postedCourierId = (int) ($snapCourier['id'] ?? 0);
        $courierName = $snapCourier['name'];
        $courierPhone = $snapCourier['phone'];
    }
    $courierId = $postedCourierId > 0 ? $postedCourierId : null;
    $letterBody = null;
    $letterBodyEn = null;
    if ($type === 'outgoing') {
        $letterBody = trim((string) ($data['letter_body'] ?? '')) ?: null;
        $letterBodyEn = trim((string) ($data['letter_body_en'] ?? '')) ?: null;
    }
    $deptId = (int) ($data['department_id'] ?? 0) ?: null;
    $linkedId = (int) ($data['linked_id'] ?? 0) ?: null;
    if ($type === 'outgoing' && $linkedId && function_exists('incoming_approval_is_held')) {
        $linkedCorr = get_correspondence($linkedId);
        if ($linkedCorr && ($linkedCorr['type'] ?? '') === 'incoming' && incoming_approval_is_held($linkedCorr)
            && (int) ($existing['linked_id'] ?? 0) !== $linkedId) {
            throw new RuntimeException(__('in_issue_appr.reply_blocked'));
        }
    }

    $issuingDeptId = null;
    if ($type === 'outgoing') {
        if ($id && !can_choose_correspondence_department($user)
            && (!$existing || ($existing['type'] ?? '') !== $type || !user_can_access_correspondence($existing, $user))) {
            throw new RuntimeException(__('auth.forbidden'));
        }
        [$deptId, $issuingDeptId] = outgoing_resolve_sign_from(
            $user,
            (int) ($data['issuing_department_id'] ?? 0),
            $id ? $existing : null
        );
    } elseif (!can_choose_correspondence_department($user)) {
        $deptId = resolve_assigned_department_id($user, $deptId, false);
        if (!$deptId) {
            throw new RuntimeException(__('corr.department_account_required'));
        }
        if ($id) {
            if (!$existing || ($existing['type'] ?? '') !== $type || !user_can_access_correspondence($existing, $user)) {
                throw new RuntimeException(__('auth.forbidden'));
            }
            $existingDept = (int) ($existing['department_id'] ?? 0);
            if ($existingDept > 0 && user_can_access_department($existingDept, $user)) {
                $posted = (int) ($data['department_id'] ?? 0);
                $deptId = ($posted > 0 && user_can_access_department($posted, $user)) ? $posted : $existingDept;
            }
        }
    }
    if ($type === 'incoming' && !$deptId) {
        throw new RuntimeException(__('corr.dept_required'));
    }

    $allowedStatus = correspondence_assignable_status_codes();
    if ($id && $existing) {
        $existingStatus = (string) ($existing['status'] ?? '');
        if (in_array($existingStatus, ['closed', 'cancelled'], true)
            && $status === $existingStatus
            && !in_array($status, $allowedStatus, true)) {
            $allowedStatus[] = $existingStatus;
        }
        if (function_exists('incoming_approval_is_held') && incoming_approval_is_held($existing)) {
            $status = $existingStatus;
            if (!in_array($status, $allowedStatus, true)) {
                $allowedStatus[] = $status;
            }
        }
    }
    $allowedPriority = array_values(array_filter(array_map(
        static fn(array $row): string => trim((string) ($row['code'] ?? '')),
        function_exists('priority_levels_list') ? priority_levels_list(false) : []
    ))) ?: ['normal', 'urgent'];
    if ($id && is_array($existing ?? null)) {
        $existingPriority = trim((string) ($existing['priority'] ?? ''));
        if ($existingPriority !== '' && $priority === $existingPriority && !in_array($priority, $allowedPriority, true)) {
            $allowedPriority[] = $existingPriority;
        }
    }
    $allowedConf = array_values(array_filter(array_map(
        static fn(array $row): string => trim((string) ($row['code'] ?? '')),
        function_exists('confidentiality_levels_list') ? confidentiality_levels_list(false) : []
    ))) ?: ['normal', 'confidential'];
    if ($id && is_array($existing ?? null)) {
        $existingConf = trim((string) ($existing['confidentiality'] ?? ''));
        if ($existingConf !== '' && $confidentiality === $existingConf && !in_array($confidentiality, $allowedConf, true)) {
            $allowedConf[] = $existingConf;
        }
    }
    if ($subject === '' || $party === '' || !in_array($status, $allowedStatus, true)
        || !in_array($priority, $allowedPriority, true) || !in_array($confidentiality, $allowedConf, true)) {
        throw new InvalidArgumentException('بيانات المعاملة غير مكتملة.');
    }

    if ($type === 'incoming' && !$id) {
        $incomingUploads = function_exists('dms_normalize_upload_files')
            ? dms_normalize_upload_files(is_array($file) ? $file : null)
            : [];
        // المرفق اختياري عند التسجيل (مثلاً ظرف لم يُفتح بعد) — يُرفع لاحقاً من صفحة العرض
        if ($incomingUploads && function_exists('dms_validate_document_upload')) {
            foreach ($incomingUploads as $uploadFile) {
                dms_validate_document_upload($uploadFile);
            }
        }
    }

    $snap = correspondence_resolve_save_snapshot(
        $existing,
        $entity,
        $type,
        $party,
        $entityId,
        $deptId,
        $issuingDeptId
    );
    $snapVals = correspondence_snapshot_bind_values($snap);
    $asOutgoingDraft = false;

    if ($id) {
        $oldStatus = $existing['status'] ?? null;
        $stmt = db()->prepare(
            "UPDATE `tblcorrespondence` SET subject=?, party_name=?, entity_id=?,
             snap_title_prefix=?, snap_person_name=?, snap_job_title=?, snap_honorific=?, snap_org_name=?,
             snap_from_prefix=?, snap_from_person=?, snap_from_job=?, snap_from_org=?, snap_from_signature=?,
             external_ref=?, external_date=?, corr_date=?, reply_due=?, status=?, priority=?, confidentiality=?, category_id=?,
             notes=?, letter_body=?, letter_body_en=?, department_id=?, issuing_department_id=?, linked_id=?, rsvp_required=?, intend_electronic=?, courier_name=?, courier_phone=?, courier_id=?
             WHERE id=? AND type=?"
        );
        $stmt->execute(array_merge(
            [$subject, $party, $entityId],
            $snapVals,
            [
                $externalRef, $externalDate, $corrDate, $replyDue, $status, $priority,
                $confidentiality, $categoryId, $notes, $letterBody, $letterBodyEn, $deptId, $issuingDeptId, $linkedId, $rsvpRequired,
                $intendElectronic, $courierName, $courierPhone, $courierId, $id, $type,
            ]
        ));
        $corrId = $id;
        log_activity((int)$user['id'], $corrId, 'update', 'تحديث معاملة ' . type_label($type));
        if ($oldStatus !== null && $oldStatus !== $status) {
            log_activity(
                (int) $user['id'],
                $corrId,
                'status',
                status_label((string) $oldStatus) . ' ← ' . status_label($status)
            );
        }
    } else {
        $asOutgoingDraft = $type === 'outgoing'
            && function_exists('outgoing_prep_enabled')
            && outgoing_prep_enabled()
            && function_exists('outgoing_prep_placeholder_ref');
        $ref = $asOutgoingDraft ? outgoing_prep_placeholder_ref() : next_ref_number($type, $deptId);
        $token = generate_qr_token();
        $prepStatus = $asOutgoingDraft ? 'draft' : null;
        $stmt = db()->prepare(
            "INSERT INTO `tblcorrespondence`
            (type, ref_number, subject, party_name, entity_id,
             snap_title_prefix, snap_person_name, snap_job_title, snap_honorific, snap_org_name,
             snap_from_prefix, snap_from_person, snap_from_job, snap_from_org, snap_from_signature,
             external_ref, external_date, corr_date, reply_due,
             status, priority, confidentiality, category_id, notes, letter_body, letter_body_en, qr_token, linked_id, department_id, issuing_department_id, created_by, rsvp_required, intend_electronic, courier_name, courier_phone, courier_id, outgoing_prep_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute(array_merge(
            [$type, $ref, $subject, $party, $entityId],
            $snapVals,
            [
                $externalRef, $externalDate, $corrDate, $replyDue,
                $status, $priority, $confidentiality, $categoryId, $notes, $letterBody, $letterBodyEn,
                $token, $linkedId, $deptId, $issuingDeptId, (int)$user['id'], $rsvpRequired, $intendElectronic,
                $courierName, $courierPhone, $courierId, $prepStatus,
            ]
        ));
        $corrId = (int) db()->lastInsertId();
        if ($asOutgoingDraft && function_exists('outgoing_prep_bind_id_ref')) {
            try {
                outgoing_prep_bind_id_ref($corrId);
                $ref = 'OD-' . $corrId;
            } catch (Throwable $refEx) {
                error_log('DMS outgoing draft ref: ' . $refEx->getMessage());
            }
        }
        log_activity(
            (int) $user['id'],
            $corrId,
            'create',
            $asOutgoingDraft
                ? ('إنشاء مسودة صادر')
                : ('إنشاء ' . type_label($type) . ' رقم ' . $ref)
        );

        if ($linkedId) {
            db()->prepare("UPDATE `tblcorrespondence` SET linked_id = ? WHERE id = ? AND linked_id IS NULL")
                ->execute([$corrId, $linkedId]);
            if ($type === 'outgoing' && !$asOutgoingDraft) {
                db()->prepare("UPDATE `tblcorrespondence` SET status = 'replied' WHERE id = ? AND type = 'incoming' AND status NOT IN ('closed', 'cancelled')")
                    ->execute([$linkedId]);
            }
        }

        if ($ccIds !== []) {
            corr_cc_set_pending($corrId, $ccIds, (int) $user['id']);
        }
        $heldForApproval = false;
        if ($type === 'incoming' && function_exists('incoming_park_at_desk')) {
            incoming_park_at_desk($corrId);
            $heldForApproval = true;
        }
        if ($ccIds !== [] && !$heldForApproval) {
            corr_cc_cancel($corrId, $user);
        }
        if ($type === 'incoming' && $deptId && !$heldForApproval && function_exists('dms_notify_incoming_assigned')) {
            dms_notify_incoming_assigned($corrId, [
                'id' => $corrId,
                'type' => 'incoming',
                'ref_number' => $ref,
                'subject' => $subject,
                'party_name' => $party,
            ], $deptId, $user, 'new');
        }
    }

    if ($id && $type === 'incoming' && $existing) {
        $oldDept = (int) ($existing['department_id'] ?? 0);
        $stillHeld = function_exists('incoming_approval_is_held') && incoming_approval_is_held($existing);
        if ($deptId && $oldDept !== $deptId && !$stillHeld && function_exists('dms_notify_incoming_assigned')) {
            dms_notify_incoming_assigned($corrId, array_merge($existing, [
                'id' => $corrId,
                'department_id' => $deptId,
            ]), $deptId, $user, 'dept_change');
        }
    }

    $uploadFiles = function_exists('dms_normalize_upload_files')
        ? dms_normalize_upload_files(is_array($file) ? $file : null)
        : [];
    if (
        $uploadFiles !== []
        && $id
        && is_array($existing ?? null)
        && function_exists('outgoing_draft_files_locked')
        && outgoing_draft_files_locked($existing)
        && (!function_exists('outgoing_approval_status_of') || outgoing_approval_status_of($existing) !== 'rejected')
    ) {
        throw new RuntimeException(__('out_prep.draft_file_locked'));
    }
    if ($uploadFiles !== []) {
        $uploadedAny = false;
        try {
            foreach ($uploadFiles as $uploadFile) {
                upload_document($uploadFile, $corrId, (int) $user['id']);
                $uploadedAny = true;
            }
            if ($uploadedAny) {
                log_activity((int) $user['id'], $corrId, 'upload', 'رفع مرفق');
            }
        } catch (Throwable $uploadEx) {
            // وارد جديد: المرفق إلزامي — احذف السجل إن فشل الحفظ بعد الإدراج
            if (!$id && $type === 'incoming' && $corrId > 0) {
                try {
                    db()->prepare('DELETE FROM `tbldocuments` WHERE correspondence_id = ?')->execute([$corrId]);
                    if (function_exists('save_correspondence_enclosures')) {
                        // إن وُجدت جداول مرافقة فارغة لا مشكلة
                    }
                    db()->prepare('DELETE FROM `tblcorrespondence` WHERE id = ? AND type = ?')->execute([$corrId, 'incoming']);
                } catch (Throwable $cleanupEx) {
                    // أعد الخطأ الأصلي للمستخدم
                }
                throw $uploadEx;
            }
            // الصادر: المرفق اختياري — أبقِ المعاملة واعرض تحذيراً بدل إخفاء نجاح الحفظ
            if ($type === 'outgoing' && $corrId > 0) {
                error_log('DMS outgoing attachment upload: ' . $uploadEx->getMessage());
                if (function_exists('flash')) {
                    flash('warning', $uploadEx->getMessage());
                }
            } else {
                throw $uploadEx;
            }
        }
    }

    save_correspondence_enclosures($corrId, correspondence_enclosures_from_request($data));

    if ($id && function_exists('outgoing_approval_after_edit')) {
        $skipPrepReset = $existing && function_exists('outgoing_prep_is_open') && outgoing_prep_is_open($existing);
        if (!$skipPrepReset) {
            try {
                outgoing_approval_after_edit($corrId, $user);
            } catch (Throwable $apprEx) {
                error_log('DMS approval reset after edit: ' . $apprEx->getMessage());
            }
        }
    }

    // صادر جديد ليس مسودة: يدخل المراجعة أو الاعتماد إن كان مفتاحهما مفعّلاً، وإلا يُحال للاتصالات الإدارية
    if (!$id && $type === 'outgoing' && empty($asOutgoingDraft)) {
        try {
            if (function_exists('outgoing_issue_flow_active') && outgoing_issue_flow_active() && function_exists('outgoing_approval_request')) {
                outgoing_approval_request($corrId, $user);
            } elseif (function_exists('outgoing_release_to_communications')) {
                outgoing_release_to_communications($corrId);
            }
        } catch (Throwable $apprEx) {
            error_log('DMS outgoing approval auto-request: ' . $apprEx->getMessage());
        }
    }

    if ($type === 'outgoing' && $courierName && function_exists('outgoing_courier_remember')) {
        outgoing_courier_remember($courierName, (string) ($courierPhone ?? ''));
    }

    return $corrId;
}

function linkable_correspondence(string $forType, ?int $excludeId = null, ?array $user = null): array
{
    $found = linkable_correspondence_search($forType, $user, [
        'exclude_id' => $excludeId,
        'limit' => 200,
    ]);

    return $found['rows'];
}

/**
 * بحث خفيف لنافذة الربط: بلا JOINs ولا عدّ للمرفقات، وحدّ للنتائج حتى تبقى سريعة مع كثرة السجلات.
 *
 * @return array{rows:list<array<string,mixed>>,truncated:bool}
 */
function linkable_correspondence_search(string $forType, ?array $user = null, array $options = []): array
{
    $user = $user ?? (function_exists('current_user') ? current_user() : null);
    $needType = $forType === 'outgoing' ? 'incoming' : 'outgoing';
    $q = mb_substr(trim((string) ($options['q'] ?? '')), 0, 100, 'UTF-8');
    $excludeId = max(0, (int) ($options['exclude_id'] ?? 0));
    $limit = max(1, min(50, (int) ($options['limit'] ?? 25)));
    $fetch = $limit + 1;
    ensure_corr_external_date_schema();

    $sql = 'SELECT c.id, c.ref_number, c.subject, c.corr_date, c.external_date, c.party_name, c.status,
                   c.snap_person_name, c.snap_org_name, c.snap_title_prefix, c.snap_honorific, c.type
            FROM `tblcorrespondence` c
            WHERE c.type = ?';
    $params = [$needType];

    [$scopeSql, $scopeParams] = department_scope_sql($user, 'c');
    if ($scopeSql !== '') {
        $sql .= ' AND ' . $scopeSql;
        foreach ($scopeParams as $p) {
            $params[] = $p;
        }
    }
    if ($excludeId > 0) {
        $sql .= ' AND c.id <> ?';
        $params[] = $excludeId;
    }
    if ($needType === 'outgoing') {
        $sql .= ' AND (c.outgoing_prep_status IS NULL OR c.outgoing_prep_status NOT IN (\'draft\',\'pending_signer\',\'superseded\'))';
    }
    /* لا يُعرض وارد محجوز (مكتب/مراجعة/اعتماد) كمرجع لرد صادر */
    if ($needType === 'incoming' && function_exists('incoming_department_hidden_statuses')) {
        $heldStatuses = incoming_department_hidden_statuses();
        if ($heldStatuses !== []) {
            $ph = implode(',', array_fill(0, count($heldStatuses), '?'));
            $sql .= " AND (c.issue_approval_status IS NULL OR c.issue_approval_status NOT IN ($ph))";
            foreach ($heldStatuses as $hs) {
                $params[] = $hs;
            }
        }
    }
    if ($q !== '') {
        $likeRaw = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q);
        // أرقام/مراجع: بادئة أسرع من %...%
        if (preg_match('/^[\d\/\-_.]+$/u', $q) === 1) {
            $prefix = $likeRaw . '%';
            $sql .= ' AND (c.ref_number LIKE ? OR c.external_ref LIKE ? OR c.ref_number LIKE ? OR c.subject LIKE ? OR c.party_name LIKE ?)';
            array_push($params, $prefix, $prefix, '%' . $likeRaw . '%', '%' . $likeRaw . '%', '%' . $likeRaw . '%');
        } else {
            $like = '%' . $likeRaw . '%';
            $sql .= ' AND (c.ref_number LIKE ? OR c.subject LIKE ? OR c.party_name LIKE ? OR c.external_ref LIKE ?
                OR c.snap_person_name LIKE ? OR c.snap_org_name LIKE ?)';
            array_push($params, $like, $like, $like, $like, $like, $like);
        }
    }

    $sql .= ' ORDER BY c.id DESC LIMIT ' . $fetch;
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    $truncated = count($rows) > $limit;
    if ($truncated) {
        $rows = array_slice($rows, 0, $limit);
    }

    return [
        'rows' => array_map('correspondence_apply_ref_display', $rows),
        'truncated' => $truncated,
    ];
}

/**
 * صف واحد لنافذة ربط وارد/صادر.
 *
 * @return array{id:int,ref:string,subject:string,party:string,date:string,status:string}
 */
/**
 * تاريخ الخطاب المختار للربط (Y-m-d): الوارد بتاريخ خطاب الجهة إن وُجد، والصادر بتاريخه.
 */
function linkable_correspondence_letter_date(array $row): string
{
    if (($row['type'] ?? '') === 'incoming') {
        $ext = corr_external_date_value($row['external_date'] ?? '');
        if ($ext !== null) {
            return $ext;
        }
    }
    return corr_external_date_value(substr((string) ($row['corr_date'] ?? ''), 0, 10)) ?? '';
}

function linkable_correspondence_payload(array $row): array
{
    $party = function_exists('correspondence_party_display_name')
        ? correspondence_party_display_name($row)
        : trim((string) ($row['party_name'] ?? ''));
    if ($party === '') {
        $party = trim((string) ($row['party_name'] ?? ''));
    }

    return [
        'id' => (int) ($row['id'] ?? 0),
        'ref' => format_ref_number($row['ref_number'] ?? ''),
        'subject' => (string) ($row['subject'] ?? ''),
        'party' => $party,
        'date' => function_exists('format_date') ? format_date($row['corr_date'] ?? null) : (string) ($row['corr_date'] ?? ''),
        'letter_date' => linkable_correspondence_letter_date($row),
        'status' => function_exists('status_label') ? status_label((string) ($row['status'] ?? '')) : (string) ($row['status'] ?? ''),
    ];
}

function update_correspondence_status(int $id, string $status, array $user): void
{
    $allowed = correspondence_assignable_status_codes();
    if (!in_array($status, $allowed, true)) {
        throw new InvalidArgumentException(__('corr.invalid_request'));
    }
    $corr = get_correspondence($id);
    if (!$corr || !user_can_access_correspondence($corr, $user)) {
        throw new RuntimeException(__('auth.forbidden'));
    }
    if (correspondence_is_cancelled($corr)) {
        throw new RuntimeException(__('cancel.locked'));
    }
    if (correspondence_is_closed($corr)) {
        throw new RuntimeException(__('reopen.use_reopen'));
    }
    if (($corr['type'] ?? '') === 'outgoing'
        && function_exists('outgoing_can_issue')
        && !outgoing_can_issue($corr)
    ) {
        $msg = function_exists('outgoing_approval_block_post_issue')
            ? outgoing_approval_block_post_issue($corr)
            : __('issue_appr.blocked_post_issue');
        throw new RuntimeException($msg !== '' ? $msg : __('issue_appr.blocked_post_issue'));
    }
    if (function_exists('corr_approval_is_held') && corr_approval_is_held($corr)) {
        throw new RuntimeException(corr_held_text($corr, 'status_blocked'));
    }
    if ($status === 'cancelled' || $status === 'closed') {
        throw new InvalidArgumentException(
            $status === 'closed' ? __('close.use_form') : __('corr.invalid_request')
        );
    }
    $old = (string) ($corr['status'] ?? '');
    if ($old === $status) {
        return;
    }
    db()->prepare('UPDATE `tblcorrespondence` SET status = ? WHERE id = ?')->execute([$status, $id]);
    log_activity(
        (int) $user['id'],
        $id,
        'status',
        status_label($old) . ' ← ' . status_label($status)
    );
}

function cancel_outgoing_correspondence(int $id, string $kind, string $reason, array $user): void
{
    if (!user_can('outgoing.cancel', $user)) {
        throw new RuntimeException(__('auth.forbidden'));
    }
    ensure_correspondence_cancel_schema();
    $kind = $kind === 'withdrawn' ? 'withdrawn' : 'void';
    $reason = trim($reason);
    if (mb_strlen($reason) < 5) {
        throw new InvalidArgumentException(__('cancel.reason_required'));
    }
    $reason = mb_substr($reason, 0, 500);
    $corr = get_correspondence($id);
    if (!$corr || ($corr['type'] ?? '') !== 'outgoing' || !user_can_access_correspondence($corr, $user)) {
        throw new RuntimeException(__('corr.not_found'));
    }
    if (correspondence_is_cancelled($corr)) {
        throw new RuntimeException(__('cancel.already'));
    }
    if (function_exists('corr_assert_can_edit_in_cycle')) {
        corr_assert_can_edit_in_cycle($user, $corr);
    }
    db()->prepare(
        "UPDATE `tblcorrespondence`
         SET status = 'cancelled', cancel_kind = ?, cancel_reason = ?, cancelled_at = NOW(), cancelled_by = ?
         WHERE id = ? AND type = 'outgoing'"
    )->execute([$kind, $reason, (int) $user['id'], $id]);
    log_activity(
        (int) $user['id'],
        $id,
        'cancel',
        correspondence_cancel_kind_label($kind) . ' — ' . $reason
    );
}

/**
 * إحصائيات لوحة التحكم مع عزل القسم
 * @return array{today_in:int,today_out:int,in_progress:int,completed:int,overdue:int,pending_ref:int,recent:list,overdue_rows:list}
 */
function correspondence_dashboard_data(?array $user = null, array $opts = []): array
{
    $user = $user ?? current_user();
    $pdo = db();
    [$scopeSql, $scopeParams] = department_scope_sql($user, 'c');
    $scopeAnd = $scopeSql !== '' ? ' AND ' . $scopeSql : '';
    $perPage = max(1, min(100, (int) ($opts['per_page'] ?? dms_list_per_page())));
    $actionInPage = max(1, (int) ($opts['action_in_page'] ?? 1));
    $actionOutPage = max(1, (int) ($opts['action_out_page'] ?? 1));
    $actionInternalPage = max(1, (int) ($opts['action_internal_page'] ?? 1));
    $actionApprovalPage = max(1, (int) ($opts['action_approval_page'] ?? 1));
    $recentInPage = max(1, (int) ($opts['recent_in_page'] ?? 1));
    $recentOutPage = max(1, (int) ($opts['recent_out_page'] ?? 1));
    $recentInternalPage = max(1, (int) ($opts['recent_internal_page'] ?? 1));
    $overdueInPage = max(1, (int) ($opts['overdue_in_page'] ?? 1));
    $overdueOutPage = max(1, (int) ($opts['overdue_out_page'] ?? 1));
    $overdueInternalPage = max(1, (int) ($opts['overdue_internal_page'] ?? 1));

    $openList = correspondence_open_statuses();
    $doneList = correspondence_done_statuses();
    $openPlaceholders = implode(',', array_fill(0, count($openList), '?'));
    $donePlaceholders = implode(',', array_fill(0, count($doneList), '?'));

    // إحصاءات اللوحة للوارد والصادر والداخلي.
    $statsStmt = $pdo->prepare(
        "SELECT
            COALESCE(SUM(c.type = 'incoming' AND c.corr_date = CURDATE()), 0) AS today_in,
            COALESCE(SUM(c.type = 'outgoing' AND c.corr_date = CURDATE()), 0) AS today_out,
            COALESCE(SUM(c.type = 'internal' AND c.corr_date = CURDATE()), 0) AS today_internal,
            COALESCE(SUM(c.type IN ('incoming','outgoing','internal') AND c.status IN ({$openPlaceholders})), 0) AS in_progress,
            COALESCE(SUM(c.type = 'incoming' AND c.status IN ({$openPlaceholders})), 0) AS in_progress_in,
            COALESCE(SUM(c.type = 'outgoing' AND c.status IN ({$openPlaceholders})), 0) AS in_progress_out,
            COALESCE(SUM(c.type = 'internal' AND c.status IN ({$openPlaceholders})), 0) AS in_progress_internal,
            COALESCE(SUM(c.type IN ('incoming','outgoing','internal') AND c.status IN ({$donePlaceholders})), 0) AS completed,
            COALESCE(SUM(c.type IN ('incoming','outgoing','internal') AND c.reply_due IS NOT NULL AND c.reply_due < CURDATE()
                         AND c.status NOT IN (" . correspondence_settled_sql_in() . ")), 0) AS overdue
         FROM `tblcorrespondence` c
         WHERE 1=1{$scopeAnd}"
    );
    $statsStmt->execute(array_merge(
        $openList,
        $openList,
        $openList,
        $openList,
        $doneList,
        $scopeParams
    ));
    $stats = $statsStmt->fetch() ?: [];

    $todayIn = (int) ($stats['today_in'] ?? 0);
    $todayOut = (int) ($stats['today_out'] ?? 0);
    $todayInternal = (int) ($stats['today_internal'] ?? 0);
    $inProgress = (int) ($stats['in_progress'] ?? 0);
    $inProgressIn = (int) ($stats['in_progress_in'] ?? 0);
    $inProgressOut = (int) ($stats['in_progress_out'] ?? 0);
    $inProgressInternal = (int) ($stats['in_progress_internal'] ?? 0);
    $completed = (int) ($stats['completed'] ?? 0);
    $overdue = (int) ($stats['overdue'] ?? 0);

    $refSql = "SELECT COUNT(*) FROM `tblreferrals` r WHERE r.status = 'pending'";
    $refParams = [];
    if (function_exists('user_needs_department_scope') && user_needs_department_scope($user)) {
        $ids = function_exists('user_department_ids') ? user_department_ids($user) : [];
        if (!$ids) {
            $refSql .= ' AND 1=0';
        } else {
            [$toIn, $toParams] = dms_sql_in('r.to_department_id', $ids);
            [$fromIn, $fromParams] = dms_sql_in('r.from_department_id', $ids);
            $refSql .= " AND ({$toIn} OR {$fromIn})";
            $refParams = array_merge($refParams, $toParams, $fromParams);
        }
    }
    $refStmt = $pdo->prepare($refSql);
    $refStmt->execute($refParams);
    $pendingRef = (int) $refStmt->fetchColumn();

    $typedList = static function (
        string $type,
        int $page,
        string $orderSql,
        ?string $extraWhere = null,
        array $extraParams = []
    ) use ($pdo, $scopeAnd, $scopeParams, $perPage): array {
        $where = " AND c.type = ?" . ($extraWhere ?? '');
        $params = array_merge($scopeParams, [$type], $extraParams);
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM `tblcorrespondence` c WHERE 1=1{$scopeAnd}{$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();
        $pagination = dms_pagination_meta($total, $page, $perPage);
        $sql = "SELECT c.*, d.name AS department_name, d.name_en AS department_name_en,
                       d.title_prefix AS department_title_prefix, d.directed_from AS department_directed_from
                FROM `tblcorrespondence` c
                LEFT JOIN `tbldepartments` d ON d.id = c.department_id
                WHERE 1=1{$scopeAnd}{$where}
                ORDER BY {$orderSql}
                LIMIT {$perPage} OFFSET {$pagination['offset']}";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return ['rows' => $stmt->fetchAll(), 'pagination' => $pagination, 'total' => $total];
    };

    $overdueWhere = " AND c.reply_due IS NOT NULL AND c.reply_due < CURDATE()
                      AND c.status NOT IN (" . correspondence_settled_sql_in() . ")";
    $outPrepWhere = function_exists('outgoing_prep_exclude_sql')
        ? ' AND ' . outgoing_prep_exclude_sql('c')
        : '';

    $recentIn = $typedList('incoming', $recentInPage, 'c.id DESC');
    $recentOut = $typedList('outgoing', $recentOutPage, 'c.id DESC', $outPrepWhere);
    $recentInternal = $typedList('internal', $recentInternalPage, 'c.id DESC');
    $overdueIn = $typedList('incoming', $overdueInPage, 'c.reply_due ASC', $overdueWhere);
    $overdueOut = $typedList('outgoing', $overdueOutPage, 'c.reply_due ASC', $overdueWhere . $outPrepWhere);
    $overdueInternal = $typedList('internal', $overdueInternalPage, 'c.reply_due ASC', $overdueWhere);

    $actionCounts = function_exists('correspondence_action_counts')
        ? correspondence_action_counts($user)
        : ['total' => 0];
    $actionInPack = function_exists('correspondence_action_items_page')
        ? correspondence_action_items_page($user, $actionInPage, $perPage, 'incoming')
        : ['items' => [], 'pagination' => dms_pagination_meta(0, $actionInPage, $perPage)];
    $actionOutPack = function_exists('correspondence_action_items_page')
        ? correspondence_action_items_page($user, $actionOutPage, $perPage, 'outgoing')
        : ['items' => [], 'pagination' => dms_pagination_meta(0, $actionOutPage, $perPage)];
    $actionInternalPack = function_exists('correspondence_action_items_page')
        ? correspondence_action_items_page($user, $actionInternalPage, $perPage, 'internal')
        : ['items' => [], 'pagination' => dms_pagination_meta(0, $actionInternalPage, $perPage)];

    $actionApprovalPack = ['items' => [], 'pagination' => dms_pagination_meta(0, $actionApprovalPage, $perPage)];
    if (
        function_exists('outgoing_user_may_see_approval_queue')
        && outgoing_user_may_see_approval_queue($user)
        && function_exists('correspondence_list_page')
    ) {
        $apprList = correspondence_list_page('all', ['action' => 'issue_approval'], $user, $actionApprovalPage, $perPage);
        $apprItems = [];
        foreach ($apprList['rows'] as $row) {
            $row['action_kind'] = 'issue_approval';
            $apprItems[] = $row;
        }
        $actionApprovalPack = [
            'items' => $apprItems,
            'pagination' => $apprList['pagination'],
        ];
    }

    $overdueInCount = $overdueIn['total'];
    $overdueOutCount = $overdueOut['total'];
    $overdueInternalCount = $overdueInternal['total'];

    return [
        'today_in' => $todayIn,
        'today_out' => $todayOut,
        'today_internal' => $todayInternal,
        'in_progress' => $inProgress,
        'in_progress_in' => $inProgressIn,
        'in_progress_out' => $inProgressOut,
        'in_progress_internal' => $inProgressInternal,
        'completed' => $completed,
        'overdue' => $overdue,
        'overdue_in' => $overdueInCount,
        'overdue_out' => $overdueOutCount,
        'overdue_internal' => $overdueInternalCount,
        'pending_ref' => $pendingRef,
        'recent_in' => $recentIn['rows'],
        'recent_in_pagination' => $recentIn['pagination'],
        'recent_out' => $recentOut['rows'],
        'recent_out_pagination' => $recentOut['pagination'],
        'recent_internal' => $recentInternal['rows'],
        'recent_internal_pagination' => $recentInternal['pagination'],
        'overdue_in_rows' => $overdueIn['rows'],
        'overdue_in_pagination' => $overdueIn['pagination'],
        'overdue_out_rows' => $overdueOut['rows'],
        'overdue_out_pagination' => $overdueOut['pagination'],
        'overdue_internal_rows' => $overdueInternal['rows'],
        'overdue_internal_pagination' => $overdueInternal['pagination'],
        'action_counts' => $actionCounts,
        'action_in_items' => $actionInPack['items'],
        'action_in_pagination' => $actionInPack['pagination'],
        'action_out_items' => $actionOutPack['items'],
        'action_out_pagination' => $actionOutPack['pagination'],
        'action_internal_items' => $actionInternalPack['items'],
        'action_internal_pagination' => $actionInternalPack['pagination'],
        'action_approval_items' => $actionApprovalPack['items'],
        'action_approval_pagination' => $actionApprovalPack['pagination'],
        'action_total' => (int) ($actionCounts['total'] ?? 0),
    ];
}

function add_comment(int $corrId, int $userId, string $body): void
{
    $body = trim($body);
    if ($body === '') {
        throw new InvalidArgumentException('نص التعليق مطلوب.');
    }
    db()->prepare(
        "INSERT INTO `tblcomments` (correspondence_id, user_id, body) VALUES (?, ?, ?)"
    )->execute([$corrId, $userId, $body]);
    log_activity($userId, $corrId, 'comment', mb_substr($body, 0, 120));
}

function save_outgoing_batch(array $data, array $entityIds, array $user, ?array $attachment = null): int
{
    ensure_entities_schema();
    ensure_correspondence_party_snapshot_schema();
    ensure_entity_groups_schema();
    if (function_exists('ensure_corr_rsvp_schema')) {
        ensure_corr_rsvp_schema();
    }
    if (function_exists('ensure_corr_letter_schema')) {
        ensure_corr_letter_schema();
    }
    if (!user_can('outgoing.bulk', $user)) {
        throw new RuntimeException(__('auth.forbidden'));
    }

    $subject = trim((string) ($data['subject'] ?? ''));
    $letterBody = trim((string) ($data['letter_body'] ?? '')) ?: null;
    $letterBodyEn = trim((string) ($data['letter_body_en'] ?? '')) ?: null;
    $intendElectronic = !empty($data['intend_electronic']) ? 1 : 0;
    if (!$intendElectronic) {
        $letterBody = null;
        $letterBodyEn = null;
    }
    $requiresRsvp = (!empty($data['requires_rsvp']) || !empty($data['rsvp_required'])) ? 1 : 0;
    $corrDate = $data['corr_date'] ?? date('Y-m-d');
    $replyDue = trim((string) ($data['reply_due'] ?? '')) ?: null;
    $status = 'new';
    $priority = $data['priority'] ?? 'normal';
    $confidentiality = $data['confidentiality'] ?? 'normal';
    $categoryId = (int) ($data['category_id'] ?? 0) ?: null;
    $notes = trim((string) ($data['notes'] ?? '')) ?: null;
    $groupId = (int) ($data['entity_group_id'] ?? 0) ?: null;
    [$deptId, $issuingDeptId] = outgoing_resolve_sign_from($user, (int) ($data['issuing_department_id'] ?? 0));
    $allowedStatus = correspondence_assignable_status_codes();
    $allowedPriority = array_values(array_filter(array_map(
        static fn(array $row): string => trim((string) ($row['code'] ?? '')),
        function_exists('priority_levels_list') ? priority_levels_list(false) : []
    ))) ?: ['normal', 'urgent'];
    $allowedConf = array_values(array_filter(array_map(
        static fn(array $row): string => trim((string) ($row['code'] ?? '')),
        function_exists('confidentiality_levels_list') ? confidentiality_levels_list(false) : []
    ))) ?: ['normal', 'confidential'];

    if ($subject === '' || !in_array($status, $allowedStatus, true)
        || !in_array($priority, $allowedPriority, true) || !in_array($confidentiality, $allowedConf, true)) {
        throw new InvalidArgumentException(__('bulk.invalid'));
    }

    $uniqueIds = [];
    foreach ($entityIds as $id) {
        $id = (int) $id;
        if ($id > 0) {
            $uniqueIds[$id] = $id;
        }
    }
    if (count($uniqueIds) < 2) {
        throw new InvalidArgumentException(__('bulk.min_recipients'));
    }
    if (count($uniqueIds) > 200) {
        throw new InvalidArgumentException(__('bulk.max_recipients'));
    }

    $recipients = [];
    foreach ($uniqueIds as $id) {
        $entity = get_entity($id);
        if (!$entity || !user_can_select_entity($entity, $user) || !(int) ($entity['is_active'] ?? 1)) {
            continue;
        }
        $recipients[] = $entity;
    }
    if (count($recipients) < 2) {
        throw new InvalidArgumentException(__('bulk.min_recipients'));
    }

    if ($groupId) {
        $group = get_entity_group($groupId);
        if (!$group || !user_can_use_entity_group($group, $user)) {
            $groupId = null;
        }
    }

    $uploadFiles = function_exists('dms_normalize_upload_files')
        ? dms_normalize_upload_files(is_array($attachment) ? $attachment : null)
        : [];
    foreach ($uploadFiles as $uploadFile) {
        if (function_exists('dms_validate_document_upload')) {
            dms_validate_document_upload($uploadFile);
        }
    }
    $asDraft = function_exists('outgoing_prep_enabled')
        && outgoing_prep_enabled()
        && function_exists('outgoing_prep_placeholder_ref')
        && function_exists('outgoing_prep_bind_id_ref');
    if (!$asDraft && !$intendElectronic && $uploadFiles === []) {
        throw new RuntimeException(__('out_prep.attachment_before_review'));
    }
    if (function_exists('ensure_outgoing_prep_schema')) {
        ensure_outgoing_prep_schema();
    }

    // تجهيز المخطط خارج المعاملة: أي DDL في MySQL يُنهي المعاملة ضمنياً
    ensure_sequences_schema();
    if (function_exists('ensure_lookups_schema')) {
        ensure_lookups_schema();
    }
    if (function_exists('ensure_security_schema')) {
        ensure_security_schema();
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare(
            "INSERT INTO `tbloutgoing_batches` (subject, letter_body, letter_body_en, requires_rsvp, entity_group_id, created_by)
             VALUES (?, ?, ?, ?, ?, ?)"
        )->execute([$subject, $letterBody, $letterBodyEn, $requiresRsvp, $groupId, (int) $user['id']]);
        $batchId = (int) $pdo->lastInsertId();
        $refs = $asDraft ? [] : next_ref_numbers('outgoing', count($recipients), $deptId);
        $insert = $pdo->prepare(
            "INSERT INTO `tblcorrespondence`
            (type, ref_number, subject, party_name, entity_id,
             snap_title_prefix, snap_person_name, snap_job_title, snap_honorific, snap_org_name,
             snap_from_prefix, snap_from_person, snap_from_job, snap_from_org, snap_from_signature,
             external_ref, corr_date, reply_due,
             status, priority, confidentiality, category_id, notes, letter_body, letter_body_en, qr_token, linked_id, batch_id, department_id, issuing_department_id, created_by, rsvp_required, intend_electronic, outgoing_prep_status)
            VALUES ('outgoing', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, ?)"
        );
        $seedDocs = [];
        foreach ($recipients as $i => $entity) {
            $party = entity_recipient_name($entity);
            $snapVals = correspondence_snapshot_bind_values(correspondence_resolve_save_snapshot(
                null,
                $entity,
                'outgoing',
                $party,
                (int) $entity['id'],
                $deptId,
                $issuingDeptId
            ));
            $ref = $asDraft ? outgoing_prep_placeholder_ref() : $refs[$i];
            $insert->execute(array_merge(
                [
                    $ref,
                    $subject,
                    $party,
                    (int) $entity['id'],
                ],
                $snapVals,
                [
                    $corrDate,
                    $replyDue,
                    $status,
                    $priority,
                    $confidentiality,
                    $categoryId,
                    $notes,
                    $letterBody,
                    $letterBodyEn,
                    generate_qr_token(),
                    $batchId,
                    $deptId,
                    $issuingDeptId,
                    (int) $user['id'],
                    $requiresRsvp,
                    $intendElectronic,
                    $asDraft ? 'draft' : null,
                ]
            ));
            $corrId = (int) $pdo->lastInsertId();
            if ($asDraft) {
                outgoing_prep_bind_id_ref($corrId);
                $ref = 'OD-' . $corrId;
            }
            save_correspondence_enclosures($corrId, correspondence_enclosures_from_request($data));
            if ($uploadFiles !== []) {
                if ($seedDocs === []) {
                    foreach ($uploadFiles as $uploadFile) {
                        $doc = upload_document($uploadFile, $corrId, (int) $user['id']);
                        if ($doc) {
                            $seedDocs[] = $doc;
                        }
                    }
                } else {
                    foreach ($seedDocs as $doc) {
                        clone_document_to_correspondence($doc, $corrId, (int) $user['id']);
                    }
                }
            }
            log_activity((int) $user['id'], $corrId, 'create', ($asDraft ? 'إنشاء مسودة صادر جماعي ' : 'إنشاء صادر جماعي رقم ') . $ref);
        }
        if ($pdo->inTransaction()) {
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    // بعد الالتزام: المسودة تنتظر صاحب الشأن. غيرها يدخل المراجعة أو الاعتماد، أو يُحال للاتصالات.
    if ($asDraft) {
        return $batchId;
    }
    if (function_exists('outgoing_issue_flow_active')
        && outgoing_issue_flow_active()
        && function_exists('outgoing_batch_request_approvals')
    ) {
        try {
            outgoing_batch_request_approvals($batchId, $user);
        } catch (Throwable $apprEx) {
            error_log('DMS bulk outgoing approval auto-request: ' . $apprEx->getMessage());
        }
    } elseif (function_exists('outgoing_release_to_communications')) {
        try {
            $batchItems = db()->prepare("SELECT id FROM `tblcorrespondence` WHERE batch_id = ? AND type = 'outgoing'");
            $batchItems->execute([$batchId]);
            foreach ($batchItems->fetchAll(PDO::FETCH_COLUMN) ?: [] as $batchCorrId) {
                outgoing_release_to_communications((int) $batchCorrId);
            }
        } catch (Throwable $apprEx) {
            error_log('DMS bulk outgoing communications handoff: ' . $apprEx->getMessage());
        }
    }

    return $batchId;
}
