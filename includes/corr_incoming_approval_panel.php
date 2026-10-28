<?php
/**
 * لوحة مراجعة واعتماد توجيه الوارد على صفحة العرض
 * يتطلب: $corr, $user
 */
if (($corr['type'] ?? '') !== 'incoming' || !function_exists('incoming_approval_decide')) {
    return;
}
if (function_exists('incoming_is_at_desk') && incoming_is_at_desk($corr)) {
    return;
}

$inAppr = appr_with('incoming', static function () use ($corr, $user): array {
    $status = outgoing_approval_status_of($corr);
    $canDecide = outgoing_user_can_decide_approval($user, $corr);
    $isAdminDecide = $canDecide && appr_is_admin_decide_override($user, $corr);
    $isAlternate = $canDecide && !$isAdminDecide && outgoing_user_is_alternate_approver($user, $corr);
    $canReview = outgoing_user_can_review($user, $corr);
    $reviewAssignee = $status === 'pending_review' ? (int) ($corr['issue_approval_assignee_id'] ?? 0) : 0;
    $reviewAssigneeName = $reviewAssignee > 0 ? outgoing_user_name_by_id($reviewAssignee) : '';
    $reviewSodBlocked = !$canReview && outgoing_user_review_blocked_by_sod($user, $corr);
    return [
        'status' => $status,
        'label' => $status !== '' ? outgoing_approval_label($status) : __('incoming_appr.list_in_scope'),
        'can_request' => outgoing_user_can_request_approval($user, $corr),
        'can_review' => $canReview,
        'can_decide' => $canDecide,
        'is_alternate' => $isAlternate,
        'is_admin_decide' => $isAdminDecide,
        'is_admin_review' => $canReview && appr_is_admin_review_override($user, $corr),
        'sod_blocked' => !$canDecide && outgoing_user_blocked_by_sod($user, $corr),
        'review_waiting' => $reviewSodBlocked
            ? appr__('issue_review.sod_blocked')
            : ($reviewAssigneeName !== ''
                ? str_replace(':name', $reviewAssigneeName, appr__('issue_review.waiting_assigned'))
                : appr__('issue_review.waiting_hint')),
        'review_sod' => $reviewSodBlocked,
        'reviewed_by' => outgoing_user_name_by_id((int) ($corr['issue_review_by'] ?? 0)),
        'decided_by' => outgoing_user_name_by_id((int) ($corr['issue_approval_decided_by'] ?? 0)),
        'returned_by_approver' => $status === 'returned' && (int) ($corr['issue_approval_decided_by'] ?? 0) > 0,
        'request_label' => appr__($status === 'returned' ? 'issue_review.resubmit' : 'issue_review.request'),
        'alternate_hint' => outgoing_appr_text('issue_appr.alternate_decide_hint'),
        'waiting_approver' => in_array($status, ['pending_sg', 'pending_deputy', 'pending_alternate'], true)
            ? outgoing_approval_label($status)
            : '',
    ];
});

