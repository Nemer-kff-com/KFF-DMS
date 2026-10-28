<?php
/**
 * قوالب ملصقات ظرف المراسلات (ستيكرز) — ورق Letter (8.5×11 إنش).
 * المقاسات بالملم — عدّل المصفوفة أو مرّر w/h/cols/rows في الرابط حسب ورق السوق.
 */

/** @return array<string,array<string,mixed>> */
function label_print_templates(): array
{
    // Letter: 8.5in × 11in
    $letterW = 215.9;
    $letterH = 279.4;

    return [
        // مفاتيح a4-* محفوظة للتوافق مع الروابط/localStorage السابقة
        // مقاس سوق: Letter 2×5 — هامش 12مم أعلى/أسفل، 5مم يمين/يسار، فراغ 2مم بين العمودين
        'a4-2x5' => [
            'id' => 'a4-2x5',
            'name_key' => 'label.tpl_letter_2x5',
            'page' => 'letter',
            'page_w' => $letterW,
            'page_h' => $letterH,
            'cols' => 2,
            'rows' => 5,
            'w' => 101.95,
            'h' => 51.08,
            'mt' => 12.0,
            'mr' => 5.0,
            'mb' => 12.0,
            'ml' => 5.0,
            'gx' => 2.0,
            'gy' => 0.0,
        ],
        'a4-2x4' => [
            'id' => 'a4-2x4',
            'name_key' => 'label.tpl_letter_2x4',
            'page' => 'letter',
            'page_w' => $letterW,
            'page_h' => $letterH,
            'cols' => 2,
            'rows' => 4,
            'w' => 101.6,
            'h' => 63.5,
            'mt' => 12.7,
            'mr' => 4.0,
            'mb' => 12.7,
            'ml' => 4.0,
            'gx' => 4.7,
            'gy' => 0.0,
        ],
        'a4-1x1' => [
            'id' => 'a4-1x1',
            'name_key' => 'label.tpl_letter_1x1',
            'page' => 'letter',
            'page_w' => $letterW,
            'page_h' => $letterH,
            'cols' => 1,
            'rows' => 1,
            'w' => 190.0,
            'h' => 120.0,
            'mt' => 40.0,
            'mr' => 12.95,
            'mb' => 40.0,
            'ml' => 12.95,
            'gx' => 0.0,
            'gy' => 0.0,
        ],
    ];
}

function label_print_slots(array $tpl): int
{
    return max(1, (int) $tpl['cols'] * (int) $tpl['rows']);
}

function label_print_default_id(): string
{
    return 'a4-2x5';
}

/** @return array<string,mixed> */
function label_print_resolve_template(array $get): array
{
    $all = label_print_templates();
    $id = preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) ($get['tpl'] ?? ''))) ?: label_print_default_id();
    $tpl = $all[$id] ?? $all[label_print_default_id()];
    $map = [
        'cols' => 'cols',
        'rows' => 'rows',
        'w' => 'w',
        'h' => 'h',
        'mt' => 'mt',
        'mr' => 'mr',
        'mb' => 'mb',
        'ml' => 'ml',
        'gx' => 'gx',
        'gy' => 'gy',
    ];
    $custom = false;
    foreach ($map as $query => $key) {
        if (!isset($get[$query]) || $get[$query] === '') {
            continue;
        }
        $n = (float) str_replace(',', '.', (string) $get[$query]);
        if ($n < 0) {
            continue;
        }
        if (in_array($key, ['cols', 'rows'], true)) {
            $n = max(1, min(12, (int) round($n)));
        } else {
            $n = min(300, $n);
        }
        $tpl[$key] = $n;
        $custom = true;
    }
    if ($custom) {
        $tpl['id'] = 'custom';
        $tpl['name_key'] = 'label.tpl_custom';
    }
    $tpl['cols'] = max(1, (int) $tpl['cols']);
    $tpl['rows'] = max(1, (int) $tpl['rows']);
    return $tpl;
}

