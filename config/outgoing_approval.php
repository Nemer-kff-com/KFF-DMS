<?php
/**
 * موافقة إصدار الصادر: الأمين العام أولاً، ثم تصعيد للأمين المساعد بعد المهلة.
 */

declare(strict_types=1);

/** أنواع المسارات التي يخدمها محرك الاعتماد */
function appr_types(): array
{
    return ['outgoing', 'incoming', 'internal'];
}

function appr_type_of(array $corr): string
{
    $type = (string) ($corr['type'] ?? '');
    return in_array($type, appr_types(), true) ? $type : 'outgoing';
}

/** نوع المسار الحالي (outgoing|incoming|internal) — تُقرأ منه بادئة الإعدادات والروابط */
function appr_p(): string
{
    return $GLOBALS['_dms_appr_p'] ?? 'outgoing';
}

/** تشغيل دالة ضمن مسار نوع معيّن ثم استعادة المسار السابق */
function appr_with(string $type, callable $fn): mixed
{
    $prev = $GLOBALS['_dms_appr_p'] ?? null;
    $GLOBALS['_dms_appr_p'] = in_array($type, appr_types(), true) ? $type : 'outgoing';
    try {
        return $fn();
    } finally {
        if ($prev === null) {
            unset($GLOBALS['_dms_appr_p']);
        } else {
            $GLOBALS['_dms_appr_p'] = $prev;
        }
    }
}

/** نوع مسار المعاملة إن اختلف عن المسار الحالي، وإلا null */
function appr_switch_for(array $corr): ?string
{
    $type = appr_type_of($corr);
    return $type === appr_p() ? null : $type;
}

/**
 * شروط SQL لطابور يشمل كل الأنواع: (شروط الصادر) OR (شروط الوارد) OR (شروط الداخلية).
 *
 * @param callable(): list<string> $fn
 * @return list<string>
 */
function appr_sql_any(callable $fn): array
{
    $parts = [];
    foreach (appr_types() as $type) {
        $conds = appr_with($type, $fn);
        if ($conds !== ['1=0']) {
            $parts[] = '(' . implode(' AND ', $conds) . ')';
        }
    }
    return $parts === [] ? ['1=0'] : ['(' . implode(' OR ', $parts) . ')'];
}

/** نص المسار الحالي: للوارد يُفضَّل مفتاح in_ وللداخلية int_ إن وُجد */
function appr__(string $key): string
{
    $prefix = match (appr_p()) {
        'incoming' => 'in_',
        'internal' => 'int_',
        default => '',
    };
    if ($prefix !== '') {
        $alt = __($prefix . $key, "\0");
        if ($alt !== "\0") {
            return $alt;
        }
    }
    return __($key);
}

/** شرط «أي نوع» لدوال الطوابير حين لا يكون السياق محدداً */
function appr_any(callable $fn): bool
{
    foreach (appr_types() as $type) {
        if (appr_with($type, $fn)) {
            return true;
        }
    }
    return false;
}

function ensure_outgoing_approval_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $cols = [
            'issue_approval_status' => "VARCHAR(32) NULL DEFAULT NULL COMMENT 'pending_review|returned|pending_copy|pending_sg|pending_deputy|pending_alternate|approved|rejected' AFTER status",
            'issue_approval_requested_at' => 'DATETIME NULL DEFAULT NULL AFTER issue_approval_status',
            'issue_approval_requested_by' => 'INT UNSIGNED NULL DEFAULT NULL AFTER issue_approval_requested_at',
            'issue_approval_assignee_id' => 'INT UNSIGNED NULL DEFAULT NULL AFTER issue_approval_requested_by',
            'issue_approval_decided_at' => 'DATETIME NULL DEFAULT NULL AFTER issue_approval_assignee_id',
            'issue_approval_decided_by' => 'INT UNSIGNED NULL DEFAULT NULL AFTER issue_approval_decided_at',
            'issue_approval_note' => 'VARCHAR(500) NULL DEFAULT NULL AFTER issue_approval_decided_by',
            'issue_approval_escalated_at' => 'DATETIME NULL DEFAULT NULL AFTER issue_approval_note',
            'issue_review_by' => 'INT UNSIGNED NULL DEFAULT NULL AFTER issue_approval_escalated_at',
            'issue_reviewed_at' => 'DATETIME NULL DEFAULT NULL AFTER issue_review_by',
            'issue_review_note' => 'VARCHAR(500) NULL DEFAULT NULL AFTER issue_reviewed_at',
            'issue_approval_reassigned_at' => 'DATETIME NULL DEFAULT NULL AFTER issue_review_note',
        ];
        foreach ($cols as $name => $ddl) {
            if (!dms_db_column_exists('tblcorrespondence', $name)) {
                db()->exec('ALTER TABLE `tblcorrespondence` ADD COLUMN `' . $name . '` ' . $ddl);
            }
        }
    } catch (Throwable $e) {
        $done = false;
    }
}

function outgoing_approval_enabled(): bool
{
    return setting(appr_p() . '_approval_enabled', appr_p() === 'outgoing' ? '1' : '0') === '1';
}

function outgoing_approval_clamp_minutes(int $m): int
{
    if ($m < 1) {
        $m = 1;
    }
    if ($m > 4320) {
        $m = 4320;
    }
    return $m;
}

/**
 * مهلة مرحلة الاعتماد بالدقائق.
 * @param 'primary'|'deputy' $stage
 */
function outgoing_approval_minutes_for(string $stage = 'primary'): int
{
    if ($stage === 'deputy') {
        $raw = trim((string) setting(appr_p() . '_approval_deputy_minutes', ''));
        if ($raw === '') {
            return outgoing_approval_minutes_for('primary');
        }
        return outgoing_approval_clamp_minutes((int) $raw);
    }

    $raw = trim((string) setting(appr_p() . '_approval_minutes', ''));
    if ($raw === '') {
        $h = (int) setting(appr_p() . '_approval_hours', '2');
        if ($h < 1) {
            $h = 1;
        }
        if ($h > 72) {
            $h = 72;
        }
        return outgoing_approval_clamp_minutes($h * 60);
    }
    return outgoing_approval_clamp_minutes((int) $raw);
}

function outgoing_approval_minutes(): int
{
    return outgoing_approval_minutes_for('primary');
}

function outgoing_approval_deputy_minutes(): int
{
    return outgoing_approval_minutes_for('deputy');
}

/** @deprecated للتوافق — استخدم outgoing_approval_minutes() */
function outgoing_approval_hours(): int
{
    return max(1, (int) ceil(outgoing_approval_minutes() / 60));
}

/**
 * وحدة عرض المهلة: minutes|hours
 * @param 'primary'|'deputy' $stage
 */
function outgoing_approval_unit_for(string $stage = 'primary'): string
{
    $key = appr_p() . ($stage === 'deputy' ? '_approval_deputy_unit' : '_approval_unit');
    $u = strtolower(trim((string) setting($key, '')));
    if ($u === 'minutes' || $u === 'hours') {
        return $u;
    }
    $m = outgoing_approval_minutes_for($stage);
    return ($m % 60 === 0 && $m >= 60) ? 'hours' : 'minutes';
}

function outgoing_approval_unit(): string
{
    return outgoing_approval_unit_for('primary');
}

/**
 * نص المهلة للعرض
 * @param 'primary'|'deputy' $stage
 */
function outgoing_approval_timeout_label_for(string $stage = 'primary'): string
{
    $m = outgoing_approval_minutes_for($stage);
    $unit = outgoing_approval_unit_for($stage);
    if ($unit === 'hours' || ($m % 60 === 0 && $m >= 60 && $unit !== 'minutes')) {
        $h = max(1, (int) round($m / 60));
        return $h . ' ' . __('settings.hours');
    }
    return $m . ' ' . __('settings.minutes');
}

function outgoing_approval_timeout_label(): string
{
    return outgoing_approval_timeout_label_for('primary');
}

function outgoing_approval_sg_user_id(): int
{
    return (int) setting(appr_p() . '_approver_sg_user_id', '0');
}

function outgoing_approval_deputy_user_id(): int
{
    return (int) setting(appr_p() . '_approver_deputy_user_id', '0');
}

function outgoing_approval_alternate_user_id(): int
{
    return (int) setting(appr_p() . '_approver_alternate_user_id', '0');
}

function outgoing_approval_deputy_enabled(): bool
{
    return setting(appr_p() . '_approver_deputy_enabled', '1') === '1';
}

function outgoing_approval_alternate_enabled(): bool
{
    return setting(appr_p() . '_approver_alternate_enabled', '1') === '1';
}

/** مستخدم المعتمد الثاني إن كان مفعّلاً للتصعيد، وإلا 0 */
function outgoing_approval_deputy_escalation_user_id(): int
{
    return outgoing_approval_deputy_enabled() ? outgoing_approval_deputy_user_id() : 0;
}

/** مستخدم المعتمد البديل إن كان مفعّلاً للتصعيد، وإلا 0 */
function outgoing_approval_alternate_escalation_user_id(): int
{
    return outgoing_approval_alternate_enabled() ? outgoing_approval_alternate_user_id() : 0;
}

/** هل المستخدم هو المعتمد البديل المعيَّن في الإعدادات */
function outgoing_user_is_alternate_approver(array $user, ?array $corr = null): bool
{
    // مع المعاملة: الحكم بمرحلتها الحالية لا بهوية المستخدم، فالبديل المعيَّن يُعامَل في مرحلة أخرى كمعتمدها
    if ($corr !== null) {
        if ($t = appr_switch_for($corr)) {
            return appr_with($t, static fn (): bool => outgoing_user_is_alternate_approver($user, $corr));
        }
        return outgoing_approval_status_of($corr) === 'pending_alternate'
            && appr_user_matches_stage((int) ($user['id'] ?? 0), $corr);
    }
    $uid = (int) ($user['id'] ?? 0);
    $alt = outgoing_approval_alternate_user_id();
    return $uid > 0 && $alt > 0 && $uid === $alt;
}

/**
 * صلاحية فتح صادر بسبب دور اعتماد على هذه المعاملة فقط — ليست صلاحية عامة.
 *
 * يُسمح عند:
 * - كونه المعيَّن الحالي / من يحق له البتّ الآن
 * - كونه طالب الموافقة أو من بَتّ فيها (اطلاع على ما شارك فيه)
 * - كونه من مرحلة سابقة بعد التصعيد (اطلاع دون بتّ)
 */
function outgoing_user_can_access_for_approval(array $user, array $corr): bool
{
    if ($t = appr_switch_for($corr)) {
        return appr_with($t, static fn (): bool => outgoing_user_can_access_for_approval($user, $corr));
    }
    if (($corr['type'] ?? '') !== appr_p()) {
        return false;
    }
    $uid = (int) ($user['id'] ?? 0);
    if ($uid <= 0) {
        return false;
    }
    $status = outgoing_approval_status_of($corr);
    if ($status === '') {
        return false;
    }

    $assignee = (int) ($corr['issue_approval_assignee_id'] ?? 0);
    if ($assignee > 0 && $assignee === $uid) {
        return true;
    }
    if (outgoing_user_can_decide_approval($user, $corr)) {
        return true;
    }
    if ($uid === (int) ($corr['issue_approval_decided_by'] ?? 0)) {
        return true;
    }
    if ($uid === (int) ($corr['issue_approval_requested_by'] ?? 0)) {
        return true;
    }
    if ($uid === (int) ($corr['issue_review_by'] ?? 0) || outgoing_user_can_review($user, $corr)) {
        return true;
    }
    if ($status === 'pending_review' && $uid === outgoing_review_primary_user_id()) {
        return true;
    }

    // اطلاع المرحلة السابقة بعد التصعيد — دون صلاحية اعتماد
    if ($status === 'pending_deputy' && $uid === outgoing_approval_sg_user_id()) {
        return true;
    }
    if ($status === 'pending_alternate'
        && ($uid === outgoing_approval_sg_user_id() || $uid === outgoing_approval_deputy_user_id())
    ) {
        return true;
    }
    // من نُقلت عنه المعاملة بتدخّل مدير النظام يبقى مطّلعاً دون بتّ
    if (!empty($corr['issue_approval_reassigned_at'])
        && in_array($uid, [outgoing_approval_sg_user_id(), outgoing_approval_deputy_user_id(), outgoing_approval_alternate_user_id()], true)
    ) {
        return true;
    }

    return false;
}

/** المسمى الوظيفي للمستخدم حسب لغة الواجهة (إنجليزي إن وُجد، وإلا العربي) */
function user_job_title(?array $row): string
{
    if (!$row) {
        return '';
    }
    $ar = trim((string) ($row['job_title'] ?? ''));
    $en = trim((string) ($row['job_title_en'] ?? ''));
    if (function_exists('dms_lang_code') && dms_lang_code() === 'en' && $en !== '') {
        return $en;
    }
    return $ar !== '' ? $ar : $en;
}

/** مسمى المعتمد = المسمى الوظيفي للمستخدم المختار في إعدادات الموافقة */
function outgoing_approver_role_title(string $role, string $defaultKey): string
{
    static $cache = [];
    $userId = (int) setting(appr_p() . '_approver_' . $role . '_user_id', '0');
    if ($userId > 0) {
        if (!array_key_exists($userId, $cache)) {
            try {
                $stmt = db()->prepare('SELECT job_title, job_title_en FROM `tblusers` WHERE id = ? LIMIT 1');
                $stmt->execute([$userId]);
                $cache[$userId] = $stmt->fetch() ?: null;
            } catch (Throwable $e) {
                $cache[$userId] = null;
            }
        }
        $title = user_job_title($cache[$userId]);
        if ($title !== '') {
            return $title;
        }
    }
    return __($defaultKey);
}

function outgoing_approver_sg_title(): string
{
    return outgoing_approver_role_title('sg', 'settings.outgoing_approver_sg');
}

function outgoing_approver_deputy_title(): string
{
    return outgoing_approver_role_title('deputy', 'settings.outgoing_approver_deputy');
}

function outgoing_approver_alternate_title(): string
{
    return outgoing_approver_role_title('alternate', 'settings.outgoing_approver_alternate');
}

/**
 * ترجمة مفاتيح موافقة الإصدار مع استبدال :sg و :deputy و :alternate وبدائل إضافية.
 *
 * @param array<string,string> $extra
 */
function outgoing_appr_text(string $key, array $extra = []): string
{
    $text = appr__($key);
    $map = [
        ':sg' => outgoing_approver_sg_title(),
        ':deputy' => outgoing_approver_deputy_title(),
        ':alternate' => outgoing_approver_alternate_title(),
    ];
    foreach ($extra as $k => $v) {
        $map[(string) $k] = (string) $v;
    }
    return str_replace(array_keys($map), array_values($map), $text);
}

/** هل المسار مفعّل وجاهز (مُعرَّف المعتمد الأول على الأقل) */
function outgoing_approval_is_configured(): bool
{
    return outgoing_approval_enabled() && outgoing_approval_sg_user_id() > 0;
}

/**
 * أول مرحلة اعتماد للمعاملة بعد المراجعة (أو مباشرة).
 * المعتمد الأول من الإعدادات في الصادر والوارد والداخلية.
 * إن كان المعتمد الأول غير نشط أو منشئها/طالبها/مراجعها فالمعتمد الثاني، ثم البديل.
 *
 * @param list<int> $excludeIds
 * @param array<int,string> $roles دور المستبعَدين لسبب التخطّي: requester | reviewer
 * @return array{status:string, assignee:int, skip_note:string}|null
 */
function appr_first_stage(array $corr, array $excludeIds = [], array $roles = []): ?array
{
    $exclude = array_values(array_filter(array_map('intval', $excludeIds)));
    $exclude[] = (int) ($corr['created_by'] ?? 0);
    $skipped = [];
    $sgId = outgoing_approval_sg_user_id();
    $firstIds = outgoing_active_user_ids([$sgId]);
    if ($sgId > 0 && ($firstIds === [] || in_array($sgId, $exclude, true))) {
        $skipped[] = appr_skip_note($sgId, __('appr_skip.stage_first'), $corr, $roles);
    }
    foreach ($firstIds as $id) {
        if (!in_array($id, $exclude, true)) {
            return ['status' => 'pending_sg', 'assignee' => $id, 'skip_note' => ''];
        }
    }
    foreach ([
        'pending_deputy' => outgoing_approval_deputy_escalation_user_id(),
        'pending_alternate' => outgoing_approval_alternate_escalation_user_id(),
    ] as $status => $id) {
        if ($id > 0 && outgoing_active_user_ids([$id]) !== []
            && ($status === 'pending_alternate' || !in_array($id, $exclude, true))) {
            return ['status' => $status, 'assignee' => $id, 'skip_note' => implode(' ', array_filter($skipped))];
        }
        if ($status === 'pending_deputy' && $id > 0) {
            $skipped[] = appr_skip_note($id, __('appr_skip.stage_second'), $corr, $roles);
        }
    }
    return null;
}

/**
 * سبب تخطّي معتمد للسجل والإشعار: غير نشط، أو منشئ المعاملة / طالب الاعتماد / مراجعها (فصل المهام).
 *
 * @param array<int,string> $roles requester | reviewer لمن لم يُسجَّل دوره في المعاملة بعد
 */
function appr_skip_note(int $userId, string $stageLabel, array $corr, array $roles = []): string
{
    if ($userId <= 0) {
        return '';
    }
    $role = match (true) {
        outgoing_active_user_ids([$userId]) === [] => 'inactive',
        $userId === (int) ($corr['created_by'] ?? 0) => 'creator',
        isset($roles[$userId]) => $roles[$userId],
        $userId === (int) ($corr['issue_approval_requested_by'] ?? 0) => 'requester',
        $userId === (int) ($corr['issue_review_by'] ?? 0) => 'reviewer',
        default => 'requester',
    };
    $note = strtr(__('appr_skip.note'), [
        ':stage' => $stageLabel,
        ':name' => outgoing_user_name_by_id($userId),
        ':reason' => __('appr_skip.reason_' . $role),
    ]);
    return str_replace(' ()', '', $note);
}

/** مرحلة المراجعة. في الصادر يكفي تفعيل مفتاحها، ولا تُربط بمفتاح الاعتماد. */
function outgoing_review_enabled(): bool
{
    if (setting(appr_p() . '_review_enabled', '0') !== '1') {
        return false;
    }
    if (appr_p() !== 'outgoing') {
        return outgoing_approval_is_configured();
    }

    return true;
}

