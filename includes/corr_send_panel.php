<?php

/**

 * إرسال الصادر بالبريد أو واتساب

 * يتطلب: $corr, $user, $canEdit

 */

$sendUi = dms_perm_ui('outgoing.send', $user);
if (($corr['type'] ?? '') !== 'outgoing' || empty($canEdit) || !empty($isCancelled) || $sendUi === 'hide') {
    return;
}
if (!function_exists('correspondence_intend_electronic') || !correspondence_intend_electronic($corr)) {
    return;
}
if (function_exists('outgoing_prep_is_superseded') && outgoing_prep_is_superseded($corr)) {
    return;
}
$sendBlockedTip = $sendUi === 'deny'
    ? __('perm.denied_tip')
    : ((function_exists('outgoing_can_issue') && !outgoing_can_issue($corr)) ? __('issue_appr.print_blocked_short') : '');
$sendTipOpen = $sendBlockedTip !== ''
    ? '<span class="d-block" tabindex="0" data-bs-toggle="tooltip" data-bs-placement="top" title="' . e($sendBlockedTip) . '">'
    : '';
$sendTipClose = $sendBlockedTip !== '' ? '</span>' : '';

$sendEmail = dms_corr_email_address($corr);

$sendMobile = dms_corr_mobile_number($corr);

$honored = dms_corr_honored_name($corr);

$last = dms_corr_last_deliveries((int) $corr['id']);

$emailReady = function_exists('dms_mail_ready')

    ? dms_mail_ready((int) ($corr['department_id'] ?? 0) ?: null)

    : false;

$waReady = (dms_whatsapp_readiness()['state'] ?? '') === 'ready';

$entityId = (int) ($corr['entity_id'] ?? 0);



$sendStatusTone = static function (?array $row): string {

    if (!$row) {

        return 'pending';

    }

    return match ((string) ($row['status'] ?? '')) {

        'sent' => 'ok',

        'failed' => 'fail',

        default => 'muted',

    };

};

$emailTone = $sendStatusTone($last['email'] ?? null);

$waTone = $sendStatusTone($last['whatsapp'] ?? null);

$preferEmail = function_exists('correspondence_intend_electronic')
    && correspondence_intend_electronic($corr);

?>

