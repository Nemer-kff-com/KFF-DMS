<?php
/**
 * قائمة موحدة للمعاملات (وارد + صادر) — تُستخدم من لوحة التحكم
 */
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/correspondence.php';
$user = require_login();

$filters = [
    'q' => trim($_GET['q'] ?? ''),
    'status' => $_GET['status'] ?? '',
    'status_group' => $_GET['status_group'] ?? '',
    'type' => $_GET['type'] ?? 'all',
    'from' => $_GET['from'] ?? '',
    'to' => $_GET['to'] ?? '',
    'hijri_from' => trim((string) ($_GET['hijri_from'] ?? '')),
    'hijri_to' => trim((string) ($_GET['hijri_to'] ?? '')),
    'department_id' => $_GET['department_id'] ?? '',
    'receipt' => $_GET['receipt'] ?? '',
    'action' => $_GET['action'] ?? '',
    'overdue' => !empty($_GET['overdue']) ? '1' : '',
];

$typeFilter = $filters['type'];
if ($typeFilter !== 'incoming' && $typeFilter !== 'outgoing' && $typeFilter !== 'internal') {
    $typeFilter = 'all';
}

if ($filters['overdue'] !== '') {
    $pageTitle = __('dash.overdue');
} elseif ($filters['status_group'] === 'open') {
    $pageTitle = __('dash.in_progress');
} elseif ($filters['status_group'] === 'done') {
    $pageTitle = __('dash.completed');
} else {
    $pageTitle = __('nav.transactions');
}

$list = correspondence_list_page($typeFilter, $filters, $user, dms_request_page());
$rows = $list['rows'];
$pagination = $list['pagination'];
$listTotal = $list['total'];
$departments = departments_list();
$missingReceiptCount = $typeFilter !== 'incoming' ? outgoing_missing_receipt_count($user) : 0;
$alertDays = outgoing_receipt_alert_days();
$showAppr = function_exists('corr_approval_badge');
$pageScripts = dms_list_page_scripts();

require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title"><?= e($pageTitle) ?></h1>
        <p class="page-sub">
            <?php if ($filters['overdue'] !== ''): ?>
                <?= e(__('corr.overdue_sub')) ?>
            <?php elseif ($filters['status_group'] === 'open'): ?>
                <?= e(__('corr.open_sub')) ?>
            <?php elseif ($filters['status_group'] === 'done'): ?>
                <?= e(__('corr.done_sub')) ?>
            <?php else: ?>
                <?= e(__('corr.all_sub')) ?>
            <?php endif; ?>
            <span class="text-muted">(<?= (int) $listTotal ?>)</span>
        </p>
    </div>
</div>

<?php if ($missingReceiptCount > 0): ?>
<div class="alert alert-warning d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <i class="bi bi-exclamation-triangle me-1"></i>
        <?= e(str_replace(
            [':count', ':days'],
            [(string) $missingReceiptCount, (string) $alertDays],
            __('receipt.alert_overdue')
        )) ?>
    </div>
    <a class="btn btn-sm btn-outline-dark"
       href="<?= e(base_url('correspondence/index.php?' . http_build_query(array_filter([
           'receipt' => 'overdue_missing',
           'type' => $typeFilter !== 'all' ? $typeFilter : 'outgoing',
           'status_group' => $filters['status_group'] ?: null,
           'overdue' => $filters['overdue'] ?: null,
       ])))) ?>">
        <?= e(__('receipt.alert_view')) ?>
    </a>
</div>
<?php endif; ?>

<div class="card-panel mb-3">
    <div class="card-body p-3">
        <form class="list-filters" method="get">
            <?php
            $statusOptions = correspondence_status_codes();
            if ($filters['status_group'] === 'open') {
                $statusOptions = correspondence_open_statuses();
            } elseif ($filters['status_group'] === 'done') {
                $statusOptions = correspondence_done_statuses();
            }
            render_correspondence_list_filters($user, $departments, $filters, [
                'list_type' => $typeFilter,
                'show_type' => true,
                'show_receipt' => $typeFilter !== 'incoming',
                'type_value' => $typeFilter,
                'status_options' => $statusOptions,
                'hidden' => [
                    'status_group' => $filters['status_group'],
                    'overdue' => $filters['overdue'],
                ],
            ]);
            ?>
        </form>
    </div>
