<?php
/**
 * إرسال الصادر عبر البريد وواتساب — مستقل عن أي نظام آخر
 */
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/whatsapp_sender.php';

function ensure_corr_deliveries_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        db()->exec(
            "CREATE TABLE IF NOT EXISTS `tblcorr_deliveries` (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                correspondence_id INT UNSIGNED NOT NULL,
                channel ENUM('email','whatsapp','delivery') NOT NULL,
                recipient VARCHAR(255) NOT NULL DEFAULT '',
                status ENUM('sent','failed','skipped') NOT NULL,
                error_message TEXT DEFAULT NULL,
                provider VARCHAR(50) DEFAULT NULL,
                sent_by INT UNSIGNED DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_deliv_corr (correspondence_id),
                INDEX idx_deliv_channel (channel, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    } catch (Throwable $e) {
        error_log('DMS deliveries schema: ' . $e->getMessage());
    }
    try {
        $col = dms_db_column_meta('tblcorr_deliveries', 'channel');
        $type = (string) ($col['Type'] ?? '');
        if ($type !== '' && stripos($type, 'delivery') === false) {
            if (stripos($type, 'paper') !== false) {
                db()->exec(
                    "ALTER TABLE `tblcorr_deliveries`
                     MODIFY COLUMN `channel` ENUM('email','whatsapp','paper','delivery') NOT NULL"
                );
                db()->exec("UPDATE `tblcorr_deliveries` SET channel = 'delivery' WHERE channel = 'paper'");
            }
            db()->exec(
                "ALTER TABLE `tblcorr_deliveries`
                 MODIFY COLUMN `channel` ENUM('email','whatsapp','delivery') NOT NULL"
            );
        }
    } catch (Throwable $e) {
        error_log('DMS deliveries channel: ' . $e->getMessage());
    }
}

function dms_corr_verify_url(array $corr): string
{
    $public = dms_corr_public_verify_url($corr);
    if ($public !== '') {
        return $public;
    }
    // شبكة داخلية / إعداد qr_scan_base_url — حتى يعمل البريد والاختبار المحلي
    return function_exists('dms_corr_qr_content') ? dms_corr_qr_content($corr) : '';
}

function dms_corr_recipient_name(array $corr): string
{
    $party = correspondence_archived_party($corr);
    if ($party['person'] !== '') {
        return $party['person'];
    }
    return entity_recipient_name($corr);
}

function dms_corr_honored_name(array $corr): string
{
    return format_entity_honored_name($corr);
}

function dms_corr_email_address(array $corr): string
{
    if (function_exists('entity_email_address')) {
        $fromEntity = entity_email_address($corr);
        if ($fromEntity !== '') {
            return $fromEntity;
        }
    }
    $email = trim((string) ($corr['entity_email'] ?? $corr['email'] ?? ''));
    $email = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}\x{00A0}]/u', '', $email) ?? $email;
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
}

function dms_corr_mobile_number(array $corr): string
{
    $mobile = trim((string) ($corr['entity_mobile'] ?? $corr['mobile'] ?? ''));
    if ($mobile === '') {
        $mobile = trim((string) ($corr['entity_phone'] ?? $corr['phone'] ?? ''));
    }
    if (!function_exists('dms_whatsapp_normalize_msisdn')) {
        return $mobile;
    }
    $digits = dms_whatsapp_normalize_msisdn($mobile);
    return ($digits !== '' && strlen($digits) >= 8) ? $digits : '';
}

function dms_corr_log_delivery(
    int $corrId,
    string $channel,
    string $recipient,
    string $status,
    string $error,
    string $provider,
    ?array $user
): void {
    ensure_corr_deliveries_schema();
    try {
        db()->prepare(
            'INSERT INTO `tblcorr_deliveries`
             (correspondence_id, channel, recipient, status, error_message, provider, sent_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $corrId,
            $channel,
            $recipient,
            $status,
            $error !== '' ? $error : null,
            $provider !== '' ? $provider : null,
            isset($user['id']) ? (int) $user['id'] : null,
        ]);
    } catch (Throwable $e) {
        error_log('DMS log delivery: ' . $e->getMessage());
    }
}

function dms_corr_last_deliveries(int $corrId): array
{
    return dms_corr_last_deliveries_map([$corrId])[$corrId] ?? ['email' => null, 'whatsapp' => null, 'delivery' => null];
}

/**
 * @param int[] $ids
 * @return array<int, array{email:?array,whatsapp:?array}>
 */
function dms_corr_last_deliveries_map(array $ids): array
{
    ensure_corr_deliveries_schema();
    $out = [];
    $clean = [];
    foreach ($ids as $id) {
        $id = (int) $id;
        if ($id > 0) {
            $clean[$id] = $id;
            $out[$id] = ['email' => null, 'whatsapp' => null, 'delivery' => null];
        }
    }
    if (!$clean) {
        return $out;
    }
    $placeholders = implode(',', array_fill(0, count($clean), '?'));
    try {
        $stmt = db()->prepare(
            "SELECT d.*
             FROM `tblcorr_deliveries` d
             INNER JOIN (
                SELECT correspondence_id, channel, MAX(id) AS max_id
                FROM `tblcorr_deliveries`
                WHERE correspondence_id IN ($placeholders)
                GROUP BY correspondence_id, channel
             ) x ON x.max_id = d.id"
        );
        $stmt->execute(array_values($clean));
        foreach ($stmt->fetchAll() as $row) {
            $cid = (int) $row['correspondence_id'];
            $ch = (string) $row['channel'];
            if (isset($out[$cid]) && array_key_exists($ch, $out[$cid])) {
                $out[$cid][$ch] = $row;
            }
        }
    } catch (Throwable $e) {
        error_log('DMS last deliveries: ' . $e->getMessage());
    }
    return $out;
}

