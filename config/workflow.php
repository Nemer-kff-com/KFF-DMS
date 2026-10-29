<?php
/**
 * متابعة العمل، البحث الموحد، ومؤشرات التقارير
 */

function correspondence_view_url(array $row): string
{
    $folder = function_exists('correspondence_type_folder')
        ? correspondence_type_folder((string) ($row['type'] ?? 'incoming'))
        : ((($row['type'] ?? '') === 'outgoing') ? 'outgoing' : 'incoming');
    return base_url($folder . '/view.php?id=' . (int) ($row['id'] ?? 0));
}

function correspondence_action_kinds(?array $user = null): array
{
    $kinds = ['no_referral', 'unreplied', 'no_attach', 'unsent', 'no_receipt', 'internal_open', 'internal_no_attach', 'due_soon'];
    if (function_exists('outgoing_user_may_see_approval_queue') && outgoing_user_may_see_approval_queue($user)) {
        array_unshift($kinds, 'issue_approval');
    }
    if (function_exists('outgoing_user_may_see_review_queue') && outgoing_user_may_see_review_queue($user)) {
        array_unshift($kinds, 'issue_review');
    }
    return $kinds;
}

/** أولوية عرض نوع المتابعة عند تعدد الأسباب لنفس المعاملة (الأصغر = الأهم) */
function correspondence_action_kind_rank_sql(string $expr = 'action_kind'): string
{
    return "CASE {$expr}
        WHEN 'issue_review' THEN 0
        WHEN 'issue_approval' THEN 0
        WHEN 'no_referral' THEN 1
        WHEN 'unsent' THEN 1
        WHEN 'internal_open' THEN 1
        WHEN 'unreplied' THEN 2
        WHEN 'no_receipt' THEN 2
        WHEN 'no_attach' THEN 3
        WHEN 'internal_no_attach' THEN 3
        WHEN 'due_soon' THEN 4
        ELSE 9 END";
}

/** شروط SQL لنوع متابعة — بدون AND الافتتاحي */
function correspondence_action_sql(string $kind, ?array $user = null): array
{
    $days = function_exists('outgoing_receipt_alert_days') ? outgoing_receipt_alert_days() : 7;
    $settledIn = correspondence_settled_sql_in();
    $openIn = "'new','in_progress','referred','awaiting_reply'";
    $routedIn = "IFNULL(c.issue_approval_status, '') NOT IN ('pending_review','returned','pending_sg','pending_deputy','pending_alternate','filed','pending_desk')";
    return match ($kind) {
        'issue_review' => function_exists('outgoing_review_awaiting_sql')
            ? outgoing_review_awaiting_sql($user)
            : ['1=0'],
        'issue_approval' => function_exists('outgoing_approval_awaiting_decision_sql')
            ? outgoing_approval_awaiting_decision_sql($user)
            : ['1=0'],
        'no_referral' => [
            "c.type = 'incoming'",
            "c.status IN ('new','in_progress')",
            $routedIn,
            "NOT EXISTS (SELECT 1 FROM `tblreferrals` r WHERE r.correspondence_id = c.id)",
        ],
        'unreplied' => [
            "c.type = 'incoming'",
            "c.status NOT IN ('replied','closed','cancelled')",
            $routedIn,
            "NOT EXISTS (
                SELECT 1 FROM `tblcorrespondence` o
                WHERE o.type = 'outgoing' AND o.status <> 'cancelled'
                  AND " . outgoing_prep_exclude_sql('o') . "
                  AND (o.linked_id = c.id OR c.linked_id = o.id)
            )",
        ],
        'no_attach' => [
            "c.type = 'incoming'",
            "c.status IN ({$openIn})",
            "NOT EXISTS (SELECT 1 FROM `tbldocuments` d WHERE d.correspondence_id = c.id)",
        ],
        'unsent' => [
            "c.type = 'outgoing'",
            outgoing_prep_exclude_sql('c'),
            "c.status NOT IN ('delivered','closed','cancelled')",
            "NOT EXISTS (
                SELECT 1 FROM `tblcorr_deliveries` d
                WHERE d.correspondence_id = c.id AND d.status = 'sent'
            )",
        ],
        'no_receipt' => [
            "c.type = 'outgoing'",
            outgoing_prep_exclude_sql('c'),
            "(c.receipt_name IS NULL OR c.receipt_name = '')",
            "c.status NOT IN ('delivered','closed','cancelled')",
            'c.corr_date <= DATE_SUB(CURDATE(), INTERVAL ' . (int) $days . ' DAY)',
        ],
        'internal_open' => [
            "c.type = 'internal'",
            "c.status NOT IN ({$settledIn})",
            $routedIn,
        ],
        'internal_no_attach' => [
            "c.type = 'internal'",
            "c.status IN ({$openIn})",
            "NOT EXISTS (SELECT 1 FROM `tbldocuments` d WHERE d.correspondence_id = c.id)",
        ],
        'due_soon' => [
            "c.type IN ('incoming','outgoing','internal')",
            '(c.type <> \'outgoing\' OR ' . outgoing_prep_exclude_sql('c') . ')',
            'c.reply_due IS NOT NULL',
            'c.reply_due BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 2 DAY)',
            "c.status NOT IN ({$settledIn})",
        ],
        default => ['1=0'],
    };
}

