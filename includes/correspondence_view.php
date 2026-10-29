<?php
/**
 * صفحة عرض معاملة (تُستدعى من incoming/view.php أو outgoing/view.php)
 * يتطلب: $corr, $documents, $referrals, $user, $typeFolder
 */
$ccViewerOnly = !empty($ccViewerOnly);
$canEdit = !$ccViewerOnly && can_edit_correspondence($user);
$isCancelled = correspondence_is_cancelled($corr);
$canMutate = $canEdit && !$isCancelled;
$cancelKind = (string) ($corr['cancel_kind'] ?? '');
$departments = departments_list();
$isOutgoing = ($corr['type'] ?? '') === 'outgoing';
$showOfficialRef = !$isOutgoing
    || !function_exists('outgoing_show_official_ref')
    || outgoing_show_official_ref($corr);
$showThermalSide = !empty($showOfficialRef)
    && function_exists('corr_user_can_view_thermal')
    && corr_user_can_view_thermal((string) ($corr['type'] ?? ''), $user);
$qrContent = $showThermalSide ? dms_corr_qr_content($corr) : '';
$isInternal = ($corr['type'] ?? '') === 'internal';
$isIncoming = !$isOutgoing && !$isInternal;
$canIssueActions = !$isOutgoing
    || !function_exists('outgoing_can_issue')
    || outgoing_can_issue($corr);
$issueBlockPost = ($isOutgoing && !$canIssueActions && function_exists('outgoing_approval_block_post_issue'))
    ? outgoing_approval_block_post_issue($corr)
    : '';
$canMutateIssued = $canMutate && $canIssueActions;
$letterLang = $isOutgoing && function_exists('correspondence_letter_channel_lang')
    ? correspondence_letter_channel_lang($corr)
    : 'ar';
$letterText = $isOutgoing && function_exists('correspondence_letter_text')
    ? correspondence_letter_text($corr)
    : '';
$rsvpRequired = $isOutgoing && function_exists('correspondence_rsvp_required')
    && correspondence_rsvp_required($corr);
$hasReceipt = $isOutgoing && correspondence_has_receipt($corr);
$replyOut = $isIncoming ? correspondence_reply_outgoing($corr) : null;
$relatedGroup = function_exists('correspondence_link_group')
    ? correspondence_link_group((int) ($corr['id'] ?? 0), $user)
    : [];
$relatedUrl = base_url('correspondence/related.php?id=' . (int) ($corr['id'] ?? 0));
$briefEnabled = ($isIncoming || $isInternal) && function_exists('get_correspondence_brief');
$briefSheet = $briefEnabled ? get_correspondence_brief((int) $corr['id']) : null;
$quickComments = [
    __('comment.please_advise'),
    __('comment.reviewed'),
    __('comment.approved'),
    __('comment.needs_edit'),
];
$thermalPrintUrl = base_url('correspondence/thermal_label.php?id=' . (int) $corr['id']);
$isElectronic = $isOutgoing && function_exists('correspondence_intend_electronic')
    && correspondence_intend_electronic($corr);
$printFormLabel = $isIncoming
    ? __('corr.print_receipt_form')
    : __('bulk.print_corr');
$printFormIcon = 'bi-clipboard-check';
$batchId = (int) ($corr['batch_id'] ?? 0);
$hasLetterPrint = $isOutgoing
    && function_exists('correspondence_letter_text')
    && correspondence_letter_text($corr) !== '';
$printLetterUrl = base_url($typeFolder . '/print.php?id=' . (int) $corr['id']);
$printFormUrl = $hasLetterPrint
    ? base_url($typeFolder . '/print.php?id=' . (int) $corr['id'] . '&mode=form')
    : $printLetterUrl;
$inHeld = !$isOutgoing && function_exists('corr_approval_is_held') && corr_approval_is_held($corr);
$apprHeld = $inHeld || ($isOutgoing && !$canIssueActions && !$isCancelled);
$apprHeldTip = $isOutgoing
    ? ((function_exists('outgoing_prep_is_open') && outgoing_prep_is_open($corr))
        ? __('out_prep.print_blocked_short')
        : __('issue_appr.print_blocked_short'))
    : corr_held_text($corr, 'print_blocked_short');