function dms_delivery_status_label(?array $row): string
{
    if (!$row) {
        return __('send.never');
    }
    $status = (string) ($row['status'] ?? '');
    $when = format_datetime($row['created_at'] ?? null);
    return match ($status) {
        'sent' => __('send.last_sent') . ' — ' . $when,
        'failed' => __('send.last_failed') . ' — ' . $when,
        'skipped' => __('send.last_skipped') . ' — ' . $when,
        default => $when,
    };
}

/** أسطر «موجهة إلى» كما تُطبع على الخطاب. */
function dms_corr_letter_addressee_lines(array $corr): array
{
    if (!function_exists('print_direction_parts') || !function_exists('print_direction_part_has_content')) {
        return [];
    }
    $to = print_direction_parts($corr)['to'] ?? [];
    if (!is_array($to)) {
        return [];
    }
    if (function_exists('letter_apply_addressee_flags') && function_exists('letter_display_flags')) {
        $to = letter_apply_addressee_flags($to, letter_display_flags($corr));
    }
    if (!print_direction_part_has_content($to)) {
        return [];
    }
    $prefix = trim((string) ($to['prefix'] ?? ''));
    $person = trim((string) ($to['person'] ?? ''));
    $honorific = trim((string) ($to['honorific'] ?? ''));
    $job = trim((string) ($to['job'] ?? ''));
    $org = trim((string) ($to['org'] ?? ''));
    if ($org !== '' && $person !== '' && $org === $person) {
        $org = '';
    }
    $name = trim($prefix . ($prefix !== '' && $person !== '' ? ' ' : '') . $person);
    if ($honorific !== '') {
        $name = trim($name . ' ' . $honorific);
    }
    $lines = [];
    if ($name !== '') {
        $lines[] = $name;
    }
    if ($job !== '') {
        $lines[] = $job;
    }
    if ($org !== '') {
        $lines[] = $org;
    }
    foreach ($to['titles'] ?? [] as $title) {
        $title = trim((string) $title);
        if ($title !== '') {
            $lines[] = $title;
        }
    }

    return $lines;
}

/** رقم صادر ظاهر في البريد — بلا أرقام المسودة المؤقتة. */
function dms_corr_public_ref(array $corr): string
{
    $raw = trim((string) ($corr['ref_number'] ?? ''));
    if ($raw === '' || str_starts_with($raw, 'OD-') || str_starts_with($raw, 'ODTMP-')) {
        return '';
    }

    return function_exists('format_ref_number') ? format_ref_number($raw) : $raw;
}

function dms_corr_letter_addressee_html(array $corr, string $font): string
{
    $lines = dms_corr_letter_addressee_lines($corr);
    if ($lines === []) {
        return '';
    }
    $html = '';
    foreach ($lines as $i => $line) {
        $weight = $i === 0 ? '700' : '400';
        $html .= '<div style="margin:0 0 2px;font-weight:' . $weight . ';font-family:' . $font . ';color:#000">'
            . e($line) . '</div>';
    }

    return $html;
}

