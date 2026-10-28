<?php
require_once __DIR__ . '/../config/auth.php';

$user = require_permission('admin.backup');
$pageTitle = __('nav.backup');
date_default_timezone_set('Asia/Riyadh');

$settingsFile = dirname(__DIR__) . '/tools/backup-maintenance/ui_settings.json';

require_once __DIR__ . '/../config/backup/maint.php';
require_once __DIR__ . '/../config/backup/cloud.php';
require_once __DIR__ . '/../config/backup/mysql_runner.php';
require_once __DIR__ . '/../config/backup/project_runner.php';
require_once __DIR__ . '/../tools/backup-maintenance/cron_scheduler.php';
$envPaths = dms_backup_maint_env_paths();
$settings = dms_backup_maint_load($settingsFile);
$settings = dms_backup_settings_apply_environment($settings, true, $settingsFile);

$allowedBackupDirs = dms_backup_maint_allowed_backup_dirs($envPaths, $settings);
$activeBackupDir = (string) $settings['host_backup_dir'];
if ($activeBackupDir !== '' && !is_dir($activeBackupDir)) {
    @mkdir($activeBackupDir, 0775, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = (string) ($_POST['action'] ?? '');
    $res = ['ok' => false, 'code' => 1, 'output' => ''];
    $okMsg = 'تم التنفيذ بنجاح.';
    $errMsg = 'تعذر التنفيذ.';

    if ($action === 'save_settings') {
        $prev = dms_backup_maint_load($settingsFile);
        $settings = dms_backup_maint_settings_from_post($prev, $envPaths);
        if ($settings === null) {
            $_SESSION['dms_backup_swal_type'] = 'error';
            $_SESSION['dms_backup_swal_text'] = 'صيغة الوقت غير صحيحة.';
            $_SESSION['dms_backup_output'] = '';
            redirect(base_url('admin/backup.php'));
            exit;
        }
        $saved = dms_backup_maint_save($settingsFile, $settings);
        if (!$saved) {
            $_SESSION['dms_backup_swal_type'] = 'error';
            $_SESSION['dms_backup_swal_text'] = 'تعذر حفظ الإعدادات.';
            $_SESSION['dms_backup_output'] = '';
            redirect(base_url('admin/backup.php'));
            exit;
        }
        $_SESSION['dms_backup_swal_type'] = 'success';
        $_SESSION['dms_backup_swal_text'] = 'تم حفظ الإعدادات.';
        $_SESSION['dms_backup_output'] = '';
        redirect(base_url('admin/backup.php'));
        exit;
    }

    if ($action === 'disconnect_cloud') {
        $settings['gdrive_refresh_token'] = '';
        $settings['gdrive_folder_id'] = '';
        $settings['onedrive_refresh_token'] = '';
        $settings['onedrive_folder_id'] = '';
        $settings['cloud_enabled'] = 0;
        dms_backup_maint_save($settingsFile, $settings);
        $_SESSION['dms_backup_swal_type'] = 'success';
        $_SESSION['dms_backup_swal_text'] = 'تم قطع الربط السحابي.';
        $_SESSION['dms_backup_output'] = '';
        redirect(base_url('admin/backup.php'));
        exit;
    }

    if ($action === 'connect_cloud' || $action === 'connect_onedrive' || $action === 'connect_googledrive') {
        $prev = dms_backup_maint_load($settingsFile);
        $_POST['cloud_section'] = '1';
        $provider = match ($action) {
            'connect_onedrive' => 'onedrive',
            'connect_googledrive' => 'googledrive',
            default => strtolower(trim((string) ($_POST['connect_provider'] ?? ''))),
        };
        if (!in_array($provider, dms_backup_cloud_providers(), true)) {
            $_SESSION['dms_backup_swal_type'] = 'error';
            $_SESSION['dms_backup_swal_text'] = 'اختر OneDrive أو Google Drive للربط.';
            redirect(base_url('admin/backup.php'));
            exit;
        }
        $_POST['cloud_provider'] = $provider;
        $settings = dms_backup_maint_settings_from_post($prev, $envPaths);
        if ($settings === null) {
            $_SESSION['dms_backup_swal_type'] = 'error';
            $_SESSION['dms_backup_swal_text'] = 'تعذر قراءة إعدادات الربط.';
            redirect(base_url('admin/backup.php'));
            exit;
        }
        $settings['cloud_provider'] = $provider;
        $settings['cloud_enabled'] = 1;

        if ($provider === 'onedrive') {
            $clientId = dms_backup_normalize_azure_client_id((string) ($_POST['onedrive_client_id'] ?? $settings['onedrive_client_id'] ?? ''));
            $clientSecret = trim((string) ($_POST['onedrive_client_secret'] ?? ''));
            $tenant = trim((string) ($_POST['onedrive_tenant'] ?? $settings['onedrive_tenant'] ?? 'common'));
            $settings['onedrive_client_id'] = $clientId;
            $settings['onedrive_tenant'] = $tenant !== '' ? $tenant : 'common';
            // لا تُفرَّغ الأسرار عند ترك الحقل فارغاً — تُحدَّث فقط إذا أُدخلَت قيمة جديدة
            if ($clientSecret !== '') {
                $settings['onedrive_client_secret'] = $clientSecret;
            } else {
                $settings['onedrive_client_secret'] = (string) ($prev['onedrive_client_secret'] ?? $settings['onedrive_client_secret'] ?? '');
            }
            if ($clientId === '' || trim((string) $settings['onedrive_client_secret']) === '') {
                $_SESSION['dms_backup_swal_type'] = 'error';
                $_SESSION['dms_backup_swal_text'] = 'أدخل Application (client) ID و Client Secret Value من Azure ثم اضغط «ربط OneDrive». السر غير محفوظ حالياً ويجب إدخاله مرة أخرى.';
                redirect(base_url('admin/backup.php'));
                exit;
            }
            $idHint = dms_backup_azure_client_id_hint($clientId);
            if ($idHint !== null) {
                $_SESSION['dms_backup_swal_type'] = 'error';
                $_SESSION['dms_backup_swal_text'] = $idHint;
                redirect(base_url('admin/backup.php'));
                exit;
            }
            $secretHint = dms_backup_azure_client_secret_hint((string) $settings['onedrive_client_secret']);
            if ($secretHint !== null) {
                $_SESSION['dms_backup_swal_type'] = 'error';
                $_SESSION['dms_backup_swal_text'] = $secretHint;
                redirect(base_url('admin/backup.php'));
                exit;
            }
        } else {
            $gSecret = trim((string) ($_POST['gdrive_client_secret'] ?? ''));
            if ($gSecret !== '') {
                $settings['gdrive_client_secret'] = $gSecret;
            } else {
                $settings['gdrive_client_secret'] = (string) ($prev['gdrive_client_secret'] ?? $settings['gdrive_client_secret'] ?? '');
            }
            $idOk = trim((string) $settings['gdrive_client_id']) !== '';
            $secOk = trim((string) $settings['gdrive_client_secret']) !== '';
            if (!$idOk || !$secOk) {
                $_SESSION['dms_backup_swal_type'] = 'error';
                $_SESSION['dms_backup_swal_text'] = 'أدخل Client ID و Client Secret لـ Google Drive في الحقول ثم اضغط ربط.';
                redirect(base_url('admin/backup.php'));
                exit;
            }
        }

        dms_backup_maint_save($settingsFile, $settings);
        $state = bin2hex(random_bytes(16)) . '.' . $provider;
        $_SESSION['dms_backup_cloud_oauth_state'] = $state;
        $_SESSION['dms_backup_cloud_oauth_provider'] = $provider;
        header('Location: ' . dms_backup_cloud_authorize_url($provider, $state));
        exit;
    }

    if ($action === 'backup_now' || $action === 'project_backup_now' || $action === 'storage_backup_now') {
        @set_time_limit(0);
        @ignore_user_abort(true);
    }

    if ($action === 'backup_now') {
        $backupDir = dms_backup_maint_web_backup_dir($settings, $envPaths);
        $res = dms_backup_mysql_create($backupDir, (int) $settings['keep_days'], false, $settings);
        $okMsg = 'تم إنشاء نسخة قاعدة البيانات بنجاح.';
        $errMsg = 'فشل إنشاء نسخة قاعدة البيانات.';
        if ($res['ok'] && !empty($res['cloud_uploaded'])) {
            $okMsg = 'تم إنشاء نسخة قاعدة البيانات ورفعها إلى السحابة بنجاح.';
        }
    } elseif ($action === 'project_backup_now') {
        $backupDir = dms_backup_maint_web_backup_dir($settings, $envPaths);
        $projectRoot = trim((string) ($settings['host_project_root'] ?? ''));
        if ($projectRoot === '' || str_contains($projectRoot, 'USERNAME') || dms_backup_path_mismatches_environment($projectRoot)) {
            $projectRoot = (string) $envPaths['project_root'];
        }
        $res = dms_backup_project_create(
            $projectRoot,
            $backupDir,
            false,
            $settings,
            max(1, min(365, (int) ($settings['project_keep_days'] ?? $settings['keep_days'] ?? 30)))
        );
        $okMsg = 'تم إنشاء نسخة النظام (الكود) المضغوطة بنجاح.';
        $errMsg = 'فشل إنشاء نسخة النظام المضغوطة.';
        if ($res['ok'] && !empty($res['cloud_uploaded'])) {
            $okMsg = 'تم إنشاء نسخة النظام ورفعها إلى السحابة بنجاح.';
        }
    } elseif ($action === 'storage_backup_now') {
        $backupDir = dms_backup_maint_web_backup_dir($settings, $envPaths);
        $res = dms_backup_storage_create(
            $backupDir,
            false,
            $settings,
            max(1, min(365, (int) ($settings['project_keep_days'] ?? $settings['keep_days'] ?? 30)))
        );
        $okMsg = 'تم إنشاء نسخة ملفات المعاملات المضغوطة بنجاح.';
        $errMsg = 'فشل إنشاء نسخة ملفات المعاملات.';
        if ($res['ok'] && !empty($res['cloud_uploaded'])) {
            $okMsg = 'تم إنشاء نسخة ملفات المعاملات ورفعها إلى السحابة بنجاح.';
        }
    } elseif ($action === 'schedule_diagnose') {
        $lines = dms_backup_cron_diagnose($settings);
        $res = [
            'ok' => true,
            'code' => 0,
            'output' => implode("\n", $lines),
        ];
        $okMsg = 'تم فحص حالة الجدولة.';
        $errMsg = 'فشل فحص الجدولة.';
        $_SESSION['dms_backup_swal_type'] = 'success';
        $_SESSION['dms_backup_swal_text'] = $okMsg;
        $_SESSION['dms_backup_output'] = $res['output'];
        redirect(base_url('admin/backup.php'));
        exit;
    } elseif ($action === 'alert_email_test') {
        $to = trim((string) ($settings['alert_email'] ?? ''));
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['dms_backup_swal_type'] = 'error';
            $_SESSION['dms_backup_swal_text'] = 'أدخل بريداً صالحاً ثم احفظ الإعدادات قبل الاختبار.';
            $_SESSION['dms_backup_output'] = '';
            redirect(base_url('admin/backup.php'));
            exit;
        }
        require_once __DIR__ . '/../config/mailer.php';
        $mailError = null;
        $sent = dms_send_mail(
            $to,
            '[DMS Backup] اختبار إشعار النسخ',
            '<p dir="rtl">هذا بريد تجريبي من صفحة النسخ الاحتياطي.</p>'
                . '<p dir="rtl">إذا وصلتك الرسالة فإعدادات SMTP وبريد التنبيه تعمل (فشل و/أو نجاح الجدولة).</p>',
            "Test backup alert email from DMS backup page.",
            $mailError
        );
        $res = [
            'ok' => $sent,
            'code' => $sent ? 0 : 1,
            'output' => $sent ? '' : (string) $mailError,
        ];
        $okMsg = 'تم إرسال بريد الاختبار إلى ' . $to;
        $errMsg = 'فشل إرسال بريد الاختبار.';
    } elseif ($action === 'restore_backup') {
        $encodedFile = (string) ($_POST['restore_file'] ?? '');
        $restorePath = dms_backup_maint_restore_allowed_backup_path($encodedFile, $allowedBackupDirs);
        if ($restorePath === null) {
            $_SESSION['dms_backup_swal_type'] = 'error';
            $_SESSION['dms_backup_swal_text'] = 'ملف النسخة الاحتياطية غير صالح.';
            $_SESSION['dms_backup_output'] = '';
            redirect(base_url('admin/backup.php'));
            exit;
        }
        $cfg = dms_backup_db_config();
        $dbName = (string) ($cfg['dbname'] ?? '');
        $mysqlExe = $envPaths['mysql_bin'];
        $res = dms_backup_maint_restore_file($mysqlExe, $dbName, $restorePath, $cfg);
        $okMsg = 'تم استرجاع النسخة الاحتياطية بنجاح.';
        $errMsg = 'فشل استرجاع النسخة الاحتياطية.';
    } elseif ($action === 'delete_backup') {
        $encodedFile = (string) ($_POST['delete_file'] ?? '');
        $deletePath = dms_backup_maint_allowed_file_path($encodedFile, $allowedBackupDirs, ['sql', 'zip']);
        if ($deletePath === null) {
            $_SESSION['dms_backup_swal_type'] = 'error';
            $_SESSION['dms_backup_swal_text'] = 'ملف النسخة الاحتياطية غير صالح.';
            $_SESSION['dms_backup_output'] = '';
            redirect(base_url('admin/backup.php'));
            exit;
        }
        $deleted = @unlink($deletePath);
        $res = [
            'ok' => $deleted,
            'code' => $deleted ? 0 : 1,
            'output' => $deleted ? '' : 'تعذر حذف الملف.',
        ];
        $okMsg = 'تم حذف النسخة الاحتياطية بنجاح.';
        $errMsg = 'فشل حذف النسخة الاحتياطية.';
    }

    $_SESSION['dms_backup_swal_type'] = $res['ok'] ? 'success' : 'error';
    if ($res['ok']) {
        $_SESSION['dms_backup_swal_text'] = $okMsg;
        $_SESSION['dms_backup_output'] = '';
    } else {
        $detail = trim((string) ($res['output'] ?? ''));
        // اختصر للتلميح في النافذة؛ التفاصيل الكاملة تظهر أسفل الصفحة
        $flat = preg_replace('/\s+/u', ' ', $detail) ?? $detail;
        $hint = function_exists('mb_substr')
            ? mb_substr($flat, 0, 280)
            : substr($flat, 0, 280);
        $_SESSION['dms_backup_swal_text'] = $errMsg . ' (Code: ' . $res['code'] . ')'
            . ($hint !== '' ? "\n" . $hint : '');
        $_SESSION['dms_backup_output'] = $detail;
    }
    redirect(base_url('admin/backup.php'));
    exit;
}

