<?php
/**
 * المصادقة والصلاحيات - DMS
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/security.php';

dms_apply_runtime_security();
dms_send_security_headers();
dms_configure_session();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: text/html; charset=utf-8');
mb_internal_encoding('UTF-8');

require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/lookups.php';
require_once __DIR__ . '/archive.php';
require_once __DIR__ . '/entity_groups.php';
require_once __DIR__ . '/whatsapp_settings.php';
require_once __DIR__ . '/correspondence_send.php';
require_once __DIR__ . '/notifications.php';
require_once __DIR__ . '/letter_templates.php';
require_once __DIR__ . '/workflow.php';
require_once __DIR__ . '/user_departments.php';
require_once __DIR__ . '/user_coverage.php';
require_once __DIR__ . '/sidebar_menu.php';
require_once __DIR__ . '/briefing.php';
require_once __DIR__ . '/closure.php';
require_once __DIR__ . '/close_requests.php';
require_once __DIR__ . '/issuing.php';
require_once __DIR__ . '/circulars.php';
require_once __DIR__ . '/internal.php';
require_once __DIR__ . '/outgoing_approval.php';
require_once __DIR__ . '/outgoing_draft.php';
require_once __DIR__ . '/corr_cc.php';
require_once __DIR__ . '/mail_draft.php';
require_once __DIR__ . '/roles.php';
require_once __DIR__ . '/permissions.php';

try {
    dms_run_schema_bootstrap();
} catch (Throwable $e) {
    // تجاهل أثناء التثبيت الأول
}

// معالجة تبديل اللغة مبكراً
dms_lang_code();
dms_load_dict();

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    static $user = null;
    static $loaded = false;
    if (!$loaded) {
        $load = static function () use (&$user): void {
            $stmt = db()->prepare(
                "SELECT u.*, d.name AS department_name, d.name_en AS department_name_en
                 FROM `tblusers` u
                 LEFT JOIN `tbldepartments` d ON d.id = u.department_id
                 WHERE u.id = ? AND u.is_active = 1"
            );
            $stmt->execute([(int) $_SESSION['user_id']]);
            $user = $stmt->fetch() ?: null;
            if (!$user) {
                unset($_SESSION['user_id']);
            } elseif (function_exists('attach_user_department_scope')) {
                $user = attach_user_department_scope($user);
            }
        };
        try {
            $load();
        } catch (PDOException $e) {
            if (!function_exists('db_is_gone_away') || !db_is_gone_away($e)) {
                throw $e;
            }
            db_reconnect();
            $load();
        }
        $loaded = true;
    }
    return $user;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        flash('warning', __('auth.need_login'));
        redirect(base_url('auth/login.php'));
    }
    enforce_idle_timeout();
    touch_user_session();
    return $user;
}

function require_role(array $roles): array
{
    $user = require_login();
    if (!in_array($user['role'], $roles, true)) {
        log_security('UNAUTHORIZED_ACCESS', 'WARNING', 'role=' . $user['role'] . ' page=' . ($_SERVER['REQUEST_URI'] ?? ''), (int) $user['id']);
        flash('danger', __('auth.no_permission'));
        redirect(base_url('index.php'));
    }
    return $user;
}

/** @return list<string> */
function dms_user_roles(): array
{
    return ['admin', 'communications', 'archive', 'department', 'viewer'];
}

function can_manage_users(?array $user = null): bool
{
    $user = $user ?? current_user();
    return $user && $user['role'] === 'admin';
}

function can_edit_correspondence(?array $user = null): bool
{
    $user = $user ?? current_user();
    if (!$user || !in_array($user['role'] ?? '', ['admin', 'communications', 'archive', 'department'], true)) {
        return false;
    }
    if (($user['role'] ?? '') === 'admin' || !function_exists('user_can')) {
        return true;
    }
    return user_can('outgoing.edit', $user)
        || user_can('incoming.edit', $user)
        || user_can('internal.edit', $user);
}

function can_choose_correspondence_department(?array $user = null): bool
{
    $user = $user ?? current_user();
    // بوابة وارد مركزية: التسجيل لأي قسم (صلاحية corr.any_department)
    return $user && user_can('corr.any_department', $user);
}

function can_delete(?array $user = null): bool
{
    $user = $user ?? current_user();
    return $user && in_array($user['role'], ['admin', 'archive'], true);
}

function can_view_all_departments(?array $user = null): bool
{
    $user = $user ?? current_user();
    return $user && user_can('view.all_departments', $user);
}

function attempt_login(string $emailOrUser, string $password): bool
{
    $login = trim($emailOrUser);
    if ($login === '' || $password === '') {
        log_security('FAILED_LOGIN', 'WARNING', 'empty_credentials');
        return false;
    }

    // الدخول بالبريد الإلكتروني فقط (المعرف الفريد)
    $email = strtolower($login);
    $stmt = db()->prepare(
        "SELECT * FROM `tblusers` WHERE LOWER(TRIM(email)) = ? AND is_active = 1 LIMIT 1"
    );
    $stmt->execute([$email]);

    $user = $stmt->fetch();
    if (!$user || !password_verify($password, $user['password'])) {
        dms_record_failed_login($login);
        log_security('FAILED_LOGIN', 'WARNING', 'login=' . mb_substr($login, 0, 120));
        return false;
    }
    if (empty($user['email'])) {
        dms_record_failed_login($login);
        log_security('FAILED_LOGIN', 'WARNING', 'no_email user_id=' . (int) ($user['id'] ?? 0));
        return false;
    }
    dms_clear_login_attempts($login);
    $oldSid = session_id();
    session_regenerate_id(true);
    if ($oldSid !== '' && $oldSid !== session_id()) {
        remove_user_session($oldSid);
    }
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['last_activity'] = time();
    log_activity((int) $user['id'], null, 'login', 'تسجيل دخول');
    log_security('SUCCESSFUL_LOGIN', 'INFO', 'user=' . $user['email'], (int) $user['id']);
    register_user_session($user);
    return true;
}

function logout_user(): void
{
    $uid = $_SESSION['user_id'] ?? null;
    if ($uid) {
        log_activity((int) $uid, null, 'logout', 'تسجيل خروج');
        log_security('LOGOUT', 'INFO', '', (int) $uid);
    }
    remove_user_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
