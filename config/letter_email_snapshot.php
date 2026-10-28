<?php
/**
 * تصوير نص الخطاب بخطّه الحقيقي حتى يظهر في Outlook/Gmail كما في المعاينة.
 */

/**
 * @return array{path:string,w:int,h:int}
 */
function letter_email_snapshot(array $corr, string $letter, string $lang = 'ar'): array
{
    $empty = ['path' => '', 'w' => 0, 'h' => 0];
    $letter = trim($letter);
    if ($letter === '') {
        return $empty;
    }
    $bin = letter_email_browser_bin();
    if ($bin === '') {
        return $empty;
    }
    $html = letter_email_snapshot_document($corr, $letter, $lang);
    if ($html === '') {
        return $empty;
    }
    $dir = sys_get_temp_dir();
    $id = bin2hex(random_bytes(6));
    $htmlPath = $dir . DIRECTORY_SEPARATOR . 'dms_letter_' . $id . '.html';
    $pngPath = $dir . DIRECTORY_SEPARATOR . 'dms_letter_' . $id . '.png';
    if (@file_put_contents($htmlPath, $html) === false) {
        return $empty;
    }
    $ok = letter_email_browser_screenshot($bin, $htmlPath, $pngPath, letter_email_snapshot_height($letter));
    @unlink($htmlPath);
    if (!$ok || !is_file($pngPath) || filesize($pngPath) < 800) {
        @unlink($pngPath);
        return $empty;
    }
    $info = @getimagesize($pngPath);
    $w = is_array($info) ? (int) $info[0] : 640;
    $h = is_array($info) ? (int) $info[1] : 0;

    return ['path' => $pngPath, 'w' => $w, 'h' => $h];
}

function letter_email_browser_bin(): string
{
    $pf = (string) (getenv('ProgramFiles') ?: getenv('PROGRAMFILES') ?: 'C:\\Program Files');
    $pf86 = (string) (getenv('ProgramFiles(x86)') ?: getenv('PROGRAMFILES(X86)') ?: 'C:\\Program Files (x86)');
    $local = (string) (getenv('LOCALAPPDATA') ?: '');
    $cands = [
        $pf . '\\Microsoft\\Edge\\Application\\msedge.exe',
        $pf86 . '\\Microsoft\\Edge\\Application\\msedge.exe',
        $pf . '\\Google\\Chrome\\Application\\chrome.exe',
        $pf86 . '\\Google\\Chrome\\Application\\chrome.exe',
        $local !== '' ? $local . '\\Google\\Chrome\\Application\\chrome.exe' : '',
    ];
    foreach ($cands as $path) {
        if ($path !== '' && is_file($path)) {
            return $path;
        }
    }
    return '';
}

function letter_email_snapshot_height(string $letter): int
{
    $lines = max(8, substr_count($letter, "\n") + 12);

    return min(3600, max(900, $lines * 36));
}

function letter_email_browser_screenshot(string $bin, string $htmlPath, string $pngPath, int $height): bool
{
    $disabled = strtolower((string) ini_get('disable_functions'));
    if (str_contains($disabled, 'proc_open')) {
        return false;
    }
    $htmlUrl = letter_email_file_url($htmlPath);
    $modes = ['--headless=new', '--headless'];
    foreach ($modes as $mode) {
        $args = [
            $bin,
            $mode,
            '--disable-gpu',
            '--hide-scrollbars',
            '--no-first-run',
            '--no-default-browser-check',
            '--disable-extensions',
            '--force-device-scale-factor=2',
            '--window-size=680,' . max(800, $height),
            '--screenshot=' . $pngPath,
            $htmlUrl,
        ];
        $spec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $proc = @proc_open($args, $spec, $pipes, sys_get_temp_dir(), null, ['bypass_shell' => true]);
        if (!is_resource($proc)) {
            continue;
        }
        fclose($pipes[0]);
        stream_set_timeout($pipes[1], 20);
        stream_set_timeout($pipes[2], 20);
        stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($proc);
        if (is_file($pngPath) && filesize($pngPath) > 800) {
            return true;
        }
        if ($status === 0 && is_file($pngPath)) {
            return true;
        }
        @unlink($pngPath);
    }
    return false;
}