$cronPhpPath = dms_backup_maint_cron_php_path();
$hostSchedulerCron = sprintf(
    '* * * * * %s -q %s/tools/backup-maintenance/cron_scheduler.php >> %s/scheduler.log 2>&1',
    $cronPhpPath,
    $settings['host_project_root'],
    $settings['host_backup_dir']
);
$enableDbBackup = !empty($settings['enable_db_backup']);
$enableProjectBackup = !empty($settings['enable_project_backup']);
$dbEveryDays = dms_backup_maint_clamp_every_days($settings['db_backup_every_days'] ?? 1);
$projectEveryDays = dms_backup_maint_clamp_every_days($settings['project_backup_every_days'] ?? 1);
$projectKeepDays = max(1, min(365, (int) ($settings['project_keep_days'] ?? 30)));
$schedulerStateFile = dms_backup_cron_state_file();
$schedulerState = dms_backup_cron_state_load($schedulerStateFile);
$jobDbView = dms_backup_cron_job_view($settings, $schedulerState, 'backup', 'enable_db_backup', 'backup_time', '03:00', $dbEveryDays, $schedulerStateFile);
$jobProjectView = dms_backup_cron_job_view($settings, $schedulerState, 'project_backup', 'enable_project_backup', 'project_backup_time', '04:00', $projectEveryDays, $schedulerStateFile);
$isLocalEnv = function_exists('dms_backup_db_is_local') && dms_backup_db_is_local();
$cronPathsLookLocal = preg_match('/^[A-Za-z]:\//', (string) $settings['host_project_root']) === 1
    || str_contains((string) $settings['host_project_root'], 'xampp')
    || str_contains((string) $settings['host_backup_dir'], 'xampp');

