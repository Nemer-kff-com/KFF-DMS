<?php
/**
 * جذر تخزين ملفات المعاملات فقط (وارد/صادر/داخلي/تعاميم/سندات).
 * مرفقات الأرشيف لها مسار مستقل (archive_disk_root).
 * الشعار وصور المستخدمين والتواقيع تبقى في assets/uploads كما هي.
 */
declare(strict_types=1);

/** مجلدات المعاملات تحت جذر التخزين (lowercase — HostGator). */
function dms_storage_subdirs(): array
{
    return ['incoming', 'outgoing', 'internal', 'archive', 'circulars', 'receipts'];
}

/** الجذر الافتراضي داخل المشروع: storage/uploads */
function dms_storage_default_root(): string
{
    return rtrim(str_replace('\\', '/', dirname(__DIR__)), '/') . '/storage/uploads';
}

function dms_storage_normalize_root(string $path): string
{
    return rtrim(str_replace('\\', '/', trim($path)), '/');
}

/**
 * @return array{ok:bool,path:string,error:string}
 */
function dms_storage_validate_root(string $path, bool $create = false): array
{
    $path = dms_storage_normalize_root($path);
    if ($path === '') {
        return ['ok' => false, 'path' => '', 'error' => 'مسار التخزين فارغ.'];
    }
    if (str_contains($path, "\0") || str_contains($path, '..')) {
        return ['ok' => false, 'path' => $path, 'error' => 'مسار التخزين غير صالح.'];
    }
    if (PHP_OS_FAMILY !== 'Windows' && !str_starts_with($path, '/')) {
        return ['ok' => false, 'path' => $path, 'error' => 'على السيرفر استخدم مساراً مطلقاً يبدأ بـ / (مثل /home/user/dms-data/uploads).'];
    }

    if (!is_dir($path)) {
        if (!$create) {
            return ['ok' => false, 'path' => $path, 'error' => 'المجلد غير موجود: ' . $path];
        }
        if (!@mkdir($path, 0755, true) && !is_dir($path)) {
            return ['ok' => false, 'path' => $path, 'error' => 'تعذر إنشاء مجلد التخزين: ' . $path];
        }
    }

    $real = realpath($path);
    if ($real === false || !is_dir($real)) {
        return ['ok' => false, 'path' => $path, 'error' => 'تعذر التحقق من مجلد التخزين.'];
    }
    if (!is_writable($real)) {
        return ['ok' => false, 'path' => str_replace('\\', '/', $real), 'error' => 'مجلد التخزين غير قابل للكتابة.'];
    }

    return ['ok' => true, 'path' => str_replace('\\', '/', $real), 'error' => ''];
}

function dms_storage_ensure_subdirs(string $root): void
{
    $root = dms_storage_normalize_root($root);
    foreach (dms_storage_subdirs() as $sub) {
        $dir = $root . '/' . $sub;
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
    }
    $ht = $root . '/.htaccess';
    if (!is_file($ht)) {
        @file_put_contents($ht, <<<'HT'
# DMS transaction files — no direct HTTP when under web root.
Options -Indexes
<FilesMatch "\.(php|phtml|php\d|phar|cgi|pl|asp|aspx|exe|sh|bat)$">
    Require all denied
</FilesMatch>
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^(incoming|outgoing|internal|archive|circulars|receipts)(/|$) - [F,L]
</IfModule>
HT);
    }
}

function dms_storage_root(): string
{
    if (!empty($GLOBALS['_dms_storage_root_force'])) {
        $GLOBALS['_dms_storage_root'] = null;
        $GLOBALS['_dms_storage_root_force'] = false;
    }
    if (!empty($GLOBALS['_dms_storage_root']) && is_string($GLOBALS['_dms_storage_root'])) {
        return $GLOBALS['_dms_storage_root'];
    }

    $configured = '';
    if (function_exists('setting')) {
        try {
            $configured = dms_storage_normalize_root((string) setting('storage_root', ''));
        } catch (Throwable $e) {
            $configured = '';
        }
    }

    if ($configured !== '') {
        $check = dms_storage_validate_root($configured, true);
        if ($check['ok']) {
            dms_storage_ensure_subdirs($check['path']);
            $GLOBALS['_dms_storage_root'] = $check['path'];
            return $check['path'];
        }
    }

    $fallback = dms_storage_default_root();
    $check = dms_storage_validate_root($fallback, true);
    if ($check['ok']) {
        dms_storage_ensure_subdirs($check['path']);
        $GLOBALS['_dms_storage_root'] = $check['path'];
        return $check['path'];
    }

    // ملاذ أخير: المجلد القديم داخل المشروع
    $legacy = dms_storage_normalize_root(dirname(__DIR__) . '/assets/uploads');
    @mkdir($legacy, 0755, true);
    dms_storage_ensure_subdirs($legacy);
    $GLOBALS['_dms_storage_root'] = $legacy;
    return $legacy;
}

function dms_storage_root_clear_cache(): void
{
    $GLOBALS['_dms_storage_root'] = null;
    $GLOBALS['_dms_storage_root_force'] = true;
}

/**
 * مسار نسبي داخل تخزين المعاملات.
 * يدعم القديم: assets/uploads/incoming/...
 */
function dms_storage_relative(string $storedPath): string
{
    $rel = ltrim(str_replace('\\', '/', trim($storedPath)), '/');
    if ($rel === '' || str_contains($rel, '..')) {
        return '';
    }
    if (str_starts_with($rel, 'assets/uploads/')) {
        $rel = substr($rel, strlen('assets/uploads/'));
    } elseif (str_starts_with($rel, 'storage/uploads/')) {
        $rel = substr($rel, strlen('storage/uploads/'));
    }
    $rel = ltrim($rel, '/');
    $parts = explode('/', $rel);
    if ($parts !== [] && $parts[0] !== '') {
        $first = strtolower($parts[0]);
        if (in_array($first, dms_storage_subdirs(), true)) {
            $parts[0] = $first;
        }
    }
    return implode('/', $parts);
}