function correspondence_action_counts(?array $user = null): array
{
    $user = $user ?? current_user();
    [$scopeSql, $scopeParams] = department_scope_sql($user, 'c');
    $scopeAnd = $scopeSql !== '' ? ' AND ' . $scopeSql : '';
    $pdo = db();
    $out = [];
    foreach (correspondence_action_kinds($user) as $kind) {
        $parts = correspondence_action_sql($kind, $user);
        // موافقة الإصدار: حسب المعيَّن فقط — بلا قيد قسم
        $kindScopeAnd = in_array($kind, ['issue_approval', 'issue_review'], true) ? '' : $scopeAnd;
        $kindScopeParams = in_array($kind, ['issue_approval', 'issue_review'], true) ? [] : $scopeParams;
        $sql = 'SELECT COUNT(*) FROM `tblcorrespondence` c WHERE ' . implode(' AND ', $parts) . $kindScopeAnd;
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($kindScopeParams);
            $cnt = (int) $stmt->fetchColumn();
        } catch (Throwable $e) {
            $cnt = 0;
        }
        $out[$kind] = $cnt;
    }

    // الإجمالي = معاملات مميزة تحتاج إجراء (وليس مجموع الأنواع؛ المعاملة قد تطابق أكثر من نوع)
    $total = 0;
    try {
        [$unionSql, $params] = correspondence_action_union_sql($user, null);
        if ($unionSql !== '') {
            $stmt = $pdo->prepare("SELECT COUNT(DISTINCT id) FROM ({$unionSql}) AS action_rows");
            $stmt->execute($params);
            $total = (int) $stmt->fetchColumn();
        }
    } catch (Throwable $e) {
        $total = (int) array_sum($out);
    }
    $out['total'] = $total;
    return $out;
}

/**
 * @return list<array<string,mixed>>
 */
function correspondence_action_items(?array $user = null, int $perKind = 8): array
{
    $user = $user ?? current_user();
    [$scopeSql, $scopeParams] = department_scope_sql($user, 'c');
    $scopeAnd = $scopeSql !== '' ? ' AND ' . $scopeSql : '';
    $perKind = max(1, min(30, $perKind));
    $items = [];
    $pdo = db();
    if (function_exists('ensure_correspondence_party_snapshot_schema')) {
        ensure_correspondence_party_snapshot_schema();
    }
    foreach (correspondence_action_kinds($user) as $kind) {
        $parts = correspondence_action_sql($kind, $user);
        $kindScopeAnd = in_array($kind, ['issue_approval', 'issue_review'], true) ? '' : $scopeAnd;
        $kindScopeParams = in_array($kind, ['issue_approval', 'issue_review'], true) ? [] : $scopeParams;
        $sql = "SELECT c.id, c.type, c.ref_number, c.subject, c.party_name, c.status, c.reply_due, c.corr_date,
                       c.snap_title_prefix, c.snap_person_name, c.snap_honorific, c.snap_org_name,
                       d.name AS department_name, d.name_en AS department_name_en,
                       d.title_prefix AS department_title_prefix, d.directed_from AS department_directed_from
                FROM `tblcorrespondence` c
                LEFT JOIN `tbldepartments` d ON d.id = c.department_id
                WHERE " . implode(' AND ', $parts) . $kindScopeAnd . "
                ORDER BY COALESCE(c.reply_due, c.corr_date) ASC, c.id ASC
                LIMIT {$perKind}";
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($kindScopeParams);
            foreach ($stmt->fetchAll() as $row) {
                $row['action_kind'] = $kind;
                $items[] = correspondence_apply_ref_display($row);
            }
        } catch (Throwable $e) {
            continue;
        }
    }
    return $items;
}

