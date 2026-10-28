# 08 — الأمان والجلسات

## المصادقة

- الملف: `config/auth.php`
- الجلسة تبدأ مبكراً مع تحميل المساعدات والأمان
- `require_login()` للصفحات العامة بعد الدخول
- `require_role([...])` لصفحات الإدارة

## تتبع الجلسات

- عند الدخول الناجح: `register_user_session($user)`
- عند كل طلب محمي: `touch_user_session()` يحدّث `last_activity` في `tbluser_sessions`
- المدير يمكنه إنهاء جلسة عبر `tblsession_kills` → عند الطلب التالي يُخرج المستخدم برسالة `sessions.forced_logout`

صفحة الإدارة: `admin/active_sessions.php`

## مهلة الخمول

موثّقة بالكامل في [05-session-timeout.md](05-session-timeout.md).

حدث السجل الأمني عند المهلة: `SESSION_TIMEOUT`.

## استعادة كلمة المرور

- الصفحات: `auth/forgot_password.php`, `auth/reset_password.php`
- تعتمد على إعدادات SMTP في `admin/email_settings.php`
- المنطق: `config/password_reset.php`, `config/mailer.php`

## رؤوس وممارسات عامة

- CSRF عبر `csrf_field()` / `verify_csrf()` في النماذج، مع قبول هيدر `X-CSRF-Token` فقط (لا من query string).
- ملفات الجلسة داخل `sessions/` (غير قابل للوصول عبر الويب).
- المجلدات الحساسة محجوبة في `.htaccess` الجذري وداخل المجلد: `config/` `sql/` `doc/` `var/` `logs/` `includes/` `lang/` `sessions/` `storage/` `tools/` `lib/`. مجلدات التطبيق (`admin/` `incoming/` …) تبقى مفتوحة لأنها صفحات النظام، مع منع تصفح الفهارس من الجذر.
- المرفقات (وارد/صادر/أرشيف/سندات) لا تُفتح برابط مباشر؛ التحميل عبر `files/download.php` بعد تسجيل الدخول وفحص صلاحية القسم.
- الشعار وصور الملف الشخصي تبقى عامة من `assets/uploads/organization` و `users`.
- صفحات عامة بدون دخول: `auth/login.php`, `forgot_password.php`, `reset_password.php`, `verify.php`, `api/qr.php` (مختصر).
- مجلد `install/` أُزيل من المشروع بعد الإعداد؛ النسخة الاحتياطية في `C:\backup\dms-install` (خارج webroot). إن أُعيد مؤقتاً للتثبيت: يتطلب Host محلي و`REMOTE_ADDR` حلقي ويُقفل بـ `var/install.lock`.
- عنوان العميل للحد من المحاولات يعتمد على `REMOTE_ADDR`؛ تفعيل ترويسات البروكسي فقط عبر `DMS_TRUST_PROXY=1`.
- رموز إعادة تعيين كلمة المرور تُخزَّن كـ SHA-256؛ تغيير/إعادة التعيين يُنهي جلسات المستخدم.
- كلمة المرور: 8 أحرف كحد أدنى.
- معاملات/أرشيف بلا قسم (`department_id` فارغ) لا تُعرض لمستخدمي الأقسام المحدودين.
