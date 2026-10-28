<?php
/**
 * طباعة مسار الموافقة (صادر / وارد / داخلي) لعرض الإدارة.
 */
require_once __DIR__ . '/../config/auth.php';
$user = require_permission('admin.settings');

$path = trim((string) ($_GET['path'] ?? 'outgoing'));
$allowed = ['outgoing', 'incoming', 'internal', 'all'];
if (!in_array($path, $allowed, true)) {
    $path = 'outgoing';
}

$paths = $path === 'all' ? ['outgoing', 'incoming', 'internal'] : [$path];

$usersById = [];
foreach ((function_exists('outgoing_approval_active_users') ? outgoing_approval_active_users() : []) as $au) {
    $usersById[(int) $au['id']] = $au;
}

$pathPerson = static function (int $userId) use ($usersById): array {
    if ($userId <= 0) {
        return ['name' => __('settings.path_print_unassigned'), 'job' => ''];
    }
    $au = $usersById[$userId] ?? null;
    if (!$au) {
        try {
            $st = db()->prepare('SELECT id, name_ar, name_en, job_title, job_title_en FROM `tblusers` WHERE id = ? LIMIT 1');
            $st->execute([$userId]);
            $au = $st->fetch() ?: null;
        } catch (Throwable $e) {
            $au = null;
        }
    }
    if (!$au) {
        return ['name' => __('settings.path_print_unassigned'), 'job' => ''];
    }
    return [
        'name' => user_display_name($au),
        'job' => function_exists('user_job_title') ? user_job_title($au) : '',
    ];
};

$pathTimeout = static function (string $prefix, string $stageKey): string {
    $unit = (string) setting($prefix . '_' . $stageKey . '_unit', 'hours');
    $minutes = (int) setting($prefix . '_' . $stageKey . '_minutes', '120');
    if ($unit === 'hours') {
        $h = max(1, (int) round($minutes / 60));
        return $h . ' ' . __('settings.hours');
    }
    return max(1, $minutes) . ' ' . __('settings.minutes');
};

$pathStatus = static function (bool $on): array {
    return $on
        ? ['label' => __('settings.path_print_on'), 'class' => 'is-on']
        : ['label' => __('settings.path_print_off'), 'class' => 'is-off'];
};

$pathTitle = static function (string $p): string {
    return match ($p) {
        'incoming' => __('settings.incoming_approval_section'),
        'internal' => __('settings.internal_approval_section'),
        default => __('settings.outgoing_approval_section'),
    };
};

