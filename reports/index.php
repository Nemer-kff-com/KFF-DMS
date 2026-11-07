<?php
require_once __DIR__ . '/../config/auth.php';
$user = require_login();
if (function_exists('ensure_correspondence_party_snapshot_schema')) {
    ensure_correspondence_party_snapshot_schema();
}
if (function_exists('ensure_correspondence_issuing_schema')) {
    ensure_correspondence_issuing_schema();
}
$pageTitle = __('nav.reports');

require __DIR__ . '/../includes/reports_scope.php';
$export = $_GET['export'] ?? '';

$stmt = db()->prepare(
    "SELECT c.type, COUNT(*) AS cnt FROM `tblcorrespondence` c WHERE $whereSql GROUP BY c.type"
);
$stmt->execute($params);
$byType = ['incoming' => 0, 'outgoing' => 0, 'internal' => 0];
foreach ($stmt->fetchAll() as $row) {
    $byType[$row['type']] = (int) $row['cnt'];
}

$stmt = db()->prepare(
    "SELECT c.status, COUNT(*) AS cnt FROM `tblcorrespondence` c WHERE $whereSql GROUP BY c.status"
);
$stmt->execute($params);
$byStatus = $stmt->fetchAll();

$stmt = db()->prepare(
    "SELECT d.id, d.name, d.name_en, d.title_prefix, d.directed_from, COUNT(*) AS cnt
     FROM `tblcorrespondence` c
     LEFT JOIN `tbldepartments` d ON d.id = IFNULL(c.issuing_department_id, c.department_id)
     WHERE $whereSql
     GROUP BY IFNULL(c.issuing_department_id, c.department_id), d.id, d.name, d.name_en, d.title_prefix, d.directed_from
     ORDER BY cnt DESC"
);
$stmt->execute($params);
$byDept = $stmt->fetchAll();

$overdueStmt = db()->prepare(
    "SELECT COUNT(*) FROM `tblcorrespondence` c
     WHERE $whereSql AND c.reply_due IS NOT NULL AND c.reply_due < CURDATE()
       AND c.status NOT IN ('replied','delivered','closed','cancelled')"
);
$overdueStmt->execute($params);
$overdueCount = (int) $overdueStmt->fetchColumn();

$extras = correspondence_report_extras($whereSql, $params);
$byDept = $extras['by_dept'] ?: $byDept;
if (function_exists('department_officer_fields')) {
    foreach ($byDept as &$deptRow) {
        $officer = department_officer_fields($deptRow);
        $deptRow['title_prefix'] = $officer['title_prefix'];
        $deptRow['directed_from'] = $officer['directed_from'];
    }
    unset($deptRow);
}

$detailCountStmt = db()->prepare(
    "SELECT COUNT(*) FROM `tblcorrespondence` c
     WHERE $whereSql"
);
$detailCountStmt->execute($params);
$detailTotal = (int) $detailCountStmt->fetchColumn();
$detailPagination = dms_pagination_meta($detailTotal, dms_request_page(), dms_list_per_page());

$detailStmt = db()->prepare(
    "SELECT c.ref_number, c.type, c.subject, c.party_name, c.corr_date, c.reply_due, c.status,
            c.snap_title_prefix, c.snap_person_name, c.snap_job_title, c.snap_honorific, c.snap_org_name,
            c.snap_from_prefix, c.snap_from_person, c.snap_from_job, c.snap_from_org,
            d.name AS department_name, d.name_en AS department_name_en,
            d.title_prefix AS department_title_prefix, d.directed_from AS department_directed_from,
            idpt.name AS issuing_department_name, idpt.name_en AS issuing_department_name_en,
            idpt.title_prefix AS issuing_title_prefix, idpt.directed_from AS issuing_directed_from
     FROM `tblcorrespondence` c
     LEFT JOIN `tbldepartments` d ON d.id = c.department_id
     LEFT JOIN `tbldepartments` idpt ON idpt.id = IFNULL(c.issuing_department_id, c.department_id)
     WHERE $whereSql
     ORDER BY c.corr_date DESC, c.id DESC
     LIMIT {$detailPagination['per_page']} OFFSET {$detailPagination['offset']}"
);
$detailStmt->execute($params);
$details = $detailStmt->fetchAll();

