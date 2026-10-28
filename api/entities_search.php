<?php
/**
 * بحث الجهات لقائمة الاختيار في نماذج المراسلات.
 * الاستخدام: /api/entities_search.php?q=...&type=outgoing&include_id=12
 */
require_once __DIR__ . '/../config/auth.php';

$user = require_login();
header('Content-Type: application/json; charset=utf-8');

$q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100, 'UTF-8');
$isIncoming = ($_GET['type'] ?? 'incoming') === 'incoming';
$includeId = max(0, (int) ($_GET['include_id'] ?? 0));
$limit = 25;

$ids = [];
foreach (explode(',', (string) ($_GET['ids'] ?? '')) as $id) {
    $id = (int) $id;
    if ($id > 0) {
        $ids[$id] = $id;
    }
}
$ids = array_values($ids);
if (count($ids) > 200) {
    $ids = array_slice($ids, 0, 200);
}

$truncated = false;
if ($ids) {
    $rows = entities_list(true, $user, null, [
        'ids' => $ids,
        'limit' => 200,
        'picker' => true,
    ]);
    $total = count($rows);
} else {
    // LIMIT+1 بدل COUNT الثقيل — نفس أسلوب بحث الربط بالمعاملات
    $fetch = $limit + 1;
    $rows = entities_list(true, $user, $includeId ?: null, [
        'q' => $q,
        'limit' => $fetch,
        'picker' => true,
    ]);
    $truncated = count($rows) > $limit;
    if ($truncated) {
        $rows = array_slice($rows, 0, $limit);
    }
    $total = $truncated ? ($limit + 1) : count($rows);
}

$items = [];
foreach ($rows as $row) {
    $items[] = entity_picker_payload($row, $isIncoming);
}

echo json_encode([
    'ok' => true,
    'total' => $total,
    'limit' => $limit,
    'truncated' => $truncated,
    'items' => $items,
], JSON_UNESCAPED_UNICODE);
