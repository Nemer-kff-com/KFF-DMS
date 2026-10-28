<?php
require_once __DIR__ . '/../config/auth.php';
$user = require_login();
$pageTitle = __('nav.circulars');

$filters = [
    'q' => trim($_GET['q'] ?? ''),
    'status' => (string) ($_GET['status'] ?? ''),
    'from' => $_GET['from'] ?? '',
    'to' => $_GET['to'] ?? '',
    'hijri_from' => trim((string) ($_GET['hijri_from'] ?? '')),
    'hijri_to' => trim((string) ($_GET['hijri_to'] ?? '')),
    'department_id' => $_GET['department_id'] ?? '',
];
$list = circular_list_page($filters, $user, dms_request_page());
$rows = $list['rows'];
$pagination = $list['pagination'];
$listTotal = $list['total'];
$departments = departments_list();
if (function_exists('departments_visible_to_user')) {
    $departments = departments_visible_to_user($user, $departments);
}
$pageScripts = dms_list_page_scripts();
$reportQuery = http_build_query(array_filter($filters, static fn($v) => $v !== '' && $v !== null));
$reportQs = $reportQuery !== '' ? '?' . $reportQuery : '';

require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title"><?= e(__('circular.title')) ?></h1>
        <p class="page-sub"><?= e(__('circular.subtitle')) ?></p>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-outline-secondary" href="<?= e(base_url('circulars/list_print.php' . $reportQs)) ?>" target="_blank"><i class="bi bi-printer"></i> <?= e(__('action.print')) ?></a>
        <a class="btn btn-outline-secondary" href="<?= e(base_url('circulars/export.php' . $reportQs)) ?>"><i class="bi bi-file-earmark-excel"></i> <?= e(__('action.export_excel')) ?></a>
        <?= dms_perm_add_btn('circulars.create', base_url('circulars/create.php'), __('circular.new'), 'bi-megaphone', $user) ?>
    </div>
</div>

<div class="card-panel mb-3">
    <div class="card-body p-3">
        <form class="list-filters" method="get">
            <div class="list-filters-main">
                <div class="list-filters-item list-filters-search">
                    <label class="form-label"><?= e(__('action.search')) ?></label>
                    <input type="text" name="q" class="form-control" placeholder="<?= e(__('circular.search_ph')) ?>" value="<?= e($filters['q']) ?>">
                </div>
                <div class="list-filters-item">
                    <label class="form-label"><?= e(__('common.status')) ?></label>
                    <select name="status" class="form-select">
                        <option value=""><?= e(__('common.all')) ?></option>
                        <option value="draft" <?= $filters['status'] === 'draft' ? 'selected' : '' ?>><?= e(__('circular.status.draft')) ?></option>
                        <option value="issued" <?= $filters['status'] === 'issued' ? 'selected' : '' ?>><?= e(__('circular.status.issued')) ?></option>
                    </select>
                </div>
                <?php render_filter_department_field($user, $departments, $filters['department_id']); ?>
            </div>
            <div class="list-filters-footer">
                <?php render_filter_date_range($filters); ?>
                <div class="list-filters-actions">
                    <button class="btn btn-outline-secondary w-100" type="submit"><?= e(__('action.filter')) ?></button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card-panel" id="list-main" data-list-panel>
    <div class="list-panel-body">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th><?= e(__('circular.ref')) ?></th>
                    <th><?= e(__('circular.subject')) ?></th>
                    <th><?= e(__('circular.issuing_dept')) ?></th>
                    <th><?= e(__('circular.audience')) ?></th>
                    <th class="list-col-date"><?= e(__('circular.date')) ?></th>
                    <th><?= e(__('common.status')) ?></th>
                    <th><?= e(__('circular.files')) ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="8" class="text-center text-muted py-4"><?= e(__('circular.no_items')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td dir="ltr" class="fw-semibold"><?= e($r['ref_number']) ?></td>
                    <td>
                        <a class="fw-semibold" href="<?= e(base_url('circulars/view.php?id='.(int)$r['id'])) ?>"><?= e($r['subject']) ?></a>
                        <?php if (circular_is_electronic($r)): ?>
                            <span class="badge text-bg-light border ms-1"><?= e(__('circular.badge_electronic')) ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?php render_correspondence_department_officer(circular_officer_display_row($r), 'receiving'); ?></td>
                    <td><?= e(circular_audience_label((string) $r['audience'])) ?></td>
                    <td class="list-col-date"><?php render_corr_date_cell($r['circular_date'] ?? null); ?></td>
                    <td><?= circular_status_badge((string) $r['status']) ?></td>
                    <td><?= (int) $r['files_count'] ?></td>
                    <td><a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('circulars/view.php?id='.(int)$r['id'])) ?>"><?= e(__('action.view_circular')) ?></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php $pageParam = 'page'; require __DIR__ . '/../includes/list_pagination.php'; ?>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
