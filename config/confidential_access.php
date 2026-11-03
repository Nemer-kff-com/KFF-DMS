<?php
/**
 * سرية الرؤية والمرفقات — طبقات A/B/C/D (انظر doc/25-confidential-correspondence.md)
 */

function dms_confidentiality_visibility_enabled(): bool
{
    return (string) setting('confidentiality_restricts_visibility', '1') === '1';
}

function dms_conf_attach_setting(string $stage): bool
{
    $key = match ($stage) {
        'prep_signer' => 'conf_attach_prep_signer',
        'reviewer' => 'conf_attach_reviewer',
        'approver' => 'conf_attach_approver',
        'comms' => 'conf_attach_comms',
        'cc' => 'conf_attach_cc',
        default => '',
    };
    if ($key === '') {
        return true;
    }

    return (string) setting($key, '1') === '1';
}

/** @return array<string,string> stage => inherit|allow|deny */
function dms_conf_attach_overrides_from_row(array $row): array
{
    $raw = $row['confidential_attach_overrides'] ?? '';
    if (is_array($raw)) {
        $data = $raw;
    } else {
        $data = json_decode((string) $raw, true);
    }
    if (!is_array($data)) {
        return [];
    }
    $out = [];
    foreach (['prep_signer', 'reviewer', 'approver', 'comms', 'cc'] as $stage) {
        $v = strtolower(trim((string) ($data[$stage] ?? 'inherit')));
        if (in_array($v, ['inherit', 'allow', 'deny'], true)) {
            $out[$stage] = $v;
        }
    }

    return $out;
}

function dms_conf_attach_effective(string $stage, array $row): bool
{
    $o = dms_conf_attach_overrides_from_row($row)[$stage] ?? 'inherit';
    if ($o === 'allow') {
        return true;
    }
    if ($o === 'deny') {
        return false;
    }

    return dms_conf_attach_setting($stage);
}

function dms_user_is_system_admin(?array $user): bool
{
    if (!$user) {
        return false;
    }
    if (($user['role'] ?? '') === 'admin') {
        return true;
    }
    if (function_exists('dms_role_is_admin') && function_exists('dms_user_role_row')) {
        try {
            $role = dms_user_role_row($user);

            return $role ? dms_role_is_admin($role) : false;
        } catch (Throwable $e) {
            return false;
        }
    }

    return false;
}

/**
 * مخوّل برؤية المعاملة السرية (طبقة A) — بعد اجتياز نطاق القسم/المسار الأساسي.
 */
function user_is_confidential_visibility_authorized(array $corr, ?array $user = null): bool
{
    $user = $user ?? (function_exists('current_user') ? current_user() : null);
    if (!$user) {
        return false;
    }
    if (dms_user_is_system_admin($user)) {
        return true;
    }
    $perm = function_exists('view_confidential_perm_for_type')
        ? view_confidential_perm_for_type((string) ($corr['type'] ?? ''))
        : '';
    if ($perm !== '' && function_exists('user_can') && user_can($perm, $user)) {
        return true;
    }
    $uid = (int) ($user['id'] ?? 0);
    if ($uid > 0 && $uid === (int) ($corr['created_by'] ?? 0)) {
        return true;
    }
    if (function_exists('outgoing_prep_is_open') && outgoing_prep_is_open($corr)
        && function_exists('outgoing_user_can_access_prep') && outgoing_user_can_access_prep($user, $corr)
    ) {
        return true;
    }
    if (function_exists('outgoing_user_can_access_for_approval')
        && outgoing_user_can_access_for_approval($user, $corr)
    ) {
        return true;
    }
    if (function_exists('outgoing_user_can_dispatch') && outgoing_user_can_dispatch($user, $corr)) {
        return true;
    }
    if (function_exists('corr_cc_user_can_view') && corr_cc_user_can_view($corr, $user)) {
        return true;
    }

    return false;
}

function user_passes_confidential_visibility(array $corr, ?array $user = null): bool
{
    if (!function_exists('confidentiality_is_restricted')
        || !confidentiality_is_restricted($corr['confidentiality'] ?? '')
    ) {
        return true;
    }
    if (!dms_confidentiality_visibility_enabled()) {
        return true;
    }

    return user_is_confidential_visibility_authorized($corr, $user);
}

/**
 * أدوار المسار المنطبقة على المستخدم لهذه المعاملة (للمرفقات).
 *
 * @return list<string>
 */