/** هل يبدأ مسار الصادر: المراجعة مفعّلة، أو الاعتماد مضبوط. */
function outgoing_issue_flow_active(): bool
{
    if (appr_p() === 'outgoing' && outgoing_review_enabled()) {
        return true;
    }

    return outgoing_approval_is_configured();
}

/**
 * أول وجهة بعد الإنشاء حسب إعدادات النوع: prep | review | approval | none
 * (الوارد عند التسجيل يبقى في المكتب؛ الوجهة تُحسب عند إكمال المسار.)
 */
function corr_issue_first_destination(string $type): string
{
    if (!in_array($type, appr_types(), true)) {
        return 'none';
    }
    if ($type === 'outgoing' && function_exists('outgoing_prep_enabled') && outgoing_prep_enabled()) {
        return 'prep';
    }

    return appr_with($type, static function () use ($type): string {
        if (outgoing_review_enabled()) {
            if ($type === 'outgoing' || outgoing_approval_is_configured()) {
                return 'review';
            }
        }
        if (outgoing_approval_is_configured()) {
            return 'approval';
        }

        return 'none';
    });
}

/**
 * وجهة مسار النوع الحالية من الإعدادات: prep | review | approval | none
 * تُستخدم لرسائل التأكيد بعد تغيير الإعدادات مباشرة.
 */
function corr_path_destination(string $type): string
{
    return corr_issue_first_destination($type);
}

/** رسالة تأكيد زر الحفظ عند إنشاء صادر/وارد/داخلي — تتبع إعداد المسار. */
function corr_create_confirm_message(string $type): string
{
    $dest = corr_path_destination($type);

    return match ($type) {
        'outgoing' => match ($dest) {
            'prep' => __('out_prep.confirm_submit'),
            'review' => __('corr.confirm_save_out_review'),
            'approval' => __('corr.confirm_save_out_approver'),
            default => __('corr.confirm_save_out'),
        },
        'incoming' => match ($dest) {
            'review' => __('corr.confirm_save_in_review'),
            'approval' => __('corr.confirm_save_in_approver'),
            default => __('corr.confirm_save_in'),
        },
        'internal' => match ($dest) {
            'review' => __('internal.confirm_save_review'),
            'approval' => __('internal.confirm_save_approver'),
            default => __('internal.confirm_save'),
        },
        default => '',
    };
}

/**
 * وجهة الوارد عند إكمال المسار لهذه المعاملة: review | approval | none
 * (none = إحالة مباشرة للجهة إن خارج النطاق أو بلا اعتماد).
 */
function incoming_desk_destination(array $corr): string
{
    if (!function_exists('incoming_approval_in_scope') || !incoming_approval_in_scope($corr)) {
        return 'none';
    }

    return appr_with('incoming', static function (): string {
        if (outgoing_review_enabled()) {
            return 'review';
        }
        if (outgoing_approval_is_configured()) {
            return 'approval';
        }

        return 'none';
    });
}

/** رسالة تأكيد «إكمال المسار» للوارد حسب مسار المعاملة. */
function incoming_desk_confirm_message(array $corr): string
{
    return match (incoming_desk_destination($corr)) {
        'review' => __('in_issue_appr.desk_confirm_review'),
        'approval' => __('in_issue_appr.desk_confirm_approver'),
        default => __('in_issue_appr.desk_confirm_direct'),
    };
}

/** رسالة تأكيد إرسال/إعادة إرسال الوارد إلى مسار المراجعة أو الاعتماد. */
function incoming_flow_request_confirm(bool $resubmit = false): string
{
    $dest = corr_path_destination('incoming');
    if ($resubmit) {
        return match ($dest) {
            'review' => __('in_issue_appr.confirm_resubmit_review'),
            'approval' => __('in_issue_appr.confirm_resubmit_approver'),
            default => __('in_issue_appr.confirm_resubmit'),
        };
    }

    return match ($dest) {
        'review' => __('in_issue_appr.confirm_request_review'),
        'approval' => __('in_issue_appr.confirm_request_approver'),
        default => __('in_issue_appr.confirm_request'),
    };
}

/** مستخدم دور «الاتصالات الإدارية» — مجمّع المراجعين حين لا يُعيَّن مراجع محدد */
function outgoing_user_is_reviewer(?array $user): bool
{
    return $user !== null && (string) ($user['role'] ?? '') === 'communications';
}

function outgoing_review_primary_user_id(): int
{
    return (int) setting(appr_p() . '_reviewer_primary_user_id', '0');
}

/** المراجع الثاني إن كان مفعّلاً، وإلا 0 */
function outgoing_review_alternate_user_id(): int
{
    if (setting(appr_p() . '_reviewer_alternate_enabled', '1') !== '1') {
        return 0;
    }
    return (int) setting(appr_p() . '_reviewer_alternate_user_id', '0');
}

/** هل عُيِّن مراجع محدد (أساسي أو بديل)؟ وإلا يراجع أي مستخدم من «الاتصالات الإدارية» */
function outgoing_review_has_assigned(): bool
{
    return outgoing_review_primary_user_id() > 0 || outgoing_review_alternate_user_id() > 0;
}

/** مهلة المراجع الأول بالدقائق قبل التحويل للمراجع الثاني */
function outgoing_review_minutes(): int
{
    $raw = trim((string) setting(appr_p() . '_review_minutes', ''));
    return outgoing_approval_clamp_minutes($raw === '' ? 120 : (int) $raw);
}

function outgoing_review_timeout_label(): string
{
    $m = outgoing_review_minutes();
    if (setting(appr_p() . '_review_unit', 'hours') === 'hours' && $m % 60 === 0) {
        return ($m / 60) . ' ' . __('settings.hours');
    }
    return $m . ' ' . __('settings.minutes');
}

/** @param list<int> $ids @return list<int> المستخدمون النشطون فقط بنفس الترتيب */
function outgoing_active_user_ids(array $ids): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
    if ($ids === []) {
        return [];
    }
    try {
        $stmt = db()->prepare(
            'SELECT id FROM `tblusers` WHERE is_active = 1 AND id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')'
        );
        $stmt->execute($ids);
        $active = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    } catch (Throwable $e) {
        return [];
    }
    return array_values(array_filter($ids, static fn (int $id): bool => in_array($id, $active, true)));
}

/**
 * مستخدمو «الاتصالات الإدارية» النشطون المؤهّلون لمعاملة (دون منشئها أو طالبها).
 *
 * @return list<int>
 */
function outgoing_review_user_ids(array $excludeIds = []): array
{
    try {
        $ids = db()->query(
            "SELECT id FROM `tblusers` WHERE role = 'communications' AND is_active = 1 ORDER BY id"
        )->fetchAll(PDO::FETCH_COLUMN) ?: [];
    } catch (Throwable $e) {
        return [];
    }
    $exclude = array_filter(array_map('intval', $excludeIds));
    return array_values(array_diff(array_map('intval', $ids), $exclude));
}

/**
 * توجيه المراجعة: المراجع الأول، ثم الثاني (إن كان الأول غير مؤهَّل)،
 * ثم مجمّع «الاتصالات الإدارية». assignee = 0 يعني أن أي مستخدم من المجمّع يستطيع المراجعة.
 *
 * @param list<int> $excludeIds منشئ المعاملة وطالب الاعتماد
 * @return array{assignee:int, notify:list<int>}|null null = لا يوجد مراجع مؤهَّل
 */
function outgoing_review_route(array $excludeIds): ?array
{
    $exclude = array_values(array_filter(array_map('intval', $excludeIds)));
    $eligible = outgoing_active_user_ids([outgoing_review_primary_user_id(), outgoing_review_alternate_user_id()]);
    foreach ($eligible as $id) {
        if (!in_array($id, $exclude, true)) {
            return ['assignee' => $id, 'notify' => [$id]];
        }
    }
    $pool = outgoing_review_user_ids($exclude);
    return $pool === [] ? null : ['assignee' => 0, 'notify' => $pool];
}

function outgoing_user_can_review(array $user, array $corr): bool
{
    if ($t = appr_switch_for($corr)) {
        return appr_with($t, static fn (): bool => outgoing_user_can_review($user, $corr));
    }
    if (($corr['type'] ?? '') !== appr_p() || outgoing_approval_status_of($corr) !== 'pending_review') {
        return false;
    }
    if (correspondence_is_cancelled($corr)) {
        return false;
    }
    $uid = (int) ($user['id'] ?? 0);
    if ($uid <= 0
        || $uid === (int) ($corr['created_by'] ?? 0)
        || $uid === (int) ($corr['issue_approval_requested_by'] ?? 0)
    ) {
        return false;
    }
    $assignee = (int) ($corr['issue_approval_assignee_id'] ?? 0);
    return ($assignee > 0 ? $assignee === $uid : outgoing_user_is_reviewer($user))
        || appr_user_is_admin($user);
}

/** هل المستخدم مراجع لمعاملة قيد المراجعة لكنه محجوب بفصل المهام (منشئ/طالب)؟ */
function outgoing_user_review_blocked_by_sod(array $user, array $corr): bool
{
    if ($t = appr_switch_for($corr)) {
        return appr_with($t, static fn (): bool => outgoing_user_review_blocked_by_sod($user, $corr));
    }
    if (outgoing_approval_status_of($corr) !== 'pending_review') {
        return false;
    }
    $uid = (int) ($user['id'] ?? 0);
    if ($uid <= 0 || ($uid !== (int) ($corr['created_by'] ?? 0) && $uid !== (int) ($corr['issue_approval_requested_by'] ?? 0))) {
        return false;
    }
    $assignee = (int) ($corr['issue_approval_assignee_id'] ?? 0);
    return $assignee > 0 ? $assignee === $uid : outgoing_user_is_reviewer($user);
}

function outgoing_user_may_see_review_queue(?array $user = null): bool
{
    if (!isset($GLOBALS['_dms_appr_p'])) {
        return appr_any(static fn (): bool => outgoing_user_may_see_review_queue($user));
    }
    $user = $user ?? (function_exists('current_user') ? current_user() : null);
    if ($user === null) {
        return false;
    }
    $uid = (int) ($user['id'] ?? 0);
    if ($uid <= 0) {
        return false;
    }
    if (!outgoing_review_enabled() && !appr_user_has_assigned_pending($uid, ['pending_review'])) {
        return false;
    }
    return outgoing_user_is_reviewer($user)
        || ($uid > 0 && ($uid === outgoing_review_primary_user_id() || $uid === outgoing_review_alternate_user_id()))
        || appr_user_has_assigned_pending($uid, ['pending_review']);
}

/**
 * شروط SQL للصادر بانتظار مراجعة المستخدم الحالي.
 *
 * @return list<string>
 */
function outgoing_review_awaiting_sql(?array $user = null, string $alias = 'c'): array
{
    if (!isset($GLOBALS['_dms_appr_p'])) {
        return appr_sql_any(static fn (): array => outgoing_review_awaiting_sql($user, $alias));
    }
    ensure_outgoing_approval_schema();
    $user = $user ?? (function_exists('current_user') ? current_user() : null);
    $uid = (int) ($user['id'] ?? 0);
    if ($uid <= 0 || !outgoing_user_may_see_review_queue($user)) {
        return ['1=0'];
    }
    $who = "{$alias}.issue_approval_assignee_id = {$uid}";
    if (outgoing_user_is_reviewer($user)) {
        $who = "({$who} OR IFNULL({$alias}.issue_approval_assignee_id, 0) = 0)";
    }
    return [
        "{$alias}.type = '" . appr_p() . "'",
        "{$alias}.issue_approval_status = 'pending_review'",
        "{$alias}.created_by <> {$uid}",
        "IFNULL({$alias}.issue_approval_requested_by, 0) <> {$uid}",
        $who,
    ];
}

function outgoing_approval_status_of(array $corr): string
{
    return trim((string) ($corr['issue_approval_status'] ?? ''));
}

/**
 * داخل دورة المراجعة/الاعتماد (أو صادر معتمد لم يُرسل بعد) يقتصر التعديل على منشئ المعاملة
 * ومدير النظام (لتحريكها إن تعطّل سيرها)؛ المراجع والمعتمد يعيدانها بملاحظات ولا يعدّلان.
 * الصادر بانتظار المراجعة، أو بعد موافقة المراجع، يبقى للعرض على المعد ويصبح نهائياً. مدير النظام يستثنى إن تعطّل السير.
 */
function corr_approval_edit_locked(array $corr): bool
{
    $s = outgoing_approval_status_of($corr);
    if (in_array($s, ['pending_review', 'returned', 'pending_copy', 'pending_sg', 'pending_deputy', 'pending_alternate', 'rejected'], true)) {
        return true;
    }
    return ($corr['type'] ?? '') === 'outgoing' && in_array($s, ['approved', 'with_comms'], true)
        && !in_array((string) ($corr['status'] ?? ''), ['sent', 'delivered', 'closed'], true);
}

function corr_user_can_edit_in_cycle(array $user, array $corr): bool
{
    if (function_exists('outgoing_prep_is_superseded') && outgoing_prep_is_superseded($corr)) {
        return false;
    }
    if (function_exists('outgoing_prep_is_open') && outgoing_prep_is_open($corr)) {
        if (($user['role'] ?? '') === 'admin') {
            return true;
        }
        return function_exists('outgoing_prep_is_draft')
            && outgoing_prep_is_draft($corr)
            && function_exists('outgoing_user_is_prep_creator')
            && outgoing_user_is_prep_creator($user, $corr);
    }
    if (($corr['type'] ?? '') === 'outgoing' && (
        outgoing_approval_status_of($corr) === 'pending_review'
        || outgoing_reviewer_has_passed($corr)
    )) {
        return ($user['role'] ?? '') === 'admin';
    }
    return !corr_approval_edit_locked($corr)
        || ($user['role'] ?? '') === 'admin'
        || ((int) ($user['id'] ?? 0) > 0 && (int) ($user['id'] ?? 0) === (int) ($corr['created_by'] ?? 0));
}

/** المراجع طابق المعاملة ولم يُعدها ولم يرفضها المعتمد: المحتوى نهائي على المعد. */
function outgoing_reviewer_has_passed(array $corr): bool
{
    if (($corr['type'] ?? '') !== 'outgoing') {
        return false;
    }
    if ((int) ($corr['issue_review_by'] ?? 0) <= 0) {
        return false;
    }

    return !in_array(outgoing_approval_status_of($corr), ['', 'returned', 'pending_review', 'rejected'], true);
}

function corr_edit_cycle_block_message(array $corr): string
{
    if (($corr['type'] ?? '') === 'outgoing' && outgoing_reviewer_has_passed($corr)) {
        return __('issue_review.edit_final');
    }
    if (($corr['type'] ?? '') === 'outgoing' && outgoing_approval_status_of($corr) === 'pending_review') {
        return __('issue_review.edit_locked');
    }

    return __('issue_appr.edit_creator_only');
}

function corr_assert_can_edit_in_cycle(array $user, array $corr): void
{
    if (!corr_user_can_edit_in_cycle($user, $corr)) {
        throw new RuntimeException(corr_edit_cycle_block_message($corr));
    }
}

/** جهات تسجيل الوارد: تعدّل بياناته بعد اعتماد التوجيه مع منشئه */
function incoming_registry_role(array $user): bool
{
    return in_array($user['role'] ?? '', ['admin', 'communications', 'archive'], true);
}

/** تعديل بيانات تسجيل الوارد — القسم المستلم وأقسام الإحالة لا تعدّلها؛ المغلقة تُعاد فتحها أولاً */
function incoming_user_can_edit_registration(array $user, array $corr): bool
{
    if (($corr['type'] ?? '') !== 'incoming') {
        return true;
    }
    if (correspondence_is_closed($corr)) {
        return false;
    }
    if (corr_approval_edit_locked($corr)) {
        return corr_user_can_edit_in_cycle($user, $corr);
    }
    $uid = (int) ($user['id'] ?? 0);
    return incoming_registry_role($user) || ($uid > 0 && $uid === (int) ($corr['created_by'] ?? 0));
}

function incoming_assert_can_edit_registration(array $user, array $corr): void
{
    if (incoming_user_can_edit_registration($user, $corr)) {
        return;
    }
    if (($corr['type'] ?? '') === 'incoming' && correspondence_is_closed($corr)) {
        throw new RuntimeException(__('in_edit.closed'));
    }
    corr_assert_can_edit_in_cycle($user, $corr);
    throw new RuntimeException(__('in_edit.registry_only'));
}

function outgoing_approval_label(string $status): string
{
    return match ($status) {
        'pending_review' => appr__('issue_review.status_pending'),
        'returned' => appr__('issue_review.status_returned'),
        'pending_copy' => __('issue_appr.status_pending_copy'),
        'pending_sg' => outgoing_appr_text('issue_appr.status_pending_sg'),
        'pending_deputy' => outgoing_appr_text('issue_appr.status_pending_deputy'),
        'pending_alternate' => outgoing_appr_text('issue_appr.status_pending_alternate'),
        'approved' => appr_p() === 'outgoing' ? __('issue_appr.status_comms') : appr__('issue_appr.status_approved'),
        'with_comms' => __('issue_appr.status_comms'),
        'rejected' => appr__('issue_appr.status_rejected'),
        'filed' => __('in_issue_appr.status_filed'),
        'pending_desk' => __('in_issue_appr.desk_status'),
        default => appr__('issue_appr.status_none'),
    };
}

/** @return array{name_ar?:string,name_en?:string,job_title?:string,job_title_en?:string}|null */
function outgoing_badge_user_row(int $userId): ?array
{
    static $cache = [];
    if ($userId <= 0) {
        return null;
    }
    if (!array_key_exists($userId, $cache)) {
        try {
            $stmt = db()->prepare('SELECT name_ar, name_en, job_title, job_title_en FROM `tblusers` WHERE id = ? LIMIT 1');
            $stmt->execute([$userId]);
            $cache[$userId] = $stmt->fetch() ?: null;
        } catch (Throwable $e) {
            $cache[$userId] = null;
        }
    }
    return $cache[$userId];
}

