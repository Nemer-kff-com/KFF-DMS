<?php
require_once __DIR__ . '/../config/auth.php';
$user = require_permission('admin.users');
ensure_user_permissions_schema();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$target = $id ? admin_row('tblusers', $id) : null;
if (!$target) {
    flash('danger', __('users.not_found'));
    redirect(base_url('admin/users.php'));
}
$isAdminTarget = (string) ($target['role'] ?? '') === 'admin';
$pageTitle = __('perm.user_title') . ' — ' . user_display_name($target);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if ($isAdminTarget) {
        flash('warning', __('perm.admin_always'));
    } else {
        $posted = is_array($_POST['ov'] ?? null) ? $_POST['ov'] : [];
        $changed = dms_user_permissions_save($target, $posted, $user);
        flash('success', $changed > 0 ? str_replace(':n', (string) $changed, __('perm.user_saved')) : __('perm.no_changes'));
    }
    redirect(base_url('admin/user_permissions.php?id=' . $id));
}

$base = (string) ($target['role'] ?? '');
$roleId = user_role_id($target);
$roleMap = dms_role_permissions_map()[$roleId] ?? [];
$overrides = dms_user_permission_overrides($id);
$catalog = dms_permission_catalog();
$isEn = dms_lang_code() === 'en';

require __DIR__ . '/../includes/header.php';
?>
<div class="page-header page-narrow">
    <div>
        <h1 class="page-title"><?= e(__('perm.user_title')) ?></h1>
        <p class="page-sub">
            <?= e(user_display_name($target)) ?>
            <span class="badge text-bg-light border ms-1"><?= e(user_role_label($target)) ?></span>
        </p>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-outline-secondary" href="<?= e(base_url('admin/permissions.php')) ?>">
            <i class="bi bi-shield-check me-1"></i><?= e(__('perm.title')) ?>
        </a>
        <a class="btn btn-outline-secondary" href="<?= e(base_url('admin/users_edit.php?id=' . $id)) ?>">
            <i class="bi <?= dms_is_rtl() ? 'bi-arrow-right' : 'bi-arrow-left' ?> me-1"></i><?= e(__('action.back')) ?>
        </a>
    </div>
</div>

<div class="alert alert-info py-2 d-flex align-items-center gap-2 page-narrow">
    <i class="bi bi-info-circle"></i>
    <span><?= e($isAdminTarget ? __('perm.admin_always') : __('perm.user_notice')) ?></span>
</div>

<form method="post" id="userPermForm" class="card-panel page-narrow">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $id ?>">
    <div class="table-responsive perm-matrix-wrap">
        <table class="table perm-matrix perm-user-table mb-0 align-middle">
            <thead>
                <tr>
                    <th class="perm-col-name"><?= e(__('perm.col_permission')) ?></th>
                    <th class="text-center"><span title="<?= e(__('perm.col_from_role_tip')) ?>" data-bs-toggle="tooltip"><?= e(__('perm.col_from_role')) ?></span></th>
                    <th class="perm-col-ov"><?= e(__('perm.col_override')) ?></th>
                    <th class="text-center"><span title="<?= e(__('perm.col_result_tip')) ?>" data-bs-toggle="tooltip"><?= e(__('perm.col_result')) ?></span></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($catalog as $group): ?>
                <tr class="perm-group-row">
                    <th class="perm-col-name" colspan="4"><i class="bi <?= e($group['icon']) ?> me-1"></i><?= e(__($group['label'])) ?></th>
                </tr>
                <?php foreach ($group['items'] as $code => $item): ?>
                    <?php
                    $allowed = $isAdminTarget || dms_user_perm_applicable($target, $code);
                    $fromRole = $isAdminTarget || ($allowed && isset($roleMap[$code]));
                    $ov = $allowed && !$isAdminTarget ? ($overrides[$code] ?? 'role') : 'role';
                    $effective = $isAdminTarget || ($allowed && ($ov === 'grant' || ($ov === 'role' && $fromRole)));
                    $lockTip = $isAdminTarget ? __('perm.admin_always')
                        : (!$allowed ? (dms_permission_admin_only($code) ? __('perm.admin_only') : str_replace(':base', role_label($base), __('perm.base_not_allowed'))) : '');
                    ?>
                    <tr class="<?= $ov !== 'role' ? 'perm-ov-active' : '' ?>">
                        <td class="perm-col-name"><span class="perm-name"><?= e($isEn ? $item['en'] : $item['ar']) ?></span></td>
                        <td class="text-center">
                            <i class="bi <?= $fromRole ? 'bi-check-circle-fill text-success' : 'bi-dash-circle text-muted' ?>"
                               aria-label="<?= e($fromRole ? __('common.yes') : __('common.no')) ?>"></i>
                        </td>
                        <td class="perm-col-ov">
                            <?php if ($lockTip !== ''): ?>
                                <span class="d-block" tabindex="0" title="<?= e($lockTip) ?>" data-bs-toggle="tooltip">
                                    <select class="form-select form-select-sm" disabled aria-label="<?= e($lockTip) ?>">
                                        <option><?= e(__('perm.ov_role')) ?></option>
                                    </select>
                                </span>
                            <?php else: ?>
                                <select name="ov[<?= e($code) ?>]" class="form-select form-select-sm perm-ov-select"
                                        data-from-role="<?= $fromRole ? '1' : '0' ?>"
                                        aria-label="<?= e(($isEn ? $item['en'] : $item['ar']) . ' — ' . __('perm.col_override')) ?>">
                                    <option value="role"<?= $ov === 'role' ? ' selected' : '' ?>><?= e(__('perm.ov_role')) ?></option>
                                    <option value="grant"<?= $ov === 'grant' ? ' selected' : '' ?>><?= e(__('perm.ov_grant')) ?></option>
                                    <option value="deny"<?= $ov === 'deny' ? ' selected' : '' ?>><?= e(__('perm.ov_deny')) ?></option>
                                </select>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <i class="bi perm-eff <?= $effective ? 'bi-check-circle-fill text-success' : 'bi-x-circle text-danger' ?>"
                               aria-label="<?= e($effective ? __('common.yes') : __('common.no')) ?>"></i>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if (!$isAdminTarget): ?>
    <div class="admin-form-actions px-3 pb-3">
        <button class="btn btn-kff" type="submit">
            <i class="bi bi-check2 me-1"></i><?= e(__('action.save_changes')) ?>
        </button>
        <button class="btn btn-outline-secondary" type="button" id="userPermReset"
                title="<?= e(__('perm.ov_reset_tip')) ?>" data-bs-toggle="tooltip">
            <i class="bi bi-arrow-counterclockwise me-1"></i><?= e(__('perm.ov_reset')) ?>
        </button>
    </div>
    <?php endif; ?>
</form>
<script>
(function () {
  var form = document.getElementById('userPermForm');
  if (!form) return;
  function sync(sel) {
    var v = sel.value;
    var on = v === 'grant' || (v === 'role' && sel.dataset.fromRole === '1');
    var row = sel.closest('tr');
    var icon = row.querySelector('.perm-eff');
    row.classList.toggle('perm-ov-active', v !== 'role');
    icon.className = 'bi perm-eff ' + (on ? 'bi-check-circle-fill text-success' : 'bi-x-circle text-danger');
  }
  var selects = Array.prototype.slice.call(form.querySelectorAll('.perm-ov-select'));
  selects.forEach(function (s) { s.addEventListener('change', function () { sync(s); }); });
  var reset = document.getElementById('userPermReset');
  if (reset) {
    reset.addEventListener('click', function () {
      selects.forEach(function (s) { s.value = 'role'; sync(s); });
    });
  }
})();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