$backupFiles = dms_backup_maint_backup_files($activeBackupDir);
$backupCount = count($backupFiles);
$backupTotalBytes = array_sum(array_column($backupFiles, 'size'));
$latestBackup = $backupFiles[0] ?? null;
$dbNameLabel = (string) ($envPaths['db_name'] !== '' ? $envPaths['db_name'] : '');
$oauthRedirectUri = dms_backup_cloud_oauth_redirect_uri();
$gdriveConnected = trim((string) ($settings['gdrive_refresh_token'] ?? '')) !== '';
$onedriveConnected = trim((string) ($settings['onedrive_refresh_token'] ?? '')) !== '';
$onedriveSecretSaved = trim((string) ($settings['onedrive_client_secret'] ?? '')) !== '';
$gdriveSecretSaved = trim((string) ($settings['gdrive_client_secret'] ?? '')) !== '';
$onedriveClientIdSaved = trim((string) ($settings['onedrive_client_id'] ?? '')) !== '';

$lastOutput = '';
if (!empty($_SESSION['dms_backup_swal_type'])) {
    $pageAlert = [
        'type' => (string) $_SESSION['dms_backup_swal_type'],
        'message' => (string) ($_SESSION['dms_backup_swal_text'] ?? ''),
    ];
    $lastOutput = (string) ($_SESSION['dms_backup_output'] ?? '');
    unset($_SESSION['dms_backup_swal_type'], $_SESSION['dms_backup_swal_text'], $_SESSION['dms_backup_output']);
}

require __DIR__ . '/../includes/header.php';

