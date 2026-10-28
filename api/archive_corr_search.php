<?php
require_once __DIR__ . '/../config/auth.php';
$user = require_login();
header('Content-Type: application/json; charset=utf-8');

if (!user_can_archive_section('general', 'create', $user) && !user_can_archive_section('general', 'edit', $user)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'items' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$q = (string) ($_GET['q'] ?? '');
$found = archive_correspondence_search($user, $q, 25);
echo json_encode([
    'ok' => true,
    'items' => $found['rows'],
    'truncated' => !empty($found['truncated']),
], JSON_UNESCAPED_UNICODE);
