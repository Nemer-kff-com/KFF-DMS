# معايير التطوير (DMS)

نُقلت من قواعد Cursor السابقة (`.cursor/rules`) إلى التوثيق الرسمي.

---

## 1) أسماء جداول MySQL بحروف صغيرة (HostGator)

على **Linux / HostGator** أسماء الجداول حسّاسة لحالة الأحرف. Windows/XAMPP قد يخفي الخطأ.

### مطلوب

- **كل أسماء الجداول:** حروف صغيرة بالكامل بعد `tbl`، مثل `tblcorrespondence`، `tblcirculars`، `tblusers`
- **المرجع:** `DmsDbTable::*` في `config/db_tables.php`
- **CREATE TABLE / ALTER / SQL:** معرفات صغيرة فقط
- **فحص الوجود:** `dms_db_table_exists()` / `dms_db_column_exists()` — لا تستخدم `SHOW TABLES LIKE 'tbl…'`
- **جدول جديد:** أضف ثابتاً إلى `DmsDbTable` في نفس التغيير

### ممنوع

- `tblCorrespondence`، `tblUsers`، `tblCirculars`، أو أي `tbl…` بحروف داخلية كبيرة
- الاعتماد على عدم حساسية Windows أثناء الاختبار المحلي
- افتراض أن مسار المجلد دائماً `/dms` فقط — على Linux قد يكون `/DMS`

### أسماء الأعمدة

أسماء الأعمدة القديمة قد تبقى MixedCase — **المطلوب صغيراً هو اسم الجدول فقط**.

### مساعدات

- `dms_db_table_name($table)` — تطبيع قبل SHOW TABLES
- `dms_db_table_exists()` / `dms_db_column_exists()` / `dms_sql_table()`
- `dms_db_normalize_table_names()` — يعمل من `ensure_core_tables()`

---

## 2) القوائم والبحث والأداء

التوثيق التفصيلي: [17-scalability.md](17-scalability.md)

### القاعدة الأساسية

> **لا تجلب كل صفوف جدول إلى PHP.** كل قائمة تُجزَّأ من الخادم، وكل بحث يُنفَّذ في SQL لا في المتصفح.

ممنوع:

- استعلام قائمة بلا `LIMIT`
- `fetchAll()` على استعلام غير محدود
- إخفاء صفوف بـ JavaScript كبديل عن البحث
- طباعة `data-search` على كل صف لبحث في المتصفح

### النمط المطلوب لأي مورد جديد

أربع دوال تشترك في بانٍ واحد لشروط `WHERE`:

```php
function xxx_list_where(...): array;                                   // ['where' => [], 'params' => []]
function xxx_list_count(...): int;
function xxx_list(..., ?int $limit = null, ?int $offset = null): array;
function xxx_list_page(..., int $page = 1, ?int $perPage = null): array;
```

قدوة: `entities_list_*` و`correspondence_list_*`.

### في الصفحة

```php
$filters = xxx_list_filters_from_request($user);
$list = xxx_list_page(..., dms_request_page());
$rows = $list['rows'];
$pagination = $list['pagination'];
$pageScripts = dms_list_page_scripts();
```

- حقل البحث `name="q"` داخل نموذج `method="get"`
- لا تضع `page` في النموذج
- اللوحة: `data-list-panel` و`.list-panel-body` + `includes/list_pagination.php`

### التصدير والطباعة

- التصدير: بثّ الصفوف مع `flush()` — لا `fetchAll()`
- الطباعة: سقف معقول مع إحالة لتصدير Excel

### الفهارس

استخدم `dms_ensure_index($table, $name, $definition)`. لا تكرّر أعمدة المفتاح الأساسي بلا داعٍ في الفهارس الثانوية.

---

## 3) طباعة التقارير والمستندات

### التذييل الموحّد — إلزامي

كل صفحة طباعة أو تقرير **يجب** أن تنتهي بـ:

```php
<?php render_print_page_footer(); ?>
```

لا تُنشئ HTML تذييل يدوياً. لا تستدعِ `render_print_contact()` في كود جديد.

### ما يطبع في أسفل كل صفحة

1. **سطر المستخدم** (يسار): `الاسم العربي · YYYY-MM-DD · HH:MM`
2. **سطر المؤسسة** (وسط): `العنوان | الهاتف | البريد`

### هيكل HTML القياسي

```html
<body class="print-page">
<div class="print-sheet">
  <?php render_print_logo('print'); ?>
  <!-- محتوى التقرير -->
  <?php render_print_page_footer(); ?>
</div>
</body>
```

### ورقة المعاملة (وارد/صادر)

الترتيب: الترويسة ← الموضوع ← **بطاقة التوجيه** ← بقية الحقول ← أنموذج الاستلام ← التذييل.

```php
<?php render_print_direction($corr, $swap); ?>
<?php render_print_receipt($corr); ?>
```

- رقم المعاملة تحت QR فقط — لا يتكرر في الجداول
- لا تطبع بدائل للفراغ (`common.none` أو `—`)؛ رشّح الحقول الفارغة
- الأنماط في `assets/css/app.css` — لا تكرّر قواعد `@media print` للتذييل

### صفحات الطباعة الحالية

- `includes/print_correspondence.php`
- `circulars/print.php`، `archive/print.php`
- `reports/print.php`
- `admin/entities_print.php`، `admin/entities_card_print.php`
- `outgoing/batch_print.php`، `outgoing/batch_recipients.php`
- `verify.php` (بدون سطر مستخدم)
