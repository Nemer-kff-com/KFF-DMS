<?php
/** @var list<array{kind:string,label:string,icon:string,href?:string,class?:string,target?:string,modal?:string,disabled?:string}> */
$secondaryActions = [];
if (!empty($corr['batch_id']) && $isOutgoing) {
    $batchUi = dms_perm_ui('outgoing.bulk', $user);
    $canOpenBatch = $batchUi === 'allow'
        || (function_exists('user_can_view_outgoing_batch_pages') && user_can_view_outgoing_batch_pages($user))
        || (function_exists('outgoing_user_can_access_for_approval') && outgoing_user_can_access_for_approval($user, $corr));
    if ($canOpenBatch || $batchUi === 'deny') {
        $secondaryActions[] = [
            'kind' => 'link',
            'label' => __('corr.batch_view'),
            'icon' => 'bi-collection',
            'href' => base_url('outgoing/batch.php?id=' . (int) $corr['batch_id']),
            'class' => 'btn-outline-secondary',
            'disabled' => (!$canOpenBatch && $batchUi === 'deny') ? dms_perm_denied_tip(__('corr.batch_view')) : '',
        ];
    }
}
if (!$isInternal && (count($relatedGroup) > 1 || !empty($corr['linked_id']) || !empty($replyOut))) {
    $secondaryActions[] = [
        'kind' => 'link',
        'label' => __('corr.related_open'),
        'icon' => 'bi-link-45deg',
        'href' => $relatedUrl,
        'class' => 'btn-outline-secondary',
    ];
}
if ($isIncoming && $canEdit) {
    if ($replyOut) {
        $secondaryActions[] = [
            'kind' => 'link',
            'label' => __('corr.view_reply'),
            'icon' => 'bi-reply',
            'href' => base_url('outgoing/view.php?id=' . (int) $replyOut['id']),
            'class' => 'btn-outline-secondary',
        ];
    } elseif (($replyUi = dms_perm_ui('outgoing.create', $user)) !== 'hide') {
        $secondaryActions[] = [
            'kind' => 'link',
            'label' => __('corr.create_reply'),
            'icon' => 'bi-send',
            'href' => base_url('outgoing/create.php?link=' . (int) $corr['id']),
            'class' => 'btn-outline-secondary',
            'disabled' => $replyUi === 'deny'
                ? __('perm.denied_tip')
                : ($inHeld ? __('in_issue_appr.reply_blocked') : ''),
        ];
    }
}
if ($briefEnabled && $briefSheet) {
    $secondaryActions[] = [
        'kind' => 'link',
        'label' => __('brief.open'),
        'icon' => 'bi-file-earmark-ruled',
        'href' => base_url('correspondence/brief.php?id=' . (int) $corr['id']),
        'class' => 'btn-outline-secondary',
    ];
} elseif ($briefEnabled && $canMutate) {
    $secondaryActions[] = [
        'kind' => 'link',
        'label' => __('brief.create'),
        'icon' => 'bi-file-earmark-ruled',
        'href' => base_url('correspondence/brief.php?id=' . (int) $corr['id']),
        'class' => 'btn-outline-secondary',
    ];
}
if ($showCloseOpen) {
    $secondaryActions[] = [
        'kind' => 'link',
        'label' => __('close.open'),
        'icon' => 'bi-lock',
        'href' => base_url('correspondence/close.php?id=' . (int) $corr['id']),
        'class' => 'btn-outline-warning',
    ];
    if ($canReopen) {
        $secondaryActions[] = [
            'kind' => 'modal',
            'label' => __('reopen.action'),
            'icon' => 'bi-unlock',
            'modal' => '#reopenCorrModal',
            'class' => 'btn-outline-success',
        ];
    }
} elseif ($showCloseAction && $closeUi === 'deny') {
    $secondaryActions[] = [
        'kind' => 'link',
        'label' => __('close.action_btn'),
        'icon' => 'bi-lock',
        'href' => '#',
        'class' => 'btn-outline-warning',
        'disabled' => __('perm.denied_tip'),
    ];
} elseif ($showCloseAction && $canClosePerm && ($canCloseDirect || !$closeReq)) {
    $secondaryActions[] = [
        'kind' => 'link',
        'label' => $canCloseDirect ? __('close.action_btn') : __('close_req.action'),
        'icon' => $canCloseDirect ? 'bi-lock' : 'bi-send-check',
        'href' => base_url('correspondence/close.php?id=' . (int) $corr['id']),
        'class' => 'btn-outline-warning',
        'disabled' => $apprHeld ? $apprHeldTip : '',
    ];
} elseif ($showCloseAction && $canClosePerm) {
    $secondaryActions[] = [
        'kind' => 'link',
        'label' => __('close_req.action'),
        'icon' => 'bi-hourglass-split',
        'href' => '#',
        'class' => 'btn-outline-warning',
        'disabled' => str_replace(':dept', $closeDeciderName, __('close_req.pending_intro')),
    ];
}
if ($showCancel) {
    $secondaryActions[] = [
        'kind' => 'modal',
        'label' => __('cancel.action'),
        'icon' => 'bi-x-octagon',
        'modal' => '#cancelOutgoingModal',
        'class' => 'btn-outline-danger',
        'disabled' => $cancelUi === 'deny' ? __('perm.denied_tip') : '',
    ];
}
if ($ccViewerOnly) {
    $secondaryActions = [];
}
$hasSecondaryActions = $secondaryActions !== [];
?>
<div class="page-header no-print corr-view-header">
    <div class="corr-view-title-wrap">
        <div class="corr-view-badges">
            <?php if (is_overdue($corr['reply_due'], $corr['status'])): ?>
                <span class="badge badge-overdue"><?= e(__('corr.overdue_badge')) ?></span>
            <?php endif; ?>
            <?php if ($isOutgoing && !$isCancelled): ?>
                <?php if ($isElectronic): ?>
                    <span class="badge text-bg-info"><?= e(__('corr.electronic_badge')) ?></span>
                    <?php
                    $headerEmail = function_exists('dms_corr_email_address') ? dms_corr_email_address($corr) : '';
                    if ($headerEmail !== ''):
                    ?>
                        <span class="small text-muted" dir="ltr"><?= e($headerEmail) ?></span>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if ($hasReceipt): ?>
                    <span class="badge text-bg-success"><?= e(__('receipt.recorded')) ?></span>
                <?php elseif (!$isElectronic && !empty($canIssueActions)): ?>
                    <span class="badge text-bg-secondary"><?= e(__('receipt.pending')) ?></span>
                <?php endif; ?>
                <?php if ($rsvpRequired): ?>
                    <?= rsvp_response_badge($corr['rsvp_response'] ?? null) ?>
                <?php endif; ?>
            <?php endif; ?>
            <?php if ($briefSheet): ?>
                <span class="badge <?= !empty($briefSheet['decided']) ? 'text-bg-success' : 'text-bg-secondary' ?>">
                    <?= e(!empty($briefSheet['decided']) ? __('brief.status_decided') : __('brief.status_draft')) ?>
                </span>
            <?php endif; ?>
        </div>
        <h1 class="page-title">
            <span<?= !empty($showOfficialRef) ? ' dir="ltr"' : '' ?> id="corr-ref-number"><?= e(function_exists('correspondence_ref_caption_safe') ? correspondence_ref_caption_safe($corr) : format_ref_caption((string) ($corr['type'] ?? ''), $corr['ref_number'] ?? '')) ?></span>
            — <?= e(type_label($corr['type'])) ?>
            <span class="corr-title-status"><?= status_badge($corr['status'], $cancelKind !== '' ? $cancelKind : null) ?></span>
        </h1>
        <p class="page-sub"><?= e($corr['subject']) ?></p>
        <?php
        $apprHeadline = '';
        $apprHeadlineClass = 'text-bg-warning';
        $apprHeadlineSub = '';
        if ($isOutgoing && !$isCancelled && function_exists('outgoing_prep_is_open') && outgoing_prep_is_open($corr)) {
            $apprHeadline = outgoing_prep_list_label($corr);
            $apprHeadlineClass = outgoing_prep_is_pending_signer($corr) ? 'text-bg-warning' : 'text-bg-secondary';
        } elseif ($isOutgoing && !$isCancelled && function_exists('outgoing_prep_is_superseded') && outgoing_prep_is_superseded($corr)) {
            $apprHeadline = outgoing_prep_list_label($corr);
            $apprHeadlineClass = 'text-bg-secondary';
        } elseif ($isOutgoing && !$isCancelled && function_exists('outgoing_approval_status_of')) {
            $apprSt = outgoing_approval_status_of($corr);
            if ($apprSt !== '' || (function_exists('outgoing_approval_is_configured') && outgoing_approval_is_configured())) {
                if ($apprSt === 'pending_review') {
                    $apprHeadline = __('issue_review.header_pending');
                    $apprHeadlineClass = 'text-bg-warning';
                } elseif ($apprSt === 'returned') {
                    $apprHeadline = __('issue_review.header_returned');
                    $apprHeadlineClass = 'text-bg-info';
                } elseif ($apprSt === 'pending_copy') {
                    $apprHeadline = __('issue_appr.header_pending_copy');
                    $apprHeadlineClass = 'text-bg-warning';
                } elseif ($apprSt === 'pending_sg' || $apprSt === 'pending_deputy' || $apprSt === 'pending_alternate') {
                    $apprHeadline = function_exists('outgoing_appr_text')
                        ? outgoing_appr_text('issue_appr.header_' . $apprSt)
                        : outgoing_approval_label($apprSt);
                    $apprHeadlineClass = 'text-bg-warning';
                } elseif ($apprSt === 'approved' || $apprSt === 'with_comms') {
                    $apprHeadline = __('issue_appr.status_comms');
                    $apprHeadlineClass = 'text-bg-success';
                } elseif ($apprSt === 'rejected') {
                    $apprHeadline = __('issue_appr.header_rejected');
                    $apprHeadlineClass = 'text-bg-danger';
                }
                if (($apprSt === 'approved' || $apprSt === 'rejected') && function_exists('user_display_name')) {
                    $decidedName = '';
                    if (isset($corr['issue_approval_decided_by_name']) || isset($corr['issue_approval_decided_by_name_en'])) {
                        $decidedName = user_display_name($corr, 'issue_approval_decided_by_name', 'issue_approval_decided_by_name_en');
                    }
                    if ($decidedName === '') {
                        $decidedId = (int) ($corr['issue_approval_decided_by'] ?? 0);
                        if ($decidedId > 0) {
                            try {
                                $du = db()->prepare('SELECT name_ar, name_en FROM `tblusers` WHERE id = ? LIMIT 1');
                                $du->execute([$decidedId]);
                                $duRow = $du->fetch();
                                if ($duRow) {
                                    $decidedName = user_display_name($duRow);
                                }
                            } catch (Throwable $e) {
                                $decidedName = '';
                            }
                        }
                    }
                    if ($decidedName !== '') {
                        $apprHeadlineSub = $decidedName;
                    }
                }
                if ($apprSt === 'approved' && $apprHeadlineSub !== '') {
                    $apprHeadlineSub = str_replace(':name', $apprHeadlineSub, __('issue_appr.header_approved_by'));
                }
            }
        } elseif ($isIncoming && !$isCancelled && function_exists('outgoing_approval_status_of')) {
            $apprSt = outgoing_approval_status_of($corr);
            if ($apprSt !== '') {
                [$apprHeadline, $apprHeadlineClass] = appr_with('incoming', static function () use ($apprSt): array {
                    return match ($apprSt) {
                        'pending_desk' => [__('in_issue_appr.desk_status'), 'text-bg-secondary'],
                        'pending_review' => [__('issue_review.header_pending'), 'text-bg-warning'],
                        'returned' => [__('issue_review.header_returned'), 'text-bg-info'],
                        'pending_sg', 'pending_deputy', 'pending_alternate' => [
                            outgoing_appr_text('issue_appr.status_pending_' . substr($apprSt, strlen('pending_'))),
                            'text-bg-warning',
                        ],
                        'approved' => [__('in_issue_appr.status_approved'), 'text-bg-success'],
                        'filed' => [__('in_issue_appr.status_filed'), 'text-bg-secondary'],
                        'rejected' => [__('issue_appr.status_rejected'), 'text-bg-danger'],
                        default => [outgoing_approval_label($apprSt), 'text-bg-warning'],
                    };
                });
                if (in_array($apprSt, ['approved', 'filed', 'rejected'], true) && function_exists('outgoing_user_name_by_id')) {
                    $decidedName = outgoing_user_name_by_id((int) ($corr['issue_approval_decided_by'] ?? 0));
                    if ($decidedName !== '') {
                        $apprHeadlineSub = $apprSt === 'approved'
                            ? str_replace(':name', $decidedName, __('issue_appr.header_approved_by'))
                            : $decidedName;
                    }
                }
            }
        }
        ?>
        <?php if ($apprHeadline !== ''): ?>
            <p class="corr-appr-headline mb-0 mt-2">
                <span class="badge <?= e($apprHeadlineClass) ?>"><?= e($apprHeadline) ?></span>
                <?php if ($apprHeadlineSub !== ''): ?>
                    <span class="small text-muted ms-2"><?= e($apprHeadlineSub) ?></span>
                <?php endif; ?>
            </p>
        <?php endif; ?>
    </div>
    <div class="page-header-actions corr-view-actions">
        <div class="corr-actions-group corr-actions-primary">
            <a class="btn btn-outline-secondary corr-action-primary" href="<?= e(base_url($ccViewerOnly ? 'cc/index.php' : $typeFolder . '/index.php')) ?>">
                <i class="bi <?= dms_is_rtl() ? 'bi-arrow-right' : 'bi-arrow-left' ?> me-1"></i><?= e(__('action.back')) ?>
            </a>

            <?php
            $corrPrintType = (string) ($corr['type'] ?? '');
            $canPrintForm = function_exists('corr_user_can_print') && corr_user_can_print($corrPrintType, 'form', $user);
            $canPrintEnvelope = function_exists('corr_user_can_print') && corr_user_can_print($corrPrintType, 'envelope', $user);
            $canPrintThermal = function_exists('corr_user_can_print') && corr_user_can_print($corrPrintType, 'thermal', $user);
            $showPrintEnvelope = $canPrintEnvelope && !$isCancelled && !$isElectronic;
            $showPrintThermal = $canPrintThermal && !$isCancelled && !$isElectronic;
            $showPrintMail = !$isCancelled && mail_draft_user_allowed($user);
            $showPrintClose = !$isCancelled && correspondence_is_closed($corr) && trim((string) ($corr['close_action'] ?? '')) !== '';
            $showPrintMenu = !$ccViewerOnly && ($hasLetterPrint || $canPrintForm || $showPrintEnvelope || $showPrintThermal || $showPrintMail || $showPrintClose);
            ?>
            <?php if ($showPrintMenu): ?>
            <div class="corr-actions-print dropdown">
                <button type="button" class="btn btn-outline-secondary dropdown-toggle corr-action-primary" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-printer me-1"></i><?= e(__('corr.print_menu')) ?>
                </button>
                <?php
                $printItem = static function (string $href, string $icon, string $label, string $blockedTip): void {
                    if ($blockedTip !== '') {
                        echo '<span class="d-block" tabindex="0" data-bs-toggle="tooltip" data-bs-placement="top" title="' . e($blockedTip) . '">'
                            . '<span class="dropdown-item disabled" aria-disabled="true"><i class="bi ' . e($icon) . ' me-2"></i>' . e($label) . '</span></span>';
                        return;
                    }
                    echo '<a class="dropdown-item" href="' . e($href) . '" target="_blank" rel="noopener"><i class="bi ' . e($icon) . ' me-2"></i>' . e($label) . '</a>';
                };
                $printBlockedTip = $apprHeld ? $apprHeldTip : '';
                $thermalBlockedTip = $isIncoming ? '' : $printBlockedTip;
                if ($isOutgoing && function_exists('outgoing_can_print_thermal') && !outgoing_can_print_thermal($corr)) {
                    $thermalBlockedTip = function_exists('outgoing_thermal_wait_message')
                        ? outgoing_thermal_wait_message(true)
                        : __('out_prep.thermal_blocked_short');
                }
                ?>
                <ul class="dropdown-menu dropdown-menu-end">
                    <?php if ($hasLetterPrint): ?>
                    <li><?php $printItem($printLetterUrl, 'bi-file-text', __('bulk.print_letter'), $printBlockedTip); ?></li>
                    <?php endif; ?>
                    <?php if ($canPrintForm): ?>
                    <li><?php $printItem($printFormUrl, $printFormIcon, $printFormLabel, $printBlockedTip); ?></li>
                    <?php endif; ?>
                    <?php if ($showPrintEnvelope): ?>
                    <li><?php $printItem(base_url($typeFolder . '/label.php?id=' . (int) $corr['id']), 'bi-sticky', __('label.print'), $printBlockedTip); ?></li>
                    <?php endif; ?>
                    <?php if ($showPrintThermal): ?>
                    <li><?php $printItem($thermalPrintUrl, 'bi-upc', __('thermal.print'), $thermalBlockedTip); ?></li>
                    <?php endif; ?>
                    <?php if ($showPrintMail): ?>
                    <?php $mailDraftBlocked = !user_can_view_corr_files($corr, $user) ? __('conf.file_locked') : mail_draft_block_reason($corr); ?>
                    <li class="js-mail-draft-item d-none d-lg-block"><hr class="dropdown-divider"></li>
                    <?php if ($mailDraftBlocked !== ''): ?>
                    <li class="js-mail-draft-item d-none d-lg-block"><?php $printItem('#', 'bi-envelope-paper', __('mail_draft.action'), $mailDraftBlocked); ?></li>
                    <?php else: ?>
                    <?php $mailDraftUrl = base_url('correspondence/mail_draft.php?id=' . (int) $corr['id']); ?>
                    <li class="js-mail-draft-item d-none d-lg-block">
                        <a class="dropdown-item js-mail-draft" href="<?= e($mailDraftUrl) ?>"
                           title="<?= e(__('mail_draft.action_tip')) ?>" data-bs-toggle="tooltip" data-bs-placement="top">
                            <i class="bi bi-envelope-paper me-2"></i><?= e(__('mail_draft.action')) ?>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php endif; ?>
                    <?php if ($showPrintClose): ?>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item" href="<?= e(base_url('correspondence/close_print.php?id='.(int)$corr['id'])) ?>" target="_blank" rel="noopener">
                            <i class="bi bi-printer me-2"></i><?= e(__('close.print')) ?>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
            <script>
            /* ترتيب «موجهة إلى / من» في مسودة البريد يتبع آخر اختيار في صفحة الطباعة (نفس مفتاح التخزين) */
            (function () {
                /* ملف .eml لا يُفتح كرسالة على الجوال/اللوحي (ومنها iPad الذي يعرّف نفسه Mac) */
                var ua = navigator.userAgent || '';
                var mobile = (navigator.userAgentData && navigator.userAgentData.mobile)
                    || /Android|iPhone|iPad|iPod|Mobile|Tablet/i.test(ua)
                    || (/Macintosh/.test(ua) && navigator.maxTouchPoints > 1);
                if (mobile) {
                    document.querySelectorAll('.js-mail-draft-item').forEach(function (li) { li.remove(); });
                    return;
                }
                document.querySelectorAll('.js-mail-draft').forEach(function (a) {
                    a.addEventListener('click', function () {
                        var dir = 'to';
                        try { dir = localStorage.getItem('dms.print.dirOrder') === 'from' ? 'from' : 'to'; } catch (e) { /* تخزين محلي معطّل */ }
                        a.href = a.href.split('&dir=')[0] + '&dir=' + dir;
                    });
                });
            })();
            </script>
            <?php endif; ?>

            <?php if ($showEdit && $editLockTip !== ''): ?>
                <span class="d-inline-block" tabindex="0" data-bs-toggle="tooltip" data-bs-placement="top" title="<?= e($editLockTip) ?>">
                    <button type="button" class="btn btn-kff corr-action-primary" disabled>
                        <i class="bi bi-pencil-square me-1"></i><?= e(__('action.edit')) ?>
                    </button>
                </span>
            <?php elseif ($showEdit): ?>
                <a class="btn btn-kff corr-action-primary" href="<?= e(base_url($typeFolder . '/edit.php?id='.(int)$corr['id'])) ?>">
                    <i class="bi bi-pencil-square me-1"></i><?= e(__('action.edit')) ?>
                </a>
            <?php endif; ?>

            <?php if ($hasSecondaryActions): ?>
            <div class="corr-actions-more dropdown d-md-none">
                <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" aria-label="<?= e(__('corr.more_actions')) ?>">
                    <i class="bi bi-three-dots me-1"></i><?= e(__('corr.more_actions')) ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <?php foreach ($secondaryActions as $action): ?>
                        <li>
                            <?php if (($action['disabled'] ?? '') !== ''): ?>
                                <span class="d-block" tabindex="0" data-bs-toggle="tooltip" data-bs-placement="top" title="<?= e((string) $action['disabled']) ?>">
                                    <span class="dropdown-item disabled" aria-disabled="true">
                                        <i class="bi <?= e((string) $action['icon']) ?> me-2"></i><?= e((string) $action['label']) ?>
                                    </span>
                                </span>
                            <?php elseif (($action['kind'] ?? '') === 'modal'): ?>
                                <button type="button" class="dropdown-item<?= str_contains((string) ($action['class'] ?? ''), 'danger') ? ' text-danger' : (str_contains((string) ($action['class'] ?? ''), 'success') ? ' text-success' : '') ?>" data-bs-toggle="modal" data-bs-target="<?= e((string) ($action['modal'] ?? '')) ?>">
                                    <i class="bi <?= e((string) $action['icon']) ?> me-2"></i><?= e((string) $action['label']) ?>
                                </button>
                            <?php else: ?>
                                <a class="dropdown-item<?= str_contains((string) ($action['class'] ?? ''), 'warning') ? ' text-warning' : (str_contains((string) ($action['class'] ?? ''), 'danger') ? ' text-danger' : '') ?>"
                                   href="<?= e((string) ($action['href'] ?? '#')) ?>">
                                    <i class="bi <?= e((string) $action['icon']) ?> me-2"></i><?= e((string) $action['label']) ?>
                                </a>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($hasSecondaryActions): ?>
        <div class="corr-actions-group corr-actions-secondary corr-actions-desktop-only" role="group" aria-label="<?= e(__('corr.more_actions')) ?>">
            <?php foreach ($secondaryActions as $action): ?>
                <?php
                $btnClass = trim((string) ($action['class'] ?? 'btn-outline-secondary'));
                ?>
                <?php if (($action['disabled'] ?? '') !== ''): ?>
                    <span class="d-inline-block" tabindex="0" data-bs-toggle="tooltip" data-bs-placement="top" title="<?= e((string) $action['disabled']) ?>">
                        <span class="btn <?= e($btnClass) ?> corr-action-secondary disabled" aria-disabled="true">
                            <i class="bi <?= e((string) $action['icon']) ?> me-1"></i><?= e((string) $action['label']) ?>
                        </span>
                    </span>
                <?php elseif (($action['kind'] ?? '') === 'modal'): ?>
                    <button type="button" class="btn <?= e($btnClass) ?> corr-action-secondary" data-bs-toggle="modal" data-bs-target="<?= e((string) ($action['modal'] ?? '')) ?>">
                        <i class="bi <?= e((string) $action['icon']) ?> me-1"></i><?= e((string) $action['label']) ?>
                    </button>
                <?php else: ?>
                    <a class="btn <?= e($btnClass) ?> corr-action-secondary" href="<?= e((string) ($action['href'] ?? '#')) ?>">
                        <i class="bi <?= e((string) $action['icon']) ?> me-1"></i><?= e((string) $action['label']) ?>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

