<?php
/**
 * نطاق تقرير المعاملات من الطلب الحالي.
 * يضبط: $from, $to, $hijriFrom, $hijriTo, $deptId, $entityId, $toDeptId, $typeFilter, $where, $params, $whereSql
 *
 * فلاتر المسار كما في قوائم المراسلات:
 * - الصادر / الكل: issuing_department_id = موجه من، entity_id = موجهة إلى
 * - الوارد: entity_id = موجه من، department_id = موجهة إلى
 * - الداخلي: issuing_department_id = موجه من، department_id = موجهة إلى
 */
[$from, $to, $hijriFrom, $hijriTo] = reports_date_range_from_request();
$typeFilter = reports_type_from_request();

$issuingDeptId = (int) ($_GET['issuing_department_id'] ?? 0);
$toDeptId = (int) ($_GET['department_id'] ?? 0);
$entityId = (int) ($_GET['entity_id'] ?? 0);

// روابط قديمة للتقارير: department_id كان يُستخدم كموجه من (قبل بطاقة المسار)
if ($issuingDeptId <= 0 && $toDeptId > 0 && $typeFilter !== 'incoming' && $typeFilter !== 'internal') {
    $issuingDeptId = $toDeptId;
    $toDeptId = 0;
}

$deptId = $issuingDeptId > 0 ? $issuingDeptId : (($typeFilter === 'incoming' || $typeFilter === 'internal') ? $toDeptId : 0);

$where = ['c.corr_date BETWEEN ? AND ?'];
$params = [$from, $to];
[$scopeSql, $scopeParams] = department_scope_sql($user, 'c');
if ($scopeSql !== '') {
    $where[] = $scopeSql;
    foreach ($scopeParams as $p) {
        $params[] = $p;
    }
}

$canAccess = static function (int $id) use ($user): bool {
    if ($id <= 0) {
        return false;
    }
    if (function_exists('user_can_access_department')) {
        return user_can_access_department($id, $user);
    }
    return true;
};

if ($scopeSql !== '') {
    if ($issuingDeptId > 0 && !$canAccess($issuingDeptId)) {
        $issuingDeptId = 0;
    }
    if ($toDeptId > 0 && !$canAccess($toDeptId)) {
        $toDeptId = 0;
    }
    $deptId = $issuingDeptId > 0 ? $issuingDeptId : (($typeFilter === 'incoming' || $typeFilter === 'internal') ? $toDeptId : 0);
}

if ($issuingDeptId > 0) {
    $where[] = 'IFNULL(c.issuing_department_id, c.department_id) = ?';
    $params[] = $issuingDeptId;
}
if ($toDeptId > 0 && ($typeFilter === 'incoming' || $typeFilter === 'internal')) {
    $where[] = 'c.department_id = ?';
    $params[] = $toDeptId;
}
if ($entityId > 0) {
    $where[] = 'c.entity_id = ?';
    $params[] = $entityId;
}

reports_apply_type_filter($where, $params, $typeFilter);
$whereSql = implode(' AND ', $where);