$pathBuild = static function (string $p) use ($pathPerson, $pathTimeout, $pathStatus): array {
    $stages = [];
    $flow = [];

    if ($p === 'outgoing') {
        $prepOn = setting('outgoing_prep_enabled', '1') === '1';
        $st = $pathStatus($prepOn);
        $stages[] = [
            'icon' => 'bi-pencil-square',
            'title' => __('settings.outgoing_prep_title'),
            'status' => $st,
            'desc' => $prepOn ? __('settings.outgoing_prep_desc') : __('settings.outgoing_prep_stage_off_hint'),
            'rows' => [],
        ];
        if ($prepOn) {
            $flow[] = __('settings.outgoing_prep_stage_name');
        }
    }

    $reviewOn = setting($p . '_review_enabled', '0') === '1';
    $reviewSt = $pathStatus($reviewOn);
    $reviewRows = [];
    if ($reviewOn) {
        $pri = $pathPerson((int) setting($p . '_reviewer_primary_user_id', '0'));
        $reviewRows[] = [
            'label' => __('settings.review_primary_heading'),
            'value' => $pri['name'] . ($pri['job'] !== '' ? ' — ' . $pri['job'] : ''),
        ];
        $reviewRows[] = [
            'label' => __('settings.review_timeout'),
            'value' => $pathTimeout($p, 'review'),
        ];
        $altOn = setting($p . '_reviewer_alternate_enabled', '1') === '1';
        if ($altOn) {
            $alt = $pathPerson((int) setting($p . '_reviewer_alternate_user_id', '0'));
            $reviewRows[] = [
                'label' => __('settings.review_alternate_heading'),
                'value' => $alt['name'] . ($alt['job'] !== '' ? ' — ' . $alt['job'] : ''),
            ];
        } else {
            $reviewRows[] = [
                'label' => __('settings.review_alternate_heading'),
                'value' => __('settings.path_print_off'),
            ];
        }
        $flow[] = __('settings.review_stage_name');
    }
    $stages[] = [
        'icon' => 'bi-clipboard-check',
        'title' => __($p === 'incoming' ? 'settings.incoming_review_title' : ($p === 'internal' ? 'settings.internal_review_title' : 'settings.outgoing_review_title')),
        'status' => $reviewSt,
        'desc' => $reviewOn
            ? __($p === 'incoming' ? 'settings.incoming_review_desc' : ($p === 'internal' ? 'settings.internal_review_desc' : 'settings.outgoing_review_desc'))
            : __('settings.review_stage_off_hint'),
        'rows' => $reviewRows,
    ];

    $approvalOn = setting($p . '_approval_enabled', $p === 'outgoing' ? '1' : '0') === '1';
    $apprSt = $pathStatus($approvalOn);
    $apprRows = [];
    if ($approvalOn) {
        if ($p === 'incoming') {
            $scopeAll = setting('incoming_approval_scope_all', '0') === '1';
            if ($scopeAll) {
                $apprRows[] = [
                    'label' => __('settings.incoming_approval_scope'),
                    'value' => __('settings.incoming_approval_scope_all'),
                ];
            } else {
                $lookupName = static function (array $row): string {
                    $lang = function_exists('dms_lang_code') ? dms_lang_code() : 'ar';
                    $name = trim((string) ($row['name_' . $lang] ?? ''));
                    return $name !== '' ? $name : (string) ($row['name_ar'] ?? $row['code'] ?? '');
                };
                $confCodes = array_filter(explode(',', (string) setting('incoming_approval_confidentiality', 'confidential')));
                $priCodes = array_filter(explode(',', (string) setting('incoming_approval_priority', 'urgent')));
                $confNames = [];
                foreach (function_exists('confidentiality_levels_list') ? confidentiality_levels_list() : [] as $lv) {
                    if (in_array((string) ($lv['code'] ?? ''), $confCodes, true)) {
                        $confNames[] = $lookupName($lv);
                    }
                }
                $priNames = [];
                foreach (function_exists('priority_levels_list') ? priority_levels_list() : [] as $lv) {
                    if (in_array((string) ($lv['code'] ?? ''), $priCodes, true)) {
                        $priNames[] = $lookupName($lv);
                    }
                }
                $apprRows[] = [
                    'label' => __('settings.incoming_approval_scope'),
                    'value' => __('settings.incoming_approval_scope_levels'),
                ];
                if ($confNames !== []) {
                    $apprRows[] = [
                        'label' => __('settings.incoming_approval_scope_confidentiality'),
                        'value' => implode('، ', $confNames),
                    ];
                }
                if ($priNames !== []) {
                    $apprRows[] = [
                        'label' => __('settings.incoming_approval_scope_priority'),
                        'value' => implode('، ', $priNames),
                    ];
                }
            }
        }

        $sg = $pathPerson((int) setting($p . '_approver_sg_user_id', '0'));
        $apprRows[] = [
            'label' => __('settings.outgoing_approver_primary_heading'),
            'value' => $sg['name'] . ($sg['job'] !== '' ? ' — ' . $sg['job'] : ''),
        ];
        $apprRows[] = [
            'label' => __('settings.outgoing_approval_timeout_primary'),
            'value' => $pathTimeout($p, 'approval'),
        ];

        $deputyOn = setting($p . '_approver_deputy_enabled', '1') === '1';
        if ($deputyOn) {
            $dep = $pathPerson((int) setting($p . '_approver_deputy_user_id', '0'));
            $apprRows[] = [
                'label' => __('settings.outgoing_approver_escalation_heading'),
                'value' => $dep['name'] . ($dep['job'] !== '' ? ' — ' . $dep['job'] : '')
                    . ' (' . $pathTimeout($p, 'approval_deputy') . ')',
            ];
        } else {
            $apprRows[] = [
                'label' => __('settings.outgoing_approver_escalation_heading'),
                'value' => __('settings.path_print_off'),
            ];
        }

        $altOn = setting($p . '_approver_alternate_enabled', '1') === '1';
        if ($altOn) {
            $alt = $pathPerson((int) setting($p . '_approver_alternate_user_id', '0'));
            $apprRows[] = [
                'label' => __('settings.outgoing_approver_alternate_heading'),
                'value' => $alt['name'] . ($alt['job'] !== '' ? ' — ' . $alt['job'] : ''),
            ];
        } else {
            $apprRows[] = [
                'label' => __('settings.outgoing_approver_alternate_heading'),
                'value' => __('settings.path_print_off'),
            ];
        }
        $flow[] = __('settings.approval_stage_title');
    }
    $stages[] = [
        'icon' => 'bi-shield-check',
        'title' => __('settings.approval_stage_title'),
        'status' => $apprSt,
        'desc' => $approvalOn
            ? __('settings.' . $p . '_approval_enabled')
            : __('settings.' . $p . '_approval_stage_off_hint'),
        'rows' => $apprRows,
    ];

    if ($p === 'outgoing') {
        $commsNames = [];
        if (function_exists('outgoing_dispatch_user_ids')) {
            $ids = outgoing_dispatch_user_ids();
            if ($ids !== []) {
                $marks = implode(',', array_fill(0, count($ids), '?'));
                $st = db()->prepare(
                    "SELECT id, name_ar, name_en, job_title, job_title_en
                     FROM `tblusers`
                     WHERE id IN ($marks) AND role = 'communications' AND is_active = 1
                     ORDER BY id"
                );
                $st->execute($ids);
                foreach ($st->fetchAll() ?: [] as $cu) {
                    $job = function_exists('user_job_title') ? user_job_title($cu) : '';
                    $commsNames[] = user_display_name($cu) . ($job !== '' ? ' — ' . $job : '');
                }
            }
        }
        $stages[] = [
            'icon' => 'bi-printer',
            'title' => __('settings.outgoing_comms_stage_title'),
            'status' => ['label' => __('settings.path_print_always'), 'class' => 'is-always'],
            'desc' => __('settings.outgoing_comms_stage_desc'),
            'rows' => [[
                'label' => __('settings.outgoing_comms_stage_users'),
                'value' => $commsNames !== [] ? implode("\n", $commsNames) : __('settings.outgoing_comms_stage_empty'),
            ]],
        ];
        $flow[] = __('settings.outgoing_comms_stage_title');
    } elseif ($p === 'incoming') {
        $flow[] = __('settings.path_print_to_department');
    } elseif ($p === 'internal') {
        $flow[] = __('settings.path_print_to_recipient');
    }

    return [
        'stages' => $stages,
        'flow' => $flow,
    ];
};

