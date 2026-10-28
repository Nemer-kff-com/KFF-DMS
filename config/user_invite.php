<?php
/**
 * دعوة مستخدم بالبريد: رابط النظام + الاسم + البريد (الدخول) + كلمة مرور مؤقتة جديدة
 */
require_once __DIR__ . '/mailer.php';

function dms_generate_temp_password(int $length = 10): string
{
    $length = max(dms_password_min_length(), $length);
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
    $max = strlen($chars) - 1;
    $out = '';
    for ($i = 0; $i < $length; $i++) {
        $out .= $chars[random_int(0, $max)];
    }
    return $out;
}

/**
 * رتبة عنوان الرابط للبريد: أعلى = أفضل للمستلمين الخارجيين.
 * 3 = نطاق/IP عام، 2 = اسم مضيف داخلي، 1 = شبكة محلية، 0 = localhost
 */
function dms_invite_url_rank(string $url): int
{
    $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?: ''));
    if ($host === '' || $host === 'localhost' || $host === '127.0.0.1' || $host === '::1') {
        return 0;
    }
    if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        if (function_exists('dms_ipv4_is_lan') && dms_ipv4_is_lan($host)) {
            return 1;
        }
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return 3;
        }
        return 1;
    }
    if (function_exists('dms_http_host_is_public') && dms_http_host_is_public($host)) {
        return 3;
    }
    return 2;
}

/** رابط الدخول في البريد: يفضّل الدومين العام، ثم الإعداد، ثم IP الشبكة — ليس localhost. */
function dms_user_invite_login_url(): string
{
    return dms_mail_public_base_url() . '/auth/login.php';
}

/** رابط صفحة داخل النظام صالح للفتح من البريد (نفس أولوية رابط الدعوة). */
function dms_mail_public_url(string $path): string
{
    return dms_mail_public_base_url() . '/' . ltrim($path, '/');
}

/** أساس روابط البريد: الدومين العام، ثم الإعداد، ثم IP الشبكة — ليس localhost. */
function dms_mail_public_base_url(): string
{
    $candidates = [];

    $configured = trim((string) setting('qr_scan_base_url', ''));
    if ($configured !== '') {
        $candidates[] = rtrim($configured, '/');
    }

    // عند فتح النظام من الدومين العام بعد الرفع — استخدمه مباشرة
    $requestBase = rtrim(dms_detect_base_url(), '/');
    if ($requestBase !== '') {
        $candidates[] = $requestBase;
    }

    if (function_exists('dms_corr_qr_base_url')) {
        $candidates[] = rtrim(dms_corr_qr_base_url(), '/');
    }

    $lan = function_exists('dms_detect_lan_ipv4') ? dms_detect_lan_ipv4() : '';
    if ($lan !== '') {
        $scheme = function_exists('dms_request_scheme') ? dms_request_scheme() : 'http';
        $path = function_exists('dms_project_base') ? dms_project_base() : '/dms';
        $candidates[] = rtrim($scheme . '://' . $lan . $path, '/');
    }

    $pick = $requestBase !== '' ? $requestBase : 'http://localhost/dms';
    $bestRank = -1;
    foreach ($candidates as $candidate) {
        $candidate = rtrim((string) $candidate, '/');
        if ($candidate === '') {
            continue;
        }
        $rank = dms_invite_url_rank($candidate);
        if ($rank > $bestRank) {
            $bestRank = $rank;
            $pick = $candidate;
        }
    }

    return $pick;
}