/**
 * استعلام UNION لكل أنواع «يحتاج إجراء»
 * @return array{0:string,1:list<mixed>}
 */
function correspondence_action_union_sql(?array $user = null, ?string $type = null): array
{
    $user = $user ?? current_user();
    if (function_exists('ensure_correspondence_party_snapshot_schema')) {
        ensure_correspondence_party_snapshot_schema();
    }
    [$scopeSql, $scopeParams] = department_scope_sql($user, 'c');
    $scopeAnd = $scopeSql !== '' ? ' AND ' . $scopeSql : '';
    $unions = [];
    $params = [];
    $incomingKinds = ['issue_review', 'issue_approval', 'no_referral', 'unreplied', 'no_attach', 'due_soon'];
    $outgoingKinds = ['issue_review', 'issue_approval', 'unsent', 'no_receipt', 'due_soon'];
    $internalKinds = ['internal_open', 'internal_no_attach', 'due_soon'];

    foreach (correspondence_action_kinds($user) as $kind) {
        if ($type === 'incoming' && !in_array($kind, $incomingKinds, true)) {
            continue;
        }
        if ($type === 'outgoing' && !in_array($kind, $outgoingKinds, true)) {
            continue;
        }
        if ($type === 'internal' && !in_array($kind, $internalKinds, true)) {
            continue;
        }
        $parts = correspondence_action_sql($kind, $user);
        if ($type === 'incoming' || $type === 'outgoing' || $type === 'internal') {
            $parts[] = 'c.type = ?';
        }
        $kindScopeAnd = in_array($kind, ['issue_approval', 'issue_review'], true) ? '' : $scopeAnd;
        $kindScopeParams = in_array($kind, ['issue_approval', 'issue_review'], true) ? [] : $scopeParams;
        $unions[] = "(SELECT c.id, c.type, c.ref_number, c.subject, c.party_name, c.status, c.reply_due, c.corr_date,
                             c.snap_title_prefix, c.snap_person_name, c.snap_honorific, c.snap_org_name,
                             d.name AS department_name, d.name_en AS department_name_en,
                             d.title_prefix AS department_title_prefix, d.directed_from AS department_directed_from,
                             ? AS action_kind
                      FROM `tblcorrespondence` c
                      LEFT JOIN `tbldepartments` d ON d.id = c.department_id
                      WHERE " . implode(' AND ', $parts) . $kindScopeAnd . ")";
        $params[] = $kind;
        if ($type === 'incoming' || $type === 'outgoing' || $type === 'internal') {
            $params[] = $type;
        }
        foreach ($kindScopeParams as $p) {
            $params[] = $p;
        }
    }

    return [implode(' UNION ALL ', $unions), $params];
}

/**
 * @return array{items:list<array<string,mixed>>,pagination:array<string,mixed>}
 */
