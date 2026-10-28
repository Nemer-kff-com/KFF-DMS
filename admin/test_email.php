<?php
/**
 * اختبار إرسال البريد — JSON
 */
ob_start();
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/mailer.php';

header('Content-Type: application/json; charset=utf-8');

function dms_test_email_json(array $payload, int $status = 200): void
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
        dms_test_email_json(['success' => false, 'message' => 'ليست لديك صلاحية اختبار البريد.'], 403);
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        dms_test_email_json(['success' => false, 'message' => 'Method not allowed'], 405);
    }
    $token = (string) ($_POST['csrf_token'] ?? '');
    if ($token === '' || !hash_equals(csrf_token(), $token)) {
        dms_test_email_json(['success' => false, 'message' => 'رمز الأمان غير صحيح. حدّث الصفحة وحاول مرة أخرى.'], 400);
    }

    $testEmail = trim((string) ($_POST['test_email'] ?? ''));
    if ($testEmail === '' || !filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
        dms_test_email_json(['success' => false, 'message' => 'عنوان البريد غير صحيح.'], 400);
    }

    $org = org_name();
    $subject = 'اختبار بريد — ' . __('app.name');
    $htmlBody = '<div style="font-family:' . dms_mail_font_family() . ';direction:rtl;text-align:right;">'
        . '<h2>رسالة اختبار</h2>'
        . '<p>تم إرسال هذه الرسالة من صفحة إعدادات البريد في ' . htmlspecialchars(__('app.name'), ENT_QUOTES, 'UTF-8') . '.</p>'
        . '<p><strong>' . htmlspecialchars($org, ENT_QUOTES, 'UTF-8') . '</strong></p>'
        . '<p>الوقت: ' . htmlspecialchars(date('Y-m-d H:i:s'), ENT_QUOTES, 'UTF-8') . '</p>'
        . '</div>';
    $textBody = 'رسالة اختبار من ' . __('app.name') . "\n{$org}\n" . date('Y-m-d H:i:s');

    $mailError = null;
    $deptId = (int) ($_POST['department_id'] ?? 0);
    $typedPass = (string) ($_POST['smtp_password'] ?? '');
    $sent = dms_send_mail(
        $testEmail,
        $subject,
        $htmlBody,
        $textBody,
        $mailError,
        true,
        $deptId > 0 ? $deptId : null,
        $deptId > 0,
        $typedPass !== '' ? $typedPass : null
    );

    if ($sent) {
        dms_test_email_json(['success' => true, 'message' => 'تم إرسال البريد التجريبي بنجاح.']);
    }
    dms_test_email_json([
        'success' => false,
        'message' => $mailError ?: 'فشل إرسال البريد التجريبي.',
    ]);
} catch (Throwable $e) {
    error_log('dms test_email.php: ' . $e->getMessage());
    dms_test_email_json(['success' => false, 'message' => $e->getMessage()], 500);
}
