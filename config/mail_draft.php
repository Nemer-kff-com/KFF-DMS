<?php
/**
 * مسودة بريد (.eml) لنموذج التسليم ومرفقات المعاملة — تُفتح في برنامج بريد المستخدم (Outlook) وهو من يرسلها.
 * النظام لا يرسل شيئاً؛ يجهّز الملف فقط ويسجّل العملية.
 */

const MAIL_DRAFT_MAX_BYTES = 50 * 1024 * 1024;

/** العمود القديم can_mail_draft يبقى لنقل قيمه إلى صلاحية mail.draft (انظر dms_user_permissions_migrate_mail_draft) */
function ensure_mail_draft_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        if (!in_array('can_mail_draft', dms_db_column_names('tblusers'), true)) {
            db()->exec("ALTER TABLE `tblusers` ADD COLUMN can_mail_draft TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'زر إرسال بالبريد' AFTER is_active");
            // مدير النظام كان يستخدم الزر في المرحلة التجريبية
            db()->exec("UPDATE `tblusers` SET can_mail_draft = 1 WHERE role = 'admin'");
        }
    } catch (Throwable $e) {
        // قاعدة غير جاهزة
    }
}

function mail_draft_user_allowed(array $user): bool
{
    ensure_mail_draft_schema();
    return user_can('mail.draft', $user);
}

/** سبب منع التجهيز لمعاملة (نص فارغ = مسموح) */
function mail_draft_block_reason(array $corr): string
{
    if (correspondence_is_cancelled($corr)) {
        return __('mail_draft.blocked_cancelled');
    }
    if (($corr['type'] ?? '') === 'outgoing') {
        if (function_exists('outgoing_can_issue') && !outgoing_can_issue($corr)) {
            return __('issue_appr.print_blocked_short');
        }
        return '';
    }
    if (function_exists('corr_approval_is_held') && corr_approval_is_held($corr)) {
        return corr_held_text($corr, 'print_blocked_short');
    }
    return '';
}

/** @return list<array{name:string,path:string}> */
function mail_draft_files(array $corr): array
{
    $files = [];
    foreach (get_documents((int) $corr['id']) as $doc) {
        $rel = (string) ($doc['file_path'] ?? '');
        $path = ($rel !== '' && dms_is_safe_upload_path($rel)) ? dms_storage_disk_path($rel) : '';
        if ($path !== '' && is_file($path)) {
            $files[] = ['name' => (string) $doc['file_name'], 'path' => $path];
        }
    }
    $slip = (string) ($corr['receipt_file_path'] ?? '');
    if ($slip !== '' && dms_is_safe_upload_path($slip)) {
        $path = dms_storage_disk_path($slip);
        if ($path !== '' && is_file($path)) {
            $files[] = ['name' => (string) ($corr['receipt_file_name'] ?: 'receipt'), 'path' => $path];
        }
    }
    return $files;
}

function mail_draft_files_bytes(array $files): int
{
    $total = 0;
    foreach ($files as $f) {
        $total += (int) @filesize($f['path']);
    }
    return $total;
}

/** طرف «موجهة إلى/من» كسطور HTML */
function mail_draft_party_html(array $part): string
{
    $person = trim((string) ($part['person'] ?? ''));
    $name = trim(trim((string) ($part['prefix'] ?? '')) . ' ' . $person);
    $honorific = trim((string) ($part['honorific'] ?? ''));
    $lines = [];
    if ($name !== '' || $honorific !== '') {
        $lines[] = '<b>' . e(trim($name . ' ' . $honorific)) . '</b>';
    }
    foreach (['job', 'org'] as $key) {
        $v = trim((string) ($part[$key] ?? ''));
        if ($v !== '' && $v !== $person) {
            $lines[] = e($v);
        }
    }
    foreach ($part['titles'] ?? [] as $title) {
        if (trim((string) $title) !== '') {
            $lines[] = e((string) $title);
        }
    }
    return implode('<br>', $lines);
}

/** نص رسالة الاستلام على سطرين: ما قبل السطر الأخير ثم السطر الأخير */
function mail_draft_receipt_message_html(): string
{
    $lines = array_values(array_filter(
        array_map('trim', preg_split('/\R/u', print_receipt_message()) ?: []),
        'strlen'
    ));
    if (count($lines) > 2) {
        $last = array_pop($lines);
        $lines = [implode(' ', $lines), $last];
    }
    return implode('<br>', array_map('e', $lines));
}

