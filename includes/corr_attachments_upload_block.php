<?php
/**
 * صف موحّد لرفع المرفقات: نوع المرفق ثم الملف (نفس سلوك الصادر).
 *
 * قبل التضمين (اختياري):
 * - $dmsFile (array) إعدادات dms_file_field
 * - $showEnclosure (bool) افتراضي true
 * - $attachUploadShowFile (bool) افتراضي true
 * - $enclosureOnlyWhenUpload (bool) لا تعرض النوع إن لم يُعرض الرفع
 */
$showEnclosure = $showEnclosure ?? true;
$attachUploadShowFile = $attachUploadShowFile ?? true;
if (!$attachUploadShowFile && !empty($enclosureOnlyWhenUpload)) {
    $showEnclosure = false;
}
if (!$showEnclosure && !$attachUploadShowFile) {
    return;
}

$dmsFile = (isset($dmsFile) && is_array($dmsFile)) ? $dmsFile : [];
$dmsFile += [
    'name' => 'attachments[]',
    'id' => 'attachment',
    'multiple' => true,
    'accept' => '.pdf,.jpg,.jpeg,.png,.gif,.webp',
    'label' => __('corr.attach'),
    'hint' => '',
    'browse' => __('file.browse'),
    'placeholder' => __('file.placeholder'),
    'selected' => __('file.selected'),
];
?>
<div class="row g-2 align-items-start corr-attachments-upload-row">
    <?php if ($showEnclosure): ?>
    <div class="<?= $attachUploadShowFile ? 'col-md-6' : 'col-12' ?>">
        <?php
        $enclosureFieldClass = 'col-12';
        $enclosureWrapClass = 'row g-2';
        require __DIR__ . '/corr_enclosures_fields.php';
        ?>
    </div>
    <?php endif; ?>
    <?php if ($attachUploadShowFile): ?>
    <div class="<?= $showEnclosure ? 'col-md-6' : 'col-12' ?>">
        <?php require __DIR__ . '/dms_file_field.php'; ?>
    </div>
    <?php endif; ?>
</div>
<?php
unset($showEnclosure, $attachUploadShowFile, $enclosureOnlyWhenUpload, $enclosureFieldClass, $enclosureWrapClass);
?>