function dms_corr_email_bodies(array $corr): array
{
    $lang = function_exists('correspondence_letter_channel_lang') ? correspondence_letter_channel_lang($corr) : 'ar';
    $t = static fn(string $key): string => function_exists('__lang') ? __lang($key, $lang) : __($key);
    $org = $lang === 'en' ? org_name_en() : org_name();
    if ($org === '') {
        $org = org_name();
    }
    $name = dms_corr_honored_name($corr);
    $number = format_ref_number($corr['ref_number'] ?? '');
    $subject = (string) ($corr['subject'] ?? '');
    $confRedact = function_exists('dms_corr_is_confidential_row') && dms_corr_is_confidential_row($corr)
        && function_exists('dms_conf_external_notify_redacted') && dms_conf_external_notify_redacted();
    if ($confRedact) {
        $safe = function_exists('dms_conf_safe_notify_bits')
            ? dms_conf_safe_notify_bits($corr, $subject)
            : ['subject' => $number];
        $subject = (string) ($safe['subject'] ?? $number);
    }
    $link = dms_corr_verify_url($corr);
    $rsvpOn = function_exists('correspondence_rsvp_required') && correspondence_rsvp_required($corr);
    if ($link === '' && $rsvpOn) {
        $link = dms_corr_qr_content($corr, false, true);
    }
    $letter = $confRedact
        ? ''
        : (function_exists('correspondence_letter_text') ? correspondence_letter_text($corr, $lang) : '');
    $publicRef = dms_corr_public_ref($corr);
    $mailSubject = $subject !== '' ? $subject : ($org . ' — ' . $number);
    if ($letter !== '' && $publicRef !== '' && !str_contains($mailSubject, $publicRef)) {
        $mailSubject = ($subject !== '' ? $subject . ' — ' : '') . $publicRef;
    }
    $greet = $name !== '' ? $name : $t('send.recipient');
    $dir = $lang === 'en' ? 'ltr' : 'rtl';
    $align = $lang === 'en' ? 'left' : 'right';
    $font = function_exists('letter_email_forced_family')
        ? letter_email_forced_family()
        : 'Arial, Helvetica, sans-serif';

    $needsRsvp = $link !== '' && $rsvpOn;
    if ($needsRsvp && function_exists('dms_corr_rsvp_url')) {
        $rsvpLink = dms_corr_rsvp_url($corr, true);
        if ($rsvpLink !== '') {
            $link = $rsvpLink;
        }
    }

    $fontPack = function_exists('letter_email_font_pack') && $letter !== ''
        ? letter_email_font_pack($letter)
        : ['css' => '', 'embeds' => [], 'stack' => $font, 'mso' => 'Arial'];
    $font = (string) ($fontPack['stack'] ?? $font);
    $fontMso = (string) ($fontPack['mso'] ?? 'Arial');
    $corr['_email_font'] = $font;
    $corr['_email_mso'] = $fontMso;

    $embeds = is_array($fontPack['embeds'] ?? null) ? $fontPack['embeds'] : [];
    $cleanup = [];
    if (function_exists('letter_signature_context')) {
        $sigCtx = letter_signature_context($corr);
        $sigDisk = (string) ($sigCtx['disk'] ?? '');
        if ($sigDisk !== '') {
            $cid = 'corr-dept-signature';
            $prepared = function_exists('letter_email_prepared_image')
                ? letter_email_prepared_image($sigDisk, 140, 48)
                : ['path' => $sigDisk, 'w' => 140, 'h' => 48, 'cleanup' => ''];
            $embeds[] = [
                'path' => (string) $prepared['path'],
                'cid' => $cid,
                'name' => 'signature.png',
            ];
            if ((string) ($prepared['cleanup'] ?? '') !== '') {
                $cleanup[] = (string) $prepared['cleanup'];
            }
            $corr['signature_cid'] = $cid;
            $corr['_sig_img_w'] = (int) $prepared['w'];
            $corr['_sig_img_h'] = (int) $prepared['h'];
        }
    }
    if ($letter !== '' && str_contains($letter, '{logo}') && function_exists('dms_org_logo_disk_path')) {
        $logoDisk = dms_org_logo_disk_path();
        if ($logoDisk !== '') {
            $cid = 'corr-letter-logo';
            $prepared = function_exists('letter_email_prepared_image')
                ? letter_email_prepared_image($logoDisk, 160, 56)
                : ['path' => $logoDisk, 'w' => 160, 'h' => 56, 'cleanup' => ''];
            $embeds[] = [
                'path' => (string) $prepared['path'],
                'cid' => $cid,
                'name' => 'logo.png',
            ];
            if ((string) ($prepared['cleanup'] ?? '') !== '') {
                $cleanup[] = (string) $prepared['cleanup'];
            }
            $corr['logo_cid'] = $cid;
            $corr['_logo_img_w'] = (int) $prepared['w'];
            $corr['_logo_img_h'] = (int) $prepared['h'];
        }
    }
    $html = '<div style="font-family:' . $font . ';'
        . (function_exists('letter_email_mso_style') ? letter_email_mso_style($fontMso) : '')
        . 'font-size:14pt;direction:' . $dir . ';text-align:' . $align . ';color:#000;line-height:1.5;max-width:640px">';
    $emailLinkStyle = 'font-family:' . $font . ';'
        . (function_exists('letter_email_mso_style') ? letter_email_mso_style($fontMso) : '')
        . 'font-size:14pt;font-weight:normal;line-height:1.5;color:#000 !important;text-decoration:underline;border:0;outline:0;';
    if ($letter !== '') {
        if ($number !== '' && !str_starts_with($number, 'ODTMP-')) {
            $startAlign = $lang === 'en' ? 'left' : 'right';
            $html .= '<div dir="' . $dir . '" style="margin:0 0 16px;font-family:' . $font . ';'
                . (function_exists('letter_email_mso_style') ? letter_email_mso_style($fontMso) : '')
                . 'font-size:14pt;font-weight:normal;line-height:1.65;color:#000;text-align:' . $startAlign . '">'
                . e($t('corr.ref_label_outgoing')) . ': <span dir="ltr" style="font-family:' . $font . ';'
                . (function_exists('letter_email_mso_style') ? letter_email_mso_style($fontMso) : '')
                . 'font-size:14pt;font-weight:normal;color:#000">' . e($number) . '</span></div>';
        }
        $letterPrint = function_exists('letter_strip_link_qr_token') ? letter_strip_link_qr_token($letter) : $letter;
        $html .= '<div style="margin:0 0 20px;line-height:1.65;font-size:14pt;font-family:' . $font . ';color:#000">'
            . render_letter_body_html($letterPrint, true, $corr)
            . '</div>';
        // أسفل النص: مكان الاجتماع كنص قابل للنقر (بدون QR في البريد)
        if (function_exists('letter_link_qr_markup')) {
            $corr['_email_font'] = $font;
            $corr['_email_mso'] = $fontMso;
            $corr['_email_dir'] = $lang === 'en' ? 'ltr' : 'rtl';
            $linkBlock = letter_link_qr_markup(true, $corr);
            if ($linkBlock !== '') {
                $html .= $linkBlock;
            }
        }
    } else {
        $html .= '<div style="margin:0 0 18px;font-size:14pt;color:#000;font-weight:normal;font-family:' . $font . ';'
            . (function_exists('letter_email_mso_style') ? letter_email_mso_style($fontMso) : '')
            . 'line-height:1.65">'
            . e($t('corr.ref_label_outgoing'))
            . ': <span dir="ltr" style="font-family:' . $font . ';font-size:14pt;font-weight:normal;color:#000">'
            . e($number) . '</span>'
            . '</div>'
            . '<p style="margin:0 0 10px;font-family:' . $font . ';color:#000">' . e($t('send.email_peace')) . '</p>'
            . '<p style="margin:0 0 18px;font-weight:700;font-size:16px;font-family:' . $font . ';color:#000">' . e($greet) . '</p>'
            . '<p style="margin:0 0 12px;font-family:' . $font . ';color:#000">' . e($t('send.email_intro')) . '</p>'
            . '<p style="margin:0 0 16px;font-weight:700;font-family:' . $font . ';color:#000">' . e($subject) . '</p>';
    }
    if ($needsRsvp && $link !== '') {
        $askKey = function_exists('correspondence_rsvp_ask_key')
            ? correspondence_rsvp_ask_key($corr, 'email')
            : 'rsvp.email_ask';
        $askText = $t($askKey);
        if (function_exists('letter_end_note_text')) {
            $askText = letter_end_note_text($askText);
        } elseif ($askText !== '' && !str_starts_with($askText, '*')) {
            $askText = '* ' . $askText;
        }
        if (function_exists('letter_email_with_click_here')) {
            $askText = letter_email_with_click_here($askText);
        }
        $html .= '<div style="margin:12px 0 8px;font-family:' . $font . ';'
            . (function_exists('letter_email_mso_style') ? letter_email_mso_style($fontMso) : '')
            . 'font-size:14pt;line-height:1.5;color:#000">'
            . '<a href="' . e($link) . '" target="_blank" rel="noopener noreferrer" style="' . $emailLinkStyle . '">'
            . e($askText) . '</a></div>';
    } elseif ($link !== '') {
        $verifyText = $t('send.email_verify');
        if (function_exists('letter_email_with_click_here')) {
            $verifyText = letter_email_with_click_here($verifyText);
        }
        $html .= '<div style="margin:18px 0 8px;font-family:' . $font . ';'
            . (function_exists('letter_email_mso_style') ? letter_email_mso_style($fontMso) : '')
            . 'font-size:14pt;line-height:1.5;color:#000">'
            . '<a href="' . e($link) . '" target="_blank" rel="noopener noreferrer" style="' . $emailLinkStyle . '">'
            . e($verifyText) . '</a></div>';
    }
    $html .= '</div>';

    if ($letter !== '') {
        $textParts = [];
        if ($number !== '' && !str_starts_with($number, 'ODTMP-')) {
            $textParts[] = $t('corr.ref_label_outgoing') . ': ' . $number;
            $textParts[] = '';
        }
        $letterPlainSrc = function_exists('letter_strip_link_qr_token') ? letter_strip_link_qr_token($letter) : $letter;
        $textParts[] = letter_body_plain($letterPlainSrc, $corr);
        $text = implode("\n", $textParts) . "\n\n";
    } else {
        $text = $t('corr.ref_label_outgoing') . ': ' . $number . "\n\n"
            . $t('send.email_peace') . "\n" . $greet . "\n\n" . $t('send.email_intro') . "\n" . $subject . "\n\n";
    }
    $textAsk = function_exists('correspondence_rsvp_ask_key')
        ? $t(correspondence_rsvp_ask_key($corr, 'email'))
        : $t('rsvp.email_ask');
    if (function_exists('letter_end_note_text')) {
        $textAsk = letter_end_note_text($textAsk);
    }
    if (function_exists('letter_email_with_click_here')) {
        $textAsk = letter_email_with_click_here($textAsk);
    }
    $linkLabel = function_exists('correspondence_letter_link_label') ? correspondence_letter_link_label($corr) : '';
    $linkUrlPlain = function_exists('correspondence_letter_link_url') ? correspondence_letter_link_url($corr) : '';
    if ($linkLabel !== '' && $linkUrlPlain !== '') {
        $plainLabel = function_exists('letter_end_note_text') ? letter_end_note_text($linkLabel) : $linkLabel;
        if (function_exists('letter_email_with_click_here')) {
            $plainLabel = letter_email_with_click_here($plainLabel);
        }
        $text .= $plainLabel . "\n" . $linkUrlPlain . "\n";
    }
    $verifyPlain = $t('send.email_verify');
    if (function_exists('letter_email_with_click_here')) {
        $verifyPlain = letter_email_with_click_here($verifyPlain);
    }
    $text .= ($needsRsvp ? $textAsk . "\n" . ($link !== '' ? $link . "\n" : '') : '')
        . ($link !== '' && !$needsRsvp ? $verifyPlain . "\n" . $link : '');
    $text = rtrim($text);

    return [
        'subject' => $mailSubject,
        'html' => $html,
        'text' => $text,
        'embeds' => $embeds,
        'cleanup' => $cleanup,
        'font_css' => (string) ($fontPack['css'] ?? ''),
        'font_stack' => $font,
        'font_mso' => $fontMso,
    ];
}