function user_confidential_attach_stages(array $corr, ?array $user = null): array
{
    $user = $user ?? (function_exists('current_user') ? current_user() : null);
    if (!$user) {
        return [];
    }
    $uid = (int) ($user['id'] ?? 0);
    $stages = [];
    $isCreator = $uid > 0 && $uid === (int) ($corr['created_by'] ?? 0);

    if (!$isCreator
        && function_exists('outgoing_prep_is_open') && outgoing_prep_is_open($corr)
        && function_exists('outgoing_user_is_prep_signer') && outgoing_user_is_prep_signer($user, $corr)
    ) {
        $stages[] = 'prep_signer';
    }

    $status = function_exists('outgoing_approval_status_of') ? outgoing_approval_status_of($corr) : '';
    if ($status === 'pending_review'
        && function_exists('outgoing_user_can_review') && outgoing_user_can_review($user, $corr)
    ) {
        $stages[] = 'reviewer';
    } elseif ($uid > 0 && $uid === (int) ($corr['issue_review_by'] ?? 0)) {
        $stages[] = 'reviewer';
    }

    if (in_array($status, ['pending_sg', 'pending_deputy', 'pending_alternate'], true)) {
        $isApprover = false;
        if (function_exists('outgoing_user_can_decide_approval') && outgoing_user_can_decide_approval($user, $corr)) {
            $isApprover = true;
        }
        $assignee = (int) ($corr['issue_approval_assignee_id'] ?? 0);
        if ($assignee > 0 && $assignee === $uid) {
            $isApprover = true;
        }
        if ($isApprover) {
            $stages[] = 'approver';
        }
    } elseif ($uid > 0 && $uid === (int) ($corr['issue_approval_decided_by'] ?? 0)) {
        $stages[] = 'approver';
    }

    if (function_exists('outgoing_is_comms_print_stage') && outgoing_is_comms_print_stage($corr)
        && function_exists('outgoing_user_can_dispatch') && outgoing_user_can_dispatch($user, $corr)
    ) {
        $stages[] = 'comms';
    }

    if (function_exists('corr_cc_user_can_view') && corr_cc_user_can_view($corr, $user)) {
        $stages[] = 'cc';
    }

    return array_values(array_unique($stages));
}

/**
 * بعد السماح الأساسي بالمرفقات السرية عبر المسار: طبّق مفاتيح B/C (المنع يغلب).
 */
function user_confidential_attach_stages_allow(array $corr, array $stages): bool
{
    if ($stages === []) {
        return true;
    }
    foreach ($stages as $stage) {
        if (!dms_conf_attach_effective($stage, $corr)) {
            return false;
        }
    }

    return true;
}

/**
 * شرط SQL تقريبي للقوائم — يُستكمل بفحص PHP عند الفتح.
 *
 * @return array{0:string,1:list<mixed>}
 */
function sql_confidential_visibility_clause(string $alias, ?array $user = null): array
{
    if (!dms_confidentiality_visibility_enabled()) {
        return ['1=1', []];
    }
    $user = $user ?? (function_exists('current_user') ? current_user() : null);
    if (!$user) {
        return ['1=0', []];
    }
    if (dms_user_is_system_admin($user)) {
        return ['1=1', []];
    }

    $uid = (int) ($user['id'] ?? 0);
    $hasAnyConfPerm = false;
    if (function_exists('user_can') && function_exists('view_confidential_perm_for_type')) {
        foreach (['outgoing', 'incoming', 'internal'] as $t) {
            $p = view_confidential_perm_for_type($t);
            if ($p !== '' && user_can($p, $user)) {
                $hasAnyConfPerm = true;
                break;
            }
        }
    }
    if ($hasAnyConfPerm) {
        return ['1=1', []];
    }

    $a = preg_replace('/[^a-zA-Z0-9_]/', '', $alias) ?: 'c';
    $sql = "(IFNULL({$a}.confidentiality,'normal') IN ('','normal')
        OR {$a}.created_by = ?
        OR IFNULL({$a}.issue_approval_assignee_id,0) = ?
        OR IFNULL({$a}.issue_review_by,0) = ?
        OR IFNULL({$a}.issue_approval_requested_by,0) = ?
        OR IFNULL({$a}.issue_approval_decided_by,0) = ?
        OR EXISTS (
            SELECT 1 FROM `tblcorr_cc` cc
            WHERE cc.correspondence_id = {$a}.id AND cc.user_id = ?
        ))";

    return [$sql, [$uid, $uid, $uid, $uid, $uid, $uid]];
}

