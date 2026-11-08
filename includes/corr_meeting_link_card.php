<?php
/**
 * بطاقة رابط الاجتماع (بعد إرسال الخطاب) — دفعة أو صادر مفرد.
 *
 * @var array $meetingData
 * @var bool $meetingCanEdit
 * @var bool $meetingCanSend
 * @var int $meetingRemoteCount
 * @var int $meetingRemotePending
 * @var bool $meetingInvitationSent
 * @var int $meetingBatchId
 * @var int $meetingCorrId
 */
$meetingData = is_array($meetingData ?? null) ? $meetingData : correspondence_meeting_data();
$meetingCanEdit = !empty($meetingCanEdit);
$meetingCanSend = !empty($meetingCanSend);
$meetingRemoteCount = (int) ($meetingRemoteCount ?? 0);
$meetingRemotePending = (int) ($meetingRemotePending ?? 0);
$meetingInvitationSent = !empty($meetingInvitationSent);
$meetingBatchId = (int) ($meetingBatchId ?? 0);
$meetingCorrId = (int) ($meetingCorrId ?? 0);
$meetingUrl = (string) ($meetingData['url'] ?? '');
$hasUrl = $meetingUrl !== '';
$atLocal = function_exists('correspondence_meeting_at_local')
    ? correspondence_meeting_at_local((string) ($meetingData['at'] ?? ''))
    : '';
$sendEnabled = $meetingCanSend && $hasUrl && $meetingInvitationSent && $meetingRemoteCount > 0;
$sendTip = !$meetingInvitationSent
    ? __('meeting.send_need_letter')
    : (!$hasUrl
        ? __('meeting.send_need_url')
        : ($meetingRemoteCount < 1 ? __('meeting.send_need_remote') : __('meeting.send')));
$meetingCollapseId = $meetingBatchId > 0
    ? 'corr-meeting-body-b' . $meetingBatchId
    : ('corr-meeting-body-c' . ($meetingCorrId > 0 ? $meetingCorrId : '0'));
