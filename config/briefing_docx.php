<?php
/**
 * تصدير ورقة العرض كملف Word حقيقي (.docx) مع خط Greta Arabic مضمّناً.
 * ملف HTML/.doc يجعل وورد يستبدل الخط بـ Times New Roman.
 */

function briefing_docx_font_name(): string
{
    return 'Greta Arabic';
}

function briefing_docx_xml(string $text): string
{
    return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function briefing_docx_guid(): string
{
    $b = random_bytes(16);
    return sprintf(
        '{%02X%02X%02X%02X-%02X%02X-%02X%02X-%02X%02X-%02X%02X%02X%02X%02X%02X}',
        ord($b[0]), ord($b[1]), ord($b[2]), ord($b[3]),
        ord($b[4]), ord($b[5]),
        ord($b[6]), ord($b[7]),
        ord($b[8]), ord($b[9]),
        ord($b[10]), ord($b[11]), ord($b[12]), ord($b[13]), ord($b[14]), ord($b[15])
    );
}

function briefing_docx_obfuscate_font(string $data, string $fontKey): string
{
    $hex = strtoupper(preg_replace('/[^0-9A-F]/', '', $fontKey) ?? '');
    if (strlen($hex) !== 32 || strlen($data) < 32) {
        return $data;
    }
    $key = [];
    for ($i = 0; $i < 16; $i++) {
        $key[15 - $i] = hexdec(substr($hex, $i * 2, 2));
    }
    for ($i = 0; $i < 32; $i++) {
        $data[$i] = chr(ord($data[$i]) ^ $key[$i % 16]);
    }
    return $data;
}

function briefing_docx_otf_family(string $path): string
{
    $data = @file_get_contents($path);
    if ($data === false || strlen($data) < 28) {
        return briefing_docx_font_name();
    }
    $num = unpack('n', substr($data, 4, 2))[1];
    $nameOff = 0;
    $nameLen = 0;
    for ($i = 0; $i < $num; $i++) {
        $rec = substr($data, 12 + $i * 16, 16);
        if (substr($rec, 0, 4) !== 'name') {
            continue;
        }
        $nameOff = unpack('N', substr($rec, 8, 4))[1];
        $nameLen = unpack('N', substr($rec, 12, 4))[1];
        break;
    }
    if ($nameOff <= 0 || $nameLen <= 0) {
        return briefing_docx_font_name();
    }
    $tbl = substr($data, $nameOff, $nameLen);
    $count = unpack('n', substr($tbl, 2, 2))[1];
    $strOff = unpack('n', substr($tbl, 4, 2))[1];
    $found = [];
    for ($i = 0; $i < $count; $i++) {
        $rec = substr($tbl, 6 + $i * 12, 12);
        $plat = unpack('n', substr($rec, 0, 2))[1];
        $nid = unpack('n', substr($rec, 6, 2))[1];
        $len = unpack('n', substr($rec, 8, 2))[1];
        $off = unpack('n', substr($rec, 10, 2))[1];
        if ($plat !== 3 || !in_array($nid, [16, 1], true)) {
            continue;
        }
        $raw = substr($tbl, $strOff + $off, $len);
        $txt = @mb_convert_encoding($raw, 'UTF-8', 'UTF-16BE');
        $txt = trim((string) $txt);
        if ($txt !== '') {
            $found[$nid] = $txt;
        }
    }
    if (!empty($found[16])) {
        return $found[16];
    }
    if (!empty($found[1])) {
        return $found[1];
    }
    return briefing_docx_font_name();
}

function briefing_docx_font_files(): array
{
    $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'fonts';
    $out = [];
    $regular = $dir . DIRECTORY_SEPARATOR . 'GretaArabic-Regular.otf';
    $bold = $dir . DIRECTORY_SEPARATOR . 'GretaArabic-Bold.otf';
    if (is_file($regular)) {
        $out[] = ['path' => $regular, 'tag' => 'embedRegular', 'part' => 'font1.odttf'];
    }
    if (is_file($bold)) {
        $out[] = ['path' => $bold, 'tag' => 'embedBold', 'part' => 'font2.odttf'];
    }
    return $out;
}

function briefing_docx_rfonts(string $font): string
{
    $f = briefing_docx_xml($font);
    return '<w:rFonts w:ascii="' . $f . '" w:hAnsi="' . $f . '" w:cs="' . $f . '" w:eastAsia="' . $f . '" w:hint="cs"/>';
}

function briefing_docx_cs_rtl(): string
{
    return '<w:rtl/><w:cs/><w:lang w:val="ar-SA" w:bidi="ar-SA"/>';
}

function briefing_docx_rpr(string $font, array $o = []): string
{
    $sz = (string) (int) round((float) ($o['size'] ?? 12.5) * 2);
    $color = preg_replace('/[^0-9A-Fa-f]/', '', (string) ($o['color'] ?? '1F2A37'));
    if ($color === '') {
        $color = '1F2A37';
    }
    $xml = briefing_docx_rfonts($font);
    if (!empty($o['bold'])) {
        $xml .= '<w:b w:val="1"/><w:bCs w:val="1"/>';
    }
    $xml .= '<w:color w:val="' . $color . '"/>'
        . '<w:sz w:val="' . $sz . '"/><w:szCs w:val="' . $sz . '"/>'
        . briefing_docx_cs_rtl();
    return $xml;
}

function briefing_docx_run(string $text, string $font, array $o = []): string
{
    $text = str_replace("\0", '', $text);
    $rPr = '<w:rPr>' . briefing_docx_rpr($font, $o) . '</w:rPr>';
    if ($text === '') {
        return '<w:r>' . $rPr . '</w:r>';
    }
    $parts = preg_split("/\r\n|\n|\r/", $text) ?: [''];
    $xml = '';
    $last = count($parts) - 1;
    foreach ($parts as $i => $part) {
        if ($i > 0) {
            $xml .= '<w:r>' . $rPr . '<w:br/></w:r>';
        }
        $xml .= '<w:r>' . $rPr . '<w:t xml:space="preserve">' . briefing_docx_xml($part) . '</w:t></w:r>';
        if ($i !== $last) {
            // line break already added at start of next
        }
    }
    return $xml;
}

function briefing_docx_p(string $runs, array $o = []): string
{
    $jc = $o['align'] ?? 'right';
    $before = (int) ($o['before'] ?? 0);
    $after = (int) ($o['after'] ?? 80);
    $pPr = '<w:pPr><w:pStyle w:val="Normal"/>';
    if (!empty($o['keep'])) {
        $pPr .= '<w:keepNext w:val="1"/>';
    }
    $borders = '';
    if (!empty($o['top'])) {
        $borders .= '<w:top w:val="single" w:sz="' . (int) $o['top'] . '" w:space="4" w:color="' . ($o['topColor'] ?? '3B4F6E') . '"/>';
    }
    if (!empty($o['bottom'])) {
        $borders .= '<w:bottom w:val="single" w:sz="' . (int) $o['bottom'] . '" w:space="1" w:color="' . ($o['bottomColor'] ?? 'D7DDE4') . '"/>';
    }
    if ($borders !== '') {
        $pPr .= '<w:pBdr>' . $borders . '</w:pBdr>';
    }
    $pPr .= '<w:autoSpaceDE w:val="0"/><w:autoSpaceDN w:val="0"/>'
        . '<w:bidi w:val="1"/>'
        . '<w:adjustRightInd w:val="0"/>'
        . '<w:spacing w:before="' . $before . '" w:after="' . $after . '" w:line="360" w:lineRule="auto"/>'
        . '<w:jc w:val="' . $jc . '"/>'
        . '<w:rPr><w:rFonts w:hint="cs"/>' . briefing_docx_cs_rtl() . '</w:rPr></w:pPr>';
    return '<w:p>' . $pPr . $runs . '</w:p>';
}

function briefing_docx_empty_p(array $o = []): string
{
    return briefing_docx_p('', $o);
}

function briefing_docx_kv_table(array $rows, string $font): string
{
    if ($rows === []) {
        return '';
    }
    $xml = '';
    foreach ($rows as $row) {
        $label = (string) ($row[0] ?? '');
        $value = (string) ($row[1] ?? '');
        $opts = is_array($row[2] ?? null) ? $row[2] : [];
        $runs = briefing_docx_run("\u{200F}" . $label . ': ', $font, ['bold' => true, 'size' => 12.5])
            . briefing_docx_run($value, $font, [
                'size' => 12.5,
                'bold' => !empty($opts['bold']),
                'color' => $opts['color'] ?? '1F2A37',
            ]);
        $xml .= briefing_docx_p($runs, ['after' => 160, 'align' => 'right']);
    }
    return $xml;
}

function briefing_docx_logo_bytes(): ?array
{
    $rel = function_exists('org_logo_path') ? org_logo_path() : '';
    $abs = '';
    if ($rel !== '') {
        $abs = dirname(__DIR__) . '/' . ltrim(str_replace('\\', '/', $rel), '/');
    }
    if ($abs === '' || !is_file($abs)) {
        return null;
    }
    $raw = @file_get_contents($abs);
    if ($raw === false || $raw === '') {
        return null;
    }
    $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
    $mime = 'image/png';
    $partExt = 'png';
    if ($ext === 'jpg' || $ext === 'jpeg') {
        $mime = 'image/jpeg';
        $partExt = 'jpeg';
    } elseif ($ext === 'gif') {
        $mime = 'image/gif';
        $partExt = 'gif';
    } elseif ($ext === 'png') {
        $mime = 'image/png';
        $partExt = 'png';
    } else {
        return null;
    }
    $info = @getimagesizefromstring($raw);
    $w = is_array($info) ? (int) $info[0] : 120;
    $h = is_array($info) ? (int) $info[1] : 120;
    if ($w < 1) {
        $w = 120;
    }
    if ($h < 1) {
        $h = 120;
    }
    $targetW = 120;
    $targetH = (int) round($h * ($targetW / $w));
    if ($targetH < 1) {
        $targetH = 40;
    }
    return [
        'bytes' => $raw,
        'ext' => $partExt,
        'mime' => $mime,
        'cx' => (int) round($targetW * 9525),
        'cy' => (int) round($targetH * 9525),
    ];
}

function briefing_docx_logo_drawing(array $logo): string
{
    $cx = (int) $logo['cx'];
    $cy = (int) $logo['cy'];
    return '<w:r><w:rPr><w:noProof/></w:rPr><w:drawing>'
        . '<wp:inline distT="0" distB="0" distL="0" distR="0">'
        . '<wp:extent cx="' . $cx . '" cy="' . $cy . '"/>'
        . '<wp:effectExtent l="0" t="0" r="0" b="0"/>'
        . '<wp:docPr id="1" name="Logo"/>'
        . '<wp:cNvGraphicFramePr><a:graphicFrameLocks xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" noChangeAspect="1"/></wp:cNvGraphicFramePr>'
        . '<a:graphic xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">'
        . '<a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">'
        . '<pic:pic xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">'
        . '<pic:nvPicPr><pic:cNvPr id="0" name="logo"/><pic:cNvPicPr/></pic:nvPicPr>'
        . '<pic:blipFill><a:blip r:embed="rIdLogo"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill>'
        . '<pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="' . $cx . '" cy="' . $cy . '"/></a:xfrm>'
        . '<a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr>'
        . '</pic:pic></a:graphicData></a:graphic></wp:inline></w:drawing></w:r>';
}

function briefing_docx_check(string $font, bool $on): string
{
    $mark = $on ? '✓' : ' ';
    return '<w:tbl><w:tblPr><w:tblW w:w="260" w:type="dxa"/>'
        . '<w:tblBorders>'
        . '<w:top w:val="single" w:sz="8" w:color="1F2A37"/>'
        . '<w:left w:val="single" w:sz="8" w:color="1F2A37"/>'
        . '<w:bottom w:val="single" w:sz="8" w:color="1F2A37"/>'
        . '<w:right w:val="single" w:sz="8" w:color="1F2A37"/>'
        . '</w:tblBorders></w:tblPr>'
        . '<w:tblGrid><w:gridCol w:w="260"/></w:tblGrid>'
        . '<w:tr><w:trPr><w:trHeight w:val="260" w:hRule="atLeast"/></w:trPr>'
        . '<w:tc><w:tcPr><w:tcW w:w="260" w:type="dxa"/><w:vAlign w:val="center"/></w:tcPr>'
        . briefing_docx_p(briefing_docx_run($mark, $font, ['bold' => true, 'size' => 9]), ['align' => 'center', 'after' => 0, 'before' => 0])
        . '</w:tc></w:tr></w:tbl>';
}

function briefing_docx_document_body(array $corr, array $brief, bool $fromFirst, string $font, ?array $logo): string
{
    $title = briefing_title_for_type((string) ($corr['type'] ?? 'incoming'));
    $options = briefing_normalize_options($brief['options'] ?? []);
    $basisLine = briefing_basis_line($brief);
    $names = function_exists('org_visible_names') ? org_visible_names('print') : [];
    $headerDate = trim((string) ($corr['corr_date'] ?? ''));
    $receiptDate = trim((string) ($brief['receipt_date'] ?? ''));
    $hijri = ($headerDate !== '' && function_exists('format_hijri_date')) ? format_hijri_date($headerDate) : '';
    $greg = $headerDate !== '' ? date('Y/m/d', strtotime($headerDate) ?: time()) : '';

    $side = briefing_docx_p(briefing_docx_run(format_ref_number($corr['ref_number'] ?? ''), $font, ['bold' => true, 'size' => 9, 'color' => '3B4F6E']), ['align' => 'center', 'after' => 40]);
    if ($hijri !== '') {
        $side .= briefing_docx_p(briefing_docx_run($hijri . ' هـ', $font, ['bold' => true, 'size' => 10.5, 'color' => '3B4F6E']), ['align' => 'center', 'after' => 20]);
    }
    if ($greg !== '') {
        $side .= briefing_docx_p(briefing_docx_run($greg . ' م', $font, ['bold' => true, 'size' => 10.5, 'color' => '3B4F6E']), ['align' => 'center', 'after' => 0]);
    }

    $center = '';
    if ($logo) {
        $center .= briefing_docx_p(briefing_docx_logo_drawing($logo), ['align' => 'center', 'after' => 80]);
    }
    if (!empty($names['primary'])) {
        $center .= briefing_docx_p(briefing_docx_run((string) $names['primary'], $font, ['bold' => true, 'size' => 13]), ['align' => 'center', 'after' => 40]);
    }
    if (!empty($names['secondary'])) {
        $center .= briefing_docx_p(briefing_docx_run((string) $names['secondary'], $font, ['size' => 10.5]), ['align' => 'center', 'after' => 80]);
    }
    $center .= briefing_docx_p(briefing_docx_run($title, $font, ['bold' => true, 'size' => 14, 'color' => '3B4F6E']), ['align' => 'center', 'after' => 40]);

    // جدول الترويسة من اليمين لليسار: يمين فارغ، وسط الشعار، يسار الرقم والتاريخ (مثل الطباعة).
    $body = '<w:tbl><w:tblPr><w:bidiVisual w:val="1"/><w:tblW w:w="5000" w:type="pct"/>'
        . '<w:tblBorders><w:top w:val="nil"/><w:left w:val="nil"/><w:bottom w:val="nil"/><w:right w:val="nil"/><w:insideH w:val="nil"/><w:insideV w:val="nil"/></w:tblBorders>'
        . '</w:tblPr><w:tblGrid><w:gridCol w:w="2170"/><w:gridCol w:w="4730"/><w:gridCol w:w="2170"/></w:tblGrid>'
        . '<w:tr>'
        . '<w:tc><w:tcPr><w:tcW w:w="2170" w:type="dxa"/></w:tcPr>' . briefing_docx_empty_p(['after' => 0]) . '</w:tc>'
        . '<w:tc><w:tcPr><w:tcW w:w="4730" w:type="dxa"/></w:tcPr>' . $center . '</w:tc>'
        . '<w:tc><w:tcPr><w:tcW w:w="2170" w:type="dxa"/></w:tcPr>' . $side . '</w:tc>'
        . '</w:tr></w:tbl>';
    $body .= briefing_docx_p('', ['after' => 120, 'top' => 12, 'topColor' => '3B4F6E']);

    $toLine = trim((string) ($brief['directed_to'] ?? '')) ?: '—';
    $fromLine = trim((string) ($brief['directed_from'] ?? '')) ?: '—';
    $dirRows = $fromFirst
        ? [[__('print.directed_from'), $fromLine, ['bold' => true]], [__('print.directed_to'), $toLine, ['bold' => true]]]
        : [[__('print.directed_to'), $toLine, ['bold' => true]], [__('print.directed_from'), $fromLine, ['bold' => true]]];

    $body .= briefing_docx_kv_table([
        [__('corr.subject'), (string) ($corr['subject'] ?? ''), ['bold' => true]],
    ], $font);
    $body .= briefing_docx_kv_table($dirRows, $font);

    $extra = [];
    if ($basisLine !== '') {
        $extra[] = [__('brief.basis_label'), $basisLine, []];
    }
    if ($receiptDate !== '' && $receiptDate !== $headerDate) {
        $extra[] = [__('brief.receipt_date'), briefing_dual_date($receiptDate), []];
    }
    $attach = trim((string) ($brief['attachments_desc'] ?? ''));
    if ($attach !== '') {
        $extra[] = [__('brief.attachments'), $attach, []];
    }
    $request = trim((string) ($brief['request_text'] ?? ''));
    if ($request !== '') {
        $extra[] = [__('brief.request'), $request, ['color' => 'EE0000']];
    }
    if ($extra !== []) {
        $body .= briefing_docx_kv_table($extra, $font);
    }
    $body .= briefing_docx_p('', ['after' => 200, 'top' => 18, 'topColor' => '3B4F6E']);

    $blocks = [
        ['k' => __('brief.basis'), 'v' => $brief['basis_text'] ?? '', 'justify' => true],
        ['k' => __('brief.study'), 'v' => $brief['study_text'] ?? '', 'justify' => false],
    ];
    foreach ($blocks as $block) {
        $text = trim((string) $block['v']);
        if ($text === '') {
            continue;
        }
        $body .= briefing_docx_p(briefing_docx_run((string) $block['k'], $font, ['bold' => true]), [
            'bottom' => 6,
            'after' => 80,
            'keep' => true,
        ]);
        $align = !empty($block['justify']) ? 'both' : 'right';
        $body .= briefing_docx_p(briefing_docx_run($text, $font), ['align' => $align, 'after' => 200]);
    }

    $rec = is_array($brief['recommendation'] ?? null)
        ? $brief['recommendation']
        : briefing_parse_recommendation($brief['recommendation_text'] ?? '');
    if (briefing_recommendation_has_content($rec)) {
        $body .= briefing_docx_p(briefing_docx_run(__('brief.recommendation'), $font, ['bold' => true]), [
            'bottom' => 6,
            'after' => 80,
            'keep' => true,
        ]);
        $introRec = trim((string) ($rec['intro'] ?? ''));
        if ($introRec !== '') {
            $body .= briefing_docx_p(briefing_docx_run($introRec, $font, ['color' => 'EE0000']), ['after' => 80]);
        }
        $n = 0;
        foreach (array_values($rec['items'] ?? []) as $item) {
            $item = trim((string) $item);
            if ($item === '') {
                continue;
            }
            $n++;
            $runs = briefing_docx_run("\u{200F}" . $n . '- ', $font, ['bold' => true, 'color' => 'EE0000'])
                . briefing_docx_run($item, $font, ['color' => 'EE0000']);
            $body .= briefing_docx_p($runs, ['after' => 140]);
        }
    }

    $body .= briefing_docx_p(briefing_docx_run(__('brief.direction'), $font, ['bold' => true]), [
        'bottom' => 6,
        'after' => 80,
        'keep' => true,
    ]);
    $intro = trim((string) ($brief['direction_intro'] ?? ''));
    if ($intro !== '') {
        $body .= briefing_docx_p(briefing_docx_run($intro, $font), ['after' => 160]);
    }
    foreach ($options as $opt) {
        $label = (string) ($opt['label'] ?? '');
        $on = !empty($opt['selected']);
        $mark = $on ? '☑ ' : '☐ ';
        $body .= briefing_docx_p(
            briefing_docx_run("\u{200F}" . $mark, $font, ['bold' => true, 'size' => 12.5])
            . briefing_docx_run($label, $font, ['size' => 12.5]),
            ['after' => 140, 'align' => 'right']
        );
    }
    $note = trim((string) ($brief['decision_note'] ?? ''));
    if ($note !== '') {
        $body .= briefing_docx_p(briefing_docx_run($note, $font), ['after' => 120]);
    }

    $approver = trim((string) ($brief['approver_name'] ?? '')) ?: '—';
    $approverTitle = trim((string) ($brief['approver_title'] ?? ''));
    $body .= '<w:tbl><w:tblPr><w:bidiVisual w:val="1"/><w:tblW w:w="2800" w:type="dxa"/><w:jc w:val="left"/>'
        . '<w:tblBorders><w:top w:val="nil"/><w:left w:val="nil"/><w:bottom w:val="nil"/><w:right w:val="nil"/></w:tblBorders>'
        . '</w:tblPr><w:tblGrid><w:gridCol w:w="2800"/></w:tblGrid><w:tr><w:tc>'
        . briefing_docx_p(briefing_docx_run($approver, $font, ['bold' => true, 'size' => 12.5]), ['align' => 'center', 'before' => 400, 'after' => 40])
        . ($approverTitle !== '' ? briefing_docx_p(briefing_docx_run($approverTitle, $font, ['size' => 12.5]), ['align' => 'center', 'after' => 0]) : '')
        . '</w:tc></w:tr></w:tbl>';

    $body .= '<w:sectPr>'
        . '<w:pgSz w:w="11906" w:h="16838"/>'
        . '<w:pgMar w:top="851" w:right="1418" w:bottom="851" w:left="1418" w:header="0" w:footer="0" w:gutter="0"/>'
        . '<w:bidi w:val="1"/>'
        . '<w:rtlGutter w:val="0"/>'
        . '<w:docGrid w:linePitch="360"/>'
        . '</w:sectPr>';

    return $body;
}

function briefing_docx_styles(string $font): string
{
    $rFonts = briefing_docx_rfonts($font);
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
        . '<w:docDefaults><w:rPrDefault><w:rPr>' . $rFonts
        . '<w:sz w:val="25"/><w:szCs w:val="25"/>' . briefing_docx_cs_rtl() . '</w:rPr></w:rPrDefault>'
        . '<w:pPrDefault><w:pPr><w:autoSpaceDE w:val="0"/><w:autoSpaceDN w:val="0"/><w:bidi w:val="1"/>'
        . '<w:adjustRightInd w:val="0"/><w:jc w:val="right"/>'
        . '<w:rPr><w:rFonts w:hint="cs"/>' . briefing_docx_cs_rtl() . '</w:rPr></w:pPr></w:pPrDefault></w:docDefaults>'
        . '<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/><w:qFormat/>'
        . '<w:pPr><w:autoSpaceDE w:val="0"/><w:autoSpaceDN w:val="0"/><w:bidi w:val="1"/>'
        . '<w:adjustRightInd w:val="0"/><w:jc w:val="right"/>'
        . '<w:rPr><w:rFonts w:hint="cs"/>' . briefing_docx_cs_rtl() . '</w:rPr></w:pPr>'
        . '<w:rPr>' . $rFonts . '<w:sz w:val="25"/><w:szCs w:val="25"/>' . briefing_docx_cs_rtl() . '</w:rPr></w:style>'
        . '</w:styles>';
}

function briefing_docx_settings(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<w:settings xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
        . '<w:embedTrueTypeFonts/>'
        . '<w:saveSubsetFonts w:val="false"/>'
        . '<w:view w:val="print"/>'
        . '<w:characterSpacingControl w:val="doNotCompress"/>'
        . '<w:themeFontLang w:val="ar-SA" w:bidi="ar-SA"/>'
        . '</w:settings>';
}

function briefing_docx_build(array $corr, array $brief, bool $fromFirst = false): string
{
    $files = briefing_docx_font_files();
    $font = briefing_docx_font_name();
    if ($files !== []) {
        $font = briefing_docx_otf_family($files[0]['path']);
    }
    $logo = briefing_docx_logo_bytes();
    $body = briefing_docx_document_body($corr, $brief, $fromFirst, $font, $logo);
    return briefing_docx_package($body, $font, $logo, $files);
}

function briefing_docx_package(string $body, string $font, ?array $logo, array $files): string
{
    $document = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<w:document xmlns:wpc="http://schemas.microsoft.com/office/word/2010/wordprocessingCanvas"'
        . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"'
        . ' xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"'
        . ' xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing">'
        . '<w:body>' . $body . '</w:body></w:document>';

    $fontRels = '';
    $fontEmbeds = '';
    $fontParts = [];
    foreach ($files as $i => $file) {
        $raw = @file_get_contents($file['path']);
        if ($raw === false || $raw === '') {
            continue;
        }
        $key = briefing_docx_guid();
        $rid = 'rFont' . ($i + 1);
        $fontParts[] = ['part' => $file['part'], 'bytes' => briefing_docx_obfuscate_font($raw, $key)];
        $fontRels .= '<Relationship Id="' . $rid . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/font" Target="fonts/' . $file['part'] . '"/>';
        $fontEmbeds .= '<w:' . $file['tag'] . ' r:id="' . $rid . '" w:fontKey="' . $key . '" w:subsetted="false"/>';
    }

    $fontTable = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<w:fonts xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"'
        . ' xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
        . '<w:font w:name="' . briefing_docx_xml($font) . '">'
        . '<w:charset w:val="B2"/>'
        . '<w:family w:val="auto"/>'
        . '<w:pitch w:val="variable"/>'
        . $fontEmbeds
        . '</w:font></w:fonts>';

    $overrides = [
        '/word/document.xml' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml',
        '/word/styles.xml' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml',
        '/word/settings.xml' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml',
        '/word/fontTable.xml' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.fontTable+xml',
    ];
    $defaults = [
        'rels' => 'application/vnd.openxmlformats-package.relationships+xml',
        'xml' => 'application/xml',
        'odttf' => 'application/vnd.openxmlformats-officedocument.obfuscatedFont',
    ];
    $docRels = '<Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . '<Relationship Id="rIdSettings" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/settings" Target="settings.xml"/>'
        . '<Relationship Id="rIdFonts" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/fontTable" Target="fontTable.xml"/>';
    if ($logo) {
        $defaults[$logo['ext']] = $logo['mime'];
        $docRels .= '<Relationship Id="rIdLogo" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/logo.' . $logo['ext'] . '"/>';
    }

    $types = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">';
    foreach ($defaults as $ext => $ct) {
        $types .= '<Default Extension="' . $ext . '" ContentType="' . $ct . '"/>';
    }
    foreach ($overrides as $part => $ct) {
        $types .= '<Override PartName="' . $part . '" ContentType="' . $ct . '"/>';
    }
    $types .= '</Types>';

    $rootRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
        . '</Relationships>';
    $wordRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . $docRels
        . '</Relationships>';
    $fontTableRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . $fontRels
        . '</Relationships>';

    $tmp = tempnam(sys_get_temp_dir(), 'brf');
    if ($tmp === false) {
        throw new RuntimeException('temp');
    }
    @unlink($tmp);
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::CREATE) !== true) {
        throw new RuntimeException('zip');
    }
    $zip->addFromString('[Content_Types].xml', $types);
    $zip->addFromString('_rels/.rels', $rootRels);
    $zip->addFromString('word/document.xml', $document);
    $zip->addFromString('word/_rels/document.xml.rels', $wordRels);
    $zip->addFromString('word/styles.xml', briefing_docx_styles($font));
    $zip->addFromString('word/settings.xml', briefing_docx_settings());
    $zip->addFromString('word/fontTable.xml', $fontTable);
    $zip->addFromString('word/_rels/fontTable.xml.rels', $fontTableRels);
    foreach ($fontParts as $part) {
        $zip->addFromString('word/fonts/' . $part['part'], $part['bytes']);
    }
    if ($logo) {
        $zip->addFromString('word/media/logo.' . $logo['ext'], $logo['bytes']);
    }
    $zip->close();
    $bin = (string) file_get_contents($tmp);
    @unlink($tmp);
    return $bin;
}

function briefing_docx_send(array $corr, array $brief, bool $fromFirst = false): void
{
    $bin = briefing_docx_build($corr, $brief, $fromFirst);
    $ascii = preg_replace('/\.doc$/i', '.docx', briefing_word_filename($corr));
    $utfName = 'ورقة-عرض-' . preg_replace('/[\\\\\/:*?"<>|]+/', '-', (string) ($corr['ref_number'] ?? 'corr')) . '.docx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($utfName));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . (string) strlen($bin));
    echo $bin;
    exit;
}
