<?php
/**
 * حقول التواصل لجهة المراسلة
 * يتطلب: $efId, $values
 * اختياري: $entityContactCompact, $entityContactShowHint
 */
$entityContactCompact = !empty($entityContactCompact);
$entityContactShowHint = !empty($entityContactShowHint);
?>
<?php if ($entityContactShowHint): ?>
<p class="admin-form-hint"><?= e(__('entities.contact_send_hint')) ?></p>
<?php endif; ?>
<div class="<?= $entityContactCompact ? 'entity-form-field-stack' : '' ?>">
    <div class="<?= $entityContactCompact ? '' : 'mb-3' ?>">
        <label class="form-label" for="<?= e($efId('entity_mobile')) ?>"><?= e(__('entities.mobile')) ?></label>
        <input type="text" name="mobile" id="<?= e($efId('entity_mobile')) ?>" class="form-control" dir="ltr"
               value="<?= e((string) ($values['mobile'] ?? '')) ?>" placeholder="<?= e(__('entities.mobile_ph')) ?>">
        <?php if ($entityContactShowHint): ?>
        <p class="admin-form-hint mb-0 mt-1"><?= e(__('entities.mobile_hint')) ?></p>
        <?php endif; ?>
    </div>
    <div class="<?= $entityContactCompact ? '' : 'mb-3' ?>">
        <label class="form-label" for="<?= e($efId('entity_email')) ?>"><?= e(__('entities.email')) ?></label>
        <input type="email" name="email" id="<?= e($efId('entity_email')) ?>" class="form-control" dir="ltr"
               value="<?= e((string) ($values['email'] ?? '')) ?>" placeholder="<?= e(__('entities.email_ph')) ?>">
    </div>
    <div class="<?= $entityContactCompact ? '' : 'mb-3' ?>">
        <label class="form-label" for="<?= e($efId('entity_phone')) ?>"><?= e(__('entities.phone_landline')) ?></label>
        <input type="text" name="phone" id="<?= e($efId('entity_phone')) ?>" class="form-control" dir="ltr" value="<?= e((string) ($values['phone'] ?? '')) ?>">
    </div>
    <div class="<?= $entityContactCompact ? '' : 'mb-3' ?>">
        <label class="form-label" for="<?= e($efId('entity_address')) ?>"><?= e(__('entities.address')) ?></label>
        <input type="text" name="address" id="<?= e($efId('entity_address')) ?>" class="form-control"
               value="<?= e((string) ($values['address'] ?? '')) ?>" placeholder="<?= e(__('entities.address_ph')) ?>">
    </div>
    <div class="<?= $entityContactCompact ? '' : 'mb-3' ?>">
        <label class="form-label" for="<?= e($efId('entity_location')) ?>"><?= e(__('entities.location')) ?></label>
        <input type="url" name="location" id="<?= e($efId('entity_location')) ?>" class="form-control" dir="ltr" inputmode="url"
               value="<?= e((string) ($values['location'] ?? '')) ?>" placeholder="<?= e(__('entities.location_ph')) ?>"
               autocomplete="url">
        <p class="admin-form-hint mb-0 mt-1"><?= e(__('entities.location_hint')) ?></p>
    </div>
    <div class="<?= $entityContactCompact ? '' : 'mb-0' ?>">
        <label class="form-label" for="<?= e($efId('entity_notes')) ?>"><?= e(__('entities.notes')) ?></label>
        <textarea name="notes" id="<?= e($efId('entity_notes')) ?>" class="form-control" rows="3"
                  placeholder="<?= e(__('entities.notes_ph')) ?>"><?= e((string) ($values['notes'] ?? '')) ?></textarea>
    </div>
</div>
