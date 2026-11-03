# 03 — إعدادات الجهة والنظام

الصفحة: `admin/settings.php`  
الصلاحية: `admin` فقط

## أقسام الصفحة

### 1) بيانات الجهة

| الحقل | مفتاح الإعداد | ملاحظات |
|--------|----------------|---------|
| اسم الجهة (عربي) | `org_name_ar` (+ مرادف `org_name`) | مطلوب |
| اسم الجهة (English) | `org_name_en` | اختياري |
| الهاتف | `org_phone` | |
| البريد | `org_email` | |
| العنوان (عربي) | `org_address` | |
| العنوان (English) | `org_address_en` | يُعرض في المعاينة والطباعة |

الدوال المساعدة: `org_name_ar()`, `org_name_en()`, `org_phone()`, `org_email()`, `org_address()`, `org_address_en()`.

### 2) عرض اسم الجهة

مفاتيح منفصلة لكل لغة × كل مكان:

| المكان | عربي | إنجليزي |
|--------|------|---------|
| الصفحات (هيدر) | `show_org_name_ar_pages` | `show_org_name_en_pages` |
| صفحة الدخول | `show_org_name_ar_login` | `show_org_name_en_login` |
| القائمة الجانبية | `show_org_name_ar_sidebar` | `show_org_name_en_sidebar` |
| التقارير والطباعة | `show_org_name_ar_print` | `show_org_name_en_print` |

القيم: `1` = إظهار، `0` = إخفاء.

الدوال: `org_show_name_lang($lang, $context)`, `org_visible_names($context)`, `render_org_brand_text(...)`.

مجموعة «الدخول» تشمل: `login.php`, `forgot_password.php`, `reset_password.php`.

### 3) الترقيم

| الحقل | المفتاح | افتراضي شائع |
|--------|---------|----------------|
| بادئة الوارد | `incoming_prefix` | `و` أو `IN` |
| بادئة الصادر | `outgoing_prefix` | `ص` أو `OUT` |

> **رابط النظام غير موجود في الواجهة** — يُكتشف تلقائياً (انظر [04-auto-base-url.md](04-auto-base-url.md)).

### 4) أمان الجلسة

| الحقل | المفتاح | الافتراضي | النطاق |
|--------|---------|-----------|--------|
| مدة الخمول (دقائق) | `session_timeout_minutes` | `30` | 5–1440 |

التفاصيل في [05-session-timeout.md](05-session-timeout.md).

### 5) الشعار والمعاينة

- رفع الشعار إلى `assets/uploads/organization/`
- المفتاح: `org_logo`
- **المعاينة السريعة:**
  1. الشعار في المنتصف
  2. الاسم عربي / إنجليزي في المنتصف
  3. الهاتف والبريد في سطر واحد مفصول بـ `|`
  4. العنوان عربي ثم إنجليزي في المنتصف

## إعدادات البريد

صفحة منفصلة: `admin/email_settings.php`  
يمكن استيراد SMTP من نظام الدعوات إن وُجد على نفس السيرفر. التفاصيل: [11-email-whatsapp.md](11-email-whatsapp.md).

## إعدادات السرية

صفحة منفصلة ضمن محور الإعدادات: `admin/confidential_settings.php` (صلاحية `admin.settings`).

| التحكم | المفتاح | المعنى |
|--------|---------|--------|
| تقييد رؤية السري | `confidentiality_restricts_visibility` | إخفاء المعاملة/الأرشيف السري عن غير المخوّلين (افتراضي مفعّل) |
| مرفقات حسب الدور | `conf_attach_prep_signer` · `reviewer` · `approver` · `comms` · `cc` | من يفتح الملفات السرية أثناء دوره في المسار |
| تحييد القنوات الخارجية | `conf_external_notify_mode` = `redacted` / `full` | بريد/واتساب/إشعارات بلا موضوع كامل ولا مرفقات سرية |

تجاوز لكل معاملة/وثيقة من زر القفل بجانب حقل السرية في النماذج. المرجع الكامل: [25-confidential-correspondence.md](25-confidential-correspondence.md).

دورة الاعتماد والمراجعون/المعتمدون تُضبط من تبويبات المسار في نفس صفحة الإعدادات — انظر [24-approval-workflow.md](24-approval-workflow.md).

## المساعدة داخل النظام

مدير النظام: القائمة الجانبية ← **المساعدة** (`admin/help.php`) — فهرس موجّه للتشغيل من مجلد `doc/`.

## أوصاف الإعدادات في الجدول

عمود `desc` في `tblsettings` يُملأ عربياً عبر `ensure_settings_schema()` و`settings_desc_ar_map()` في `config/helpers.php`.
