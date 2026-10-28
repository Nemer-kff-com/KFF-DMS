<?php
if (!isset($pageTitle)) {
    $pageTitle = __('app.name');
}
$user = current_user();
$flash = get_flash();
$lang = dms_lang_code();
$dir = dms_page_dir();
$notifyUnread = 0;
$notifyPreview = [];
if ($user && function_exists('dms_generate_due_notifications')) {
    dms_generate_due_notifications($user);
    $notifyUnread = dms_notifications_unread_count($user);
    $notifyPreview = dms_notifications_list($user, 6);
}
if ($user && function_exists('outgoing_approval_run_escalations')) {
    outgoing_approval_run_escalations($user);
}
$topbarSearch = trim((string) ($_GET['q'] ?? ''));
if (($pageTitle ?? '') === __('search.title')) {
    $topbarSearch = trim((string) ($_GET['q'] ?? $topbarSearch));
}
?>
<!DOCTYPE html>
<html lang="<?= e($lang) ?>" dir="<?= e($dir) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= e($pageTitle) ?> | <?= e(dms_org_display_name()) ?></title>
    <link href="<?= e(dms_bootstrap_css()) ?>" rel="stylesheet">
    <?php require __DIR__ . '/head_assets.php'; ?>
    <?= csrf_head_tags() ?>
    <?php if (!empty($pageExtraHead)): ?>
    <?= $pageExtraHead ?>
    <?php endif; ?>
    <?php if (!dms_is_rtl()): ?>
    <style>
      .sidebar { right: auto; left: 0; box-shadow: 4px 0 24px rgba(59,79,110,.08); border-left: none; border-right: 1px solid var(--kff-border); }
      .sidebar .nav-link { border-right: none; border-left: 3px solid transparent; }
      .sidebar .nav-link:hover, .sidebar .nav-link.active { border-left-color: var(--kff-accent); border-right-color: transparent; }
      .main-wrap { margin-right: 0; margin-left: var(--sidebar-width); }
      .sidebar-rail-btn { right: auto; left: calc(var(--sidebar-width) - 15px); border-radius: 0 10px 10px 0; box-shadow: 2px 0 10px rgba(59,79,110,.15); }
      body.sidebar-collapsed .sidebar { transform: translateX(-100%); }
      body.sidebar-collapsed .main-wrap { margin-left: 0; }
      body.sidebar-collapsed .sidebar-rail-btn { left: 0; border-radius: 0 10px 10px 0; }
      @media (max-width: 992px) {
        .sidebar { transform: translateX(-100%); }
        .sidebar.open { transform: translateX(0); }
        .main-wrap { margin-left: 0; }
        .sidebar-rail-btn { display: none !important; }
      }
    </style>
    <?php endif; ?>
</head>
<body>
<?php
$dmsMissingAssets = [];
if ($user && (($user['role'] ?? '') === 'admin') && function_exists('dms_critical_assets_status')) {
    $dmsMissingAssets = array_values(array_filter(
        dms_critical_assets_status(),
        static fn(array $row): bool => empty($row['ok'])
    ));
}
if ($dmsMissingAssets): ?>
<div class="dms-assets-warn no-print" style="background:#fff3cd;border-bottom:1px solid #ffecb5;color:#664d03;padding:.65rem 1rem;font-size:.9rem;position:relative;z-index:200">
    <strong>ملفات تنسيق ناقصة بعد النقل:</strong>
    <?= e(implode(' ، ', array_column($dmsMissingAssets, 'path'))) ?>
    — انسخ مجلدات <code>assets/css</code> و <code>assets/fonts</code> و <code>assets/vendor</code> كاملة.
    <a href="<?= e(base_url('admin/check_assets.php')) ?>">تفاصيل الفحص</a>
</div>
<?php endif; ?>
<script>
try {
  if (window.matchMedia('(min-width: 993px)').matches && localStorage.getItem('dms_sidebar_collapsed') === '1') {
    document.body.classList.add('sidebar-collapsed');
  }
} catch (e) {}
</script>
<button type="button" class="sidebar-backdrop" id="sidebarBackdrop" aria-label="Close menu"></button>
<div class="app-shell">
<?php require __DIR__ . '/sidebar.php'; ?>
<?php if ($user): ?>
<button type="button" class="sidebar-rail-btn" id="sidebarRailToggle"
        aria-expanded="true"
        aria-controls="sidebar"
        data-hide-label="<?= e(__('nav.hide_sidebar')) ?>"
        data-show-label="<?= e(__('nav.show_sidebar')) ?>"
        title="<?= e(__('nav.hide_sidebar')) ?>"
        aria-label="<?= e(__('nav.hide_sidebar')) ?>">
    <i class="bi bi-chevron-right" aria-hidden="true"></i>
