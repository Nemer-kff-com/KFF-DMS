<?php
require_once __DIR__ . '/../config/auth.php';
require_permission('admin.sidebar');
redirect(base_url('admin/sidebar_menu.php?open_group=1'));
