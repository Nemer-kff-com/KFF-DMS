<?php
/**
 * نظام الترجمة عربي / إنجليزي — DMS
 */

function dms_supported_languages(): array
{
    return ['ar', 'en'];
}

function dms_lang_code(): string
{
    if (isset($_GET['lang'])) {
        $requested = strtolower(trim((string) $_GET['lang']));
        if (in_array($requested, dms_supported_languages(), true)) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['dms_lang'] = $requested;
            // إزالة lang من الرابط بعد الحفظ عبر redirect اختياري — نبقيه في الجلسة فقط
        }
    }

    $code = $_SESSION['dms_lang'] ?? 'ar';
    return in_array($code, dms_supported_languages(), true) ? $code : 'ar';
}

function dms_page_dir(?string $lang = null): string
{
    return ($lang ?? dms_lang_code()) === 'en' ? 'ltr' : 'rtl';
}

function dms_is_rtl(): bool
{
    return dms_page_dir() === 'rtl';
}

function dms_load_dict(?string $lang = null): array
{
    static $cache = [];
    $lang = $lang ?? dms_lang_code();
    if (isset($cache[$lang])) {
        return $cache[$lang];
    }
    $file = __DIR__ . '/../lang/' . $lang . '.php';
    $dict = is_file($file) ? require $file : [];
    $cache[$lang] = is_array($dict) ? $dict : [];
    return $cache[$lang];
}

function __(string $key, ?string $default = null): string
{
    return __lang($key, dms_lang_code(), $default);
}

function __lang(string $key, string $lang, ?string $default = null): string
{
    $lang = $lang === 'en' ? 'en' : 'ar';
    $dict = dms_load_dict($lang);
    if (array_key_exists($key, $dict)) {
        return (string) $dict[$key];
    }
    if ($lang !== 'ar') {
        $ar = dms_load_dict('ar');
        if (array_key_exists($key, $ar)) {
            return (string) $ar[$key];
        }
    }
    return $default ?? $key;
}

function dms_lang_url(string $lang): string
{
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $parts = parse_url($uri);
    $path = $parts['path'] ?? '/';
    $query = [];
    if (!empty($parts['query'])) {
        parse_str($parts['query'], $query);
    }
    $query['lang'] = $lang;
    return $path . '?' . http_build_query($query);
}

function dms_vendor_asset(string $relPath): string
{
    $relPath = ltrim(str_replace('\\', '/', $relPath), '/');
    if (function_exists('dms_asset_url')) {
        return dms_asset_url('assets/vendor/' . $relPath, true);
    }
    $disk = dirname(__DIR__) . '/assets/vendor/' . $relPath;
    $url = base_url('assets/vendor/' . $relPath);
    $mtime = is_file($disk) ? (int) filemtime($disk) : 1;
    return $url . (str_contains($url, '?') ? '&' : '?') . 'v=' . $mtime;
}

function dms_bootstrap_css(): string
{
    return dms_is_rtl()
        ? dms_vendor_asset('bootstrap/bootstrap.rtl.min.css')
        : dms_vendor_asset('bootstrap/bootstrap.min.css');
}

function dms_bootstrap_js(): string
{
    return dms_vendor_asset('bootstrap/bootstrap.bundle.min.js');
}

function dms_bootstrap_icons_css(): string
{
    return dms_vendor_asset('bootstrap-icons/bootstrap-icons.min.css');
}

function dms_fontawesome_css(): string
{
    return dms_vendor_asset('fontawesome/css/all.min.css');
}

function dms_sweetalert_js(): string
{
    return dms_vendor_asset('sweetalert2/sweetalert2.all.min.js');
}

function dms_brand_fonts_css(): string
{
    $rel = 'assets/css/brand-fonts.css';
    if (function_exists('dms_asset_url')) {
        return dms_asset_url($rel, true);
    }
    $disk = dirname(__DIR__) . '/' . $rel;
    $url = base_url($rel);
    $mtime = is_file($disk) ? (int) filemtime($disk) : 1;
    return $url . (str_contains($url, '?') ? '&' : '?') . 'v=' . $mtime;
}

/** خطوط إضافية (Tajawal / Arial / Calibri / Aldhabi / Sakkal Majalla / AL-Mohanad / Fanan) — للقوالب فقط */
function dms_brand_fonts_extra_css(): string
{
    $rel = 'assets/css/brand-fonts-extra.css';
    if (function_exists('dms_asset_url')) {
        return dms_asset_url($rel, true);
    }
    $disk = dirname(__DIR__) . '/' . $rel;
    $url = base_url($rel);
    $mtime = is_file($disk) ? (int) filemtime($disk) : 1;
    return $url . (str_contains($url, '?') ? '&' : '?') . 'v=' . $mtime;
}

function dms_print_fast_css(): string
{
    $rel = 'assets/css/print-fast.css';
    if (function_exists('dms_asset_url')) {
        return dms_asset_url($rel, true);
    }
    $disk = dirname(__DIR__) . '/' . $rel;
    $url = base_url($rel);
    $mtime = is_file($disk) ? (int) filemtime($disk) : 1;
    return $url . (str_contains($url, '?') ? '&' : '?') . 'v=' . $mtime;
}

function dms_org_display_name(): string
{
    if (dms_lang_code() === 'en') {
        $en = org_name_en();
        if ($en !== '') {
            return $en;
        }
    }
    return org_name();
}