if ($export === 'csv' || $export === 'excel') {
    require_once __DIR__ . '/../config/export_excel.php';

    $exportStmt = db()->prepare(
        "SELECT c.ref_number, c.type, c.subject, c.party_name, c.corr_date, c.reply_due, c.status,
                c.snap_title_prefix, c.snap_person_name, c.snap_job_title, c.snap_honorific, c.snap_org_name,
                c.snap_from_prefix, c.snap_from_person, c.snap_from_job, c.snap_from_org,
                d.name AS department_name, d.name_en AS department_name_en,
                d.title_prefix AS department_title_prefix, d.directed_from AS department_directed_from,
                idpt.name AS issuing_department_name, idpt.name_en AS issuing_department_name_en,
                idpt.title_prefix AS issuing_title_prefix, idpt.directed_from AS issuing_directed_from
         FROM `tblcorrespondence` c
         LEFT JOIN `tbldepartments` d ON d.id = c.department_id
         LEFT JOIN `tbldepartments` idpt ON idpt.id = IFNULL(c.issuing_department_id, c.department_id)
         WHERE $whereSql
         ORDER BY c.corr_date DESC, c.id DESC"
    );
    $exportStmt->execute($params);

    $headers = [
        __('common.serial'),
        __('corr.number'),
        __('corr.type'),
        __('corr.subject'),
        __('corr.party_from'),
        __('corr.party_to'),
        __('corr.date'),
        __('corr.reply_due'),
        __('corr.status'),
    ];
    $colspan = count($headers);
    $period = __('common.from') . ' ' . format_date($from) . ' — ' . __('common.to') . ' ' . format_date($to);

    dms_export_excel_open(__('reports.excel_title') . '-' . date('Ymd'));
    dms_export_excel_title_row(dms_org_display_name() . ' — ' . __('reports.excel_title'), $colspan);
    dms_export_excel_subtitle_row($period, $colspan);
    dms_export_excel_header_row($headers);

    // تُبثّ الصفوف صفًا صفًا حتى لا يُحمَّل التقرير كاملاً في الذاكرة مهما كبر.
    $exportCount = 0;
    while ($r = $exportStmt->fetch()) {
        $exportCount++;
        echo '<tr>';
        dms_export_excel_cell((string) $exportCount);
        dms_export_excel_cell(format_ref_number($r['ref_number'] ?? ''));
        dms_export_excel_cell(type_label($r['type']));
        dms_export_excel_cell((string) ($r['subject'] ?? ''));
        $fromArchived = function_exists('correspondence_archived_from') ? correspondence_archived_from($r) : [];
        $fromExport = trim(trim((string) ($fromArchived['prefix'] ?? '')) . "\n" . trim((string) ($fromArchived['person'] ?? '')));
        if (trim(str_replace("\n", '', $fromExport)) === '') {
            $fromExport = correspondence_department_officer_label($r);
        }
        dms_export_excel_cell($fromExport);
        $partyParts = correspondence_archived_party($r);
        if (trim((string) ($partyParts['person'] ?? '')) === '') {
            $partyParts['person'] = trim((string) ($r['party_name'] ?? ''));
        }
        $partyNameLine = trim((string) ($partyParts['person'] ?? ''));
        $partyHon = trim((string) ($partyParts['honorific'] ?? ''));
        if ($partyHon !== '') {
            $partyNameLine = trim($partyNameLine . ($partyNameLine !== '' ? ' ' : '') . $partyHon);
        }
        $partyExport = trim(trim((string) ($partyParts['prefix'] ?? '')) . "\n" . $partyNameLine);
        dms_export_excel_cell($partyExport !== '' ? $partyExport : correspondence_party_display_name($r));
        dms_export_excel_cell(format_date($r['corr_date'] ?? null));
        dms_export_excel_cell(format_date($r['reply_due'] ?? null));
        dms_export_excel_cell(status_label($r['status']));
        echo '</tr>';
        if ($exportCount % 200 === 0) {
            flush();
        }
    }
    $exportStmt->closeCursor();

    echo '<tr><td class="col-text" colspan="' . $colspan . '"><strong>'
        . dms_export_excel_escape(__('common.total') . ': ' . $exportCount)
        . '</strong></td></tr>';
    dms_export_excel_end();
    exit;
}

