<?php
/**
 * بطاقات سلسلة الاعتماد (الأول / الثاني / الثالث) — مشتركة بين الصادر والوارد والداخلية.
 *
 * @var array $chain ['prefix' => outgoing|incoming|internal, 'first' => user|dept_manager,
 *                    'intro' => ?callable (محتوى قبل المعتمدين داخل البطاقة)]
 * @var array $settings
 * @var array $approvalUsers
 * @var callable $approverPersonHtml
 * @var callable $approverPhotoUrl
 * @var callable $stageToggleTips
 */
$p = (string) $chain['prefix'];
$firstMode = (string) ($chain['first'] ?? 'user');

$chainTimeout = static function (string $stage) use ($settings, $p): array {
    $key = $stage === 'deputy' ? $p . '_approval_deputy' : $p . '_approval';
    $unit = (string) ($settings[$key . '_unit'] ?? 'hours');
    $minutes = (int) ($settings[$key . '_minutes'] ?? 120);
    $value = $unit === 'hours' ? max(1, (int) round($minutes / 60)) : $minutes;
    return [$key, $unit, $value];
};

$renderUserSelect = static function (string $selectId) use ($approvalUsers, $approverPhotoUrl, $settings, $p): void {
    $current = (int) ($settings[$selectId] ?? 0);
    ?>
    <select name="<?= e($selectId) ?>" id="<?= e($selectId) ?>" class="d-none" data-approver-select data-approver-chain="<?= e($p) ?>" aria-hidden="true" tabindex="-1">
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

$renderTimeout = static function (string $stage, string $labelKey, string $hintKey) use ($chainTimeout): void {
    [$key, $unit, $value] = $chainTimeout($stage);
    ?>
    <div class="settings-timeout-row">
        <label class="form-label mb-0" for="<?= e($key) ?>_value"><?= e(__($labelKey)) ?></label>
        <div class="input-group settings-timeout-input">
            <input type="number" name="<?= e($key) ?>_value" id="<?= e($key) ?>_value" class="form-control text-center" dir="ltr"
                   min="1" max="<?= $unit === 'hours' ? 72 : 4320 ?>" step="1" value="<?= (int) $value ?>">
            <select name="<?= e($key) ?>_unit" id="<?= e($key) ?>_unit" class="form-select"
                    onchange="(function(s){var i=document.getElementById('<?= e($key) ?>_value');if(!i)return;i.max=s.value==='hours'?72:4320;if(parseInt(i.value,10)>parseInt(i.max,10))i.value=i.max;})(this)">
                <option value="minutes" <?= $unit === 'minutes' ? 'selected' : '' ?>><?= e(__('settings.minutes')) ?></option>
                <option value="hours" <?= $unit === 'hours' ? 'selected' : '' ?>><?= e(__('settings.hours')) ?></option>
            </select>
        </div>
    </div>
    <div class="form-text"><?= e(__($hintKey)) ?></div>
    <?php
};

$renderPersons = static function (string $role) use ($approverPersonHtml, $settings, $p): string {
    $selectId = $p . '_approver_' . $role . '_user_id';
    return $approverPersonHtml($selectId, (int) ($settings[$selectId] ?? 0));
};

$renderStageToggle = static function (string $role, string $headingKey, bool $on) use ($stageToggleTips, $p): void {
    $tip = $stageToggleTips($headingKey);
    $id = $p . '_approver_' . $role . '_enabled';
    ?>
    <div class="form-check form-switch mb-0 settings-approver-toggle"
         data-bs-toggle="tooltip" data-bs-trigger="hover" data-bs-placement="top"
         title="<?= e($on ? $tip['on'] : $tip['off']) ?>"
         data-tip-on="<?= e($tip['on']) ?>" data-tip-off="<?= e($tip['off']) ?>">
        <input class="form-check-input" type="checkbox" role="switch" name="<?= e($id) ?>" id="<?= e($id) ?>" value="1"
               data-approver-stage-toggle <?= $on ? 'checked' : '' ?>>
        <label class="form-check-label" for="<?= e($id) ?>"><?= e(__('settings.outgoing_approver_stage_enable')) ?></label>
    </div>
    <?php
};

$deputyStageOn = ($settings[$p . '_approver_deputy_enabled'] ?? '1') === '1';
$altStageOn = ($settings[$p . '_approver_alternate_enabled'] ?? '1') === '1';
$approvalFlag = $p . '_approval_enabled';
$approvalOn = ($settings[$approvalFlag] ?? '0') === '1';
$approvalTip = $stageToggleTips('settings.approval_stage_title');
?>
<div class="settings-approver-block settings-approval-stage-block <?= $approvalOn ? '' : 'is-stage-off' ?>" data-approver-stage>
    <div class="settings-approver-block-head">
        <span class="settings-approver-step settings-approval-stage-step"><i class="bi bi-shield-check" aria-hidden="true"></i></span>
        <div class="flex-grow-1 min-w-0">
            <div class="settings-approver-block-title"><?= e(__('settings.approval_stage_title')) ?></div>
            <div class="settings-review-desc"><?= e(__('settings.' . $p . '_approval_enabled')) ?></div>
        </div>
        <div class="form-check form-switch mb-0 settings-approver-toggle"
             data-bs-toggle="tooltip" data-bs-trigger="hover" data-bs-placement="top"
             title="<?= e($approvalOn ? $approvalTip['on'] : $approvalTip['off']) ?>"
             data-tip-on="<?= e($approvalTip['on']) ?>" data-tip-off="<?= e($approvalTip['off']) ?>">
            <input class="form-check-input" type="checkbox" role="switch" name="<?= e($approvalFlag) ?>" id="<?= e($approvalFlag) ?>" value="1"
                   data-approver-stage-toggle <?= $approvalOn ? 'checked' : '' ?>>
            <label class="form-check-label" for="<?= e($approvalFlag) ?>"><?= e(__('settings.outgoing_approver_stage_enable')) ?></label>
        </div>
    </div>
    <div class="alert alert-secondary py-2 px-3 small mb-3 settings-approver-off-note"><?= e(__('settings.' . $p . '_approval_stage_off_hint')) ?></div>
    <div class="settings-approver-stage-body">
<?php if (!empty($chain['intro']) && is_callable($chain['intro'])) {
    ($chain['intro'])();
} ?>
<div class="settings-approval-chain">
<div class="row g-3">
    <div class="col-lg-4">
        <div class="settings-approver-block">
            <div class="settings-approver-block-head">
                <span class="settings-approver-step">1</span>
                <div class="flex-grow-1 min-w-0">
                    <div class="settings-approver-block-title"><?= e(__('settings.outgoing_approver_primary_heading')) ?></div>
                </div>
                <?php if ($firstMode === 'dept_manager'): ?>
                    <div class="settings-approver-person is-auto">
                        <span class="settings-approver-avatar-wrap">
                            <span class="settings-approver-avatar is-fallback is-auto"><i class="bi bi-diagram-3" aria-hidden="true"></i></span>
                        </span>
                        <div class="settings-approver-person-text">
                            <div class="settings-approver-person-name"><?= e(__('settings.internal_approver_dept_manager')) ?></div>
                            <div class="settings-approver-person-job"><?= e(__('settings.internal_approver_dept_manager_auto')) ?></div>
                        </div>
                    </div>
                <?php else: ?>
                    <?= $renderPersons('sg') ?>
                <?php endif; ?>
            </div>
            <div class="row g-3">
                <div class="col-12">
                    <?php if ($firstMode === 'dept_manager'): ?>
                        <div class="form-text"><?= e(__('settings.internal_approver_dept_manager_hint')) ?></div>
                    <?php else: ?>
                        <?php $renderUserSelect($p . '_approver_sg_user_id'); ?>
                    <?php endif; ?>
                </div>
                <div class="col-12">
                    <?php $renderTimeout('primary', 'settings.outgoing_approval_timeout_primary', 'settings.outgoing_approval_timeout_primary_hint'); ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="settings-approver-block <?= $deputyStageOn ? '' : 'is-stage-off' ?>" data-approver-stage>
            <div class="settings-approver-block-head">
                <span class="settings-approver-step settings-approver-step-second">2</span>
                <div class="flex-grow-1 min-w-0">
                    <div class="settings-approver-block-title"><?= e(__('settings.outgoing_approver_escalation_heading')) ?></div>
                </div>
                <?php $renderStageToggle('deputy', 'settings.outgoing_approver_escalation_heading', $deputyStageOn); ?>
                <?= $renderPersons('deputy') ?>
            </div>
            <div class="alert alert-secondary py-2 px-3 small mb-3 settings-approver-off-note"><?= e(__('settings.outgoing_approver_stage_disabled_hint')) ?></div>
            <div class="row g-3 settings-approver-stage-body">
                <div class="col-12">
                    <?php $renderUserSelect($p . '_approver_deputy_user_id'); ?>
                </div>
                <div class="col-12">
                    <?php $renderTimeout('deputy', 'settings.outgoing_approval_timeout_deputy', 'settings.outgoing_approval_timeout_deputy_hint'); ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="settings-approver-block <?= $altStageOn ? '' : 'is-stage-off' ?>" data-approver-stage>
            <div class="settings-approver-block-head">
                <span class="settings-approver-step settings-approver-step-alt">3</span>
                <div class="flex-grow-1 min-w-0">
                    <div class="settings-approver-block-title"><?= e(__('settings.outgoing_approver_alternate_heading')) ?></div>
                </div>
                <?php $renderStageToggle('alternate', 'settings.outgoing_approver_alternate_heading', $altStageOn); ?>
                <?= $renderPersons('alternate') ?>
            </div>
            <div class="alert alert-secondary py-2 px-3 small mb-3 settings-approver-off-note"><?= e(__('settings.outgoing_approver_stage_disabled_hint')) ?></div>
            <div class="row g-3 settings-approver-stage-body">
                <div class="col-12">
                    <?php $renderUserSelect($p . '_approver_alternate_user_id'); ?>
                    <div class="form-text"><?= e(__('settings.outgoing_approver_alternate_hint')) ?></div>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
    </div>
<?php if ($p === 'outgoing'): ?>
<?php
$commsUsers = [];
if (function_exists('outgoing_dispatch_user_ids')) {
    $commsIds = outgoing_dispatch_user_ids();
    if ($commsIds !== []) {
        $marks = implode(',', array_fill(0, count($commsIds), '?'));
        $commsStmt = db()->prepare("SELECT id, name_ar, name_en, job_title, job_title_en, photo_path FROM `tblusers` WHERE id IN ($marks) AND role = 'communications' AND is_active = 1 ORDER BY id");
        $commsStmt->execute($commsIds);
        $commsUsers = $commsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
?>
<div class="settings-approver-block settings-comms-stage mt-3">
    <div class="settings-approver-block-head">
        <span class="settings-approver-step settings-approver-step-comms"><i class="bi bi-printer" aria-hidden="true"></i></span>
        <div class="flex-grow-1 min-w-0">
            <div class="settings-approver-block-title"><?= e(__('settings.outgoing_comms_stage_title')) ?></div>
            <div class="settings-review-desc"><?= e(__('settings.outgoing_comms_stage_desc')) ?></div>
        </div>
    </div>
    <div class="settings-approver-stage-body">
        <?php if ($commsUsers === []): ?>
            <div class="alert alert-warning py-2 px-3 small mb-0"><?= e(__('settings.outgoing_comms_stage_empty')) ?></div>
        <?php else: ?>
            <div class="form-text mb-2"><?= e(__('settings.outgoing_comms_stage_users')) ?></div>
            <div class="settings-comms-users">
                <?php foreach ($commsUsers as $commsUser):
                    $commsPhoto = function_exists('user_photo_url') ? user_photo_url($commsUser) : '';
                    $commsJob = function_exists('user_job_title') ? user_job_title($commsUser) : '';
                    ?>
                    <div class="settings-approver-person">
                        <span class="settings-approver-avatar-wrap">
                            <?php if ($commsPhoto !== ''): ?>
                                <img src="<?= e($commsPhoto) ?>" alt="" class="settings-approver-avatar">
                            <?php else: ?>
                                <span class="settings-approver-avatar is-fallback"><i class="bi bi-person" aria-hidden="true"></i></span>
                            <?php endif; ?>
                        </span>
                        <div class="settings-approver-person-text">
                            <div class="settings-approver-person-name"><?= e(user_display_name($commsUser)) ?></div>
                            <?php if ($commsJob !== ''): ?>
                                <div class="settings-approver-person-job"><?= e($commsJob) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
</div>
