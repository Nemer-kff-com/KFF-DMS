<?php
/**
 * طباعة تقرير الفترة — شعار في المنتصف + تذييل ثابت أسفل الصفحة
 */
require_once __DIR__ . '/../config/auth.php';
$user = require_login();
if (function_exists('ensure_correspondence_party_snapshot_schema')) {
    ensure_correspondence_party_snapshot_schema();
}

require __DIR__ . '/../includes/reports_scope.php';

$stmt = db()->prepare(
    "SELECT c.type, COUNT(*) AS cnt FROM `tblcorrespondence` c WHERE $whereSql GROUP BY c.type"
);
$stmt->execute($params);
$byType = ['incoming' => 0, 'outgoing' => 0, 'internal' => 0];
foreach ($stmt->fetchAll() as $row) {
    $byType[$row['type']] = (int) $row['cnt'];
}

$overdueStmt = db()->prepare(
    "SELECT COUNT(*) FROM `tblcorrespondence` c
     WHERE $whereSql AND c.reply_due IS NOT NULL AND c.reply_due < CURDATE()
       AND c.status NOT IN ('replied','delivered','closed','cancelled')"
);
$overdueStmt->execute($params);
$overdueCount = (int) $overdueStmt->fetchColumn();
$extras = correspondence_report_extras($whereSql, $params);

// سقف للطباعة: تقرير بعشرات الآلاف من الصفوف غير قابل للطباعة أصلاً،
// ومن تجاوز السقف يستخدم تصدير Excel الذي يُبثّ بلا حدّ.
$printLimit = 2000;
$detailStmt = db()->prepare(
    "SELECT c.ref_number, c.type, c.subject, c.party_name, c.corr_date, c.reply_due, c.status,
            c.snap_title_prefix, c.snap_person_name, c.snap_job_title, c.snap_honorific, c.snap_org_name,
            d.name AS department_name, d.name_en AS department_name_en,
            d.title_prefix AS department_title_prefix, d.directed_from AS department_directed_from
     FROM `tblcorrespondence` c
     LEFT JOIN `tbldepartments` d ON d.id = c.department_id
     WHERE $whereSql
     ORDER BY c.corr_date DESC, c.id DESC
     LIMIT " . ($printLimit + 1)
);
$detailStmt->execute($params);
$details = $detailStmt->fetchAll();
$detailsTruncated = count($details) > $printLimit;
if ($detailsTruncated) {
    $details = array_slice($details, 0, $printLimit);
}

$deptLabel = '';
$deptOffice = '';
if ($deptId > 0) {
    foreach (departments_list() as $d) {
        if ((int) $d['id'] === $deptId) {
            $deptLabel = department_person_option_label($d);
            $deptOffice = department_officer_subtitle($d);
            break;
        }
    }
}

$cssVer = @filemtime(__DIR__ . '/../assets/css/app.css') ?: time();
$avgClose = $extras['avg_close_days'] !== null ? (string) $extras['avg_close_days'] : '—';
?>
<!DOCTYPE html>
<html lang="<?= e(dms_lang_code()) ?>" dir="<?= e(dms_page_dir()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(__('reports.title')) ?></title>
    <link rel="icon" href="<?= e(base_url('assets/img/favicon.ico')) ?>">
    <link href="<?= e(dms_bootstrap_css()) ?>" rel="stylesheet">
    <link href="<?= e(dms_bootstrap_icons_css()) ?>" rel="stylesheet">
    <link href="<?= e(base_url('assets/css/app.css')) ?>?v=<?= (int) $cssVer ?>" rel="stylesheet">
    <link href="<?= e(dms_print_fast_css()) ?>" rel="stylesheet">
    <style>
      @page { size: A4 portrait; margin: 0; }
    </style>
</head>
<body class="print-page print-report">
<div class="print-toolbar no-print">
    <div class="print-toolbar-title"><?= e(__('reports.title')) ?></div>
    <div class="d-flex flex-wrap gap-2">
        <button type="button" class="btn btn-kff btn-sm" onclick="window.print()">
            <i class="bi bi-printer me-1"></i><?= e(__('action.print')) ?>
        </button>
        <a class="btn btn-outline-secondary btn-sm" href="<?= e(base_url('reports/index.php?' . http_build_query([
            'from' => $from,
            'to' => $to,
            'department_id' => $deptId,
            'type' => $typeFilter,
        ]))) ?>"><?= e(__('action.back')) ?></a>
    </div>
</div>