/**
 * معاينة البريد كما سيُرسل (بدون إرسال) — المستلم والموضوع والنص HTML.
 *
 * @return array{ok:bool,to?:string,recipient?:string,subject?:string,html?:string,text?:string,error?:string}
 */
/**
 * نص البريد كما سيصل، مع الصور مضمّنة للعرض في المتصفح.
 *
 * @return array{to:string,recipient:string,subject:string,html:string,text:string}
 */
function dms_corr_email_message_view(array $corr): array
{
    $bodies = dms_corr_email_bodies($corr);
    $html = (string) ($bodies['html'] ?? '');
    foreach ($bodies['embeds'] ?? [] as $emb) {
        if (!is_array($emb)) {
            continue;
        }
        $path = (string) ($emb['path'] ?? '');
        $cid = (string) ($emb['cid'] ?? '');
        if ($path === '' || $cid === '' || !is_file($path)) {
            continue;
        }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (in_array($ext, ['otf', 'ttf', 'woff', 'woff2'], true)) {
            continue;
        }
        $bin = @file_get_contents($path);
        if ($bin === false || $bin === '') {
            continue;
        }
        $mime = match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'image/png',
        };
        $html = str_replace(
            'cid:' . $cid,
            'data:' . $mime . ';base64,' . base64_encode($bin),
            $html
        );
    }
    foreach ($bodies['cleanup'] ?? [] as $path) {
        if (is_string($path) && $path !== '' && is_file($path)) {
            @unlink($path);
        }
    }

    return [
        'to' => dms_corr_email_address($corr),
        'recipient' => function_exists('dms_corr_honored_name') ? dms_corr_honored_name($corr) : '',
        'subject' => (string) ($bodies['subject'] ?? ''),
        'html' => $html,
        'text' => (string) ($bodies['text'] ?? ''),
    ];
}

