<?php
/**
 * لوحة موافقة إصدار الصادر على صفحة العرض
 * يتطلب: $corr, $user
 */
if (($corr['type'] ?? '') !== 'outgoing') {
    return;
}
if (function_exists('outgoing_prep_is_open') && outgoing_prep_is_open($corr)) {
    return;
}
if (function_exists('outgoing_prep_is_superseded') && outgoing_prep_is_superseded($corr)) {
    return;
}
if (!function_exists('outgoing_approval_is_configured')) {
    return;
}

$apprConfigured = outgoing_approval_is_configured();
$apprStatus = outgoing_approval_status_of($corr);
$canRequest = outgoing_user_can_request_approval($user, $corr);
$canDecide = outgoing_user_can_decide_approval($user, $corr);
$isAlternate = $canDecide && outgoing_user_is_alternate_approver($user, $corr);
$sodBlocked = !$canDecide && function_exists('outgoing_user_blocked_by_sod')
    && outgoing_user_blocked_by_sod($user, $corr);
$canReview = function_exists('outgoing_user_can_review') && outgoing_user_can_review($user, $corr);
$reviewOn = function_exists('outgoing_review_enabled') && outgoing_review_enabled();
$reviewSodBlocked = !$canReview && function_exists('outgoing_user_review_blocked_by_sod')
    && outgoing_user_review_blocked_by_sod($user, $corr);
$reviewAssignee = $apprStatus === 'pending_review' ? (int) ($corr['issue_approval_assignee_id'] ?? 0) : 0;
$reviewAssigneeName = $reviewAssignee > 0 && function_exists('outgoing_user_name_by_id') ? outgoing_user_name_by_id($reviewAssignee) : '';
$reviewedBy = (int) ($corr['issue_review_by'] ?? 0);
$reviewedByName = $reviewedBy > 0 && function_exists('outgoing_user_name_by_id') ? outgoing_user_name_by_id($reviewedBy) : '';
$resubmitReview = in_array($apprStatus, ['returned', 'rejected'], true);
$requestLabel = !$reviewOn
    ? __('issue_appr.request')
    : __($resubmitReview ? 'issue_review.resubmit' : 'issue_review.request');
$requestConfirm = !$reviewOn
    ? __('issue_appr.confirm_request')
    : __($resubmitReview ? 'issue_review.confirm_resubmit' : 'issue_review.confirm_request');
$canIssue = outgoing_can_issue($corr);
$reviewFinishedWithoutApproval = $reviewOn
    && !$apprConfigured
    && $apprStatus === ''
    && trim((string) ($corr['issue_reviewed_at'] ?? '')) !== ''
    && $canIssue;
if ($reviewFinishedWithoutApproval) {
    return;
}
$blockReason = outgoing_approval_block_reason($corr);
$isAdminDecide = $canDecide && appr_is_admin_decide_override($user, $corr);
$isAdminReview = $canReview && appr_is_admin_review_override($user, $corr);
$isAlternate = $isAlternate && !$isAdminDecide;
$issuedCopyMissing = function_exists('outgoing_needs_issued_copy')
    && outgoing_needs_issued_copy($corr)
    && !outgoing_has_issued_copy((int) ($corr['id'] ?? 0));
$issuedCopyTip = $issuedCopyMissing ? __('out_prep.issued_copy_required') : '';
$requestFileMissing = function_exists('outgoing_request_missing_attachment')
    && outgoing_request_missing_attachment($corr);
