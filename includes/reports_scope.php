<?php
/**
 * نطاق تقرير المعاملات من الطلب الحالي.
 * يضبط: $from, $to, $hijriFrom, $hijriTo, $deptId, $typeFilter, $where, $params, $whereSql
 */
[$from, $to, $hijriFrom, $hijriTo] = reports_date_range_from_request();
$deptId = (int) ($_GET['department_id'] ?? 0);
$typeFilter = reports_type_from_request();

$where = ['c.corr_date BETWEEN ? AND ?'];
$params = [$from, $to];
[$scopeSql, $scopeParams] = department_scope_sql($user, 'c');
if ($scopeSql !== '') {
    $where[] = $scopeSql;
    foreach ($scopeParams as $p) {
        $params[] = $p;
    }
    if ($deptId > 0 && function_exists('user_can_access_department') && user_can_access_department($deptId, $user)) {
        $where[] = 'c.department_id = ?';
        $params[] = $deptId;
    } else {
        $deptId = 0;
    }
} elseif ($deptId > 0) {
    $where[] = 'c.department_id = ?';
    $params[] = $deptId;
}
reports_apply_type_filter($where, $params, $typeFilter);
$whereSql = implode(' AND ', $where);
