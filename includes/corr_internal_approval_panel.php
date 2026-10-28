<?php
/**
 * لوحة مراجعة واعتماد المعاملة الداخلية على صفحة العرض
 * يتطلب: $corr, $user
 */
if (($corr['type'] ?? '') !== 'internal' || !function_exists('internal_approval_decide')) {
    return;
}

$intAppr = appr_with('internal', static function () use ($corr, $user): array {
    $status = outgoing_approval_status_of($corr);
    $canDecide = outgoing_user_can_decide_approval($user, $corr);
    $isAdminDecide = $canDecide && appr_is_admin_decide_override($user, $corr);
    $isAlternate = $canDecide && !$isAdminDecide && outgoing_user_is_alternate_approver($user, $corr);
    $canReview = outgoing_user_can_review($user, $corr);
    $reviewAssignee = $status === 'pending_review' ? (int) ($corr['issue_approval_assignee_id'] ?? 0) : 0;
    $reviewAssigneeName = $reviewAssignee > 0 ? outgoing_user_name_by_id($reviewAssignee) : '';
    $reviewSodBlocked = !$canReview && outgoing_user_review_blocked_by_sod($user, $corr);
    $waitingAssignee = in_array($status, ['pending_sg', 'pending_deputy', 'pending_alternate'], true)
        ? outgoing_user_name_by_id((int) ($corr['issue_approval_assignee_id'] ?? 0))
        : '';
    return [
        'status' => $status,
        'label' => outgoing_approval_label($status),
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
        'request_label' => appr__('issue_review.resubmit'),
        'alternate_hint' => outgoing_appr_text('issue_appr.alternate_decide_hint'),
        'waiting' => in_array($status, ['pending_sg', 'pending_deputy', 'pending_alternate'], true),
        'waiting_assignee' => $waitingAssignee,
    ];
});

