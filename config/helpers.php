<?php
/**
 * DMS helpers
 */

date_default_timezone_set('Asia/Riyadh');

require_once __DIR__ . '/storage.php';

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** زر أيقوني مع تلميح (tooltip) — رابط أو زر */
function btn_icon(string $label, array $opts = []): string
{
    $href = (string) ($opts['href'] ?? '');
    $icon = (string) ($opts['icon'] ?? 'bi-plus-lg');
    $class = trim('btn btn-icon ' . (string) ($opts['class'] ?? 'btn-kff'));
    $extra = trim((string) ($opts['attrs'] ?? ''));
    $tag = $href !== '' ? 'a' : 'button';
    $html = '<' . $tag . ' class="' . e($class) . '"';
    if ($href !== '') {
        $html .= ' href="' . e($href) . '"';
        if (!empty($opts['target'])) {
            $html .= ' target="' . e((string) $opts['target']) . '"';
        }
    } else {
        $html .= ' type="' . e((string) ($opts['type'] ?? 'button')) . '"';
    }
    $html .= ' title="' . e($label) . '" aria-label="' . e($label) . '"'
        . ' data-bs-toggle="tooltip" data-bs-placement="top"';
    if ($extra !== '') {
        $html .= ' ' . $extra;
    }
    $html .= '><i class="bi ' . e($icon) . '" aria-hidden="true"></i></' . $tag . '>';
    return $html;
}

/** زر إضافة أيقوني مع تلميح (tooltip) */
function btn_icon_add(string $href, string $label, string $icon = 'bi-plus-lg'): string
{
    return btn_icon($label, [
        'href' => $href,
        'icon' => $icon,
        'class' => 'btn-kff btn-icon-add',
    ]);
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array
{
    if (empty($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

/** مسار مجلد المشروع على الويب (مثل /dms) — مستنتج تلقائياً */
function dms_project_base(): string
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $normalizeBase = static function (string $base): string {
        $base = str_replace('\\', '/', $base);
        $base = rtrim($base, '/');
        if ($base === '' || $base === '/' || $base === '.') {
            return '';
        }
        return $base[0] === '/' ? $base : '/' . $base;
    };

    $projectRoot = str_replace('\\', '/', realpath(__DIR__ . '/..') ?: dirname(__DIR__));
    $projectFolder = basename($projectRoot !== '' ? $projectRoot : 'dms') ?: 'dms';
    $docRoot = str_replace('\\', '/', realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? '')) ?: '');
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $scriptFile = str_replace('\\', '/', realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) ?: '');

    // 1) الأدق غالباً: مقارنة مسار الملف الفعلي مع جذر المشروع + SCRIPT_NAME
    //    يعمل حتى مع symlink أو DOCUMENT_ROOT غير مطابق بعد النقل للسيرفر.
    if ($scriptFile !== '' && $projectRoot !== '' && str_starts_with($scriptFile, $projectRoot)) {
        $relFromProject = substr($scriptFile, strlen($projectRoot));
        if ($relFromProject === false) {
            $relFromProject = '';
        }
        if ($relFromProject !== '' && $relFromProject[0] !== '/') {
            $relFromProject = '/' . $relFromProject;
        }
        if ($scriptName !== '' && $relFromProject !== '' && str_ends_with($scriptName, $relFromProject)) {
            $base = substr($scriptName, 0, -strlen($relFromProject));
            $cached = $normalizeBase($base === false ? '' : $base);
            return $cached;
        }
    }

    // 2) مقارنة DOCUMENT_ROOT مع مجلد المشروع
    if ($docRoot !== '' && $projectRoot !== '' && str_starts_with($projectRoot, $docRoot)) {
        $rel = substr($projectRoot, strlen($docRoot));
        $cached = $normalizeBase($rel === false ? '' : $rel);
        return $cached;
    }

    // 3) استدلال من SCRIPT_NAME (مجلدات النظام المعروفة)
    if ($scriptName !== '' && $scriptName[0] === '/') {
        $modules = 'auth|admin|incoming|outgoing|internal|referrals|archive|documents|reports|comments|search|notifications|correspondence|circulars|files|api|tools';
        if (preg_match('#^(.*)/(?:' . $modules . ')/[A-Za-z0-9_.-]+\.php$#', $scriptName, $m)) {
            $cached = $normalizeBase($m[1] === '' ? '' : $m[1]);
            return $cached;
        }
        $folderRe = preg_quote($projectFolder, '#');
        if (preg_match('#^(.*?)(/' . $folderRe . ')(?=/|$)#i', $scriptName, $m)) {
            $cached = $normalizeBase($m[1] . $m[2]);
            return $cached;
        }
    }

    $cached = $normalizeBase('/' . $projectFolder);
    return $cached;
}

/** بروتوكول الطلب الحالي (مع دعم البروكسي) */
function dms_request_scheme(): string
{
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $proto = strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']);
        if (str_contains($proto, ',')) {
            $proto = trim(explode(',', $proto)[0]);
        }
        if ($proto === 'https' || $proto === 'http') {
            return $proto;
        }
    }
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return 'https';
    }
    $port = (string) ($_SERVER['SERVER_PORT'] ?? '');
    if ($port === '443') {
        return 'https';
    }
    return 'http';
}

/**
 * رابط أساس النظام تلقائياً من الطلب الحالي
 * (مثل نظام الدعوات: لا حاجة لتعديل يدوي عند النقل للدومين)
 */
function dms_detect_base_url(): string
{
    static $cached = null;
    if (is_string($cached)) {
        return $cached;
    }

    $scheme = dms_request_scheme();
    $host = (string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost');
    if ($host === '') {
        $host = 'localhost';
    }
    $path = dms_project_base();
    $cached = rtrim($scheme . '://' . $host . $path, '/');
    return $cached;
}

/**
 * مسار ويب نسبي للجذر (مثل /dms/assets/...) — لا يعتمد على host/protocol.
 * استخدمه لملفات CSS/JS/الخطوط حتى لا يختلف التنسيق عند النقل أو الدخول بـ IP بدل localhost.
 */
function dms_web_path(string $path = ''): string
{
    $base = dms_project_base();
    $path = ltrim(str_replace('\\', '/', $path), '/');
    if ($path === '') {
        return $base === '' ? '/' : $base;
    }
    return ($base === '' ? '' : $base) . '/' . $path;
}

/**
 * رابط أصل ثابت (CSS/JS/صور/خطوط): مسار نسبي للجذر + ?v=mtime لكسر الكاش.
 */
function dms_asset_url(string $relPath, bool $cacheBust = true): string
{
    $relPath = ltrim(str_replace('\\', '/', $relPath), '/');
    $url = dms_web_path($relPath);
    if (!$cacheBust) {
        return $url;
    }
    $disk = dirname(__DIR__) . '/' . $relPath;
    $mtime = is_file($disk) ? (int) filemtime($disk) : 1;
    return $url . (str_contains($url, '?') ? '&' : '?') . 'v=' . $mtime;
}

/**
 * يتحقق من وجود ملفات التنسيق الأساسية بعد النقل لجهاز/سيرفر آخر.
 *
 * @return list<array{path:string,ok:bool}>
 */
function dms_critical_assets_status(): array
{
    $root = dirname(__DIR__);
    $required = [
        'assets/css/app.css',
        'assets/css/brand-fonts.css',
        'assets/css/swal-dms.css',
        'assets/js/app.js',
        'assets/fonts/GretaArabic-Regular.otf',
        'assets/fonts/GretaArabic-Bold.otf',
        'assets/fonts/Amiri-Regular.ttf',
        'assets/vendor/bootstrap/bootstrap.rtl.min.css',
        'assets/vendor/bootstrap/bootstrap.min.css',
        'assets/vendor/bootstrap/bootstrap.bundle.min.js',
        'assets/vendor/bootstrap-icons/bootstrap-icons.min.css',
        'assets/vendor/bootstrap-icons/fonts/bootstrap-icons.woff2',
        'assets/vendor/bootstrap-icons/fonts/bootstrap-icons.woff',
        'assets/vendor/fontawesome/css/all.min.css',
        'assets/vendor/fontawesome/webfonts/fa-solid-900.woff2',
        'assets/vendor/sweetalert2/sweetalert2.all.min.js',
    ];
    $out = [];
    foreach ($required as $rel) {
        $out[] = [
            'path' => $rel,
            'ok' => is_file($root . '/' . $rel),
        ];
    }
    return $out;
}

function base_url(string $path = ''): string
{
    $base = dms_detect_base_url();
    $path = ltrim(str_replace('\\', '/', $path), '/');
    return $path === '' ? $base : $base . '/' . $path;
}

function setting(string $key, string $default = ''): string
{
    dms_settings_warmup();
    if (!isset($GLOBALS['_dms_settings_cache']) || !is_array($GLOBALS['_dms_settings_cache'])) {
        $GLOBALS['_dms_settings_cache'] = [];
    }
    $cache =& $GLOBALS['_dms_settings_cache'];
    if (!array_key_exists($key, $cache)) {
        $cache[$key] = $default;
    }
    return $cache[$key];
}

/** تحميل كل الإعدادات مرة واحدة لكل طلب بدل SELECT لكل مفتاح */
function dms_settings_warmup(): void
{
    if (!empty($GLOBALS['_dms_settings_warm'])) {
        return;
    }
    $GLOBALS['_dms_settings_warm'] = true;
    if (!isset($GLOBALS['_dms_settings_cache']) || !is_array($GLOBALS['_dms_settings_cache'])) {
        $GLOBALS['_dms_settings_cache'] = [];
    }
    try {
        $rows = db()->query('SELECT setting_key, setting_value FROM `tblsettings`')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $k = (string) ($row['setting_key'] ?? '');
            if ($k !== '') {
                $GLOBALS['_dms_settings_cache'][$k] = (string) ($row['setting_value'] ?? '');
            }
        }
    } catch (Throwable $e) {
        // جدول الإعدادات غير جاهز بعد
    }
}

/** أوصاف إعدادات النظام بالعربية (لحقل settings.desc) */
function settings_desc_ar_map(): array
{
    return [
        'org_name' => 'اسم الجهة (قديم / مرادف للاسم العربي)',
        'org_name_ar' => 'اسم الجهة بالعربية',
        'org_name_en' => 'اسم الجهة بالإنجليزية',
        'org_phone' => 'هاتف الجهة',
        'org_email' => 'بريد الجهة الإلكتروني',
        'org_address' => 'عنوان الجهة بالعربية',
        'org_address_en' => 'عنوان الجهة بالإنجليزية',
        'org_logo' => 'مسار شعار الجهة',
        'org_signature' => 'مسار التوقيع الإلكتروني للجهة',
        'incoming_prefix' => 'بادئة ترقيم الوارد',
        'outgoing_prefix' => 'بادئة ترقيم الصادر',
        'internal_prefix' => 'بادئة ترقيم المعاملات الداخلية',
        'circular_prefix' => 'بادئة ترقيم التعاميم الداخلية',
        'qr_scan_base_url' => 'عنوان أساس رابط QR (داخلي أو الدومين العام بعد الرفع)',
        'base_url' => 'رابط النظام (يُستنتج تلقائياً من الدومين — لا يُعدّل يدوياً)',
        'show_org_name_pages' => 'إظهار اسم الجهة في الصفحات (قديم)',
        'show_org_name_sidebar' => 'إظهار اسم الجهة في القائمة الجانبية (قديم)',
        'show_org_name_print' => 'إظهار اسم الجهة في التقارير والطباعة (قديم)',
        'show_org_name_ar' => 'إظهار الاسم العربي (قديم)',
        'show_org_name_en' => 'إظهار الاسم الإنجليزي (قديم)',
        'show_org_name_ar_pages' => 'إظهار الاسم العربي في الصفحات (هيدر / صفحات عامة)',
        'show_org_name_en_pages' => 'إظهار الاسم الإنجليزي في الصفحات (هيدر / صفحات عامة)',
        'show_org_name_ar_login' => 'إظهار الاسم العربي في صفحة الدخول واستعادة كلمة المرور',
        'show_org_name_en_login' => 'إظهار الاسم الإنجليزي في صفحة الدخول واستعادة كلمة المرور',
        'show_org_name_ar_sidebar' => 'إظهار الاسم العربي في القائمة الجانبية',
        'show_org_name_en_sidebar' => 'إظهار الاسم الإنجليزي في القائمة الجانبية',
        'show_org_name_ar_print' => 'إظهار الاسم العربي في التقارير والطباعة',
        'show_org_name_en_print' => 'إظهار الاسم الإنجليزي في التقارير والطباعة',
        'show_org_name_ar_verify' => 'إظهار الاسم العربي في صفحة التحقق ورد الحضور',
        'show_org_name_en_verify' => 'إظهار الاسم الإنجليزي في صفحة التحقق ورد الحضور',
        'session_timeout_minutes' => 'مدة الخمول بالدقائق قبل تسجيل الخروج التلقائي',
            'outgoing_receipt_alert_days' => 'أيام التنبيه لصادر بلا تسجيل استلام',
            'outgoing_approval_enabled' => 'تفعيل موافقة إصدار الصادر قبل الإرسال/الطباعة',
            'outgoing_approval_hours' => 'مهلة موافقة المعتمد الأول قبل التصعيد',
            'outgoing_approval_minutes' => 'مهلة موافقة المعتمد الأول بالدقائق قبل التصعيد',
            'outgoing_approval_unit' => 'وحدة عرض مهلة المعتمد الأول (دقائق/ساعات)',
            'outgoing_approval_deputy_minutes' => 'مهلة موافقة معتمد التصعيد بالدقائق قبل التحويل للبديل',
            'outgoing_approval_deputy_unit' => 'وحدة عرض مهلة معتمد التصعيد (دقائق/ساعات)',
            'outgoing_approver_sg_title' => 'مسمى المعتمد الأول لموافقة إصدار الصادر',
            'outgoing_approver_deputy_title' => 'مسمى المعتمد عند تصعيد موافقة الإصدار',
            'outgoing_approver_alternate_title' => 'مسمى المعتمد البديل لموافقة إصدار الصادر',
            'outgoing_approver_sg_title_en' => 'English title for primary approver',
            'outgoing_approver_deputy_title_en' => 'English title for escalation approver',
            'outgoing_approver_alternate_title_en' => 'English title for alternate approver',
            'outgoing_approver_sg_user_id' => 'مستخدم المعتمد الأول لموافقة إصدار الصادر',
            'outgoing_approver_deputy_user_id' => 'مستخدم المعتمد عند تصعيد موافقة الإصدار',
            'outgoing_approver_alternate_user_id' => 'مستخدم المعتمد البديل (استثناء) لموافقة إصدار الصادر',
            'storage_root' => 'جذر تخزين ملفات المعاملات فقط (فارغ = storage/uploads داخل المشروع؛ الشعار والصور تبقى في assets/uploads)',
            'archive_disk_root' => 'جذر تخزين مرفقات الأرشيف فقط (فارغ = G:/archives على ويندوز أو storage/archives داخل المشروع)',
        'print_signer_name' => 'اسم الموجِّه في أنموذج الاستلام (موجهة من)',
        'print_signer_title_1' => 'المنصب الأول للموجِّه في أنموذج الاستلام',
        'print_signer_title_2' => 'المنصب الثاني للموجِّه في أنموذج الاستلام',
        'print_signer_title_3' => 'المنصب الثالث للموجِّه في أنموذج الاستلام',
        'print_receipt_message' => 'نص تنبيه الاستلام في ورقة الطباعة',
        'letter_email_font' => 'نوع خط الخطاب في المعاينة والإرسال الإلكتروني',
    ];
}

function settings_desc_ar(string $key): string
{
    $map = settings_desc_ar_map();
    return $map[$key] ?? '';
}

/** إضافة عمود desc وملء الأوصاف العربية */
function ensure_settings_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        if (!dms_db_column_exists('tblsettings', 'desc')) {
            db()->exec("ALTER TABLE `tblsettings` ADD COLUMN `desc` VARCHAR(255) NULL DEFAULT NULL COMMENT 'وصف الإعداد بالعربية' AFTER setting_value");
        }
        $stmt = db()->prepare(
            "UPDATE `tblsettings` SET `desc` = ? WHERE setting_key = ? AND (`desc` IS NULL OR `desc` = '')"
        );
        foreach (settings_desc_ar_map() as $key => $desc) {
            $stmt->execute([$desc, $key]);
        }
    } catch (Throwable $e) {
        // قاعدة البيانات غير جاهزة بعد
    }
}

function set_setting(string $key, string $value, ?string $desc = null): void
{
    ensure_settings_schema();
    $descText = $desc !== null ? $desc : settings_desc_ar($key);
    $stmt = db()->prepare(
        "INSERT INTO `tblsettings` (setting_key, setting_value, `desc`) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
    );
    $stmt->execute([$key, $value, $descText !== '' ? $descText : null]);
    // إن وُجد صف بدون وصف، حدّث الوصف
    if ($descText !== '') {
        db()->prepare(
            "UPDATE `tblsettings` SET `desc` = ? WHERE setting_key = ? AND (`desc` IS NULL OR `desc` = '')"
        )->execute([$descText, $key]);
    }
    if (!isset($GLOBALS['_dms_settings_cache']) || !is_array($GLOBALS['_dms_settings_cache'])) {
        $GLOBALS['_dms_settings_cache'] = [];
    }
    $GLOBALS['_dms_settings_cache'][$key] = $value;
}

function clear_setting_cache(?string $key = null): void
{
    if ($key === null) {
        $GLOBALS['_dms_settings_cache'] = [];
        $GLOBALS['_dms_settings_warm'] = false;
        return;
    }
    unset($GLOBALS['_dms_settings_cache'][$key]);
}

/**
 * إعدادات الجهة (قابلة للتخصيص لأي مؤسسة)
 */
function org_name(): string
{
    $ar = setting('org_name_ar', '');
    if ($ar !== '') {
        return $ar;
    }
    return setting('org_name', 'مؤسسة الملك فيصل');
}

function org_name_ar(): string
{
    return org_name();
}

function org_name_en(): string
{
    return setting('org_name_en', 'King Faisal Foundation');
}

function org_phone(): string
{
    return setting('org_phone', '');
}

function org_email(): string
{
    return setting('org_email', '');
}

function org_address(): string
{
    return setting('org_address', '');
}

function org_address_en(): string
{
    return setting('org_address_en', '');
}

function dms_org_uploads_dir(): string
{
    return __DIR__ . '/../assets/uploads/organization';
}

function dms_org_asset_disk_path(string $rel): string
{
    return __DIR__ . '/../' . ltrim(str_replace('\\', '/', $rel), '/');
}

/**
 * ابحث عن ملف شعار موجود على القرص (الاسم الثابت أولاً ثم logo_* القديم).
 * يفيد بعد نسخ المجلد لجهاز آخر دون مزامنة مسار الإعداد في قاعدة البيانات.
 */
function dms_discover_org_logo_file(): string
{
    $dir = dms_org_uploads_dir();
    if (!is_dir($dir)) {
        return '';
    }
    foreach (['png', 'jpg', 'jpeg', 'webp', 'gif'] as $ext) {
        $full = $dir . DIRECTORY_SEPARATOR . 'logo.' . $ext;
        if (is_file($full)) {
            return 'assets/uploads/organization/logo.' . $ext;
        }
    }
    $best = '';
    $bestMtime = 0;
    foreach (scandir($dir) ?: [] as $name) {
        if (!preg_match('/^logo_\d+\.(png|jpe?g|webp|gif)$/i', $name)) {
            continue;
        }
        $full = $dir . DIRECTORY_SEPARATOR . $name;
        if (!is_file($full)) {
            continue;
        }
        $mtime = (int) filemtime($full);
        if ($mtime >= $bestMtime) {
            $bestMtime = $mtime;
            $best = 'assets/uploads/organization/' . $name;
        }
    }
    return $best;
}

function dms_discover_org_signature_file(): string
{
    $dir = dms_org_uploads_dir();
    if (!is_dir($dir)) {
        return '';
    }
    foreach (['png', 'jpg', 'jpeg', 'webp', 'gif'] as $ext) {
        $full = $dir . DIRECTORY_SEPARATOR . 'signature.' . $ext;
        if (is_file($full)) {
            return 'assets/uploads/organization/signature.' . $ext;
        }
    }
    $best = '';
    $bestMtime = 0;
    foreach (scandir($dir) ?: [] as $name) {
        // توقيع الجهة فقط (ليس توقيع الأقسام sign_d*)
        if (!preg_match('/^sign_\d+\.(png|jpe?g|webp|gif)$/i', $name)) {
            continue;
        }
        $full = $dir . DIRECTORY_SEPARATOR . $name;
        if (!is_file($full)) {
            continue;
        }
        $mtime = (int) filemtime($full);
        if ($mtime >= $bestMtime) {
            $bestMtime = $mtime;
            $best = 'assets/uploads/organization/' . $name;
        }
    }
    return $best;
}

function org_logo_path(): string
{
    $path = str_replace('\\', '/', trim(setting('org_logo', '')));
    if ($path !== '' && is_file(dms_org_asset_disk_path($path))) {
        return $path;
    }
    $found = dms_discover_org_logo_file();
    if ($found !== '' && $found !== $path) {
        try {
            set_setting('org_logo', $found);
        } catch (Throwable $e) {
            // تجاهل فشل الكتابة؛ أعِد المسار المكتشف للعرض
        }
        return $found;
    }
    return $path;
}

function org_logo_url(): string
{
    $path = org_logo_path();
    if ($path === '') {
        return '';
    }
    $url = function_exists('dms_web_path') ? dms_web_path($path) : base_url($path);
    $disk = dms_org_asset_disk_path($path);
    if (is_file($disk)) {
        $url .= (str_contains($url, '?') ? '&' : '?') . 'v=' . (int) filemtime($disk);
    }
    return $url;
}

function org_signature_path(): string
{
    $path = str_replace('\\', '/', trim(setting('org_signature', '')));
    if ($path !== '' && is_file(dms_org_asset_disk_path($path))) {
        return $path;
    }
    $found = dms_discover_org_signature_file();
    if ($found !== '' && $found !== $path) {
        try {
            set_setting('org_signature', $found);
        } catch (Throwable $e) {
            // تجاهل
        }
        return $found;
    }
    return $path;
}

function org_signature_url(): string
{
    $path = org_signature_path();
    if ($path === '') {
        return '';
    }
    $url = base_url($path);
    $disk = __DIR__ . '/../' . ltrim(str_replace('\\', '/', $path), '/');
    if (is_file($disk)) {
        $url .= (str_contains($url, '?') ? '&' : '?') . 'v=' . (int) filemtime($disk);
    }
    return $url;
}

/** مدة الخمول بالدقائق قبل الخروج التلقائي (5–1440) */
function session_timeout_minutes(): int
{
    $minutes = (int) setting('session_timeout_minutes', '30');
    if ($minutes < 5) {
        return 5;
    }
    if ($minutes > 1440) {
        return 1440;
    }
    return $minutes;
}

/** هل يُعرض الاسم بلغة معيّنة في سياق معيّن؟ context: pages | sidebar | print | login | verify */
function org_show_name_lang(string $lang, string $context = 'pages'): bool
{
    $lang = $lang === 'en' ? 'en' : 'ar';
    $context = in_array($context, ['pages', 'sidebar', 'print', 'login', 'verify'], true) ? $context : 'pages';
    return setting('show_org_name_' . $lang . '_' . $context, '1') === '1';
}

/**
 * الأسماء الظاهرة حسب الإعدادات ولغة الواجهة.
 * @return array{primary?:string,secondary?:string,lines?:list<array{text:string,dir:string}>}
 */
function org_visible_names(string $context = 'pages'): array
{
    $ar = org_show_name_lang('ar', $context) ? org_name_ar() : '';
    $en = org_show_name_lang('en', $context) ? org_name_en() : '';
    $out = [];

    if (dms_lang_code() === 'en') {
        if ($en !== '') {
            $out['primary'] = $en;
        }
        if ($ar !== '') {
            if (isset($out['primary'])) {
                $out['secondary'] = $ar;
            } else {
                $out['primary'] = $ar;
            }
        }
    } else {
        if ($ar !== '') {
            $out['primary'] = $ar;
        }
        if ($en !== '') {
            if (isset($out['primary'])) {
                $out['secondary'] = $en;
            } else {
                $out['primary'] = $en;
            }
        }
    }

    return $out;
}

/** طباعة كتلة اسم الجهة (عربي/إنجليزي) حسب الإعدادات */
function render_org_brand_text(string $context = 'pages', string $primaryClass = 'fw-semibold', string $secondaryClass = 'small text-muted'): void
{
    $names = org_visible_names($context);
    if (empty($names)) {
        return;
    }
    if (!empty($names['primary'])) {
        $isArabic = (bool) preg_match('/\p{Arabic}/u', $names['primary']);
        echo '<div class="' . e($primaryClass) . '" dir="' . ($isArabic ? 'rtl' : 'ltr') . '">' . e($names['primary']) . '</div>';
    }
    if (!empty($names['secondary'])) {
        $isArabic = (bool) preg_match('/\p{Arabic}/u', $names['secondary']);
        echo '<div class="' . e($secondaryClass) . '" dir="' . ($isArabic ? 'rtl' : 'ltr') . '">' . e($names['secondary']) . '</div>';
    }
}

/** شعار الجهة في منتصف أعلى صفحة الطباعة/التقرير */
function render_print_logo(string $context = 'print'): void
{
    echo '<div class="print-brand-top">';
    if (org_logo_url()) {
        echo '<img src="' . e(org_logo_url()) . '" alt="" class="print-org-logo">';
    } else {
        echo '<img src="' . e(base_url('assets/img/favicon.ico')) . '" alt="" class="print-system-icon">';
    }
    echo '<div class="print-brand-names">';
    render_org_brand_text($context, 'print-brand-primary', 'print-brand-secondary');
    echo '</div></div>';
}

/** سطر الطباعة: اسم من يطبع الآن · التاريخ · الوقت — محاذاة يسار */
function render_print_meta_footer(): void
{
    if (!function_exists('current_user')) {
        return;
    }
    $user = current_user();
    if (!$user) {
        return;
    }
    $name = user_actor_name($user);
    if ($name === '') {
        return;
    }
    $now = date('Y-m-d H:i:s');
    echo '<div class="print-meta-footer">';
    echo '<i class="bi bi-printer print-footer-icon" title="' . e(__('print.printed_by')) . '" aria-hidden="true"></i>';
    echo '<span class="print-meta-user">' . e($name) . '</span>';
    echo '<span class="print-meta-sep" aria-hidden="true"> · </span>';
    echo '<span class="print-meta-datetime" dir="ltr" data-print-clock="1">';
    echo e(format_date($now)) . ' · ' . e(date('H:i', strtotime($now)));
    echo '</span>';
    echo '</div>';
}

function print_correspondence_creator_name(array $corr): string
{
    $name = user_display_name($corr, 'creator_name', 'creator_name_en');
    if ($name !== '') {
        return $name;
    }
    $uid = (int) ($corr['created_by'] ?? 0);
    if ($uid > 0 && function_exists('admin_row')) {
        return user_display_name(admin_row('tblusers', $uid));
    }
    return '';
}

function print_correspondence_has_created_by(array $corr): bool
{
    return print_correspondence_creator_name($corr) !== '';
}

/** أُنشئ بواسطة : اسم من أنشأ المعاملة — يمين (التاريخ أعلى الورقة) */
function render_print_created_by(array $corr): void
{
    $name = print_correspondence_creator_name($corr);
    if ($name === '') {
        return;
    }

    $dir = function_exists('dms_is_rtl') && dms_is_rtl() ? 'rtl' : 'ltr';
    echo '<div class="print-created-by" dir="' . e($dir) . '">';
    echo '<span class="print-footer-icon print-footer-write" title="' . e(__('corr.created_by')) . '" aria-hidden="true">&#x270D;&#xFE0E;</span>';
    echo '<span class="print-created-by-label">' . e(__('corr.created_by')) . ' :</span> ';
    echo '<span class="print-created-by-name">' . e($name) . '</span>';
    echo '</div>';
}

function print_correspondence_closed_by_name(array $corr): string
{
    $name = user_display_name($corr, 'closed_by_name', 'closed_by_name_en');
    if ($name !== '') {
        return $name;
    }
    $uid = (int) ($corr['closed_by'] ?? 0);
    if ($uid > 0 && function_exists('admin_row')) {
        return user_display_name(admin_row('tblusers', $uid));
    }
    return '';
}

function print_correspondence_has_closed_by(array $corr): bool
{
    return print_correspondence_closed_by_name($corr) !== '';
}

/** أُقفل بواسطة : من أقفل المعاملة — لتذييل نموذج الإقفال */
function render_print_closed_by(array $corr): void
{
    $name = print_correspondence_closed_by_name($corr);
    if ($name === '') {
        return;
    }

    $dir = function_exists('dms_is_rtl') && dms_is_rtl() ? 'rtl' : 'ltr';
    echo '<div class="print-created-by print-closed-by" dir="' . e($dir) . '">';
    echo '<span class="print-footer-icon print-footer-write" title="' . e(__('close.closed_by')) . '" aria-hidden="true">&#x270D;&#xFE0E;</span>';
    echo '<span class="print-created-by-label">' . e(__('close.closed_by')) . ' :</span> ';
    echo '<span class="print-created-by-name">' . e($name) . '</span>';
    echo '</div>';
}

/**
 * تذييل موحّد لجميع صفحات الطباعة والتقارير (الحالية والمستقبلية).
 *
 * يُطبَع في أسفل كل صفحة (@media print — position: fixed):
 * 1) يسار: اسم مستخدم الطباعة · التاريخ · الوقت
 *    يمين (معاملة صادر/وارد): أُنشئ بواسطة : المنشئ
 *    يمين (نموذج إقفال، side=closed): أُقفل بواسطة : من أقفل
 * 2) سطر تواصل المؤسسة (عنوان | هاتف | بريد) — في المنتصف
 *
 * @param array{side?:string} $opts side=created|closed
 * @see render_print_logo() للترويسة
 * @see assets/css/app.css — .print-page-footer
 */
function render_print_page_footer(?array $corr = null, array $opts = []): void
{
    $phone = org_phone();
    $email = org_email();
    $addrAr = org_address();
    $addrEn = org_address_en();
    $side = ($opts['side'] ?? 'created') === 'closed' ? 'closed' : 'created';
    $showUsers = ($opts['show_users'] ?? true) !== false;

    $parts = [];
    if ($addrAr !== '') {
        $parts[] = '<span class="print-contact-address" dir="rtl">' . e($addrAr) . '</span>';
    }
    if ($addrEn !== '') {
        $parts[] = '<span class="print-contact-address" dir="ltr">' . e($addrEn) . '</span>';
    }
    if ($phone !== '') {
        $parts[] = '<span class="print-contact-item" dir="ltr"><i class="bi bi-telephone-fill" aria-hidden="true"></i>' . e($phone) . '</span>';
    }
    if ($email !== '') {
        $parts[] = '<span class="print-contact-item" dir="ltr"><i class="bi bi-envelope-fill" aria-hidden="true"></i>' . e($email) . '</span>';
    }

    $hasContact = $parts !== [];
    $showSide = false;
    if ($showUsers && $corr) {
        $showSide = $side === 'closed'
            ? print_correspondence_has_closed_by($corr)
            : print_correspondence_has_created_by($corr);
    }
    $hasMeta = $showUsers && function_exists('current_user') && current_user() && user_actor_name(current_user()) !== '';
    if (!$hasContact && !$hasMeta && !$showSide) {
        return;
    }

    echo '<div class="print-page-footer">';
    if ($hasMeta && $showSide) {
        echo '<div class="print-footer-users">';
        render_print_meta_footer();
        if ($side === 'closed') {
            render_print_closed_by($corr);
        } else {
            render_print_created_by($corr);
        }
        echo '</div>';
    } elseif ($showSide) {
        if ($side === 'closed') {
            render_print_closed_by($corr);
        } else {
            render_print_created_by($corr);
        }
    } elseif ($hasMeta) {
        render_print_meta_footer();
    }
    if ($hasContact) {
        echo '<div class="print-contact">';
        echo '<div class="print-contact-line">';
        echo implode('<span class="print-contact-sep">|</span>', $parts);
        echo '</div></div>';
    }
    echo '</div>';
}

/** @deprecated استخدم render_print_page_footer() */
function render_print_contact(): void
{
    render_print_page_footer();
}

function print_signer_name(): string
{
    $name = trim(setting('print_signer_name', ''));
    return $name !== '' ? $name : org_name_ar();
}

/** جهة العمل في الطباعة: اسم الجهة من الإعدادات وليس اسم القسم */
function print_org_work_name(): string
{
    $ar = trim(org_name_ar());
    $en = trim(org_name_en());
    if (function_exists('dms_lang_code') && dms_lang_code() === 'en') {
        return $en !== '' ? $en : $ar;
    }
    return $ar !== '' ? $ar : $en;
}

function print_signer_titles(): array
{
    $titles = [];
    foreach (['print_signer_title_1', 'print_signer_title_2', 'print_signer_title_3'] as $key) {
        $t = trim(setting($key, ''));
        if ($t !== '') {
            $titles[] = $t;
        }
    }
    return $titles;
}

function print_receipt_message(): string
{
    $msg = trim(setting('print_receipt_message', ''));
    if ($msg !== '') {
        return $msg;
    }
    return "أخي الكريم ..\nقبل التوقيع أدناه باستلامك للمعاملة المرفقة ...\nأرجو التأكد من مطابقة بياناتها مع ما هو مدون في هذا الأنموذج";
}

/**
 * طرفا المعاملة كما يظهران في «موجهة إلى / موجهة من»
 * صادر: إلى = الجهة الخارجية ، من = القسم (الشخص/الوظيفة) + جهة العمل من الإعدادات
 * وارد: العكس
 *
 * يُعرض كل طرف كحقلين مثل قائمة الجهات: اللقب ثم الاسم، والوظيفة ثم جهة العمل.
 * التكريم يُفصل عن الاسم ليُطبع بخط أصغر.
 *
 * @return array{to: array{prefix: string, person: string, honorific: string, job: string, org: string, titles: string[]}, from: array{prefix: string, person: string, honorific: string, job: string, org: string, titles: string[]}}
 */
function print_direction_parts(array $corr): array
{
    if (($corr['type'] ?? '') === 'internal') {
        $route = function_exists('correspondence_internal_route_names')
            ? correspondence_internal_route_names($corr)
            : ['from' => '', 'to' => ''];
        $toName = (string) ($route['to'] ?? '');
        $fromName = (string) ($route['from'] ?? '');
        $org = print_org_work_name();

        // القسم = جهة العمل؛ الاسم/اللقب/الوظيفة فقط إن وُجدت — بلا بدائل
        $side = static function (string $deptName): array {
            return [
                'prefix' => '',
                'person' => '',
                'honorific' => '',
                'job' => '',
                'org' => $deptName,
                'titles' => [],
            ];
        };

        $toSide = $side($toName);
        if (correspondence_has_party_snapshot($corr)) {
            $archivedTo = correspondence_archived_party($corr);
            $toPerson = trim((string) ($archivedTo['person'] ?? ''));
            $toOrg = trim((string) ($archivedTo['org'] ?? ''));
            $toPrefix = trim((string) ($archivedTo['prefix'] ?? ''));
            $toJob = trim((string) ($archivedTo['job'] ?? ''));
            if ($toPerson !== '' || $toOrg !== '' || $toPrefix !== '' || $toJob !== '') {
                if ($toOrg !== '' && $toPerson !== '' && $toOrg === $toPerson) {
                    $toOrg = '';
                }
                $toSide = [
                    'prefix' => $toPrefix,
                    'person' => $toPerson,
                    'honorific' => (string) ($archivedTo['honorific'] ?? ''),
                    'job' => $toJob,
                    // جهة العمل = اسم القسم فقط إن وُجد في اللقطة، دون إسقاط الاسم مكانه
                    'org' => $toOrg !== '' ? $toOrg : $toName,
                    'titles' => [],
                ];
            }
        }

        $fromSide = $side($fromName);
        $archivedFrom = correspondence_archived_from($corr);
        $fromPerson = trim((string) ($archivedFrom['person'] ?? ''));
        $fromPrefix = trim((string) ($archivedFrom['prefix'] ?? ''));
        $fromJob = trim((string) ($archivedFrom['job'] ?? ''));
        $fromOrg = trim((string) ($archivedFrom['org'] ?? ''));
        if ($fromPerson !== '' || $fromPrefix !== '' || $fromJob !== '' || $fromOrg !== '') {
            if ($fromOrg !== '' && $fromPerson !== '' && $fromOrg === $fromPerson) {
                $fromOrg = '';
            }
            $fromSide = [
                'prefix' => $fromPrefix,
                'person' => $fromPerson,
                'honorific' => '',
                'job' => $fromJob,
                'org' => $fromOrg !== '' ? $fromOrg : ($fromName !== '' ? $fromName : $org),
                'titles' => [],
            ];
        }
        return ['to' => $toSide, 'from' => $fromSide];
    }

    $isOutgoing = ($corr['type'] ?? '') === 'outgoing';
    $party = correspondence_archived_party($corr);
    $externalSide = [
        'prefix' => $party['prefix'],
        'person' => $party['person'],
        'honorific' => $party['honorific'],
        'job' => $party['job'],
        'org' => $party['org'],
        'titles' => [],
    ];

    $archivedFrom = correspondence_archived_from($corr);
    if (trim((string) ($archivedFrom['person'] ?? '')) !== ''
        || trim((string) ($archivedFrom['prefix'] ?? '')) !== ''
        || trim((string) ($archivedFrom['job'] ?? '')) !== '') {
        $internalSide = [
            'prefix' => $archivedFrom['prefix'],
            'person' => $archivedFrom['person'],
            'honorific' => '',
            'job' => $archivedFrom['job'],
            'org' => $archivedFrom['org'],
            'titles' => [],
        ];
    } else {
        // بلا موجّه في القسم: تظهر جهة العمل فقط — يُضبط الموجّه من بطاقة القسم
        $internalSide = [
            'prefix' => '',
            'person' => '',
            'honorific' => '',
            'job' => '',
            'org' => '',
            'titles' => [],
        ];
    }

    $internalOrg = print_org_work_name();
    if ($internalOrg !== '') {
        $internalSide['org'] = $internalOrg;
    }

    return $isOutgoing
        ? ['to' => $externalSide, 'from' => $internalSide]
        : ['to' => $internalSide, 'from' => $externalSide];
}

function print_direction_part_has_content(array $part): bool
{
    foreach (['prefix', 'person', 'honorific', 'job', 'org'] as $key) {
        if (trim((string) ($part[$key] ?? '')) !== '') {
            return true;
        }
    }
    foreach ($part['titles'] ?? [] as $title) {
        if (trim((string) $title) !== '') {
            return true;
        }
    }
    return false;
}

/** عرض طرف التوجيه بنفس أسلوب قائمة الجهات: لقب/اسم ثم وظيفة/جهة عمل */
function render_print_party_stack(array $part): void
{
    $prefix = trim((string) ($part['prefix'] ?? ''));
    $person = trim((string) ($part['person'] ?? ''));
    $honorific = trim((string) ($part['honorific'] ?? ''));
    $job = trim((string) ($part['job'] ?? ''));
    $org = trim((string) ($part['org'] ?? ''));
    if ($org !== '' && $person !== '' && $org === $person) {
        $org = '';
    }
    $titles = [];
    foreach ($part['titles'] ?? [] as $title) {
        $title = trim((string) $title);
        if ($title !== '') {
            $titles[] = $title;
        }
    }

    $nameBits = [];
    if ($prefix !== '') {
        $nameBits[] = $prefix;
    }
    if ($person !== '') {
        $nameBits[] = $person;
    }
    $nameLine = trim(implode(' ', $nameBits));

    echo '<div class="print-dir-stack">';
    if ($nameLine !== '' || $honorific !== '') {
        echo '<div class="print-dir-name">';
        echo e($nameLine);
        if ($honorific !== '') {
            echo ($nameLine !== '' ? ' ' : '') . '<span class="print-honorific">' . e($honorific) . '</span>';
        }
        echo '</div>';
    }
    if ($job !== '') {
        echo '<div class="print-dir-job">' . e($job) . '</div>';
    }
    if ($org !== '') {
        echo '<div class="print-dir-org">' . e($org) . '</div>';
    }
    foreach ($titles as $title) {
        echo '<div class="print-dir-job">' . e($title) . '</div>';
    }
    echo '</div>';
}

