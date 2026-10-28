<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/correspondence.php';
$user = require_login();
$pageTitle = __('nav.outgoing');

$filters = [
    'q' => trim($_GET['q'] ?? ''),
    'status' => $_GET['status'] ?? '',
    'status_group' => $_GET['status_group'] ?? '',
    'receipt' => $_GET['receipt'] ?? '',
    'channel' => $_GET['channel'] ?? '',
    'issue_approval' => $_GET['issue_approval'] ?? '',
    'action' => $_GET['action'] ?? '',
    'from' => $_GET['from'] ?? '',
    'to' => $_GET['to'] ?? '',
    'hijri_from' => trim((string) ($_GET['hijri_from'] ?? '')),
    'hijri_to' => trim((string) ($_GET['hijri_to'] ?? '')),
    'department_id' => $_GET['department_id'] ?? '',
    'prep' => trim((string) ($_GET['prep'] ?? '')),
];
$list = correspondence_list_page('outgoing', $filters, $user, dms_request_page());
$rows = $list['rows'];
$pagination = $list['pagination'];
$listTotal = $list['total'];
$departments = departments_list();
$missingReceiptCount = outgoing_missing_receipt_count($user);
$alertDays = outgoing_receipt_alert_days();
$showIssueAppr = function_exists('outgoing_approval_badge');
$pageScripts = dms_list_page_scripts();
$prepFilter = trim((string) ($filters['prep'] ?? ''));
$prepEnabled = function_exists('outgoing_prep_enabled') && outgoing_prep_enabled();
$prepMineCount = correspondence_list_count('outgoing', ['prep' => 'mine'], $user);
$prepSignerCount = function_exists('outgoing_user_may_see_prep_signer_queue') && outgoing_user_may_see_prep_signer_queue($user)
    ? correspondence_list_count('outgoing', ['prep' => 'signer'], $user)
    : 0;
$showPrepTabs = $prepEnabled || $prepMineCount > 0 || $prepSignerCount > 0;

require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <div class="page-header-main">
        <h1 class="page-title"><?= e(__('corr.outgoing_list')) ?></h1>
        <p class="page-sub"><?= e(__('corr.outgoing_sub')) ?></p>
        <div class="page-header-bulk">
            <?php $bulkUi = dms_perm_ui('outgoing.bulk', $user); ?>
            <?php $canViewBatches = function_exists('user_can_view_outgoing_batch_pages') && user_can_view_outgoing_batch_pages($user); ?>
            <?php if ($bulkUi === 'allow'): ?>
                <a class="btn btn-outline-secondary btn-sm" href="<?= e(base_url('outgoing/bulk.php')) ?>">
                    <i class="bi bi-people me-1"></i><?= e(__('corr.outgoing_bulk')) ?>
                </a>
                <a class="btn btn-outline-secondary btn-sm" href="<?= e(base_url('outgoing/batches.php')) ?>">
                    <i class="bi bi-collection me-1"></i><?= e(__('bulk.batches_title')) ?>
                </a>
            <?php else: ?>
                <?php if ($bulkUi === 'deny'): ?>
                    <?= dms_perm_denied_btn(__('corr.outgoing_bulk'), 'bi-people', 'btn-outline-secondary btn-sm') ?>
                <?php endif; ?>
                <?php if ($canViewBatches): ?>
                    <a class="btn btn-outline-secondary btn-sm" href="<?= e(base_url('outgoing/batches.php')) ?>">
                        <i class="bi bi-collection me-1"></i><?= e(__('bulk.batches_title')) ?>
                    </a>
                <?php elseif ($bulkUi === 'deny'): ?>
                    <?= dms_perm_denied_btn(__('bulk.batches_title'), 'bi-collection', 'btn-outline-secondary btn-sm') ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php $addBtn = dms_perm_add_btn('outgoing.create', base_url('outgoing/create.php'), __('corr.outgoing_new'), 'bi-plus-lg', $user); ?>
    <?php if ($addBtn !== ''): ?>
        <div class="page-header-actions">
            <?= $addBtn ?>
        </div>
    <?php endif; ?>
</div>

<?php if ($showPrepTabs): ?>
<div class="mb-3 d-flex flex-wrap gap-2">
    <a class="btn btn-sm <?= $prepFilter === '' ? 'btn-kff' : 'btn-outline-secondary' ?>" href="<?= e(base_url('outgoing/index.php')) ?>">
        <?= e(__('out_prep.filter_all')) ?>
    </a>
    <a class="btn btn-sm <?= $prepFilter === 'mine' ? 'btn-kff' : 'btn-outline-secondary' ?>" href="<?= e(base_url('outgoing/index.php?prep=mine')) ?>">
        <?= e(__('out_prep.filter_mine')) ?><?php if ($prepMineCount > 0): ?> <span class="badge text-bg-light text-dark"><?= (int) $prepMineCount ?></span><?php endif; ?>
    </a>
    <?php if (function_exists('outgoing_user_may_see_prep_signer_queue') && outgoing_user_may_see_prep_signer_queue($user)): ?>
    <a class="btn btn-sm <?= $prepFilter === 'signer' ? 'btn-kff' : 'btn-outline-secondary' ?>" href="<?= e(base_url('outgoing/index.php?prep=signer')) ?>">
        <?= e(__('out_prep.filter_signer')) ?> <span class="badge text-bg-light text-dark"><?= (int) $prepSignerCount ?></span>
    </a>
    <?php endif; ?>
</div>
<?php endif; ?>

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
       href="<?= e(base_url('outgoing/index.php?' . http_build_query(['receipt' => 'overdue_missing']))) ?>">
        <?= e(__('receipt.alert_view')) ?>
    </a>
