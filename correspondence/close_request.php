<?php
require_once __DIR__ . '/../config/auth.php';
$user = require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(base_url('index.php'));
}
verify_csrf();
$action = (string) ($_POST['action'] ?? '');
$corrId = (int) ($_POST['id'] ?? 0);
$reqId = (int) ($_POST['request_id'] ?? 0);

$viewUrl = static function (int $id): string {
    $corr = $id > 0 ? get_correspondence($id) : null;
    $folder = correspondence_folder_for_type((string) ($corr['type'] ?? 'incoming'));
    return base_url($folder . '/view.php?id=' . $id);
};

try {
    switch ($action) {
        case 'reject':
            $corrId = close_request_reject($reqId, (string) ($_POST['decision_note'] ?? ''), $user);
            flash('success', __('close_req.rejected'));
            break;
        case 'withdraw':
            $corrId = close_request_withdraw($reqId, $user);
            flash('success', __('close_req.withdrawn'));
            break;
        default:
            flash('danger', __('auth.forbidden'));
    }
} catch (Throwable $e) {
    flash('danger', $e->getMessage());
}
redirect($viewUrl($corrId));
