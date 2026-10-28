# 15 — النسخ الاحتياطي

## الهدف

صفحة **النسخ الاحتياطي** (`admin/backup.php`) — منسوخة من نظام الدعوات (`Invitations/Ceremony/admin/backup.php`) ومكيّفة لـ DMS. تتيح:

- نسخة **SQL** لقاعدة البيانات `dms`
- نسخة **ZIP** لملفات المشروع
- **جدولة Cron** (سطر واحد في cPanel)
- رفع **OneDrive / Google Drive**
- **تنبيهات بريد** عند النجاح أو الفailure
- **استرجاع / تنزيل / حذف** النسخ المحفوظة

**الصلاحية:** مدير النظام فقط (`require_role(['admin'])`).

---

## الوصول

| العنصر | المسار |
|--------|--------|
| الصفحة | `admin/backup.php` |
| تنزيل ملف | `admin/backup_download.php` |
| OAuth سحابي | `admin/backup_oauth.php` |
| مجدول Cron | `tools/backup-maintenance/cron_scheduler.php` |
| الإعدادات | `tools/backup-maintenance/ui_settings.json` |

---

## الملفات البرمجية

| الملف | الوظيفة |
|-------|---------|
| `config/backup/db.php` | إعدادات اتصال DB للنسخ |
| `config/backup/cloud.php` | OneDrive / Google Drive OAuth ورفع |
| `config/backup/mysql_runner.php` | إنشاء dump SQL عبر `mysqldump` |
| `config/backup/project_runner.php` | أرشفة ZIP للمشروع |
| `config/backup/maint.php` | تحميل/حفظ الإعدادات، استرجاع، قائمة الملفات |
| `tools/backup-maintenance/cron_scheduler.php` | تنفيذ المهام المجدولة (CLI فقط) |

---

## المسارات الافتراضية (محلي XAMPP)

| الإعداد | القيمة الافتراضية |
|---------|-------------------|
| مسار المشروع | `C:/xampp/htdocs/dms` |
| مجلد النسخ | `C:/xampp/htdocs/dms/Backup/mysql-scheduled` |
| مجلد OneDrive | `DmsBackups` |

على **HostGator** عدّل المسارات من الصفحة واحفظ، مثلاً:

- `/home/USERNAME/public_html/dms`
- `/home/USERNAME/backups/dms`

---

## Cron (cPanel)

سطر واحد يُنفَّذ **كل دقيقة**:

```text
* * * * * /usr/local/bin/php -q /home/USERNAME/public_html/dms/tools/backup-maintenance/cron_scheduler.php >> /home/USERNAME/backups/dms/scheduler.log 2>&1
```

يُنفّذ مهمة SQL و/أو ZIP حسب الوقت والتكرار المفعّل في الصفحة.

---

## OneDrive / Azure

### Redirect URI (يظهر في الصفحة)

```text
https://YOUR-DOMAIN/dms/admin/backup_oauth.php
```

محلياً: `http://localhost/dms/admin/backup_oauth.php`

### الحقول

| الحقل | المصدر في Azure |
|-------|-----------------|
| **Application (client) ID** | App registrations → Overview → UUID |
| **Client secret Value** | Certificates & secrets → Value |
| **Tenant** | Directory (tenant) ID أو `common` |

**تحذير:** لا تضع Tenant ID أو Client Secret في حقل Application ID — يجب أن يكون UUID من Overview.

### مشاركة تطبيق مع نظام الدعوات

يمكن استخدام **نفس تطبيق Azure**، لكن:

1. أضف Redirect URI لـ DMS في Azure
2. انسخ ID و Secret من `Invitations/tools/backup-maintenance/ui_settings.json` أو من Azure
3. اضغط **«ربط OneDrive»** — refresh token خاص بـ DMS

---

## ملف الإعدادات `ui_settings.json`

يحتوي على:

- أوقات الجدولة وتكرار الأيام
- مسارات المشروع والنسخ
- بريد التنبيه
- مفاتيح OAuth (Client ID, Secret, refresh token)

**لا ترفعه إلى Git** — فيه أسرار.

---

## الأمان

- `tools/backup-maintenance/.htaccess` — يمنع الوصول من المتصفح
- `Backup/.htaccess` — يمنع تصفح مجلد النسخ
- التنزيل والاسترجاع يتطلبان CSRF + دور admin + مسار ضمن قائمة مسموحة

---

## استكشاف الأخطاء

| المشكلة | الحل |
|---------|------|
| فشل SQL | تأكد من `mysqldump` في XAMPP: `C:\xampp\mysql\bin\mysqldump.exe` |
| Client ID غير صالح | UUID في الحقل الأول؛ Secret في الحقل الثاني |
| OneDrive غير مربوط | اضغط «ربط OneDrive» بعد إضافة Redirect URI |
| أيقونات لا تظهر | Bootstrap Icons: `bi-floppy2`, `bi-file-earmark-zip` |

---

*آخر مراجعة: أغسطس 2026 — مأخوذ من نظام الدعوات ومكيّف لـ DMS.*
