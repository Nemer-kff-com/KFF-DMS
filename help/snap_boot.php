<?php
/**
 * تمهيد جلسة لالتقاط لقطات الدليل — localhost فقط.
 * مثال: help/snap_boot.php?sid=SESSION&to=index.php
 */
$host = (string) ($_SERVER['HTTP_HOST'] ?? '');
if (!preg_match('/^(localhost|127\.0\.0\.1)(:\d+)?$/i', $host)) {
    http_response_code(403);
    exit('local only');
}

$sid = preg_replace('/[^a-zA-Z0-9,-]/', '', (string) ($_GET['sid'] ?? ''));
$to = str_replace('\\', '/', trim((string) ($_GET['to'] ?? 'index.php')));
$to = ltrim($to, '/');
$anchor = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($_GET['anchor'] ?? ''));
$full = ((string) ($_GET['full'] ?? '')) === '1';
if ($sid === '' || str_contains($to, '..') || !preg_match('#^[a-zA-Z0-9/_.,?&=%-]+\.php(\?[a-zA-Z0-9/_.,?&=%-]*)?$#', $to)) {
    http_response_code(400);
    exit('bad params');
}
// مرّر مرساة التمرير كمعامل صفحة ليلتقطها الـ footer (أوثق من #hash في headless)
if ($anchor !== '' && !str_contains($to, 'guide_anchor=')) {
    $to .= (str_contains($to, '?') ? '&' : '?') . 'guide_anchor=' . rawurlencode($anchor);
}
if ($full && !str_contains($to, 'guide_full=')) {
    $to .= (str_contains($to, '?') ? '&' : '?') . 'guide_full=1';
}

$sessionFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'sessions' . DIRECTORY_SEPARATOR . 'sess_' . $sid;
if (!is_file($sessionFile) || filesize($sessionFile) < 20) {
    http_response_code(403);
    exit('session missing');
}

// اربط الجلسة قبل auth حتى لا تُنشأ جلسة فارغة جديدة
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/security.php';
dms_configure_session();
if (session_status() === PHP_SESSION_NONE) {
    session_id($sid);
    session_start();
}

require_once __DIR__ . '/../config/auth.php';

$user = function_exists('current_user') ? current_user() : null;
if (!$user) {
    http_response_code(403);
    exit('not logged in');
}

header('Cache-Control: no-store');
header('Location: ' . base_url($to), true, 302);
exit;
