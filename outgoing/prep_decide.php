<?php
require_once __DIR__ . '/../config/auth.php';
$user = require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(base_url('outgoing/index.php'));
}
verify_csrf();
$id = (int) ($_POST['id'] ?? 0);
$decision = trim((string) ($_POST['decision'] ?? ''));
$note = trim((string) ($_POST['note'] ?? ''));
$corr = get_correspondence($id);
if (!$corr || ($corr['type'] ?? '') !== 'outgoing' || !user_can_access_correspondence($corr, $user)) {
    flash('danger', __('corr.not_found'));
    redirect(base_url('outgoing/index.php'));
}
try {
    if ($decision === 'return') {
        outgoing_prep_return($id, $user, $note);
        flash('success', __('out_prep.returned_ok'));
        $after = get_correspondence($id);
        if (!$after || !user_can_access_correspondence($after, $user)) {
            redirect(base_url('outgoing/index.php'));
        }
    } elseif ($decision === 'approve') {
        outgoing_prep_approve($id, $user, $note);
        flash('success', outgoing_prep_converted_message($id));
    } else {
        throw new RuntimeException(__('corr.invalid_request'));
    }
} catch (Throwable $e) {
    flash('danger', $e->getMessage());
}
redirect(base_url('outgoing/view.php?id=' . $id));
