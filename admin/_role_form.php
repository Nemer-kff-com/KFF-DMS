<?php
/**
 * نموذج الدور — يتطلب: $values, $isEdit, $isSystemRole
 */
?>
<div class="admin-form-section">
    <div class="admin-form-section-title"><?= e(__('roles.section_basic')) ?></div>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label" for="role_name_ar"><?= e(__('roles.name_ar')) ?> *</label>
            <input type="text" name="name_ar" id="role_name_ar" class="form-control" maxlength="100" required
                   value="<?= e((string) ($values['name_ar'] ?? '')) ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label" for="role_name_en"><?= e(__('roles.name_en')) ?></label>
            <input type="text" name="name_en" id="role_name_en" class="form-control" maxlength="100" dir="ltr"
                   value="<?= e((string) ($values['name_en'] ?? '')) ?>">
        </div>
        <div class="col-12">
            <label class="form-label" for="role_description"><?= e(__('roles.description')) ?></label>
            <textarea name="description" id="role_description" class="form-control" rows="2" maxlength="500"
                      placeholder="<?= e(__('roles.description_ph')) ?>"><?= e((string) ($values['description'] ?? '')) ?></textarea>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="role_sort"><?= e(__('roles.sort_order')) ?></label>
            <input type="number" name="sort_order" id="role_sort" class="form-control" min="0" max="9999"
                   value="<?= e((string) ($values['sort_order'] ?? '')) ?>">
        </div>
        <?php if (!$isSystemRole): ?>
        <div class="col-md-4 d-flex align-items-end">
            <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" name="is_active" id="role_active" value="1" <?= (int) ($values['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
                <label class="form-check-label" for="role_active" title="<?= e(__('roles.active_tip')) ?>" data-bs-toggle="tooltip" data-bs-placement="top"><?= e(__('common.active')) ?></label>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($rolePermEditor)): ?>
<div class="admin-form-section admin-form-section-last role-perm-section">
    <div class="admin-form-section-title"><?= e(__('roles.perms_title')) ?></div>
    <p class="admin-form-hint"><?= e(__('roles.perms_hint')) ?></p>
    <div class="role-perm-grid">
    <?php foreach ($rolePermCatalog as $groupKey => $group): ?>
        <section class="role-perm-card">
            <header class="role-perm-card-head">
                <span class="role-perm-card-title"><i class="bi <?= e($group['icon']) ?>"></i><?= e(__($group['label'])) ?></span>
                <label class="role-perm-all" title="<?= e(__('perm.group_toggle')) ?>">
                    <input type="checkbox" class="form-check-input role-perm-group" data-group="<?= e($groupKey) ?>" aria-label="<?= e(__('perm.group_toggle')) ?>">
                </label>
            </header>
            <ul class="role-perm-list">
            <?php foreach ($group['items'] as $code => $item): ?>
                <?php
                $allowed = !empty($isSystemRole)
                    ? dms_permission_allowed_for_base($code, (string) ($rolePermBase ?? ''))
                    : !dms_permission_admin_only($code);
                $checked = $allowed && isset($rolePermHave[$code]);
                $label = (function_exists('dms_lang_code') && dms_lang_code() === 'en') ? $item['en'] : $item['ar'];
                $tip = $allowed ? '' : (dms_permission_admin_only($code)
                    ? __('perm.admin_only')
                    : str_replace(':base', role_label((string) ($rolePermBase ?? '')), __('perm.base_not_allowed')));
                ?>
                <li class="role-perm-item<?= $allowed ? '' : ' is-locked' ?>">
                    <label class="role-perm-choice" for="perm_<?= e(str_replace('.', '-', $code)) ?>"<?= $tip !== '' ? ' title="' . e($tip) . '" data-bs-toggle="tooltip"' : '' ?>>
                        <input class="form-check-input" type="checkbox" name="perm[]" value="<?= e($code) ?>"
                               id="perm_<?= e(str_replace('.', '-', $code)) ?>" data-group="<?= e($groupKey) ?>"
                               <?= $checked ? 'checked' : '' ?> <?= $allowed ? '' : 'disabled' ?>>
                        <span><?= e($label) ?></span>
                    </label>
                    <?php if (!empty($item['enforced'])): ?>
                        <span class="badge perm-enforced-badge"><?= e(__('perm.enforced')) ?></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
            </ul>
        </section>
    <?php endforeach; ?>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var root = document.querySelector('.role-perm-section');
  if (!root) return;
  function items(group) {
    return Array.prototype.filter.call(root.querySelectorAll('.role-perm-item input[data-group="' + group + '"]'), function (box) {
      return !box.disabled;
    });
  }
  function sync(group) {
    var head = root.querySelector('.role-perm-group[data-group="' + group + '"]');
    var list = items(group);
    if (!head) return;
    if (!list.length) {
      head.disabled = true;
      head.checked = false;
      head.indeterminate = false;
      return;
    }
    var n = list.filter(function (box) { return box.checked; }).length;
    head.checked = n > 0 && n === list.length;
    head.indeterminate = n > 0 && n < list.length;
  }
  root.querySelectorAll('.role-perm-group').forEach(function (head) {
    sync(head.getAttribute('data-group'));
    head.addEventListener('change', function () {
      items(head.getAttribute('data-group')).forEach(function (box) { box.checked = head.checked; });
      sync(head.getAttribute('data-group'));
    });
  });
  root.addEventListener('change', function (ev) {
    var box = ev.target;
    if (!box || !box.getAttribute || box.classList.contains('role-perm-group')) return;
    var group = box.getAttribute('data-group');
    if (group) sync(group);
  });
});
</script>
<?php elseif (!empty($isSystemRole) && function_exists('dms_role_is_admin') && !empty($role) && dms_role_is_admin($role)): ?>
<div class="admin-form-section">
    <div class="admin-form-section-title"><?= e(__('roles.perms_title')) ?></div>
    <p class="admin-form-hint mb-0"><?= e(__('roles.perms_admin')) ?></p>
</div>
<?php endif; ?>

<div class="admin-form-actions">
    <button class="btn btn-kff" type="submit">
        <i class="bi bi-check2 me-1"></i><?= e($isEdit ? __('action.save_changes') : __('action.add')) ?>
    </button>
    <a href="<?= e(base_url('admin/roles.php')) ?>" class="btn btn-outline-secondary"><?= e(__('action.cancel')) ?></a>
</div>