/** شارة مختصرة لقوائم الصادر — تظهر دائماً حتى مع إيقاف التفعيل (للسجل التاريخي) */
function outgoing_approval_badge(array $corr): string
{
    if (($corr['type'] ?? '') !== 'outgoing') {
        return '';
    }
    $s = outgoing_approval_status_of($corr);
    $enabled = outgoing_approval_enabled();
    $class = match ($s) {
        'approved', 'with_comms' => 'text-bg-success',
        'rejected' => 'text-bg-danger',
        'pending_review', 'pending_copy', 'pending_sg', 'pending_deputy', 'pending_alternate' => 'text-bg-warning',
        'returned' => 'text-bg-info',
        default => 'text-bg-secondary',
    };
    if ($s === '' && !$enabled) {
        $short = appr__('issue_appr.list_not_required');
        $title = appr__('issue_appr.status_not_required');
    } else {
        $short = match ($s) {
            'pending_review' => appr__('issue_review.list_pending'),
            'returned' => appr__('issue_review.list_returned'),
            'pending_copy' => __('issue_appr.list_pending_copy'),
            'pending_sg' => outgoing_appr_text('issue_appr.list_pending_sg'),
            'pending_deputy' => outgoing_appr_text('issue_appr.list_pending_deputy'),
            'pending_alternate' => outgoing_appr_text('issue_appr.list_pending_alternate'),
            'approved', 'with_comms' => __('issue_appr.list_comms'),
            'rejected' => appr__('issue_appr.list_rejected'),
            default => appr__('issue_appr.list_none'),
        };
        $title = outgoing_approval_label($s);
    }

    $personStage = $s !== '' ? $s : ($enabled ? 'none_waiting' : 'not_required');
    return appr_badge_render($class, $title, $short, $corr, $personStage);
}

/**
 * صاحب المرحلة الحالية لسطر القائمة: المعيَّن للمراجعة/الاعتماد، والمنشئ عند الإعادة أو قبل طلب الاعتماد،
 * ومن بتّ بعد القرار. يُستدعى داخل سياق النوع (appr_with) لتصحّ إعدادات المعتمدين.
 * none_waiting = لم يُطلب الاعتماد بعد وهو لازم؛ not_required = لا يلزم اعتماد.
 *
 * @return array{id:int, tip:string}
 */
function appr_badge_stage_user(array $corr, string $s): array
{
    $assignee = (int) ($corr['issue_approval_assignee_id'] ?? 0);
    $creator = (int) ($corr['created_by'] ?? 0);
    $waiting = __('appr_list.waiting_at');
    switch ($s) {
        case 'pending_review':
            return ['id' => $assignee, 'tip' => $waiting];
        case 'pending_sg':
        case 'pending_deputy':
        case 'pending_alternate':
            if ($assignee <= 0) {
                $assignee = match ($s) {
                    'pending_sg' => outgoing_approval_sg_user_id(),
                    'pending_deputy' => outgoing_approval_deputy_user_id(),
                    default => outgoing_approval_alternate_user_id(),
                };
            }
            return ['id' => $assignee, 'tip' => $waiting];
        case 'pending_copy':
        case 'returned':
        case 'none_waiting':
        case 'pending_desk':
            return ['id' => $creator, 'tip' => $waiting];
        case 'not_required':
            return ['id' => $creator, 'tip' => __('appr_list.created_by')];
        case 'approved':
        case 'with_comms':
            if (($corr['type'] ?? '') === 'outgoing' || $s === 'with_comms') {
                $id = (int) ($corr['issue_approval_assignee_id'] ?? 0);
                if ($id <= 0 && function_exists('outgoing_dispatch_user_ids')) {
                    $ids = outgoing_dispatch_user_ids();
                    if (count($ids) === 1) {
                        $id = $ids[0];
                    }
                }
                return ['id' => $id, 'tip' => __('issue_appr.header_with_comms')];
            }
            return ['id' => (int) ($corr['issue_approval_decided_by'] ?? 0), 'tip' => __('appr_list.decided_by')];
        case 'rejected':
        case 'filed':
            return ['id' => (int) ($corr['issue_approval_decided_by'] ?? 0), 'tip' => __('appr_list.decided_by')];
    }
    return ['id' => 0, 'tip' => ''];
}

/** سطر تحت شارة الاعتماد في القوائم: اسم صاحب المرحلة ثم وظيفته (مع تلميح: لدى من الآن / صاحب القرار / المنشئ) */
function appr_badge_person_html(array $corr, string $s): string
{
    $stage = appr_badge_stage_user($corr, $s);
    $who = outgoing_badge_user_row($stage['id']);
    if ($who === null) {
        $showRole = $s === 'pending_review'
            || $s === 'with_comms'
            || ($s === 'approved' && ($corr['type'] ?? '') === 'outgoing');
        return $showRole
            ? '<span title="' . e($stage['tip']) . '">' . e(__('role.communications')) . '</span>'
            : '';
    }
    $name = function_exists('user_display_name') ? user_display_name($who) : trim((string) ($who['name_ar'] ?? ''));
    if ($name === '') {
        return '';
    }
    $job = user_job_title($who);
    if ($job === '' && in_array($s, ['approved', 'rejected'], true) && $stage['id'] === outgoing_approval_alternate_user_id()) {
        $job = outgoing_approver_alternate_title();
    }
    return '<span title="' . e($stage['tip']) . '">' . e($name) . '</span>'
        . ($job !== '' ? '<div class="lh-sm">' . e($job) . '</div>' : '');
}

/** شارة اعتماد أي معاملة حسب نوعها (للقوائم الموحّدة) */
function corr_approval_badge(array $corr): string
{
    return match ($corr['type'] ?? '') {
        'outgoing' => outgoing_approval_badge($corr),
        'incoming' => incoming_approval_badge($corr),
        'internal' => internal_approval_badge($corr),
        default => '',
    };
}

/** شارة الاعتماد مع صاحب المرحلة؛ مراحل الاعتماد تُختصر إلى «بانتظار الاعتماد» ما دام الاسم والوظيفة ظاهرين تحتها */
function appr_badge_render(string $class, string $title, string $short, array $corr, string $s): string
{
    $line = appr_badge_person_html($corr, $s);
    if ($line !== '' && in_array($s, ['pending_sg', 'pending_deputy', 'pending_alternate'], true)) {
        $short = __('appr_list.pending_approval');
    }
    $badge = '<span class="badge ' . $class . '" title="' . e($title) . '">' . e($short) . '</span>';
    if ($line === '') {
        return $badge;
    }
    return '<div class="outgoing-appr-cell">' . $badge
        . '<div class="small text-muted mt-1 lh-sm">' . $line . '</div></div>';
}

function incoming_approval_in_scope(array $corr): bool
{
    if (setting('incoming_approval_enabled', '0') !== '1') {
        return false;
    }
    if (setting('incoming_approval_scope_all', '0') === '1') {
        return true;
    }
    // «للاطلاع» يُلزم المرور بدورة الاعتماد كاملة
    if (!empty($corr['id']) && function_exists('corr_cc_has_pending') && corr_cc_has_pending((int) $corr['id'])) {
        return true;
    }
    foreach (['confidentiality' => 'confidential', 'priority' => 'urgent'] as $field => $default) {
        $codes = array_filter(explode(',', (string) setting('incoming_approval_' . $field, $default)));
        if (in_array((string) ($corr[$field] ?? ''), $codes, true)) {
            return true;
        }
    }
    return false;
}

function incoming_approval_badge(array $corr): string
{
    if (($corr['type'] ?? '') !== 'incoming') {
        return '';
    }
    $s = outgoing_approval_status_of($corr);
    if ($s !== '') {
        return appr_with('incoming', static function () use ($s, $corr): string {
            $class = match ($s) {
                'approved' => 'text-bg-success',
                'filed', 'pending_desk' => 'text-bg-secondary',
                'returned' => 'text-bg-info',
                default => 'text-bg-warning',
            };
            $short = match ($s) {
                'pending_review' => appr__('issue_review.list_pending'),
                'returned' => appr__('issue_review.list_returned'),
                'pending_sg' => outgoing_appr_text('issue_appr.list_pending_sg'),
                'pending_deputy' => outgoing_appr_text('issue_appr.list_pending_deputy'),
                'pending_alternate' => outgoing_appr_text('issue_appr.list_pending_alternate'),
                'approved' => appr__('issue_appr.list_approved'),
                'filed' => __('in_issue_appr.list_filed'),
                'pending_desk' => __('in_issue_appr.desk_list'),
                default => $s,
            };
            return appr_badge_render($class, outgoing_approval_label($s), $short, $corr, $s);
        });
    }
    if (incoming_approval_in_scope($corr)) {
        return appr_badge_render('text-bg-warning', __('incoming_appr.status_in_scope'), __('incoming_appr.list_in_scope'), $corr, 'none_waiting');
    }
    $title = setting('incoming_approval_enabled', '0') === '1'
        ? __('incoming_appr.status_out_of_scope')
        : __('incoming_appr.status_disabled');
    return appr_badge_render('text-bg-secondary', $title, __('issue_appr.list_not_required'), $corr, 'not_required');
}

function internal_approval_badge(array $corr): string
{
    if (($corr['type'] ?? '') !== 'internal') {
        return '';
    }
    $s = outgoing_approval_status_of($corr);
    if ($s === '') {
        return appr_badge_render('text-bg-secondary', __('issue_appr.list_not_required'), __('issue_appr.list_not_required'), $corr, 'not_required');
    }
    return appr_with('internal', static function () use ($s, $corr): string {
        $class = match ($s) {
            'approved' => 'text-bg-success',
            'returned' => 'text-bg-info',
            default => 'text-bg-warning',
        };
        $short = match ($s) {
            'pending_review' => appr__('issue_review.list_pending'),
            'returned' => appr__('issue_review.list_returned'),
            'pending_sg' => outgoing_appr_text('issue_appr.list_pending_sg'),
            'pending_deputy' => outgoing_appr_text('issue_appr.list_pending_deputy'),
            'pending_alternate' => outgoing_appr_text('issue_appr.list_pending_alternate'),
            'approved' => appr__('issue_appr.list_approved'),
            default => $s,
        };
        return appr_badge_render($class, outgoing_approval_label($s), $short, $corr, $s);
    });
}

/** حالات الوارد المحجوز عن الجهة حتى اعتماد التوجيه */
function incoming_approval_held_statuses(): array
{
    return ['pending_review', 'returned', 'pending_sg', 'pending_deputy', 'pending_alternate'];
}

/** حالات لا تراها الجهة: دورة التوجيه، أو التوقف بعد الحفظ قبل زر إكمال المسار */
function incoming_department_hidden_statuses(): array
{
    return array_merge(incoming_approval_held_statuses(), ['pending_desk']);
}

function incoming_is_at_desk(array $corr): bool
{
    return ($corr['type'] ?? '') === 'incoming'
        && outgoing_approval_status_of($corr) === 'pending_desk';
}

function incoming_approval_is_held(array $corr): bool
{
    return ($corr['type'] ?? '') === 'incoming'
        && in_array(outgoing_approval_status_of($corr), incoming_department_hidden_statuses(), true);
}

/** وارد أو معاملة داخلية محجوزة حتى الاعتماد (لم تصل بعد إلى الجهة) */
function corr_approval_is_held(array $corr): bool
{
    return incoming_approval_is_held($corr)
        || (function_exists('internal_approval_is_held') && internal_approval_is_held($corr));
}

/** نص منع إجراء على معاملة محجوزة حسب نوعها (in_issue_appr.* أو int_issue_appr.*) */
function corr_held_text(array $corr, string $key): string
{
    return __((($corr['type'] ?? '') === 'internal' ? 'int_' : 'in_') . 'issue_appr.' . $key);
}

/** شرط SQL: استبعاد الوارد المحجوز (للنطاق حسب القسم) */
function incoming_approval_not_held_sql(string $alias = 'c'): string
{
    $in = "'" . implode("','", incoming_department_hidden_statuses()) . "'";
    return "NOT ({$alias}.type = 'incoming' AND IFNULL({$alias}.issue_approval_status, '') IN ({$in}))";
}

/**
 * بعد حفظ وارد جديد: يبقى في صفحته بلا إشعار لأحد حتى يُضغط «إكمال المسار».
 */
function incoming_park_at_desk(int $corrId): void
{
    if ($corrId <= 0) {
        return;
    }
    ensure_outgoing_approval_schema();
    db()->prepare(
        "UPDATE `tblcorrespondence` SET issue_approval_status = 'pending_desk' WHERE id = ? AND type = 'incoming'"
    )->execute([$corrId]);
}

function incoming_user_can_release_desk(array $user, array $corr): bool
{
    if (!incoming_is_at_desk($corr) || correspondence_is_cancelled($corr)) {
        return false;
    }
    $uid = (int) ($user['id'] ?? 0);
    return incoming_registry_role($user) || ($uid > 0 && $uid === (int) ($corr['created_by'] ?? 0));
}

/**
 * إكمال المسار: يبدأ المراجعة والاعتماد إن كان الوارد ضمنهما، وإلا تصل المعاملة إلى الجهة.
 *
 * @return string حالة التوجيه بعد الإكمال، أو department
 */
function incoming_release_desk(int $corrId, array $user): string
{
    $corr = get_correspondence($corrId);
    if (!$corr || ($corr['type'] ?? '') !== 'incoming') {
        throw new RuntimeException(__('corr.not_found'));
    }
    if (!incoming_user_can_release_desk($user, $corr)) {
        throw new RuntimeException(__('auth.forbidden'));
    }
    if (incoming_approval_in_scope($corr)) {
        try {
            return appr_with('incoming', static fn (): string => outgoing_approval_start($corr, $user, true));
        } catch (RuntimeException $e) {
            // لا مراجع ولا معتمد مؤهَّل — تُحال للجهة كما لو كان الاعتماد معطّلاً
        }
    }
    $cleared = db()->prepare(
        "UPDATE `tblcorrespondence` SET issue_approval_status = NULL
         WHERE id = ? AND type = 'incoming' AND issue_approval_status = 'pending_desk'"
    );
    $cleared->execute([$corrId]);
    if ($cleared->rowCount() === 0) {
        throw new RuntimeException(__('in_issue_appr.desk_already'));
    }
    if (function_exists('corr_cc_cancel')) {
        corr_cc_cancel($corrId, $user);
    }
    $deptId = (int) ($corr['department_id'] ?? 0);
    if ($deptId > 0 && function_exists('dms_notify_incoming_assigned')) {
        dms_notify_incoming_assigned($corrId, $corr, $deptId, $user, 'new');
    }
    log_activity((int) $user['id'], $corrId, 'incoming_desk_release', __('activity.action.incoming_desk_release'));
    return 'department';
}

/**
 * عند تسجيل وارد جديد: إن كان ضمن نطاق الاعتماد ومسار الوارد مضبوطاً يُحجز للمراجعة/الاعتماد
 * بدل إشعار الجهة. الحفظ الحالي يتوقف أولاً عند المكتب عبر incoming_park_at_desk.
 * @return bool true = حُجز (لا تُشعَر الجهة الآن)
 */
function incoming_approval_hold_on_create(int $corrId, array $user): bool
{
    ensure_outgoing_approval_schema();
    $corr = get_correspondence($corrId);
    if (!$corr || ($corr['type'] ?? '') !== 'incoming' || !incoming_approval_in_scope($corr)) {
        return false;
    }
    return appr_with('incoming', static function () use ($corr, $user): bool {
        if (!outgoing_approval_is_configured()) {
            return false;
        }
        try {
            outgoing_approval_start($corr, $user);
            return true;
        } catch (RuntimeException $e) {
            // لا مراجع ولا معتمد مؤهَّل — يُحال مباشرة كما لو كان الاعتماد معطّلاً
            return false;
        }
    });
}

/**
 * تغيير القسم المراد إرسال الوارد إليه أثناء الحجز. يعيد true إن تغيّر فعلاً.
 */
function incoming_approval_set_department(int $corrId, array $user, int $newDeptId, int $currentDeptId): bool
{
    if ($newDeptId <= 0 || $newDeptId === $currentDeptId) {
        return false;
    }
    $valid = array_map(static fn (array $d): int => (int) $d['id'], departments_list());
    if (!in_array($newDeptId, $valid, true)) {
        throw new InvalidArgumentException(__('in_issue_appr.dept_required'));
    }
    db()->prepare('UPDATE `tblcorrespondence` SET department_id = ? WHERE id = ? AND type = ?')
        ->execute([$newDeptId, $corrId, 'incoming']);
    log_activity((int) $user['id'], $corrId, 'incoming_route_change', __('activity.action.incoming_route_change'));
    return true;
}

/**
 * قرار معتمد الوارد: اعتماد التوجيه (مع تأشيرة اختيارية)، أو الحفظ للعلم، أو الإعادة للمنشئ بملاحظات
 * (تغيير القسم من صلاحية المنشئ فقط، قبل إعادة الإرسال).
 *
 * @param 'approve'|'file'|'return' $decision
 */
