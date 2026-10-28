<?php
/**
 * ورقة عرض المعاملة (وارد أو صادر) — طبقة دراسة وتوجيه فوق القيد.
 * لا تغيّر ترقيم الصادر/الوارد ولا حالات المعاملة ولا طباعة الخطاب.
 */

function ensure_briefing_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $pdo = db();
        $exists = dms_db_table_exists('tblcorrespondence_briefs');
        if (!$exists) {
            $pdo->exec(
                "CREATE TABLE `tblcorrespondence_briefs` (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    correspondence_id INT UNSIGNED NOT NULL,
                    receipt_date DATE NULL DEFAULT NULL,
                    basis_number VARCHAR(120) NULL DEFAULT NULL,
                    basis_date DATE NULL DEFAULT NULL,
                    directed_to VARCHAR(255) NULL DEFAULT NULL,
                    directed_from VARCHAR(255) NULL DEFAULT NULL,
                    attachments_desc TEXT NULL DEFAULT NULL,
                    request_text TEXT NULL DEFAULT NULL,
                    basis_text TEXT NULL DEFAULT NULL,
                    study_text TEXT NULL DEFAULT NULL,
                    recommendation_text TEXT NULL DEFAULT NULL,
                    direction_intro TEXT NULL DEFAULT NULL,
                    options_json TEXT NULL DEFAULT NULL,
                    decision_note TEXT NULL DEFAULT NULL,
                    decided TINYINT(1) NOT NULL DEFAULT 0,
                    decided_at DATETIME NULL DEFAULT NULL,
                    decided_by INT UNSIGNED NULL DEFAULT NULL,
                    approver_name VARCHAR(255) NULL DEFAULT NULL,
                    approver_title VARCHAR(255) NULL DEFAULT NULL,
                    created_by INT UNSIGNED NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_brief_corr (correspondence_id),
                    CONSTRAINT fk_brief_corr FOREIGN KEY (correspondence_id) REFERENCES `tblcorrespondence`(id) ON DELETE CASCADE,
                    CONSTRAINT fk_brief_user FOREIGN KEY (created_by) REFERENCES `tblusers`(id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }
    } catch (Throwable $e) {
        // قاعدة غير جاهزة
    }
}

function briefing_parse_date(?string $raw): ?string
{
    $raw = trim((string) $raw);
    if ($raw === '') {
        return null;
    }
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $m)) {
        return $m[1] . '-' . $m[2] . '-' . $m[3];
    }
    if (preg_match('/^(\d{4})[\/.](\d{1,2})[\/.](\d{1,2})$/', $raw, $m)) {
        return sprintf('%04d-%02d-%02d', (int) $m[1], (int) $m[2], (int) $m[3]);
    }
    if (function_exists('hijri_to_gregorian_date')) {
        $hijri = hijri_to_gregorian_date($raw);
        if ($hijri !== '') {
            return $hijri;
        }
    }
    $ts = strtotime(str_replace('/', '-', $raw));
    return $ts ? date('Y-m-d', $ts) : null;
}

function briefing_party_line(array $part): string
{
    $fields = briefing_party_fields($part);
    $bits = array_values(array_filter([
        $fields['name'],
        $fields['job'],
        $fields['org'],
    ], static fn(string $v): bool => $v !== ''));

    return implode(' — ', $bits);
}

/** @return array{name:string,job:string,org:string} */
function briefing_party_fields(array $part, string $stored = ''): array
{
    if (trim($stored) !== '') {
        $bits = preg_split('/\s+[—–-]\s+/u', trim($stored)) ?: [];
        $bits = array_values(array_map('trim', $bits));
        $name = (string) ($bits[0] ?? trim($stored));
        $job = (string) ($bits[1] ?? '');
        $org = (string) ($bits[2] ?? '');
        if (isset($bits[3])) {
            $org = trim($org . ' — ' . implode(' — ', array_slice($bits, 3)));
        }
        return ['name' => $name, 'job' => $job, 'org' => $org];
    }
    $name = trim(implode(' ', array_filter([
        trim((string) ($part['prefix'] ?? '')),
        trim((string) ($part['person'] ?? '')),
        trim((string) ($part['honorific'] ?? '')),
    ], static fn(string $v): bool => $v !== '')));
    return [
        'name' => $name,
        'job' => trim((string) ($part['job'] ?? '')),
        'org' => trim((string) ($part['org'] ?? '')),
    ];
}

