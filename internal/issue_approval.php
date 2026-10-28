<?php
/**
 * مراجعة واعتماد المعاملة الداخلية — POST actions
 */
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/correspondence.php';
require_once __DIR__ . '/../config/outgoing_approval.php';
require_once __DIR__ . '/../config/internal.php';

$user = require_login();
verify_csrf();

$id = (int) ($_POST['id'] ?? 0);
$action = trim((string) ($_POST['action'] ?? ''));
$note = trim((string) ($_POST['note'] ?? ''));

try {
    $corr = $id > 0 ? get_correspondence($id) : null;
    if (!$corr || ($corr['type'] ?? '') !== 'internal') {
        throw new InvalidArgumentException(__('corr.not_found'));
    }
    if ($action === 'request') {
        $ccIds = null;
        if (isset($_POST['cc_present']) && corr_cc_user_can_edit($user, $corr)) {
            $ccIds = corr_cc_ids_from_request($_POST, (int) ($corr['created_by'] ?? 0));
            corr_cc_assert_allowed('internal', $ccIds);
        }
        $blocker = outgoing_approval_request_blocker($corr, $user);
        if ($blocker !== '') {
            throw new RuntimeException($blocker);
        }
        $newTo = (int) ($_POST['to_department_id'] ?? 0);
        if ($newTo > 0) {
            internal_approval_set_target($id, $user, $newTo);
        }
        if ($ccIds !== null) {
            corr_cc_set_pending($id, $ccIds, (int) $user['id']);
        }
        outgoing_approval_request($id, $user);
        flash('success', appr_with('internal', static fn (): string => outgoing_approval_requested_message(get_correspondence($id) ?: [])));
    } elseif ($action === 'review_pass') {
        outgoing_review_decide($id, $user, true, $note);
        flash('success', __('issue_review.passed_ok'));
    } elseif ($action === 'review_return') {
        outgoing_review_decide($id, $user, false, $note);
        flash('success', __('int_issue_appr.review_returned_ok'));
    } elseif (in_array($action, ['approve', 'return'], true)) {
        internal_approval_decide($id, $user, $action, $note);
        flash('success', __($action === 'return' ? 'int_issue_appr.returned_ok' : 'int_issue_appr.approved_ok'));
    } elseif ($action === 'admin_reassign') {
        appr_admin_reassign($id, $user, (int) ($_POST['target_user_id'] ?? 0), $note);
        flash('success', __('issue_appr.admin_reassigned_ok'));
    } else {
        throw new InvalidArgumentException(__('profile.invalid_action'));
    }
} catch (Throwable $e) {
    flash('danger', $e->getMessage());
}

redirect(base_url('internal/view.php?id=' . $id));