function ensure_confidential_attach_overrides_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $cols = db()->query('SHOW COLUMNS FROM `tblcorrespondence` LIKE \'confidential_attach_overrides\'')->fetchAll();
        if (!$cols) {
            db()->exec(
                'ALTER TABLE `tblcorrespondence`
                 ADD COLUMN `confidential_attach_overrides` JSON NULL
                 COMMENT \'per-stage inherit|allow|deny for confidential attachments\'
                 AFTER `confidentiality`'
            );
        }
    } catch (Throwable $e) {
        try {
            db()->exec(
                'ALTER TABLE `tblcorrespondence`
                 ADD COLUMN `confidential_attach_overrides` TEXT NULL
                 AFTER `confidentiality`'
            );
        } catch (Throwable $e2) {
            // ignore
        }
    }
    try {
        $cols = db()->query('SHOW COLUMNS FROM `tblarchive_items` LIKE \'confidential_attach_overrides\'')->fetchAll();
        if (!$cols) {
            db()->exec(
                'ALTER TABLE `tblarchive_items`
                 ADD COLUMN `confidential_attach_overrides` TEXT NULL
                 AFTER `confidentiality`'
            );
        }
    } catch (Throwable $e) {
        // ignore
    }
}

function dms_normalize_conf_attach_overrides_input($raw): ?string
{
    if (!is_array($raw)) {
        return null;
    }
    $out = [];
    foreach (['prep_signer', 'reviewer', 'approver', 'comms', 'cc'] as $stage) {
        $v = strtolower(trim((string) ($raw[$stage] ?? 'inherit')));
        if (!in_array($v, ['inherit', 'allow', 'deny'], true)) {
            $v = 'inherit';
        }
        if ($v !== 'inherit') {
            $out[$stage] = $v;
        }
    }
    if ($out === []) {
        return null;
    }

    return json_encode($out, JSON_UNESCAPED_UNICODE);
}

/** وضع الإشعار الخارجي: redacted = رقم/نوع فقط بلا موضوع أو مرفق سري */
function dms_conf_external_notify_redacted(): bool
{
    return (string) setting('conf_external_notify_mode', 'redacted') !== 'full';
}

function dms_corr_is_confidential_row(?array $corr): bool
{
    return $corr
        && function_exists('confidentiality_is_restricted')
        && confidentiality_is_restricted($corr['confidentiality'] ?? '');
}

/** نص آمن للعرض/الإشعار عند السرية */
function dms_conf_public_subject_label(?array $corr = null): string
{
    if (function_exists('__')) {
        return __('conf.redacted_subject');
    }

    return 'معاملة سرية';
}

/**
 * تصفية مستلمي إشعار مرتبط بمعاملة: من لا يجتاز طبقة A يُستبعد.
 *
 * @param list<int> $userIds
 * @return list<int>
 */
function dms_filter_notify_user_ids_for_corr(array $userIds, ?array $corr): array
{
    if (!$corr || !dms_corr_is_confidential_row($corr)) {
        return array_values(array_unique(array_map('intval', $userIds)));
    }
    if (!function_exists('user_can_access_correspondence')) {
        return array_values(array_unique(array_map('intval', $userIds)));
    }
    $out = [];
    $stmt = null;
    try {
        $stmt = db()->prepare('SELECT * FROM `tblusers` WHERE id = ? LIMIT 1');
    } catch (Throwable $e) {
        return [];
    }
    foreach ($userIds as $uid) {
        $uid = (int) $uid;
        if ($uid <= 0) {
            continue;
        }
        try {
            $stmt->execute([$uid]);
            $u = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Throwable $e) {
            $u = null;
        }
        if ($u && user_can_access_correspondence($corr, $u)) {
            $out[] = $uid;
        }
    }

    return array_values(array_unique($out));
}

/**
 * جسم إشعار داخلي/بريد: عند السرية أبقِ الرقم والنوع فقط.
 *
 * @return array{subject:string,body:string,details:string}
 */
function dms_conf_safe_notify_bits(array $corr, string $subject, string $body = '', string $details = ''): array
{
    if (!dms_corr_is_confidential_row($corr) || !dms_conf_external_notify_redacted()) {
        return ['subject' => $subject, 'body' => $body, 'details' => $details];
    }
    $ref = function_exists('format_ref_number')
        ? format_ref_number($corr['ref_number'] ?? '')
        : (string) ($corr['ref_number'] ?? '');
    $type = function_exists('type_label')
        ? type_label((string) ($corr['type'] ?? ''))
        : (string) ($corr['type'] ?? '');
    $safe = trim($type . ($ref !== '' ? ' — ' . $ref : ''));
    if ($safe === '') {
        $safe = dms_conf_public_subject_label($corr);
    }

    return ['subject' => $safe, 'body' => $safe, 'details' => $safe];
}

