<?php
/**
 * مرحلة الاتصالات الإدارية للصادر: مسؤول أساسي بمهلة، ثم بديل.
 * عند التعيين تُمنح صلاحيات المرحلة تلقائياً، وعند الإزالة تُسحب المنح التلقائية فقط.
 * إن تُركا فارغين تُفتح للمجمّع ممن يملكون صلاحية outgoing.dispatch.
 *
 * @var array $settings
 * @var array $approvalUsers كل المستخدمين النشطين
 * @var callable $approverPersonHtml
 * @var callable $approverPhotoUrl
 * @var callable $stageToggleTips
 */
$commsPrimaryId = 'outgoing_comms_primary_user_id';
$commsAltId = 'outgoing_comms_alternate_user_id';
$commsAltFlag = 'outgoing_comms_alternate_enabled';
$commsAltOn = ($settings[$commsAltFlag] ?? '1') === '1';
$commsAltTip = $stageToggleTips('settings.outgoing_comms_alternate_heading');
$commsTimeoutKey = 'outgoing_comms';
$commsTimeoutUnit = (string) ($settings[$commsTimeoutKey . '_unit'] ?? 'hours');
$commsTimeoutMinutes = (int) ($settings[$commsTimeoutKey . '_minutes'] ?? 120);
$commsTimeoutValue = $commsTimeoutUnit === 'hours' ? max(1, (int) round($commsTimeoutMinutes / 60)) : $commsTimeoutMinutes;
$commsUsers = is_array($approvalUsers ?? null) ? $approvalUsers : [];

$renderCommsSelect = static function (string $selectId) use ($commsUsers, $approverPhotoUrl, $settings): void {
    $current = (int) ($settings[$selectId] ?? 0);
    ?>
    <select name="<?= e($selectId) ?>" id="<?= e($selectId) ?>" class="d-none" data-approver-select data-approver-chain="outgoing_comms" aria-hidden="true" tabindex="-1">
        <option value="0"><?= e(__('settings.outgoing_approver_none')) ?></option>
        <?php foreach ($commsUsers as $au): ?>
            <option value="<?= (int) $au['id'] ?>" <?= $current === (int) $au['id'] ? 'selected' : '' ?>
                    data-job-title="<?= e(user_job_title($au)) ?>"
                    data-name="<?= e(user_display_name($au)) ?>"
                    data-email="<?= e((string) ($au['email'] ?? '')) ?>"
                    data-photo="<?= e($approverPhotoUrl($au)) ?>">
                <?= e(user_display_name($au)) ?> — <?= e((string) ($au['email'] ?? '')) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <div class="form-text" data-approver-title-for="<?= e($selectId) ?>"></div>
    <?php
};
?>
<div class="settings-approver-block settings-comms-stage mt-3">
    <div class="settings-approver-block-head">
        <span class="settings-approver-step settings-approver-step-comms"><i class="bi bi-printer" aria-hidden="true"></i></span>
        <div class="flex-grow-1 min-w-0">
            <div class="settings-approver-block-title"><?= e(__('settings.outgoing_comms_stage_title')) ?></div>
            <div class="settings-review-desc"><?= e(__('settings.outgoing_comms_stage_desc')) ?></div>
        </div>
    </div>
    <div class="settings-comms-stage-body">
        <div class="row g-3">
            <div class="col-md-6">
                <div class="settings-review-slot">
                    <div class="settings-approver-block-head">
                        <span class="settings-approver-step settings-review-step is-sm">1</span>
                        <div class="flex-grow-1 min-w-0">
                            <div class="settings-approver-block-title"><?= e(__('settings.outgoing_comms_primary_heading')) ?></div>
                        </div>
                        <?= $approverPersonHtml($commsPrimaryId, (int) ($settings[$commsPrimaryId] ?? 0)) ?>
                    </div>
                    <?php $renderCommsSelect($commsPrimaryId); ?>
                    <div class="settings-timeout-row">
                        <label class="form-label mb-0" for="<?= e($commsTimeoutKey) ?>_value"><?= e(__('settings.outgoing_comms_timeout')) ?></label>
                        <div class="input-group settings-timeout-input">
                            <input type="number" name="<?= e($commsTimeoutKey) ?>_value" id="<?= e($commsTimeoutKey) ?>_value" class="form-control text-center" dir="ltr"
                                   min="1" max="<?= $commsTimeoutUnit === 'hours' ? 72 : 4320 ?>" step="1" value="<?= (int) $commsTimeoutValue ?>">
                            <select name="<?= e($commsTimeoutKey) ?>_unit" id="<?= e($commsTimeoutKey) ?>_unit" class="form-select"
                                    onchange="(function(s){var i=document.getElementById('<?= e($commsTimeoutKey) ?>_value');if(!i)return;i.max=s.value==='hours'?72:4320;if(parseInt(i.value,10)>parseInt(i.max,10))i.value=i.max;})(this)">
                                <option value="minutes" <?= $commsTimeoutUnit === 'minutes' ? 'selected' : '' ?>><?= e(__('settings.minutes')) ?></option>
                                <option value="hours" <?= $commsTimeoutUnit === 'hours' ? 'selected' : '' ?>><?= e(__('settings.hours')) ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="form-text"><?= e(__('settings.outgoing_comms_timeout_hint')) ?></div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="settings-approver-block settings-review-slot <?= $commsAltOn ? '' : 'is-stage-off' ?>" data-approver-stage>
                    <div class="settings-approver-block-head">
                        <span class="settings-approver-step settings-review-step is-sm is-second">2</span>
                        <div class="flex-grow-1 min-w-0">
                            <div class="settings-approver-block-title"><?= e(__('settings.outgoing_comms_alternate_heading')) ?></div>
                        </div>
                        <div class="form-check form-switch mb-0 settings-approver-toggle"
                             data-bs-toggle="tooltip" data-bs-trigger="hover" data-bs-placement="top"
                             title="<?= e($commsAltOn ? $commsAltTip['on'] : $commsAltTip['off']) ?>"
                             data-tip-on="<?= e($commsAltTip['on']) ?>" data-tip-off="<?= e($commsAltTip['off']) ?>">
                            <input class="form-check-input" type="checkbox" role="switch" name="<?= e($commsAltFlag) ?>" id="<?= e($commsAltFlag) ?>" value="1"
                                   data-approver-stage-toggle <?= $commsAltOn ? 'checked' : '' ?>>
                            <label class="form-check-label" for="<?= e($commsAltFlag) ?>"><?= e(__('settings.outgoing_approver_stage_enable')) ?></label>
                        </div>
                        <?= $approverPersonHtml($commsAltId, (int) ($settings[$commsAltId] ?? 0)) ?>
                    </div>
                    <div class="alert alert-secondary py-2 px-3 small mb-3 settings-approver-off-note"><?= e(__('settings.outgoing_approver_stage_disabled_hint')) ?></div>
                    <div class="settings-approver-stage-body">
                        <?php $renderCommsSelect($commsAltId); ?>
                        <div class="form-text"><?= e(__('settings.outgoing_comms_alternate_hint')) ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="form-text mt-3 mb-0"><?= e(__('settings.outgoing_comms_pool_hint')) ?></div>
    </div>
</div>

