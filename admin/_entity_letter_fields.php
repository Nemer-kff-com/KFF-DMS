<?php
/**
 * حقول الخطاب: اللقب + الاسم + التكريم
 * يتطلب: $values, $efId (callable)
 * اختياري: $entityLetterCompact, $entityLetterHideTitle, $entityLetterIncludeJob
 */
$titlePrefixValue = trim((string) ($values['title_prefix'] ?? ''));
$entityLetterCompact = !empty($entityLetterCompact);
$entityLetterHideTitle = !empty($entityLetterHideTitle);
$entityLetterIncludeJob = isset($entityLetterIncludeJob) ? (bool) $entityLetterIncludeJob : true;
$entityLetterWide = !empty($entityLetterWide);
$entityLetterControlClass = $entityLetterWide ? 'form-control' : 'form-control form-control-sm';
$entityLetterSelectClass = $entityLetterWide ? 'form-select' : 'form-select form-select-sm';

$honorificValue = trim((string) ($values['honorific'] ?? ''));
$honorificPresets = entity_honorific_presets();
$honorificIsCustom = $honorificValue !== '' && !array_key_exists($honorificValue, $honorificPresets);
$honorificChoice = $honorificIsCustom ? '__custom__' : $honorificValue;
?>
<div class="admin-form-section entity-letter-section<?= !empty($entityLetterSectionLast) ? ' admin-form-section-last mb-0' : '' ?><?= $entityLetterCompact ? ' entity-letter-section-compact' : '' ?><?= $entityLetterHideTitle ? ' entity-letter-section-plain' : '' ?>">
    <?php if (!$entityLetterHideTitle): ?>
    <div class="admin-form-section-title"><?= e(__('entities.section_letter')) ?></div>
    <?php endif; ?>
    <p class="admin-form-hint"><?= e(__('entities.section_letter_hint')) ?></p>

    <?php if ($entityLetterCompact): ?>
    <div class="entity-letter-field-stack<?= $entityLetterWide ? ' entity-letter-field-stack-wide' : '' ?>">
        <div>
            <label class="form-label" for="<?= e($efId('entity_title_prefix')) ?>"><?= e(__('entities.title_prefix')) ?></label>
            <input type="text" name="title_prefix" id="<?= e($efId('entity_title_prefix')) ?>" class="<?= e($entityLetterControlClass) ?> entity-letter-field"
                   value="<?= e($titlePrefixValue) ?>" placeholder="<?= e(__('entities.title_prefix_ph')) ?>">
        </div>
        <div>
            <label class="form-label" for="<?= e($efId('entity_directed_to')) ?>"><?= e(__('entities.directed_to')) ?></label>
            <input type="text" name="directed_to" id="<?= e($efId('entity_directed_to')) ?>" class="<?= e($entityLetterControlClass) ?> entity-letter-field"
                   value="<?= e((string) ($values['directed_to'] ?? '')) ?>" placeholder="<?= e(__('entities.directed_to_ph')) ?>">
        </div>
        <div>
            <?php require __DIR__ . '/_entity_honorific_field.php'; ?>
        </div>
        <?php if ($entityLetterIncludeJob): ?>
        <div>
            <label class="form-label" for="<?= e($efId('entity_job_title')) ?>"><?= e(__('entities.job_title')) ?></label>
            <input type="text" name="job_title" id="<?= e($efId('entity_job_title')) ?>" class="<?= e($entityLetterControlClass) ?>"
                   value="<?= e((string) ($values['job_title'] ?? '')) ?>" placeholder="<?= e(__('entities.job_title_ph')) ?>">
        </div>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="mb-3">
        <label class="form-label" for="<?= e($efId('entity_title_prefix')) ?>"><?= e(__('entities.title_prefix')) ?></label>
        <input type="text" name="title_prefix" id="<?= e($efId('entity_title_prefix')) ?>" class="form-control entity-letter-field"
               value="<?= e($titlePrefixValue) ?>" placeholder="<?= e(__('entities.title_prefix_ph')) ?>">
    </div>
    <div class="mb-3">
        <label class="form-label" for="<?= e($efId('entity_directed_to')) ?>"><?= e(__('entities.directed_to')) ?></label>
        <input type="text" name="directed_to" id="<?= e($efId('entity_directed_to')) ?>" class="form-control entity-letter-field"
               value="<?= e((string) ($values['directed_to'] ?? '')) ?>" placeholder="<?= e(__('entities.directed_to_ph')) ?>">
    </div>
    <div class="mb-3">
        <?php require __DIR__ . '/_entity_honorific_field.php'; ?>
    </div>
    <?php if ($entityLetterIncludeJob): ?>
    <div class="mb-0">
        <label class="form-label" for="<?= e($efId('entity_job_title')) ?>"><?= e(__('entities.job_title')) ?></label>
        <input type="text" name="job_title" id="<?= e($efId('entity_job_title')) ?>" class="form-control"
               value="<?= e((string) ($values['job_title'] ?? '')) ?>" placeholder="<?= e(__('entities.job_title_ph')) ?>">
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>
