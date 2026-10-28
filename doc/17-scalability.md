# 17 — التحمّل والأداء عند كثرة البيانات

كيف يتصرّف النظام حين تصير الجهات بالآلاف والمعاملات بعشرات الآلاف، وما القواعد التي يجب الالتزام بها عند إضافة أي قائمة جديدة.

## القاعدة الأساسية

> **لا تجلب كل الصفوف إلى PHP أبداً.** كل قائمة تُجزَّأ من الخادم، وكل بحث يُنفَّذ في SQL لا في المتصفح.

المخالفة الشائعة التي أُصلحت: دالة قائمة بلا `LIMIT` تُستدعى في صفحة تُفتح يومياً، فيتضخّم الوقت وحجم الصفحة طردياً مع نمو الجدول.

---

## 1. الأدوات الجاهزة للتجزئة

موجودة في `config/helpers.php` ويجب استخدامها بدل كتابة تجزئة جديدة:

| الدالة | الغرض |
|--------|--------|
| `dms_list_per_page()` | عدد الصفوف في الصفحة (20) |
| `dms_request_page($param = 'page')` | رقم الصفحة من `$_GET` |
| `dms_pagination_meta($total, $page, $perPage)` | يحسب `offset` و`pages` و`from`/`to` |
| `dms_render_pagination($meta, $pageParam)` | شريط أرقام الصفحات |
| `dms_pagination_summary($meta)` | «عرض 1–20 من 340» |
| `dms_list_page_scripts()` | يُدرج `assets/js/list-pagination.js` |

### النمط المتّبع في أي قائمة

ثلاث دوال لكل مورد، تشترك في بانٍ واحد لشروط `WHERE` حتى لا يفترق العدّ عن الجلب:

```php
function xxx_list_where(...): array   // ['where' => [...], 'params' => [...]]
function xxx_list_count(...): int
function xxx_list(..., ?int $limit = null, ?int $offset = null): array
function xxx_list_page(..., int $page = 1, ?int $perPage = null): array
```

أمثلة قائمة: `entities_list_*`، `correspondence_list_*`، `documents_list_*`، `referrals_list_*`، `archive_list_*`.

### التجزئة داخل الصفحة دون إعادة تحميل

غلّف القائمة بهذا الهيكل ليعمل `list-pagination.js`:

```html
<div class="card-panel" id="list-main" data-list-panel>
    <div class="list-panel-body">
        <!-- الجدول -->
        <?php $pageParam = 'page'; require __DIR__ . '/../includes/list_pagination.php'; ?>
    </div>
</div>
```

> السكربت يستبدل `innerHTML` للوحة، فيفقد المحتوى الجديد تلميحات Bootstrap. لذلك يُعيد `initTooltips()` تهيئتها بعد كل استبدال. أي مكوّن JS آخر داخل اللوحة يحتاج المعالجة نفسها.

---

## 2. الترقيم في القوائم المجزّأة

عمود «#» يُولَّد بعدّاد CSS لا من PHP:

```css
.entities-table tbody { counter-reset: entities-row; }
.entities-table tbody tr { counter-increment: entities-row; }
.entities-table tbody td.entities-col-num::before { content: counter(entities-row); }
```

وتُضبط بداية العدّاد من إزاحة الصفحة:

```php
<tbody style="counter-reset: entities-row <?= (int) $pagination['offset'] ?>;">
```

**لماذا لا يُستخدم `id` بدل العدّاد؟** لأن `id` يصير متقطّعاً بعد أي حذف (1، 2، 5، 9)، وترتيب العرض لا يطابقه أصلاً عند الفرز. و`id` موجود ومستقر لمن يحتاج مرجعاً ثابتاً، فلا داعي لعمود «كود» إضافي في الجدول.

---

## 3. جهات المراسلة

### قائمة الإدارة `admin/entities.php`

- تعرض 20 جهة في الصفحة عبر `entities_list_page()`.
- البحث من الخادم: يُرسل في `?q=` بعد توقّف الكتابة بـ 450 مللي ثانية، ويشمل الاسم والوظيفة والجوال والبريد والعنوان والملاحظات واسم القسم.
- `q` جزء من مصفوفة المرشّحات، لذلك يحترمه **التصدير والطباعة** تلقائياً عبر `entities_filter_query_string()`.

### القائمة المنبثقة لاختيار الجهة

في `includes/corr_form_fields.php`، تُستخدم في إنشاء وتعديل الصادر والوارد.

- **لا تُحمَّل أي جهة مع الصفحة.** الجدول فارغ عند تحميل النموذج.
- عند فتح القائمة أو الكتابة فيها تُجلب أول 25 نتيجة من `api/entities_search.php`.
- الجهة المختارة وحدها تُجلب من الخادم عبر `get_entity()` لعرض اسمها في الحقل.
- إن تجاوز إجمالي النتائج المعروض، يظهر تنبيه «تابع الكتابة لتضييق البحث».

### نقطة البحث `api/entities_search.php`

| المعامل | الغرض |
|---------|--------|
| `q` | نص البحث (حد أقصى 100 حرف) |
| `type` | `incoming` أو `outgoing` — يحدّد اسم الطرف المُعاد |
| `include_id` | يضمن ظهور الجهة المختارة حتى لو خرجت من نطاق القسم |

تتطلّب تسجيل دخول، وتُرجع `{ok, total, limit, items[]}`.

**مصدر واحد للعرض:** الدالة `entity_picker_payload($row, $isIncoming)` في `config/helpers.php` تبني بيانات الصف، وتستخدمها الصفحة ونقطة البحث معاً، فلا يفترق شكل العرض بين الجهتين.