/**
 * بطاقة «موجهة إلى / موجهة من» المستقلة على ورقة الطباعة.
 * $swap يقدّم «موجهة من» حين يكون المرسِل أعلى مقاماً من المرسَل إليه.
 */
function render_print_direction(array $corr, bool $swap = false, string $tableId = 'printDirection'): void
{
    $parts = print_direction_parts($corr);
    $labels = ['to' => __('print.directed_to'), 'from' => __('print.directed_from')];
    $keys = $swap ? ['from', 'to'] : ['to', 'from'];
    $keys = array_values(array_filter(
        $keys,
        static fn(string $key): bool => print_direction_part_has_content($parts[$key] ?? [])
    ));
    if (!$keys) {
        return;
    }

    $tableId = preg_replace('/[^A-Za-z0-9_-]/', '', $tableId) ?: 'printDirection';
    echo '<table class="table table-bordered print-direction" id="' . e($tableId) . '">';
    foreach ($keys as $key) {
        echo '<tr data-dir-row="' . e($key) . '">';
        echo '<th>' . e($labels[$key]) . '</th><td>';
        render_print_party_stack($parts[$key]);
        echo '</td></tr>';
    }
    echo '</table>';
}

/**
 * أنموذج استلام على ورقة الطباعة
 * التوجيه يُطبع مستقلاً عبر render_print_direction() قبل هذا الأنموذج.
 */
function render_print_receipt(array $corr): void
{
    echo '<div class="print-receipt">';
    echo '<div class="print-receipt-msg">' . e(print_receipt_message()) . '</div>';

    $hasReceipt = correspondence_has_receipt($corr);
    echo '<table class="table table-bordered print-receipt-sign">';
    echo '<thead><tr>';
    echo '<th>' . e(__('print.receiver_name')) . '</th>';
    echo '<th>' . e(__('print.receive_date')) . '</th>';
    echo '<th>' . e(__('print.receiver_phone')) . '</th>';
    echo '<th>' . e(__('print.signature')) . '</th>';
    echo '</tr></thead><tbody><tr>';
    if ($hasReceipt) {
        echo '<td>' . e((string) $corr['receipt_name']) . '</td>';
        echo '<td>' . e(!empty($corr['receipt_date']) ? format_date($corr['receipt_date']) : '') . '</td>';
        echo '<td dir="ltr">' . e((string) ($corr['receipt_phone'] ?? '')) . '</td>';
        echo '<td class="print-sign-cell text-muted small">' . e(!empty($corr['receipt_file_path']) ? __('receipt.slip_attached') : ' ') . '</td>';
    } else {
        echo '<td class="print-sign-cell">&nbsp;</td>';
        echo '<td class="print-sign-cell">&nbsp;</td>';
        echo '<td class="print-sign-cell">&nbsp;</td>';
        echo '<td class="print-sign-cell">&nbsp;</td>';
    }
    echo '</tr></tbody></table>';
    echo '</div>';
}

function ensure_org_settings(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        ensure_settings_schema();
        $legacyPages = setting('show_org_name_pages', '1');
        $legacySidebar = setting('show_org_name_sidebar', '1');
        $legacyPrint = setting('show_org_name_print', '1');

        $defaults = [
            'org_name_ar' => setting('org_name', 'مؤسسة الملك فيصل'),
            'org_name_en' => 'King Faisal Foundation',
            'org_phone' => '',
            'org_email' => '',
            'org_address' => '',
            'org_address_en' => '',
            'org_logo' => '',
            'org_signature' => '',
            'show_org_name_ar_pages' => $legacyPages,
            'show_org_name_en_pages' => $legacyPages,
            'show_org_name_ar_login' => $legacyPages,
            'show_org_name_en_login' => $legacyPages,
            'show_org_name_ar_sidebar' => $legacySidebar,
            'show_org_name_en_sidebar' => $legacySidebar,
            'show_org_name_ar_print' => $legacyPrint,
            'show_org_name_en_print' => $legacyPrint,
            'show_org_name_ar_verify' => $legacyPages,
            'show_org_name_en_verify' => $legacyPages,
            'session_timeout_minutes' => '30',
            'outgoing_receipt_alert_days' => '7',
            'outgoing_approval_enabled' => '1',
            'outgoing_prep_enabled' => '1',
            'outgoing_approval_hours' => '2',
            'outgoing_approval_minutes' => '',
            'outgoing_approval_unit' => 'hours',
            'outgoing_approval_deputy_minutes' => '',
            'outgoing_approval_deputy_unit' => 'hours',
            'outgoing_approver_sg_title' => '',
            'outgoing_approver_deputy_title' => '',
            'outgoing_approver_alternate_title' => '',
            'outgoing_approver_sg_title_en' => '',
            'outgoing_approver_deputy_title_en' => '',
            'outgoing_approver_alternate_title_en' => '',
            'outgoing_approver_sg_user_id' => '0',
            'outgoing_approver_deputy_user_id' => '0',
            'outgoing_approver_alternate_user_id' => '0',
            'circular_prefix' => '',
            'internal_prefix' => '',
            'incoming_prefix' => '',
            'archive_prefix' => '',
            'numbering_year_mode' => 'hijri',
            'qr_scan_base_url' => '',
            'storage_root' => '',
            'archive_disk_root' => '',
            'print_signer_name' => '',
            'print_signer_title_1' => '',
            'print_signer_title_2' => '',
            'print_signer_title_3' => '',
            'print_receipt_message' => "أخي الكريم ..\nقبل التوقيع أدناه باستلامك للمعاملة المرفقة ...\nأرجو التأكد من مطابقة بياناتها مع ما هو مدون في هذا الأنموذج",
            'letter_email_font' => 'arial',
        ];
        foreach ($defaults as $key => $value) {
            dms_settings_warmup();
            if (!array_key_exists($key, $GLOBALS['_dms_settings_cache'] ?? [])) {
                set_setting($key, $value);
            }
        }
        ensure_departments_schema();
        if (function_exists('ensure_archive_schema')) {
            // يُحمَّل لاحقاً عند الحاجة
        }
    } catch (Throwable $e) {
        // قاعدة البيانات غير جاهزة بعد
    }
}

/**
 * إصدار ترحيل المخطط — زِد الرقم عند إضافة ensure_* جديدة تحتاج التشغيل مرة.
 * عند التطابق يُتخطى فحص SHOW TABLES / ALTER في كل طلب (تحسين أداء السيرفر).
 */
const DMS_SCHEMA_BOOTSTRAP_VERSION = '20261010.2';

function dms_schema_bootstrap_flag_path(): string
{
    return dirname(__DIR__) . '/storage/schema_bootstrap.version';
}

function dms_schema_bootstrap_is_current(): bool
{
    $path = dms_schema_bootstrap_flag_path();
    if (!is_file($path)) {
        return false;
    }
    return trim((string) @file_get_contents($path)) === DMS_SCHEMA_BOOTSTRAP_VERSION;
}

function dms_schema_bootstrap_mark_current(): void
{
    $path = dms_schema_bootstrap_flag_path();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    @file_put_contents($path, DMS_SCHEMA_BOOTSTRAP_VERSION);
}

/** تشغيل ترحيلات المخطط مرة عند الحاجة فقط — وليس في كل طلب صفحة */
function dms_run_schema_bootstrap(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    if (dms_schema_bootstrap_is_current()) {
        return;
    }

    ensure_core_tables();
    ensure_org_settings();
    if (function_exists('dms_storage_bootstrap')) {
        dms_storage_bootstrap();
    }
    ensure_lookups_schema();
    ensure_archive_schema();
    ensure_security_schema();
    ensure_correspondence_receipt_schema();
    if (is_file(__DIR__ . '/couriers.php')) {
        require_once __DIR__ . '/couriers.php';
        if (function_exists('ensure_outgoing_courier_schema')) {
            ensure_outgoing_courier_schema();
        }
    }
    ensure_users_profile_schema();
    if (function_exists('ensure_user_departments_schema')) {
        ensure_user_departments_schema();
    }
    if (function_exists('ensure_user_coverage_schema')) {
        ensure_user_coverage_schema();
    }
    ensure_entities_schema();
    ensure_entity_groups_schema();
    if (function_exists('ensure_whatsapp_settings_table')) {
        ensure_whatsapp_settings_table();
    }
    if (function_exists('ensure_email_settings_table')) {
        ensure_email_settings_table();
    }
    if (function_exists('ensure_corr_deliveries_schema')) {
        ensure_corr_deliveries_schema();
    }
    if (function_exists('ensure_notifications_schema')) {
        ensure_notifications_schema();
    }
    if (function_exists('ensure_letter_templates_schema')) {
        ensure_letter_templates_schema();
    }
    if (function_exists('ensure_briefing_schema')) {
        ensure_briefing_schema();
    }
    if (function_exists('ensure_circulars_schema')) {
        ensure_circulars_schema();
    }
    if (function_exists('ensure_internal_correspondence_schema')) {
        ensure_internal_correspondence_schema();
    }
    if (function_exists('ensure_outgoing_approval_schema')) {
        ensure_outgoing_approval_schema();
    }
    if (function_exists('ensure_outgoing_prep_schema')) {
        ensure_outgoing_prep_schema();
    }
    if (function_exists('ensure_corr_letter_schema')) {
        ensure_corr_letter_schema();
    }
    if (function_exists('ensure_corr_cc_schema')) {
        ensure_corr_cc_schema();
    }
    // لا تشغّل dms_purge_foreign_key_orphans هنا: مسح information_schema لكل القيود
    // يعلّق صفحة الدخول/الرئيسية على XAMPP، وقد يحذف صفوفاً. التنظيف أداة استيراد فقط.

    dms_schema_bootstrap_mark_current();
}

function dms_sql_ident(string $name): string
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
        throw new InvalidArgumentException('invalid identifier');
    }
    return '`' . $name . '`';
}

/**
 * يحذف أو يصفّر المراجع اليتيمة حتى لا يفشل استيراد phpMyAdmin عند ADD CONSTRAINT.
 *
 * @return list<array{table:string,column:string,parent:string,action:string,rows:int,error?:string}>
 */
function dms_purge_foreign_key_orphans(): array
{
    try {
        $pdo = db();
        $fks = $pdo->query(
            "SELECT
                kcu.TABLE_NAME AS child_table,
                kcu.COLUMN_NAME AS child_col,
                kcu.REFERENCED_TABLE_NAME AS parent_table,
                kcu.REFERENCED_COLUMN_NAME AS parent_col,
                rc.DELETE_RULE AS delete_rule,
                col.IS_NULLABLE AS is_nullable
             FROM information_schema.KEY_COLUMN_USAGE kcu
             INNER JOIN information_schema.REFERENTIAL_CONSTRAINTS rc
                ON rc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA
               AND rc.TABLE_NAME = kcu.TABLE_NAME
               AND rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
             INNER JOIN information_schema.COLUMNS col
                ON col.TABLE_SCHEMA = kcu.TABLE_SCHEMA
               AND col.TABLE_NAME = kcu.TABLE_NAME
               AND col.COLUMN_NAME = kcu.COLUMN_NAME
             WHERE kcu.CONSTRAINT_SCHEMA = DATABASE()
               AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
             ORDER BY kcu.TABLE_NAME, kcu.ORDINAL_POSITION"
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }

    $report = [];
    foreach ($fks as $fk) {
        $childName = (string) ($fk['child_table'] ?? '');
        $colName = (string) ($fk['child_col'] ?? '');
        $parentName = (string) ($fk['parent_table'] ?? '');
        $prefName = (string) ($fk['parent_col'] ?? '');
        if ($childName === '' || $colName === '' || $parentName === '' || $prefName === '') {
            continue;
        }
        try {
            $child = dms_sql_ident($childName);
            $col = dms_sql_ident($colName);
            $parent = dms_sql_ident($parentName);
            $pref = dms_sql_ident($prefName);
            $count = (int) $pdo->query(
                "SELECT COUNT(*) FROM {$child} c
                 LEFT JOIN {$parent} p ON p.{$pref} = c.{$col}
                 WHERE c.{$col} IS NOT NULL AND p.{$pref} IS NULL"
            )->fetchColumn();
            if ($count < 1) {
                continue;
            }
            $setNull = strtoupper((string) ($fk['delete_rule'] ?? '')) === 'SET NULL'
                && strtoupper((string) ($fk['is_nullable'] ?? '')) === 'YES';
            if ($setNull) {
                $pdo->exec(
                    "UPDATE {$child} c
                     LEFT JOIN {$parent} p ON p.{$pref} = c.{$col}
                     SET c.{$col} = NULL
                     WHERE c.{$col} IS NOT NULL AND p.{$pref} IS NULL"
                );
                $action = 'null';
            } else {
                $pdo->exec(
                    "DELETE c FROM {$child} c
                     LEFT JOIN {$parent} p ON p.{$pref} = c.{$col}
                     WHERE c.{$col} IS NOT NULL AND p.{$pref} IS NULL"
                );
                $action = 'delete';
            }
            $report[] = [
                'table' => $childName,
                'column' => $colName,
                'parent' => $parentName,
                'action' => $action,
                'rows' => $count,
            ];
        } catch (Throwable $e) {
            $report[] = [
                'table' => $childName,
                'column' => $colName,
                'parent' => $parentName,
                'action' => 'skip',
                'rows' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }
    return $report;
}

/** جداول أساسية قد تكون ناقصة بعد تثبيت جزئي */
function ensure_core_tables(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        dms_db_normalize_table_names();
        $pdo = db();
        $existing = dms_db_tables_map();

        if (empty($existing['tbldocuments'])) {
            $pdo->exec(
                "CREATE TABLE `tbldocuments` (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    correspondence_id INT UNSIGNED NOT NULL,
                    file_name VARCHAR(255) NOT NULL,
                    file_path VARCHAR(500) NOT NULL,
                    file_type VARCHAR(50) DEFAULT NULL,
                    file_size INT UNSIGNED DEFAULT 0,
                    uploaded_by INT UNSIGNED NOT NULL,
                    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    CONSTRAINT fk_docs_corr FOREIGN KEY (correspondence_id) REFERENCES `tblcorrespondence`(id) ON DELETE CASCADE,
                    CONSTRAINT fk_docs_user FOREIGN KEY (uploaded_by) REFERENCES `tblusers`(id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        if (empty($existing['tblreferrals'])) {
            $pdo->exec(
                "CREATE TABLE `tblreferrals` (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    correspondence_id INT UNSIGNED NOT NULL,
                    from_department_id INT UNSIGNED DEFAULT NULL,
                    to_department_id INT UNSIGNED NOT NULL,
                    from_user_id INT UNSIGNED NOT NULL,
                    notes TEXT DEFAULT NULL,
                    status ENUM('pending','received','completed','returned') NOT NULL DEFAULT 'pending',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    CONSTRAINT fk_ref_corr FOREIGN KEY (correspondence_id) REFERENCES `tblcorrespondence`(id) ON DELETE CASCADE,
                    CONSTRAINT fk_ref_from_dept FOREIGN KEY (from_department_id) REFERENCES `tbldepartments`(id) ON DELETE SET NULL,
                    CONSTRAINT fk_ref_to_dept FOREIGN KEY (to_department_id) REFERENCES `tbldepartments`(id) ON DELETE RESTRICT,
                    CONSTRAINT fk_ref_user FOREIGN KEY (from_user_id) REFERENCES `tblusers`(id) ON DELETE RESTRICT,
                    INDEX idx_ref_status (status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        if (empty($existing['tblactivity_log'])) {
            $pdo->exec(
                "CREATE TABLE `tblactivity_log` (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    user_id INT UNSIGNED DEFAULT NULL,
                    correspondence_id INT UNSIGNED DEFAULT NULL,
                    action VARCHAR(100) NOT NULL,
                    details TEXT DEFAULT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    CONSTRAINT fk_log_user FOREIGN KEY (user_id) REFERENCES `tblusers`(id) ON DELETE SET NULL,
                    CONSTRAINT fk_log_corr FOREIGN KEY (correspondence_id) REFERENCES `tblcorrespondence`(id) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }
        dms_db_tables_map(true);
    } catch (Throwable $e) {
        // قاعدة غير جاهزة
    }
}

/** إضافة عمود الاسم الإنجليزي و«موجه من» للأقسام إن لم يكونا موجودين */
function ensure_departments_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $cols = dms_db_column_map('tbldepartments');
        if (empty($cols['name_en'])) {
            db()->exec('ALTER TABLE `tbldepartments` ADD COLUMN name_en VARCHAR(150) NULL AFTER name');
        }
        if (empty($cols['directed_from'])) {
            db()->exec("ALTER TABLE `tbldepartments` ADD COLUMN directed_from VARCHAR(255) NULL DEFAULT NULL COMMENT 'موجه من' AFTER code");
        }
        if (empty($cols['title_prefix'])) {
            db()->exec("ALTER TABLE `tbldepartments` ADD COLUMN title_prefix VARCHAR(255) NULL DEFAULT NULL COMMENT 'اللقب' AFTER code");
        }
        if (empty($cols['job_title'])) {
            db()->exec("ALTER TABLE `tbldepartments` ADD COLUMN job_title VARCHAR(255) NULL DEFAULT NULL COMMENT 'الوظيفة' AFTER directed_from");
        }
        if (empty($cols['principal_user_id'])) {
            db()->exec(
                "ALTER TABLE `tbldepartments`
                 ADD COLUMN `principal_user_id` INT UNSIGNED NULL DEFAULT NULL
                 COMMENT 'صاحب الشأن'
                 AFTER job_title"
            );
        }
        if (empty($cols['incoming_prefix'])) {
            db()->exec("ALTER TABLE `tbldepartments` ADD COLUMN incoming_prefix VARCHAR(20) NULL DEFAULT NULL COMMENT 'بادئة وارد خاصة' AFTER job_title");
        }
        if (empty($cols['outgoing_prefix'])) {
            db()->exec("ALTER TABLE `tbldepartments` ADD COLUMN outgoing_prefix VARCHAR(20) NULL DEFAULT NULL COMMENT 'بادئة صادر خاصة' AFTER incoming_prefix");
        }
        if (empty($cols['signature_path'])) {
            db()->exec("ALTER TABLE `tbldepartments` ADD COLUMN signature_path VARCHAR(255) NULL DEFAULT NULL COMMENT 'التوقيع الإلكتروني للقسم' AFTER job_title");
        }
        if (empty($cols['is_signing_office'])) {
            db()->exec(
                "ALTER TABLE `tbldepartments`
                 ADD COLUMN `is_signing_office` TINYINT(1) NOT NULL DEFAULT 0
                 COMMENT 'مكتب توقيع يمكن اختياره كموجهة من'
                 AFTER `is_active`"
            );
        }
        if (empty($cols['sort_order'])) {
            db()->exec(
                "ALTER TABLE `tbldepartments`
                 ADD COLUMN `sort_order` INT NOT NULL DEFAULT 0
                 COMMENT 'ترتيب العرض'
                 AFTER `code`"
            );
            try {
                db()->exec('UPDATE `tbldepartments` SET sort_order = id * 10 WHERE sort_order = 0');
            } catch (Throwable $e) {
                // تجاهل
            }
        }
        department_migrate_title_prefix_from_directed_from(db());
        ensure_sequences_schema();
        if (function_exists('ensure_correspondence_issuing_schema')) {
            ensure_correspondence_issuing_schema();
        }
    } catch (Throwable $e) {
        // تجاهل
    }
}

/** إنشاء/ترحيل جدول جهات المراسلة `tblentities` */
function ensure_entities_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $pdo = db();
        $hasEntities = dms_db_table_exists('tblentities');
        $orgIdent = dms_db_tables_map()['tblorganizations'] ?? null;
        $hasOrganizations = $orgIdent !== null;

        if (!$hasEntities && $hasOrganizations) {
            $pdo->exec('RENAME TABLE `' . str_replace('`', '', (string) $orgIdent) . '` TO `tblentities`');
            dms_db_tables_map(true);
            $hasEntities = true;
        }

        if (!$hasEntities) {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS `tblentities` (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    name_ar VARCHAR(255) NOT NULL,
                    name_en VARCHAR(255) DEFAULT NULL,
                    directed_to VARCHAR(255) DEFAULT NULL COMMENT 'الاسم في الخطاب',
                    title_prefix VARCHAR(255) DEFAULT NULL COMMENT 'اللقب مثل سعادة أو معالي',
                    job_title VARCHAR(255) DEFAULT NULL COMMENT 'الوظيفة',
                    honorific VARCHAR(100) DEFAULT NULL COMMENT 'التكريم مثل حفظه الله',
                    phone VARCHAR(50) DEFAULT NULL,
                    mobile VARCHAR(50) DEFAULT NULL COMMENT 'الجوال لإرسال واتساب',
                    email VARCHAR(150) DEFAULT NULL,
                    address VARCHAR(500) DEFAULT NULL COMMENT 'العنوان',
                    location VARCHAR(255) DEFAULT NULL COMMENT 'موقع الخريطة (Google Maps)',
                    notes TEXT DEFAULT NULL COMMENT 'ملاحظات',
                    department_id INT UNSIGNED DEFAULT NULL COMMENT 'NULL = جهة عامة',
                    sort_order INT NOT NULL DEFAULT 0 COMMENT 'ترتيب العرض',
                    is_active TINYINT(1) NOT NULL DEFAULT 1,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_entities_department (department_id),
                    CONSTRAINT fk_entities_department
                        FOREIGN KEY (department_id) REFERENCES `tbldepartments`(id) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        $cols = dms_db_column_map('tblentities');
        if (empty($cols['directed_to'])) {
            $pdo->exec("ALTER TABLE `tblentities` ADD COLUMN directed_to VARCHAR(255) NULL DEFAULT NULL COMMENT 'الاسم في الخطاب' AFTER name_en");
        }
        if (empty($cols['title_prefix'])) {
            $pdo->exec("ALTER TABLE `tblentities` ADD COLUMN title_prefix VARCHAR(255) NULL DEFAULT NULL COMMENT 'اللقب' AFTER directed_to");
        }
        if (empty($cols['job_title'])) {
            $after = empty($cols['title_prefix']) ? 'directed_to' : 'title_prefix';
            $pdo->exec("ALTER TABLE `tblentities` ADD COLUMN job_title VARCHAR(255) NULL DEFAULT NULL COMMENT 'الوظيفة' AFTER `$after`");
        }
        if (empty($cols['honorific'])) {
            $pdo->exec("ALTER TABLE `tblentities` ADD COLUMN honorific VARCHAR(100) NULL DEFAULT NULL COMMENT 'التكريم مثل حفظه الله' AFTER job_title");
        }
        entity_migrate_title_prefix_from_directed_to($pdo);
        if (empty($cols['mobile'])) {
            $after = empty($cols['phone']) ? 'honorific' : 'phone';
            $pdo->exec("ALTER TABLE `tblentities` ADD COLUMN mobile VARCHAR(50) NULL DEFAULT NULL COMMENT 'الجوال لإرسال واتساب' AFTER `$after`");
            try {
                $pdo->exec("UPDATE `tblentities` SET mobile = phone WHERE (mobile IS NULL OR mobile = '') AND phone IS NOT NULL AND phone <> ''");
            } catch (Throwable $e) {
                // تجاهل إن لم يكتمل النسخ
            }
        }
        if (empty($cols['department_id'])) {
            // البيانات السابقة تبقى عامة للحفاظ على سلوك النظام الحالي.
            $pdo->exec("ALTER TABLE `tblentities` ADD COLUMN department_id INT UNSIGNED NULL DEFAULT NULL COMMENT 'NULL = جهة عامة' AFTER email");
            $cols['department_id'] = true;
        }
        if (empty($cols['sort_order'])) {
            $after = !empty($cols['department_id']) ? 'department_id' : 'name_ar';
            $pdo->exec("ALTER TABLE `tblentities` ADD COLUMN sort_order INT NOT NULL DEFAULT 0 COMMENT 'ترتيب العرض' AFTER `$after`");
            try {
                $pdo->exec('UPDATE `tblentities` SET sort_order = id * 10 WHERE sort_order = 0');
            } catch (Throwable $e) {
                // تجاهل إن تعذر التهيئة الأولية للترتيب
            }
            $cols['sort_order'] = true;
        }
        if (empty($cols['address'])) {
            $after = empty($cols['email']) ? 'mobile' : 'email';
            $pdo->exec("ALTER TABLE `tblentities` ADD COLUMN address VARCHAR(500) NULL DEFAULT NULL COMMENT 'العنوان' AFTER `$after`");
            $cols['address'] = true;
        }
        if (!empty($cols['website']) && empty($cols['location'])) {
            $pdo->exec("ALTER TABLE `tblentities` CHANGE COLUMN `website` `location` VARCHAR(255) NULL DEFAULT NULL COMMENT 'موقع الخريطة (Google Maps)'");
            $cols['location'] = true;
            unset($cols['website']);
        }
        if (empty($cols['location'])) {
            $after = empty($cols['address']) ? 'email' : 'address';
            $pdo->exec("ALTER TABLE `tblentities` ADD COLUMN location VARCHAR(255) NULL DEFAULT NULL COMMENT 'موقع الخريطة (Google Maps)' AFTER `$after`");
            $cols['location'] = true;
        }
        if (empty($cols['notes'])) {
            $after = empty($cols['location']) ? (empty($cols['address']) ? 'email' : 'address') : 'location';
            $pdo->exec("ALTER TABLE `tblentities` ADD COLUMN notes TEXT NULL DEFAULT NULL COMMENT 'ملاحظات' AFTER `$after`");
        }

        $entityDeptIndexStmt = $pdo->prepare(
            "SELECT INDEX_NAME FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND LOWER(TABLE_NAME) = ?
               AND COLUMN_NAME = 'department_id'
             LIMIT 1"
        );
        $entityDeptIndexStmt->execute(['tblentities']);
        $entityDeptIndex = $entityDeptIndexStmt->fetchColumn();
        if (!$entityDeptIndex) {
            $pdo->exec('ALTER TABLE `tblentities` ADD INDEX `idx_entities_department` (`department_id`)');
        }

        // فهرس التصفية والفرز الأكثر استخدامًا في القوائم بعد أن صارت مجزّأة.
        $entityListIndexStmt = $pdo->prepare(
            "SELECT INDEX_NAME FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND LOWER(TABLE_NAME) = ?
               AND INDEX_NAME = 'idx_entities_active_name'
             LIMIT 1"
        );
        $entityListIndexStmt->execute(['tblentities']);
        $entityListIndex = $entityListIndexStmt->fetchColumn();
        if (!$entityListIndex) {
            try {
                $pdo->exec('ALTER TABLE `tblentities` ADD INDEX `idx_entities_active_name` (`is_active`, `name_ar`(100))');
            } catch (Throwable $e) {
                // الفهرس تحسين اختياري، فلا نُفشل التهيئة بسببه
            }
        }

        // فهرس فتح قائمة الجهات بدون بحث: is_active + sort_order
        $entitySortIndexStmt = $pdo->prepare(
            "SELECT INDEX_NAME FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND LOWER(TABLE_NAME) = ?
               AND INDEX_NAME = 'idx_entities_active_sort'
             LIMIT 1"
        );
        $entitySortIndexStmt->execute(['tblentities']);
        if (!$entitySortIndexStmt->fetchColumn()) {
            try {
                $pdo->exec('ALTER TABLE `tblentities` ADD INDEX `idx_entities_active_sort` (`is_active`, `sort_order`, `id`)');
            } catch (Throwable $e) {
                // اختياري
            }
        }

        $entityDeptFkStmt = $pdo->prepare(
            "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND LOWER(TABLE_NAME) = ?
               AND COLUMN_NAME = 'department_id'
               AND LOWER(REFERENCED_TABLE_NAME) = ?
             LIMIT 1"
        );
        $entityDeptFkStmt->execute(['tblentities', 'tbldepartments']);
        $entityDeptFk = $entityDeptFkStmt->fetchColumn();
        if (!$entityDeptFk) {
            try {
                $pdo->exec(
                    'ALTER TABLE `tblentities`
                     ADD CONSTRAINT `fk_entities_department`
                     FOREIGN KEY (`department_id`) REFERENCES `tbldepartments`(`id`) ON DELETE SET NULL'
                );
            } catch (Throwable $e) {
                // نُبقي العزل البرمجي فعالاً إن تعذر إنشاء القيد في قاعدة قديمة.
            }
        }

        // ترحيل عمود الارتباط في المعاملات: organization_id → entity_id
        $corrCols = dms_db_column_map('tblcorrespondence');
        if (!empty($corrCols['organization_id']) && empty($corrCols['entity_id'])) {
            // إسقاط أي FK قديم على organization_id إن وُجد
            $fkStmt = $pdo->prepare(
                "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND LOWER(TABLE_NAME) = ?
                   AND COLUMN_NAME = 'organization_id'
                   AND REFERENCED_TABLE_NAME IS NOT NULL"
            );
            $fkStmt->execute(['tblcorrespondence']);
            $fks = $fkStmt->fetchAll(PDO::FETCH_COLUMN);
            foreach ($fks as $fkName) {
                $pdo->exec('ALTER TABLE `tblcorrespondence` DROP FOREIGN KEY `' . str_replace('`', '``', (string) $fkName) . '`');
            }
            $pdo->exec('ALTER TABLE `tblcorrespondence` CHANGE COLUMN `organization_id` `entity_id` INT UNSIGNED NULL DEFAULT NULL');
            $corrCols['entity_id'] = true;
            unset($corrCols['organization_id']);
        }
        if (empty($corrCols['entity_id'])) {
            $pdo->exec('ALTER TABLE `tblcorrespondence` ADD COLUMN `entity_id` INT UNSIGNED NULL DEFAULT NULL AFTER `party_name`');
        }

        // ربط FK إن لم يكن موجوداً
        $entityFkStmt = $pdo->prepare(
            "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND LOWER(TABLE_NAME) = ?
               AND COLUMN_NAME = 'entity_id'
               AND LOWER(REFERENCED_TABLE_NAME) = ?
             LIMIT 1"
        );
        $entityFkStmt->execute(['tblcorrespondence', 'tblentities']);
        $entityFk = $entityFkStmt->fetchColumn();
        if (!$entityFk) {
            try {
                $pdo->exec(
                    'ALTER TABLE `tblcorrespondence`
                     ADD CONSTRAINT `fk_corr_entity`
                     FOREIGN KEY (`entity_id`) REFERENCES `tblentities`(`id`) ON DELETE SET NULL'
                );
            } catch (Throwable $e) {
                // قد يفشل إن وُجدت قيم يتيمة — نترك العمود بدون FK
            }
        }
    } catch (Throwable $e) {
        // لا نوقف التطبيق أثناء التثبيت الأولي، لكن نسجل سبب فشل الترحيل.
        error_log('DMS entities schema migration failed: ' . $e->getMessage());
    }

    if (function_exists('ensure_entity_groups_schema')) {
        ensure_entity_groups_schema();
    }
    ensure_correspondence_party_snapshot_schema();
}

/**
 * اسم القسم حسب لغة الواجهة (إنجليزي إن وُجد، وإلا العربي).
 * يقبل صف قسم (name/name_en) أو صف معاملة (department_name/department_name_en).
 */
function department_display_name(?array $row, string $arKey = 'name', string $enKey = 'name_en'): string
{
    if (!$row) {
        return '';
    }
    $ar = trim((string) ($row[$arKey] ?? ''));
    $en = trim((string) ($row[$enKey] ?? ''));
    if (function_exists('dms_lang_code') && dms_lang_code() === 'en' && $en !== '') {
        return $en;
    }
    return $ar !== '' ? $ar : $en;
}

/**
 * لقب واسم ووظيفة المسؤول.
 * إن رُبط صاحب شأن تُؤخذ من حسابه، وإلا من حقول الطباعة على القسم.
 *
 * @return array{title_prefix:string,directed_from:string,job_title:string}
 */
function department_officer_fields(array $dept): array
{
    static $users = null;
    $title = trim((string) ($dept['title_prefix'] ?? ''));
    $name = trim((string) ($dept['directed_from'] ?? ''));
    $job = trim((string) ($dept['job_title'] ?? ''));
    $uid = (int) ($dept['principal_user_id'] ?? 0);
    if ($uid <= 0) {
        return ['title_prefix' => $title, 'directed_from' => $name, 'job_title' => $job];
    }
    if ($users === null) {
        $users = [];
        try {
            if (function_exists('ensure_users_profile_schema')) {
                ensure_users_profile_schema();
            }
            $rows = db()->query('SELECT id, name_ar, name_en, title_prefix, job_title FROM `tblusers`')->fetchAll() ?: [];
            foreach ($rows as $row) {
                $users[(int) ($row['id'] ?? 0)] = $row;
            }
        } catch (Throwable $e) {
            $users = [];
        }
    }
    $user = $users[$uid] ?? null;
    if (!$user) {
        return ['title_prefix' => $title, 'directed_from' => $name, 'job_title' => $job];
    }
    $userName = trim((string) ($user['name_ar'] ?? ''));
    if ($userName === '') {
        $userName = trim((string) ($user['name_en'] ?? ''));
    }
    return [
        'title_prefix' => trim((string) ($user['title_prefix'] ?? '')),
        'directed_from' => $userName !== '' ? $userName : $name,
        'job_title' => trim((string) ($user['job_title'] ?? '')),
    ];
}

/** لقب + اسم الموجّه من بطاقة القسم، أو اسم القسم إن لم يُعبَّأ الاسم. */
function department_person_option_label(array $d): string
{
    $prefix = trim((string) ($d['title_prefix'] ?? ''));
    $person = trim((string) ($d['directed_from'] ?? ''));
    $name = trim($prefix . ($prefix !== '' && $person !== '' ? ' ' : '') . $person);
    if ($name !== '') {
        return $name;
    }
    return department_display_name($d);
}

/**
 * تسميات خيارات القسم بالاسم الظاهر في الخطاب. يُلحق اسم المكتب عند تكرار الاسم.
 *
 * @param list<array<string,mixed>> $departments
 * @return array<int,string>
 */
function department_person_option_map(array $departments): array
{
    $keys = [];
    foreach ($departments as $d) {
        $id = (int) ($d['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }
        $keys[$id] = trim(trim((string) ($d['title_prefix'] ?? '')) . ' ' . trim((string) ($d['directed_from'] ?? '')));
    }
    $counts = array_count_values(array_values(array_filter($keys)));
    $out = [];
    foreach ($departments as $d) {
        $id = (int) ($d['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }
        $label = department_person_option_label($d);
        $key = $keys[$id] ?? '';
        $office = department_display_name($d);
        if ($key !== '' && ($counts[$key] ?? 0) > 1 && $office !== '' && $office !== $label) {
            $label .= ' — ' . $office;
        }
        $out[$id] = $label;
    }
    return $out;
}

/** اسم المسؤول عن القسم كما يُستخدم في المعاملات، أو اسم القسم إن لم يُعبَّأ. */
function correspondence_department_officer_label(array $row): string
{
    return department_person_option_label([
        'title_prefix' => $row['department_title_prefix'] ?? ($row['title_prefix'] ?? ''),
        'directed_from' => $row['department_directed_from'] ?? ($row['directed_from'] ?? ''),
        'name' => $row['department_name'] ?? ($row['name'] ?? ''),
        'name_en' => $row['department_name_en'] ?? ($row['name_en'] ?? ''),
    ]);
}

/** اسم المسؤول في طرف الإحالة (from أو to). */
function referral_party_officer_label(array $row, string $side): string
{
    $prefix = $side === 'from' ? 'from' : 'to';

    return department_person_option_label([
        'title_prefix' => $row[$prefix . '_dept_title_prefix'] ?? '',
        'directed_from' => $row[$prefix . '_dept_directed_from'] ?? '',
        'name' => $row[$prefix . '_dept_name'] ?? '',
        'name_en' => $row[$prefix . '_dept_name_en'] ?? '',
    ]);
}

/** اسم القسم تحت اسم المسؤول، ويُترك فارغاً إن كان مطابقاً للاسم. */
function department_officer_subtitle(array $d): string
{
    $person = department_person_option_label($d);
    $office = department_display_name($d);
    if ($office === '' || $office === $person) {
        return '';
    }
    return $office;
}

function correspondence_officer_subtitle(array $row): string
{
    $name = correspondence_department_officer_label($row);
    $office = department_display_name($row, 'department_name', 'department_name_en');
    if ($office === '') {
        $office = trim((string) ($row['department_name'] ?? ''));
    }
    if ($office === '' || $office === $name) {
        return '';
    }
    return $office;
}

function referral_party_subtitle(array $row, string $side): string
{
    $name = referral_party_officer_label($row, $side);
    $prefix = $side === 'from' ? 'from' : 'to';
    $office = department_display_name([
        'name' => $row[$prefix . '_dept_name'] ?? '',
        'name_en' => $row[$prefix . '_dept_name_en'] ?? '',
    ]);
    if ($office === '' || $office === $name) {
        return '';
    }
    return $office;
}

function officer_with_office_text(string $name, string $office = ''): string
{
    $name = trim($name);
    $office = trim($office);
    if ($name === '') {
        return __('common.none');
    }
    if ($office === '' || $office === $name) {
        return $name;
    }
    return $name . "\n" . $office;
}

function render_officer_with_office(string $name, string $office = ''): void
{
    $name = trim($name);
    $office = trim($office);
    if ($name === '') {
        $office = '';
        $name = __('common.none');
    } elseif ($office === $name) {
        $office = '';
    }
    echo '<span class="corr-officer-stack">';
    echo '<span class="corr-officer-pick-name">' . e($name) . '</span>';
    if ($office !== '') {
        echo '<span class="corr-officer-pick-office">' . e($office) . '</span>';
    }
    echo '</span>';
}

/**
 * طرف القسم في الصادر والوارد: اللقب بسطر ثم الاسم فقط (بدون اسم القسم).
 * issuing = موجهة من (أرشيف المعاملة إن وُجد)، receiving = موجهة إلى (بطاقة القسم المستلم).
 */
function render_correspondence_department_officer(array $row, string $side): void
{
    if ($side === 'issuing') {
        $archived = function_exists('correspondence_archived_from') ? correspondence_archived_from($row) : [];
        $prefix = trim((string) ($archived['prefix'] ?? ''));
        $person = trim((string) ($archived['person'] ?? ''));
        $office = department_display_name($row, 'issuing_department_name', 'issuing_department_name_en');
        if ($office === '') {
            $office = department_display_name($row, 'department_name', 'department_name_en');
        }
    } else {
        $prefix = function_exists('department_title_prefix_value')
            ? department_title_prefix_value($row)
            : trim((string) ($row['department_title_prefix'] ?? $row['title_prefix'] ?? ''));
        $person = trim((string) ($row['department_directed_from'] ?? $row['directed_from'] ?? ''));
        $office = department_display_name($row, 'department_name', 'department_name_en');
    }
    if ($person === '' && $prefix === '') {
        $person = $office;
        $prefix = '';
    }
    if (function_exists('render_corr_list_person')) {
        render_corr_list_person(['prefix' => $prefix, 'person' => $person]);
        return;
    }
    $name = trim($prefix . ($prefix !== '' && $person !== '' ? ' ' : '') . $person);
    render_officer_with_office($name, '');
}

function render_correspondence_officer(array $row): void
{
    render_officer_with_office(
        correspondence_department_officer_label($row),
        correspondence_officer_subtitle($row)
    );
}

function render_referral_party(array $row, string $side): void
{
    render_officer_with_office(
        referral_party_officer_label($row, $side),
        referral_party_subtitle($row, $side)
    );
}

/**
 * اسم المستخدم حسب لغة الواجهة.
 * name_ar = الاسم العربي، name_en = الاسم الإنجليزي (البريد هو المعرف الفريد).
 */
function user_display_name(?array $row, string $arKey = 'name_ar', string $enKey = 'name_en'): string
{
    if (!$row) {
        return '';
    }
    $ar = trim((string) ($row[$arKey] ?? $row['name_ar'] ?? $row['full_name'] ?? ''));
    $en = trim((string) ($row[$enKey] ?? $row['name_en'] ?? $row['username'] ?? ''));
    if (function_exists('dms_lang_code') && dms_lang_code() === 'en' && $en !== '') {
        return $en;
    }
    if ($ar !== '') {
        return $ar;
    }
    if ($en !== '') {
        return $en;
    }

    return trim((string) ($row['email'] ?? ''));
}

/** اسم للمراجعة والسجلات: العربي ثم الإنجليزي ثم البريد */
function user_actor_name(?array $row): string
{
    if (!$row) {
        return '';
    }
    $ar = trim((string) ($row['name_ar'] ?? $row['full_name'] ?? ''));
    if ($ar !== '') {
        return $ar;
    }
    $en = trim((string) ($row['name_en'] ?? $row['username'] ?? ''));
    if ($en !== '') {
        return $en;
    }

    return trim((string) ($row['email'] ?? ''));
}

/** أعمدة الاسم العربي والإنجليزي في استعلام (للعرض عبر user_display_name) */
function user_name_sql(string $alias, string $as): string
{
    $alias = preg_replace('/[^A-Za-z0-9_]/', '', $alias) ?: 'u';
    $as = preg_replace('/[^A-Za-z0-9_]/', '', $as) ?: 'user_name';

    return "`{$alias}`.name_ar AS `{$as}`, `{$alias}`.name_en AS `{$as}_en`";
}

function dms_normalize_user_email(string $email): string
{
    $email = strtolower(trim($email));
    // أحرف Unicode غير مرئية قد تسبب تطابقاً خاطئاً أو فشل UNIQUE
    $email = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $email) ?? $email;

    return $email;
}

function dms_user_owns_email(int $userId, string $email): bool
{
    if ($userId <= 0) {
        return false;
    }
    $email = dms_normalize_user_email($email);
    if ($email === '') {
        return false;
    }
    $stmt = db()->prepare('SELECT LOWER(TRIM(email)) FROM `tblusers` WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $current = $stmt->fetchColumn();

    return $current !== false && dms_normalize_user_email((string) $current) === $email;
}

function dms_is_user_email_taken(string $email, ?int $excludeUserId = null): bool
{
    $email = dms_normalize_user_email($email);
    if ($email === '') {
        return false;
    }
    $excludeUserId = ($excludeUserId !== null && (int) $excludeUserId > 0) ? (int) $excludeUserId : null;
    if ($excludeUserId !== null && dms_user_owns_email($excludeUserId, $email)) {
        return false;
    }
    if ($excludeUserId !== null) {
        $stmt = db()->prepare('SELECT id FROM `tblusers` WHERE LOWER(TRIM(email)) = ? AND id <> ? LIMIT 1');
        $stmt->execute([$email, $excludeUserId]);
    } else {
        $stmt = db()->prepare('SELECT id FROM `tblusers` WHERE LOWER(TRIM(email)) = ? LIMIT 1');
        $stmt->execute([$email]);
    }

    return $stmt->fetchColumn() !== false;
}

/** @return string|null رسالة خطأ أو null إذا صالح */
function dms_validate_user_english_name(string $nameEn): ?string
{
    $nameEn = trim($nameEn);
    if ($nameEn === '') {
        return __('users.name_en_required');
    }
    if (!preg_match('/^[A-Za-z\s\-\']+$/', $nameEn)) {
        return __('users.name_en_letters');
    }

    return null;
}

/**
 * التحقق من الاسم العربي والإنجليزي والبريد (المعرف الفريد).
 *
 * @return string|null رسالة خطأ أو null إذا صالح
 */
function dms_validate_user_profile_data(string $nameAr, string $nameEn, string $email, ?int $excludeUserId = null): ?string
{
    if (trim($nameAr) === '') {
        return __('users.name_ar_required');
    }
    $engError = dms_validate_user_english_name($nameEn);
    if ($engError !== null) {
        return $engError;
    }
    $email = dms_normalize_user_email($email);
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return __('users.email_required');
    }
    if (dms_is_user_email_taken($email, $excludeUserId)) {
        return __('profile.email_taken');
    }

    return null;
}

function upload_org_logo(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return org_logo_path();
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('فشل رفع الشعار.');
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('نوع الشعار غير مسموح. المسموح: JPG, PNG, WEBP, GIF.');
    }
    if ($file['size'] > dms_document_upload_max_bytes()) {
        throw new RuntimeException('حجم الشعار أكبر من ' . dms_document_upload_max_label() . '.');
    }

    $dir = dms_org_uploads_dir();
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('تعذر إنشاء مجلد الشعار.');
    }

    foreach (scandir($dir) ?: [] as $existing) {
        if (preg_match('/^logo(_\d+)?\.(png|jpe?g|webp|gif)$/i', $existing)) {
            @unlink($dir . DIRECTORY_SEPARATOR . $existing);
        }
    }

    $ext = $allowed[$mime];
    $name = 'logo.' . $ext;
    $dest = $dir . DIRECTORY_SEPARATOR . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException('تعذر حفظ الشعار.');
    }

    return 'assets/uploads/organization/' . $name;
}

function upload_org_signature(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return org_signature_path();
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException(__('settings.signature_upload_fail'));
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!isset($allowed[$mime])) {
        throw new RuntimeException(__('settings.signature_type'));
    }
    if ($file['size'] > dms_document_upload_max_bytes()) {
        throw new RuntimeException(__('settings.signature_size'));
    }

    $dir = dms_org_uploads_dir();
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException(__('settings.signature_dir_fail'));
    }

    foreach (scandir($dir) ?: [] as $existing) {
        if (preg_match('/^(signature|sign_\d+)\.(png|jpe?g|webp|gif)$/i', $existing)) {
            @unlink($dir . DIRECTORY_SEPARATOR . $existing);
        }
    }

    $ext = $allowed[$mime];
    $name = 'signature.' . $ext;
    $dest = $dir . DIRECTORY_SEPARATOR . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException(__('settings.signature_save_fail'));
    }

    return 'assets/uploads/organization/' . $name;
}

function department_signature_path_in_use(string $relPath, ?int $exceptDeptId = null): bool
{
    $relPath = str_replace('\\', '/', trim($relPath));
    if ($relPath === '') {
        return false;
    }
    try {
        if ($exceptDeptId !== null && $exceptDeptId > 0) {
            $stmt = db()->prepare('SELECT id FROM `tbldepartments` WHERE signature_path = ? AND id <> ? LIMIT 1');
            $stmt->execute([$relPath, $exceptDeptId]);
        } else {
            $stmt = db()->prepare('SELECT id FROM `tbldepartments` WHERE signature_path = ? LIMIT 1');
            $stmt->execute([$relPath]);
        }
        if ($stmt->fetchColumn()) {
            return true;
        }
        if (function_exists('ensure_users_profile_schema')) {
            ensure_users_profile_schema();
        }
        $stmt = db()->prepare('SELECT id FROM `tblusers` WHERE signature_path = ? LIMIT 1');
        $stmt->execute([$relPath]);
        if ($stmt->fetchColumn()) {
            return true;
        }
        $stmt = db()->prepare('SELECT id FROM `tblcorrespondence` WHERE snap_from_signature = ? LIMIT 1');
        $stmt->execute([$relPath]);
        return (bool) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return true;
    }
}

function department_signature_unlink_if_unused(string $relPath, ?int $exceptDeptId = null): void
{
    $relPath = str_replace('\\', '/', ltrim(trim($relPath), '/'));
    if ($relPath === '' || str_contains($relPath, '..')) {
        return;
    }
    if (department_signature_path_in_use($relPath, $exceptDeptId)) {
        return;
    }
    $full = __DIR__ . '/../' . ltrim(str_replace('\\', '/', $relPath), '/');
    if (is_file($full)) {
        @unlink($full);
    }
}

function upload_department_signature(array $file, int $deptId, string $oldPath = ''): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return $oldPath;
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException(__('settings.signature_upload_fail'));
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!isset($allowed[$mime])) {
        throw new RuntimeException(__('settings.signature_type'));
    }
    if ($file['size'] > dms_document_upload_max_bytes()) {
        throw new RuntimeException(__('settings.signature_size'));
    }

    $dir = __DIR__ . '/../assets/uploads/organization';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException(__('settings.signature_dir_fail'));
    }

    $ext = $allowed[$mime];
    $name = 'sign_d' . max(0, $deptId) . '_' . time() . '.' . $ext;
    $dest = $dir . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException(__('settings.signature_save_fail'));
    }

    $newPath = 'assets/uploads/organization/' . $name;
    if ($oldPath !== '' && $oldPath !== $newPath) {
        department_signature_unlink_if_unused($oldPath, $deptId);
    }

    return $newPath;
}