$departments = departments_list();
require_once __DIR__ . '/../includes/reports_list_links.php';

$linkScope = reports_current_link_scope(
    $from,
    $to,
    $hijriFrom,
    $hijriTo,
    $issuingDeptId,
    $toDeptId,
    $entityId,
    $typeFilter
);

$volumeTotal = (int) $byType['incoming'] + (int) $byType['outgoing'] + (int) $byType['internal'];
$volumeStats = [
    [
        'label' => __('type.incoming'),
        'value' => (int) $byType['incoming'],
        'icon' => 'bi-inbox',
        'tone' => 'accent',
        'href' => reports_list_url('incoming/index.php', $linkScope),
    ],
    [
        'label' => __('type.outgoing'),
        'value' => (int) $byType['outgoing'],
        'icon' => 'bi-send',
        'tone' => 'accent',
        'href' => reports_list_url('outgoing/index.php', $linkScope),
    ],
    [
        'label' => __('type.internal'),
        'value' => (int) $byType['internal'],
        'icon' => 'bi-building-gear',
        'tone' => 'accent',
        'href' => reports_list_url('internal/index.php', $linkScope),
    ],
    [
        'label' => __('common.total'),
        'value' => $volumeTotal,
        'icon' => 'bi-layers',
        'tone' => 'ok',
        'href' => reports_list_url('correspondence/index.php', $linkScope, [
            'type' => $typeFilter !== 'all' ? $typeFilter : null,
        ]),
    ],
];

$attentionStats = [
    [
        'label' => __('dash.overdue'),
        'value' => (int) $overdueCount,
        'icon' => 'bi-exclamation-triangle',
        'tone' => 'danger',
        'href' => reports_list_url('correspondence/index.php', $linkScope, [
            'overdue' => '1',
            'type' => $typeFilter !== 'all' ? $typeFilter : null,
        ]),
    ],
    [
        'label' => __('actionq.unreplied'),
        'value' => (int) $extras['unreplied'],
        'icon' => 'bi-reply',
        'tone' => 'warn',
        'href' => reports_list_url('incoming/index.php', $linkScope, ['action' => 'unreplied']),
    ],
    [
        'label' => __('actionq.no_receipt'),
        'value' => (int) $extras['missing_receipt'],
        'icon' => 'bi-clipboard-x',
        'tone' => 'warn',
        'href' => reports_list_url('outgoing/index.php', $linkScope, ['receipt' => 'missing']),
    ],
    [
        'label' => __('actionq.unsent'),
        'value' => (int) $extras['unsent'],
        'icon' => 'bi-envelope-x',
        'tone' => 'warn',
        'href' => reports_list_url('outgoing/index.php', $linkScope, ['action' => 'unsent']),
    ],
    [
        'label' => __('reports.avg_close'),
        'value' => $extras['avg_close_days'] !== null ? (string) $extras['avg_close_days'] : '—',
        'icon' => 'bi-hourglass-split',
        'tone' => 'accent',
        'raw' => true,
    ],
];

$renderReportStat = static function (array $s): void {
    $isLink = !empty($s['href']);
    $tag = $isLink ? 'a' : 'div';
    $href = $isLink ? ' href="' . e((string) $s['href']) . '"' : '';
    $title = $isLink ? ' title="' . e(__('reports.stat_open_list')) . '"' : '';
    $cls = 'stat-card tone-' . e((string) ($s['tone'] ?? 'accent')) . ($isLink ? ' reports-stat-link' : '');
    echo '<' . $tag . $href . $title . ' class="' . $cls . '">';
    echo '<div class="stat-card-top"><span class="stat-icon"><i class="bi ' . e((string) ($s['icon'] ?? 'bi-bar-chart')) . '"></i></span></div>';
    echo '<div class="label">' . e((string) ($s['label'] ?? '')) . '</div>';
    $valCls = (($s['tone'] ?? '') === 'danger') ? ' value text-danger' : ' value';
    $val = !empty($s['raw']) ? e((string) $s['value']) : (string) (int) $s['value'];
    echo '<div class="' . trim($valCls) . '">' . $val . '</div>';
    echo '</' . $tag . '>';
};

$pageScripts = dms_list_page_scripts();
$pagination = $detailPagination;

