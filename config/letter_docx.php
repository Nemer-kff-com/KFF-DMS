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
    $parts = print_direction_parts($corr);
    $to = $parts['to'] ?? [];
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

    if (print_direction_part_has_content($to)) {
        $xml .= briefing_docx_p(
            briefing_docx_run(__lang('print.directed_to', $lang), $font, ['bold' => true, 'size' => 11, 'color' => '3B4F6E']),
            ['align' => $align, 'after' => 60]
        );
        $prefix = trim((string) ($to['prefix'] ?? ''));
        $person = trim((string) ($to['person'] ?? ''));
        $honorific = trim((string) ($to['honorific'] ?? ''));
        $job = trim((string) ($to['job'] ?? ''));
        $org = trim((string) ($to['org'] ?? ''));
        if ($prefix !== '') {
            $xml .= briefing_docx_p(briefing_docx_run($prefix, $font, ['size' => 10]), ['align' => $align, 'after' => 20]);
        }
        if ($person !== '') {
            $xml .= briefing_docx_p(briefing_docx_run($person, $font, ['bold' => true, 'size' => 13]), ['align' => $align, 'after' => 20]);
        }
        if ($honorific !== '') {
            $xml .= briefing_docx_p(briefing_docx_run($honorific, $font, ['size' => 10]), ['align' => $align, 'after' => 40]);
        }
        if ($job !== '') {
            $xml .= briefing_docx_p(briefing_docx_run($job, $font, ['size' => 10.5]), ['align' => $align, 'after' => 20]);
        }
        if ($org !== '') {
            $xml .= briefing_docx_p(briefing_docx_run($org, $font, ['bold' => true, 'size' => 12.5]), ['align' => $align, 'after' => 160]);
        }
    }

    $subject = trim((string) ($corr['subject'] ?? ''));
    if ($subject !== '') {
        $xml .= briefing_docx_p(briefing_docx_run($subject, $font, ['bold' => true, 'size' => 14, 'color' => '3B4F6E']), [
            'align' => $align,
            'after' => 200,
        ]);
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
