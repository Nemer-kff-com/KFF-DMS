<?php
require_once __DIR__ . '/../config/auth.php';
$user = require_permission('admin.logs');
$pageTitle = __('nav.active_sessions');
ensure_security_schema();

$currentSid = session_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'disconnect_one') {
        $sid = (string) ($_POST['session_id'] ?? '');
        if ($sid === $currentSid) {
            flash('warning', __('sessions.cannot_disconnect_self'));
        } elseif (disconnect_user_session($sid)) {
            log_security('SESSION_DISCONNECT', 'WARNING', 'session=' . substr($sid, 0, 16) . '…', (int) $user['id']);
            flash('success', __('sessions.disconnected'));
        } else {
            flash('danger', __('sessions.disconnect_fail'));
        }
    } elseif ($action === 'clear_others') {
        $stmt = db()->query('SELECT session_id FROM `tbluser_sessions`');
        $count = 0;
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $sid) {
            if ($sid === $currentSid) {
                continue;
            }
            if (disconnect_user_session($sid)) {
                $count++;
            }
        }
        log_security('SESSION_DISCONNECT_ALL', 'WARNING', 'count=' . $count, (int) $user['id']);
        flash('success', __('sessions.disconnected_others') . ' (' . $count . ')');
    } elseif ($action === 'purge_stale') {
        $count = purge_stale_user_sessions($currentSid);
        log_security('SESSION_PURGE_STALE', 'INFO', 'count=' . $count, (int) $user['id']);
        flash('success', __('sessions.purged_stale') . ' (' . $count . ')');
    }
    redirect(base_url('admin/active_sessions.php'));
}

purge_stale_user_sessions($currentSid);

$sessions = db()->query(
    "SELECT s.*, u.email, u.role
     FROM `tbluser_sessions` s
     LEFT JOIN `tblusers` u ON u.id = s.user_id
     ORDER BY s.last_activity DESC"
)->fetchAll();

require __DIR__ . '/../includes/header.php';
?>
<div class="page-header page-narrow">
    <div>
        <h1 class="page-title"><?= e(__('sessions.title')) ?></h1>
        <p class="page-sub"><?= e(__('sessions.subtitle')) ?></p>
    </div>
    <div class="page-header-actions">
        <form method="post" data-confirm="<?= e(__('sessions.confirm_purge_stale')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="purge_stale">
            <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="bi bi-clock-history"></i> <?= e(__('sessions.purge_stale')) ?></button>
        </form>
        <form method="post" data-confirm="<?= e(__('sessions.confirm_clear_others')) ?>">
            <?= csrf_field() ?>
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
                        <form method="post" class="d-inline" data-confirm="<?= e(__('sessions.confirm_disconnect')) ?>">
                            <?= csrf_field() ?>
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
<?php require __DIR__ . '/../includes/footer.php'; ?>