function dms_corr_email_preview(array $corr): array
{
    $to = dms_corr_email_address($corr);
    if ($to === '') {
        return [
            'ok' => false,
            'error' => __('send.err_no_email'),
        ];
    }
    $view = dms_corr_email_message_view($corr);

    return ['ok' => true] + $view;
}

function dms_corr_whatsapp_text(array $corr): string
{
    $lang = function_exists('correspondence_letter_channel_lang') ? correspondence_letter_channel_lang($corr) : 'ar';
    $t = static fn(string $key): string => function_exists('__lang') ? __lang($key, $lang) : __($key);
    $name = dms_corr_honored_name($corr);
    $number = format_ref_number($corr['ref_number'] ?? '');
    $subject = (string) ($corr['subject'] ?? '');
    $date = format_date($corr['corr_date'] ?? null);
    $link = dms_corr_verify_url($corr);
    $rsvpOn = function_exists('correspondence_rsvp_required') && correspondence_rsvp_required($corr);
    if ($link === '' && $rsvpOn) {
        $link = dms_corr_qr_content($corr, false, true);
    }
    $needsRsvp = $link !== '' && $rsvpOn;
    if ($needsRsvp && function_exists('dms_corr_rsvp_url')) {
        $rsvpLink = dms_corr_rsvp_url($corr, true);
        if ($rsvpLink !== '') {
            $link = $rsvpLink;
        }
    }
    $letter = function_exists('correspondence_letter_text') ? correspondence_letter_text($corr, $lang) : '';
    $lines = [];
    if ($letter !== '') {
        $waRef = dms_corr_public_ref($corr);
        if ($waRef !== '') {
            $lines[] = $t('corr.number') . ': ' . $waRef;
        }
        if ($date !== '') {
            $lines[] = $t('corr.date') . ': ' . $date;
        }
        $lines[] = '';
        $lines[] = letter_body_plain($letter, $corr);
        $lines[] = '';
    } else {
        $lines[] = $t('corr.number') . ': ' . $number;
        $lines[] = $t('corr.date') . ': ' . $date;
        $lines[] = '';
        $lines[] = $t('send.email_peace');
        $lines[] = $name !== '' ? $name : $t('send.recipient');
        $lines[] = '';
        $lines[] = $t('send.wa_intro');
        $lines[] = $subject;
        $lines[] = '';
    }
    if ($link !== '' && !$needsRsvp) {
        $verifyWa = $t('send.email_verify');
        if (function_exists('letter_email_with_click_here')) {
            $verifyWa = letter_email_with_click_here($verifyWa);
        }
        $lines[] = $verifyWa . "\n" . $link;
    }
    if ($needsRsvp) {
        $waAskKey = function_exists('correspondence_rsvp_ask_key')
            ? correspondence_rsvp_ask_key($corr, 'whatsapp')
            : 'rsvp.email_ask';
        $waAsk = $t($waAskKey);
        if (function_exists('letter_end_note_text')) {
            $waAsk = letter_end_note_text($waAsk);
        }
        if ($waAskKey === 'rsvp.email_ask' && function_exists('letter_email_with_click_here')) {
            $waAsk = letter_email_with_click_here($waAsk);
        }
        $lines[] = $waAsk;
        if ($link !== '') {
            $lines[] = $link;
        }
    }
    return implode("\n", $lines);
}

