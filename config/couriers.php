<?php
/**
 * مراسلو الصادر: قائمة اختيار. الإضافة من شاشة المراسلين. لا يغيّر سير المعاملة.
 */

function ensure_outgoing_courier_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        db()->exec(
            "CREATE TABLE IF NOT EXISTS `tblcouriers` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(255) NOT NULL,
                `phone` VARCHAR(50) NULL DEFAULT NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `uk_courier_name` (`name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $cols = function_exists('dms_db_column_map') ? dms_db_column_map('tblcorrespondence') : [];
        $alters = [];
        if (empty($cols['courier_name'])) {
            $alters[] = 'ADD COLUMN `courier_name` VARCHAR(255) NULL DEFAULT NULL COMMENT \'المراسل\' AFTER `intend_electronic`';
        }
        if (empty($cols['courier_phone'])) {
            $after = !empty($cols['courier_name']) || $alters !== [] ? 'courier_name' : 'intend_electronic';
            $alters[] = 'ADD COLUMN `courier_phone` VARCHAR(50) NULL DEFAULT NULL COMMENT \'جوال المراسل\' AFTER `' . $after . '`';
            $cols['courier_phone'] = true;
        }
        if (empty($cols['courier_id'])) {
            if (!empty($cols['courier_phone'])) {
                $after = 'courier_phone';
            } elseif (!empty($cols['courier_name']) || $alters !== []) {
                $after = 'courier_name';
            } else {
                $after = 'intend_electronic';
            }
            $alters[] = 'ADD COLUMN `courier_id` INT UNSIGNED NULL DEFAULT NULL COMMENT \'مرجع الدليل فقط؛ الحذف لا يمسّ الاسم المحفوظ على المعاملة\' AFTER `' . $after . '`';
        }
        if ($alters) {
            db()->exec('ALTER TABLE `tblcorrespondence` ' . implode(', ', $alters));
        }
        $count = (int) db()->query('SELECT COUNT(*) FROM `tblcouriers`')->fetchColumn();
        if ($count === 0) {
            db()->prepare('INSERT INTO `tblcouriers` (`name`, `phone`) VALUES (?, NULL)')
                ->execute(['سلّم شخصياً']);
        }
    } catch (Throwable $e) {
    }
}

/** @return array{id:int,name:string,phone:string,is_active:int} */
function outgoing_courier_payload(array $row): array
{
    return [
        'id' => (int) ($row['id'] ?? 0),
        'name' => trim((string) ($row['name'] ?? '')),
        'phone' => trim((string) ($row['phone'] ?? '')),
        'is_active' => (int) ($row['is_active'] ?? 1),
    ];
}

/** @return list<array{id:int,name:string,phone:string,is_active?:int}> */
function outgoing_couriers_list(bool $activeOnly = true): array
{
    ensure_outgoing_courier_schema();
    try {
        $sql = 'SELECT id, name, phone, is_active FROM `tblcouriers`';
        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY name ASC';
        $rows = db()->query($sql)->fetchAll();
        return array_map('outgoing_courier_payload', $rows);
    } catch (Throwable $e) {
        return [];
    }
}

function outgoing_courier_get(int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    ensure_outgoing_courier_schema();
    try {
        $stmt = db()->prepare('SELECT id, name, phone, is_active FROM `tblcouriers` WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ? outgoing_courier_payload($row) : null;
    } catch (Throwable $e) {
        return null;
    }
}

function outgoing_courier_save(array $data, ?int $id = null): int
{
    ensure_outgoing_courier_schema();
    $name = trim((string) ($data['name'] ?? ''));
    $phone = trim((string) ($data['phone'] ?? '')) ?: null;
    if ($name === '') {
        throw new InvalidArgumentException(__('couriers.name_required'));
    }
    $dup = db()->prepare('SELECT id FROM `tblcouriers` WHERE name = ? AND id <> ? LIMIT 1');
    $dup->execute([$name, (int) ($id ?? 0)]);
    if ($dup->fetch()) {
        throw new InvalidArgumentException(__('couriers.duplicate'));
    }
    $active = isset($data['is_active']) ? ((int) $data['is_active'] ? 1 : 0) : 1;
    if ($id) {
        db()->prepare('UPDATE `tblcouriers` SET name = ?, phone = ?, is_active = ? WHERE id = ?')
            ->execute([$name, $phone, $active, $id]);
        return $id;
    }
    db()->prepare('INSERT INTO `tblcouriers` (`name`, `phone`, `is_active`) VALUES (?, ?, 1)')
        ->execute([$name, $phone]);
    return (int) db()->lastInsertId();
}

function outgoing_courier_set_active(int $id, bool $active): void
{
    ensure_outgoing_courier_schema();
    db()->prepare('UPDATE `tblcouriers` SET is_active = ? WHERE id = ?')
        ->execute([$active ? 1 : 0, $id]);
}

/** حذف من الدليل فقط. اسم المراسل المحفوظ على المعاملات الصادرة يبقى كما هو. */
function outgoing_courier_delete(int $id): void
{
    if ($id <= 0) {
        return;
    }
    ensure_outgoing_courier_schema();
    db()->prepare('DELETE FROM `tblcouriers` WHERE id = ?')->execute([$id]);
}

function correspondence_courier_is_issued(?array $corr): bool
{
    if (!$corr || ($corr['type'] ?? '') !== 'outgoing') {
        return false;
    }
    if (function_exists('outgoing_prep_is_open')) {
        return !outgoing_prep_is_open($corr);
    }
    return true;
}

/**
 * لقطة اسم المراسل على المعاملة.
 * الصادر المُصدر يُبقي الاسم والجوال إذا بقي نفس المراسل، حتى بعد تعديل الدليل أو حذفه.
 *
 * @return array{id:?int,name:?string,phone:?string}
 */
function correspondence_resolve_courier_snapshot(?array $existing, int $postedId, ?string $postedName, ?string $postedPhone): array
{
    $postedName = trim((string) $postedName);
    $postedPhone = trim((string) $postedPhone);
    $name = $postedName !== '' ? $postedName : null;
    $phone = $postedPhone !== '' ? $postedPhone : null;
    $id = $postedId > 0 ? $postedId : null;

    if (!$existing || !correspondence_courier_is_issued($existing)) {
        return ['id' => $id, 'name' => $name, 'phone' => $phone];
    }

    $prevId = (int) ($existing['courier_id'] ?? 0);
    $prevName = trim((string) ($existing['courier_name'] ?? ''));
    $prevPhone = trim((string) ($existing['courier_phone'] ?? ''));
    $same = ($postedId > 0 && $prevId > 0 && $postedId === $prevId)
        || $postedName === $prevName;
    if (!$same) {
        return ['id' => $id, 'name' => $name, 'phone' => $phone];
    }

    return [
        'id' => $prevId > 0 ? $prevId : $id,
        'name' => $prevName !== '' ? $prevName : null,
        'phone' => $prevPhone !== '' ? $prevPhone : null,
    ];
}

function outgoing_courier_remember(string $name, string $phone): void
{
    $name = trim($name);
    if ($name === '') {
        return;
    }
    ensure_outgoing_courier_schema();
    $phone = trim($phone) ?: null;
    try {
        $stmt = db()->prepare('SELECT id, phone FROM `tblcouriers` WHERE name = ? LIMIT 1');
        $stmt->execute([$name]);
        $row = $stmt->fetch();
        if ($row) {
            if ($phone !== null && trim((string) ($row['phone'] ?? '')) === '') {
                db()->prepare('UPDATE `tblcouriers` SET phone = ? WHERE id = ?')
                    ->execute([$phone, (int) $row['id']]);
            }
            return;
        }
        db()->prepare('INSERT INTO `tblcouriers` (`name`, `phone`) VALUES (?, ?)')
            ->execute([$name, $phone]);
    } catch (Throwable $e) {
    }
}
