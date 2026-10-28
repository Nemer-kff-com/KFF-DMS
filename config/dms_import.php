<?php
/**
 * استيراد جماعي: جهات المراسلة، وارد قديم، صادر قديم (Excel/CSV)
 */
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

require_once __DIR__ . '/../lib/simple_xlsx.php';

const DMS_IMPORT_MAX_ROWS = 2000;

/** @return array<string,array{label:string,sheet:string}> */
function dms_import_kinds(): array
{
    return [
        'entities' => [
            'label' => __('import.kind_entities'),
            'sheet' => __('import.sheet_entities'),
        ],
        'outgoing' => [
            'label' => __('import.kind_outgoing'),
            'sheet' => __('import.sheet_outgoing'),
        ],
        'incoming' => [
            'label' => __('import.kind_incoming'),
            'sheet' => __('import.sheet_incoming'),
        ],
    ];
}

function dms_import_kind(string $kind): ?array
{
    $kinds = dms_import_kinds();
    return $kinds[$kind] ?? null;
}

/** @return array<string,string> */
function dms_import_column_headers(string $kind): array
{
    return match ($kind) {
        'entities' => [
            'title_prefix' => __('import.col_title_prefix'),
            'directed_to' => __('import.col_directed_to'),
            'honorific' => __('import.col_honorific'),
            'job_title' => __('import.col_job_title'),
            'name_ar' => __('import.col_name_ar'),
            'mobile' => __('import.col_mobile'),
            'email' => __('import.col_email'),
            'phone' => __('import.col_phone'),
            'address' => __('import.col_address'),
            'location' => __('import.col_location'),
            'notes' => __('import.col_notes'),
            'name_en' => __('import.col_name_en'),
            'scope' => __('import.col_entity_scope'),
            'is_active' => __('import.col_is_active'),
        ],
        'incoming', 'outgoing' => [
            'subject' => __('import.col_subject'),
            'party_name' => __('import.col_party_name'),
            'entity_name' => __('import.col_entity_name'),
            'corr_date' => __('import.col_corr_date'),
            'external_ref' => __('import.col_external_ref'),
            'department' => __('import.col_department'),
            'status' => __('import.col_status'),
            'priority' => __('import.col_priority'),
            'confidentiality' => __('import.col_confidentiality'),
            'category' => __('import.col_category'),
            'reply_due' => __('import.col_reply_due'),
            'notes' => __('import.col_notes'),
        ],
        default => [],
    };
}

/** @return list<string> */
function dms_import_required_keys(string $kind): array
{
    return match ($kind) {
        'entities' => ['name_ar'],
        'incoming', 'outgoing' => ['subject', 'corr_date'],
        default => [],
    };
}

function dms_import_normalize_header(string $value): string
{
    $value = trim($value);
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
    $value = str_replace(['ـ', "\u{200c}", "\u{200f}", "\u{200e}"], '', $value);
    return mb_strtolower($value, 'UTF-8');
}

/** @param list<mixed> $headerRow
 *  @return array{map: array<int,string>, missing: list<string>} */