function dms_corr_email_attachments(array $corr): array
{
    $corrId = (int) ($corr['id'] ?? 0);
    if ($corrId < 1 || !function_exists('get_documents')) {
        return [];
    }
    // لا تُرفق ملفات سرية عبر البريد/واتساب الخارجي
    if (function_exists('dms_corr_is_confidential_row') && dms_corr_is_confidential_row($corr)
        && function_exists('dms_conf_external_notify_redacted') && dms_conf_external_notify_redacted()
    ) {
        return [];
    }
    $closeKinds = function_exists('correspondence_close_doc_kind_values')
        ? array_fill_keys(correspondence_close_doc_kind_values(), true)
        : [];
    $out = [];
    foreach (get_documents($corrId) as $doc) {
        $kind = trim((string) ($doc['doc_kind'] ?? ''));
        if ($kind !== '' && isset($closeKinds[$kind])) {
            continue;
        }
        $relative = str_replace('\\', '/', trim((string) ($doc['file_path'] ?? '')));
        if ($relative === '' || !function_exists('dms_is_safe_upload_path') || !dms_is_safe_upload_path($relative)) {
            continue;
        }
        $full = function_exists('dms_storage_disk_path')
            ? dms_storage_disk_path($relative)
            : realpath(dirname(__DIR__) . '/' . $relative);
        if ($full === false || $full === '' || !is_file($full)) {
            continue;
        }
        $name = trim((string) ($doc['file_name'] ?? ''));
        if ($name === '') {
            $name = basename($full);
        }
        $out[] = [
            'id' => (int) ($doc['id'] ?? 0),
            'path' => $full,
            'name' => $name,
            'mime' => trim((string) ($doc['file_type'] ?? '')),
            'relative' => $relative,
        ];
    }
    return $out;
}

/** @deprecated استخدم dms_corr_email_attachments */
function dms_corr_send_attachments(array $corr): array
{
    return dms_corr_email_attachments($corr);
}

function dms_send_outgoing_email(array $corr, array $user): array
{
    $id = (int) ($corr['id'] ?? 0);
    $email = dms_corr_email_address($corr);
    if ($email === '') {
        $result = [
            'ok' => false,
            'status' => 'skipped',
            'channel' => 'email',
            'recipient' => '',
            'error' => __('send.err_no_email'),
            'provider' => 'smtp',
        ];
        if ($id) {
            dms_corr_log_delivery($id, 'email', '', 'skipped', $result['error'], 'smtp', $user);
        }
        return $result;
    }
    $bodies = dms_corr_email_bodies($corr);
    $html = (string) ($bodies['html'] ?? '');
    if ($html !== '' && !preg_match('/<html[\s>]/i', $html)) {
        $lang = function_exists('correspondence_letter_channel_lang')
            ? correspondence_letter_channel_lang($corr)
            : 'ar';
        $dir = $lang === 'en' ? 'ltr' : 'rtl';
        $font = (string) ($bodies['font_stack'] ?? '');
        if ($font === '') {
            $font = function_exists('letter_email_font_family')
                ? letter_email_font_family(function_exists('correspondence_letter_text') ? correspondence_letter_text($corr, $lang) : '')
                : (function_exists('dms_mail_font_family') ? dms_mail_font_family() : 'Arial, sans-serif');
        }
        $mso = (string) ($bodies['font_mso'] ?? 'Arial');
        $msoStyle = function_exists('letter_email_mso_style') ? letter_email_mso_style($mso) : '';
        $html = '<!DOCTYPE html><html lang="' . htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') . '" dir="' . $dir . '">'
            . '<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">'
            . (string) ($bodies['font_css'] ?? '')
            . '</head><body style="margin:0;padding:16px;font-family:' . $font . ';' . $msoStyle . 'font-size:14pt;line-height:1.5;">'
            . $html
            . '</body></html>';
    }
    $attachments = dms_corr_email_attachments($corr);
    $officialTmp = '';
    $redacted = function_exists('dms_corr_is_confidential_row') && dms_corr_is_confidential_row($corr)
        && function_exists('dms_conf_external_notify_redacted') && dms_conf_external_notify_redacted();
    if (!$redacted
        && (int) ($corr['email_letter_attach'] ?? 0) === 1
        && function_exists('correspondence_intend_electronic')
        && correspondence_intend_electronic($corr)
    ) {
        if (!function_exists('letter_email_official_attachment')) {
            require_once __DIR__ . '/letter_docx.php';
        }
        $official = letter_email_official_attachment($corr);
        if (is_array($official) && ($official['path'] ?? '') !== '') {
            $attachments[] = $official;
            $officialTmp = (string) $official['path'];
        }
    }
    $error = null;
    $mailDeptId = (int) ($corr['department_id'] ?? 0);
    if ($mailDeptId < 1) {
        $mailDeptId = (int) ($corr['issuing_department_id'] ?? 0);
    }
    if ($mailDeptId < 1) {
        $mailDeptId = (int) ($user['department_id'] ?? 0);
    }
    $ok = dms_send_mail(
        $email,
        $bodies['subject'],
        $html,
        $bodies['text'],
        $error,
        false,
        $mailDeptId > 0 ? $mailDeptId : null,
        false,
        null,
        $bodies['embeds'] ?? [],
        $attachments
    );
    foreach ($bodies['cleanup'] ?? [] as $tmp) {
        if (is_string($tmp) && $tmp !== '' && is_file($tmp)) {
            @unlink($tmp);
        }
    }
    if ($officialTmp !== '' && is_file($officialTmp)) {
        @unlink($officialTmp);
    }
    $result = [
        'ok' => $ok,
        'status' => $ok ? 'sent' : 'failed',
        'channel' => 'email',
        'recipient' => $email,
        'error' => $ok ? '' : (string) $error,
        'provider' => 'smtp',
    ];
    if ($id) {
        dms_corr_log_delivery($id, 'email', $email, $result['status'], $result['error'], 'smtp', $user);
        log_activity((int) ($user['id'] ?? 0), $id, $ok ? 'send_email' : 'send_email_fail', $email);
        if ($ok && function_exists('correspondence_intend_electronic') && correspondence_intend_electronic($corr)
            && function_exists('correspondence_mark_sent_if_open')) {
            correspondence_mark_sent_if_open($id, $user, __('send.email'));
        }
    }
    return $result;
}

