<?php
require_once __DIR__ . '/../config/auth.php';
$user = require_login();
if (function_exists('ensure_correspondence_party_snapshot_schema')) {
    ensure_correspondence_party_snapshot_schema();
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
    "SELECT d.name, d.name_en, COUNT(*) AS cnt
     FROM `tblcorrespondence` c
     LEFT JOIN `tbldepartments` d ON d.id = c.department_id
     WHERE $whereSql
     GROUP BY c.department_id, d.name, d.name_en
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

$detailCountStmt = db()->prepare(
    "SELECT COUNT(*) FROM `tblcorrespondence` c
     LEFT JOIN `tbldepartments` d ON d.id = c.department_id
     WHERE $whereSql"
);
$detailCountStmt->execute($params);
$detailTotal = (int) $detailCountStmt->fetchColumn();
$detailPagination = dms_pagination_meta($detailTotal, dms_request_page(), dms_list_per_page());

$detailStmt = db()->prepare(
    "SELECT c.ref_number, c.type, c.subject, c.party_name, c.corr_date, c.reply_due, c.status,
            c.snap_title_prefix, c.snap_person_name, c.snap_job_title, c.snap_honorific, c.snap_org_name,
            d.name AS department_name, d.name_en AS department_name_en,
            d.title_prefix AS department_title_prefix, d.directed_from AS department_directed_from
     FROM `tblcorrespondence` c
     LEFT JOIN `tbldepartments` d ON d.id = c.department_id
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
                d.name AS department_name, d.name_en AS department_name_en,
                d.title_prefix AS department_title_prefix, d.directed_from AS department_directed_from
         FROM `tblcorrespondence` c
         LEFT JOIN `tbldepartments` d ON d.id = c.department_id
         WHERE $whereSql
         ORDER BY c.corr_date DESC, c.id DESC"
    );
    $exportStmt->execute($params);

    $headers = [
        __('common.serial'),
        __('corr.number'),
        __('corr.type'),
        __('corr.subject'),
        __('corr.party'),
        __('corr.date'),
        __('corr.reply_due'),
        __('corr.status'),
        __('departments.col_officer'),
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
        dms_export_excel_cell(correspondence_party_display_name($r));
        dms_export_excel_cell(format_date($r['corr_date'] ?? null));
        dms_export_excel_cell(format_date($r['reply_due'] ?? null));
        dms_export_excel_cell(status_label($r['status']));
        dms_export_excel_cell(officer_with_office_text(
            correspondence_department_officer_label($r),
            correspondence_officer_subtitle($r)
        ));
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

$reportStats = [
    [
        'label' => __('type.incoming'),
        'value' => (int) $byType['incoming'],
        'icon' => 'bi-inbox',
        'tone' => 'accent',
    ],
    [
        'label' => __('type.outgoing'),
        'value' => (int) $byType['outgoing'],
        'icon' => 'bi-send',
        'tone' => 'accent',
    ],
    [
        'label' => __('type.internal'),
        'value' => (int) $byType['internal'],
        'icon' => 'bi-building-gear',
        'tone' => 'accent',
    ],
    [
        'label' => __('common.total'),
        'value' => (int) $byType['incoming'] + (int) $byType['outgoing'] + (int) $byType['internal'],
        'icon' => 'bi-layers',
        'tone' => 'ok',
    ],
    [
        'label' => __('dash.overdue'),
        'value' => (int) $overdueCount,
        'icon' => 'bi-exclamation-triangle',
        'tone' => 'danger',
    ],
    [
        'label' => __('reports.avg_close'),
        'value' => $extras['avg_close_days'] !== null ? (string) $extras['avg_close_days'] : '—',
        'icon' => 'bi-hourglass-split',
        'tone' => 'accent',
        'raw' => true,
    ],
    [
        'label' => __('actionq.no_receipt'),
        'value' => (int) $extras['missing_receipt'],
        'icon' => 'bi-clipboard-x',
        'tone' => 'warn',
    ],
    [
        'label' => __('actionq.unreplied'),
        'value' => (int) $extras['unreplied'],
        'icon' => 'bi-reply',
        'tone' => 'warn',
    ],
    [
        'label' => __('actionq.unsent'),
        'value' => (int) $extras['unsent'],
        'icon' => 'bi-envelope-x',
        'tone' => 'warn',
    ],
];

$pageScripts = dms_list_page_scripts();
$pagination = $detailPagination;

require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title"><i class="bi bi-bar-chart-line me-1"></i><?= e(__('reports.title')) ?></h1>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-outline-secondary" href="<?= e(base_url('reports/print.php?' . http_build_query([
            'from' => $from,
            'to' => $to,
            'hijri_from' => $hijriFrom,
            'hijri_to' => $hijriTo,
            'department_id' => $deptId,
            'type' => $typeFilter,
        ]))) ?>" target="_blank"><i class="bi bi-printer"></i> <?= e(__('action.print')) ?></a>
        <a class="btn btn-outline-secondary" href="?<?= e(http_build_query(array_merge($_GET, ['export' => 'excel']))) ?>"><i class="bi bi-file-earmark-excel"></i> <?= e(__('action.export_excel')) ?></a>
    </div>
