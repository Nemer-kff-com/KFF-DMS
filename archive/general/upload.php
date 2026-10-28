<?php
require_once __DIR__ . '/../../config/auth.php';
$user = require_archive_section('general', 'edit');
if (!can_edit_archive($user)) {
    flash('danger', __('auth.forbidden'));
    redirect(base_url('archive/general/index.php'));
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(base_url('archive/general/index.php'));
}
verify_csrf();
$archiveId = (int) ($_POST['archive_id'] ?? 0);
$item = get_archive_item($archiveId);
if (!$item || !user_can_edit_archive($item, $user)) {
    flash('danger', __('archive.not_found'));
    redirect(base_url('archive/general/index.php'));
}
try {
    $uploaded = 0;
    if (!empty($_FILES['attachments']['name']) && is_array($_FILES['attachments']['name'])) {
        $count = count($_FILES['attachments']['name']);
        for ($i = 0; $i < $count; $i++) {
            if (($_FILES['attachments']['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $one = [
                'name' => $_FILES['attachments']['name'][$i],
                'type' => $_FILES['attachments']['type'][$i],
                'tmp_name' => $_FILES['attachments']['tmp_name'][$i],
                'error' => $_FILES['attachments']['error'][$i],
                'size' => $_FILES['attachments']['size'][$i],
            ];
            upload_archive_file($one, $archiveId, (int) $user['id']);
            $uploaded++;
        }
    } elseif (!empty($_FILES['attachment']['name'])) {
        upload_archive_file($_FILES['attachment'], $archiveId, (int) $user['id']);
        $uploaded = 1;
    }
    flash('success', $uploaded ? __('archive.files_uploaded') : __('archive.no_file_selected'));
} catch (Throwable $e) {
    flash('danger', $e->getMessage());
}
redirect(base_url('archive/general/view.php?id=' . $archiveId));
