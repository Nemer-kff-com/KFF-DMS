<?php
require_once __DIR__ . '/../config/auth.php';
$reason = $_GET['reason'] ?? '';
logout_user();
if ($reason === 'timeout') {
    flash('warning', __('auth.session_timeout'));
} else {
    flash('success', __('auth.logout_ok'));
}
redirect(base_url('auth/login.php'));