$showCloseOpen = !$isCancelled && correspondence_is_closed($corr);
$canReopen = $showCloseOpen && !$ccViewerOnly && correspondence_user_can_reopen($user, $corr);
$closedLockTip = $showCloseOpen ? __('reopen.use_reopen') : '';
$showCloseAction = $canMutate && !correspondence_is_closed($corr);
$closeReq = ($showCloseAction && close_request_applies($corr)) ? close_request_pending((int) $corr['id']) : null;
$closeUi = dms_perm_ui('correspondence.close', $user);
$canClosePerm = $closeUi === 'allow';
$canCloseDirect = !$showCloseAction || ($canClosePerm && close_user_can_close_direct($user, $corr));
$closeDeciderName = ($showCloseAction && close_request_applies($corr)) ? close_department_name(close_decider_department_id($corr)) : '';
$canWithdrawCloseReq = $closeReq && ((int) $closeReq['requested_by'] === (int) ($user['id'] ?? 0) || ($user['role'] ?? '') === 'admin');
$canEditInCycle = !function_exists('corr_user_can_edit_in_cycle') || corr_user_can_edit_in_cycle($user, $corr);
$cancelUi = dms_perm_ui('outgoing.cancel', $user);
$showCancel = $isOutgoing && $canEdit && !$isCancelled && $canEditInCycle && $cancelUi !== 'hide';
$canEditContent = $canMutate && $canEditInCycle;
$editUi = dms_perm_ui(($isOutgoing ? 'outgoing' : 'incoming') . '.edit', $user);
$showEdit = $canEditContent && !$isInternal && $editUi !== 'hide';
$uploadUi = dms_perm_ui('documents.upload', $user);
$deleteDocUi = dms_perm_ui('documents.delete', $user);
$commentUi = dms_perm_ui('comments.add', $user);
$referUi = dms_perm_ui('referrals.manage', $user);
$quickStatusUi = dms_perm_ui(($isInternal ? 'internal' : 'incoming') . '.edit', $user);
$canUploadDocs = $canEditContent && $uploadUi === 'allow';
$canDeletePreReview = $isOutgoing
    && $canEditContent
    && $uploadUi === 'allow'
    && function_exists('outgoing_approval_status_of')
    && in_array(outgoing_approval_status_of($corr), ['', 'returned', 'rejected'], true);
$canDeleteDocs = ($canEditContent && $deleteDocUi === 'allow') || $canDeletePreReview;
$canAddComment = $canEdit && $commentUi === 'allow';
$canRefer = $canMutate && $referUi === 'allow';
$canQuickStatus = $canMutate && $quickStatusUi === 'allow';
$uploadDenied = $canEditContent && $uploadUi === 'deny';
$deleteDocDenied = $canEditContent && $deleteDocUi === 'deny';
$commentDenied = $canEdit && $commentUi === 'deny';
$referDenied = $canMutate && $referUi === 'deny';
$quickStatusDenied = $canMutate && $quickStatusUi === 'deny';
$editLockTip = '';
if ($showEdit && $isIncoming && function_exists('incoming_user_can_edit_registration')
    && !incoming_user_can_edit_registration($user, $corr)) {
    $uid = (int) ($user['id'] ?? 0);
    $mayEditWhenOpen = incoming_registry_role($user) || ($uid > 0 && $uid === (int) ($corr['created_by'] ?? 0));
    $showEdit = $showCloseOpen && $mayEditWhenOpen;
    $editLockTip = $showEdit ? __('in_edit.closed') : '';
}
if ($showEdit && $editUi === 'deny') {
    $editLockTip = dms_perm_denied_tip(__('action.edit'));
}