function correspondence_action_items_page(?array $user = null, int $page = 1, int $perPage = 10, ?string $type = null): array
{
    $user = $user ?? current_user();
    $page = max(1, $page);
    $perPage = max(1, min(50, $perPage));
    $empty = ['items' => [], 'pagination' => dms_pagination_meta(0, $page, $perPage)];

    try {
        [$unionSql, $params] = correspondence_action_union_sql($user, $type);
        if ($unionSql === '') {
            return $empty;
        }

        $pdo = db();
        $stmt = $pdo->prepare(
            "SELECT * FROM ({$unionSql}) AS action_rows
             ORDER BY COALESCE(reply_due, corr_date) ASC, id ASC"
        );
        $stmt->execute($params);
        $all = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // معاملة واحدة = صف واحد (أهم سبب متابعة فقط)
        $prio = [
            'issue_review' => 0,
            'issue_approval' => 0,
            'no_referral' => 1,
            'unsent' => 1,
            'internal_open' => 1,
            'unreplied' => 2,
            'no_receipt' => 2,
            'no_attach' => 3,
            'internal_no_attach' => 3,
            'due_soon' => 4,
        ];
        $best = [];
        foreach ($all as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $rank = $prio[(string) ($row['action_kind'] ?? '')] ?? 9;
            if (!isset($best[$id]) || $rank < (int) ($best[$id]['_rank'] ?? 99)) {
                $row['_rank'] = $rank;
                $best[$id] = $row;
            }
        }
        $items = array_values($best);
        usort($items, static function (array $a, array $b): int {
            $da = (string) ($a['reply_due'] ?? $a['corr_date'] ?? '');
            $db = (string) ($b['reply_due'] ?? $b['corr_date'] ?? '');
            return [$da, (int) ($a['id'] ?? 0)] <=> [$db, (int) ($b['id'] ?? 0)];
        });

        $total = count($items);
        $meta = dms_pagination_meta($total, $page, $perPage);
        $slice = array_slice($items, (int) $meta['offset'], (int) $meta['per_page']);
        foreach ($slice as &$row) {
            unset($row['_rank']);
            $row = correspondence_apply_ref_display($row);
        }
        unset($row);

        return ['items' => $slice, 'pagination' => $meta];
    } catch (Throwable $e) {
        return $empty;
    }
}

function correspondence_reply_outgoing(?array $corr): ?array
{
    if (!$corr || ($corr['type'] ?? '') !== 'incoming') {
        return null;
    }
    $linkedId = (int) ($corr['linked_id'] ?? 0);
    if ($linkedId > 0 && ($corr['linked_type'] ?? '') === 'outgoing') {
        $linked = get_correspondence($linkedId);
        if ($linked && !correspondence_is_cancelled($linked)) {
            return [
                'id' => $linkedId,
                'ref_number' => format_ref_number($linked['ref_number'] ?? $corr['linked_ref'] ?? ''),
            ];
        }
    }
    try {
        $stmt = db()->prepare(
            "SELECT id, ref_number FROM `tblcorrespondence`
             WHERE type = 'outgoing' AND status <> 'cancelled' AND (id = ? OR linked_id = ?)
             ORDER BY id DESC LIMIT 1"
        );
        $id = (int) ($corr['id'] ?? 0);
        $stmt->execute([$linkedId ?: $id, $id]);
        $row = $stmt->fetch();
        return $row ? correspondence_apply_ref_display($row) : null;
    } catch (Throwable $e) {
        return null;
    }
}

function correspondence_type_badge_class(string $type): string
{
    if ($type === 'outgoing') {
        return 'text-bg-secondary';
    }
    if ($type === 'internal') {
        return 'text-bg-info';
    }
    return 'text-bg-primary';
}

/**
 * سلسلة المعاملات المرتبطة ثنائياً عبر linked_id.
 *
 * @return list<array<string,mixed>>
 */