function dms_import_map_headers(string $kind, array $headerRow): array
{
    $labels = dms_import_column_headers($kind);
    $aliases = [];
    foreach ($labels as $key => $label) {
        $aliases[dms_import_normalize_header($label)] = $key;
        $aliases[dms_import_normalize_header($key)] = $key;
    }
    $extra = [
        'الاسم العربي' => 'name_ar', 'name_ar' => 'name_ar',
        'الموضوع' => 'subject', 'subject' => 'subject',
        'الجهة' => 'party_name', 'party' => 'party_name', 'party_name' => 'party_name',
        'اسم الجهة في النظام' => 'entity_name', 'entity_name' => 'entity_name',
        'التاريخ' => 'corr_date', 'corr_date' => 'corr_date',
        'الرقم القديم' => 'external_ref', 'external_ref' => 'external_ref',
        'القسم' => 'department', 'department' => 'department',
        'الحالة' => 'status', 'status' => 'status',
        'الأولوية' => 'priority', 'priority' => 'priority',
        'السرية' => 'confidentiality', 'confidentiality' => 'confidentiality',
        'التصنيف' => 'category', 'category' => 'category',
        'موعد الرد' => 'reply_due', 'reply_due' => 'reply_due',
        'ملاحظات' => 'notes', 'notes' => 'notes',
    ];
    foreach ($extra as $label => $key) {
        if (isset($labels[$key]) || in_array($kind, ['incoming', 'outgoing'], true)) {
            $aliases[dms_import_normalize_header($label)] = $key;
        }
    }
    if ($kind === 'entities') {
        foreach ([
            'النطاق' => 'scope',
            'نطاق' => 'scope',
            'نطاق الجهة' => 'scope',
            'القسم' => 'scope',
            'department' => 'scope',
            'scope' => 'scope',
            'جهة عامة' => 'scope',
            'جهة العمل' => 'name_ar',
            'العنوان' => 'address',
            'address' => 'address',
            'الموقع' => 'location',
            'موقع الخريطة' => 'location',
            'location' => 'location',
            'google maps' => 'location',
            'خرائط google' => 'location',
            'رابط google maps' => 'location',
            'maps' => 'location',
            'هاتف - فاكس' => 'phone',
            'هاتف-فاكس' => 'phone',
            'فاكس' => 'phone',
            'البريد الإلكتروني' => 'email',
            'البريد الالكتروني' => 'email',
            'الوظيفة' => 'job_title',
            'نشط' => 'is_active',
            'is_active' => 'is_active',
            'active' => 'is_active',
        ] as $label => $key) {
            $aliases[dms_import_normalize_header($label)] = $key;
        }
    }

    $map = [];
    foreach ($headerRow as $colIndex => $raw) {
        $norm = dms_import_normalize_header((string) $raw);
        if ($norm === '' || !isset($aliases[$norm])) {
            continue;
        }
        $key = $aliases[$norm];
        if (!isset($labels[$key])) {
            continue;
        }
        $map[(int) $colIndex] = $key;
    }

    $found = array_values(array_unique(array_values($map)));
    $missing = [];
    foreach (dms_import_required_keys($kind) as $req) {
        if (!in_array($req, $found, true)) {
            $missing[] = $labels[$req] ?? $req;
        }
    }

    return ['map' => $map, 'missing' => $missing];
}

function dms_import_cell_string(mixed $value): string
{
    if ($value === null) {
        return '';
    }
    if (is_bool($value)) {
        return $value ? '1' : '0';
    }
    if (is_float($value) && floor($value) == $value) {
        return (string) (int) $value;
    }
    return trim((string) $value);
}

function dms_import_parse_date(string $raw): ?string
{
    $raw = trim($raw);
    if ($raw === '') {
        return null;
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
        return $raw;
    }
    if (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})$/', $raw, $m)) {
        return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
    }
    if (preg_match('/^(\d{4})[\/\-.](\d{1,2})[\/\-.](\d{1,2})$/', $raw, $m)) {
        return sprintf('%04d-%02d-%02d', (int) $m[1], (int) $m[2], (int) $m[3]);
    }
    $ts = strtotime($raw);
    if ($ts !== false) {
        return date('Y-m-d', $ts);
    }
    return null;
}

/** @return array<string,string> */
function dms_import_lookup_map(string $sql, string $keyCol = 'name'): array
{
    $map = [];
    try {
        foreach (db()->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $key = dms_import_normalize_header((string) ($row[$keyCol] ?? ''));
            if ($key !== '') {
                $map[$key] = (string) ($row['id'] ?? '');
            }
            if (!empty($row['name_en'])) {
                $en = dms_import_normalize_header((string) $row['name_en']);
                if ($en !== '' && !isset($map[$en])) {
                    $map[$en] = (string) ($row['id'] ?? '');
                }
            }
        }
    } catch (Throwable $e) {
        return [];
    }
    return $map;
}