function clear_department_signature(int $deptId, string $oldPath = ''): void
{
    db()->prepare('UPDATE `tbldepartments` SET signature_path = NULL WHERE id = ?')->execute([$deptId]);
    if ($oldPath !== '') {
        department_signature_unlink_if_unused($oldPath, $deptId);
    }
}

function user_signature_path(int $userId): string
{
    static $cache = [];
    if ($userId <= 0) {
        return '';
    }
    if (array_key_exists($userId, $cache)) {
        return $cache[$userId];
    }
    ensure_users_profile_schema();
    try {
        $st = db()->prepare('SELECT signature_path FROM `tblusers` WHERE id = ? LIMIT 1');
        $st->execute([$userId]);
        $cache[$userId] = trim((string) ($st->fetchColumn() ?: ''));
    } catch (Throwable $e) {
        $cache[$userId] = '';
    }
    return $cache[$userId];
}

function department_principal_signature_path(array $dept): string
{
    return user_signature_path((int) ($dept['principal_user_id'] ?? 0));
}

function upload_user_signature(array $file, int $userId, string $oldPath = ''): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return $oldPath;
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException(__('settings.signature_upload_fail'));
    }
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!isset($allowed[$mime])) {
        throw new RuntimeException(__('settings.signature_type'));
    }
    if ($file['size'] > dms_document_upload_max_bytes()) {
        throw new RuntimeException(__('settings.signature_size'));
    }
    $dir = __DIR__ . '/../assets/uploads/organization';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException(__('settings.signature_dir_fail'));
    }
    $ext = $allowed[$mime];
    $name = 'sign_u' . max(0, $userId) . '_' . time() . '.' . $ext;
    $dest = $dir . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException(__('settings.signature_save_fail'));
    }
    return 'assets/uploads/organization/' . $name;
}

function clear_user_signature(int $userId, string $oldPath = ''): void
{
    ensure_users_profile_schema();
    db()->prepare('UPDATE `tblusers` SET signature_path = NULL WHERE id = ?')->execute([$userId]);
    if ($oldPath !== '') {
        department_signature_unlink_if_unused($oldPath);
    }
}

/** ينقل توقيع القسم الحالي إلى صاحب الشأن، ويبقي ملف الصورة للمعاملات القديمة. */
function users_adopt_department_signatures(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $rows = db()->query(
            'SELECT id, principal_user_id, signature_path FROM `tbldepartments`
             WHERE signature_path IS NOT NULL AND TRIM(signature_path) <> \'\' AND principal_user_id IS NOT NULL'
        )->fetchAll() ?: [];
        $set = db()->prepare(
            'UPDATE `tblusers` SET signature_path = ? WHERE id = ? AND (signature_path IS NULL OR TRIM(signature_path) = \'\')'
        );
        foreach ($rows as $row) {
            $uid = (int) ($row['principal_user_id'] ?? 0);
            $path = trim((string) ($row['signature_path'] ?? ''));
            if ($uid > 0 && $path !== '') {
                $set->execute([$path, $uid]);
            }
        }
        db()->exec('UPDATE `tbldepartments` SET signature_path = NULL WHERE signature_path IS NOT NULL');
    } catch (Throwable $e) {
        error_log('DMS adopt department signatures: ' . $e->getMessage());
    }
}

/** أعمدة الملف الشخصي: name_ar / name_en، صورة، هاتف، والبريد فريد */
function ensure_users_profile_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $cols = dms_db_column_names('tblusers');

        $indexes = db()->query('SHOW INDEX FROM ' . dms_sql_table('tblusers'))->fetchAll();
        $usernameUniqueKeys = [];
        $hasEmailUnique = false;
        foreach ($indexes as $idx) {
            $col = (string) ($idx['Column_name'] ?? '');
            $key = (string) ($idx['Key_name'] ?? '');
            $nonUnique = (int) ($idx['Non_unique'] ?? 1);
            if ($col === 'username' && $nonUnique === 0 && $key !== '' && strcasecmp($key, 'PRIMARY') !== 0) {
                $usernameUniqueKeys[$key] = true;
            }
            if ($col === 'email' && $nonUnique === 0) {
                $hasEmailUnique = true;
            }
        }
        foreach (array_keys($usernameUniqueKeys) as $key) {
            $safe = preg_replace('/[^A-Za-z0-9_]/', '', $key);
            if ($safe === '') {
                continue;
            }
            try {
                db()->exec("ALTER TABLE `tblusers` DROP INDEX `{$safe}`");
            } catch (Throwable $e) {
                // ربما سقط الفهرس مسبقاً
            }
        }

        if (in_array('full_name', $cols, true) && !in_array('name_ar', $cols, true)) {
            db()->exec('ALTER TABLE `tblusers` CHANGE COLUMN `full_name` `name_ar` VARCHAR(150) NOT NULL');
            $cols = dms_db_column_names('tblusers');
        } elseif (in_array('full_name', $cols, true) && in_array('name_ar', $cols, true)) {
            db()->exec("UPDATE `tblusers` SET name_ar = full_name WHERE (name_ar IS NULL OR TRIM(name_ar) = '') AND TRIM(full_name) <> ''");
            db()->exec('ALTER TABLE `tblusers` DROP COLUMN `full_name`');
            $cols = dms_db_column_names('tblusers');
        }

        if (in_array('username', $cols, true) && !in_array('name_en', $cols, true)) {
            db()->exec('ALTER TABLE `tblusers` CHANGE COLUMN `username` `name_en` VARCHAR(150) NOT NULL');
            $cols = dms_db_column_names('tblusers');
        } elseif (in_array('username', $cols, true) && in_array('name_en', $cols, true)) {
            db()->exec("UPDATE `tblusers` SET name_en = username WHERE (name_en IS NULL OR TRIM(name_en) = '') AND TRIM(username) <> ''");
            db()->exec('ALTER TABLE `tblusers` DROP COLUMN `username`');
            $cols = dms_db_column_names('tblusers');
        }

        $roleCol = db()->query("SHOW COLUMNS FROM `tblusers` LIKE 'role'")->fetch();
        if ($roleCol && stripos((string) ($roleCol['Type'] ?? ''), "'communications'") === false) {
            db()->exec(
                "ALTER TABLE `tblusers` MODIFY role ENUM('admin','communications','archive','department','viewer') NOT NULL DEFAULT 'department'"
            );
        }

        if (!in_array('photo_path', $cols, true)) {
            db()->exec("ALTER TABLE `tblusers` ADD COLUMN photo_path VARCHAR(500) NULL AFTER email");
        }
        if (!in_array('phone', $cols, true)) {
            db()->exec("ALTER TABLE `tblusers` ADD COLUMN phone VARCHAR(50) NULL AFTER photo_path");
        }
        $addedJobTitle = false;
        if (!in_array('job_title', $cols, true)) {
            db()->exec("ALTER TABLE `tblusers` ADD COLUMN job_title VARCHAR(150) NULL DEFAULT NULL COMMENT 'المسمى الوظيفي' AFTER phone");
            $addedJobTitle = true;
        }
        if (!in_array('job_title_en', $cols, true)) {
            db()->exec("ALTER TABLE `tblusers` ADD COLUMN job_title_en VARCHAR(150) NULL DEFAULT NULL COMMENT 'المسمى الوظيفي إنجليزي' AFTER job_title");
        }
        if (!in_array('title_prefix', $cols, true)) {
            db()->exec("ALTER TABLE `tblusers` ADD COLUMN title_prefix VARCHAR(255) NULL DEFAULT NULL COMMENT 'اللقب' AFTER name_ar");
        }
        if (!in_array('signature_path', $cols, true)) {
            db()->exec("ALTER TABLE `tblusers` ADD COLUMN signature_path VARCHAR(255) NULL DEFAULT NULL COMMENT 'التوقيع الإلكتروني' AFTER photo_path");
        }
        users_adopt_department_signatures();
        if (!in_array('notes', $cols, true)) {
            db()->exec("ALTER TABLE `tblusers` ADD COLUMN notes TEXT NULL DEFAULT NULL COMMENT 'ملاحظات إدارية' AFTER job_title_en");
        }
        if ($addedJobTitle && function_exists('setting')) {
            // نقل مسميات المعتمدين المكتوبة سابقاً في إعدادات الموافقة إلى بطاقات المستخدمين
            $copy = db()->prepare(
                "UPDATE `tblusers`
                 SET job_title = COALESCE(NULLIF(job_title, ''), ?),
                     job_title_en = COALESCE(NULLIF(job_title_en, ''), ?)
                 WHERE id = ?"
            );
            foreach (['sg', 'deputy', 'alternate'] as $apprRole) {
                $apprUserId = (int) setting('outgoing_approver_' . $apprRole . '_user_id', '0');
                $apprAr = trim((string) setting('outgoing_approver_' . $apprRole . '_title', ''));
                $apprEn = trim((string) setting('outgoing_approver_' . $apprRole . '_title_en', ''));
                if ($apprUserId > 0 && ($apprAr !== '' || $apprEn !== '')) {
                    $copy->execute([$apprAr !== '' ? $apprAr : null, $apprEn !== '' ? $apprEn : null, $apprUserId]);
                }
            }
        }

        if (!$hasEmailUnique) {
            try {
                db()->exec('ALTER TABLE `tblusers` ADD UNIQUE KEY `email` (`email`)');
            } catch (Throwable $e) {
                // بريد مكرر أو الفهرس موجود
            }
        }
        if (!in_array('sort_order', $cols, true)) {
            db()->exec("ALTER TABLE `tblusers` ADD COLUMN sort_order INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'ترتيب الظهور' AFTER is_active");
            db()->exec('UPDATE `tblusers` SET sort_order = id');
        }
    } catch (Throwable $e) {
        // قاعدة غير جاهزة
    }
}

function user_photo_path(?array $user = null): string
{
    $user = $user ?? (function_exists('current_user') ? current_user() : null);
    return trim((string) ($user['photo_path'] ?? ''));
}

function user_photo_url(?array $user = null): string
{
    $path = user_photo_path($user);
    if ($path === '') {
        return '';
    }
    $disk = dirname(__DIR__) . '/' . ltrim(str_replace('\\', '/', $path), '/');
    if (!is_file($disk)) {
        return '';
    }
    $url = function_exists('dms_web_path') ? dms_web_path($path) : base_url($path);
    $ver = (string) (@filemtime($disk) ?: time()) . '-' . (string) (@filesize($disk) ?: 0);
    return $url . (str_contains($url, '?') ? '&' : '?') . 'v=' . rawurlencode($ver);
}

function dms_upload_error_message(int $code): string
{
    return match ($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => __('profile.photo_too_large_server'),
        UPLOAD_ERR_PARTIAL => __('profile.photo_upload_failed'),
        UPLOAD_ERR_NO_FILE => __('profile.photo_required'),
        UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => __('profile.photo_save_failed'),
        default => __('profile.photo_upload_failed'),
    };
}

/** @return null|array{mime:string,ext:string} */
function dms_detect_image_upload(array $file): ?array
{
    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_file($tmp)) {
        return null;
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/jpg' => 'jpg',
        'image/pjpeg' => 'jpg',
        'image/png' => 'png',
        'image/x-png' => 'png',
        'image/webp' => 'webp',
        'image/x-webp' => 'webp',
        'image/gif' => 'gif',
    ];

    $mime = '';
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detected = $finfo->file($tmp);
        if (is_string($detected)) {
            $mime = strtolower(trim(explode(';', $detected)[0]));
        }
    }
    if ($mime === '' || $mime === 'application/octet-stream' || !isset($allowed[$mime])) {
        $info = @getimagesize($tmp);
        if (is_array($info) && !empty($info['mime'])) {
            $mime = strtolower((string) $info['mime']);
        }
    }
    if ($mime === '' || !isset($allowed[$mime])) {
        $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        $byExt = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
        ];
        if (isset($byExt[$ext])) {
            $info = @getimagesize($tmp);
            if (is_array($info)) {
                $mime = $byExt[$ext];
            }
        }
    }
    if ($mime === '' || !isset($allowed[$mime])) {
        return null;
    }

    return ['mime' => $mime, 'ext' => $allowed[$mime]];
}

/**
 * اسم ملف الصورة الشخصية من الاسم الإنجليزي فقط (أحرف/أرقام/-).
 * مثال: "Zafer Alrasheed" → "Zafer-Alrasheed"
 */
function user_photo_basename(string $nameEn, int $userId): string
{
    $nameEn = trim($nameEn);
    // توحيد المسافات والشرطات ثم الإبقاء على اللاتيني فقط
    $slug = preg_replace('/[\s_]+/u', '-', $nameEn) ?? '';
    $slug = preg_replace('/[^A-Za-z0-9\-]+/', '', $slug) ?? '';
    $slug = preg_replace('/-+/', '-', $slug) ?? '';
    $slug = trim($slug, '-');
    if ($slug === '') {
        return '';
    }
    // جعل كل مقطع يبدأ بحرف كبير لثبات الأسماء: zafer-alrasheed → Zafer-Alrasheed
    $parts = explode('-', strtolower($slug));
    $parts = array_map(static fn(string $p): string => $p === '' ? '' : ucfirst($p), $parts);
    return implode('-', array_filter($parts, static fn(string $p): bool => $p !== ''));
}

/**
 * تصحيح اتجاه JPEG عبر EXIF إن أمكن (فشل بصري شائع من الجوال).
 * يُنفَّذ بأمان: أي خطأ يُتجاهل ويبقى الملف كما رُفع.
 */
function dms_normalize_user_photo_file(string $path, string $ext): void
{
    $ext = strtolower($ext);
    if ($ext !== 'jpg' && $ext !== 'jpeg') {
        return;
    }
    if (!function_exists('imagecreatefromjpeg') || !function_exists('imagerotate')) {
        return;
    }
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $angle = 0;
    if (function_exists('exif_read_data')) {
        $exif = @exif_read_data($path);
        $orientation = (int) ($exif['Orientation'] ?? 0);
        $angle = match ($orientation) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };
    }
    if ($angle === 0) {
        return;
    }

    $img = @imagecreatefromjpeg($path);
    if ($img === false) {
        return;
    }
    $rotated = @imagerotate($img, $angle, 0);
    imagedestroy($img);
    if ($rotated === false) {
        return;
    }
    @imagejpeg($rotated, $path, 90);
    imagedestroy($rotated);
}

function upload_user_photo(int $userId, array $file, string $oldPath = ''): string
{
    $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err !== UPLOAD_ERR_OK) {
        throw new RuntimeException(dms_upload_error_message($err));
    }
    if (empty($file['tmp_name']) || !is_uploaded_file((string) $file['tmp_name'])) {
        throw new RuntimeException(__('profile.photo_required'));
    }

    $detected = dms_detect_image_upload($file);
    if ($detected === null) {
        throw new RuntimeException(__('profile.photo_type'));
    }
    if ((int) ($file['size'] ?? 0) > dms_document_upload_max_bytes()) {
        throw new RuntimeException(__('profile.photo_size'));
    }

    $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'users';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException(__('profile.photo_dir_failed'));
    }
    if (!is_writable($dir)) {
        throw new RuntimeException(__('profile.photo_dir_failed'));
    }

    $nameEn = '';
    $email = '';
    try {
        $stmt = db()->prepare('SELECT name_en, email FROM `tblusers` WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $nameEn = trim((string) ($row['name_en'] ?? ''));
        $email = trim((string) ($row['email'] ?? ''));
    } catch (Throwable $e) {
        $nameEn = '';
        $email = '';
    }

    $base = user_photo_basename($nameEn, $userId);
    // إن لم يوجد اسم إنجليزي صالح، جرّب الجزء اللاتيني من البريد قبل @
    if ($base === '' && $email !== '' && str_contains($email, '@')) {
        $local = (string) strstr($email, '@', true);
        $base = user_photo_basename($local, $userId);
    }
    // آخر ملاذ ثابت حتى لا يفشل الرفع بسبب الاسم فقط
    if ($base === '') {
        $base = 'user-' . $userId;
    }

    $ext = $detected['ext'];
    $name = $base . '.' . $ext;
    $dest = $dir . DIRECTORY_SEPARATOR . $name;
    $oldDisk = '';
    if ($oldPath !== '') {
        $oldDisk = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($oldPath, '/\\'));
    }

    $pathsEqual = static function (string $a, string $b): bool {
        if ($a === '' || $b === '' || !is_file($a) || !is_file($b)) {
            return false;
        }
        $ra = @realpath($a);
        $rb = @realpath($b);
        if ($ra && $rb) {
            return strcasecmp($ra, $rb) === 0;
        }
        return strcasecmp(
            str_replace('\\', '/', $a),
            str_replace('\\', '/', $b)
        ) === 0;
    };

    // إن وُجد ملف بنفس الاسم وليس صورة المستخدم الحالية → أضف رقم المستخدم لتجنّب التعارض
    if (is_file($dest) && !$pathsEqual($dest, $oldDisk)) {
        $name = $base . '-' . $userId . '.' . $ext;
        $dest = $dir . DIRECTORY_SEPARATOR . $name;
    }

    // لا نستبدل الملف مباشرة (ويندوز قد يقفل الصورة المعروضة في المتصفح)
    $partName = $name . '.part.' . bin2hex(random_bytes(4));
    $partDest = $dir . DIRECTORY_SEPARATOR . $partName;
    $tmp = (string) $file['tmp_name'];
    $saved = @move_uploaded_file($tmp, $partDest);
    if (!$saved) {
        $saved = @copy($tmp, $partDest);
        if ($saved) {
            @unlink($tmp);
        }
    }
    if (!$saved || !is_file($partDest)) {
        @unlink($partDest);
        throw new RuntimeException(__('profile.photo_save_failed'));
    }

    try {
        dms_normalize_user_photo_file($partDest, $ext);
    } catch (Throwable $e) {
        // اختياري — الإبقاء على الملف كما رُفع
    }

    if (is_file($dest) && !$pathsEqual($dest, $partDest)) {
        @unlink($dest);
    }
    if (!@rename($partDest, $dest)) {
        if (!@copy($partDest, $dest) || !is_file($dest)) {
            @unlink($partDest);
            throw new RuntimeException(__('profile.photo_save_failed'));
        }
        @unlink($partDest);
    }

    if ($oldDisk !== '' && is_file($oldDisk) && !$pathsEqual($oldDisk, $dest)) {
        @unlink($oldDisk);
    }

    @touch($dest);

    return 'assets/uploads/users/' . $name;
}

function remove_user_photo_file(string $path): void
{
    $path = trim($path);
    if ($path === '') {
        return;
    }
    $disk = __DIR__ . '/../' . ltrim(str_replace('\\', '/', $path), '/');
    if (is_file($disk)) {
        @unlink($disk);
    }
}

function generate_qr_token(): string
{
    return bin2hex(random_bytes(16));
}

/** هل عنوان الطلب الحالي قابلاً للوصول من الإنترنت (ليس localhost ولا شبكة داخلية)؟ */
function dms_http_host_is_public(?string $host = null): bool
{
    $host = strtolower(trim((string) ($host ?? ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''))));
    $host = preg_replace('/:\d+$/', '', $host) ?: '';
    if ($host === '' || $host === 'localhost' || $host === '127.0.0.1' || $host === '::1') {
        return false;
    }
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }
    foreach (['.local', '.lan', '.internal', '.localhost'] as $suffix) {
        if (str_ends_with($host, $suffix)) {
            return false;
        }
    }
    // اسم جهاز محلي بدون نطاق (أي اسم سيرفر على الشبكة الداخلية)
    if (!str_contains($host, '.')) {
        return false;
    }
    return true;
}

function dms_ipv4_is_lan(string $ip): bool
{
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return false;
    }
    if ($ip === '127.0.0.1' || str_starts_with($ip, '169.254.')) {
        return false;
    }
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE) === false;
}

function dms_detect_lan_ipv4(): string
{
    $serverAddr = (string) ($_SERVER['SERVER_ADDR'] ?? '');
    if (dms_ipv4_is_lan($serverAddr)) {
        return $serverAddr;
    }

    $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $found = [];
    if (function_exists('net_get_interfaces')) {
        $ifaces = @net_get_interfaces();
        if (is_array($ifaces)) {
            foreach ($ifaces as $info) {
                foreach ($info['unicast'] ?? [] as $unicast) {
                    $ip = (string) ($unicast['address'] ?? '');
                    if (dms_ipv4_is_lan($ip)) {
                        $found[] = $ip;
                    }
                }
            }
        }
    }
    $found = array_values(array_unique($found));
    if ($found === []) {
        return '';
    }
    if (dms_ipv4_is_lan($remote)) {
        $prefix = substr($remote, 0, (int) strrpos($remote, '.') + 1);
        foreach ($found as $ip) {
            if (str_starts_with($ip, $prefix)) {
                return $ip;
            }
        }
    }
    foreach ($found as $ip) {
        if (str_starts_with($ip, '192.168.')) {
            return $ip;
        }
    }
    return $found[0];
}

/** أساس رابط QR: إعداد يدوي اختياري، وإلا يُستنتج تلقائياً من الدومين/الشبكة مثل HostGator. */
function dms_corr_qr_base_url(): string
{
    $configured = trim((string) setting('qr_scan_base_url', ''));
    if ($configured !== '') {
        return rtrim($configured, '/');
    }

    $httpHost = (string) ($_SERVER['HTTP_HOST'] ?? '');
    $hostOnly = strtolower(preg_replace('/:\d+$/', '', $httpHost) ?: '');

    // نطاق عام أو IP عام/LAN صريح في عنوان الطلب → نفس رابط النظام الحالي
    if ($hostOnly !== '' && (dms_http_host_is_public($hostOnly) || dms_ipv4_is_lan($hostOnly))) {
        return dms_detect_base_url();
    }

    // localhost أو اسم جهاز محلي: فضّل IP الشبكة حتى يعمل المسح من الجوال
    $lan = dms_detect_lan_ipv4();
    if ($lan === '') {
        return dms_detect_base_url();
    }

    $scheme = dms_request_scheme();
    $port = '';
    if (preg_match('/:(\d+)$/', $httpHost, $m) && !in_array($m[1], ['80', '443'], true)) {
        $port = ':' . $m[1];
    } else {
        $serverPort = (string) ($_SERVER['SERVER_PORT'] ?? '');
        if ($serverPort !== '' && !in_array($serverPort, ['80', '443'], true)) {
            $port = ':' . $serverPort;
        }
    }

    return rtrim($scheme . '://' . $lan . $port . dms_project_base(), '/');
}

/** رابط صفحة التحقق للبريد — فقط على نطاق عام. */
function dms_corr_public_verify_url(array $corr): string
{
    $token = trim((string) ($corr['qr_token'] ?? ''));
    if ($token === '' || !dms_http_host_is_public()) {
        return '';
    }
    return base_url('verify.php?token=' . rawurlencode($token));
}

/**
 * بيانات المعاملة المعروضة عند المسح (عربي للمعاينة).
 * @return array{org:string,type:string,ref:string}
 */
function dms_corr_qr_fields(array $corr): array
{
    $org = trim(org_name());
    if ($org === '') {
        $org = trim(org_name_en());
    }
    if ($org === '') {
        $org = 'المنشأة';
    }

    $corrType = (string) ($corr['type'] ?? '');
    if ($corrType === 'outgoing') {
        $type = 'معاملة صادرة';
    } elseif ($corrType === 'internal') {
        $type = 'معاملة داخلية';
    } else {
        $type = 'معاملة واردة';
    }
    $ref = format_ref_number($corr['ref_number'] ?? '');

    return [
        'org' => $org,
        'type' => $type,
        'ref' => $ref,
    ];
}

/** محتوى رمز QR: رابط صفحة التحقق فقط. */
function dms_corr_qr_content(array $corr, bool $compact = false): string
{
    unset($compact);
    if (function_exists('outgoing_show_official_ref') && !outgoing_show_official_ref($corr)) {
        return '';
    }
    $token = trim((string) ($corr['qr_token'] ?? ''));
    if ($token === '') {
        return '';
    }
    return dms_corr_qr_base_url() . '/verify.php?token=' . rawurlencode($token);
}

/** رابط التحقق مع التركيز على تأكيد الحضور. */
function dms_corr_rsvp_url(array $corr): string
{
    $url = dms_corr_qr_content($corr);
    if ($url === '') {
        return '';
    }
    return $url . (str_contains($url, '?') ? '&' : '?') . 'rsvp=1';
}

function dms_load_php_qrcode(): bool
{
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    if (!extension_loaded('gd')) {
        return $ok = false;
    }
    $path = __DIR__ . '/../lib/php_qrcode.php';
    if (!is_file($path)) {
        return $ok = false;
    }
    require_once $path;
    return $ok = class_exists('DmsQrCode', false);
}

/** PNG ثنائي لرمز QR (فارغ عند التعذر). */
function dms_qr_png_bytes(string $text, int $size = 160): string
{
    $text = trim($text);
    if ($text === '' || !dms_load_php_qrcode()) {
        return '';
    }
    $size = max(80, min(400, $size));
    try {
        $generator = new DmsQrCode($text, [
            's' => 'qrm',
            'w' => $size,
            'h' => $size,
            'p' => 8,
            'bc' => '#FFFFFF',
            'fc' => '#000000',
        ]);
        $img = $generator->render_image();
        if (!$img) {
            return '';
        }
        ob_start();
        imagepng($img);
        imagedestroy($img);
        return (string) ob_get_clean();
    } catch (Throwable $e) {
        return '';
    }
}

/** data-URI لطباعة/عرض رمز التحقق بدون JavaScript. */
function dms_corr_qr_data_uri(array $corr, int $size = 160, bool $rsvpFocus = false): string
{
    $text = $rsvpFocus ? dms_corr_rsvp_url($corr) : dms_corr_qr_content($corr);
    $bytes = dms_qr_png_bytes($text, $size);
    if ($bytes === '') {
        return '';
    }
    return 'data:image/png;base64,' . base64_encode($bytes);
}

/** ملف PNG مؤقت لتضمين QR في البريد (CID). احذفه بعد الإرسال. */
function dms_corr_qr_temp_png(array $corr, int $size = 160, bool $rsvpFocus = false): string
{
    $text = $rsvpFocus ? dms_corr_rsvp_url($corr) : dms_corr_qr_content($corr);
    $bytes = dms_qr_png_bytes($text, $size);
    if ($bytes === '') {
        return '';
    }
    $path = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
        . 'dms-qr-' . bin2hex(random_bytes(8)) . '.png';
    if (@file_put_contents($path, $bytes) === false) {
        return '';
    }
    return $path;
}

/** صندوق QR للطباعة — صورة من السيرفر أولاً. */
function render_print_qr_box(string $domId, array $corr, string $extraClass = ''): void
{
    $uri = dms_corr_qr_data_uri($corr, 160);
    $cls = trim('print-qr ' . $extraClass);
    echo '<div id="' . e($domId) . '" class="' . e($cls) . '">';
    if ($uri !== '') {
        echo '<img src="' . e($uri) . '" alt="" width="160" height="160" decoding="sync">';
    }
    echo '</div>';
}

function corr_sanitize_prefix(string $prefix): string
{
    $prefix = trim(str_replace(['/', '\\'], '', $prefix));
    if ($prefix === '') {
        return '';
    }
    return mb_substr($prefix, 0, 20);
}

/** وضع سنة الترقيم: hijri | gregorian | none */
function corr_numbering_year_mode(): string
{
    $mode = strtolower(trim((string) (function_exists('setting') ? setting('numbering_year_mode', 'hijri') : 'hijri')));
    return in_array($mode, ['hijri', 'gregorian', 'none'], true) ? $mode : 'hijri';
}

/** السنة الهجرية الكاملة لعدّاد الترقيم (مثال: 1448) */
function corr_numbering_hijri_year(?string $gregorianDate = null): int
{
    if ($gregorianDate !== null && $gregorianDate !== '') {
        $ts = strtotime($gregorianDate);
        if ($ts !== false) {
            $parts = gregorian_to_hijri_parts((int) date('Y', $ts), (int) date('n', $ts), (int) date('j', $ts));
            if ($parts !== null) {
                return (int) $parts[0];
            }
        }
    }
    if (function_exists('dms_current_hijri_parts')) {
        $parts = dms_current_hijri_parts();
        if (!empty($parts[0])) {
            return (int) $parts[0];
        }
    }
    return (int) date('Y') - 579;
}

/** سنة العدّاد حسب الإعداد: هجري كاملة، ميلادي كاملة، أو 0 بدون سنة */
function corr_numbering_year(?string $gregorianDate = null): int
{
    $mode = corr_numbering_year_mode();
    if ($mode === 'none') {
        return 0;
    }
    if ($mode === 'gregorian') {
        if ($gregorianDate !== null && $gregorianDate !== '') {
            $ts = strtotime($gregorianDate);
            if ($ts !== false) {
                return (int) date('Y', $ts);
            }
        }
        return (int) date('Y');
    }
    return corr_numbering_hijri_year($gregorianDate);
}

/** سنة الترقيم بخانتين حسب الإعداد؛ فارغ إذا كان الوضع «بدون سنة» */
function corr_year_yy(?int $year = null): string
{
    if (corr_numbering_year_mode() === 'none') {
        return '';
    }
    $year = $year ?? corr_numbering_year();
    if ((int) $year <= 0) {
        return '';
    }
    return sprintf('%02d', abs((int) $year) % 100);
}

/**
 * رقم التسلسل: 4 خانات على الأقل، ويتوسع تلقائياً بعد 9999.
 */
function corr_seq_pad(int $seq): string
{
    $seq = max(0, (int) $seq);
    $width = max(4, strlen((string) $seq));
    return sprintf('%0' . $width . 'd', $seq);
}

/** بناء رقم خام للتخزين: [بادئة/][سنة/]تسلسل */
function corr_build_ref_number(string $prefix, int $year, int $seq): string
{
    $pad = corr_seq_pad($seq);
    $yy = corr_year_yy($year);
    $prefix = corr_sanitize_prefix($prefix);
    $core = $yy !== '' ? ($yy . '/' . $pad) : $pad;
    return $prefix !== '' ? ($prefix . '/' . $core) : $core;
}

/** هل وُجدت أي بادئة مفعّلة في الإعدادات؟ */
function corr_ref_prefixes_enabled(): bool
{
    foreach (['incoming', 'outgoing', 'internal', 'circular'] as $type) {
        if (corr_global_prefix($type) !== '') {
            return true;
        }
    }
    return false;
}

