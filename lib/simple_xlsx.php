<?php
/**
 * Minimal XLSX read/write (ZIP + XML).
 * Uses ZipArchive when available; otherwise pure-PHP ZIP (no extension required).
 */

/**
 * @param array<string, list<list<scalar|null>>> $sheets sheetTitle => rows of cells
 */
function dms_simple_xlsx_write(string $filePath, array $sheets): void
{
    if ($sheets === []) {
        throw new InvalidArgumentException('No sheets to write');
    }

    $shared = [];
    $sharedIndex = [];
    $addShared = static function (string $value) use (&$shared, &$sharedIndex): int {
        if (isset($sharedIndex[$value])) {
            return $sharedIndex[$value];
        }
        $idx = count($shared);
        $shared[] = $value;
        $sharedIndex[$value] = $idx;

        return $idx;
    };

    $sheetXml = [];
    $sheetNames = [];
    $i = 0;
    foreach ($sheets as $title => $rows) {
        $i++;
        $safeTitle = dms_simple_xlsx_safe_sheet_title((string) $title, $i);
        $sheetNames[$i] = $safeTitle;
        $sheetXml[$i] = dms_simple_xlsx_build_sheet_xml($rows, $addShared, true);
    }

    $files = [
        '[Content_Types].xml' => dms_simple_xlsx_content_types(count($sheetNames)),
        '_rels/.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>
XML,
        'xl/workbook.xml' => dms_simple_xlsx_workbook_xml($sheetNames),
        'xl/_rels/workbook.xml.rels' => dms_simple_xlsx_workbook_rels(count($sheetNames)),
        'xl/styles.xml' => dms_simple_xlsx_styles_xml(),
        'xl/sharedStrings.xml' => dms_simple_xlsx_shared_strings_xml($shared),
    ];
    foreach ($sheetXml as $num => $xml) {
        $files['xl/worksheets/sheet' . $num . '.xml'] = $xml;
    }

    $binary = dms_zip_build_store($files);
    if (@file_put_contents($filePath, $binary) === false) {
        throw new RuntimeException('تعذر حفظ ملف Excel');
    }
}

/**
 * @return array{sheets: array<string, list<list<string>>>, first: list<list<string>>}
 */
function dms_simple_xlsx_read(string $filePath): array
{
    $entries = dms_zip_read_entries($filePath);
    if ($entries === []) {
        throw new RuntimeException('تعذر فتح ملف Excel');
    }

    $sharedXml = $entries['xl/sharedStrings.xml'] ?? '';
    $shared = dms_simple_xlsx_parse_shared_strings_xml($sharedXml);
    $sheetFiles = dms_simple_xlsx_sheet_targets_from_entries($entries);
    $sheets = [];
    foreach ($sheetFiles as $title => $target) {
        $path = dms_simple_xlsx_resolve_sheet_path($target);
        $xml = $entries[$path] ?? $entries['xl/worksheets/' . basename($target)] ?? null;
        if ($xml === null || $xml === '') {
            continue;
        }
        $sheets[$title] = dms_simple_xlsx_parse_sheet_xml($xml, $shared);
    }

    $first = $sheets === [] ? [] : reset($sheets);

    return ['sheets' => $sheets, 'first' => $first];
}

/**
 * Build a ZIP archive using STORE (no compression). Excel accepts this for XLSX.
 *
 * @param array<string, string> $files path => content
 */
function dms_zip_build_store(array $files): string
{
    $local = '';
    $central = '';
    $offset = 0;
    $count = 0;

    foreach ($files as $name => $data) {
        $name = str_replace('\\', '/', (string) $name);
        $data = (string) $data;
        $nameLen = strlen($name);
        $size = strlen($data);
        $crc = unpack('V', pack('l', crc32($data)))[1];

        $localHeader = pack(
            'VvvvvvVVVvv',
            0x04034b50,
            20,
            0,
            0,
            0,
            0,
            $crc,
            $size,
            $size,
            $nameLen,
            0
        ) . $name . $data;

        // Central directory header: V + 6v + 3V + 5v + 2V
        $central .= pack(
            'VvvvvvvVVVvvvvvVV',
            0x02014b50, // signature
            20,         // version made by
            20,         // version needed
            0,          // flags
            0,          // method STORE
            0,          // time
            0,          // date
            $crc,
            $size,
            $size,
            $nameLen,
            0,          // extra len
            0,          // comment len
            0,          // disk start
            0,          // internal attrs
            0,          // external attrs
            $offset
        ) . $name;

        $offset += strlen($localHeader);
        $local .= $localHeader;
        $count++;
    }

    $end = pack(
        'VvvvvVVv',
        0x06054b50,
        0,
        0,
        $count,
        $count,
        strlen($central),
        strlen($local),
        0
    );

    return $local . $central . $end;
}