### فخّ تجنّبه

كانت خمس صفحات (`outgoing/create.php`، `outgoing/edit.php`، `outgoing/bulk.php`، `incoming/create.php`، `incoming/edit.php`) تستدعي `entities_list()` بلا حاجة بعد تحويل القائمة إلى AJAX. حُذف الاستدعاء. **عند تعديل نموذج مراسلة، لا تُعِد تمرير `$entities` إليه.**

---

## 4. المعاملات (الصادر والوارد)

القوائم مجزّأة من الخادم أصلاً عبر `correspondence_list_page()`. التحسينات هنا كانت في الاستعلامات لا في التجزئة:

### إحصاءات لوحة التحكم

كانت `correspondence_dashboard_data()` تُنفّذ **سبعة** استعلامات `COUNT` منفصلة، كل واحد يمسح الجدول كاملاً. صارت استعلاماً واحداً بتجميع شرطي:

```sql
SELECT
    COALESCE(SUM(c.type = 'incoming' AND c.corr_date = CURDATE()), 0) AS today_in,
    COALESCE(SUM(c.status IN (...)), 0) AS in_progress,
    ...
FROM `tblcorrespondence` c WHERE 1=1 {scope}
```

> ترتيب المعاملات مهم: معاملات قائمة `SELECT` تسبق معاملات `WHERE`.

### تصدير التقارير

`reports/index.php?export=excel` كان يجلب كل صفوف الفترة بـ `fetchAll()`. صار يبثّها صفاً صفاً مع `flush()` كل 200 صف، فاستهلاك الذاكرة ثابت مهما كبر التقرير.

**القاعدة:** أي تصدير يُبثّ بـ `while ($r = $stmt->fetch())`، ولا يُستعمل `fetchAll()` على استعلام بلا `LIMIT`.

### طباعة التقارير

`reports/print.php` محدود بـ **2000 سجل**، ويظهر في نهاية الجدول سطر يحيل إلى تصدير Excel للقائمة الكاملة. تقرير بعشرات الآلاف من الصفوف غير قابل للطباعة أصلاً.

---

## 5. الفهارس

تُنشأ تلقائياً ولا تحتاج تدخّلاً يدوياً:

| الدالة | الملف |
|--------|--------|
| `dms_ensure_index($table, $name, $definition)` | `config/helpers.php` — يضيف الفهرس إن لم يوجد |
| `ensure_correspondence_indexes()` | تُستدعى من `ensure_correspondence_receipt_schema()` |
| `ensure_entities_schema()` | فهرس الجهات |

### الفهارس المضافة

| الجدول | الفهرس | يخدم |
|--------|--------|--------|
| `tblcorrespondence` | `(type, status, corr_date)` | تصفية الحالة والفترة داخل نوع واحد |
| `tblcorrespondence` | `(department_id, type)` | القوائم المحصورة بقسم المستخدم |
| `tblcorrespondence` | `(reply_due, status)` | المتأخرة والمستحقة قريباً |
| `tblreferrals` | `(correspondence_id, to_department_id)` | شرط `EXISTS` في `department_scope_sql()` |
| `tblentities` | `(is_active, name_ar(100))` | تصفية وفرز قائمة الجهات |

### قاعدة مهمة عند إضافة فهرس جديد

**InnoDB يُلحق المفتاح الأساسي بكل فهرس ثانوي تلقائياً.** أي أن `idx_type (type)` يعمل فعلياً كـ `(type, id)` ويغطي `ORDER BY id DESC` دون فرز إضافي.

لذلك **لا تُنشئ** فهرساً مثل `(type, id)` أو `(corr_date, id)` — فهو نسخة مكرّرة تُكلّف عند كل إدخال بلا أي مقابل. تحقّق دائماً بـ:

```sql
EXPLAIN SELECT ... ;   -- راجع possible_keys و key و Extra
SHOW INDEX FROM tblcorrespondence;
```

---

## 6. ما لم يُنفَّذ عمداً

**البحث النصي** في المعاملات يستخدم `LIKE '%كلمة%'` على `ref_number` و`subject` و`party_name` و`external_ref`. هذا النمط لا يستفيد من فهارس B-tree بطبيعته لأن البداية غير محدّدة.

- حتى عشرات الآلاف من السجلات: مقبول (عشرات الأجزاء من الثانية).
- عند مئات الآلاف: يصير محسوساً، والحل فهرس `FULLTEXT` مع `MATCH … AGAINST`.

لم يُنفَّذ الآن لأن سلوك `FULLTEXT` مع العربية يختلف عن `LIKE` في تجزئة الكلمات والحد الأدنى لطول الكلمة، ويحتاج اختباراً على بيانات حقيقية قبل اعتماده.

---

## 7. قائمة تحقّق قبل إضافة أي قائمة جديدة

- [ ] هل الاستعلام فيه `LIMIT`؟
- [ ] هل البحث يُنفَّذ في SQL لا في JavaScript؟
- [ ] هل شرط `WHERE` مشترك بين دالة العدّ ودالة الجلب؟
- [ ] هل أعمدة `WHERE` و`ORDER BY` مفهرسة؟ وهل الفهرس غير مكرّر مع فهرس قائم؟
- [ ] هل التصدير يبثّ الصفوف بدل `fetchAll()`؟
- [ ] هل للطباعة سقف معقول؟
- [ ] هل اللوحة ملفوفة بـ `data-list-panel` و`.list-panel-body`؟
- [ ] هل مكوّنات JS داخل اللوحة تُعاد تهيئتها بعد تبديل الصفحة؟
