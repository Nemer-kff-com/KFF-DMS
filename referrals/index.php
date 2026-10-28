<?php
require_once __DIR__ . '/../config/auth.php';
$user = require_login();
$pageTitle = __('nav.referrals');

$statusFilter = $_GET['status'] ?? '';
$list = referrals_list_page($statusFilter, $user, dms_request_page());
$rows = $list['rows'];
$pagination = $list['pagination'];
$listTotal = $list['total'];
$referUi = dms_perm_ui('referrals.manage', $user);
$pageScripts = dms_list_page_scripts();

require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title"><?= e(__('ref.title')) ?></h1>
        <p class="page-sub"><?= e(__('ref.subtitle')) ?></p>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-outline-secondary" href="<?= e(base_url('referrals/list_print.php' . ($statusFilter !== '' ? '?status=' . rawurlencode((string) $statusFilter) : ''))) ?>" target="_blank"><i class="bi bi-printer"></i> <?= e(__('action.print')) ?></a>
        <a class="btn btn-outline-secondary" href="<?= e(base_url('referrals/export.php' . ($statusFilter !== '' ? '?status=' . rawurlencode((string) $statusFilter) : ''))) ?>"><i class="bi bi-file-earmark-excel"></i> <?= e(__('action.export_excel')) ?></a>
    </div>
</div>

<div class="card-panel mb-3">
    <div class="card-body p-3">
        <form method="get" class="list-filters">
            <div class="list-filters-main">
                <div class="list-filters-item">
                    <label class="form-label"><?= e(__('corr.status')) ?></label>
                    <select name="status" class="form-select">
                        <option value=""><?= e(__('common.all')) ?></option>
                        <?php foreach (['pending','received','completed','returned'] as $s): ?>
                            <option value="<?= $s ?>" <?= $statusFilter===$s?'selected':'' ?>><?= e(status_label($s)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
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
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th><?= e(__('ref.correspondence')) ?></th>
                    <th><?= e(__('ref.from')) ?></th>
                    <th><?= e(__('ref.to')) ?></th>
                    <th><?= e(__('ref.by')) ?></th>
                    <th><?= e(__('ref.date')) ?></th>
                    <th><?= e(__('corr.status')) ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="7" class="text-center text-muted py-4"><?= e(__('ref.no_items')) ?></td></tr><?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <?php $folder = function_exists('correspondence_type_folder') ? correspondence_type_folder((string) ($r['type'] ?? '')) : ($r['type'] === 'incoming' ? 'incoming' : 'outgoing'); ?>
                <tr>
                    <td>
                        <a href="<?= e(base_url($folder.'/view.php?id='.(int)$r['correspondence_id'])) ?>"><?= e($r['ref_number']) ?></a>
                        <div class="small text-muted"><?= e($r['subject']) ?></div>
                    </td>
                    <td><?php render_referral_party($r, 'from'); ?></td>
                    <td><?php render_referral_party($r, 'to'); ?></td>
                    <td><?= e(user_display_name($r, 'from_user_name', 'from_user_name_en')) ?></td>
                    <td><?= e(format_datetime($r['created_at'])) ?></td>
                    <td><?= status_badge($r['status']) ?></td>
                    <td class="text-nowrap">
                        <?php if ($referUi === 'allow' && $r['status'] === 'pending'): ?>
                        <form method="post" action="<?= e(base_url('referrals/update.php')) ?>" class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                            <input type="hidden" name="status" value="received">
                            <input type="hidden" name="return" value="referrals/index.php">
                            <button class="btn btn-sm btn-outline-success" type="submit"><?= e(__('corr.receive')) ?></button>
                        </form>
                        <form method="post" action="<?= e(base_url('referrals/update.php')) ?>" class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                            <input type="hidden" name="status" value="completed">
                            <input type="hidden" name="return" value="referrals/index.php">
                            <button class="btn btn-sm btn-outline-primary" type="submit"><?= e(__('action.complete')) ?></button>
                        </form>
                        <?php elseif ($referUi === 'deny' && $r['status'] === 'pending'): ?>
                        <?= dms_perm_denied_btn(__('corr.receive'), '', 'btn-sm btn-outline-success') ?>
                        <?= dms_perm_denied_btn(__('action.complete'), '', 'btn-sm btn-outline-primary') ?>
                        <?php endif; ?>
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