/**
 * Read ZIP entries (STORE or DEFLATE). Prefers ZipArchive when available.
 *
 * @return array<string, string> path => content
 */
function dms_zip_read_entries(string $filePath): array
{
    if (class_exists('ZipArchive', false)) {
        $zip = new ZipArchive();
        if ($zip->open($filePath) === true) {
            $out = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if ($stat === false) {
                    continue;
                }
                $name = str_replace('\\', '/', (string) ($stat['name'] ?? ''));
                if ($name === '' || str_ends_with($name, '/')) {
                    continue;
                }
                $content = $zip->getFromIndex($i);
                if ($content !== false) {
                    $out[$name] = $content;
                }
            }
            $zip->close();
            if ($out !== []) {
                return $out;
            }
        }
    }

    return dms_zip_read_entries_pure($filePath);
}

/**
 * @return array<string, string>
 */
function dms_zip_read_entries_pure(string $filePath): array
{
    $bin = @file_get_contents($filePath);
    if ($bin === false || $bin === '') {
        return [];
    }

    $out = [];
    $len = strlen($bin);
    $pos = 0;
    while ($pos + 30 <= $len) {
        $sig = unpack('V', substr($bin, $pos, 4))[1];
        if ($sig === 0x02014b50 || $sig === 0x06054b50) {
            break;
        }
        if ($sig !== 0x04034b50) {
            $pos++;
            continue;
        }

        $header = unpack(
            'vver/vflag/vmethod/vtime/vdate/Vcrc/Vcsize/Vsize/vnamelen/vextralen',
            substr($bin, $pos + 4, 26)
        );
        if ($header === false) {
            break;
        }
        $nameStart = $pos + 30;
        $name = substr($bin, $nameStart, $header['namelen']);
        $dataStart = $nameStart + $header['namelen'] + $header['extralen'];
        $comp = substr($bin, $dataStart, $header['csize']);
        $pos = $dataStart + $header['csize'];

        $name = str_replace('\\', '/', $name);
        if ($name === '' || str_ends_with($name, '/')) {
            continue;
        }

        if ($header['method'] === 0) {
            $out[$name] = $comp;
        } elseif ($header['method'] === 8) {
            if (($header['flag'] & 0x01) !== 0) {
                continue; // encrypted
            }
            // ZIP deflate is raw; try gzinflate then gzdecode fallback
            $raw = @gzinflate($comp);
            if ($raw === false) {
                $raw = @gzdecode($comp);
            }
            if ($raw !== false) {
                $out[$name] = $raw;
            }
        }
    }

    return $out;
}

function dms_simple_xlsx_resolve_sheet_path(string $target): string
{
    $target = str_replace('\\', '/', $target);
    if (str_starts_with($target, '/')) {
        return ltrim($target, '/');
    }
    if (str_starts_with($target, 'xl/')) {
        return $target;
    }

    return 'xl/' . preg_replace('#^\./#', '', $target);
}

function dms_simple_xlsx_safe_sheet_title(string $title, int $fallbackIndex): string
{
    $title = trim($title);
    $title = str_replace(['\\', '/', '*', '?', ':', '[', ']'], '', $title);
    if ($title === '') {
        $title = 'Sheet' . $fallbackIndex;
    }
    if (mb_strlen($title, 'UTF-8') > 31) {
        $title = mb_substr($title, 0, 31, 'UTF-8');
    }

    return $title;
}

/**
 * @param list<list<scalar|null>> $rows
 * @param callable(string):int $addShared
 */