/** @return array{subject:string,html:string,text:string,embeds:list<array{path:string,cid:string,name:string}>} */
function dms_user_invite_build_email(array $user, string $plainPassword, string $loginUrl): array
{
    $name = trim((string) user_display_name($user));
    if ($name === '') {
        $name = (string) ($user['email'] ?? '');
    }
    $email = trim((string) ($user['email'] ?? ''));
    $safeName = e($name);
    $safeEmail = e($email);
    $safePass = e($plainPassword);
    $safeUrl = e($loginUrl);
    $font = dms_mail_font_family();
    $accent = '#3B4F6E';
    $bg = '#f8f6f2';
    $border = '#e6e2da';
    $muted = '#6b7280';
    $subject = __('users.invite_email_subject') . ' — ' . org_name();
    $greeting = e(__('users.invite_email_greeting')) . ' ' . $safeName . '،';

    $body = '<p style="margin:0 0 14px;font-size:16px;font-weight:700;color:' . $accent . ';font-family:' . $font . ';">' . $greeting . '</p>'
        . '<p style="margin:0 0 18px;font-size:14px;color:' . $accent . ';font-family:' . $font . ';">' . e(__('users.invite_email_intro')) . '</p>'
        . '<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin:0 0 22px;background:' . $bg . ';border:1px solid ' . $border . ';border-radius:12px;">'
        . '<tr><td style="padding:16px 18px;font-size:14px;color:' . $accent . ';font-family:' . $font . ';">'
        . '<div style="margin:0 0 12px;"><span style="display:block;font-size:11px;color:' . $muted . ';margin-bottom:2px;font-family:' . $font . ';">' . e(__('users.invite_email_name')) . '</span>'
        . '<strong style="font-size:15px;font-family:' . $font . ';">' . $safeName . '</strong></div>'
        . '<div style="margin:0 0 12px;"><span style="display:block;font-size:11px;color:' . $muted . ';margin-bottom:2px;font-family:' . $font . ';">' . e(__('users.invite_email_login')) . '</span>'
        . '<strong dir="ltr" style="unicode-bidi:embed;font-size:15px;font-family:Arial,sans-serif;">' . $safeEmail . '</strong></div>'
        . '<div style="margin:0;"><span style="display:block;font-size:11px;color:' . $muted . ';margin-bottom:2px;font-family:' . $font . ';">' . e(__('users.invite_email_password')) . '</span>'
        . '<strong dir="ltr" style="unicode-bidi:embed;font-family:Consolas,Arial,monospace;font-size:16px;letter-spacing:.04em;">' . $safePass . '</strong></div>'
        . '</td></tr></table>'
        . '<p style="text-align:center;margin:0 0 16px;">'
        . '<a href="' . $safeUrl . '" style="display:inline-block;padding:12px 28px;background:' . $accent . ';color:#ffffff;text-decoration:none;font-weight:700;border-radius:8px;font-size:14px;font-family:' . $font . ';">'
        . e(__('users.invite_email_cta')) . '</a></p>'
        . '<p style="margin:0 0 6px;font-size:12px;color:' . $muted . ';font-family:' . $font . ';">' . e(__('users.invite_email_link')) . ':</p>'
        . '<p style="margin:0 0 18px;font-size:12px;word-break:break-all;direction:ltr;text-align:left;font-family:Arial,sans-serif;">'
        . '<a href="' . $safeUrl . '" style="color:' . $accent . ';">' . $safeUrl . '</a></p>';

    $shell = dms_mail_branded_shell($body, __('users.invite_email_footer'));

    $text = __('users.invite_email_greeting') . ' ' . $name . "،\n\n"
        . __('users.invite_email_intro') . "\n\n"
        . __('users.invite_email_name') . ': ' . $name . "\n"
        . __('users.invite_email_login') . ': ' . $email . "\n"
        . __('users.invite_email_password') . ': ' . $plainPassword . "\n"
        . __('users.invite_email_link') . ":\n" . $loginUrl . "\n\n"
        . __('users.invite_email_footer') . "\n";

    return [
        'subject' => $subject,
        'html' => $shell['html'],
        'text' => $text,
        'embeds' => $shell['embeds'],
    ];
}

/**
 * يولّد كلمة مرور جديدة، يحفظها، ويرسل الدعوة بالبريد.
 * @param-out string|null $sentTo يُملأ بعنوان المستلم عند النجاح
 * @return bool
 */
function dms_user_invite_send(int $userId, ?string &$error = null, ?int $actorId = null, ?string &$sentTo = null): bool
{
    $error = null;
    $sentTo = null;
    if ($userId <= 0) {
        $error = __('users.not_found');
        return false;
    }

    $stmt = db()->prepare('SELECT * FROM `tblusers` WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $target = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$target) {
        $error = __('users.not_found');
        return false;
    }

    $email = trim((string) ($target['email'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = __('users.invite_no_email');
        return false;
    }

    if (!(int) ($target['is_active'] ?? 0)) {
        $error = __('users.invite_inactive');
        return false;
    }

    $plain = dms_generate_temp_password(10);
    if (!dms_password_meets_policy($plain)) {
        $error = __('users.invite_failed');
        return false;
    }

    $hash = password_hash($plain, PASSWORD_DEFAULT);
    db()->prepare('UPDATE `tblusers` SET password = ? WHERE id = ?')->execute([$hash, $userId]);

    if (function_exists('dms_invalidate_user_sessions')) {
        dms_invalidate_user_sessions($userId);
    }

    $loginUrl = dms_user_invite_login_url();
    $mail = dms_user_invite_build_email($target, $plain, $loginUrl);
    $ok = dms_send_mail(
        $email,
        $mail['subject'],
        $mail['html'],
        $mail['text'],
        $error,
        false,
        null,
        false,
        null,
        $mail['embeds'] ?? []
    );

    if (!$ok) {
        if ($error === null || $error === '') {
            $error = __('users.invite_failed');
        }
        return false;
    }

    $sentTo = $email;
    log_activity($actorId, null, 'user_invite', 'دعوة مستخدم #' . $userId . ' — ' . $email);

    return true;
}
