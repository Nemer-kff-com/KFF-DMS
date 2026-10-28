<?php
declare(strict_types=1);

// Schedule times are wall-clock Arabia (same as rest of Invitations app).
date_default_timezone_set('Asia/Riyadh');

// Web hit on this file must never run the scheduler (defense if .htaccess is missing).
if (PHP_SAPI !== 'cli' && realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Forbidden');
}

/**
 * @return array<string,string>
 */
function dms_backup_cron_state_load(string $file): array
{
    if (!is_file($file)) {
        return [];
    }
    $raw = @file_get_contents($file);
    $json = is_string($raw) ? json_decode($raw, true) : null;

    return is_array($json) ? $json : [];
}

/**
 * @param array<string,string> $state
 */
function dms_backup_cron_state_save(string $file, array $state): void
{
    $json = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if (is_string($json)) {
        @file_put_contents($file, $json);
    }
}

function dms_backup_cron_state_file(): string
{
    return __DIR__ . '/scheduler_state.json';
}

/**
 * True when enough calendar days have passed since last successful run
 * (or since schedule start when never ran successfully).
 *
 * @param array<string,string> $state
 */
function dms_backup_cron_interval_due(array &$state, string $job, int $everyDays, ?string $stateFile = null): bool
{
    $everyDays = max(1, min(365, $everyDays));
    $today = date('Y-m-d');
    $last = trim((string) ($state[$job] ?? ''));
    if ($last === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $last)) {
        if ($everyDays === 1) {
            return true;
        }
        $start = dms_backup_cron_ensure_job_start($state, $job, $stateFile);
        $last = $start;
    }
    $lastTs = strtotime($last . ' 00:00:00');
    $todayTs = strtotime($today . ' 00:00:00');
    if ($lastTs === false || $todayTs === false) {
        return true;
    }
    $diffDays = (int) (($todayTs - $lastTs) / 86400);

    return $diffDays >= $everyDays;
}

/**
 * Persist first-seen schedule date so "every N days" stays stable before the first success.
 *
 * @param array<string,string> $state
 */
function dms_backup_cron_ensure_job_start(array &$state, string $job, ?string $stateFile = null): string
{
    $key = $job . '_start';
    $start = trim((string) ($state[$key] ?? ''));
    if ($start !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) {
        return $start;
    }
    $start = date('Y-m-d');
    $state[$key] = $start;
    if ($stateFile !== null && $stateFile !== '') {
        dms_backup_cron_state_save($stateFile, $state);
    }

    return $start;
}

/**
 * Next calendar date the job becomes due after a successful run on $lastYmd.
 */
function dms_backup_cron_next_due_ymd(string $lastYmd, int $everyDays): string
{
    $everyDays = max(1, min(365, $everyDays));
    if ($lastYmd === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $lastYmd)) {
        return date('Y-m-d');
    }
    $ts = strtotime($lastYmd . ' 00:00:00');
    if ($ts === false) {
        return date('Y-m-d');
    }

    return date('Y-m-d', $ts + ($everyDays * 86400));
}

/**
 * Next calendar date the job should run (interval + whether today's time slot remains).
 *
 * @param array<string,string> $state
 */
function dms_backup_cron_next_run_ymd(array &$state, string $job, string $lastYmd, string $timeHHMM, int $everyDays, ?string $stateFile = null): string
{
    $everyDays = max(1, min(365, $everyDays));
    $today = date('Y-m-d');
    $nowTime = date('H:i');
    $todayTs = strtotime($today . ' 00:00:00');
    if ($todayTs === false) {
        return $today;
    }

    if ($lastYmd === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $lastYmd)) {
        if ($everyDays === 1) {
            if ($nowTime < $timeHHMM || $nowTime === $timeHHMM) {
                return $today;
            }

            return date('Y-m-d', $todayTs + 86400);
        }
        $start = dms_backup_cron_ensure_job_start($state, $job, $stateFile);
        $byInterval = dms_backup_cron_next_due_ymd($start, $everyDays);
        if ($byInterval > $today) {
            return $byInterval;
        }
        if ($nowTime < $timeHHMM || $nowTime === $timeHHMM) {
            return $today;
        }

        return date('Y-m-d', $todayTs + 86400);
    }

    $byInterval = dms_backup_cron_next_due_ymd($lastYmd, $everyDays);
    if ($byInterval > $today) {
        return $byInterval;
    }

    if ($nowTime < $timeHHMM || $nowTime === $timeHHMM) {
        return $today;
    }

    return date('Y-m-d', $todayTs + 86400);
}

