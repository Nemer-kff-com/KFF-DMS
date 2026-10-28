<?php
/**
 * سجل الأمان والجلسات النشطة + حماية الإنتاج — DMS
 */

/** هل الطلب من بيئة محلية / شبكة داخلية (وليس استضافة عامة)؟ */
function dms_is_local_host(): bool
{
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $host = preg_replace('/:\d+$/', '', $host) ?: 'localhost';
    if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
        return true;
    }
    if (function_exists('dms_http_host_is_public')) {
        return !dms_http_host_is_public($host);
    }
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }
    foreach (['.local', '.lan', '.internal', '.localhost'] as $suffix) {
        if (str_ends_with($host, $suffix)) {
            return true;
        }
    }
    // اسم جهاز بدون نطاق (أي اسم سيرفر محلي تغيّره لاحقاً)
    return !str_contains($host, '.');
}

/** هل العميل يتصل من عنوان حلقي محلي؟ */
function dms_is_local_remote_addr(): bool
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    return in_array($ip, ['127.0.0.1', '::1'], true);
}

/**
 * هل يُوثق النظام ترويسات البروكسي؟
 * فعّل فقط خلف بروكسي موثوق (مثل Cloudflare) عبر DMS_TRUST_PROXY=1
 */
function dms_trust_proxy_headers(): bool
{
    $flag = strtolower(trim((string) (getenv('DMS_TRUST_PROXY') ?: '')));
    return in_array($flag, ['1', 'true', 'yes', 'on'], true);
}

/** هل الاتصال عبر HTTPS؟ */
function dms_is_https_request(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443) {
        return true;
    }
    if (dms_trust_proxy_headers()) {
        $fwd = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        if ($fwd === 'https') {
            return true;
        }
    }
    return false;
}

/** إعدادات تشغيل آمنة للإنتاج */
function dms_apply_runtime_security(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    if (dms_is_local_host()) {
        return;
    }

    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL);

    $logsDir = dirname(__DIR__) . '/logs';
    if (!is_dir($logsDir)) {
        @mkdir($logsDir, 0755, true);
    }
    if (is_dir($logsDir) && is_writable($logsDir)) {
        ini_set('error_log', $logsDir . '/php_errors.log');
    }
}

