<?php
/**
 * تصدير خطابات الصادر كملف Word حقيقي (.docx) مع خط Greta Arabic.
 */

require_once __DIR__ . '/briefing_docx.php';
if (!function_exists('letter_body_plain')) {
    require_once __DIR__ . '/letter_templates.php';
}

function letter_docx_sect_pr(): string
{
    return '<w:sectPr>'
        . '<w:pgSz w:w="11906" w:h="16838"/>'
        . '<w:pgMar w:top="851" w:right="1418" w:bottom="851" w:left="1418" w:header="0" w:footer="0" w:gutter="0"/>'
        . '<w:bidi w:val="1"/>'
        . '<w:rtlGutter w:val="0"/>'
        . '<w:docGrid w:linePitch="360"/>'
        . '</w:sectPr>';
}

function letter_docx_page_break(): string
{
    return '<w:p><w:pPr><w:bidi w:val="1"/></w:pPr><w:r><w:br w:type="page"/></w:r></w:p>';
}

function letter_docx_one_letter(array $corr, string $font, ?array $logo): string
{
    $lang = correspondence_letter_channel_lang($corr);
    $isEn = $lang === 'en';
    $align = $isEn ? 'left' : 'right';
    $names = function_exists('org_visible_names') ? org_visible_names('print') : [];
    $xml = '';

    if ($logo) {
        $xml .= briefing_docx_p(briefing_docx_logo_drawing($logo), ['align' => 'center', 'after' => 80]);
    }
    if (!empty($names['primary'])) {
        $xml .= briefing_docx_p(briefing_docx_run((string) $names['primary'], $font, ['bold' => true, 'size' => 13]), [
            'align' => 'center',
            'after' => 40,
        ]);
    }
    if (!empty($names['secondary'])) {
        $xml .= briefing_docx_p(briefing_docx_run((string) $names['secondary'], $font, ['size' => 10.5]), [
            'align' => 'center',
            'after' => 80,
        ]);
    }

    $xml .= briefing_docx_p(
        briefing_docx_run(__lang('corr.number', $lang) . ': ', $font, ['bold' => true, 'size' => 11])
        . briefing_docx_run(format_ref_number($corr['ref_number'] ?? ''), $font, ['bold' => true, 'size' => 11, 'color' => '3B4F6E']),
        ['align' => $align, 'after' => 40]
    );
    $dateLabel = __lang('corr.date_out', $lang);
    $dateValue = !empty($corr['corr_date']) ? format_date($corr['corr_date']) : '';
    if ($dateValue !== '') {
        $xml .= briefing_docx_p(
            briefing_docx_run($dateLabel . ': ', $font, ['bold' => true, 'size' => 11])
            . briefing_docx_run($dateValue, $font, ['size' => 11]),
            ['align' => $align, 'after' => 160]
        );
    }

    $bodyText = letter_body_plain(correspondence_letter_text($corr, $lang), $corr);
    $bodyText = trim($bodyText);
    if ($bodyText !== '') {
        $paras = preg_split("/\r\n|\n|\r/", $bodyText) ?: [];
        foreach ($paras as $para) {
            $xml .= briefing_docx_p(briefing_docx_run($para, $font, ['size' => 12.5]), [
                'align' => $isEn ? 'left' : 'both',
                'after' => 140,
            ]);
        }
    }

    return $xml;
}

function letter_docx_document_body(array $items, string $font, ?array $logo): string
{
    $xml = '';
    $last = count($items) - 1;
    foreach ($items as $i => $corr) {
        $xml .= letter_docx_one_letter($corr, $font, $logo);
        if ($i !== $last) {
            $xml .= letter_docx_page_break();
        }
    }
    $xml .= letter_docx_sect_pr();
    return $xml;
}

function letter_docx_build(array $items): string
{
    $files = briefing_docx_font_files();
    $font = briefing_docx_font_name();
    if ($files !== []) {
        $font = briefing_docx_otf_family($files[0]['path']);
    }
    $logo = briefing_docx_logo_bytes();
    $body = letter_docx_document_body($items, $font, $logo);
    return briefing_docx_package($body, $font, $logo, $files);
}