function correspondence_link_group(int $id, ?array $user = null, int $max = 40): array
{
    if ($id <= 0) {
        return [];
    }
    $seen = [];
    $queue = [$id];
    $rows = [];
    while ($queue !== [] && count($seen) < $max) {
        $cid = (int) array_shift($queue);
        if ($cid <= 0 || isset($seen[$cid])) {
            continue;
        }
        $seen[$cid] = true;
        $row = function_exists('get_correspondence') ? get_correspondence($cid) : null;
        if (!$row) {
            continue;
        }
        if ($user && function_exists('user_can_access_correspondence') && !user_can_access_correspondence($row, $user)) {
            continue;
        }
        $rows[] = $row;
        $lid = (int) ($row['linked_id'] ?? 0);
        if ($lid > 0 && !isset($seen[$lid])) {
            $queue[] = $lid;
        }
        try {
            $st = db()->prepare('SELECT id FROM `tblcorrespondence` WHERE linked_id = ?');
            $st->execute([$cid]);
            foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $oid) {
                $oid = (int) $oid;
                if ($oid > 0 && !isset($seen[$oid])) {
                    $queue[] = $oid;
                }
            }
        } catch (Throwable $e) {
        }
    }
    usort($rows, static function (array $a, array $b): int {
        $da = (string) ($a['corr_date'] ?? '');
        $db = (string) ($b['corr_date'] ?? '');
        if ($da !== $db) {
            return $da <=> $db;
        }
        return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
    });

    return $rows;
}

/**
 * @param list<int> $excludeIds
 * @return list<array<string,mixed>>
 */