</div>
<?php endif; ?>

<div class="card-panel mb-3">
    <div class="card-body p-3">
        <form class="list-filters" method="get">
            <?php render_correspondence_list_filters($user, $departments, $filters, [
                'list_type' => 'outgoing',
                'hidden' => [
                    'status_group' => $filters['status_group'] ?? '',
                    'prep' => $filters['prep'] ?? '',
                ],
            ]); ?>
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
                    <th><?= e(__('corr.subject')) ?></th>
                    <th><?= e(__('corr.party_from')) ?></th>
                    <th><?= e(__('corr.party_to')) ?></th>
                    <th class="list-col-date"><?= e(__('corr.date')) ?></th>
                    <th><?= e(__('corr.status')) ?></th>
                    <?php if ($showIssueAppr): ?>
                    <th><?= e(__('issue_appr.col')) ?></th>
                    <?php endif; ?>
                    <th><?= e(__('receipt.col')) ?></th>
                    <th><?= e(__('corr.linked')) ?></th>
                    <?php require __DIR__ . '/../includes/corr_linked_col_th.php'; ?>
                </tr>
            </thead>
            <tbody>
            <?php
            $colspan = $showIssueAppr ? 10 : 9;
            if (!$rows): ?><tr><td colspan="<?= (int) $colspan ?>" class="text-center text-muted py-4"><?= e(__('corr.no_items')) ?></td></tr><?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <?php
                $hasReceipt = correspondence_has_receipt($r);
                $isElectronic = function_exists('correspondence_intend_electronic') && correspondence_intend_electronic($r);
                $rowEmail = $isElectronic && function_exists('dms_corr_email_address')
                    ? dms_corr_email_address($r)
                    : '';
                $warnMissingReceipt = !$isElectronic && !$hasReceipt && !correspondence_is_settled($r)
                    && ($r['corr_date'] ?? '') <= date('Y-m-d', strtotime('-' . $alertDays . ' days'));
                ?>
                <tr class="<?= $warnMissingReceipt ? 'table-warning' : '' ?>">
                    <td class="fw-semibold">
                        <div><?= e(function_exists('correspondence_ref_display') ? correspondence_ref_display($r) : $r['ref_number']) ?></div>
                        <?php if ($isElectronic): ?>
                            <span class="badge text-bg-info mt-1" title="<?= e(__('corr.intend_electronic')) ?>">
                                <i class="bi bi-envelope-at me-1"></i><?= e(__('corr.electronic_badge')) ?>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td><?= e($r['subject']) ?></td>
                    <td>
                        <?php
                        render_correspondence_department_officer($r, 'issuing');
                        ?>
                    </td>
                    <td>
                        <?php
                        $partyParts = correspondence_archived_party($r);
                        if (trim((string) ($partyParts['person'] ?? '')) === '') {
                            $partyParts['person'] = trim((string) ($r['party_name'] ?? ''));
                        }
                        render_corr_list_person($partyParts);
                        ?>
                        <?php if ($isElectronic): ?>
                            <?php if ($rowEmail !== ''): ?>
                                <div class="small text-muted mt-1" dir="ltr"><?= e($rowEmail) ?></div>
                            <?php else: ?>
                                <div class="small text-danger mt-1"><?= e(__('send.missing_email')) ?></div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td class="list-col-date"><?php render_corr_date_cell($r['corr_date'] ?? null); ?></td>
                    <td><?= status_badge($r['status'], $r['cancel_kind'] ?? null) ?></td>
                    <?php if ($showIssueAppr): ?>
                    <td><?= function_exists('outgoing_approval_badge') ? outgoing_approval_badge($r) : '' ?></td>
                    <?php endif; ?>
                    <td>
                        <?php if ($isElectronic): ?>
                            <?php if (($r['status'] ?? '') === 'delivered'): ?>
                                <span class="badge text-bg-success"><?= e(__('status.delivered')) ?></span>
                            <?php elseif (($r['status'] ?? '') === 'sent'): ?>
                                <span class="badge text-bg-info"><?= e(__('status.sent')) ?></span>
                            <?php else: ?>
                                <span class="badge text-bg-info" title="<?= e(__('corr.intend_electronic')) ?>">
                                    <i class="bi bi-envelope-at me-1"></i><?= e(__('corr.channel_electronic')) ?>
                                </span>
                            <?php endif; ?>
                        <?php elseif ($hasReceipt): ?>
                            <span class="badge text-bg-success"><?= e(__('receipt.recorded')) ?></span>
                        <?php else: ?>
                            <span class="badge text-bg-secondary"><?= e(__('receipt.pending')) ?></span>
                        <?php endif; ?>
                        <?php if ((int) ($r['documents_count'] ?? 0) > 0): ?>
                            <span class="badge text-bg-success" title="<?= e(__('corr.attach')) ?>"><i class="bi bi-paperclip"></i></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($r['linked_ref']) && in_array((string) ($r['linked_type'] ?? ''), ['incoming', 'outgoing', 'internal'], true)): ?>
                            <a class="badge text-bg-success text-decoration-none" href="<?= e(base_url('correspondence/related.php?id=' . (int) $r['id'])) ?>" title="<?= e(__('corr.related_open')) ?>"><?= e($r['linked_ref']) ?></a>
                        <?php endif; ?>
                    </td>
                    <td><a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('outgoing/view.php?id='.(int)$r['id'])) ?>"><?= e(__('action.view')) ?></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php require __DIR__ . '/../includes/list_pagination.php'; ?>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