$printQs = http_build_query(array_filter([
    'from' => $from,
    'to' => $to,
    'hijri_from' => $hijriFrom,
    'hijri_to' => $hijriTo,
    'issuing_department_id' => $issuingDeptId > 0 ? $issuingDeptId : null,
    'department_id' => $toDeptId > 0 ? $toDeptId : null,
    'entity_id' => $entityId > 0 ? $entityId : null,
    'type' => $typeFilter,
], static fn ($v) => $v !== null && $v !== ''));
$excelQs = http_build_query(array_merge($_GET, ['export' => 'excel']));
$filterActiveCount = 0;
if ($typeFilter !== 'all' && $typeFilter !== '') {
    $filterActiveCount++;
}
if ($issuingDeptId > 0) {
    $filterActiveCount++;
}
if ($toDeptId > 0 && ($typeFilter === 'incoming' || $typeFilter === 'internal')) {
    $filterActiveCount++;
}
if ($entityId > 0) {
    $filterActiveCount++;
}
if ($from !== '' || $hijriFrom !== '') {
    $filterActiveCount++;
}
if ($to !== '' || $hijriTo !== '') {
    $filterActiveCount++;
}
$filterActive = $filterActiveCount > 0;
$routeListType = match ($typeFilter) {
    'incoming' => 'incoming',
    'internal' => 'internal',
    default => 'outgoing',
};
$detailsForceOpen = (int) ($detailPagination['page'] ?? 1) > 1;

require __DIR__ . '/../includes/header.php';
?>
<div class="reports-page">
<div class="page-header">
    <div class="page-header-main">
        <h1 class="page-title"><?= e(__('reports.title')) ?></h1>
        <p class="page-sub"><?= e(__('reports.subtitle')) ?></p>
    </div>
</div>

