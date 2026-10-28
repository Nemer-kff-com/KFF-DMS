# توثيق نظام الصادر والوارد (DMS)

**مجلد توثيق موحّد** — دُمج سابقاً `doc/` و `Docs/` في مكان واحد.

لا يُفتح من المتصفح (`doc/.htaccess` يمنع الوصول المباشر).

---

## فهرس الوثائق

### أساسيات النظام

| الملف | المحتوى |
|--------|---------|
| [00-readme.md](00-readme.md) | بدء سريع: المتطلبات، التثبيت، الوحدات، الأدوار |
| [01-overview.md](01-overview.md) | نظرة عامة، التقنيات، هيكل المجلدات، دورة العمل |
| [02-installation.md](02-installation.md) | التثبيت والنشر على السيرفر |
| [03-settings.md](03-settings.md) | إعدادات الجهة والعرض والعنوان |
| [04-auto-base-url.md](04-auto-base-url.md) | اكتشاف رابط النظام تلقائياً |
| [05-session-timeout.md](05-session-timeout.md) | الخروج التلقائي بعد الخمول |
| [06-ui-theme.md](06-ui-theme.md) | الواجهة، الثيم، القائمة، SweetAlert |

### قاعدة البيانات والأمان

| الملف | المحتوى |
|--------|---------|
| [07-database.md](07-database.md) | الجداول، `tblsettings`، الإرسال، الأمان |
| [08-security.md](08-security.md) | الأمان والجلسات والسجلات |
| [09-outgoing-receipt.md](09-outgoing-receipt.md) | استلام الصادر وسند التسليم (اسم المستلم، التاريخ) |

### التشغيل اليومي

| الملف | المحتوى |
|--------|---------|
| [10-entities.md](10-entities.md) | جهات المراسلة: الجوال، البريد، التكريم |
| [11-email-whatsapp.md](11-email-whatsapp.md) | إعدادات البريد وواتساب |
| [12-sending.md](12-sending.md) | إرسال الخطاب من الصادر المفرد والجماعي |
| [13-rsvp.md](13-rsvp.md) | تأكيد الحضور أو الاعتذار |
| [22-actions-queue.md](22-actions-queue.md) | يحتاج إجراء: معنى كل قائمة وكيف تُغلق المعاملة |
| [24-approval-workflow.md](24-approval-workflow.md) | دورة المراجعة والاعتماد: المراحل، التصعيد، فصل الصلاحيات، قفل التعديل، تدخّل مدير النظام ونقل المعاملة |

### للمستخدم النهائي

| الملف | المحتوى |
|--------|---------|
| [19-user-guide.md](19-user-guide.md) | دليل المستخدم الشامل (دخول، وارد/صادر/داخلي، ملصق ظرف، تقارير…) |
| [user-guide.html](user-guide.html) | نفس الدليل بصيغة HTML للطباعة / PDF وإرساله للمستخدمين |

### الإدارة والصلاحيات

| الملف | المحتوى |
|--------|---------|
| [14-roles-and-permissions-guide.md](14-roles-and-permissions-guide.md) | دليل الأدوار والصلاحيات (أرشيف، قسم، مدير، مشاهد) |
| [15-backup.md](15-backup.md) | النسخ الاحتياطي SQL/ZIP، Cron، OneDrive |
| [16-users-and-login.md](16-users-and-login.md) | المستخدمون: البريد للدخول، name_ar / name_en |
| [17-scalability.md](17-scalability.md) | التحمّل عند كثرة البيانات: التجزئة، البحث من الخادم، الفهارس |
| [18-coding-standards.md](18-coding-standards.md) | معايير التطوير: أسماء الجداول، القوائم، الطباعة |
| [20-upload-storage.md](20-upload-storage.md) | أماكن حفظ الملفات: وارد/صادر/داخلية/تعاميم/أرشيف/إقفال/صور المستخدمين مع أمثلة |
| [21-signing-office.md](21-signing-office.md) | ملاحظة: معنى «مكتب توقيع» وكيفية ضبطه في الأقسام |
| [23-transaction-cycles-proposal.html](23-transaction-cycles-proposal.html) | مقترح للنقاش: مصفوفة الصلاحيات ودورة الصادر والوارد والمعاملة الداخلية (للطباعة) |

### سجل التغييرات

| الملف | المحتوى |
|--------|---------|
| [CHANGELOG.md](CHANGELOG.md) | سجل ما تم بناؤه وتعلمه |

---

## روابط سريعة

| الوظيفة | الرابط |
|---------|--------|
| النظام | `http://localhost/dms` |
| الإعدادات | `admin/settings.php` |
| النسخ الاحتياطي | `admin/backup.php` |
| المستخدمون | `admin/users.php` |
| الجهات | `admin/entities.php` |
| إعدادات البريد | `admin/email_settings.php` |
| إعدادات واتساب | `admin/whatsapp_settings.php` |
| الصادر الجماعي | `outgoing/bulk.php` |
| التحقق QR | `verify.php?token=...` |

---

## قبل إضافة أي قائمة أو تقرير جديد

راجع [17-scalability.md](17-scalability.md) — فيه قائمة تحقّق تمنع الأخطاء التي تظهر فقط بعد تضخّم البيانات.

---

## ملاحظة عن مجلد `Docs/` القديم

تم **إلغاء التكرار**. إن وُجد مجلد `Docs/` في الجذر فهو يحيل فقط إلى هذا المجلد — **المرجع الرسمي: `doc/`**.