/**
 * توحيد عرض الرقم المخزّن: تسلسل 4+، والسنة بخانتين إن وُجدت في الرقم.
 * البادئة تُعرض فقط إن فُعّلت في الإعدادات.
 */
function format_ref_number(string|int|null $ref): string
{
    $ref = trim((string) $ref);
    if ($ref === '') {
        return '';
    }
    $ref = trim($ref, "() \t\n\r");
    $prefix = '';
    $yearPart = '';
    $seqPart = '';
    if (preg_match('~^([^/]+)/(\d{2,4})/(\d+)$~u', $ref, $m)) {
        $prefix = corr_sanitize_prefix($m[1]);
        $yearPart = $m[2];
        $seqPart = $m[3];
    } elseif (preg_match('~^(\d{2,4})/(\d+)$~u', $ref, $m)) {
        $yearPart = $m[1];
        $seqPart = $m[2];
    } elseif (preg_match('~^([^/]+)/(\d+)$~u', $ref, $m) && !preg_match('~^\d{2,4}$~u', $m[1])) {
        $prefix = corr_sanitize_prefix($m[1]);
        $seqPart = $m[2];
    } elseif (preg_match('~^(\d+)$~u', $ref, $m)) {
        $seqPart = $m[1];
    } else {
        return $ref;
    }
    if ($prefix !== '' && !corr_ref_prefixes_enabled()) {
        $prefix = '';
    }
    $core = corr_seq_pad((int) $seqPart);
    if ($yearPart !== '') {
        $core = sprintf('%02d', abs((int) $yearPart) % 100) . '/' . $core;
    }
    if ($prefix !== '') {
        $core = $prefix . '/' . $core;
    }
    return $core;
}

/** تسمية نوع الرقم: رقم الصادر / الوارد / … */
function corr_ref_type_label(string $type): string
{
    return match ($type) {
        'outgoing' => __('corr.ref_label_outgoing'),
        'incoming' => __('corr.ref_label_incoming'),
        'internal' => __('corr.ref_label_internal'),
        'circular' => __('corr.ref_label_circular'),
        default => __('corr.number'),
    };
}

/** عرض كامل: رقم الصادر: 48/0004 */
function format_ref_caption(?string $type, string|int|null $ref): string
{
    $num = format_ref_number($ref);
    if ($num === '') {
        return '';
    }
    $type = (string) $type;
    if ($type === '') {
        return $num;
    }
    return corr_ref_type_label($type) . ': ' . $num;
}

/** ملصق حراري: التسمية عادية والرقم أكبر — سطر واحد */
function render_thermal_ref_caption(?string $type, string|int|null $ref): void
{
    $num = format_ref_number($ref);
    if ($num === '') {
        return;
    }
    $type = (string) $type;
    echo '<div class="print-thermal-ref" dir="rtl">';
    if ($type !== '') {
        echo '<span class="print-thermal-ref-label">' . e(corr_ref_type_label($type) . ':') . '</span>';
    }
    echo '<span class="print-thermal-ref-num" dir="ltr">' . e($num) . '</span>';
    echo '</div>';
}

/**
 * ملصق حراري واحد كما في صفحة الطباعة (50×25 عند المعاينة).
 * $copyIndex يميّز نسخ الطباعة. $rootId و $qrElementId لزر النسخ في صفحة العرض.
 */
function render_thermal_sticker(array $corr, int $copyIndex = 0, string $rootId = '', string $qrElementId = ''): void
{
    $qrId = $qrElementId !== '' ? $qrElementId : ('qrcode-' . $copyIndex);
    $logoUrl = function_exists('org_logo_url') ? org_logo_url() : '';
    $hasLogo = $logoUrl !== '';
    $qrContent = function_exists('dms_corr_qr_content') ? dms_corr_qr_content($corr) : '';
    $hasQr = $qrContent !== '';
    $corrDate = $corr['corr_date'] ?? null;
    $hijriStr = '';
    $gregorianStr = '';
    if ($corrDate) {
        $ts = strtotime((string) $corrDate);
        if ($ts !== false) {
            $gregorianStr = date('d/m/Y', $ts);
            if (function_exists('gregorian_to_hijri_parts')) {
                $hParts = gregorian_to_hijri_parts((int) date('Y', $ts), (int) date('n', $ts), (int) date('j', $ts));
                if ($hParts !== null) {
                    $hijriStr = sprintf('%02d/%02d/%04d', $hParts[2], $hParts[1], $hParts[0]);
                }
            }
        }
    }
    /** إظهار التاريخ الهجري على الملصق الحراري — عطّل مؤقتاً؛ أعد true لإظهاره. */
    $showThermalHijri = false;
    $hasHijriDate = $showThermalHijri && $hijriStr !== '';
    $hasGregorianDate = $gregorianStr !== '' && $gregorianStr !== '—';
    $hMark = __('corr.cal_h_mark');
    $gMark = __('corr.cal_g_mark');
    $renderThermalDate = static function (string $dmy, string $mark): void {
        $parts = explode('/', $dmy);
        if (count($parts) !== 3) {
            return;
        }
        echo '<span class="print-thermal-date" dir="rtl">';
        echo '<span class="print-thermal-date-num" dir="ltr">' . e($parts[0]) . '</span>';
        echo '<span class="print-thermal-date-slash">/</span>';
        echo '<span class="print-thermal-date-num" dir="ltr">' . e($parts[1]) . '</span>';
        echo '<span class="print-thermal-date-slash">/</span>';
        echo '<span class="print-thermal-date-num" dir="ltr">' . e($parts[2]) . '</span>';
        echo '<span class="print-label-mark">' . e($mark) . '</span>';
        echo '</span>';
    };
    $cls = 'print-thermal-sticker' . ($hasQr ? ' has-qr' : '') . ($hasLogo ? ' has-logo' : '');
    echo '<div class="' . e($cls) . '"';
    if ($rootId !== '') {
        echo ' id="' . e($rootId) . '"';
    }
    echo ' data-sticker-w="50" data-sticker-h="25" dir="rtl">';
    if ($hasLogo) {
        echo '<div class="print-thermal-logo">';
        echo '<img src="' . e($logoUrl) . '" alt="" class="print-thermal-logo-img">';
        echo '</div>';
    }
    echo '<div class="print-thermal-body" dir="rtl">';
    echo '<div class="print-thermal-text" dir="rtl">';
    render_thermal_ref_caption((string) ($corr['type'] ?? ''), $corr['ref_number'] ?? '');
    if ($hasHijriDate || $hasGregorianDate) {
        echo '<div class="print-thermal-meta print-label-dates" dir="rtl">';
        if ($hasHijriDate) {
            $renderThermalDate($hijriStr, $hMark);
        }
        if ($hasHijriDate && $hasGregorianDate) {
            echo '<span class="print-thermal-date-sep" aria-hidden="true">-</span>';
        }
        if ($hasGregorianDate) {
            $renderThermalDate($gregorianStr, $gMark);
        }
        echo '</div>';
    }
    echo '</div>';
    if ($hasQr) {
        echo '<div class="print-thermal-qr" id="' . e($qrId) . '" aria-hidden="true"></div>';
    }
    echo '</div></div>';
}

/** تطبيق صيغة العرض على حقول الرقم في صف معاملة */
function correspondence_apply_ref_display(array $row): array
{
    foreach (['ref_number', 'linked_ref', 'reply_ref'] as $key) {
        if (!array_key_exists($key, $row) || $row[$key] === null || $row[$key] === '') {
            continue;
        }
        $row[$key] = format_ref_number($row[$key]);
    }
    return $row;
}

/** مثال ترقيم للإعدادات والنماذج */
function corr_ref_sample(string $prefix = '', int $seq = 1): string
{
    return format_ref_number(corr_build_ref_number($prefix, corr_numbering_year(), $seq));
}

/** تسلسل من رقم مخزّن إن طابق البادئة وسنة العدّاد الحالية */
function corr_ref_extract_seq_for_year(string $ref, string $prefix, int $year): ?int
{
    $ref = trim($ref, "() \t");
    if ($ref === '') {
        return null;
    }
    $prefix = corr_sanitize_prefix($prefix);
    $yy = corr_year_yy($year);
    if ($yy === '') {
        if ($prefix !== '') {
            return preg_match('~^' . preg_quote($prefix, '~') . '/(\d+)$~u', $ref, $m) ? (int) $m[1] : null;
        }
        return preg_match('~^(\d+)$~u', $ref, $m) ? (int) $m[1] : null;
    }
    if ($prefix !== '') {
        if (!preg_match('~^' . preg_quote($prefix, '~') . '/(\d{2,4})/(\d+)$~u', $ref, $m)) {
            return null;
        }
        if (sprintf('%02d', abs((int) $m[1]) % 100) !== $yy) {
            return null;
        }
        return (int) $m[2];
    }
    if (!preg_match('~^(\d{2,4})/(\d+)$~u', $ref, $m)) {
        return null;
    }
    if (sprintf('%02d', abs((int) $m[1]) % 100) !== $yy) {
        return null;
    }
    return (int) $m[2];
}

function corr_global_prefix(string $type): string
{
    $key = match ($type) {
        'incoming' => 'incoming_prefix',
        'internal' => 'internal_prefix',
        'circular' => 'circular_prefix',
        default => 'outgoing_prefix',
    };
    return corr_sanitize_prefix((string) setting($key, ''));
}

function corr_department_custom_prefix(string $type, ?int $departmentId): string
{
    $departmentId = (int) $departmentId;
    if ($departmentId <= 0 || $type === 'internal' || $type === 'circular') {
        return '';
    }
    ensure_departments_schema();
    $col = $type === 'incoming' ? 'incoming_prefix' : 'outgoing_prefix';
    try {
        $stmt = db()->prepare("SELECT `$col` FROM `tbldepartments` WHERE id = ?");
        $stmt->execute([$departmentId]);
        return corr_sanitize_prefix((string) ($stmt->fetchColumn() ?: ''));
    } catch (Throwable $e) {
        return '';
    }
}

function corr_ref_prefix(string $type, ?int $departmentId = null): string
{
    $global = corr_global_prefix($type);
    $custom = corr_department_custom_prefix($type, $departmentId);
    if ($custom !== '' && $custom !== $global) {
        return $custom;
    }
    return $global;
}

/** نطاق عدّاد مستقل للقسم عند وجود بادئة خاصة أو معرّف قسم */
function corr_ref_sequence_scope(string $type, ?int $departmentId, string $prefix): string
{
    $global = corr_global_prefix($type);
    if ($prefix !== '' && $prefix !== $global) {
        return mb_substr($prefix, 0, 40);
    }
    $departmentId = (int) $departmentId;
    if ($departmentId > 0 && $type !== 'internal' && $type !== 'circular') {
        $custom = corr_department_custom_prefix($type, $departmentId);
        if ($custom !== '' && $custom !== $global) {
            return mb_substr($custom, 0, 40);
        }
    }
    return '';
}

function dms_sequences_type_enum_sql(): string
{
    return "ENUM('incoming','outgoing','internal','circular','archive') NOT NULL";
}

function ensure_sequences_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $pdo = db();
        $has = dms_db_table_exists('tblsequences');
        if (!$has) {
            $pdo->exec(
                "CREATE TABLE `tblsequences` (
                    seq_type " . dms_sequences_type_enum_sql() . ",
                    seq_year SMALLINT UNSIGNED NOT NULL,
                    seq_scope VARCHAR(40) NOT NULL DEFAULT '',
                    last_number INT UNSIGNED NOT NULL DEFAULT 0,
                    PRIMARY KEY (seq_type, seq_year, seq_scope)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            dms_db_tables_map(true);
            return;
        }
        $cols = dms_db_column_map('tblsequences');
        if (empty($cols['seq_scope'])) {
            $pdo->exec("ALTER TABLE `tblsequences` ADD COLUMN seq_scope VARCHAR(40) NOT NULL DEFAULT '' AFTER seq_year");
            $pdo->exec('ALTER TABLE `tblsequences` DROP PRIMARY KEY');
            $pdo->exec('ALTER TABLE `tblsequences` ADD PRIMARY KEY (seq_type, seq_year, seq_scope)');
        }
        // لا تنفّذ ALTER بلا حاجة — DDL يُنهي أي معاملة مفتوحة (مثل الصادر الجماعي)
        $seqType = function_exists('dms_db_column_meta')
            ? (string) ((dms_db_column_meta('tblsequences', 'seq_type')['COLUMN_TYPE'] ?? ''))
            : '';
        if ($seqType !== '' && (
            stripos($seqType, "'internal'") === false
            || stripos($seqType, "'circular'") === false
            || stripos($seqType, "'archive'") === false
        )) {
            try {
                $pdo->exec(
                    'ALTER TABLE `tblsequences` MODIFY COLUMN `seq_type` ' . dms_sequences_type_enum_sql()
                );
            } catch (Throwable $e) {
                // قد يكون موسّعاً مسبقاً
            }
        }
        dms_sequences_purge_untyped_rows($pdo);
    } catch (Throwable $e) {
        $done = false;
    }
}

/** صفوف بلا نوع (ENUM فارغ) من الجدول القديم — لا تُستخدم في الترقيم. */
function dms_sequences_purge_untyped_rows(PDO $pdo): void
{
    try {
        $pdo->exec(
            "DELETE FROM `tblsequences`
             WHERE seq_type IS NULL
                OR seq_type = ''
                OR seq_type NOT IN ('incoming','outgoing','internal','circular','archive')"
        );
    } catch (Throwable $e) {
        // ignore
    }
}

function next_ref_number(string $type, ?int $departmentId = null): string
{
    $refs = next_ref_numbers($type, 1, $departmentId);
    return $refs[0];
}

/**
 * تخصيص عدة أرقام متتالية ضمن قفل واحد (للصادر الجماعي).
 * لكل نوع (وارد / صادر / داخلي) عدّاد مستقل حسب بادئته.
 * @return list<string>
 */