function dms_simple_xlsx_build_sheet_xml(array $rows, callable $addShared, bool $rtl = true): string
{
    $out = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
    $out .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
    if ($rtl) {
        $out .= '<sheetViews><sheetView workbookViewId="0" rightToLeft="1"/></sheetViews>';
    }
    $out .= '<sheetData>';
    foreach ($rows as $rIndex => $cells) {
        $rowNum = $rIndex + 1;
        $out .= '<row r="' . $rowNum . '">';
        $styleIdx = $rtl ? ($rIndex === 0 ? '2' : '1') : null;
        foreach ($cells as $cIndex => $value) {
            $col = dms_simple_xlsx_col_letter($cIndex + 1);
            $ref = $col . $rowNum;
            if ($value === null || $value === '') {
                continue;
            }
            // Keep long phone/MSISDN values as text so Excel does not alter them.
            $asText = is_string($value) && (
                preg_match('/^0\d+/', $value)
                || preg_match('/^\d{10,}$/', $value)
                || !is_numeric($value)
            );
            $styleAttr = $styleIdx !== null ? ' s="' . $styleIdx . '"' : '';
            if (!$asText && (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value)))) {
                $out .= '<c r="' . $ref . '"' . $styleAttr . '><v>' . htmlspecialchars((string) $value, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</v></c>';
            } else {
                $idx = $addShared((string) $value);
                $out .= '<c r="' . $ref . '" t="s"' . $styleAttr . '><v>' . $idx . '</v></c>';
            }
        }
        $out .= '</row>';
    }
    $out .= '</sheetData></worksheet>';

    return $out;
}

function dms_simple_xlsx_col_letter(int $index): string
{
    $letter = '';
    while ($index > 0) {
        $index--;
        $letter = chr(65 + ($index % 26)) . $letter;
        $index = intdiv($index, 26);
    }

    return $letter;
}

function dms_simple_xlsx_col_index(string $letters): int
{
    $letters = strtoupper($letters);
    $n = 0;
    $len = strlen($letters);
    for ($i = 0; $i < $len; $i++) {
        $n = $n * 26 + (ord($letters[$i]) - 64);
    }

    return $n;
}

