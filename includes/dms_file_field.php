<?php
/**
 * حقل اختيار ملفات موحّد — نفس تنسيق الصادر الجماعي.
 *
 * المتغير المتوقع: $dmsFile (array)
 *  name       string  اسم الحقل (مطلوب) مثل attachment أو attachments[]
 *  id         string  اختياري
 *  multiple   bool
 *  required   bool
 *  accept     string
 *  label      string  فارغ لتخطي التسمية
 *  hint       string
 *  placeholder, browse, selected  نصوص اختيارية
 *  show_list  bool    افتراضي true
 *  wrap_class string  أصناف إضافية للحاوية الخارجية
 *  field_class string أصناف على .dms-file-field
 *  attrs      string  سمات إضافية على input
 *
 * المتغيرات الداخلية مسبوقة بـ $dmsFile حتى لا تطغى على متغيرات الصفحة المُضمِّنة ($id, $name ...).
 */
$dmsFile = (isset($dmsFile) && is_array($dmsFile)) ? $dmsFile : [];
$dmsFileName = trim((string) ($dmsFile['name'] ?? ''));
if ($dmsFileName === '') {
    unset($dmsFile, $dmsFileName);
    return;
}

$dmsFileId = trim((string) ($dmsFile['id'] ?? ''));
if ($dmsFileId === '') {
    $dmsFileId = preg_replace('/[^a-zA-Z0-9_-]+/', '_', rtrim($dmsFileName, '[]')) ?: 'dms_file';
}

$dmsFileMultiple = !empty($dmsFile['multiple']);
$dmsFileRequired = !empty($dmsFile['required']);
$dmsFileAccept = trim((string) ($dmsFile['accept'] ?? '.pdf,.jpg,.jpeg,.png,.gif,.webp'));
$dmsFileLabel = (string) ($dmsFile['label'] ?? '');
$dmsFileHint = (string) ($dmsFile['hint'] ?? '');
$dmsFilePlaceholder = (string) ($dmsFile['placeholder'] ?? __('file.placeholder'));
$dmsFileBrowse = (string) ($dmsFile['browse'] ?? __('file.browse'));
$dmsFileSelected = (string) ($dmsFile['selected'] ?? __('file.selected'));
$dmsFileShowList = !array_key_exists('show_list', $dmsFile) || !empty($dmsFile['show_list']);
$dmsFileAutosubmit = !empty($dmsFile['autosubmit']);
$dmsFileWrapClass = trim((string) ($dmsFile['wrap_class'] ?? 'mb-0'));
$dmsFileFieldClass = trim((string) ($dmsFile['field_class'] ?? ''));
$dmsFileAttrs = trim((string) ($dmsFile['attrs'] ?? ''));
$dmsFileInputName = $dmsFileMultiple && !str_ends_with($dmsFileName, '[]') ? ($dmsFileName . '[]') : $dmsFileName;
?>
<div class="<?= e($dmsFileWrapClass !== '' ? $dmsFileWrapClass : 'mb-0') ?>">
    <?php if ($dmsFileLabel !== ''): ?>
        <label class="form-label" for="<?= e($dmsFileId) ?>"><?= e($dmsFileLabel) ?></label>
    <?php endif; ?>
    <div class="dms-file-field<?= $dmsFileFieldClass !== '' ? ' ' . e($dmsFileFieldClass) : '' ?>"
         data-dms-file-field
         data-dms-file-multiple="<?= $dmsFileMultiple ? '1' : '0' ?>"
         data-dms-file-placeholder="<?= e($dmsFilePlaceholder) ?>"
         data-dms-file-selected="<?= e($dmsFileSelected) ?>"
         data-dms-file-delete="<?= e(__('action.delete')) ?>"
         data-dms-file-type-pdf="<?= e(__('file.type_pdf')) ?>"
         data-dms-file-type-image="<?= e(__('file.type_image')) ?>"
         data-dms-file-type-other="<?= e(__('file.type_other')) ?>"
         <?= $dmsFileAutosubmit ? 'data-dms-file-autosubmit="1"' : '' ?>>
        <div class="dms-file-field-main">
            <span data-dms-file-label><?= e($dmsFilePlaceholder) ?></span>
            <input type="file"
                   name="<?= e($dmsFileInputName) ?>"
                   id="<?= e($dmsFileId) ?>"
                   <?= $dmsFileMultiple ? 'multiple' : '' ?>
                   <?= $dmsFileRequired ? 'required' : '' ?>
                   <?= $dmsFileAccept !== '' ? 'accept="' . e($dmsFileAccept) . '"' : '' ?>
                   data-dms-file-input
                   <?= $dmsFileAttrs !== '' ? $dmsFileAttrs : '' ?>>
        </div>
        <label class="btn btn-outline-secondary btn-icon dms-file-field-btn"
               for="<?= e($dmsFileId) ?>"
               title="<?= e($dmsFileBrowse) ?>"
               aria-label="<?= e($dmsFileBrowse) ?>"
               data-bs-toggle="tooltip"
               data-bs-placement="top">
            <i class="bi bi-paperclip" aria-hidden="true"></i>
        </label>
    </div>
    <?php if ($dmsFileShowList): ?>
        <ul class="dms-file-list" data-dms-file-list hidden></ul>
    <?php endif; ?>
    <?php if ($dmsFileHint !== ''): ?>
        <p class="admin-form-hint mb-0 mt-1"><?= e($dmsFileHint) ?></p>
    <?php endif; ?>
</div>
<?php
unset(
    $dmsFile, $dmsFileName, $dmsFileId, $dmsFileMultiple, $dmsFileRequired, $dmsFileAccept,
    $dmsFileLabel, $dmsFileHint, $dmsFilePlaceholder, $dmsFileBrowse, $dmsFileSelected,
    $dmsFileShowList, $dmsFileAutosubmit, $dmsFileWrapClass, $dmsFileFieldClass, $dmsFileAttrs, $dmsFileInputName
);
