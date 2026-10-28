# أماكن حفظ الملفات المرفوعة

الجذر على القرص (نسبياً من مجلد المشروع `dms/`):

```text
assets/uploads/
```

المسار الكامل محلياً غالباً:

```text
C:\xampp\htdocs\dms\assets\uploads\
```

معظم المجلدات تُقسَّم حسب السنة/الشهر: `YYYY/MM` (مثال: `2026/09`).

---

## ملخص سريع

| النوع | المجلد | جدول المسار في قاعدة البيانات |
|--------|---------|--------------------------------|
| وارد | `assets/uploads/incoming/YYYY/MM/` | `tbldocuments.file_path` |
| صادر | `assets/uploads/outgoing/YYYY/MM/` | `tbldocuments.file_path` |
| معاملة داخلية | `assets/uploads/internal/YYYY/MM/` | `tbldocuments.file_path` |
| مرفقات إقفال المعاملة | نفس مجلد نوع المعاملة أعلاه | `tbldocuments` + عمود `doc_kind` |
| تعاميم | `assets/uploads/circulars/YYYY/MM/` | `tblcircular_files.file_path` |
| أرشيف الوثائق | `assets/uploads/archive/YYYY/MM/` | `tblarchive_files.file_path` |
| سند استلام الصادر | `assets/uploads/receipts/YYYY/MM/` | `tblcorrespondence.receipt_file_path` |
| صور المستخدمين | `assets/uploads/avatars/` | `tblusers.photo_path` |
| شعار / تواقيع الجهة | `assets/uploads/branding/` | `tblsettings` / إعدادات الأقسام |

عند **إعادة ضبط النظام** (`admin/reset_all.php`) تُمسح مجلدات العمل التشغيلية ويُعاد إنشاؤها، مع **الإبقاء** على `branding/` و `avatars/`.

---

## 1) معاملات الوارد

| البند | القيمة |
|--------|---------|
| المجلد | `assets/uploads/incoming/YYYY/MM/` |
| الأنواع المسموحة | PDF، JPG، JPEG، PNG، GIF، WEBP |
| اسم الملف | مشتق من رقم المعاملة (يُستبدل غير المسموح بـ `-`)؛ عند التكرار يُضاف `_2`, `_3`… |

**أمثلة:**

```text
assets/uploads/incoming/2026/09/و-2026-000012.pdf
assets/uploads/incoming/2026/09/و-2026-000012_2.jpg
assets/uploads/incoming/2026/09/IN-2026-45.png
```

---

## 2) معاملات الصادر

| البند | القيمة |
|--------|---------|
| المجلد | `assets/uploads/outgoing/YYYY/MM/` |
| الأنواع | نفس مرفقات الوارد (PDF / صور) |
| اسم الملف | من رقم الصادر بنفس قاعدة الـ slug |

**أمثلة:**

```text
assets/uploads/outgoing/2026/09/ص-2026-000088.pdf
assets/uploads/outgoing/2026/09/ص-2026-000088_2.pdf
```

---

## 3) المعاملات الداخلية

| البند | القيمة |
|--------|---------|
| المجلد | `assets/uploads/internal/YYYY/MM/` |
| الأنواع | PDF / صور |
| اسم الملف | من رقم المعاملة الداخلية (بادئة الترقيم الافتراضية `د`) |

**أمثلة:**

```text
assets/uploads/internal/2026/09/د-2026-000003.pdf
assets/uploads/internal/2026/09/د-2026-000003.webp
```

---

## 4) مرفقات إقفال المعاملة

تُحفظ في **نفس مجلد نوع المعاملة** (وارد / صادر / داخلية)، وليست في مجلد منفصل.

| البند | القيمة |
|--------|---------|
| المجلد | `incoming` أو `outgoing` أو `internal` حسب نوع المعاملة |
| التمييز | عمود `tbldocuments.doc_kind` |
| أنواع `doc_kind` | `close_final` (الحالي)، وقديماً: `close_decision`, `close_letter`, `close_proof`, `close_other` |
| اسم الملف | رقم المعاملة + `_` + نوع الإقفال |

**أمثلة:**

```text
assets/uploads/incoming/2026/09/و-2026-000012_close_final.pdf
assets/uploads/outgoing/2026/09/ص-2026-000088_close_final.pdf
assets/uploads/internal/2026/09/د-2026-000003_close_decision.pdf
```

---

## 5) التعاميم