if ($intAppr['status'] === '') {
    return;
}
$intStatus = $intAppr['status'];
$intBadge = match ($intStatus) {
    'approved' => 'text-bg-success',
    'returned' => 'text-bg-info',
    default => 'text-bg-warning',
};
$intAction = base_url('internal/issue_approval.php');
$intFromDept = (int) ($corr['issuing_department_id'] ?? 0);
$intTargetId = internal_target_department_id($corr);
$intTargetName = '';
$intTargetOffice = '';
$intDepts = departments_list();
foreach ($intDepts as $d) {
    if ((int) $d['id'] === $intTargetId) {
        $intTargetName = department_person_option_label($d);
        $intTargetOffice = department_officer_subtitle($d);
        break;
    }
}
?>
<div class="card-panel mb-3 no-print">
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
        <span><i class="bi bi-shield-check me-1"></i><?= e(__('int_issue_appr.panel_title')) ?></span>
        <span class="badge <?= $intBadge ?>"><?= e($intAppr['label']) ?></span>
    </div>
    <div class="card-body">
        <?php if ($intStatus === 'returned'): ?>
            <div class="alert alert-warning py-2 small mb-2">
                <?php if ($intAppr['returned_by_approver']): ?>
                    <div class="fw-semibold"><i class="bi bi-arrow-counterclockwise me-1"></i><?= e(__('int_issue_appr.returned_by_approver')) ?><?= $intAppr['decided_by'] !== '' ? ' ' . e($intAppr['decided_by']) : '' ?></div>
                    <?php if (!empty($corr['issue_approval_note'])): ?>
                        <div class="mt-1"><?= e((string) $corr['issue_approval_note']) ?></div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="fw-semibold"><i class="bi bi-arrow-counterclockwise me-1"></i><?= e(__('issue_review.returned_by')) ?><?= $intAppr['reviewed_by'] !== '' ? ' ' . e($intAppr['reviewed_by']) : '' ?></div>
                    <?php if (!empty($corr['issue_review_note'])): ?>
                        <div class="mt-1"><?= e((string) $corr['issue_review_note']) ?></div>
                    <?php endif; ?>
                <?php endif; ?>
                <div class="mt-1 text-muted"><?= e(__('int_issue_appr.returned_hint')) ?></div>
            </div>
        <?php elseif ($intStatus === 'pending_review'): ?>
            <div class="alert <?= $intAppr['review_sod'] ? 'alert-warning' : 'alert-info' ?> py-2 small mb-2">
                <i class="bi bi-clipboard-check me-1"></i><?= e($intAppr['review_waiting']) ?>
            </div>
        <?php else: ?>
            <?php if ($intAppr['reviewed_by'] !== '' && !empty($corr['issue_reviewed_at'])): ?>
                <div class="small mb-1"><strong><?= e(__('issue_review.reviewed_by')) ?>:</strong>
                    <?= e($intAppr['reviewed_by']) ?> — <span dir="ltr"><?= e((string) $corr['issue_reviewed_at']) ?></span>
                    <?php if (!empty($corr['issue_review_note'])): ?>
                        <div class="text-muted"><?= e((string) $corr['issue_review_note']) ?></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <?php if ($intAppr['waiting']): ?>
                <div class="alert alert-info py-2 small mb-2"><i class="bi bi-hourglass-split me-1"></i><?= e(__('int_issue_appr.held_hint')) ?><?= $intAppr['waiting_assignee'] !== '' ? ' — ' . e($intAppr['waiting_assignee']) : '' ?></div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($intStatus !== 'approved'): ?>
            <div class="small mb-1"><strong><?= e(__('int_issue_appr.route_to')) ?>:</strong> <?php render_officer_with_office($intTargetName !== '' ? $intTargetName : '—', $intTargetOffice); ?></div>
        <?php endif; ?>
        <?php if (!empty($corr['issue_approval_escalated_at'])): ?>
            <div class="small mb-1"><strong><?= e(__('issue_appr.escalated_at')) ?>:</strong>
                <span dir="ltr"><?= e((string) $corr['issue_approval_escalated_at']) ?></span>
            </div>
        <?php endif; ?>
        <?php if ($intStatus === 'approved'): ?>
            <div class="small mb-1"><strong><?= e(__('int_issue_appr.decided_by')) ?>:</strong>
                <?= e($intAppr['decided_by']) ?>
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
        <?php if ($intAppr['sod_blocked']): ?>
            <div class="alert alert-warning py-2 small mb-2"><?= e(__('int_issue_appr.sod_blocked')) ?></div>
        <?php endif; ?>

        <?php if ($intAppr['can_request']): ?>
            <?php $intCcEdit = function_exists('corr_cc_user_can_edit') && corr_cc_user_can_edit($user, $corr); ?>
            <form method="post" action="<?= e($intAction) ?>" class="row g-2 <?= $intCcEdit ? 'align-items-start' : 'align-items-end' ?> mt-1">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $corr['id'] ?>">
                <input type="hidden" name="action" value="request">
                <div class="col-md-5">
                    <label class="form-label small mb-1" for="int_request_dept"><?= e(__('int_issue_appr.send_to_dept')) ?></label>
                    <select name="to_department_id" id="int_request_dept" class="form-select form-select-sm corr-officer-pick">
                        <?php foreach ($intDepts as $d): ?>
                            <?php if ((int) $d['id'] === $intFromDept) { continue; } ?>
                            <option value="<?= (int) $d['id'] ?>" title="<?= e(department_display_name($d)) ?>" data-office="<?= e(department_officer_subtitle($d)) ?>" <?= (int) $d['id'] === $intTargetId ? 'selected' : '' ?>>
                                <?= e(department_person_option_label($d)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($intCcEdit): ?>
                <div class="col-md-7">
                    <label class="form-label small mb-1" for="int_request_cc" title="<?= e(__('cc.field_hint')) ?>"><?= e(__('cc.field')) ?></label>
                    <?php
                    $ccType = 'internal';
                    $ccInputId = 'int_request_cc';
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
                    <button type="submit" class="btn btn-kff btn-sm">
                        <i class="bi bi-send-check me-1"></i><?= e($intAppr['request_label']) ?>
                    </button>
                </div>
            </form>
        <?php endif; ?>

        <?php if ($intAppr['can_review']): ?>
            <form method="post" action="<?= e($intAction) ?>" class="row g-2 align-items-end mt-2">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $corr['id'] ?>">
                <?php if ($intAppr['is_admin_review']): ?>
                    <div class="col-12"><div class="alert alert-warning py-2 small mb-0"><i class="bi bi-person-gear me-1"></i><?= e(__('issue_appr.admin_acting_hint')) ?></div></div>
                <?php endif; ?>
                <div class="col-md-6">
                    <label class="form-label small mb-1" for="int_review_note"><?= e($intAppr['is_admin_review'] ? __('issue_appr.admin_reason') . ' *' : __('issue_review.note')) ?></label>
                    <input type="text" name="note" id="int_review_note" class="form-control form-control-sm" maxlength="450"
                           <?= $intAppr['is_admin_review'] ? 'required' : '' ?>
                           placeholder="<?= e($intAppr['is_admin_review'] ? __('issue_appr.admin_reason_placeholder') : __('issue_review.note_placeholder')) ?>">
                </div>
                <div class="col-md-6 d-flex flex-wrap gap-2">
                    <button type="submit" name="action" value="review_pass" class="btn btn-success btn-sm">
                        <i class="bi bi-check2-circle me-1"></i><?= e(__('issue_review.pass')) ?>
                    </button>
                    <button type="submit" name="action" value="review_return" class="btn btn-orange btn-sm"
                            data-note-required="int_review_note"
                            data-note-required-msg="<?= e(__('issue_review.note_required')) ?>">
                        <i class="bi bi-arrow-counterclockwise me-1"></i><?= e(__('issue_review.return')) ?>
                    </button>
                </div>
            </form>
        <?php endif; ?>

        <?php if ($intAppr['can_decide']): ?>
            <?php if ($intAppr['is_admin_decide']): ?>
                <div class="alert alert-warning py-2 small mb-2"><i class="bi bi-person-gear me-1"></i><?= e(__('issue_appr.admin_acting_hint')) ?></div>
            <?php elseif ($intAppr['is_alternate']): ?>
                <div class="alert alert-info py-2 small mb-2"><?= e($intAppr['alternate_hint']) ?></div>
            <?php endif; ?>
            <?php $intNoteReq = $intAppr['is_alternate'] || $intAppr['is_admin_decide']; ?>
            <form method="post" action="<?= e($intAction) ?>" class="row g-2 align-items-end mt-2">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $corr['id'] ?>">
                <div class="col-md-6">
                    <label class="form-label small mb-1" for="int_appr_note"><?= e($intAppr['is_admin_decide'] ? __('issue_appr.admin_reason') : ($intAppr['is_alternate'] ? __('issue_appr.alternate_reason') : __('int_issue_appr.note_label'))) ?><?= $intNoteReq ? ' *' : '' ?></label>
                    <input type="text" name="note" id="int_appr_note" class="form-control form-control-sm" maxlength="450"
                           <?= $intNoteReq ? 'required' : '' ?>
                           placeholder="<?= e($intAppr['is_admin_decide'] ? __('issue_appr.admin_reason_placeholder') : __('int_issue_appr.note_placeholder')) ?>">
                </div>
                <div class="col-md-6 d-flex flex-wrap gap-2">
                    <button type="submit" name="action" value="approve" class="btn btn-success btn-sm">
                        <i class="bi bi-check2-circle me-1"></i><?= e(__('int_issue_appr.approve')) ?>
                    </button>
                    <button type="submit" name="action" value="return" class="btn btn-orange btn-sm" title="<?= e(__('int_issue_appr.return_hint')) ?>">
                        <i class="bi bi-arrow-counterclockwise me-1"></i><?= e(__('int_issue_appr.return')) ?>
                    </button>
                </div>
            </form>
        <?php endif; ?>
        <?php
        $admActionUrl = $intAction;
        require __DIR__ . '/corr_admin_override_panel.php';
        ?>
    </div>
</div>
