<?php
/**
 * حقول مجموعة الجهات.
 * يتطلب: $values, $isEdit, $canManageAllEntities, $departments, $user, $selectedPayloads
 */
$selectedPayloads = is_array($selectedPayloads ?? null) ? $selectedPayloads : [];
?>
<div class="mb-3">
    <label class="form-label" for="group_name"><?= e(__('groups.name')) ?></label>
    <input type="text" name="name" id="group_name" class="form-control" required value="<?= e((string) ($values['name'] ?? '')) ?>" placeholder="<?= e(__('groups.name_ph')) ?>">
</div>
<div class="mb-3">
    <label class="form-label" for="group_department"><?= e(__('groups.department')) ?></label>
    <?php if ($canManageAllEntities): ?>
        <select name="department_id" id="group_department" class="form-select">
            <option value=""><?= e(__('entities.scope_public')) ?></option>
            <?php foreach ($departments as $department): ?>
                <option
                    value="<?= (int) $department['id'] ?>"
                    <?= (string) ($values['department_id'] ?? '') === (string) $department['id'] ? 'selected' : '' ?>
                >
                    <?= e(department_display_name($department)) ?>
                </option>
            <?php endforeach; ?>
        </select>
    <?php else: ?>
        <div class="form-control bg-light" id="group_department"><?= e($user['department_name'] ?? __('common.none')) ?></div>
    <?php endif; ?>
</div>
<div class="mb-3">
    <label class="form-label"><?= e(__('groups.members_count')) ?></label>
    <?php
    $inputName = 'entity_ids[]';
    $pickerId = 'group-members';
    $hideGroupSelect = true;
    $omitGroupHidden = true;
    $showSelectedNames = false;
    $compactSummary = true;
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
<div class="form-check form-switch mb-0">
    <input class="form-check-input" type="checkbox" name="is_active" id="is_active" <?= (int) ($values['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
    <label class="form-check-label" for="is_active"><?= e(__('common.active')) ?></label>
</div>
<?php endif; ?>