function label_print_start(array $get, array $tpl): int
{
    $slots = label_print_slots($tpl);
    $start = (int) ($get['start'] ?? 1);
    if ($start < 1) {
        $start = 1;
    }
    if ($start > $slots) {
        $start = $slots;
    }
    return $start;
}

function label_print_copies(array $get): int
{
    $n = (int) ($get['copies'] ?? 1);
    if ($n < 1) {
        return 1;
    }
    return min(40, $n);
}

function label_print_next_start(int $start, int $used, int $per): int
{
    if ($used < 1 || $per < 1) {
        return max(1, $start);
    }
    return (($start - 1 + $used) % $per) + 1;
}

function label_print_sheet_style(array $tpl): string
{
    $fmt = static function ($n): string {
        $s = number_format((float) $n, 2, '.', '');
        return rtrim(rtrim($s, '0'), '.');
    };
    $pageW = (float) ($tpl['page_w'] ?? 215.9);
    $pageH = (float) ($tpl['page_h'] ?? 279.4);
    // أقل قليلاً من ارتفاع الورقة لتفادي صفحة فارغة بسبب التقريب في المتصفح
    $printH = max(1, $pageH - 0.8);
    return '--label-page-w:' . $fmt($pageW) . 'mm'
        . ';--label-page-h:' . $fmt($pageH) . 'mm'
        . ';--label-print-h:' . $fmt($printH) . 'mm'
        . ';--label-cols:' . (int) $tpl['cols']
        . ';--label-rows:' . (int) $tpl['rows']
        . ';--label-w:' . $fmt($tpl['w']) . 'mm'
        . ';--label-h:' . $fmt($tpl['h']) . 'mm'
        . ';--label-mt:' . $fmt($tpl['mt']) . 'mm'
        . ';--label-mr:' . $fmt($tpl['mr']) . 'mm'
        . ';--label-mb:' . $fmt($tpl['mb']) . 'mm'
        . ';--label-ml:' . $fmt($tpl['ml']) . 'mm'
        . ';--label-gx:' . $fmt($tpl['gx']) . 'mm'
        . ';--label-gy:' . $fmt($tpl['gy']) . 'mm';
}

function render_envelope_label_party(array $part, string $address = ''): void
{
    $prefix = trim((string) ($part['prefix'] ?? ''));
    $person = trim((string) ($part['person'] ?? ''));
    $honorific = trim((string) ($part['honorific'] ?? ''));
    $job = trim((string) ($part['job'] ?? ''));
    $org = trim((string) ($part['org'] ?? ''));

    echo '<div class="print-label-stack">';
    if ($prefix !== '') {
        echo '<div class="print-label-prefix">' . e($prefix) . '</div>';
    }
    if ($person !== '' || $honorific !== '') {
        echo '<div class="print-label-name">';
        if ($person !== '') {
            echo e($person);
            if ($honorific !== '') {
                echo ' <span class="print-label-honorific">' . e($honorific) . '</span>';
            }
        } else {
            echo e($honorific);
        }
        echo '</div>';
    }
    if ($job !== '') {
        echo '<div class="print-label-job">' . e($job) . '</div>';
    }
    if ($org !== '') {
        echo '<div class="print-label-org">' . e($org) . '</div>';
    }
    echo '</div>';
}

function envelope_label_party_address(array $corr): string
{
    $direct = trim((string) ($corr['entity_address'] ?? $corr['address'] ?? ''));
    if ($direct !== '') {
        return $direct;
    }
    $entityId = (int) ($corr['entity_id'] ?? 0);
    if ($entityId > 0 && function_exists('get_entity')) {
        $entity = get_entity($entityId);
        if ($entity) {
            return trim((string) ($entity['address'] ?? ''));
        }
    }
    return '';
}

/**
 * اسم الشخص في سطر «من» على ملصق الظرف (وليس جهة العمل).
 */