$sections = [];
foreach ($paths as $p) {
    $built = $pathBuild($p);
    $sections[] = [
        'path' => $p,
        'title' => $pathTitle($p),
        'stages' => $built['stages'],
        'flow' => $built['flow'],
    ];
}

$orgName = function_exists('org_name') ? org_name() : '';
$logo = function_exists('org_logo_url') ? org_logo_url() : '';
$printedAt = function_exists('format_date') ? format_date(date('Y-m-d')) : date('Y/m/d');
$printedAt .= ' — ' . date('H:i');
$backTab = $path === 'all' ? 'approval_outgoing' : ('approval_' . $path);
$backUrl = base_url('admin/settings.php?tab=' . $backTab);
$pageTitle = $path === 'all'
    ? __('settings.path_print_all_title')
    : str_replace(':path', $pathTitle($path), __('settings.path_print_title'));
$cssVer = (int) (@filemtime(__DIR__ . '/../assets/css/app.css') ?: 1);
$isRtl = function_exists('dms_is_rtl') ? dms_is_rtl() : true;
$htmlLang = function_exists('dms_lang_code') ? dms_lang_code() : 'ar';
$htmlDir = $isRtl ? 'rtl' : 'ltr';
?>
<!DOCTYPE html>
<html lang="<?= e($htmlLang) ?>" dir="<?= e($htmlDir) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(__('action.print')) ?> — <?= e($pageTitle) ?></title>
    <link rel="icon" href="<?= e(base_url('assets/img/favicon.ico')) ?>">
    <link href="<?= e(dms_bootstrap_css()) ?>" rel="stylesheet">
    <link href="<?= e(dms_bootstrap_icons_css()) ?>" rel="stylesheet">
    <link href="<?= e(base_url('assets/css/app.css')) ?>?v=<?= $cssVer ?>" rel="stylesheet">
    <style>
        .path-print-sheet { max-width: 900px; margin: 0 auto; padding: 1.25rem 1.5rem 2rem; background: #fff; }
        .path-print-brand { display: flex; align-items: center; justify-content: space-between; gap: 1rem; border-bottom: 2px solid #3B4F6E; padding-bottom: .85rem; margin-bottom: 1.25rem; }
        .path-print-brand-logo { max-height: 56px; max-width: 160px; object-fit: contain; }
        .path-print-brand h1 { font-size: 1.25rem; margin: 0; color: #3B4F6E; font-weight: 700; }
        .path-print-brand .meta { font-size: .85rem; color: #667; margin-top: .25rem; }
        .path-print-section + .path-print-section { margin-top: 2rem; page-break-before: always; }
        .path-print-section-title { font-size: 1.1rem; font-weight: 700; color: #3B4F6E; margin-bottom: .75rem; }
        .path-print-flow { background: #f4f6f9; border: 1px solid #d8dee8; border-radius: 8px; padding: .75rem 1rem; margin-bottom: 1.25rem; font-size: .95rem; }
        .path-print-flow strong { color: #3B4F6E; }
        .path-print-stage { border: 1px solid #d8dee8; border-radius: 10px; padding: 1rem 1.1rem; margin-bottom: .35rem; background: #fff; }
        .path-print-stage.is-off { opacity: .72; background: #fafafa; }
        .path-print-stage-head { display: flex; align-items: flex-start; gap: .75rem; }
        .path-print-step { flex: 0 0 2rem; width: 2rem; height: 2rem; border-radius: 50%; background: #3B4F6E; color: #fff; display: inline-flex; align-items: center; justify-content: center; font-size: .95rem; }
        .path-print-stage.is-off .path-print-step { background: #9aa3b2; }
        .path-print-stage-title { font-weight: 700; font-size: 1rem; margin: 0; }
        .path-print-badge { display: inline-block; font-size: .75rem; font-weight: 600; padding: .15rem .55rem; border-radius: 999px; margin-inline-start: .4rem; vertical-align: middle; }
        .path-print-badge.is-on { background: #e6f4ea; color: #137333; }
        .path-print-badge.is-off { background: #f1f3f4; color: #5f6368; }
        .path-print-badge.is-always { background: #e8f0fe; color: #1a73e8; }
        .path-print-desc { color: #555; font-size: .88rem; margin: .35rem 0 .5rem; }
        .path-print-rows { width: 100%; border-collapse: collapse; font-size: .9rem; margin-top: .35rem; }
        .path-print-rows th, .path-print-rows td { border-top: 1px solid #e6e9ef; padding: .4rem .2rem; vertical-align: top; }
        .path-print-rows th { width: 32%; color: #667; font-weight: 600; text-align: start; }
        .path-print-rows td { white-space: pre-line; }
        .path-print-arrow { text-align: center; color: #3B4F6E; font-size: 1.1rem; line-height: 1; margin: .15rem 0; }
        .path-print-footer { margin-top: 1.5rem; padding-top: .75rem; border-top: 1px solid #d8dee8; font-size: .8rem; color: #778; display: flex; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
        @media print {
            .no-print { display: none !important; }
            body { background: #fff; }
            .path-print-sheet { max-width: none; padding: 0; }
            .path-print-stage { break-inside: avoid; }
        }
        @page { size: A4 portrait; margin: 1.4cm 1.6cm; }
    </style>
</head>
<body class="print-page" dir="<?= e($htmlDir) ?>">
<div class="print-toolbar no-print">
    <div class="print-toolbar-title"><?= e($pageTitle) ?></div>
    <div class="d-flex flex-wrap gap-2 align-items-center">
        <?php if ($path !== 'all'): ?>
        <a class="btn btn-outline-secondary btn-sm" href="<?= e(base_url('admin/settings_path_print.php?path=all')) ?>">
            <i class="bi bi-collection me-1"></i><?= e(__('settings.path_print_all')) ?>
        </a>
        <?php endif; ?>
        <button type="button" class="btn btn-kff btn-sm" onclick="window.print()">
            <i class="bi bi-printer me-1"></i><?= e(__('action.print')) ?>
        </button>
        <a class="btn btn-outline-secondary btn-sm" href="<?= e($backUrl) ?>">
            <i class="bi <?= $isRtl ? 'bi-arrow-right' : 'bi-arrow-left' ?> me-1"></i><?= e(__('action.back')) ?>
        </a>
    </div>
</div>

<div class="path-print-sheet">
    <?php foreach ($sections as $si => $section): ?>
    <section class="path-print-section">
        <div class="path-print-brand">
            <div>
                <h1><?= e($section['title']) ?></h1>
                <div class="meta"><?= e(__('settings.path_print_doc_label')) ?><?php if ($orgName !== ''): ?> — <?= e($orgName) ?><?php endif; ?></div>
            </div>
            <?php if ($logo !== ''): ?>
            <img class="path-print-brand-logo" src="<?= e($logo) ?>" alt="">
            <?php endif; ?>
        </div>

        <div class="path-print-flow">
            <strong><?= e(__('settings.path_print_flow')) ?></strong>
            <?= e($section['flow'] !== [] ? implode(' ← ', $section['flow']) : __('settings.path_print_flow_empty')) ?>
        </div>

        <?php foreach ($section['stages'] as $i => $stage):
            $off = ($stage['status']['class'] ?? '') === 'is-off';
            ?>
            <?php if ($i > 0): ?>
            <div class="path-print-arrow" aria-hidden="true"><i class="bi bi-arrow-down"></i></div>
            <?php endif; ?>
            <article class="path-print-stage<?= $off ? ' is-off' : '' ?>">
                <div class="path-print-stage-head">
                    <span class="path-print-step"><i class="bi <?= e($stage['icon']) ?>" aria-hidden="true"></i></span>
                    <div class="flex-grow-1">
                        <h2 class="path-print-stage-title">
                            <?= e($stage['title']) ?>
                            <span class="path-print-badge <?= e($stage['status']['class']) ?>"><?= e($stage['status']['label']) ?></span>
                        </h2>
                        <?php if (trim((string) ($stage['desc'] ?? '')) !== ''): ?>
                        <p class="path-print-desc mb-0"><?= e($stage['desc']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($stage['rows'])): ?>
                        <table class="path-print-rows">
                            <tbody>
                            <?php foreach ($stage['rows'] as $row):
                                if (trim((string) ($row['value'] ?? '')) === '' && trim((string) ($row['label'] ?? '')) === '') {
                                    continue;
                                }
                                ?>
                                <tr>
                                    <th><?= e((string) $row['label']) ?></th>
                                    <td><?= e((string) ($row['value'] !== '' ? $row['value'] : '—')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>

        <div class="path-print-footer">
            <span><?= e(__('settings.path_print_printed_at')) ?>: <?= e($printedAt) ?></span>
            <span><?= e(__('settings.path_print_confidential')) ?></span>
        </div>
    </section>
    <?php endforeach; ?>
</div>
</body>
</html>