$pageExtraHead = <<<'CSS'
<style>
.dms-backup-pro { max-width: 1080px; margin: 0 auto; }
.dms-backup-pro .hero {
  background: linear-gradient(135deg, rgba(155,125,59,.10), rgba(84,89,95,.08));
  border: 1px solid rgba(155,125,59,.25);
  border-radius: 14px; padding: 1rem 1.25rem; margin-bottom: 1rem;
}
.dms-backup-pro .title { font-weight: 800; color: #38404a; margin: 0; font-size: 1.25rem; }
.dms-backup-pro .panel {
  background: #fff; border: 1px solid #e8eaee; border-radius: 12px; padding: 1.05rem 1.15rem; margin-bottom: .9rem;
}
.dms-backup-pro .panel h3 {
  font-size: .98rem; font-weight: 800; margin: 0 0 .9rem; color: #3d4650;
  display:flex; align-items:center; gap:.45rem;
}
.dms-backup-pro .panel h3 .sec-num {
  width:1.35rem; height:1.35rem; border-radius:50%; background:rgba(155,125,59,.15); color:#8a6d34;
  display:inline-flex; align-items:center; justify-content:center; font-size:.72rem; font-weight:800; flex-shrink:0;
}
.dms-backup-pro .btn-kff { background: #9B7D3B; border-color: #9B7D3B; color: #fff; }
.dms-backup-pro .btn-kff:hover { background: #8a6d34; border-color: #8a6d34; color: #fff; }
.dms-backup-pro .icon-actions { display:flex; flex-wrap:wrap; gap:.45rem; align-items:center; padding-top:.25rem; }
.dms-backup-pro .icon-actions .inv-btn-icon { margin: 0; }
.dms-backup-pro .stats-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:.75rem; margin-bottom:1rem; }
.dms-backup-pro .stat-card {
  border:1px solid #e5e7eb; border-radius:12px; background:#fff; padding:.95rem .85rem;
  text-align:center; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:.35rem;
  box-shadow:0 1px 2px rgba(16,24,40,.04);
}
.dms-backup-pro .stat-card .ico {
  width:2.35rem; height:2.35rem; border-radius:50%;
  display:inline-flex; align-items:center; justify-content:center;
  background:rgba(155,125,59,.12); color:#9B7D3B; font-size:1rem;
}
.dms-backup-pro .stat-card .k { color:#6b7280; font-size:.78rem; margin:0; line-height:1.3; }
.dms-backup-pro .stat-card .v { color:#1f2937; font-weight:800; font-size:1.08rem; margin:0; line-height:1.25; }
.dms-backup-pro .schedule-toggles { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:.55rem; }
.dms-backup-pro .schedule-toggles .task-toggle { height:100%; }
.dms-backup-pro .ctrl-jobs {
  display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.75rem; margin-bottom:.85rem;
}
.dms-backup-pro .ctrl-job {
  border:1px solid #e8eaee; border-radius:12px; background:#fafbfc; padding:.85rem .9rem;
  display:flex; flex-direction:column; gap:.65rem; min-height:100%;
}
.dms-backup-pro .ctrl-job-head {
  display:flex; align-items:flex-start; gap:.5rem; padding-bottom:.55rem;
  border-bottom:1px solid #eef0f3;
}
.dms-backup-pro .ctrl-job-head .form-check-label {
  font-weight:800; color:#374151; font-size:.88rem; line-height:1.35;
}
.dms-backup-pro .ctrl-job-ico {
  width:1.7rem; height:1.7rem; border-radius:8px; flex-shrink:0;
  display:inline-flex; align-items:center; justify-content:center;
  background:rgba(155,125,59,.12); color:#9B7D3B; font-size:.78rem; margin-top:.1rem;
}
.dms-backup-pro .ctrl-job .form-label {
  font-size:.74rem; font-weight:700; color:#6b7280; margin-bottom:.25rem;
}
.dms-backup-pro .ctrl-job .form-control { font-size:.88rem; }
.dms-backup-pro .ctrl-job-fields { display:grid; grid-template-columns:1fr 1fr; gap:.55rem; }
.dms-backup-pro .ctrl-job-fields.single { grid-template-columns:1fr; }
.dms-backup-pro .ctrl-job-fields.triple { grid-template-columns:repeat(3,minmax(0,1fr)); }
.dms-backup-pro .ctrl-meta {
  border:1px solid #e8eaee; border-radius:12px; background:#fff; padding:.85rem .9rem; margin-bottom:.85rem;
}
.dms-backup-pro .panel-head-actions {
  display:flex; flex-wrap:wrap; align-items:center; gap:.45rem; margin:0;
}
.dms-backup-pro .ctrl-job-foot {
  margin-top:auto; padding-top:.55rem; border-top:1px solid #eef0f3;
  font-size:.72rem; color:#6b7280; line-height:1.5;
}
.dms-backup-pro .ctrl-job-foot b { color:#374151; font-weight:700; }
.dms-backup-pro .ctrl-job-foot .ready { color:#166534; font-weight:800; }
.dms-backup-pro .ctrl-alert-box {
  margin-top:.85rem; padding:.75rem .85rem;
  border:1px solid #e8eaee; border-radius:12px; background:#fafbfc;
}
.dms-backup-pro .ctrl-alert-toggles {
  display:flex; flex-wrap:wrap; gap:.55rem; height:100%; align-items:flex-end;
}
.dms-backup-pro .ctrl-alert-toggles .alert-opt {
  flex:1 1 140px; min-width:0;
}
.dms-backup-pro .ctrl-alert-toggles .task-toggle {
  height:100%; background:#fff; margin:0;
  padding:.5rem .65rem;
}
.dms-backup-pro .ctrl-alert-toggles .task-toggle.fail-on {
  border-color:#fecaca; background:#fffafa;
}
.dms-backup-pro .ctrl-alert-toggles .task-toggle.ok-on {
  border-color:#bbf7d0; background:#f7fdf9;
}
.dms-backup-pro .ctrl-alert-toggles .form-check-label {
  font-size:.82rem; font-weight:600; color:#374151; line-height:1.3;
  white-space:nowrap;
}
.dms-backup-pro .cron-chip.off { opacity:.45; text-decoration:line-through; }
.dms-backup-pro .status-grid { display: grid; gap: .75rem; grid-template-columns: repeat(3, minmax(0,1fr)); }
.dms-backup-pro .status-card { border: 1px solid #e5e7eb; border-radius: 10px; padding: .75rem; background: #fafbfc; }
.dms-backup-pro .status-top { display:flex; justify-content:space-between; gap:.5rem; margin-bottom:.4rem; align-items:center; }
.dms-backup-pro .chip { font-size:.74rem; font-weight:800; border-radius:999px; padding:.12rem .55rem; }
.dms-backup-pro .chip.ok { background:#dcfce7; color:#166534; }
.dms-backup-pro .chip.warn { background:#fef3c7; color:#92400e; }
.dms-backup-pro .chip.bad { background:#fee2e2; color:#991b1b; }
.dms-backup-pro .meta { color:#6b7280; font-size:.82rem; line-height:1.55; }
.dms-backup-pro .mono {
  border:1px solid #e5e7eb; background:#f8fafc; border-radius:8px; padding:.65rem; direction:ltr; text-align:left;
  word-break: break-all; font-family: Consolas, Monaco, monospace; font-size:.82rem;
}
.dms-backup-pro table { width:100%; border-collapse:collapse; }
.dms-backup-pro th, .dms-backup-pro td { border-bottom:1px solid #eceff3; padding:.55rem .45rem; font-size:.86rem; }
.dms-backup-pro th { color:#4b5563; font-weight:800; background:#f9fafb; }
.dms-backup-pro .restore-box { border:1px solid #f1e4e4; background:#fffaf9; border-radius:10px; padding:.8rem; margin-bottom:.8rem; }
.dms-backup-pro .row-actions { display:inline-flex; gap:.35rem; align-items:center; justify-content:center; }
.dms-backup-pro th:last-child,
.dms-backup-pro td:last-child { text-align:center; vertical-align:middle; }
.dms-backup-pro td:last-child .row-actions { margin-inline:auto; }
.dms-backup-pro .delete-backup-form { display:inline-flex; margin:0; }
.dms-backup-pro .task-toggles { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:.55rem; }
.dms-backup-pro .task-toggle { border:1px solid #e5e7eb; border-radius:10px; padding:.55rem .65rem; background:#fafbfc; }
.dms-backup-pro .soft-note { color:#6b7280; font-size:.8rem; margin-top:.35rem; }
.dms-backup-pro details.dms-fold {
  border:1px solid #e8eaee; border-radius:12px; background:#fff; margin-bottom:.9rem; padding:.15rem 1rem .85rem;
}
.dms-backup-pro details.dms-fold > summary {
  list-style:none; cursor:pointer; font-weight:800; color:#3d4650; padding:.85rem 0 .45rem;
  display:flex; align-items:center; justify-content:space-between; gap:.75rem;
}
.dms-backup-pro details.dms-fold > summary::-webkit-details-marker { display:none; }
.dms-backup-pro details.dms-fold > summary::after { content:"▾"; color:#9B7D3B; font-size:.9rem; }
.dms-backup-pro details.dms-fold[open] > summary::after { content:"▴"; }
.dms-backup-pro .cloud-intro {
  display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:.55rem; margin-bottom:1rem;
}
.dms-backup-pro .cloud-intro-step {
  border:1px solid #e8eaee; border-radius:10px; background:#fafbfc; padding:.65rem .75rem;
}
.dms-backup-pro .cloud-intro-step .n {
  width:1.2rem; height:1.2rem; border-radius:50%; background:rgba(155,125,59,.15); color:#8a6d34;
  display:inline-flex; align-items:center; justify-content:center; font-size:.7rem; font-weight:800; margin-inline-end:.35rem;
}
.dms-backup-pro .cloud-intro-step .t { font-size:.8rem; color:#4b5563; line-height:1.45; font-weight:600; }
.dms-backup-pro .cloud-section-label {
  font-size:.78rem; font-weight:800; color:#9B7D3B; letter-spacing:.02em; margin:0 0 .55rem; text-transform:none;
}
.dms-backup-pro .cloud-box {
  border:1px solid #e5e7eb; border-radius:12px; padding:1rem; background:#fff; margin-bottom:0;
  display:none; box-shadow:0 1px 0 rgba(15,23,42,.03);
}
.dms-backup-pro .cloud-box.is-visible { display:block; }
.dms-backup-pro .cloud-box.is-active {
  border-color:rgba(155,125,59,.45); background:#fffdf8;
}
.dms-backup-pro .cloud-providers { display:flex; flex-direction:column; gap:.75rem; }
.dms-backup-pro .cloud-box h4 {
  font-size:.95rem; font-weight:800; margin:0 0 .55rem; color:#374151;
  display:flex; align-items:center; gap:.45rem; flex-wrap:wrap;
}
.dms-backup-pro .cloud-box .cloud-brand {
  display:inline-flex; align-items:center; justify-content:center;
  width:1.7rem; height:1.7rem; border-radius:8px; background:rgba(155,125,59,.12); color:#8a6d34; font-size:.85rem;
}
.dms-backup-pro .cloud-actions {
  display:flex; flex-wrap:wrap; gap:.45rem; align-items:center; margin-top:.75rem;
  padding-top:.65rem; border-top:1px dashed #e5e7eb;
}
.dms-backup-pro .cloud-step { color:#6b7280; font-size:.82rem; margin-bottom:.75rem; line-height:1.6; }
.dms-backup-pro .panel-head {
  display:flex; align-items:center; justify-content:space-between; gap:.75rem; flex-wrap:wrap; margin-bottom:.75rem;
}
.dms-backup-pro .panel-head h3 { margin:0; }
.dms-backup-pro .uri-copy-row { display:flex; gap:.5rem; align-items:stretch; flex-wrap:wrap; }
.dms-backup-pro .uri-copy-row .mono { flex:1 1 280px; margin:0; }
.dms-backup-pro .form-divider {
  height:1px; background:#eef0f3; margin:.15rem 0 .35rem; grid-column:1 / -1;
}
.dms-backup-pro .settings-group-label {
  font-size:.78rem; font-weight:800; color:#9B7D3B; margin:.15rem 0 .2rem; grid-column:1 / -1;
}
.dms-backup-pro .cron-panel .cron-alert {
  border:1px solid #fde68a; background:#fffbeb; color:#92400e; border-radius:8px;
  padding:.55rem .7rem; font-size:.8rem; line-height:1.45; margin-bottom:.65rem;
}
.dms-backup-pro .cron-panel .cron-alert.info {
  border-color:#bfdbfe; background:#eff6ff; color:#1e3a8a;
}
.dms-backup-pro .cron-chips {
  display:flex; flex-wrap:wrap; gap:.4rem; margin-bottom:.65rem;
}
.dms-backup-pro .cron-chip {
  display:inline-flex; align-items:center; gap:.35rem;
  border:1px solid #e5e7eb; background:#fafbfc; border-radius:999px;
  padding:.2rem .55rem; font-size:.76rem; color:#4b5563;
}
.dms-backup-pro .cron-chip b { color:#1f2937; direction:ltr; font-weight:800; }
.dms-backup-pro .cron-main {
  display:flex; gap:.45rem; align-items:stretch;
}
.dms-backup-pro .cron-main .mono {
  flex:1 1 auto; margin:0; padding:.5rem .65rem; font-size:.78rem; max-height:4.2rem; overflow:auto;
}
.dms-backup-pro .cron-main .btn-copy-cron {
  border:1px solid #d1d5db; background:#fff; color:#374151; border-radius:8px;
  padding:.35rem .7rem; font-size:.78rem; font-weight:700; white-space:nowrap; align-self:center;
}
.dms-backup-pro .cron-main .btn-copy-cron:hover { border-color:#9B7D3B; color:#8a6d34; }
@media (max-width: 900px){
  .dms-backup-pro .status-grid,
  .dms-backup-pro .task-toggles,
  .dms-backup-pro .schedule-toggles,
  .dms-backup-pro .ctrl-jobs,
  .dms-backup-pro .stats-grid,
  .dms-backup-pro .cloud-intro { grid-template-columns: 1fr 1fr; }
  .dms-backup-pro .ctrl-job-fields.triple { grid-template-columns: 1fr; }
}
@media (max-width: 560px){
  .dms-backup-pro .stats-grid,
  .dms-backup-pro .status-grid,
  .dms-backup-pro .task-toggles,
  .dms-backup-pro .schedule-toggles,
  .dms-backup-pro .ctrl-jobs,
  .dms-backup-pro .cloud-intro { grid-template-columns: 1fr; }
  .dms-backup-pro .ctrl-alert-toggles .form-check-label { white-space:normal; }
  .dms-backup-pro .ctrl-job-fields,
  .dms-backup-pro .ctrl-job-fields.triple { grid-template-columns: 1fr; }
  .dms-backup-pro .cron-main { flex-direction:column; }
}
</style>
CSS;
?>
<?= $pageExtraHead ?>
<div class="container py-3 dms-backup-pro">
<div class="hero">
        <h1 class="title"><i class="bi bi-database me-2"></i>النسخ الاحتياطي</h1>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="ico" aria-hidden="true"><i class="bi bi-clone"></i></div>
            <div class="k">إجمالي النسخ</div>
            <div class="v"><?php echo (int) $backupCount; ?></div>
        </div>
        <div class="stat-card">
            <div class="ico" aria-hidden="true"><i class="bi bi-hdd"></i></div>
            <div class="k">إجمالي الحجم</div>
            <div class="v"><?php echo htmlspecialchars(dms_backup_maint_human_size((int) $backupTotalBytes), ENT_QUOTES, 'UTF-8'); ?></div>
        </div>
        <div class="stat-card">
            <div class="ico" aria-hidden="true"><i class="bi bi-clock"></i></div>
            <div class="k">آخر نسخة</div>
            <div class="v"><?php echo htmlspecialchars($latestBackup ? date('Y-m-d H:i', (int) $latestBackup['time']) : '-', ENT_QUOTES, 'UTF-8'); ?></div>
        </div>
        <div class="stat-card">
            <div class="ico" aria-hidden="true"><i class="bi bi-calendar-check"></i></div>
            <div class="k">الاحتفاظ SQL / ZIP</div>
            <div class="v"><?php echo (int) $settings['keep_days']; ?> / <?php echo (int) $projectKeepDays; ?></div>
        </div>
    </div>

    <div class="panel">
        <form method="post" id="hostControlForm">
            <?= csrf_field() ?>
            <div class="panel-head">
                <h3><span class="sec-num">1</span> الإعدادات والتحكم</h3>
                <div class="icon-actions panel-head-actions">
                    <button type="submit" name="action" value="save_settings" class="btn btn-sm btn-outline-secondary" title="حفظ الإعدادات"><i class="bi bi-floppy2"></i></button>
                    <button type="submit" name="action" value="schedule_diagnose" class="btn btn-sm btn-outline-secondary" title="تشخيص الجدولة"><i class="bi bi-clipboard-check"></i></button>
                    <button type="submit" name="action" value="backup_now" class="btn btn-sm btn-outline-primary" title="نسخة قاعدة البيانات (SQL)"><i class="bi bi-database"></i></button>
                    <button type="submit" name="action" value="project_backup_now" class="btn btn-sm btn-outline-primary" title="نسخة النظام / الكود (ZIP)"><i class="bi bi-code-slash"></i></button>
                    <button type="submit" name="action" value="storage_backup_now" class="btn btn-sm btn-outline-primary" title="نسخة ملفات المعاملات (ZIP)"><i class="bi bi-folder2-open"></i></button>
                    <button type="submit" name="action" value="alert_email_test" class="btn btn-sm btn-outline-secondary" title="اختبار بريد التنبيه"><i class="bi bi-envelope"></i></button>
                </div>
            </div>

            <div class="alert alert-light border mb-3 py-2 small">
                <strong>نسخ فوري:</strong>
                <span class="me-2"><i class="bi bi-database"></i> قاعدة البيانات</span>
                <span class="me-2"><i class="bi bi-code-slash"></i> النظام (الكود بدون ملفات المعاملات)</span>
                <span><i class="bi bi-folder2-open"></i> ملفات المعاملات (<?= e(function_exists('dms_storage_root') ? dms_storage_root() : 'storage/uploads') ?>)</span>
            </div>

            <div class="ctrl-jobs">
                <div class="ctrl-job">
                    <div class="ctrl-job-head">
                        <span class="ctrl-job-ico" aria-hidden="true"><i class="bi bi-database"></i></span>
                        <div class="d-flex align-items-start gap-2 flex-grow-1">
                            <input class="form-check-input m-0 flex-shrink-0" type="checkbox" id="enable_db_backup" name="enable_db_backup" value="1" <?php echo !empty($settings['enable_db_backup']) ? 'checked' : ''; ?>>
                            <label class="form-check-label mb-0" for="enable_db_backup">قاعدة البيانات (SQL)</label>
                        </div>
                    </div>
                    <div class="ctrl-job-fields triple">
                        <div>
                            <label class="form-label" for="backup_time">وقت التشغيل</label>
                            <input type="time" class="form-control" id="backup_time" name="backup_time" value="<?php echo htmlspecialchars($settings['backup_time'], ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>
                        <div>
                            <label class="form-label" for="db_backup_every_days">كل كم يوم</label>
                            <input type="number" class="form-control" min="1" max="365" id="db_backup_every_days" name="db_backup_every_days" value="<?php echo (int) ($settings['db_backup_every_days'] ?? 1); ?>" required>
                        </div>
                        <div>
                            <label class="form-label" for="keep_days">أيام الحفظ</label>
                            <input type="number" class="form-control" min="1" max="90" id="keep_days" name="keep_days" value="<?php echo (int) $settings['keep_days']; ?>" required>
                        </div>
                    </div>
                    <div class="ctrl-job-foot">
                        آخر نجاح: <b><?php echo htmlspecialchars($jobDbView['last'] !== '' ? $jobDbView['last'] : '—', ENT_QUOTES, 'UTF-8'); ?></b>
                        · القادم: <b><?php echo htmlspecialchars($jobDbView['next'], ENT_QUOTES, 'UTF-8'); ?></b><br>
                        <?php if (!empty($jobDbView['ready_now'])): ?>
                            <span class="ready"><?php echo htmlspecialchars($jobDbView['status'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php else: ?>
                            <?php echo htmlspecialchars($jobDbView['status'], ENT_QUOTES, 'UTF-8'); ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="ctrl-job">
                    <div class="ctrl-job-head">
                        <span class="ctrl-job-ico" aria-hidden="true"><i class="bi bi-file-earmark-zip"></i></span>
                        <div class="d-flex align-items-start gap-2 flex-grow-1">
                            <input class="form-check-input m-0 flex-shrink-0" type="checkbox" id="enable_project_backup" name="enable_project_backup" value="1" <?php echo !empty($settings['enable_project_backup']) ? 'checked' : ''; ?>>
                            <label class="form-check-label mb-0" for="enable_project_backup">النظام / الكود (ZIP)</label>
                        </div>
                    </div>
                    <div class="ctrl-job-fields triple">
                        <div>
                            <label class="form-label" for="project_backup_time">وقت التشغيل</label>
                            <input type="time" class="form-control" id="project_backup_time" name="project_backup_time" value="<?php echo htmlspecialchars((string) ($settings['project_backup_time'] ?? '04:00'), ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>
                        <div>
                            <label class="form-label" for="project_backup_every_days">كل كم يوم</label>
                            <input type="number" class="form-control" min="1" max="365" id="project_backup_every_days" name="project_backup_every_days" value="<?php echo (int) ($settings['project_backup_every_days'] ?? 1); ?>" required>
                        </div>
                        <div>
                            <label class="form-label" for="project_keep_days">أيام الحفظ</label>
                            <input type="number" class="form-control" min="1" max="365" id="project_keep_days" name="project_keep_days" value="<?php echo (int) $projectKeepDays; ?>" required>
                        </div>
                    </div>
                    <div class="ctrl-job-foot">
                        آخر نجاح: <b><?php echo htmlspecialchars($jobProjectView['last'] !== '' ? $jobProjectView['last'] : '—', ENT_QUOTES, 'UTF-8'); ?></b>
                        · القادم: <b><?php echo htmlspecialchars($jobProjectView['next'], ENT_QUOTES, 'UTF-8'); ?></b><br>
                        <?php if (!empty($jobProjectView['ready_now'])): ?>
                            <span class="ready"><?php echo htmlspecialchars($jobProjectView['status'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php else: ?>
                            <?php echo htmlspecialchars($jobProjectView['status'], ENT_QUOTES, 'UTF-8'); ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="ctrl-meta">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="host_project_root">مسار المشروع</label>
                        <input type="text" class="form-control" id="host_project_root" name="host_project_root" dir="ltr" value="<?php echo htmlspecialchars($settings['host_project_root'], ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="host_backup_dir">مسار النسخ</label>
                        <input type="text" class="form-control" id="host_backup_dir" name="host_backup_dir" dir="ltr" value="<?php echo htmlspecialchars($settings['host_backup_dir'], ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                </div>
                <?php
                $alertFailOn = !empty($settings['alert_email_enabled']);
                $alertOkOn = !empty($settings['alert_email_success_enabled']);
                ?>
                <div class="ctrl-alert-box">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-6">
                            <div class="ctrl-alert-toggles">
                                <div class="alert-opt">
                                    <div class="task-toggle<?php echo $alertFailOn ? ' fail-on' : ''; ?>">
                                        <div class="d-flex align-items-start gap-2">
                                            <input class="form-check-input m-0 flex-shrink-0" type="checkbox" id="alert_email_enabled" name="alert_email_enabled" value="1" <?php echo $alertFailOn ? 'checked' : ''; ?>>
                                            <label class="form-check-label mb-0" for="alert_email_enabled">إشعار عند الفشل</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="alert-opt">
                                    <div class="task-toggle<?php echo $alertOkOn ? ' ok-on' : ''; ?>">
                                        <div class="d-flex align-items-start gap-2">
                                            <input class="form-check-input m-0 flex-shrink-0" type="checkbox" id="alert_email_success_enabled" name="alert_email_success_enabled" value="1" <?php echo $alertOkOn ? 'checked' : ''; ?>>
                                            <label class="form-check-label mb-0" for="alert_email_success_enabled">إشعار عند النجاح</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="alert_email">بريد التنبيه</label>
                            <input type="email" class="form-control" id="alert_email" name="alert_email" dir="ltr" placeholder="you@example.com" value="<?php echo htmlspecialchars((string) ($settings['alert_email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div class="panel cron-panel">
        <h3><span class="sec-num">2</span> أوامر Cron (cPanel)</h3>

        <?php if ($isLocalEnv || $cronPathsLookLocal): ?>
            <div class="cron-alert">
                المسارات الحالية محلية/Windows — عدّلها لمسارات HostGator ثم احفظ قبل نسخ الأوامر إلى cPanel.
            </div>
        <?php endif; ?>

        <div class="cron-chips">
            <span class="cron-chip<?php echo $enableDbBackup ? '' : ' off'; ?>">قاعدة البيانات <b><?php echo htmlspecialchars((string) $settings['backup_time'], ENT_QUOTES, 'UTF-8'); ?></b> · كل <?php echo (int) $dbEveryDays; ?> يوم<?php echo $enableDbBackup ? '' : ' (متوقف)'; ?></span>
            <span class="cron-chip<?php echo $enableProjectBackup ? '' : ' off'; ?>">النظام <b><?php echo htmlspecialchars((string) ($settings['project_backup_time'] ?? '04:00'), ENT_QUOTES, 'UTF-8'); ?></b> · كل <?php echo (int) $projectEveryDays; ?> يوم<?php echo $enableProjectBackup ? '' : ' (متوقف)'; ?></span>
        </div>

        <div class="soft-note mb-2 mt-0">سطر واحد في cPanel Cron — ينفّذ المهام المفعّلة عند وقتها وبعد مرور فترة التكرار.</div>
        <div class="cron-main">
            <div class="mono"><?php echo htmlspecialchars($hostSchedulerCron, ENT_QUOTES, 'UTF-8'); ?></div>
            <button type="button" class="btn-copy-cron" data-cron-copy="<?php echo htmlspecialchars($hostSchedulerCron, ENT_QUOTES, 'UTF-8'); ?>">نسخ</button>
        </div>
    </div>

    <?php
    $cloudStatusLabel = 'غير مفعّل';
    $cloudChip = 'bad';
    if (!empty($settings['cloud_enabled']) && ($settings['cloud_provider'] ?? '') === 'onedrive' && $onedriveConnected) {
        $cloudStatusLabel = 'OneDrive مربوط';
        $cloudChip = 'ok';
    } elseif (!empty($settings['cloud_enabled']) && ($settings['cloud_provider'] ?? '') === 'googledrive' && $gdriveConnected) {
        $cloudStatusLabel = 'Google Drive مربوط';
        $cloudChip = 'ok';
    } elseif ($onedriveConnected || $gdriveConnected) {
        $cloudStatusLabel = 'مربوط — الرفع متوقف';
        $cloudChip = 'warn';
    }
    $activeProvider = (string) ($settings['cloud_provider'] ?? '');
    ?>
    <div class="panel" id="cloudSettingsPanel">
        <div class="panel-head">
            <h3><span class="sec-num">3</span> النسخ السحابي</h3>
            <span class="chip <?php echo $cloudChip; ?>"><?php echo htmlspecialchars($cloudStatusLabel, ENT_QUOTES, 'UTF-8'); ?></span>
        </div>

        <div class="cloud-intro">
            <div class="cloud-intro-step">
                <div class="t"><span class="n">1</span> انسخ Redirect URI إلى Azure / Google</div>
            </div>
            <div class="cloud-intro-step">
                <div class="t"><span class="n">2</span> أدخل Client ID و Secret ثم اربط الحساب</div>
            </div>
            <div class="cloud-intro-step">
                <div class="t"><span class="n">3</span> فعّل الرفع وجرّب نسخة فورية</div>
            </div>
        </div>

        <form method="post" class="row g-3" id="cloudBackupForm">
            <?= csrf_field() ?>
            <input type="hidden" name="cloud_section" value="1">
            <input type="hidden" name="backup_time" value="<?php echo htmlspecialchars($settings['backup_time'], ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="project_backup_time" value="<?php echo htmlspecialchars((string) ($settings['project_backup_time'] ?? '04:00'), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="keep_days" value="<?php echo (int) $settings['keep_days']; ?>">
            <input type="hidden" name="host_project_root" value="<?php echo htmlspecialchars($settings['host_project_root'], ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="host_backup_dir" value="<?php echo htmlspecialchars($settings['host_backup_dir'], ENT_QUOTES, 'UTF-8'); ?>">

            <div class="col-12">
                <div class="cloud-section-label">خيارات الرفع</div>
                <div class="task-toggles" style="grid-template-columns:repeat(2,minmax(0,1fr));">
                    <div class="task-toggle">
                        <div class="d-flex align-items-start gap-2">
                            <input class="form-check-input m-0 flex-shrink-0" type="checkbox" id="cloud_enabled" name="cloud_enabled" value="1" <?php echo !empty($settings['cloud_enabled']) ? 'checked' : ''; ?>>
                            <label class="form-check-label mb-0" for="cloud_enabled">تفعيل الرفع السحابي بعد كل نسخة (SQL و ZIP)</label>
                        </div>
                    </div>
                    <div class="task-toggle">
                        <div class="d-flex align-items-start gap-2">
                            <input class="form-check-input m-0 flex-shrink-0" type="checkbox" id="cloud_keep_local" name="cloud_keep_local" value="1" <?php echo !empty($settings['cloud_keep_local']) ? 'checked' : ''; ?>>
                            <label class="form-check-label mb-0" for="cloud_keep_local">الإبقاء على نسخة محلية بعد الرفع</label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <label class="form-label" for="cloud_provider">المزوّد</label>
                <select class="form-select" id="cloud_provider" name="cloud_provider">
                    <option value="" <?php echo $activeProvider === '' ? 'selected' : ''; ?>>— اختر —</option>
                    <option value="onedrive" <?php echo $activeProvider === 'onedrive' ? 'selected' : ''; ?>>OneDrive</option>
                    <option value="googledrive" <?php echo $activeProvider === 'googledrive' ? 'selected' : ''; ?>>Google Drive</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="cloud_folder_name">اسم المجلد السحابي</label>
                <input type="text" class="form-control" id="cloud_folder_name" name="cloud_folder_name" value="<?php echo htmlspecialchars((string) ($settings['cloud_folder_name'] ?? 'DmsBackups'), ENT_QUOTES, 'UTF-8'); ?>">
            </div>

            <div class="col-12">
                <div class="cloud-section-label">Redirect URI</div>
                <div class="uri-copy-row">
                    <div class="mono" id="oauthRedirectUriText"><?php echo htmlspecialchars($oauthRedirectUri, ENT_QUOTES, 'UTF-8'); ?></div>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="copyOauthRedirectUri" title="نسخ">نسخ</button>
                </div>
                <div class="soft-note">أضفه في Azure → Authentication (Web) أو Google OAuth Client كما هو بدون تعديل.</div>
            </div>

            <div class="col-12">
                <div class="cloud-section-label">بيانات التطبيق والربط</div>
                <div class="cloud-providers">
                <div class="cloud-box <?php echo ($activeProvider === '' || $activeProvider === 'onedrive') ? 'is-visible' : ''; ?> <?php echo ($activeProvider === '' || $activeProvider === 'onedrive') ? 'is-active' : ''; ?>" data-cloud-panel="onedrive">
                    <h4>
                        <span class="cloud-brand"><i class="bi bi-microsoft"></i></span>
                        OneDrive
                        <span class="chip <?php echo $onedriveConnected ? 'ok' : 'bad'; ?>"><?php echo $onedriveConnected ? 'مربوط' : 'غير مربوط'; ?></span>
                    </h4>
                    <div class="soft-note mb-2">Azure Portal → <strong>App registrations</strong> → تطبيقك → <strong>Overview</strong> → انسخ <strong>Application (client) ID</strong> (UUID). <strong>Client secret Value</strong> من Certificates &amp; secrets — لا تخلط بين الحقلين.</div>
                    <?php if (!$onedriveConnected): ?>
                        <div class="alert alert-warning py-2 px-3 mb-2">
                            <?php if (!$onedriveSecretSaved): ?>
                                لإعادة الربط: الصق <strong>Client secret Value</strong> من Azure ثم اضغط «ربط OneDrive». السر لا يُعرض في الصفحة لأسباب أمنية.
                            <?php else: ?>
                                اضغط «ربط OneDrive» لإتمام التفويض مع Microsoft.
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="form-label" for="onedrive_client_id">Application (client) ID</label>
                            <input type="text" class="form-control" id="onedrive_client_id" name="onedrive_client_id" dir="ltr" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx" value="<?php echo htmlspecialchars((string) ($settings['onedrive_client_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="onedrive_client_secret">Client secret Value</label>
                            <input type="text" class="form-control" id="onedrive_client_secret" name="onedrive_client_secret" dir="ltr" autocomplete="off" spellcheck="false" value="" placeholder="<?php echo $onedriveSecretSaved ? 'اتركه فارغاً للإبقاء، أو الصق Value جديداً' : 'Certificates & secrets → عمود Value'; ?>">
                            <div class="form-text">من Azure → <strong>Certificates &amp; secrets</strong> انسخ عمود <strong>Value</strong> فقط (ليس Secret ID وليس أي ID من Overview). Value غالباً طويل وفيه <code>~</code>.</div>
                            <?php if ($onedriveSecretSaved): ?>
                                <div class="form-text text-success">سر محفوظ على الخادم — الصق Value جديداً فقط إذا كان السابق خاطئاً.</div>
                            <?php else: ?>
                                <div class="form-text text-danger">لا يوجد سر محفوظ — أنشئ سراً جديداً والصق Value هنا ثم اربط.</div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="onedrive_tenant">Tenant</label>
                            <input type="text" class="form-control" id="onedrive_tenant" name="onedrive_tenant" dir="ltr" value="<?php echo htmlspecialchars((string) ($settings['onedrive_tenant'] ?? 'common'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
                        </div>
                    </div>
                    <div class="cloud-actions">
                        <button type="submit" class="btn btn-sm btn-kff js-cloud-connect" name="action" value="connect_onedrive" data-provider="onedrive">ربط OneDrive</button>
                    </div>
                </div>

                <div class="cloud-box <?php echo ($activeProvider === '' || $activeProvider === 'googledrive') ? 'is-visible' : ''; ?> <?php echo $activeProvider === 'googledrive' ? 'is-active' : ''; ?>" data-cloud-panel="googledrive">
                    <h4>
                        <span class="cloud-brand"><i class="bi bi-google"></i></span>
                        Google Drive
                        <span class="chip <?php echo $gdriveConnected ? 'ok' : 'bad'; ?>"><?php echo $gdriveConnected ? 'مربوط' : 'غير مربوط'; ?></span>
                    </h4>
                    <div class="soft-note mb-2">Google Cloud → APIs &amp; Services → Credentials → OAuth Client (Web).</div>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label" for="gdrive_client_id">Client ID</label>
                            <input type="text" class="form-control" id="gdrive_client_id" name="gdrive_client_id" dir="ltr" value="<?php echo htmlspecialchars((string) ($settings['gdrive_client_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="gdrive_client_secret">Client Secret</label>
                            <input type="password" class="form-control" id="gdrive_client_secret" name="gdrive_client_secret" dir="ltr" autocomplete="new-password" value="" placeholder="<?php echo $gdriveSecretSaved ? '•••••••• (اتركه فارغاً للإبقاء)' : 'ألصق السر هنا'; ?>">
                            <?php if ($gdriveSecretSaved): ?>
                                <div class="form-text text-success">سر محفوظ على الخادم — أدخل قيمة جديدة فقط عند الاستبدال.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="cloud-actions">
                        <button type="submit" class="btn btn-sm btn-outline-secondary js-cloud-connect" name="action" value="connect_googledrive" data-provider="googledrive">ربط Google Drive</button>
                    </div>
                </div>
                </div>
                <input type="hidden" name="connect_provider" id="connect_provider_field" value="">
            </div>

            <div class="col-12 icon-actions">
                <button type="submit" name="action" value="save_settings" class="btn btn-sm btn-outline-secondary" title="حفظ إعدادات السحابة"><i class="bi bi-floppy2"></i></button>
                <button type="submit" name="action" value="disconnect_cloud" class="btn btn-sm btn-outline-secondary" title="قطع الربط السحابي"><i class="bi bi-link-45deg"></i></button>
            </div>
        </form>
    </div>

    <div class="panel">
        <h3><span class="sec-num">4</span> النسخ الاحتياطية</h3>
        <div class="soft-note mb-2">المجلد: <span dir="ltr"><?php echo htmlspecialchars($activeBackupDir, ENT_QUOTES, 'UTF-8'); ?></span></div>
        <div class="restore-box">
            <div class="fw-bold mb-2">استرجاع نسخة احتياطية</div>
            <div class="small text-muted mb-2">تنبيه: الاسترجاع يستبدل بيانات قاعدة <code dir="ltr"><?php echo htmlspecialchars($dbNameLabel, ENT_QUOTES, 'UTF-8'); ?></code> الحالية.</div>
            <form method="post" id="restoreBackupForm" class="d-flex flex-wrap gap-2 align-items-center">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="restore_backup">
                <select name="restore_file" id="restore_file" class="form-select" style="max-width: 520px;" required>
                    <option value="">اختر نسخة للاسترجاع...</option>
                    <?php foreach ($backupFiles as $bk): ?>
                        <?php if (($bk['ext'] ?? '') !== 'sql') { continue; } ?>
                        <option value="<?php echo htmlspecialchars(rawurlencode(base64_encode((string) $bk['path'])), ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars((string) $bk['name'] . ' - ' . date('Y-m-d H:i:s', (int) $bk['time']), ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="button" id="restoreBackupBtn" class="btn btn-sm btn-kff" title="استرجاع النسخة المحددة"><i class="bi bi-upload"></i></button>
            </form>
        </div>
        <?php if ($backupCount === 0): ?>
            <div class="text-muted">لا توجد نسخ احتياطية SQL حالياً في المجلد أعلاه.</div>
        <?php else: ?>
            <div style="overflow:auto">
                <table>
                    <thead>
                        <tr>
                            <th>اسم الملف</th>
                            <th>الحجم</th>
                            <th>التاريخ</th>
                            <th>النوع</th>
                            <th>إجراء</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($backupFiles, 0, 12) as $bk): ?>
                            <?php $encoded = rawurlencode(base64_encode((string) $bk['path'])); ?>
                            <tr>
                                <td><?php echo htmlspecialchars((string) $bk['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars(dms_backup_maint_human_size((int) $bk['size']), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars(date('Y-m-d H:i:s', (int) $bk['time']), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars(strtoupper((string) ($bk['ext'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <div class="row-actions">
                                        <a href="<?= e('backup_download.php?file=' . $encoded . '&csrf_token=' . rawurlencode(csrf_token())) ?>" class="btn btn-sm btn-outline-secondary" title="تنزيل"><i class="bi bi-download"></i></a>
                                        <form method="post" class="delete-backup-form">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete_backup">
                                            <input type="hidden" name="delete_file" value="<?php echo htmlspecialchars($encoded, ENT_QUOTES, 'UTF-8'); ?>">
                                            <button type="button" class="btn btn-sm btn-outline-danger js-delete-backup-btn" title="حذف"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($lastOutput !== ''): ?>
        <div class="panel">
            <h3>تفاصيل الخطأ الأخير</h3>
            <div class="mono"><?php echo htmlspecialchars($lastOutput, ENT_QUOTES, 'UTF-8'); ?></div>
        </div>
    <?php endif; ?>
</div>
<?php ob_start(); ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var providerSelect = document.getElementById('cloud_provider');
    var connectProviderField = document.getElementById('connect_provider_field');
    function syncCloudPanels() {
        var v = providerSelect ? providerSelect.value : '';
        document.querySelectorAll('[data-cloud-panel]').forEach(function (box) {
            var id = box.getAttribute('data-cloud-panel') || '';
            var show = (v === '' || id === v);
            box.classList.toggle('is-visible', show);
            box.classList.toggle('is-active', show && (v === '' || id === v));
        });
    }
    if (providerSelect) {
        providerSelect.addEventListener('change', syncCloudPanels);
        syncCloudPanels();
    }
    document.querySelectorAll('.js-cloud-connect').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var provider = btn.getAttribute('data-provider') || '';
            if (connectProviderField) {
                connectProviderField.value = provider;
            }
            if (providerSelect && provider) {
                providerSelect.value = provider;
                syncCloudPanels();
            }
        });
    });

    var copyUriBtn = document.getElementById('copyOauthRedirectUri');
    var uriTextEl = document.getElementById('oauthRedirectUriText');
    if (copyUriBtn && uriTextEl) {
        copyUriBtn.addEventListener('click', function () {
            var text = (uriTextEl.textContent || '').trim();
            function doneOk() {
                Swal.fire({
                    icon: 'success',
                    title: 'تم النسخ',
                    text: 'تم نسخ Redirect URI.',
                    timer: 1600,
                    showConfirmButton: false
                });
            }
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(doneOk).catch(function () {
                    Swal.fire({ icon: 'info', title: 'انسخ يدوياً', text: text, confirmButtonColor: '#9B7D3B' });
                });
            } else {
                Swal.fire({ icon: 'info', title: 'انسخ يدوياً', text: text, confirmButtonColor: '#9B7D3B' });
            }
        });
    }

    document.querySelectorAll('[data-cron-copy]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var text = (btn.getAttribute('data-cron-copy') || '').trim();
            if (!text) return;
            function doneOk() {
                Swal.fire({
                    icon: 'success',
                    title: 'تم النسخ',
                    text: 'تم نسخ أمر Cron.',
                    timer: 1400,
                    showConfirmButton: false
                });
            }
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(doneOk).catch(function () {
                    Swal.fire({ icon: 'info', title: 'انسخ يدوياً', text: text, confirmButtonColor: '#9B7D3B' });
                });
            } else {
                Swal.fire({ icon: 'info', title: 'انسخ يدوياً', text: text, confirmButtonColor: '#9B7D3B' });
            }
        });
    });

    var btn = document.getElementById('restoreBackupBtn');
    var form = document.getElementById('restoreBackupForm');
    var select = document.getElementById('restore_file');
    if (btn && form && select) {
        btn.addEventListener('click', function () {
            if (!select.value) {
                Swal.fire({
                    icon: 'warning',
                    title: 'تنبيه',
                    text: 'اختر ملف النسخة الاحتياطية أولاً.',
                    confirmButtonColor: '#9B7D3B'
                });
                return;
            }
            Swal.fire({
                icon: 'warning',
                title: 'تأكيد الاسترجاع',
                text: 'سيتم استبدال بيانات قاعدة <?php echo htmlspecialchars($dbNameLabel, ENT_QUOTES, 'UTF-8'); ?> الحالية. هل أنت متأكد؟',
                showCancelButton: true,
                confirmButtonText: 'نعم، استرجاع',
                cancelButtonText: 'إلغاء',
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d'
            }).then(function (result) {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    }

    document.querySelectorAll('.js-delete-backup-btn').forEach(function (delBtn) {
        delBtn.addEventListener('click', function () {
            var delForm = delBtn.closest('form');
            if (!delForm) return;
            Swal.fire({
                icon: 'warning',
                title: 'تأكيد الحذف',
                text: 'هل تريد حذف هذه النسخة الاحتياطية نهائيًا؟',
                showCancelButton: true,
                confirmButtonText: 'نعم، حذف',
                cancelButtonText: 'إلغاء',
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d'
            }).then(function (result) {
                if (result.isConfirmed) {
                    delForm.submit();
                }
            });
        });
    });
});
</script><?php $pageScripts = ob_get_clean(); ?>
<?php require __DIR__ . '/../includes/footer.php';
