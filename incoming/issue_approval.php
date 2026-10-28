<?php
/**
 * مراجعة واعتماد توجيه الوارد — POST actions
 */
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/correspondence.php';
require_once __DIR__ . '/../config/outgoing_approval.php';

$user = require_login();
verify_csrf();

$id = (int) ($_POST['id'] ?? 0);
$action = trim((string) ($_POST['action'] ?? ''));
$note = trim((string) ($_POST['note'] ?? ''));

try {
    $corr = $id > 0 ? get_correspondence($id) : null;
    if (!$corr || ($corr['type'] ?? '') !== 'incoming') {
        throw new InvalidArgumentException(__('corr.not_found'));
    }
    if ($action === 'request') {
        $newDeptId = (int) ($_POST['department_id'] ?? 0);
        $setDept = $newDeptId > 0 && can_choose_correspondence_department($user)
            && appr_with('incoming', static fn (): bool => outgoing_user_can_request_approval($user, $corr));
        $ccIds = null;
        if (isset($_POST['cc_present']) && corr_cc_user_can_edit($user, $corr)) {
            $ccIds = corr_cc_ids_from_request($_POST, (int) ($corr['created_by'] ?? 0));
            corr_cc_assert_allowed('incoming', $ccIds);
        }
        $blocker = outgoing_approval_request_blocker($setDept ? array_merge($corr, ['department_id' => $newDeptId]) : $corr, $user);
        if ($blocker !== '') {
            throw new RuntimeException($blocker);
        }
        if ($setDept) {
            incoming_approval_set_department($id, $user, $newDeptId, (int) ($corr['department_id'] ?? 0));
        }
        if ($ccIds !== null) {
            corr_cc_set_pending($id, $ccIds, (int) $user['id']);
        }
        outgoing_approval_request($id, $user);
        flash('success', appr_with('incoming', static fn (): string => outgoing_approval_requested_message(get_correspondence($id) ?: [])));
    } elseif ($action === 'release') {
        $released = incoming_release_desk($id, $user);
        $fresh = get_correspondence($id) ?: [];
        flash('success', $released === 'department'
            ? __('in_issue_appr.desk_released')
            : appr_with('incoming', static fn (): string => outgoing_approval_requested_message($fresh)));
    } elseif ($action === 'review_pass') {
        outgoing_review_decide($id, $user, true, $note);
        flash('success', __('issue_review.passed_ok'));
    } elseif ($action === 'review_return') {
        outgoing_review_decide($id, $user, false, $note);
        flash('success', __('in_issue_review.returned_ok'));
    } elseif (in_array($action, ['approve', 'file', 'return'], true)) {
        incoming_approval_decide($id, $user, $action, $note);
        flash('success', __(match ($action) {
            'file' => 'in_issue_appr.filed_ok',
            'return' => 'in_issue_appr.returned_ok',
            default => 'in_issue_appr.approved_ok',
        }));
    } elseif ($action === 'admin_reassign') {
        appr_admin_reassign($id, $user, (int) ($_POST['target_user_id'] ?? 0), $note);
        flash('success', __('issue_appr.admin_reassigned_ok'));
    } else {
        throw new InvalidArgumentException(__('profile.invalid_action'));
    }
} catch (Throwable $e) {
    flash('danger', $e->getMessage());
}

redirect(base_url('incoming/view.php?id=' . $id));