/** @return array{departments: array<string,int>, categories: array<string,int>} */
function dms_import_reference_maps(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $departments = [];
    $categories = [];
    try {
        foreach (db()->query('SELECT id, name, name_en FROM `tbldepartments` ORDER BY name')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $id = (int) $row['id'];
            foreach (['name', 'name_en'] as $col) {
                $k = dms_import_normalize_header((string) ($row[$col] ?? ''));
                if ($k !== '') {
                    $departments[$k] = $id;
                }
            }
        }
        foreach (db()->query('SELECT id, name, name_en FROM `tblcategories` ORDER BY name')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $id = (int) $row['id'];
            foreach (['name', 'name_en'] as $col) {
                $k = dms_import_normalize_header((string) ($row[$col] ?? ''));
                if ($k !== '') {
                    $categories[$k] = $id;
                }
            }
        }
    } catch (Throwable $e) {
        // ignore
    }
    $cache = ['departments' => $departments, 'categories' => $categories];
    return $cache;
}

function dms_import_resolve_department_id(string $raw, array $maps, ?array $user): ?int
{
    $raw = trim($raw);
    if ($raw === '') {
        if ($user && ($user['role'] ?? '') === 'department') {
            $id = function_exists('user_primary_department_id')
                ? user_primary_department_id($user)
                : (int) ($user['department_id'] ?? 0);
            return $id > 0 ? $id : null;
        }
        return null;
    }
    $key = dms_import_normalize_header($raw);
    return $maps['departments'][$key] ?? null;
}

function dms_import_parse_enum(string $raw, array $allowed, array $aliases = []): ?string
{
    $norm = dms_import_normalize_header($raw);
    if ($norm === '') {
        return null;
    }
    foreach ($aliases as $label => $code) {
        if ($norm === dms_import_normalize_header($label)) {
            return in_array($code, $allowed, true) ? $code : null;
        }
    }
    if (in_array($norm, $allowed, true)) {
        return $norm;
    }
    return null;
}

/** @param list<list<string>> $matrix
 *  @return array{ok:bool, error?:string, rows?:list<array{row_num:int,data:array<string,string>}>} */
function dms_import_rows_from_matrix(string $kind, array $matrix): array
{
    if ($matrix === [] || !isset($matrix[0])) {
        return ['ok' => false, 'error' => __('import.file_empty')];
    }
    $headerMap = dms_import_map_headers($kind, $matrix[0]);
    if ($headerMap['missing'] !== []) {
        return [
            'ok' => false,
            'error' => __('import.missing_columns') . ': ' . implode('، ', $headerMap['missing']),
        ];
    }
    if ($headerMap['map'] === []) {
        return ['ok' => false, 'error' => __('import.unknown_columns')];
    }

    $rows = [];
    $dataRowCount = 0;
    for ($i = 1, $n = count($matrix); $i < $n; $i++) {
        $line = $matrix[$i];
        $data = [];
        $any = false;
        foreach ($headerMap['map'] as $colIndex => $key) {
            $val = dms_import_cell_string($line[$colIndex] ?? '');
            $data[$key] = $val;
            if ($val !== '') {
                $any = true;
            }
        }
        if (!$any) {
            continue;
        }
        $dataRowCount++;
        if ($dataRowCount > DMS_IMPORT_MAX_ROWS) {
            return ['ok' => false, 'error' => __('import.too_many_rows')];
        }
        $rows[] = ['row_num' => $i + 1, 'data' => $data];
    }
    if ($rows === []) {
        return ['ok' => false, 'error' => __('import.no_data_rows')];
    }
    return ['ok' => true, 'rows' => $rows];
}

