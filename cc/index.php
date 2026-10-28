<?php
/**
 * صندوق «للاطلاع»: المعاملات التي وصلت للمستخدم للاطلاع بعد اعتمادها
 */
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/correspondence.php';
$user = require_login();
$pageTitle = __('cc.inbox_title');

$filter = (string) ($_GET['f'] ?? '');
if (!in_array($filter, ['unseen', 'seen'], true)) {
    $filter = '';
}
$list = corr_cc_inbox_page((int) $user['id'], $filter, dms_request_page());
$rows = $list['rows'];
$pagination = $list['pagination'];
$unseenCount = corr_cc_unread_count((int) $user['id']);

require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title"><?= e(__('cc.inbox_title')) ?></h1>
        <p class="page-sub"><?= e(__('cc.inbox_sub')) ?></p>
    </div>
</div>

<div class="card-panel mb-3">
    <div class="card-body p-3 d-flex flex-wrap gap-2">
        <?php foreach (['' => __('cc.filter_all'), 'unseen' => __('cc.filter_unseen'), 'seen' => __('cc.filter_seen')] as $key => $label): ?>
            <a class="btn btn-sm <?= $filter === $key ? 'btn-kff' : 'btn-outline-secondary' ?>"
               href="<?= e(base_url('cc/index.php' . ($key !== '' ? '?f=' . $key : ''))) ?>">
                <?= e($label) ?>
                <?php if ($key === 'unseen' && $unseenCount > 0): ?>
                    <span class="badge text-bg-danger ms-1"><?= (int) $unseenCount ?></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
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
                    <th><?= e(__('corr.date')) ?></th>
                    <th><?= e(__('cc.from_creator')) ?></th>
                    <th><?= e(__('cc.released_at')) ?></th>
                    <th><?= e(__('cc.col_status')) ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="8" class="text-center text-muted py-4"><?= e(__('cc.inbox_empty')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <?php $viewUrl = base_url(correspondence_type_folder((string) $r['type']) . '/view.php?id=' . (int) $r['id']); ?>
                <tr class="<?= empty($r['seen_at']) ? 'fw-semibold' : '' ?>">
                    <td dir="ltr"><?= e($r['ref_number']) ?></td>
                    <td><?= e(type_label((string) $r['type'])) ?></td>
                    <td><a href="<?= e($viewUrl) ?>"><?= e($r['subject']) ?></a></td>
                    <td><?php render_corr_date_cell($r['corr_date'] ?? null); ?></td>
                    <td><?= e(user_display_name($r, 'creator_name', 'creator_name_en') ?: __('common.none')) ?></td>
                    <td><?= e(format_datetime((string) $r['released_at'])) ?></td>
                    <td>
                        <?php if (empty($r['seen_at'])): ?>
                            <span class="badge text-bg-warning"><?= e(__('cc.status_unseen')) ?></span>
                        <?php else: ?>
                            <span class="badge text-bg-success" title="<?= e(format_datetime((string) $r['seen_at'])) ?>"><?= e(__('cc.status_seen')) ?></span>
                        <?php endif; ?>
                    </td>
                    <td><a class="btn btn-sm btn-outline-primary" href="<?= e($viewUrl) ?>"><?= e(__('action.view')) ?></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php $pageParam = 'page'; require __DIR__ . '/../includes/list_pagination.php'; ?>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