</div>

<div class="card-panel mb-3">
    <div class="card-body p-3">
        <form method="get" class="list-filters list-filters-report">
            <div class="list-filters-toolbar">
                <div class="filter-date-group">
                    <div class="filter-date-group-title"><?= e(__('corr.type')) ?></div>
                    <div class="filter-date-pair filter-select-pair">
                        <select name="type" class="form-select">
                            <option value="all" <?= $typeFilter === 'all' ? 'selected' : '' ?>><?= e(__('common.all')) ?></option>
                            <option value="incoming" <?= $typeFilter === 'incoming' ? 'selected' : '' ?>><?= e(__('nav.incoming')) ?></option>
                            <option value="outgoing" <?= $typeFilter === 'outgoing' ? 'selected' : '' ?>><?= e(__('nav.outgoing')) ?></option>
                            <option value="internal" <?= $typeFilter === 'internal' ? 'selected' : '' ?>><?= e(__('nav.internal')) ?></option>
                        </select>
                    </div>
                </div>
                <?php render_filter_department_field($user, $departments, $deptId, '0', ['boxed' => true]); ?>
                <?php render_filter_date_range([
                    'from' => $from,
                    'to' => $to,
                    'hijri_from' => $hijriFrom,
                    'hijri_to' => $hijriTo,
                ]); ?>
                <div class="list-filters-actions">
                    <button class="btn btn-kff" type="submit"><?= e(__('action.show')) ?></button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="stat-grid">
    <?php foreach ($reportStats as $s): ?>
        <div class="stat-card tone-<?= e($s['tone']) ?>">
            <div class="stat-card-top">
                <span class="stat-icon"><i class="bi <?= e($s['icon']) ?>"></i></span>
            </div>
            <div class="label"><?= e($s['label']) ?></div>
            <div class="value<?= $s['tone'] === 'danger' ? ' text-danger' : '' ?>"><?= !empty($s['raw']) ? e((string) $s['value']) : (int) $s['value'] ?></div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="card-panel h-100">
            <div class="card-header"><i class="bi bi-flag me-1"></i><?= e(__('reports.by_status')) ?></div>
            <ul class="list-group list-group-flush">
                <?php if (!$byStatus): ?><li class="list-group-item text-muted"><?= e(__('common.no_data')) ?></li><?php endif; ?>
                <?php foreach ($byStatus as $s): ?>
                    <li class="list-group-item d-flex justify-content-between">
                        <span><?= e(status_label($s['status'])) ?></span>
                        <strong><?= (int)$s['cnt'] ?></strong>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card-panel h-100">
            <div class="card-header"><i class="bi bi-building me-1"></i><?= e(__('reports.by_dept')) ?></div>
            <ul class="list-group list-group-flush">
                <?php if (!$byDept): ?><li class="list-group-item text-muted"><?= e(__('common.no_data')) ?></li><?php endif; ?>
                <?php foreach ($byDept as $d): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><?= e(department_display_name($d) ?: __('reports.no_dept')) ?></span>
                        <span>
                            <?php if (isset($d['incoming_cnt'])): ?>
                                <span class="small text-muted me-2"><?= e(__('type.incoming')) ?> <?= (int) $d['incoming_cnt'] ?></span>
                                <span class="small text-muted me-2"><?= e(__('type.outgoing')) ?> <?= (int) $d['outgoing_cnt'] ?></span>
                                <?php if (isset($d['internal_cnt'])): ?>
                                    <span class="small text-muted me-2"><?= e(__('type.internal')) ?> <?= (int) $d['internal_cnt'] ?></span>
                                <?php endif; ?>
                            <?php endif; ?>
                            <strong><?= (int)$d['cnt'] ?></strong>
                        </span>
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
    <div class="card-body">
        <p class="small text-muted mb-3"><?= e(__('reports.rsvp_period')) ?></p>
        <div class="d-flex flex-wrap gap-3">
            <span class="badge text-bg-success"><?= e(__('rsvp.attending')) ?>: <?= (int) $extras['rsvp_attending'] ?></span>
            <span class="badge text-bg-danger"><?= e(__('rsvp.declined')) ?>: <?= (int) $extras['rsvp_declined'] ?></span>
            <span class="badge text-bg-secondary"><?= e(__('rsvp.pending')) ?>: <?= (int) $extras['rsvp_pending'] ?></span>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card-panel" id="list-main" data-list-panel>
    <div class="card-header"><i class="bi bi-table me-1"></i><?= e(__('reports.details')) ?> <span class="text-muted small">(<?= (int) $detailTotal ?>)</span></div>
    <div class="list-panel-body">
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead>
                <tr>
                    <th><?= e(__('common.serial')) ?></th>
                    <th><?= e(__('corr.number')) ?></th>
                    <th><?= e(__('corr.type')) ?></th>
                    <th><?= e(__('corr.subject')) ?></th>
                    <th><?= e(__('corr.party')) ?></th>
                    <th><?= e(__('corr.date')) ?></th>
                    <th><?= e(__('corr.reply_due')) ?></th>
                    <th><?= e(__('corr.status')) ?></th>
                    <th><?= e(__('departments.col_officer')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$details): ?><tr><td colspan="9" class="text-center text-muted py-3"><?= e(__('common.no_results')) ?></td></tr><?php endif; ?>
            <?php
            $seq = (int) ($detailPagination['offset'] ?? 0);
            foreach ($details as $r):
                $seq++;
            ?>
                <tr class="<?= is_overdue($r['reply_due'], $r['status']) ? 'table-danger' : '' ?>">
                    <td class="text-muted"><?= (int) $seq ?></td>
                    <td><?= e(format_ref_number($r['ref_number'] ?? '')) ?></td>
                    <td>
                        <span class="badge <?= ($r['type'] ?? '') === 'outgoing' ? 'text-bg-secondary' : (($r['type'] ?? '') === 'internal' ? 'text-bg-warning' : 'text-bg-primary') ?>">
                            <?= e(type_label($r['type'])) ?>
                        </span>
                    </td>
                    <td><?= e($r['subject']) ?></td>
                    <td><?= e(correspondence_party_display_name($r)) ?></td>
                    <td><?php render_corr_date_cell($r['corr_date'] ?? null); ?></td>
                    <td><?= e(format_date($r['reply_due'])) ?></td>
                    <td><?= status_badge($r['status'], $r['cancel_kind'] ?? null) ?></td>
                    <td><?php render_correspondence_officer($r); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php $pageParam = 'page'; require __DIR__ . '/../includes/list_pagination.php'; ?>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
