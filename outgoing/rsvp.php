<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/correspondence.php';

$user = require_login();
if (!can_edit_correspondence($user)) {
    flash('danger', __('auth.forbidden'));
    redirect(base_url('outgoing/index.php'));
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    redirect(base_url('outgoing/index.php'));
}
verify_csrf();

$id = (int) ($_POST['correspondence_id'] ?? 0);
$corr = $id ? get_correspondence($id) : null;
if (!$corr || ($corr['type'] ?? '') !== 'outgoing' || !user_can_access_correspondence($corr, $user)) {
    flash('danger', __('corr.not_found'));
    redirect(base_url('outgoing/index.php'));
}

try {
    correspondence_assert_mutable($corr);
    save_correspondence_rsvp(
        $corr,
        (string) ($_POST['rsvp_response'] ?? ''),
        (string) ($_POST['rsvp_note'] ?? ''),
        'staff',
        $user
    );
    flash('success', __('rsvp.saved'));
} catch (Throwable $e) {
    flash('danger', $e->getMessage());
}
redirect(base_url('outgoing/view.php?id=' . $id));
