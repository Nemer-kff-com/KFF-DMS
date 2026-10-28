<?php
require_once __DIR__ . '/../config/auth.php';
$user = require_login();
if (!can_edit_circulars($user)) {
    flash('danger', __('auth.forbidden'));
    redirect(base_url('circulars/index.php'));
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(base_url('circulars/index.php'));
}
verify_csrf();
$circularId = (int) ($_POST['circular_id'] ?? 0);
$item = get_circular($circularId);
if (!$item || !user_can_edit_circular($item, $user)) {
    flash('danger', __('circular.not_found'));
    redirect(base_url('circulars/index.php'));
}
try {
    $uploaded = circular_upload_from_request($circularId, (int) $user['id']);
    flash('success', $uploaded ? __('circular.files_uploaded') : __('circular.no_file_selected'));
} catch (Throwable $e) {
    flash('danger', $e->getMessage());
}
redirect(base_url('circulars/view.php?id=' . $circularId));
