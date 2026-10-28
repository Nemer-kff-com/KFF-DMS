<?php
/**
 * نموذج مجموعة جهات
 * يتطلب: $values, $isEdit, $canManageAllEntities, $departments, $user, $selectedPayloads
 */
$selectedPayloads = is_array($selectedPayloads ?? null) ? $selectedPayloads : [];
?>
<div class="admin-form-section">
    <div class="admin-form-section-title"><?= e(__('entities.section_basic')) ?></div>
    <div class="mb-0">
        <label class="form-label" for="group_name"><?= e(__('groups.name')) ?></label>
        <input type="text" name="name" id="group_name" class="form-control" required value="<?= e((string) ($values['name'] ?? '')) ?>" placeholder="<?= e(__('groups.name_ph')) ?>">
    </div>
</div>

<div class="admin-form-section">
    <div class="admin-form-section-title"><?= e(__('entities.scope')) ?></div>
    <?php if ($canManageAllEntities): ?>
        <label class="form-label" for="group_department"><?= e(__('entities.visibility')) ?></label>
        <select name="department_id" id="group_department" class="form-select">
            <option value=""><?= e(__('entities.scope_public')) ?></option>
            <?php foreach ($departments as $department): ?>
                <option
                    value="<?= (int) $department['id'] ?>"
                    <?= (string) ($values['department_id'] ?? '') === (string) $department['id'] ? 'selected' : '' ?>
                >
                    <?= e(__('entities.scope_department') . ': ' . department_display_name($department)) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="admin-form-hint mb-0 mt-2"><?= e(__('entities.scope_hint')) ?></p>
    <?php else: ?>
        <div class="alert alert-light border mb-0">
            <i class="bi bi-building me-1"></i>
            <?= e(__('entities.scope_department') . ': ' . ($user['department_name'] ?? __('common.none'))) ?>
        </div>
    <?php endif; ?>
</div>

<div class="admin-form-section<?= $isEdit ? '' : ' admin-form-section-last' ?>">
    <div class="admin-form-section-title"><?= e(__('groups.section_members')) ?></div>
    <?php
    $inputName = 'entity_ids[]';
    $pickerId = 'group-members';
    $hideGroupSelect = true;
    $omitGroupHidden = true;
    $showSelectedNames = false;
    $groups = [];
    $groupMembers = [];
    $preGroup = 0;
    $pickerTitle = __('groups.picker_title');
    $pickLabel = __('groups.add_members');
    $editLabel = __('groups.edit_members');
    $emptyText = __('groups.no_members');
    $hintText = '';
    require __DIR__ . '/../includes/entity_multi_picker.php';
    ?>
</div>

<?php if ($isEdit): ?>
<div class="admin-form-section admin-form-section-last">
    <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" <?= (int) ($values['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
        <label class="form-check-label" for="is_active"><?= e(__('common.active')) ?></label>
    </div>
</div>
<?php endif; ?>

<div class="admin-form-actions">
    <button class="btn btn-kff" type="submit">
        <i class="bi bi-check2 me-1"></i><?= e($isEdit ? __('action.save_changes') : __('action.save')) ?>
    </button>
    <a class="btn btn-outline-secondary" href="<?= e(base_url('admin/entity_groups.php')) ?>"><?= e(__('action.cancel')) ?></a>
</div>