/** جسم الرسالة: نموذج التسليم بألوان نموذج الطباعة وخط Greta ثم Arial */
function mail_draft_body_html(array $corr, array $files, string $dirOrder): string
{
    $navy = '#3B4F6E';
    $ink = '#1f2a37';
    $thBg = '#d6dde8';
    $dirBg = '#c7d2e2';
    $font = "font-family:'GretaArabic','Greta Arabic',Arial,sans-serif;";
    $cell = $font . 'border:1pt solid ' . $navy . ';padding:4px 8px;text-align:right;font-size:11pt;color:' . $ink . ';vertical-align:middle;';
    $th = static fn(string $bg): string => 'style="' . $cell . 'background:' . $bg . ';font-weight:bold;white-space:nowrap;width:110px"';
    $td = 'style="' . $cell . 'background:#ffffff"';
    $tdBold = 'style="' . $cell . 'background:#ffffff;font-weight:bold"';
    $table = 'dir="rtl" cellpadding="0" cellspacing="0" style="border-collapse:collapse;width:520px;max-width:100%;margin:0 0 6px 0;border:1.5pt solid ' . $navy . '"';
    $render = static function (array $rows) use ($table, $th): string {
        if (!$rows) {
            return '';
        }
        $out = '<table ' . $table . '>';
        foreach ($rows as [$label, $value, $bg, $tdStyle]) {
            $out .= '<tr><th ' . $th($bg) . '>' . e($label) . '</th><td ' . $tdStyle . '>' . $value . '</td></tr>';
        }
        return $out . '</table>';
    };

    $fields = [[__('mail_draft.ref'), '<span dir="ltr">' . e((string) $corr['ref_number']) . '</span>', $thBg, $td]];
    if (!empty($corr['corr_date'])) {
        $fields[] = [__('mail_draft.date'), '<span dir="ltr">' . e(date('Y/m/d', strtotime((string) $corr['corr_date']))) . '</span> ' . e(__('mail_draft.greg_mark')), $thBg, $td];
    }
    $fields[] = [__('corr.subject'), e((string) $corr['subject']), $thBg, $tdBold];

    $parts = print_direction_parts($corr);
    $labels = ['to' => __('print.directed_to'), 'from' => __('print.directed_from')];
    $dirRows = [];
    foreach ($dirOrder === 'from' ? ['from', 'to'] : ['to', 'from'] as $key) {
        if (print_direction_part_has_content($parts[$key] ?? [])) {
            $dirRows[] = [$labels[$key], mail_draft_party_html($parts[$key]), $dirBg, $td];
        }
    }

    $flags = [];
    $conf = strtolower(trim((string) ($corr['confidentiality'] ?? '')));
    if ($conf !== '' && $conf !== 'normal') {
        $flags[] = confidentiality_label($conf);
    }
    $prio = strtolower(trim((string) ($corr['priority'] ?? '')));
    if ($prio !== '' && $prio !== 'normal') {
        $flags[] = priority_label($prio);
    }

    $extra = [];
    $enclosures = correspondence_enclosures((int) $corr['id']);
    if ($enclosures) {
        $extra[] = [__('corr.enclosures'), e(implode('، ', array_map('correspondence_enclosure_label', $enclosures))), $thBg, $td];
    }
    if ($files) {
        $extra[] = [__('mail_draft.files'), e(implode('، ', array_column($files, 'name'))), $thBg, $td];
    }

    $html = '<div dir="rtl" style="' . $font . 'font-size:11pt;color:' . $ink . '">';
    $html .= '<p style="' . $font . 'font-size:11pt;color:' . $ink . ';margin:0 0 8px 0">' . e(__('mail_draft.greeting')) . '</p>';
    $html .= '<p style="' . $font . 'font-size:13pt;font-weight:bold;color:' . $navy . ';margin:0 0 6px 0">'
        . e(__('mail_draft.form_title')) . ' — ' . e(type_label((string) $corr['type'])) . '</p>';
    $html .= $render($fields) . $render($dirRows);
    if ($flags) {
        $html .= '<p style="' . $font . 'font-size:12pt;font-weight:bold;color:#EE0000;margin:0 0 6px 0">' . e(implode('   ', $flags)) . '</p>';
    }
    $html .= $render($extra);

    $html .= '<div style="width:520px;max-width:100%;border-top:0.75pt dashed ' . $navy . ';margin:8px 0 0 0;padding-top:6px">';
    $html .= '<table ' . $table . '>';
    $html .= '<tr><td colspan="4" style="' . $cell . 'background:#ffffff;line-height:1.6">' . mail_draft_receipt_message_html() . '</td></tr><tr>';
    foreach (['print.receiver_name', 'print.receive_date', 'print.receiver_phone', 'print.signature'] as $key) {
        $html .= '<th style="' . $cell . 'background:' . $thBg . ';font-weight:bold;text-align:center;font-size:10.5pt;width:25%">' . e(__($key)) . '</th>';
    }
    $html .= '</tr><tr>';
    $sign = 'style="' . $cell . 'background:#ffffff;height:40px;text-align:center"';
    if (correspondence_has_receipt($corr)) {
        $html .= '<td ' . $sign . '>' . e((string) $corr['receipt_name']) . '</td>';
        $html .= '<td ' . $sign . '><span dir="ltr">' . e(date('Y/m/d', strtotime((string) $corr['receipt_date']))) . '</span></td>';
        $html .= '<td ' . $sign . '><span dir="ltr">' . e((string) ($corr['receipt_phone'] ?? '')) . '</span></td>';
        $html .= '<td ' . $sign . '>' . (!empty($corr['receipt_file_path'])
            ? '<span style="font-size:10pt;color:#6c757d">' . e(__('receipt.slip_attached')) . '</span>'
            : '&nbsp;') . '</td>';
    } else {
        $html .= str_repeat('<td ' . $sign . '>&nbsp;</td>', 4);
    }
    $html .= '</tr></table></div></div>';
    return $html;
}

