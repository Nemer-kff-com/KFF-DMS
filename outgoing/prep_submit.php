<?php
require_once __DIR__ . '/../config/auth.php';
$user = require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(base_url('outgoing/index.php'));
}
verify_csrf();
$id = (int) ($_POST['id'] ?? 0);
$corr = get_correspondence($id);
if (!$corr || ($corr['type'] ?? '') !== 'outgoing' || !user_can_access_correspondence($corr, $user)) {
    flash('danger', __('corr.not_found'));
    redirect(base_url('outgoing/index.php'));
}
try {
    $result = outgoing_prep_submit($id, $user);
    flash('success', $result === 'converted' ? outgoing_prep_converted_message($id) : __('out_prep.submitted_ok'));
} catch (Throwable $e) {
    flash('danger', $e->getMessage());
}
redirect(base_url('outgoing/view.php?id=' . $id));