require __DIR__ . '/corr_view_header.php';
$atDesk = $isIncoming && !$isCancelled && function_exists('incoming_is_at_desk') && incoming_is_at_desk($corr);
$canReleaseDesk = $atDesk && function_exists('incoming_user_can_release_desk') && incoming_user_can_release_desk($user, $corr);
if ($atDesk):
    $deskDest = function_exists('incoming_desk_destination') ? incoming_desk_destination($corr) : 'none';
    $deskHintKey = match ($deskDest) {
        'review' => 'in_issue_appr.desk_hint_review',
        'approval' => 'in_issue_appr.desk_hint_approval',
        default => 'in_issue_appr.desk_hint_direct',
    };
    $deskHasFile = !empty($documents);
?>
<div class="alert alert-info py-2 small no-print d-flex flex-wrap align-items-center justify-content-between gap-2">
    <span>
        <i class="bi bi-hourglass-split me-1"></i><?= e(__($deskHintKey)) ?>
        <span class="d-block mt-1"><?= e(__($deskHasFile ? 'in_issue_appr.desk_has_file' : 'in_issue_appr.desk_no_file')) ?></span>
    </span>
    <span class="d-flex flex-wrap gap-2">
        <?php if ($canReleaseDesk): ?>
        <form method="post" action="<?= e(base_url('incoming/issue_approval.php')) ?>" class="d-inline">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $corr['id'] ?>">
            <input type="hidden" name="action" value="release">
            <button type="submit" class="btn btn-kff btn-sm" data-confirm="<?= e(function_exists('incoming_desk_confirm_message') ? incoming_desk_confirm_message($corr) : __('in_issue_appr.desk_confirm')) ?>">
                <i class="bi bi-send-check me-1"></i><?= e(__('in_issue_appr.desk_release')) ?>
            </button>
        </form>
        <?php endif; ?>
    </span>
</div>
<?php endif; ?>
<?php
if ($isCancelled): ?>
<div class="alert alert-danger corr-cancel-banner no-print">
    <div class="fw-bold mb-1">
        <i class="bi bi-x-octagon me-1"></i><?= e(correspondence_cancel_kind_label($cancelKind !== '' ? $cancelKind : 'void')) ?>
        — <?= e(__('cancel.locked')) ?>
    </div>
    <?php if (trim((string) ($corr['cancel_reason'] ?? '')) !== ''): ?>
        <div><?= e($corr['cancel_reason']) ?></div>
    <?php endif; ?>
    <div class="small mt-1">
        <?php if (!empty($corr['cancelled_at'])): ?>
            <?= e(__('cancel.at')) ?>: <?= e(format_datetime($corr['cancelled_at'])) ?>
        <?php endif; ?>
        <?php
        $cancelByName = '';
        if (!empty($corr['cancelled_by'])) {
            try {
                $byStmt = db()->prepare('SELECT name_ar, name_en FROM `tblusers` WHERE id = ?');
                $byStmt->execute([(int) $corr['cancelled_by']]);
                $byRow = $byStmt->fetch();
                if ($byRow) {
                    $cancelByName = user_display_name($byRow, 'name_ar', 'name_en');
                }
            } catch (Throwable $e) {
                $cancelByName = '';
            }
        }
        ?>
        <?php if ($cancelByName !== ''): ?>
            · <?= e(__('cancel.by')) ?>: <?= e($cancelByName) ?>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($closeReq): ?>
