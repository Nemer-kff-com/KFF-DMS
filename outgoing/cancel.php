<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/correspondence.php';
$user = require_login();
if (!user_can('outgoing.cancel', $user)) {
    flash('danger', __('auth.forbidden'));
    redirect(base_url('outgoing/index.php'));
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(base_url('outgoing/index.php'));
}
verify_csrf();
$id = (int) ($_POST['id'] ?? 0);
$kind = (string) ($_POST['cancel_kind'] ?? 'void');
$reason = (string) ($_POST['cancel_reason'] ?? '');
try {
    cancel_outgoing_correspondence($id, $kind, $reason, $user);
    flash('success', __('cancel.done'));
} catch (Throwable $e) {
    flash('danger', $e->getMessage());
}
redirect(base_url('outgoing/view.php?id=' . $id));
