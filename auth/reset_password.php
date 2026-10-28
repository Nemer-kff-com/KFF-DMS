<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/password_reset.php';

if (current_user()) {
    redirect(base_url('index.php'));
}

$token = trim($_POST['token'] ?? $_GET['token'] ?? '');
$error = '';
$entry = $token !== '' ? dms_password_reset_get($token) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $token = trim($_POST['token'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');
    $entry = dms_password_reset_get($token);

    if (!$entry) {
        $error = __('auth.reset_invalid');
    } elseif (!dms_password_meets_policy($password)) {
        $error = __('auth.password_min');
    } elseif ($password !== $confirm) {
        $error = __('auth.password_mismatch');
    } elseif (!dms_password_reset_apply($token, $password)) {
        $error = __('auth.reset_failed');
    } else {
        redirect(base_url('auth/login.php?reset=1'));
    }
} elseif ($token === '') {
    $error = __('auth.reset_missing');
} elseif (!$entry) {
    $error = __('auth.reset_invalid');
}
$lang = dms_lang_code();
$dir = dms_page_dir();
?>
<!DOCTYPE html>
<html lang="<?= e($lang) ?>" dir="<?= e($dir) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= e(__('auth.reset_title')) ?> | <?= e(dms_org_display_name()) ?></title>
    <link href="<?= e(dms_bootstrap_css()) ?>" rel="stylesheet">
    <?php require __DIR__ . '/../includes/head_assets.php'; ?>
    <?= csrf_head_tags() ?>
    <?php require __DIR__ . '/../includes/login_head_fallback.php'; ?>
</head>
<body class="login-page">
<div class="login-lang">
    <div class="btn-group btn-group-sm">
        <a href="<?= e(dms_lang_url('ar')) ?>" class="btn btn-sm <?= $lang === 'ar' ? 'btn-kff' : 'btn-outline-secondary' ?>">ع</a>
        <a href="<?= e(dms_lang_url('en')) ?>" class="btn btn-sm <?= $lang === 'en' ? 'btn-kff' : 'btn-outline-secondary' ?>">EN</a>
    </div>
</div>
<div class="login-card">
    <div class="text-center mb-4">
        <?php render_org_brand_text('login', 'brand-gold mb-1', 'small text-muted mb-1'); ?>
        <h1 class="h5 text-dark mb-0"><?= e(__('auth.reset_title')) ?></h1>
    </div>
    <?php if ($error && (!$entry || $_SERVER['REQUEST_METHOD'] !== 'POST')): ?>
        <a href="<?= e(base_url('auth/forgot_password.php')) ?>" class="btn btn-kff w-100"><?= e(__('auth.request_new_link')) ?></a>
        <a href="<?= e(base_url('auth/login.php')) ?>" class="btn btn-outline-secondary w-100 mt-2"><?= e(__('action.login')) ?></a>
    <?php else: ?>
        <form method="post" autocomplete="off" id="resetForm">
            <?= csrf_field() ?>
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <div class="mb-3">
                <label class="form-label"><?= e(__('auth.new_password')) ?></label>
                <input type="password" name="password" id="password" class="form-control" required minlength="6">
            </div>
            <div class="mb-3">
                <label class="form-label"><?= e(__('auth.confirm_password')) ?></label>
                <input type="password" name="confirm_password" id="confirm_password" class="form-control" required minlength="6">
            </div>
            <button type="submit" class="btn btn-kff w-100"><?= e(__('auth.save_password')) ?></button>
        </form>
        <script>
        document.getElementById('resetForm').addEventListener('submit', function (e) {
            var p = document.getElementById('password').value;
            var c = document.getElementById('confirm_password').value;
            if (p.length < <?= (int) dms_password_min_length() ?>) {
                e.preventDefault();
                if (window.DmsSwal) DmsSwal.error(<?= json_encode(__('auth.password_min'), JSON_UNESCAPED_UNICODE) ?>);
                else alert(<?= json_encode(__('auth.password_min'), JSON_UNESCAPED_UNICODE) ?>);
                return;
            }
            if (p !== c) {
                e.preventDefault();
                if (window.DmsSwal) DmsSwal.error(<?= json_encode(__('auth.password_mismatch'), JSON_UNESCAPED_UNICODE) ?>);
                else alert(<?= json_encode(__('auth.password_mismatch'), JSON_UNESCAPED_UNICODE) ?>);
            }
        });
        </script>
    <?php endif; ?>
</div>
<?php
$flash = null;
if ($error !== '') {
    $flash = ['type' => 'danger', 'message' => $error];
}
require __DIR__ . '/../includes/swal_boot.php';
?>
</body>
</html>