/** @return array{ok:bool, error?:string, rows?:list<array{row_num:int,data:array<string,string>}>} */
function dms_import_parse_upload(string $kind, string $tmpPath, string $originalName): array
{
    if ($tmpPath === '' || !is_file($tmpPath)) {
        return ['ok' => false, 'error' => __('import.upload_read_failed')];
    }
    $ext = strtolower(pathinfo($originalName !== '' ? $originalName : $tmpPath, PATHINFO_EXTENSION));
    if ($ext === 'csv') {
        $fh = fopen($tmpPath, 'rb');
        if ($fh === false) {
            return ['ok' => false, 'error' => __('import.upload_read_failed')];
        }
        $bom = fread($fh, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($fh);
        }
        $matrix = [];
        while (($line = fgetcsv($fh)) !== false) {
            $matrix[] = array_map('dms_import_cell_string', $line);
        }
        fclose($fh);
        return dms_import_rows_from_matrix($kind, $matrix);
    }

    try {
        $read = dms_simple_xlsx_read($tmpPath);
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => __('import.invalid_xlsx')];
    }
    $meta = dms_import_kind($kind);
    $sheetName = $meta['sheet'] ?? '';
    $matrix = $read['sheets'][$sheetName] ?? $read['first'] ?? [];
    return dms_import_rows_from_matrix($kind, $matrix);
}

function dms_import_parse_active(string $raw): int
{
    $norm = dms_import_normalize_header($raw);
    if ($norm === '') {
        return 1;
    }
    foreach (['0', 'no', 'false', 'inactive', 'off', 'disabled', 'غير نشط', 'معطل', 'لا'] as $inactive) {
        if ($norm === dms_import_normalize_header($inactive)) {
            return 0;
        }
    }
    return 1;
}

