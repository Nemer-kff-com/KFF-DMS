<?php
/**
 * حفظ مسميات القوائم (JSON) — إضافة/تعديل من النافذة المنبثقة.
 */
require_once __DIR__ . '/../config/auth.php';

header('Content-Type: application/json; charset=utf-8');
if (ob_get_level() === 0) {
    ob_start();
}

$user = current_user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => __('auth.need_login')], JSON_UNESCAPED_UNICODE);
    exit;
}
if (!user_can('lookups.manage', $user)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => __('auth.no_permission')], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => __('auth.forbidden')], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    verify_csrf();
    ensure_lookups_schema();

    $kind = (string) ($_POST['kind'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    $allowed = ['categories', 'priorities', 'confidentiality', 'statuses', 'archive_types', 'attachment_types'];
    $catalogDef = function_exists('archive_catalog_def') ? archive_catalog_def($kind) : null;
    if ($catalogDef) {
        $kind = 'catalog_' . $catalogDef['key'];
    } elseif (!in_array($kind, $allowed, true)) {
        throw new InvalidArgumentException(__('common.not_found'));
    }

    $sortOrder = (int) ($_POST['sort_order'] ?? 0);
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $nameAr = trim((string) ($_POST['name_ar'] ?? $_POST['name'] ?? ''));
    $nameEn = trim((string) ($_POST['name_en'] ?? ''));
    $codeIn = trim((string) ($_POST['code'] ?? ''));

    if ($nameAr === '') {
        throw new InvalidArgumentException(__('common.name_required'));
    }

    if ($catalogDef) {
        $table = archive_catalog_table($catalogDef['key']);
        if ($table === null) {
            throw new InvalidArgumentException(__('common.not_found'));
        }
        if ($id > 0) {
            if (!admin_row($table, $id)) {
                throw new InvalidArgumentException(__('common.not_found'));
            }
            if (admin_name_exists($table, 'name_ar', $nameAr, $id)) {
                throw new InvalidArgumentException(__('common.duplicate_name'));
            }
            $set = 'name_ar=?, name_en=?, sort_order=?, is_active=?';
            $params = [$nameAr, $nameEn !== '' ? $nameEn : null, $sortOrder, $isActive];
            if (dms_db_column_exists($table, 'iso2')) {
                $set .= ', iso2=?';
                $iso2 = strtoupper(preg_replace('/[^A-Za-z]/', '', (string) ($_POST['iso2'] ?? '')) ?? '');
                $params[] = $iso2 !== '' ? substr($iso2, 0, 8) : null;
            }
            if (dms_db_column_exists($table, 'region_id')) {
                $set .= ', region_id=?';
                $rid = trim((string) ($_POST['region_id'] ?? ''));
                $params[] = $rid !== '' ? substr($rid, 0, 8) : null;
            }
            if (dms_db_column_exists($table, 'country_id')) {
                $set .= ', country_id=?';
                $cid = (int) ($_POST['country_id'] ?? 0);
                $params[] = $cid > 0 ? $cid : null;
            }
            $params[] = $id;
            db()->prepare("UPDATE `{$table}` SET {$set} WHERE id=?")->execute($params);
            $msg = __('lookups.updated');
        } else {
            if (admin_name_exists($table, 'name_ar', $nameAr)) {
                throw new InvalidArgumentException(__('common.duplicate_name'));
            }
            if ($sortOrder <= 0) {
                $sortOrder = (int) db()->query("SELECT COALESCE(MAX(sort_order), 0) + 10 FROM `{$table}`")->fetchColumn();
            }
            $cols = ['name_ar', 'name_en', 'sort_order', 'is_active'];
            $params = [$nameAr, $nameEn !== '' ? $nameEn : null, $sortOrder, 1];
            if (dms_db_column_exists($table, 'iso2')) {
                $cols[] = 'iso2';
                $iso2 = strtoupper(preg_replace('/[^A-Za-z]/', '', (string) ($_POST['iso2'] ?? '')) ?? '');
                $params[] = $iso2 !== '' ? substr($iso2, 0, 8) : null;
            }
            if (dms_db_column_exists($table, 'region_id')) {
                $cols[] = 'region_id';
                $rid = trim((string) ($_POST['region_id'] ?? ''));
                $params[] = $rid !== '' ? substr($rid, 0, 8) : null;
            }
            if (dms_db_column_exists($table, 'country_id')) {
                $cols[] = 'country_id';
                $cid = (int) ($_POST['country_id'] ?? 0);
                $params[] = $cid > 0 ? $cid : null;
            }
            $ph = implode(',', array_fill(0, count($cols), '?'));
            db()->prepare('INSERT INTO `' . $table . '` (' . implode(',', $cols) . ') VALUES (' . $ph . ')')
                ->execute($params);
            $id = (int) db()->lastInsertId();
            $msg = __('lookups.created');
        }
    } elseif ($kind === 'categories') {
        if (admin_name_exists('tblcategories', 'name', $nameAr, $id > 0 ? $id : null)) {
            throw new InvalidArgumentException(__('common.duplicate_name'));
        }
        if ($id > 0) {
            if (!admin_row('tblcategories', $id)) {
                throw new InvalidArgumentException(__('common.not_found'));
            }
            db()->prepare('UPDATE `tblcategories` SET name=?, name_en=?, sort_order=?, is_active=? WHERE id=?')
                ->execute([$nameAr, $nameEn !== '' ? $nameEn : null, $sortOrder, $isActive, $id]);
            $msg = __('categories.updated');
        } else {
            db()->prepare('INSERT INTO `tblcategories` (name, name_en, sort_order, is_active) VALUES (?,?,?,1)')
                ->execute([$nameAr, $nameEn !== '' ? $nameEn : null, $sortOrder]);
            $id = (int) db()->lastInsertId();
            $msg = __('categories.created');
        }
    } elseif (in_array($kind, ['priorities', 'confidentiality', 'statuses'], true)) {
        $table = match ($kind) {
            'priorities' => 'tblpriority_levels',
            'confidentiality' => 'tblconfidentiality_levels',
            default => 'tblstatus_levels',
        };
        if ($nameEn === '') {
            $nameEn = $nameAr;
        }
        if ($id > 0) {
            $row = admin_row($table, $id);
            if (!$row) {
                throw new InvalidArgumentException(__('common.not_found'));
            }
            db()->prepare("UPDATE `{$table}` SET name_ar=?, name_en=?, sort_order=?, is_active=? WHERE id=?")
                ->execute([$nameAr, $nameEn, $sortOrder, $isActive, $id]);
            $msg = __('lookups.updated');
        } else {
            $code = slugify_lookup_code($codeIn !== '' ? $codeIn : ($nameEn !== '' ? $nameEn : $nameAr));
            if ($code === '') {
                throw new InvalidArgumentException(__('lookups.code_exists'));
            }
            $base = $code;
            $n = 1;
            $chk = db()->prepare("SELECT id FROM `{$table}` WHERE code = ?");
            while ($n < 50) {
                $chk->execute([$code]);
                if (!$chk->fetch()) {
                    break;
                }
                $code = substr($base, 0, 35) . '_' . $n;
                $n++;
            }
            if ($sortOrder <= 0) {
                $sortOrder = (int) db()->query("SELECT COALESCE(MAX(sort_order), 0) + 10 FROM `{$table}`")->fetchColumn();
            }
            db()->prepare(
                "INSERT INTO `{$table}` (code, name_ar, name_en, sort_order, is_active, is_system) VALUES (?,?,?,?,1,0)"
            )->execute([$code, $nameAr, $nameEn, $sortOrder]);
            $id = (int) db()->lastInsertId();
            $msg = __('lookups.created');
        }
    } elseif ($kind === 'archive_types') {
        if ($id > 0) {
            $row = admin_row('tblarchive_doc_types', $id);
            if (!$row) {
                throw new InvalidArgumentException(__('common.not_found'));
            }
            if (admin_name_exists('tblarchive_doc_types', 'name_ar', $nameAr, $id)) {
                throw new InvalidArgumentException(__('common.duplicate_name'));
            }
            db()->prepare('UPDATE `tblarchive_doc_types` SET name_ar=?, name_en=?, sort_order=?, is_active=? WHERE id=?')
                ->execute([$nameAr, $nameEn !== '' ? $nameEn : $nameAr, $sortOrder, $isActive, $id]);
            $msg = __('lookups.updated');
        } else {
            if (admin_name_exists('tblarchive_doc_types', 'name_ar', $nameAr)) {
                throw new InvalidArgumentException(__('common.duplicate_name'));
            }
            $code = slugify_lookup_code($codeIn !== '' ? $codeIn : ($nameEn !== '' ? $nameEn : $nameAr));
            $base = $code;
            $n = 1;
            $chk = db()->prepare('SELECT id FROM `tblarchive_doc_types` WHERE code = ?');
            while ($n < 50) {
                $chk->execute([$code]);
                if (!$chk->fetch()) {
                    break;
                }
                $code = substr($base, 0, 35) . '_' . $n;
                $n++;
            }
            db()->prepare('INSERT INTO `tblarchive_doc_types` (code, name_ar, name_en, sort_order) VALUES (?,?,?,?)')
                ->execute([$code, $nameAr, $nameEn !== '' ? $nameEn : $nameAr, $sortOrder]);
            $id = (int) db()->lastInsertId();
            $msg = __('lookups.created');
        }
    } else { // attachment_types
        if ($id > 0) {
            $row = admin_row('tblattachment_types', $id);
            if (!$row) {
                throw new InvalidArgumentException(__('common.not_found'));
            }
            if (admin_name_exists('tblattachment_types', 'name_ar', $nameAr, $id)) {
                throw new InvalidArgumentException(__('common.duplicate_name'));
            }
            if ($nameEn === '') {
                throw new InvalidArgumentException(__('common.name_required'));
            }
            db()->prepare('UPDATE `tblattachment_types` SET name_ar=?, name_en=?, sort_order=?, is_active=? WHERE id=?')
                ->execute([$nameAr, $nameEn, $sortOrder, $isActive, $id]);
            $msg = __('lookups.updated');
        } else {
            $row = attachment_type_create($nameAr, $nameEn, $codeIn);
            $id = (int) $row['id'];
            $msg = __('lookups.created');
        }
    }

    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    echo json_encode(['ok' => true, 'id' => $id, 'message' => $msg], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
