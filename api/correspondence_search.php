<?php
/**
 * بحث معاملات للربط: وارد يبحث في الصادر، وصادر يبحث في الوارد.
 * خفيف مثل بحث الجهات: حد 25 صفاً بلا تحميل القائمة كاملة.
 * /api/correspondence_search.php?for=incoming&q=...&exclude_id=12
 */
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/correspondence.php';

$user = require_login();
header('Content-Type: application/json; charset=utf-8');

if (!can_edit_correspondence($user)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'items' => [], 'total' => 0, 'limit' => 25], JSON_UNESCAPED_UNICODE);
    exit;
}

$forType = ($_GET['for'] ?? '') === 'outgoing' ? 'outgoing' : 'incoming';
$q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100, 'UTF-8');
$excludeId = max(0, (int) ($_GET['exclude_id'] ?? 0));
$limit = 25;

$found = linkable_correspondence_search($forType, $user, [
    'q' => $q,
    'exclude_id' => $excludeId,
    'limit' => $limit,
]);

$items = [];
foreach ($found['rows'] as $row) {
    $items[] = linkable_correspondence_payload($row);
}

$total = count($items);
if (!empty($found['truncated'])) {
    $total = $limit + 1;
}

echo json_encode([
    'ok' => true,
    'total' => $total,
    'limit' => $limit,
    'items' => $items,
], JSON_UNESCAPED_UNICODE);