<?php
$closeReqData = $closeReq['data'] ?? [];
$closeReqAction = trim((string) ($closeReqData['close_action'] ?? ''));
?>
<div class="card-panel mb-3 no-print border-warning corr-close-request">
    <div class="card-header">
        <span><i class="bi bi-send-check me-1"></i><?= e(__('close_req.panel_title')) ?></span>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <div class="text-muted small"><?= e(__('close_req.row_by')) ?></div>
                <div><?= e(user_display_name($closeReq, 'requested_by_name', 'requested_by_name_en')) ?></div>
                <div class="small text-muted"><?= e(format_datetime($closeReq['created_at'] ?? null)) ?></div>
            </div>
            <div class="col-md-4">
                <div class="text-muted small"><?= e(__('close_req.row_decider')) ?></div>
                <div><?= e($closeDeciderName !== '' ? $closeDeciderName : __('common.none')) ?></div>
            </div>
            <div class="col-md-4">
                <div class="text-muted small"><?= e(__('close.result')) ?></div>
                <div><?= e(correspondence_close_result_label((string) ($closeReqData['close_result'] ?? ''))) ?></div>
            </div>
            <div class="col-12">
                <div class="text-muted small"><?= e(__('close.action')) ?></div>
                <div><?= nl2br(e(mb_substr($closeReqAction, 0, 400) . (mb_strlen($closeReqAction) > 400 ? '…' : ''))) ?></div>
            </div>
        </div>
        <?php if ($canCloseDirect || $canWithdrawCloseReq): ?>
        <div class="d-flex flex-wrap gap-2 mt-3 align-items-center">
            <?php if ($canCloseDirect): ?>
                <a class="btn btn-kff btn-sm" href="<?= e(base_url('correspondence/close.php?id=' . (int) $corr['id'])) ?>">
                    <i class="bi bi-lock me-1"></i><?= e(__('close_req.approve')) ?>
                </a>
                <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectCloseReqModal">
                    <i class="bi bi-x-circle me-1"></i><?= e(__('close_req.reject')) ?>
                </button>
                <i class="bi bi-info-circle text-muted" tabindex="0" data-bs-toggle="tooltip" data-bs-placement="top" title="<?= e(__('close_req.decider_hint')) ?>"></i>
            <?php endif; ?>
            <?php if ($canWithdrawCloseReq): ?>
                <form method="post" action="<?= e(base_url('correspondence/close_request.php')) ?>" class="d-inline" data-confirm="<?= e(__('close_req.withdraw_confirm')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="withdraw">
                    <input type="hidden" name="request_id" value="<?= (int) $closeReq['id'] ?>">
                    <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-counterclockwise me-1"></i><?= e(__('close_req.withdraw')) ?></button>
                </form>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if (correspondence_is_closed($corr) && trim((string) ($corr['close_action'] ?? '')) !== ''): ?>
<?php
$closeActionFull = trim((string) $corr['close_action']);
$closeActionLimit = 180;
$closeActionLong = function_exists('mb_strlen')
    ? mb_strlen($closeActionFull, 'UTF-8') > $closeActionLimit
    : strlen($closeActionFull) > $closeActionLimit;
$closeActionPreview = $closeActionLong
    ? (function_exists('mb_substr')
        ? mb_substr($closeActionFull, 0, $closeActionLimit, 'UTF-8')
        : substr($closeActionFull, 0, $closeActionLimit)) . '…'
    : $closeActionFull;
