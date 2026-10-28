<?php
/**
 * إرسال البريد عبر SMTP — يقرأ من جدول email_settings
 */
require_once __DIR__ . '/email_settings.php';

function dms_mail_settings_map(array $row, bool $forceActive = false): array
{
    $fromName = trim((string) ($row['from_name'] ?? $row['mail_from_name'] ?? ''));
    $active = $forceActive ? true : !empty($row['is_active'] ?? $row['smtp_active'] ?? false);
    return [
        'smtp_host' => trim((string) ($row['smtp_host'] ?? '')),
        'smtp_port' => (int) ($row['smtp_port'] ?? 0),
        'smtp_encryption' => (string) ($row['smtp_encryption'] ?? ''),
        'smtp_username' => trim((string) ($row['smtp_username'] ?? '')),
        'smtp_password' => (string) ($row['smtp_password'] ?? ''),
        'mail_from_email' => trim((string) ($row['from_email'] ?? $row['mail_from_email'] ?? '')),
        'mail_from_name' => $fromName !== '' ? $fromName : org_name(),
        'smtp_active' => $active,
        'timeout_seconds' => (int) ($row['timeout_seconds'] ?? 15),
        'scope' => (string) ($row['resolved_from'] ?? $row['scope'] ?? 'system'),
        'department_id' => (int) ($row['department_id'] ?? 0),
        'fallback' => !empty($row['fallback']),
    ];
}

function dms_mail_settings(?int $departmentId = null): array
{
    return dms_mail_settings_map(dms_resolve_email_settings($departmentId));
}

function dms_mail_ready(?int $departmentId = null): bool
{
    $cfg = dms_mail_settings($departmentId);
    return !empty($cfg['smtp_active'])
        && $cfg['smtp_host'] !== ''
        && $cfg['smtp_username'] !== ''
        && $cfg['mail_from_email'] !== ''
        && $cfg['smtp_password'] !== '';
}

/** خط البريد: أسماء خطوط النظام المحلي (العميل يستخدم البديل إن لم تُضمَّن). */
function dms_mail_font_family(): string
{
    return "'GretaArabic', 'Greta Arabic', Greta, Arial, sans-serif";
}

/**
 * تحميل خط Greta (الخط الأساسي للنظام) داخل البريد من ملفات النظام.
 * Gmail وOutlook لسطح المكتب لا يدعمان خطوط الويب فيعرضان Arial؛
 * و@media screen يمنع Outlook من الرجوع إلى Times New Roman.
 */
function dms_mail_font_face_css(): string
{
    require_once __DIR__ . '/user_invite.php';
    $base = dms_mail_public_base_url() . '/assets/fonts/';
    $faces = '';
    foreach (['Regular' => 400, 'Bold' => 700] as $file => $weight) {
        $faces .= "@font-face{font-family:'GretaArabic';src:url('" . $base . 'GretaArabic-' . $file . ".otf') format('opentype');font-weight:" . $weight . ";font-style:normal;}";
    }

    return '<style type="text/css">@media screen{' . $faces . '}</style>'
        . '<!--[if mso]><style type="text/css">body,table,td,div,p,span,a,strong{font-family:Arial,sans-serif !important;}</style><![endif]-->';
}

/** مسار ملف شعار الجهة على القرص (للتضمين في البريد). */
function dms_org_logo_disk_path(): string
{
    if (!function_exists('org_logo_path')) {
        return '';
    }
    $rel = trim(str_replace('\\', '/', (string) org_logo_path()));
    if ($rel === '') {
        return '';
    }
    $disk = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($rel, '/'));
    return is_file($disk) ? $disk : '';
}

/**
 * غلاف HTML موحّد لرسائل النظام (نفس ثيم دعوة المستخدم).
 *
 * @return array{html:string,embeds:list<array{path:string,cid:string,name:string}>}
 */
