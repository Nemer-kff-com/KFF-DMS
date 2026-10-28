<?php
/**
 * القوائم المرجعية (عربي / إنجليزي) — قابلة للتعديل من الإدارة
 */

function ensure_lookups_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        db()->exec(
            "CREATE TABLE IF NOT EXISTS `tblarchive_doc_types` (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(40) NOT NULL UNIQUE,
                name_ar VARCHAR(150) NOT NULL,
                name_en VARCHAR(150) NOT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        db()->exec(
            "CREATE TABLE IF NOT EXISTS `tblpriority_levels` (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(40) NOT NULL UNIQUE,
                name_ar VARCHAR(150) NOT NULL,
                name_en VARCHAR(150) NOT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                is_system TINYINT(1) NOT NULL DEFAULT 1
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        db()->exec(
            "CREATE TABLE IF NOT EXISTS `tblconfidentiality_levels` (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(40) NOT NULL UNIQUE,
                name_ar VARCHAR(150) NOT NULL,
                name_en VARCHAR(150) NOT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                is_system TINYINT(1) NOT NULL DEFAULT 1
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        db()->exec(
            "CREATE TABLE IF NOT EXISTS `tblstatus_levels` (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(40) NOT NULL UNIQUE,
                name_ar VARCHAR(150) NOT NULL,
                name_en VARCHAR(150) NOT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                is_system TINYINT(1) NOT NULL DEFAULT 1
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        db()->exec(
            "CREATE TABLE IF NOT EXISTS `tblattachment_types` (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(40) NOT NULL UNIQUE,
                name_ar VARCHAR(150) NOT NULL,
                name_en VARCHAR(150) NOT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        db()->exec(
            "CREATE TABLE IF NOT EXISTS `tblcorr_attachments` (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                correspondence_id INT UNSIGNED NOT NULL,
                attachment_type_id INT UNSIGNED NOT NULL,
                quantity INT UNSIGNED NOT NULL DEFAULT 1,
                UNIQUE KEY uq_corr_attachment (correspondence_id, attachment_type_id),
                KEY idx_corr_att_type (attachment_type_id),
                CONSTRAINT fk_corr_att_corr FOREIGN KEY (correspondence_id)
                    REFERENCES `tblcorrespondence` (id) ON DELETE CASCADE,
                CONSTRAINT fk_corr_att_type FOREIGN KEY (attachment_type_id)
                    REFERENCES `tblattachment_types` (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        // تصنيفات المعاملات: اسم إنجليزي + ترتيب
        if (!dms_db_column_exists('tblcategories', 'name_en')) {
            db()->exec("ALTER TABLE `tblcategories` ADD COLUMN name_en VARCHAR(150) NULL AFTER name");
        }
        if (!dms_db_column_exists('tblcategories', 'sort_order')) {
            db()->exec("ALTER TABLE `tblcategories` ADD COLUMN sort_order INT NOT NULL DEFAULT 0 AFTER name_en");
            db()->exec("UPDATE `tblcategories` SET sort_order = id * 10 WHERE sort_order = 0");
        }

        // السماح بإضافة رموز جديدة للأولوية/السرية/الحالة (بدل ENUM الثابت)
        dms_lookup_widen_enum_column('tblcorrespondence', 'priority', 'normal');
        dms_lookup_widen_enum_column('tblcorrespondence', 'status', 'new');
        if (dms_db_column_exists('tblcorrespondence', 'confidentiality')) {
            dms_lookup_widen_enum_column('tblcorrespondence', 'confidentiality', 'normal');
        }
        if (dms_db_table_exists('tblarchive_items') && dms_db_column_exists('tblarchive_items', 'confidentiality')) {
            dms_lookup_widen_enum_column('tblarchive_items', 'confidentiality', 'normal');
        }

        seed_lookup_defaults();
    } catch (Throwable $e) {
        // قاعدة غير جاهزة
    }
}

/** حوّل عمود ENUM إلى VARCHAR إن لزم لدعم مسميات قابلة للإضافة */
function dms_lookup_widen_enum_column(string $table, string $column, string $default): void
{
    $table = preg_replace('/[^a-z0-9_]/i', '', $table) ?? '';
    $column = preg_replace('/[^a-z0-9_]/i', '', $column) ?? '';
    if ($table === '' || $column === '' || !dms_db_column_exists($table, $column)) {
        return;
    }
    try {
        $stmt = db()->query("SHOW COLUMNS FROM `{$table}` LIKE " . db()->quote($column));
        $col = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
        $type = strtolower((string) ($col['Type'] ?? ''));
        if ($type === '' || !str_starts_with($type, 'enum(')) {
            return;
        }
        $defaultSql = db()->quote($default);
        db()->exec(
            "ALTER TABLE `{$table}`
             MODIFY COLUMN `{$column}` VARCHAR(40) NOT NULL DEFAULT {$defaultSql}"
        );
    } catch (Throwable $e) {
        // ignore
    }
}

function seed_lookup_defaults(): void
{
    $archTypes = [
        ['old_letter', 'خطاب قديم', 'Old letter', 10],
        ['correspondence', 'مراسلة', 'Correspondence', 20],
        ['receipt', 'إيصال', 'Receipt', 30],
        ['journal_entry', 'قيد محاسبي', 'Accounting entry', 35],
        ['check', 'شيك', 'Check', 40],
        ['scholarship', 'منحة دراسية', 'Scholarship', 50],
        ['project', 'مشروع', 'Project', 55],
        ['contract', 'عقد', 'Contract', 60],
        ['decision', 'قرار', 'Decision', 70],
        ['other', 'أخرى', 'Other', 90],
    ];
    $insArch = db()->prepare(
        "INSERT IGNORE INTO `tblarchive_doc_types` (code, name_ar, name_en, sort_order) VALUES (?,?,?,?)"
    );
    foreach ($archTypes as $t) {
        $insArch->execute($t);
    }

    $priorities = [
        ['normal', 'عادي', 'Normal', 10],
        ['urgent', 'عاجل', 'Urgent', 20],
    ];
    $insP = db()->prepare(
        "INSERT IGNORE INTO `tblpriority_levels` (code, name_ar, name_en, sort_order, is_system) VALUES (?,?,?,?,1)"
    );
    foreach ($priorities as $p) {
        $insP->execute($p);
    }
    // إزالة «عاجل جداً» من القائمة الافتراضية (المعاملات القديمة تحتفظ بالقيمة إن وُجدت)
    try {
        db()->exec("DELETE FROM `tblpriority_levels` WHERE code = 'very_urgent'");
    } catch (Throwable $e) {
        // ignore
    }

    $conf = [
        ['normal', 'عادي', 'Normal', 10],
        ['confidential', 'سري', 'Confidential', 20],
    ];
    $insC = db()->prepare(
        "INSERT IGNORE INTO `tblconfidentiality_levels` (code, name_ar, name_en, sort_order, is_system) VALUES (?,?,?,?,1)"
    );
    foreach ($conf as $c) {
        $insC->execute($c);
    }
    // إزالة «سري للغاية» و«سري جداً» من القائمة (السجلات القديمة تحتفظ بالقيمة إن وُجدت)
    try {
        db()->exec("DELETE FROM `tblconfidentiality_levels` WHERE code IN ('secret','top_secret')");
    } catch (Throwable $e) {
        // ignore
    }

    $statuses = [
        ['new', 'جديدة', 'New', 10],
        ['in_progress', 'قيد الإجراء', 'In progress', 20],
        ['referred', 'محالة', 'Referred', 30],
        ['awaiting_reply', 'بانتظار الرد', 'Awaiting reply', 40],
        ['replied', 'تم الرد', 'Replied', 50],
        ['sent', 'تم الإرسال', 'Sent', 55],
        ['delivered', 'تم التسليم', 'Delivered', 60],
        ['closed', 'مغلقة', 'Closed', 70],
        ['cancelled', 'ملغى', 'Cancelled', 80],
    ];
    $insS = db()->prepare(
        "INSERT IGNORE INTO `tblstatus_levels` (code, name_ar, name_en, sort_order, is_system) VALUES (?,?,?,?,1)"
    );
    foreach ($statuses as $s) {
        $insS->execute($s);
    }

    $attachmentTypes = [
        ['book', 'كتاب', 'Book', 10],
        ['brochure', 'مطوية', 'Brochure', 30],
        ['intro_brief', 'نبذة تعريفية', 'Introductory brief', 40],
        ['magazine', 'مجلة', 'Magazine', 50],
        ['invoices', 'فواتير', 'Invoices', 60],
        ['petition', 'معروض', 'Petition', 70],
        ['contract', 'عقد', 'Contract', 80],
        ['transcript', 'كشف درجات', 'Transcript', 90],
        ['letters', 'خطابات', 'Letters', 100],
        ['check', 'شيك', 'Cheque', 110],
        ['photos', 'صور', 'Photos', 120],
        ['old_manuscript', 'مخطوطة قديمة', 'Old manuscript', 130],
    ];
    $insAtt = db()->prepare(
        "INSERT IGNORE INTO `tblattachment_types` (code, name_ar, name_en, sort_order) VALUES (?,?,?,?)"
    );
    foreach ($attachmentTypes as $t) {
        $insAtt->execute($t);
    }
    // إزالة «قرص CD» من القائمة الافتراضية
    try {
        $cdId = (int) db()->query("SELECT id FROM `tblattachment_types` WHERE code = 'cd' LIMIT 1")->fetchColumn();
        if ($cdId > 0) {
            if (attachment_type_in_use($cdId)) {
                db()->prepare('UPDATE `tblattachment_types` SET is_active = 0 WHERE id = ?')->execute([$cdId]);
            } else {
                db()->prepare('DELETE FROM `tblattachment_types` WHERE id = ?')->execute([$cdId]);
            }
        }
    } catch (Throwable $e) {
        // ignore
    }
}

/** اسم عربي/إنجليزي حسب لغة الواجهة */
function bilingual_label(array $row, string $arKey = 'name_ar', string $enKey = 'name_en', string $fallbackKey = 'name'): string
{
    $ar = trim((string) ($row[$arKey] ?? $row[$fallbackKey] ?? ''));
    $en = trim((string) ($row[$enKey] ?? ''));
    if (function_exists('dms_lang_code') && dms_lang_code() === 'en' && $en !== '') {
        return $en;
    }
    return $ar !== '' ? $ar : $en;
}

function category_display_name(array $row): string
{
    return bilingual_label($row, 'name', 'name_en', 'name');
}

function archive_doc_types_list(bool $activeOnly = true): array
{
    ensure_lookups_schema();
    $sql = 'SELECT * FROM `tblarchive_doc_types`';
    if ($activeOnly) {
        $sql .= ' WHERE is_active = 1';
    }
    $sql .= ' ORDER BY sort_order, id';
    return db()->query($sql)->fetchAll();
}

function archive_doc_types(): array
{
    return array_column(archive_doc_types_list(true), 'code');
}

function archive_doc_type_label(string $type): string
{
    ensure_lookups_schema();
    static $map = null;
    if ($map === null) {
        $map = [];
        foreach (archive_doc_types_list(false) as $row) {
            $map[$row['code']] = $row;
        }
    }
    if (isset($map[$type])) {
        return bilingual_label($map[$type]);
    }
    $key = 'archive.type.' . $type;
    $t = __($key);
    return $t !== $key ? $t : $type;
}

function priority_levels_list(bool $activeOnly = true): array
{
    ensure_lookups_schema();
    $sql = 'SELECT * FROM `tblpriority_levels`';
    if ($activeOnly) {
        $sql .= ' WHERE is_active = 1';
    }
    $sql .= ' ORDER BY sort_order, id';
    return db()->query($sql)->fetchAll();
}

function confidentiality_levels_list(bool $activeOnly = true): array
{
    ensure_lookups_schema();
    $sql = 'SELECT * FROM `tblconfidentiality_levels`';
    if ($activeOnly) {
        $sql .= ' WHERE is_active = 1';
    }
    $sql .= ' ORDER BY sort_order, id';
    return db()->query($sql)->fetchAll();
}

function status_levels_list(bool $activeOnly = true): array
{
    ensure_lookups_schema();
    $sql = 'SELECT * FROM `tblstatus_levels`';
    if ($activeOnly) {
        $sql .= ' WHERE is_active = 1';
    }
    $sql .= ' ORDER BY sort_order, id';
    return db()->query($sql)->fetchAll();
}

function attachment_types_list(bool $activeOnly = true): array
{
    ensure_lookups_schema();
    $sql = 'SELECT * FROM `tblattachment_types`';
    if ($activeOnly) {
        $sql .= ' WHERE is_active = 1';
    }
    $sql .= ' ORDER BY sort_order, id';
    return db()->query($sql)->fetchAll();
}

/** أنواع المرفقات للنموذج: النشطة + أي نوع مرتبط بالمعاملة حتى لو عُطّل */
function attachment_types_for_form(?int $corrId = null): array
{
    $rows = attachment_types_list(true);
    $byId = [];
    foreach ($rows as $row) {
        $byId[(int) $row['id']] = $row;
    }
    if ($corrId && $corrId > 0) {
        $stmt = db()->prepare(
            'SELECT t.* FROM `tblattachment_types` t
             INNER JOIN `tblcorr_attachments` a ON a.attachment_type_id = t.id
             WHERE a.correspondence_id = ? AND t.is_active = 0'
        );
        $stmt->execute([$corrId]);
        foreach ($stmt->fetchAll() as $row) {
            $byId[(int) $row['id']] = $row;
        }
    }
    uasort($byId, static fn(array $a, array $b): int =>
        ((int) ($a['sort_order'] ?? 0) <=> (int) ($b['sort_order'] ?? 0))
        ?: ((int) ($a['id'] ?? 0) <=> (int) ($b['id'] ?? 0))
    );
    return array_values($byId);
}

function attachment_type_in_use(int $typeId): bool
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM `tblcorr_attachments` WHERE attachment_type_id = ?');
    $stmt->execute([$typeId]);
    return (int) $stmt->fetchColumn() > 0;
}

/** @return array<int,int> type_id => quantity */
function correspondence_enclosures_from_request(array $post): array
{
    $ids = array_values(array_map('intval', (array) ($post['enclosures'] ?? [])));
    $qtysRaw = (array) ($post['enclosure_qty'] ?? []);
    /* enclosure_qty[] متوازٍ مع enclosures[]، مع دعم الشكل القديم enclosure_qty[id] */
    $isList = $qtysRaw === [] || array_keys($qtysRaw) === range(0, count($qtysRaw) - 1);
    $out = [];
    foreach ($ids as $i => $id) {
        if ($id <= 0) {
            continue;
        }
        if ($isList) {
            $q = (int) ($qtysRaw[$i] ?? 1);
        } else {
            $q = (int) ($qtysRaw[$id] ?? 1);
        }
        $q = max(1, min(9999, $q));
        $out[$id] = min(9999, ($out[$id] ?? 0) + $q);
    }
    return $out;
}

/** @return array<int,int> type_id => quantity */
function correspondence_enclosures_map(int $corrId): array
{
    ensure_lookups_schema();
    $stmt = db()->prepare(
        'SELECT attachment_type_id, quantity FROM `tblcorr_attachments` WHERE correspondence_id = ?'
    );
    $stmt->execute([$corrId]);
    $map = [];
    foreach ($stmt->fetchAll() as $row) {
        $map[(int) $row['attachment_type_id']] = max(1, (int) $row['quantity']);
    }
    return $map;
}

/** @return list<array{id:int,quantity:int,code:string,name_ar:string,name_en:string}> */
function correspondence_enclosures(int $corrId): array
{
    ensure_lookups_schema();
    $stmt = db()->prepare(
        'SELECT a.attachment_type_id AS id, a.quantity, t.code, t.name_ar, t.name_en
         FROM `tblcorr_attachments` a
         INNER JOIN `tblattachment_types` t ON t.id = a.attachment_type_id
         WHERE a.correspondence_id = ?
         ORDER BY t.sort_order, t.id'
    );
    $stmt->execute([$corrId]);
    return $stmt->fetchAll();
}

function correspondence_enclosure_label(array $row): string
{
    $name = bilingual_label($row);
    $qty = (int) ($row['quantity'] ?? 1);
    return $qty > 1 ? $name . ' × ' . $qty : $name;
}

/** @param array<int,int> $map type_id => quantity */
function save_correspondence_enclosures(int $corrId, array $map): void
{
    ensure_lookups_schema();
    $pdo = db();
    $pdo->prepare('DELETE FROM `tblcorr_attachments` WHERE correspondence_id = ?')->execute([$corrId]);
    if (!$map) {
        return;
    }
    $valid = [];
    foreach (attachment_types_list(false) as $row) {
        $valid[(int) $row['id']] = true;
    }
    $ins = $pdo->prepare(
        'INSERT INTO `tblcorr_attachments` (correspondence_id, attachment_type_id, quantity) VALUES (?,?,?)'
    );
    foreach ($map as $typeId => $qty) {
        $typeId = (int) $typeId;
        if ($typeId <= 0 || empty($valid[$typeId])) {
            continue;
        }
        $ins->execute([$corrId, $typeId, max(1, min(9999, (int) $qty))]);
    }
}

function attachment_types_next_sort(): int
{
    ensure_lookups_schema();
    $max = (int) db()->query('SELECT COALESCE(MAX(sort_order), 0) FROM `tblattachment_types`')->fetchColumn();
    return $max > 0 ? $max + 10 : 10;
}

/**
 * @return array{id:int,code:string,name_ar:string,name_en:string,sort_order:int}
 */
function attachment_type_create(string $nameAr, string $nameEn = '', string $code = ''): array
{
    ensure_lookups_schema();
    $nameAr = trim($nameAr);
    $nameEn = trim($nameEn);
    $code = trim($code);
    if ($nameAr === '') {
        throw new InvalidArgumentException(__('common.name_required'));
    }
    if (admin_name_exists('tblattachment_types', 'name_ar', $nameAr)) {
        throw new InvalidArgumentException(__('common.duplicate_name'));
    }
    $slug = slugify_lookup_code($code !== '' ? $code : ($nameEn !== '' ? $nameEn : $nameAr));
    $base = $slug;
    $n = 1;
    $chk = db()->prepare('SELECT id FROM `tblattachment_types` WHERE code = ?');
    while ($n < 50) {
        $chk->execute([$slug]);
        if (!$chk->fetch()) {
            break;
        }
        $slug = substr($base, 0, 35) . '_' . $n;
        $n++;
    }
    $sort = attachment_types_next_sort();
    $nameEn = $nameEn !== '' ? $nameEn : $nameAr;
    try {
        db()->prepare(
            'INSERT INTO `tblattachment_types` (code, name_ar, name_en, sort_order) VALUES (?,?,?,?)'
        )->execute([$slug, $nameAr, $nameEn, $sort]);
    } catch (PDOException $e) {
        throw new RuntimeException(__('lookups.code_exists'));
    }
    $id = (int) db()->lastInsertId();
    return [
        'id' => $id,
        'code' => $slug,
        'name_ar' => $nameAr,
        'name_en' => $nameEn,
        'sort_order' => $sort,
    ];
}

function lookup_label_from_table(string $table, string $code): ?string
{
    ensure_lookups_schema();
    $allowed = [
        'tblstatus_levels',
        'tblpriority_levels',
        'tblconfidentiality_levels',
    ];
    if (!in_array($table, $allowed, true)) {
        return null;
    }
    static $cache = [];
    $key = $table;
    if (!isset($cache[$key])) {
        $cache[$key] = [];
        try {
            foreach (db()->query("SELECT * FROM `{$table}`")->fetchAll() as $row) {
                $cache[$key][$row['code']] = $row;
            }
        } catch (Throwable $e) {
            $cache[$key] = [];
        }
    }
    if (!isset($cache[$key][$code])) {
        return null;
    }
    return bilingual_label($cache[$key][$code]);
}

function slugify_lookup_code(string $text): string
{
    $text = trim($text);
    $text = preg_replace('/\s+/u', '_', $text) ?? '';
    $text = preg_replace('/[^a-zA-Z0-9_\-]/', '', $text) ?? '';
    $text = strtolower($text);
    if ($text === '') {
        $text = 'type_' . substr(bin2hex(random_bytes(3)), 0, 6);
    }
    return substr($text, 0, 40);
}
