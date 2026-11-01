<?php
/** @var array $user */
ensure_security_schema();

$sessionsSelf = base_url('admin/system_logs.php?tab=sessions');
$currentSid = session_id();

purge_stale_user_sessions($currentSid);

$sessions = db()->query(
    "SELECT s.*, u.email, u.role
     FROM `tbluser_sessions` s
     LEFT JOIN `tblusers` u ON u.id = s.user_id
     ORDER BY s.last_activity DESC"
)->fetchAll();
?>
<div class="page-header page-narrow">
    <div>
        <h1 class="page-title"><?= e(__('sessions.title')) ?></h1>
        <p class="page-sub mb-0"><?= e(__('sessions.subtitle')) ?></p>
    </div>
    <div class="page-header-actions">
        <form method="post" action="<?= e($sessionsSelf) ?>" data-confirm="<?= e(__('sessions.confirm_purge_stale')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="tab" value="sessions">
            <input type="hidden" name="action" value="purge_stale">
            <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="bi bi-clock-history"></i> <?= e(__('sessions.purge_stale')) ?></button>
        </form>
        <form method="post" action="<?= e($sessionsSelf) ?>" data-confirm="<?= e(__('sessions.confirm_clear_others')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="tab" value="sessions">
            <input type="hidden" name="action" value="clear_others">
            <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-x-circle"></i> <?= e(__('sessions.disconnect_others')) ?></button>
        </form>
    </div>
</div>

<div class="card-panel page-narrow">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th><?= e(__('activity.user')) ?></th>
                    <th>IP</th>
                    <th><?= e(__('sessions.browser')) ?></th>
                    <th><?= e(__('sessions.os')) ?></th>
                    <th><?= e(__('sessions.last_activity')) ?></th>
                    <th><?= e(__('sessions.session_id')) ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$sessions): ?>
                <tr><td colspan="7" class="text-center text-muted py-4"><?= e(__('sessions.none')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($sessions as $s): ?>
                <?php $isCurrent = ($s['session_id'] === $currentSid); ?>
                <tr class="<?= $isCurrent ? 'table-success' : '' ?>">
                    <td>
                        <div class="fw-semibold"><?= e($s['user_name'] ?? __('common.none')) ?></div>
                        <div class="small text-muted" dir="ltr"><?= e($s['email'] ?? '') ?></div>
                        <?php if ($isCurrent): ?>
                            <span class="badge text-bg-success"><?= e(__('sessions.current')) ?></span>
                        <?php endif; ?>
                    </td>
                    <td dir="ltr"><?= e($s['ip_address'] ?? '—') ?></td>
                    <td><?= e($s['browser'] ?? '—') ?></td>
                    <td><?= e($s['os_name'] ?? '—') ?></td>
                    <td class="text-nowrap"><?= e(format_datetime($s['last_activity'])) ?></td>
                    <td class="small" dir="ltr"><code><?= e(substr($s['session_id'], 0, 12)) ?>…</code></td>
                    <td>
                        <?php if (!$isCurrent): ?>
                        <form method="post" action="<?= e($sessionsSelf) ?>" class="d-inline" data-confirm="<?= e(__('sessions.confirm_disconnect')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="tab" value="sessions">
                            <input type="hidden" name="action" value="disconnect_one">
                            <input type="hidden" name="session_id" value="<?= e($s['session_id']) ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger"><?= e(__('sessions.disconnect')) ?></button>
                        </form>
                        <?php else: ?>
                            <span class="text-muted small">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