function dms_mail_branded_shell(string $bodyInnerHtml, string $footerNote = ''): array
{
    $org = e(org_name());
    $orgEn = e(org_name_en());
    $appName = e(__('app.name'));
    $font = dms_mail_font_family();
    $accent = '#3B4F6E';
    $bg = '#f8f6f2';
    $border = '#e6e2da';
    $muted = '#6b7280';

    $embeds = [];
    $logoHtml = '';
    $logoDisk = dms_org_logo_disk_path();
    if ($logoDisk !== '') {
        $cid = 'dms_org_logo';
        $embeds[] = [
            'path' => $logoDisk,
            'cid' => $cid,
            'name' => basename($logoDisk),
        ];
        $logoHtml = '<div style="margin:0 auto 12px;">'
            . '<img src="cid:' . $cid . '" alt="' . $org . '" width="160" style="width:160px;max-width:160px;height:auto;max-height:80px;object-fit:contain;display:inline-block;border:0;outline:none;">'
            . '</div>';
    }

    if ($footerNote === '') {
        $footerNote = __('notify.email_footer');
    }

    $html = '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
        . dms_mail_font_face_css()
        . '</head>'
        . '<body style="margin:0;padding:0;background:' . $bg . ';font-family:' . $font . ';">'
        . '<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:' . $bg . ';padding:28px 12px;font-family:' . $font . ';">'
        . '<tr><td align="center">'
        . '<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="max-width:560px;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid ' . $border . ';border-top:4px solid ' . $accent . ';font-family:' . $font . ';">'
        . '<tr><td style="background:#ffffff;padding:26px 24px 18px;text-align:center;color:#212529;border-bottom:1px solid ' . $border . ';font-family:' . $font . ';">'
        . ($logoHtml !== ''
            ? $logoHtml
            : '<div style="font-family:' . $font . ';font-size:16px;font-weight:700;color:' . $accent . ';margin:0 0 8px;">' . $org
                . ($orgEn !== '' ? '<div style="font-size:11px;font-weight:400;color:' . $muted . ';direction:ltr;margin-top:2px;">' . $orgEn . '</div>' : '')
                . '</div>')
        . '<div style="font-family:' . $font . ';font-size:18px;font-weight:700;line-height:1.4;color:#212529;">' . $appName . '</div>'
        . '</td></tr>'
        . '<tr><td style="padding:28px 24px;font-family:' . $font . ';color:' . $accent . ';line-height:1.75;text-align:right;">'
        . $bodyInnerHtml
        . '<p style="margin:0;padding-top:14px;border-top:1px solid ' . $border . ';font-size:12px;color:' . $muted . ';font-family:' . $font . ';">'
        . e($footerNote) . '</p>'
        . '</td></tr></table>'
        . '</td></tr></table></body></html>';

    return ['html' => $html, 'embeds' => $embeds];
}

function dms_phpmailer_autoload(): ?string
{
    $path = __DIR__ . '/../lib/PHPMailer/autoload.php';
    return is_file($path) ? $path : null;
}

/**
 * تهيئة PHPMailer وفق إعدادات DMS (متوافق مع Office 365)
 *
 * @param \PHPMailer\PHPMailer\PHPMailer $mail
 */
function dms_configure_phpmailer($mail, array $cfg, bool $debug = false): void
{
    $mail->isSMTP();
    $mail->Host = $cfg['smtp_host'];
    $mail->SMTPAuth = true;
    $mail->AuthType = 'LOGIN';
    $mail->Username = $cfg['smtp_username'];
    $mail->Password = $cfg['smtp_password'];
    $mail->Port = (int) $cfg['smtp_port'];
    $mail->Timeout = max(5, (int) $cfg['timeout_seconds']);
    $mail->CharSet = 'UTF-8';
    $mail->SMTPKeepAlive = false;

    // مهم لـ XAMPP على Windows مع شهادات TLS
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true,
        ],
    ];

    $encryption = $cfg['smtp_encryption'];
    if ($encryption === 'ssl') {
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        $mail->SMTPAutoTLS = true;
    } elseif ($encryption === 'tls') {
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->SMTPAutoTLS = true;
    } else {
        $mail->SMTPSecure = '';
        $mail->SMTPAutoTLS = false;
    }

    if ($debug) {
        $mail->SMTPDebug = 2;
        $mail->Debugoutput = static function ($str, $level) {
            $GLOBALS['_dms_smtp_debug'] = ($GLOBALS['_dms_smtp_debug'] ?? '') . trim((string) $str) . "\n";
        };
    } else {
        $mail->SMTPDebug = 0;
    }

    $mail->Encoding = 'base64';
    $mail->WordWrap = 78;
    $fromEmail = trim((string) ($cfg['mail_from_email'] ?? ''));
    $fromName = trim((string) ($cfg['mail_from_name'] ?? ''));
    if ($fromName === '' && function_exists('org_name')) {
        $fromName = trim((string) org_name());
    }
    // Envelope sender = From (مهم لـ Office 365 / تقليل الرفض عند Gmail)
    // auto=false حتى لا يُستبدل Sender؛ ثم نفرض FromName صراحةً من إعدادات DMS
    $mail->setFrom($fromEmail, $fromName, false);
    $mail->Sender = $fromEmail;
    $mail->FromName = $fromName;
    // Reply-To بنفس البريد المرسل واسم العرض من الإعدادات
    $mail->clearReplyTos();
    $mail->addReplyTo($fromEmail, $fromName);
    $fromHost = strtolower((string) substr(strrchr($fromEmail, '@') ?: '', 1));
    if ($fromHost !== '') {
        $mail->Hostname = $fromHost;
        $mail->MessageID = sprintf('<%s@%s>', bin2hex(random_bytes(12)), $fromHost);
    }
}