<div class="card-panel mb-3 no-print corr-send-panel<?= $preferEmail ? ' is-electronic' : '' ?>">

    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">

        <span><i class="bi bi-send me-1"></i><?= e(__('send.title')) ?></span>

        <?php if ($entityId && ($canEditInCycle ?? true)): ?>

            <a class="small corr-send-edit-entity" href="<?= e(base_url('admin/entities_edit.php?id=' . $entityId)) ?>">

                <i class="bi bi-pencil-square me-1"></i><?= e(__('send.edit_entity')) ?>

            </a>

        <?php endif; ?>

    </div>

    <div class="card-body corr-send-body">

        <?php if ($preferEmail): ?>
            <div class="alert alert-info py-2 px-3 small mb-3"><?= e(__('send.electronic_hint')) ?></div>
            <p class="small text-muted mb-3"><?= e(__('send.electronic_status_flow')) ?></p>
        <?php endif; ?>

        <div class="corr-send-party">

            <span class="corr-meta-label"><?= e(__('entities.letter_preview')) ?></span>

            <div class="corr-send-party-name"><?= e($honored !== '' ? $honored : ($corr['party_name'] ?? __('common.none'))) ?></div>

        </div>



        <div class="corr-send-grid">

            <article class="corr-send-channel tone-email<?= $preferEmail ? ' is-preferred' : '' ?>">

                <div class="corr-send-channel-head text-center">

                    <span class="corr-send-channel-icon"
                          data-bs-toggle="tooltip"
                          data-bs-placement="top"
                          title="<?= e(__('entities.email')) ?>"
                          aria-label="<?= e(__('entities.email')) ?>"><i class="bi bi-envelope-at"></i></span>

                    <div class="corr-send-channel-value<?= $sendEmail === '' ? ' is-missing' : '' ?>" dir="ltr">

                        <?= e($sendEmail !== '' ? $sendEmail : __('send.missing_email')) ?>

                    </div>

                </div>

                <div class="corr-send-channel-body">

                    <span class="corr-send-status is-<?= e($emailTone) ?>"><?= e(dms_delivery_status_label($last['email'] ?? null)) ?></span>

                    <?php if (!empty($last['email']['error_message']) && ($last['email']['status'] ?? '') !== 'sent'): ?>

                        <div class="corr-send-error"><?= e((string) $last['email']['error_message']) ?></div>

                    <?php endif; ?>

                    <form method="post" action="<?= e(base_url('outgoing/send.php')) ?>" data-confirm="<?= e(__('send.confirm_email')) ?>" class="corr-send-form">

                        <?= csrf_field() ?>

                        <input type="hidden" name="correspondence_id" value="<?= (int) $corr['id'] ?>">

                        <input type="hidden" name="channel" value="email">

                        <?= $sendTipOpen ?><button class="btn btn-kff w-100 corr-send-btn" type="submit" <?= ($sendBlockedTip === '' && $sendEmail !== '' && $emailReady) ? '' : 'disabled' ?>>

                            <i class="bi bi-envelope me-1"></i><?= e(__('send.do_email')) ?>

                        </button><?= $sendTipClose ?>

                    </form>

                    <?php if (!$emailReady): ?>

                        <div class="corr-send-note"><?= e(__('send.need_email_settings')) ?></div>

                    <?php elseif ($sendEmail === ''): ?>

                        <div class="corr-send-note"><?= e(__('send.need_entity_email')) ?></div>

                    <?php endif; ?>

                </div>

            </article>



            <article class="corr-send-channel tone-wa<?= $preferEmail ? ' is-secondary' : '' ?>">

                <div class="corr-send-channel-head text-center">

                    <span class="corr-send-channel-icon"
                          data-bs-toggle="tooltip"
                          data-bs-placement="top"
                          title="<?= e(__('entities.mobile')) ?>"
                          aria-label="<?= e(__('entities.mobile')) ?>"><i class="bi bi-whatsapp"></i></span>

                    <div class="corr-send-channel-value<?= $sendMobile === '' ? ' is-missing' : '' ?>" dir="ltr">

                        <?= e($sendMobile !== '' ? ('+' . $sendMobile) : __('send.missing_mobile')) ?>

                    </div>

                </div>

                <div class="corr-send-channel-body">

                    <span class="corr-send-status is-<?= e($waTone) ?>"><?= e(dms_delivery_status_label($last['whatsapp'] ?? null)) ?></span>

                    <?php if (!empty($last['whatsapp']['error_message']) && ($last['whatsapp']['status'] ?? '') !== 'sent'): ?>

                        <div class="corr-send-error"><?= e((string) $last['whatsapp']['error_message']) ?></div>

                    <?php endif; ?>

                    <form method="post" action="<?= e(base_url('outgoing/send.php')) ?>" data-confirm="<?= e(__('send.confirm_wa')) ?>" class="corr-send-form">

                        <?= csrf_field() ?>

                        <input type="hidden" name="correspondence_id" value="<?= (int) $corr['id'] ?>">

                        <input type="hidden" name="channel" value="whatsapp">

                        <?= $sendTipOpen ?><button class="btn btn-success w-100 corr-send-btn" type="submit" <?= ($sendBlockedTip === '' && $sendMobile !== '' && $waReady) ? '' : 'disabled' ?>>

                            <i class="bi bi-whatsapp me-1"></i><?= e(__('send.do_wa')) ?>

                        </button><?= $sendTipClose ?>

                    </form>

                    <?php if (!$waReady): ?>

                        <div class="corr-send-note"><?= e(__('send.need_wa_settings')) ?></div>

                    <?php elseif ($sendMobile === ''): ?>

                        <div class="corr-send-note"><?= e(__('send.need_entity_mobile')) ?></div>

                    <?php endif; ?>

                </div>

            </article>

        </div>



        <p class="corr-send-foot-hint mb-0"><?= e(__('send.delivery_hint')) ?></p>

    </div>

</div>


