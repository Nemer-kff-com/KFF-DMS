<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/correspondence.php';
$user = require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(base_url('index.php'));
}
verify_csrf();
$id = (int) ($_POST['id'] ?? 0);
$return = dms_safe_return_path($_POST['return'] ?? '', 'index.php');
$stmt = db()->prepare("SELECT * FROM `tbldocuments` WHERE id = ?");
$stmt->execute([$id]);
$doc = $stmt->fetch();
if (!$doc) {
    flash('danger', __('documents.not_found'));
    redirect(base_url($return));
}
$corr = get_correspondence((int) $doc['correspondence_id']);
if (!$corr || !user_can_access_correspondence($corr, $user)) {
    flash('danger', __('auth.forbidden'));
    redirect(base_url($return));
}
if (correspondence_is_cancelled($corr)) {
    flash('danger', __('cancel.locked'));
    redirect(base_url($return));
}
$issuedKindEarly = function_exists('outgoing_issued_copy_kind') ? outgoing_issued_copy_kind() : 'issued_copy';
if ((string) ($doc['doc_kind'] ?? '') === $issuedKindEarly
    && function_exists('outgoing_issued_copy_mutable')
    && (!outgoing_issued_copy_mutable($corr) || !outgoing_user_can_manage_issued_copy($user, $corr))
) {
    flash('danger', __('out_prep.issued_copy_locked'));
    redirect(base_url($return));
}
if (function_exists('outgoing_draft_files_locked') && outgoing_draft_files_locked($corr)) {
    outgoing_ensure_draft_files_stamped($corr);
    $reload = db()->prepare('SELECT * FROM `tbldocuments` WHERE id = ?');
    $reload->execute([$id]);
    $fresh = $reload->fetch();
    if ($fresh) {
        $doc = $fresh;
    }
    $kind = (string) ($doc['doc_kind'] ?? '');
    $issuedKind = function_exists('outgoing_issued_copy_kind') ? outgoing_issued_copy_kind() : 'issued_copy';
    if ($kind === $issuedKind) {
        if (!outgoing_issued_copy_mutable($corr) || !outgoing_user_can_manage_issued_copy($user, $corr)) {
            flash('danger', __('out_prep.issued_copy_locked'));
            redirect(base_url($return));
        }
    } elseif (
        outgoing_approval_status_of($corr) !== 'rejected'
        || !corr_user_can_edit_in_cycle($user, $corr)
    ) {
        flash('danger', __('out_prep.draft_file_locked'));
        redirect(base_url($return));
    }
}
$canDeleteIssuedNow = (string) ($doc['doc_kind'] ?? '') === $issuedKindEarly
    && function_exists('outgoing_issued_copy_mutable')
    && outgoing_issued_copy_mutable($corr)
    && function_exists('outgoing_user_can_manage_issued_copy')
    && outgoing_user_can_manage_issued_copy($user, $corr);
if (!$canDeleteIssuedNow && !corr_user_can_edit_in_cycle($user, $corr)) {
    flash('danger', function_exists('corr_edit_cycle_block_message') ? corr_edit_cycle_block_message($corr) : __('issue_appr.edit_creator_only'));
    redirect(base_url($return));
}
$docKind = (string) ($doc['doc_kind'] ?? '');
$isCloseDoc = str_starts_with($docKind, 'close_');
$draftEditor = !$isCloseDoc
    && ($corr['type'] ?? '') === 'outgoing'
    && function_exists('outgoing_prep_is_open')
    && outgoing_prep_is_open($corr)
    && user_can('outgoing.edit', $user)
    && function_exists('corr_user_can_edit_in_cycle')
    && corr_user_can_edit_in_cycle($user, $corr);
$issuedManager = function_exists('outgoing_issued_copy_kind')
    && $docKind === outgoing_issued_copy_kind()
    && function_exists('outgoing_issued_copy_mutable')
    && outgoing_issued_copy_mutable($corr)
    && outgoing_user_can_manage_issued_copy($user, $corr);
$preReviewOutgoing = ($corr['type'] ?? '') === 'outgoing'
    && function_exists('outgoing_approval_status_of')
    && in_array(outgoing_approval_status_of($corr), ['', 'returned', 'rejected'], true)
    && corr_user_can_edit_in_cycle($user, $corr)
    && user_can('documents.upload', $user);
if (!$isCloseDoc && !user_can('documents.delete', $user) && !$draftEditor && !$issuedManager && !$preReviewOutgoing) {
    flash('danger', __('documents.delete_forbidden'));
    redirect(base_url($return));
}
if ($isCloseDoc && !user_can('documents.delete', $user) && !user_can('correspondence.close', $user)) {
    flash('danger', __('documents.delete_forbidden'));
    redirect(base_url($return));
}
$sharedPath = db()->prepare('SELECT COUNT(*) FROM `tbldocuments` WHERE file_path = ? AND id <> ?');
$sharedPath->execute([(string) $doc['file_path'], $id]);
$sharedByOther = (int) $sharedPath->fetchColumn() > 0;
if (!$sharedByOther && dms_is_safe_upload_path((string) $doc['file_path'])) {
    $path = dms_storage_disk_path((string) $doc['file_path']);
    if ($path !== '' && is_file($path)) {
        @unlink($path);
    }
}
db()->prepare("DELETE FROM `tbldocuments` WHERE id = ?")->execute([$id]);
log_activity((int)$user['id'], (int)$doc['correspondence_id'], 'delete_doc', 'حذف مرفق: ' . $doc['file_name']);
flash('success', __('documents.deleted'));
redirect(base_url($return));
