<?php
require_once __DIR__ . '/../config/auth.php';
$user = require_permission('admin.logs');
$pageTitle = __('nav.activity_log');
ensure_security_schema();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'clear_all') {
    verify_csrf();
    $deleted = (int) db()->exec('DELETE FROM `tblactivity_log`');
    log_security('CLEAR_ACTIVITY_LOG', 'WARNING', 'deleted=' . $deleted, (int) $user['id']);
    flash('success', __('activity.cleared') . ' (' . $deleted . ')');
    redirect(base_url('admin/activity_log.php'));
}

$q = trim($_GET['q'] ?? '');
$actionFilter = trim($_GET['action'] ?? '');
$params = [];
$sql = "SELECT a.*, u.name_ar, u.name_en, u.email, c.ref_number
        FROM `tblactivity_log` a
        LEFT JOIN `tblusers` u ON u.id = a.user_id
        LEFT JOIN `tblcorrespondence` c ON c.id = a.correspondence_id
        WHERE 1=1";
if ($q !== '') {
    $sql .= " AND (a.details LIKE ? OR a.action LIKE ? OR u.name_ar LIKE ? OR u.name_en LIKE ? OR u.email LIKE ? OR c.ref_number LIKE ?)";
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like, $like, $like);
}
if ($actionFilter !== '') {
    $sql .= " AND a.action = ?";
    $params[] = $actionFilter;
}
$sql .= " ORDER BY a.id DESC LIMIT 400";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$actions = db()->query("SELECT DISTINCT action FROM `tblactivity_log` ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);

require __DIR__ . '/../includes/header.php';
?>
<div class="page-header page-narrow">
    <div>
        <h1 class="page-title"><?= e(__('activity.title')) ?></h1>
        <p class="page-sub"><?= e(__('activity.subtitle')) ?></p>
    </div>
    <div class="page-header-actions">
        <form method="post" data-confirm="<?= e(__('activity.confirm_clear')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="clear_all">
            <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i> <?= e(__('activity.clear_all')) ?></button>
        </form>
    </div>
</div>

<div class="card-panel mb-3 page-narrow">
    <div class="card-body p-3">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label"><?= e(__('action.search')) ?></label>
                <input type="text" name="q" class="form-control" value="<?= e($q) ?>" placeholder="<?= e(__('activity.search_ph')) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label"><?= e(__('activity.action')) ?></label>
                <select name="action" class="form-select">
                    <option value=""><?= e(__('common.all')) ?></option>
                    <?php foreach ($actions as $a): ?>
                        <option value="<?= e($a) ?>" <?= $actionFilter === $a ? 'selected' : '' ?>><?= e(activity_action_label($a)) ?></option>
                    <?php endforeach; ?>
                </select>
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
                    <th><?= e(__('activity.user')) ?></th>
                    <th><?= e(__('activity.action')) ?></th>
                    <th><?= e(__('activity.details')) ?></th>
                    <th><?= e(__('corr.number')) ?></th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="7" class="text-center text-muted py-4"><?= e(__('common.no_data')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= (int)$r['id'] ?></td>
                    <td class="text-nowrap"><?= e(format_datetime($r['created_at'])) ?></td>
                    <td>
                        <?= e(user_display_name($r) ?: __('common.none')) ?>
                        <?php if (!empty($r['email'])): ?><div class="small text-muted" dir="ltr"><?= e($r['email']) ?></div><?php endif; ?>
                    </td>
                    <td><span class="badge text-bg-light border"><?= e(activity_action_label($r['action'])) ?></span></td>
                    <td><?= e(activity_details_for_display((string) ($r['action'] ?? ''), (string) ($r['details'] ?? ''))) ?></td>
                    <td>
                        <?php if (!empty($r['correspondence_id']) && !empty($r['ref_number'])): ?>
                            <a href="<?= e(base_url('incoming/view.php?id='.(int)$r['correspondence_id'])) ?>"><?= e($r['ref_number']) ?></a>
                        <?php else: ?>
                            <?= e(__('common.none')) ?>
                        <?php endif; ?>
                    </td>
                    <td dir="ltr" class="small"><?= e($r['ip_address'] ?? '—') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