<?php
$filtersForceOpen = $filterActive;
$filtersCardId = 'listFiltersCard-reports';
$filtersBodyId = $filtersCardId . '-body';
$filtersStorageKey = 'dms.listFilters.open.reports';
?>
<div class="card-panel mb-3 list-filters-card<?= $filtersForceOpen ? ' is-open' : '' ?>"
     id="<?= e($filtersCardId) ?>"
     data-list-filters-card
     data-storage-key="<?= e($filtersStorageKey) ?>"
     data-force-open="<?= $filtersForceOpen ? '1' : '0' ?>">
    <script>
    (function () {
      var el = document.getElementById(<?= json_encode($filtersCardId, JSON_UNESCAPED_UNICODE) ?>);
      if (!el || el.getAttribute('data-force-open') === '1') return;
      try {
        if (localStorage.getItem(el.getAttribute('data-storage-key')) === '1') {
          el.classList.add('is-open');
        }
      } catch (e) {}
    })();
    </script>
    <div class="list-filters-card-bar">
        <button type="button"
                class="list-filters-card-toggle"
                data-list-filters-toggle
                data-show-label="<?= e(__('corr.filters_show')) ?>"
                data-hide-label="<?= e(__('corr.filters_hide')) ?>"
                aria-expanded="<?= $filtersForceOpen ? 'true' : 'false' ?>"
                aria-controls="<?= e($filtersBodyId) ?>"
                title="<?= e($filtersForceOpen ? __('corr.filters_hide') : __('corr.filters_show')) ?>"
                aria-label="<?= e($filtersForceOpen ? __('corr.filters_hide') : __('corr.filters_show')) ?>">
            <span class="list-filters-card-toggle-main">
                <i class="bi bi-funnel" aria-hidden="true"></i>
                <span><?= e(__('corr.filters_title')) ?></span>
                <?php if ($filterActiveCount > 0): ?>
                    <span class="badge text-bg-secondary list-filters-card-count"><?= (int) $filterActiveCount ?></span>
                <?php endif; ?>
            </span>
            <i class="bi bi-chevron-down list-filters-card-chevron" aria-hidden="true"></i>
        </button>
        <?php if ($filterActive): ?>
            <a class="btn btn-sm btn-outline-secondary list-filters-card-clear" href="<?= e(base_url('reports/index.php')) ?>">
                <i class="bi bi-x-lg me-1" aria-hidden="true"></i><?= e(__('corr.filters_clear')) ?>
            </a>
        <?php endif; ?>
    </div>
    <div class="list-filters-card-body card-body p-3 pt-2" id="<?= e($filtersBodyId) ?>">
        <form method="get" class="list-filters">
            <div class="list-filters-main">
                <div class="list-filters-item">
                    <label class="form-label"><?= e(__('corr.type')) ?></label>
                    <select name="type" class="form-select">
                        <option value="all" <?= $typeFilter === 'all' ? 'selected' : '' ?>><?= e(__('common.all')) ?></option>
                        <option value="incoming" <?= $typeFilter === 'incoming' ? 'selected' : '' ?>><?= e(__('nav.incoming')) ?></option>
                        <option value="outgoing" <?= $typeFilter === 'outgoing' ? 'selected' : '' ?>><?= e(__('nav.outgoing')) ?></option>
                        <option value="internal" <?= $typeFilter === 'internal' ? 'selected' : '' ?>><?= e(__('nav.internal')) ?></option>
                    </select>
                </div>
            </div>
            <div class="list-filters-footer">
                <?php render_filter_date_range([
                    'from' => $from,
                    'to' => $to,
                    'hijri_from' => $hijriFrom,
                    'hijri_to' => $hijriTo,
                ]); ?>
                <?php
                render_filter_route_pair($user, $departments, [
                    'issuing_department_id' => $issuingDeptId > 0 ? (string) $issuingDeptId : '',
                    'department_id' => $toDeptId > 0 ? (string) $toDeptId : '',
                    'entity_id' => $entityId > 0 ? (string) $entityId : '',
                ], $routeListType, '');
                ?>
                <div class="list-filters-actions">
                    <button class="btn btn-kff list-filters-submit" type="submit">
                        <i class="bi bi-funnel me-1" aria-hidden="true"></i><?= e(__('action.filter')) ?>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card-panel mb-3">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="bi bi-layers me-1"></i><?= e(__('reports.volume')) ?></span>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('reports/print.php?' . $printQs)) ?>" target="_blank">
                <i class="bi bi-printer me-1"></i><?= e(__('action.print')) ?>
            </a>
            <a class="btn btn-sm btn-outline-secondary" href="?<?= e($excelQs) ?>">
                <i class="bi bi-file-earmark-excel me-1"></i><?= e(__('action.export_excel')) ?>
            </a>
        </div>
    </div>
    <div class="card-body p-3">
        <div class="stat-grid reports-stat-grid reports-stat-grid-volume mb-0">
            <?php foreach ($volumeStats as $s) { $renderReportStat($s); } ?>
        </div>
    </div>
</div>

<div class="card-panel mb-3">
    <div class="card-header"><i class="bi bi-lightning-charge me-1"></i><?= e(__('reports.attention')) ?></div>
    <div class="card-body p-3">
        <div class="stat-grid reports-stat-grid reports-stat-grid-attention mb-0">
            <?php foreach ($attentionStats as $s) { $renderReportStat($s); } ?>
        </div>
    </div>
</div>