function dms_smtp_auth_hint(string $error): string
{
    $e = mb_strtolower($error);
    if (str_contains($e, '5.7.139') || str_contains($e, 'credentials were incorrect')) {
        return ' Microsoft رفض بيانات الدخول. هذا ليس عطلاً في النظام: إما كلمة المرور ليست «كلمة مرور تطبيق»، أو SMTP AUTH غير مفعّل لهذا الصندوق. من مركز إدارة Microsoft 365: المستخدم ← البريد ← Manage email apps ← فعّل Authenticated SMTP. ثم من حساب Microsoft أنشئ App Password واحفظها هنا (ليست كلمة دخول الويب). اسم المستخدم والبريد المرسل يجب أن يكونا نفس الصندوق.';
    }
    if (str_contains($e, 'smtpclientauthentication is disabled') || str_contains($e, '5.7.57')) {
        return ' الإرسال SMTP معطّل لهذا الصندوق أو للمستأجر. فعّل Authenticated SMTP للمستخدم في مركز إدارة Microsoft 365.';
    }
    if (str_contains($e, 'authenticate') || str_contains($e, '535') || str_contains($e, '5.7.3') || str_contains($e, 'password')) {
        return ' فشل المصادقة غالباً بسبب: كلمة مرور خاطئة، أو حساب Office 365 يتطلب «كلمة مرور تطبيق» (App Password) بعد تفعيل المصادقة الثنائية، أو أن المصادقة الأساسية Basic Auth معطّلة للحساب. تأكد أن اسم المستخدم والبريد المرسل نفس صندوق البريد.';
    }
    if (str_contains($e, 'connect') || str_contains($e, 'timed out')) {
        return ' تعذر الاتصال بالخادم. تحقق من المنفذ (587 مع TLS أو 465 مع SSL) وجدار الحماية.';
    }
    return '';
}