$panelTitleKey = 'issue_appr.panel_title';
if (in_array($apprStatus, ['pending_review', 'returned'], true)) {
    $panelTitleKey = 'issue_appr.panel_title_review';
} elseif ($apprStatus === 'pending_copy') {
    $panelTitleKey = 'issue_appr.panel_title_copy';
} elseif (in_array($apprStatus, ['pending_sg', 'pending_deputy', 'pending_alternate'], true)) {
    $panelTitleKey = 'issue_appr.panel_title_approve';
}
$noteRequired = $isAlternate || $isAdminDecide;
$noteLabel = $isAdminDecide ? __('issue_appr.admin_reason') : ($isAlternate ? __('issue_appr.alternate_reason') : __('issue_appr.note'));
$isElectronic = function_exists('correspondence_intend_electronic') && correspondence_intend_electronic($corr);
$apprEmail = '';
if ($isElectronic && function_exists('dms_corr_email_address')) {
    $apprEmail = dms_corr_email_address($corr);
}
?>
<div class="card-panel corr-issue-card mb-3 no-print">
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
        <span><i class="bi bi-shield-check me-1"></i><?= e(__($panelTitleKey)) ?></span>
        <span class="badge <?= ($apprStatus === 'approved' || $apprStatus === 'with_comms') ? 'text-bg-success' : ($apprStatus === 'rejected' ? 'text-bg-danger' : ($apprStatus === 'returned' ? 'text-bg-info' : ($apprStatus !== '' ? 'text-bg-warning' : 'text-bg-secondary'))) ?>">
            <?= e(outgoing_approval_label($apprStatus)) ?>
        </span>
    </div>
    <div class="card-body">
        <?php if ($apprStatus === 'with_comms'): ?>
            <div class="alert alert-success py-2 small mb-0">
                <i class="bi bi-printer me-1"></i><?= e($isElectronic ? __('issue_appr.handed_to_comms_electronic') : __('issue_appr.handed_to_comms')) ?>
            </div>
        <?php elseif (!$apprConfigured && !$reviewOn): ?>
            <p class="text-muted small mb-0"><?= e(function_exists('outgoing_appr_text') ? outgoing_appr_text('issue_appr.not_configured') : __('issue_appr.not_configured')) ?>
                <?php if (($user['role'] ?? '') === 'admin'): ?>
                    — <a href="<?= e(base_url('admin/settings.php')) ?>"><?= e(__('nav.settings')) ?></a>
                <?php endif; ?>
            </p>
        <?php else: ?>
            <?php if ($isElectronic): ?>
                <div class="alert <?= $apprEmail !== '' ? 'alert-info' : 'alert-warning' ?> py-2 small mb-3 corr-appr-electronic">
                    <div class="fw-semibold mb-1">
                        <i class="bi bi-envelope-at me-1"></i><?= e(__('issue_appr.electronic_channel')) ?>
                    </div>
                    <div><?= e(__('issue_appr.electronic_email_label')) ?>:
                        <?php if ($apprEmail !== ''): ?>
                            <strong dir="ltr"><?= e($apprEmail) ?></strong>
                        <?php else: ?>
                            <strong class="text-danger"><?= e(__('send.missing_email')) ?></strong>
                        <?php endif; ?>
                    </div>
                    <?php if ($canDecide): ?>
                        <div class="mt-1 text-muted"><?= e(__('issue_appr.electronic_decide_hint')) ?></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <?php if ($apprStatus === 'returned'): ?>
                <div class="alert alert-warning py-2 small mb-2">
                    <div class="fw-semibold"><i class="bi bi-arrow-counterclockwise me-1"></i><?= e(__('issue_review.returned_by')) ?><?= $reviewedByName !== '' ? ' ' . e($reviewedByName) : '' ?></div>
                    <?php if (!empty($corr['issue_review_note'])): ?>
                        <div class="mt-1"><?= e((string) $corr['issue_review_note']) ?></div>
                    <?php endif; ?>
                    <div class="mt-1 text-muted"><?= e(__('issue_review.returned_hint')) ?></div>
                </div>
            <?php elseif ($apprStatus === 'pending_review'): ?>
                <div class="alert <?= $reviewSodBlocked ? 'alert-warning' : 'alert-info' ?> py-2 small mb-2">
                    <i class="bi bi-clipboard-check me-1"></i><?= e($reviewSodBlocked
                        ? __('issue_review.sod_blocked')
                        : ($reviewAssigneeName !== ''
                            ? str_replace(':name', $reviewAssigneeName, __('issue_review.waiting_assigned'))
                            : __('issue_review.waiting_hint'))) ?>
                </div>
            <?php elseif ($reviewedByName !== '' && !empty($corr['issue_reviewed_at'])): ?>
                <?php $issueMetaOpen = true; ?>
            <?php endif; ?>
            <?php
            $showIssueMeta = !empty($issueMetaOpen)
                || !empty($corr['issue_approval_requested_at'])
                || !empty($corr['issue_approval_escalated_at'])
                || !empty($corr['issue_approval_note']);
            ?>
            <?php if ($showIssueMeta): ?>
            <div class="corr-issue-meta">
            <?php if (!empty($issueMetaOpen)): ?>
                <div><strong><?= e(__('issue_review.reviewed_by')) ?>:</strong>
                    <?= e($reviewedByName) ?> — <span dir="ltr"><?= e((string) $corr['issue_reviewed_at']) ?></span>
                    <?php if (!empty($corr['issue_review_note'])): ?>
                        <div class="text-muted"><?= e((string) $corr['issue_review_note']) ?></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($corr['issue_approval_requested_at'])): ?>
                <div><strong><?= e(__('issue_appr.requested_at')) ?>:</strong>
                    <span dir="ltr"><?= e((string) $corr['issue_approval_requested_at']) ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($corr['issue_approval_escalated_at'])): ?>
                <div><strong><?= e(__('issue_appr.escalated_at')) ?>:</strong>
                    <span dir="ltr"><?= e((string) $corr['issue_approval_escalated_at']) ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($corr['issue_approval_note'])): ?>
                <div><strong><?= e(__('issue_appr.note')) ?>:</strong> <?= e((string) $corr['issue_approval_note']) ?></div>
            <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php if ($apprStatus === 'approved'): ?>
                <div class="alert alert-success py-2 small mb-2">
                    <i class="bi bi-printer me-1"></i><?= e($isElectronic ? __('issue_appr.handed_to_comms_electronic') : __('issue_appr.handed_to_comms')) ?>
                </div>
            <?php endif; ?>
            <?php if ($canRequest && $requestFileMissing): ?>
                <div class="alert alert-warning py-2 small mb-2"><?= e(__('out_prep.attachment_before_review')) ?></div>
            <?php elseif ($apprStatus === 'pending_copy'): ?>
                <div class="alert alert-warning py-2 small mb-2"><?= e($issuedCopyMissing ? __('out_prep.copy_before_approver') : __('out_prep.copy_ready_to_send')) ?></div>
            <?php elseif ($issuedCopyMissing): ?>
                <div class="alert alert-warning py-2 small mb-2"><?= e(__('out_prep.issued_copy_missing')) ?></div>
            <?php endif; ?>
            <?php if ($sodBlocked): ?>
                <div class="alert alert-warning py-2 small mb-2"><?= e(__('issue_appr.sod_blocked')) ?></div>
            <?php elseif (!$canIssue && $blockReason !== '' && $apprStatus === 'rejected'): ?>
                <div class="alert alert-warning py-2 small mb-2"><?= e($blockReason) ?></div>
            <?php endif; ?>

            <?php if ($apprStatus === 'pending_copy' && function_exists('outgoing_user_can_forward_issued_copy') && outgoing_user_can_forward_issued_copy($user, $corr)): ?>
                <form method="post" action="<?= e(base_url('outgoing/issue_approval.php')) ?>" class="corr-issue-decide">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $corr['id'] ?>">
                    <input type="hidden" name="action" value="forward_copy">
                    <div class="corr-decide-actions">
                        <?php if ($issuedCopyMissing): ?>
                        <span tabindex="0" data-bs-toggle="tooltip" data-bs-placement="top" title="<?= e(__('out_prep.forward_copy_need_file')) ?>">
                            <button type="button" class="btn btn-kff btn-sm" disabled>
                                <i class="bi bi-send-check me-1"></i><?= e(__('out_prep.forward_copy')) ?>
                            </button>
                        </span>
                        <?php else: ?>
                        <button type="submit" class="btn btn-kff btn-sm" data-confirm="<?= e(__('out_prep.confirm_forward_copy')) ?>">
                            <i class="bi bi-send-check me-1"></i><?= e(__('out_prep.forward_copy')) ?>
                        </button>
                        <?php endif; ?>
                    </div>
                </form>
            <?php endif; ?>

            <?php if ($canRequest): ?>
                <form method="post" action="<?= e(base_url('outgoing/issue_approval.php')) ?>" class="corr-issue-decide">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $corr['id'] ?>">
                    <input type="hidden" name="action" value="request">
                    <div class="corr-decide-actions">
                        <?php if ($requestFileMissing): ?>
                        <span tabindex="0" data-bs-toggle="tooltip" data-bs-placement="top" title="<?= e(__('out_prep.attachment_before_review')) ?>">
                            <button type="button" class="btn btn-kff btn-sm" disabled>
                                <i class="bi bi-send-check me-1"></i><?= e($requestLabel) ?>
                            </button>
                        </span>
                        <?php else: ?>
                        <button type="submit" class="btn btn-kff btn-sm" data-confirm="<?= e($requestConfirm) ?>">
                            <i class="bi bi-send-check me-1"></i><?= e($requestLabel) ?>
                        </button>
                        <?php endif; ?>
                    </div>
                </form>
            <?php endif; ?>

            <?php if ($canReview): ?>
                <?php if ($isAdminReview): ?>
                    <div class="alert alert-warning py-2 small mb-2"><i class="bi bi-person-gear me-1"></i><?= e(__('issue_appr.admin_acting_hint')) ?></div>
                <?php endif; ?>
                <form method="post" action="<?= e(base_url('outgoing/issue_approval.php')) ?>" class="corr-issue-decide">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $corr['id'] ?>">
                    <div>
                        <label class="form-label small mb-1" for="issue_review_note"><?= e($isAdminReview ? __('issue_appr.admin_reason') . ' *' : __('issue_review.note')) ?></label>
                        <input type="text" name="note" id="issue_review_note" class="form-control form-control-sm" maxlength="450"
                               <?= $isAdminReview ? 'required' : '' ?>
                               placeholder="<?= e($isAdminReview ? __('issue_appr.admin_reason_placeholder') : __('issue_review.note_placeholder')) ?>">
                    </div>
                    <div class="corr-decide-actions">
                        <button type="submit" name="action" value="review_pass" class="btn btn-success btn-sm"
                                data-confirm="<?= e(__('issue_review.confirm_pass')) ?>">
                            <i class="bi bi-check2-circle me-1"></i><?= e(__('issue_review.pass')) ?>
                        </button>
                        <button type="submit" name="action" value="review_return" class="btn btn-orange btn-sm"
                                data-confirm="<?= e(__('issue_review.confirm_return')) ?>"
                                data-note-required="issue_review_note"
                                data-note-required-msg="<?= e(__('issue_review.note_required')) ?>">
                            <i class="bi bi-arrow-counterclockwise me-1"></i><?= e(__('issue_review.return')) ?>
                        </button>
                    </div>
                </form>
            <?php endif; ?>

            <?php if ($canDecide): ?>
                <?php if ($isAdminDecide): ?>
                    <div class="alert alert-warning py-2 small mb-2"><i class="bi bi-person-gear me-1"></i><?= e(__('issue_appr.admin_acting_hint')) ?></div>
                <?php elseif ($isAlternate): ?>
                    <div class="alert alert-info py-2 small mb-2"><?= e(outgoing_appr_text('issue_appr.alternate_decide_hint')) ?></div>
                <?php endif; ?>
                <form method="post" action="<?= e(base_url('outgoing/issue_approval.php')) ?>" class="corr-issue-decide">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $corr['id'] ?>">
                    <div>
                        <label class="form-label small mb-1" for="issue_appr_note"><?= e($noteLabel) ?><?= $noteRequired ? ' *' : '' ?></label>
                        <input type="text" name="note" id="issue_appr_note" class="form-control form-control-sm" maxlength="450"
                               <?= $noteRequired ? 'required' : '' ?>
                               placeholder="<?= e($isAdminDecide ? __('issue_appr.admin_reason_placeholder') : ($isAlternate ? __('issue_appr.alternate_reason_placeholder') : '')) ?>">
                    </div>
                    <div class="corr-decide-actions">
                        <?php if ($issuedCopyMissing): ?>
                        <span tabindex="0" data-bs-toggle="tooltip" data-bs-placement="top" title="<?= e($issuedCopyTip) ?>">
                            <button type="button" class="btn btn-success btn-sm" disabled>
                                <i class="bi bi-check2-circle me-1"></i><?= e(__('issue_appr.approve')) ?>
                            </button>
                        </span>
                        <?php else: ?>
                        <button type="submit" name="action" value="approve" class="btn btn-success btn-sm"
                                data-confirm="<?= e(__('issue_appr.confirm_approve')) ?>">
                            <i class="bi bi-check2-circle me-1"></i><?= e(__('issue_appr.approve')) ?>
                        </button>
                        <?php endif; ?>
                        <button type="submit" name="action" value="reject" class="btn btn-danger btn-sm"
                                data-confirm="<?= e(__('issue_appr.confirm_reject')) ?>" data-issue-reject="1"
                                title="<?= e(__('issue_appr.reject_hint')) ?>">
                            <i class="bi bi-x-circle me-1"></i><?= e(__('issue_appr.reject')) ?>
                        </button>
                    </div>
                </form>
            <?php endif; ?>
            <script>
            (function () {
                var btn = document.querySelector('[data-issue-reject]');
                if (!btn) return;
                btn.addEventListener('click', function (e) {
                    var note = document.getElementById('issue_appr_note');
                    if (note && String(note.value || '').replace(/\s+/g, ' ').trim() !== '') return;
                    e.preventDefault();
                    var msg = <?= json_encode(__('issue_appr.reject_note_required'), JSON_UNESCAPED_UNICODE) ?>;
                    if (window.DmsSwal) window.DmsSwal.warning(msg);
                });
            })();
            </script>
            <?php
            $admActionUrl = base_url('outgoing/issue_approval.php');
            require __DIR__ . '/corr_admin_override_panel.php';
            ?>
        <?php endif; ?>
    </div>
</div>