function incoming_approval_decide(int $corrId, array $user, string $decision, string $note = ''): void
{
    ensure_outgoing_approval_schema();
    $corr = get_correspondence($corrId);
    if (!$corr || ($corr['type'] ?? '') !== 'incoming') {
        throw new RuntimeException(__('corr.not_found'));
    }
    if (!in_array($decision, ['approve', 'file', 'return'], true)) {
        throw new InvalidArgumentException(__('profile.invalid_action'));
    }
    appr_with('incoming', static function () use ($corrId, $corr, $user, $decision, $note): void {
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
            throw new InvalidArgumentException(__('in_issue_appr.return_note_required'));
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
        $deptId = (int) ($corr['department_id'] ?? 0);
        if ($decision === 'approve' && $deptId <= 0) {
            throw new InvalidArgumentException(__('in_issue_appr.dept_required'));
        }

        $status = match ($decision) {
            'file' => 'filed',
            'return' => 'returned',
            default => 'approved',
        };
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
        if ($decision === 'file') {
            $oldStatus = (string) ($corr['status'] ?? '');
            db()->prepare("UPDATE `tblcorrespondence` SET status = 'closed' WHERE id = ?")->execute([$corrId]);
            if ($oldStatus !== 'closed') {
                log_activity($uid, $corrId, 'status', status_label($oldStatus) . ' ← ' . status_label('closed'));
            }
        }

        $action = 'incoming_approval_' . $decision;
        $desc = __('activity.action.' . $action) . ($note !== '' ? ': ' . $note : '');
        log_activity($uid, $corrId, $action, $desc);

        $fresh = get_correspondence($corrId) ?: $corr;
        if ($decision === 'approve' && function_exists('dms_notify_incoming_assigned')) {
            dms_notify_incoming_assigned($corrId, $fresh, $deptId, $user, 'new');
        }
        if ($decision === 'approve' && function_exists('corr_cc_release')) {
            corr_cc_release($corrId, $user);
        } elseif ($decision === 'file' && function_exists('corr_cc_cancel')) {
            corr_cc_cancel($corrId, $user);
        }

        $notifyIds = array_values(array_unique(array_filter([
            (int) ($corr['created_by'] ?? 0),
            (int) ($corr['issue_approval_requested_by'] ?? 0),
        ], static fn (int $id): bool => $id > 0 && $id !== $uid)));
        if ($notifyIds !== [] && function_exists('dms_notify_users')) {
            $ref = format_ref_number($corr['ref_number'] ?? $corrId);
            $subjectLine = trim((string) ($corr['subject'] ?? ''));
            $key = match ($decision) {
                'file' => 'filed',
                'return' => 'returned',
                default => 'approved',
            };
            dms_notify_users($notifyIds, [
                'type' => 'outgoing_issue_approval',
                'title' => __('in_issue_appr.notify_' . $key . '_title'),
                'body' => str_replace(':ref', $ref, __('in_issue_appr.notify_' . $key . '_body')),
                'link' => 'incoming/view.php?id=' . $corrId,
                'correspondence_id' => $corrId,
                'department_id' => $deptId ?: null,
                'dedupe_key' => 'in_appr_dec_' . $corrId . '_' . $key . '_' . time(),
                'email' => true,
                'email_intro' => __('in_issue_appr.email_' . $key . '_intro'),
                'email_details' => trim($ref . ($subjectLine !== '' ? ' — ' . $subjectLine : '') . ($note !== '' ? ' — ' . $note : '')),
                'email_rows' => outgoing_approval_email_rows($fresh),
                'email_cta' => __('notify.email_view_correspondence'),
            ]);
        }
    });
}

function outgoing_approval_is_pending(array $corr): bool
{
    $s = outgoing_approval_status_of($corr);
    return $s === 'pending_review' || $s === 'pending_sg' || $s === 'pending_deputy' || $s === 'pending_alternate';
}

function outgoing_approval_is_approved(array $corr): bool
{
    return outgoing_approval_status_of($corr) === 'approved';
}

/**
 * هل يُسمح بالإصدار/الإرسال/الطباعة الرسمية؟
 * إذا المسار غير مفعّل أو غير مضبوط → مسموح (لا يعطّل العمل).
 */
function outgoing_can_issue(array $corr): bool
{
    if (($corr['type'] ?? '') !== 'outgoing') {
        return true;
    }
    if (function_exists('outgoing_prep_is_superseded') && outgoing_prep_is_superseded($corr)) {
        return false;
    }
    if (function_exists('outgoing_prep_is_open') && outgoing_prep_is_open($corr)) {
        return false;
    }
    $s = outgoing_approval_status_of($corr);
    if ($s === 'with_comms') {
        return true;
    }
    if (in_array($s, ['pending_review', 'returned', 'pending_copy', 'pending_sg', 'pending_deputy', 'pending_alternate', 'rejected'], true)) {
        return false;
    }
    if (setting('outgoing_review_enabled', '0') === '1'
        && $s === ''
        && trim((string) ($corr['issue_reviewed_at'] ?? '')) === ''
    ) {
        return false;
    }
    if (!outgoing_approval_is_configured()) {
        return true;
    }
    return outgoing_approval_is_approved($corr);
}

function outgoing_approval_block_reason(array $corr): string
{
    if (outgoing_can_issue($corr)) {
        return '';
    }
    if (function_exists('outgoing_prep_is_superseded') && outgoing_prep_is_superseded($corr)) {
        return __('out_prep.superseded_hint');
    }
    if (function_exists('outgoing_prep_is_open') && outgoing_prep_is_open($corr)) {
        return __('out_prep.print_blocked');
    }
    $s = outgoing_approval_status_of($corr);
    if ($s === 'rejected') {
        if (($corr['type'] ?? '') === 'outgoing' && function_exists('outgoing_review_enabled') && outgoing_review_enabled()) {
            return __('issue_appr.blocked_rejected_review');
        }
        return appr__('issue_appr.blocked_rejected');
    }
    if ($s === 'returned') {
        return appr__('issue_review.blocked_returned');
    }
    if ($s === 'pending_review') {
        return appr__('issue_review.blocked_pending');
    }
    if ($s === 'pending_copy') {
        return __('out_prep.copy_before_approver');
    }
    if (outgoing_approval_is_pending($corr)) {
        return appr__('issue_appr.blocked_pending');
    }
    return appr__('issue_appr.blocked_need_request');
}

/** حظر استلام الصادر / تأكيد الحضور قبل اعتماد الإصدار */
function outgoing_approval_block_post_issue(array $corr): string
{
    if (outgoing_can_issue($corr)) {
        return '';
    }
    $s = outgoing_approval_status_of($corr);
    if ($s === 'rejected') {
        return appr__('issue_appr.blocked_rejected');
    }
    if ($s === 'returned') {
        return appr__('issue_review.blocked_returned');
    }
    if (outgoing_approval_is_pending($corr)) {
        return appr__('issue_appr.blocked_post_issue');
    }
    return appr__('issue_appr.blocked_post_issue_need_request');
}

function outgoing_approval_active_users(): array
{
    if (function_exists('ensure_users_profile_schema')) {
        ensure_users_profile_schema();
    }
    try {
        return db()->query(
            "SELECT id, name_ar, name_en, title_prefix, email, role, job_title, job_title_en, photo_path
             FROM `tblusers`
             WHERE is_active = 1
             ORDER BY sort_order, name_ar, name_en, id"
        )->fetchAll() ?: [];
    } catch (Throwable $e) {
        try {
            return db()->query(
                "SELECT id, name_ar, name_en, title_prefix, email, role, job_title, job_title_en
                 FROM `tblusers`
                 WHERE is_active = 1
                 ORDER BY sort_order, name_ar, name_en, id"
            )->fetchAll() ?: [];
        } catch (Throwable $e2) {
            return [];
        }
    }
}

function outgoing_user_can_request_approval(array $user, array $corr): bool
{
    if ($t = appr_switch_for($corr)) {
        return appr_with($t, static fn (): bool => outgoing_user_can_request_approval($user, $corr));
    }
    if (($corr['type'] ?? '') !== appr_p() || correspondence_is_cancelled($corr)) {
        return false;
    }
    if (function_exists('can_edit_correspondence')) {
        if (!can_edit_correspondence($user)) {
            return false;
        }
    } elseif (!in_array(($user['role'] ?? ''), ['admin', 'archive', 'department'], true)) {
        return false;
    }
    if (!outgoing_issue_flow_active()) {
        return false;
    }
    $s = outgoing_approval_status_of($corr);
    if (appr_p() === 'outgoing'
        && outgoing_review_enabled()
        && !outgoing_approval_enabled()
        && $s === ''
        && trim((string) ($corr['issue_reviewed_at'] ?? '')) !== ''
    ) {
        return false;
    }
    if (($s === 'returned' || $s === 'rejected') && !corr_user_can_edit_in_cycle($user, $corr)) {
        return false;
    }
    if (appr_p() === 'incoming') {
        return $s === 'returned' || ($s === '' && incoming_approval_in_scope($corr));
    }
    if (appr_p() === 'internal') {
        return $s === 'returned';
    }
    return $s === '' || $s === 'rejected' || $s === 'returned';
}

function outgoing_user_can_decide_approval(array $user, array $corr): bool
{
    if ($t = appr_switch_for($corr)) {
        return appr_with($t, static fn (): bool => outgoing_user_can_decide_approval($user, $corr));
    }
    if (!outgoing_approval_is_pending($corr)) {
        return false;
    }
    $uid = (int) ($user['id'] ?? 0);
    if ($uid <= 0) {
        return false;
    }

    $s = outgoing_approval_status_of($corr);
    if ($s === 'pending_review') {
        return false;
    }
    $isOverride = false;
    if (!appr_user_matches_stage($uid, $corr)) {
        if (!appr_user_is_admin($user)) {
            return false;
        }
        $isOverride = true;
    }

    // فصل الصلاحيات: من أنشأ أو طلب الموافقة لا يبتّ في مرحلتي الأول والتصعيد.
    // مرحلة البديل مسار طوارئ (سبب إلزامي) — يُسمح حتى لو كان المنشئ/الطالب هو البديل المعيَّن.
    if (($s !== 'pending_alternate' || $isOverride)
        && (
            $uid === (int) ($corr['created_by'] ?? 0)
            || $uid === (int) ($corr['issue_approval_requested_by'] ?? 0)
            || $uid === (int) ($corr['issue_review_by'] ?? 0)
        )
    ) {
        return false;
    }

    return true;
}

function appr_user_is_admin(?array $user): bool
{
    return $user !== null && ($user['role'] ?? '') === 'admin';
}

/** هل المستخدم هو المعتمد المعيَّن للمرحلة الحالية (دون تدخّل مدير النظام)؟ */
function appr_user_matches_stage(int $uid, array $corr): bool
{
    if ($uid <= 0) {
        return false;
    }
    $s = outgoing_approval_status_of($corr);
    $assignee = (int) ($corr['issue_approval_assignee_id'] ?? 0);
    $stageStatuses = ['pending_sg', 'pending_deputy', 'pending_alternate'];
    // بعد نقل مدير النظام إلى مستخدم محدد: يبتّ المعيَّن الجديد وحده
    if (!empty($corr['issue_approval_reassigned_at'])) {
        return $assignee > 0 && $assignee === $uid && in_array($s, $stageStatuses, true);
    }
    if ($s === 'pending_sg' && $uid === outgoing_approval_sg_user_id()) {
        return true;
    }
    if ($s === 'pending_deputy' && $uid === outgoing_approval_deputy_user_id()) {
        return true;
    }
    if ($s === 'pending_alternate' && $uid === outgoing_approval_alternate_user_id()) {
        return true;
    }
    return $assignee > 0 && $assignee === $uid && in_array($s, $stageStatuses, true);
}

/** بتّ مدير النظام نيابةً عن المعتمد المعيَّن (معاملة متعطّلة) — سبب إلزامي */
function appr_is_admin_decide_override(array $user, array $corr): bool
{
    if ($t = appr_switch_for($corr)) {
        return appr_with($t, static fn (): bool => appr_is_admin_decide_override($user, $corr));
    }
    return appr_user_is_admin($user)
        && !appr_user_matches_stage((int) ($user['id'] ?? 0), $corr)
        && outgoing_user_can_decide_approval($user, $corr);
}

/** مراجعة مدير النظام نيابةً عن المراجع المعيَّن — سبب إلزامي */
function appr_is_admin_review_override(array $user, array $corr): bool
{
    if ($t = appr_switch_for($corr)) {
        return appr_with($t, static fn (): bool => appr_is_admin_review_override($user, $corr));
    }
    if (!appr_user_is_admin($user) || !outgoing_user_can_review($user, $corr)) {
        return false;
    }
    $assignee = (int) ($corr['issue_approval_assignee_id'] ?? 0);
    return $assignee > 0 ? $assignee !== (int) ($user['id'] ?? 0) : !outgoing_user_is_reviewer($user);
}

/**
 * سبب تدخّل مدير النظام: إلزامي ويُسبق بوسم يوضّح أنه بالنيابة.
 */
function appr_admin_override_note(string $note): string
{
    $note = trim($note);
    if ($note === '') {
        throw new InvalidArgumentException(__('issue_appr.admin_reason_required'));
    }
    $prefix = __('issue_appr.admin_override_prefix');
    if (!str_starts_with($note, $prefix)) {
        $note = $prefix . ' ' . $note;
    }
    return mb_substr($note, 0, 500);
}

/**
 * المرحلة التالية المتاحة لنقل معاملة متعطّلة (المعتمد الثاني ثم البديل).
 *
 * @return array{status:string, assignee:int}|null
 */
function appr_admin_next_stage(array $corr): ?array
{
    $s = outgoing_approval_status_of($corr);
    $sod = [
        (int) ($corr['created_by'] ?? 0),
        (int) ($corr['issue_approval_requested_by'] ?? 0),
        (int) ($corr['issue_review_by'] ?? 0),
    ];
    $candidates = [];
    if ($s === 'pending_sg') {
        $candidates['pending_deputy'] = outgoing_approval_deputy_escalation_user_id();
    }
    if ($s === 'pending_sg' || $s === 'pending_deputy') {
        $candidates['pending_alternate'] = outgoing_approval_alternate_escalation_user_id();
    }
    foreach ($candidates as $status => $id) {
        if ($id > 0 && outgoing_active_user_ids([$id]) !== []
            && ($status === 'pending_alternate' || !in_array($id, $sod, true))) {
            return ['status' => $status, 'assignee' => $id];
        }
    }
    return null;
}

/** هل يستطيع مدير النظام نقل المعاملة (مرحلة مراجعة أو اعتماد معلّقة)؟ */
function appr_admin_can_reassign(array $user, array $corr): bool
{
    return appr_user_is_admin($user)
        && outgoing_approval_is_pending($corr)
        && !correspondence_is_cancelled($corr);
}

/**
 * مستخدمون يمكن نقل المعاملة إليهم (نشطون، دون المنشئ/الطالب/المراجع).
 *
 * @return list<array{id:int,name:string}>
 */
function appr_admin_reassign_candidates(array $corr): array
{
    $exclude = array_filter([
        (int) ($corr['created_by'] ?? 0),
        (int) ($corr['issue_approval_requested_by'] ?? 0),
        (int) ($corr['issue_review_by'] ?? 0),
        (int) ($corr['issue_approval_assignee_id'] ?? 0),
    ]);
    $out = [];
    foreach (outgoing_approval_active_users() as $row) {
        $id = (int) ($row['id'] ?? 0);
        if ($id > 0 && !in_array($id, $exclude, true)) {
            $out[] = ['id' => $id, 'name' => user_display_name($row)];
        }
    }
    return $out;
}

/**
 * تدخّل مدير النظام في معاملة متعطّلة: نقلها للمرحلة التالية ($targetId = 0) أو لمستخدم محدد
 * يتولّى المرحلة الحالية. السبب إلزامي ويُسجَّل، وتُعاد مهلة المرحلة من الآن.
 */
function appr_admin_reassign(int $corrId, array $user, int $targetId, string $reason): void
{
    ensure_outgoing_approval_schema();
    $corr = get_correspondence($corrId);
    if (!$corr) {
        throw new RuntimeException(__('corr.not_found'));
    }
    if ($t = appr_switch_for($corr)) {
        appr_with($t, static fn () => appr_admin_reassign($corrId, $user, $targetId, $reason));
        return;
    }
    if (!appr_admin_can_reassign($user, $corr)) {
        throw new RuntimeException(__('auth.forbidden'));
    }
    $reason = appr_admin_override_note($reason);
    $s = outgoing_approval_status_of($corr);
    $prevId = (int) ($corr['issue_approval_assignee_id'] ?? 0);
    $now = date('Y-m-d H:i:s');

    if ($targetId <= 0) {
        $next = appr_admin_next_stage($corr);
        if ($next === null) {
            throw new RuntimeException(__('issue_appr.admin_no_next_stage'));
        }
        $newStatus = $next['status'];
        $targetId = $next['assignee'];
    } else {
        $allowed = array_map(static fn (array $r): int => $r['id'], appr_admin_reassign_candidates($corr));
        if (!in_array($targetId, $allowed, true)) {
            throw new InvalidArgumentException(__('issue_appr.admin_target_invalid'));
        }
        $newStatus = $s;
    }

    $clockCol = in_array($newStatus, ['pending_review', 'pending_sg'], true)
        ? 'issue_approval_requested_at'
        : 'issue_approval_escalated_at';
    // نقل لمستخدم محدد حصري (يُسقط حق المعتمد المعيَّن في الإعدادات)؛ المرحلة التالية تعود لمعتمدها المعتاد
    $movedAt = $newStatus === $s ? $now : null;
    $stmt = db()->prepare(
        "UPDATE `tblcorrespondence` SET
            issue_approval_status = ?,
            issue_approval_assignee_id = ?,
            `{$clockCol}` = ?,
            issue_approval_reassigned_at = ?
         WHERE id = ? AND issue_approval_status = ?"
    );
    $stmt->execute([$newStatus, $targetId, $now, $movedAt, $corrId, $s]);
    if ($stmt->rowCount() === 0) {
        throw new RuntimeException(__('auth.forbidden'));
    }

    $targetName = outgoing_user_name_by_id($targetId);
    log_activity(
        (int) $user['id'],
        $corrId,
        'issue_approval_admin_reassign',
        __('activity.action.issue_approval_admin_reassign') . ': ' . $targetName
            . ($newStatus !== $s ? ' (' . outgoing_approval_label($newStatus) . ')' : '') . ' — ' . $reason
    );

    $fresh = get_correspondence($corrId) ?: $corr;
    if ($newStatus === 'pending_review') {
        outgoing_review_notify_reviewers($fresh, [$targetId], $user);
    } else {
        outgoing_approval_notify_first_approver($fresh, $user, $targetId);
    }
    if ($prevId > 0 && $prevId !== $targetId && function_exists('dms_notify_users')) {
        $ref = format_ref_number($corr['ref_number'] ?? $corrId);
        dms_notify_users([$prevId], [
            'type' => 'outgoing_issue_approval',
            'title' => __('issue_appr.admin_reassigned_title'),
            'body' => str_replace([':ref', ':name'], [$ref, $targetName], __('issue_appr.admin_reassigned_body')),
            'link' => appr_p() . '/view.php?id=' . $corrId,
            'correspondence_id' => $corrId,
            'department_id' => (int) ($corr['department_id'] ?? 0) ?: null,
            'dedupe_key' => 'issue_appr_admin_move_' . $corrId . '_' . time(),
        ]);
    }
}