function dms_send_mail(string $toEmail, string $subject, string $htmlBody, string $textBody = '', ?string &$error = null, bool $debug = false, ?int $departmentId = null, bool $testStored = false, ?string $passwordOverride = null, array $embeds = [], array $attachments = []): bool
{
    $error = null;
    $GLOBALS['_dms_smtp_debug'] = '';
    $GLOBALS['_dms_smtp_from_mismatch'] = false;
    if ($testStored && (int) $departmentId > 0) {
        $row = dms_get_department_email_settings((int) $departmentId);
        if (empty($row['exists']) && ($passwordOverride === null || $passwordOverride === '')) {
            $error = 'احفظ إعدادات بريد القسم أولاً ثم أرسل اختباراً.';
            return false;
        }
        $cfg = dms_mail_settings_map($row, true);
    } else {
        $cfg = dms_mail_settings($departmentId);
    }
    if ($passwordOverride !== null && $passwordOverride !== '') {
        $cfg['smtp_password'] = $passwordOverride;
        $cfg['smtp_active'] = true;
    }

    if (!$cfg['smtp_active']) {
        $error = 'إعدادات البريد غير مفعّلة. راجع: الإدارة → إعدادات البريد.';
        return false;
    }
    if ($cfg['smtp_host'] === '' || $cfg['mail_from_email'] === '' || $cfg['smtp_username'] === '') {
        $error = 'إعدادات SMTP غير مكتملة (الخادم / المستخدم / البريد المرسل).';
        return false;
    }
    if ($cfg['smtp_password'] === '') {
        $error = 'كلمة مرور SMTP فارغة. أعد إدخالها واحفظ الإعدادات.';
        return false;
    }
    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        $error = 'بريد المستلم غير صالح.';
        return false;
    }
    // Office 365: يفضّل تطابق المستخدم مع المرسل
    if (stripos($cfg['smtp_host'], 'office365') !== false || stripos($cfg['smtp_host'], 'outlook') !== false) {
        if (strcasecmp($cfg['smtp_username'], $cfg['mail_from_email']) !== 0) {
            // لا نمنع الإرسال لكن نضيف تحذيراً في الخطأ عند الفشل
            $GLOBALS['_dms_smtp_from_mismatch'] = true;
        }
    }

    if ($textBody === '') {
        $textBody = trim(html_entity_decode(strip_tags($htmlBody), ENT_QUOTES, 'UTF-8'));
    }

    $autoload = dms_phpmailer_autoload();
    if (!$autoload) {
        $error = 'مكتبة PHPMailer غير موجودة في النظام (lib/PHPMailer).';
        return false;
    }

    try {
        require_once $autoload;
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        dms_configure_phpmailer($mail, $cfg, $debug);
        $mail->isHTML(true);
        $mail->addAddress($toEmail);
        $mail->Subject = $subject;
        foreach ($embeds as $embed) {
            if (!is_array($embed)) {
                continue;
            }
            $path = (string) ($embed['path'] ?? '');
            $cid = trim((string) ($embed['cid'] ?? ''));
            if ($cid === '' || $path === '' || !is_file($path)) {
                continue;
            }
            $name = (string) ($embed['name'] ?? basename($path));
            $mail->addEmbeddedImage($path, $cid, $name);
        }
        foreach ($attachments as $attachment) {
            if (!is_array($attachment)) {
                continue;
            }
            $path = (string) ($attachment['path'] ?? '');
            if ($path === '' || !is_file($path)) {
                continue;
            }
            $name = trim((string) ($attachment['name'] ?? basename($path)));
            if ($name === '') {
                $name = basename($path);
            }
            $mail->addAttachment($path, $name);
        }
        $mail->Body = $htmlBody;
        $mail->AltBody = $textBody;
        $mail->send();
        return true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        if (!empty($mail) && !empty($mail->ErrorInfo)) {
            $error = trim($mail->ErrorInfo);
        }
        $error .= dms_smtp_auth_hint($error);
        if (!empty($GLOBALS['_dms_smtp_from_mismatch'])) {
            $error .= ' ملاحظة: اسم مستخدم SMTP يختلف عن البريد المرسل — لـ Office 365 يُفضّل أن يكونا متطابقين.';
        }
        if ($debug && !empty($GLOBALS['_dms_smtp_debug'])) {
            $debugTail = trim($GLOBALS['_dms_smtp_debug']);
            // اقتصاص السطر الأخير المفيد فقط
            $lines = array_filter(array_map('trim', explode("\n", $debugTail)));
            $useful = [];
            foreach ($lines as $line) {
                if (preg_match('/535|5\.7\.|AUTH|Username|Password|authenticated|denied|login/i', $line)) {
                    $useful[] = $line;
                }
            }
            if ($useful) {
                $error .= ' | تفاصيل: ' . implode(' ← ', array_slice($useful, -4));
            }
        }
        error_log('DMS mailer error: ' . $error);
        return false;
    }
}

/**
 * استيراد إعدادات SMTP من قاعدة نظام الدعوات (إن وُجدت)
 */
function dms_import_invitations_smtp(): bool
{
    try {
        $inv = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=invitations;charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $row = $inv->query('SELECT * FROM tblemailsettings WHERE id = 1 LIMIT 1')->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return false;
        }
        dms_save_email_settings([
            'smtp_host' => $row['smtp_host'] ?? 'smtp.office365.com',
            'smtp_port' => $row['smtp_port'] ?? 587,
            'smtp_encryption' => $row['smtp_encryption'] ?? 'tls',
            'smtp_username' => $row['smtp_username'] ?? '',
            'smtp_password' => $row['smtp_password'] ?? '',
            'from_email' => $row['from_email'] ?? '',
            'from_name' => $row['from_name'] ?? org_name(),
            'timeout_seconds' => $row['timeout_seconds'] ?? 15,
            'is_active' => !empty($row['is_active']) ? 1 : 0,
        ], 'import-invitations');
        return true;
    } catch (Throwable $e) {
        error_log('DMS import INV SMTP: ' . $e->getMessage());
        return false;
    }
}