</button>
<?php endif; ?>
<div class="main-wrap">
    <div class="topbar">
        <div class="topbar-inner">
            <div class="topbar-leading">
                <button type="button" class="btn btn-sm btn-outline-secondary d-lg-none flex-shrink-0" id="sidebarToggle" aria-label="Menu">
                    <i class="bi bi-list"></i>
                </button>
                <div class="topbar-title min-w-0">
                    <div class="fw-semibold text-truncate"><?= e($pageTitle) ?></div>
                    <div class="small text-muted text-truncate">
                        <?php
                        $topbarOrg = org_visible_names('pages');
                        echo e($topbarOrg['primary'] ?? __('app.name'));
                        ?>
                    </div>
                </div>
                <?php if ($user): ?>
                    <form class="topbar-search d-none d-md-flex" method="get" action="<?= e(base_url('search/index.php')) ?>" role="search">
                        <input type="search" name="q" value="<?= e($topbarSearch) ?>" placeholder="<?= e(__('search.placeholder')) ?>" aria-label="<?= e(__('search.title')) ?>">
                        <button type="submit" title="<?= e(__('action.search')) ?>"><i class="bi bi-search"></i></button>
                    </form>
                <?php endif; ?>
            </div>
            <div class="d-flex align-items-center gap-2 gap-md-3 topbar-actions flex-shrink-0">
                <?php if ($user): ?>
                    <a class="btn btn-sm btn-outline-secondary topbar-search-mobile d-md-none"
                       href="<?= e(base_url('search/index.php')) ?>"
                       title="<?= e(__('action.search')) ?>"
                       aria-label="<?= e(__('action.search')) ?>">
                        <i class="bi bi-search"></i>
                    </a>
                    <div class="dropdown">
                        <a class="btn btn-sm btn-outline-secondary topbar-notify-btn position-relative" href="<?= e(base_url('notifications/index.php')) ?>"
                           data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" title="<?= e(__('notify.title')) ?>"
                           onclick="if(window.bootstrap){event.preventDefault();}">
                            <i class="bi bi-bell"></i>
                            <?php if ($notifyUnread > 0): ?>
                                <span class="topbar-notify-badge"><?= $notifyUnread > 9 ? '9+' : (int) $notifyUnread ?></span>
                            <?php endif; ?>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end topbar-notify-menu">
                            <div class="dropdown-header d-flex justify-content-between">
                                <span><?= e(__('notify.title')) ?></span>
                                <?php if ($notifyUnread > 0): ?>
                                    <span class="badge text-bg-danger"><?= (int) $notifyUnread ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if (!$notifyPreview): ?>
                                <div class="px-3 py-2 small text-muted"><?= e(__('notify.empty')) ?></div>
                            <?php endif; ?>
                            <?php foreach ($notifyPreview as $n): ?>
                                <a class="dropdown-item <?= empty($n['is_read']) ? 'fw-semibold' : '' ?>" href="<?= e(base_url('notifications/go.php?id=' . (int) $n['id'])) ?>">
                                    <?php
                                    $nTitle = function_exists('dms_notification_display_text')
                                        ? dms_notification_display_text((string) ($n['title'] ?? ''))
                                        : (string) ($n['title'] ?? '');
                                    $nBody = function_exists('dms_notification_display_text')
                                        ? dms_notification_display_text((string) ($n['body'] ?? ''))
                                        : (string) ($n['body'] ?? '');
                                    ?>
                                    <span class="d-block"><?= e($nTitle) ?></span>
                                    <?php if ($nBody !== ''): ?>
                                    <span class="small text-muted"><?= e(mb_strlen($nBody) > 90 ? mb_substr($nBody, 0, 90) . '…' : $nBody) ?></span>
                                    <?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-center" href="<?= e(base_url('notifications/index.php')) ?>"><?= e(__('notify.view_all')) ?></a>
                        </div>
                    </div>
                <?php endif; ?>
                <div class="btn-group btn-group-sm" role="group" aria-label="Language">
                    <a href="<?= e(dms_lang_url('ar')) ?>" class="btn <?= $lang === 'ar' ? 'btn-kff' : 'btn-outline-secondary' ?>">ع</a>
                    <a href="<?= e(dms_lang_url('en')) ?>" class="btn <?= $lang === 'en' ? 'btn-kff' : 'btn-outline-secondary' ?>">EN</a>
                </div>
                <?php if ($user): ?>
                    <?php
                    $topbarPhoto = function_exists('user_photo_url') ? user_photo_url($user) : '';
                    ?>
                    <a href="<?= e(base_url('auth/profile.php')) ?>" class="topbar-profile text-decoration-none text-reset d-flex align-items-center gap-2" title="<?= e(__('nav.profile')) ?>">
                        <?php if ($topbarPhoto !== ''): ?>
                            <img src="<?= e($topbarPhoto) ?>" alt="" class="topbar-avatar">
                        <?php else: ?>
                            <span class="topbar-avatar topbar-avatar-fallback"><i class="bi bi-person"></i></span>
                        <?php endif; ?>
                        <div class="text-end small topbar-user d-none d-md-block">
                            <div class="fw-semibold"><?= e(user_display_name($user)) ?></div>
                            <div class="text-muted"><?= e(user_role_label($user)) ?><?php
                                $deptLabel = department_display_name($user, 'department_name', 'department_name_en');
                                if ($deptLabel !== '') {
                                    echo ' — ' . e($deptLabel);
                                }
                            ?></div>
                        </div>
                    </a>
                    <a href="<?= e(base_url('auth/logout.php')) ?>" class="btn btn-sm btn-outline-danger topbar-logout" title="<?= e(__('action.logout')) ?>" aria-label="<?= e(__('action.logout')) ?>">
                        <i class="fas fa-sign-out-alt" aria-hidden="true"></i>
                        <span class="visually-hidden"><?= e(__('action.logout')) ?></span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="content">
        <?php /* الرسائل عبر SweetAlert — انظر includes/swal_boot.php */ ?>
