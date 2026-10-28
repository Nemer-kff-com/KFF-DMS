<?php
/**
 * موافقة إصدار الصادر — POST actions (معاملة واحدة أو دفعة جماعية)
 */
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/correspondence.php';
require_once __DIR__ . '/../config/outgoing_approval.php';

$user = require_login();
verify_csrf();

$id = (int) ($_POST['id'] ?? 0);
$batchId = (int) ($_POST['batch_id'] ?? 0);
$action = trim((string) ($_POST['action'] ?? ''));
$note = trim((string) ($_POST['note'] ?? ''));

$back = $batchId > 0
    ? base_url('outgoing/batch.php?id=' . $batchId)
    : base_url('outgoing/view.php?id=' . $id);

try {
    if ($batchId > 0) {
        if ($action === 'request') {
            $n = outgoing_batch_request_approvals($batchId, $user);
            flash('success', str_replace(':count', (string) $n, __('issue_appr.batch_requested_ok')));
        } elseif ($action === 'approve') {
            $n = outgoing_batch_decide($batchId, $user, true, $note);
            flash('success', str_replace(':count', (string) $n, __('issue_appr.batch_approved_ok')));
        } elseif ($action === 'reject') {
            $n = outgoing_batch_decide($batchId, $user, false, $note);
            flash('success', str_replace(':count', (string) $n, __('issue_appr.batch_rejected_ok')));
        } elseif ($action === 'review_pass' || $action === 'review_return') {
            $pass = $action === 'review_pass';
            $n = outgoing_batch_review($batchId, $user, $pass, $note);
            flash('success', str_replace(':count', (string) $n, __($pass ? 'issue_review.batch_passed_ok' : 'issue_review.batch_returned_ok')));
        } else {
            throw new InvalidArgumentException(__('profile.invalid_action'));
        }
    } else {
        if ($id <= 0) {
            throw new InvalidArgumentException(__('corr.not_found'));
        }
        if ($action === 'request') {
            outgoing_approval_request($id, $user);
            flash('success', outgoing_approval_requested_message(get_correspondence($id) ?: []));
        } elseif ($action === 'review_pass') {
            outgoing_review_decide($id, $user, true, $note);
            flash('success', __('issue_review.passed_ok'));
        } elseif ($action === 'review_return') {
            outgoing_review_decide($id, $user, false, $note);
            flash('success', __('issue_review.returned_ok'));
        } elseif ($action === 'approve') {
            outgoing_approval_decide($id, $user, true, $note);
            flash('success', __('issue_appr.approved_ok'));
        } elseif ($action === 'reject') {
            outgoing_approval_decide($id, $user, false, $note);
            flash('success', __('issue_appr.rejected_ok'));
        } elseif ($action === 'forward_copy') {
            $corr = get_correspondence($id);
            if (!$corr || !outgoing_user_can_forward_issued_copy($user, $corr)) {
                throw new RuntimeException(__('auth.forbidden'));
            }
            if (!function_exists('outgoing_has_issued_copy') || !outgoing_has_issued_copy($id)) {
                throw new RuntimeException(__('out_prep.forward_copy_need_file'));
            }
            if (!outgoing_release_issued_copy_to_approver($id)) {
                throw new RuntimeException(__('issue_appr.no_eligible'));
            }
            flash('success', __('out_prep.issued_copy_forwarded'));
        } elseif ($action === 'admin_reassign') {
            appr_admin_reassign($id, $user, (int) ($_POST['target_user_id'] ?? 0), $note);
            flash('success', __('issue_appr.admin_reassigned_ok'));
        } else {
            throw new InvalidArgumentException(__('profile.invalid_action'));
        }
    }
} catch (Throwable $e) {
    flash('danger', $e->getMessage());
}

redirect($back);