function next_ref_numbers(string $type, int $count, ?int $departmentId = null): array
{
    $count = max(1, min(200, $count));
    $year = corr_numbering_year();
    $prefix = corr_ref_prefix($type, $departmentId);
    $scope = corr_ref_sequence_scope($type, $departmentId, $prefix);
    $seqType = in_array($type, ['incoming', 'outgoing', 'internal'], true) ? $type : 'outgoing';

    ensure_sequences_schema();
    $pdo = db();
    $ownTx = !$pdo->inTransaction();
    if ($ownTx) {
        $pdo->beginTransaction();
    }
    try {
        $usedMax = corr_ref_max_used_sequence($year, $prefix);
        $stmt = $pdo->prepare(
            "SELECT last_number FROM `tblsequences` WHERE seq_type = ? AND seq_year = ? AND seq_scope = ? FOR UPDATE"
        );
        $stmt->execute([$seqType, $year, $scope]);
        $last = $stmt->fetchColumn();
        if ($last === false) {
            $pdo->prepare(
                "INSERT INTO `tblsequences` (seq_type, seq_year, seq_scope, last_number) VALUES (?, ?, ?, 0)"
            )->execute([$seqType, $year, $scope]);
            $last = 0;
        }
        // بادئة بلا أرقام سابقة: سلسلة جديدة من 1، لا نكمل العدّاد المشترك القديم
        $seq = ($prefix !== '' && $usedMax === 0) ? 0 : max((int) $last, $usedMax);
        $refs = [];
        $n = 0;
        $guard = 0;
        while ($n < $count && $guard < $count + 500) {
            $guard++;
            $seq++;
            $candidate = corr_build_ref_number($prefix, $year, $seq);
            if (corr_ref_number_taken($candidate)) {
                continue;
            }
            $refs[] = $candidate;
            $n++;
        }
        if (count($refs) < $count) {
            throw new RuntimeException('تعذر تخصيص أرقام معاملات فريدة.');
        }
        $pdo->prepare(
            "UPDATE `tblsequences` SET last_number = ? WHERE seq_type = ? AND seq_year = ? AND seq_scope = ?"
        )->execute([$seq, $seqType, $year, $scope]);
        if ($ownTx) {
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if ($ownTx && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return $refs;
}

/** أعلى تسلسل مستخدم لنفس السنة/البادئة في المراسلات */
function corr_ref_max_used_sequence(int $year, string $prefix = ''): int
{
    $max = 0;
    try {
        $rows = db()->query('SELECT ref_number FROM `tblcorrespondence`')->fetchAll(PDO::FETCH_COLUMN);
        foreach ($rows as $ref) {
            $seq = corr_ref_extract_seq_for_year((string) $ref, $prefix, $year);
            if ($seq !== null) {
                $max = max($max, $seq);
            }
        }
    } catch (Throwable $e) {
        // تجاهل
    }
    return $max;
}

function corr_ref_number_taken(string $ref): bool
{
    $ref = trim($ref);
    if ($ref === '') {
        return false;
    }
    try {
        $stmt = db()->prepare('SELECT 1 FROM `tblcorrespondence` WHERE ref_number = ? LIMIT 1');
        $stmt->execute([$ref]);
        if ($stmt->fetchColumn()) {
            return true;
        }
    } catch (Throwable $e) {
        return false;
    }
    return false;
}

function categories_list(bool $activeOnly = true): array
{
    if (function_exists('ensure_lookups_schema')) {
        ensure_lookups_schema();
    }
    $sql = "SELECT * FROM `tblcategories`";
    if ($activeOnly) {
        $sql .= " WHERE is_active = 1";
    }
    $sql .= " ORDER BY sort_order, name, id";
    try {
        return db()->query($sql)->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * الجهات المتاحة للمستخدم: العامة + جهات قسمه، أو جميع الجهات للأدوار المركزية.
 * يمكن تضمين جهة المعاملة الحالية للحفاظ على السجل عند تعديل معاملة محالة.
 */
function entities_department_scope_available(): bool
{
    try {
        return dms_db_column_exists('tblentities', 'department_id');
    } catch (Throwable $e) {
        return false;
    }
}

function entities_list_filters_from_request(?array $user = null): array
{
    $user = $user ?? (function_exists('current_user') ? current_user() : null);
    $filters = [
        'q' => mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100, 'UTF-8'),
        'scope' => trim((string) ($_GET['scope'] ?? '')),
        'department_id' => max(0, (int) ($_GET['department_id'] ?? 0)),
        'status' => trim((string) ($_GET['status'] ?? '')),
        'contact' => trim((string) ($_GET['contact'] ?? '')),
    ];
    if (!in_array($filters['scope'], ['', 'public', 'department'], true)) {
        $filters['scope'] = '';
    }
    if (!in_array($filters['status'], ['', 'active', 'inactive'], true)) {
        $filters['status'] = '';
    }
    if (!in_array($filters['contact'], ['', 'has_email', 'has_mobile', 'has_both', 'missing'], true)) {
        $filters['contact'] = '';
    }
    if (!function_exists('can_view_all_departments') || !can_view_all_departments($user)) {
        $filters['department_id'] = 0;
    }
    return $filters;
}

function entities_filter_query_string(array $filters, array $extra = []): string
{
    $q = $extra;
    foreach (['q', 'scope', 'status', 'contact'] as $key) {
        if (($filters[$key] ?? '') !== '') {
            $q[$key] = $filters[$key];
        }
    }
    if (!empty($filters['department_id'])) {
        $q['department_id'] = (int) $filters['department_id'];
    }
    $built = http_build_query($q);
    return $built !== '' ? ('?' . $built) : '';
}

/**
 * شروط WHERE المشتركة بين جلب الجهات وعدّها، حتى لا يفترقا.
 *
 * @return array{where:list<string>,params:list<mixed>}
 */
function entities_list_where(bool $activeOnly, ?array $user, ?int $includeId, array $options): array
{
    $where = [];
    $params = [];
    $scopeAvailable = entities_department_scope_available();
    $filters = is_array($options['filters'] ?? null) ? $options['filters'] : [];
    $statusFilter = (string) ($filters['status'] ?? '');

    if ($statusFilter === 'active') {
        $where[] = 'e.is_active = 1';
    } elseif ($statusFilter === 'inactive') {
        $where[] = 'e.is_active = 0';
    } elseif ($activeOnly) {
        $where[] = 'e.is_active = 1';
    }

    if (
        $scopeAvailable
        && $user
        && ($user['role'] ?? '') === 'department'
        && function_exists('user_department_ids')
        && user_department_ids($user)
    ) {
        $ids = user_department_ids($user);
        [$inSql, $inParams] = dms_sql_in('e.department_id', $ids);
        $scope = '(e.department_id IS NULL OR ' . $inSql;
        $params = array_merge($params, $inParams);
        if ($includeId) {
            $scope .= ' OR e.id = ?';
            $params[] = $includeId;
        }
        $where[] = $scope . ')';
    }

    $scopeFilter = (string) ($filters['scope'] ?? '');
    $deptFilterId = (int) ($filters['department_id'] ?? 0);
    if (
        $deptFilterId > 0
        && function_exists('can_view_all_departments')
        && can_view_all_departments($user)
    ) {
        $where[] = 'e.department_id = ?';
        $params[] = $deptFilterId;
    } elseif ($scopeFilter === 'public') {
        $where[] = 'e.department_id IS NULL';
    } elseif ($scopeFilter === 'department') {
        if (
            $user
            && ($user['role'] ?? '') === 'department'
            && function_exists('user_department_ids')
            && user_department_ids($user)
        ) {
            [$inSql, $inParams] = dms_sql_in('e.department_id', user_department_ids($user));
            $where[] = $inSql;
            $params = array_merge($params, $inParams);
        } else {
            $where[] = 'e.department_id IS NOT NULL';
        }
    }

    $contactFilter = (string) ($filters['contact'] ?? '');
    if ($contactFilter === 'has_email') {
        $where[] = "NULLIF(TRIM(e.email), '') IS NOT NULL";
    } elseif ($contactFilter === 'has_mobile') {
        $where[] = "NULLIF(TRIM(e.mobile), '') IS NOT NULL";
    } elseif ($contactFilter === 'has_both') {
        $where[] = "NULLIF(TRIM(e.email), '') IS NOT NULL AND NULLIF(TRIM(e.mobile), '') IS NOT NULL";
    } elseif ($contactFilter === 'missing') {
        $where[] = "(NULLIF(TRIM(e.email), '') IS NULL OR NULLIF(TRIM(e.mobile), '') IS NULL)";
    }

    $q = trim((string) ($options['q'] ?? $filters['q'] ?? ''));
    if ($q !== '') {
        $picker = !empty($options['picker']);
        // قائمة الاختيار: أعمدة أساسية فقط لتسريع LIKE
        $columns = $picker
            ? [
                'e.name_ar',
                'e.name_en',
                'e.directed_to',
                'e.job_title',
                'e.mobile',
                'e.email',
            ]
            : [
                'e.name_ar',
                'e.name_en',
                'e.directed_to',
                'e.title_prefix',
                'e.job_title',
                'e.honorific',
                'e.mobile',
                'e.phone',
                'e.email',
                'e.address',
                'e.notes',
            ];
        if ($scopeAvailable && !$picker) {
            $columns[] = 'd.name';
            $columns[] = 'd.name_en';
        }
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
        $parts = [];
        foreach ($columns as $column) {
            $parts[] = $column . ' LIKE ?';
            $params[] = $like;
        }
        $where[] = '(' . implode(' OR ', $parts) . ')';
    }

    $ids = [];
    if (!empty($options['ids']) && is_array($options['ids'])) {
        foreach ($options['ids'] as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        if (count($ids) > 200) {
            $ids = array_slice($ids, 0, 200, true);
        }
    }
    if ($ids) {
        $where[] = 'e.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
        foreach ($ids as $id) {
            $params[] = $id;
        }
    }

    return ['where' => $where, 'params' => $params];
}

/**
 * الجزء الخاص بإحصاءات المراسلات، يُضاف كجدول فرعي حتى لا يتضخّم عدد الصفوف.
 *
 * @return array{select:string,join:string,params:list<mixed>}
 */
function entities_list_stats_sql(?array $user): array
{
    $whereParts = ['c.entity_id IS NOT NULL'];
    $params = [];
    [$corrScopeSql, $corrScopeParams] = department_scope_sql($user, 'c');
    if ($corrScopeSql !== '') {
        $whereParts[] = $corrScopeSql;
        $params = $corrScopeParams;
    }
    $statsWhere = implode(' AND ', $whereParts);

    return [
        'select' => ', COALESCE(ec.corr_count, 0) AS corr_count, ec.last_corr_date',
        'join' => " LEFT JOIN (
            SELECT c.entity_id, COUNT(*) AS corr_count, MAX(c.corr_date) AS last_corr_date
            FROM `tblcorrespondence` c
            WHERE {$statsWhere}
            GROUP BY c.entity_id
        ) ec ON ec.entity_id = e.id",
        'params' => $params,
    ];
}

function entities_list(bool $activeOnly = true, ?array $user = null, ?int $includeId = null, array $options = []): array
{
    ensure_entities_schema();
    $user = $user ?? (function_exists('current_user') ? current_user() : null);
    $scopeAvailable = entities_department_scope_available();
    $withStats = !empty($options['with_stats']);

    $built = entities_list_where($activeOnly, $user, $includeId, $options);
    $params = [];
    $statsSelect = '';
    $statsJoin = '';
    if ($withStats) {
        $stats = entities_list_stats_sql($user);
        $statsSelect = $stats['select'];
        $statsJoin = $stats['join'];
        $params = $stats['params'];
    }

    $picker = !empty($options['picker']) && !$withStats;
    if ($picker) {
        $sql = $scopeAvailable
            ? "SELECT e.id, e.name_ar, e.name_en, e.directed_to, e.title_prefix, e.job_title, e.honorific,
                      e.mobile, e.email, e.department_id, e.sort_order, e.is_active,
                      d.name AS department_name, d.name_en AS department_name_en
               FROM `tblentities` e
               LEFT JOIN `tbldepartments` d ON d.id = e.department_id"
            : "SELECT e.id, e.name_ar, e.name_en, e.directed_to, e.title_prefix, e.job_title, e.honorific,
                      e.mobile, e.email, e.department_id, e.sort_order, e.is_active,
                      NULL AS department_name, NULL AS department_name_en
               FROM `tblentities` e";
    } else {
        $sql = $scopeAvailable
            ? "SELECT e.*, d.name AS department_name, d.name_en AS department_name_en{$statsSelect}
               FROM `tblentities` e
               LEFT JOIN `tbldepartments` d ON d.id = e.department_id{$statsJoin}"
            : "SELECT e.*, NULL AS department_id, NULL AS department_name, NULL AS department_name_en{$statsSelect}
               FROM `tblentities` e{$statsJoin}";
    }    if ($built['where']) {
        $sql .= ' WHERE ' . implode(' AND ', $built['where']);
    }
    $params = array_merge($params, $built['params']);

    if ($withStats) {
        $sql .= $scopeAvailable
            ? ' ORDER BY e.sort_order ASC, e.department_id IS NOT NULL, d.name, e.name_ar, e.id'
            : ' ORDER BY e.sort_order ASC, e.name_ar, e.id';
    } else {
        $idOrder = [];
        if (!empty($options['ids']) && is_array($options['ids'])) {
            foreach ($options['ids'] as $id) {
                $id = (int) $id;
                if ($id > 0) {
                    $idOrder[] = $id;
                }
            }
            $idOrder = array_values(array_unique($idOrder));
        }
        if ($idOrder) {
            $sql .= ' ORDER BY FIELD(e.id, ' . implode(',', $idOrder) . ')';
        } elseif ($scopeAvailable) {
            $sql .= ' ORDER BY e.sort_order ASC, e.department_id IS NOT NULL, d.name, e.name_ar, e.id';
        } else {
            $sql .= ' ORDER BY e.sort_order ASC, e.name_ar, e.id';
        }
    }

    $limit = isset($options['limit']) ? (int) $options['limit'] : null;
    if ($limit !== null && $limit > 0) {
        $sql .= ' LIMIT ' . $limit . ' OFFSET ' . max(0, (int) ($options['offset'] ?? 0));
    }

    try {
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function entities_list_count(bool $activeOnly = true, ?array $user = null, ?int $includeId = null, array $options = []): int
{
    ensure_entities_schema();
    $user = $user ?? (function_exists('current_user') ? current_user() : null);
    $scopeAvailable = entities_department_scope_available();

    $built = entities_list_where($activeOnly, $user, $includeId, $options);
    $sql = $scopeAvailable
        ? 'SELECT COUNT(*) FROM `tblentities` e LEFT JOIN `tbldepartments` d ON d.id = e.department_id'
        : 'SELECT COUNT(*) FROM `tblentities` e';
    if ($built['where']) {
        $sql .= ' WHERE ' . implode(' AND ', $built['where']);
    }

    try {
        $stmt = db()->prepare($sql);
        $stmt->execute($built['params']);
        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

/**
 * @return array{rows:list<array<string,mixed>>,pagination:array<string,mixed>,total:int}
 */
function entities_list_page(bool $activeOnly = true, ?array $user = null, array $options = [], int $page = 1, ?int $perPage = null): array
{
    $perPage = $perPage ?? dms_list_per_page();
    $total = entities_list_count($activeOnly, $user, null, $options);
    $pagination = dms_pagination_meta($total, $page, $perPage);
    $rows = entities_list($activeOnly, $user, null, $options + [
        'limit' => $pagination['per_page'],
        'offset' => $pagination['offset'],
    ]);

    return [
        'rows' => $rows,
        'pagination' => $pagination,
        'total' => $total,
    ];
}

function get_entity(int $id): ?array
{
    ensure_entities_schema();
    $stmt = db()->prepare('SELECT * FROM `tblentities` WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** عبارات التكريم الافتراضية في الخطابات */
function entity_honorific_base_presets(): array
{
    return [
        '' => __('entities.honorific_none'),
        'حفظه الله' => __('entities.honorific_him'),
        'حفظها الله' => __('entities.honorific_her'),
        'حفظهم الله' => __('entities.honorific_them'),
    ];
}

/** @return string[] */
function entity_honorific_extra_presets(): array
{
    $raw = setting('entity_honorific_extra', '[]');
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [];
    }
    $out = [];
    foreach ($decoded as $item) {
        $value = trim((string) $item);
        if ($value !== '' && !in_array($value, $out, true)) {
            $out[] = $value;
        }
    }
    return $out;
}

function entity_honorific_presets(): array
{
    $presets = entity_honorific_base_presets();
    foreach (entity_honorific_extra_presets() as $extra) {
        if (!array_key_exists($extra, $presets)) {
            $presets[$extra] = $extra;
        }
    }
    return $presets;
}

function entity_honorific_save_extra_preset(string $value): bool
{
    $value = trim($value);
    if ($value === '' || mb_strlen($value) > 100) {
        return false;
    }
    if (array_key_exists($value, entity_honorific_base_presets())) {
        return false;
    }
    $extras = entity_honorific_extra_presets();
    if (in_array($value, $extras, true)) {
        return true;
    }
    $extras[] = $value;
    set_setting('entity_honorific_extra', json_encode(array_values($extras), JSON_UNESCAPED_UNICODE), 'بنود التكريم الإضافية لجهات المراسلة');
    return true;
}

/** @return string[] */
function entity_title_prefix_split_candidates(): array
{
    return [
        'صاحب السمو الملكي الأمير',
        'صاحب السمو الأمير',
        'صاحبة السمو الأميرة',
        'سعادة الأمين العام',
        'سعادة المحافظ',
        'سعادة الأستاذ',
        'سعادة الدكتور',
        'معالي الوزير',
        'سعادة',
        'معالي',
    ];
}

function entity_title_prefix_from_input(array $input): string
{
    $value = trim((string) ($input['title_prefix'] ?? ''));
    return mb_substr($value, 0, 255);
}

function entity_choice_from_input(array $input, string $choiceKey, string $customKey, array $presets): string
{
    $choice = trim((string) ($input[$choiceKey] ?? ''));
    $custom = trim((string) ($input[$customKey] ?? ''));
    if ($choice === '__custom__') {
        return mb_substr($custom, 0, 255);
    }
    if (array_key_exists($choice, $presets)) {
        return $choice;
    }
    return mb_substr($custom, 0, 255);
}

function entity_honorific_from_input(array $input): string
{
    return mb_substr(entity_choice_from_input($input, 'honorific_choice', 'honorific_custom', entity_honorific_presets()), 0, 100);
}

function entity_maps_url_normalize(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }
    if (!preg_match('#^https?://#i', $url)) {
        $url = 'https://' . $url;
    }
    return $url;
}

function entity_maps_url_valid(string $url): bool
{
    if ($url === '') {
        return true;
    }
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));
    if ($host === '') {
        return false;
    }
    if (in_array($host, ['goo.gl', 'maps.app.goo.gl'], true)) {
        return true;
    }
    return (bool) preg_match('/(^|\.)google(\.[a-z]{2,3}){0,2}$/', $host);
}

function entity_google_maps_url(array $entity): string
{
    $maps = entity_maps_url_normalize((string) ($entity['location'] ?? ''));
    if ($maps !== '' && entity_maps_url_valid($maps)) {
        return $maps;
    }
    return '';
}

/** رابط يُرمَّز في QR لموقع جهة العمل (خرائط Google أو بحث بالعنوان). */
function entity_location_qr_url(array $entity): string
{
    $maps = entity_google_maps_url($entity);
    if ($maps !== '') {
        return $maps;
    }
    $address = trim((string) ($entity['address'] ?? ''));
    if ($address !== '') {
        return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($address);
    }
    return '';
}

function entity_visible_to_user(int $entityId, ?array $user = null): bool
{
    $user = $user ?? (function_exists('current_user') ? current_user() : null);
    if ($entityId <= 0) {
        return false;
    }
    foreach (entities_list(false, $user, $entityId) as $row) {
        if ((int) ($row['id'] ?? 0) === $entityId) {
            return true;
        }
    }
    return false;
}

/**
 * @return array{title_prefix: string, directed_to: string}
 */
function entity_split_directed_to_prefix(string $directedTo): array
{
    $directedTo = trim($directedTo);
    if ($directedTo === '') {
        return ['title_prefix' => '', 'directed_to' => ''];
    }
    $prefixes = entity_title_prefix_split_candidates();
    foreach ($prefixes as $prefix) {
        if ($prefix === '') {
            continue;
        }
        $len = mb_strlen($prefix);
        if (mb_substr($directedTo, 0, $len) !== $prefix) {
            continue;
        }
        $rest = trim(mb_substr($directedTo, $len));
        if ($rest === '') {
            continue;
        }
        return ['title_prefix' => $prefix, 'directed_to' => $rest];
    }
    return ['title_prefix' => '', 'directed_to' => $directedTo];
}

function entity_migrate_title_prefix_from_directed_to(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $rows = $pdo->query(
            "SELECT id, directed_to FROM `tblentities`
             WHERE directed_to IS NOT NULL AND TRIM(directed_to) <> ''
               AND (title_prefix IS NULL OR TRIM(title_prefix) = '')"
        )->fetchAll();
        $stmt = $pdo->prepare('UPDATE `tblentities` SET title_prefix = ?, directed_to = ? WHERE id = ?');
        foreach ($rows as $row) {
            $split = entity_split_directed_to_prefix((string) ($row['directed_to'] ?? ''));
            if ($split['title_prefix'] === '') {
                continue;
            }
            $stmt->execute([
                $split['title_prefix'],
                $split['directed_to'] !== '' ? $split['directed_to'] : null,
                (int) $row['id'],
            ]);
        }
    } catch (Throwable $e) {
        error_log('DMS entity title_prefix migration: ' . $e->getMessage());
    }
}

function department_migrate_title_prefix_from_directed_from(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $rows = $pdo->query(
            "SELECT id, directed_from FROM `tbldepartments`
             WHERE directed_from IS NOT NULL AND TRIM(directed_from) <> ''
               AND (title_prefix IS NULL OR TRIM(title_prefix) = '')"
        )->fetchAll();
        $stmt = $pdo->prepare('UPDATE `tbldepartments` SET title_prefix = ?, directed_from = ? WHERE id = ?');
        foreach ($rows as $row) {
            $split = entity_split_directed_to_prefix((string) ($row['directed_from'] ?? ''));
            if ($split['title_prefix'] === '') {
                continue;
            }
            $stmt->execute([
                $split['title_prefix'],
                $split['directed_to'] !== '' ? $split['directed_to'] : null,
                (int) $row['id'],
            ]);
        }
    } catch (Throwable $e) {
        error_log('DMS department title_prefix migration: ' . $e->getMessage());
    }
}

function entity_recipient_name(array $row): string
{
    $name = trim((string) ($row['entity_directed_to'] ?? $row['directed_to'] ?? ''));
    if ($name === '') {
        $name = trim((string) ($row['party_name'] ?? $row['name_ar'] ?? ''));
    }
    return $name;
}

/**
 * بيانات صف واحد في قائمة اختيار الجهة.
 * مشتركة بين الصفحة ونقطة البحث حتى يبقى العرض واحدًا في الجهتين.
 *
 * @return array<string,mixed>
 */
function entity_picker_payload(array $row, bool $isIncoming): array
{
    $org = bilingual_label($row, 'name_ar', 'name_en');
    $person = trim((string) ($row['directed_to'] ?? ''));
    $job = trim((string) ($row['job_title'] ?? ''));
    $letter = format_entity_honored_name($row);
    $displayParts = array_filter([$person, $job, $org], static fn(string $p): bool => $p !== '');

    if (empty($row['department_id'])) {
        $scopeText = __('entities.scope_public');
        $scopeIcon = 'bi-globe2';
        $scopeModifier = 'is-public';
        $scopeTitle = $scopeText;
    } else {
        $scopeText = department_display_name($row, 'department_name', 'department_name_en') ?: __('common.none');
        $scopeIcon = 'bi-building';
        $scopeModifier = 'is-department';
        $scopeTitle = __('entities.scope_department') . ': ' . $scopeText;
    }

    return [
        'id' => (int) ($row['id'] ?? 0),
        'party' => $isIncoming ? $org : entity_recipient_name($row),
        'display' => implode(' — ', $displayParts) ?: $org,
        'letter' => $letter !== '' ? $letter : $org,
        'prefix' => trim((string) ($row['title_prefix'] ?? '')),
        'person' => $person,
        'job' => $job,
        'org' => $org,
        'mobile' => trim((string) ($row['mobile'] ?? '')),
        'email' => trim((string) ($row['email'] ?? '')),
        'scope_icon' => $scopeIcon,
        'scope_modifier' => $scopeModifier,
        'scope_title' => $scopeTitle,
        'scope_text' => $scopeText,
    ];
}

function entity_title_prefix_value(array $row): string
{
    return trim((string) ($row['entity_title_prefix'] ?? $row['title_prefix'] ?? ''));
}

function department_title_prefix_value(array $row): string
{
    return trim((string) ($row['department_title_prefix'] ?? $row['title_prefix'] ?? ''));
}

function entity_honorific_value(array $row): string
{
    return trim((string) ($row['entity_honorific'] ?? $row['honorific'] ?? ''));
}

/**
 * عمودا «موجهة إلى» و«جهة العمل» بنفس أسلوب قائمة الجهات:
 * اللقب أصغر من الاسم، والوظيفة أصغر من جهة العمل.
 *
 * @param array{prefix?:string,person?:string,honorific?:string,job?:string,org?:string} $party
 */
function render_entity_party_columns(array $party): void
{
    $prefix = trim((string) ($party['prefix'] ?? ''));
    $person = trim((string) ($party['person'] ?? ''));
    $honorific = trim((string) ($party['honorific'] ?? ''));
    $job = trim((string) ($party['job'] ?? ''));
    $org = trim((string) ($party['org'] ?? ''));
    if ($org !== '' && $person !== '' && $org === $person) {
        $org = '';
    }

    echo '<td class="entities-col-letter">';
    if ($prefix !== '') {
        echo '<div class="admin-entity-sub">' . e($prefix) . '</div>';
    }
    if ($person !== '') {
        echo '<div class="entities-letter-line">' . e($person) . '</div>';
    } elseif ($prefix === '' && $honorific === '') {
        echo '<span class="text-muted">' . e(__('common.none')) . '</span>';
    }
    if ($honorific !== '') {
        echo '<div class="admin-entity-sub entities-honorific">' . e($honorific) . '</div>';
    }
    echo '</td>';

    echo '<td class="entities-col-name">';
    if ($job !== '') {
        echo '<div class="admin-entity-sub">' . e($job) . '</div>';
    }
    if ($org !== '') {
        echo '<div class="admin-entity-name">' . e($org) . '</div>';
    } elseif ($job === '') {
        echo '<span class="text-muted">' . e(__('common.none')) . '</span>';
    }
    echo '</td>';
}

function format_entity_honored_name(array $row): string
{
    $type = (string) ($row['type'] ?? '');
    if ($type === 'internal') {
        $partyName = trim((string) ($row['party_name'] ?? ''));
        if ($partyName !== '') {
            return $partyName;
        }
        if (function_exists('correspondence_internal_route_names')) {
            $route = correspondence_internal_route_names($row);
            if ($route['from'] !== '' || $route['to'] !== '') {
                return trim($route['from'] . ' → ' . $route['to'], " →");
            }
        }
        $dept = department_display_name($row, 'department_name', 'department_name_en');
        return $dept !== '' ? $dept : '';
    }
    if ($type === 'incoming' || $type === 'outgoing') {
        $party = correspondence_archived_party($row);
        $parts = array_values(array_filter([
            $party['prefix'],
            $party['person'],
            $party['honorific'],
        ], static fn(string $part): bool => $part !== ''));
        return implode(' ', $parts);
    }
    $parts = array_values(array_filter([
        entity_title_prefix_value($row),
        entity_recipient_name($row),
        entity_honorific_value($row),
    ], static fn(string $part): bool => $part !== ''));
    return implode(' ', $parts);
}

/** اسم الجهة/الشخص للعرض في القوائم والعرض والتحقق */
function correspondence_party_display_name(array $row): string
{
    $honored = format_entity_honored_name($row);
    if ($honored !== '') {
        return $honored;
    }
    // إن وُجدت لقطة أطراف فلا تُعرض party_name كبديل للاسم الفارغ
    if (correspondence_has_party_snapshot($row)) {
        return '';
    }
    return trim((string) ($row['party_name'] ?? ''));
}

function correspondence_snapshot_column_names(): array
{
    return [
        'snap_title_prefix',
        'snap_person_name',
        'snap_job_title',
        'snap_honorific',
        'snap_org_name',
        'snap_from_prefix',
        'snap_from_person',
        'snap_from_job',
        'snap_from_org',
        'snap_from_signature',
    ];
}

function correspondence_has_party_snapshot(array $row): bool
{
    foreach (['snap_person_name', 'snap_org_name', 'snap_title_prefix', 'snap_job_title', 'snap_honorific'] as $key) {
        if (trim((string) ($row[$key] ?? '')) !== '') {
            return true;
        }
    }
    return false;
}

function correspondence_has_from_snapshot(array $row): bool
{
    foreach (['snap_from_person', 'snap_from_prefix', 'snap_from_job', 'snap_from_org', 'snap_from_signature'] as $key) {
        if (trim((string) ($row[$key] ?? '')) !== '') {
            return true;
        }
    }
    return false;
}

/** بيانات الجهة كما حُفظت على المعاملة (أرشيف ثابت) مع رجوع للسجل الحي إن لم تُحفظ */
function correspondence_archived_party(array $row): array
{
    $type = (string) ($row['type'] ?? '');

    if (correspondence_has_party_snapshot($row)) {
        $person = trim((string) ($row['snap_person_name'] ?? ''));
        $org = trim((string) ($row['snap_org_name'] ?? ''));
        // بيانات قديمة خاطئة: جهة العمل = الاسم → لا تُعرض مرتين
        if ($org !== '' && $person !== '' && $org === $person) {
            $org = '';
        }
        return [
            'prefix' => trim((string) ($row['snap_title_prefix'] ?? '')),
            'person' => $person,
            'honorific' => trim((string) ($row['snap_honorific'] ?? '')),
            'job' => trim((string) ($row['snap_job_title'] ?? '')),
            'org' => $org,
        ];
    }

    if ($type === 'internal') {
        $person = trim((string) ($row['department_directed_from'] ?? ''));
        $org = '';
        if (function_exists('department_display_name')) {
            $org = department_display_name($row, 'department_name', 'department_name_en');
        }
        if ($org === '') {
            $org = trim((string) ($row['department_name'] ?? ''));
        }
        if ($org !== '' && $person !== '' && $org === $person) {
            $org = '';
        }
        return [
            'prefix' => department_title_prefix_value($row),
            'person' => $person,
            'honorific' => '',
            'job' => trim((string) ($row['department_job_title'] ?? '')),
            'org' => $org,
        ];
    }

    $isOutgoing = $type === 'outgoing';
    $partyName = trim((string) ($row['party_name'] ?? ''));
    $livePerson = trim((string) ($row['entity_directed_to'] ?? $row['directed_to'] ?? ''));
    $org = bilingual_label([
        'name_ar' => (string) ($row['entity_name'] ?? $row['name_ar'] ?? ''),
        'name_en' => (string) ($row['entity_name_en'] ?? $row['name_en'] ?? ''),
    ], 'name_ar', 'name_en');

    if ($isOutgoing) {
        // صادر: party_name = اسم الشخص، وليس جهة العمل
        $person = $livePerson !== '' ? $livePerson : $partyName;
    } else {
        // وارد: لا تُستبدل جهة العمل الفارغة بالاسم
        $person = $livePerson;
    }

    if ($org !== '' && $person !== '' && $org === $person) {
        $org = '';
    }

    return [
        'prefix' => entity_title_prefix_value($row),
        'person' => $person,
        'honorific' => entity_honorific_value($row),
        'job' => trim((string) ($row['entity_job_title'] ?? $row['job_title'] ?? '')),
        'org' => $org,
    ];
}

function correspondence_archived_from(array $row): array
{
    $org = trim((string) ($row['snap_from_org'] ?? ''));
    if ($org === '') {
        $org = print_org_work_name();
    }
    if (correspondence_has_from_snapshot($row)) {
        return [
            'prefix' => trim((string) ($row['snap_from_prefix'] ?? '')),
            'person' => trim((string) ($row['snap_from_person'] ?? '')),
            'honorific' => '',
            'job' => trim((string) ($row['snap_from_job'] ?? '')),
            'org' => $org,
            'titles' => [],
        ];
    }
    $prefix = trim((string) ($row['issuing_title_prefix'] ?? ''));
    if ($prefix === '') {
        $prefix = department_title_prefix_value($row);
    }
    $person = trim((string) ($row['issuing_directed_from'] ?? ''));
    if ($person === '') {
        $person = trim((string) ($row['department_directed_from'] ?? ''));
    }
    $job = trim((string) ($row['issuing_job_title'] ?? ''));
    if ($job === '') {
        $job = trim((string) ($row['department_job_title'] ?? ''));
    }

    return [
        'prefix' => $prefix,
        'person' => $person,
        'honorific' => '',
        'job' => $job,
        'org' => $org,
        'titles' => [],
    ];
}

/**
 * عرض طرف المعاملة: اللقب، الاسم، الوظيفة، جهة العمل — كل منها في سطر.
 *
 * @param array{prefix?:string,person?:string,honorific?:string,job?:string,org?:string} $part
 */
function render_corr_list_person(array $part): void
{
    $prefix = trim((string) ($part['prefix'] ?? ''));
    $person = trim((string) ($part['person'] ?? ''));
    if ($prefix !== '') {
        echo '<div class="small text-muted lh-sm">' . e($prefix) . '</div>';
    }
    if ($person !== '') {
        echo '<div' . ($prefix !== '' ? ' class="fw-semibold"' : '') . '>' . e($person) . '</div>';
    }
}

function render_officer_office_line(string $person, string $office): void
{
    $person = trim($person);
    $office = trim($office);
    if ($office === '' || ($person !== '' && $office === $person)) {
        return;
    }
    if ($person === '') {
        echo '<div>' . e($office) . '</div>';
        return;
    }
    echo '<div class="corr-officer-pick-office">' . e($office) . '</div>';
}

function render_corr_party_lines(array $part): void
{
    $prefix = trim((string) ($part['prefix'] ?? ''));
    $person = trim((string) ($part['person'] ?? ''));
    $honorific = trim((string) ($part['honorific'] ?? ''));
    $job = trim((string) ($part['job'] ?? ''));
    $org = trim((string) ($part['org'] ?? ''));
    if ($org !== '' && $person !== '' && $org === $person) {
        $org = '';
    }
    echo '<div class="corr-party-stack">';
    if ($prefix !== '') {
        echo '<div class="corr-party-prefix">' . e($prefix) . '</div>';
    }
    if ($person !== '' || $honorific !== '') {
        echo '<div class="corr-party-name">';
        if ($person !== '') {
            echo e($person);
        }
        if ($honorific !== '') {
            echo ($person !== '' ? ' ' : '') . '<span class="corr-party-honorific">' . e($honorific) . '</span>';
        }
        echo '</div>';
    }
    if ($job !== '') {
        echo '<div class="corr-party-job">' . e($job) . '</div>';
    }
    if ($org !== '') {
        echo '<div class="corr-party-org">' . e($org) . '</div>';
    }
    echo '</div>';
}

function correspondence_snapshot_from_row(array $row): array
{
    $out = [];
    foreach (correspondence_snapshot_column_names() as $key) {
        $value = trim((string) ($row[$key] ?? ''));
        $out[$key] = $value !== '' ? $value : null;
    }
    return $out;
}

function correspondence_capture_party_snapshot(?array $entity, string $type, string $partyFallback): array
{
    $partyFallback = trim($partyFallback);
    $snap = [
        'snap_title_prefix' => null,
        'snap_person_name' => null,
        'snap_job_title' => null,
        'snap_honorific' => null,
        'snap_org_name' => null,
    ];
    if (!$entity) {
        if ($type === 'outgoing') {
            $snap['snap_person_name'] = $partyFallback !== '' ? $partyFallback : null;
        } else {
            $snap['snap_org_name'] = $partyFallback !== '' ? $partyFallback : null;
        }
        return $snap;
    }
    $person = trim((string) ($entity['directed_to'] ?? ''));
    $org = trim((string) ($entity['name_ar'] ?? ''));
    $snap['snap_title_prefix'] = entity_title_prefix_value($entity) ?: null;
    $snap['snap_person_name'] = $person !== '' ? $person : ($type === 'outgoing' && $partyFallback !== '' ? $partyFallback : null);
    $snap['snap_job_title'] = trim((string) ($entity['job_title'] ?? '')) ?: null;
    $snap['snap_honorific'] = entity_honorific_value($entity) ?: null;
    // لا تُملأ جهة العمل باسم بديل إن كانت فارغة
    $snap['snap_org_name'] = $org !== '' ? $org : null;
    return $snap;
}

function correspondence_capture_from_snapshot(?int $deptId): array
{
    $snap = [
        'snap_from_prefix' => null,
        'snap_from_person' => null,
        'snap_from_job' => null,
        'snap_from_org' => null,
        'snap_from_signature' => null,
    ];
    if ($deptId === null || $deptId <= 0) {
        return $snap;
    }
    $dept = function_exists('admin_row') ? admin_row('tbldepartments', $deptId) : null;
    if (!$dept) {
        return $snap;
    }
    $officer = function_exists('department_officer_fields') ? department_officer_fields($dept) : [
        'title_prefix' => trim((string) ($dept['title_prefix'] ?? '')),
        'directed_from' => trim((string) ($dept['directed_from'] ?? '')),
        'job_title' => trim((string) ($dept['job_title'] ?? '')),
    ];
    $snap['snap_from_prefix'] = $officer['title_prefix'] !== '' ? $officer['title_prefix'] : null;
    $snap['snap_from_person'] = $officer['directed_from'] !== '' ? $officer['directed_from'] : null;
    $snap['snap_from_job'] = $officer['job_title'] !== '' ? $officer['job_title'] : null;
    $snap['snap_from_org'] = print_org_work_name() ?: null;
    $sign = function_exists('department_principal_signature_path') ? department_principal_signature_path($dept) : '';
    $snap['snap_from_signature'] = $sign !== '' ? $sign : null;
    return $snap;
}

/**
 * لقطة قسم كطرف مستلم (للمعاملات الداخلية): الموجّه إليه + اسم القسم.
 *
 * @param array<string,mixed> $dept
 * @return array{snap_title_prefix:?string,snap_person_name:?string,snap_job_title:?string,snap_honorific:?string,snap_org_name:?string}
 */
function correspondence_capture_department_party_snapshot(array $dept, string $orgFallback = ''): array
{
    $org = trim((string) (function_exists('department_display_name') ? department_display_name($dept) : ($dept['name'] ?? '')));
    if ($org === '') {
        $org = trim($orgFallback);
    }

    $officer = function_exists('department_officer_fields') ? department_officer_fields($dept) : [
        'title_prefix' => trim((string) ($dept['title_prefix'] ?? '')),
        'directed_from' => trim((string) ($dept['directed_from'] ?? '')),
        'job_title' => trim((string) ($dept['job_title'] ?? '')),
    ];
    return [
        'snap_title_prefix' => $officer['title_prefix'] !== '' ? $officer['title_prefix'] : null,
        'snap_person_name' => $officer['directed_from'] !== '' ? $officer['directed_from'] : null,
        'snap_job_title' => $officer['job_title'] !== '' ? $officer['job_title'] : null,
        'snap_honorific' => null,
        'snap_org_name' => $org !== '' ? $org : null,
    ];
}

/**
 * أسماء مسار المعاملة الداخلية كما حُفظت وقت الإنشاء (مع رجوع للأسماء الحية إن لزم).
 *
 * @return array{from:string,to:string}
 */
function correspondence_internal_route_names(array $corr): array
{
    $from = '';
    $to = '';
    $party = trim((string) ($corr['party_name'] ?? ''));
    if ($party !== '' && str_contains($party, '→')) {
        $bits = array_map('trim', explode('→', $party, 2));
        $from = (string) ($bits[0] ?? '');
        $to = (string) ($bits[1] ?? '');
    }
    if ($to === '' && correspondence_has_party_snapshot($corr)) {
        $to = trim((string) ($corr['snap_org_name'] ?? ''));
    }
    if ($from === '' && function_exists('department_display_name')) {
        $from = department_display_name($corr, 'issuing_department_name', 'issuing_department_name_en');
    }
    if ($to === '' && function_exists('department_display_name')) {
        $to = department_display_name($corr, 'department_name', 'department_name_en');
    }

    return ['from' => $from, 'to' => $to];
}

function ensure_correspondence_party_snapshot_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $cols = dms_db_column_map('tblcorrespondence');
        $alters = [];
        $after = !empty($cols['party_name']) ? 'party_name' : 'subject';
        $defs = [
            'snap_title_prefix' => "VARCHAR(255) NULL DEFAULT NULL COMMENT 'أرشيف: لقب الجهة وقت الإنشاء'",
            'snap_person_name' => "VARCHAR(255) NULL DEFAULT NULL COMMENT 'أرشيف: اسم الشخص وقت الإنشاء'",
            'snap_job_title' => "VARCHAR(255) NULL DEFAULT NULL COMMENT 'أرشيف: الوظيفة وقت الإنشاء'",
            'snap_honorific' => "VARCHAR(100) NULL DEFAULT NULL COMMENT 'أرشيف: التكريم وقت الإنشاء'",
            'snap_org_name' => "VARCHAR(255) NULL DEFAULT NULL COMMENT 'أرشيف: جهة العمل وقت الإنشاء'",
            'snap_from_prefix' => "VARCHAR(255) NULL DEFAULT NULL COMMENT 'أرشيف: لقب الموجّه من القسم'",
            'snap_from_person' => "VARCHAR(255) NULL DEFAULT NULL COMMENT 'أرشيف: اسم الموجّه من القسم'",
            'snap_from_job' => "VARCHAR(255) NULL DEFAULT NULL COMMENT 'أرشيف: وظيفة الموجّه من القسم'",
            'snap_from_org' => "VARCHAR(255) NULL DEFAULT NULL COMMENT 'أرشيف: جهة العمل من الإعدادات'",
            'snap_from_signature' => "VARCHAR(255) NULL DEFAULT NULL COMMENT 'أرشيف: توقيع القسم وقت الإنشاء'",
        ];
        foreach ($defs as $name => $def) {
            if (empty($cols[$name])) {
                $alters[] = "ADD COLUMN `{$name}` {$def} AFTER `{$after}`";
                $after = $name;
            } else {
                $after = $name;
            }
        }
        if ($alters) {
            db()->exec('ALTER TABLE `tblcorrespondence` ' . implode(', ', $alters));
        }
    } catch (Throwable $e) {
        error_log('DMS correspondence party snapshot schema: ' . $e->getMessage());
    }
}

function correspondence_snapshot_bind_values(array $snap): array
{
    $vals = [];
    foreach (correspondence_snapshot_column_names() as $key) {
        $value = trim((string) ($snap[$key] ?? ''));
        $vals[] = $value !== '' ? $value : null;
    }
    return $vals;
}

/**
 * لقطة الأسماء وقت الإنشاء، أو الإبقاء عليها عند التعديل دون تغيير الجهة/جهة التوقيع.
 * لا تُملأ المعاملات القديمة من البطاقة الحالية حتى لا تُجمَّد أسماء اليوم على أرشيف سابق.
 *
 * @param int|null $issuingDeptId جهة التوقيع (صادر)؛ إن null تُستخدم $deptId
 */
function correspondence_resolve_save_snapshot(
    ?array $existing,
    ?array $entity,
    string $type,
    string $party,
    ?int $entityId,
    ?int $deptId,
    ?int $issuingDeptId = null
): array {
    $partyKeys = ['snap_title_prefix', 'snap_person_name', 'snap_job_title', 'snap_honorific', 'snap_org_name'];
    $fromKeys = ['snap_from_prefix', 'snap_from_person', 'snap_from_job', 'snap_from_org', 'snap_from_signature'];
    $emptyParty = array_fill_keys($partyKeys, null);
    $emptyFrom = array_fill_keys($fromKeys, null);

    $entityChanged = !$existing || (int) ($existing['entity_id'] ?? 0) !== (int) ($entityId ?? 0);

    $effectiveFrom = $deptId;
    if ($type === 'outgoing' && function_exists('correspondence_effective_issuing_department_id')) {
        $effectiveFrom = correspondence_effective_issuing_department_id($deptId, $issuingDeptId);
    }
    $prevFrom = null;
    if ($existing) {
        if ($type === 'outgoing' && function_exists('correspondence_issuing_from_row')) {
            $prevFrom = correspondence_issuing_from_row($existing);
        } else {
            $prevFrom = (int) ($existing['department_id'] ?? 0) ?: null;
        }
    }
    $fromChanged = !$existing || (int) ($prevFrom ?? 0) !== (int) ($effectiveFrom ?? 0);

    if (!$existing || $entityChanged) {
        $partySnap = correspondence_capture_party_snapshot($entity, $type, $party);
    } elseif (correspondence_has_party_snapshot($existing)) {
        $partySnap = array_intersect_key(correspondence_snapshot_from_row($existing), $emptyParty);
    } else {
        $partySnap = $emptyParty;
    }

    if (!$existing || $fromChanged) {
        $fromSnap = correspondence_capture_from_snapshot($effectiveFrom);
    } elseif (correspondence_has_from_snapshot($existing)) {
        $fromSnap = array_intersect_key(correspondence_snapshot_from_row($existing), $emptyFrom);
    } else {
        $fromSnap = $emptyFrom;
    }

    return array_merge($partySnap, $fromSnap);
}

/** إدارة كل الجهات، لا جهات قسم واحد. الاتصالات الإدارية بعد منحها صلاحية الجهات. */
function user_manages_all_entities(?array $user = null): bool
{
    $user = $user ?? (function_exists('current_user') ? current_user() : null);
    if (!$user) {
        return false;
    }
    $role = (string) ($user['role'] ?? '');
    if (in_array($role, ['admin', 'archive'], true)) {
        return true;
    }
    return $role === 'communications'
        && function_exists('user_can')
        && user_can('entities.manage', $user);
}

/** هل يمكن للمستخدم اختيار الجهة لمعاملة جديدة أو تغيير جهة معاملة؟ */
function user_can_select_entity(?array $entity, ?array $user = null): bool
{
    if (!$entity) {
        return false;
    }
    $user = $user ?? (function_exists('current_user') ? current_user() : null);
    if (!$user) {
        return false;
    }
    if (function_exists('user_manages_all_entities') && user_manages_all_entities($user)) {
        return true;
    }
    if (($user['role'] ?? '') !== 'department' || !function_exists('user_department_ids') || !user_department_ids($user)) {
        return false;
    }
    return empty($entity['department_id'])
        || in_array((int) $entity['department_id'], user_department_ids($user), true);
}

/** جلب صف من جدول إداري بالمعرّف (أسماء الجداول ثابتة داخل النظام) */
function admin_row(string $table, int $id): ?array
{
    $table = str_replace('`', '', $table);
    $stmt = db()->prepare("SELECT * FROM `{$table}` WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** هل الاسم مستخدم مسبقاً في جدول إداري؟ */
function admin_name_exists(string $table, string $column, string $value, ?int $ignoreId = null): bool
{
    $table = str_replace('`', '', $table);
    $column = str_replace('`', '', $column);
    $sql = "SELECT id FROM `{$table}` WHERE LOWER(TRIM(`{$column}`)) = LOWER(TRIM(?))";
    $params = [$value];
    if ($ignoreId) {
        $sql .= ' AND id <> ?';
        $params[] = $ignoreId;
    }
    $stmt = db()->prepare($sql . ' LIMIT 1');
    $stmt->execute($params);
    return (bool) $stmt->fetchColumn();
}

/** هل معرّف القسم موجود ضمن قائمة أقسام محمّلة مسبقاً؟ */
function department_exists(int $departmentId, array $departments): bool
{
    foreach ($departments as $department) {
        if ((int) $department['id'] === $departmentId) {
            return true;
        }
    }
    return false;
}

/** هل يوجد اسم جهة مطابق داخل النطاق نفسه (عام أو قسم محدد)؟ */
function entity_name_exists_in_scope(string $nameAr, ?int $departmentId, ?int $ignoreId = null): bool
{
    return entity_find_name_conflict_in_scope($nameAr, $departmentId, $ignoreId) !== null;
}

/** هل رقم ترتيب العرض مستخدم لجهة أخرى؟ */
function entity_sort_order_taken(int $sortOrder, ?int $ignoreId = null): bool
{
    $sql = 'SELECT id FROM `tblentities` WHERE sort_order = ?';
    $params = [$sortOrder];
    $ignoreId = $ignoreId !== null ? (int) $ignoreId : 0;
    if ($ignoreId > 0) {
        $sql .= ' AND id <> ?';
        $params[] = $ignoreId;
    }
    $stmt = db()->prepare($sql . ' LIMIT 1');
    $stmt->execute($params);
    return (bool) $stmt->fetchColumn();
}

/**
 * أرقام الترتيب المستخدمة (لاستبعاد الجهة الحالية عند التعديل).
 *
 * @return list<int>
 */
function entity_sort_orders_in_use(?int $ignoreId = null): array
{
    $ignoreId = $ignoreId !== null ? (int) $ignoreId : 0;
    if ($ignoreId > 0) {
        $stmt = db()->prepare('SELECT DISTINCT sort_order FROM `tblentities` WHERE id <> ?');
        $stmt->execute([$ignoreId]);
    } else {
        $stmt = db()->query('SELECT DISTINCT sort_order FROM `tblentities`');
    }
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
}

/** هل رقم ترتيب المستخدم مستخدم لحساب آخر؟ */
function user_sort_order_taken(int $sortOrder, ?int $ignoreId = null): bool
{
    $sql = 'SELECT id FROM `tblusers` WHERE sort_order = ?';
    $params = [$sortOrder];
    $ignoreId = $ignoreId !== null ? (int) $ignoreId : 0;
    if ($ignoreId > 0) {
        $sql .= ' AND id <> ?';
        $params[] = $ignoreId;
    }
    $stmt = db()->prepare($sql . ' LIMIT 1');
    $stmt->execute($params);
    return (bool) $stmt->fetchColumn();
}

function user_next_sort_order(): int
{
    try {
        $n = (int) db()->query('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM `tblusers`')->fetchColumn();
        return max(1, $n);
    } catch (Throwable $e) {
        return 1;
    }
}

/** هل رقم ترتيب القسم مستخدم لقسم آخر؟ */
function department_sort_order_taken(int $sortOrder, ?int $ignoreId = null): bool
{
    $sql = 'SELECT id FROM `tbldepartments` WHERE sort_order = ?';
    $params = [$sortOrder];
    $ignoreId = $ignoreId !== null ? (int) $ignoreId : 0;
    if ($ignoreId > 0) {
        $sql .= ' AND id <> ?';
        $params[] = $ignoreId;
    }
    $stmt = db()->prepare($sql . ' LIMIT 1');
    $stmt->execute($params);
    return (bool) $stmt->fetchColumn();
}

/**
 * أرقام ترتيب الأقسام المستخدمة.
 *
 * @return list<int>
 */
function department_sort_orders_in_use(?int $ignoreId = null): array
{
    $ignoreId = $ignoreId !== null ? (int) $ignoreId : 0;
    if ($ignoreId > 0) {
        $stmt = db()->prepare('SELECT DISTINCT sort_order FROM `tbldepartments` WHERE id <> ?');
        $stmt->execute([$ignoreId]);
    } else {
        $stmt = db()->query('SELECT DISTINCT sort_order FROM `tbldepartments`');
    }
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
}

/**
 * يعيد صف الجهة المتعارضة إن وُجدت بنفس الاسم داخل النطاق.
 *
 * ملاحظة: لا نستخدم `? IS NULL` مع PDO/native prepares — قد يفشل ربط NULL.
 *
 * @return array{id:int,name_ar:string,department_id:int|null}|null
 */
function entity_find_name_conflict_in_scope(string $nameAr, ?int $departmentId, ?int $ignoreId = null): ?array
{
    $nameAr = trim($nameAr);
    if ($nameAr === '') {
        return null;
    }

    $ignoreId = $ignoreId !== null ? (int) $ignoreId : 0;
    $params = [$nameAr];

    // مقارنة الاسم بدون LOWER(): utf8mb4_unicode_ci كافٍ، وتجنب اختلافات تعريب LOWER().
    if ($departmentId === null) {
        $sql = 'SELECT id, name_ar, department_id
                FROM `tblentities`
                WHERE TRIM(name_ar) = TRIM(?)
                  AND department_id IS NULL';
    } else {
        $sql = 'SELECT id, name_ar, department_id
                FROM `tblentities`
                WHERE TRIM(name_ar) = TRIM(?)
                  AND department_id = ?';
        $params[] = (int) $departmentId;
    }

    if ($ignoreId > 0) {
        $sql .= ' AND id <> ?';
        $params[] = $ignoreId;
    }

    $sql .= ' LIMIT 1';
    try {
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        return [
            'id' => (int) $row['id'],
            'name_ar' => (string) $row['name_ar'],
            'department_id' => $row['department_id'] !== null ? (int) $row['department_id'] : null,
        ];
    } catch (Throwable $e) {
        return null;
    }
}

/** هل يمكن للمستخدم إنشاء جهة مراسلة؟ */
function user_can_create_entity(?array $user = null): bool
{
    $user = $user ?? (function_exists('current_user') ? current_user() : null);
    if (!$user) {
        return false;
    }
    if (function_exists('user_manages_all_entities') && user_manages_all_entities($user)) {
        return true;
    }
    if (function_exists('user_can') && !user_can('entities.manage', $user)) {
        return false;
    }
    return ($user['role'] ?? '') === 'department'
        && function_exists('user_has_assigned_departments')
        && user_has_assigned_departments($user);
}

/** هل يمكن للمستخدم تعديل الجهة من شاشة الإدارة؟ */
function user_can_manage_entity(?array $entity, ?array $user = null): bool
{
    if (!$entity) {
        return false;
    }
    $user = $user ?? (function_exists('current_user') ? current_user() : null);
    if (!$user) {
        return false;
    }
    if (function_exists('user_manages_all_entities') && user_manages_all_entities($user)) {
        return true;
    }
    if (function_exists('user_can') && !user_can('entities.manage', $user)) {
        return false;
    }
    return ($user['role'] ?? '') === 'department'
        && function_exists('user_can_access_department')
        && user_can_access_department((int) ($entity['department_id'] ?? 0), $user);
}

function get_comments(int $corrId): array
{
    $stmt = db()->prepare(
        "SELECT c.*, " . user_name_sql('u', 'user_name') . "
         FROM `tblcomments` c
         LEFT JOIN `tblusers` u ON u.id = c.user_id
         WHERE c.correspondence_id = ?
         ORDER BY c.created_at ASC"
    );
    $stmt->execute([$corrId]);
    return $stmt->fetchAll();
}

function status_label(string $status): string
{
    if (function_exists('lookup_label_from_table')) {
        $db = lookup_label_from_table('tblstatus_levels', $status);
        if ($db !== null && $db !== '') {
            return $db;
        }
    }
    $key = 'status.' . $status;
    $t = __($key);
    return $t !== $key ? $t : $status;
}

function priority_label(string $priority): string
{
    if (function_exists('lookup_label_from_table')) {
        $db = lookup_label_from_table('tblpriority_levels', $priority);
        if ($db !== null && $db !== '') {
            return $db;
        }
    }
    $key = 'priority.' . $priority;
    $t = __($key);
    return $t !== $key ? $t : $priority;
}

function confidentiality_label(string $level): string
{
    if (function_exists('lookup_label_from_table')) {
        $db = lookup_label_from_table('tblconfidentiality_levels', $level);
        if ($db !== null && $db !== '') {
            return $db;
        }
    }
    $key = 'conf.' . $level;
    $t = __($key);
    return $t !== $key ? $t : $level;
}

function type_label(string $type): string
{
    if ($type === 'incoming') {
        return __('type.incoming');
    }
    if ($type === 'internal') {
        return __('type.internal');
    }
    return __('type.outgoing');
}

/**
 * مجلد صفحات المعاملة حسب النوع.
 */
function correspondence_type_folder(string $type): string
{
    if ($type === 'outgoing') {
        return 'outgoing';
    }
    if ($type === 'internal') {
        return 'internal';
    }
    return 'incoming';
}

function role_label(string $role): string
{
    $key = 'role.' . $role;
    $t = __($key);
    return $t !== $key ? $t : $role;
}

function status_badge(string $status, ?string $cancelKind = null): string
{
    $colors = [
        'new' => 'primary',
        'in_progress' => 'info',
        'referred' => 'warning',
        'awaiting_reply' => 'secondary',
        'replied' => 'success',
        'sent' => 'info',
        'delivered' => 'success',
        'closed' => 'dark',
        'cancelled' => 'danger',
        'pending' => 'warning',
        'received' => 'info',
        'completed' => 'success',
        'returned' => 'danger',
    ];
    $color = $colors[$status] ?? 'secondary';
    $label = $status === 'cancelled' && $cancelKind !== null && $cancelKind !== ''
        ? correspondence_cancel_kind_label($cancelKind)
        : status_label($status);
    return '<span class="badge text-bg-' . $color . '">' . e($label) . '</span>';
}

function log_activity(?int $userId, ?int $corrId, string $action, string $details = '', ?int $archiveId = null, ?int $circularId = null): void
{
    try {
        if (function_exists('ensure_security_schema')) {
            ensure_security_schema();
        }
        $ip = function_exists('client_ip') ? (client_ip() ?: null) : null;
        $cols = ['user_id', 'correspondence_id', 'action', 'details'];
        $vals = [$userId, $corrId, $action, $details];
        if ($archiveId && function_exists('dms_db_column_exists') && dms_db_column_exists('tblactivity_log', 'archive_id')) {
            $cols[] = 'archive_id';
            $vals[] = $archiveId;
        }
        if ($circularId && function_exists('dms_db_column_exists') && dms_db_column_exists('tblactivity_log', 'circular_id')) {
            $cols[] = 'circular_id';
            $vals[] = $circularId;
        }
        if ($ip !== null && function_exists('dms_db_column_exists') && dms_db_column_exists('tblactivity_log', 'ip_address')) {
            $cols[] = 'ip_address';
            $vals[] = $ip;
        }
        $placeholders = implode(', ', array_fill(0, count($cols), '?'));
        db()->prepare(
            'INSERT INTO `tblactivity_log` (' . implode(', ', $cols) . ') VALUES (' . $placeholders . ')'
        )->execute($vals);
    } catch (Throwable $e) {
        try {
            db()->prepare(
                'INSERT INTO `tblactivity_log` (user_id, correspondence_id, action, details) VALUES (?, ?, ?, ?)'
            )->execute([$userId, $corrId, $action, $details]);
        } catch (Throwable $e2) {
            // لا تمنع العملية الأساسية
        }
    }
}

function format_date(?string $date): string
{
    if (!$date) {
        return '—';
    }
    $ts = strtotime($date);
    if ($ts === false) {
        return '—';
    }
    /* نفس عرض قائمة الصادر: سنة/شهر/يوم مع dir=ltr */
    return date('Y/m/d', $ts);
}

/** @return list<string> */
function dms_hijri_month_names(): array
{
    if (function_exists('dms_lang_code') && dms_lang_code() === 'en') {
        return ['Muharram', 'Safar', 'Rabi I', 'Rabi II', 'Jumada I', 'Jumada II', 'Rajab', 'Shaban', 'Ramadan', 'Shawwal', 'Dhu al-Qadah', 'Dhu al-Hijjah'];
    }
    return ['محرم', 'صفر', 'ربيع الأول', 'ربيع الآخر', 'جمادى الأولى', 'جمادى الآخرة', 'رجب', 'شعبان', 'رمضان', 'شوال', 'ذو القعدة', 'ذو الحجة'];
}

function dms_filter_min_gregorian_year(): int
{
    return 2000;
}

function dms_filter_min_hijri_year(): int
{
    $parts = gregorian_to_hijri_parts(dms_filter_min_gregorian_year(), 1, 1);
    return $parts !== null ? $parts[0] : 1420;
}

function format_date_input_value(?string $date): string
{
    $date = trim((string) $date);
    if ($date === '') {
        return '';
    }
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $date, $m)) {
        return $m[1] . '/' . $m[2] . '/' . $m[3];
    }
    $ts = strtotime($date);
    return $ts ? date('Y/m/d', $ts) : $date;
}

/**
 * حقل تاريخ ميلادي موحّد (عرض سنة/شهر/يوم + أيقونة التقويم).
 *
 * @param array{
 *   name:string,
 *   id?:string,
 *   value?:string|null,
 *   required?:bool,
 *   readonly?:bool,
 *   disabled?:bool,
 *   min?:string,
 *   max?:string,
 *   title?:string,
 *   attrs?:string
 * } $opts
 */
function render_dms_gregorian_date_field(array $opts): void
{
    $name = trim((string) ($opts['name'] ?? ''));
    if ($name === '') {
        return;
    }
    $id = trim((string) ($opts['id'] ?? ''));
    if ($id === '') {
        $id = preg_replace('/[^a-zA-Z0-9_-]+/', '_', rtrim($name, '[]')) ?: 'dms_date';
    }
    $raw = trim((string) ($opts['value'] ?? ''));
    $native = '';
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
        $native = $raw;
    } elseif ($raw !== '' && function_exists('dms_parse_gregorian_ymd')) {
        $parsed = dms_parse_gregorian_ymd($raw);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $parsed)) {
            $native = $parsed;
        }
    }
    $display = $native !== '' ? format_date_input_value($native) : '';
    $required = !empty($opts['required']);
    $readonly = !empty($opts['readonly']);
    $disabled = !empty($opts['disabled']);
    $min = trim((string) ($opts['min'] ?? ''));
    $max = trim((string) ($opts['max'] ?? ''));
    $title = trim((string) ($opts['title'] ?? __('corr.gregorian_pick')));
    $attrs = trim((string) ($opts['attrs'] ?? ''));
    $displayId = $id . '-display';
    ?>
<div class="gregorian-date-field">
    <input id="<?= e($displayId) ?>" type="text" class="form-control gregorian-date-display"
           dir="ltr" inputmode="numeric" autocomplete="off"
           data-date-input="<?= e($id) ?>" value="<?= e($display) ?>"
           <?= $required ? 'required' : '' ?>
           <?= $readonly || $disabled ? 'readonly' : '' ?>
           <?= $disabled ? 'disabled' : '' ?>
           <?= $attrs !== '' ? $attrs : '' ?>>
    <input id="<?= e($id) ?>" type="date" name="<?= e($name) ?>" class="gregorian-date-native"
           value="<?= e($native) ?>"
           title="<?= e($title) ?>"
           aria-label="<?= e($title) ?>"
           <?= $min !== '' ? 'min="' . e($min) . '"' : '' ?>
           <?= $max !== '' ? 'max="' . e($max) . '"' : '' ?>
           <?= $readonly ? 'readonly' : '' ?>
           <?= $disabled ? 'disabled' : '' ?>
           <?= ($readonly || $disabled) ? 'tabindex="-1"' : '' ?>>
    <?php if (!$readonly && !$disabled): ?>
    <span class="gregorian-date-btn" aria-hidden="true"><i class="bi bi-calendar3"></i></span>
    <?php endif; ?>
</div>
    <?php
}

/** إلحاق سكربت منتقي التاريخ إن لم يكن محمّلاً. */
function dms_page_scripts_with_date_picker(string $extra = ''): string
{
    if (!function_exists('dms_hijri_picker_boot')) {
        return $extra;
    }
    if (str_contains($extra, 'hijri-picker.js')) {
        return $extra;
    }
    return dms_hijri_picker_boot() . $extra;
}

/** تحويل ميلادي → هجري حسب تقويم أم القرى */
function format_hijri_date(?string $date): string
{
    if (!$date) {
        return '';
    }
    $ts = strtotime($date);
    if ($ts === false) {
        return '';
    }
    $parts = gregorian_to_hijri_parts((int) date('Y', $ts), (int) date('n', $ts), (int) date('j', $ts));
    if ($parts === null) {
        return '';
    }
    return sprintf('%04d/%02d/%02d', $parts[0], $parts[1], $parts[2]);
}

/** @return array{0:int,1:int,2:int}|null */
function gregorian_to_hijri_parts(int $gy, int $gm, int $gd): ?array
{
    require_once __DIR__ . '/hijri.php';
    return dms_ummalqura_from_gregorian($gy, $gm, $gd);
}

/** تحويل نص هجري (1448/03/14 أو 14/03/1448) إلى ميلادي Y-m-d */
function hijri_to_gregorian_date(string $raw): string
{
    $raw = trim(str_replace(['هـ', 'ه', ' '], '', $raw));
    if ($raw === '' || !preg_match('/^(\d{1,4})[\/\-\.](\d{1,2})[\/\-\.](\d{1,4})$/u', $raw, $m)) {
        return '';
    }
    $a = (int) $m[1];
    $b = (int) $m[2];
    $c = (int) $m[3];
    if ($a >= 1356 && $a <= 1500) {
        $hy = $a;
        $hm = $b;
        $hd = $c;
    } elseif ($c >= 1356 && $c <= 1500) {
        $hd = $a;
        $hm = $b;
        $hy = $c;
    } else {
        return '';
    }
    require_once __DIR__ . '/hijri.php';
    $g = dms_ummalqura_to_gregorian($hy, $hm, $hd);
    if ($g === null) {
        return '';
    }
    return sprintf('%04d-%02d-%02d', $g[0], $g[1], $g[2]);
}

