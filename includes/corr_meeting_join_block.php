<?php
/**
 * زر انضمام للاجتماع في صفحة التحقق بعد الرد «عن بعد».
 * @var array $corr
 */
if (empty($corr) || !is_array($corr) || !function_exists('correspondence_is_remote_attendee')) {
    return;
}
if (!correspondence_is_remote_attendee($corr) || !function_exists('correspondence_meeting_data')) {
    return;
}
$joinMeeting = correspondence_meeting_data($corr);
$joinUrl = trim((string) ($joinMeeting['url'] ?? ''));
if ($joinUrl === '') {
    return;
}
$joinTitle = trim((string) ($joinMeeting['title'] ?? ''));
$joinWhen = trim((string) ($joinMeeting['at'] ?? ''));
$joinId = trim((string) ($joinMeeting['meeting_id'] ?? ''));
$joinCode = trim((string) ($joinMeeting['code'] ?? ''));
?>
<div class="verify-meeting mt-3">
    <p class="mb-2"><?= e(__('meeting.join_hint')) ?></p>
    <?php if ($joinTitle !== ''): ?>
        <div class="small mb-1"><?= e($joinTitle) ?></div>
    <?php endif; ?>
    <?php if ($joinWhen !== '' && !str_starts_with($joinWhen, '0000') && function_exists('format_datetime')): ?>
        <div class="small text-muted mb-1"><?= e(format_datetime($joinWhen)) ?></div>
    <?php endif; ?>
    <?php if ($joinId !== ''): ?>
        <div class="small text-muted mb-1" dir="ltr"><?= e(__('meeting.id')) ?>: <?= e($joinId) ?></div>
    <?php endif; ?>
    <?php if ($joinCode !== ''): ?>
        <div class="small text-muted mb-2" dir="ltr"><?= e(__('meeting.code')) ?>: <?= e($joinCode) ?></div>
    <?php endif; ?>
    <a class="btn verify-rsvp-choice verify-rsvp-choice--remote" href="<?= e($joinUrl) ?>" target="_blank" rel="noopener" dir="ltr">
        <i class="bi bi-camera-video me-1"></i><?= e(__('meeting.join')) ?>
    </a>
</div>
<?php
unset($joinMeeting, $joinUrl, $joinTitle, $joinWhen, $joinId, $joinCode);
