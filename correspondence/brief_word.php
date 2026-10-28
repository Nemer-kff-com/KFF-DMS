<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/briefing.php';
$user = require_login();
$id = (int) ($_GET['id'] ?? 0);
$corr = get_correspondence($id);
if (!$corr || !user_can_access_correspondence($corr, $user)) {
    exit(__('common.not_found'));
}
briefing_require_incoming($corr);
$brief = get_correspondence_brief($id);
if (!$brief) {
    exit(__('common.not_found'));
}

$dirState = briefing_dir_order_from_request();
briefing_send_word($corr, $brief, $dirState['order'] === 'from');