function briefing_compose_party_line(array $data, string $side): string
{
    $side = $side === 'from' ? 'from' : 'to';
    $nameKey = 'directed_' . $side . '_name';
    $jobKey = 'directed_' . $side . '_job';
    $orgKey = 'directed_' . $side . '_org';
    if (!isset($data[$nameKey]) && !isset($data[$jobKey]) && !isset($data[$orgKey])) {
        return trim((string) ($data['directed_' . $side] ?? ''));
    }
    return briefing_party_line([
        'prefix' => '',
        'person' => trim((string) ($data[$nameKey] ?? '')),
        'honorific' => '',
        'job' => trim((string) ($data[$jobKey] ?? '')),
        'org' => trim((string) ($data[$orgKey] ?? '')),
    ]);
}

function briefing_strip_rec_num(string $line): string
{
    return trim((string) preg_replace('/^\d+\s*[-.)]\s*/u', '', $line));
}

function briefing_parse_recommendation($raw): array
{
    $empty = ['intro' => '', 'items' => []];
    if (is_array($raw)) {
        $intro = trim((string) ($raw['intro'] ?? $raw['recommendation_intro'] ?? ''));
        $items = [];
        $src = $raw['items'] ?? $raw['rec_item'] ?? [];
        if (!is_array($src)) {
            $src = [];
        }
        foreach ($src as $item) {
            $item = briefing_strip_rec_num(trim((string) $item));
            if ($item !== '') {
                $items[] = $item;
            }
        }

        return ['intro' => $intro, 'items' => $items];
    }
    $raw = trim((string) $raw);
    if ($raw === '') {
        return $empty;
    }
    if (isset($raw[0]) && $raw[0] === '{') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded) && (array_key_exists('intro', $decoded) || array_key_exists('items', $decoded))) {
            return briefing_parse_recommendation($decoded);
        }
    }
    $lines = preg_split('/\R/u', $raw) ?: [];
    $lines = array_values(array_filter(array_map('trim', $lines), static fn(string $line): bool => $line !== ''));
    if ($lines === []) {
        return $empty;
    }
    if (preg_match('/^\d+\s*[-.)]\s*/u', $lines[0])) {
        $items = [];
        foreach ($lines as $line) {
            $line = briefing_strip_rec_num($line);
            if ($line !== '') {
                $items[] = $line;
            }
        }

        return ['intro' => '', 'items' => $items];
    }
    $intro = $lines[0];
    $items = [];
    foreach (array_slice($lines, 1) as $line) {
        $line = briefing_strip_rec_num($line);
        if ($line !== '') {
            $items[] = $line;
        }
    }

    return ['intro' => $intro, 'items' => $items];
}

function briefing_encode_recommendation(array $data): ?string
{
    $parts = briefing_parse_recommendation([
        'intro' => $data['recommendation_intro'] ?? '',
        'items' => $data['rec_item'] ?? $data['recommendation_items'] ?? [],
    ]);
    if ($parts['intro'] === '' && $parts['items'] === []) {
        $legacy = trim((string) ($data['recommendation_text'] ?? ''));
        if ($legacy === '') {
            return null;
        }
        $parts = briefing_parse_recommendation($legacy);
        if ($parts['intro'] === '' && $parts['items'] === []) {
            return null;
        }
    }

    return json_encode($parts, JSON_UNESCAPED_UNICODE);
}

function briefing_recommendation_has_content(array $parts): bool
{
    return trim((string) ($parts['intro'] ?? '')) !== '' || ($parts['items'] ?? []) !== [];
}

function briefing_recommendation_plain(array $parts): string
{
    $out = [];
    $intro = trim((string) ($parts['intro'] ?? ''));
    if ($intro !== '') {
        $out[] = $intro;
    }
    foreach ($parts['items'] ?? [] as $i => $item) {
        $item = trim((string) $item);
        if ($item !== '') {
            $out[] = ($i + 1) . '- ' . $item;
        }
    }

    return implode("\n", $out);
}

function briefing_default_options(): array
{
    return [
        ['label' => __('brief.opt_approve'), 'selected' => false],
        ['label' => __('brief.opt_board'), 'selected' => false],
    ];
}

function briefing_normalize_options($raw): array
{
    $rows = [];
    if (is_string($raw) && $raw !== '') {
        $decoded = json_decode($raw, true);
        $raw = is_array($decoded) ? $decoded : [];
    }
    if (!is_array($raw)) {
        $raw = [];
    }
    foreach ($raw as $item) {
        if (is_string($item)) {
            $label = trim($item);
            $selected = false;
        } else {
            $label = trim((string) ($item['label'] ?? ''));
            $selected = !empty($item['selected']);
        }
        if ($label === '') {
            continue;
        }
        $rows[] = ['label' => $label, 'selected' => $selected];
    }

    return $rows;
}

