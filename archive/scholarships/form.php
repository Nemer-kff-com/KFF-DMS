<?php
require_once __DIR__ . '/../../config/auth.php';
$q = $_GET ? '?' . http_build_query($_GET) : '';
redirect(base_url('archive/scholarships/create.php') . $q);
