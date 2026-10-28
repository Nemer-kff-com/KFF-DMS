<?php
require_once __DIR__ . '/../config/auth.php';

if (current_user()) {
    redirect(base_url('index.php'));
}

$error = '';
$success = '';
if (!empty($_GET['reset'])) {
    $success = __('auth.reset_ok');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($email === '' || $password === '') {
        $error = __('auth.required');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = __('auth.invalid_email');
    } else {
        try {
            $lock = dms_check_login_lockout($email);
            if ($lock['locked']) {
                $error = str_replace(':minutes', (string) $lock['minutes'], __('auth.locked'));
                log_security('LOGIN_LOCKOUT', 'CRITICAL', 'email=' . mb_substr($email, 0, 120));
            } elseif (attempt_login($email, $password)) {
                redirect(base_url('index.php'));
            } else {
                $error = __('auth.invalid');
            }
        } catch (PDOException $e) {
            error_log('DMS login DB error: ' . $e->getMessage());
            $error = __('auth.service_unavailable');
        }
    }
}
$lang = dms_lang_code();
$dir = dms_page_dir();
?>
<!DOCTYPE html>
<html lang="<?= e($lang) ?>" dir="<?= e($dir) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= e(__('auth.login_title')) ?> | <?= e(dms_org_display_name()) ?></title>
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
        <?php if (org_logo_url()): ?>
            <img src="<?= e(org_logo_url()) ?>" alt="<?= e(dms_org_display_name()) ?>" class="login-logo mb-3">
        <?php endif; ?>
        <?php render_org_brand_text('login', 'brand-gold mb-1', 'small text-muted mb-2'); ?>
        <h1 class="h4 text-dark mb-1"><?= e(__('app.name')) ?></h1>
    </div>
    <form method="post" autocomplete="off">
        <?= csrf_field() ?>
        <div class="mb-3">
            <label class="form-label"><?= e(__('auth.email')) ?></label>
            <input type="email" name="email" class="form-control" required autofocus dir="ltr"
                   placeholder="name@example.com"
                   value="<?= e($_POST['email'] ?? '') ?>">
        </div>
        <div class="mb-3">
            <div class="login-pass-row">
                <label class="form-label"><?= e(__('auth.password')) ?></label>
                <a href="<?= e(base_url('auth/forgot_password.php')) ?>" class="small"><?= e(__('auth.forgot')) ?></a>
            </div>
            <input type="password" name="password" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-kff w-100"><?= e(__('action.login')) ?></button>
    </form>
</div>
<?php
$flash = null;
if ($error !== '') {
    $flash = ['type' => 'danger', 'message' => $error];
} elseif ($success !== '') {
    $flash = ['type' => 'success', 'message' => $success];
}
require __DIR__ . '/../includes/swal_boot.php';
?>
</body>
</html>