function briefing_options_from_request(array $data, bool $forDecide): array
{
    $labels = $data['option_label'] ?? [];
    if (!is_array($labels)) {
        $labels = [];
    }
    $on = $data['option_on'] ?? [];
    if (!is_array($on)) {
        $on = [];
    }
    $rows = [];
    foreach ($labels as $i => $label) {
        $label = trim((string) $label);
        if ($label === '') {
            continue;
        }
        $selected = $forDecide && (string) ($on[$i] ?? '') === '1';
        $rows[] = ['label' => $label, 'selected' => $selected];
    }
    if ($rows === []) {
        $rows = briefing_default_options();
        if ($forDecide) {
            foreach ($rows as $i => $row) {
                $rows[$i]['selected'] = (string) ($on[$i] ?? '') === '1';
            }
        }
    }

    return $rows;
}

function briefing_defaults_from_correspondence(array $corr): array
{
    $parts = function_exists('print_direction_parts') ? print_direction_parts($corr) : ['to' => [], 'from' => []];
    $docs = function_exists('get_documents') ? get_documents((int) $corr['id']) : [];
    $attach = [];
    foreach ($docs as $doc) {
        $name = trim((string) ($doc['file_name'] ?? ''));
        if ($name !== '') {
            $attach[] = $name;
        }
    }
    $from = function_exists('correspondence_archived_from')
        ? correspondence_archived_from($corr)
        : [];
    $approverName = trim(implode(' ', array_filter([
        (string) ($from['prefix'] ?? ''),
        (string) ($from['person'] ?? ''),
    ])));
    $approverTitle = trim((string) ($from['job'] ?? ''));

    return [
        'receipt_date' => $corr['corr_date'] ?? date('Y-m-d'),
        'basis_number' => trim((string) ($corr['external_ref'] ?? '')),
        'basis_date' => $corr['corr_date'] ?? null,
        'directed_to' => briefing_party_line($parts['to'] ?? []),
        'directed_from' => briefing_party_line($parts['from'] ?? []),
        'attachments_desc' => implode("\n", $attach),
        'request_text' => '',
        'basis_text' => '',
        'study_text' => '',
        'recommendation_text' => '',
        'recommendation_intro' => __('brief.recommendation_intro_default'),
        'recommendation_items' => ['', ''],
        'direction_intro' => __('brief.direction_intro_default'),
        'options' => briefing_default_options(),
        'decision_note' => '',
        'approver_name' => $approverName,
        'approver_title' => $approverTitle,
    ];
}

