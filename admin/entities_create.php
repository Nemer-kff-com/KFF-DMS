<?php
require_once __DIR__ . '/../config/auth.php';
require_permission('entities.manage');
redirect(base_url('admin/entities.php?add=1'));
