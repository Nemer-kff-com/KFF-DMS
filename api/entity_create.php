<?php
/**
 * إنشاء جهة مراسلة سريعاً من نافذة اختيار الجهة (وارد/صادر).
 * يقبل نفس حقول نموذج جهات المراسلة في الإدارة.
 */
require_once __DIR__ . '/../config/auth.php';

$user = require_login();
header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => __('auth.forbidden')], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    verify_csrf();
    ensure_entities_schema();

    if (!user_can_create_entity($user)) {
        throw new RuntimeException(__('auth.forbidden'));
    }
    if (!entities_department_scope_available()) {
        throw new RuntimeException(__('entities.migration_pending'));
    }

    $isIncoming = ($_POST['corr_type'] ?? 'incoming') === 'incoming';
    $canManageAll = function_exists('user_manages_all_entities') && user_manages_all_entities($user);
    $isSystemAdmin = function_exists('can_manage_users') && can_manage_users($user);

    $nameAr = trim((string) ($_POST['name_ar'] ?? ''));
    $directedTo = trim((string) ($_POST['directed_to'] ?? ''));
    $jobTitle = trim((string) ($_POST['job_title'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $mobile = trim((string) ($_POST['mobile'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $address = trim((string) ($_POST['address'] ?? ''));
    $location = entity_maps_url_normalize(trim((string) ($_POST['location'] ?? '')));
    $notes = trim((string) ($_POST['notes'] ?? ''));
    $titlePrefix = entity_title_prefix_from_input($_POST);
    $honorific = entity_honorific_from_input($_POST);

    if ($nameAr === '') {
        throw new InvalidArgumentException(__('entities.name_required'));
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException(__('entities.email_invalid'));
    }
    if ($location !== '' && !entity_maps_url_valid($location)) {
        throw new InvalidArgumentException(__('entities.location_invalid'));
    }

    $departmentId = null;
    if ($canManageAll) {
        $rawDept = trim((string) ($_POST['department_id'] ?? ''));
        if ($rawDept !== '' && $rawDept !== '0') {
            $departmentId = (int) $rawDept;
            if ($departmentId < 1 || !department_exists_id($departmentId)) {
                throw new InvalidArgumentException(__('entities.invalid_department'));
            }
        }
    } else {
        $departmentId = function_exists('resolve_assigned_department_id')
            ? resolve_assigned_department_id($user, $_POST['department_id'] ?? 0, false)
            : (int) ($user['department_id'] ?? 0);
        if ($departmentId < 1) {
            throw new InvalidArgumentException(__('entities.department_required'));
        }
    }

    if (entity_name_exists_in_scope($nameAr, $departmentId)) {
        throw new InvalidArgumentException(__('entities.duplicate'));
    }

    $nextSort = 10;
    try {
        $nextSort = (int) db()->query('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM `tblentities`')->fetchColumn();
    } catch (Throwable $e) {
        $nextSort = 10;
    }
    $postedSort = trim((string) ($_POST['sort_order'] ?? ''));
    if ($isSystemAdmin && $postedSort !== '' && ctype_digit($postedSort) && (int) $postedSort > 0) {
        $sortOrder = (int) $postedSort;
    } else {
        $sortOrder = $nextSort;
    }
    if ($sortOrder < 0) {
        $sortOrder = $nextSort;
    }
    if (entity_sort_order_taken($sortOrder)) {
        if (!$isSystemAdmin || $postedSort === '') {
            $sortOrder = $nextSort;
            while (entity_sort_order_taken($sortOrder)) {
                $sortOrder += 10;
            }
        } else {
            throw new InvalidArgumentException(__('entities.sort_duplicate'));
        }
    }

    db()->prepare(
        "INSERT INTO `tblentities`
         (name_ar, directed_to, title_prefix, job_title, honorific, phone, mobile, email, address, location, notes, department_id, sort_order)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)"
    )->execute([
        $nameAr,
        $directedTo !== '' ? $directedTo : null,
        $titlePrefix !== '' ? $titlePrefix : null,
        $jobTitle !== '' ? $jobTitle : null,
        $honorific !== '' ? $honorific : null,
        $phone !== '' ? $phone : null,
        $mobile !== '' ? $mobile : null,
        $email !== '' ? $email : null,
        $address !== '' ? $address : null,
        $location !== '' ? $location : null,
        $notes !== '' ? $notes : null,
        $departmentId,
        $sortOrder,
    ]);

    $newId = (int) db()->lastInsertId();
    $rows = entities_list(true, $user, $newId, [
        'ids' => [$newId],
        'limit' => 1,
    ]);
    $row = $rows[0] ?? get_entity($newId);
    if (!$row) {
        throw new RuntimeException(__('common.not_found'));
    }

    echo json_encode([
        'ok' => true,
        'message' => __('entities.created'),
        'item' => entity_picker_payload($row, $isIncoming),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
