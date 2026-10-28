<?php
/**
 * بطاقة مرحلة المراجعة قبل سلسلة الاعتماد: مراجع أساسي بمهلة، ثم مراجع بديل،
 * وإن تُركا فارغين يراجع أي مستخدم من «الاتصالات الإدارية».
 *
 * @var array $review ['prefix' => outgoing|incoming|internal, 'title' => key, 'desc' => key, 'then' => key]
 * @var array $settings
 * @var array $approvalUsers
 * @var callable $approverPersonHtml
 * @var callable $approverPhotoUrl
 * @var callable $stageToggleTips
 */
$rp = (string) $review['prefix'];
$reviewFlag = $rp . '_review_enabled';
$reviewOn = ($settings[$reviewFlag] ?? '0') === '1';
$reviewTip = $stageToggleTips('settings.review_stage_name');

$renderReviewSelect = static function (string $selectId) use ($approvalUsers, $approverPhotoUrl, $settings, $rp): void {
    $current = (int) ($settings[$selectId] ?? 0);
    ?>
    <select name="<?= e($selectId) ?>" id="<?= e($selectId) ?>" class="d-none" data-approver-select data-approver-chain="<?= e($rp) ?>" aria-hidden="true" tabindex="-1">
        <option value="0"><?= e(__('settings.outgoing_approver_none')) ?></option>
        <?php foreach ($approvalUsers as $au): ?>
            <option value="<?= (int) $au['id'] ?>" <?= $current === (int) $au['id'] ? 'selected' : '' ?>
                    data-job-title="<?= e(user_job_title($au)) ?>"
                    data-name="<?= e(user_display_name($au)) ?>"
                    data-photo="<?= e($approverPhotoUrl($au)) ?>">
                <?= e(user_display_name($au)) ?> — <?= e((string) ($au['email'] ?? '')) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <div class="form-text" data-approver-title-for="<?= e($selectId) ?>"></div>
    <?php
};

