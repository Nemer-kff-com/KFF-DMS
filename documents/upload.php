<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/correspondence.php';
$user = require_login();
if (!user_can('documents.upload', $user)) {
    flash('danger', __('auth.forbidden'));
    redirect(base_url('index.php'));
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(base_url('index.php'));
}
verify_csrf();
$corrId = (int) ($_POST['correspondence_id'] ?? 0);
$return = dms_safe_return_path($_POST['return'] ?? '', 'index.php');
$corr = get_correspondence($corrId);
if (!$corr || !user_can_access_correspondence($corr, $user)) {
    flash('danger', __('corr.not_found'));
    redirect(base_url($return));
}
try {
    correspondence_assert_mutable($corr);
    $uploads = function_exists('dms_normalize_upload_files')
        ? dms_normalize_upload_files($_FILES['attachments'] ?? $_FILES['attachment'] ?? null)
        : [];
    if ($uploads === []) {
        throw new InvalidArgumentException(__('file.placeholder_one'));
    }
    $forcedKind = null;
    $issuedBypass = false;
    $wantsIssued = function_exists('outgoing_issued_copy_kind')
        && trim((string) ($_POST['doc_kind'] ?? '')) === outgoing_issued_copy_kind();
    $needsIssued = $wantsIssued
        && function_exists('outgoing_needs_issued_copy')
        && outgoing_needs_issued_copy($corr);
    if (function_exists('outgoing_draft_files_locked') && outgoing_draft_files_locked($corr)) {
        outgoing_ensure_draft_files_stamped($corr);
        $canIssued = $needsIssued
            && outgoing_issued_copy_mutable($corr)
            && outgoing_user_can_manage_issued_copy($user, $corr);
        if (!$canIssued) {
            throw new RuntimeException($wantsIssued && !outgoing_issued_copy_mutable($corr)
                ? __('out_prep.issued_copy_locked')
                : __('out_prep.draft_file_locked'));
        }
        $issuedBypass = !corr_user_can_edit_in_cycle($user, $corr);
        $forcedKind = outgoing_issued_copy_kind();
    } elseif ($needsIssued) {
        if (!outgoing_issued_copy_mutable($corr) || !outgoing_user_can_manage_issued_copy($user, $corr)) {
            throw new RuntimeException(__('out_prep.issued_copy_locked'));
        }
        $issuedBypass = !corr_user_can_edit_in_cycle($user, $corr);
        $forcedKind = outgoing_issued_copy_kind();
    }
    if (!$issuedBypass) {
        corr_assert_can_edit_in_cycle($user, $corr);
    }
    foreach ($uploads as $uploadFile) {
        upload_document($uploadFile, $corrId, (int) $user['id'], $forcedKind);
    }
    if ($forcedKind === null
        && function_exists('correspondence_enclosures_from_request')
        && function_exists('correspondence_enclosures_map')
        && function_exists('save_correspondence_enclosures')
    ) {
        $addedEnclosures = correspondence_enclosures_from_request($_POST);
        if ($addedEnclosures !== []) {
            $enclosureMap = correspondence_enclosures_map($corrId);
            foreach ($addedEnclosures as $typeId => $qty) {
                $typeId = (int) $typeId;
                $enclosureMap[$typeId] = min(9999, (int) ($enclosureMap[$typeId] ?? 0) + max(1, (int) $qty));
            }
            save_correspondence_enclosures($corrId, $enclosureMap);
        }
    }
    log_activity((int)$user['id'], $corrId, 'upload', 'رفع مرفق');
    $issuedUploaded = $forcedKind === (function_exists('outgoing_issued_copy_kind') ? outgoing_issued_copy_kind() : 'issued_copy')
        && function_exists('outgoing_approval_status_of')
        && outgoing_approval_status_of(get_correspondence($corrId) ?: $corr) === 'pending_copy';
    flash('success', $issuedUploaded ? __('out_prep.issued_copy_uploaded') : __('documents.uploaded'));
} catch (Throwable $e) {
    flash('danger', $e->getMessage());
}
redirect(base_url($return));