<div class="row g-3 mb-3 reports-breakdown-row">
    <div class="col-lg-6">
        <div class="card-panel h-100 reports-breakdown-card">
            <div class="card-header"><i class="bi bi-flag me-1"></i><?= e(__('reports.by_status')) ?></div>
            <ul class="list-group list-group-flush reports-breakdown-list">
                <?php if (!$byStatus): ?><li class="list-group-item text-muted px-3 py-2"><?= e(__('common.no_data')) ?></li><?php endif; ?>
                <?php foreach ($byStatus as $s):
                    $statusCode = (string) ($s['status'] ?? '');
                    $statusHref = reports_list_url('correspondence/index.php', $linkScope, [
                        'status' => $statusCode !== '' ? $statusCode : null,
                        'type' => $typeFilter !== 'all' ? $typeFilter : null,
                    ]);
                ?>
                    <li class="list-group-item p-0">
                        <a class="reports-breakdown-link d-flex justify-content-between align-items-center gap-2" href="<?= e($statusHref) ?>">
                            <span><?= e(status_label($statusCode)) ?></span>
                            <strong class="text-nowrap"><?= (int) $s['cnt'] ?></strong>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card-panel h-100 reports-breakdown-card">
            <div class="card-header"><i class="bi bi-person-lines-fill me-1"></i><?= e(__('reports.by_dept')) ?></div>
            <ul class="list-group list-group-flush reports-breakdown-list">
                <?php if (!$byDept): ?><li class="list-group-item text-muted px-3 py-2"><?= e(__('common.no_data')) ?></li><?php endif; ?>
                <?php foreach ($byDept as $d):
                    $deptRowId = (int) ($d['id'] ?? 0);
                    $officerName = department_person_option_label($d);
                    if ($officerName === '') {
                        $officerName = department_display_name($d) ?: __('reports.no_dept');
                    }
                    $officeSub = department_officer_subtitle($d);
                    $deptHref = $deptRowId > 0
                        ? reports_list_url('correspondence/index.php', $linkScope, [
                            'issuing_department_id' => $deptRowId,
                            'type' => $typeFilter !== 'all' ? $typeFilter : null,
                        ])
                        : '';
                ?>
                    <li class="list-group-item p-0">
                        <?php if ($deptHref !== ''): ?>
                        <a class="reports-breakdown-link d-flex justify-content-between align-items-center gap-2" href="<?= e($deptHref) ?>">
                        <?php else: ?>
                        <div class="reports-breakdown-link d-flex justify-content-between align-items-center gap-2 is-static">
                        <?php endif; ?>
                            <span class="text-break">
                                <?php render_officer_with_office($officerName, $officeSub); ?>
                            </span>
                            <span class="text-nowrap">
                                <?php if (isset($d['incoming_cnt'])): ?>
                                    <span class="small text-muted me-2"><?= e(__('type.incoming')) ?> <?= (int) $d['incoming_cnt'] ?></span>
                                    <span class="small text-muted me-2"><?= e(__('type.outgoing')) ?> <?= (int) $d['outgoing_cnt'] ?></span>
                                    <?php if (isset($d['internal_cnt'])): ?>
                                        <span class="small text-muted me-2"><?= e(__('type.internal')) ?> <?= (int) $d['internal_cnt'] ?></span>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <strong><?= (int) $d['cnt'] ?></strong>
                            </span>
                        <?php if ($deptHref !== ''): ?></a><?php else: ?></div><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>

<?php
$rsvpTotal = (int) ($extras['rsvp_total'] ?? 0);
if ($rsvpTotal <= 0) {
    $rsvpTotal = (int) $extras['rsvp_attending'] + (int) $extras['rsvp_declined'] + (int) $extras['rsvp_pending'];
}
if ($rsvpTotal > 0):
?>
<div class="card-panel mb-3">
    <div class="card-header"><i class="bi bi-check2-circle me-1"></i><?= e(__('reports.rsvp')) ?></div>
    <div class="card-body p-3">
        <p class="text-muted mb-3"><?= e(__('reports.rsvp_period')) ?></p>
        <div class="stat-grid reports-stat-grid reports-stat-grid-rsvp mb-0">
            <div class="stat-card tone-ok">
                <div class="stat-card-top"><span class="stat-icon"><i class="bi bi-check-lg"></i></span></div>
                <div class="label"><?= e(__('rsvp.attending')) ?></div>
                <div class="value"><?= (int) $extras['rsvp_attending'] ?></div>
            </div>
            <div class="stat-card tone-danger">
                <div class="stat-card-top"><span class="stat-icon"><i class="bi bi-x-lg"></i></span></div>
                <div class="label"><?= e(__('rsvp.declined')) ?></div>
                <div class="value text-danger"><?= (int) $extras['rsvp_declined'] ?></div>
            </div>
            <div class="stat-card tone-warn">
                <div class="stat-card-top"><span class="stat-icon"><i class="bi bi-hourglass-split"></i></span></div>
                <div class="label"><?= e(__('rsvp.pending')) ?></div>
                <div class="value"><?= (int) $extras['rsvp_pending'] ?></div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
