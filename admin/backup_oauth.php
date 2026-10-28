<?php
require_once __DIR__ . '/../config/auth.php';

$user = require_permission('admin.backup');

require_once __DIR__ . '/../config/backup/cloud.php';

$action = (string) ($_GET['action'] ?? '');
$provider = strtolower(trim((string) ($_GET['provider'] ?? '')));

$isOauthReturn = isset($_GET['code']) || isset($_GET['error'])
    || ($action === 'callback')
    || (isset($_GET['state']) && $action === '');

if ($provider === '' && !empty($_SESSION['dms_backup_cloud_oauth_provider'])) {
    $provider = strtolower(trim((string) $_SESSION['dms_backup_cloud_oauth_provider']));
}

if ($provider === '' && !empty($_GET['state'])) {
    $stateRaw = (string) $_GET['state'];
    if (preg_match('/^[0-9a-f]+\.(onedrive|googledrive)$/i', $stateRaw, $m)) {
        $provider = strtolower($m[1]);
    }
}

if (!$isOauthReturn && $action === '') {
    $action = 'start';
}
if ($isOauthReturn) {
    $action = 'callback';
}

if (!in_array($provider, dms_backup_cloud_providers(), true)) {
    $_SESSION['dms_backup_swal_type'] = 'error';
    $_SESSION['dms_backup_swal_text'] = 'مزوّد السحابة غير صالح. أعد الضغط على «ربط OneDrive» من صفحة النسخ الاحتياطي.';
    redirect(base_url('admin/backup.php'));
}

if ($action === 'start') {
    $csrf = (string) ($_GET['csrf_token'] ?? '');
    if ($csrf === '' || !hash_equals(csrf_token(), $csrf)) {
        $_SESSION['dms_backup_swal_type'] = 'error';
        $_SESSION['dms_backup_swal_text'] = 'رمز الأمان غير صحيح.';
        redirect(base_url('admin/backup.php'));
    }

    $settings = dms_backup_cloud_merge_settings([]);
    $raw = @file_get_contents(dms_backup_cloud_settings_file());
    if (is_string($raw)) {
        $json = json_decode($raw, true);
        if (is_array($json)) {
            $settings = dms_backup_cloud_merge_settings($json);
        }
    }

    if ($provider === 'googledrive' && (trim((string) $settings['gdrive_client_id']) === '' || trim((string) $settings['gdrive_client_secret']) === '')) {
        $_SESSION['dms_backup_swal_type'] = 'error';
        $_SESSION['dms_backup_swal_text'] = 'احفظ Client ID و Client Secret لـ Google Drive أولاً.';
        redirect(base_url('admin/backup.php'));
    }
    if ($provider === 'onedrive' && (trim((string) $settings['onedrive_client_id']) === '' || trim((string) $settings['onedrive_client_secret']) === '')) {
        $_SESSION['dms_backup_swal_type'] = 'error';
        $_SESSION['dms_backup_swal_text'] = 'احفظ Client ID و Client Secret لـ OneDrive أولاً.';
        redirect(base_url('admin/backup.php'));
    }

    $nonce = bin2hex(random_bytes(16));
    $state = $nonce . '.' . $provider;
    $_SESSION['dms_backup_cloud_oauth_state'] = $state;
    $_SESSION['dms_backup_cloud_oauth_provider'] = $provider;
    redirect(dms_backup_cloud_authorize_url($provider, $state));
}

if ($action === 'callback') {
    $state = (string) ($_GET['state'] ?? '');
    $code = (string) ($_GET['code'] ?? '');
    $err = (string) ($_GET['error'] ?? '');
    $expected = (string) ($_SESSION['dms_backup_cloud_oauth_state'] ?? '');
    $expectedProvider = (string) ($_SESSION['dms_backup_cloud_oauth_provider'] ?? $provider);
    unset($_SESSION['dms_backup_cloud_oauth_state'], $_SESSION['dms_backup_cloud_oauth_provider']);

    if ($err !== '') {
        $desc = trim((string) ($_GET['error_description'] ?? ''));
        $_SESSION['dms_backup_swal_type'] = 'error';
        $_SESSION['dms_backup_swal_text'] = 'تم إلغاء أو رفض الربط السحابي.' . ($desc !== '' ? ' ' . $desc : '');
        redirect(base_url('admin/backup.php'));
    }

    $stateOk = ($expected !== '' && $state !== '' && hash_equals($expected, $state))
        || ($state !== '' && preg_match('/^[0-9a-f]+\.' . preg_quote($provider, '/') . '$/i', $state));
    if (!$stateOk || $code === '' || ($expectedProvider !== '' && $expectedProvider !== $provider)) {
        $_SESSION['dms_backup_swal_type'] = 'error';
        $_SESSION['dms_backup_swal_text'] = 'فشل التحقق من جلسة الربط السحابي. أعد المحاولة من زر الربط.';
        redirect(base_url('admin/backup.php'));
    }

    $ex = dms_backup_cloud_exchange_code($provider, $code);
    $_SESSION['dms_backup_swal_type'] = $ex['ok'] ? 'success' : 'error';
    $_SESSION['dms_backup_swal_text'] = $ex['ok']
        ? ($provider === 'onedrive' ? 'تم ربط OneDrive بنجاح.' : 'تم ربط Google Drive بنجاح.')
        : $ex['error'];
    redirect(base_url('admin/backup.php'));
}

$_SESSION['dms_backup_swal_type'] = 'error';
$_SESSION['dms_backup_swal_text'] = 'طلب ربط سحابي غير معروف.';
redirect(base_url('admin/backup.php'));