/** هل يحق لمستخدم رؤية صف سجل نشاط مرتبط بكيان سري؟ */
function activity_row_visible_to_user(array $row, ?array $user = null): bool
{
    $user = $user ?? (function_exists('current_user') ? current_user() : null);
    if (!$user) {
        return false;
    }
    if (dms_user_is_system_admin($user)) {
        return true;
    }
    if (function_exists('user_can') && user_can('admin.logs', $user)) {
        return true;
    }
    $corrId = (int) ($row['correspondence_id'] ?? 0);
    if ($corrId > 0 && function_exists('get_correspondence') && function_exists('user_can_access_correspondence')) {
        $corr = get_correspondence($corrId);
        if ($corr && dms_corr_is_confidential_row($corr)) {
            return user_can_access_correspondence($corr, $user);
        }
    }
    $archiveId = (int) ($row['archive_id'] ?? 0);
    if ($archiveId > 0 && function_exists('get_archive_item') && function_exists('user_can_access_archive')) {
        $item = get_archive_item($archiveId);
        if ($item
            && function_exists('confidentiality_is_restricted')
            && confidentiality_is_restricted($item['confidentiality'] ?? '')
        ) {
            return user_can_access_archive($item, $user);
        }
    }

    return true;
}

/**
 * رؤية صف سجل أرشيف (منح/مشاريع/مالية) عند السرية.
 */
function user_can_access_register_row(string $kind, array $row, ?array $user = null): bool
{
    $user = $user ?? (function_exists('current_user') ? current_user() : null);
    if (!$user || !function_exists('user_can_archive_section') || !user_can_archive_section($kind, 'view', $user)) {
        return false;
    }
    $level = (string) ($row['confidentiality'] ?? $row['CONFIDENTIALITY'] ?? '');
    if (!function_exists('confidentiality_is_restricted') || !confidentiality_is_restricted($level)) {
        return true;
    }
    if (!dms_confidentiality_visibility_enabled()) {
        return true;
    }
    if (dms_user_is_system_admin($user)) {
        return true;
    }
    $perm = function_exists('view_confidential_perm_for_type') ? view_confidential_perm_for_type($kind) : '';
    if ($perm !== '' && function_exists('user_can') && user_can($perm, $user)) {
        return true;
    }
    $uid = (int) ($user['id'] ?? 0);
    $created = (int) ($row['created_by'] ?? $row['CREATED_BY'] ?? 0);

    return $uid > 0 && $uid === $created;
}

/**
 * شرط SQL لسجلات الأرشيف عند وجود عمود السرية.
 *
 * @return array{0:string,1:list<mixed>}
 */
function sql_register_confidential_visibility_clause(string $kind, string $alias = '', ?array $user = null): array
{
    if (!dms_confidentiality_visibility_enabled()) {
        return ['1=1', []];
    }
    $user = $user ?? (function_exists('current_user') ? current_user() : null);
    if (!$user) {
        return ['1=0', []];
    }
    if (dms_user_is_system_admin($user)) {
        return ['1=1', []];
    }
    $perm = function_exists('view_confidential_perm_for_type') ? view_confidential_perm_for_type($kind) : '';
    if ($perm !== '' && function_exists('user_can') && user_can($perm, $user)) {
        return ['1=1', []];
    }
    $prefix = $alias !== '' ? rtrim($alias, '.') . '.' : '';
    $uid = (int) ($user['id'] ?? 0);
    // أسماء أعمدة مختلطة بين الجداول
    $sql = "(IFNULL({$prefix}confidentiality, IFNULL({$prefix}CONFIDENTIALITY,'normal')) IN ('','normal')
        OR IFNULL({$prefix}created_by, IFNULL({$prefix}CREATED_BY,0)) = ?)";

    return [$sql, [$uid]];
}

function ensure_register_confidentiality_schema(): void
{
    static $done = false;
    if ($done || !function_exists('archive_register_defs')) {
        return;
    }
    $done = true;
    foreach (archive_register_defs() as $def) {
        $table = (string) ($def['table'] ?? '');
        if ($table === '' || !preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            continue;
        }
        try {
            $cols = db()->query("SHOW COLUMNS FROM `{$table}` LIKE 'confidentiality'")->fetchAll();
            if (!$cols) {
                db()->exec(
                    "ALTER TABLE `{$table}` ADD COLUMN `confidentiality` VARCHAR(40) NULL DEFAULT 'normal'"
                );
            }
            $ov = db()->query("SHOW COLUMNS FROM `{$table}` LIKE 'confidential_attach_overrides'")->fetchAll();
            if (!$ov) {
                db()->exec(
                    "ALTER TABLE `{$table}` ADD COLUMN `confidential_attach_overrides` TEXT NULL"
                );
            }
        } catch (Throwable $e) {
            // ignore
        }
    }
}
