# 07 — قاعدة البيانات ومفاتيح الإعدادات

## اصطلاح التسمية

كل الجداول تبدأ بـ **`tbl`** مباشرة دون شرطة سفلية بعد البادئة، مع الإبقاء على الشرطات داخل الاسم المركب:

| صحيح | خاطئ |
|------|--------|
| `tblusers` | `tbl_users` |
| `tblsettings` | `tbl_settings` |
| `tblactivity_log` | `tblactivitylog` (الاسم المركب يحتفظ بـ `_`) |
| `tbluser_sessions` | |

المخطط الأساسي: `sql/schema.sql`  
هجرات مساعدة قديمة: `sql/migrate_org_settings.sql`

## جدول الإعدادات `tblsettings`

أعمدة أساسية:

- `setting_key`
- `setting_value`
- `desc` — وصف عربي للإعداد (يُحدَّث عبر `ensure_settings_schema()`)

### مفاتيح شائعة

| المفتاح | الغرض |
|---------|--------|
| `org_name` / `org_name_ar` | اسم الجهة عربي |
| `org_name_en` | اسم الجهة إنجليزي |
| `org_phone` / `org_email` | تواصل |
| `org_address` | عنوان عربي |
| `org_address_en` | عنوان إنجليزي |
| `org_logo` | مسار الشعار النسبي |
| `show_org_name_{ar\|en}_{pages\|login\|sidebar\|print}` | إظهار/إخفاء الاسم |
| `incoming_prefix` / `outgoing_prefix` | بادئات الترقيم |
| `session_timeout_minutes` | خمول الجلسة بالدقائق |

> مفتاح `base_url` إن وُجد قديماً في الجدول **لا يُستخدم وقت التشغيل** بعد التحويل للاكتشاف التلقائي.

## جهات المراسلة `tblentities`

- `mobile`: رقم الجوال لإرسال واتساب.
- `email`: البريد لإرسال الخطاب.
- `honorific`: عبارة التكريم (`حفظه الله` / `حفظها الله` …).
- `department_id = NULL`: جهة عامة تظهر لجميع الأقسام.
- `department_id = <id>`: جهة خاصة بالقسم المحدد.
- البيانات التي سبقت إضافة العزل تُرحّل تلقائياً كجهات عامة دون تغيير ارتباط المعاملات القديمة.
- المسؤول والأرشيف يديران جميع الجهات، بينما مستخدم القسم يدير جهات قسمه فقط وتبقى الجهات العامة للقراءة.

## جداول الأمان والجلسات

| الجدول | الغرض |
|--------|--------|
| `tbluser_sessions` | جلسات المستخدمين النشطة (`last_activity`…) |
| `tblsession_kills` | إنهاء جلسة من المدير |
| `tblactivity_log` | سجل نشاط المستخدم |
| `tblsecurity_log` (أو ما يعادله في المخطط) | أحداث أمنية مثل الدخول والمهلة |

إنشاء/ترقية مخطط الأمان: `ensure_security_schema()` في `config/security.php`.

## التهيئة التلقائية

عند التشغيل، `ensure_org_settings()` يضمن وجود المفاتيح الافتراضية إن لم تكن موجودة (بما فيها `session_timeout_minutes = 30` و`org_address_en` …).

---

## جداول الإرسال والمراسلات

الترحيل تلقائي عند فتح النظام (`ensure_entities_schema` و `ensure_corr_deliveries_schema`).

## `tblentities`

| العمود | الوصف |
|--------|--------|
| `directed_to` | الاسم الظاهر في الخطاب |
| `honorific` | التكريم (حفظه الله / حفظها الله / …) |
| `job_title` | الوظيفة |
| `mobile` | الجوال لواتساب |
| `phone` | هاتف اختياري |
| `email` | البريد للإرسال |
| `department_id` | NULL = عامة |

## `tblwhatsapp_settings`

صف واحد (`id = 1`): المزود، التفعيل، رقم المرسل، JSON الإعدادات والقوالب.

## `tblemail_settings`

صف واحد (`id = 1`): حساب SMTP للنظام (نسيان كلمة المرور + احتياطي المراسلات).

## `tblemail_department_settings`

صف لكل قسم يريد الإرسال من صندوقه. إن لم يوجد صف أو لم يُفعَّل، يُستخدم حساب النظام.

| العمود | الوصف |
|--------|--------|
| `department_id` | القسم (فريد) |
| `smtp_*` / `from_*` | نفس حقول حساب النظام |
| `is_active` | إن كان 1 يُفضَّل على حساب النظام عند إرسال صادر هذا القسم |

## `tblcorr_deliveries`

سجل إرسال الصادر.

| العمود | الوصف |
|--------|--------|
| `correspondence_id` | المعاملة |
| `channel` | `email` أو `whatsapp` |
| `recipient` | البريد أو الرقم الدولي |
| `status` | `sent` / `failed` / `skipped` |
| `error_message` | سبب الفشل أو التخطي |
| `provider` | smtp / twilio / meta / custom |
| `sent_by` | المستخدم |
| `created_at` | وقت المحاولة |

## `tblattachment_types` و `tblcorr_attachments`

أنواع المرفقات المادية المرافقة للمعاملة (كتاب، قرص، صور…) مستقلة عن الملفات الإلكترونية في `tbldocuments`.

| الجدول | الغرض |
|--------|--------|
| `tblattachment_types` | قائمة الأنواع (عربي/إنجليزي، رمز، ترتيب، نشط) — تُدار من الإعدادات |
| `tblcorr_attachments` | ربط المعاملة بنوع + كمية. فريد على `(correspondence_id, attachment_type_id)` |

الإنشاء والبذور عبر `ensure_lookups_schema()`. الحفظ عبر `save_correspondence_enclosures()`.

## `tbloutgoing_batches` و `tblcorrespondence.batch_id`

تربط دفعة الصادر الجماعي بمعاملاتها. الإرسال الجماعي يمر على كل صف `type = outgoing` في الدفعة.

---

## الفهارس

تُنشأ تلقائياً عبر `dms_ensure_index()` و`ensure_correspondence_indexes()` في `config/helpers.php`.

| الجدول | الفهرس | يخدم |
|--------|--------|--------|
| `tblcorrespondence` | `(type, status, corr_date)` | تصفية الحالة والفترة داخل نوع واحد |
| `tblcorrespondence` | `(department_id, type)` | القوائم المحصورة بقسم المستخدم |
| `tblcorrespondence` | `(reply_due, status)` | المتأخرة والمستحقة قريباً |
| `tblreferrals` | `(correspondence_id, to_department_id)` | شرط `EXISTS` في `department_scope_sql()` |
| `tblentities` | `(is_active, name_ar(100))` | تصفية وفرز قائمة الجهات |

> **تنبيه:** InnoDB يُلحق المفتاح الأساسي بكل فهرس ثانوي، فـ `idx_type (type)` يعمل فعلياً كـ `(type, id)`. لا تُنشئ فهرساً مثل `(type, id)` — مكرّر ويُكلّف عند كل إدخال.

التفاصيل وقائمة التحقق في [17-scalability.md](17-scalability.md).
