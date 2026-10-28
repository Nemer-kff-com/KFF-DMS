<?php
/**
 * اختبار إرسال واتساب — JSON
 */
ob_start();
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/whatsapp_sender.php';

header('Content-Type: application/json; charset=utf-8');

function dms_test_whatsapp_json(array $payload, int $status = 200): void
{
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $user = current_user();
    if (!$user || !user_can('admin.settings', $user)) {
        dms_test_whatsapp_json(['success' => false, 'message' => 'ليست لديك صلاحية اختبار واتساب.'], 403);
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        dms_test_whatsapp_json(['success' => false, 'message' => 'Method not allowed'], 405);
    }
    $token = (string) ($_POST['csrf_token'] ?? '');
    if ($token === '' || !hash_equals(csrf_token(), $token)) {
        dms_test_whatsapp_json(['success' => false, 'message' => 'رمز الأمان غير صحيح. حدّث الصفحة وحاول مرة أخرى.'], 400);
    }

    $testMobile = trim((string) ($_POST['test_mobile'] ?? ''));
    $digits = dms_whatsapp_normalize_msisdn($testMobile);
    if ($digits === '' || strlen($digits) < 8) {
        dms_test_whatsapp_json(['success' => false, 'message' => __('wa.err_mobile')], 400);
    }

    $settings = dms_get_whatsapp_settings();
    $provider = (string) ($settings['provider'] ?? 'twilio');
    $providerLabel = dms_whatsapp_provider_label($provider, $settings);
    $testLang = strtolower(trim((string) ($_POST['test_lang'] ?? 'ar'))) === 'en' ? 'en' : 'ar';
    $org = org_name();

    $sendMode = 'text';
    $templateId = dms_whatsapp_template_id($settings, $testLang);
    if ($provider === 'meta' && $templateId !== '') {
        $vars = $testLang === 'en'
            ? ['name' => 'Test recipient', 'number' => 'TEST', 'link' => '']
            : ['name' => 'مستلم تجريبي', 'number' => 'TEST', 'link' => ''];
        $result = dms_whatsapp_send_template($digits, $testLang, $vars);
        $sendMode = 'template';
    } else {
        $message = 'اختبار واتساب — ' . __('app.name')
            . "\n" . $org
            . "\n" . 'المزود: ' . $providerLabel
            . "\n" . 'الوقت: ' . date('Y-m-d H:i:s');
        $result = dms_whatsapp_send_text($digits, $message);
    }

    $ok = !empty($result['ok']);
    $detailParts = [];
    if (!empty($result['http'])) {
        $detailParts[] = 'HTTP ' . (int) $result['http'];
    }
    if (!empty($result['error'])) {
        $detailParts[] = (string) $result['error'];
    }
    $preview = trim((string) ($result['response'] ?? ''));
    if ($preview !== '') {
        if (strlen($preview) > 400) {
            $preview = substr($preview, 0, 400) . '…';
        }
        $detailParts[] = $preview;
    }

    $successMessage = $ok
        ? ('تم إرسال رسالة الاختبار عبر ' . $providerLabel . ' إلى +' . $digits)
        : ('فشل اختبار واتساب عبر ' . $providerLabel);
    if ($ok && $sendMode === 'template') {
        $successMessage .= ' (قالب: ' . $testLang . ')';
    }

    dms_test_whatsapp_json([
        'success' => $ok,
        'message' => $successMessage,
        'provider' => $provider,
        'provider_label' => $providerLabel,
        'send_mode' => $sendMode,
        'http' => (int) ($result['http'] ?? 0),
        'details' => implode(' | ', $detailParts),
        'meta_hint' => ($provider === 'meta' && $ok)
            ? 'قبول الطلب لا يعني التسليم دائماً. إن لم تصل: انشر تطبيق Meta على Live، أو أضف الرقم كرقم اختبار، وراجع WhatsApp Manager.'
            : '',
    ], $ok ? 200 : 502);
} catch (Throwable $e) {
    error_log('dms test_whatsapp.php: ' . $e->getMessage());
    dms_test_whatsapp_json(['success' => false, 'message' => 'خطأ أثناء الاختبار: ' . $e->getMessage()], 500);
}