/** رؤوس أمان HTTP (مع HSTS على HTTPS في الإنتاج) */
function dms_send_security_headers(): void
{
    static $done = false;
    if ($done || headers_sent()) {
        return;
    }
    $done = true;

    header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-XSS-Protection: 1; mode=block');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

    if (dms_is_https_request()) {
        if (dms_is_local_host()) {
            // إلغاء HSTS السابق على الشبكة المحلية إن وُجد في المتصفح
            header('Strict-Transport-Security: max-age=0');
        } else {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }
}

/** ضبط كوكي الجلسة قبل session_start() */
function dms_configure_session(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    $secure = dms_is_https_request() && !dms_is_local_host();
    $path = '/';
    if (function_exists('dms_project_base')) {
        $base = dms_project_base();
        if ($base !== '' && $base !== '/') {
            $path = $base;
        }
    }

    $sessionDir = dirname(__DIR__) . '/sessions';
    if (!is_dir($sessionDir)) {
        @mkdir($sessionDir, 0700, true);
    }
    if (is_dir($sessionDir) && is_writable($sessionDir)) {
        session_save_path($sessionDir);
    }

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $path,
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    if ($secure) {
        ini_set('session.cookie_secure', '1');
    }
}

function dms_login_attempts_path(): string
{
    $dir = dirname(__DIR__) . '/var';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir . '/login_attempts.json';
}

function dms_login_attempt_key(string $login): string
{
    return hash('sha256', mb_strtolower(trim($login)) . '|' . client_ip());
}

/**
 * @return array{locked:bool, minutes:int}
 */
function dms_check_login_lockout(string $login): array
{
    $maxAttempts = 5;
    $lockoutSeconds = 900;
    $path = dms_login_attempts_path();
    $attempts = [];
    if (is_file($path)) {
        $attempts = json_decode((string) file_get_contents($path), true) ?: [];
    }

    $now = time();
    foreach ($attempts as $key => $data) {
        if ($now - (int) ($data['time'] ?? 0) > $lockoutSeconds) {
            unset($attempts[$key]);
        }
    }
    @file_put_contents($path, json_encode($attempts, JSON_UNESCAPED_UNICODE), LOCK_EX);

    $key = dms_login_attempt_key($login);
    if (isset($attempts[$key]) && (int) $attempts[$key]['count'] >= $maxAttempts) {
        $left = $lockoutSeconds - ($now - (int) $attempts[$key]['time']);
        if ($left > 0) {
            return ['locked' => true, 'minutes' => (int) max(1, ceil($left / 60))];
        }
    }

    return ['locked' => false, 'minutes' => 0];
}

function dms_record_failed_login(string $login): void
{
    $path = dms_login_attempts_path();
    $attempts = [];
    if (is_file($path)) {
        $attempts = json_decode((string) file_get_contents($path), true) ?: [];
    }
    $key = dms_login_attempt_key($login);
    if (!isset($attempts[$key])) {
        $attempts[$key] = ['count' => 0, 'time' => time()];
    }
    $attempts[$key]['count'] = (int) $attempts[$key]['count'] + 1;
    $attempts[$key]['time'] = time();
    @file_put_contents($path, json_encode($attempts, JSON_UNESCAPED_UNICODE), LOCK_EX);

    if (function_exists('log_security')) {
        log_security('FAILED_LOGIN', 'WARNING', 'lockout_count=' . $attempts[$key]['count'] . ' login=' . mb_substr($login, 0, 80));
    }
}

function dms_clear_login_attempts(string $login): void
{
    $path = dms_login_attempts_path();
    if (!is_file($path)) {
        return;
    }
    $attempts = json_decode((string) file_get_contents($path), true) ?: [];
    unset($attempts[dms_login_attempt_key($login)]);
    @file_put_contents($path, json_encode($attempts, JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function ensure_security_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        db()->exec(
            "CREATE TABLE IF NOT EXISTS `tblsecurity_log` (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                severity VARCHAR(20) NOT NULL DEFAULT 'INFO',
                event VARCHAR(80) NOT NULL,
                details TEXT NULL,
                ip_address VARCHAR(45) NULL,
                user_agent VARCHAR(255) NULL,
                user_id INT UNSIGNED NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_sec_created (created_at),
                INDEX idx_sec_event (event),
                INDEX idx_sec_severity (severity)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        db()->exec(
            "CREATE TABLE IF NOT EXISTS `tbluser_sessions` (
                session_id VARCHAR(128) NOT NULL PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                user_name VARCHAR(150) NULL,
                ip_address VARCHAR(45) NULL,
                user_agent VARCHAR(255) NULL,
                browser VARCHAR(100) NULL,
                os_name VARCHAR(100) NULL,
                last_activity DATETIME NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_sess_user (user_id),
                INDEX idx_sess_activity (last_activity),
                CONSTRAINT fk_sess_user FOREIGN KEY (user_id) REFERENCES `tblusers`(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        db()->exec(
            "CREATE TABLE IF NOT EXISTS `tblsession_kills` (
                session_id VARCHAR(128) NOT NULL PRIMARY KEY,
                killed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        // إثراء activity_log بـ IP إن لم يوجد
        if (!dms_db_column_exists('tblactivity_log', 'ip_address')) {
            db()->exec("ALTER TABLE `tblactivity_log` ADD COLUMN ip_address VARCHAR(45) NULL AFTER details");
        }
        if (!dms_db_column_exists('tblactivity_log', 'archive_id')) {
            db()->exec('ALTER TABLE `tblactivity_log` ADD COLUMN archive_id INT UNSIGNED DEFAULT NULL AFTER correspondence_id');
            try {
                db()->exec('ALTER TABLE `tblactivity_log` ADD INDEX idx_log_archive (archive_id)');
            } catch (Throwable $e) {
                // الفهرس موجود
            }
            try {
                db()->exec(
                    'ALTER TABLE `tblactivity_log` ADD CONSTRAINT fk_log_archive FOREIGN KEY (archive_id) REFERENCES `tblarchive_items`(id) ON DELETE SET NULL'
                );
            } catch (Throwable $e) {
                // القيد موجود أو جدول الأرشيف غير جاهز
            }
        }
        if (!dms_db_column_exists('tblactivity_log', 'circular_id')) {
            db()->exec('ALTER TABLE `tblactivity_log` ADD COLUMN circular_id INT UNSIGNED DEFAULT NULL');
            try {
                db()->exec('ALTER TABLE `tblactivity_log` ADD INDEX idx_log_circular (circular_id)');
            } catch (Throwable $e) {
                // الفهرس موجود
            }
        }

        // ترحيل سجل التسليم مرة واحدة فقط (لا في كل طلب)
        $migKey = 'activity_log_delivery_migrated';
        if (function_exists('setting') && setting($migKey, '') !== '1') {
            db()->exec("UPDATE `tblactivity_log` SET action = 'delivery' WHERE action = 'send_paper'");
            db()->exec(
                "UPDATE `tblactivity_log` SET details = ''
                 WHERE action = 'delivery'
                   AND (details LIKE '%ورقي%' OR LOWER(details) LIKE '%paper%deliver%')"
            );
            if (function_exists('set_setting')) {
                set_setting($migKey, '1');
            }
        }
    } catch (Throwable $e) {
        // قاعدة غير جاهزة
    }
}

function client_ip(): string
{
    // افتراضياً REMOTE_ADDR فقط — ترويسات X-Forwarded-For قابلة للتزوير بدون بروكسي موثوق
    if (dms_trust_proxy_headers()) {
        $keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
    } else {
        $keys = ['REMOTE_ADDR'];
    }
    foreach ($keys as $k) {
        if (empty($_SERVER[$k])) {
            continue;
        }
        $raw = (string) $_SERVER[$k];
        if ($k === 'HTTP_X_FORWARDED_FOR') {
            $parts = explode(',', $raw);
            $raw = trim($parts[0]);
        }
        if (filter_var($raw, FILTER_VALIDATE_IP)) {
            return $raw;
        }
    }
    return '';
}

function client_user_agent(): string
{
    return mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
}

/** الحد الأدنى لطول كلمة المرور */
function dms_password_min_length(): int
{
    return 8;
}

function dms_password_meets_policy(string $password): bool
{
    return strlen($password) >= dms_password_min_length();
}

function parse_browser_os(string $ua): array
{
    $browser = 'Unknown';
    $os = 'Unknown';
    if (preg_match('/Edg\/[\d.]+/i', $ua)) {
        $browser = 'Edge';
    } elseif (preg_match('/Chrome\/[\d.]+/i', $ua) && !preg_match('/Edg/i', $ua)) {
        $browser = 'Chrome';
    } elseif (preg_match('/Firefox\/[\d.]+/i', $ua)) {
        $browser = 'Firefox';
    } elseif (preg_match('/Safari\/[\d.]+/i', $ua) && !preg_match('/Chrome/i', $ua)) {
        $browser = 'Safari';
    } elseif (preg_match('/MSIE|Trident/i', $ua)) {
        $browser = 'IE';
    }

    if (preg_match('/Windows NT/i', $ua)) {
        $os = 'Windows';
    } elseif (preg_match('/Mac OS X/i', $ua)) {
        $os = 'macOS';
    } elseif (preg_match('/Android/i', $ua)) {
        $os = 'Android';
    } elseif (preg_match('/iPhone|iPad/i', $ua)) {
        $os = 'iOS';
    } elseif (preg_match('/Linux/i', $ua)) {
        $os = 'Linux';
    }

    return ['browser' => $browser, 'os' => $os];
}

function log_security(string $event, string $severity = 'INFO', string $details = '', ?int $userId = null): void
{
    ensure_security_schema();
    $severity = strtoupper($severity);
    if (!in_array($severity, ['INFO', 'WARNING', 'CRITICAL'], true)) {
        $severity = 'INFO';
    }
    try {
        db()->prepare(
            "INSERT INTO `tblsecurity_log` (severity, event, details, ip_address, user_agent, user_id)
             VALUES (?, ?, ?, ?, ?, ?)"
        )->execute([
            $severity,
            mb_substr($event, 0, 80),
            $details !== '' ? $details : null,
            client_ip() ?: null,
            client_user_agent() ?: null,
            $userId,
        ]);
    } catch (Throwable $e) {
        // لا تمنع التشغيل
    }
}

function dms_session_save_dir(): string
{
    $path = (string) session_save_path();
    if ($path !== '' && str_contains($path, ';')) {
        $parts = explode(';', $path);
        $path = (string) end($parts);
    }
    $path = rtrim($path, '/\\');
    if ($path !== '' && is_dir($path)) {
        return $path;
    }
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'sessions';
}

function dms_session_file_path(string $sessionId): string
{
    $sessionId = preg_replace('/[^A-Za-z0-9,-]/', '', $sessionId) ?? '';
    if ($sessionId === '') {
        return '';
    }
    return dms_session_save_dir() . DIRECTORY_SEPARATOR . 'sess_' . $sessionId;
}

function dms_session_file_exists(string $sessionId): bool
{
    $file = dms_session_file_path($sessionId);
    return $file !== '' && is_file($file);
}

function dms_unlink_session_file(string $sessionId): void
{
    if ($sessionId === '' || $sessionId === session_id()) {
        return;
    }
    $file = dms_session_file_path($sessionId);
    if ($file !== '' && is_file($file)) {
        @unlink($file);
    }
}

/** حذف الجلسات المنتهية أو بلا ملف جلسة — مع الإبقاء على الجلسة الحالية */
function purge_stale_user_sessions(?string $keepSessionId = null): int
{
    ensure_security_schema();
    $keepSessionId = $keepSessionId ?? session_id();
    $timeoutSec = max(5, session_timeout_minutes()) * 60;
    $count = 0;
    try {
        $rows = db()->query('SELECT session_id, last_activity FROM `tbluser_sessions`')->fetchAll();
        $del = db()->prepare('DELETE FROM `tbluser_sessions` WHERE session_id = ?');
        foreach ($rows as $row) {
            $sid = (string) ($row['session_id'] ?? '');
            if ($sid === '' || $sid === $keepSessionId) {
                continue;
            }
            $last = strtotime((string) ($row['last_activity'] ?? '')) ?: 0;
            $idleExpired = $last > 0 && (time() - $last) > $timeoutSec;
            if (!$idleExpired && dms_session_file_exists($sid)) {
                continue;
            }
            $del->execute([$sid]);
            dms_unlink_session_file($sid);
            $count++;
        }
        db()->exec('DELETE FROM `tblsession_kills` WHERE killed_at < (NOW() - INTERVAL 2 DAY)');
    } catch (Throwable $e) {
        return $count;
    }
    return $count;
}

/** جلسات قاعدة البيانات المنتهية، وملفات sess_* القديمة التي لا تتبع جلسة حية. @return list<string> */
function dms_expired_session_ids(): array
{
    ensure_security_schema();
    $keep = session_id();
    $timeoutSec = max(5, session_timeout_minutes()) * 60;
    $ids = [];
    $known = [];
    try {
        $rows = db()->query('SELECT session_id, last_activity FROM `tbluser_sessions`')->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        $rows = [];
    }
    foreach ($rows as $row) {
        $sid = (string) ($row['session_id'] ?? '');
        if ($sid === '') {
            continue;
        }
        $known[$sid] = true;
        if ($sid === $keep) {
            continue;
        }
        $last = strtotime((string) ($row['last_activity'] ?? '')) ?: 0;
        $idle = $last > 0 && (time() - $last) > $timeoutSec;
        if ($idle || !dms_session_file_exists($sid)) {
            $ids[$sid] = true;
        }
    }
    $dir = dms_session_save_dir();
    if (is_dir($dir)) {
        $entries = @scandir($dir);
        if (is_array($entries)) {
            foreach ($entries as $file) {
                if (!str_starts_with($file, 'sess_')) {
                    continue;
                }
                $sid = substr($file, 5);
                if ($sid === '' || $sid === $keep || isset($known[$sid])) {
                    continue;
                }
                $path = $dir . DIRECTORY_SEPARATOR . $file;
                $mtime = @filemtime($path) ?: 0;
                if ($mtime > 0 && (time() - $mtime) > $timeoutSec) {
                    $ids[$sid] = true;
                }
            }
        }
    }

    return array_keys($ids);
}

/** حذف الجلسات المنتهية وملفاتها، مع الإبقاء على الجلسة الحالية. */
function dms_purge_expired_sessions(): int
{
    ensure_security_schema();
    $ids = dms_expired_session_ids();
    $del = db()->prepare('DELETE FROM `tbluser_sessions` WHERE session_id = ?');
    $n = 0;
    foreach ($ids as $sid) {
        if ($sid === '' || $sid === session_id()) {
            continue;
        }
        $del->execute([$sid]);
        dms_unlink_session_file($sid);
        $n++;
    }
    try {
        db()->exec('DELETE FROM `tblsession_kills` WHERE killed_at < (NOW() - INTERVAL 2 DAY)');
    } catch (Throwable $e) {
        // الجدول قد يكون فارغاً
    }

    return $n;
}

/** سجل الأمان العادي بعد 90 يوماً، والحرج بعد سنة. @return array{info:int,critical:int} */
function dms_security_log_old_counts(): array
{
    ensure_security_schema();
    $info = (int) db()->query(
        "SELECT COUNT(*) FROM `tblsecurity_log`
         WHERE severity <> 'CRITICAL' AND created_at < (NOW() - INTERVAL 90 DAY)"
    )->fetchColumn();
    $critical = (int) db()->query(
        "SELECT COUNT(*) FROM `tblsecurity_log`
         WHERE severity = 'CRITICAL' AND created_at < (NOW() - INTERVAL 365 DAY)"
    )->fetchColumn();

    return ['info' => $info, 'critical' => $critical];
}

function dms_security_log_purge_old(): int
{
    ensure_security_schema();
    $n = (int) db()->exec(
        "DELETE FROM `tblsecurity_log`
         WHERE severity <> 'CRITICAL' AND created_at < (NOW() - INTERVAL 90 DAY)"
    );
    $n += (int) db()->exec(
        "DELETE FROM `tblsecurity_log`
         WHERE severity = 'CRITICAL' AND created_at < (NOW() - INTERVAL 365 DAY)"
    );

    return $n;
}

function register_user_session(array $user): void
{
    ensure_security_schema();
    $sid = session_id();
    if ($sid === '') {
        return;
    }
    $ua = client_user_agent();
    $parsed = parse_browser_os($ua);
    try {
        db()->prepare(
            "INSERT INTO `tbluser_sessions` (session_id, user_id, user_name, ip_address, user_agent, browser, os_name, last_activity)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE
                user_id = VALUES(user_id),
                user_name = VALUES(user_name),
                ip_address = VALUES(ip_address),
                user_agent = VALUES(user_agent),
                browser = VALUES(browser),
                os_name = VALUES(os_name),
                last_activity = NOW()"
        )->execute([
            $sid,
            (int) $user['id'],
            user_actor_name($user),
            client_ip() ?: null,
            $ua ?: null,
            $parsed['browser'],
            $parsed['os'],
        ]);
    } catch (Throwable $e) {
        // تجاهل
    }
}

function touch_user_session(): void
{
    if (empty($_SESSION['user_id'])) {
        return;
    }
    $sid = session_id();
    if ($sid === '') {
        return;
    }

    $now = time();
    // حدّث نشاط الجلسة في PHP دائماً (للخمول)
    $_SESSION['last_activity'] = $now;

    // فحص الإخراج القسري على الأكثر كل 30 ثانية
    $lastKillCheck = (int) ($_SESSION['_dms_kill_check_at'] ?? 0);
    $checkKill = ($now - $lastKillCheck) >= 30;
    // تحديث last_activity في قاعدة البيانات على الأكثر كل دقيقتين
    $lastDbTouch = (int) ($_SESSION['_dms_session_db_touch_at'] ?? 0);
    $touchDb = ($now - $lastDbTouch) >= 120;
    if (!$checkKill && !$touchDb) {
        return;
    }

    try {
        if ($checkKill) {
            $_SESSION['_dms_kill_check_at'] = $now;
            $kill = db()->prepare('SELECT session_id FROM `tblsession_kills` WHERE session_id = ?');
            $kill->execute([$sid]);
            if ($kill->fetchColumn()) {
                db()->prepare('DELETE FROM `tblsession_kills` WHERE session_id = ?')->execute([$sid]);
                db()->prepare('DELETE FROM `tbluser_sessions` WHERE session_id = ?')->execute([$sid]);
                $_SESSION = [];
                if (ini_get('session.use_cookies')) {
                    $p = session_get_cookie_params();
                    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
                }
                @session_destroy();
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }
                flash('warning', __('sessions.forced_logout'));
                redirect(base_url('auth/login.php'));
                return;
            }
        }

        if ($touchDb) {
            $_SESSION['_dms_session_db_touch_at'] = $now;
            $stmt = db()->prepare('SELECT session_id FROM `tbluser_sessions` WHERE session_id = ?');
            $stmt->execute([$sid]);
            if ($stmt->fetchColumn()) {
                db()->prepare('UPDATE `tbluser_sessions` SET last_activity = NOW() WHERE session_id = ?')->execute([$sid]);
            } else {
                $user = current_user();
                if ($user) {
                    register_user_session($user);
                }
            }
        }
    } catch (Throwable $e) {
        // تجاهل
    }
}

/** خروج تلقائي عند تجاوز مدة الخمول */
function enforce_idle_timeout(): void
{
    if (empty($_SESSION['user_id'])) {
        return;
    }
    $timeoutSec = session_timeout_minutes() * 60;
    $last = (int) ($_SESSION['last_activity'] ?? 0);
    if ($last <= 0) {
        $_SESSION['last_activity'] = time();
        return;
    }
    if ((time() - $last) <= $timeoutSec) {
        return;
    }

    $uid = (int) $_SESSION['user_id'];
    log_security('SESSION_TIMEOUT', 'INFO', 'idle_minutes=' . session_timeout_minutes(), $uid);
    logout_user();
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    flash('warning', __('auth.session_timeout'));
    redirect(base_url('auth/login.php'));
}

function disconnect_user_session(string $sessionId): bool
{
    ensure_security_schema();
    $sessionId = trim($sessionId);
    if ($sessionId === '' || $sessionId === session_id()) {
        return false;
    }
    try {
        db()->prepare('DELETE FROM `tbluser_sessions` WHERE session_id = ?')->execute([$sessionId]);
        db()->prepare(
            'INSERT INTO `tblsession_kills` (session_id, killed_at) VALUES (?, NOW())
             ON DUPLICATE KEY UPDATE killed_at = NOW()'
        )->execute([$sessionId]);
        dms_unlink_session_file($sessionId);
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function remove_user_session(?string $sessionId = null): void
{
    ensure_security_schema();
    $sid = $sessionId ?? session_id();
    if ($sid === '') {
        return;
    }
    try {
        db()->prepare('DELETE FROM `tbluser_sessions` WHERE session_id = ?')->execute([$sid]);
        if ($sessionId !== null && $sid !== session_id()) {
            dms_unlink_session_file($sid);
        }
    } catch (Throwable $e) {
        // تجاهل
    }
}

/** إنهاء كل جلسات مستخدم معيّن (بعد تغيير/إعادة تعيين كلمة المرور) */
function dms_invalidate_user_sessions(int $userId): int
{
    ensure_security_schema();
    if ($userId <= 0) {
        return 0;
    }
    $count = 0;
    try {
        $stmt = db()->prepare('SELECT session_id FROM `tbluser_sessions` WHERE user_id = ?');
        $stmt->execute([$userId]);
        $sids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($sids as $sid) {
            $sid = trim((string) $sid);
            if ($sid === '') {
                continue;
            }
            db()->prepare('DELETE FROM `tbluser_sessions` WHERE session_id = ?')->execute([$sid]);
            db()->prepare(
                'INSERT INTO `tblsession_kills` (session_id, killed_at) VALUES (?, NOW())
                 ON DUPLICATE KEY UPDATE killed_at = NOW()'
            )->execute([$sid]);
            dms_unlink_session_file($sid);
            $count++;
        }
    } catch (Throwable $e) {
        return $count;
    }
    return $count;
}

function security_event_label(string $event): string
{
    $key = 'security.event.' . strtolower($event);
    $t = __($key);
    return $t !== $key ? $t : $event;
}

function security_severity_label(string $severity): string
{
    $key = 'security.sev.' . strtolower($severity);
    $t = __($key);
    return $t !== $key ? $t : $severity;
}

/** يعرض سطور سجل مسودة الصادر بصيغة «صاحب الشأن»، بما فيها السطور المحفوظة سابقاً. */
function outgoing_prep_log_details_for_display(string $details): string
{
    $details = trim($details);
    $exact = [
        'إرسال مسودة الصادر' => 'out_prep.log_submit',
        'إرسال مسودة الصادر لصاحب الشأن' => 'out_prep.log_submit',
        'Outgoing draft sent' => 'out_prep.log_submit',
        'Outgoing draft sent to issuing-office owner' => 'out_prep.log_submit',
    ];
    if (isset($exact[$details])) {
        return __($exact[$details]);
    }
    $prefixes = [
        'إعادة مسودة الصادر للمُعدّ' => 'out_prep.log_return',
        'Outgoing draft returned to creator' => 'out_prep.log_return',
        'تحويل المسودة إلى صادر رقم' => 'out_prep.log_convert',
        'Draft converted to outgoing no.' => 'out_prep.log_convert',
    ];
    foreach ($prefixes as $old => $key) {
        if ($details === $old) {
            return __($key);
        }
        if (str_starts_with($details, $old)) {
            return __($key) . mb_substr($details, mb_strlen($old));
        }
    }
    return $details;
}

function activity_action_label(string $action): string
{
    $key = 'activity.action.' . $action;
    $t = __($key);
    return $t !== $key ? $t : $action;
}

function activity_details_for_display(string $action, string $details): string
{
    $details = trim($details);
    if ($details === '') {
        return '';
    }
    if (in_array($action, ['send_paper', 'delivery'], true)) {
        return '';
    }
    if ($action === 'create') {
        $labels = [
            'القسم المعدّ',
            'موجهة من',
            'Preparing department',
            'Directed from',
        ];
        $bits = [];
        foreach ($labels as $label) {
            $bits[] = preg_quote($label, '/');
        }
        $details = preg_replace('/\s*—\s*(?:' . implode('|', $bits) . ')\s*:.*$/u', '', $details) ?? $details;
        $details = trim($details);
    }
    if (preg_match('/ورقي/u', $details) || preg_match('/paper\s*deliver/i', $details)) {
        return '';
    }
    if ($action === 'outgoing_prep') {
        $details = outgoing_prep_log_details_for_display($details);
    }
    $actionLabel = activity_action_label($action);
    if ($actionLabel !== $action && $details === $actionLabel) {
        return '';
    }
    // نصوص قديمة كانت تُخزَّن مع كود الإجراء — أخفِها إن طابقت المعنى
    $legacySame = [
        'issue_approval_request' => [
            'طلب موافقة إصدار الصادر',
            'طلب موافقة الإصدار',
            'Issue approval requested',
        ],
        'issue_approval_approve' => [
            'اعتماد موافقة إصدار الصادر',
            'اعتماد الإصدار',
            'Issue approved',
        ],
        'issue_approval_reject' => [
            'رفض موافقة إصدار الصادر',
            'رفض موافقة الإصدار',
            'Issue approval rejected',
        ],
        'issue_approval_escalate' => [
            'تصعيد موافقة الإصدار إلى الأمين العام المساعد بعد المهلة',
            'تصعيد موافقة الإصدار',
            'Issue approval escalated',
        ],
        'archive_create' => [
            'إضافة أرشيف',
            'Archive create',
        ],
        'archive_update' => [
            'تحديث أرشيف',
            'Archive update',
        ],
    ];
    if (isset($legacySame[$action]) && in_array($details, $legacySame[$action], true)) {
        return '';
    }
    return $details;
}
