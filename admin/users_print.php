<?php
/**
 * طباعة قائمة المستخدمين لعرض الإدارة.
 */
require_once __DIR__ . '/../config/auth.php';
$user = require_permission('admin.users');
ensure_users_profile_schema();

$roleFilterId = (int) ($_GET['role_id'] ?? 0);
$roleFilter = ($roleFilterId > 0 && function_exists('dms_role_get')) ? dms_role_get($roleFilterId) : null;
if ($roleFilterId > 0 && !$roleFilter) {
    $roleFilterId = 0;
}

$usersSql = "SELECT u.*, d.name AS department_name, d.name_en AS department_name_en
     FROM `tblusers` u
     LEFT JOIN `tbldepartments` d ON d.id = u.department_id";
if ($roleFilterId > 0) {
    $usersStmt = db()->prepare($usersSql . ' WHERE u.role_id = ? ORDER BY u.sort_order ASC, u.name_ar ASC, u.id ASC');
    $usersStmt->execute([$roleFilterId]);
    $users = $usersStmt->fetchAll();
} else {
    $users = db()->query($usersSql . ' ORDER BY u.sort_order ASC, u.name_ar ASC, u.id ASC')->fetchAll();
}

/* الطباعة للإدارة: بدون حسابات مدير النظام */
$users = array_values(array_filter($users, static function (array $u): bool {
    if ((string) ($u['role'] ?? '') === 'admin') {
        return false;
    }
    $roleId = (int) ($u['role_id'] ?? 0);
    if ($roleId > 0 && function_exists('dms_role_get')) {
        $role = dms_role_get($roleId);
        if ($role && (string) ($role['base_role'] ?? '') === 'admin') {
            return false;
        }
    }
    return true;
}));

$userIds = array_map(static fn ($row) => (int) $row['id'], $users);
$usersDeptMap = function_exists('users_departments_map') ? users_departments_map($userIds) : [];
$usersDraftMap = function_exists('outgoing_prep_users_delegate_map') ? outgoing_prep_users_delegate_map($userIds) : [];
$usersPrincipalMap = function_exists('outgoing_prep_users_principal_map') ? outgoing_prep_users_principal_map($userIds) : [];

