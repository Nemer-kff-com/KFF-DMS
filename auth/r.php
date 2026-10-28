<?php
/**
 * رابط قصير لإعادة التعيين: /auth/r.php?t=TOKEN
 */
require_once __DIR__ . '/../config/auth.php';

$token = trim($_GET['t'] ?? $_GET['token'] ?? '');
$qs = http_build_query(['token' => $token]);
redirect(base_url('auth/reset_password.php' . ($token !== '' ? '?' . $qs : '')));