$detailsCardId = 'reportsDetailsCard';
$detailsBodyId = $detailsCardId . '-body';
$detailsStorageKey = 'dms.reports.details.open';
?>
<div class="card-panel mb-0 list-filters-card reports-details-card<?= $detailsForceOpen ? ' is-open' : '' ?>"
     id="<?= e($detailsCardId) ?>"
     data-list-filters-card
     data-storage-key="<?= e($detailsStorageKey) ?>"
     data-force-open="<?= $detailsForceOpen ? '1' : '0' ?>">
    <script>
    (function () {
      var el = document.getElementById(<?= json_encode($detailsCardId, JSON_UNESCAPED_UNICODE) ?>);
      if (!el || el.getAttribute('data-force-open') === '1') return;
      try {
        if (localStorage.getItem(el.getAttribute('data-storage-key')) === '1') {
          el.classList.add('is-open');
        }
      } catch (e) {}
    })();
    </script>
    <div class="list-filters-card-bar">
        <button type="button"
                class="list-filters-card-toggle"
                data-list-filters-toggle
                data-show-label="<?= e(__('reports.details_show')) ?>"
                data-hide-label="<?= e(__('reports.details_hide')) ?>"
                aria-expanded="<?= $detailsForceOpen ? 'true' : 'false' ?>"
                aria-controls="<?= e($detailsBodyId) ?>"
                title="<?= e($detailsForceOpen ? __('reports.details_hide') : __('reports.details_show')) ?>"
                aria-label="<?= e($detailsForceOpen ? __('reports.details_hide') : __('reports.details_show')) ?>">
            <span class="list-filters-card-toggle-main">
                <i class="bi bi-table" aria-hidden="true"></i>
                <span><?= e(__('reports.details')) ?></span>
                <span class="badge text-bg-secondary list-filters-card-count"><?= (int) $detailTotal ?></span>
            </span>
            <i class="bi bi-chevron-down list-filters-card-chevron" aria-hidden="true"></i>
        </button>
    </div>
    <div class="list-filters-card-body" id="<?= e($detailsBodyId) ?>">
        <div class="px-3 pt-2 pb-1 small text-muted"><?= e(__('reports.details_hint')) ?></div>
        <div class="list-panel-body" id="list-main" data-list-panel>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 reports-detail-table">
                    <thead>
                        <tr>
                            <th><?= e(__('common.serial')) ?></th>
                            <th><?= e(__('corr.number')) ?></th>
                            <th><?= e(__('corr.type')) ?></th>
                            <th><?= e(__('corr.subject')) ?></th>
                            <th><?= e(__('corr.party_from')) ?></th>
                            <th><?= e(__('corr.party_to')) ?></th>
                            <th class="list-col-date"><?= e(__('corr.date')) ?></th>
                            <th><?= e(__('corr.reply_due')) ?></th>
                            <th class="list-col-status"><?= e(__('corr.status')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$details): ?><tr><td colspan="9" class="text-center text-muted py-4"><?= e(__('common.no_results')) ?></td></tr><?php endif; ?>
                    <?php
                    $seq = (int) ($detailPagination['offset'] ?? 0);
                    foreach ($details as $r):
                        $seq++;
                    ?>
                        <tr class="<?= is_overdue($r['reply_due'], $r['status']) ? 'table-danger' : '' ?>">
                            <td class="text-muted"><?= (int) $seq ?></td>
                            <td class="fw-semibold"><?= e(format_ref_number($r['ref_number'] ?? '')) ?></td>
                            <td>
                                <span class="badge <?= ($r['type'] ?? '') === 'outgoing' ? 'text-bg-secondary' : (($r['type'] ?? '') === 'internal' ? 'text-bg-warning' : 'text-bg-primary') ?>">
                                    <?= e(type_label($r['type'])) ?>
                                </span>
                            </td>
                            <td><?= e($r['subject']) ?></td>
                            <td><?php render_correspondence_department_officer($r, 'issuing'); ?></td>
                            <td><?php
                                $partyParts = correspondence_archived_party($r);
                                if (trim((string) ($partyParts['person'] ?? '')) === '') {
                                    $partyParts['person'] = trim((string) ($r['party_name'] ?? ''));
                                }
                                render_corr_list_person($partyParts);
                            ?></td>
                            <td class="list-col-date"><?php render_corr_date_cell($r['corr_date'] ?? null); ?></td>
                            <td><?= e(format_date($r['reply_due'])) ?></td>
                            <td class="list-col-status"><?= status_badge($r['status'], $r['cancel_kind'] ?? null) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php $pageParam = 'page'; require __DIR__ . '/../includes/list_pagination.php'; ?>
        </div>
    </div>
</div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