/** هل المستخدم أحد معيّني اعتماد الإصدار (لظهور طابور «يحتاج موافقة») */
function outgoing_user_may_see_approval_queue(?array $user = null): bool
{
    if (!isset($GLOBALS['_dms_appr_p'])) {
        return appr_any(static fn (): bool => outgoing_user_may_see_approval_queue($user));
    }
    if (!outgoing_approval_enabled() || !outgoing_approval_is_configured()) {
        return false;
    }
    $user = $user ?? (function_exists('current_user') ? current_user() : null);
    $uid = (int) ($user['id'] ?? 0);
    if ($uid <= 0) {
        return false;
    }
    return $uid === outgoing_approval_sg_user_id()
        || $uid === outgoing_approval_deputy_user_id()
        || $uid === outgoing_approval_alternate_user_id()
        || appr_user_has_assigned_pending($uid, ['pending_sg', 'pending_deputy', 'pending_alternate']);
}

/**
 * هل المستخدم معيَّن صراحةً على معاملة معلّقة من النوع الحالي (مثلاً بنقل مدير النظام)؟
 *
 * @param list<string> $statuses
 */
function appr_user_has_assigned_pending(int $uid, array $statuses): bool
{
    if ($uid <= 0 || $statuses === []) {
        return false;
    }
    try {
        $stmt = db()->prepare(
            'SELECT 1 FROM `tblcorrespondence`
             WHERE type = ? AND issue_approval_assignee_id = ?
               AND issue_approval_status IN (' . implode(',', array_fill(0, count($statuses), '?')) . ')
             LIMIT 1'
        );
        $stmt->execute(array_merge([appr_p(), $uid], $statuses));
        return (bool) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * شروط SQL للصادر بانتظار بتّ المستخدم الحالي (يطابق منطق outgoing_user_can_decide_approval).
 * بدون قيد القسم — الاعتماد يتجاوز نطاق القسم.
 *
 * @return list<string>
 */
function outgoing_approval_awaiting_decision_sql(?array $user = null, string $alias = 'c'): array
{
    if (!isset($GLOBALS['_dms_appr_p'])) {
        return appr_sql_any(static fn (): array => outgoing_approval_awaiting_decision_sql($user, $alias));
    }
    if (function_exists('ensure_outgoing_approval_schema')) {
        ensure_outgoing_approval_schema();
    }
    if (!outgoing_approval_enabled() || !outgoing_approval_is_configured()) {
        return ['1=0'];
    }
    $user = $user ?? (function_exists('current_user') ? current_user() : null);
    $uid = (int) ($user['id'] ?? 0);
    if ($uid <= 0) {
        return ['1=0'];
    }

    $sg = outgoing_approval_sg_user_id();
    $deputy = outgoing_approval_deputy_user_id();
    $alt = outgoing_approval_alternate_user_id();
    $sod = "{$alias}.created_by <> {$uid} AND IFNULL({$alias}.issue_approval_requested_by, 0) <> {$uid}"
        . " AND IFNULL({$alias}.issue_review_by, 0) <> {$uid}";

    $notMoved = "{$alias}.issue_approval_reassigned_at IS NULL";
    $or = [];
    if ($sg > 0 && $uid === $sg) {
        $or[] = "({$alias}.issue_approval_status = 'pending_sg' AND {$notMoved} AND {$sod})";
    }
    if ($deputy > 0 && $uid === $deputy) {
        $or[] = "({$alias}.issue_approval_status = 'pending_deputy' AND {$notMoved} AND {$sod})";
    }
    if ($alt > 0 && $uid === $alt) {
        $or[] = "({$alias}.issue_approval_status = 'pending_alternate' AND {$notMoved})";
    }
    // معيَّن صراحةً على المعاملة (نفس قيود فصل الصلاحيات عدا مرحلة البديل)
    $or[] = "({$alias}.issue_approval_assignee_id = {$uid}
        AND {$alias}.issue_approval_status IN ('pending_sg','pending_deputy','pending_alternate')
        AND (
            {$alias}.issue_approval_status = 'pending_alternate'
            OR ({$sod})
        ))";

    if ($or === []) {
        return ['1=0'];
    }

    return [
        "{$alias}.type = '" . appr_p() . "'",
        "{$alias}.issue_approval_status IN ('pending_sg','pending_deputy','pending_alternate')",
        '(' . implode(' OR ', $or) . ')',
    ];
}

/**
 * هل المستخدم المعيَّن للبتّ الحالي محجوب بفصل الصلاحيات (منشئ/طالب)؟
 */
function outgoing_user_blocked_by_sod(array $user, array $corr): bool
{
    if ($t = appr_switch_for($corr)) {
        return appr_with($t, static fn (): bool => outgoing_user_blocked_by_sod($user, $corr));
    }
    if (!outgoing_approval_is_pending($corr)) {
        return false;
    }
    $uid = (int) ($user['id'] ?? 0);
    if ($uid <= 0) {
        return false;
    }
    $s = outgoing_approval_status_of($corr);
    if ($s === 'pending_alternate') {
        return false;
    }
    $isCreatorOrRequester = $uid === (int) ($corr['created_by'] ?? 0)
        || $uid === (int) ($corr['issue_approval_requested_by'] ?? 0)
        || $uid === (int) ($corr['issue_review_by'] ?? 0);
    if (!$isCreatorOrRequester) {
        return false;
    }
    if ($s === 'pending_sg' && $uid === outgoing_approval_sg_user_id()) {
        return true;
    }
    if ($s === 'pending_deputy' && $uid === outgoing_approval_deputy_user_id()) {
        return true;
    }
    $assignee = (int) ($corr['issue_approval_assignee_id'] ?? 0);
    return $assignee > 0 && $assignee === $uid;
}

/**
 * صفوف بطاقة بريد الاعتماد — بنفس تنسيق بريد دعوة المستخدم.
 *
 * @return list<array{label:string,value:string,ltr?:bool}>
 */
function outgoing_approval_email_rows(array $corr, ?array $requester = null): array
{
    $rows = outgoing_approval_email_base_rows($corr, $requester);
    return function_exists('dms_notification_rows_with_parties')
        ? dms_notification_rows_with_parties($rows, '', (int) ($corr['id'] ?? 0))
        : $rows;
}

function outgoing_approval_email_base_rows(array $corr, ?array $requester = null): array
{
    $deptName = '';
    $deptId = (int) ($corr['department_id'] ?? 0);
    if ($deptId > 0) {
        try {
            $stmt = db()->prepare('SELECT name, name_en FROM `tbldepartments` WHERE id = ?');
            $stmt->execute([$deptId]);
            $dept = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($dept) {
                $deptName = function_exists('department_display_name') ? department_display_name($dept) : (string) ($dept['name'] ?? '');
            }
        } catch (Throwable $e) {
            // تجاهل — الصف اختياري
        }
    }

    if ($requester === null) {
        $requesterId = (int) ($corr['issue_approval_requested_by'] ?? 0);
        if ($requesterId > 0) {
            try {
                $stmt = db()->prepare('SELECT id, name_ar, name_en, email FROM `tblusers` WHERE id = ?');
                $stmt->execute([$requesterId]);
                $requester = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            } catch (Throwable $e) {
                $requester = null;
            }
        }
    }
    $requesterName = $requester ? trim((string) user_display_name($requester)) : '';

    $type = (string) ($corr['type'] ?? '');
    $incoming = $type === 'incoming';
    if ($type === 'internal') {
        return [
            ['label' => __('corr.number'), 'value' => format_ref_number($corr['ref_number'] ?? ''), 'ltr' => true],
            ['label' => __('corr.subject'), 'value' => trim((string) ($corr['subject'] ?? ''))],
            ['label' => __('internal.section_route'), 'value' => trim((string) ($corr['party_name'] ?? ''))],
            ['label' => __('corr.date'), 'value' => format_date($corr['corr_date'] ?? null)],
            ['label' => appr__('issue_appr.email_requested_by'), 'value' => $requesterName],
        ];
    }
    return [
        ['label' => __('corr.number'), 'value' => format_ref_number($corr['ref_number'] ?? ''), 'ltr' => true],
        ['label' => __('corr.subject'), 'value' => trim((string) ($corr['subject'] ?? ''))],
        ['label' => __($incoming ? 'corr.party' : 'corr.party_to'), 'value' => trim((string) ($corr['party_name'] ?? ''))],
        ['label' => __($incoming ? 'corr.date' : 'corr.date_out'), 'value' => format_date($corr['corr_date'] ?? null)],
        ['label' => __($incoming ? 'corr.department' : 'corr.dept_preparing'), 'value' => $deptName],
        ['label' => appr__('issue_appr.email_requested_by'), 'value' => $requesterName],
    ];
}

function outgoing_approval_request(int $corrId, array $user, bool $notify = true): void
{
    ensure_outgoing_approval_schema();
    $row = get_correspondence($corrId);
    if ($row && ($t = appr_switch_for($row))) {
        appr_with($t, static fn () => outgoing_approval_request($corrId, $user, $notify));
        return;
    }
    if (!outgoing_issue_flow_active()) {
        throw new RuntimeException(function_exists('outgoing_appr_text') ? outgoing_appr_text('issue_appr.not_configured') : appr__('issue_appr.not_configured'));
    }
    $corr = get_correspondence($corrId);
    if (!$corr || ($corr['type'] ?? '') !== appr_p()) {
        throw new RuntimeException(__('corr.not_found'));
    }
    if (correspondence_is_cancelled($corr)) {
        throw new RuntimeException(__('send.err_cancelled'));
    }
    if (!outgoing_user_can_request_approval($user, $corr)) {
        throw new RuntimeException(__('auth.forbidden'));
    }
    if (function_exists('outgoing_request_missing_attachment') && outgoing_request_missing_attachment($corr)) {
        throw new RuntimeException(__('out_prep.attachment_before_review'));
    }

    outgoing_approval_start($corr, $user, $notify);
}

/**
 * سبب منع طلب الاعتماد (نص فارغ = مسموح) — يُستدعى قبل حفظ أي تعديل مصاحب للطلب
 * حتى لا يُحفظ تغيير القسم أو النسخ ثم يفشل الطلب.
 */
function outgoing_approval_request_blocker(array $corr, array $user): string
{
    if ($t = appr_switch_for($corr)) {
        return appr_with($t, static fn (): string => outgoing_approval_request_blocker($corr, $user));
    }
    if (!outgoing_issue_flow_active()) {
        return function_exists('outgoing_appr_text') ? outgoing_appr_text('issue_appr.not_configured') : appr__('issue_appr.not_configured');
    }
    if (correspondence_is_cancelled($corr)) {
        return __('send.err_cancelled');
    }
    if (!outgoing_user_can_request_approval($user, $corr)) {
        return __('auth.forbidden');
    }
    $uid = (int) $user['id'];
    if (outgoing_review_enabled() && outgoing_review_route([(int) ($corr['created_by'] ?? 0), $uid]) !== null) {
        return '';
    }
    if (!outgoing_approval_enabled()) {
        return __('issue_review.no_reviewer');
    }
    return appr_first_stage($corr, [$uid], [$uid => 'requester']) === null ? appr__('issue_appr.no_eligible') : '';
}

/**
 * بدء مسار الإصدار: المراجعة أولاً إن كانت مفعّلة ولها مراجع مؤهَّل، وإلا المعتمد الأول مباشرة.
 *
 * @return 'pending_review'|'pending_sg'
 */
function outgoing_approval_start(array $corr, array $user, bool $notify = true): string
{
    if ($t = appr_switch_for($corr)) {
        return appr_with($t, static fn (): string => outgoing_approval_start($corr, $user, $notify));
    }
    $corrId = (int) $corr['id'];
    $uid = (int) $user['id'];
    $route = outgoing_review_enabled()
        ? outgoing_review_route([(int) ($corr['created_by'] ?? 0), $uid])
        : null;
    $toReview = $route !== null;
    $stage = ($toReview || !outgoing_approval_enabled()) ? null : appr_first_stage($corr, [$uid], [$uid => 'requester']);
    if (!$toReview && !outgoing_approval_enabled()) {
        throw new RuntimeException(__('issue_review.no_reviewer'));
    }
    if (!$toReview && $stage === null) {
        throw new RuntimeException(appr__('issue_appr.no_eligible'));
    }
    $now = date('Y-m-d H:i:s');
    $status = $toReview ? 'pending_review' : $stage['status'];
    db()->prepare(
        "UPDATE `tblcorrespondence` SET
            issue_approval_status = ?,
            issue_approval_requested_at = ?,
            issue_approval_requested_by = ?,
            issue_approval_assignee_id = ?,
            issue_approval_decided_at = NULL,
            issue_approval_decided_by = NULL,
            issue_approval_note = NULL,
            issue_approval_escalated_at = ?,
            issue_approval_reassigned_at = NULL,
            issue_review_by = NULL,
            issue_reviewed_at = NULL,
            issue_review_note = NULL
         WHERE id = ?"
    )->execute([
        $status,
        $now,
        $uid,
        $toReview ? ($route['assignee'] ?: null) : $stage['assignee'],
        in_array($status, ['pending_deputy', 'pending_alternate'], true) ? $now : null,
        $corrId,
    ]);

    if ($toReview) {
        log_activity($uid, $corrId, 'issue_review_request', __('activity.action.issue_review_request'));
        if ($notify) {
            outgoing_review_notify_reviewers($corr, $route['notify'], $user);
        }
        return 'pending_review';
    }
    log_activity($uid, $corrId, 'issue_approval_request', __('activity.action.issue_approval_request'));
    if ($stage['skip_note'] !== '') {
        log_activity(null, $corrId, 'issue_approval_skip', $stage['skip_note']);
    }
    if ($notify) {
        outgoing_approval_notify_first_approver($corr, $user, $stage['assignee'], $stage['skip_note']);
    }
    return $status;
}

function outgoing_user_name_by_id(int $userId): string
{
    if ($userId <= 0) {
        return '';
    }
    try {
        $stmt = db()->prepare('SELECT name_ar, name_en FROM `tblusers` WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return $row ? (string) user_display_name($row) : '';
    } catch (Throwable $e) {
        return '';
    }
}

function outgoing_user_can_forward_issued_copy(array $user, array $corr): bool
{
    if (($corr['type'] ?? '') !== 'outgoing' || correspondence_is_cancelled($corr)) {
        return false;
    }
    if (outgoing_approval_status_of($corr) !== 'pending_copy') {
        return false;
    }

    return function_exists('outgoing_user_can_manage_issued_copy')
        && outgoing_user_can_manage_issued_copy($user, $corr);
}

/** بعد تحقق المعد من نسخة الصادر يُحيلها بنفسه إلى المعتمد الأول. */
function outgoing_release_issued_copy_to_approver(int $corrId): bool
{
    ensure_outgoing_approval_schema();
    $corr = get_correspondence($corrId);
    if (!$corr || ($corr['type'] ?? '') !== 'outgoing') {
        return false;
    }
    if ($t = appr_switch_for($corr)) {
        return appr_with($t, static fn (): bool => outgoing_release_issued_copy_to_approver($corrId));
    }
    if (outgoing_approval_status_of($corr) !== 'pending_copy') {
        return false;
    }
    if (!function_exists('outgoing_has_issued_copy') || !outgoing_has_issued_copy($corrId)) {
        return false;
    }
    $reviewerId = (int) ($corr['issue_review_by'] ?? 0);
    $nextStage = appr_first_stage(
        $corr,
        [$reviewerId, (int) ($corr['issue_approval_requested_by'] ?? 0)],
        $reviewerId > 0 ? [$reviewerId => 'reviewer'] : []
    );
    if ($nextStage === null) {
        throw new RuntimeException(appr__('issue_appr.no_eligible'));
    }
    $now = date('Y-m-d H:i:s');
    $stmt = db()->prepare(
        "UPDATE `tblcorrespondence` SET
            issue_approval_status = ?,
            issue_approval_requested_at = ?,
            issue_approval_assignee_id = ?,
            issue_approval_escalated_at = ?,
            issue_approval_reassigned_at = NULL
         WHERE id = ? AND issue_approval_status = 'pending_copy'"
    );
    $stmt->execute([
        $nextStage['status'],
        $now,
        $nextStage['assignee'],
        $nextStage['status'] === 'pending_sg' ? null : $now,
        $corrId,
    ]);
    if ($stmt->rowCount() === 0) {
        return false;
    }
    $skipNote = (string) ($nextStage['skip_note'] ?? '');
    if ($skipNote !== '') {
        log_activity(null, $corrId, 'issue_approval_skip', $skipNote);
    }
    log_activity((int) ($corr['created_by'] ?? 0), $corrId, 'issue_copy_forward', __('activity.action.issue_copy_forward'));
    $fresh = get_correspondence($corrId) ?: $corr;
    outgoing_approval_notify_first_approver($fresh, null, (int) ($nextStage['assignee'] ?? 0), $skipNote);

    return true;
}

function outgoing_approval_requested_message(array $corr): string
{
    return outgoing_approval_status_of($corr) === 'pending_review'
        ? appr__('issue_review.requested_ok')
        : outgoing_appr_text('issue_appr.requested_ok');
}

function outgoing_approval_notify_first_approver(array $corr, ?array $requester = null, int $assigneeId = 0, string $skipNote = ''): void
{
    $sgId = $assigneeId > 0 ? $assigneeId : outgoing_approval_sg_user_id();
    if ($sgId <= 0 || !function_exists('dms_notify_users')) {
        return;
    }
    $corrId = (int) $corr['id'];
    $ref = format_ref_number($corr['ref_number'] ?? $corrId);
    $subjectLine = trim((string) ($corr['subject'] ?? ''));
    $emailRows = outgoing_approval_email_rows($corr, $requester);
    if ($skipNote !== '') {
        $emailRows[] = ['label' => __('appr_skip.row'), 'value' => $skipNote];
    }
    dms_notify_users([$sgId], [
        'type' => 'outgoing_issue_approval',
        'title' => outgoing_appr_text('issue_appr.notify_request_title'),
        'body' => trim(outgoing_appr_text('issue_appr.notify_request_body', [':ref' => $ref]) . ' ' . $skipNote),
        'link' => appr_p() . '/view.php?id=' . $corrId,
        'correspondence_id' => $corrId,
        'department_id' => (int) ($corr['department_id'] ?? 0) ?: null,
        'dedupe_key' => 'issue_appr_req_' . $corrId . '_' . time(),
        'email' => true,
        'email_intro' => outgoing_appr_text('issue_appr.email_request_intro'),
        'email_details' => trim($ref . ($subjectLine !== '' ? ' — ' . $subjectLine : '')),
        'email_rows' => $emailRows,
        'email_cta' => __('notify.email_view_correspondence'),
    ]);
}

/** @param list<int> $reviewerIds */
function outgoing_review_notify_reviewers(array $corr, array $reviewerIds, ?array $requester = null): void
{
    if ($reviewerIds === [] || !function_exists('dms_notify_users')) {
        return;
    }
    $corrId = (int) $corr['id'];
    $ref = format_ref_number($corr['ref_number'] ?? $corrId);
    $subjectLine = trim((string) ($corr['subject'] ?? ''));
    dms_notify_users($reviewerIds, [
        'type' => 'outgoing_issue_review',
        'title' => appr__('issue_review.notify_request_title'),
        'body' => str_replace(':ref', $ref, appr__('issue_review.notify_request_body')),
        'link' => appr_p() . '/view.php?id=' . $corrId,
        'correspondence_id' => $corrId,
        'department_id' => (int) ($corr['department_id'] ?? 0) ?: null,
        'dedupe_key' => 'issue_review_req_' . $corrId . '_' . time(),
        'email' => true,
        'email_intro' => appr__('issue_review.email_request_intro'),
        'email_details' => trim($ref . ($subjectLine !== '' ? ' — ' . $subjectLine : '')),
        'email_rows' => outgoing_approval_email_rows($corr, $requester),
        'email_cta' => __('notify.email_view_correspondence'),
    ]);
}

/**
 * قرار المراجع: مطابقة ← المعتمد الأول، أو إعادة بملاحظات ← المُعِدّ.
 */
function outgoing_review_decide(int $corrId, array $user, bool $pass, string $note = '', bool $notify = true): void
{
    ensure_outgoing_approval_schema();
    $corr = get_correspondence($corrId);
    if ($corr && ($t = appr_switch_for($corr))) {
        appr_with($t, static fn () => outgoing_review_decide($corrId, $user, $pass, $note, $notify));
        return;
    }
    if (!$corr || ($corr['type'] ?? '') !== appr_p()) {
        throw new RuntimeException(__('corr.not_found'));
    }
    if (!outgoing_user_can_review($user, $corr)) {
        throw new RuntimeException(__('auth.forbidden'));
    }
    if (outgoing_review_escalate_if_due($corr)) {
        $corr = get_correspondence($corrId) ?: $corr;
        if (!outgoing_user_can_review($user, $corr)) {
            throw new RuntimeException(appr__('issue_review.escalated_away'));
        }
    }
    $note = trim($note);
    if (!$pass && $note === '') {
        throw new InvalidArgumentException(appr__('issue_review.note_required'));
    }
    if ($pass && function_exists('outgoing_require_issued_copy')) {
        outgoing_require_issued_copy($corr);
    }
    if (mb_strlen($note) > 500) {
        $note = mb_substr($note, 0, 500);
    }

    if (appr_is_admin_review_override($user, $corr)) {
        $note = appr_admin_override_note($note);
    }
    $uid = (int) $user['id'];
    $now = date('Y-m-d H:i:s');
    $nextStage = null;
    $holdForCopy = $pass
        && function_exists('outgoing_review_pass_waits_for_copy')
        && outgoing_review_pass_waits_for_copy($corr);
    if ($pass && $holdForCopy) {
        $creatorId = (int) ($corr['created_by'] ?? 0);
        $stmt = db()->prepare(
            "UPDATE `tblcorrespondence` SET
                issue_approval_status = 'pending_copy',
                issue_approval_requested_at = ?,
                issue_approval_assignee_id = ?,
                issue_approval_escalated_at = NULL,
                issue_approval_reassigned_at = NULL,
                issue_review_by = ?,
                issue_reviewed_at = ?,
                issue_review_note = ?
             WHERE id = ? AND issue_approval_status = 'pending_review'"
        );
        $stmt->execute([
            $now,
            $creatorId > 0 ? $creatorId : null,
            $uid,
            $now,
            $note !== '' ? $note : null,
            $corrId,
        ]);
    } elseif ($pass) {
        $nextStage = outgoing_approval_enabled()
            ? appr_first_stage($corr, [$uid, (int) ($corr['issue_approval_requested_by'] ?? 0)], [$uid => 'reviewer'])
            : null;
        if (outgoing_approval_enabled() && $nextStage === null) {
            throw new RuntimeException(appr__('issue_appr.no_eligible'));
        }
        if ($nextStage === null) {
            $releaseStatus = ($corr['type'] ?? '') === 'outgoing' ? 'with_comms' : null;
            $stmt = db()->prepare(
                "UPDATE `tblcorrespondence` SET
                    issue_approval_status = ?,
                    issue_approval_assignee_id = NULL,
                    issue_approval_escalated_at = NULL,
                    issue_approval_reassigned_at = NULL,
                    issue_review_by = ?,
                    issue_reviewed_at = ?,
                    issue_review_note = ?
                 WHERE id = ? AND issue_approval_status = 'pending_review'"
            );
            $stmt->execute([
                $releaseStatus,
                $uid,
                $now,
                $note !== '' ? $note : null,
                $corrId,
            ]);
        } else {
            $stmt = db()->prepare(
                "UPDATE `tblcorrespondence` SET
                    issue_approval_status = ?,
                    issue_approval_requested_at = ?,
                    issue_approval_assignee_id = ?,
                    issue_approval_escalated_at = ?,
                    issue_approval_reassigned_at = NULL,
                    issue_review_by = ?,
                    issue_reviewed_at = ?,
                    issue_review_note = ?
                 WHERE id = ? AND issue_approval_status = 'pending_review'"
            );
            $stmt->execute([
                $nextStage['status'],
                $now,
                $nextStage['assignee'],
                $nextStage['status'] === 'pending_sg' ? null : $now,
                $uid,
                $now,
                $note !== '' ? $note : null,
                $corrId,
            ]);
        }
    } else {
        $stmt = db()->prepare(
            "UPDATE `tblcorrespondence` SET
                issue_approval_status = 'returned',
                issue_approval_assignee_id = NULL,
                issue_approval_escalated_at = NULL,
                issue_approval_reassigned_at = NULL,
                issue_review_by = ?,
                issue_reviewed_at = ?,
                issue_review_note = ?
             WHERE id = ? AND issue_approval_status = 'pending_review'"
        );
        $stmt->execute([$uid, $now, $note, $corrId]);
    }
    if ($stmt->rowCount() === 0) {
        throw new RuntimeException(__('auth.forbidden'));
    }

    $action = $pass ? 'issue_review_pass' : 'issue_review_return';
    $descKey = $holdForCopy ? 'activity.action.issue_review_pass_copy' : ('activity.action.' . $action);
    $desc = __($descKey) . ($note !== '' ? ': ' . $note : '');
    log_activity($uid, $corrId, $action, $desc);

    $fresh = get_correspondence($corrId) ?: $corr;
    if ($pass && !$holdForCopy && $nextStage === null && ($corr['type'] ?? '') === 'outgoing') {
        outgoing_handoff_print_to_communications($corrId);
    }
    if ($pass && !$holdForCopy && $nextStage !== null) {
        $skipNote = (string) ($nextStage['skip_note'] ?? '');
        if ($skipNote !== '') {
            log_activity(null, $corrId, 'issue_approval_skip', $skipNote);
        }
        if ($notify) {
            outgoing_approval_notify_first_approver($fresh, null, (int) ($nextStage['assignee'] ?? 0), $skipNote);
        }
    }
    if (!$notify) {
        return;
    }

    $notifyIds = array_values(array_unique(array_filter([
        (int) ($corr['created_by'] ?? 0),
        (int) ($corr['issue_approval_requested_by'] ?? 0),
    ], static fn (int $id): bool => $id > 0 && $id !== $uid)));
    if ($notifyIds !== [] && function_exists('dms_notify_users')) {
        $ref = format_ref_number($corr['ref_number'] ?? $corrId);
        $subjectLine = trim((string) ($corr['subject'] ?? ''));
        dms_notify_users($notifyIds, [
            'type' => 'outgoing_issue_review',
            'title' => appr__($pass ? 'issue_review.notify_passed_title' : 'issue_review.notify_returned_title'),
            'body' => str_replace(':ref', $ref, appr__($pass
                ? ($holdForCopy
                    ? 'issue_review.notify_passed_copy_body'
                    : ($nextStage === null && !outgoing_approval_enabled()
                        ? 'issue_review.notify_passed_ready_body'
                        : 'issue_review.notify_passed_body'))
                : 'issue_review.notify_returned_body')),
            'link' => appr_p() . '/view.php?id=' . $corrId,
            'correspondence_id' => $corrId,
            'department_id' => (int) ($corr['department_id'] ?? 0) ?: null,
            'dedupe_key' => 'issue_review_dec_' . $corrId . '_' . ($pass ? 'pass' : 'return') . '_' . time(),
            'email' => !$pass,
            'email_intro' => appr__('issue_review.email_returned_intro'),
            'email_details' => trim($ref . ($subjectLine !== '' ? ' — ' . $subjectLine : '') . ($note !== '' ? ' — ' . $note : '')),
            'email_rows' => outgoing_approval_email_rows($fresh),
            'email_cta' => __('notify.email_view_correspondence'),
        ]);
    }
}

/**
 * قفل التعديل: تعديل صادر قيد الاعتماد أو معتمد (قبل الإرسال) يعيده إلى بداية المسار —
 * المراجعة إن كانت مفعّلة، وإلا المعتمد الأول — ويُسقط أي اعتماد سابق.
 */
function outgoing_approval_after_edit(int $corrId, array $user): void
{
    ensure_outgoing_approval_schema();
    $corr = get_correspondence($corrId);
    if (!$corr || correspondence_is_cancelled($corr)) {
        return;
    }
    if ($t = appr_switch_for($corr)) {
        appr_with($t, static fn () => outgoing_approval_after_edit($corrId, $user));
        return;
    }
    if (!outgoing_approval_is_configured()) {
        return;
    }
    $s = outgoing_approval_status_of($corr);
    $resetOn = ['pending_review', 'pending_sg', 'pending_deputy', 'pending_alternate'];
    if (appr_p() === 'outgoing') {
        $resetOn[] = 'approved';
    }
    if (!in_array($s, $resetOn, true)) {
        return;
    }
    if (in_array((string) ($corr['status'] ?? ''), ['sent', 'delivered', 'closed'], true)) {
        return;
    }
    log_activity((int) $user['id'], $corrId, 'issue_approval_reset_edit', __('activity.action.issue_approval_reset_edit'));
    try {
        outgoing_approval_start($corr, $user);
    } catch (RuntimeException $e) {
        // لا معتمد مؤهَّل الآن: يُسقط الاعتماد السابق حتى لا يُصدَر المعدَّل دون موافقة،
        // والوارد/الداخلية تبقى محجوزة (معادة للمنشئ) حتى يُعاد طلبها
        db()->prepare(
            "UPDATE `tblcorrespondence` SET
                issue_approval_status = " . (appr_p() === 'outgoing' ? 'NULL' : "'returned'") . ",
                issue_approval_assignee_id = NULL,
                issue_approval_decided_at = NULL,
                issue_approval_decided_by = NULL,
                issue_approval_escalated_at = NULL
             WHERE id = ?"
        )->execute([$corrId]);
        throw $e;
    }
}

/** مستخدمو الاتصالات الإدارية الذين تُحال إليهم طباعة الصادر بعد الاعتماد. @return list<int> */
function outgoing_dispatch_user_ids(): array
{
    return function_exists('outgoing_review_user_ids') ? outgoing_review_user_ids([]) : [];
}

function corr_print_perm(string $type, string $kind): string
{
    $type = in_array($type, ['outgoing', 'incoming', 'internal'], true) ? $type : 'outgoing';
    $kind = in_array($kind, ['form', 'envelope', 'thermal'], true) ? $kind : 'form';

    return $type . '.print_' . $kind;
}

function corr_user_can_print(string $type, string $kind, ?array $user = null): bool
{
    $user = $user ?? (function_exists('current_user') ? current_user() : null);

    return $user !== null && function_exists('user_can') && user_can(corr_print_perm($type, $kind), $user);
}

/** معاينة الملصق الحراري في صفحة العرض — أو ضمنياً إن وُجدت صلاحية طباعته. */
function corr_user_can_view_thermal(string $type, ?array $user = null): bool
{
    $user = $user ?? (function_exists('current_user') ? current_user() : null);
    if ($user === null || !function_exists('user_can')) {
        return false;
    }
    $type = in_array($type, ['outgoing', 'incoming', 'internal'], true) ? $type : 'outgoing';

    return user_can($type . '.view_thermal', $user) || user_can($type . '.print_thermal', $user);
}

function outgoing_require_print_perm(string $perm, array $user, string $backUrl): void
{
    if (function_exists('user_can') && user_can($perm, $user)) {
        return;
    }
    if (function_exists('flash')) {
        flash('warning', __('auth.no_permission'));
    }
    if (function_exists('redirect')) {
        redirect($backUrl);
    }
    exit(__('auth.forbidden'));
}

/** آخر خطوة في الصادر: الإحالة إلى الاتصالات الإدارية للطباعة، بعد آخر مرحلة مفعّلة. */
function outgoing_handoff_print_to_communications(int $corrId): void
{
    $ids = outgoing_dispatch_user_ids();
    if ($ids === [] || $corrId <= 0) {
        return;
    }
    $assignee = count($ids) === 1 ? $ids[0] : 0;
    db()->prepare(
        "UPDATE `tblcorrespondence` SET issue_approval_assignee_id = ?
         WHERE id = ? AND type = 'outgoing' AND issue_approval_status IN ('approved', 'with_comms')"
    )->execute([$assignee > 0 ? $assignee : null, $corrId]);
    if (function_exists('log_activity')) {
        log_activity(null, $corrId, 'issue_print_handoff', __('activity.action.issue_print_handoff'));
    }
    if (!function_exists('dms_notify_users') || !function_exists('get_correspondence')) {
        return;
    }
    $corr = get_correspondence($corrId);
    if (!$corr) {
        return;
    }
    $ref = format_ref_number($corr['ref_number'] ?? $corrId);
    $subjectLine = trim((string) ($corr['subject'] ?? ''));
    $electronic = function_exists('correspondence_intend_electronic') && correspondence_intend_electronic($corr);
    $printBody = str_replace(':ref', $ref, __($electronic ? 'issue_appr.notify_print_body_electronic' : 'issue_appr.notify_print_body'));
    dms_notify_users($ids, [
        'type' => 'outgoing_issue_approval',
        'title' => __('issue_appr.notify_print_title'),
        'body' => $printBody,
        'link' => 'outgoing/view.php?id=' . $corrId,
        'correspondence_id' => $corrId,
        'department_id' => (int) ($corr['department_id'] ?? 0) ?: null,
        'dedupe_key' => 'issue_print_handoff_' . $corrId . '_' . time(),
        'email' => true,
        'email_intro' => $printBody,
        'email_details' => trim($ref . ($subjectLine !== '' ? ' — ' . $subjectLine : '')),
        'email_rows' => function_exists('outgoing_approval_email_rows') ? outgoing_approval_email_rows($corr) : [],
        'email_cta' => __('notify.email_view_correspondence'),
    ]);
}

/** الصادر الذي أنهى المراحل المفعّلة بلا اعتماد: يصبح لدى الاتصالات الإدارية للطباعة. */
function outgoing_release_to_communications(int $corrId): void
{
    if ($corrId <= 0) {
        return;
    }
    db()->prepare(
        "UPDATE `tblcorrespondence`
         SET issue_approval_status = 'with_comms'
         WHERE id = ? AND type = 'outgoing'
           AND (issue_approval_status IS NULL OR issue_approval_status = '')"
    )->execute([$corrId]);
    outgoing_handoff_print_to_communications($corrId);
}

function outgoing_approval_decide(int $corrId, array $user, bool $approve, string $note = '', bool $notify = true): void
{
    ensure_outgoing_approval_schema();
    $corr = get_correspondence($corrId);
    if (!$corr || ($corr['type'] ?? '') !== appr_p()) {
        throw new RuntimeException(__('corr.not_found'));
    }
    if (!outgoing_user_can_decide_approval($user, $corr)) {
        throw new RuntimeException(__('auth.forbidden'));
    }

    // صعّد أولاً إن انتهت المهلة قبل البت
    outgoing_approval_escalate_if_due($corrId);
    $corr = get_correspondence($corrId) ?: $corr;
    if (!outgoing_user_can_decide_approval($user, $corr)) {
        throw new RuntimeException(__('auth.forbidden'));
    }

    $note = trim($note);
    $isOverride = appr_is_admin_decide_override($user, $corr);
    $isAlternate = !$isOverride && outgoing_user_is_alternate_approver($user, $corr);
    if ($isAlternate && $note === '') {
        throw new InvalidArgumentException(appr__('issue_appr.alternate_reason_required'));
    }
    if (!$approve && $note === '') {
        throw new InvalidArgumentException(__('issue_appr.reject_note_required'));
    }
    if ($approve && function_exists('outgoing_require_issued_copy')) {
        outgoing_require_issued_copy($corr);
    }
    if (mb_strlen($note) > 500) {
        $note = mb_substr($note, 0, 500);
    }
    if ($isAlternate) {
        $prefix = outgoing_appr_text('issue_appr.alternate_reason_prefix');
        if ($prefix !== '' && !str_starts_with($note, $prefix)) {
            $note = $prefix . ' ' . $note;
            if (mb_strlen($note) > 500) {
                $note = mb_substr($note, 0, 500);
            }
        }
    }
    if ($isOverride) {
        $note = appr_admin_override_note($note);
    }
    $status = $approve ? 'approved' : 'rejected';
    $now = date('Y-m-d H:i:s');
    $stmt = db()->prepare(
        "UPDATE `tblcorrespondence` SET
            issue_approval_status = ?,
            issue_approval_decided_at = ?,
            issue_approval_decided_by = ?,
            issue_approval_note = ?,
            issue_approval_assignee_id = NULL
         WHERE id = ? AND issue_approval_status = ?"
    );
    $stmt->execute([$status, $now, (int) $user['id'], $note !== '' ? $note : null, $corrId, outgoing_approval_status_of($corr)]);
    if ($stmt->rowCount() === 0) {
        throw new RuntimeException(__('auth.forbidden'));
    }

    $action = $approve ? 'issue_approval_approve' : 'issue_approval_reject';
    if ($isAlternate) {
        $action = $approve ? 'issue_approval_approve_alternate' : 'issue_approval_reject_alternate';
    }
    $desc = $approve
        ? ($isAlternate ? __('activity.action.issue_approval_approve_alternate') : __('activity.action.issue_approval_approve'))
        : ($isAlternate ? __('activity.action.issue_approval_reject_alternate') : __('activity.action.issue_approval_reject'));
    if ($note !== '') {
        $desc .= ': ' . $note;
    }
    log_activity((int) $user['id'], $corrId, $action, $desc);

    if ($approve && ($corr['type'] ?? '') === 'outgoing') {
        outgoing_handoff_print_to_communications($corrId);
    }

    // إشعار داخل النظام + بريد: منشئ المعاملة وطالب الموافقة (إن اختلف)، دون من بَتّ
    $deciderId = (int) ($user['id'] ?? 0);
    $notifyIds = [];
    $createdBy = (int) ($corr['created_by'] ?? 0);
    if ($createdBy > 0 && $createdBy !== $deciderId) {
        $notifyIds[] = $createdBy;
    }
    $reqBy = (int) ($corr['issue_approval_requested_by'] ?? 0);
    if ($reqBy > 0 && $reqBy !== $deciderId && $reqBy !== $createdBy) {
        $notifyIds[] = $reqBy;
    }
    $notifyIds = array_values(array_unique(array_filter(array_map('intval', $notifyIds))));

    if ($notify && $notifyIds !== [] && function_exists('dms_notify_users')) {
        $ref = format_ref_number($corr['ref_number'] ?? $corrId);
        $subjectLine = trim((string) ($corr['subject'] ?? ''));
        $title = $approve ? appr__('issue_appr.notify_approved_title') : appr__('issue_appr.notify_rejected_title');
        $bodyKey = $approve ? 'issue_appr.notify_approved_body' : 'issue_appr.notify_rejected_body';
        $body = ($approve && ($corr['type'] ?? '') === 'outgoing')
            ? str_replace(':ref', $ref, __('issue_appr.notify_approved_to_comms'))
            : str_replace(':ref', $ref, appr__($bodyKey));
        $deptId = (int) ($corr['department_id'] ?? 0) ?: null;
        $link = appr_p() . '/view.php?id=' . $corrId;
        $details = trim($ref . ($subjectLine !== '' ? ' — ' . $subjectLine : ''));
        if ($isAlternate) {
            $details .= ' — ' . outgoing_approver_alternate_title();
        }
        dms_notify_users($notifyIds, [
            'type' => 'outgoing_issue_approval',
            'title' => $title,
            'body' => $body,
            'link' => $link,
            'correspondence_id' => $corrId,
            'department_id' => $deptId,
            'dedupe_key' => 'issue_appr_dec_' . $corrId . '_' . $status . '_' . (int) strtotime((string) ($corr['issue_approval_requested_at'] ?? '')),
            'email' => true,
            'email_intro' => $approve
                ? appr__('issue_appr.email_approved_intro')
                : appr__('issue_appr.email_rejected_intro'),
            'email_details' => $details,
            'email_rows' => outgoing_approval_email_rows($corr),
            'email_cta' => __('notify.email_view_correspondence'),
        ]);
    }
}

function outgoing_approval_escalate_if_due(int $corrId): bool
{
    ensure_outgoing_approval_schema();
    $corr = get_correspondence($corrId);
    if (!$corr) {
        return false;
    }
    if ($t = appr_switch_for($corr)) {
        return appr_with($t, static fn (): bool => outgoing_approval_escalate_if_due($corrId));
    }
    $status = outgoing_approval_status_of($corr);

    if ($status === 'pending_review') {
        return outgoing_review_escalate_if_due($corr);
    }

    // 1) المعتمد الأول → المعتمد الثاني (أو البديل مباشرة إن كان الثاني غير مفعّل)
    if ($status === 'pending_sg') {
        $deputyId = outgoing_approval_deputy_escalation_user_id();
        $altId = outgoing_approval_alternate_escalation_user_id();
        if ($deputyId <= 0 && $altId <= 0) {
            return false;
        }
        $requestedAt = strtotime((string) ($corr['issue_approval_requested_at'] ?? ''));
        if ($requestedAt <= 0) {
            return false;
        }
        $deadline = $requestedAt + (outgoing_approval_minutes_for('primary') * 60);
        if (time() < $deadline) {
            return false;
        }

        $firstId = (int) ($corr['issue_approval_assignee_id'] ?? 0) ?: outgoing_approval_sg_user_id();
        $sodIds = [
            (int) ($corr['created_by'] ?? 0),
            (int) ($corr['issue_approval_requested_by'] ?? 0),
            (int) ($corr['issue_review_by'] ?? 0),
        ];
        $skipNote = '';
        if ($deputyId > 0 && (outgoing_active_user_ids([$deputyId]) === [] || in_array($deputyId, $sodIds, true))) {
            $skipNote = appr_skip_note($deputyId, __('appr_skip.stage_second'), $corr);
            $deputyId = 0;
        }
        if ($deputyId <= 0) {
            return outgoing_approval_escalate_to_alternate($corr, $altId, 'pending_sg', 'primary', $firstId, $skipNote);
        }

        $now = date('Y-m-d H:i:s');
        $stmt = db()->prepare(
            "UPDATE `tblcorrespondence` SET
                issue_approval_status = 'pending_deputy',
                issue_approval_assignee_id = ?,
                issue_approval_escalated_at = ?,
                issue_approval_reassigned_at = NULL
             WHERE id = ? AND issue_approval_status = 'pending_sg'"
        );
        $stmt->execute([$deputyId, $now, $corrId]);
        if ($stmt->rowCount() === 0) {
            return false;
        }

        log_activity(null, $corrId, 'issue_approval_escalate', __('activity.action.issue_approval_escalate'));

        if (function_exists('dms_notify_users')) {
            $ref = format_ref_number($corr['ref_number'] ?? $corrId);
            $subjectLine = trim((string) ($corr['subject'] ?? ''));
            $timeout = outgoing_approval_timeout_label_for('primary');
            $body = outgoing_appr_text('issue_appr.notify_escalated_body', [':ref' => $ref, ':timeout' => $timeout]);
            $ids = [$deputyId];
            if ($firstId > 0) {
                $ids[] = $firstId;
            }
            $deptId = (int) ($corr['department_id'] ?? 0) ?: null;
            $link = appr_p() . '/view.php?id=' . $corrId;
            $details = trim($ref . ($subjectLine !== '' ? ' — ' . $subjectLine : ''));
            dms_notify_users($ids, [
                'type' => 'outgoing_issue_approval',
                'title' => outgoing_appr_text('issue_appr.notify_escalated_title'),
                'body' => $body,
                'link' => $link,
                'correspondence_id' => $corrId,
                'department_id' => $deptId,
                'dedupe_key' => 'issue_appr_esc_' . $corrId . '_' . $requestedAt,
            ]);
            if (function_exists('dms_notify_user_emails')) {
                dms_notify_user_emails(
                    [$deputyId],
                    outgoing_appr_text('issue_appr.email_escalated_intro'),
                    $details,
                    $link,
                    __('notify.email_view_correspondence'),
                    $deptId,
                    outgoing_approval_email_rows($corr)
                );
            }
        }
        return true;
    }

    // 2) المعتمد الثاني → المعتمد البديل
    if ($status === 'pending_deputy') {
        $altId = outgoing_approval_alternate_escalation_user_id();
        if ($altId <= 0) {
            return false;
        }
        $escalatedAt = strtotime((string) ($corr['issue_approval_escalated_at'] ?? ''));
        if ($escalatedAt <= 0) {
            $escalatedAt = strtotime((string) ($corr['issue_approval_requested_at'] ?? ''));
        }
        if ($escalatedAt <= 0) {
            return false;
        }
        $deadline = $escalatedAt + (outgoing_approval_minutes_for('deputy') * 60);
        if (time() < $deadline) {
            return false;
        }

        return outgoing_approval_escalate_to_alternate($corr, $altId, 'pending_deputy', 'deputy', (int) ($corr['issue_approval_assignee_id'] ?? 0) ?: outgoing_approval_deputy_user_id());
    }

    return false;
}

/** المراجع الأول → المراجع الثاني بعد انتهاء المهلة */
function outgoing_review_escalate_if_due(array $corr): bool
{
    if ($t = appr_switch_for($corr)) {
        return appr_with($t, static fn (): bool => outgoing_review_escalate_if_due($corr));
    }
    $corrId = (int) ($corr['id'] ?? 0);
    $primaryId = outgoing_review_primary_user_id();
    $altId = outgoing_review_alternate_user_id();
    if ($corrId <= 0 || $primaryId <= 0 || $altId <= 0 || $altId === $primaryId
        || (int) ($corr['issue_approval_assignee_id'] ?? 0) !== $primaryId
    ) {
        return false;
    }
    if (in_array($altId, [(int) ($corr['created_by'] ?? 0), (int) ($corr['issue_approval_requested_by'] ?? 0)], true)
        || outgoing_active_user_ids([$altId]) === []
    ) {
        return false;
    }
    $requestedAt = strtotime((string) ($corr['issue_approval_requested_at'] ?? ''));
    if ($requestedAt <= 0 || time() < $requestedAt + outgoing_review_minutes() * 60) {
        return false;
    }

    $stmt = db()->prepare(
        "UPDATE `tblcorrespondence` SET
            issue_approval_assignee_id = ?,
            issue_approval_escalated_at = ?
         WHERE id = ? AND issue_approval_status = 'pending_review' AND issue_approval_assignee_id = ?"
    );
    $stmt->execute([$altId, date('Y-m-d H:i:s'), $corrId, $primaryId]);
    if ($stmt->rowCount() === 0) {
        return false;
    }

    log_activity(null, $corrId, 'issue_review_escalate', __('activity.action.issue_review_escalate'));

    if (function_exists('dms_notify_users')) {
        $ref = format_ref_number($corr['ref_number'] ?? $corrId);
        $subjectLine = trim((string) ($corr['subject'] ?? ''));
        $body = str_replace([':ref', ':timeout'], [$ref, outgoing_review_timeout_label()], appr__('issue_review.notify_escalated_body'));
        $deptId = (int) ($corr['department_id'] ?? 0) ?: null;
        $link = appr_p() . '/view.php?id=' . $corrId;
        dms_notify_users([$altId, $primaryId], [
            'type' => 'outgoing_issue_review',
            'title' => appr__('issue_review.notify_escalated_title'),
            'body' => $body,
            'link' => $link,
            'correspondence_id' => $corrId,
            'department_id' => $deptId,
            'dedupe_key' => 'issue_review_esc_' . $corrId . '_' . $requestedAt,
        ]);
        if (function_exists('dms_notify_user_emails')) {
            dms_notify_user_emails(
                [$altId],
                appr__('issue_review.email_escalated_intro'),
                trim($ref . ($subjectLine !== '' ? ' — ' . $subjectLine : '')),
                $link,
                __('notify.email_view_correspondence'),
                $deptId,
                outgoing_approval_email_rows($corr)
            );
        }
    }
    return true;
}

/**
 * تحويل المعاملة إلى المعتمد البديل من المرحلة الحالية مع الإشعارات.
 *
 * @param 'pending_sg'|'pending_deputy' $fromStatus
 * @param 'primary'|'deputy' $timeoutStage
 */
function outgoing_approval_escalate_to_alternate(array $corr, int $altId, string $fromStatus, string $timeoutStage, int $previousUserId, string $skipNote = ''): bool
{
    $corrId = (int) ($corr['id'] ?? 0);
    if ($corrId <= 0 || $altId <= 0 || outgoing_active_user_ids([$altId]) === []) {
        return false;
    }

    $now = date('Y-m-d H:i:s');
    $stmt = db()->prepare(
        "UPDATE `tblcorrespondence` SET
            issue_approval_status = 'pending_alternate',
            issue_approval_assignee_id = ?,
            issue_approval_escalated_at = COALESCE(issue_approval_escalated_at, ?),
            issue_approval_reassigned_at = NULL
         WHERE id = ? AND issue_approval_status = ?"
    );
    $stmt->execute([$altId, $now, $corrId, $fromStatus]);
    if ($stmt->rowCount() === 0) {
        return false;
    }

    log_activity(null, $corrId, 'issue_approval_escalate_alternate', trim(__('activity.action.issue_approval_escalate_alternate')
        . ($skipNote !== '' ? ' — ' . $skipNote : '')));

    if (function_exists('dms_notify_users')) {
        $ref = format_ref_number($corr['ref_number'] ?? $corrId);
        $subjectLine = trim((string) ($corr['subject'] ?? ''));
        $timeout = outgoing_approval_timeout_label_for($timeoutStage);
        $body = trim(outgoing_appr_text('issue_appr.notify_escalated_alternate_body', [':ref' => $ref, ':timeout' => $timeout]) . ' ' . $skipNote);
        $emailRows = outgoing_approval_email_rows($corr);
        if ($skipNote !== '') {
            $emailRows[] = ['label' => __('appr_skip.row'), 'value' => $skipNote];
        }
        $ids = [$altId];
        if ($previousUserId > 0) {
            $ids[] = $previousUserId;
        }
        $deptId = (int) ($corr['department_id'] ?? 0) ?: null;
        $link = appr_p() . '/view.php?id=' . $corrId;
        $details = trim($ref . ($subjectLine !== '' ? ' — ' . $subjectLine : ''));
        dms_notify_users($ids, [
            'type' => 'outgoing_issue_approval',
            'title' => outgoing_appr_text('issue_appr.notify_escalated_alternate_title'),
            'body' => $body,
            'link' => $link,
            'correspondence_id' => $corrId,
            'department_id' => $deptId,
            'dedupe_key' => 'issue_appr_esc_alt_' . $corrId . '_' . (int) strtotime((string) ($corr['issue_approval_requested_at'] ?? '')),
        ]);
        if (function_exists('dms_notify_user_emails')) {
            dms_notify_user_emails(
                [$altId],
                outgoing_appr_text('issue_appr.email_escalated_alternate_intro'),
                $details,
                $link,
                __('notify.email_view_correspondence'),
                $deptId,
                $emailRows
            );
        }
    }
    return true;
}

/**
 * ملخص موافقة الإصدار لدفعة جماعية.
 *
 * @param list<array<string,mixed>> $items
 * @return array{
 *   configured:bool,
 *   can_issue:bool,
 *   total:int,
 *   active:int,
 *   pending:int,
 *   approved:int,
 *   rejected:int,
 *   none:int,
 *   block_reason:string,
 *   can_request:bool,
 *   can_decide:bool
 * }
 */
function outgoing_batch_approval_summary(array $items, ?array $user = null): array
{
    $configured = outgoing_approval_is_configured() || outgoing_issue_flow_active();
    $summary = [
        'configured' => $configured,
        'can_issue' => true,
        'total' => count($items),
        'active' => 0,
        'pending' => 0,
        'approved' => 0,
        'rejected' => 0,
        'none' => 0,
        'block_reason' => '',
        'can_request' => false,
        'can_decide' => false,
        'can_review' => false,
        'in_review' => 0,
        'returned' => 0,
    ];
    if (!$configured) {
        foreach ($items as $corr) {
            if (($corr['type'] ?? '') !== 'outgoing' || correspondence_is_cancelled($corr)) {
                continue;
            }
            $summary['active']++;
            if (!outgoing_can_issue($corr)) {
                $summary['can_issue'] = false;
                if ($summary['block_reason'] === '') {
                    $summary['block_reason'] = (function_exists('outgoing_prep_is_open') && outgoing_prep_is_open($corr))
                        ? __('out_prep.print_blocked')
                        : appr__('issue_appr.batch_blocked_pending');
                }
            }
        }
        if ($summary['active'] === 0) {
            $summary['can_issue'] = false;
            $summary['block_reason'] = __('cancel.batch_print_empty');
        }
        return $summary;
    }

    foreach ($items as $corr) {
        if (($corr['type'] ?? '') !== 'outgoing' || correspondence_is_cancelled($corr)) {
            continue;
        }
        $summary['active']++;
        $s = outgoing_approval_status_of($corr);
        if ($s === 'approved' || $s === 'with_comms') {
            $summary['approved']++;
        } elseif ($s === 'rejected') {
            $summary['rejected']++;
        } elseif ($s === 'pending_review') {
            $summary['pending']++;
            $summary['in_review']++;
        } elseif (outgoing_approval_is_pending($corr)) {
            $summary['pending']++;
        } else {
            $summary['none']++;
            if ($s === 'returned') {
                $summary['returned']++;
            }
        }
        if (!outgoing_can_issue($corr)) {
            $summary['can_issue'] = false;
        }
        if ($user) {
            if (outgoing_user_can_request_approval($user, $corr)) {
                $summary['can_request'] = true;
            }
            if (outgoing_user_can_decide_approval($user, $corr) && !appr_is_admin_decide_override($user, $corr)) {
                $summary['can_decide'] = true;
            }
            if (outgoing_user_can_review($user, $corr) && !appr_is_admin_review_override($user, $corr)) {
                $summary['can_review'] = true;
            }
        }
    }

    if ($summary['active'] === 0) {
        $summary['can_issue'] = false;
        $summary['block_reason'] = __('cancel.batch_print_empty');
    } elseif (!$summary['can_issue']) {
        if ($summary['rejected'] > 0) {
            $summary['block_reason'] = appr__('issue_appr.batch_blocked_rejected');
        } elseif ($summary['pending'] > 0 || $summary['none'] > 0) {
            $summary['block_reason'] = appr__('issue_appr.batch_blocked_pending');
        } else {
            $summary['block_reason'] = appr__('issue_appr.blocked_pending');
        }
    }

    return $summary;
}

function outgoing_batch_can_issue(array $items): bool
{
    return (bool) (outgoing_batch_approval_summary($items)['can_issue'] ?? false);
}

/** @return list<array<string,mixed>> معاملات الدفعة غير الملغاة (دون تصفية الصلاحية) */
function outgoing_batch_cycle_rows(int $batchId): array
{
    $stmt = db()->prepare(
        "SELECT id, type, status, created_by, issue_approval_status FROM `tblcorrespondence`
         WHERE batch_id = ? AND type = 'outgoing' AND status <> 'cancelled'"
    );
    $stmt->execute([$batchId]);
    return $stmt->fetchAll() ?: [];
}

/** تفعيل تأكيد الحضور تعديل: أثناء الدورة للمنشئ ومدير النظام فقط */
function outgoing_batch_can_enable_rsvp(int $batchId, array $user): bool
{
    if (!can_edit_correspondence($user)) {
        return false;
    }
    foreach (outgoing_batch_cycle_rows($batchId) as $r) {
        if (!corr_user_can_edit_in_cycle($user, $r)) {
            return false;
        }
    }
    return true;
}

function outgoing_batch_rsvp_in_cycle(int $batchId): bool
{
    foreach (outgoing_batch_cycle_rows($batchId) as $r) {
        if (corr_approval_edit_locked($r)) {
            return true;
        }
    }
    return false;
}

/**
 * تفعيل تأكيد الحضور للدفعة، ويعيد دورة الاعتماد لما كان بانتظار القرار أو معتمداً غير مرسل.
 *
 * @return string رسالة تعذّر إعادة الدورة (إن وُجدت)
 */
function outgoing_batch_enable_rsvp(int $batchId, array $user): string
{
    if (!outgoing_batch_can_enable_rsvp($batchId, $user)) {
        throw new RuntimeException(can_edit_correspondence($user) ? __('issue_appr.edit_creator_only') : __('auth.forbidden'));
    }
    $rows = outgoing_batch_cycle_rows($batchId);
    set_batch_rsvp_required($batchId, true);
    $resetErr = '';
    foreach ($rows as $r) {
        try {
            outgoing_approval_after_edit((int) $r['id'], $user);
        } catch (Throwable $e) {
            $resetErr = $e->getMessage();
            error_log('DMS batch rsvp approval reset #' . (int) $r['id'] . ': ' . $resetErr);
        }
    }
    return $resetErr;
}

/**
 * @param array<int,int> $countsByUser
 */
function outgoing_batch_send_digest(
    int $batchId,
    array $countsByUser,
    array $sampleCorr,
    ?array $actor,
    string $type,
    string $titleKey,
    string $bodyKey,
    string $introKey,
    bool $email,
    string $dedupePrefix,
    string $note = ''
): void {
    if ($countsByUser === [] || !function_exists('dms_notify_users')) {
        return;
    }
    $batch = function_exists('get_outgoing_batch') ? get_outgoing_batch($batchId) : null;
    $subject = trim((string) ($batch['subject'] ?? $sampleCorr['subject'] ?? ''));
    $link = 'outgoing/batch.php?id=' . $batchId;
    $deptId = (int) ($sampleCorr['department_id'] ?? 0) ?: null;
    $baseRows = function_exists('outgoing_approval_email_rows')
        ? outgoing_approval_email_rows($sampleCorr, $actor)
        : [];
    $actorId = (int) ($actor['id'] ?? 0);
    foreach ($countsByUser as $uid => $count) {
        $uid = (int) $uid;
        $count = (int) $count;
        if ($uid <= 0 || $count <= 0 || $uid === $actorId) {
            continue;
        }
        $rows = $baseRows;
        $rows[] = ['label' => __('bulk.recipients'), 'value' => (string) $count];
        if ($note !== '') {
            $rows[] = ['label' => __('issue_review.note'), 'value' => $note];
        }
        $body = str_replace(
            [':subject', ':count'],
            [$subject !== '' ? $subject : '—', (string) $count],
            __($bodyKey)
        );
        $details = trim(($subject !== '' ? $subject . ' — ' : '') . (string) $count);
        dms_notify_users([$uid], [
            'type' => $type,
            'title' => __($titleKey),
            'body' => $body,
            'link' => $link,
            'correspondence_id' => (int) ($sampleCorr['id'] ?? 0) ?: null,
            'department_id' => $deptId,
            'dedupe_key' => $dedupePrefix . $batchId . '_' . $uid . '_' . time(),
            'email' => $email,
            'email_intro' => __($introKey),
            'email_details' => $details,
            'email_rows' => $rows,
            'email_cta' => __('notify.email_view_batch'),
        ]);
    }
}

/** @param array<int,int> $map */
function outgoing_batch_digest_add(array &$map, int $uid): void
{
    if ($uid <= 0) {
        return;
    }
    $map[$uid] = ($map[$uid] ?? 0) + 1;
}

/** @return list<int> */
function outgoing_batch_item_review_recipients(array $corr): array
{
    $assignee = (int) ($corr['issue_approval_assignee_id'] ?? 0);
    if ($assignee > 0) {
        return [$assignee];
    }
    $exclude = [
        (int) ($corr['created_by'] ?? 0),
        (int) ($corr['issue_approval_requested_by'] ?? 0),
    ];
    $route = function_exists('outgoing_review_route') ? outgoing_review_route($exclude) : null;
    return array_values(array_unique(array_filter(array_map('intval', $route['notify'] ?? []))));
}

/** @return list<int> */
function outgoing_batch_item_approval_recipients(array $corr): array
{
    $assignee = (int) ($corr['issue_approval_assignee_id'] ?? 0);
    if ($assignee > 0) {
        return [$assignee];
    }
    $sg = function_exists('outgoing_approval_sg_user_id') ? outgoing_approval_sg_user_id() : 0;
    return $sg > 0 ? [$sg] : [];
}

/** @param list<array<string,mixed>> $items */
function outgoing_batch_stakeholder_counts(array $items, int $excludeUid): array
{
    $map = [];
    foreach ($items as $corr) {
        $seen = [];
        foreach ([(int) ($corr['created_by'] ?? 0), (int) ($corr['issue_approval_requested_by'] ?? 0)] as $uid) {
            if ($uid > 0 && $uid !== $excludeUid && !isset($seen[$uid])) {
                $seen[$uid] = true;
                outgoing_batch_digest_add($map, $uid);
            }
        }
    }
    return $map;
}

/** @param list<array<string,mixed>> $items */
function outgoing_batch_notify_cycle(int $batchId, array $items, array $actor, string $event, string $note = ''): void
{
    if ($batchId <= 0 || $items === []) {
        return;
    }
    $sample = $items[0];
    $reviewers = [];
    $approvers = [];
    foreach ($items as $corr) {
        $st = function_exists('outgoing_approval_status_of') ? outgoing_approval_status_of($corr) : '';
        if ($event === 'request') {
            if ($st === 'pending_review') {
                foreach (outgoing_batch_item_review_recipients($corr) as $uid) {
                    outgoing_batch_digest_add($reviewers, $uid);
                }
            } elseif (in_array($st, ['pending_sg', 'pending_deputy', 'pending_alternate'], true)) {
                foreach (outgoing_batch_item_approval_recipients($corr) as $uid) {
                    outgoing_batch_digest_add($approvers, $uid);
                }
            }
        } elseif ($event === 'review_pass') {
            if (in_array($st, ['pending_sg', 'pending_deputy', 'pending_alternate'], true)) {
                foreach (outgoing_batch_item_approval_recipients($corr) as $uid) {
                    outgoing_batch_digest_add($approvers, $uid);
                }
            }
        }
    }

    if ($event === 'request') {
        outgoing_batch_send_digest(
            $batchId, $reviewers, $sample, $actor,
            'outgoing_issue_review',
            'issue_review.notify_batch_request_title',
            'issue_review.notify_batch_request_body',
            'issue_review.email_batch_request_intro',
            true, 'issue_review_batch_req_', $note
        );
        outgoing_batch_send_digest(
            $batchId, $approvers, $sample, $actor,
            'outgoing_issue_approval',
            'issue_appr.notify_batch_request_title',
            'issue_appr.notify_batch_request_body',
            'issue_appr.email_batch_request_intro',
            true, 'issue_appr_batch_req_', $note
        );
        return;
    }

    $stakeholders = outgoing_batch_stakeholder_counts($items, (int) ($actor['id'] ?? 0));
    if ($event === 'review_pass') {
        $held = [];
        $forwarded = [];
        foreach ($items as $corr) {
            $st = function_exists('outgoing_approval_status_of') ? outgoing_approval_status_of($corr) : '';
            if ($st === 'pending_copy') {
                $held[] = $corr;
            } else {
                $forwarded[] = $corr;
            }
        }
        if ($held !== []) {
            outgoing_batch_send_digest(
                $batchId,
                outgoing_batch_stakeholder_counts($held, (int) ($actor['id'] ?? 0)),
                $held[0],
                $actor,
                'outgoing_issue_review',
                'issue_review.notify_batch_passed_copy_title',
                'issue_review.notify_batch_passed_copy_body',
                'issue_review.email_batch_passed_copy_intro',
                true,
                'issue_review_batch_copy_',
                $note
            );
        }
        if ($forwarded === []) {
            return;
        }
        $approvers = [];
        foreach ($forwarded as $corr) {
            $st = function_exists('outgoing_approval_status_of') ? outgoing_approval_status_of($corr) : '';
            if (in_array($st, ['pending_sg', 'pending_deputy', 'pending_alternate'], true)) {
                foreach (outgoing_batch_item_approval_recipients($corr) as $uid) {
                    outgoing_batch_digest_add($approvers, $uid);
                }
            }
        }
        $stakeholders = outgoing_batch_stakeholder_counts($forwarded, (int) ($actor['id'] ?? 0));
        $sample = $forwarded[0];
        outgoing_batch_send_digest(
            $batchId, $approvers, $sample, $actor,
            'outgoing_issue_approval',
            'issue_appr.notify_batch_request_title',
            'issue_appr.notify_batch_request_body',
            'issue_appr.email_batch_request_intro',
            true, 'issue_appr_batch_fwd_', $note
        );
        outgoing_batch_send_digest(
            $batchId, $stakeholders, $sample, $actor,
            'outgoing_issue_review',
            'issue_review.notify_batch_passed_title',
            'issue_review.notify_batch_passed_body',
            'issue_review.email_batch_passed_intro',
            false, 'issue_review_batch_pass_', $note
        );
        return;
    }
    if ($event === 'review_return') {
        outgoing_batch_send_digest(
            $batchId, $stakeholders, $sample, $actor,
            'outgoing_issue_review',
            'issue_review.notify_batch_returned_title',
            'issue_review.notify_batch_returned_body',
            'issue_review.email_batch_returned_intro',
            true, 'issue_review_batch_ret_', $note
        );
        return;
    }
    if ($event === 'approved') {
        outgoing_batch_send_digest(
            $batchId, $stakeholders, $sample, $actor,
            'outgoing_issue_approval',
            'issue_appr.notify_batch_approved_title',
            'issue_appr.notify_batch_approved_body',
            'issue_appr.email_batch_approved_intro',
            true, 'issue_appr_batch_ok_', $note
        );
        return;
    }
    if ($event === 'rejected') {
        outgoing_batch_send_digest(
            $batchId, $stakeholders, $sample, $actor,
            'outgoing_issue_approval',
            'issue_appr.notify_batch_rejected_title',
            'issue_appr.notify_batch_rejected_body',
            'issue_appr.email_batch_rejected_intro',
            true, 'issue_appr_batch_no_', $note
        );
    }
}

/** طلب موافقة الإصدار لكل معاملات الدفعة التي ينقصها الطلب */
function outgoing_batch_request_approvals(int $batchId, array $user): int
{
    if (!outgoing_issue_flow_active() || $batchId <= 0) {
        return 0;
    }
    $items = function_exists('outgoing_batch_items')
        ? outgoing_batch_items($batchId, $user)
        : [];
    $n = 0;
    $done = [];
    foreach ($items as $corr) {
        try {
            if (!outgoing_user_can_request_approval($user, $corr)) {
                continue;
            }
            outgoing_approval_request((int) $corr['id'], $user, false);
            $fresh = get_correspondence((int) $corr['id']);
            if ($fresh) {
                $done[] = $fresh;
            }
            $n++;
        } catch (Throwable $e) {
            error_log('DMS batch approval request #' . (int) ($corr['id'] ?? 0) . ': ' . $e->getMessage());
        }
    }
    if ($done !== []) {
        outgoing_batch_notify_cycle($batchId, $done, $user, 'request');
    }
    return $n;
}

/** اعتماد/رفض كل معاملات الدفعة التي يمكن للمستخدم البتّ فيها */
function outgoing_batch_decide(int $batchId, array $user, bool $approve, string $note = ''): int
{
    if (!outgoing_approval_is_configured() || $batchId <= 0) {
        return 0;
    }
    if (!$approve && trim($note) === '') {
        throw new InvalidArgumentException(__('issue_appr.reject_note_required'));
    }
    $items = function_exists('outgoing_batch_items')
        ? outgoing_batch_items($batchId, $user)
        : [];
    $decidable = array_filter(
        $items,
        static fn (array $corr): bool => outgoing_user_can_decide_approval($user, $corr) && !appr_is_admin_decide_override($user, $corr)
    );
    if (trim($note) === '' && array_filter($decidable, static fn (array $corr): bool => outgoing_user_is_alternate_approver($user, $corr)) !== []) {
        throw new InvalidArgumentException(appr__('issue_appr.alternate_reason_required'));
    }
    $n = 0;
    $done = [];
    foreach ($decidable as $corr) {
        try {
            outgoing_approval_decide((int) $corr['id'], $user, $approve, $note, false);
            $fresh = get_correspondence((int) $corr['id']);
            if ($fresh) {
                $done[] = $fresh;
            }
            $n++;
        } catch (Throwable $e) {
            error_log('DMS batch approval decide #' . (int) ($corr['id'] ?? 0) . ': ' . $e->getMessage());
        }
    }
    if ($done !== []) {
        outgoing_batch_notify_cycle($batchId, $done, $user, $approve ? 'approved' : 'rejected', $note);
    }
    return $n;
}

/** مراجعة كل معاملات الدفعة التي يمكن للمستخدم مراجعتها */
function outgoing_batch_review(int $batchId, array $user, bool $pass, string $note = ''): int
{
    if (!outgoing_issue_flow_active() || $batchId <= 0) {
        return 0;
    }
    if (!$pass && trim($note) === '') {
        throw new InvalidArgumentException(appr__('issue_review.note_required'));
    }
    $items = function_exists('outgoing_batch_items')
        ? outgoing_batch_items($batchId, $user)
        : [];
    $n = 0;
    $done = [];
    foreach ($items as $corr) {
        try {
            if (!outgoing_user_can_review($user, $corr) || appr_is_admin_review_override($user, $corr)) {
                continue;
            }
            outgoing_review_decide((int) $corr['id'], $user, $pass, $note, false);
            $fresh = get_correspondence((int) $corr['id']);
            if ($fresh) {
                $done[] = $fresh;
            }
            $n++;
        } catch (Throwable $e) {
            error_log('DMS batch review #' . (int) ($corr['id'] ?? 0) . ': ' . $e->getMessage());
        }
    }
    if ($done !== []) {
        outgoing_batch_notify_cycle($batchId, $done, $user, $pass ? 'review_pass' : 'review_return', $note);
    }
    return $n;
}

/** تشغيل التصعيد للمعاملات المستحقة — يُستدعى من الهيدر بتخفيف حمل */
function outgoing_approval_run_escalations(?array $user = null): void
{
    if (!isset($GLOBALS['_dms_appr_p'])) {
        if ($user && !empty($_SESSION['dms_issue_appr_esc_at']) && (int) $_SESSION['dms_issue_appr_esc_at'] > time() - 120) {
            return;
        }
        if ($user) {
            $_SESSION['dms_issue_appr_esc_at'] = time();
        }
        foreach (appr_types() as $type) {
            appr_with($type, static fn () => outgoing_approval_run_escalations($user));
        }
        return;
    }
    if (!outgoing_approval_is_configured()) {
        return;
    }

    ensure_outgoing_approval_schema();
    try {
        $ids = [];
        $reviewPrimary = outgoing_review_primary_user_id();
        if ($reviewPrimary > 0 && outgoing_review_alternate_user_id() > 0) {
            $stmt = db()->prepare(
                "SELECT id FROM `tblcorrespondence`
                 WHERE type = '" . appr_p() . "'
                   AND issue_approval_status = 'pending_review'
                   AND issue_approval_assignee_id = ?
                   AND issue_approval_requested_at <= (NOW() - INTERVAL ? MINUTE)
                 ORDER BY id ASC
                 LIMIT 30"
            );
            $stmt->execute([$reviewPrimary, outgoing_review_minutes()]);
            $ids = array_merge($ids, $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
        }
        if (outgoing_approval_deputy_escalation_user_id() > 0 || outgoing_approval_alternate_escalation_user_id() > 0) {
            $stmt = db()->prepare(
                "SELECT id FROM `tblcorrespondence`
                 WHERE type = '" . appr_p() . "'
                   AND issue_approval_status = 'pending_sg'
                   AND issue_approval_requested_at IS NOT NULL
                   AND issue_approval_requested_at <= (NOW() - INTERVAL ? MINUTE)
                 ORDER BY id ASC
                 LIMIT 30"
            );
            $stmt->execute([outgoing_approval_minutes_for('primary')]);
            $ids = array_merge($ids, $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
        }
        if (outgoing_approval_alternate_escalation_user_id() > 0) {
            $stmt = db()->prepare(
                "SELECT id FROM `tblcorrespondence`
                 WHERE type = '" . appr_p() . "'
                   AND issue_approval_status = 'pending_deputy'
                   AND issue_approval_escalated_at IS NOT NULL
                   AND issue_approval_escalated_at <= (NOW() - INTERVAL ? MINUTE)
                 ORDER BY id ASC
                 LIMIT 30"
            );
            $stmt->execute([outgoing_approval_minutes_for('deputy')]);
            $ids = array_merge($ids, $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
        }
        $ids = array_unique(array_map('intval', $ids));
        foreach ($ids as $id) {
            outgoing_approval_escalate_if_due((int) $id);
        }
    } catch (Throwable $e) {
        // تجاهل أثناء الإقلاع
    }
}