function envelope_label_from_person(array $corr, array $parts): string
{
    // 1) أرشيف الموجّه المحفوظ على المعاملة
    if (function_exists('correspondence_archived_from')) {
        $archived = correspondence_archived_from($corr);
        $snapPerson = trim((string) ($archived['person'] ?? ''));
        if ($snapPerson !== '') {
            return $snapPerson;
        }
    }

    // 2) اسم الموقّع من الإعدادات فقط — بدون الرجوع لاسم جهة العمل
    $signer = trim((string) setting('print_signer_name', ''));
    if ($signer !== '') {
        return $signer;
    }

    // 3) اسم منشئ المعاملة
    $uid = (int) ($corr['created_by'] ?? 0);
    if ($uid > 0) {
        try {
            $stmt = db()->prepare('SELECT name_ar, name_en, email FROM `tblusers` WHERE id = ? LIMIT 1');
            $stmt->execute([$uid]);
            $userRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($userRow) {
                $name = function_exists('user_actor_name')
                    ? user_actor_name($userRow)
                    : trim((string) ($userRow['name_ar'] ?? ''));
                if ($name !== '') {
                    return $name;
                }
            }
        } catch (Throwable $e) {
            // تجاهل
        }
    }

    // 4) اسم الشخص من طرف التوجيه إن وُجد، مع استبعاد اسم جهة العمل إن تسرب كقيمة احتياطية
    $type = (string) ($corr['type'] ?? 'incoming');
    $side = ($type === 'outgoing' || $type === 'internal')
        ? ($parts['from'] ?? [])
        : ($parts['to'] ?? []);
    $person = trim((string) ($side['person'] ?? ''));
    $orgFallback = '';
    if (function_exists('print_org_work_name')) {
        $orgFallback = print_org_work_name();
    }
    if ($orgFallback === '' && function_exists('org_name_ar')) {
        $orgFallback = trim(org_name_ar());
    }
    if ($person !== '' && $person !== $orgFallback) {
        return $person;
    }

    return '';
}

/**
 * بيانات ملصق المعاملة الداخلية: لقب / اسم / وظيفة / قسم (من بطاقة القسم المستلم).
 *
 * @return array{prefix:string,person:string,honorific:string,job:string,org:string,titles:list}
 */