/**
 * @param array<string,mixed> $settings
 * @param array<string,string> $state
 * @return array{enabled:bool,time:string,every:int,last:string,next:string,status:string,ready_now:bool}
 */
function dms_backup_cron_job_view(array $settings, array &$state, string $job, string $enableKey, string $timeKey, string $defaultTime, int $everyDays, ?string $stateFile = null): array
{
    $enabled = !array_key_exists($enableKey, $settings) || !empty($settings[$enableKey]);
    $time = preg_match('/^\d{2}:\d{2}$/', (string) ($settings[$timeKey] ?? ''))
        ? (string) $settings[$timeKey]
        : $defaultTime;
    $everyDays = max(1, min(365, $everyDays));
    $last = trim((string) ($state[$job] ?? ''));
    if ($last !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $last)) {
        $last = '';
    }
    $next = dms_backup_cron_next_run_ymd($state, $job, $last, $time, $everyDays, $stateFile);
    $nowTime = date('H:i');
    $today = date('Y-m-d');
    $readyNow = false;
    $status = 'متوقف';

    if (!$enabled) {
        $status = 'متوقف';
        $next = '—';
    } elseif ($nowTime === $time && dms_backup_cron_interval_due($state, $job, $everyDays, $stateFile)) {
        $status = 'جاهز للتنفيذ الآن';
        $readyNow = true;
        $next = $today;
    } elseif ($last === '') {
        $status = 'أول تشغيل ' . $next . ' الساعة ' . $time . ' · كل ' . $everyDays . ' يوم';
    } elseif ($next > $today) {
        $status = 'القادم ' . $next . ' الساعة ' . $time;
    } elseif ($nowTime < $time) {
        $status = 'اليوم الساعة ' . $time;
    } else {
        $status = 'القادم ' . $next . ' الساعة ' . $time;
    }

    return [
        'enabled' => $enabled,
        'time' => $time,
        'every' => $everyDays,
        'last' => $last,
        'next' => $next,
        'status' => $status,
        'ready_now' => $readyNow,
    ];
}

/**
 * @param array<string,mixed> $settings
 * @return list<string>
 */
function dms_backup_cron_diagnose(array $settings, ?array $state = null): array
{
    $stateFile = dms_backup_cron_state_file();
    $state = $state ?? dms_backup_cron_state_load($stateFile);
    $dbEvery = max(1, min(365, (int) ($settings['db_backup_every_days'] ?? 1)));
    $projectEvery = max(1, min(365, (int) ($settings['project_backup_every_days'] ?? 1)));

    $jobs = [
        'قاعدة البيانات' => dms_backup_cron_job_view($settings, $state, 'backup', 'enable_db_backup', 'backup_time', '03:00', $dbEvery, $stateFile),
        'المشروع ZIP' => dms_backup_cron_job_view($settings, $state, 'project_backup', 'enable_project_backup', 'project_backup_time', '04:00', $projectEvery, $stateFile),
    ];

    $lines = ['تشخيص الجدولة · توقيت السيرفر: ' . date('Y-m-d H:i')];
    foreach ($jobs as $label => $view) {
        $lastLabel = $view['last'] !== '' ? $view['last'] : '—';
        $lines[] = $label . ': ' . $view['status']
            . ' | آخر نجاح: ' . $lastLabel
            . ' | القادم: ' . $view['next']
            . ' | كل ' . $view['every'] . ' يوم';
    }

    return $lines;
}

/**
 * @param array<string,mixed> $settings
 */
function dms_backup_cron_alert_recipient(array $settings): string
{
    $to = trim((string) ($settings['alert_email'] ?? ''));
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return '';
    }

    return $to;
}

/**
 * @param array<string,mixed> $settings
 * @param array{ok?:bool,output?:string,file?:string,cloud_uploaded?:bool}|null $result
 */
