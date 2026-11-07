<?php
/**
 * روابط من تقرير الفترة إلى قوائم المراسلات بنفس الفلاتر الزمنية/المسار.
 *
 * @param array{
 *   from?:string,to?:string,hijri_from?:string,hijri_to?:string,
 *   issuing_department_id?:int,department_id?:int,entity_id?:int,type?:string
 * } $scope
 * @param array<string,scalar|null> $extra
 */
function reports_list_query(array $scope, array $extra = []): array
{
    $q = [];
    foreach (['from', 'to', 'hijri_from', 'hijri_to'] as $key) {
        $val = trim((string) ($scope[$key] ?? ''));
        if ($val !== '') {
            $q[$key] = $val;
        }
    }

    $type = (string) ($scope['type'] ?? 'all');
    $issuing = (int) ($scope['issuing_department_id'] ?? 0);
    $toDept = (int) ($scope['department_id'] ?? 0);
    $entity = (int) ($scope['entity_id'] ?? 0);

    if ($issuing > 0) {
        $q['issuing_department_id'] = $issuing;
    }
    if ($entity > 0) {
        $q['entity_id'] = $entity;
    }
    if ($toDept > 0 && ($type === 'incoming' || $type === 'internal')) {
        $q['department_id'] = $toDept;
    }

    foreach ($extra as $key => $val) {
        if ($val === null || $val === '' || $val === false) {
            continue;
        }
        $q[$key] = $val;
    }

    return $q;
}

/**
 * @param array<string,mixed> $scope
 * @param array<string,scalar|null> $extra
 */
function reports_list_url(string $path, array $scope, array $extra = []): string
{
    $q = reports_list_query($scope, $extra);
    $qs = $q !== [] ? ('?' . http_build_query($q)) : '';
    return base_url($path . $qs);
}

/**
 * نطاق التقرير الحالي كحزمة للروابط.
 *
 * @return array<string,mixed>
 */
function reports_current_link_scope(
    string $from,
    string $to,
    string $hijriFrom,
    string $hijriTo,
    int $issuingDeptId,
    int $toDeptId,
    int $entityId,
    string $typeFilter
): array {
    return [
        'from' => $from,
        'to' => $to,
        'hijri_from' => $hijriFrom,
        'hijri_to' => $hijriTo,
        'issuing_department_id' => $issuingDeptId,
        'department_id' => $toDeptId,
        'entity_id' => $entityId,
        'type' => $typeFilter,
    ];
}