function envelope_label_internal_party(array $corr): array
{
    $prefix = trim((string) ($corr['department_title_prefix'] ?? ''));
    $person = trim((string) ($corr['department_directed_from'] ?? ''));
    $job = trim((string) ($corr['department_job_title'] ?? ''));
    $deptName = '';
    if (function_exists('department_display_name')) {
        $deptName = department_display_name($corr, 'department_name', 'department_name_en');
    }
    if ($deptName === '') {
        $deptName = trim((string) ($corr['department_name'] ?? ''));
    }

    // إن لم تُحمَّل أعمدة القسم مع الصف، اجلبها من المعرّف
    $deptId = (int) ($corr['department_id'] ?? 0);
    if ($deptId > 0 && ($prefix === '' && $person === '' && $job === '')) {
        try {
            $stmt = db()->prepare(
                'SELECT name, name_en, title_prefix, directed_from, job_title
                 FROM `tbldepartments` WHERE id = ? LIMIT 1'
            );
            $stmt->execute([$deptId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($row) {
                $prefix = trim((string) ($row['title_prefix'] ?? ''));
                $person = trim((string) ($row['directed_from'] ?? ''));
                $job = trim((string) ($row['job_title'] ?? ''));
                if ($deptName === '' && function_exists('department_display_name')) {
                    $deptName = department_display_name($row);
                }
                if ($deptName === '') {
                    $deptName = trim((string) ($row['name'] ?? ''));
                }
            }
        } catch (Throwable $e) {
            // تجاهل
        }
    }

    // احتياطي من party_name: «من → إلى»
    if ($deptName === '') {
        $party = trim((string) ($corr['party_name'] ?? ''));
        if ($party !== '' && str_contains($party, '→')) {
            $bits = array_map('trim', explode('→', $party, 2));
            $deptName = (string) ($bits[1] ?? '');
        } elseif ($party !== '') {
            $deptName = $party;
        }
    }

    return [
        'prefix' => $prefix,
        'person' => $person,
        'honorific' => '',
        'job' => $job,
        'org' => $deptName, // يُعرض كقسم في الملصق
        'titles' => [],
    ];
}

/**
 * @return array{type:string,ref:string,date:string,date_hijri:string,flags:list<string>,to_label:string,from_label:string,party:array,address:string,from_person:string,from_org:string,verify:string,dept:string}
 */
function envelope_label_payload(array $corr): array
{
    $type = (string) ($corr['type'] ?? 'incoming');
    $isOutgoing = $type === 'outgoing';
    $isInternal = $type === 'internal';
    $parts = function_exists('print_direction_parts') ? print_direction_parts($corr) : ['to' => [], 'from' => []];
    if ($isInternal) {
        $party = envelope_label_internal_party($corr);
    } else {
        $party = $isOutgoing ? ($parts['to'] ?? []) : ($parts['from'] ?? []);
    }
    $fromPerson = envelope_label_from_person($corr, $parts);
    $flags = [];
    $confCode = strtolower(trim((string) ($corr['confidentiality'] ?? '')));
    $prioCode = strtolower(trim((string) ($corr['priority'] ?? '')));
    if ($confCode !== '' && $confCode !== 'normal' && function_exists('confidentiality_label')) {
        $flags[] = confidentiality_label($confCode);
    }
    if ($prioCode !== '' && $prioCode !== 'normal' && function_exists('priority_label')) {
        $flags[] = priority_label($prioCode);
    }
    $date = trim((string) ($corr['corr_date'] ?? ''));
    $hijri = ($date !== '' && function_exists('format_hijri_date')) ? format_hijri_date($date) : '';
    $dept = '';
    if (function_exists('department_display_name')) {
        if ($isInternal) {
            $dept = trim((string) ($party['org'] ?? ''));
            if ($dept === '') {
                $dept = department_display_name($corr, 'department_name', 'department_name_en');
            }
        } else {
            $dept = department_display_name($corr, 'department_name', 'department_name_en');
        }
    }
    return [
        'type' => $isInternal ? 'internal' : ($isOutgoing ? 'outgoing' : 'incoming'),
        'ref' => function_exists('format_ref_caption')
            ? format_ref_caption($type, $corr['ref_number'] ?? '')
            : (string) ($corr['ref_number'] ?? ''),
        'date' => $date !== '' ? date('Y/m/d', strtotime($date)) : '',
        'date_hijri' => $hijri,
        'flags' => $flags,
        'to_label' => ($isOutgoing || $isInternal) ? __('print.directed_to') : __('print.directed_from'),
        'from_label' => __('label.from'),
        'party' => $party,
        'address' => $isInternal ? '' : envelope_label_party_address($corr),
        'from_person' => $fromPerson,
        'from_org' => $fromPerson,
        'verify' => function_exists('dms_corr_qr_content') ? dms_corr_qr_content($corr, true) : '',
        'dept' => $dept,
    ];
}

/**
 * @param list<array|null> $filled
 * @return list<list<array|null>>
 */
function label_print_paginate(array $filled, array $tpl, int $start): array
{
    $per = label_print_slots($tpl);
    $skip = max(0, $start - 1);
    $queue = array_merge(array_fill(0, $skip, null), $filled);
    $pages = [];
    while ($queue) {
        $page = array_splice($queue, 0, $per);
        while (count($page) < $per) {
            $page[] = null;
        }
        $pages[] = $page;
    }
    if (!$pages) {
        $pages[] = array_fill(0, $per, null);
    }

    // لا نُبقي صفحات فارغة في النهاية (تسبب ورقة طباعة زائدة)
    while (count($pages) > 1) {
        $last = $pages[count($pages) - 1];
        $hasContent = false;
        foreach ($last as $cell) {
            if ($cell !== null) {
                $hasContent = true;
                break;
            }
        }
        if ($hasContent) {
            break;
        }
        array_pop($pages);
    }

    return $pages;
}
