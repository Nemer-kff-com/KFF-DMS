<?php
require_once __DIR__ . '/../config/auth.php';
$user = require_permission('admin.logs');
$pageTitle = __('nav.security_log');
ensure_security_schema();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'clear_all') {
    verify_csrf();
    $deleted = (int) db()->exec('DELETE FROM `tblsecurity_log`');
    log_security('CLEAR_SECURITY_LOG', 'CRITICAL', 'deleted=' . $deleted, (int) $user['id']);
    flash('success', __('security.cleared') . ' (' . $deleted . ')');
    redirect(base_url('admin/security_log.php'));
}

$filter = strtoupper(trim($_GET['filter'] ?? 'ALL'));
if (!in_array($filter, ['ALL', 'INFO', 'WARNING', 'CRITICAL'], true)) {
    $filter = 'ALL';
}
$q = trim($_GET['q'] ?? '');

$params = [];
$sql = "SELECT s.*, u.name_ar, u.name_en, u.email
        FROM `tblsecurity_log` s
        LEFT JOIN `tblusers` u ON u.id = s.user_id
        WHERE 1=1";
if ($filter !== 'ALL') {
    $sql .= " AND s.severity = ?";
    $params[] = $filter;
}
if ($q !== '') {
    $sql .= " AND (s.event LIKE ? OR s.details LIKE ? OR s.ip_address LIKE ? OR u.name_ar LIKE ? OR u.name_en LIKE ? OR u.email LIKE ?)";
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like, $like, $like);
}
$sql .= " ORDER BY s.id DESC LIMIT 400";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

require __DIR__ . '/../includes/header.php';
?>
<div class="page-header page-narrow">
    <div>
        <h1 class="page-title"><?= e(__('security.title')) ?></h1>
        <p class="page-sub"><?= e(__('security.subtitle')) ?></p>
    </div>
    <div class="page-header-actions">
        <form method="post" data-confirm="<?= e(__('security.confirm_clear')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="clear_all">
            <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i> <?= e(__('security.clear_all')) ?></button>
        </form>
    </div>
</div>

<div class="card-panel mb-3 page-narrow">
    <div class="card-body p-3">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label"><?= e(__('action.search')) ?></label>
                <input type="text" name="q" class="form-control" value="<?= e($q) ?>">
            </div>
            <div class="col-md-5">
                <label class="form-label"><?= e(__('security.severity')) ?></label>
                <div class="btn-group w-100">
                    <?php foreach (['ALL','INFO','WARNING','CRITICAL'] as $sev): ?>
                        <a class="btn btn-sm <?= $filter === $sev ? 'btn-kff' : 'btn-outline-secondary' ?>"
                           href="?filter=<?= $sev ?>&q=<?= urlencode($q) ?>"><?= e(security_severity_label($sev)) ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="col-md-2">
                <button class="btn btn-outline-secondary w-100" type="submit"><?= e(__('action.filter')) ?></button>
            </div>
        </form>
    </div>
</div>

<div class="card-panel page-narrow">
    <div class="table-responsive" style="max-height:70vh">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light sticky-top">
                <tr>
                    <th>#</th>
                    <th><?= e(__('common.date')) ?></th>
                    <th><?= e(__('security.severity')) ?></th>
                    <th><?= e(__('security.event')) ?></th>
                    <th><?= e(__('activity.user')) ?></th>
                    <th><?= e(__('activity.details')) ?></th>
                    <th>IP</th>
                    <th><?= e(__('sessions.browser')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="8" class="text-center text-muted py-4"><?= e(__('common.no_data')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <?php
                $sev = strtoupper((string) $r['severity']);
                if ($sev === 'CRITICAL') {
                    $sevClass = 'text-bg-danger';
                } elseif ($sev === 'WARNING') {
                    $sevClass = 'text-bg-warning';
                } else {
                    $sevClass = 'text-bg-secondary';
                }
                $parsed = parse_browser_os((string) ($r['user_agent'] ?? ''));
                ?>
                <tr>
                    <td><?= (int)$r['id'] ?></td>
                    <td class="text-nowrap"><?= e(format_datetime($r['created_at'])) ?></td>
                    <td><span class="badge <?= $sevClass ?>"><?= e(security_severity_label($r['severity'])) ?></span></td>
                    <td><?= e(security_event_label($r['event'])) ?></td>
                    <td>
                        <?= e(user_display_name($r) ?: __('common.none')) ?>
                        <?php if (!empty($r['email'])): ?><div class="small text-muted" dir="ltr"><?= e($r['email']) ?></div><?php endif; ?>
                    </td>
                    <td class="small"><?= e($r['details'] ?? '') ?></td>
                    <td dir="ltr" class="small"><?= e($r['ip_address'] ?? '—') ?></td>
                    <td class="small"><?= e($parsed['browser'] . ' / ' . $parsed['os']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