/** هل المسار يخص ملفات المعاملات (وليس شعار/أفاتار)؟ */
function dms_storage_is_transaction_rel(string $rel): bool
{
    $rel = dms_storage_relative($rel);
    if ($rel === '') {
        return false;
    }
    $top = strtolower((string) strtok($rel, '/'));
    return in_array($top, dms_storage_subdirs(), true);
}

/** مسار القرص لملف معاملة (جذر التخزين، مع سقوط للمسار القديم). */
function dms_storage_disk_path(string $storedPath): string
{
    $rel = dms_storage_relative($storedPath);
    if ($rel === '' || !dms_storage_is_transaction_rel($rel)) {
        return '';
    }
    $primary = dms_storage_root() . '/' . $rel;
    if (is_file($primary) || is_dir(dirname(str_replace('/', DIRECTORY_SEPARATOR, $primary)))) {
        // إن وُجد الملف في التخزين الجديد أو المجلد جاهز للكتابة
        if (is_file($primary)) {
            return str_replace('/', DIRECTORY_SEPARATOR, $primary);
        }
    }
    $legacy = dirname(__DIR__) . '/assets/uploads/' . $rel;
    if (is_file($legacy)) {
        return str_replace('/', DIRECTORY_SEPARATOR, $legacy);
    }
    return str_replace('/', DIRECTORY_SEPARATOR, $primary);
}

/** قيمة تُحفظ في القاعدة لملف معاملة. */
function dms_storage_db_path(string $relativeInsideStorage): string
{
    return dms_storage_relative($relativeInsideStorage);
}

/**
 * نقل ملف مرفوع إلى التخزين — مع احتياطي copy على Windows/XAMPP.
 */
function dms_move_uploaded_file(string $tmp, string $dest): bool
{
    $tmp = (string) $tmp;
    $dest = (string) $dest;
    if ($tmp === '' || $dest === '' || !is_file($tmp)) {
        return false;
    }
    if (@move_uploaded_file($tmp, $dest)) {
        return true;
    }
    // بعض بيئات Windows تفشل move_uploaded_file رغم أن الملف مرفوع فعلياً
    if (is_uploaded_file($tmp) && @copy($tmp, $dest)) {
        @unlink($tmp);
        return is_file($dest);
    }
    return false;
}

/**
 * @return array{ok:bool,path:string,error:string}
 */
function dms_storage_save_root(string $path): array
{
    $path = dms_storage_normalize_root($path);
    if ($path === '') {
        if (function_exists('set_setting')) {
            set_setting('storage_root', '', 'جذر تخزين ملفات المعاملات (فارغ = storage/uploads داخل المشروع)');
        }
        dms_storage_root_clear_cache();
        $check = dms_storage_validate_root(dms_storage_default_root(), true);
        if ($check['ok']) {
            dms_storage_ensure_subdirs($check['path']);
        }
        return $check['ok']
            ? ['ok' => true, 'path' => $check['path'], 'error' => '']
            : $check;
    }

    $check = dms_storage_validate_root($path, true);
    if (!$check['ok']) {
        return $check;
    }
    dms_storage_ensure_subdirs($check['path']);
    if (function_exists('set_setting')) {
        set_setting('storage_root', $check['path'], 'جذر تخزين ملفات المعاملات (فارغ = storage/uploads داخل المشروع)');
    }
    dms_storage_root_clear_cache();
    return ['ok' => true, 'path' => $check['path'], 'error' => ''];
}

/**
 * ترحيل ملفات المعاملات فقط من assets/uploads إلى جذر التخزين.
 * @return array{copied:int,skipped:int}
 */
function dms_storage_migrate_legacy_once(): array
{
    $legacy = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'uploads';
    $legacyReal = realpath($legacy);
    $root = dms_storage_root();
    $rootReal = realpath($root);
    $copied = 0;
    $skipped = 0;
    if ($legacyReal === false || $rootReal === false) {
        return ['copied' => 0, 'skipped' => 0];
    }
    if (strcasecmp(str_replace('\\', '/', $legacyReal), str_replace('\\', '/', $rootReal)) === 0) {
        return ['copied' => 0, 'skipped' => 0];
    }

    $flag = $root . '/.migrated_from_assets';
    if (is_file($flag)) {
        return ['copied' => 0, 'skipped' => 0];
    }

    foreach (dms_storage_subdirs() as $sub) {
        $src = $legacyReal . DIRECTORY_SEPARATOR . $sub;
        if (!is_dir($src)) {
            continue;
        }
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS)
        );
        /** @var SplFileInfo $f */
        foreach ($it as $f) {
            if (!$f->isFile()) {
                continue;
            }
            $full = $f->getPathname();
            $rel = substr($full, strlen($src) + 1);
            $dest = $rootReal . DIRECTORY_SEPARATOR . $sub . DIRECTORY_SEPARATOR . $rel;
            $destDir = dirname($dest);
            if (!is_dir($destDir)) {
                @mkdir($destDir, 0755, true);
            }
            if (is_file($dest)) {
                $skipped++;
                continue;
            }
            if (@copy($full, $dest)) {
                $copied++;
            } else {
                $skipped++;
            }
        }
    }
    @file_put_contents($flag, date('c') . " copied=$copied skipped=$skipped\n");
    return ['copied' => $copied, 'skipped' => $skipped];
}

function dms_storage_bootstrap(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        dms_storage_root();
        dms_storage_migrate_legacy_once();
    } catch (Throwable $e) {
        // لا تمنع الإقلاع
    }
}
