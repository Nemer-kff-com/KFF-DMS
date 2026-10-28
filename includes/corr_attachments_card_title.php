<?php
/**
 * عنوان بطاقة الوثائق المرفقة + زر نوع مرفق جديد (إن وُجدت الصلاحية).
 */
$attachCardTitle = $attachCardTitle ?? __('corr.attachments');
$canAddEnclosureTypeTitle = isset($user) && user_can('lookups.manage', $user);
?>
<div class="corr-field-card-title corr-attachments-card-title">
    <span><i class="bi bi-paperclip me-1"></i><?= e($attachCardTitle) ?></span>
    <?php if ($canAddEnclosureTypeTitle): ?>
    <button type="button" class="btn btn-sm btn-outline-secondary enclosure-type-add"
            data-bs-toggle="tooltip" data-bs-placement="top"
            data-enclosure-open-create
            title="<?= e(__('lookups.new_attachment_type')) ?>">
        <i class="bi bi-plus-lg" aria-hidden="true"></i>
        <span class="visually-hidden"><?= e(__('lookups.new_attachment_type')) ?></span>
    </button>
    <?php elseif (isset($user) && dms_perm_ui('lookups.manage', $user) === 'deny'): ?>
    <?= dms_perm_denied_wrap('<button type="button" class="btn btn-sm btn-outline-secondary enclosure-type-add" disabled aria-disabled="true"><i class="bi bi-plus-lg" aria-hidden="true"></i></button>', __('lookups.new_attachment_type')) ?>
    <?php endif; ?>
</div>