$closeDetailsUrl = base_url('correspondence/close.php?id=' . (int) $corr['id']);
?>
<div class="card-panel mb-3 no-print corr-close-summary">
    <div class="card-header">
        <span><i class="bi bi-lock me-1"></i><?= e(__('close.summary')) ?></span>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-8">
                <div class="text-muted small"><?= e(__('close.action')) ?></div>
                <div class="corr-close-action-preview"><?= nl2br(e($closeActionPreview)) ?></div>
                <a class="corr-close-details-link" href="<?= e($closeDetailsUrl) ?>"><?= e(__('close.view_details')) ?></a>
            </div>
            <div class="col-md-4">
                <div class="text-muted small"><?= e(__('close.result')) ?></div>
                <div><?= e(correspondence_close_result_label((string) ($corr['close_result'] ?? ''))) ?></div>
            </div>
            <?php if (!empty($corr['close_decision_no']) || !empty($corr['close_decision_date'])): ?>
            <div class="col-md-4">
                <div class="text-muted small"><?= e(__('close.decision_no')) ?></div>
                <div><?= e((string) ($corr['close_decision_no'] ?? '—')) ?></div>
            </div>
            <div class="col-md-4">
                <div class="text-muted small"><?= e(__('close.decision_date')) ?></div>
                <div><?= e(!empty($corr['close_decision_date']) ? format_date($corr['close_decision_date']) : '—') ?></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($corr['closed_at'])): ?>
            <div class="col-md-4">
                <div class="text-muted small"><?= e(__('close.at')) ?></div>
                <div>
                    <?= e(format_datetime($corr['closed_at'])) ?>
                    <?php
                    $closedByName = user_display_name($corr, 'closed_by_name', 'closed_by_name_en');
                    if ($closedByName !== ''):
                    ?>
                        <span class="text-muted">— <?= e($closedByName) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row g-4 corr-view">
    <div class="col-xl-8">
        <div class="card-panel mb-3">
            <div class="card-header"><i class="bi bi-info-circle me-1"></i><?= e(__('corr.details')) ?></div>
            <div class="card-body p-0">
                <div class="corr-meta-grid">
                    <div class="corr-meta-item">
                        <span class="corr-meta-label"><?= e(corr_ref_type_label((string) ($corr['type'] ?? ''))) ?></span>
                        <span class="corr-meta-value fw-semibold corr-meta-num" dir="ltr"><?= e(function_exists('correspondence_ref_display') ? correspondence_ref_display($corr) : (string) ($corr['ref_number'] ?? '')) ?></span>
                    </div>
                    <div class="corr-meta-item">
                        <span class="corr-meta-label"><?= e(__('corr.type')) ?></span>
                        <span class="corr-meta-value"><?= e(type_label((string) ($corr['type'] ?? ''))) ?></span>
                    </div>
                    <?php if ($isOutgoing || $isIncoming): ?>
                    <?php
                    $partyArchived = correspondence_archived_party($corr);
                    if (trim((string) ($partyArchived['person'] ?? '')) === '') {
                        $partyArchived['person'] = trim((string) ($corr['party_name'] ?? ''));
                    }
                    $directionFrom = $isOutgoing ? null : $partyArchived;
                    $directionTo = $isOutgoing ? $partyArchived : null;
                    ?>
                    <div class="corr-meta-item">
                        <span class="corr-meta-label"><?= e(__('corr.party_from')) ?></span>
                        <span class="corr-meta-value">
                            <?php if ($isOutgoing): ?>
                                <?php render_correspondence_department_officer($corr, 'issuing'); ?>
                            <?php else: ?>
                                <?php render_corr_party_lines($directionFrom); ?>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="corr-meta-item">
                        <span class="corr-meta-label"><?= e(__('corr.party_to')) ?></span>
                        <span class="corr-meta-value">
                            <?php if ($isIncoming): ?>
                                <?php render_correspondence_department_officer($corr, 'receiving'); ?>
                            <?php else: ?>
                                <?php render_corr_party_lines($directionTo); ?>
                            <?php endif; ?>
                        </span>
                    </div>
                    <?php elseif ($isInternal): ?>
                    <div class="corr-meta-item">
                        <span class="corr-meta-label"><?= e(__('corr.party_from')) ?></span>
                        <span class="corr-meta-value">
                            <?php render_correspondence_department_officer($corr, 'issuing'); ?>
                        </span>
                    </div>
                    <div class="corr-meta-item">
                        <span class="corr-meta-label"><?= e(__('corr.party_to')) ?></span>
                        <span class="corr-meta-value">
                            <?php
                            $toParts = correspondence_archived_party($corr);
                            if (trim((string) ($toParts['person'] ?? '')) === '') {
                                $toParts['person'] = trim((string) ($corr['party_name'] ?? ''));
                            }
                            render_corr_list_person($toParts);
                            ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    <?php if ($isElectronic): ?>
                    <div class="corr-meta-item corr-meta-span-2">
                        <span class="corr-meta-label"><?= e(__('corr.channel_filter')) ?></span>
                        <span class="corr-meta-value">
                            <span class="badge text-bg-info"><i class="bi bi-envelope-at me-1"></i><?= e(__('corr.channel_electronic')) ?></span>
                        </span>
                        <?php
                        $detailEmail = function_exists('dms_corr_email_address') ? dms_corr_email_address($corr) : '';
                        ?>
                        <div class="small mt-1">
                            <?= e(__('issue_appr.electronic_email_label')) ?>:
                            <?php if ($detailEmail !== ''): ?>
                                <strong dir="ltr"><?= e($detailEmail) ?></strong>
                            <?php else: ?>
                                <strong class="text-danger"><?= e(__('send.missing_email')) ?></strong>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="corr-meta-item">
                        <span class="corr-meta-label"><?= e(__('corr.date')) ?></span>
                        <span class="corr-meta-value"><?= e(format_date($corr['corr_date'])) ?></span>
                    </div>
                    <div class="corr-meta-item">
                        <span class="corr-meta-label"><?= e(__('corr.reply_due')) ?></span>
                        <span class="corr-meta-value">
                            <?= e(format_date($corr['reply_due'])) ?>
                            <?php if (is_overdue($corr['reply_due'], $corr['status'])): ?>
                                <span class="badge badge-overdue"><?= e(__('corr.overdue_badge')) ?></span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <?php if ($isInternal): ?>
                    <div class="corr-meta-item">
                        <span class="corr-meta-label"><?= e(__('corr.dept_preparing')) ?></span>
                        <span class="corr-meta-value">
                            <?php
                            $route = correspondence_internal_route_names($corr);
                            echo e($route['from'] !== '' ? $route['from'] : __('common.none'));
                            ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    <?php if ($isInternal): ?>
                    <div class="corr-meta-item">
                        <span class="corr-meta-label"><?= e(__('internal.to_department')) ?></span>
                        <span class="corr-meta-value">
                            <?php
                            $route = correspondence_internal_route_names($corr);
                            echo e($route['to'] !== '' ? $route['to'] : __('common.none'));
                            ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    <div class="corr-meta-item">
                        <span class="corr-meta-label"><?= e(__('corr.category')) ?></span>
                        <span class="corr-meta-value"><?= e(category_display_name(['name' => $corr['category_name'] ?? '', 'name_en' => $corr['category_name_en'] ?? '']) ?: __('common.none')) ?></span>
                    </div>
                    <div class="corr-meta-item">
                        <span class="corr-meta-label"><?= e(__('corr.priority')) ?></span>
                        <span class="corr-meta-value"><?= e(priority_label($corr['priority'])) ?></span>
                    </div>
                    <div class="corr-meta-item">
                        <span class="corr-meta-label"><?= e(__('corr.confidentiality')) ?></span>
                        <span class="corr-meta-value"><?= e(confidentiality_label($corr['confidentiality'] ?? 'normal')) ?></span>
                    </div>
                    <?php $extRef = trim((string) ($corr['external_ref'] ?? '')); ?>
                    <?php if ($extRef !== ''): ?>
                    <div class="corr-meta-item">
                        <span class="corr-meta-label"><?= e(__('corr.external_ref')) ?></span>
                        <span class="corr-meta-value corr-meta-num" dir="ltr"><?= e($extRef) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php $corrExternalDate = corr_external_date_value($corr['external_date'] ?? ''); ?>
                    <?php if ($corrExternalDate !== null): ?>
                    <div class="corr-meta-item">
                        <span class="corr-meta-label"><?= e(($corr['type'] ?? '') === 'outgoing' ? __('corr.external_date_out') : __('corr.external_date')) ?></span>
                        <span class="corr-meta-value"><?= e(format_date($corrExternalDate)) ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="corr-meta-item">
                        <span class="corr-meta-label"><?= e(__('corr.status')) ?></span>
                        <span class="corr-meta-value"><?= status_badge($corr['status'], $cancelKind !== '' ? $cancelKind : null) ?></span>
                    </div>
                    <div class="corr-meta-item">
                        <span class="corr-meta-label"><?= e(__('corr.created_by')) ?></span>
                        <span class="corr-meta-value corr-courier-value">
                            <span><?= e(user_display_name($corr, 'creator_name', 'creator_name_en') ?: __('common.none')) ?></span>
                            <span class="corr-courier-sep" aria-hidden="true">—</span>
                            <?= format_datetime_html($corr['created_at'] ?? null) ?>
                        </span>
                    </div>
                    <?php
                    $courierNameShow = $isOutgoing ? trim((string) ($corr['courier_name'] ?? '')) : '';
                    $courierPhoneShow = $isOutgoing ? trim((string) ($corr['courier_phone'] ?? '')) : '';
                    ?>
                    <?php if ($courierNameShow !== '' || $courierPhoneShow !== ''): ?>
                    <div class="corr-meta-item">
                        <span class="corr-meta-label"><?= e(__('corr.courier')) ?></span>
                        <span class="corr-meta-value corr-courier-value">
                            <?php if ($courierNameShow !== ''): ?>
                                <span class="corr-courier-name"><?= e($courierNameShow) ?></span>
                            <?php endif; ?>
                            <?php if ($courierNameShow !== '' && $courierPhoneShow !== ''): ?>
                                <span class="corr-courier-sep" aria-hidden="true">—</span>
                            <?php endif; ?>
                            <?php if ($courierPhoneShow !== ''): ?>
                                <span class="corr-courier-phone" dir="ltr"><?= e($courierPhoneShow) ?></span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    <?php
                    $hasLinked = !$isInternal && (!empty($corr['linked_id']) || !empty($replyOut));
                    $showReplyCreate = $isIncoming && $canMutate && !$inHeld && user_can('outgoing.create', $user);
                    ?>
                    <?php if ($hasLinked || $showReplyCreate): ?>
                    <div class="corr-meta-item">
                        <span class="corr-meta-label"><?= e(__('corr.linked')) ?></span>
                        <span class="corr-meta-value">
                            <?php if ($corr['linked_id']): ?>
                                <a href="<?= e($relatedUrl) ?>" title="<?= e(__('corr.related_open')) ?>"><?= e($corr['linked_ref']) ?></a>
                            <?php elseif ($replyOut): ?>
                                <a href="<?= e($relatedUrl) ?>" title="<?= e(__('corr.related_open')) ?>"><?= e($replyOut['ref_number']) ?></a>
                            <?php elseif ($showReplyCreate): ?>
                                <a class="small" href="<?= e(base_url('outgoing/create.php?link=' . (int) $corr['id'])) ?>"><?= e(__('corr.create_reply')) ?></a>
                            <?php endif; ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    <?php
                    $enclosures = correspondence_enclosures((int) $corr['id']);
                    if ($enclosures):
                    ?>
                    <div class="corr-meta-item corr-meta-span-full">
                        <span class="corr-meta-label"><?= e(__('corr.enclosures')) ?></span>
                        <span class="corr-meta-value"><?= e(implode('، ', array_map('correspondence_enclosure_label', $enclosures))) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($corr['notes']): ?>
                    <div class="corr-meta-item corr-meta-span-full">
                        <span class="corr-meta-label"><?= e(__('corr.notes')) ?></span>
                        <span class="corr-meta-value"><?= nl2br(e($corr['notes'])) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if (!$isInternal && count($relatedGroup) > 1): ?>
        <div class="card-panel mb-3">
            <div class="card-header d-flex justify-content-between align-items-center gap-2">
                <span><i class="bi bi-link-45deg me-1"></i><?= e(__('corr.related_chain')) ?></span>
                <a class="small" href="<?= e($relatedUrl) ?>"><?= e(__('corr.related_open')) ?></a>
            </div>
            <?php $relatedRows = $relatedGroup; $relatedHighlightId = (int) $corr['id']; require __DIR__ . '/corr_related_table.php'; ?>
        </div>
        <?php endif; ?>

        <?php if ($briefSheet): ?>
        <div class="card-panel mb-3">
            <div class="card-header d-flex justify-content-between align-items-center gap-2">
                <span><i class="bi bi-file-earmark-ruled me-1"></i><?= e(__('brief.page')) ?></span>
                <span class="badge <?= !empty($briefSheet['decided']) ? 'text-bg-success' : 'text-bg-secondary' ?>">
                    <?= e(!empty($briefSheet['decided']) ? __('brief.status_decided') : __('brief.status_draft')) ?>
                </span>
            </div>
            <div class="card-body p-4">
                <?php
                $briefRecParts = is_array($briefSheet['recommendation'] ?? null)
                    ? $briefSheet['recommendation']
                    : briefing_parse_recommendation($briefSheet['recommendation_text'] ?? '');
                $briefRec = briefing_recommendation_plain($briefRecParts);
                ?>
                <?php if ($briefRec !== ''): ?>
                    <div class="small text-muted mb-1"><?= e(__('brief.recommendation')) ?></div>
                    <?php
                    if (mb_strlen($briefRec) > 280) {
                        $briefRec = mb_substr($briefRec, 0, 280) . '…';
                    }
                    ?>
                    <div class="mb-3"><?= nl2br(e($briefRec)) ?></div>
                <?php endif; ?>
                <div class="d-flex flex-wrap gap-2 no-print">
                    <a class="btn btn-sm btn-kff" href="<?= e(base_url('correspondence/brief.php?id='.(int)$corr['id'])) ?>"><?= e(__('brief.open')) ?></a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($letterText !== ''): ?>
        <div class="card-panel mb-3">
            <div class="card-header"><i class="bi bi-file-text me-1"></i><?= e(__('corr.letter_body')) ?></div>
            <div class="card-body">
                <div class="corr-letter-text" dir="<?= $letterLang === 'en' ? 'ltr' : 'rtl' ?>"><?= render_letter_body_html($letterText, false, $corr) ?></div>
            </div>
        </div>
        <?php endif; ?>

        <?php require __DIR__ . '/corr_view_attachments.php'; ?>

        <?php require __DIR__ . '/corr_outgoing_prep_panel.php'; ?>
        <?php require __DIR__ . '/corr_issue_approval_panel.php'; ?>
        <?php require __DIR__ . '/corr_incoming_approval_panel.php'; ?>
        <?php require __DIR__ . '/corr_internal_approval_panel.php'; ?>
        <?php require __DIR__ . '/corr_cc_panel.php'; ?>

        <?php if ($canIssueActions): ?>
        <?php require __DIR__ . '/corr_view_rsvp.php'; ?>
        <?php endif; ?>

        <?php require __DIR__ . '/corr_send_panel.php'; ?>

        <?php
        $prepUnofficial = (function_exists('outgoing_prep_is_open') && outgoing_prep_is_open($corr))
            || (function_exists('outgoing_prep_is_superseded') && outgoing_prep_is_superseded($corr));
        ?>
        <?php if ($isOutgoing && !$prepUnofficial && $canIssueActions): ?>
            <?php
            $canEdit = $canMutate;
            require __DIR__ . '/corr_delivery_status_panel.php';
            ?>
        <?php endif; ?>

        <?php require __DIR__ . '/corr_view_comments_referrals.php'; ?>

        <?php require __DIR__ . '/corr_view_side.php'; ?>