</div>

<div class="card-panel" id="list-main" data-list-panel>
    <div class="list-panel-body">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th><?= e(__('corr.number')) ?></th>
                    <th><?= e(__('corr.type')) ?></th>
                    <th><?= e(__('corr.subject')) ?></th>
                    <th><?= e(__('corr.party')) ?></th>
                    <th><?= e(__('corr.date')) ?></th>
                    <th><?= e(__('departments.col_officer')) ?></th>
                    <th class="list-col-status"><?= e(__('corr.status')) ?></th>
                    <?php if ($showAppr): ?>
                    <th class="list-col-appr"><?= e(__('issue_appr.col')) ?></th>
                    <?php endif; ?>
                    <th class="list-col-after-appr"><?= e(__('receipt.col')) ?></th>
                    <th><?= e(__('corr.linked')) ?></th>
                    <?php require __DIR__ . '/../includes/corr_linked_col_th.php'; ?>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="<?= $showAppr ? 11 : 10 ?>" class="text-center text-muted py-4"><?= e(__('corr.no_items')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <?php
                $viewBase = match ($r['type'] ?? '') {
                    'outgoing' => 'outgoing/view.php',
                    'internal' => 'internal/view.php',
                    default => 'incoming/view.php',
                };
                ?>
                <tr class="<?= is_overdue($r['reply_due'] ?? null, $r['status'] ?? '') ? 'table-danger' : '' ?>">
                    <td class="fw-semibold"><?= e(function_exists('correspondence_ref_display') ? correspondence_ref_display($r) : $r['ref_number']) ?></td>
                    <td>
                        <span class="badge <?= ($r['type'] ?? '') === 'outgoing' ? 'text-bg-secondary' : 'text-bg-primary' ?>">
                            <?= e(type_label($r['type'] ?? 'incoming')) ?>
                        </span>
                    </td>
                    <td><?= e($r['subject']) ?></td>
                    <td><?= e(correspondence_party_display_name($r)) ?></td>
                    <td><?php render_corr_date_cell($r['corr_date'] ?? null); ?></td>
                    <td><?php render_correspondence_officer($r); ?></td>
                    <td class="list-col-status"><?= status_badge($r['status'], $r['cancel_kind'] ?? null) ?></td>
                    <?php if ($showAppr): ?>
                    <td class="list-col-appr"><?= corr_approval_badge($r) ?></td>
                    <?php endif; ?>
                    <td class="list-col-after-appr">
                        <?php if (($r['type'] ?? '') === 'outgoing'): ?>
                            <?php if (correspondence_has_receipt($r)): ?>
                                <span class="badge text-bg-success"><?= e(__('receipt.recorded')) ?></span>
                            <?php else: ?>
                                <span class="badge text-bg-secondary text-nowrap"><?= e(__('receipt.pending')) ?></span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php
                        $linkedShow = trim((string) ($r['reply_ref'] ?? '')) !== ''
                            ? (string) $r['reply_ref']
                            : trim((string) ($r['linked_ref'] ?? ''));
                        ?>
                        <?php if ($linkedShow !== '' && in_array((string) ($r['type'] ?? ''), ['incoming', 'outgoing'], true)): ?>
                            <a class="badge text-bg-success text-decoration-none" href="<?= e(base_url('correspondence/related.php?id=' . (int) $r['id'])) ?>" title="<?= e(__('corr.related_open')) ?>"><?= e($linkedShow) ?></a>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a class="btn btn-sm btn-outline-primary" href="<?= e(base_url($viewBase . '?id=' . (int) $r['id'])) ?>">
                            <?= e(__('action.view')) ?>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php $pageParam = 'page'; require __DIR__ . '/../includes/list_pagination.php'; ?>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