function dms_send_outgoing_whatsapp(array $corr, array $user): array
{
    $id = (int) ($corr['id'] ?? 0);
    $digits = dms_corr_mobile_number($corr);
    $settings = dms_get_whatsapp_settings();
    $provider = (string) ($settings['provider'] ?? 'twilio');
    $providerLabel = dms_whatsapp_provider_label($provider, $settings);
    if ($digits === '') {
        $result = [
            'ok' => false,
            'status' => 'skipped',
            'channel' => 'whatsapp',
            'recipient' => '',
            'error' => __('send.err_no_mobile'),
            'provider' => $provider,
        ];
        if ($id) {
            dms_corr_log_delivery($id, 'whatsapp', '', 'skipped', $result['error'], $provider, $user);
        }
        return $result;
    }

    $lang = function_exists('correspondence_letter_channel_lang')
        ? correspondence_letter_channel_lang($corr)
        : (function_exists('dms_lang_code') && dms_lang_code() === 'en' ? 'en' : 'ar');
    $templateId = dms_whatsapp_template_id($settings, $lang);
    $useTemplate = $templateId !== '' && in_array($provider, ['twilio', 'meta'], true);
    $textBody = dms_corr_whatsapp_text($corr);
    $attachments = dms_corr_email_attachments($corr);

    if ($useTemplate) {
        $vars = [
            'name' => dms_corr_honored_name($corr) ?: __lang('send.recipient', $lang),
            'number' => format_ref_number($corr['ref_number'] ?? ''),
            'link' => dms_corr_verify_url($corr),
            'subject' => (string) ($corr['subject'] ?? ''),
        ];
        $send = dms_whatsapp_send_template($digits, $lang, $vars);
    } else {
        // أول مرفق مع النص إن أمكن، ثم باقي المرفقات لاحقاً
        $first = $attachments[0] ?? null;
        $opts = [];
        if (is_array($first)) {
            $opts['attachment'] = $first;
            $attachments = array_slice($attachments, 1);
        }
        $send = dms_whatsapp_send_text($digits, $textBody, $opts);
    }

    $ok = !empty($send['ok']);
    $error = $ok ? '' : trim((string) ($send['error'] ?? __('send.wa_fail')));
    $attachWarnings = [];

    if ($ok && $attachments !== []) {
        foreach ($attachments as $attachment) {
            $docSend = dms_whatsapp_send_document($digits, $attachment, '');
            if (empty($docSend['ok'])) {
                $attachWarnings[] = trim((string) ($docSend['error'] ?? __('send.wa_attach_fail')));
            }
        }
    }

    if ($ok && $attachWarnings !== []) {
        $error = __('send.wa_sent_attach_partial') . ' ' . implode(' | ', array_slice($attachWarnings, 0, 3));
    }

    $result = [
        'ok' => $ok,
        'status' => $ok ? 'sent' : 'failed',
        'channel' => 'whatsapp',
        'recipient' => '+' . $digits,
        'error' => $error,
        'provider' => $provider,
        'provider_label' => $providerLabel,
    ];
    if ($id) {
        dms_corr_log_delivery($id, 'whatsapp', '+' . $digits, $result['status'], $error, $provider, $user);
        log_activity((int) ($user['id'] ?? 0), $id, $ok ? 'send_whatsapp' : 'send_whatsapp_fail', '+' . $digits);
    }
    return $result;
}

function dms_mark_delivery(array $corr, array $user): array
{
    $id = (int) ($corr['id'] ?? 0);
    if (($corr['type'] ?? '') !== 'outgoing' || $id <= 0) {
        return [
            'ok' => false,
            'status' => 'skipped',
            'channel' => 'delivery',
            'recipient' => '',
            'error' => __('send.err_outgoing_only'),
            'provider' => 'delivery',
        ];
    }
    $recipient = trim((string) ($corr['party_name'] ?? ''));
    dms_corr_log_delivery($id, 'delivery', $recipient, 'sent', '', 'delivery', $user);
    log_activity((int) ($user['id'] ?? 0), $id, 'delivery');
    return [
        'ok' => true,
        'status' => 'sent',
        'channel' => 'delivery',
        'recipient' => $recipient,
        'error' => '',
        'provider' => 'delivery',
    ];
}