$rows = [];
foreach ($users as $u) {
    $uid = (int) $u['id'];
    $uAssigned = $usersDeptMap[$uid] ?? [];
    $assignedIds = [];
    foreach ($uAssigned as $ud) {
        $aid = (int) ($ud['id'] ?? 0);
        if ($aid > 0) {
            $assignedIds[$aid] = true;
        }
    }
    $uDepts = $uAssigned;
    if (!$uDepts && department_display_name($u, 'department_name', 'department_name_en')) {
        $uDepts = [[
            'id' => (int) ($u['department_id'] ?? 0),
            'name' => (string) ($u['department_name'] ?? ''),
            'name_en' => (string) ($u['department_name_en'] ?? ''),
            'is_primary' => 1,
        ]];
    }
    $uDrafts = $usersDraftMap[$uid] ?? [];
    $draftIds = [];
    foreach ($uDrafts as $dd) {
        $draftIds[(int) $dd['id']] = $dd;
    }
    $uPrincipals = $usersPrincipalMap[$uid] ?? [];
    $principalIds = [];
    foreach ($uPrincipals as $pd) {
        $principalIds[(int) $pd['id']] = $pd;
    }
    $uPrimary = null;
    $homeId = (int) ($u['department_id'] ?? 0);
    if ($homeId > 0) {
        foreach ($uDepts as $ud) {
            if ((int) ($ud['id'] ?? 0) === $homeId) {
                $uPrimary = $ud;
                break;
            }
        }
        if ($uPrimary === null) {
            foreach ($uDepts as $ud) {
                if (!empty($ud['is_primary'])) {
                    $uPrimary = $ud;
                    break;
                }
            }
        }
        if ($uPrimary === null) {
            $uPrimary = [
                'id' => $homeId,
                'name' => (string) ($u['department_name'] ?? ''),
                'name_en' => (string) ($u['department_name_en'] ?? ''),
                'is_primary' => 1,
            ];
        }
    } elseif ($uDepts) {
        foreach ($uDepts as $ud) {
            if (!empty($ud['is_primary'])) {
                $uPrimary = $ud;
                break;
            }
        }
        if ($uPrimary === null) {
            $uPrimary = $uDepts[0];
        }
    }
    $primaryId = (int) ($uPrimary['id'] ?? 0);
    $primaryHtml = '';
    if ($uPrimary) {
        $primaryHtml = e(department_display_name($uPrimary));
        $pFlags = [];
        if ($primaryId > 0 && isset($principalIds[$primaryId])) {
            $pFlags[] = '<span class="users-print-flag is-principal">' . e(__('users.dept_col_principal')) . '</span>';
        }
        if ($primaryId > 0 && isset($assignedIds[$primaryId])) {
            $pFlags[] = '<span class="users-print-flag is-assign">' . e(__('users.dept_col_assign')) . '</span>';
        }
        if ($primaryId > 0 && isset($draftIds[$primaryId])) {
            $pFlags[] = '<span class="users-print-flag is-draft">' . e(__('users.dept_col_draft')) . '</span>';
        }
        if ($pFlags !== []) {
            $primaryHtml .= ' ' . implode(' ', $pFlags);
        }
    }
    $uSupport = [];
    foreach ($uAssigned as $ud) {
        $did = (int) ($ud['id'] ?? 0);
        if ($did <= 0 || $did === $primaryId) {
            continue;
        }
        $uSupport[$did] = [
            'name' => department_display_name($ud),
            'assign' => true,
            'draft' => isset($draftIds[$did]),
            'principal' => isset($principalIds[$did]),
        ];
    }
    foreach ($uDrafts as $dd) {
        $did = (int) ($dd['id'] ?? 0);
        if ($did <= 0 || $did === $primaryId) {
            continue;
        }
        if (!isset($uSupport[$did])) {
            $uSupport[$did] = [
                'name' => department_display_name($dd),
                'assign' => false,
                'draft' => true,
                'principal' => isset($principalIds[$did]),
            ];
        } else {
            $uSupport[$did]['draft'] = true;
        }
    }
    foreach ($uPrincipals as $pd) {
        $did = (int) ($pd['id'] ?? 0);
        if ($did <= 0 || $did === $primaryId) {
            continue;
        }
        if (!isset($uSupport[$did])) {
            $uSupport[$did] = [
                'name' => department_display_name($pd),
                'assign' => false,
                'draft' => isset($draftIds[$did]),
                'principal' => true,
            ];
        } else {
            $uSupport[$did]['principal'] = true;
        }
    }
    $supportHtmlParts = [];
    foreach ($uSupport as $sup) {
        $flags = [];
        if (!empty($sup['principal'])) {
            $flags[] = '<span class="users-print-flag is-principal">' . e(__('users.dept_col_principal')) . '</span>';
        }
        if (!empty($sup['assign'])) {
            $flags[] = '<span class="users-print-flag is-assign">' . e(__('users.dept_col_assign')) . '</span>';
        }
        if (!empty($sup['draft'])) {
            $flags[] = '<span class="users-print-flag is-draft">' . e(__('users.dept_col_draft')) . '</span>';
        }
        $label = e((string) $sup['name']);
        if ($flags !== []) {
            $label .= ' ' . implode(' ', $flags);
        }
        $supportHtmlParts[] = $label;
    }

    $prefix = trim((string) ($u['title_prefix'] ?? ''));
    $job = function_exists('user_job_title') ? user_job_title($u) : trim((string) ($u['job_title'] ?? ''));
    $active = !empty($u['is_active']);

    $rows[] = [
        'sort' => (int) ($u['sort_order'] ?? 0),
        'name' => user_display_name($u),
        'prefix' => $prefix,
        'job' => $job,
        'role' => user_role_label($u),
        'primary_html' => $primaryHtml,
        'support_html' => implode("<br>", $supportHtmlParts),
        'status' => $active ? __('common.active') : __('common.inactive'),
        'active' => $active,
    ];
}