/** إن وُجد هجري ولم يُملأ الميلادي: حوّل الهجري واملأ from/to */
/** @return array{0:string,1:string,2:string,3:string} from, to, hijri_from, hijri_to */
function dms_parse_gregorian_ymd(string $raw): string
{
    $raw = trim($raw);
    if ($raw === '') {
        return '';
    }
    if (preg_match('/^(\d{4})[\/\-.](\d{1,2})[\/\-.](\d{1,2})$/', $raw, $m)) {
        return sprintf('%04d-%02d-%02d', (int) $m[1], (int) $m[2], (int) $m[3]);
    }
    /* يوم/شهر/سنة */
    if (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})$/', $raw, $m)) {
        return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
    }
    return $raw;
}

function reports_date_range_from_request(): array
{
    $fromRaw = dms_parse_gregorian_ymd((string) ($_GET['from'] ?? ''));
    $toRaw = dms_parse_gregorian_ymd((string) ($_GET['to'] ?? ''));
    $hijriFrom = trim((string) ($_GET['hijri_from'] ?? ''));
    $hijriTo = trim((string) ($_GET['hijri_to'] ?? ''));
    if ($fromRaw !== '') {
        $from = $fromRaw;
    } else {
        $converted = $hijriFrom !== '' ? hijri_to_gregorian_date($hijriFrom) : '';
        $from = $converted !== '' ? $converted : date('Y-m-01');
    }
    if ($toRaw !== '') {
        $to = $toRaw;
    } else {
        $converted = $hijriTo !== '' ? hijri_to_gregorian_date($hijriTo) : '';
        $to = $converted !== '' ? $converted : date('Y-m-d');
    }
    return [$from, $to, $hijriFrom, $hijriTo];
}

function reports_type_from_request(): string
{
    $type = strtolower(trim((string) ($_GET['type'] ?? 'all')));
    if ($type === 'incoming' || $type === 'outgoing' || $type === 'internal') {
        return $type;
    }
    return 'all';
}

function reports_apply_type_filter(array &$where, array &$params, string $type): void
{
    if ($type === 'incoming' || $type === 'outgoing' || $type === 'internal') {
        $where[] = 'c.type = ?';
        $params[] = $type;
    }
}

function correspondence_normalize_date_filters(array $filters): array
{
    $from = dms_parse_gregorian_ymd((string) ($filters['from'] ?? ''));
    $to = dms_parse_gregorian_ymd((string) ($filters['to'] ?? ''));
    $hijriFrom = trim((string) ($filters['hijri_from'] ?? ''));
    $hijriTo = trim((string) ($filters['hijri_to'] ?? ''));
    if ($from !== '') {
        $filters['from'] = $from;
    }
    if ($to !== '') {
        $filters['to'] = $to;
    }
    if ($from === '' && $hijriFrom !== '') {
        $converted = hijri_to_gregorian_date($hijriFrom);
        if ($converted !== '') {
            $filters['from'] = $converted;
        }
    }
    if ($to === '' && $hijriTo !== '') {
        $converted = hijri_to_gregorian_date($hijriTo);
        if ($converted !== '') {
            $filters['to'] = $converted;
        }
    }
    return $filters;
}

function render_corr_date_cell(?string $date): void
{
    $g = format_date($date);
    echo '<span dir="ltr">' . e($g) . '</span>';
    $h = format_hijri_date($date);
    if ($h !== '') {
        echo '<div class="small text-muted" dir="ltr">' . e($h) . '</div>';
    }
}

function dms_current_hijri_parts(): array
{
    $parts = gregorian_to_hijri_parts((int) date('Y'), (int) date('n'), (int) date('j'));
    if ($parts === null) {
        return [(int) date('Y') - 579, (int) date('n'), (int) date('j')];
    }
    return $parts;
}

/** @param array{from?:string,to?:string,hijri_from?:string,hijri_to?:string} $filters */
function render_filter_date_range(array $filters): void
{
    static $seq = 0;
    $seq++;
    $fromId = 'filter-date-from-' . $seq;
    $toId = 'filter-date-to-' . $seq;
    $hijriFromId = 'filter-hijri-from-' . $seq;
    $hijriToId = 'filter-hijri-to-' . $seq;
    $from = (string) ($filters['from'] ?? '');
    $to = (string) ($filters['to'] ?? '');
    $hijriFrom = (string) ($filters['hijri_from'] ?? '');
    $hijriTo = (string) ($filters['hijri_to'] ?? '');
    $gMin = dms_filter_min_gregorian_year() . '-01-01';
    $gMax = date('Y-12-31');
    $gPh = e(__('corr.gregorian_ph'));
    $hPh = e(__('corr.hijri_ph'));
    $pickG = e(__('corr.gregorian_pick'));
    $pickH = e(__('corr.hijri_pick'));
    $fromNative = preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) ? $from : (hijri_to_gregorian_date($from) ?: $from);
    $toNative = preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) ? $to : (hijri_to_gregorian_date($to) ?: $to);
    ?>
    <div class="list-filters-dates">
        <div class="filter-date-group">
            <div class="filter-date-group-title"><?= e(__('corr.date_g_title')) ?></div>
            <div class="filter-date-pair">
                <div class="filter-date-slot">
                    <label class="form-label" for="<?= e($fromId) ?>-display"><?= e(__('common.from')) ?></label>
                    <div class="gregorian-date-field">
                        <input id="<?= e($fromId) ?>-display" type="text" class="form-control gregorian-date-display" dir="ltr"
                               inputmode="numeric" autocomplete="off" placeholder="<?= $gPh ?>"
                               data-date-input="<?= e($fromId) ?>" value="<?= e(format_date_input_value($fromNative)) ?>">
                        <input id="<?= e($fromId) ?>" type="date" name="from" class="gregorian-date-native"
                               min="<?= e($gMin) ?>" max="<?= e($gMax) ?>" data-hijri-target="<?= e($hijriFromId) ?>" value="<?= e($fromNative) ?>">
                        <button type="button" class="gregorian-date-btn" aria-label="<?= $pickG ?>" title="<?= $pickG ?>">
                            <i class="bi bi-calendar3"></i>
                        </button>
                    </div>
                </div>
                <div class="filter-date-slot">
                    <label class="form-label" for="<?= e($toId) ?>-display"><?= e(__('common.to')) ?></label>
                    <div class="gregorian-date-field">
                        <input id="<?= e($toId) ?>-display" type="text" class="form-control gregorian-date-display" dir="ltr"
                               inputmode="numeric" autocomplete="off" placeholder="<?= $gPh ?>"
                               data-date-input="<?= e($toId) ?>" value="<?= e(format_date_input_value($toNative)) ?>">
                        <input id="<?= e($toId) ?>" type="date" name="to" class="gregorian-date-native"
                               min="<?= e($gMin) ?>" max="<?= e($gMax) ?>" data-hijri-target="<?= e($hijriToId) ?>" value="<?= e($toNative) ?>">
                        <button type="button" class="gregorian-date-btn" aria-label="<?= $pickG ?>" title="<?= $pickG ?>">
                            <i class="bi bi-calendar3"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="filter-date-group">
            <div class="filter-date-group-title"><?= e(__('corr.date_h_title')) ?></div>
            <div class="filter-date-pair">
                <div class="filter-date-slot">
                    <label class="form-label" for="<?= e($hijriFromId) ?>"><?= e(__('common.from')) ?></label>
                    <div class="hijri-date-field">
                        <input id="<?= e($hijriFromId) ?>" type="text" name="hijri_from" class="form-control hijri-date-input"
                               dir="ltr" inputmode="numeric" autocomplete="off" placeholder="<?= $hPh ?>"
                               data-gregorian-target="<?= e($fromId) ?>" value="<?= e($hijriFrom) ?>"
                               aria-label="<?= e(__('corr.from_hijri')) ?>">
                        <button type="button" class="hijri-date-btn" aria-label="<?= $pickH ?>" title="<?= $pickH ?>">
                            <i class="bi bi-calendar3"></i>
                        </button>
                    </div>
                </div>
                <div class="filter-date-slot">
                    <label class="form-label" for="<?= e($hijriToId) ?>"><?= e(__('common.to')) ?></label>
                    <div class="hijri-date-field">
                        <input id="<?= e($hijriToId) ?>" type="text" name="hijri_to" class="form-control hijri-date-input"
                               dir="ltr" inputmode="numeric" autocomplete="off" placeholder="<?= $hPh ?>"
                               data-gregorian-target="<?= e($toId) ?>" value="<?= e($hijriTo) ?>"
                               aria-label="<?= e(__('corr.to_hijri')) ?>">
                        <button type="button" class="hijri-date-btn" aria-label="<?= $pickH ?>" title="<?= $pickH ?>">
                            <i class="bi bi-calendar3"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}

/** مربع القسم في فلاتر القوائم: ثابت لمستخدم القسم */
function render_filter_department_field(?array $user, array $departments, string|int $selectedId, string $allValue = '', array $opts = []): void
{
    $boxed = !empty($opts['boxed']);
    $fieldName = trim((string) ($opts['name'] ?? 'department_id'));
    if ($fieldName === '') {
        $fieldName = 'department_id';
    }
    $label = trim((string) ($opts['label'] ?? ''));
    if ($label === '') {
        $listType = (string) ($opts['list_type'] ?? '');
        $label = match ($listType) {
            'outgoing' => __('corr.filter_from'),
            'incoming' => __('corr.filter_to'),
            'internal' => __('corr.filter_to'),
            default => __('departments.col_officer'),
        };
    }
    $lockToPrimary = !array_key_exists('lock_to_primary', $opts) || !empty($opts['lock_to_primary']);
    ob_start();
    if (can_view_all_departments($user)): ?>
            <select name="<?= e($fieldName) ?>" class="form-select corr-officer-pick">
                <option value="<?= e($allValue) ?>"><?= e(__('common.all')) ?></option>
                <?php foreach ($departments as $d): ?>
                    <option value="<?= (int) $d['id'] ?>" data-office="<?= e(department_officer_subtitle($d)) ?>" <?= (string) $selectedId === (string) $d['id'] ? 'selected' : '' ?>><?= e(department_person_option_label($d)) ?></option>
                <?php endforeach; ?>
            </select>
    <?php elseif (function_exists('user_department_ids') && count(user_department_ids($user)) > 1): ?>
            <?php $mine = function_exists('departments_visible_to_user') ? departments_visible_to_user($user, $departments) : $departments; ?>
            <select name="<?= e($fieldName) ?>" class="form-select corr-officer-pick">
                <option value="<?= e($allValue) ?>"><?= e(__('common.all')) ?></option>
                <?php foreach ($mine as $d): ?>
                    <option value="<?= (int) $d['id'] ?>" data-office="<?= e(department_officer_subtitle($d)) ?>" <?= (string) $selectedId === (string) $d['id'] ? 'selected' : '' ?>><?= e(department_person_option_label($d)) ?></option>
                <?php endforeach; ?>
            </select>
    <?php elseif ($lockToPrimary): ?>
            <?php
            $lockedId = function_exists('user_primary_department_id')
                ? user_primary_department_id($user)
                : (int) ($user['department_id'] ?? 0);
            $lockedName = '';
            $lockedOffice = '';
            if ($lockedId > 0) {
                foreach ($departments as $d) {
                    if ((int) $d['id'] === $lockedId) {
                        $lockedName = department_person_option_label($d);
                        $lockedOffice = department_officer_subtitle($d);
                        break;
                    }
                }
            }
            ?>
            <input type="hidden" name="<?= e($fieldName) ?>" value="<?= $lockedId ?>">
            <div class="form-control corr-officer-locked bg-light"><?php render_officer_with_office($lockedName, $lockedOffice); ?></div>
    <?php else: ?>
            <select name="<?= e($fieldName) ?>" class="form-select corr-officer-pick">
                <option value="<?= e($allValue) ?>"><?= e(__('common.all')) ?></option>
                <?php foreach ($departments as $d): ?>
                    <option value="<?= (int) $d['id'] ?>" data-office="<?= e(department_officer_subtitle($d)) ?>" <?= (string) $selectedId === (string) $d['id'] ? 'selected' : '' ?>><?= e(department_person_option_label($d)) ?></option>
                <?php endforeach; ?>
            </select>
    <?php endif;
    $control = ob_get_clean();
    if (!empty($opts['bare'])) {
        echo $control;
        return;
    }
    if ($boxed) {
        echo '<div class="filter-date-group">'
            . '<div class="filter-date-group-title">' . e($label) . '</div>'
            . '<div class="filter-date-pair filter-select-pair">' . $control . '</div>'
            . '</div>';
        return;
    }
    echo '<div class="list-filters-item"><label class="form-label">' . e($label) . '</label>' . $control . '</div>';
}

/**
 * فلتر جهة المراسلة عبر بحث (لا يحمّل كل الجهات في القائمة).
 *
 * @param array{bare?:bool} $opts
 */
function render_filter_entity_field(string|int $selectedId, string $label, string $corrType = 'outgoing', array $opts = []): void
{
    static $assetsPrinted = false;

    $selected = max(0, (int) $selectedId);
    $isIncoming = $corrType === 'incoming';
    $display = '';
    if ($selected > 0 && function_exists('get_entity')) {
        $row = get_entity($selected);
        if ($row) {
            $payload = entity_picker_payload($row, $isIncoming);
            $display = trim((string) ($payload['display'] ?? ''));
            if ($display === '') {
                $display = trim((string) ($payload['party'] ?? $payload['org'] ?? ''));
            }
        } else {
            $selected = 0;
        }
    }
    $hasValue = $selected > 0 && $display !== '';
    $emptyLabel = __('common.all');

    ob_start();
    echo '<div class="input-group filter-entity-picker" data-filter-entity data-corr-type="'
        . e($isIncoming ? 'incoming' : 'outgoing') . '" data-empty-label="' . e($emptyLabel) . '">';
    echo '<input type="hidden" name="entity_id" value="' . ($hasValue ? $selected : '') . '" data-filter-entity-id>';
    echo '<div class="form-control filter-entity-display' . ($hasValue ? '' : ' is-empty') . '"'
        . ' role="button" tabindex="0" data-filter-entity-open'
        . ' title="' . e(__('corr.entity_picker_open')) . '"'
        . ' aria-label="' . e($label) . '">'
        . '<span data-filter-entity-label>' . e($hasValue ? $display : $emptyLabel) . '</span>'
        . '</div>';
    echo '<button type="button" class="btn btn-outline-secondary" data-filter-entity-pick'
        . ' title="' . e(__('corr.entity_picker_open')) . '"' . ($hasValue ? ' hidden' : '') . '>'
        . '<i class="bi bi-search"></i></button>';
    echo '<button type="button" class="btn btn-outline-secondary" data-filter-entity-clear'
        . ' title="' . e(__('corr.entity_picker_clear')) . '"' . ($hasValue ? '' : ' hidden') . '>'
        . '<i class="bi bi-x-lg"></i></button>';
    echo '</div>';
    $control = ob_get_clean();

    if (!empty($opts['bare'])) {
        echo $control;
    } else {
        echo '<div class="list-filters-item"><label class="form-label">' . e($label) . '</label>' . $control . '</div>';
    }

    if (!$assetsPrinted) {
        $assetsPrinted = true;
        require __DIR__ . '/../includes/filter_entity_picker.php';
    }
}

/** بطاقة فلتر واحدة بنفس أسلوب بطاقات التاريخ */
function render_filter_route_card(string $title, string $controlHtml): void
{
    echo '<div class="filter-date-group list-filters-route-card">'
        . '<div class="filter-date-group-title">' . e($title) . '</div>'
        . '<div class="filter-date-pair filter-select-pair">' . $controlHtml . '</div>'
        . '</div>';
}

/** زوج موجه من / موجهة إلى كبطاقتين متوافقتين مع حقلي التاريخ */
function render_filter_route_pair(?array $user, array $departments, array $filters, string $listType, string $deptAll = ''): void
{
    echo '<div class="list-filters-route">';
    if ($listType === 'outgoing') {
        ob_start();
        render_filter_department_field($user, $departments, $filters['issuing_department_id'] ?? '', $deptAll, [
            'name' => 'issuing_department_id',
            'label' => __('corr.filter_from'),
            'lock_to_primary' => false,
            'bare' => true,
        ]);
        render_filter_route_card(__('corr.filter_from'), ob_get_clean());

        ob_start();
        render_filter_entity_field($filters['entity_id'] ?? '', __('corr.filter_to'), 'outgoing', ['bare' => true]);
        render_filter_route_card(__('corr.filter_to'), ob_get_clean());
    } elseif ($listType === 'incoming') {
        ob_start();
        render_filter_entity_field($filters['entity_id'] ?? '', __('corr.filter_from'), 'incoming', ['bare' => true]);
        render_filter_route_card(__('corr.filter_from'), ob_get_clean());

        ob_start();
        render_filter_department_field($user, $departments, $filters['department_id'] ?? '', $deptAll, [
            'name' => 'department_id',
            'label' => __('corr.filter_to'),
            'lock_to_primary' => false,
            'bare' => true,
        ]);
        render_filter_route_card(__('corr.filter_to'), ob_get_clean());
    } else {
        ob_start();
        render_filter_department_field($user, $departments, $filters['issuing_department_id'] ?? '', $deptAll, [
            'name' => 'issuing_department_id',
            'label' => __('corr.filter_from'),
            'lock_to_primary' => false,
            'bare' => true,
        ]);
        render_filter_route_card(__('corr.filter_from'), ob_get_clean());

        ob_start();
        render_filter_department_field($user, $departments, $filters['department_id'] ?? '', $deptAll, [
            'name' => 'department_id',
            'label' => __('corr.filter_to'),
            'lock_to_primary' => false,
            'bare' => true,
        ]);
        render_filter_route_card(__('corr.filter_to'), ob_get_clean());
    }
    echo '</div>';
}

/** أنواع المتابعة حسب نوع القائمة */
function correspondence_followup_kinds_for_filter(string $type = 'all', ?array $user = null): array
{
    $approval = [];
    if (function_exists('outgoing_user_may_see_review_queue') && outgoing_user_may_see_review_queue($user)) {
        $approval[] = 'issue_review';
    }
    if (function_exists('outgoing_user_may_see_approval_queue') && outgoing_user_may_see_approval_queue($user)) {
        $approval[] = 'issue_approval';
    }
    if ($type === 'incoming') {
        return array_merge($approval, ['unreplied', 'no_referral', 'no_attach', 'due_soon']);
    }
    if ($type === 'outgoing') {
        return array_merge($approval, ['unsent', 'no_receipt', 'due_soon']);
    }
    if ($type === 'internal') {
        return ['no_attach', 'due_soon'];
    }
    return array_merge($approval, ['unreplied', 'no_referral', 'no_attach', 'unsent', 'no_receipt', 'due_soon']);
}

/**
 * عدد الفلاتر النشطة في قائمة مراسلات (بدون تبويبات مثل prep / status_group).
 */
function correspondence_list_filters_active_count(array $filters, string $listType = 'all'): int
{
    $keys = [
        'q', 'status', 'action', 'from', 'to', 'hijri_from', 'hijri_to',
        'department_id', 'issuing_department_id', 'entity_id',
    ];
    if ($listType === 'outgoing' || $listType === 'all') {
        $keys[] = 'receipt';
        $keys[] = 'channel';
        $keys[] = 'issue_approval';
    }
    $count = 0;
    foreach ($keys as $key) {
        $value = $filters[$key] ?? '';
        if ($value === null) {
            continue;
        }
        $value = is_string($value) ? trim($value) : $value;
        if ($value === '' || $value === '0' || $value === 0) {
            continue;
        }
        $count++;
    }
    return $count;
}

/**
 * بطاقة فلاتر قابلة للطي لقوائم الصادر/الوارد/الداخلي.
 *
 * @param array<string,mixed> $options نفس خيارات render_correspondence_list_filters
 */
function render_correspondence_filters_card(?array $user, array $departments, array $filters, array $options = []): void
{
    $listType = (string) ($options['list_type'] ?? 'all');
    if ($listType !== 'incoming' && $listType !== 'outgoing' && $listType !== 'internal') {
        $listType = 'all';
    }
    $activeCount = correspondence_list_filters_active_count($filters, $listType);
    $forceOpen = $activeCount > 0;
    $cardId = 'listFiltersCard-' . $listType;
    $storageKey = 'dms.listFilters.open.' . $listType;
    $bodyId = $cardId . '-body';

    $hidden = is_array($options['hidden'] ?? null) ? $options['hidden'] : [];
    $clearQuery = [];
    foreach ($hidden as $name => $value) {
        if ($value === null || $value === '') {
            continue;
        }
        $clearQuery[(string) $name] = (string) $value;
    }
    $script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
    $clearHref = $script . ($clearQuery ? ('?' . http_build_query($clearQuery)) : '');
    ?>
<div class="card-panel mb-3 list-filters-card<?= $forceOpen ? ' is-open' : '' ?>"
     id="<?= e($cardId) ?>"
     data-list-filters-card
     data-storage-key="<?= e($storageKey) ?>"
     data-force-open="<?= $forceOpen ? '1' : '0' ?>">
    <script>
    (function () {
      var el = document.getElementById(<?= json_encode($cardId, JSON_UNESCAPED_UNICODE) ?>);
      if (!el || el.getAttribute('data-force-open') === '1') return;
      try {
        if (localStorage.getItem(el.getAttribute('data-storage-key')) === '1') {
          el.classList.add('is-open');
        }
      } catch (e) {}
    })();
    </script>
    <div class="list-filters-card-bar">
        <button type="button"
                class="list-filters-card-toggle"
                data-list-filters-toggle
                data-show-label="<?= e(__('corr.filters_show')) ?>"
                data-hide-label="<?= e(__('corr.filters_hide')) ?>"
                aria-expanded="<?= $forceOpen ? 'true' : 'false' ?>"
                aria-controls="<?= e($bodyId) ?>"
                title="<?= e($forceOpen ? __('corr.filters_hide') : __('corr.filters_show')) ?>"
                aria-label="<?= e($forceOpen ? __('corr.filters_hide') : __('corr.filters_show')) ?>">
            <span class="list-filters-card-toggle-main">
                <i class="bi bi-funnel" aria-hidden="true"></i>
                <span><?= e(__('corr.filters_title')) ?></span>
                <?php if ($activeCount > 0): ?>
                    <span class="badge text-bg-secondary list-filters-card-count"><?= (int) $activeCount ?></span>
                <?php endif; ?>
            </span>
            <i class="bi bi-chevron-down list-filters-card-chevron" aria-hidden="true"></i>
        </button>
        <?php if ($activeCount > 0): ?>
            <a class="btn btn-sm btn-outline-secondary list-filters-card-clear" href="<?= e($clearHref) ?>">
                <i class="bi bi-x-lg me-1" aria-hidden="true"></i><?= e(__('corr.filters_clear')) ?>
            </a>
        <?php endif; ?>
    </div>
    <div class="list-filters-card-body card-body p-3 pt-2" id="<?= e($bodyId) ?>">
        <form class="list-filters" method="get">
            <?php render_correspondence_list_filters($user, $departments, $filters, $options); ?>
        </form>
    </div>
</div>
    <?php
}

/**
 * ترتيب موحّد لفلاتر القوائم:
 * بحث → النوع → الحالة → الاستلام → المتابعة → القسم → التواريخ → زر التصفية
 *
 * @param array{
 *   list_type?:string,
 *   show_type?:bool,
 *   show_receipt?:bool,
 *   show_followup?:bool,
 *   followup_kinds?:list<string>,
 *   status_options?:list<string>,
 *   search_placeholder?:string,
 *   type_value?:string,
 *   hidden?:array<string,scalar|null>,
 *   submit_label?:string,
 *   department_all_value?:string
 * } $options
 */
function render_correspondence_list_filters(?array $user, array $departments, array $filters, array $options = []): void
{
    $listType = (string) ($options['list_type'] ?? 'all');
    if ($listType !== 'incoming' && $listType !== 'outgoing' && $listType !== 'internal') {
        $listType = 'all';
    }
    $showType = !empty($options['show_type']);
    $showReceipt = array_key_exists('show_receipt', $options)
        ? !empty($options['show_receipt'])
        : ($listType === 'outgoing' || $listType === 'all');
    $showFollowup = !array_key_exists('show_followup', $options) || !empty($options['show_followup']);
    $typeValue = (string) ($options['type_value'] ?? ($filters['type'] ?? $listType));
    if ($typeValue !== 'incoming' && $typeValue !== 'outgoing' && $typeValue !== 'internal') {
        $typeValue = 'all';
    }
    $followupKinds = $options['followup_kinds'] ?? correspondence_followup_kinds_for_filter($showType ? $typeValue : $listType, $user);
    $statusOptions = $options['status_options'] ?? correspondence_status_codes();
    $searchPlaceholder = (string) ($options['search_placeholder'] ?? __('corr.search_placeholder'));
    $submitLabel = (string) ($options['submit_label'] ?? __('action.filter'));
    $deptAll = (string) ($options['department_all_value'] ?? '');
    $hidden = $options['hidden'] ?? [];

    foreach ($hidden as $name => $value) {
        if ($value === null || $value === '') {
            continue;
        }
        echo '<input type="hidden" name="' . e((string) $name) . '" value="' . e((string) $value) . '">' . "\n";
    }
    ?>
    <div class="list-filters-main">
        <div class="list-filters-item list-filters-search">
            <label class="form-label"><?= e(__('action.search')) ?></label>
            <input type="text" name="q" class="form-control" placeholder="<?= e($searchPlaceholder) ?>" value="<?= e((string) ($filters['q'] ?? '')) ?>">
        </div>
        <?php if ($showType): ?>
        <div class="list-filters-item">
            <label class="form-label"><?= e(__('corr.type')) ?></label>
            <select name="type" class="form-select">
                <option value="all" <?= $typeValue === 'all' ? 'selected' : '' ?>><?= e(__('common.all')) ?></option>
                <option value="incoming" <?= $typeValue === 'incoming' ? 'selected' : '' ?>><?= e(__('nav.incoming')) ?></option>
                <option value="outgoing" <?= $typeValue === 'outgoing' ? 'selected' : '' ?>><?= e(__('nav.outgoing')) ?></option>
                <option value="internal" <?= $typeValue === 'internal' ? 'selected' : '' ?>><?= e(__('nav.internal')) ?></option>
            </select>
        </div>
        <?php endif; ?>
        <div class="list-filters-item">
            <label class="form-label"><?= e(__('corr.status')) ?></label>
            <select name="status" class="form-select">
                <option value=""><?= e(__('common.all')) ?></option>
                <?php foreach ($statusOptions as $s): ?>
                    <option value="<?= e($s) ?>" <?= (string) ($filters['status'] ?? '') === $s ? 'selected' : '' ?>><?= e(status_label($s)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if ($showReceipt): ?>
        <div class="list-filters-item">
            <label class="form-label"><?= e(__('receipt.filter')) ?></label>
            <select name="receipt" class="form-select">
                <option value=""><?= e(__('common.all')) ?></option>
                <option value="missing" <?= ($filters['receipt'] ?? '') === 'missing' ? 'selected' : '' ?>><?= e(__('receipt.filter_missing')) ?></option>
                <option value="recorded" <?= ($filters['receipt'] ?? '') === 'recorded' ? 'selected' : '' ?>><?= e(__('receipt.filter_recorded')) ?></option>
                <option value="overdue_missing" <?= ($filters['receipt'] ?? '') === 'overdue_missing' ? 'selected' : '' ?>><?= e(__('receipt.filter_overdue')) ?></option>
            </select>
        </div>
        <div class="list-filters-item">
            <label class="form-label"><?= e(__('corr.channel_filter')) ?></label>
            <select name="channel" class="form-select">
                <option value=""><?= e(__('common.all')) ?></option>
                <option value="electronic" <?= ($filters['channel'] ?? '') === 'electronic' ? 'selected' : '' ?>><?= e(__('corr.channel_electronic')) ?></option>
                <option value="paper" <?= ($filters['channel'] ?? '') === 'paper' ? 'selected' : '' ?>><?= e(__('corr.channel_paper')) ?></option>
            </select>
        </div>
        <?php endif; ?>
        <?php
        $showIssueAppr = ($listType === 'outgoing' || $listType === 'all')
            && function_exists('outgoing_approval_badge');
        if ($showIssueAppr):
            $issueApprNoneLabel = (function_exists('outgoing_approval_enabled') && !outgoing_approval_enabled())
                ? __('issue_appr.filter_not_required')
                : __('issue_appr.filter_none');
        ?>
        <div class="list-filters-item">
            <label class="form-label"><?= e(__('issue_appr.filter')) ?></label>
            <select name="issue_approval" class="form-select">
                <option value=""><?= e(__('common.all')) ?></option>
                <option value="pending" <?= ($filters['issue_approval'] ?? '') === 'pending' ? 'selected' : '' ?>><?= e(__('issue_appr.filter_pending')) ?></option>
                <option value="pending_review" <?= ($filters['issue_approval'] ?? '') === 'pending_review' ? 'selected' : '' ?>><?= e(__('issue_review.filter_pending')) ?></option>
                <option value="returned" <?= ($filters['issue_approval'] ?? '') === 'returned' ? 'selected' : '' ?>><?= e(__('issue_review.filter_returned')) ?></option>
                <option value="approved" <?= ($filters['issue_approval'] ?? '') === 'approved' ? 'selected' : '' ?>><?= e(__('issue_appr.filter_approved')) ?></option>
                <option value="rejected" <?= ($filters['issue_approval'] ?? '') === 'rejected' ? 'selected' : '' ?>><?= e(__('issue_appr.filter_rejected')) ?></option>
                <option value="none" <?= ($filters['issue_approval'] ?? '') === 'none' ? 'selected' : '' ?>><?= e($issueApprNoneLabel) ?></option>
            </select>
        </div>
        <?php endif; ?>
        <?php if ($showFollowup && $followupKinds): ?>
        <div class="list-filters-item">
            <label class="form-label"><?= e(__('corr.followup')) ?></label>
            <select name="action" class="form-select">
                <option value=""><?= e(__('common.all')) ?></option>
                <?php foreach ($followupKinds as $kind): ?>
                    <option value="<?= e($kind) ?>" <?= ($filters['action'] ?? '') === $kind ? 'selected' : '' ?>><?= e(__('actionq.' . $kind)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <?php if ($listType === 'all'): ?>
            <?php render_filter_department_field($user, $departments, $filters['department_id'] ?? '', $deptAll, ['list_type' => $listType]); ?>
        <?php endif; ?>
    </div>
    <div class="list-filters-footer">
        <?php render_filter_date_range($filters); ?>
        <?php if ($listType === 'outgoing' || $listType === 'incoming' || $listType === 'internal'): ?>
            <?php render_filter_route_pair($user, $departments, $filters, $listType, $deptAll); ?>
        <?php endif; ?>
        <div class="list-filters-actions">
            <button class="btn btn-kff list-filters-submit" type="submit">
                <i class="bi bi-funnel me-1" aria-hidden="true"></i><?= e($submitLabel) ?>
            </button>
        </div>
    </div>
    <?php
}

function format_datetime(?string $dt): string
{
    if (!$dt) {
        return '—';
    }
    $ts = strtotime($dt);
    if ($ts === false) {
        return '—';
    }
    return date('Y-m-d', $ts) . ' — ' . date('H:i', $ts);
}

/** تاريخ ووقت مفصولان بصرياً داخل صفحة RTL */
function format_datetime_html(?string $dt): string
{
    if (!$dt) {
        return e('—');
    }
    $ts = strtotime($dt);
    if ($ts === false) {
        return e('—');
    }
    return '<span class="dms-datetime" dir="ltr">'
        . '<span class="dms-datetime-date">' . e(date('Y-m-d', $ts)) . '</span>'
        . '<span class="dms-datetime-sep" aria-hidden="true">—</span>'
        . '<span class="dms-datetime-time">' . e(date('H:i', $ts)) . '</span>'
        . '</span>';
}

function is_overdue(?string $replyDue, string $status): bool
{
    if (!$replyDue || in_array($status, correspondence_settled_statuses(), true)) {
        return false;
    }
    return $replyDue < date('Y-m-d');
}

/** حالات المعاملة المعتمدة */
function correspondence_status_codes(): array
{
    $codes = ['new', 'in_progress', 'referred', 'awaiting_reply', 'replied', 'sent', 'delivered', 'closed', 'cancelled'];
    if (function_exists('status_levels_list')) {
        try {
            foreach (status_levels_list(false) as $row) {
                $code = trim((string) ($row['code'] ?? ''));
                if ($code !== '' && !in_array($code, $codes, true)) {
                    $codes[] = $code;
                }
            }
        } catch (Throwable $e) {
            // ignore
        }
    }
    return $codes;
}

/** حالات يمكن اختيارها يدوياً — الإلغاء والإقفال من إجراءات مستقلة */
function correspondence_assignable_status_codes(): array
{
    $base = array_values(array_filter(
        correspondence_status_codes(),
        static fn(string $s): bool => $s !== 'cancelled' && $s !== 'closed'
    ));
    if (function_exists('status_levels_list')) {
        try {
            $active = array_column(status_levels_list(true), 'code');
            if ($active !== []) {
                $base = array_values(array_filter(
                    $base,
                    static fn(string $s): bool => in_array($s, $active, true)
                ));
            }
        } catch (Throwable $e) {
            // ignore
        }
    }
    return $base;
}

function correspondence_settled_statuses(): array
{
    return ['replied', 'delivered', 'closed', 'cancelled'];
}

function correspondence_settled_sql_in(): string
{
    return "'" . implode("','", correspondence_settled_statuses()) . "'";
}

function correspondence_is_cancelled(?array $corr): bool
{
    return ($corr['status'] ?? '') === 'cancelled';
}

function correspondence_is_settled(?array $corr): bool
{
    return in_array((string) ($corr['status'] ?? ''), correspondence_settled_statuses(), true);
}

function correspondence_assert_mutable(?array $corr): void
{
    if (correspondence_is_cancelled($corr)) {
        throw new RuntimeException(__('cancel.locked'));
    }
}

/** @return list<string> */
function correspondence_cancel_kinds(): array
{
    return ['void', 'withdrawn'];
}

function correspondence_cancel_kind_label(string $kind): string
{
    return $kind === 'withdrawn' ? __('cancel.kind_withdrawn') : __('cancel.kind_void');
}

function correspondence_suggested_cancel_kind(array $corr): string
{
    if (correspondence_has_receipt($corr)
        || in_array((string) ($corr['status'] ?? ''), ['sent', 'delivered'], true)
    ) {
        return 'withdrawn';
    }
    return 'void';
}

function ensure_correspondence_status_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $fullEnum = "ENUM('new','in_progress','referred','awaiting_reply','replied','sent','delivered','closed','cancelled')";
    try {
        $row = dms_db_column_meta('tblcorrespondence', 'status');
        $type = (string) ($row['Type'] ?? '');
        if ($type === '') {
            return;
        }
        $needsAlter = stripos($type, "'cancelled'") === false
            || stripos($type, "'delivered'") === false
            || stripos($type, "'sent'") === false;
        if ($needsAlter) {
            db()->exec(
                "ALTER TABLE `tblcorrespondence`
                 MODIFY COLUMN `status` {$fullEnum}
                 NOT NULL DEFAULT 'new'"
            );
        }
    } catch (Throwable $e) {
        // تجاهل
    }
    if (function_exists('ensure_lookups_schema')) {
        try {
            ensure_lookups_schema();
            db()->prepare(
                "INSERT IGNORE INTO `tblstatus_levels` (code, name_ar, name_en, sort_order, is_system) VALUES (?,?,?,?,1)"
            )->execute(['sent', 'تم الإرسال', 'Sent', 55]);
        } catch (Throwable $e) {
            // ignore
        }
    }
}

function ensure_correspondence_cancel_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    ensure_correspondence_status_schema();
    try {
        $cols = dms_db_column_map('tblcorrespondence');
        $alters = [];
        if (empty($cols['cancel_kind'])) {
            $alters[] = "ADD COLUMN `cancel_kind` ENUM('void','withdrawn') NULL DEFAULT NULL COMMENT 'ملغى أو مسحوب' AFTER `status`";
        }
        if (empty($cols['cancel_reason'])) {
            $alters[] = "ADD COLUMN `cancel_reason` VARCHAR(500) NULL DEFAULT NULL COMMENT 'سبب إلغاء الصادر' AFTER `cancel_kind`";
        }
        if (empty($cols['cancelled_at'])) {
            $alters[] = "ADD COLUMN `cancelled_at` DATETIME NULL DEFAULT NULL AFTER `cancel_reason`";
        }
        if (empty($cols['cancelled_by'])) {
            $alters[] = "ADD COLUMN `cancelled_by` INT UNSIGNED NULL DEFAULT NULL AFTER `cancelled_at`";
        }
        if ($alters) {
            db()->exec('ALTER TABLE `tblcorrespondence` ' . implode(', ', $alters));
        }
    } catch (Throwable $e) {
        // قاعدة غير جاهزة
    }
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_token_from_request(): string
{
    $header = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if ($header !== '') {
        return $header;
    }
    if (isset($_POST['csrf_token'])) {
        return (string) $_POST['csrf_token'];
    }
    return '';
}

function csrf_wants_json(): bool
{
    $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
    if (stripos($accept, 'application/json') !== false) {
        return true;
    }
    return strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
}

function csrf_deny(): void
{
    http_response_code(403);
    if (csrf_wants_json()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'ok' => false, 'error' => 'csrf'], JSON_UNESCAPED_UNICODE);
    } else {
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Forbidden';
    }
    exit;
}

function verify_csrf(): void
{
    $token = csrf_token_from_request();
    if ($token === '' || !hash_equals(csrf_token(), $token)) {
        csrf_deny();
    }
}