<div class="print-sheet print-sheet-wide print-report-sheet">
    <div class="print-report-body">
        <?php render_print_logo('print'); ?>

        <div class="print-meta-row print-meta-row-center">
            <div class="text-center w-100">
                <h1 class="print-doc-title"><?= e(__('reports.title')) ?></h1>
                <div class="print-doc-ref print-report-period">
                    <?= e(__('common.from')) ?> <?= e(format_date($from)) ?>
                    — <?= e(__('common.to')) ?> <?= e(format_date($to)) ?>
                    <?php if ($deptLabel !== ''): ?>
                        · <?= e(__('departments.col_officer')) ?>: <?= e($deptLabel) ?><?php if ($deptOffice !== ''): ?> <span class="small"><?= e($deptOffice) ?></span><?php endif; ?>
                    <?php endif; ?>
                    <?php if ($typeFilter !== 'all'): ?>
                        · <?= e(__('corr.type')) ?>: <?= e(type_label($typeFilter)) ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <table class="print-report-stats">
            <tr>
                <th><?= e(__('type.incoming')) ?></th>
                <td><?= (int) $byType['incoming'] ?></td>
                <th><?= e(__('type.outgoing')) ?></th>
                <td><?= (int) $byType['outgoing'] ?></td>
            </tr>
            <tr>
                <th><?= e(__('type.internal')) ?></th>
                <td><?= (int) $byType['internal'] ?></td>
                <th><?= e(__('common.total')) ?></th>
                <td><?= (int) $byType['incoming'] + (int) $byType['outgoing'] + (int) $byType['internal'] ?></td>
            </tr>
            <tr>
                <th><?= e(__('dash.overdue')) ?></th>
                <td><?= (int) $overdueCount ?></td>
                <th><?= e(__('reports.avg_close')) ?></th>
                <td><?= e($avgClose) ?></td>
            </tr>
            <tr>
                <th><?= e(__('actionq.no_receipt')) ?></th>
                <td><?= (int) $extras['missing_receipt'] ?></td>
                <th><?= e(__('actionq.unreplied')) ?></th>
                <td><?= (int) $extras['unreplied'] ?></td>
            </tr>
            <tr>
                <th><?= e(__('actionq.unsent')) ?></th>
                <td colspan="3"><?= (int) $extras['unsent'] ?></td>
            </tr>
            <?php
            $rsvpTotal = (int) ($extras['rsvp_total'] ?? 0);
            if ($rsvpTotal <= 0) {
                $rsvpTotal = (int) $extras['rsvp_attending'] + (int) $extras['rsvp_declined'] + (int) $extras['rsvp_pending'];
            }
            if ($rsvpTotal > 0):
            ?>
            <tr>
                <th><?= e(__('reports.rsvp')) ?></th>
                <td colspan="3">
                    <?= e(__('rsvp.attending')) ?> <?= (int) $extras['rsvp_attending'] ?>
                    · <?= e(__('rsvp.declined')) ?> <?= (int) $extras['rsvp_declined'] ?>
                    · <?= e(__('rsvp.pending')) ?> <?= (int) $extras['rsvp_pending'] ?>
                </td>
            </tr>
            <?php endif; ?>
        </table>

        <div class="print-report-section"><?= e(__('reports.details')) ?></div>
        <table class="print-report-table">
            <colgroup>
                <col class="c-seq">
                <col class="c-ref">
                <col class="c-type">
                <col class="c-subject">
                <col class="c-party">
                <col class="c-date">
                <col class="c-date">
                <col class="c-status">
                <col class="c-dept">
            </colgroup>
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
            <?php if (!$details): ?>
                <tr><td colspan="9" class="text-center"><?= e(__('common.no_results')) ?></td></tr>
            <?php endif; ?>
            <?php $seq = 0; foreach ($details as $r): $seq++; ?>
                <tr>
                    <td class="c-seq"><?= (int) $seq ?></td>
                    <td class="c-ref" dir="ltr"><?= e(format_ref_number($r['ref_number'] ?? '')) ?></td>
                    <td>
                        <span class="badge <?= ($r['type'] ?? '') === 'outgoing' ? 'text-bg-secondary' : (($r['type'] ?? '') === 'internal' ? 'text-bg-warning' : 'text-bg-primary') ?>">
                            <?= e(type_label($r['type'])) ?>
                        </span>
                    </td>
                    <td><?= e($r['subject']) ?></td>
                    <td><?= e(correspondence_party_display_name($r)) ?></td>
                    <td><?php render_corr_date_cell($r['corr_date'] ?? null); ?></td>
                    <td><?= e(format_date($r['reply_due'])) ?></td>
                    <td><?= e(status_label($r['status'])) ?></td>
                    <td><?php render_correspondence_officer($r); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($detailsTruncated): ?>
                <tr>
                    <td colspan="9" class="text-center">
                        <?= e(str_replace(':count', (string) $printLimit, __('reports.print_truncated'))) ?>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php render_print_page_footer(); ?>
</div>
<script src="<?= e(dms_bootstrap_js()) ?>"></script>
<script src="<?= e(base_url('assets/js/app.js')) ?>?v=<?= (int) (@filemtime(__DIR__ . '/../assets/js/app.js') ?: 24) ?>"></script>
<script>
</script>
</body>
</html>
