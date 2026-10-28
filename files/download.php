<?php
/**
 * تحميل المرفقات بعد التحقق من الجلسة والصلاحية.
 * الاستخدام: files/download.php?type=document|archive|receipt|circular&id=
 */
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/correspondence.php';
$user = require_login();

$type = strtolower(trim((string) ($_GET['type'] ?? '')));
$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0 || !in_array($type, ['document', 'archive', 'receipt', 'circular'], true)) {
    http_response_code(400);
    exit('Bad request');
}

if ($type === 'document') {
    $stmt = db()->prepare(
        "SELECT d.*, c.type AS corr_type, c.department_id, c.id AS correspondence_id
         FROM `tbldocuments` d
         JOIN `tblcorrespondence` c ON c.id = d.correspondence_id
         WHERE d.id = ?"
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        http_response_code(404);
        exit('Not found');
    }
    $corr = get_correspondence((int) $row['correspondence_id']);
    if (!$corr || (!user_can_access_correspondence($corr, $user) && !corr_cc_user_can_view($corr, $user))) {
        http_response_code(403);
        exit('Forbidden');
    }
    if (!user_can_view_corr_document($corr, $row, $user)) {
        log_security('UNAUTHORIZED_ACCESS', 'WARNING', 'confidential document=' . $id, (int) $user['id']);
        http_response_code(403);
        exit('Forbidden');
    }
    dms_stream_upload((string) $row['file_path'], (string) $row['file_name']);
}

if ($type === 'archive') {
    $stmt = db()->prepare("SELECT * FROM `tblarchive_files` WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        http_response_code(404);
        exit('Not found');
    }
    $item = get_archive_item((int) $row['archive_id']);
    if (!$item || !user_can_access_archive($item, $user)) {
        http_response_code(403);
        exit('Forbidden');
    }
    if (!user_can_view_archive_file($item, $row, $user)) {
        log_security('UNAUTHORIZED_ACCESS', 'WARNING', 'confidential archive_file=' . $id, (int) $user['id']);
        http_response_code(403);
        exit('Forbidden');
    }
    $siblings = get_archive_files((int) $item['id']);
    $display = archive_item_file_display_name($item, $row, $siblings);
    $disk = archive_stored_file_disk_path((string) $row['file_path']);
    if ($disk !== '') {
        dms_stream_file($disk, $display);
        exit;
    }
    dms_stream_upload((string) $row['file_path'], $display);
}

if ($type === 'circular') {
    $stmt = db()->prepare('SELECT * FROM `tblcircular_files` WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        http_response_code(404);
        exit('Not found');
    }
    $item = get_circular((int) $row['circular_id']);
    if (!$item || !user_can_access_circular($item, $user)) {
        http_response_code(403);
        exit('Forbidden');
    }
    dms_stream_upload((string) $row['file_path'], (string) $row['file_name']);
}

if ($type === 'receipt') {
    $corr = get_correspondence($id);
    if (!$corr || ($corr['type'] ?? '') !== 'outgoing' || !user_can_access_correspondence($corr, $user)) {
        http_response_code(403);
        exit('Forbidden');
    }
    $path = (string) ($corr['receipt_file_path'] ?? '');
    $name = (string) ($corr['receipt_file_name'] ?? 'receipt');
    if ($path === '') {
        http_response_code(404);
        exit('Not found');
    }
    dms_stream_upload($path, $name);
}

http_response_code(400);
exit('Bad request');