function dms_send_outgoing_channel(array $corr, string $channel, array $user, string $receiptName = ''): array
{
    if (($corr['type'] ?? '') !== 'outgoing') {
        return [
            'ok' => false,
            'status' => 'skipped',
            'channel' => $channel,
            'recipient' => '',
            'error' => __('send.err_outgoing_only'),
            'provider' => '',
        ];
    }
    if (correspondence_is_cancelled($corr)) {
        return [
            'ok' => false,
            'status' => 'skipped',
            'channel' => $channel,
            'recipient' => '',
            'error' => __('send.err_cancelled'),
            'provider' => '',
        ];
    }
    $electronic = function_exists('correspondence_intend_electronic') && correspondence_intend_electronic($corr);
    $paper = function_exists('correspondence_intend_paper') && correspondence_intend_paper($corr);
    if (in_array($channel, ['email', 'whatsapp'], true) && !$electronic) {
        return [
            'ok' => false,
            'status' => 'skipped',
            'channel' => $channel,
            'recipient' => '',
            'error' => __('send.skip_not_electronic'),
            'provider' => '',
        ];
    }
    if ($channel === 'delivery' && !$paper) {
        return [
            'ok' => false,
            'status' => 'skipped',
            'channel' => $channel,
            'recipient' => '',
            'error' => __('send.skip_not_paper'),
            'provider' => '',
        ];
    }
    if (function_exists('outgoing_can_issue') && !outgoing_can_issue($corr)) {
        $msg = function_exists('outgoing_approval_block_reason') ? outgoing_approval_block_reason($corr) : __('issue_appr.blocked_pending');
        return [
            'ok' => false,
            'status' => 'skipped',
            'channel' => $channel,
            'recipient' => '',
            'error' => $msg !== '' ? $msg : __('issue_appr.blocked_pending'),
            'provider' => '',
        ];
    }
    if ($channel === 'delivery') {
        $paper = function_exists('correspondence_intend_paper') && correspondence_intend_paper($corr);
        if (!$paper) {
            return [
                'ok' => false,
                'status' => 'skipped',
                'channel' => 'delivery',
                'recipient' => '',
                'error' => __('send.skip_not_paper'),
                'provider' => '',
            ];
        }
        $receiptName = trim($receiptName);
        if ($receiptName === '') {
            return [
                'ok' => false,
                'status' => 'skipped',
                'channel' => 'delivery',
                'recipient' => '',
                'error' => __('receipt.name_required'),
                'provider' => 'delivery',
            ];
        }
        $date = trim((string) ($corr['receipt_date'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d');
        }
        try {
            save_outgoing_receipt((int) $corr['id'], [
                'receipt_name' => $receiptName,
                'receipt_date' => $date,
                'receipt_phone' => (string) ($corr['receipt_phone'] ?? ''),
            ], null, (int) ($user['id'] ?? 0));
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'status' => 'failed',
                'channel' => 'delivery',
                'recipient' => $receiptName,
                'error' => $e->getMessage(),
                'provider' => 'delivery',
            ];
        }
        $fresh = function_exists('get_correspondence') ? (get_correspondence((int) $corr['id']) ?: $corr) : $corr;

        return dms_mark_delivery($fresh, $user);
    }
    return $channel === 'whatsapp'
        ? dms_send_outgoing_whatsapp($corr, $user)
        : dms_send_outgoing_email($corr, $user);
}

function dms_send_outgoing_batch_channel(int $batchId, string $channel, array $user, ?array $onlyIds = null, array $receiptNames = []): array
{
    @set_time_limit(0);
    $items = outgoing_batch_items($batchId, $user);
    if ($onlyIds !== null) {
        $want = array_fill_keys(array_map('intval', $onlyIds), true);
        $items = array_values(array_filter(
            $items,
            static fn(array $row): bool => isset($want[(int) ($row['id'] ?? 0)])
        ));
    }
    $summary = [
        'total' => count($items),
        'sent' => 0,
        'failed' => 0,
        'skipped' => 0,
        'channel' => $channel,
        'items' => [],
    ];
    foreach ($items as $corr) {
        $name = trim((string) ($receiptNames[(int) ($corr['id'] ?? 0)] ?? ''));
        $row = dms_send_outgoing_channel($corr, $channel, $user, $name);
        $summary['items'][] = $row;
        if (($row['status'] ?? '') === 'sent') {
            $summary['sent']++;
        } elseif (($row['status'] ?? '') === 'skipped') {
            $summary['skipped']++;
        } else {
            $summary['failed']++;
        }
    }
    return $summary;
}

function dms_send_flash_from_result(array $result): void
{
    $channel = (string) ($result['channel'] ?? 'email');
    $status = (string) ($result['status'] ?? 'failed');
    if ($status === 'sent') {
        $okMsg = match ($channel) {
            'whatsapp' => __('send.wa_ok'),
            'delivery' => __('send.delivery_ok'),
            default => __('send.email_ok'),
        };
        flash('success', $okMsg);
        return;
    }
    $error = trim((string) ($result['error'] ?? ''));
    $failPrefix = match ($channel) {
        'whatsapp' => __('send.wa_fail'),
        'delivery' => __('send.delivery_fail'),
        default => __('send.email_fail'),
    };
    $msg = $status === 'skipped'
        ? $error
        : ($failPrefix . ($error !== '' ? ': ' . $error : ''));
    flash($status === 'skipped' ? 'warning' : 'danger', $msg);
}

function dms_send_flash_from_batch(array $summary): void
{
    $msg = sprintf(
        __('send.batch_ok'),
        (int) ($summary['sent'] ?? 0),
        (int) ($summary['failed'] ?? 0),
        (int) ($summary['skipped'] ?? 0)
    );
    $sent = (int) ($summary['sent'] ?? 0);
    $failed = (int) ($summary['failed'] ?? 0);
    if ($sent > 0 && $failed === 0) {
        flash('success', $msg);
    } elseif ($sent > 0) {
        flash('warning', $msg);
    } else {
        flash('danger', $msg);
    }
}
