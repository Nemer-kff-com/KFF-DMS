<?php
require_once __DIR__ . '/../config/auth.php';
require_login();
$tab = (string) ($lookupTab ?? '');
if ($tab === '') {
    redirect(base_url('admin/lookups.php'));
}
$qs = ['tab' => $tab];
if (!empty($lookupNew)) {
    $qs['new'] = '1';
} else {
    $id = (int) ($_GET['id'] ?? 0);
    if ($id > 0) {
        $qs['edit'] = $id;
    }
}
redirect(base_url('admin/lookups.php?' . http_build_query($qs)));
