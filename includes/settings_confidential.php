<?php
/** إعدادات سرية الرؤية والمرفقات والقنوات الخارجية */
$confVisOn = ($settings['confidentiality_restricts_visibility'] ?? '1') === '1';
$confExtRedacted = ($settings['conf_external_notify_mode'] ?? 'redacted') !== 'full';
$attachStages = [
    'prep_signer' => 'settings.conf_attach_prep_signer',
    'reviewer' => 'settings.conf_attach_reviewer',
    'approver' => 'settings.conf_attach_approver',
    'comms' => 'settings.conf_attach_comms',
    'cc' => 'settings.conf_attach_cc',
];
?>
<div class="settings-approver-block" data-confidential-settings>
    <div class="form-check form-switch mb-3">
        <input class="form-check-input" type="checkbox" role="switch" name="confidentiality_restricts_visibility" id="confidentiality_restricts_visibility" value="1"
               <?= $confVisOn ? 'checked' : '' ?>>
        <label class="form-check-label" for="confidentiality_restricts_visibility"><?= e(__('settings.conf_restricts_visibility')) ?></label>
    </div>
    <p class="form-text mb-3"><?= e(__('settings.conf_restricts_visibility_help')) ?></p>
    <div class="fw-semibold mb-2"><?= e(__('settings.conf_attach_heading')) ?></div>
    <p class="form-text mb-3"><?= e(__('settings.conf_attach_help')) ?></p>
    <div class="row g-2 mb-4">
        <?php foreach ($attachStages as $stageKey => $labelKey):
            $on = ($settings['conf_attach_' . $stageKey] ?? '1') === '1';
            $id = 'conf_attach_' . $stageKey;
            ?>
            <div class="col-md-6">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" name="<?= e($id) ?>" id="<?= e($id) ?>" value="1" <?= $on ? 'checked' : '' ?>>
                    <label class="form-check-label" for="<?= e($id) ?>"><?= e(__($labelKey)) ?></label>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="fw-semibold mb-2"><?= e(__('settings.conf_external_heading')) ?></div>
    <p class="form-text mb-3"><?= e(__('settings.conf_external_help')) ?></p>
    <div class="form-check form-switch mb-2">
        <input class="form-check-input" type="checkbox" role="switch" name="conf_external_notify_redacted" id="conf_external_notify_redacted" value="1"
               <?= $confExtRedacted ? 'checked' : '' ?>>
        <label class="form-check-label" for="conf_external_notify_redacted"><?= e(__('settings.conf_external_redacted')) ?></label>
    </div>
    <p class="form-text mb-0"><?= e(__('settings.conf_external_redacted_help')) ?></p>
</div>