function csrf_head_tags(): string
{
    $token = csrf_token();
    $jsUrl = function_exists('dms_asset_url')
        ? dms_asset_url('assets/js/dms-csrf.js', true)
        : (base_url('assets/js/dms-csrf.js') . '?v=' . (int) (@filemtime(dirname(__DIR__) . '/assets/js/dms-csrf.js') ?: time()));
    return '<script>window.DMS_CSRF_TOKEN='
        . json_encode($token, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
        . ';</script>' . "\n    "
        . '<script src="' . e($jsUrl) . '"></script>';
}

/** مسار رجوع داخلي فقط — يمنع open redirect من POST. */
function dms_safe_return_path(?string $path, string $fallback = 'index.php'): string
{
    $path = str_replace('\\', '/', trim((string) $path));
    if ($path === '' || str_contains($path, '..') || str_starts_with($path, '/') || str_contains($path, '://')) {
        return $fallback;
    }
    if (!preg_match('#^[A-Za-z0-9/_.?=&%-]+$#', $path)) {
        return $fallback;
    }
    return $path;
}

function file_download_url(string $type, int $id): string
{
    return base_url('files/download.php?type=' . rawurlencode($type) . '&id=' . $id);
}

/** تسمية نوع ملف مرفوع للعرض: PDF / صورة / ملف */
function dms_upload_file_type_label(string $fileName, string $mime = ''): string
{
    $name = strtolower($fileName);
    $mime = strtolower($mime);
    if (str_contains($mime, 'pdf') || str_ends_with($name, '.pdf')) {
        return __('file.type_pdf');
    }
    if (str_starts_with($mime, 'image/') || preg_match('/\.(jpe?g|png|gif|webp|bmp)$/', $name) === 1) {
        return __('file.type_image');
    }
    return __('file.type_other');
}

function dms_is_safe_upload_path(string $relative): bool
{
    $rel = dms_storage_relative($relative);
    if ($rel === '' || str_contains($rel, '..')) {
        return false;
    }
    $full = realpath(dms_storage_disk_path($rel));
    $root = realpath(dms_storage_root());
    if ($full === false || $root === false) {
        $candidate = str_replace('\\', '/', dms_storage_disk_path($rel));
        $rootNorm = str_replace('\\', '/', dms_storage_root());
        return str_starts_with(strtolower($candidate), strtolower($rootNorm . '/'));
    }
    $fullN = str_replace('\\', '/', $full);
    $rootN = str_replace('\\', '/', $root);
    return str_starts_with(strtolower($fullN), strtolower($rootN . '/')) || strcasecmp($fullN, $rootN) === 0;
}

function dms_favicon_href(): string
{
    $rel = 'assets/img/favicon.ico';
    if (function_exists('dms_web_path')) {
        return dms_web_path($rel);
    }
    return function_exists('base_url') ? base_url($rel) : '/' . $rel;
}

function dms_wants_raw_file(): bool
{
    $raw = strtolower(trim((string) ($_GET['raw'] ?? '')));
    return $raw === '1' || $raw === 'true' || $raw === 'yes';
}

function dms_is_browser_preview_mime(string $mime): bool
{
    $mime = strtolower(trim($mime));
    return $mime === 'application/pdf' || str_starts_with($mime, 'image/');
}

function dms_file_raw_url(): string
{
    $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    $query = $_GET;
    $query['raw'] = '1';
    return $script . '?' . http_build_query($query);
}

function dms_render_file_preview(string $downloadName, string $mime): void
{
    $title = str_replace(["\r", "\n"], '', $downloadName);
    $icon = dms_favicon_href();
    $src = dms_file_raw_url();
    $isImg = str_starts_with(strtolower($mime), 'image/');
    $lang = function_exists('dms_lang_code') ? dms_lang_code() : 'ar';
    $dir = function_exists('dms_page_dir') ? dms_page_dir() : 'rtl';
    header('Content-Type: text/html; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    echo '<!DOCTYPE html><html lang="' . e($lang) . '" dir="' . e($dir) . '"><head>';
    echo '<meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . e($title) . '</title>';
    echo '<link rel="icon" href="' . e($icon) . '">';
    echo '<link rel="shortcut icon" href="' . e($icon) . '">';
    echo '<style>html,body{margin:0;height:100%;background:#1f2937}';
    echo '.dms-file-frame{border:0;width:100%;height:100%;display:block}';
    echo '.dms-file-img{max-width:100%;max-height:100%;object-fit:contain;display:block;margin:0 auto}';
    echo 'body.dms-file-img-body{display:flex;align-items:center;justify-content:center}</style>';
    echo '</head><body' . ($isImg ? ' class="dms-file-img-body"' : '') . '>';
    if ($isImg) {
        echo '<img class="dms-file-img" src="' . e($src) . '" alt="' . e($title) . '">';
    } else {
        echo '<iframe class="dms-file-frame" src="' . e($src) . '" title="' . e($title) . '"></iframe>';
    }
    echo '</body></html>';
    exit;
}

function dms_send_file_bytes(string $fullPath, string $downloadName, string $mime): void
{
    $safeName = str_replace(['"', "\r", "\n"], '', $downloadName);
    $icon = dms_favicon_href();
    header('X-Content-Type-Options: nosniff');
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string) filesize($fullPath));
    header('Content-Disposition: inline; filename="' . $safeName . '"');
    header('X-Frame-Options: SAMEORIGIN');
    header('Link: <' . $icon . '>; rel="icon"');
    readfile($fullPath);
    exit;
}

function dms_stream_file(string $fullPath, string $downloadName, ?string $mime = null): void
{
    $full = realpath($fullPath);
    if ($full === false || !is_file($full)) {
        http_response_code(404);
        exit('Not found');
    }
    if ($mime === null || $mime === '') {
        $mime = 'application/octet-stream';
        if (class_exists('finfo')) {
            $detected = (string) (new finfo(FILEINFO_MIME_TYPE))->file($full);
            if ($detected !== '') {
                $mime = $detected;
            }
        }
    }
    $previewable = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ];
    if (!in_array($mime, $previewable, true) && !str_starts_with(strtolower($mime), 'image/')) {
        $mime = 'application/octet-stream';
    }
    if (!dms_wants_raw_file() && dms_is_browser_preview_mime($mime)) {
        dms_render_file_preview($downloadName, $mime);
    }
    dms_send_file_bytes($full, $downloadName, $mime);
}

function dms_stream_upload(string $relative, string $downloadName): void
{
    if (!dms_is_safe_upload_path($relative)) {
        http_response_code(404);
        exit('Not found');
    }
    $full = realpath(dms_storage_disk_path($relative));
    if ($full === false || !is_file($full)) {
        http_response_code(404);
        exit('Not found');
    }
    dms_stream_file($full, $downloadName);
}

function dms_app_signing_key(): string
{
    static $key = null;
    if (is_string($key) && $key !== '') {
        return $key;
    }
    $configured = trim((string) setting('app_signing_key', ''));
    if ($configured !== '') {
        $key = $configured;
        return $key;
    }
    // توليد مفتاح عشوائي قوي مرة واحدة وحفظه — لا تعتمد على اسم الجهة/المسار.
    try {
        $generated = bin2hex(random_bytes(32));
        set_setting('app_signing_key', $generated, 'مفتاح توقيع الروابط العامة المؤقتة');
        $key = $generated;
        return $key;
    } catch (Throwable $e) {
        $key = hash('sha256', 'dms-sign-fallback|' . dirname(__DIR__) . '|' . php_uname());
        return $key;
    }
}

function dms_document_signed_token(int $docId, int $ttlSeconds = 7200): string
{
    $docId = max(0, $docId);
    $exp = time() + max(300, $ttlSeconds);
    $payload = $docId . '|' . $exp;
    $sig = hash_hmac('sha256', $payload, dms_app_signing_key());
    return rtrim(strtr(base64_encode($payload . '|' . $sig), '+/', '-_'), '=');
}

function dms_document_verify_signed_token(string $token): ?int
{
    $token = strtr($token, '-_', '+/');
    $pad = strlen($token) % 4;
    if ($pad > 0) {
        $token .= str_repeat('=', 4 - $pad);
    }
    $raw = base64_decode($token, true);
    if ($raw === false || $raw === '') {
        return null;
    }
    $parts = explode('|', $raw);
    if (count($parts) !== 3) {
        return null;
    }
    [$docIdRaw, $expRaw, $sig] = $parts;
    $docId = (int) $docIdRaw;
    $exp = (int) $expRaw;
    if ($docId < 1 || $exp < time()) {
        return null;
    }
    $payload = $docId . '|' . $exp;
    $expected = hash_hmac('sha256', $payload, dms_app_signing_key());
    if (!hash_equals($expected, $sig)) {
        return null;
    }
    return $docId;
}

/** رابط عام موقّت لمرفق — يحتاجه Twilio/Meta لجلب الملف. */
function dms_document_public_media_url(int $docId): string
{
    $docId = max(0, $docId);
    if ($docId < 1) {
        return '';
    }
    $base = dms_corr_qr_base_url();
    if ($base === '') {
        $base = rtrim(base_url(''), '/');
    }
    if ($base === '') {
        return '';
    }
    $host = parse_url($base, PHP_URL_HOST);
    $host = is_string($host) ? strtolower($host) : '';
    if ($host === '' || $host === 'localhost' || $host === '127.0.0.1' || $host === '::1') {
        return '';
    }
    $token = dms_document_signed_token($docId);
    return $base . '/files/wa_document.php?t=' . rawurlencode($token);
}

function correspondence_attachment_folder(string $type): string
{
    if ($type === 'outgoing') {
        return 'outgoing';
    }
    if ($type === 'internal') {
        return 'internal';
    }
    return 'incoming';
}

function correspondence_attachment_basename(string $refNumber): string
{
    $slug = preg_replace('/[^\p{L}\p{N}\-_]+/u', '-', $refNumber) ?? '';
    $slug = trim($slug, '-_');
    $slug = preg_replace('/-+/', '-', $slug) ?? '';
    return $slug !== '' ? $slug : 'corr';
}

/**
 * لاحقة قديمة (قبل المسار الحالي) كانت تحجز الرقم لنسخة نهائية — لم تعد تُستخدم عند الرفع.
 * تبقى للتعرف على الملفات القديمة وإعادة تسميتها.
 */
function correspondence_attachment_review_token(): string
{
    return 'REVIEW';
}

/**
 * اسم ملف مرفق الصادر: الرقم الرسمي (أو OD للمسودة).
 * النسخة النهائية (issued_copy) والمسودة المعتمدة تبقيان على نفس القاعدة.
 * لاحقة REVIEW أُلغيت — كانت لمسار قديم يحجز الرقم قبل النسخة المختومة.
 */
function correspondence_attachment_stem(string $base, string $type, ?string $docKind): string
{
    if ($type !== 'outgoing' || $base === '') {
        return $base;
    }
    if (str_starts_with($base, 'OD-') || str_starts_with($base, 'ODTMP-')) {
        return $base;
    }

    return $base;
}

function correspondence_attachment_pick_name(string $dir, string $stem, string $ext): string
{
    $n = 1;
    $safeName = $stem . '.' . $ext;
    do {
        $safeName = $n === 1 ? $stem . '.' . $ext : $stem . '_' . $n . '.' . $ext;
        $dest = $dir . DIRECTORY_SEPARATOR . $safeName;
        $n++;
    } while (is_file($dest) && $n < 200);
    if (is_file($dest)) {
        throw new RuntimeException('تعذر حفظ الملف.');
    }

    return $safeName;
}

/** إن كان رقم الصادر الأساسي محجوزاً بمرفق معاملة لنفس الصادر، يُنقل ذلك المرفق لاسم المعاملة. */
function correspondence_release_official_attachment_name(int $corrId, string $dir, string $relDir, string $base, string $ext): void
{
    if ($corrId <= 0 || $base === '' || $ext === '') {
        return;
    }
    $plain = $base . '.' . $ext;
    $dest = $dir . DIRECTORY_SEPARATOR . $plain;
    if (!is_file($dest)) {
        return;
    }
    $rel = dms_storage_db_path($relDir . '/' . $plain);
    $st = db()->prepare('SELECT id, correspondence_id, doc_kind, file_name, file_path FROM `tbldocuments` WHERE file_path = ?');
    $st->execute([$rel]);
    $rows = $st->fetchAll() ?: [];
    if (count($rows) !== 1) {
        return;
    }
    $row = $rows[0];
    if ((int) ($row['correspondence_id'] ?? 0) !== $corrId) {
        return;
    }
    $issued = function_exists('outgoing_issued_copy_kind') ? outgoing_issued_copy_kind() : 'issued_copy';
    if ((string) ($row['doc_kind'] ?? '') === $issued) {
        return;
    }
    $kind = $row['doc_kind'] ?? null;
    if (!is_string($kind) || trim($kind) === '') {
        $kind = null;
    }
    $stem = correspondence_attachment_stem($base, 'outgoing', $kind);
    correspondence_rename_stored_document($row, $stem, $ext);
}

function correspondence_rename_stored_document(array $doc, string $stem, string $ext): bool
{
    $id = (int) ($doc['id'] ?? 0);
    $stored = (string) ($doc['file_path'] ?? '');
    if ($id <= 0 || $stored === '' || $stem === '' || $ext === '') {
        return false;
    }
    $shared = db()->prepare('SELECT COUNT(*) FROM `tbldocuments` WHERE file_path = ? AND id <> ?');
    $shared->execute([$stored, $id]);
    if ((int) $shared->fetchColumn() > 0) {
        return false;
    }
    $full = dms_storage_disk_path($stored);
    if ($full === '' || !is_file($full)) {
        return false;
    }
    $current = (string) ($doc['file_name'] ?? '');
    $dir = dirname($full);
    $newName = correspondence_attachment_pick_name($dir, $stem, $ext);
    if ($newName === $current) {
        return false;
    }
    $newFull = $dir . DIRECTORY_SEPARATOR . $newName;
    if (!@rename($full, $newFull)) {
        return false;
    }
    $rel = str_replace('\\', '/', dms_storage_relative($stored));
    $relDir = trim(str_replace('\\', '/', dirname($rel)), '.');
    $newRel = dms_storage_db_path($relDir . '/' . $newName);
    db()->prepare('UPDATE `tbldocuments` SET file_name = ?, file_path = ? WHERE id = ?')->execute([$newName, $newRel, $id]);

    return true;
}

/** يفصل اسم مرفقات المعاملة عن رقم الصادر الأساسي لنسخة الصادر النهائية. */
function correspondence_align_outgoing_attachment_names(int $corrId): void
{
    static $done = [];
    if ($corrId <= 0 || isset($done[$corrId])) {
        return;
    }
    $done[$corrId] = true;
    $st = db()->prepare('SELECT type, ref_number FROM `tblcorrespondence` WHERE id = ?');
    $st->execute([$corrId]);
    $corr = $st->fetch();
    if (!$corr || (string) ($corr['type'] ?? '') !== 'outgoing') {
        return;
    }
    $base = correspondence_attachment_basename((string) ($corr['ref_number'] ?? ''));
    if ($base === '' || $base === 'corr' || str_starts_with($base, 'OD-') || str_starts_with($base, 'ODTMP-')) {
        return;
    }
    $docs = db()->prepare('SELECT id, file_name, file_path, doc_kind FROM `tbldocuments` WHERE correspondence_id = ? ORDER BY id');
    $docs->execute([$corrId]);
    $rows = $docs->fetchAll() ?: [];
    $issued = function_exists('outgoing_issued_copy_kind') ? outgoing_issued_copy_kind() : 'issued_copy';
    $draft = function_exists('outgoing_draft_file_kind') ? outgoing_draft_file_kind() : 'draft_approved';
    $token = correspondence_attachment_review_token();
    $quoted = preg_quote($base, '/');
    /* أسماء قديمة: *-REVIEW أو *-معاملة / *-مسودة → الرقم الرسمي */
    $legacy = '/^' . $quoted . '-(?:' . preg_quote($token, '/') . '|معاملة|مسودة)(?:_\d+)?\.([A-Za-z0-9]+)$/u';
    foreach ($rows as $doc) {
        $kind = trim((string) ($doc['doc_kind'] ?? ''));
        if ($kind === $issued || $kind === $draft || str_starts_with($kind, 'close_')) {
            continue;
        }
        $name = (string) ($doc['file_name'] ?? '');
        if (!preg_match($legacy, $name, $match)) {
            continue;
        }
        correspondence_rename_stored_document($doc, $base, (string) $match[1]);
    }
    $fresh = db()->prepare('SELECT id, file_name, file_path, doc_kind FROM `tbldocuments` WHERE correspondence_id = ? AND doc_kind = ? ORDER BY id');
    $fresh->execute([$corrId, $issued]);
    $issuedRows = $fresh->fetchAll() ?: [];
    if ($issuedRows === []) {
        return;
    }
    $first = $issuedRows[0];
    $ext = strtolower(pathinfo((string) ($first['file_name'] ?? ''), PATHINFO_EXTENSION));
    if ($ext === '' || (string) ($first['file_name'] ?? '') === $base . '.' . $ext) {
        return;
    }
    correspondence_rename_stored_document($first, $base, $ext);
}

function ensure_documents_doc_kind_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $cols = dms_db_column_map(DmsDbTable::DOCUMENTS);
        if (empty($cols['doc_kind'])) {
            db()->exec(
                "ALTER TABLE `" . DmsDbTable::DOCUMENTS . "`
                 ADD COLUMN `doc_kind` VARCHAR(32) NULL DEFAULT NULL COMMENT 'تصنيف المرفق مثل close_decision' AFTER `file_size`"
            );
        }
    } catch (Throwable $e) {
        // تجاهل أثناء التثبيت
    }
}

function upload_document(array $file, int $corrId, int $userId, ?string $docKind = null): ?array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    [$mime, $ext] = dms_validate_document_upload($file);

    ensure_documents_doc_kind_schema();

    $docKind = $docKind !== null ? trim($docKind) : null;
    if ($docKind === '') {
        $docKind = null;
    }
    if ($docKind !== null) {
        $allowedKinds = function_exists('correspondence_close_doc_kind_values')
            ? correspondence_close_doc_kind_values()
            : ['close_final', 'close_decision', 'close_letter', 'close_proof', 'close_other'];
        if (function_exists('outgoing_attachment_kind_values')) {
            $allowedKinds = array_merge($allowedKinds, outgoing_attachment_kind_values());
        }
        if (!in_array($docKind, $allowedKinds, true)) {
            throw new InvalidArgumentException(__('close.invalid_doc_kind'));
        }
    }

    $stmt = db()->prepare('SELECT type, ref_number FROM `tblcorrespondence` WHERE id = ?');
    $stmt->execute([$corrId]);
    $corr = $stmt->fetch();
    if (!$corr) {
        throw new RuntimeException(__('corr.not_found'));
    }

    $folder = correspondence_attachment_folder((string) ($corr['type'] ?? 'incoming'));
    $base = correspondence_attachment_basename((string) ($corr['ref_number'] ?? ''));
    $type = (string) ($corr['type'] ?? 'incoming');
    $relDir = $folder . '/' . date('Y/m');
    $dir = dms_storage_root() . '/' . $relDir;
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('تعذر إنشاء مجلد الرفع.');
    }

    $issuedKind = function_exists('outgoing_issued_copy_kind') ? outgoing_issued_copy_kind() : 'issued_copy';
    if ($docKind === $issuedKind) {
        correspondence_release_official_attachment_name($corrId, $dir, $relDir, $base, $ext);
    }
    $stem = correspondence_attachment_stem($base, $type, $docKind);
    $safeName = correspondence_attachment_pick_name($dir, $stem, $ext);
    $dest = $dir . DIRECTORY_SEPARATOR . $safeName;

    if (!dms_move_uploaded_file((string) $file['tmp_name'], $dest)) {
        throw new RuntimeException('تعذر حفظ الملف. تحقق من صلاحيات مجلد الرفع.');
    }

    $relative = dms_storage_db_path($relDir . '/' . $safeName);

    $stmt = db()->prepare(
        "INSERT INTO `tbldocuments` (correspondence_id, file_name, file_path, file_type, file_size, doc_kind, uploaded_by)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([$corrId, $safeName, $relative, $mime, (int) $file['size'], $docKind, $userId]);

    return [
        'file_name' => $safeName,
        'file_path' => $relative,
        'file_type' => $mime,
        'file_size' => (int) $file['size'],
        'doc_kind' => $docKind,
    ];
}

/**
 * نسخ مرفق موجود إلى معاملة أخرى (للصادر الجماعي بنفس الملف).
 * @param array{file_name:string,file_path:string,file_type:string,file_size:int,doc_kind?:?string} $source
 */
function clone_document_to_correspondence(array $source, int $corrId, int $userId): ?array
{
    $relativeSrc = trim((string) ($source['file_path'] ?? ''));
    if ($relativeSrc === '' || $corrId < 1) {
        return null;
    }
    $fullSrc = function_exists('dms_storage_disk_path')
        ? dms_storage_disk_path($relativeSrc)
        : (dirname(__DIR__) . '/' . ltrim(str_replace('\\', '/', $relativeSrc), '/'));
    if ($fullSrc === '' || !is_file($fullSrc)) {
        throw new RuntimeException('تعذر قراءة ملف المرفق للنسخ.');
    }

    ensure_documents_doc_kind_schema();
    $stmt = db()->prepare('SELECT type, ref_number FROM `tblcorrespondence` WHERE id = ?');
    $stmt->execute([$corrId]);
    $corr = $stmt->fetch();
    if (!$corr) {
        throw new RuntimeException(__('corr.not_found'));
    }

    $ext = strtolower(pathinfo((string) ($source['file_name'] ?? $fullSrc), PATHINFO_EXTENSION));
    if ($ext === '') {
        $ext = strtolower(pathinfo($fullSrc, PATHINFO_EXTENSION));
    }
    $folder = correspondence_attachment_folder((string) ($corr['type'] ?? 'outgoing'));
    $base = correspondence_attachment_basename((string) ($corr['ref_number'] ?? ''));
    $type = (string) ($corr['type'] ?? 'outgoing');
    $relDir = $folder . '/' . date('Y/m');
    $dir = dms_storage_root() . '/' . $relDir;
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('تعذر إنشاء مجلد الرفع.');
    }

    $docKind = $source['doc_kind'] ?? null;
    if (is_string($docKind) && trim($docKind) === '') {
        $docKind = null;
    }
    $issuedKind = function_exists('outgoing_issued_copy_kind') ? outgoing_issued_copy_kind() : 'issued_copy';
    if ($docKind === $issuedKind) {
        correspondence_release_official_attachment_name($corrId, $dir, $relDir, $base, $ext);
    }
    $stem = correspondence_attachment_stem($base, $type, is_string($docKind) ? $docKind : null);
    $safeName = correspondence_attachment_pick_name($dir, $stem, $ext);
    $dest = $dir . DIRECTORY_SEPARATOR . $safeName;
    if (!@copy($fullSrc, $dest) || !is_file($dest)) {
        throw new RuntimeException('تعذر نسخ ملف المرفق.');
    }

    $relative = dms_storage_db_path($relDir . '/' . $safeName);
    $mime = trim((string) ($source['file_type'] ?? '')) ?: 'application/octet-stream';
    $size = (int) ($source['file_size'] ?? @filesize($dest) ?: 0);
    $docKind = $source['doc_kind'] ?? null;
    if (is_string($docKind) && trim($docKind) === '') {
        $docKind = null;
    }

    db()->prepare(
        "INSERT INTO `tbldocuments` (correspondence_id, file_name, file_path, file_type, file_size, doc_kind, uploaded_by)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    )->execute([$corrId, $safeName, $relative, $mime, $size, $docKind, $userId]);

    return [
        'file_name' => $safeName,
        'file_path' => $relative,
        'file_type' => $mime,
        'file_size' => $size,
        'doc_kind' => $docKind,
    ];
}

/**
 * تطبيع حقل رفع واحد أو متعدد إلى قائمة ملفات.
 * @return list<array{name:string,type:string,tmp_name:string,error:int,size:int}>
 */
function dms_normalize_upload_files(?array $fileInput): array
{
    if (!$fileInput || !isset($fileInput['name'])) {
        return [];
    }
    if (!is_array($fileInput['name'])) {
        if ((int) ($fileInput['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return [];
        }
        return [$fileInput];
    }
    $out = [];
    foreach ($fileInput['name'] as $i => $name) {
        $error = (int) ($fileInput['error'][$i] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $out[] = [
            'name' => (string) $name,
            'type' => (string) ($fileInput['type'][$i] ?? ''),
            'tmp_name' => (string) ($fileInput['tmp_name'][$i] ?? ''),
            'error' => $error,
            'size' => (int) ($fileInput['size'][$i] ?? 0),
        ];
    }
    return $out;
}

/**
 * الحد الأقصى لحجم مرفق المعاملة (بايت).
 * يجب أن يبقى أقل من upload_max_filesize في php.ini.
 */
function dms_document_upload_max_bytes(): int
{
    return 50 * 1024 * 1024; // 50 MB
}

function dms_document_upload_max_label(): string
{
    $mb = (int) round(dms_document_upload_max_bytes() / (1024 * 1024));
    return $mb . ' ميجابايت';
}

/**
 * التحقق من ملف مرفق قبل إنشاء المعاملة (لتفادي سجل بلا مرفق عند فشل الرفع).
 * @return array{0:string,1:string} [mime, ext]
 */
function dms_validate_document_upload(array $file): array
{
    $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err === UPLOAD_ERR_NO_FILE || empty($file['tmp_name'])) {
        throw new InvalidArgumentException(__('corr.scan_required'));
    }
    if ($err !== UPLOAD_ERR_OK) {
        $phpLimit = (string) ini_get('upload_max_filesize');
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'حجم الملف أكبر من الحد المسموح في الخادم'
                . ($phpLimit !== '' ? ' (' . $phpLimit . '). صغّر الملف أو ارفع الحد في php.ini ثم أعد تشغيل Apache.' : '.'),
            UPLOAD_ERR_FORM_SIZE => 'حجم الملف أكبر من الحد المسموح في النموذج.',
            UPLOAD_ERR_PARTIAL => 'تم رفع الملف جزئياً فقط. أعد المحاولة.',
            UPLOAD_ERR_NO_TMP_DIR => 'مجلد الملفات المؤقتة غير متاح على الخادم.',
            UPLOAD_ERR_CANT_WRITE => 'تعذر كتابة الملف على القرص.',
            UPLOAD_ERR_EXTENSION => 'امتداد PHP منع رفع الملف.',
        ];
        throw new RuntimeException($messages[$err] ?? 'فشل رفع الملف.');
    }

    $allowed = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];
    $extByName = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
    ];

    $mime = '';
    if (class_exists('finfo') && is_readable((string) $file['tmp_name'])) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($file['tmp_name']);
    }
    $nameExt = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));

    // بعض المتصفحات/الماسحات ترفع PDF كـ octet-stream
    if (!isset($allowed[$mime]) && isset($extByName[$nameExt])) {
        if ($mime === '' || $mime === 'application/octet-stream' || $mime === 'application/x-download') {
            $mime = $extByName[$nameExt];
        }
    }
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('نوع الملف غير مسموح. المسموح: PDF, JPG, PNG, GIF, WEBP.');
    }
    if ((int) ($file['size'] ?? 0) > dms_document_upload_max_bytes()) {
        throw new RuntimeException('حجم الملف أكبر من ' . dms_document_upload_max_label() . '.');
    }

    return [$mime, $allowed[$mime]];
}

function departments_list(bool $activeOnly = true): array
{
    ensure_departments_schema();
    $sql = "SELECT * FROM `tbldepartments`";
    if ($activeOnly) {
        $sql .= " WHERE is_active = 1";
    }
    $sql .= " ORDER BY sort_order, name, id";
    $rows = db()->query($sql)->fetchAll() ?: [];
    if (!function_exists('department_officer_fields')) {
        return $rows;
    }
    foreach ($rows as &$row) {
        $officer = department_officer_fields($row);
        $row['title_prefix'] = $officer['title_prefix'];
        $row['directed_from'] = $officer['directed_from'];
        $row['job_title'] = $officer['job_title'];
    }
    unset($row);
    return $rows;
}

function get_correspondence(int $id): ?array
{
    ensure_correspondence_receipt_schema();
    if (function_exists('ensure_correspondence_closure_schema')) {
        ensure_correspondence_closure_schema();
    }
    if (function_exists('ensure_correspondence_issuing_schema')) {
        ensure_correspondence_issuing_schema();
    }
    if (function_exists('ensure_outgoing_approval_schema')) {
        ensure_outgoing_approval_schema();
    }
    ensure_departments_schema();
    ensure_entities_schema();
    ensure_correspondence_party_snapshot_schema();
    if (function_exists('ensure_entity_groups_schema')) {
        ensure_entity_groups_schema();
    }
    if (function_exists('ensure_corr_letter_schema')) {
        ensure_corr_letter_schema();
    }
    $stmt = db()->prepare(
        "SELECT c.*, d.name AS department_name, d.name_en AS department_name_en,
                d.directed_from AS department_directed_from,
                d.title_prefix AS department_title_prefix,
                d.job_title AS department_job_title,
                idpt.name AS issuing_department_name, idpt.name_en AS issuing_department_name_en,
                idpt.directed_from AS issuing_directed_from,
                idpt.title_prefix AS issuing_title_prefix,
                idpt.job_title AS issuing_job_title,
                " . user_name_sql('u', 'creator_name') . ",
                " . user_name_sql('ru', 'receipt_recorded_by_name') . ",
                " . user_name_sql('cu', 'closed_by_name') . ",
                l.ref_number AS linked_ref, l.type AS linked_type,
                cat.name AS category_name, cat.name_en AS category_name_en,
                e.name_ar AS entity_name,
                e.name_en AS entity_name_en,
                e.directed_to AS entity_directed_to,
                e.title_prefix AS entity_title_prefix,
                e.job_title AS entity_job_title,
                e.honorific AS entity_honorific,
                e.mobile AS entity_mobile,
                e.phone AS entity_phone,
                e.email AS entity_email,
                b.letter_body AS batch_letter_body,
                b.letter_body_en AS batch_letter_body_en,
                b.requires_rsvp AS batch_requires_rsvp
         FROM `tblcorrespondence` c
         LEFT JOIN `tbldepartments` d ON d.id = c.department_id
         LEFT JOIN `tbldepartments` idpt ON idpt.id = IFNULL(c.issuing_department_id, c.department_id)
         LEFT JOIN `tblusers` u ON u.id = c.created_by
         LEFT JOIN `tblusers` ru ON ru.id = c.receipt_recorded_by
         LEFT JOIN `tblusers` cu ON cu.id = c.closed_by
         LEFT JOIN `tblcorrespondence` l ON l.id = c.linked_id
         LEFT JOIN `tblcategories` cat ON cat.id = c.category_id
         LEFT JOIN `tblentities` e ON e.id = c.entity_id
         LEFT JOIN `tbloutgoing_batches` b ON b.id = c.batch_id
         WHERE c.id = ?"
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    return correspondence_apply_ref_display($row);
}

/** أعمدة بيانات استلام الصادر + صورة سند التسليم */
function ensure_correspondence_receipt_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        ensure_correspondence_status_schema();
        $cols = dms_db_column_map('tblcorrespondence');
        $alters = [];
        if (empty($cols['receipt_name'])) {
            $alters[] = 'ADD COLUMN `receipt_name` VARCHAR(255) NULL DEFAULT NULL COMMENT \'اسم مستلم الصادر\' AFTER `notes`';
        }
        if (empty($cols['receipt_date'])) {
            $alters[] = 'ADD COLUMN `receipt_date` DATE NULL DEFAULT NULL COMMENT \'تاريخ استلام الصادر\' AFTER `receipt_name`';
        }
        if (empty($cols['receipt_phone'])) {
            $alters[] = 'ADD COLUMN `receipt_phone` VARCHAR(50) NULL DEFAULT NULL COMMENT \'هاتف مستلم الصادر\' AFTER `receipt_date`';
        }
        if (empty($cols['receipt_file_path'])) {
            $alters[] = 'ADD COLUMN `receipt_file_path` VARCHAR(500) NULL DEFAULT NULL COMMENT \'مسار صورة سند التسليم\' AFTER `receipt_phone`';
        }
        if (empty($cols['receipt_file_name'])) {
            $alters[] = 'ADD COLUMN `receipt_file_name` VARCHAR(255) NULL DEFAULT NULL COMMENT \'اسم ملف سند التسليم\' AFTER `receipt_file_path`';
        }
        if (empty($cols['receipt_recorded_at'])) {
            $alters[] = 'ADD COLUMN `receipt_recorded_at` DATETIME NULL DEFAULT NULL COMMENT \'وقت تسجيل الاستلام\' AFTER `receipt_file_name`';
        }
        if (empty($cols['receipt_recorded_by'])) {
            $alters[] = 'ADD COLUMN `receipt_recorded_by` INT UNSIGNED NULL DEFAULT NULL COMMENT \'من سجّل الاستلام\' AFTER `receipt_recorded_at`';
        }
        if (empty($cols['courier_name'])) {
            $alters[] = 'ADD COLUMN `courier_name` VARCHAR(255) NULL DEFAULT NULL COMMENT \'المراسل\' AFTER `intend_electronic`';
        }
        if (empty($cols['courier_phone'])) {
            $alters[] = 'ADD COLUMN `courier_phone` VARCHAR(50) NULL DEFAULT NULL COMMENT \'جوال المراسل\' AFTER `courier_name`';
        }
        if (empty($cols['courier_id'])) {
            $alters[] = 'ADD COLUMN `courier_id` INT UNSIGNED NULL DEFAULT NULL COMMENT \'مرجع الدليل فقط؛ الحذف لا يمسّ الاسم المحفوظ على المعاملة\' AFTER `courier_phone`';
        }
        if ($alters) {
            db()->exec('ALTER TABLE `tblcorrespondence` ' . implode(', ', $alters));
        }
    } catch (Throwable $e) {
        // قاعدة البيانات غير جاهزة بعد
    }
    ensure_correspondence_indexes();
    ensure_corr_rsvp_schema();
    ensure_corr_electronic_schema();
    if (function_exists('ensure_correspondence_cancel_schema')) {
        ensure_correspondence_cancel_schema();
    }
    if (function_exists('ensure_briefing_schema')) {
        ensure_briefing_schema();
    }
}

/** نية الإرسال الإلكتروني (بريد) للصادر */
function ensure_corr_electronic_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $cols = dms_db_column_map('tblcorrespondence');
        if (empty($cols['intend_electronic'])) {
            $after = !empty($cols['rsvp_required']) ? 'AFTER `rsvp_required`' : '';
            db()->exec(
                "ALTER TABLE `tblcorrespondence`
                 ADD COLUMN `intend_electronic` TINYINT(1) NOT NULL DEFAULT 0
                 COMMENT 'سيُرسل إلكترونياً (بريد)' {$after}"
            );
        }
    } catch (Throwable $e) {
        error_log('DMS electronic schema: ' . $e->getMessage());
    }
}

function ensure_corr_external_date_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $cols = dms_db_column_map('tblcorrespondence');
        if (empty($cols['external_date'])) {
            $after = !empty($cols['external_ref']) ? 'AFTER `external_ref`' : '';
            db()->exec(
                "ALTER TABLE `tblcorrespondence`
                 ADD COLUMN `external_date` DATE NULL DEFAULT NULL
                 COMMENT 'تاريخ خطاب الجهة' {$after}"
            );
        }
    } catch (Throwable $e) {
        error_log('DMS external date schema: ' . $e->getMessage());
    }
}

/** تاريخ Y-m-d صالح أو null */
function corr_external_date_value($raw): ?string
{
    $raw = trim((string) $raw);
    if ($raw === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) !== 1) {
        return null;
    }
    [$y, $m, $d] = array_map('intval', explode('-', $raw));
    return checkdate($m, $d, $y) ? $raw : null;
}

function correspondence_intend_electronic(array $corr): bool
{
    return (int) ($corr['intend_electronic'] ?? 0) === 1;
}

/** نص الخطاب العربي/الإنجليزي على الصادر الفردي (إضافةً لنص دفعة الجماعي) */
function ensure_corr_letter_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $cols = dms_db_column_map('tblcorrespondence');
        $alters = [];
        if (empty($cols['letter_body'])) {
            $after = !empty($cols['intend_electronic'])
                ? 'AFTER `intend_electronic`'
                : (!empty($cols['notes']) ? 'AFTER `notes`' : '');
            $alters[] = "ADD COLUMN `letter_body` TEXT NULL DEFAULT NULL COMMENT 'نص الخطاب' {$after}";
        }
        // أعد قراءة الأعمدة إن أُضيف letter_body في نفس الطلب
        if ($alters !== []) {
            db()->exec('ALTER TABLE `tblcorrespondence` ' . implode(', ', $alters));
            $cols = dms_db_column_map('tblcorrespondence');
            $alters = [];
        }
        if (empty($cols['letter_body_en'])) {
            $after = !empty($cols['letter_body']) ? 'AFTER `letter_body`' : '';
            $alters[] = "ADD COLUMN `letter_body_en` TEXT NULL DEFAULT NULL COMMENT 'نص الخطاب إنجليزي' {$after}";
        }
        if ($alters !== []) {
            db()->exec('ALTER TABLE `tblcorrespondence` ' . implode(', ', $alters));
        }
    } catch (Throwable $e) {
        error_log('DMS letter schema: ' . $e->getMessage());
    }
}

/**
 * يبني صف صادر مؤقتاً من حقول النموذج لمعاينة الخطاب/البريد قبل الحفظ.
 *
 * @param array<string,mixed> $data
 * @return array<string,mixed>
 */
function correspondence_outgoing_draft_row(array $data, ?array $user = null, bool $sampleIfNoEntity = true): array
{
    $user = $user ?? (function_exists('current_user') ? current_user() : null);
    $entityId = (int) ($data['entity_id'] ?? 0);
    $entity = ($entityId > 0 && function_exists('get_entity')) ? get_entity($entityId) : null;
    $subject = trim((string) ($data['subject'] ?? ''));
    $corrDate = trim((string) ($data['corr_date'] ?? '')) ?: date('Y-m-d');
    $deptId = (int) ($data['department_id'] ?? 0);
    if ($deptId <= 0 && is_array($user)) {
        $deptId = (int) ($user['department_id'] ?? 0);
    }
    $issuingId = (int) ($data['issuing_department_id'] ?? 0);
    $letterBody = trim((string) ($data['letter_body'] ?? ''));
    $letterBodyEn = trim((string) ($data['letter_body_en'] ?? ''));
    $rsvp = (!empty($data['rsvp_required']) || !empty($data['requires_rsvp'])) ? 1 : 0;

    $row = [
        'id' => (int) ($data['id'] ?? 0),
        'type' => 'outgoing',
        'ref_number' => trim((string) ($data['ref_number'] ?? '')),
        'subject' => $subject !== '' ? $subject : (function_exists('__') ? __('tpl.preview_subject') : ''),
        'corr_date' => $corrDate,
        'letter_body' => $letterBody !== '' ? $letterBody : null,
        'letter_body_en' => $letterBodyEn !== '' ? $letterBodyEn : null,
        'rsvp_required' => $rsvp,
        'intend_electronic' => !empty($data['intend_electronic']) ? 1 : 0,
        'department_id' => $deptId > 0 ? $deptId : null,
        'issuing_department_id' => $issuingId > 0 ? $issuingId : null,
        'entity_id' => $entityId > 0 ? $entityId : null,
        'qr_token' => 'preview',
    ];
    if ($row['ref_number'] === '' && function_exists('corr_ref_sample')) {
        $row['ref_number'] = corr_ref_sample(function_exists('setting') ? (string) setting('outgoing_prefix', '') : '');
    }

    if (is_array($entity)) {
        $row['party_name'] = function_exists('entity_recipient_name')
            ? entity_recipient_name($entity)
            : trim((string) ($entity['name_ar'] ?? ''));
        $row['snap_title_prefix'] = trim((string) ($entity['title_prefix'] ?? ''));
        $row['snap_person_name'] = trim((string) ($entity['directed_to'] ?? $entity['name_ar'] ?? ''));
        $row['snap_honorific'] = trim((string) ($entity['honorific'] ?? ''));
        $row['snap_job_title'] = trim((string) ($entity['job_title'] ?? ''));
        $row['snap_org_name'] = trim((string) ($entity['name_ar'] ?? ''));
        $row['entity_name'] = trim((string) ($entity['name_ar'] ?? ''));
        $row['entity_name_en'] = trim((string) ($entity['name_en'] ?? ''));
        $row['entity_directed_to'] = trim((string) ($entity['directed_to'] ?? ''));
        $row['entity_title_prefix'] = trim((string) ($entity['title_prefix'] ?? ''));
        $row['entity_job_title'] = trim((string) ($entity['job_title'] ?? ''));
        $row['entity_honorific'] = trim((string) ($entity['honorific'] ?? ''));
        $row['entity_email'] = trim((string) ($entity['email'] ?? ''));
        $row['entity_mobile'] = trim((string) ($entity['mobile'] ?? ''));
        $row['entity_phone'] = trim((string) ($entity['phone'] ?? ''));
    } elseif ($sampleIfNoEntity) {
        $sample = function_exists('letter_preview_sample_row')
            ? letter_preview_sample_row('ar', $deptId)
            : [];
        $row = array_merge($sample, $row);
    }

    $signDeptId = $issuingId > 0 ? $issuingId : $deptId;
    if ($signDeptId > 0 && function_exists('admin_row')) {
        $dept = admin_row('tbldepartments', $signDeptId);
        if (is_array($dept)) {
            $officer = function_exists('department_officer_fields') ? department_officer_fields($dept) : [
                'title_prefix' => trim((string) ($dept['title_prefix'] ?? '')),
                'directed_from' => trim((string) ($dept['directed_from'] ?? '')),
                'job_title' => trim((string) ($dept['job_title'] ?? '')),
            ];
            $row['snap_from_prefix'] = $officer['title_prefix'];
            $row['snap_from_person'] = $officer['directed_from'];
            $row['snap_from_job'] = $officer['job_title'];
            $row['snap_from_signature'] = function_exists('department_principal_signature_path')
                ? department_principal_signature_path($dept)
                : '';
            if ($issuingId > 0) {
                $row['issuing_department_name'] = trim((string) ($dept['name'] ?? ''));
                $row['issuing_department_name_en'] = trim((string) ($dept['name_en'] ?? ''));
            } else {
                $row['department_name'] = trim((string) ($dept['name'] ?? ''));
                $row['department_name_en'] = trim((string) ($dept['name_en'] ?? ''));
            }
        }
    }
    if ($issuingId > 0 && $deptId > 0 && $issuingId !== $deptId && function_exists('admin_row')) {
        $prep = admin_row('tbldepartments', $deptId);
        if (is_array($prep)) {
            $row['department_name'] = trim((string) ($prep['name'] ?? ''));
            $row['department_name_en'] = trim((string) ($prep['name_en'] ?? ''));
        }
    }

    return $row;
}