function letter_email_file_url(string $path): string
{
    $path = str_replace('\\', '/', $path);
    if (preg_match('#^([A-Za-z]):/(.*)$#', $path, $m) === 1) {
        $parts = array_map('rawurlencode', explode('/', (string) $m[2]));
        return 'file:///' . $m[1] . ':/' . implode('/', $parts);
    }

    return 'file://' . $path;
}

function letter_email_snapshot_document(array $corr, string $letter, string $lang): string
{
    $lang = $lang === 'en' ? 'en' : 'ar';
    $dir = $lang === 'en' ? 'ltr' : 'rtl';
    $opt = letter_font_option(letter_body_font_code($letter));
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
    $inner = render_letter_body_html($letter, false, $corr);
    $inner = letter_email_inline_local_images($inner);
    $css = $faces
        . 'html,body{margin:0;padding:0;background:#fff;}'
        . 'body{font-family:\'' . $family . '\',Arial,sans-serif;font-size:12pt;line-height:1.5;color:#000;direction:'
        . $dir . ';text-align:' . ($lang === 'en' ? 'left' : 'right') . ';}'
        . '.wrap{padding:8px 10px;max-width:640px;}'
        . '.letter-align-center{text-align:center;}.letter-align-right{text-align:right;}'
        . '.letter-align-left{text-align:left;}.letter-align-justify{text-align:justify;text-align-last:start;}'
        . '.letter-fmt-b{font-weight:700;}.letter-fmt-u{text-decoration:underline;}'
        . '.letter-fmt-big{font-size:14.5pt;font-weight:600;}'
        . '.letter-ul{margin:.25em 0 .7em;padding-inline-start:1.35em;}'
        . '.letter-signature{margin:1.1em 0 .4em;display:inline-block;max-width:12rem;}'
        . '.letter-signature-img{max-height:48px;max-width:140px;height:auto;width:auto;display:block;margin:0 0 6px;}'
        . '.letter-signature-name{font-weight:700;line-height:1.35;}'
        . '.letter-signature-title{font-size:.92em;color:#445;line-height:1.35;}'
        . '.letter-logo{max-height:56px;max-width:160px;height:auto;}';

    return '<!DOCTYPE html><html lang="' . $lang . '" dir="' . $dir . '"><head><meta charset="UTF-8">'
        . '<style>' . $css . '</style></head><body><div class="wrap">' . $inner . '</div></body></html>';
}

function letter_email_inline_local_images(string $html): string
{
    return preg_replace_callback('/(<img\b[^>]*\bsrc=")([^"]+)(")/i', static function (array $m): string {
        $uri = letter_email_src_data_uri((string) $m[2]);
        return $m[1] . ($uri !== '' ? $uri : $m[2]) . $m[3];
    }, $html) ?? $html;
}

function letter_email_src_data_uri(string $src): string
{
    $src = html_entity_decode(trim($src), ENT_QUOTES, 'UTF-8');
    if ($src === '' || str_starts_with($src, 'data:') || str_starts_with($src, 'cid:')) {
        return '';
    }
    $rel = '';
    if (function_exists('base_url')) {
        $base = rtrim(base_url(), '/');
        if ($base !== '' && str_starts_with($src, $base . '/')) {
            $rel = substr($src, strlen($base) + 1);
        }
    }
    if ($rel === '' && !preg_match('#^[a-z][a-z0-9+.-]*:#i', $src)) {
        $rel = ltrim(str_replace('\\', '/', $src), '/');
    }
    $rel = ltrim(str_replace('\\', '/', $rel), '/');
    if ($rel === '' || str_contains($rel, '..')) {
        return '';
    }
    $disk = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    if (!is_file($disk)) {
        return '';
    }
    $bin = @file_get_contents($disk);
    if ($bin === false || $bin === '') {
        return '';
    }
    $ext = strtolower(pathinfo($disk, PATHINFO_EXTENSION));
    $mime = match ($ext) {
        'jpg', 'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        default => 'image/png',
    };

    return 'data:' . $mime . ';base64,' . base64_encode($bin);
}