$meetingFormId = $meetingCollapseId . '-form';
?>
<div class="card-panel mb-3 no-print corr-meeting-card">
    <div class="corr-meeting-bar">
        <button type="button" class="corr-meeting-toggle" data-bs-toggle="collapse"
                data-bs-target="#<?= e($meetingCollapseId) ?>" aria-expanded="false" aria-controls="<?= e($meetingCollapseId) ?>">
            <span class="corr-meeting-toggle-main">
                <span><i class="bi bi-camera-video me-1"></i><?= e(__('meeting.title')) ?></span>
                <?php if ($hasUrl): ?>
                    <span class="badge text-bg-light text-dark"><?= e(function_exists('correspondence_meeting_platform_label') ? correspondence_meeting_platform_label($meetingUrl) : __('meeting.saved')) ?></span>
                <?php endif; ?>
                <?php if ($meetingRemoteCount > 0): ?>
                    <span class="badge text-bg-light text-dark"><?= e(__('rsvp.mode_remote')) ?> <?= (int) $meetingRemoteCount ?></span>
                <?php endif; ?>
            </span>
            <i class="bi bi-chevron-down corr-activity-chevron" aria-hidden="true"></i>
        </button>
        <?php if ($meetingCanEdit || $meetingCanSend): ?>
        <div class="corr-meeting-bar-actions">
            <?php if ($meetingCanEdit): ?>
                <button class="btn btn-sm btn-outline-secondary" type="submit" form="<?= e($meetingFormId) ?>" name="meeting_action" value="save">
                    <i class="bi bi-check2 me-1"></i><?= e(__('meeting.save')) ?>
                </button>
            <?php endif; ?>
            <?php if ($meetingCanSend): ?>
                <span class="d-inline-block" tabindex="0" data-bs-toggle="tooltip" title="<?= e($sendTip) ?>">
                    <button class="btn btn-sm btn-kff" type="submit" form="<?= e($meetingFormId) ?>" name="meeting_action" value="send"
                            <?= $sendEnabled ? '' : 'disabled' ?>
                            <?= $sendEnabled ? 'data-confirm="' . e(__('meeting.send_confirm')) . '"' : '' ?>>
                        <i class="bi bi-send me-1"></i><?= e(__('meeting.send')) ?>
                        <?php if ($meetingRemotePending > 0): ?>
                            (<?= (int) $meetingRemotePending ?>)
                        <?php endif; ?>
                    </button>
                </span>
                <?php if ($meetingRemoteCount > 0 && $meetingRemotePending < $meetingRemoteCount && $sendEnabled): ?>
                    <label class="small text-muted mb-0">
                        <input type="checkbox" name="meeting_resend" value="1" class="form-check-input me-1" form="<?= e($meetingFormId) ?>">
                        <?= e(__('meeting.resend')) ?>
                    </label>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <div class="collapse" id="<?= e($meetingCollapseId) ?>">
    <div class="card-body">
        <p class="corr-meeting-hint"><?= e(__('meeting.hint')) ?></p>
        <form method="post" action="<?= e(base_url('outgoing/meeting.php')) ?>" class="corr-meeting-form" id="<?= e($meetingFormId) ?>">
            <?= csrf_field() ?>
            <?php if ($meetingBatchId > 0): ?>
                <input type="hidden" name="batch_id" value="<?= $meetingBatchId ?>">
            <?php endif; ?>
            <?php if ($meetingCorrId > 0): ?>
                <input type="hidden" name="correspondence_id" value="<?= $meetingCorrId ?>">
            <?php endif; ?>
            <div class="corr-meeting-grid">
                <?php if ($meetingCanEdit): ?>
                <div class="corr-field-card corr-meeting-span-full">
                    <div class="corr-field-card-title"><i class="bi bi-clipboard-plus me-1"></i><?= e(__('meeting.paste')) ?></div>
                    <div class="corr-field-card-body">
                        <textarea class="form-control" name="meeting_paste" id="meeting_paste" rows="3" dir="ltr"
                                  placeholder="<?= e(__('meeting.paste_ph')) ?>"></textarea>
                    </div>
                </div>
                <?php endif; ?>
                <div class="corr-field-card corr-meeting-span-full">
                    <div class="corr-field-card-title"><label class="mb-0" for="meeting_url"><i class="bi bi-link-45deg me-1"></i><?= e(__('meeting.url')) ?></label></div>
                    <div class="corr-field-card-body">
                        <input type="text" class="form-control corr-meeting-ltr" name="meeting_url" id="meeting_url" dir="ltr"
                               value="<?= e($meetingUrl) ?>" maxlength="500" placeholder="<?= e(__('meeting.url_ph')) ?>"
                               inputmode="url" <?= $meetingCanEdit ? '' : 'readonly' ?>>
                    </div>
                </div>
                <div class="corr-field-card">
                    <div class="corr-field-card-title"><label class="mb-0" for="meeting_title"><i class="bi bi-type me-1"></i><?= e(__('meeting.event_title')) ?></label></div>
                    <div class="corr-field-card-body">
                        <input type="text" class="form-control" name="meeting_title" id="meeting_title"
                               value="<?= e((string) ($meetingData['title'] ?? '')) ?>" maxlength="200"
                               placeholder="<?= e(__('meeting.event_title_ph')) ?>"
                               <?= $meetingCanEdit ? '' : 'readonly' ?>>
                    </div>
                </div>
                <div class="corr-field-card">
                    <div class="corr-field-card-title"><i class="bi bi-calendar3 me-1"></i><?= e(__('meeting.when')) ?></div>
                    <div class="corr-field-card-body">
                        <input type="datetime-local" class="form-control corr-meeting-when-dt" name="meeting_at" id="meeting_at"
                               value="<?= e($atLocal) ?>" step="60"
                               title="<?= e(__('meeting.when')) ?>"
                               <?= $meetingCanEdit ? '' : 'readonly' ?>>
                    </div>
                </div>
                <div class="corr-field-card">
                    <div class="corr-field-card-title"><label class="mb-0" for="meeting_id"><i class="bi bi-hash me-1"></i><?= e(__('meeting.id')) ?></label></div>
                    <div class="corr-field-card-body">
                        <input type="text" class="form-control corr-meeting-ltr" name="meeting_id" id="meeting_id" dir="ltr"
                               value="<?= e((string) ($meetingData['meeting_id'] ?? '')) ?>" maxlength="80"
                               <?= $meetingCanEdit ? '' : 'readonly' ?>>
                    </div>
                </div>
                <div class="corr-field-card">
                    <div class="corr-field-card-title"><label class="mb-0" for="meeting_code"><i class="bi bi-key me-1"></i><?= e(__('meeting.code')) ?></label></div>
                    <div class="corr-field-card-body">
                        <input type="text" class="form-control corr-meeting-ltr" name="meeting_code" id="meeting_code" dir="ltr"
                               value="<?= e((string) ($meetingData['code'] ?? '')) ?>" maxlength="80"
                               <?= $meetingCanEdit ? '' : 'readonly' ?>>
                    </div>
                </div>
            </div>
        </form>
    </div>
    </div>
</div>
<?php
unset($meetingData, $meetingCanEdit, $meetingCanSend, $meetingRemoteCount, $meetingRemotePending, $meetingInvitationSent, $meetingBatchId, $meetingCorrId, $meetingCollapseId, $meetingFormId);
