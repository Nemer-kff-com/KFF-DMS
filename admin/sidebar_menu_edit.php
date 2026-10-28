<?php
require_once __DIR__ . '/../config/auth.php';
require_permission('admin.sidebar');
$key = trim((string) ($_GET['key'] ?? ''));
$params = $key !== '' ? '?open_edit=' . urlencode($key) : '';
redirect(base_url('admin/sidebar_menu.php' . $params));
