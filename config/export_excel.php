<?php
/**
 * تصدير Excel (.xls) بتنسيق HTML — اتجاه من اليمين لليسار كما في نظام الدعوات.
 */

declare(strict_types=1);

function dms_export_excel_send_headers(string $filename): void
{
    if (!str_ends_with(strtolower($filename), '.xls')) {
        $filename .= '.xls';
    }

    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
}

function dms_export_excel_sheet_name(string $filename): string
{
    $name = preg_replace('/\.xls(x)?$/i', '', trim($filename));
    $name = is_string($name) ? trim($name) : trim($filename);
    $name = preg_replace('/[\\\\\\/\\?\\*\\[\\]:]/', '-', $name);
    $name = is_string($name) ? trim($name) : 'Sheet1';
    if ($name === '') {
        return 'Sheet1';
    }
    if (mb_strlen($name) > 31) {
        $name = mb_substr($name, 0, 31);
    }

    return $name;
}

function dms_export_excel_open(string $filename, bool $withNumberFormat = true): void
{
    dms_export_excel_send_headers($filename);
    dms_export_excel_begin($withNumberFormat, $filename);
}

function dms_export_excel_begin(bool $withNumberFormat = true, string $sheetName = 'Sheet1'): void
{
    $sheetTitle = dms_export_excel_sheet_name($sheetName);
    $sheetTitleXml = htmlspecialchars($sheetTitle, ENT_XML1 | ENT_QUOTES, 'UTF-8');

    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" '
        . 'xmlns:x="urn:schemas-microsoft-com:office:excel" '
        . 'xmlns="http://www.w3.org/TR/REC-html40" dir="rtl" lang="ar">';
    echo '<head>';
    echo '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">';
    echo '<meta name="ProgId" content="Excel.Sheet">';
    echo '<meta name="Generator" content="Microsoft Excel">';
    echo '<!--[if gte mso 9]><xml>';
    echo '<x:ExcelWorkbook xmlns:x="urn:schemas-microsoft-com:office:excel">';
    echo '<x:ExcelWorksheets>';
    echo '<x:ExcelWorksheet>';
    echo '<x:Name>' . $sheetTitleXml . '</x:Name>';
    echo '<x:WorksheetOptions>';
    echo '<x:DisplayRightToLeft/>';
    echo '</x:WorksheetOptions>';
    echo '</x:ExcelWorksheet>';
    echo '</x:ExcelWorksheets>';
    echo '</x:ExcelWorkbook>';
    echo '</xml><![endif]-->';
    echo '<style>';
    echo 'table,th,td{direction:rtl;border-collapse:collapse;font-family:Arial,sans-serif;font-size:11pt;}';
    echo 'td{font-family:Arial,sans-serif;background-color:#FFFFFF;color:#3B4F6E;mso-pattern:auto none;text-align:right;vertical-align:middle;}';
    echo 'th{text-align:center;vertical-align:middle;}';
    if ($withNumberFormat) {
        echo 'td.col-num{mso-number-format:0;text-align:right}';
        echo 'td.col-text{mso-number-format:\\@}';
    } else {
        echo 'td{mso-number-format:\\@}';
    }
    echo '</style></head><body dir="rtl">';
    echo "<table border='1' dir='rtl' cellpadding='4' cellspacing='0'>";
}

function dms_export_excel_title_row(string $title, int $colspan): void
{
    $style = 'font-family:Arial,sans-serif;background-color:#3B4F6E;color:#FFFFFF;font-weight:bold;font-size:14pt;'
        . 'text-align:center;border:1px solid #2c3e50;mso-pattern:auto none;';
    echo '<tr><th colspan="' . (int) $colspan . '" style="' . $style . '">';
    echo dms_export_excel_escape($title);
    echo '</th></tr>';
}

function dms_export_excel_subtitle_row(string $text, int $colspan): void
{
    $style = 'font-family:Arial,sans-serif;background-color:#f8f6f2;color:#3B4F6E;font-weight:600;font-size:11pt;'
        . 'text-align:center;border:1px solid #d8d2c8;mso-pattern:auto none;';
    echo '<tr><th colspan="' . (int) $colspan . '" style="' . $style . '">';
    echo dms_export_excel_escape($text);
    echo '</th></tr>';
}

/** @param list<string> $labels */
function dms_export_excel_header_row(array $labels): void
{
    $thStyle = 'font-family:Arial,sans-serif;background-color:#0b5130;color:#FFFFFF;font-weight:bold;text-align:center;'
        . 'border:1px solid #084327;mso-pattern:auto none;';

    echo '<tr>';
    foreach ($labels as $label) {
        echo '<th style="' . $thStyle . '">';
        echo dms_export_excel_escape((string) $label);
        echo '</th>';
    }
    echo '</tr>';
}

function dms_export_excel_end(): void
{
    echo '</table></body></html>';
}

function dms_export_excel_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function dms_export_excel_cell(string $value, string $class = 'col-text'): void
{
    $escaped = dms_export_excel_escape($value);
    if (str_contains($value, "\n")) {
        $escaped = nl2br($escaped, false);
    }
    echo '<td class="' . $class . '">' . $escaped . '</td>';
}
