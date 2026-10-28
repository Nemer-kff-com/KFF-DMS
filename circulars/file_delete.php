<?php
require_once __DIR__ . '/../config/auth.php';
$user = require_login();
if (!can_edit_circulars($user)) {
    flash('danger', __('documents.delete_forbidden'));
    redirect(base_url('circulars/index.php'));
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(base_url('circulars/index.php'));
}
verify_csrf();
$fileId = (int) ($_POST['id'] ?? 0);
$circularId = (int) ($_POST['circular_id'] ?? 0);
$item = get_circular($circularId);
if (!$item || !user_can_edit_circular($item, $user)) {
    flash('danger', __('circular.not_found'));
    redirect(base_url('circulars/index.php'));
}
if (($item['status'] ?? '') === 'issued' && !circular_is_electronic($item) && count(get_circular_files($circularId)) <= 1) {
    flash('danger', __('circular.keep_issued_file'));
    redirect(base_url('circulars/view.php?id=' . $circularId));
}
$deleted = delete_circular_file($fileId);
if (!$deleted) {
    flash('danger', __('documents.not_found'));
} else {
    log_activity((int) $user['id'], null, 'delete_doc', (string) ($deleted['file_name'] ?? ''), null, $circularId);
    flash('success', __('documents.deleted'));
}
redirect(base_url('circulars/view.php?id=' . $circularId));
