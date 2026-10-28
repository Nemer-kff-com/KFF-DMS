<?php
/**
 * مرفقات الوارد + الملاحظات.
 * اختياري: $attachLabel, $attachHint, $notesValue
 */
$attachLabel = $attachLabel ?? __('corr.scan_attach');
$notesValue = (string) ($notesValue ?? '');
?>
<div class="admin-form-section admin-form-section-last">
    <div class="row g-3">
        <div class="col-lg-6">
            <div class="corr-field-card h-100">
                <?php require __DIR__ . '/corr_attachments_card_title.php'; ?>
                <div class="corr-field-card-body">
                <?php
                $dmsFile = [
                    'name' => 'attachments[]',
                    'id' => 'attachment',
                    'multiple' => true,
                    'accept' => '.pdf,.jpg,.jpeg,.png,.gif,.webp',
                    'label' => $attachLabel,
                    'hint' => '',
                    'browse' => __('file.browse'),
                    'placeholder' => __('file.placeholder'),
                    'selected' => __('file.selected'),
                ];
                require __DIR__ . '/corr_attachments_upload_block.php';
                ?>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="corr-field-card h-100">
                <div class="corr-field-card-title"><i class="bi bi-journal-text me-1"></i><?= e(__('corr.notes')) ?></div>
                <div class="corr-field-card-body">
                    <label class="visually-hidden" for="notes"><?= e(__('corr.notes')) ?></label>
                    <textarea name="notes" id="notes" class="form-control" rows="1"><?= e($notesValue) ?></textarea>
                </div>
            </div>
        </div>
    </div>
</div>