$reviewTimeoutKey = $rp . '_review';
$reviewTimeoutUnit = (string) ($settings[$reviewTimeoutKey . '_unit'] ?? 'hours');
$reviewTimeoutMinutes = (int) ($settings[$reviewTimeoutKey . '_minutes'] ?? 120);
$reviewTimeoutValue = $reviewTimeoutUnit === 'hours' ? max(1, (int) round($reviewTimeoutMinutes / 60)) : $reviewTimeoutMinutes;
$reviewPrimaryId = $rp . '_reviewer_primary_user_id';
$reviewAltId = $rp . '_reviewer_alternate_user_id';
$reviewAltFlag = $rp . '_reviewer_alternate_enabled';
$reviewAltOn = ($settings[$reviewAltFlag] ?? '1') === '1';
$reviewAltTip = $stageToggleTips('settings.review_alternate_heading');
?>
<div class="settings-approver-block settings-review-block <?= $reviewOn ? '' : 'is-stage-off' ?>" data-approver-stage>
    <div class="settings-approver-block-head">
        <span class="settings-approver-step settings-review-step"><i class="bi bi-clipboard-check" aria-hidden="true"></i></span>
        <div class="flex-grow-1 min-w-0">
            <div class="settings-approver-block-title"><?= e(__($review['title'])) ?></div>
            <div class="settings-review-desc"><?= e(__($review['desc'])) ?></div>
        </div>
        <div class="form-check form-switch mb-0 settings-approver-toggle"
             data-bs-toggle="tooltip" data-bs-trigger="hover" data-bs-placement="top"
             title="<?= e($reviewOn ? $reviewTip['on'] : $reviewTip['off']) ?>"
             data-tip-on="<?= e($reviewTip['on']) ?>" data-tip-off="<?= e($reviewTip['off']) ?>">
            <input class="form-check-input" type="checkbox" role="switch" name="<?= e($reviewFlag) ?>" id="<?= e($reviewFlag) ?>" value="1"
                   data-approver-stage-toggle <?= $reviewOn ? 'checked' : '' ?>>
            <label class="form-check-label" for="<?= e($reviewFlag) ?>"><?= e(__('settings.outgoing_approver_stage_enable')) ?></label>
        </div>
    </div>
    <div class="alert alert-secondary py-2 px-3 small mb-3 settings-approver-off-note"><?= e(__('settings.review_stage_off_hint')) ?></div>
    <div class="settings-approver-stage-body">
        <div class="row g-3">
            <div class="col-md-6">
                <div class="settings-review-slot">
                    <div class="settings-approver-block-head">
                        <span class="settings-approver-step settings-review-step is-sm">1</span>
                        <div class="flex-grow-1 min-w-0">
                            <div class="settings-approver-block-title"><?= e(__('settings.review_primary_heading')) ?></div>
                        </div>
                        <?= $approverPersonHtml($reviewPrimaryId, (int) ($settings[$reviewPrimaryId] ?? 0)) ?>
                    </div>
                    <?php $renderReviewSelect($reviewPrimaryId); ?>
                    <div class="settings-timeout-row">
                        <label class="form-label mb-0" for="<?= e($reviewTimeoutKey) ?>_value"><?= e(__('settings.review_timeout')) ?></label>
                        <div class="input-group settings-timeout-input">
                            <input type="number" name="<?= e($reviewTimeoutKey) ?>_value" id="<?= e($reviewTimeoutKey) ?>_value" class="form-control text-center" dir="ltr"
                                   min="1" max="<?= $reviewTimeoutUnit === 'hours' ? 72 : 4320 ?>" step="1" value="<?= (int) $reviewTimeoutValue ?>">
                            <select name="<?= e($reviewTimeoutKey) ?>_unit" id="<?= e($reviewTimeoutKey) ?>_unit" class="form-select"
                                    onchange="(function(s){var i=document.getElementById('<?= e($reviewTimeoutKey) ?>_value');if(!i)return;i.max=s.value==='hours'?72:4320;if(parseInt(i.value,10)>parseInt(i.max,10))i.value=i.max;})(this)">
                                <option value="minutes" <?= $reviewTimeoutUnit === 'minutes' ? 'selected' : '' ?>><?= e(__('settings.minutes')) ?></option>
                                <option value="hours" <?= $reviewTimeoutUnit === 'hours' ? 'selected' : '' ?>><?= e(__('settings.hours')) ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="form-text"><?= e(__('settings.review_timeout_hint')) ?></div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="settings-approver-block settings-review-slot <?= $reviewAltOn ? '' : 'is-stage-off' ?>" data-approver-stage>
                    <div class="settings-approver-block-head">
                        <span class="settings-approver-step settings-review-step is-sm is-second">2</span>
                        <div class="flex-grow-1 min-w-0">
                            <div class="settings-approver-block-title"><?= e(__('settings.review_alternate_heading')) ?></div>
                        </div>
                        <div class="form-check form-switch mb-0 settings-approver-toggle"
                             data-bs-toggle="tooltip" data-bs-trigger="hover" data-bs-placement="top"
                             title="<?= e($reviewAltOn ? $reviewAltTip['on'] : $reviewAltTip['off']) ?>"
                             data-tip-on="<?= e($reviewAltTip['on']) ?>" data-tip-off="<?= e($reviewAltTip['off']) ?>">
                            <input class="form-check-input" type="checkbox" role="switch" name="<?= e($reviewAltFlag) ?>" id="<?= e($reviewAltFlag) ?>" value="1"
                                   data-approver-stage-toggle <?= $reviewAltOn ? 'checked' : '' ?>>
                            <label class="form-check-label" for="<?= e($reviewAltFlag) ?>"><?= e(__('settings.outgoing_approver_stage_enable')) ?></label>
                        </div>
                        <?= $approverPersonHtml($reviewAltId, (int) ($settings[$reviewAltId] ?? 0)) ?>
                    </div>
                    <div class="alert alert-secondary py-2 px-3 small mb-3 settings-approver-off-note"><?= e(__('settings.outgoing_approver_stage_disabled_hint')) ?></div>
                    <div class="settings-approver-stage-body">
                        <?php $renderReviewSelect($reviewAltId); ?>
                        <div class="form-text"><?= e(__('settings.review_alternate_hint')) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="settings-chain-arrow"><i class="bi bi-arrow-down" aria-hidden="true"></i><?= e(__($review['then'] ?? 'settings.review_then_approval')) ?></div>