function dms_backup_cron_send_alert(
    array $settings,
    string $kind,
    string $jobLabel,
    string $details,
    ?array $result = null
): void {
    $kind = $kind === 'success' ? 'success' : 'failure';
    $enabledKey = $kind === 'success' ? 'alert_email_success_enabled' : 'alert_email_enabled';
    if (empty($settings[$enabledKey])) {
        return;
    }
    $to = dms_backup_cron_alert_recipient($settings);
    if ($to === '') {
        return;
    }

    require_once dirname(__DIR__, 2) . '/config/db.php';
    require_once dirname(__DIR__, 2) . '/config/helpers.php';
    require_once dirname(__DIR__, 2) . '/config/mailer.php';

    $host = gethostname() ?: 'server';
    $when = date('Y-m-d H:i:s');
    $file = '';
    if (is_array($result)) {
        $filePath = trim((string) ($result['file'] ?? ''));
        if ($filePath !== '') {
            $file = basename(str_replace('\\', '/', $filePath));
        }
    }
    $cloud = is_array($result) && !empty($result['cloud_uploaded']) ? 'نعم' : 'لا';

    if ($kind === 'success') {
        $subject = '[DMS Backup] نجاح: ' . $jobLabel;
        $intro = 'اكتملت مهمة النسخ الاحتياطي المجدولة بنجاح.';
        $textLead = 'Backup job succeeded';
    } else {
        $subject = '[DMS Backup] فشل: ' . $jobLabel;
        $intro = 'فشلت مهمة النسخ الاحتياطي المجدولة.';
        $textLead = 'Backup job failed';
    }

    $safeDetails = htmlspecialchars($details, ENT_QUOTES, 'UTF-8');
    $safeJob = htmlspecialchars($jobLabel, ENT_QUOTES, 'UTF-8');
    $safeFile = htmlspecialchars($file !== '' ? $file : '—', ENT_QUOTES, 'UTF-8');
    $html = '<p dir="rtl">' . htmlspecialchars($intro, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p dir="rtl"><strong>المهمة:</strong> ' . $safeJob . '</p>'
        . '<p dir="rtl"><strong>الوقت:</strong> ' . htmlspecialchars($when, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p dir="rtl"><strong>السيرفر:</strong> ' . htmlspecialchars((string) $host, ENT_QUOTES, 'UTF-8') . '</p>';
    if ($kind === 'success') {
        $html .= '<p dir="rtl"><strong>الملف:</strong> <span dir="ltr">' . $safeFile . '</span></p>'
            . '<p dir="rtl"><strong>رُفع للسحابة:</strong> ' . $cloud . '</p>';
    }
    if ($details !== '') {
        $html .= '<pre style="white-space:pre-wrap;direction:ltr;text-align:left">' . $safeDetails . '</pre>';
    }

    $text = "{$textLead}: {$jobLabel}\nTime: {$when}\nHost: {$host}";
    if ($kind === 'success') {
        $text .= "\nFile: " . ($file !== '' ? $file : '-') . "\nCloud uploaded: " . ($cloud === 'نعم' ? 'yes' : 'no');
    }
    if ($details !== '') {
        $text .= "\n\n{$details}";
    }

    $error = null;
    try {
        if (!dms_send_mail($to, $subject, $html, $text, $error) && $error) {
            fwrite(STDERR, '[backup-alert] ' . $error . PHP_EOL);
        }
    } catch (Throwable $e) {
        fwrite(STDERR, '[backup-alert] ' . $e->getMessage() . PHP_EOL);
    }
}

/**
 * @param array<string,mixed> $settings
 */
function dms_backup_cron_send_failure_alert(array $settings, string $jobLabel, string $details): void
{
    dms_backup_cron_send_alert($settings, 'failure', $jobLabel, $details);
}

/**
 * @param array<string,mixed> $settings
 * @param array{ok?:bool,output?:string,file?:string,cloud_uploaded?:bool} $result
 */
function dms_backup_cron_send_success_alert(array $settings, string $jobLabel, array $result): void
{
    dms_backup_cron_send_alert(
        $settings,
        'success',
        $jobLabel,
        (string) ($result['output'] ?? ''),
        $result
    );
}

/**
 * CLI scheduler — run every minute from cPanel Cron; executes enabled jobs at their time
 * when the configured day-interval has elapsed since the last successful run.
 */
function dms_backup_cron_scheduler_run(): int
{
    if (PHP_SAPI !== 'cli') {
        fwrite(STDERR, "CLI only.\n");
        return 1;
    }

    require_once dirname(__DIR__, 2) . '/config/backup/mysql_runner.php';
    require_once dirname(__DIR__, 2) . '/config/backup/project_runner.php';
    require_once dirname(__DIR__, 2) . '/config/backup/db.php';

    $settingsFile = __DIR__ . '/ui_settings.json';
    $stateFile = dms_backup_cron_state_file();
    $raw = is_file($settingsFile) ? @file_get_contents($settingsFile) : false;
    if (is_string($raw) && strncmp($raw, "\xEF\xBB\xBF", 3) === 0) {
        $raw = substr($raw, 3);
    }
    $json = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($json)) {
        fwrite(STDERR, "Settings file not found or invalid JSON.\n");
        return 1;
    }

    $settings = dms_backup_settings_apply_environment(
        dms_backup_cloud_merge_settings($json),
        true,
        $settingsFile
    );
    $backupDir = rtrim(str_replace('\\', '/', trim((string) ($settings['host_backup_dir'] ?? ''))), '/');
    $projectRoot = rtrim(str_replace('\\', '/', trim((string) ($settings['host_project_root'] ?? ''))), '/');

    $nowTime = date('H:i');
    $backupTime = preg_match('/^\d{2}:\d{2}$/', (string) ($settings['backup_time'] ?? ''))
        ? (string) $settings['backup_time']
        : '03:00';
    $projectBackupTime = preg_match('/^\d{2}:\d{2}$/', (string) ($settings['project_backup_time'] ?? ''))
        ? (string) $settings['project_backup_time']
        : '04:00';

    $dbEvery = max(1, min(365, (int) ($settings['db_backup_every_days'] ?? 1)));
    $projectEvery = max(1, min(365, (int) ($settings['project_backup_every_days'] ?? 1)));
    $sqlKeep = max(1, min(90, (int) ($settings['keep_days'] ?? 14)));
    $projectKeep = max(1, min(365, (int) ($settings['project_keep_days'] ?? $sqlKeep)));

    $state = dms_backup_cron_state_load($stateFile);
    $exitCode = 0;
    $dbBackupOn = !array_key_exists('enable_db_backup', $settings) || !empty($settings['enable_db_backup']);
    $projectBackupOn = !array_key_exists('enable_project_backup', $settings) || !empty($settings['enable_project_backup']);

    if (
        $dbBackupOn
        && $nowTime === $backupTime
        && dms_backup_cron_interval_due($state, 'backup', $dbEvery, $stateFile)
        && dms_backup_cron_acquire_lock('backup')
    ) {
        $res = dms_backup_mysql_create($backupDir, $sqlKeep, false, $settings);
        echo '[db-backup] every=' . $dbEvery . 'd ' . $res['output'] . PHP_EOL;
        if (!$res['ok']) {
            $exitCode = $res['code'] > 0 ? $res['code'] : 1;
            dms_backup_cron_send_failure_alert($settings, 'نسخ قاعدة البيانات', $res['output']);
        } else {
            $state['backup'] = date('Y-m-d');
            dms_backup_cron_state_save($stateFile, $state);
            dms_backup_cron_send_success_alert($settings, 'نسخ قاعدة البيانات', $res);
        }
    }

    if (
        $projectBackupOn
        && $nowTime === $projectBackupTime
        && dms_backup_cron_interval_due($state, 'project_backup', $projectEvery, $stateFile)
        && dms_backup_cron_acquire_lock('project_backup')
    ) {
        $res = dms_backup_project_create($projectRoot, $backupDir, false, $settings, $projectKeep);
        echo '[project-backup] every=' . $projectEvery . 'd ' . $res['output'] . PHP_EOL;
        if (!$res['ok']) {
            $exitCode = $res['code'] > 0 ? $res['code'] : 1;
            dms_backup_cron_send_failure_alert($settings, 'نسخ المشروع (ZIP)', $res['output']);
        } else {
            $state['project_backup'] = date('Y-m-d');
            dms_backup_cron_state_save($stateFile, $state);
            dms_backup_cron_send_success_alert($settings, 'نسخ المشروع (ZIP)', $res);
        }
    }

    return $exitCode;
}

function dms_backup_cron_acquire_lock(string $job): bool
{
    $lockDir = sys_get_temp_dir();
    $lockFile = $lockDir . DIRECTORY_SEPARATOR . 'dms_backup_' . $job . '_' . date('Ymd_Hi') . '.lock';
    if (is_file($lockFile)) {
        return false;
    }

    return @file_put_contents($lockFile, date('c')) !== false;
}

if (PHP_SAPI === 'cli' && realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    exit(dms_backup_cron_scheduler_run());
}
