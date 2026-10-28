<?php
require_once __DIR__ . '/../config/auth.php';
require_login();
redirect(base_url('index.php'));
