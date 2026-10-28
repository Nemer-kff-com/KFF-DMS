<?php
/**
 * حقل التكريم + بند جديد + إضافة للقائمة
 * يتطلب: $efId, $honorificPresets, $honorificChoice, $honorificIsCustom, $honorificValue
 * اختياري: $entityLetterControlClass, $entityLetterSelectClass
 */
$entityLetterControlClass = $entityLetterControlClass ?? 'form-control';
$entityLetterSelectClass = $entityLetterSelectClass ?? 'form-select';
?>
<label class="form-label" for="<?= e($efId('entity_honorific')) ?>"><?= e(__('entities.honorific')) ?></label>
<select name="honorific_choice" id="<?= e($efId('entity_honorific')) ?>" class="<?= e($entityLetterSelectClass) ?> entity-letter-field">
    <?php foreach ($honorificPresets as $value => $label): ?>
        <option value="<?= e($value) ?>" <?= $honorificChoice === $value ? 'selected' : '' ?>><?= e($label) ?></option>
    <?php endforeach; ?>
    <option value="__custom__" <?= $honorificIsCustom ? 'selected' : '' ?>><?= e(__('entities.honorific_custom')) ?></option>
</select>
<div class="mt-1<?= $honorificIsCustom ? '' : ' d-none' ?>" id="<?= e($efId('entityHonorificCustomWrap')) ?>">
    <input type="text" name="honorific_custom" id="<?= e($efId('entity_honorific_custom')) ?>" class="<?= e($entityLetterControlClass) ?> entity-letter-field"
           value="<?= e($honorificIsCustom ? $honorificValue : '') ?>"
           placeholder="<?= e(__('entities.honorific_custom_ph')) ?>">
</div>
<div class="entity-honorific-add mt-2">
    <button type="button" class="btn btn-sm btn-link entity-honorific-add-toggle px-0" id="<?= e($efId('entityHonorificAddToggle')) ?>">
        <i class="bi bi-plus-circle me-1"></i><?= e(__('entities.honorific_add_toggle')) ?>
    </button>
    <div class="entity-honorific-add-row d-none" id="<?= e($efId('entityHonorificAddRow')) ?>">
        <div class="input-group input-group-sm">
            <input type="text" class="form-control" id="<?= e($efId('entityHonorificAddInput')) ?>"
                   placeholder="<?= e(__('entities.honorific_add_ph')) ?>" maxlength="100">
            <button type="button" class="btn btn-outline-secondary" id="<?= e($efId('entityHonorificAddBtn')) ?>"><?= e(__('entities.honorific_add_btn')) ?></button>
        </div>
        <div class="small text-danger d-none mt-1" id="<?= e($efId('entityHonorificAddError')) ?>"></div>
    </div>
</div>
