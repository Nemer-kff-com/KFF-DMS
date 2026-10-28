<?php
require_once __DIR__ . '/../config/auth.php';
$user = require_permission('admin.users');
ensure_permissions_schema();
$pageTitle = __('perm.title');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $posted = is_array($_POST['perm'] ?? null) ? $_POST['perm'] : [];
    $changed = dms_role_permissions_save($posted, $user);
    flash('success', $changed > 0 ? str_replace(':n', (string) $changed, __('perm.saved')) : __('perm.no_changes'));
    redirect(base_url('admin/permissions.php'));
}

$roles = dms_roles_list();
$map = dms_role_permissions_map();
$catalog = dms_permission_catalog();
$isEn = dms_lang_code() === 'en';

require __DIR__ . '/../includes/header.php';
?>
<div class="page-header page-narrow">
    <div>
        <h1 class="page-title"><?= e(__('perm.title')) ?></h1>
        <p class="page-sub"><?= e(__('perm.subtitle')) ?></p>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-outline-secondary" href="<?= e(base_url('admin/roles.php')) ?>">
            <i class="bi bi-person-badge me-1"></i><?= e(__('roles.title')) ?>
        </a>
        <button type="submit" form="permMatrixForm" class="btn btn-kff">
            <i class="bi bi-check2 me-1"></i><?= e(__('action.save_changes')) ?>
        </button>
    </div>
</div>

<div class="alert alert-warning py-2 d-flex align-items-center gap-2 page-narrow">
    <i class="bi bi-info-circle"></i>
    <span><?= e(__('perm.phase_notice')) ?></span>
</div>

<form method="post" id="permMatrixForm" class="card-panel page-narrow">
    <?= csrf_field() ?>
    <div class="table-responsive perm-matrix-wrap">
        <table class="table perm-matrix mb-0 align-middle">
            <thead>
                <tr>
                    <th class="perm-col-name"><?= e(__('perm.col_permission')) ?></th>
                    <?php foreach ($roles as $r): ?>
                        <?php $isAdminRole = dms_role_is_admin($r); ?>
                        <th class="perm-col-role<?= (int) $r['is_active'] === 1 ? '' : ' is-inactive' ?>"
                            title="<?= e(trim((string) ($r['description'] ?? '')) ?: dms_role_display_name($r)) ?>" data-bs-toggle="tooltip" data-bs-placement="top">
                            <?php if ($isAdminRole): ?>
                                <i class="bi bi-lock-fill perm-role-lock" aria-hidden="true"></i>
                            <?php endif; ?>
                            <span class="perm-role-name"><?= e(dms_role_display_name($r)) ?></span>
                            <?php if (!$isAdminRole && (int) $r['is_active'] !== 1): ?>
                                <span class="perm-role-sub"><?= e(__('common.inactive')) ?></span>
                            <?php endif; ?>
                        </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($catalog as $groupKey => $group): ?>
                <tr class="perm-group-row">
                    <th class="perm-col-name"><i class="bi <?= e($group['icon']) ?> me-1"></i><?= e(__($group['label'])) ?></th>
                    <?php foreach ($roles as $r): ?>
                        <td class="text-center">
                            <?php if (!dms_role_is_admin($r)): ?>
                                <input type="checkbox" class="form-check-input perm-group-toggle"
                                       data-role="<?= (int) $r['id'] ?>" data-group="<?= e($groupKey) ?>"
                                       title="<?= e(__('perm.group_toggle')) ?>" aria-label="<?= e(__('perm.group_toggle') . ' — ' . __($group['label']) . ' — ' . dms_role_display_name($r)) ?>">
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
                <?php foreach ($group['items'] as $code => $item): ?>
                    <tr>
                        <td class="perm-col-name">
                            <span class="perm-name"><?= e($isEn ? $item['en'] : $item['ar']) ?></span>
                            <?php if ($item['enforced']): ?>
                                <span class="badge perm-enforced-badge" title="<?= e(__('perm.enforced_tip')) ?>" data-bs-toggle="tooltip"><?= e(__('perm.enforced')) ?></span>
                            <?php endif; ?>
                        </td>
                        <?php foreach ($roles as $r): ?>
                            <?php $rid = (int) $r['id']; ?>
                            <td class="text-center">
                                <?php if (dms_role_is_admin($r)): ?>
                                    <input type="checkbox" class="form-check-input" checked disabled
                                           title="<?= e(__('perm.admin_always')) ?>" aria-label="<?= e(__('perm.admin_always')) ?>">
                                <?php elseif (!dms_role_can_grant_perm($r, $code)): ?>
                                    <?php $baseTip = dms_permission_admin_only($code)
                                        ? __('perm.admin_only')
                                        : str_replace(':base', role_label((string) $r['base_role']), __('perm.base_not_allowed')); ?>
                                    <span class="d-inline-block" tabindex="0" title="<?= e($baseTip) ?>" data-bs-toggle="tooltip">
                                        <input type="checkbox" class="form-check-input" disabled aria-label="<?= e($baseTip) ?>">
                                    </span>
                                <?php else: ?>
                                    <input type="checkbox" class="form-check-input perm-check" name="perm[<?= $rid ?>][]" value="<?= e($code) ?>"
                                           data-role="<?= $rid ?>" data-group="<?= e($groupKey) ?>"
                                           aria-label="<?= e(($isEn ? $item['en'] : $item['ar']) . ' — ' . dms_role_display_name($r)) ?>"
                                           <?= isset($map[$rid][$code]) ? 'checked' : '' ?>>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="admin-form-actions px-3 pb-3">
        <button class="btn btn-kff" type="submit">
            <i class="bi bi-check2 me-1"></i><?= e(__('action.save_changes')) ?>
        </button>
    </div>
</form>
<script>
(function () {
  var form = document.getElementById('permMatrixForm');
  if (!form) return;
  function checksFor(role, group) {
    return Array.prototype.slice.call(form.querySelectorAll('.perm-check[data-role="' + role + '"][data-group="' + group + '"]'));
  }
  function syncToggle(toggle) {
    var list = checksFor(toggle.dataset.role, toggle.dataset.group);
    var n = list.filter(function (c) { return c.checked; }).length;
    toggle.checked = n > 0 && n === list.length;
    toggle.indeterminate = n > 0 && n < list.length;
  }
  var toggles = Array.prototype.slice.call(form.querySelectorAll('.perm-group-toggle'));
  toggles.forEach(function (t) {
    t.addEventListener('change', function () {
      checksFor(t.dataset.role, t.dataset.group).forEach(function (c) { c.checked = t.checked; });
      syncToggle(t);
    });
    syncToggle(t);
  });
  form.addEventListener('change', function (ev) {
    var c = ev.target;
    if (!c.classList || !c.classList.contains('perm-check')) return;
    var t = form.querySelector('.perm-group-toggle[data-role="' + c.dataset.role + '"][data-group="' + c.dataset.group + '"]');
    if (t) syncToggle(t);
  });
})();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
