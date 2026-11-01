<?php
/**
 * تشغيل فحص التحمّل — JSON لمدير النظام فقط.
 */
ob_start();
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/load_check.php';
if (is_file(__DIR__ . '/../config/outgoing_approval.php')) {
    require_once __DIR__ . '/../config/outgoing_approval.php';
}

header('Content-Type: application/json; charset=utf-8');

function dms_load_check_json(array $payload, int $status = 200): void
{
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $user = current_user();
    if (!$user || !user_can('admin.settings', $user)) {
        dms_load_check_json(['success' => false, 'message' => __('auth.forbidden')], 403);
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        dms_load_check_json(['success' => false, 'message' => 'Method not allowed'], 405);
    }
    $token = (string) ($_POST['csrf_token'] ?? '');
    if ($token === '' || !hash_equals(csrf_token(), $token)) {
        dms_load_check_json(['success' => false, 'message' => 'رمز الأمان غير صحيح. حدّث الصفحة وحاول مرة أخرى.'], 400);
    }
    if (empty($_POST['confirm'])) {
        dms_load_check_json(['success' => false, 'message' => __('load_check.confirm_required')], 400);
    }

    $left = dms_load_check_cooldown_remaining();
    if ($left > 0) {
        dms_load_check_json([
            'success' => false,
            'message' => str_replace(':s', (string) $left, __('load_check.cooldown_left')),
            'cooldown' => true,
            'cooldown_left' => $left,
        ], 429);
    }

    @set_time_limit(120);
    $report = dms_load_check_run([
        'http' => !empty($_POST['http']),
        'db' => !empty($_POST['db']),
        'escalate' => !empty($_POST['escalate']),
        'http_n' => (int) ($_POST['http_n'] ?? 40),
        'http_c' => (int) ($_POST['http_c'] ?? 5),
        'db_loops' => (int) ($_POST['db_loops'] ?? 30),
    ]);

    dms_load_check_mark_ran();
    if (function_exists('log_activity')) {
        log_activity(
            (int) ($user['id'] ?? 0),
            null,
            'load_check',
            __('load_check.log') . ' — ' . (!empty($report['pass']) ? 'PASS' : 'FAIL')
        );
    }

    dms_load_check_json([
        'success' => true,
        'message' => !empty($report['pass']) ? __('load_check.done_pass') : __('load_check.done_fail'),
        'report' => $report,
        'cooldown' => true,
        'cooldown_left' => dms_load_check_limits()['cooldown_sec'],
    ]);
} catch (Throwable $e) {
    error_log('DMS load_check_run: ' . $e->getMessage());
    dms_load_check_json(['success' => false, 'message' => $e->getMessage()], 500);
}
