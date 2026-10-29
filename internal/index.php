<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/correspondence.php';
$user = require_login();
$pageTitle = __('nav.internal');

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
    'issuing_department_id' => $_GET['issuing_department_id'] ?? '',
];
$list = correspondence_list_page('internal', $filters, $user, dms_request_page());
$rows = $list['rows'];
$pagination = $list['pagination'];
$listTotal = $list['total'];
$departments = departments_list();
$deptById = [];
foreach ($departments as $d) {
    $deptById[(int) $d['id']] = $d;
}
$showInternalAppr = function_exists('internal_approval_badge');
$pageScripts = dms_list_page_scripts();

require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title"><?= e(__('internal.list_title')) ?></h1>
        <p class="page-sub"><?= e(__('internal.list_sub')) ?></p>
    </div>
    <?php $addBtn = dms_perm_add_btn('internal.create', base_url('internal/create.php'), __('internal.new'), 'bi-plus-lg', $user); ?>
    <?php if ($addBtn !== ''): ?>
        <div class="page-header-actions">
            <?= $addBtn ?>
        </div>
    <?php endif; ?>
</div>

<?php render_correspondence_filters_card($user, $departments, $filters, [
    'list_type' => 'internal',
    'hidden' => ['status_group' => $filters['status_group'] ?? ''],
]); ?>

<div class="card-panel" id="list-main" data-list-panel>
    <div class="list-panel-body">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th class="text-center"><?= e(__('corr.number')) ?></th>
                    <th><?= e(__('corr.subject')) ?></th>
                    <th><?= e(__('internal.from_department')) ?></th>
                    <th><?= e(__('internal.to_department')) ?></th>
                    <th><?= e(__('corr.date')) ?></th>
                    <th><?= e(__('corr.reply_due')) ?></th>
                    <th class="list-col-status"><?= e(__('corr.status')) ?></th>
                    <?php if ($showInternalAppr): ?>
                    <th class="list-col-appr"><?= e(__('issue_appr.col')) ?></th>
                    <?php endif; ?>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="<?= $showInternalAppr ? 9 : 8 ?>" class="text-center text-muted py-4"><?= e(__('corr.no_items')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <tr class="<?= is_overdue($r['reply_due'], $r['status']) ? 'table-danger' : '' ?>">
                    <td class="fw-semibold text-center" dir="ltr"><?= e($r['ref_number']) ?></td>
                    <td><?= e($r['subject']) ?></td>
                    <td>
                        <?php render_correspondence_department_officer($r, 'issuing'); ?>
                    </td>
                    <td>
                        <?php
                        if (function_exists('correspondence_has_party_snapshot') && correspondence_has_party_snapshot($r)) {
                            $toParts = correspondence_archived_party($r);
                            render_corr_list_person($toParts);
                        } else {
                            $toDeptId = (int) ($r['internal_to_department_id'] ?? 0);
                            if ($toDeptId <= 0) {
                                $toDeptId = (int) ($r['department_id'] ?? 0);
                            }
                            $toDept = $deptById[$toDeptId] ?? [];
                            render_corr_list_person([
                                'prefix' => (string) ($toDept['title_prefix'] ?? ''),
                                'person' => trim((string) ($toDept['directed_from'] ?? '')),
                            ]);
                        }
                        ?>
                    </td>
                    <td><?php render_corr_date_cell($r['corr_date'] ?? null); ?></td>
                    <td>
                        <?= e(format_date($r['reply_due'])) ?>
                        <?php if (is_overdue($r['reply_due'], $r['status'])): ?>
                            <span class="badge badge-overdue"><?= e(__('corr.overdue_badge')) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="list-col-status">
                        <?= status_badge($r['status'], $r['cancel_kind'] ?? null) ?>
                        <?php if ((int) ($r['documents_count'] ?? 0) > 0): ?>
                            <span class="badge text-bg-success" title="<?= e(__('corr.attach')) ?>"><i class="bi bi-paperclip"></i></span>
                        <?php endif; ?>
                    </td>
                    <?php if ($showInternalAppr): ?>
                    <td class="list-col-appr"><?= internal_approval_badge($r) ?></td>
                    <?php endif; ?>
                    <td><a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('internal/view.php?id='.(int)$r['id'])) ?>"><?= e(__('action.view')) ?></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php $pageParam = 'page'; require __DIR__ . '/../includes/list_pagination.php'; ?>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