function get_correspondence_brief(int $corrId): ?array
{
    if ($corrId <= 0) {
        return null;
    }
    ensure_briefing_schema();
    try {
        $stmt = db()->prepare('SELECT * FROM `tblcorrespondence_briefs` WHERE correspondence_id = ?');
        $stmt->execute([$corrId]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $row['options'] = briefing_normalize_options($row['options_json'] ?? '[]');
        $row['recommendation'] = briefing_parse_recommendation($row['recommendation_text'] ?? '');
        $row['decided'] = (int) ($row['decided'] ?? 0) === 1;

        return $row;
    } catch (Throwable $e) {
        return null;
    }
}

function briefing_is_decided(?array $brief): bool
{
    return !empty($brief['decided']);
}

function save_correspondence_brief(array $corr, array $data, array $user, bool $decide = false): int
{
    ensure_briefing_schema();
    $corrId = (int) ($corr['id'] ?? 0);
    if ($corrId <= 0) {
        throw new InvalidArgumentException(__('corr.not_found'));
    }
    if (!briefing_allows_correspondence($corr)) {
        throw new RuntimeException(__('brief.incoming_only'));
    }
    if (function_exists('correspondence_is_cancelled') && correspondence_is_cancelled($corr)) {
        throw new RuntimeException(__('cancel.locked'));
    }
    if (!can_edit_correspondence($user) || !user_can_access_correspondence($corr, $user)) {
        throw new RuntimeException(__('auth.forbidden'));
    }

    $existing = get_correspondence_brief($corrId);
    if ($existing && briefing_is_decided($existing) && !$decide) {
        throw new RuntimeException(__('brief.locked'));
    }
    if ($existing && briefing_is_decided($existing) && $decide) {
        throw new RuntimeException(__('brief.locked'));
    }

    $options = briefing_options_from_request($data, $decide);
    if (!$decide) {
        foreach ($options as $i => $opt) {
            $options[$i]['selected'] = false;
        }
    }
    if ($decide) {
        $any = false;
        foreach ($options as $opt) {
            if (!empty($opt['selected'])) {
                $any = true;
                break;
            }
        }
        $note = trim((string) ($data['decision_note'] ?? ''));
        if (!$any && $note === '') {
            throw new InvalidArgumentException(__('brief.decide_required'));
        }
    }

    $fields = [
        'receipt_date' => briefing_parse_date($data['receipt_date'] ?? null),
        'basis_number' => trim((string) ($data['basis_number'] ?? '')) ?: null,
        'basis_date' => briefing_parse_date($data['basis_date'] ?? null),
        'directed_to' => briefing_compose_party_line($data, 'to') ?: null,
        'directed_from' => briefing_compose_party_line($data, 'from') ?: null,
        'attachments_desc' => trim((string) ($data['attachments_desc'] ?? '')) ?: null,
        'request_text' => trim((string) ($data['request_text'] ?? '')) ?: null,
        'basis_text' => trim((string) ($data['basis_text'] ?? '')) ?: null,
        'study_text' => trim((string) ($data['study_text'] ?? '')) ?: null,
        'recommendation_text' => briefing_encode_recommendation($data),
        'direction_intro' => trim((string) ($data['direction_intro'] ?? '')) ?: null,
        'options_json' => json_encode($options, JSON_UNESCAPED_UNICODE),
        'decision_note' => trim((string) ($data['decision_note'] ?? '')) ?: null,
        'approver_name' => trim((string) ($data['approver_name'] ?? '')) ?: null,
        'approver_title' => trim((string) ($data['approver_title'] ?? '')) ?: null,
    ];

    if ($existing) {
        $sql = 'UPDATE `tblcorrespondence_briefs` SET
            receipt_date = ?, basis_number = ?, basis_date = ?, directed_to = ?, directed_from = ?,
            attachments_desc = ?, request_text = ?, basis_text = ?, study_text = ?, recommendation_text = ?,
            direction_intro = ?, options_json = ?, decision_note = ?, approver_name = ?, approver_title = ?';
        $params = array_values($fields);
        if ($decide) {
            $sql .= ', decided = 1, decided_at = NOW(), decided_by = ?';
            $params[] = (int) $user['id'];
        }
        $sql .= ' WHERE id = ?';
        $params[] = (int) $existing['id'];
        db()->prepare($sql)->execute($params);
        $id = (int) $existing['id'];
        log_activity((int) $user['id'], $corrId, $decide ? 'brief_decide' : 'brief_update', $decide ? __('brief.activity_decide') : __('brief.activity_update'));
        return $id;
    }

    $sql = 'INSERT INTO `tblcorrespondence_briefs` (
        correspondence_id, receipt_date, basis_number, basis_date, directed_to, directed_from,
        attachments_desc, request_text, basis_text, study_text, recommendation_text,
        direction_intro, options_json, decision_note, approver_name, approver_title,
        decided, decided_at, decided_by, created_by
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
    db()->prepare($sql)->execute([
        $corrId,
        $fields['receipt_date'],
        $fields['basis_number'],
        $fields['basis_date'],
        $fields['directed_to'],
        $fields['directed_from'],
        $fields['attachments_desc'],
        $fields['request_text'],
        $fields['basis_text'],
        $fields['study_text'],
        $fields['recommendation_text'],
        $fields['direction_intro'],
        $fields['options_json'],
        $fields['decision_note'],
        $fields['approver_name'],
        $fields['approver_title'],
        $decide ? 1 : 0,
        $decide ? date('Y-m-d H:i:s') : null,
        $decide ? (int) $user['id'] : null,
        (int) $user['id'],
    ]);
    $id = (int) db()->lastInsertId();
    log_activity((int) $user['id'], $corrId, $decide ? 'brief_decide' : 'brief_create', $decide ? __('brief.activity_decide') : __('brief.activity_create'));

    return $id;
}

function briefing_is_incoming(?array $corr): bool
{
    return ($corr['type'] ?? '') === 'incoming';
}

/** ورقة العرض للوارد والمعاملات الداخلية */
function briefing_allows_correspondence(?array $corr): bool
{
    $type = (string) ($corr['type'] ?? '');
    return $type === 'incoming' || $type === 'internal';
}

function briefing_require_incoming(array $corr): void
{
    if (briefing_allows_correspondence($corr)) {
        return;
    }
    flash('warning', __('brief.incoming_only'));
    $id = (int) ($corr['id'] ?? 0);
    $folder = briefing_corr_folder($corr);
    redirect(base_url($folder . '/view.php?id=' . $id));
}

function briefing_title_for_type(string $type): string
{
    if ($type === 'outgoing') {
        return __('brief.title_outgoing');
    }
    if ($type === 'internal') {
        return __('brief.title_internal');
    }
    return __('brief.title_incoming');
}

function briefing_dual_date(?string $ymd): string
{
    $ymd = trim((string) $ymd);
    if ($ymd === '') {
        return '';
    }
    $g = date('Y/m/d', strtotime($ymd) ?: time());
    $h = function_exists('format_hijri_date') ? format_hijri_date($ymd) : '';
    if ($h !== '') {
        return $h . 'هـ الموافق ' . $g . 'م';
    }
    return $g . 'م';
}

function briefing_basis_line(array $brief): string
{
    $num = trim((string) ($brief['basis_number'] ?? ''));
    $date = briefing_dual_date($brief['basis_date'] ?? null);
    if ($num === '' && $date === '') {
        return '';
    }
    $prefix = $num !== ''
        ? __('brief.basis_letter') . ' ' . $num
        : __('brief.basis_none');
    if ($date !== '') {
        return $prefix . ' ' . __('brief.basis_and_date') . ' ' . $date;
    }
    return $prefix;
}

function briefing_corr_folder(array $corr): string
{
    if (function_exists('correspondence_type_folder')) {
        return correspondence_type_folder((string) ($corr['type'] ?? 'incoming'));
    }
    $type = (string) ($corr['type'] ?? '');
    if ($type === 'outgoing') {
        return 'outgoing';
    }
    if ($type === 'internal') {
        return 'internal';
    }
    return 'incoming';
}

function briefing_dir_order_from_request(): array
{
    $raw = (string) ($_GET['dir'] ?? '');
    return [
        'order' => $raw === 'from' ? 'from' : 'to',
        'forced' => array_key_exists('dir', $_GET),
    ];
}

function briefing_swap_dir_button(string $btnClass = 'btn btn-outline-secondary'): string
{
    return '<button type="button" class="' . e($btnClass) . '" id="swapDirectionBtn" title="'
        . e(__('print.swap_direction_hint')) . '" aria-label="' . e(__('print.swap_direction_hint')) . '">'
        . '<i class="bi bi-arrow-down-up" aria-hidden="true"></i>'
        . '<span id="swapDirectionLabel" class="visually-hidden">' . e(__('print.order_to_first')) . '</span>'
        . '</button>';
}

function briefing_dir_order_script(string $order, bool $forced): void
{
    $order = $order === 'from' ? 'from' : 'to';
    ?>
<script>
(function () {
    var STORE_KEY = 'dms.print.dirOrder';
    var LABELS = <?= json_encode(['to' => __('print.order_to_first'), 'from' => __('print.order_from_first')], JSON_UNESCAPED_UNICODE) ?>;
    var container = document.getElementById('briefDirection');
    var button = document.getElementById('swapDirectionBtn');
    var label = document.getElementById('swapDirectionLabel');
    if (!button) { return; }

    function currentOrder() {
        if (!container) { return 'to'; }
        var first = container.querySelector('[data-dir-row]');
        return first ? first.getAttribute('data-dir-row') : 'to';
    }

    function syncWordLinks(dir) {
        document.querySelectorAll('a[href*="brief_word.php"]').forEach(function (a) {
            try {
                var u = new URL(a.href, window.location.href);
                u.searchParams.set('dir', dir);
                a.href = u.toString();
            } catch (e) { /* تجاهل رابط غير صالح */ }
        });
    }

    function applyOrder(dir) {
        if (container) {
            var toRow = container.querySelector('[data-dir-row="to"]');
            var fromRow = container.querySelector('[data-dir-row="from"]');
            if (toRow && fromRow && toRow.parentNode === fromRow.parentNode) {
                if (dir === 'from') {
                    toRow.parentNode.insertBefore(fromRow, toRow);
                } else {
                    toRow.parentNode.insertBefore(toRow, fromRow);
                }
            }
        }
        var text = LABELS[dir] || LABELS.to;
        if (label) label.textContent = text;
        var tip = <?= json_encode(__('print.swap_direction_hint'), JSON_UNESCAPED_UNICODE) ?>;
        button.setAttribute('title', tip);
        button.setAttribute('aria-label', tip);
        if (typeof window.dmsSetBtnTip === 'function') {
            window.dmsSetBtnTip(button, tip);
        }
        syncWordLinks(dir);
    }

    var saved = null;
    try { saved = localStorage.getItem(STORE_KEY); } catch (e) { /* تخزين محلي معطّل */ }
    var forced = <?= $forced ? 'true' : 'false' ?>;
    var initial = <?= json_encode($order) ?>;
    applyOrder(!forced && (saved === 'to' || saved === 'from') ? saved : initial);

    button.addEventListener('click', function () {
        var next = currentOrder() === 'to' ? 'from' : 'to';
        applyOrder(next);
        try { localStorage.setItem(STORE_KEY, next); } catch (e) { /* تخزين محلي معطّل */ }
    });
})();
</script>
    <?php
}

function briefing_word_url(int $corrId, string $dir = ''): string
{
    $url = base_url('correspondence/brief_word.php?id=' . $corrId);
    if ($dir === 'from' || $dir === 'to') {
        $url .= '&dir=' . $dir;
    }
    return $url;
}

function briefing_word_filename(array $corr): string
{
    $ref = preg_replace('/[^\p{L}\p{N}]+/u', '-', (string) ($corr['ref_number'] ?? 'corr'));
    $ref = trim((string) $ref, '-');
    if ($ref === '') {
        $ref = 'corr';
    }
    return 'brief-' . $ref . '.docx';
}

function briefing_send_word(array $corr, array $brief, bool $fromFirst = false): void
{
    require_once __DIR__ . '/briefing_docx.php';
    briefing_docx_send($corr, $brief, $fromFirst);
}

function briefing_word_logo_html(): string
{
    $rel = function_exists('org_logo_path') ? org_logo_path() : '';
    $abs = '';
    if ($rel !== '') {
        $abs = dirname(__DIR__) . '/' . ltrim(str_replace('\\', '/', $rel), '/');
    }
    if ($abs === '' || !is_file($abs)) {
        $abs = dirname(__DIR__) . '/assets/img/favicon.ico';
    }
    if (!is_file($abs)) {
        return '';
    }
    $raw = @file_get_contents($abs);
    if ($raw === false || $raw === '') {
        return '';
    }
    $mime = 'image/png';
    $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
    if ($ext === 'jpg' || $ext === 'jpeg') {
        $mime = 'image/jpeg';
    } elseif ($ext === 'gif') {
        $mime = 'image/gif';
    } elseif ($ext === 'webp') {
        $mime = 'image/webp';
    } elseif ($ext === 'ico') {
        $mime = 'image/x-icon';
    } elseif ($ext === 'svg') {
        $mime = 'image/svg+xml';
    }
    $src = 'data:' . $mime . ';base64,' . base64_encode($raw);

    return '<img src="' . e($src) . '" width="120" alt="">';
}

function briefing_word_html(array $corr, array $brief, bool $fromFirst = false): string
{
    $title = briefing_title_for_type((string) ($corr['type'] ?? 'incoming'));
    $typeLabel = $title;
    $options = briefing_normalize_options($brief['options'] ?? []);
    $basisLine = briefing_basis_line($brief);
    $names = function_exists('org_visible_names') ? org_visible_names('print') : [];
    $logo = briefing_word_logo_html();
    $headerDate = trim((string) ($corr['corr_date'] ?? ''));
    $receiptDate = trim((string) ($brief['receipt_date'] ?? ''));
    $hijri = ($headerDate !== '' && function_exists('format_hijri_date')) ? format_hijri_date($headerDate) : '';
    $greg = $headerDate !== '' ? date('Y/m/d', strtotime($headerDate) ?: time()) : '';

    $sideHtml = '<p align="center" style="' . briefing_word_type_css('font-weight:700;font-size:9pt;color:#3B4F6E;margin:0;') . '">'
        . e(format_ref_number($corr['ref_number'] ?? '')) . '</p>';
    if ($hijri !== '') {
        $sideHtml .= '<p align="center" style="' . briefing_word_type_css('font-weight:700;font-size:10.5pt;color:#3B4F6E;margin:4pt 0 0;') . '">'
            . e($hijri) . ' هـ</p>';
    }
    if ($greg !== '') {
        $sideHtml .= '<p align="center" style="' . briefing_word_type_css('font-weight:700;font-size:10.5pt;color:#3B4F6E;margin:2pt 0 0;') . '" dir="ltr">'
            . e($greg) . ' م</p>';
    }

    $centerHtml = '';
    if ($logo !== '') {
        $centerHtml .= '<p align="center" style="margin:0 0 6pt;">' . $logo . '</p>';
    }
    if (!empty($names['primary'])) {
        $centerHtml .= '<p align="center" style="' . briefing_word_type_css('font-weight:700;font-size:13pt;color:#1f2a37;margin:0;') . '">' . e($names['primary']) . '</p>';
    }
    if (!empty($names['secondary'])) {
        $centerHtml .= '<p align="center" style="' . briefing_word_type_css('font-size:10.5pt;color:#1f2a37;margin:2pt 0 0;') . '">' . e($names['secondary']) . '</p>';
    }
    $centerHtml .= '<p align="center" style="' . briefing_word_type_css('font-weight:700;font-size:14pt;color:#3B4F6E;margin:8pt 0 0;') . '">'
        . e($typeLabel) . '</p>';

    $brand = '<table dir="ltr" width="100%" cellspacing="0" cellpadding="0" style="margin-bottom:12pt;border:none;border-bottom:1.5pt solid #3B4F6E;">'
        . '<tr>'
        . '<td width="24%" valign="top" align="center" dir="rtl" style="border:none;background:transparent;">' . $sideHtml . '</td>'
        . '<td width="52%" valign="top" align="center" dir="rtl" style="border:none;background:transparent;">' . $centerHtml . '</td>'
        . '<td width="24%" valign="top" style="border:none;background:transparent;"></td>'
        . '</tr></table>';

    $rowHtml = static function (string $k, string $v, bool $bold = false, bool $nl = false, string $color = ''): string {
        $val = $nl ? briefing_word_nl($v) : e($v);
        $weight = $bold ? '700' : '400';
        $colorCss = $color !== '' ? 'color:' . $color . ';' : '';
        return '<tr>'
            . '<td width="22%" valign="top" style="' . briefing_word_type_css('border:none;background:transparent;padding:8pt 8pt 8pt 0;font-weight:700;font-size:12.5pt;white-space:nowrap;') . '">'
            . e($k) . ':</td>'
            . '<td valign="top" style="' . briefing_word_type_css('border:none;background:transparent;padding:8pt 0;font-weight:' . $weight . ';font-size:12.5pt;' . $colorCss) . '">'
            . $val . '</td></tr>';
    };

    $toLine = trim((string) ($brief['directed_to'] ?? '')) ?: '—';
    $fromLine = trim((string) ($brief['directed_from'] ?? '')) ?: '—';
    $dirRows = $fromFirst
        ? $rowHtml(__('print.directed_from'), $fromLine, true) . $rowHtml(__('print.directed_to'), $toLine, true)
        : $rowHtml(__('print.directed_to'), $toLine, true) . $rowHtml(__('print.directed_from'), $fromLine, true);

    $extra = '';
    if ($basisLine !== '') {
        $extra .= $rowHtml(__('brief.basis_label'), $basisLine);
    }
    if ($receiptDate !== '' && $receiptDate !== $headerDate) {
        $extra .= $rowHtml(__('brief.receipt_date'), briefing_dual_date($receiptDate));
    }
    $attach = trim((string) ($brief['attachments_desc'] ?? ''));
    if ($attach !== '') {
        $extra .= $rowHtml(__('brief.attachments'), $attach, false, true);
    }
    $request = trim((string) ($brief['request_text'] ?? ''));
    if ($request !== '') {
        $extra .= $rowHtml(__('brief.request'), $request, false, true, '#EE0000');
    }

    $metaHtml = '<table width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;border:none;margin-bottom:6pt;">'
        . $rowHtml(__('corr.subject'), (string) ($corr['subject'] ?? ''), true)
        . '</table>'
        . '<table width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;border:none;margin-bottom:6pt;">'
        . $dirRows
        . '</table>';
    if ($extra !== '') {
        $metaHtml .= '<table width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;border:none;margin-bottom:6pt;">'
            . $extra . '</table>';
    }
    $metaHtml .= '<div style="border:none;border-top:2.25pt solid #3B4F6E;margin:7pt 0 16pt;font-size:0;line-height:0;">&nbsp;</div>';

    $headingCss = briefing_word_type_css('font-weight:700;font-size:12.5pt;border-bottom:1px solid #d7dde4;padding-bottom:3pt;margin:12pt 0 6pt;');
    $bodyCss = briefing_word_type_css('font-size:12.5pt;line-height:1.45;margin:0 0 13pt;');

    $blocks = [
        ['k' => __('brief.basis'), 'v' => $brief['basis_text'] ?? '', 'justify' => true],
        ['k' => __('brief.study'), 'v' => $brief['study_text'] ?? '', 'justify' => false],
    ];
    $bodyHtml = '';
    foreach ($blocks as $block) {
        $text = trim((string) $block['v']);
        if ($text === '') {
            continue;
        }
        $align = !empty($block['justify']) ? 'text-align:justify;text-justify:inter-word;' : '';
        $bodyHtml .= '<p style="' . $headingCss . '">' . e($block['k']) . '</p>'
            . '<p style="' . $bodyCss . $align . '">' . briefing_word_nl($text) . '</p>';
    }
    $rec = is_array($brief['recommendation'] ?? null)
        ? $brief['recommendation']
        : briefing_parse_recommendation($brief['recommendation_text'] ?? '');
    if (briefing_recommendation_has_content($rec)) {
        $bodyHtml .= '<p style="' . $headingCss . '">' . e(__('brief.recommendation')) . '</p>';
        $introRec = trim((string) ($rec['intro'] ?? ''));
        if ($introRec !== '') {
            $bodyHtml .= '<p style="' . briefing_word_type_css('font-size:12.5pt;line-height:1.45;margin:0 0 6pt;color:#EE0000;') . '">' . e($introRec) . '</p>';
        }
        foreach (array_values($rec['items'] ?? []) as $i => $item) {
            $item = trim((string) $item);
            if ($item === '') {
                continue;
            }
            $bodyHtml .= '<p style="' . briefing_word_type_css('font-size:12.5pt;line-height:1.45;margin:0 0 9pt;color:#EE0000;') . '">'
                . '<b>' . e(($i + 1) . '- ') . '</b>' . e($item) . '</p>';
        }
    }

    $optHtml = '';
    foreach ($options as $opt) {
        $on = !empty($opt['selected']);
        $mark = $on
            ? '<span style="' . briefing_word_type_css('display:inline-block;width:14px;height:14px;border:1px solid #1f2a37;text-align:center;font-weight:700;font-size:9pt;line-height:14px;') . '">✓</span>'
            : '<span style="display:inline-block;width:14px;height:14px;border:1px solid #1f2a37;font-size:4pt;line-height:14px;">&nbsp;</span>';
        $optHtml .= '<p style="' . briefing_word_type_css('font-size:12.5pt;margin:9pt 0;') . '">'
            . $mark . '&nbsp;&nbsp;' . e((string) ($opt['label'] ?? '')) . '</p>';
    }
    $intro = trim((string) ($brief['direction_intro'] ?? ''));
    $note = trim((string) ($brief['decision_note'] ?? ''));
    $dirHtml = '<p style="' . $headingCss . '">' . e(__('brief.direction')) . '</p>';
    if ($intro !== '') {
        $dirHtml .= '<p style="' . $bodyCss . '">' . briefing_word_nl($intro) . '</p>';
    }
    $dirHtml .= $optHtml;
    if ($note !== '') {
        $dirHtml .= '<p style="' . briefing_word_type_css('font-size:12.5pt;margin:8pt 0 0;') . '">' . e($note) . '</p>';
    }

    $approver = trim((string) ($brief['approver_name'] ?? '')) ?: '—';
    $approverTitle = trim((string) ($brief['approver_title'] ?? ''));
    $signHtml = '<table width="180" align="left" cellspacing="0" cellpadding="0" style="margin-top:28pt;border:none;">'
        . '<tr><td align="center" style="' . briefing_word_type_css('border:none;font-weight:700;font-size:12.5pt;') . '">' . e($approver) . '</td></tr>';
    if ($approverTitle !== '') {
        $signHtml .= '<tr><td align="center" style="' . briefing_word_type_css('border:none;padding-top:3pt;font-size:12.5pt;') . '">' . e($approverTitle) . '</td></tr>';
    }
    $signHtml .= '</table>';

    $style = briefing_word_font_face_css()
        . '@page { size: A4; margin: 1.5cm 2.5cm; mso-page-orientation: portrait; }'
        . 'html, body, p, td, th, div, span, li, b { ' . briefing_word_type_css('font-size:12.5pt;color:#1f2a37;') . ' }'
        . 'body { direction: rtl; text-align: right; font-size: 12.5pt; color: #1f2a37; }'
        . 'table { border-collapse: collapse; border: none; }'
        . 'td, th { border: none; background: transparent; }';

    return '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40" lang="ar" xml:lang="ar">'
        . '<head><meta charset="utf-8"><meta http-equiv="Content-Type" content="text/html; charset=utf-8"><title>' . e($title) . '</title>'
        . '<!--[if gte mso 9]><xml><w:WordDocument><w:View>Print</w:View><w:Zoom>100</w:Zoom>'
        . '<w:DoNotOptimizeForBrowser/></w:WordDocument></xml><![endif]-->'
        . '<style>' . $style . '</style></head>'
        . '<body dir="rtl" lang="AR-SA" style="' . briefing_word_type_css('direction:rtl;text-align:right;font-size:12.5pt;color:#1f2a37;') . '">'
        . $brand
        . $metaHtml
        . $bodyHtml
        . $dirHtml
        . $signHtml
        . '</body></html>';
}
