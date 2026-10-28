<?php
/**
 * نسيان / إعادة تعيين كلمة المرور — بنفس أسلوب نظام الدعوات
 */
require_once __DIR__ . '/mailer.php';

function dms_normalize_email(string $email): string
{
    return strtolower(trim($email));
}

function dms_password_reset_token_hash(string $token): string
{
    return hash('sha256', $token);
}

function ensure_password_reset_table(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    db()->exec(
        "CREATE TABLE IF NOT EXISTS `tblpassword_resets` (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            token VARCHAR(64) NOT NULL UNIQUE,
            expires_at DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_pr_user FOREIGN KEY (user_id) REFERENCES `tblusers`(id) ON DELETE CASCADE,
            INDEX idx_pr_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function dms_find_user_by_email(string $email): ?array
{
    $email = dms_normalize_email($email);
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return null;
    }
    $stmt = db()->prepare(
        "SELECT * FROM `tblusers` WHERE LOWER(TRIM(email)) = ? AND is_active = 1 LIMIT 1"
    );
    $stmt->execute([$email]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function dms_password_reset_issue(string $email): ?array
{
    ensure_password_reset_table();
    $user = dms_find_user_by_email($email);
    if (!$user) {
        return null;
    }

    // إلغاء الرموز السابقة لنفس المستخدم
    db()->prepare("DELETE FROM `tblpassword_resets` WHERE user_id = ? OR expires_at < NOW()")
        ->execute([(int) $user['id']]);

    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + 3600);
    db()->prepare(
        "INSERT INTO `tblpassword_resets` (user_id, token, expires_at) VALUES (?, ?, ?)"
    )->execute([(int) $user['id'], dms_password_reset_token_hash($token), $expires]);

    return ['token' => $token, 'user' => $user];
}

function dms_password_reset_get(string $token): ?array
{
    ensure_password_reset_table();
    $token = trim($token);
    if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }
    $stmt = db()->prepare(
        "SELECT pr.*, u.email, u.name_ar, u.name_en
         FROM `tblpassword_resets` pr
         JOIN `tblusers` u ON u.id = pr.user_id
         WHERE pr.token = ? AND pr.expires_at >= NOW() AND u.is_active = 1
         LIMIT 1"
    );
    $stmt->execute([dms_password_reset_token_hash($token)]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function dms_password_reset_apply(string $token, string $newPassword): bool
{
    $entry = dms_password_reset_get($token);
    if (!$entry) {
        return false;
    }
    if (!dms_password_meets_policy($newPassword)) {
        return false;
    }
    $userId = (int) $entry['user_id'];
    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    db()->prepare("UPDATE `tblusers` SET password = ? WHERE id = ?")
        ->execute([$hash, $userId]);
    db()->prepare("DELETE FROM `tblpassword_resets` WHERE user_id = ?")
        ->execute([$userId]);
    if (function_exists('dms_invalidate_user_sessions')) {
        dms_invalidate_user_sessions($userId);
    }
    log_activity($userId, null, 'password_reset', 'إعادة تعيين كلمة المرور عبر البريد');
    return true;
}

function dms_password_reset_link(string $token): string
{
    return base_url('auth/r.php?t=' . urlencode($token));
}

function dms_password_reset_build_email(array $user, string $resetLink): array
{
    $name = e(user_display_name($user) ?: $user['email']);
    $org = e(org_name());
    $orgEn = e(org_name_en());
    $safeLink = e($resetLink);
    $accent = '#3B4F6E';

    $subject = 'طلب إعادة تعيين كلمة المرور — ' . org_name();
    $font = dms_mail_font_family();
    $html = '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="UTF-8">' . dms_mail_font_face_css() . '</head>'
        . '<body style="margin:0;padding:0;background:#f3f4f6;font-family:' . $font . ';">'
        . '<table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:24px 12px;"><tr><td align="center">'
        . '<table width="100%" style="max-width:560px;background:#fff;border-radius:12px;overflow:hidden;border:1px solid #e6e2da;border-top:4px solid ' . $accent . ';">'
        . '<tr><td style="background:#ffffff;padding:22px 22px 16px;text-align:center;color:#212529;border-bottom:1px solid #e6e2da;">'
        . '<div style="font-size:16px;font-weight:700;color:' . $accent . ';">' . $org . '</div>'
        . ($orgEn ? '<div style="font-size:11px;color:#6b7280;direction:ltr;margin-top:2px;">' . $orgEn . '</div>' : '')
        . '<div style="font-size:17px;font-weight:700;color:#212529;margin-top:8px;">' . e(__('app.name')) . '</div>'
        . '</td></tr>'
        . '<tr><td style="padding:26px 24px;font-family:' . dms_mail_font_family() . ';color:#333;line-height:1.7;text-align:right;">'
        . '<p style="margin:0 0 14px;font-size:16px;font-weight:700;">مرحباً ' . $name . '،</p>'
        . '<p style="margin:0 0 18px;font-size:14px;">وردنا طلب لإعادة تعيين كلمة المرور الخاصة بك في ' . e(__('app.name')) . '.</p>'
        . '<p style="text-align:center;margin:0 0 20px;">'
        . '<a href="' . $safeLink . '" style="display:inline-block;padding:12px 28px;background:' . $accent . ';color:#ffffff;text-decoration:none;font-weight:700;border-radius:8px;">إعادة تعيين كلمة المرور</a>'
        . '</p>'
        . '<p style="margin:0 0 10px;font-size:13px;color:#6a7077;">الرابط صالح لمدة ساعة واحدة. إذا لم تطلب ذلك، تجاهل هذه الرسالة.</p>'
        . '<p style="margin:0;font-size:12px;color:#6a7077;">إذا لم يعمل الزر، انسخ الرابط:</p>'
        . '<p style="margin:6px 0 0;font-size:12px;word-break:break-all;direction:ltr;text-align:left;"><a href="' . $safeLink . '" style="color:' . $accent . ';">' . $safeLink . '</a></p>'
        . '</td></tr></table></td></tr></table></body></html>';

    $text = "مرحباً " . (user_display_name($user) ?: $user['email']) . "،\n\n"
        . 'وردنا طلب لإعادة تعيين كلمة المرور في ' . __('app.name') . ".\n\n"
        . "الرابط (صالح ساعة واحدة):\n{$resetLink}\n\n"
        . "إذا لم تطلب ذلك، تجاهل الرسالة.";

    return ['subject' => $subject, 'html' => $html, 'text' => $text];
}

function dms_password_reset_send(array $user, string $token, ?string &$error = null): bool
{
    $link = dms_password_reset_link($token);
    $mail = dms_password_reset_build_email($user, $link);
    return dms_send_mail((string) $user['email'], $mail['subject'], $mail['html'], $mail['text'], $error);
}