/**
 * يضع حالة الصادر إلى «تم الإرسال» بعد نجاح البريد/القناة — دون اعتبارها تسليماً نهائياً.
 */
function correspondence_mark_sent_if_open(int $id, ?array $user = null, string $detail = ''): void
{
    correspondence_mark_outgoing_status_if_open($id, 'sent', $user, $detail, ['sent', 'delivered', 'closed', 'cancelled']);
}

/**
 * يضع حالة الصادر إلى «تم التسليم» إن كانت ما زالت مفتوحة.
 */
function correspondence_mark_delivered_if_open(int $id, ?array $user = null, string $detail = ''): void
{
    correspondence_mark_outgoing_status_if_open($id, 'delivered', $user, $detail, ['delivered', 'closed', 'cancelled']);
}

/**
 * @param list<string> $skipIfAlready
 */
function correspondence_mark_outgoing_status_if_open(
    int $id,
    string $newStatus,
    ?array $user = null,
    string $detail = '',
    array $skipIfAlready = []
): void {
    if ($id < 1 || $newStatus === '') {
        return;
    }
    if (function_exists('ensure_correspondence_status_schema')) {
        ensure_correspondence_status_schema();
    }
    try {
        $stmt = db()->prepare(
            "SELECT id, type, status FROM `tblcorrespondence` WHERE id = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row || ($row['type'] ?? '') !== 'outgoing') {
            return;
        }
        $old = (string) ($row['status'] ?? '');
        if ($skipIfAlready !== [] && in_array($old, $skipIfAlready, true)) {
            return;
        }
        if ($old === $newStatus) {
            return;
        }
        $skipSql = $skipIfAlready !== []
            ? " AND status NOT IN (" . implode(',', array_fill(0, count($skipIfAlready), '?')) . ")"
            : '';
        $params = array_merge([$newStatus, $id], $skipIfAlready);
        db()->prepare(
            "UPDATE `tblcorrespondence` SET status = ? WHERE id = ? AND type = 'outgoing'{$skipSql}"
        )->execute($params);
        $uid = (int) ($user['id'] ?? 0);
        if ($uid > 0 && function_exists('log_activity')) {
            $msg = status_label($old) . ' ← ' . status_label($newStatus);
            if ($detail !== '') {
                $msg .= ' — ' . $detail;
            }
            log_activity($uid, $id, 'status', $msg);
        }
    } catch (Throwable $e) {
        error_log('DMS mark status: ' . $e->getMessage());
    }
}

/** يضيف فهرسًا إن لم يكن موجودًا. الفهارس تحسين، فلا يُفشل غيابها التهيئة. */
function dms_ensure_index(string $table, string $indexName, string $definition): void
{
    try {
        $pdo = db();
        $stmt = $pdo->prepare(
            'SELECT 1 FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND LOWER(TABLE_NAME) = ? AND INDEX_NAME = ?
             LIMIT 1'
        );
        $stmt->execute([dms_db_table_name($table), $indexName]);
        if ($stmt->fetchColumn()) {
            return;
        }
        $pdo->exec("ALTER TABLE `{$table}` ADD INDEX `{$indexName}` {$definition}");
    } catch (Throwable $e) {
        // تجاهل: الفهرس تحسين اختياري
    }
}

/**
 * فهارس مركّبة تُسرّع قوائم الصادر والوارد عند تضخّم عدد المعاملات.
 * كل فهرس يطابق ترتيب شرط WHERE ثم عمود الفرز في استعلام فعلي.
 */
function ensure_correspondence_indexes(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    // ملاحظة: InnoDB يُلحق المفتاح الأساسي بكل فهرس ثانوي، فـ idx_type يعمل
    // فعليًا كـ (type, id) ويغطي ORDER BY id DESC. لذلك لا نكرّره هنا.

    // تصفية الحالة والفترة داخل نوع واحد
    dms_ensure_index('tblcorrespondence', 'idx_corr_type_status_date', '(`type`, `status`, `corr_date`)');
    // القوائم المحصورة بقسم المستخدم
    dms_ensure_index('tblcorrespondence', 'idx_corr_dept_type', '(`department_id`, `type`)');
    // المتأخرة والمستحقة قريبًا
    dms_ensure_index('tblcorrespondence', 'idx_corr_due_status', '(`reply_due`, `status`)');
    // شرط EXISTS في نطاق الأقسام داخل department_scope_sql()
    dms_ensure_index('tblreferrals', 'idx_ref_corr_dept', '(`correspondence_id`, `to_department_id`)');
}

function ensure_corr_rsvp_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $pdo = db();
        if (function_exists('ensure_entity_groups_schema')) {
            ensure_entity_groups_schema();
        }
        $batchCols = dms_db_column_map('tbloutgoing_batches');
        if ($batchCols && empty($batchCols['requires_rsvp'])) {
            $pdo->exec(
                "ALTER TABLE `tbloutgoing_batches`
                 ADD COLUMN `requires_rsvp` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'يتطلب تأكيد الحضور' AFTER letter_body"
            );
            $pdo->exec(
                "UPDATE `tbloutgoing_batches`
                 SET requires_rsvp = 1
                 WHERE letter_body IS NOT NULL AND TRIM(letter_body) <> ''"
            );
        }

        $cols = dms_db_column_map('tblcorrespondence');
        $alters = [];
        if (empty($cols['rsvp_required'])) {
            $alters[] = "ADD COLUMN `rsvp_required` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'يتطلب رد حضور' AFTER `batch_id`";
        }
        if (empty($cols['rsvp_response'])) {
            $alters[] = "ADD COLUMN `rsvp_response` ENUM('attending','declined') NULL DEFAULT NULL COMMENT 'رد الحضور' AFTER `rsvp_required`";
        }
        if (empty($cols['rsvp_note'])) {
            $alters[] = "ADD COLUMN `rsvp_note` VARCHAR(400) NULL DEFAULT NULL AFTER `rsvp_response`";
        }
        if (empty($cols['rsvp_at'])) {
            $alters[] = "ADD COLUMN `rsvp_at` DATETIME NULL DEFAULT NULL AFTER `rsvp_note`";
        }
        if (empty($cols['rsvp_ip'])) {
            $alters[] = "ADD COLUMN `rsvp_ip` VARCHAR(45) NULL DEFAULT NULL AFTER `rsvp_at`";
        }
        if ($alters) {
            $pdo->exec('ALTER TABLE `tblcorrespondence` ' . implode(', ', $alters));
        }
        $pdo->exec(
            "UPDATE `tblcorrespondence` c
             INNER JOIN `tbloutgoing_batches` b ON b.id = c.batch_id
             SET c.rsvp_required = 1
             WHERE b.requires_rsvp = 1 AND c.type = 'outgoing' AND c.rsvp_required = 0"
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `tblcorr_rsvp_log` (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                correspondence_id INT UNSIGNED NOT NULL,
                response ENUM('attending','declined') NOT NULL,
                note VARCHAR(400) DEFAULT NULL,
                source VARCHAR(20) NOT NULL DEFAULT 'public',
                ip_address VARCHAR(45) DEFAULT NULL,
                user_id INT UNSIGNED DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_rsvp_corr (correspondence_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    } catch (Throwable $e) {
        error_log('DMS RSVP schema: ' . $e->getMessage());
    }
}

function correspondence_rsvp_required(array $corr): bool
{
    return (int) ($corr['rsvp_required'] ?? 0) === 1
        || (int) ($corr['batch_requires_rsvp'] ?? 0) === 1;
}

/** تنبيه طلب الرد على الحضور في الخطاب الورقي (خارج نص القالب الرسمي) */
function render_letter_rsvp_notice(array $corr, string $lang = 'ar'): void
{
    if (!correspondence_rsvp_required($corr)) {
        return;
    }
    $lang = $lang === 'en' ? 'en' : 'ar';
    $text = function_exists('__lang') ? __lang('rsvp.print_ask', $lang) : __('rsvp.print_ask');
    $qrUri = dms_corr_qr_data_uri($corr, 160, true);
    echo '<div class="letter-rsvp-block">';
    echo '<div class="letter-rsvp-note">' . e($text) . '</div>';
    if ($qrUri !== '') {
        echo '<div class="letter-rsvp-qr" aria-hidden="true">'
            . '<img src="' . e($qrUri) . '" alt="" width="160" height="160" decoding="sync">'
            . '</div>';
    }
    echo '</div>';
}

function rsvp_response_label(?string $response): string
{
    return match ((string) $response) {
        'attending' => __('rsvp.attending'),
        'declined' => __('rsvp.declined'),
        default => __('rsvp.pending'),
    };
}

function rsvp_response_badge(?string $response): string
{
    $map = [
        'attending' => 'success',
        'declined' => 'danger',
    ];
    $code = (string) $response;
    $color = $map[$code] ?? 'secondary';
    return '<span class="badge text-bg-' . $color . '">' . e(rsvp_response_label($code !== '' ? $code : null)) . '</span>';
}

function save_correspondence_rsvp(array $corr, string $response, string $note = '', string $source = 'public', ?array $user = null): void
{
    ensure_corr_rsvp_schema();
    if (($corr['type'] ?? '') !== 'outgoing') {
        throw new RuntimeException(__('rsvp.outgoing_only'));
    }
    if (correspondence_is_cancelled($corr)) {
        throw new RuntimeException(__('cancel.locked'));
    }
    if (!in_array($response, ['attending', 'declined'], true)) {
        throw new InvalidArgumentException(__('rsvp.invalid'));
    }
    if ($source === 'public' && !correspondence_rsvp_required($corr)) {
        throw new RuntimeException(__('rsvp.not_required'));
    }
    if (function_exists('outgoing_can_issue') && !outgoing_can_issue($corr)) {
        $msg = function_exists('outgoing_approval_block_post_issue')
            ? outgoing_approval_block_post_issue($corr)
            : __('issue_appr.blocked_post_issue');
        throw new RuntimeException($msg !== '' ? $msg : __('issue_appr.blocked_post_issue'));
    }
    $id = (int) ($corr['id'] ?? 0);
    if ($id < 1) {
        throw new RuntimeException(__('corr.not_found'));
    }
    $note = mb_substr(trim($note), 0, 400);
    $ip = function_exists('client_ip') ? (client_ip() ?: null) : null;
    $userId = isset($user['id']) ? (int) $user['id'] : null;
    db()->prepare(
        "UPDATE `tblcorrespondence`
         SET rsvp_required = 1, rsvp_response = ?, rsvp_note = ?, rsvp_at = NOW(), rsvp_ip = ?
         WHERE id = ? AND type = 'outgoing'"
    )->execute([$response, $note !== '' ? $note : null, $ip, $id]);
    try {
        db()->prepare(
            "INSERT INTO `tblcorr_rsvp_log` (correspondence_id, response, note, source, ip_address, user_id)
             VALUES (?, ?, ?, ?, ?, ?)"
        )->execute([$id, $response, $note !== '' ? $note : null, $source, $ip, $userId]);
    } catch (Throwable $e) {
        error_log('DMS RSVP log: ' . $e->getMessage());
    }
    log_activity($userId, $id, 'rsvp', rsvp_response_label($response) . ($note !== '' ? ' — ' . $note : ''));
}

function set_batch_rsvp_required(int $batchId, bool $required = true): void
{
    ensure_corr_rsvp_schema();
    $flag = $required ? 1 : 0;
    db()->prepare('UPDATE `tbloutgoing_batches` SET requires_rsvp = ? WHERE id = ?')->execute([$flag, $batchId]);
    db()->prepare(
        "UPDATE `tblcorrespondence` SET rsvp_required = ? WHERE batch_id = ? AND type = 'outgoing' AND status <> 'cancelled'"
    )->execute([$flag, $batchId]);
}

function batch_rsvp_summary(array $items): array
{
    $out = ['attending' => 0, 'declined' => 0, 'pending' => 0, 'total' => count($items)];
    foreach ($items as $row) {
        $resp = (string) ($row['rsvp_response'] ?? '');
        if ($resp === 'attending') {
            $out['attending']++;
        } elseif ($resp === 'declined') {
            $out['declined']++;
        } else {
            $out['pending']++;
        }
    }
    return $out;
}

/**
 * حفظ بيانات استلام الصادر بعد التسليم (+ صورة سند اختيارية)
 */
function save_outgoing_receipt(int $corrId, array $data, ?array $file, int $userId): void
{
    ensure_correspondence_receipt_schema();
    $corr = get_correspondence($corrId);
    if (!$corr || ($corr['type'] ?? '') !== 'outgoing') {
        throw new InvalidArgumentException(__('corr.not_found'));
    }
    correspondence_assert_mutable($corr);
    if (function_exists('outgoing_can_issue') && !outgoing_can_issue($corr)) {
        $msg = function_exists('outgoing_approval_block_post_issue')
            ? outgoing_approval_block_post_issue($corr)
            : __('issue_appr.blocked_post_issue');
        throw new RuntimeException($msg !== '' ? $msg : __('issue_appr.blocked_post_issue'));
    }

    $name = trim((string) ($data['receipt_name'] ?? ''));
    $date = trim((string) ($data['receipt_date'] ?? ''));
    $phone = trim((string) ($data['receipt_phone'] ?? ''));

    if ($name === '') {
        throw new InvalidArgumentException(__('receipt.name_required'));
    }
    if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        throw new InvalidArgumentException(__('receipt.date_required'));
    }

    $filePath = $corr['receipt_file_path'] ?? null;
    $fileName = $corr['receipt_file_name'] ?? null;

    if ($file && (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE)) {
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new RuntimeException(__('receipt.upload_fail'));
        }
        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
        ];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        if (!isset($allowed[$mime])) {
            throw new RuntimeException(__('receipt.file_type'));
        }
        if (($file['size'] ?? 0) > dms_document_upload_max_bytes()) {
            throw new RuntimeException(__('receipt.file_size'));
        }

        $ext = $allowed[$mime];
        $safeName = 'receipt_' . $corrId . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
        $relDir = 'receipts/' . date('Y/m');
        $dir = dms_storage_root() . '/' . $relDir;
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException(__('receipt.upload_fail'));
        }
        $dest = $dir . '/' . $safeName;
        if (!dms_move_uploaded_file((string) $file['tmp_name'], $dest)) {
            throw new RuntimeException(__('receipt.upload_fail'));
        }

        // حذف الملف القديم إن وُجد
        if (!empty($filePath)) {
            $old = dms_storage_disk_path((string) $filePath);
            if ($old !== '' && is_file($old)) {
                @unlink($old);
            }
        }

        $filePath = dms_storage_db_path($relDir . '/' . $safeName);
        $fileName = basename((string) ($file['name'] ?? $safeName));
    }

    $stmt = db()->prepare(
        "UPDATE `tblcorrespondence`
         SET receipt_name = ?, receipt_date = ?, receipt_phone = ?,
             receipt_file_path = ?, receipt_file_name = ?,
             receipt_recorded_at = NOW(), receipt_recorded_by = ?,
             status = IF(status = 'closed', 'closed', 'delivered')
         WHERE id = ? AND type = 'outgoing'"
    );
    $stmt->execute([
        $name,
        $date,
        $phone !== '' ? $phone : null,
        $filePath,
        $fileName,
        $userId,
        $corrId,
    ]);
}

function clear_outgoing_receipt_file(int $corrId): void
{
    ensure_correspondence_receipt_schema();
    $corr = get_correspondence($corrId);
    if (!$corr || ($corr['type'] ?? '') !== 'outgoing') {
        throw new InvalidArgumentException(__('corr.not_found'));
    }
    correspondence_assert_mutable($corr);
    if (function_exists('outgoing_can_issue') && !outgoing_can_issue($corr)) {
        $msg = function_exists('outgoing_approval_block_post_issue')
            ? outgoing_approval_block_post_issue($corr)
            : __('issue_appr.blocked_post_issue');
        throw new RuntimeException($msg !== '' ? $msg : __('issue_appr.blocked_post_issue'));
    }
    $path = (string) ($corr['receipt_file_path'] ?? '');
    if ($path !== '') {
        $full = dms_storage_disk_path($path);
        if ($full !== '' && is_file($full)) {
            @unlink($full);
        }
    }
    db()->prepare(
        "UPDATE `tblcorrespondence`
         SET receipt_file_path = NULL, receipt_file_name = NULL
         WHERE id = ? AND type = 'outgoing'"
    )->execute([$corrId]);
}

function correspondence_has_receipt(array $corr): bool
{
    return trim((string) ($corr['receipt_name'] ?? '')) !== ''
        && !empty($corr['receipt_date']);
}

/** أيام التنبيه لصادر بلا استلام (افتراضي 7) */
function outgoing_receipt_alert_days(): int
{
    $days = (int) setting('outgoing_receipt_alert_days', '7');
    if ($days < 1) {
        return 1;
    }
    if ($days > 365) {
        return 365;
    }
    return $days;
}

/**
 * شرط عزل بيانات القسم لمستخدم القسم:
 * قسمه المعدّ، أو جهة التوقيع، أو ما أنشأه، أو محال إليه.
 * المدير/الأرشيف/المشاهد العام: بدون قيد.
 * @return array{0:string,1:list<mixed>} [SQL بدون AND، معاملات]
 */
function department_scope_sql(?array $user = null, string $alias = 'c'): array
{
    $user = $user ?? (function_exists('current_user') ? current_user() : null);
    if (
        !$user
        || !function_exists('user_needs_department_scope')
        || !user_needs_department_scope($user)
    ) {
        return ['', []];
    }
    $ids = function_exists('user_department_ids') ? user_department_ids($user) : [];
    if (!$ids) {
        return ['1=0', []];
    }

    [$corrIn, $corrParams] = dms_sql_in("{$alias}.department_id", $ids);
    $parts = [$corrIn];
    $params = $corrParams;

    if (function_exists('dms_db_column_exists') && dms_db_column_exists('tblcorrespondence', 'issuing_department_id')) {
        [$issIn, $issParams] = dms_sql_in("{$alias}.issuing_department_id", $ids);
        $parts[] = $issIn;
        foreach ($issParams as $p) {
            $params[] = $p;
        }
    }

    [$refIn, $refParams] = dms_sql_in('r.to_department_id', $ids);
    $parts[] = "EXISTS (
        SELECT 1 FROM `tblreferrals` r
        WHERE r.correspondence_id = {$alias}.id AND {$refIn}
    )";
    foreach ($refParams as $p) {
        $params[] = $p;
    }

    $sql = '(' . implode(' OR ', $parts) . ')';
    if (function_exists('incoming_approval_not_held_sql')
        && function_exists('dms_db_column_exists')
        && dms_db_column_exists('tblcorrespondence', 'issue_approval_status')
    ) {
        $sql = '(' . $sql . ' AND ' . incoming_approval_not_held_sql($alias) . ')';
    }

    $uid = (int) ($user['id'] ?? 0);
    if ($uid > 0 && function_exists('dms_db_column_exists') && dms_db_column_exists('tblcorrespondence', 'created_by')) {
        $sql = '(' . $sql . " OR {$alias}.created_by = ?)";
        $params[] = $uid;
    }

    return [$sql, $params];
}

/** هل يحق للمستخدم رؤية/تعديل هذه المعاملة حسب القسم؟ */
function user_can_access_correspondence(?array $corr, ?array $user = null): bool
{
    if (!$corr) {
        return false;
    }
    $user = $user ?? (function_exists('current_user') ? current_user() : null);
    if (!$user) {
        return false;
    }
    if (function_exists('outgoing_prep_is_superseded') && outgoing_prep_is_superseded($corr)) {
        return outgoing_user_can_view_superseded_prep($user, $corr);
    }
    if (function_exists('outgoing_prep_is_open') && outgoing_prep_is_open($corr)) {
        return outgoing_user_can_access_prep($user, $corr);
    }
    if (
        !function_exists('user_needs_department_scope')
        || !user_needs_department_scope($user)
    ) {
        return true;
    }

    // معتمد إصدار الصادر (أول / تصعيد / بديل) يحتاج فتح المعاملة عبر الإشعار أو لوحة الاعتماد
    if (function_exists('outgoing_user_can_access_for_approval')
        && outgoing_user_can_access_for_approval($user, $corr)
    ) {
        return true;
    }

    if (function_exists('incoming_approval_is_held') && incoming_approval_is_held($corr)) {
        return (int) ($corr['created_by'] ?? 0) > 0 && (int) $corr['created_by'] === (int) ($user['id'] ?? 0);
    }

    $ids = function_exists('user_department_ids') ? user_department_ids($user) : [];
    if (!$ids) {
        return false;
    }

    $corrDept = (int) ($corr['department_id'] ?? 0);
    if ($corrDept > 0 && in_array($corrDept, $ids, true)) {
        return true;
    }

    $issuingDept = (int) ($corr['issuing_department_id'] ?? 0);
    if ($issuingDept > 0 && in_array($issuingDept, $ids, true)) {
        return true;
    }

    $createdBy = (int) ($corr['created_by'] ?? 0);
    if ($createdBy > 0 && $createdBy === (int) ($user['id'] ?? 0)) {
        return true;
    }

    try {
        [$inSql, $inParams] = dms_sql_in('to_department_id', $ids);
        $stmt = db()->prepare(
            "SELECT 1 FROM `tblreferrals` WHERE correspondence_id = ? AND {$inSql} LIMIT 1"
        );
        $stmt->execute(array_merge([(int) $corr['id']], $inParams));
        return (bool) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

/** أي درجة سرية غير «عادي» تقيّد الاطلاع على المرفقات */
function confidentiality_is_restricted(?string $level): bool
{
    $level = strtolower(trim((string) $level));
    return $level !== '' && $level !== 'normal';
}

function view_confidential_perm_for_type(string $type): string
{
    return match ($type) {
        'incoming' => 'view.confidential.incoming',
        'internal' => 'view.confidential.internal',
        'outgoing' => 'view.confidential.outgoing',
        'scholarships' => 'view.confidential.scholarships',
        'projects' => 'view.confidential.projects',
        'finance' => 'view.confidential.finance',
        'general' => 'view.confidential.general',
        default => '',
    };
}

/**
 * مرفقات المعاملة السرية: من يملك صلاحية سرية هذا النوع (والمدير)، والمنشئ، ومعتمدو المعاملة، و«للاطلاع».
 * يُشترط فوق ذلك حق فتح المعاملة نفسها.
 */
function user_can_view_corr_files(array $corr, ?array $user = null): bool
{
    if (!confidentiality_is_restricted($corr['confidentiality'] ?? '')) {
        return true;
    }
    $user = $user ?? (function_exists('current_user') ? current_user() : null);
    if (!$user) {
        return false;
    }
    $perm = view_confidential_perm_for_type((string) ($corr['type'] ?? ''));
    if ($perm !== '' && user_can($perm, $user)) {
        return true;
    }
    $uid = (int) ($user['id'] ?? 0);
    if ($uid > 0 && $uid === (int) ($corr['created_by'] ?? 0)) {
        return true;
    }
    if (function_exists('outgoing_user_can_access_for_approval') && outgoing_user_can_access_for_approval($user, $corr)) {
        return true;
    }
    return function_exists('corr_cc_user_can_view') && corr_cc_user_can_view($corr, $user);
}

/** مرفق بعينه: صاحب الرفع يبقى قادراً على فتح ما رفعه */
function user_can_view_corr_document(array $corr, array $doc, ?array $user = null): bool
{
    $user = $user ?? (function_exists('current_user') ? current_user() : null);
    $uid = (int) ($user['id'] ?? 0);
    return ($uid > 0 && $uid === (int) ($doc['uploaded_by'] ?? 0)) || user_can_view_corr_files($corr, $user);
}

/**
 * عدد الصادر المتأخر بدون بيانات استلام
 */
function outgoing_missing_receipt_count(?array $user = null, ?int $days = null): int
{
    ensure_correspondence_receipt_schema();
    $user = $user ?? current_user();
    $days = $days ?? outgoing_receipt_alert_days();
    $where = [
        "c.type = 'outgoing'",
        "(c.receipt_name IS NULL OR c.receipt_name = '')",
        "c.status <> 'delivered'",
        "c.status <> 'closed'",
        "c.status <> 'cancelled'",
        'IFNULL(c.intend_electronic, 0) = 0',
        "c.corr_date <= DATE_SUB(CURDATE(), INTERVAL ? DAY)",
        '(c.outgoing_prep_status IS NULL OR c.outgoing_prep_status NOT IN (\'draft\',\'pending_signer\',\'superseded\'))',
    ];
    $params = [$days];
    [$scopeSql, $scopeParams] = department_scope_sql($user, 'c');
    if ($scopeSql !== '') {
        $where[] = $scopeSql;
        foreach ($scopeParams as $p) {
            $params[] = $p;
        }
    }
    $stmt = db()->prepare('SELECT COUNT(*) FROM `tblcorrespondence` c WHERE ' . implode(' AND ', $where));
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

/** سجل نشاط معاملة واحدة (مسار تدقيق خفيف) */
function get_correspondence_activity(int $corrId, int $limit = 40): array
{
    $limit = max(1, min(100, $limit));
    if (function_exists('ensure_security_schema')) {
        ensure_security_schema();
    }
    try {
        $stmt = db()->prepare(
            "SELECT a.*, " . user_name_sql('u', 'user_name') . "
             FROM `tblactivity_log` a
             LEFT JOIN `tblusers` u ON u.id = a.user_id
             WHERE a.correspondence_id = ?
             ORDER BY a.id DESC
             LIMIT {$limit}"
        );
        $stmt->execute([$corrId]);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function get_documents(int $corrId): array
{
    $stmt = db()->prepare(
        "SELECT d.*, " . user_name_sql('u', 'uploader_name') . "
         FROM `tbldocuments` d
         LEFT JOIN `tblusers` u ON u.id = d.uploaded_by
         WHERE d.correspondence_id = ?
         ORDER BY d.uploaded_at DESC"
    );
    $stmt->execute([$corrId]);
    return $stmt->fetchAll();
}

function get_referrals(int $corrId): array
{
    $stmt = db()->prepare(
        "SELECT r.*, fd.name AS from_dept_name, fd.name_en AS from_dept_name_en,
                fd.title_prefix AS from_dept_title_prefix, fd.directed_from AS from_dept_directed_from,
                td.name AS to_dept_name, td.name_en AS to_dept_name_en,
                td.title_prefix AS to_dept_title_prefix, td.directed_from AS to_dept_directed_from,
                " . user_name_sql('u', 'from_user_name') . "
         FROM `tblreferrals` r
         LEFT JOIN `tbldepartments` fd ON fd.id = r.from_department_id
         LEFT JOIN `tbldepartments` td ON td.id = r.to_department_id
         LEFT JOIN `tblusers` u ON u.id = r.from_user_id
         WHERE r.correspondence_id = ?
         ORDER BY r.created_at DESC"
    );
    $stmt->execute([$corrId]);
    return $stmt->fetchAll();
}

function dms_pagination_meta(int $total, int $page, int $perPage = 10): array
{
    $perPage = max(1, min(100, $perPage));
    $pages = $total > 0 ? (int) ceil($total / $perPage) : 1;
    $pages = max(1, $pages);
    $page = max(1, min($page, $pages));
    $offset = ($page - 1) * $perPage;

    return [
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'pages' => $pages,
        'offset' => $offset,
        'from' => $total > 0 ? $offset + 1 : 0,
        'to' => $total > 0 ? min($offset + $perPage, $total) : 0,
        'has_prev' => $page > 1,
        'has_next' => $page < $pages,
    ];
}

function dms_pagination_summary(array $meta): string
{
    $total = (int) ($meta['total'] ?? 0);
    if ($total <= 0) {
        return '';
    }

    return str_replace(
        ['{from}', '{to}', '{total}'],
        [(string) ($meta['from'] ?? 0), (string) ($meta['to'] ?? 0), (string) $total],
        __('common.pagination_summary')
    );
}

function dms_render_pagination(array $meta, string $pageParam, ?string $baseUrl = null, ?string $anchor = null): string
{
    $pages = (int) ($meta['pages'] ?? 1);
    if ($pages <= 1) {
        return '';
    }

    $page = (int) ($meta['page'] ?? 1);
    $baseUrl = $baseUrl ?? strtok((string) ($_SERVER['REQUEST_URI'] ?? ''), '?');
    if ($baseUrl === false || $baseUrl === '') {
        $baseUrl = base_url('index.php');
    }

    $query = $_GET;
    unset($query[$pageParam]);
    $anchorId = $anchor !== null && $anchor !== ''
        ? preg_replace('/[^a-z0-9_-]/i', '', $anchor)
        : '';

    $link = static function (int $p) use ($baseUrl, $query, $pageParam, $anchorId): string {
        $q = $query;
        if ($p > 1) {
            $q[$pageParam] = (string) $p;
        }
        $qs = http_build_query($q);
        $url = $baseUrl . ($qs !== '' ? '?' . $qs : '');

        return e($url) . ($anchorId !== '' ? '#' . $anchorId : '');
    };

    $rtl = function_exists('dms_is_rtl') && dms_is_rtl();
    $prevIcon = $rtl ? 'bi-chevron-right' : 'bi-chevron-left';
    $nextIcon = $rtl ? 'bi-chevron-left' : 'bi-chevron-right';

    $html = '<nav class="dash-pagination" aria-label="' . e(__('common.pagination')) . '"><ul class="pagination pagination-sm mb-0">';

    if (!empty($meta['has_prev'])) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $link($page - 1) . '"><i class="bi ' . $prevIcon . '"></i> ' . e(__('common.prev')) . '</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link"><i class="bi ' . $prevIcon . '"></i> ' . e(__('common.prev')) . '</span></li>';
    }

    $window = 2;
    $start = max(1, $page - $window);
    $end = min($pages, $page + $window);
    if ($start > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $link(1) . '">1</a></li>';
        if ($start > 2) {
            $html .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
        }
    }
    for ($i = $start; $i <= $end; $i++) {
        if ($i === $page) {
            $html .= '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
        } else {
            $html .= '<li class="page-item"><a class="page-link" href="' . $link($i) . '">' . $i . '</a></li>';
        }
    }
    if ($end < $pages) {
        if ($end < $pages - 1) {
            $html .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
        }
        $html .= '<li class="page-item"><a class="page-link" href="' . $link($pages) . '">' . $pages . '</a></li>';
    }

    if (!empty($meta['has_next'])) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $link($page + 1) . '">' . e(__('common.next')) . ' <i class="bi ' . $nextIcon . '"></i></a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link">' . e(__('common.next')) . ' <i class="bi ' . $nextIcon . '"></i></span></li>';
    }

    $html .= '</ul></nav>';

    return $html;
}

function dms_list_per_page(): int
{
    return 20;
}

function dms_request_page(string $param = 'page'): int
{
    return max(1, (int) ($_GET[$param] ?? 1));
}

function dms_hijri_picker_boot(?int $minYear = null, ?int $maxYear = null): string
{
    require_once __DIR__ . '/hijri.php';
    $en = function_exists('dms_lang_code') && dms_lang_code() === 'en';
    $cfg = [
        'lengths' => dms_ummalqura_month_lengths(),
        'months' => dms_hijri_month_names(),
        'weekdays' => $en
            ? ['Sat', 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri']
            : ['سبت', 'أحد', 'إثنين', 'ثلاثاء', 'أربعاء', 'خميس', 'جمعة'],
        'today' => __('corr.hijri_today'),
        'clear' => __('corr.hijri_clear'),
        'pick' => __('corr.hijri_pick'),
        'minYear' => $minYear ?? dms_filter_min_hijri_year(),
        'maxYear' => $maxYear ?? dms_current_hijri_parts()[0],
    ];
    $js = __DIR__ . '/../assets/js/hijri-picker.js';
    $v = (int) (@filemtime($js) ?: 1);

    return '<script>window.__DMS_HIJRI__=' . json_encode($cfg, JSON_UNESCAPED_UNICODE) . ';</script>'
        . '<script src="' . e(function_exists('dms_asset_url') ? dms_asset_url('assets/js/hijri-picker.js', true) : (base_url('assets/js/hijri-picker.js') . '?v=' . $v)) . '"></script>';
}

function dms_list_page_scripts(): string
{
    static $loaded = false;
    if ($loaded) {
        return '';
    }
    $loaded = true;

    $pager = function_exists('dms_asset_url')
        ? dms_asset_url('assets/js/list-pagination.js', true)
        : (base_url('assets/js/list-pagination.js') . '?v=1');

    return dms_hijri_picker_boot()
        . '<script src="' . e($pager) . '"></script>';
}

/**
 * @return array{where:list<string>,params:list<mixed>}
 */
function referrals_list_where(string $statusFilter = '', ?array $user = null): array
{
    $user = $user ?? current_user();
    $where = ['1=1'];
    $params = [];

    if (function_exists('user_needs_department_scope') && user_needs_department_scope($user)) {
        $ids = function_exists('user_department_ids') ? user_department_ids($user) : [];
        if (!$ids) {
            $where[] = '1=0';
        } else {
            [$toIn, $toParams] = dms_sql_in('r.to_department_id', $ids);
            [$fromIn, $fromParams] = dms_sql_in('r.from_department_id', $ids);
            $where[] = "({$toIn} OR {$fromIn})";
            $params = array_merge($params, $toParams, $fromParams);
        }
    }
    if ($statusFilter !== '') {
        $where[] = 'r.status = ?';
        $params[] = $statusFilter;
    }

    return ['where' => $where, 'params' => $params];
}

function referrals_list_count(string $statusFilter = '', ?array $user = null): int
{
    $built = referrals_list_where($statusFilter, $user);
    $sql = 'SELECT COUNT(*) FROM `tblreferrals` r
            JOIN `tblcorrespondence` c ON c.id = r.correspondence_id
            WHERE ' . implode(' AND ', $built['where']);
    $stmt = db()->prepare($sql);
    $stmt->execute($built['params']);

    return (int) $stmt->fetchColumn();
}

function referrals_list(string $statusFilter = '', ?array $user = null, ?int $limit = null, ?int $offset = null): array
{
    $built = referrals_list_where($statusFilter, $user);
    $sql = "SELECT r.*, c.ref_number, c.type, c.subject,
                   fd.name AS from_dept_name, fd.name_en AS from_dept_name_en,
                   fd.title_prefix AS from_dept_title_prefix, fd.directed_from AS from_dept_directed_from,
                   td.name AS to_dept_name, td.name_en AS to_dept_name_en,
                   td.title_prefix AS to_dept_title_prefix, td.directed_from AS to_dept_directed_from,
                   " . user_name_sql('u', 'from_user_name') . "
            FROM `tblreferrals` r
            JOIN `tblcorrespondence` c ON c.id = r.correspondence_id
            LEFT JOIN `tbldepartments` fd ON fd.id = r.from_department_id
            LEFT JOIN `tbldepartments` td ON td.id = r.to_department_id
            LEFT JOIN `tblusers` u ON u.id = r.from_user_id
            WHERE " . implode(' AND ', $built['where']) . "
            ORDER BY r.created_at DESC";

    if ($limit !== null) {
        $sql .= ' LIMIT ' . max(1, (int) $limit) . ' OFFSET ' . max(0, (int) ($offset ?? 0));
    }

    $stmt = db()->prepare($sql);
    $stmt->execute($built['params']);

    return array_map('correspondence_apply_ref_display', $stmt->fetchAll());
}

/**
 * @return array{rows:list<array<string,mixed>>,pagination:array<string,mixed>,total:int}
 */
function referrals_list_page(string $statusFilter = '', ?array $user = null, int $page = 1, ?int $perPage = null): array
{
    $perPage = $perPage ?? dms_list_per_page();
    $total = referrals_list_count($statusFilter, $user);
    $pagination = dms_pagination_meta($total, $page, $perPage);
    $rows = referrals_list($statusFilter, $user, $pagination['per_page'], $pagination['offset']);

    return [
        'rows' => $rows,
        'pagination' => $pagination,
        'total' => $total,
    ];
}

/**
 * @return array{where:list<string>,params:list<mixed>}
 */
function documents_list_where(string $q = '', ?array $user = null): array
{
    $user = $user ?? current_user();
    $where = ['1=1'];
    $params = [];

    if (function_exists('user_needs_department_scope') && user_needs_department_scope($user)) {
        [$scopeSql, $scopeParams] = department_scope_sql($user, 'c');
        if ($scopeSql !== '') {
            $where[] = $scopeSql;
            foreach ($scopeParams as $p) {
                $params[] = $p;
            }
        }
    }
    if ($q !== '') {
        $where[] = '(d.file_name LIKE ? OR c.ref_number LIKE ? OR c.subject LIKE ?)';
        $like = '%' . $q . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    return ['where' => $where, 'params' => $params];
}

function documents_list_count(string $q = '', ?array $user = null): int
{
    $built = documents_list_where($q, $user);
    $sql = 'SELECT COUNT(*) FROM `tbldocuments` d
            JOIN `tblcorrespondence` c ON c.id = d.correspondence_id
            WHERE ' . implode(' AND ', $built['where']);
    $stmt = db()->prepare($sql);
    $stmt->execute($built['params']);

    return (int) $stmt->fetchColumn();
}

function documents_list(string $q = '', ?array $user = null, ?int $limit = null, ?int $offset = null): array
{
    $built = documents_list_where($q, $user);
    $sql = "SELECT d.*, c.ref_number, c.type, c.subject, c.confidentiality, " . user_name_sql('u', 'uploader_name') . "
            FROM `tbldocuments` d
            JOIN `tblcorrespondence` c ON c.id = d.correspondence_id
            LEFT JOIN `tblusers` u ON u.id = d.uploaded_by
            WHERE " . implode(' AND ', $built['where']) . "
            ORDER BY d.uploaded_at DESC";

    if ($limit !== null) {
        $sql .= ' LIMIT ' . max(1, (int) $limit) . ' OFFSET ' . max(0, (int) ($offset ?? 0));
    }

    $stmt = db()->prepare($sql);
    $stmt->execute($built['params']);

    return array_map('correspondence_apply_ref_display', $stmt->fetchAll());
}

/**
 * @return array{rows:list<array<string,mixed>>,pagination:array<string,mixed>,total:int}
 */
function documents_list_page(string $q = '', ?array $user = null, int $page = 1, ?int $perPage = null): array
{
    $perPage = $perPage ?? dms_list_per_page();
    $total = documents_list_count($q, $user);
    $pagination = dms_pagination_meta($total, $page, $perPage);
    $rows = documents_list($q, $user, $pagination['per_page'], $pagination['offset']);

    return [
        'rows' => $rows,
        'pagination' => $pagination,
        'total' => $total,
    ];
}