/** أجزاء الرقم كما يُعرض (بادئة/سنة/تسلسل) — «/» ممنوعة في أسماء الملفات */
function mail_draft_ref_parts(array $corr): array
{
    $ref = format_ref_number($corr['ref_number'] ?? '');
    $parts = array_values(array_filter(
        array_map(static fn($p) => preg_replace('/[\\\\\/:*?"<>|\s]+/u', '', $p), explode('/', $ref)),
        'strlen'
    ));
    return $parts ?: [(string) $corr['id']];
}

/** اسم الملف بلغة الواجهة: «صادر0032-48.eml» / «Outgoing-48-0032.eml» */
function mail_draft_filename(array $corr): string
{
    $parts = mail_draft_ref_parts($corr);
    $type = type_label((string) ($corr['type'] ?? ''));
    if (dms_lang_code() === 'en') {
        return $type . '-' . implode('-', $parts) . '.eml';
    }
    return $type . implode('-', array_reverse($parts)) . '.eml';
}

/** بديل ASCII لمتصفحات لا تدعم filename* */
function mail_draft_filename_ascii(array $corr): string
{
    $types = ['incoming' => 'Incoming', 'internal' => 'Internal'];
    $parts = array_map(static fn($p) => preg_replace('/[^0-9A-Za-z]+/', '', $p), mail_draft_ref_parts($corr));
    return ($types[(string) ($corr['type'] ?? '')] ?? 'Outgoing') . '-' . implode('-', array_filter($parts, 'strlen')) . '.eml';
}

function mail_draft_mime_word(string $s): string
{
    return '=?UTF-8?B?' . base64_encode($s) . '?=';
}

/**
 * يبني ملف .eml كمسودة غير مرسلة (X-Unsent) — Outlook يفتحه كرسالة جديدة جاهزة
 * @return array{filename:string,content:string}
 */
function mail_draft_build(array $corr, string $dirOrder = 'to'): array
{
    $files = mail_draft_files($corr);
    $ref = (string) ($corr['ref_number'] ?? $corr['id']);
    $subject = type_label((string) $corr['type']) . ' ' . $ref . ' — ' . (string) $corr['subject'];
    $html = '<html><head><meta charset="UTF-8"></head><body dir="rtl">'
        . mail_draft_body_html($corr, $files, $dirOrder === 'from' ? 'from' : 'to')
        . '</body></html>';

    $boundary = '----=_DMS_' . bin2hex(random_bytes(8));
    $eml = "X-Unsent: 1\r\nTo: \r\n";
    $eml .= 'Subject: ' . mail_draft_mime_word($subject) . "\r\n";
    $eml .= "MIME-Version: 1.0\r\nContent-Type: multipart/mixed; boundary=\"$boundary\"\r\n\r\n";
    $eml .= "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
    $eml .= chunk_split(base64_encode($html)) . "\r\n";
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    foreach ($files as $f) {
        $mime = $finfo->file($f['path']) ?: 'application/octet-stream';
        $name = mail_draft_mime_word($f['name']);
        $eml .= "--$boundary\r\nContent-Type: $mime; name=\"$name\"\r\n"
            . "Content-Disposition: attachment; filename=\"$name\"\r\nContent-Transfer-Encoding: base64\r\n\r\n";
        $eml .= chunk_split(base64_encode((string) file_get_contents($f['path']))) . "\r\n";
    }
    $eml .= "--$boundary--\r\n";

    return [
        'filename' => mail_draft_filename($corr),
        'content' => $eml,
    ];
}
