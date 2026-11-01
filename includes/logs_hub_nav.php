<?php
/**
 * تبويبات صفحة السجلات والجلسات.
 *
 * @var string $logsHubActive activity|security|sessions
 */
$logsHubActive = (string) ($logsHubActive ?? 'activity');
$logsHubItems = [
    [
        'key' => 'activity',
        'href' => base_url('admin/system_logs.php?tab=activity'),
        'icon' => 'bi-activity',
        'label' => __('nav.activity_log'),
    ],
    [
        'key' => 'security',
        'href' => base_url('admin/system_logs.php?tab=security'),
        'icon' => 'bi-shield-exclamation',
        'label' => __('nav.security_log'),
    ],
    [
        'key' => 'sessions',
        'href' => base_url('admin/system_logs.php?tab=sessions'),
        'icon' => 'bi-people',
        'label' => __('nav.active_sessions'),
    ],
];
?>
<ul class="nav nav-tabs dms-import-tabs flex-nowrap overflow-auto mb-3 page-narrow" id="logsHubNav" role="tablist">
    <?php foreach ($logsHubItems as $item):
        $isActive = $logsHubActive === $item['key'];
        ?>
        <li class="nav-item" role="presentation">
            <a class="nav-link <?= $isActive ? 'active' : '' ?>"
               href="<?= e($item['href']) ?>"
               role="tab"
               aria-selected="<?= $isActive ? 'true' : 'false' ?>">
                <i class="<?= e($item['icon']) ?> me-1"></i><?= e($item['label']) ?>
            </a>
        </li>
    <?php endforeach; ?>
</ul>