/** @param list<array{row_num:int,data:array<string,string>}> $rows */
function dms_import_validate_rows(string $kind, array $rows, ?array $user): array
{
    ensure_entities_schema();
    if (function_exists('ensure_correspondence_party_snapshot_schema')) {
        ensure_correspondence_party_snapshot_schema();
    }
    $maps = dms_import_reference_maps();
    $valid = [];
    $invalid = [];
    $seenEntityNames = [];

    $statusAllowed = correspondence_status_codes();
    $statusAliases = [
        'جديد' => 'new', 'new' => 'new',
        'قيد المعالجة' => 'in_progress', 'in_progress' => 'in_progress',
        'محال' => 'referred', 'referred' => 'referred',
        'بانتظار الرد' => 'awaiting_reply', 'awaiting_reply' => 'awaiting_reply',
        'تم الرد' => 'replied', 'replied' => 'replied',
        'تم التسليم' => 'delivered', 'delivered' => 'delivered',
        'تم الإرسال' => 'sent', 'sent' => 'sent',
        'مغلق' => 'closed', 'closed' => 'closed',
    ];
    $priorityAliases = [
        'عادي' => 'normal', 'normal' => 'normal',
        'عاجل' => 'urgent', 'urgent' => 'urgent',
        'عاجل جداً' => 'very_urgent', 'very_urgent' => 'very_urgent',
    ];
    $confAliases = [
        'عادي' => 'normal', 'normal' => 'normal',
        'سري' => 'confidential', 'confidential' => 'confidential',
        'سري للغاية' => 'secret', 'secret' => 'secret',
        'سري جداً' => 'top_secret', 'top_secret' => 'top_secret',
    ];

    foreach ($rows as $row) {
        $rowNum = (int) ($row['row_num'] ?? 0);
        $data = $row['data'] ?? [];
        $errors = [];

        if ($kind === 'entities') {
            $nameAr = trim((string) ($data['name_ar'] ?? ''));
            if ($nameAr === '') {
                $errors[] = __('import.err_name_ar_required');
            }
            $scopeRaw = trim((string) ($data['scope'] ?? $data['department'] ?? ''));
            $deptId = dms_import_resolve_department_id($scopeRaw, $maps, $user);
            if ($scopeRaw !== '' && $deptId === null) {
                $errors[] = __('import.err_scope_unknown');
            }
            if ($user && ($user['role'] ?? '') === 'department') {
                if (!$deptId || !function_exists('user_can_access_department') || !user_can_access_department((int) $deptId, $user)) {
                    $deptId = function_exists('user_primary_department_id')
                        ? (user_primary_department_id($user) ?: null)
                        : ((int) ($user['department_id'] ?? 0) ?: null);
                }
            }
            $scopeKey = mb_strtolower(trim($nameAr)) . '|' . ($deptId ?? 'null');
            if ($nameAr !== '' && isset($seenEntityNames[$scopeKey])) {
                $errors[] = __('import.err_duplicate_in_file');
            }
            if ($nameAr !== '' && entity_name_exists_in_scope($nameAr, $deptId)) {
                $errors[] = __('import.err_entity_exists');
            }
            $email = trim((string) ($data['email'] ?? ''));
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = __('import.err_email_invalid');
            }
            $location = trim((string) ($data['location'] ?? ''));
            if ($location !== '') {
                $location = entity_maps_url_normalize($location);
                if (!entity_maps_url_valid($location)) {
                    $errors[] = __('import.err_location_invalid');
                }
            }
            if ($errors === [] && $nameAr !== '') {
                $seenEntityNames[$scopeKey] = true;
                $valid[] = [
                    'row_num' => $rowNum,
                    'payload' => [
                        'name_ar' => $nameAr,
                        'name_en' => trim((string) ($data['name_en'] ?? '')) ?: null,
                        'directed_to' => trim((string) ($data['directed_to'] ?? '')) ?: null,
                        'title_prefix' => trim((string) ($data['title_prefix'] ?? '')) ?: null,
                        'job_title' => trim((string) ($data['job_title'] ?? '')) ?: null,
                        'honorific' => trim((string) ($data['honorific'] ?? '')) ?: null,
                        'phone' => trim((string) ($data['phone'] ?? '')) ?: null,
                        'mobile' => trim((string) ($data['mobile'] ?? '')) ?: null,
                        'email' => $email !== '' ? $email : null,
                        'address' => trim((string) ($data['address'] ?? '')) ?: null,
                        'location' => $location !== '' ? $location : null,
                        'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
                        'department_id' => $deptId,
                        'is_active' => dms_import_parse_active((string) ($data['is_active'] ?? '')),
                    ],
                ];
            } else {
                $invalid[] = ['row_num' => $rowNum, 'errors' => $errors, 'data' => $data];
            }
            continue;
        }

        // incoming / outgoing
        $subject = trim((string) ($data['subject'] ?? ''));
        if ($subject === '') {
            $errors[] = __('import.err_subject_required');
        }
        $party = trim((string) ($data['party_name'] ?? ''));
        $entityName = trim((string) ($data['entity_name'] ?? ''));
        if ($party === '' && $entityName === '') {
            $errors[] = __('import.err_party_required');
        }
        $corrDate = dms_import_parse_date((string) ($data['corr_date'] ?? ''));
        if ($corrDate === null) {
            $errors[] = __('import.err_date_invalid');
        }
        $deptId = dms_import_resolve_department_id((string) ($data['department'] ?? ''), $maps, $user);
        if (trim((string) ($data['department'] ?? '')) !== '' && $deptId === null) {
            $errors[] = __('import.err_department_unknown');
        }
        if ($user && ($user['role'] ?? '') === 'department' && (!$deptId || (function_exists('user_can_access_department') && !user_can_access_department((int) $deptId, $user)))) {
            $deptId = function_exists('user_primary_department_id')
                ? (user_primary_department_id($user) ?: null)
                : ((int) ($user['department_id'] ?? 0) ?: null);
        }
        $entityId = null;
        if ($entityName !== '') {
            $stmt = db()->prepare(
                'SELECT id, name_ar, department_id FROM `tblentities`
                 WHERE LOWER(TRIM(name_ar)) = LOWER(TRIM(?)) LIMIT 1'
            );
            $stmt->execute([$entityName]);
            $ent = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$ent) {
                $errors[] = __('import.err_entity_not_found');
            } else {
                $entityId = (int) $ent['id'];
                if ($party === '') {
                    $party = (string) $ent['name_ar'];
                }
            }
        }
        $status = dms_import_parse_enum((string) ($data['status'] ?? ''), $statusAllowed, $statusAliases) ?? 'closed';
        $priority = dms_import_parse_enum((string) ($data['priority'] ?? ''), ['normal', 'urgent', 'very_urgent'], $priorityAliases) ?? 'normal';
        $conf = dms_import_parse_enum((string) ($data['confidentiality'] ?? ''), ['normal', 'confidential', 'secret', 'top_secret'], $confAliases) ?? 'normal';
        $catRaw = trim((string) ($data['category'] ?? ''));
        $categoryId = null;
        if ($catRaw !== '') {
            $categoryId = $maps['categories'][dms_import_normalize_header($catRaw)] ?? null;
            if ($categoryId === null) {
                $errors[] = __('import.err_category_unknown');
            } else {
                $categoryId = (int) $categoryId;
            }
        }
        $replyDue = dms_import_parse_date((string) ($data['reply_due'] ?? ''));

        if ($errors === []) {
            $valid[] = [
                'row_num' => $rowNum,
                'payload' => [
                    'type' => $kind,
                    'subject' => $subject,
                    'party_name' => $party,
                    'entity_id' => $entityId,
                    'corr_date' => $corrDate,
                    'external_ref' => trim((string) ($data['external_ref'] ?? '')) ?: null,
                    'department_id' => $deptId,
                    'status' => $status,
                    'priority' => $priority,
                    'confidentiality' => $conf,
                    'category_id' => $categoryId,
                    'reply_due' => $replyDue,
                    'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
                ],
            ];
        } else {
            $invalid[] = ['row_num' => $rowNum, 'errors' => $errors, 'data' => $data];
        }
    }

    return [
        'valid' => $valid,
        'invalid' => $invalid,
        'summary' => [
            'total' => count($rows),
            'valid' => count($valid),
            'invalid' => count($invalid),
        ],
    ];
}

