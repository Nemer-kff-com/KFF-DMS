<?php
require_once __DIR__ . '/../config/auth.php';
require_permission('admin.logs');
$qs = $_GET;
$qs['tab'] = 'activity';
redirect(base_url('admin/system_logs.php?' . http_build_query($qs)));