| البند | القيمة |
|--------|---------|
| المجلد | `assets/uploads/circulars/YYYY/MM/` |
| الأنواع | PDF / صور |
| اسم الملف | `circ_{id}_{timestamp}_{random}.{ext}` |
| الاسم الأصلي | يُحفظ في `tblcircular_files.file_name` للعرض |

**أمثلة:**

```text
assets/uploads/circulars/2026/09/circ_15_1757418600_a1b2c3d4.pdf
assets/uploads/circulars/2026/09/circ_15_1757418612_9f0e1d2c.jpg
```

---

## 6) أرشيف الوثائق

| البند | القيمة |
|--------|---------|
| المجلد | `assets/uploads/archive/YYYY/MM/` |
| الأنواع | PDF / صور |
| اسم الملف | `arch_{archiveId}_{timestamp}_{random}.{ext}` |
| الاسم الأصلي | في `tblarchive_files.file_name` |

**أمثلة:**

```text
assets/uploads/archive/2026/09/arch_42_1757419000_1122aabb.pdf
assets/uploads/archive/2026/09/arch_42_1757419011_ccddee11.png
```

---

## 7) سند استلام الصادر (إيصال)

| البند | القيمة |
|--------|---------|
| المجلد | `assets/uploads/receipts/YYYY/MM/` |
| الأنواع | PDF / صور |
| اسم الملف | `receipt_{corrId}_{timestamp}_{random}.{ext}` |
| المسار في DB | `tblcorrespondence.receipt_file_path` |

**أمثلة:**

```text
assets/uploads/receipts/2026/09/receipt_88_1757419200_abc123.pdf
assets/uploads/receipts/2026/09/receipt_88_1757419210_def456.jpg
```

---

## 8) صور المستخدمين (الملف الشخصي)

| البند | القيمة |
|--------|---------|
| المجلد | `assets/uploads/avatars/` (**بدون** تقسيم سنة/شهر) |
| الأنواع | صور فقط (JPEG / PNG / GIF / WEBP) — حد الحجم تقريباً 3 ميجا |
| اسم الملف | من الاسم الإنجليزي (`name_en`) بصيغة `First-Last.ext`؛ عند التعارض يُضاف `-{id}` |

**أمثلة:**

```text
assets/uploads/avatars/Abdulrahman.jpg
assets/uploads/avatars/Zafer-Alrasheed.jpg
assets/uploads/avatars/Adel-Joudeh.webp
```

المسار النسبي يُخزَّن في `tblusers.photo_path`.

---

## 9) شعار الجهة والتواقيع (مرجع)

| البند | القيمة |
|--------|---------|
| المجلد | `assets/uploads/branding/` |
| أمثلة | `logo_….png`, ملفات توقيع الأقسام |

لا تُحذف عند إعادة ضبط بيانات العمل.

---

## شجرة مثال بعد الاستخدام

```text
assets/uploads/
├── avatars/
│   └── Abdulrahman.jpg
├── branding/
│   └── logo_1785913336.png
├── incoming/
│   └── 2026/09/
│       ├── و-2026-000012.pdf
│       └── و-2026-000012_close_final.pdf
├── outgoing/
│   └── 2026/09/
│       └── ص-2026-000088.pdf
├── internal/
│   └── 2026/09/
│       └── د-2026-000003.pdf
├── circulars/
│   └── 2026/09/
│       └── circ_15_1757418600_a1b2c3d4.pdf
├── archive/
│   └── 2026/09/
│       └── arch_42_1757419000_1122aabb.pdf
└── receipts/
    └── 2026/09/
        └── receipt_88_1757419200_abc123.pdf
```

---

## ملاحظات تشغيل

1. المسارات في قاعدة البيانات **نسبية من جذر المشروع** (`assets/uploads/...`) وليست مطلقة.
2. العرض/التحميل يتم عبر صفحات النظام (مع فحص الصلاحيات)، وليس بفتح المجلد مباشرة من المتصفح إن وُجدت حماية.
3. تأكد أن مستخدم Apache/PHP يملك صلاحية الكتابة على `assets/uploads` وكل المجلدات الفرعية.
4. الكود المرجعي:
   - مرفقات المعاملات: `config/helpers.php` → `upload_document()`, `correspondence_attachment_folder()`
   - التعاميم: `config/circulars.php`
   - الأرشيف: `config/archive.php`
   - الإيصالات: `config/helpers.php` (تسجيل الاستلام)
   - الصور: `upload_user_photo()` في `config/helpers.php`
   - إعادة الضبط: `config/operational_reset.php` → `dms_wipe_operational_upload_dirs()`