/** @param array<int, string> $sheetNames */
function dms_simple_xlsx_workbook_xml(array $sheetNames): string
{
    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
    $xml .= '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
    $xml .= '<sheets>';
    foreach ($sheetNames as $num => $name) {
        $xml .= '<sheet name="' . htmlspecialchars($name, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '" sheetId="' . $num . '" r:id="rId' . $num . '"/>';
    }
    $xml .= '</sheets></workbook>';

    return $xml;
}

function dms_simple_xlsx_workbook_rels(int $sheetCount): string
{
    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
    $xml .= '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
    for ($i = 1; $i <= $sheetCount; $i++) {
        $xml .= '<Relationship Id="rId' . $i . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $i . '.xml"/>';
    }
    $next = $sheetCount + 1;
    $xml .= '<Relationship Id="rId' . $next . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>';
    $next++;
    $xml .= '<Relationship Id="rId' . $next . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
    $xml .= '</Relationships>';

    return $xml;
}

function dms_simple_xlsx_content_types(int $sheetCount): string
{
    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
    $xml .= '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">';
    $xml .= '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>';
    $xml .= '<Default Extension="xml" ContentType="application/xml"/>';
    $xml .= '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>';
    $xml .= '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
    $xml .= '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>';
    for ($i = 1; $i <= $sheetCount; $i++) {
        $xml .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
    }
    $xml .= '</Types>';

    return $xml;
}

function dms_simple_xlsx_styles_xml(): string
{
    return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="2">
    <font><sz val="11"/><name val="Calibri"/></font>
    <font><b/><sz val="11"/><name val="Calibri"/></font>
  </fonts>
  <fills count="1"><fill><patternFill patternType="none"/></fill></fills>
  <borders count="1"><border/></borders>
  <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
  <cellXfs count="3">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1">
      <alignment horizontal="right" readingOrder="2"/>
    </xf>
    <xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyAlignment="1" applyFont="1">
      <alignment horizontal="right" readingOrder="2"/>
    </xf>
  </cellXfs>
</styleSheet>
XML;
}

/** @param list<string> $shared */
function dms_simple_xlsx_shared_strings_xml(array $shared): string
{
    $count = count($shared);
    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
    $xml .= '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . $count . '" uniqueCount="' . $count . '">';
    foreach ($shared as $s) {
        $xml .= '<si><t xml:space="preserve">' . htmlspecialchars($s, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</t></si>';
    }
    $xml .= '</sst>';

    return $xml;
}

/** @return list<string> */
function dms_simple_xlsx_parse_shared_strings_xml(string $xml): array
{
    if ($xml === '') {
        return [];
    }
    $sx = @simplexml_load_string($xml);
    if ($sx === false) {
        return [];
    }
    $out = [];
    foreach ($sx->si as $si) {
        $text = '';
        if (isset($si->t)) {
            $text = (string) $si->t;
        } else {
            foreach ($si->r as $run) {
                $text .= (string) $run->t;
            }
        }
        $out[] = $text;
    }

    return $out;
}

/**
 * @param array<string, string> $entries
 * @return array<string, string> title => target path relative to xl/
 */
function dms_simple_xlsx_sheet_targets_from_entries(array $entries): array
{
    $wb = $entries['xl/workbook.xml'] ?? '';
    $rels = $entries['xl/_rels/workbook.xml.rels'] ?? '';
    if ($wb === '' || $rels === '') {
        return ['Sheet1' => 'worksheets/sheet1.xml'];
    }
    $relMap = [];
    $rx = @simplexml_load_string($rels);
    if ($rx !== false) {
        foreach ($rx->Relationship as $rel) {
            $relMap[(string) $rel['Id']] = (string) $rel['Target'];
        }
    }
    $wx = @simplexml_load_string($wb);
    if ($wx === false) {
        return ['Sheet1' => 'worksheets/sheet1.xml'];
    }
    $out = [];
    foreach ($wx->sheets->sheet as $sheet) {
        $name = (string) $sheet['name'];
        $attrs = $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $rid = (string) ($attrs['id'] ?? '');
        if ($rid === '' || !isset($relMap[$rid])) {
            continue;
        }
        $out[$name] = $relMap[$rid];
    }

    return $out !== [] ? $out : ['Sheet1' => 'worksheets/sheet1.xml'];
}

/**
 * @param list<string> $shared
 * @return list<list<string>>
 */
function dms_simple_xlsx_parse_sheet_xml(string $xml, array $shared): array
{
    $sx = @simplexml_load_string($xml);
    if ($sx === false) {
        return [];
    }
    $grid = [];
    $maxCol = 0;
    $maxRow = 0;
    foreach ($sx->sheetData->row as $row) {
        $rowNum = (int) $row['r'];
        if ($rowNum <= 0) {
            continue;
        }
        $maxRow = max($maxRow, $rowNum);
        foreach ($row->c as $c) {
            $ref = (string) $c['r'];
            if (!preg_match('/^([A-Z]+)(\d+)$/', $ref, $m)) {
                continue;
            }
            $colIndex = dms_simple_xlsx_col_index($m[1]);
            $maxCol = max($maxCol, $colIndex);
            $type = (string) ($c['t'] ?? '');
            $value = '';
            if ($type === 's') {
                $idx = (int) ($c->v ?? -1);
                $value = $shared[$idx] ?? '';
            } elseif ($type === 'inlineStr') {
                $value = isset($c->is->t) ? (string) $c->is->t : '';
            } else {
                $value = isset($c->v) ? (string) $c->v : '';
                if ($value !== '' && is_numeric($value) && str_contains($value, 'E')) {
                    $f = (float) $value;
                    $value = (floor($f) == $f) ? (string) (int) $f : (string) $f;
                } elseif ($value !== '' && is_numeric($value) && floor((float) $value) == (float) $value) {
                    $value = (string) (int) (float) $value;
                }
            }
            $grid[$rowNum][$colIndex] = $value;
        }
    }

    $rows = [];
    for ($r = 1; $r <= $maxRow; $r++) {
        $line = [];
        for ($c = 1; $c <= $maxCol; $c++) {
            $line[] = $grid[$r][$c] ?? '';
        }
        $rows[] = $line;
    }

    return $rows;
}