function correspondence_same_subject_rows(string $subject, ?array $user = null, array $excludeIds = [], int $limit = 30, string $type = ''): array
{
    $subject = trim($subject);
    if ($subject === '') {
        return [];
    }
    $type = in_array($type, ['incoming', 'outgoing', 'internal'], true) ? $type : '';
    $excludeIds = array_values(array_unique(array_filter(array_map('intval', $excludeIds))));
    $limit = max(1, min(80, $limit));
    try {
        [$scopeSql, $scopeParams] = function_exists('department_scope_sql')
            ? department_scope_sql($user, 'c')
            : ['', []];
        $sql = "SELECT c.id, c.type, c.ref_number, c.subject, c.party_name, c.corr_date, c.status,
                       c.cancel_kind, c.outgoing_prep_status, c.linked_id
                FROM `tblcorrespondence` c
                WHERE c.subject = ? AND c.status <> 'cancelled'";
        $params = [$subject];
        if ($type !== '') {
            $sql .= ' AND c.type = ?';
            $params[] = $type;
        }
        if ($excludeIds !== []) {
            $ph = implode(',', array_fill(0, count($excludeIds), '?'));
            $sql .= " AND c.id NOT IN ($ph)";
            foreach ($excludeIds as $eid) {
                $params[] = $eid;
            }
        }
        if ($scopeSql !== '') {
            $sql .= ' AND ' . $scopeSql;
            foreach ($scopeParams as $p) {
                $params[] = $p;
            }
        }
        $sql .= ' ORDER BY c.corr_date DESC, c.id DESC LIMIT ' . $limit;
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        $out = [];
        foreach ($rows as $row) {
            if ($user && function_exists('user_can_access_correspondence') && !user_can_access_correspondence($row, $user)) {
                continue;
            }
            $out[] = function_exists('correspondence_apply_ref_display')
                ? correspondence_apply_ref_display($row)
                : $row;
        }
        return $out;
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * أزواج مرتبط (وارد/صادر/داخلي) للمراجعة.
 *
 * @return list<array{left:array<string,mixed>,right:array<string,mixed>}>
 */
function correspondence_linked_pair_list(?array $user = null, int $limit = 60): array
{
    $limit = max(1, min(120, $limit));
    try {
        [$cScope, $cParams] = function_exists('department_scope_sql')
            ? department_scope_sql($user, 'c')
            : ['', []];
        [$lScope, $lParams] = function_exists('department_scope_sql')
            ? department_scope_sql($user, 'l')
            : ['', []];
        $prepSql = '';
        if (function_exists('dms_db_column_exists') && dms_db_column_exists('tblcorrespondence', 'outgoing_prep_status')) {
            $prepSql = " AND (c.outgoing_prep_status IS NULL OR c.outgoing_prep_status NOT IN ('draft','pending_signer','superseded'))
                         AND (l.outgoing_prep_status IS NULL OR l.outgoing_prep_status NOT IN ('draft','pending_signer','superseded'))";
        }
        $sql = "SELECT c.id AS a_id, c.type AS a_type, c.ref_number AS a_ref, c.subject AS a_subject,
                       c.corr_date AS a_date, c.status AS a_status, c.cancel_kind AS a_cancel,
                       l.id AS b_id, l.type AS b_type, l.ref_number AS b_ref, l.subject AS b_subject,
                       l.corr_date AS b_date, l.status AS b_status, l.cancel_kind AS b_cancel
                FROM `tblcorrespondence` c
                INNER JOIN `tblcorrespondence` l ON l.id = c.linked_id
                WHERE c.linked_id IS NOT NULL
                  AND c.type IN ('incoming','outgoing') AND l.type IN ('incoming','outgoing')
                  AND c.status <> 'cancelled' AND l.status <> 'cancelled'" . $prepSql;
        $params = [];
        if ($cScope !== '') {
            $sql .= " AND (($cScope) OR ($lScope))";
            foreach ($cParams as $p) {
                $params[] = $p;
            }
            foreach ($lParams as $p) {
                $params[] = $p;
            }
        }
        $sql .= ' ORDER BY GREATEST(c.id, l.id) DESC LIMIT 200';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $seen = [];
        $pairs = [];
        foreach ($stmt->fetchAll() as $row) {
            $aId = (int) ($row['a_id'] ?? 0);
            $bId = (int) ($row['b_id'] ?? 0);
            if ($aId <= 0 || $bId <= 0) {
                continue;
            }
            $key = $aId < $bId ? $aId . ':' . $bId : $bId . ':' . $aId;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $left = correspondence_apply_ref_display([
                'id' => $aId,
                'type' => (string) ($row['a_type'] ?? ''),
                'ref_number' => (string) ($row['a_ref'] ?? ''),
                'subject' => (string) ($row['a_subject'] ?? ''),
                'corr_date' => $row['a_date'] ?? null,
                'status' => (string) ($row['a_status'] ?? ''),
                'cancel_kind' => $row['a_cancel'] ?? null,
            ]);
            $right = correspondence_apply_ref_display([
                'id' => $bId,
                'type' => (string) ($row['b_type'] ?? ''),
                'ref_number' => (string) ($row['b_ref'] ?? ''),
                'subject' => (string) ($row['b_subject'] ?? ''),
                'corr_date' => $row['b_date'] ?? null,
                'status' => (string) ($row['b_status'] ?? ''),
                'cancel_kind' => $row['b_cancel'] ?? null,
            ]);
            $pairs[] = ['left' => $left, 'right' => $right];
            if (count($pairs) >= $limit) {
                break;
            }
        }
        return $pairs;
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * بحث بالموضوع عبر الصادر والوارد والداخلي.
 *
 * @return list<array<string,mixed>>
 */
function correspondence_topic_search(string $q, ?array $user = null, int $limit = 40, string $type = ''): array
{
    $q = trim($q);
    if ($q === '' || mb_strlen($q) < 2) {
        return [];
    }
    $type = in_array($type, ['incoming', 'outgoing', 'internal'], true) ? $type : '';
    $limit = max(5, min(80, $limit));
    try {
        [$scopeSql, $scopeParams] = function_exists('department_scope_sql')
            ? department_scope_sql($user, 'c')
            : ['', []];
        $like = '%' . $q . '%';
        $sql = "SELECT c.id, c.type, c.ref_number, c.subject, c.party_name, c.corr_date, c.status,
                       c.cancel_kind, c.outgoing_prep_status, c.linked_id
                FROM `tblcorrespondence` c
                WHERE (c.subject LIKE ? OR c.ref_number LIKE ?)
                  AND c.status <> 'cancelled'";
        $params = [$like, $like];
        if ($type !== '') {
            $sql .= ' AND c.type = ?';
            $params[] = $type;
        }
        if ($scopeSql !== '') {
            $sql .= ' AND ' . $scopeSql;
            foreach ($scopeParams as $p) {
                $params[] = $p;
            }
        }
        $sql .= ' ORDER BY c.corr_date DESC, c.id DESC LIMIT ' . $limit;
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            if ($user && function_exists('user_can_access_correspondence') && !user_can_access_correspondence($row, $user)) {
                continue;
            }
            $out[] = function_exists('correspondence_apply_ref_display')
                ? correspondence_apply_ref_display($row)
                : $row;
        }
        return $out;
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * بحث موحد في الصادر والوارد وأرشيف الوثائق
 * @return array{correspondence:list,archive:list}
 */
function dms_unified_search(string $q, ?array $user = null, int $limit = 40): array
{
    $q = trim($q);
    $user = $user ?? current_user();
    $limit = max(5, min(80, $limit));
    $out = ['correspondence' => [], 'archive' => []];
    if ($q === '' || mb_strlen($q) < 2) {
        return $out;
    }
    $like = '%' . $q . '%';

    if (function_exists('ensure_correspondence_party_snapshot_schema')) {
        ensure_correspondence_party_snapshot_schema();
    }

    [$scopeSql, $scopeParams] = department_scope_sql($user, 'c');
    $sql = "SELECT c.id, c.type, c.ref_number, c.subject, c.party_name, c.corr_date, c.status,
                   c.snap_title_prefix, c.snap_person_name, c.snap_honorific, c.snap_org_name,
                   d.name AS department_name, d.name_en AS department_name_en,
                   d.title_prefix AS department_title_prefix, d.directed_from AS department_directed_from
            FROM `tblcorrespondence` c
            LEFT JOIN `tbldepartments` d ON d.id = c.department_id
            WHERE (c.ref_number LIKE ? OR c.subject LIKE ? OR c.party_name LIKE ? OR c.external_ref LIKE ?
                OR c.snap_person_name LIKE ? OR c.snap_org_name LIKE ?)";
    $params = [$like, $like, $like, $like, $like, $like];
    if ($scopeSql !== '') {
        $sql .= ' AND ' . $scopeSql;
        foreach ($scopeParams as $p) {
            $params[] = $p;
        }
    }
    $sql .= " ORDER BY c.id DESC LIMIT {$limit}";
    try {
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $out['correspondence'] = array_map('correspondence_apply_ref_display', $stmt->fetchAll());
    } catch (Throwable $e) {
        $out['correspondence'] = [];
    }

    if (function_exists('user_can_view_archive_module') && user_can_view_archive_module($user)) {
        $out['archive'] = [];
        if (function_exists('user_can_archive_section') && user_can_archive_section('general', 'view', $user) && function_exists('archive_list')) {
            $out['archive'] = archive_list(['q' => $q], $user);
        }
        if (function_exists('archive_register_search')) {
            foreach (archive_register_search($q, $limit) as $reg) {
                $out['archive'][] = [
                    'id' => (int) $reg['id'],
                    'archive_no' => $reg['archive_no'],
                    'title' => $reg['title'],
                    'party_name' => $reg['party_name'],
                    'doc_date' => null,
                    'view_url' => $reg['view_url'],
                    'department_name' => '',
                    'department_name_en' => '',
                ];
            }
        }
        if (count($out['archive']) > $limit) {
            $out['archive'] = array_slice($out['archive'], 0, $limit);
        }
    }
    return $out;
}

/**
 * مؤشرات إدارية إضافية لتقرير الفترة
 * @return array{
 *   avg_close_days:float|null,
 *   missing_receipt:int,
 *   unreplied:int,
 *   unsent:int,
 *   rsvp_attending:int,
 *   rsvp_declined:int,
 *   rsvp_pending:int,
 *   by_dept:list
 * }
 */
function correspondence_report_extras(string $whereSql, array $params): array
{
    $pdo = db();
    $empty = [
        'avg_close_days' => null,
        'missing_receipt' => 0,
        'unreplied' => 0,
        'unsent' => 0,
        'rsvp_attending' => 0,
        'rsvp_declined' => 0,
        'rsvp_pending' => 0,
        'rsvp_total' => 0,
        'by_dept' => [],
    ];
    try {
        $stmt = $pdo->prepare(
            "SELECT AVG(DATEDIFF(c.updated_at, c.corr_date))
             FROM `tblcorrespondence` c
             WHERE $whereSql AND c.status IN ('replied','delivered','closed')"
        );
        $stmt->execute($params);
        $avg = $stmt->fetchColumn();
        $empty['avg_close_days'] = $avg !== null && $avg !== false ? round((float) $avg, 1) : null;

        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM `tblcorrespondence` c
             WHERE $whereSql AND c.type = 'outgoing'
               AND (c.receipt_name IS NULL OR c.receipt_name = '')
               AND c.status NOT IN ('delivered','closed','cancelled')"
        );
        $stmt->execute($params);
        $empty['missing_receipt'] = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM `tblcorrespondence` c
             WHERE $whereSql AND c.type = 'incoming'
               AND c.status NOT IN ('replied','closed','cancelled')
               AND NOT EXISTS (
                    SELECT 1 FROM `tblcorrespondence` o
                    WHERE o.type = 'outgoing' AND o.status <> 'cancelled' AND (o.linked_id = c.id OR c.linked_id = o.id)
               )"
        );
        $stmt->execute($params);
        $empty['unreplied'] = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM `tblcorrespondence` c
             WHERE $whereSql AND c.type = 'outgoing'
               AND c.status NOT IN ('delivered','closed','cancelled')
               AND NOT EXISTS (
                    SELECT 1 FROM `tblcorr_deliveries` d
                    WHERE d.correspondence_id = c.id AND d.status = 'sent'
               )"
        );
        $stmt->execute($params);
        $empty['unsent'] = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT
                SUM(c.rsvp_response = 'attending') AS attending,
                SUM(c.rsvp_response = 'declined') AS declined,
                SUM(c.rsvp_required = 1 AND c.rsvp_response IS NULL) AS pending
             FROM `tblcorrespondence` c
             WHERE $whereSql AND c.type = 'outgoing' AND c.rsvp_required = 1"
        );
        $stmt->execute($params);
        $rsvp = $stmt->fetch() ?: [];
        $empty['rsvp_attending'] = (int) ($rsvp['attending'] ?? 0);
        $empty['rsvp_declined'] = (int) ($rsvp['declined'] ?? 0);
        $empty['rsvp_pending'] = (int) ($rsvp['pending'] ?? 0);
        $empty['rsvp_total'] = $empty['rsvp_attending'] + $empty['rsvp_declined'] + $empty['rsvp_pending'];

        $stmt = $pdo->prepare(
            "SELECT d.name, d.name_en,
                    SUM(c.type = 'incoming') AS incoming_cnt,
                    SUM(c.type = 'outgoing') AS outgoing_cnt,
                    SUM(c.type = 'internal') AS internal_cnt,
                    COUNT(*) AS cnt
             FROM `tblcorrespondence` c
             LEFT JOIN `tbldepartments` d ON d.id = c.department_id
             WHERE $whereSql
             GROUP BY c.department_id, d.name, d.name_en
             ORDER BY cnt DESC"
        );
        $stmt->execute($params);
        $empty['by_dept'] = $stmt->fetchAll();
    } catch (Throwable $e) {
        error_log('DMS report extras: ' . $e->getMessage());
    }
    return $empty;
}

function entity_email_address(?array $entity): string
{
    if (!$entity) {
        return '';
    }
    $raw = trim((string) ($entity['email'] ?? ''));
    if ($raw === '') {
        $raw = trim((string) ($entity['entity_email'] ?? ''));
    }
    $raw = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}\x{00A0}]/u', '', $raw) ?? $raw;
    $raw = str_replace(['،', ';'], [',', ','], $raw);
    foreach (preg_split('/[,\s]+/', $raw) ?: [] as $part) {
        $part = trim($part, " \t<>\"'");
        if ($part !== '' && filter_var($part, FILTER_VALIDATE_EMAIL)) {
            return $part;
        }
    }
    return '';
}

function entity_has_email(?array $entity): bool
{
    return entity_email_address($entity) !== '';
}
