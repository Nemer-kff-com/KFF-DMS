<?php
/**
 * إشعارات داخلية للمتابعة (إحالات ومواعيد الرد)
 */

function ensure_notifications_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        db()->exec(
            "CREATE TABLE IF NOT EXISTS `tblnotifications` (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                department_id INT UNSIGNED NULL DEFAULT NULL,
                correspondence_id INT UNSIGNED NULL DEFAULT NULL,
                type VARCHAR(40) NOT NULL,
                title VARCHAR(255) NOT NULL,
                body VARCHAR(500) NULL DEFAULT NULL,
                link VARCHAR(255) NULL DEFAULT NULL,
                dedupe_key VARCHAR(120) NULL DEFAULT NULL,
                is_read TINYINT(1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uk_notif_dedupe (user_id, dedupe_key),
                INDEX idx_notif_user_read (user_id, is_read, created_at),
                INDEX idx_notif_corr (correspondence_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        // حذف ناعم: يبقى الصف ليمنع مفتاح dedupe إعادة إنشاء الإشعار نفسه
        if (!dms_db_column_exists('tblnotifications', 'is_deleted')) {
            db()->exec("ALTER TABLE `tblnotifications` ADD COLUMN `is_deleted` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_read`");
        }
    } catch (Throwable $e) {
        error_log('DMS notifications schema: ' . $e->getMessage());
    }
}

function dms_department_user_ids(?int $departmentId): array
{
    return array_map(
        static fn(array $u): int => (int) $u['id'],
        dms_department_users($departmentId)
    );
}

/** @return list<array<string,mixed>> */
function dms_department_users(?int $departmentId): array
{
    $users = [];
    try {
        if (!$departmentId || $departmentId <= 0) {
            return [];
        }
        if (function_exists('ensure_user_departments_schema')) {
            ensure_user_departments_schema();
        }
        try {
            $stmt = db()->prepare(
                "SELECT DISTINCT u.id, u.email, u.name_ar, u.name_en, u.role, u.department_id
                 FROM `tblusers` u
                 LEFT JOIN `tbluser_departments` ud ON ud.user_id = u.id
                 WHERE u.is_active = 1
                   AND (ud.department_id = ? OR u.department_id = ?)"
            );
            $stmt->execute([$departmentId, $departmentId]);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $stmt = db()->prepare(
                "SELECT id, email, name_ar, name_en, role, department_id
                 FROM `tblusers`
                 WHERE is_active = 1 AND department_id = ?"
            );
            $stmt->execute([$departmentId]);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Throwable $e) {
        return [];
    }
    return $users;
}

/** @param list<int> $excludeUserIds */
function dms_notification_recipient_ids(?int $departmentId, array $excludeUserIds = []): array
{
    $exclude = array_fill_keys(array_map('intval', $excludeUserIds), true);
    $ids = [];
    foreach (dms_department_users($departmentId) as $row) {
        $uid = (int) ($row['id'] ?? 0);
        if ($uid > 0 && !isset($exclude[$uid])) {
            $ids[] = $uid;
        }
    }
    return array_values(array_unique($ids));
}

/**
 * @param list<array{label:string,value:string,ltr?:bool}> $rows صفوف مُعنونة داخل البطاقة (مثل بريد دعوة المستخدم)؛ فارغة = سطر التفاصيل فقط
 * @return array{subject:string,html:string,text:string,embeds:list<array{path:string,cid:string,name:string}>}
 */
function dms_notification_build_email(array $recipient, string $intro, string $details, string $ctaLabel, string $ctaUrl, array $rows = []): array
{
    require_once __DIR__ . '/mailer.php';

    $name = user_display_name($recipient) ?: (string) ($recipient['email'] ?? '');
    $safeName = e($name);
    $safeIntro = e($intro);
    $safeDetails = e($details);
    $safeCta = e($ctaLabel);
    $safeUrl = e($ctaUrl);
    $font = dms_mail_font_family();
    $accent = '#3B4F6E';
    $bg = '#f8f6f2';
    $border = '#e6e2da';
    $muted = '#6b7280';
    $greeting = str_replace(':name', $safeName, e(__('notify.email_greeting')));

    $subject = $intro . ' — ' . org_name();

    $rows = array_values(array_filter($rows, static fn($r): bool => is_array($r) && trim((string) ($r['value'] ?? '')) !== ''));
    $cardHtml = '';
    if ($rows !== []) {
        $last = count($rows) - 1;
        foreach ($rows as $i => $row) {
            $valueStyle = !empty($row['ltr'])
                ? 'unicode-bidi:embed;font-size:15px;font-family:Arial,sans-serif;'
                : 'font-size:15px;font-family:' . $font . ';line-height:1.6;';
            $cardHtml .= '<div style="margin:0 0 ' . ($i === $last ? '0' : '12px') . ';">'
                . '<span style="display:block;font-size:11px;color:' . $muted . ';margin-bottom:2px;font-family:' . $font . ';">' . e((string) $row['label']) . '</span>'
                . '<strong' . (!empty($row['ltr']) ? ' dir="ltr"' : '') . ' style="' . $valueStyle . '">' . nl2br(e((string) $row['value']), false) . '</strong></div>';
        }
    } else {
        $cardHtml = '<div style="margin:0;"><span style="display:block;font-size:11px;color:' . $muted . ';margin-bottom:4px;font-family:' . $font . ';">' . e(__('notify.email_details_label')) . '</span>'
            . '<strong style="font-size:15px;font-family:' . $font . ';line-height:1.6;">' . $safeDetails . '</strong></div>';
    }

    $body = '<p style="margin:0 0 14px;font-size:16px;font-weight:700;color:' . $accent . ';font-family:' . $font . ';">' . $greeting . '</p>'
        . '<p style="margin:0 0 18px;font-size:14px;color:' . $accent . ';font-family:' . $font . ';">' . $safeIntro . '</p>'
        . '<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin:0 0 22px;background:' . $bg . ';border:1px solid ' . $border . ';border-radius:12px;">'
        . '<tr><td style="padding:16px 18px;font-size:14px;color:' . $accent . ';font-family:' . $font . ';">'
        . $cardHtml
        . '</td></tr></table>'
        . '<p style="text-align:center;margin:0 0 16px;">'
        . '<a href="' . $safeUrl . '" style="display:inline-block;padding:12px 28px;background:' . $accent . ';color:#ffffff;text-decoration:none;font-weight:700;border-radius:8px;font-size:14px;font-family:' . $font . ';">'
        . $safeCta . '</a></p>'
        . '<p style="margin:0 0 6px;font-size:12px;color:' . $muted . ';font-family:' . $font . ';">' . e(__('notify.email_link_label')) . ':</p>'
        . '<p style="margin:0 0 18px;font-size:12px;word-break:break-all;direction:ltr;text-align:left;font-family:Arial,sans-serif;">'
        . '<a href="' . $safeUrl . '" style="color:' . $accent . ';">' . $safeUrl . '</a></p>';

    $shell = dms_mail_branded_shell($body, __('notify.email_footer'));

    $detailsText = $details;
    if ($rows !== []) {
        $detailsText = implode("\n", array_map(
            static fn(array $r): string => $r['label'] . ': ' . str_replace("\n", "\n  ", (string) $r['value']),
            $rows
        ));
    }
    $text = str_replace(':name', $name, __('notify.email_greeting')) . "\n\n"
        . $intro . "\n\n"
        . $detailsText . "\n\n"
        . $ctaLabel . ":\n{$ctaUrl}\n";

    return [
        'subject' => $subject,
        'html' => $shell['html'],
        'text' => $text,
        'embeds' => $shell['embeds'],
    ];
}

function dms_notify_incoming_assigned(int $corrId, array $corr, int $deptId, array $actor, string $reason = 'new'): void
{
    if ($corrId <= 0 || $deptId <= 0 || (($corr['type'] ?? '') !== 'incoming')) {
        return;
    }

    $ref = format_ref_number($corr['ref_number'] ?? '');
    $subjectLine = trim((string) ($corr['subject'] ?? ''));
    $party = trim((string) ($corr['party_name'] ?? ''));
    $actorName = trim(user_actor_name($actor));
    $deptName = '';
    try {
        $stmt = db()->prepare('SELECT name, name_en FROM `tbldepartments` WHERE id = ?');
        $stmt->execute([$deptId]);
        $deptRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($deptRow) {
            $deptName = department_display_name($deptRow);
        }
    } catch (Throwable $e) {
        // ignore
    }

    $title = $reason === 'dept_change'
        ? __('notify.incoming_dept_title')
        : __('notify.incoming_title');
    $bodyParts = array_filter([$ref, $subjectLine, $party !== '' ? $party : null]);
    if ($actorName !== '') {
        $bodyParts[] = $actorName;
    }
    $body = implode(' — ', $bodyParts);
    $link = 'incoming/view.php?id=' . $corrId;
    $dedupe = ($reason === 'dept_change' ? 'incoming_dept:' : 'incoming:') . $corrId . ':' . $deptId;

    $exclude = [(int) ($actor['id'] ?? 0)];
    $recipientIds = dms_notification_recipient_ids($deptId, $exclude);
    dms_notify_users($recipientIds, [
        'type' => 'incoming_assigned',
        'title' => $title,
        'body' => $body,
        'link' => $link,
        'correspondence_id' => $corrId,
        'department_id' => $deptId,
        'dedupe_key' => $dedupe,
    ]);

    $recipients = array_values(array_filter(
        dms_department_users($deptId),
        static function (array $row) use ($exclude): bool {
            $uid = (int) ($row['id'] ?? 0);
            return $uid > 0 && !in_array($uid, $exclude, true);
        }
    ));
    if ($recipients === []) {
        return;
    }

    require_once __DIR__ . '/mailer.php';
    if (!dms_mail_ready($deptId > 0 ? $deptId : null)) {
        return;
    }

    $intro = $reason === 'dept_change'
        ? __('notify.incoming_dept_email_intro')
        : __('notify.incoming_email_intro');
    if ($deptName !== '') {
        $intro .= ' (' . $deptName . ')';
    }
    $details = trim($ref . ' — ' . $subjectLine . ($party !== '' ? ' — ' . $party : ''));
    $emailRows = dms_notification_rows_with_parties([
        ['label' => __('corr.number'), 'value' => $ref, 'ltr' => true],
        ['label' => __('corr.subject'), 'value' => $subjectLine],
    ], $details, $corrId);
    require_once __DIR__ . '/user_invite.php';
    $viewUrl = dms_mail_public_url($link);
    foreach ($recipients as $recipient) {
        $email = trim((string) ($recipient['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            continue;
        }
        $built = dms_notification_build_email(
            $recipient,
            $intro,
            $details,
            __('notify.email_view_incoming'),
            $viewUrl,
            $emailRows
        );
        $err = null;
        if (!dms_send_mail($email, $built['subject'], $built['html'], $built['text'], $err, false, $deptId > 0 ? $deptId : null, false, null, $built['embeds'] ?? [])) {
            error_log('DMS incoming notify email to ' . $email . ': ' . (string) $err);
        }
    }
}

/**
 * عرض نص إشعار مخزّن — يترجم مفاتيح i18n إن حُفظت بالخطأ كمفتاح خام.
 */
function dms_notification_display_text(?string $text): string
{
    $text = trim((string) $text);
    if ($text === '') {
        return '';
    }
    if (preg_match('/^[a-z][a-z0-9]*(?:\.[a-z0-9_]+)+$/i', $text)) {
        if (function_exists('outgoing_appr_text') && str_starts_with($text, 'issue_appr.')) {
            $text = outgoing_appr_text($text);
        } else {
            $translated = __($text);
            if ($translated !== $text) {
                $text = $translated;
            }
        }
        $text = preg_replace('/\s*:[a-z_][a-z0-9_]*\s*/iu', ' ', $text) ?? $text;
        $text = trim(preg_replace('/\s{2,}/u', ' ', $text) ?? $text);
    }
    return $text;
}

/** إصلاح إشعارات حُفظت بمفتاح ترجمة بدل النص */
function dms_notifications_repair_lang_keys(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        ensure_notifications_schema();
        $stmt = db()->query(
            "SELECT id, title, body FROM `tblnotifications`
             WHERE title LIKE 'issue_appr.%'
                OR body LIKE 'issue_appr.%'
                OR title LIKE 'notify.%'
                OR body LIKE 'notify.%'
             ORDER BY id DESC
             LIMIT 300"
        );
        $rows = $stmt ? ($stmt->fetchAll() ?: []) : [];
        if ($rows === []) {
            return;
        }
        $upd = db()->prepare('UPDATE `tblnotifications` SET title = ?, body = ? WHERE id = ?');
        foreach ($rows as $row) {
            $title = dms_notification_display_text((string) ($row['title'] ?? ''));
            $body = dms_notification_display_text((string) ($row['body'] ?? ''));
            if ($title === (string) ($row['title'] ?? '') && $body === (string) ($row['body'] ?? '')) {
                continue;
            }
            if ($title === '') {
                continue;
            }
            $upd->execute([$title, $body !== '' ? $body : null, (int) $row['id']]);
        }
    } catch (Throwable $e) {
        error_log('DMS notify repair: ' . $e->getMessage());
    }
}

/** طرف «موجهة إلى/من» للبريد: اللقب، الاسم، الوظيفة، جهة العمل — كلٌّ بسطر */
function dms_notification_party_lines(array $part): string
{
    $person = trim((string) ($part['person'] ?? ''));
    $org = trim((string) ($part['org'] ?? ''));
    $lines = [
        trim((string) ($part['prefix'] ?? '')),
        trim($person . ' ' . trim((string) ($part['honorific'] ?? ''))),
        trim((string) ($part['job'] ?? '')),
        $org !== $person ? $org : '',
    ];
    return implode("\n", array_values(array_filter($lines, static fn(string $v): bool => $v !== '')));
}

/**
 * صفّا «موجهة إلى» و«موجهة من» لمعاملة، بنفس أطراف نموذج الطباعة.
 *
 * @return list<array{label:string,value:string,party:bool}>
 */
function dms_notification_party_rows(int $corrId): array
{
    if ($corrId <= 0 || !function_exists('get_correspondence') || !function_exists('print_direction_parts')) {
        return [];
    }
    try {
        $corr = get_correspondence($corrId);
        if (!$corr) {
            return [];
        }
        $parts = print_direction_parts($corr);
    } catch (Throwable $e) {
        error_log('DMS notify party rows: ' . $e->getMessage());
        return [];
    }
    $rows = [];
    foreach (['to' => 'print.directed_to', 'from' => 'print.directed_from'] as $key => $label) {
        $value = dms_notification_party_lines($parts[$key] ?? []);
        if ($value !== '') {
            $rows[] = ['label' => __($label), 'value' => $value, 'party' => true];
        }
    }
    return $rows;
}

/**
 * يضيف «موجهة إلى/من» لصفوف بريد الإشعار بعد الموضوع (مرة واحدة)، ويحذف سطر الطرف المختصر إن وُجد.
 *
 * @param list<array<string,mixed>> $rows
 * @return list<array<string,mixed>>
 */
function dms_notification_rows_with_parties(array $rows, string $details, int $corrId): array
{
    foreach ($rows as $row) {
        if (!empty($row['party'])) {
            return $rows;
        }
    }
    $partyRows = dms_notification_party_rows($corrId);
    if ($partyRows === []) {
        return $rows;
    }
    if ($rows === []) {
        $base = trim($details) !== '' ? [['label' => __('notify.email_details_label'), 'value' => trim($details)]] : [];
        return array_merge($base, $partyRows);
    }
    $shortPartyLabels = [__('corr.party_to'), __('corr.party'), __('corr.party_from'), __('internal.section_route')];
    $rows = array_values(array_filter(
        $rows,
        static fn(array $r): bool => !in_array((string) ($r['label'] ?? ''), $shortPartyLabels, true)
    ));
    $at = 0;
    foreach ($rows as $i => $row) {
        if ((string) ($row['label'] ?? '') === __('corr.subject')) {
            $at = $i + 1;
            break;
        }
    }
    array_splice($rows, $at, 0, $partyRows);
    return $rows;
}

/**
 * إرسال بريد إلكتروني لمستخدمين محددين (إشعار نظام + بريد اختياري).
 *
 * @param list<int> $userIds
 */
/** @param list<array{label:string,value:string,ltr?:bool}> $rows */
function dms_notify_user_emails(array $userIds, string $intro, string $details, string $linkPath, string $ctaLabel = '', ?int $departmentId = null, array $rows = []): void
{
    $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
    if ($userIds === []) {
        return;
    }
    require_once __DIR__ . '/mailer.php';
    $deptId = $departmentId !== null && $departmentId > 0 ? $departmentId : null;
    if (!dms_mail_ready($deptId)) {
        return;
    }

    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
    try {
        $stmt = db()->prepare(
            "SELECT id, name_ar, name_en, email
             FROM `tblusers`
             WHERE id IN ({$placeholders}) AND is_active = 1"
        );
        $stmt->execute($userIds);
        $recipients = $stmt->fetchAll() ?: [];
    } catch (Throwable $e) {
        error_log('DMS notify emails load: ' . $e->getMessage());
        return;
    }
    if ($recipients === []) {
        return;
    }

    $ctaLabel = trim($ctaLabel) !== '' ? $ctaLabel : __('notify.email_view_correspondence');
    require_once __DIR__ . '/user_invite.php';
    $viewUrl = dms_mail_public_url($linkPath);

    foreach ($recipients as $recipient) {
        $email = trim((string) ($recipient['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            continue;
        }
        $built = dms_notification_build_email($recipient, $intro, $details, $ctaLabel, $viewUrl, $rows);
        $err = null;
        if (!dms_send_mail($email, $built['subject'], $built['html'], $built['text'], $err, false, $deptId, false, null, $built['embeds'] ?? [])) {
            error_log('DMS notify email to ' . $email . ': ' . (string) $err);
        }
    }
}

/**
 * @param list<int> $userIds
 * @param array{type:string,title:string,body?:string,link?:string,correspondence_id?:int,department_id?:int,dedupe_key?:string,email?:bool,email_intro?:string,email_details?:string,email_cta?:string,email_rows?:list<array{label:string,value:string,ltr?:bool}>} $payload
 */
function dms_notify_users(array $userIds, array $payload): void
{
    ensure_notifications_schema();
    $type = trim((string) ($payload['type'] ?? ''));
    $title = trim((string) ($payload['title'] ?? ''));
    // إن وصل مفتاح ترجمة بالخطأ، حوّله لنص قبل التخزين
    $title = dms_notification_display_text($title);
    if ($type === '' || $title === '' || $userIds === []) {
        return;
    }
    $body = trim((string) ($payload['body'] ?? ''));
    $body = $body !== '' ? dms_notification_display_text($body) : '';
    $body = $body !== '' ? $body : null;
    $link = trim((string) ($payload['link'] ?? '')) ?: null;
    $corrId = (int) ($payload['correspondence_id'] ?? 0) ?: null;
    $deptId = (int) ($payload['department_id'] ?? 0) ?: null;
    $dedupe = trim((string) ($payload['dedupe_key'] ?? '')) ?: null;

    try {
        $stmt = db()->prepare(
            "INSERT IGNORE INTO `tblnotifications`
             (user_id, department_id, correspondence_id, type, title, body, link, dedupe_key)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        foreach ($userIds as $uid) {
            $uid = (int) $uid;
            if ($uid <= 0) {
                continue;
            }
            $stmt->execute([$uid, $deptId, $corrId, $type, $title, $body, $link, $dedupe]);
        }
    } catch (Throwable $e) {
        error_log('DMS notify: ' . $e->getMessage());
    }

    // بريد اختياري لنفس المستلمين
    if (!empty($payload['email']) && $link) {
        $intro = trim((string) ($payload['email_intro'] ?? $title));
        $details = trim((string) ($payload['email_details'] ?? ($body ?? '')));
        $cta = trim((string) ($payload['email_cta'] ?? ''));
        $rows = is_array($payload['email_rows'] ?? null) ? $payload['email_rows'] : [];
        $rows = dms_notification_rows_with_parties($rows, $details, (int) $corrId);
        if ($intro !== '' && ($details !== '' || $rows !== [])) {
            dms_notify_user_emails($userIds, $intro, $details, $link, $cta, $deptId, $rows);
        }
    }
}

function dms_notify_referral(int $referralId, array $corr, int $toDept, string $fromName = '', ?array $actor = null): void
{
    $corrId = (int) ($corr['id'] ?? 0);
    $folder = function_exists('correspondence_type_folder')
        ? correspondence_type_folder((string) ($corr['type'] ?? 'incoming'))
        : ((($corr['type'] ?? '') === 'outgoing') ? 'outgoing' : 'incoming');
    $ref = format_ref_number($corr['ref_number'] ?? '');
    $subject = (string) ($corr['subject'] ?? '');
    $who = trim($fromName);
    $link = $folder . '/view.php?id=' . $corrId;
    $body = trim($ref . ' — ' . $subject . ($who !== '' ? ' — ' . $who : ''));

    $exclude = $actor ? [(int) ($actor['id'] ?? 0)] : [];
    $recipientIds = dms_notification_recipient_ids($toDept, $exclude);
    dms_notify_users($recipientIds, [
        'type' => 'referral',
        'title' => __('notify.referral_title'),
        'body' => $body,
        'link' => $link,
        'correspondence_id' => $corrId,
        'department_id' => $toDept,
        'dedupe_key' => 'referral:' . $referralId,
    ]);

    $recipients = array_values(array_filter(
        dms_department_users($toDept),
        static function (array $row) use ($exclude): bool {
            $uid = (int) ($row['id'] ?? 0);
            return $uid > 0 && !in_array($uid, $exclude, true);
        }
    ));
    if ($recipients === []) {
        return;
    }

    require_once __DIR__ . '/mailer.php';
    if (!dms_mail_ready($toDept > 0 ? $toDept : null)) {
        return;
    }

    $intro = __('notify.referral_email_intro');
    $details = $body;
    $emailRows = [
        ['label' => __('corr.number'), 'value' => $ref, 'ltr' => true],
        ['label' => __('corr.subject'), 'value' => $subject],
    ];
    if ($who !== '') {
        $emailRows[] = ['label' => __('notify.referral_by'), 'value' => $who];
    }
    $emailRows = dms_notification_rows_with_parties($emailRows, $details, $corrId);
    require_once __DIR__ . '/user_invite.php';
    $viewUrl = dms_mail_public_url($link);
    $sent = 0;
    foreach ($recipients as $recipient) {
        $email = trim((string) ($recipient['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            continue;
        }
        $built = dms_notification_build_email(
            $recipient,
            $intro,
            $details,
            __('notify.email_view_correspondence'),
            $viewUrl,
            $emailRows
        );
        $err = null;
        if (!dms_send_mail($email, $built['subject'], $built['html'], $built['text'], $err, false, $toDept > 0 ? $toDept : null, false, null, $built['embeds'] ?? [])) {
            error_log('DMS referral notify email to ' . $email . ': ' . (string) $err);
            continue;
        }
        $sent++;
        error_log('DMS referral email OK referral=' . $referralId . ' to=' . $email . ' dept=' . $toDept);
    }
    if ($sent === 0) {
        error_log('DMS referral email: no recipients for dept=' . $toDept . ' referral=' . $referralId);
    }
}

function dms_generate_due_notifications(?array $user = null): void
{
    if ($user && !empty($_SESSION['dms_due_notif_at']) && (int) $_SESSION['dms_due_notif_at'] > time() - 3600) {
        return;
    }
    $_SESSION['dms_due_notif_at'] = time();
    ensure_notifications_schema();

    try {
        $stmt = db()->query(
            "SELECT c.id, c.type, c.ref_number, c.subject, c.reply_due, c.department_id
             FROM `tblcorrespondence` c
             WHERE c.reply_due IS NOT NULL
               AND c.reply_due BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 2 DAY)
               AND c.status NOT IN ('replied','delivered','closed','cancelled')"
        );
        $rows = $stmt->fetchAll();
    } catch (Throwable $e) {
        return;
    }

    $today = date('Y-m-d');
    foreach ($rows as $row) {
        $due = (string) ($row['reply_due'] ?? '');
        $kind = $due === $today ? 'due_today' : 'due_soon';
        $folder = (($row['type'] ?? '') === 'outgoing') ? 'outgoing' : 'incoming';
        $corrId = (int) $row['id'];
        $deptId = (int) ($row['department_id'] ?? 0) ?: null;
        dms_notify_users(dms_department_user_ids($deptId), [
            'type' => $kind,
            'title' => $kind === 'due_today' ? __('notify.due_today_title') : __('notify.due_soon_title'),
            'body' => trim(format_ref_number($row['ref_number'] ?? '') . ' — ' . ($row['subject'] ?? '') . ' — ' . format_date($due)),
            'link' => $folder . '/view.php?id=' . $corrId,
            'correspondence_id' => $corrId,
            'department_id' => $deptId,
            'dedupe_key' => $kind . ':' . $corrId . ':' . $today,
        ]);
    }
}

function dms_notifications_unread_count(?array $user = null): int
{
    $user = $user ?? current_user();
    if (!$user) {
        return 0;
    }
    ensure_notifications_schema();
    try {
        $stmt = db()->prepare(
            "SELECT COUNT(*) FROM `tblnotifications` WHERE user_id = ? AND is_read = 0 AND is_deleted = 0"
        );
        $stmt->execute([(int) $user['id']]);
        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

function dms_notifications_list(?array $user = null, int $limit = 50, bool $unreadOnly = false): array
{
    $user = $user ?? current_user();
    if (!$user) {
        return [];
    }
    ensure_notifications_schema();
    dms_notifications_repair_lang_keys();
    $limit = max(1, min(200, $limit));
    try {
        $sql = "SELECT * FROM `tblnotifications` WHERE user_id = ? AND is_deleted = 0";
        $params = [(int) $user['id']];
        if ($unreadOnly) {
            $sql .= " AND is_read = 0";
        }
        $sql .= " ORDER BY id DESC LIMIT {$limit}";
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function dms_notification_get(int $id, array $user): ?array
{
    ensure_notifications_schema();
    try {
        $stmt = db()->prepare(
            "SELECT * FROM `tblnotifications` WHERE id = ? AND user_id = ? AND is_deleted = 0"
        );
        $stmt->execute([$id, (int) $user['id']]);
        $row = $stmt->fetch();
        return $row ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function dms_notification_mark_read(int $id, array $user): void
{
    ensure_notifications_schema();
    try {
        db()->prepare(
            "UPDATE `tblnotifications` SET is_read = 1 WHERE id = ? AND user_id = ?"
        )->execute([$id, (int) $user['id']]);
    } catch (Throwable $e) {
        // تجاهل
    }
}

function dms_notifications_mark_all_read(array $user): void
{
    ensure_notifications_schema();
    try {
        db()->prepare(
            "UPDATE `tblnotifications` SET is_read = 1 WHERE user_id = ? AND is_read = 0"
        )->execute([(int) $user['id']]);
    } catch (Throwable $e) {
        // تجاهل
    }
}

/**
 * حذف إشعارات المستخدم المحددة (حذف ناعم يخفيها من القائمة والعدّاد).
 *
 * @param list<int> $ids
 */
function dms_notifications_delete(array $ids, array $user): int
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));
    if ($ids === []) {
        return 0;
    }
    ensure_notifications_schema();
    try {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = db()->prepare(
            "UPDATE `tblnotifications` SET is_deleted = 1, is_read = 1
             WHERE user_id = ? AND is_deleted = 0 AND id IN ({$placeholders})"
        );
        $stmt->execute(array_merge([(int) $user['id']], $ids));
        return $stmt->rowCount();
    } catch (Throwable $e) {
        error_log('DMS notifications delete: ' . $e->getMessage());
        return 0;
    }
}

function dms_notification_icon(string $type): string
{
    return match ($type) {
        'referral' => 'bi-share',
        'incoming_assigned' => 'bi-inbox',
        'due_today' => 'bi-exclamation-triangle',
        'due_soon' => 'bi-calendar-event',
        'corr_cc' => 'bi-eye',
        'corr_cc_ack' => 'bi-check2-circle',
        'corr_closed' => 'bi-lock',
        'corr_reopened' => 'bi-unlock',
        'close_request' => 'bi-send-check',
        'close_request_decided' => 'bi-check2-circle',
        'outgoing_prep' => 'bi-pencil-square',
        'circular_issued' => 'bi-megaphone',
        default => 'bi-bell',
    };
}

/**
 * هل حدث الإشعار المقروء ما زال قائماً فيُبقى ولا يُحذف.
 *
 * @param array<string,mixed> $n
 * @param array{corr:array<int,array<string,mixed>>,referral:array<int,string>,cc:array<string,array{status:string,seen:bool}>,coverage:array<int,bool>,close:array<int,bool>} $ctx
 */
function dms_notification_event_is_live(array $n, array $ctx): bool
{
    $type = (string) ($n['type'] ?? '');
    $dedupe = (string) ($n['dedupe_key'] ?? '');
    $corrId = (int) ($n['correspondence_id'] ?? 0);
    $userId = (int) ($n['user_id'] ?? 0);
    $corr = $ctx['corr'][$corrId] ?? null;
    $settled = static function (?array $row): bool {
        if (!$row) {
            return true;
        }
        return in_array((string) ($row['status'] ?? ''), ['replied', 'delivered', 'closed', 'cancelled'], true);
    };

    if (preg_match('/^referral:(\d+)$/', $dedupe, $m)) {
        return ($ctx['referral'][(int) $m[1]] ?? '') === 'pending';
    }
    if (preg_match('/^(due_today|due_soon):(\d+):(\d{4}-\d{2}-\d{2})$/', $dedupe, $m)) {
        if ($m[3] !== date('Y-m-d') || !$corr || $settled($corr)) {
            return false;
        }
        $due = (string) ($corr['reply_due'] ?? '');
        return $due !== '' && $due >= date('Y-m-d') && $due <= date('Y-m-d', strtotime('+2 days'));
    }
    if ($type === 'incoming_assigned' || preg_match('/^incoming(?:_dept)?:(\d+):(\d+)$/', $dedupe, $m)) {
        if (!$corr || $settled($corr)) {
            return false;
        }
        $dept = isset($m[2]) ? (int) $m[2] : (int) ($n['department_id'] ?? 0);
        return $dept <= 0 || (int) ($corr['department_id'] ?? 0) === $dept;
    }
    if (preg_match('/^corr_cc_(\d+)$/', $dedupe) && !str_starts_with($dedupe, 'corr_cc_ack_')) {
        $cc = $ctx['cc'][$corrId . ':' . $userId] ?? null;
        return $cc !== null && !$cc['seen'] && in_array($cc['status'], ['pending', 'released'], true);
    }
    if (preg_match('/^coverage-granted-(\d+)$/', $dedupe, $m)) {
        return !empty($ctx['coverage'][(int) $m[1]]);
    }
    if (str_starts_with($dedupe, 'coverage-revoked-') || $type === 'corr_cc_ack') {
        return false;
    }
    if ($type === 'outgoing_prep') {
        return $corr && in_array((string) ($corr['outgoing_prep_status'] ?? ''), ['draft', 'pending_signer'], true);
    }
    if (in_array($type, ['outgoing_issue_approval', 'outgoing_issue_review'], true)) {
        return $corr && in_array((string) ($corr['issue_approval_status'] ?? ''), [
            'pending_review', 'returned', 'pending_sg', 'pending_deputy', 'pending_alternate', 'pending_copy', 'pending_desk',
        ], true);
    }
    if ($type === 'close_request') {
        return $corrId > 0 && !empty($ctx['close'][$corrId]);
    }
    if (in_array($type, ['close_request_decided', 'corr_closed', 'corr_reopened', 'circular_issued'], true)) {
        return false;
    }

    return true;
}

/**
 * يفرز الإشعارات المقروءة: ما يُحذف لأن حدثه انتهى، وما يُبقى لأن حدثه قائم.
 *
 * @return array{delete:list<int>,kept:int}
 */
function dms_notifications_finished_read_plan(): array
{
    ensure_notifications_schema();
    $rows = db()->query(
        'SELECT id, user_id, department_id, correspondence_id, type, dedupe_key
         FROM `tblnotifications` WHERE is_read = 1'
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if ($rows === []) {
        return ['delete' => [], 'kept' => 0];
    }

    $corrIds = [];
    $refIds = [];
    $covIds = [];
    foreach ($rows as $row) {
        $corrIds[] = (int) ($row['correspondence_id'] ?? 0);
        $dedupe = (string) ($row['dedupe_key'] ?? '');
        if (preg_match('/^referral:(\d+)$/', $dedupe, $m)) {
            $refIds[] = (int) $m[1];
        }
        if (preg_match('/^coverage-granted-(\d+)$/', $dedupe, $m)) {
            $covIds[] = (int) $m[1];
        }
    }
    $corrIds = array_values(array_unique(array_filter($corrIds)));
    $refIds = array_values(array_unique(array_filter($refIds)));
    $covIds = array_values(array_unique(array_filter($covIds)));

    $ctx = ['corr' => [], 'referral' => [], 'cc' => [], 'coverage' => [], 'close' => []];
    $loadIn = static function (string $sql, array $ids) {
        if ($ids === []) {
            return [];
        }
        $out = [];
        foreach (array_chunk($ids, 500) as $chunk) {
            $marks = implode(',', array_fill(0, count($chunk), '?'));
            $stmt = db()->prepare(sprintf($sql, $marks));
            $stmt->execute($chunk);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                $out[] = $r;
            }
        }
        return $out;
    };

    if (function_exists('ensure_outgoing_prep_schema')) {
        ensure_outgoing_prep_schema();
    }
    if (function_exists('ensure_outgoing_approval_schema')) {
        ensure_outgoing_approval_schema();
    }
    foreach ($loadIn(
        'SELECT id, status, department_id, issue_approval_status, outgoing_prep_status, reply_due
         FROM `tblcorrespondence` WHERE id IN (%s)',
        $corrIds
    ) as $r) {
        $ctx['corr'][(int) $r['id']] = $r;
    }
    foreach ($loadIn('SELECT id, status FROM `tblreferrals` WHERE id IN (%s)', $refIds) as $r) {
        $ctx['referral'][(int) $r['id']] = (string) $r['status'];
    }
    if ($corrIds !== [] && function_exists('ensure_corr_cc_schema')) {
        ensure_corr_cc_schema();
        foreach ($loadIn(
            'SELECT correspondence_id, user_id, status, seen_at FROM `tblcorr_cc` WHERE correspondence_id IN (%s)',
            $corrIds
        ) as $r) {
            $ctx['cc'][(int) $r['correspondence_id'] . ':' . (int) $r['user_id']] = [
                'status' => (string) $r['status'],
                'seen' => trim((string) ($r['seen_at'] ?? '')) !== '',
            ];
        }
    }
    if ($covIds !== [] && function_exists('ensure_user_coverage_schema')) {
        ensure_user_coverage_schema();
    }
    if ($covIds !== [] && function_exists('dms_db_table_exists') && dms_db_table_exists('tbluser_coverage')) {
        foreach ($loadIn(
            'SELECT id, revoked_at, starts_on, ends_on FROM `tbluser_coverage` WHERE id IN (%s)',
            $covIds
        ) as $r) {
            $ctx['coverage'][(int) $r['id']] = function_exists('user_coverage_is_active_row')
                ? user_coverage_is_active_row($r)
                : empty($r['revoked_at']);
        }
    }
    if ($corrIds !== [] && function_exists('ensure_close_requests_schema')) {
        ensure_close_requests_schema();
    }
    if ($corrIds !== [] && function_exists('dms_db_table_exists') && dms_db_table_exists('tblclose_requests')) {
        foreach ($loadIn(
            "SELECT correspondence_id FROM `tblclose_requests` WHERE status = 'pending' AND correspondence_id IN (%s)",
            $corrIds
        ) as $r) {
            $ctx['close'][(int) $r['correspondence_id']] = true;
        }
    }

    $delete = [];
    $kept = 0;
    foreach ($rows as $row) {
        if (dms_notification_event_is_live($row, $ctx)) {
            $kept++;
            continue;
        }
        $delete[] = (int) $row['id'];
    }
    return ['delete' => $delete, 'kept' => $kept];
}

/**
 * حذف الإشعارات المقروءة التي انتهى حدثها. المقروء القائم وغير المقروء يبقيان.
 *
 * @return array{deleted:int,kept:int}
 */
function dms_notifications_purge_finished_read(): array
{
    $plan = dms_notifications_finished_read_plan();
    $deleted = 0;
    $stmt = db()->prepare('DELETE FROM `tblnotifications` WHERE id = ? AND is_read = 1');
    foreach (array_chunk($plan['delete'], 500) as $chunk) {
        foreach ($chunk as $id) {
            $stmt->execute([$id]);
            $deleted += $stmt->rowCount();
        }
    }

    return ['deleted' => $deleted, 'kept' => $plan['kept']];
}