if ($inAppr['status'] === '' && !$inAppr['can_request']) {
    return;
}
$inStatus = $inAppr['status'];
$inBadge = match ($inStatus) {
    'approved' => 'text-bg-success',
    'filed' => 'text-bg-secondary',
    'returned' => 'text-bg-info',
    default => 'text-bg-warning',
};
$inAction = base_url('incoming/issue_approval.php');
$inDeptName = '';
$inDeptOffice = '';
if ($inAppr['can_decide']) {
    foreach (departments_list() as $d) {
        if ((int) $d['id'] === (int) ($corr['department_id'] ?? 0)) {
            $inDeptName = department_person_option_label($d);
            $inDeptOffice = department_officer_subtitle($d);
            break;
        }
    }
}
?>
<div class="card-panel mb-3 no-print">
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
        <span><i class="bi bi-shield-check me-1"></i><?= e(__('in_issue_appr.panel_title')) ?></span>
        <span class="badge <?= $inBadge ?>"><?= e($inAppr['label']) ?></span>
    </div>
    <div class="card-body">
        <?php if ($inStatus === 'returned'): ?>
            <div class="alert alert-warning py-2 small mb-2">
                <?php if ($inAppr['returned_by_approver']): ?>
                    <div class="fw-semibold"><i class="bi bi-arrow-counterclockwise me-1"></i><?= e(__('in_issue_appr.returned_by_approver')) ?><?= $inAppr['decided_by'] !== '' ? ' ' . e($inAppr['decided_by']) : '' ?></div>
                    <?php if (!empty($corr['issue_approval_note'])): ?>
                        <div class="mt-1"><?= e((string) $corr['issue_approval_note']) ?></div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="fw-semibold"><i class="bi bi-arrow-counterclockwise me-1"></i><?= e(__('issue_review.returned_by')) ?><?= $inAppr['reviewed_by'] !== '' ? ' ' . e($inAppr['reviewed_by']) : '' ?></div>
                    <?php if (!empty($corr['issue_review_note'])): ?>
                        <div class="mt-1"><?= e((string) $corr['issue_review_note']) ?></div>
                    <?php endif; ?>
                <?php endif; ?>
                <div class="mt-1 text-muted"><?= e(__('in_issue_review.returned_hint')) ?></div>
            </div>
        <?php elseif ($inStatus === 'pending_review'): ?>
            <div class="alert <?= $inAppr['review_sod'] ? 'alert-warning' : 'alert-info' ?> py-2 small mb-2">
                <i class="bi bi-clipboard-check me-1"></i><?= e($inAppr['review_waiting']) ?>
            </div>
        <?php elseif ($inStatus === '' ): ?>
            <p class="small text-muted mb-2"><?= e(__('in_issue_appr.not_started_hint')) ?></p>
        <?php else: ?>
            <?php if ($inAppr['reviewed_by'] !== '' && !empty($corr['issue_reviewed_at'])): ?>
                <div class="small mb-1"><strong><?= e(__('issue_review.reviewed_by')) ?>:</strong>
                    <?= e($inAppr['reviewed_by']) ?> — <span dir="ltr"><?= e((string) $corr['issue_reviewed_at']) ?></span>
                    <?php if (!empty($corr['issue_review_note'])): ?>
                        <div class="text-muted"><?= e((string) $corr['issue_review_note']) ?></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <?php if ($inAppr['waiting_approver'] !== ''): ?>
                <div class="alert alert-info py-2 small mb-2"><i class="bi bi-hourglass-split me-1"></i><?= e(__('in_issue_appr.held_hint')) ?></div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (!empty($corr['issue_approval_escalated_at'])): ?>
            <div class="small mb-1"><strong><?= e(__('issue_appr.escalated_at')) ?>:</strong>
                <span dir="ltr"><?= e((string) $corr['issue_approval_escalated_at']) ?></span>
            </div>
        <?php endif; ?>
        <?php if (in_array($inStatus, ['approved', 'filed'], true)): ?>
            <div class="small mb-1"><strong><?= e(__('in_issue_appr.decided_by')) ?>:</strong>
                <?= e($inAppr['decided_by']) ?>
                <?php if (!empty($corr['issue_approval_decided_at'])): ?>
                    — <span dir="ltr"><?= e((string) $corr['issue_approval_decided_at']) ?></span>
                <?php endif; ?>
            </div>
            <?php if (!empty($corr['issue_approval_note'])): ?>
                <div class="alert alert-light border py-2 small mb-0">
                    <strong><i class="bi bi-pen me-1"></i><?= e(__('in_issue_appr.annotation')) ?>:</strong>
                    <?= e((string) $corr['issue_approval_note']) ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        <?php if ($inAppr['sod_blocked']): ?>
            <div class="alert alert-warning py-2 small mb-2"><?= e(__('in_issue_appr.sod_blocked')) ?></div>
        <?php endif; ?>

        <?php if ($inAppr['can_request']): ?>
            <?php
            $inCanChooseDept = can_choose_correspondence_department($user);
            $inCurrentDeptId = (int) ($corr['department_id'] ?? 0);
            $inRequestDepts = departments_list();
            $inCurrentDeptName = '';
            $inCurrentDeptOffice = '';
            foreach ($inRequestDepts as $d) {
                if ((int) $d['id'] === $inCurrentDeptId) {
                    $inCurrentDeptName = department_person_option_label($d);
                    $inCurrentDeptOffice = department_officer_subtitle($d);
                    break;
                }
            }
            $inCcEdit = function_exists('corr_cc_user_can_edit') && corr_cc_user_can_edit($user, $corr);
            ?>
            <form method="post" action="<?= e($inAction) ?>" class="row g-2 <?= $inCcEdit ? 'align-items-start' : 'align-items-end' ?> mt-1">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $corr['id'] ?>">
                <input type="hidden" name="action" value="request">
                <div class="col-md-5">
                    <label class="form-label small mb-1" for="in_request_dept"><?= e(__('in_issue_appr.send_to_dept')) ?></label>
                    <?php if ($inCanChooseDept): ?>
                        <select name="department_id" id="in_request_dept" class="form-select form-select-sm corr-officer-pick">
                            <?php foreach ($inRequestDepts as $d): ?>
                                <option value="<?= (int) $d['id'] ?>" data-office="<?= e(department_officer_subtitle($d)) ?>" <?= (int) $d['id'] === $inCurrentDeptId ? 'selected' : '' ?>>
                                    <?= e(department_person_option_label($d)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <div class="form-control form-control-sm bg-light corr-officer-locked"><?php render_officer_with_office($inCurrentDeptName !== '' ? $inCurrentDeptName : '—', $inCurrentDeptOffice); ?></div>
                    <?php endif; ?>
                </div>
                <?php if ($inCcEdit): ?>
                <div class="col-md-7">
                    <label class="form-label small mb-1" for="in_request_cc" title="<?= e(__('cc.field_hint')) ?>"><?= e(__('cc.field')) ?></label>
                    <?php
                    $ccType = 'incoming';
                    $ccInputId = 'in_request_cc';
                    $ccSmall = true;
                    $ccExcludeUserId = (int) ($corr['created_by'] ?? 0);
                    $ccSelectedIds = corr_cc_pending_ids((int) $corr['id']);
                    require __DIR__ . '/corr_cc_field.php';
                    ?>
                </div>
                <div class="col-12 d-flex flex-wrap gap-2">
                <?php else: ?>
                <div class="col-md-7 d-flex flex-wrap gap-2">
                <?php endif; ?>
                    <?php $inEditUi = (!correspondence_is_cancelled($corr) && incoming_user_can_edit_registration($user, $corr)) ? dms_perm_ui('incoming.edit', $user) : 'hide'; ?>
                    <?php if ($inEditUi === 'allow'): ?>
                        <a class="btn btn-outline-primary btn-sm" href="<?= e(base_url('incoming/edit.php?id=' . (int) $corr['id'])) ?>">
                            <i class="bi bi-pencil-square me-1"></i><?= e(__('in_issue_appr.edit_incoming')) ?>
                        </a>
                    <?php elseif ($inEditUi === 'deny'): ?>
                        <?= dms_perm_denied_btn(__('in_issue_appr.edit_incoming'), 'bi-pencil-square', 'btn-outline-primary btn-sm') ?>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-kff btn-sm" data-confirm="<?= e(function_exists('incoming_flow_request_confirm') ? incoming_flow_request_confirm($inStatus === 'returned') : ($inStatus === 'returned' ? __('in_issue_appr.confirm_resubmit') : __('in_issue_appr.confirm_request'))) ?>">
                        <i class="bi bi-send-check me-1"></i><?= e($inAppr['request_label']) ?>
                    </button>
                </div>
            </form>
        <?php endif; ?>

        <?php if ($inAppr['can_review']): ?>
            <form method="post" action="<?= e($inAction) ?>" class="row g-2 align-items-end mt-2">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $corr['id'] ?>">
                <?php if ($inAppr['is_admin_review']): ?>
                    <div class="col-12"><div class="alert alert-warning py-2 small mb-0"><i class="bi bi-person-gear me-1"></i><?= e(__('issue_appr.admin_acting_hint')) ?></div></div>
                <?php endif; ?>
                <div class="col-md-6">
                    <label class="form-label small mb-1" for="in_review_note"><?= e($inAppr['is_admin_review'] ? __('issue_appr.admin_reason') . ' *' : __('issue_review.note')) ?></label>
                    <input type="text" name="note" id="in_review_note" class="form-control form-control-sm" maxlength="450"
                           <?= $inAppr['is_admin_review'] ? 'required' : '' ?>
                           placeholder="<?= e($inAppr['is_admin_review'] ? __('issue_appr.admin_reason_placeholder') : __('issue_review.note_placeholder')) ?>">
                </div>
                <div class="col-md-6 d-flex flex-wrap gap-2">
                    <button type="submit" name="action" value="review_pass" class="btn btn-success btn-sm" data-confirm="<?= e(__('in_issue_review.confirm_pass')) ?>">
                        <i class="bi bi-check2-circle me-1"></i><?= e(__('issue_review.pass')) ?>
                    </button>
                    <button type="submit" name="action" value="review_return" class="btn btn-orange btn-sm"
                            data-confirm="<?= e(__('in_issue_review.confirm_return')) ?>"
                            data-note-required="in_review_note"
                            data-note-required-msg="<?= e(__('issue_review.note_required')) ?>">
                        <i class="bi bi-arrow-counterclockwise me-1"></i><?= e(__('issue_review.return')) ?>
                    </button>
                </div>
            </form>
        <?php endif; ?>

        <?php if ($inAppr['can_decide']): ?>
            <?php if ($inAppr['is_admin_decide']): ?>
                <div class="alert alert-warning py-2 small mb-2"><i class="bi bi-person-gear me-1"></i><?= e(__('issue_appr.admin_acting_hint')) ?></div>
            <?php elseif ($inAppr['is_alternate']): ?>
                <div class="alert alert-info py-2 small mb-2"><?= e($inAppr['alternate_hint']) ?></div>
            <?php endif; ?>
            <?php $inNoteReq = $inAppr['is_alternate'] || $inAppr['is_admin_decide']; ?>
            <form method="post" action="<?= e($inAction) ?>" class="row g-2 align-items-end mt-2">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $corr['id'] ?>">
                <div class="col-md-3">
                    <label class="form-label small mb-1"><?= e(__('in_issue_appr.route_to')) ?></label>
                    <div class="form-control form-control-sm bg-light corr-officer-locked"><?php render_officer_with_office($inDeptName !== '' ? $inDeptName : '—', $inDeptOffice); ?></div>
                </div>
                <div class="col-md-4">
                    <label class="form-label small mb-1" for="in_appr_note"><?= e($inAppr['is_admin_decide'] ? __('issue_appr.admin_reason') : ($inAppr['is_alternate'] ? __('issue_appr.alternate_reason') : __('in_issue_appr.annotation_optional'))) ?><?= $inNoteReq ? ' *' : '' ?></label>
                    <input type="text" name="note" id="in_appr_note" class="form-control form-control-sm" maxlength="450"
                           <?= $inNoteReq ? 'required' : '' ?>
                           placeholder="<?= e($inAppr['is_admin_decide'] ? __('issue_appr.admin_reason_placeholder') : ($inAppr['is_alternate'] ? __('in_issue_appr.alternate_reason_placeholder') : __('in_issue_appr.annotation_placeholder'))) ?>">
                </div>
                <div class="col-md-5 d-flex flex-wrap gap-2">
                    <button type="submit" name="action" value="approve" class="btn btn-success btn-sm" data-confirm="<?= e(__('in_issue_appr.confirm_approve')) ?>">
                        <i class="bi bi-check2-circle me-1"></i><?= e(__('in_issue_appr.approve')) ?>
                    </button>
                    <button type="submit" name="action" value="file" class="btn btn-kff btn-sm" title="<?= e(__('in_issue_appr.file_hint')) ?>" data-confirm="<?= e(__('in_issue_appr.confirm_file')) ?>">
                        <i class="bi bi-archive me-1"></i><?= e(__('in_issue_appr.file')) ?>
                    </button>
                    <button type="submit" name="action" value="return" class="btn btn-orange btn-sm" title="<?= e(__('in_issue_appr.return_hint')) ?>" data-confirm="<?= e(__('in_issue_appr.confirm_return')) ?>">
                        <i class="bi bi-arrow-counterclockwise me-1"></i><?= e(__('in_issue_appr.return')) ?>
                    </button>
                </div>
            </form>
        <?php endif; ?>
        <?php
        $admActionUrl = $inAction;
        require __DIR__ . '/corr_admin_override_panel.php';
        ?>
    </div>
</div>