$orgName = function_exists('org_name') ? org_name() : '';
$logo = function_exists('org_logo_url') ? org_logo_url() : '';
$printedAt = (function_exists('format_date') ? format_date(date('Y-m-d')) : date('Y/m/d')) . ' — ' . date('H:i');
$backUrl = base_url('admin/users.php' . ($roleFilterId > 0 ? '?role_id=' . $roleFilterId : ''));
$pageTitle = $roleFilter
    ? str_replace(':name', dms_role_display_name($roleFilter), __('users.print_role_title'))
    : __('users.print_title');
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
        .users-print-sheet { max-width: 1100px; margin: 0 auto; padding: 1.25rem 1.5rem 2rem; background: #fff; }
        .users-print-brand { display: flex; align-items: center; justify-content: space-between; gap: 1rem; border-bottom: 2px solid #3B4F6E; padding-bottom: .85rem; margin-bottom: 1rem; }
        .users-print-brand-logo { max-height: 56px; max-width: 160px; object-fit: contain; }
        .users-print-brand h1 { font-size: 1.25rem; margin: 0; color: #3B4F6E; font-weight: 700; }
        .users-print-brand .meta { font-size: .85rem; color: #667; margin-top: .25rem; }
        .users-print-summary { background: #f4f6f9; border: 1px solid #d8dee8; border-radius: 8px; padding: .65rem 1rem; margin-bottom: 1rem; font-size: .92rem; }
        .users-print-table { width: 100%; border-collapse: collapse; font-size: .82rem; }
        .users-print-table th, .users-print-table td { border: 1px solid #d8dee8; padding: .45rem .5rem; vertical-align: middle; text-align: start; }
        .users-print-table thead th { background: #3B4F6E; color: #fff; font-weight: 600; text-align: center; vertical-align: middle; }
        .users-print-table tbody tr:nth-child(even) { background: #f8f9fb; }
        .users-print-table .col-sort { width: 3.2rem; text-align: center; }
        .users-print-table .col-status { width: 5.5rem; text-align: center; }
        .users-print-name { font-weight: 700; color: #243247; }
        .users-print-sub { color: #667; font-size: .78rem; margin-top: .1rem; }
        .users-print-inactive { color: #a33; }
        .users-print-support { white-space: normal; }
        .users-print-col-dept { font-size: .9rem; }
        .users-print-flag {
            display: inline-block;
            padding: .05rem .35rem;
            border-radius: 999px;
            font-weight: 700;
            font-size: .78rem;
            line-height: 1.35;
            border: 1px solid transparent;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .users-print-flag.is-assign {
            color: #1f4d3a;
            background: #e7f3ec;
            border-color: #b7d4c4;
        }
        .users-print-flag.is-draft {
            color: #8a6a32;
            background: #f8f1e4;
            border-color: #c4a36a;
        }
        .users-print-flag.is-principal {
            color: #fff;
            background: #3B4F6E;
            border-color: #3B4F6E;
        }
        .users-print-footer { margin-top: 1.25rem; padding-top: .75rem; border-top: 1px solid #d8dee8; font-size: .8rem; color: #778; display: flex; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
        .users-print-legend {
            margin-top: 1rem;
            border: 1px solid #d8dee8;
            border-radius: 8px;
            background: #f8f9fb;
            padding: .65rem .85rem;
            font-size: .78rem;
            color: #445;
            line-height: 1.55;
        }
        .users-print-legend strong { color: #3B4F6E; }
        .users-print-legend-title { font-weight: 700; color: #3B4F6E; margin-bottom: .35rem; }
        .users-print-legend-item + .users-print-legend-item { margin-top: .2rem; }
        .users-print-legend-note {
            margin-top: .35rem;
            padding: .35rem .5rem;
            background: #eef2f7;
            border-radius: 6px;
            color: #3B4F6E;
            font-weight: 600;
        }
        .users-print-page-foot {
            display: none;
        }
        @media print {
            .no-print { display: none !important; }
            body { background: #fff; }
            .users-print-sheet { max-width: none; padding: 0 0 4.4cm; }
            .users-print-table thead { display: table-header-group; }
            .users-print-table tr { break-inside: avoid; }
            .users-print-footer { display: none; }
            .users-print-legend.no-print-keep { display: none; }
            .users-print-page-foot {
                display: block;
                position: fixed;
                left: 0;
                right: 0;
                bottom: 0;
                border-top: 1px solid #b8c0cc;
                background: #fff;
                padding: .35cm .2cm .15cm;
                font-size: 8.5pt;
                color: #445;
                line-height: 1.45;
            }
            .users-print-page-foot strong { color: #3B4F6E; }
        }
            @page { size: A4 landscape; margin: 1.2cm 1.4cm 4.2cm; }
    </style>
</head>
<body class="print-page" dir="<?= e($htmlDir) ?>">
<div class="print-toolbar no-print">
    <div class="print-toolbar-title"><?= e($pageTitle) ?></div>
    <div class="d-flex flex-wrap gap-2 align-items-center">
        <button type="button" class="btn btn-kff btn-sm" onclick="window.print()">
            <i class="bi bi-printer me-1"></i><?= e(__('action.print')) ?>
        </button>
        <a class="btn btn-outline-secondary btn-sm" href="<?= e($backUrl) ?>">
            <i class="bi <?= $isRtl ? 'bi-arrow-right' : 'bi-arrow-left' ?> me-1"></i><?= e(__('action.back')) ?>
        </a>
    </div>
</div>

<div class="users-print-sheet">
    <div class="users-print-brand">
        <div>
            <h1><?= e($pageTitle) ?></h1>
            <div class="meta"><?= e(__('users.print_doc_label')) ?><?php if ($orgName !== ''): ?> — <?= e($orgName) ?><?php endif; ?></div>
        </div>
        <?php if ($logo !== ''): ?>
        <img class="users-print-brand-logo" src="<?= e($logo) ?>" alt="">
        <?php endif; ?>
    </div>

    <div class="users-print-summary">
        <?= e(str_replace(':n', (string) count($rows), __('users.print_count'))) ?>
        <?php if ($roleFilter): ?>
            — <?= e(str_replace(':name', dms_role_display_name($roleFilter), __('users.role_filter'))) ?>
        <?php endif; ?>
    </div>

    <?php if ($rows === []): ?>
        <p class="text-muted mb-0"><?= e(__('common.no_data')) ?></p>
    <?php else: ?>
    <table class="users-print-table">
        <thead>
            <tr>
                <th class="col-sort">#</th>
                <th><?= e(__('users.user_col')) ?></th>
                <th><?= e(__('users.role')) ?></th>
                <th><?= e(__('users.department_primary_col')) ?></th>
                <th><?= e(__('users.departments_support')) ?></th>
                <th class="col-status"><?= e(__('common.status')) ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $i => $r): ?>
            <tr>
                <td class="col-sort"><?= $i + 1 ?></td>
                <td>
                    <?php if ($r['prefix'] !== ''): ?>
                        <div class="users-print-sub"><?= e($r['prefix']) ?></div>
                    <?php endif; ?>
                    <div class="users-print-name"><?= e($r['name']) ?></div>
                    <?php if ($r['job'] !== ''): ?>
                        <div class="users-print-sub"><?= e($r['job']) ?></div>
                    <?php endif; ?>
                </td>
                <td class="users-print-col-dept"><?= e($r['role']) ?></td>
                <td class="users-print-col-dept"><?= $r['primary_html'] ?></td>
                <td class="users-print-support users-print-col-dept"><?= $r['support_html'] ?></td>
                <td class="col-status<?= $r['active'] ? '' : ' users-print-inactive' ?>"><?= e($r['status']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php
    $printRoles = [];
    if (function_exists('dms_roles_list')) {
        $roleRows = dms_roles_list(true);
        usort($roleRows, static function (array $a, array $b): int {
            $rank = static function (array $r): int {
                $code = (string) ($r['code'] ?? '');
                if ($code === 'department') {
                    return 0;
                }
                if ($code === 'employee') {
                    return 998;
                }
                if ($code === 'viewer') {
                    return 999;
                }
                return 10 + (int) ($r['sort_order'] ?? 0);
            };
            $ra = $rank($a);
            $rb = $rank($b);
            if ($ra !== $rb) {
                return $ra <=> $rb;
            }
            return ((int) ($a['sort_order'] ?? 0)) <=> ((int) ($b['sort_order'] ?? 0));
        });
        foreach ($roleRows as $roleRow) {
            if ((string) ($roleRow['base_role'] ?? '') === 'admin' || (string) ($roleRow['code'] ?? '') === 'admin') {
                continue;
            }
            $desc = trim((string) ($roleRow['description'] ?? ''));
            if ($desc === '' && function_exists('dms_system_roles_seed')) {
                $seed = dms_system_roles_seed();
                $code = (string) ($roleRow['code'] ?? '');
                $desc = trim((string) ($seed[$code]['description'] ?? ''));
            }
            $printRoles[] = [
                'name' => dms_role_display_name($roleRow),
                'desc' => $desc,
            ];
        }
    }
    $legendHtml = static function () use ($printRoles): void {
        ?>
        <div class="users-print-legend-title"><?= e(__('users.print_legend_title')) ?></div>
        <div class="users-print-legend-item"><strong><?= e(__('users.department_primary')) ?>:</strong> <?= e(__('users.department_primary_tip')) ?></div>
        <div class="users-print-legend-item"><span class="users-print-flag is-principal"><?= e(__('users.dept_col_principal')) ?></span>: <?= e(__('users.print_legend_principal')) ?></div>
        <div class="users-print-legend-item"><span class="users-print-flag is-assign"><?= e(__('users.dept_col_assign')) ?></span>: <?= e(__('users.print_legend_assign')) ?></div>
        <div class="users-print-legend-item"><span class="users-print-flag is-draft"><?= e(__('users.dept_col_draft')) ?></span>: <?= e(__('users.print_legend_draft')) ?></div>
        <div class="users-print-legend-item users-print-legend-note"><?= e(__('users.print_legend_flags_relation')) ?></div>
        <div class="users-print-legend-item"><strong><?= e(__('common.active')) ?> / <?= e(__('common.inactive')) ?>:</strong> <?= e(__('users.print_legend_status')) ?></div>
        <?php if ($printRoles !== []): ?>
        <div class="users-print-legend-title" style="margin-top:.45rem"><?= e(__('users.print_legend_roles_title')) ?></div>
        <?php foreach ($printRoles as $pr): ?>
            <div class="users-print-legend-item">
                <strong><?= e($pr['name']) ?>:</strong>
                <?= e($pr['desc'] !== '' ? $pr['desc'] : __('users.print_legend_role_no_desc')) ?>
            </div>
        <?php endforeach; ?>
        <?php endif; ?>
        <?php
    };
    ?>
    <div class="users-print-legend no-print-keep">
        <?php $legendHtml(); ?>
    </div>

    <div class="users-print-footer">
        <span><?= e(__('users.print_printed_at')) ?>: <?= e($printedAt) ?></span>
        <span><?= e(__('users.print_confidential')) ?></span>
    </div>
</div>

<div class="users-print-page-foot" aria-hidden="true">
    <?php $legendHtml(); ?>
    <div style="margin-top:.2rem;opacity:.85"><?= e(__('users.print_printed_at')) ?>: <?= e($printedAt) ?> — <?= e(__('users.print_confidential')) ?></div>
</div>
</body>
</html>
