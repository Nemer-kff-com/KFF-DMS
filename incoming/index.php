<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/correspondence.php';
$user = require_login();
$pageTitle = __('nav.incoming');

$filters = [
    'q' => trim($_GET['q'] ?? ''),
    'status' => $_GET['status'] ?? '',
    'status_group' => $_GET['status_group'] ?? '',
    'action' => $_GET['action'] ?? '',
    'from' => $_GET['from'] ?? '',
    'to' => $_GET['to'] ?? '',
    'hijri_from' => trim((string) ($_GET['hijri_from'] ?? '')),
    'hijri_to' => trim((string) ($_GET['hijri_to'] ?? '')),
    'department_id' => $_GET['department_id'] ?? '',
];
$list = correspondence_list_page('incoming', $filters, $user, dms_request_page());
$rows = $list['rows'];
$pagination = $list['pagination'];
$listTotal = $list['total'];
$departments = departments_list();
$showIncomingAppr = function_exists('incoming_approval_badge');
$pageScripts = dms_list_page_scripts();

require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title"><?= e(__('corr.incoming_list')) ?></h1>
        <p class="page-sub"><?= e(__('corr.incoming_sub')) ?></p>
    </div>
    <?php $addBtn = dms_perm_add_btn('incoming.create', base_url('incoming/create.php'), __('corr.incoming_new'), 'bi-plus-lg', $user); ?>
    <?php if ($addBtn !== ''): ?>
        <div class="page-header-actions">
            <?= $addBtn ?>
        </div>
    <?php endif; ?>
</div>

<div class="card-panel mb-3">
    <div class="card-body p-3">
        <form class="list-filters" method="get">
            <?php render_correspondence_list_filters($user, $departments, $filters, [
                'list_type' => 'incoming',
                'hidden' => ['status_group' => $filters['status_group'] ?? ''],
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
                    <th><?= e(__('corr.reply_due')) ?></th>
                    <th><?= e(__('corr.status')) ?></th>
                    <?php if ($showIncomingAppr): ?>
                    <th><?= e(__('issue_appr.col')) ?></th>
                    <?php endif; ?>
                    <th><?= e(__('corr.linked')) ?></th>
                    <?php require __DIR__ . '/../includes/corr_linked_col_th.php'; ?>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="<?= $showIncomingAppr ? 10 : 9 ?>" class="text-center text-muted py-4"><?= e(__('corr.no_items')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <tr class="<?= is_overdue($r['reply_due'], $r['status']) ? 'table-danger' : '' ?>">
                    <td class="fw-semibold"><?= e($r['ref_number']) ?></td>
                    <td><?= e($r['subject']) ?></td>
                    <td>
                        <?php
                        $partyParts = correspondence_archived_party($r);
                        if (trim((string) ($partyParts['person'] ?? '')) === '') {
                            $partyParts['person'] = trim((string) ($r['party_name'] ?? ''));
                        }
                        render_corr_list_person($partyParts);
                        ?>
                    </td>
                    <td>
                        <?php
                        render_correspondence_department_officer($r, 'receiving');
                        ?>
                    </td>
                    <td class="list-col-date"><?php render_corr_date_cell($r['corr_date'] ?? null); ?></td>
                    <td>
                        <?= e(format_date($r['reply_due'])) ?>
                        <?php if (is_overdue($r['reply_due'], $r['status'])): ?>
                            <span class="badge badge-overdue"><?= e(__('corr.overdue_badge')) ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?= status_badge($r['status'], $r['cancel_kind'] ?? null) ?></td>
                    <?php if ($showIncomingAppr): ?>
                    <td><?= incoming_approval_badge($r) ?></td>
                    <?php endif; ?>
                    <td>
                        <?php if (!empty($r['reply_ref']) || (($r['linked_type'] ?? '') === 'outgoing' && !empty($r['linked_ref']))): ?>
                            <a class="badge text-bg-success text-decoration-none" href="<?= e(base_url('correspondence/related.php?id=' . (int) $r['id'])) ?>" title="<?= e(__('corr.related_open')) ?>"><?= e($r['reply_ref'] ?: $r['linked_ref']) ?></a>
                        <?php else: ?>
                            <span class="badge text-bg-secondary"><?= e(__('corr.no_reply')) ?></span>
                        <?php endif; ?>
                        <?php if ((int) ($r['documents_count'] ?? 0) > 0): ?>
                            <span class="badge text-bg-success" title="<?= e(__('corr.attach')) ?>"><i class="bi bi-paperclip"></i></span>
                        <?php endif; ?>
                    </td>
                    <td><a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('incoming/view.php?id='.(int)$r['id'])) ?>"><?= e(__('action.view')) ?></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php $pageParam = 'page'; require __DIR__ . '/../includes/list_pagination.php'; ?>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