/**
 * HTML خطاب رسمي للمرفق: مطابق لورقة طباعة الصادر
 * (شعار، ملصق رقم الصادر، نص الخطاب، تأكيد الحضور وQR، تذييل التواصل).
 */
function letter_email_official_print_document(array $corr): string
{
    if (!function_exists('letter_email_inline_local_images')) {
        require_once __DIR__ . '/letter_email_snapshot.php';
    }
    $lang = function_exists('correspondence_letter_channel_lang') ? correspondence_letter_channel_lang($corr) : 'ar';
    $lang = $lang === 'en' ? 'en' : 'ar';
    $isEn = $lang === 'en';
    $dir = $isEn ? 'ltr' : 'rtl';
    $body = trim(function_exists('correspondence_letter_text') ? correspondence_letter_text($corr, $lang) : '');
    if ($body === '') {
        return '';
    }
    $fontCode = function_exists('letter_body_font_code') ? letter_body_font_code($body) : 'greta';
    $opt = function_exists('letter_font_option') ? letter_font_option($fontCode) : [];
    $family = trim((string) ($opt['family'] ?? 'GretaArabic')) ?: 'GretaArabic';
    $faces = '';
    $root = dirname(__DIR__) . DIRECTORY_SEPARATOR;
    foreach ($opt['files'] ?? [] as $file) {
        if (!is_array($file)) {
            continue;
        }
        $rel = ltrim(str_replace('\\', '/', (string) ($file['rel'] ?? '')), '/');
        if ($rel === '' || str_contains($rel, '..')) {
            continue;
        }
        $disk = $root . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (!is_file($disk)) {
            continue;
        }
        $bin = @file_get_contents($disk);
        if ($bin === false || $bin === '') {
            continue;
        }
        $fmt = str_ends_with(strtolower($rel), '.otf') ? 'opentype' : 'truetype';
        $mime = $fmt === 'opentype' ? 'font/otf' : 'font/ttf';
        $weight = (int) ($file['weight'] ?? 400);
        $faces .= "@font-face{font-family:'" . $family . "';src:url('data:" . $mime . ";base64,"
            . base64_encode($bin) . "') format('" . $fmt . "');font-weight:" . $weight . ";font-style:normal;}";
    }
    $fontClass = function_exists('letter_font_css_class') ? letter_font_css_class($fontCode) : '';
    $corrId = (int) ($corr['id'] ?? 0);
    $qrId = 'letter-thermal-qr-' . $corrId;
    $thermalRootId = 'letter-thermal-' . $corrId;
    // المعيار الموحّد لتوليد QR؛ العرض يبقى صغيراً عبر CSS
    $thermalQrUri = function_exists('dms_corr_qr_data_uri') ? dms_corr_qr_data_uri($corr, 0, false, true) : '';
    $thermalQrUrl = function_exists('dms_corr_qr_content') ? dms_corr_qr_content($corr, false, true) : '';
    ob_start();
    echo '<div class="print-sheet letter-sheet' . ($isEn ? ' is-ltr' : '') . '" lang="' . e($lang) . '" dir="' . e($dir) . '">';
    echo '<div class="letter-thermal-host print-thermal-page is-50x25" title="' . e(__('thermal.title')) . '" dir="ltr">';
    if (function_exists('render_thermal_sticker')) {
        render_thermal_sticker($corr, 0, $thermalRootId, $qrId, true);
    }
    echo '</div>';
    echo '<div class="print-header">';
    if (function_exists('render_print_logo')) {
        render_print_logo('print');
    }
    echo '</div>';
    echo '<div class="letter-content ' . e($fontClass) . '">';
    $bodyPrint = function_exists('letter_strip_link_qr_token') ? letter_strip_link_qr_token($body) : $body;
    if ($bodyPrint !== '') {
        echo '<div class="letter-body">' . render_letter_body_html($bodyPrint, false, $corr) . '</div>';
    }
    if (function_exists('render_letter_end_actions')) {
        render_letter_end_actions($corr, $lang);
    } elseif (function_exists('render_letter_rsvp_notice')) {
        render_letter_rsvp_notice($corr, $lang);
    }
    echo '</div>';
    if (function_exists('render_print_page_footer')) {
        render_print_page_footer($corr, ['show_users' => false]);
    }
    echo '</div>';
    $inner = (string) ob_get_clean();
    if ($thermalQrUri !== '' && $qrId !== '') {
        $thermalImg = function_exists('dms_qr_img_tag')
            ? dms_qr_img_tag($thermalQrUri)
            : ('<img src="' . htmlspecialchars($thermalQrUri, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                . '" alt="" width="640" height="640" decoding="sync">');
        if ($thermalQrUrl !== '') {
            $thermalImg = '<a class="letter-thermal-qr-link" href="'
                . htmlspecialchars($thermalQrUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                . '" target="_blank" rel="noopener noreferrer">' . $thermalImg . '</a>';
        }
        // استبدال صورة الملصق إن وُجدت، أو ملء الحاوية الفارغة
        $replaced = preg_replace(
            '/(<div\b[^>]*\bid="' . preg_quote($qrId, '/') . '"[^>]*>)\s*(?:<a\b[^>]*>)?\s*<img\b[^>]*>\s*(?:<\/a>)?\s*(<\/div>)/i',
            '$1' . $thermalImg . '$2',
            $inner,
            1
        );
        if (is_string($replaced) && $replaced !== $inner) {
            $inner = $replaced;
        } else {
            $inner = preg_replace(
                '/(<div\b[^>]*\bid="' . preg_quote($qrId, '/') . '"[^>]*>)(\s*)(<\/div>)/i',
                '$1' . $thermalImg . '$3',
                $inner,
                1
            ) ?? $inner;
        }
    }
    $inner = letter_email_inline_local_images($inner);
    $css = $faces
        . '@page{size:A4 portrait;margin:0;}'
        . 'html,body{margin:0;padding:0;background:#fff;color:#000;width:210mm;min-height:297mm;}'
        . 'body{font-family:\'' . $family . '\',Tahoma,Arial,sans-serif;font-size:12pt;line-height:1.5;direction:' . $dir . ';-webkit-print-color-adjust:exact;print-color-adjust:exact;}'
        . '.print-sheet{width:210mm;max-width:210mm;min-height:297mm;height:297mm;margin:0;padding:14mm 2.5cm 10mm 2.5cm;box-sizing:border-box;background:#fff;position:relative;overflow:visible;display:flex;flex-direction:column;}'
        . '.print-header{position:relative;padding-bottom:2mm;margin-bottom:4mm;min-height:32mm;border:0;flex:0 0 auto;}'
        . '.print-brand-top{text-align:center;padding:0 32mm;margin:0;border:0;}'
        . '.print-org-logo{max-height:118px;max-width:260px;object-fit:contain;}'
        . '.print-system-icon{width:52px;height:52px;object-fit:contain;}'
        . '.print-brand-names{margin-top:1.5mm;}'
        . '.print-brand-primary{font-size:11pt;font-weight:700;color:#3B4F6E;line-height:1.25;}'
        . '.print-brand-secondary{font-size:9pt;color:#5a6577;line-height:1.25;}'
        . '.letter-content{display:block;min-width:0;color:#000;flex:1 1 auto;}'
        . '.letter-body,.letter-body *{font-size:12pt;line-height:1.5;color:#000 !important;}'
        . '.letter-align-center{text-align:center;}.letter-align-right{text-align:right;}'
        . '.letter-align-left{text-align:left;}.letter-align-justify{text-align:justify;text-align-last:start;}'
        . '.letter-fmt-b{font-weight:700;}.letter-fmt-u{text-decoration:underline;}'
        . '.letter-fmt-big{font-size:14.5pt;font-weight:600;}'
        . '.letter-ul{margin:.25em 0 .7em;padding-inline-start:1.35em;}'
        . '.letter-signature{margin:1.1em 0 .4em;display:inline-block;max-width:12rem;}'
        . '.letter-signature-img{max-height:48px;max-width:140px;height:auto;width:auto;display:block;margin:0 0 6px;}'
        . '.letter-signature-name{font-weight:700;line-height:1.35;}'
        . '.letter-signature-title{font-size:.92em;color:#445;line-height:1.35;}'
        . '.letter-end-actions{margin-top:10mm;display:flex;flex-direction:column;align-items:stretch;gap:7mm;page-break-inside:avoid;}'
        . '.letter-qr-row{display:flex;flex-direction:row;flex-wrap:wrap;justify-content:center;align-items:center;gap:.75rem 1.25rem;padding:0;border:0;background:transparent;page-break-inside:avoid;}'
        . '.letter-qr-text,.letter-link-qr-label,.letter-rsvp-note{flex:1 1 9cm;max-width:14cm;margin:0;color:#000 !important;font-family:inherit;font-size:inherit;font-weight:inherit;line-height:inherit;text-align:start;text-decoration:none;letter-spacing:0;word-spacing:normal;}'
        . '.letter-qr-code,.letter-rsvp-qr,.letter-link-qr-code{display:block;width:2.2cm;height:2.2cm;min-width:2.2cm;min-height:2.2cm;padding:0;box-sizing:border-box;flex-shrink:0;background:transparent;text-decoration:none;border:0;outline:0;}'
        . '.letter-rsvp-qr-link{display:block;width:100%;height:100%;text-decoration:none;color:inherit;border:0;outline:0;}'
        . '.letter-qr-code img,.letter-rsvp-qr img,.letter-link-qr-code img{display:block;width:100% !important;height:100% !important;max-width:100% !important;max-height:100% !important;object-fit:contain;image-rendering:pixelated;image-rendering:crisp-edges;}'
        . '.letter-link-qr,.letter-rsvp-block{margin:0;}'
        . '.letter-sheet.is-ltr,.letter-sheet.is-ltr .letter-body{direction:ltr;text-align:left;}'
        . '.letter-thermal-qr-link{display:block;width:100%;height:100%;text-decoration:none;border:0;outline:0;}'
        . '.print-page-footer{margin-top:auto;flex-shrink:0;text-align:center;position:absolute;left:0;right:0;bottom:4mm;padding:0 2.5cm;box-sizing:border-box;}'
        . '.print-page-footer .print-contact{margin-top:0;padding-top:0;padding-bottom:0;border-top:none;text-align:center;color:#1f2a37;}'
        . '.print-contact-line{display:flex;flex-wrap:wrap;justify-content:center;align-items:center;gap:1.2mm 2mm;font-size:9.5pt;color:#1f2a37;line-height:1.35;}'
        . '.print-contact-address,.print-contact-item,.print-contact-sep{color:#1f2a37;}'
        . '.print-contact-sep{padding:0 .35mm;opacity:.75;}'
        . '.print-contact-item i{display:none;}'
        . '.letter-thermal-host{position:absolute;top:17mm;left:1.4cm;right:auto;display:block;width:50mm;height:25mm;min-width:50mm;min-height:25mm;margin:0;padding:0;background:transparent;overflow:visible;z-index:6;direction:ltr;}'
        . '.print-thermal-sticker{box-sizing:border-box;width:50mm !important;height:25mm !important;min-width:50mm !important;min-height:25mm !important;max-width:50mm !important;max-height:25mm !important;margin:0;padding:.15mm 1.4mm .8mm;background:#fff;border:.2mm solid #9aa3ad;border-radius:1.2mm;overflow:hidden;display:flex;flex-direction:column;align-items:stretch;justify-content:flex-start;direction:rtl;gap:.35mm;color:#000;font-family:\'' . $family . '\',Tahoma,Arial,sans-serif;-webkit-print-color-adjust:exact;print-color-adjust:exact;}'
        . '.print-thermal-logo{flex:0 0 auto;display:flex;align-items:center;justify-content:center;width:100%;max-height:12.2mm;margin-top:-.3mm;line-height:0;}'
        . '.print-thermal-logo-img{display:block;width:auto;height:auto;max-width:45mm;max-height:12.2mm;object-fit:contain;filter:grayscale(1) brightness(0) contrast(1.35);-webkit-print-color-adjust:exact;print-color-adjust:exact;}'
        . '.print-thermal-body{flex:0 0 auto;min-height:12mm;display:flex;flex-direction:row;align-items:flex-start;justify-content:flex-start;direction:rtl;gap:1.2mm;overflow:visible;padding-inline:2mm;padding-top:0;box-sizing:border-box;}'
        . '.print-thermal-text{flex:1 1 auto;min-width:0;display:flex;flex-direction:column;align-items:flex-start;justify-content:flex-start;gap:.55mm;}'
        /* مقاسات مطابقة للورقي (50×25 مع شعار وQR) */
        . '.print-thermal-ref{display:flex;flex-direction:row;flex-wrap:nowrap;align-items:baseline;justify-content:flex-start;gap:.2em;direction:rtl;line-height:1.12;white-space:nowrap;font-size:10pt;font-weight:700;width:100%;box-sizing:border-box;}'
        . '.print-thermal-ref-label{flex:0 0 auto;font-size:.82em;font-weight:700;white-space:nowrap;}'
        . '.print-thermal-ref-num{flex:0 1 auto;min-width:0;font-size:1.32em;font-weight:700;letter-spacing:.01em;direction:ltr;unicode-bidi:isolate;white-space:nowrap;}'
        . '.print-thermal-meta{display:flex;flex-direction:row;flex-wrap:nowrap;align-items:center;justify-content:flex-start;gap:.2em;direction:rtl;font-size:10pt;font-weight:600;line-height:1.1;white-space:nowrap;width:100%;}'
        . '.print-thermal-date{display:inline-flex;align-items:baseline;gap:0;direction:rtl;letter-spacing:-.01em;white-space:nowrap;}'
        . '.print-thermal-date-num{display:inline-block;direction:ltr;unicode-bidi:isolate;font-size:inherit;}'
        . '.print-thermal-date-slash{display:inline-block;margin:0 .04em;font-size:inherit;}'
        . '.print-label-mark{font-size:inherit;}'
        . '.print-thermal-date-sep{display:inline-block;padding:0 .2em;font-weight:600;}'
        . '.print-thermal-qr{width:13.5mm;height:13.5mm;min-width:13.5mm;min-height:13.5mm;flex:0 0 auto;align-self:flex-start;margin-top:-.8mm;}'
        . '.print-thermal-qr img{display:block;width:100%;height:100%;object-fit:contain;image-rendering:pixelated;image-rendering:crisp-edges;}';

    return '<!DOCTYPE html><html lang="' . e($lang) . '" dir="' . e($dir) . '"><head><meta charset="UTF-8">'
        . '<style>' . $css . '</style></head>'
        . '<body class="letter-batch-print">' . $inner . '</body></html>';
}

/** ثنائي PDF للخطاب الرسمي، أو سلسلة فارغة إن تعذّر. */
function letter_email_official_pdf(array $corr): string
{
    if (!function_exists('letter_email_browser_bin')) {
        require_once __DIR__ . '/letter_email_snapshot.php';
    }
    $browser = letter_email_browser_bin();
    if ($browser === '') {
        return '';
    }
    $html = letter_email_official_print_document($corr);
    if ($html === '') {
        return '';
    }
    $dir = sys_get_temp_dir();
    $id = bin2hex(random_bytes(6));
    $htmlPath = $dir . DIRECTORY_SEPARATOR . 'dms_letter_pdf_' . $id . '.html';
    $pdfPath = $dir . DIRECTORY_SEPARATOR . 'dms_letter_pdf_' . $id . '.pdf';
    if (@file_put_contents($htmlPath, $html) === false) {
        return '';
    }
    $ok = letter_email_browser_print_pdf($browser, $htmlPath, $pdfPath);
    @unlink($htmlPath);
    if (!$ok || !is_file($pdfPath)) {
        @unlink($pdfPath);

        return '';
    }
    $bin = (string) @file_get_contents($pdfPath);
    @unlink($pdfPath);
    if ($bin === '' || strlen($bin) < 1200 || !str_starts_with($bin, '%PDF')) {
        return '';
    }

    return $bin;
}

/** احتياط Word: نص الخطاب وتأكيد الحضور فقط (بدون شعار/رقم، كالورق الرسمي). */
function letter_email_official_docx(array $corr): string
{
    $files = briefing_docx_font_files();
    $font = briefing_docx_font_name();
    if ($files !== []) {
        $font = briefing_docx_otf_family($files[0]['path']);
    }
    $lang = function_exists('correspondence_letter_channel_lang') ? correspondence_letter_channel_lang($corr) : 'ar';
    $isEn = $lang === 'en';
    $align = $isEn ? 'left' : 'right';
    $xml = '';
    // مسافة علوية مكان ترويسة الورق الرسمي
    $xml .= briefing_docx_p(briefing_docx_run(' ', $font, ['size' => 12]), ['align' => 'center', 'after' => 600]);
    $bodyText = trim(letter_body_plain(correspondence_letter_text($corr, $lang), $corr));
    if ($bodyText !== '') {
        $paras = preg_split("/\r\n|\n|\r/", $bodyText) ?: [];
        foreach ($paras as $para) {
            $xml .= briefing_docx_p(briefing_docx_run($para, $font, ['size' => 12.5]), [
                'align' => $isEn ? 'left' : 'both',
                'after' => 140,
            ]);
        }
    }
    if (function_exists('correspondence_rsvp_required') && correspondence_rsvp_required($corr)) {
        $ask = function_exists('__lang') ? __lang('rsvp.print_ask', $lang) : __('rsvp.print_ask');
        if ($ask !== '') {
            $xml .= briefing_docx_p(briefing_docx_run($ask, $font, ['bold' => true, 'size' => 12]), [
                'align' => $align,
                'after' => 120,
            ]);
        }
    }
    $xml .= letter_docx_sect_pr();

    return briefing_docx_package($xml, $font, null, $files);
}

/** @return array{path:string,name:string,mime:string}|null */
function letter_email_official_attachment(array $corr): ?array
{
    $lang = function_exists('correspondence_letter_channel_lang') ? correspondence_letter_channel_lang($corr) : 'ar';
    $body = trim(function_exists('correspondence_letter_text') ? correspondence_letter_text($corr, $lang) : '');
    if ($body === '') {
        return null;
    }
    $bin = letter_email_official_pdf($corr);
    $ext = 'pdf';
    $mime = 'application/pdf';
    if ($bin === '') {
        // احتياط إن لم يتوفر متصفح للطباعة إلى PDF
        $bin = letter_email_official_docx($corr);
        $ext = 'docx';
        $mime = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    }
    if ($bin === '') {
        return null;
    }
    $tmp = tempnam(sys_get_temp_dir(), 'dms-letter-');
    if ($tmp === false) {
        return null;
    }
    if (file_put_contents($tmp, $bin) === false) {
        @unlink($tmp);
        return null;
    }
    $ref = function_exists('format_ref_number') ? format_ref_number($corr['ref_number'] ?? '') : (string) ($corr['ref_number'] ?? '');
    $safe = trim((string) preg_replace('/[^\p{L}\p{N}\-_]+/u', '-', $ref), '-');
    if ($safe === '') {
        $safe = 'letter';
    }

    return [
        'path' => $tmp,
        'name' => $safe . '.' . $ext,
        'mime' => $mime,
    ];
}

function letter_docx_send(array $items, array $batch): void
{
    $bin = letter_docx_build($items);
    $id = (int) ($batch['id'] ?? 0);
    $ascii = 'outgoing-batch-' . $id . '-' . date('Ymd') . '.docx';
    $utfName = 'خطابات-صادر-' . $id . '-' . date('Ymd') . '.docx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($utfName));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . (string) strlen($bin));
    echo $bin;
    exit;
}