/** @param list<array{row_num:int,payload:array<string,mixed>}>} $validRows */
function dms_import_commit(string $kind, array $validRows, array $user): array
{
    if ($validRows === []) {
        return ['ok' => false, 'error' => __('import.nothing_to_import')];
    }
    if (function_exists('ensure_correspondence_party_snapshot_schema')) {
        ensure_correspondence_party_snapshot_schema();
    }
    $inserted = 0;
    $pdo = db();
    $ownTx = !$pdo->inTransaction();
    if ($ownTx) {
        $pdo->beginTransaction();
    }
    try {
        if ($kind === 'entities') {
            $stmt = $pdo->prepare(
                'INSERT INTO `tblentities`
                (name_ar, name_en, directed_to, title_prefix, job_title, honorific, phone, mobile, email, address, location, notes, department_id, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            foreach ($validRows as $row) {
                $p = $row['payload'];
                if (entity_name_exists_in_scope((string) $p['name_ar'], $p['department_id'] ?? null)) {
                    continue;
                }
                $stmt->execute([
                    $p['name_ar'],
                    $p['name_en'],
                    $p['directed_to'],
                    $p['title_prefix'],
                    $p['job_title'],
                    $p['honorific'],
                    $p['phone'],
                    $p['mobile'],
                    $p['email'],
                    $p['address'],
                    $p['location'],
                    $p['notes'],
                    $p['department_id'],
                    (int) ($p['is_active'] ?? 1),
                ]);
                $inserted++;
            }
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO `tblcorrespondence`
                (type, ref_number, subject, party_name, entity_id,
                 snap_title_prefix, snap_person_name, snap_job_title, snap_honorific, snap_org_name,
                 snap_from_prefix, snap_from_person, snap_from_job, snap_from_org, snap_from_signature,
                 external_ref, corr_date, reply_due,
                 status, priority, confidentiality, category_id, notes, qr_token, department_id, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            foreach ($validRows as $row) {
                $p = $row['payload'];
                $entity = !empty($p['entity_id']) && function_exists('get_entity')
                    ? get_entity((int) $p['entity_id'])
                    : null;
                $snapVals = correspondence_snapshot_bind_values(correspondence_resolve_save_snapshot(
                    null,
                    $entity,
                    $kind,
                    (string) $p['party_name'],
                    $p['entity_id'] ? (int) $p['entity_id'] : null,
                    $p['department_id'] ? (int) $p['department_id'] : null
                ));
                $stmt->execute(array_merge(
                    [
                        $kind,
                        next_ref_number($kind, $p['department_id'] ?? null),
                        $p['subject'],
                        $p['party_name'],
                        $p['entity_id'],
                    ],
                    $snapVals,
                    [
                        $p['external_ref'],
                        $p['corr_date'],
                        $p['reply_due'],
                        $p['status'],
                        $p['priority'],
                        $p['confidentiality'],
                        $p['category_id'],
                        $p['notes'],
                        generate_qr_token(),
                        $p['department_id'],
                        (int) $user['id'],
                    ]
                ));
                $inserted++;
            }
        }
        if ($ownTx) {
            $pdo->commit();
        }
        if ($inserted > 0 && function_exists('log_activity')) {
            log_activity(
                (int) $user['id'],
                null,
                'import',
                str_replace(
                    [':kind', ':count'],
                    [(string) (dms_import_kind($kind)['label'] ?? $kind), (string) $inserted],
                    __('import.log_done')
                )
            );
        }
        return ['ok' => true, 'inserted' => $inserted];
    } catch (Throwable $e) {
        if ($ownTx && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('DMS import commit: ' . $e->getMessage());
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

function dms_import_stream_template(string $kind): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (function_exists('header_remove')) {
        @header_remove('Content-Type');
    }

    $meta = dms_import_kind($kind);
    if (!$meta) {
        http_response_code(404);
        exit;
    }
    $headers = array_values(dms_import_column_headers($kind));
    $example = match ($kind) {
        'entities' => [
            'معالي الأمين العام',
            'أ.د. عبدالله بن عبدالمحسن التركي',
            'حفظه الله',
            'الأمين العام',
            'مركز الملك فيصل للبحوث والدراسات الإسلامية',
            '966504279270',
            'info@example.com',
            '0114622247',
            'الرياض',
            'https://maps.app.goo.gl/example',
            'ملاحظة اختيارية',
            'King Faisal Center',
            '',
            'نعم',
        ],
        'incoming' => [
            'خطاب استفسار', 'وزارة المالية', '', '2020-05-15', 'IN-2020-001', '',
            'closed', 'normal', 'normal', '', '', 'وارد قديم',
        ],
        'outgoing' => [
            'رد على استفسار', 'وزارة المالية', '', '2020-06-01', 'OUT-2020-010', '',
            'delivered', 'normal', 'normal', '', '', 'صادر قديم',
        ],
        default => array_fill(0, count($headers), ''),
    };
    $mainRows = [$headers, $example];

    $deptRows = [[__('import.col_entity_scope'), __('import.col_department') . ' EN']];
    foreach (db()->query('SELECT name, name_en FROM `tbldepartments` ORDER BY name')->fetchAll(PDO::FETCH_ASSOC) as $d) {
        $deptRows[] = [(string) $d['name'], (string) ($d['name_en'] ?? '')];
    }
    $catRows = [[__('import.col_category'), __('import.col_category') . ' EN']];
    foreach (db()->query('SELECT name, name_en FROM `tblcategories` ORDER BY name')->fetchAll(PDO::FETCH_ASSOC) as $c) {
        $catRows[] = [(string) $c['name'], (string) ($c['name_en'] ?? '')];
    }
    $helpRows = array_map(
        static fn(string $line): array => [$line],
        explode("\n", __('import.help_' . $kind))
    );

    $tmp = tempnam(sys_get_temp_dir(), 'dmsxlsx');
    if ($tmp === false) {
        throw new RuntimeException('temp file failed');
    }
    $xlsx = $tmp . '.xlsx';
    @unlink($tmp);

    $sheets = [
        $meta['sheet'] => $mainRows,
        __('import.sheet_departments') => $deptRows,
        __('import.sheet_help') => $helpRows,
    ];
    if ($kind !== 'entities') {
        $sheets[__('import.sheet_categories')] = $catRows;
    }

    dms_simple_xlsx_write($xlsx, $sheets);

    $filename = 'dms_import_' . $kind . '_template.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . (string) filesize($xlsx));
    readfile($xlsx);
    @unlink($xlsx);
    exit;
}
