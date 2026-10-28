<?php
require_once __DIR__ . '/../config/auth.php';
$user = require_permission('admin.departments');
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id > 0) {
    redirect(base_url('admin/departments.php?edit=' . $id));
}
redirect(base_url('admin/departments.php'));
