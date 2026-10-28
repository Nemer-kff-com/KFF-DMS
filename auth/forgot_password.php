<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/password_reset.php';

if (current_user()) {
    redirect(base_url('index.php'));
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = trim($_POST['email'] ?? '');
    $emailNorm = dms_normalize_email($email);
    $lockKey = $emailNorm !== '' ? $emailNorm : ('ip:' . client_ip());

    if ($emailNorm === '') {
        $error = __('auth.email_required');
    } elseif (!filter_var($emailNorm, FILTER_VALIDATE_EMAIL)) {
        $error = __('auth.invalid_email');
        } else {
            $lock = dms_check_login_lockout($lockKey);
            if ($lock['locked']) {
                $error = str_replace(':minutes', (string) $lock['minutes'], __('auth.locked'));
            } else {
                $issued = dms_password_reset_issue($emailNorm);
                if ($issued) {
                    $mailError = null;
                    if (!dms_password_reset_send($issued['user'], $issued['token'], $mailError)) {
                        error_log('DMS password reset mail error: ' . ($mailError ?? 'unknown'));
                    }
                    dms_clear_login_attempts($lockKey);
                } else {
                    dms_record_failed_login($lockKey);
                }
                // رسالة موحّدة لمنع كشف وجود البريد
                $success = __('auth.reset_sent_generic');
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
    <title><?= e(__('auth.forgot_title')) ?> | <?= e(dms_org_display_name()) ?></title>
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
            <img src="<?= e(org_logo_url()) ?>" alt="" class="login-logo mb-3">
        <?php endif; ?>
        <?php render_org_brand_text('login', 'brand-gold mb-1', 'small text-muted mb-1'); ?>
        <h1 class="h5 text-dark mb-1"><?= e(__('auth.forgot_title')) ?></h1>
        <p class="text-muted small mb-0"><?= e(__('auth.forgot_hint')) ?></p>
    </div>
    <?php if ($success): ?>
        <a href="<?= e(base_url('auth/login.php')) ?>" class="btn btn-kff w-100"><?= e(__('auth.back_login')) ?></a>
    <?php else: ?>
        <form method="post" autocomplete="off">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label"><?= e(__('auth.email')) ?></label>
                <input type="email" name="email" class="form-control" required autofocus dir="ltr"
                       value="<?= e($_POST['email'] ?? '') ?>">
            </div>
            <button type="submit" class="btn btn-kff w-100"><?= e(__('auth.send_reset')) ?></button>
            <a href="<?= e(base_url('auth/login.php')) ?>" class="btn btn-outline-secondary w-100 mt-2"><?= e(__('action.back')) ?></a>
        </form>
    <?php endif; ?>
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