<?php require __DIR__ . '/corr_view_modals.php'; ?>
<?php
$includeQrcodeJs = !empty($showThermalSide);
$stickerQrPx = (function_exists('org_logo_url') && org_logo_url() !== '') ? 192 : 208;
$qrJs = '';
if (!empty($showThermalSide)) {
    $qrJson = json_encode($qrContent, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $qrJs = 'if(document.getElementById("corr-sticker-qr")){'
        . 'if(typeof renderThermalQr==="function")renderThermalQr("corr-sticker-qr",' . $qrJson . ',' . (int) $stickerQrPx . ');'
        . 'else if(typeof renderQr==="function")renderQr("corr-sticker-qr",' . $qrJson . ',' . (int) $stickerQrPx . ');'
        . '}'
        . 'document.querySelectorAll(".corr-sticker-host .print-thermal-logo-img").forEach(function(img){if(typeof renderThermalLogo==="function")renderThermalLogo(img);});';
}
$pageScripts = dms_page_scripts_with_date_picker(
    '<script>'
    . $qrJs
    . 'document.querySelectorAll(".comment-quick").forEach(function(b){b.addEventListener("click",function(){var t=document.getElementById("comment_body");if(t)t.value=b.getAttribute("data-text");});});'
    . '</script>'
);
?>
