<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/correspondence.php';
$user = require_login();
require_outgoing_batch_view($user);
$pageTitle = __('bulk.batches_title');
ensure_entity_groups_schema();

$rows = outgoing_batches_list($user, 50);

require __DIR__ . '/../includes/header.php';
?>
<div class="page-header page-narrow">
    <div class="page-header-main">
        <h1 class="page-title"><?= e(__('bulk.batches_title')) ?></h1>
        <p class="page-sub"><?= e(__('bulk.batches_sub')) ?></p>
    </div>
    <div class="page-header-actions">
        <?php $bulkUi = dms_perm_ui('outgoing.bulk', $user); ?>
        <?php if ($bulkUi === 'allow'): ?>
            <?= btn_icon(__('corr.outgoing_bulk'), [
                'href' => base_url('outgoing/bulk.php'),
                'icon' => 'bi-people',
                'class' => 'btn-kff',
            ]) ?>
        <?php elseif ($bulkUi === 'deny'): ?>
            <?= dms_perm_denied_icon_btn(__('corr.outgoing_bulk'), 'bi-people') ?>
        <?php endif; ?>
        <?= dms_back_icon(base_url('outgoing/index.php')) ?>
    </div>
</div>

<div class="card-panel page-narrow">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th><?= e(__('corr.subject')) ?></th>
                    <th><?= e(__('corr.date')) ?></th>
                    <th><?= e(__('bulk.recipients')) ?></th>
                    <th><?= e(__('bulk.group_col')) ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="5" class="text-center text-muted py-4"><?= e(__('bulk.batches_empty')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td>
                        <div class="fw-semibold"><?= e((string) $r['subject']) ?></div>
                        <?php
                        $creator = user_display_name($r, 'creator_name', 'creator_name_en');
                        if ($creator !== ''):
                        ?>
                            <div class="small text-muted"><?= e($creator) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?php render_corr_date_cell(!empty($r['corr_date']) ? (string) $r['corr_date'] : null); ?></td>
                    <td><span class="badge text-bg-light text-dark"><?= (int) ($r['item_count'] ?? 0) ?></span></td>
                    <td><?= e(trim((string) ($r['group_name'] ?? '')) !== '' ? (string) $r['group_name'] : __('common.none')) ?></td>
                    <td class="text-end">
                        <?= btn_icon(__('corr.batch_view'), [
                            'href' => base_url('outgoing/batch.php?id=' . (int) $r['id']),
                            'icon' => 'bi-eye',
                            'class' => 'btn-outline-primary btn-sm',
                        ]) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
