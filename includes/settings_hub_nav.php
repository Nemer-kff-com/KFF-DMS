<?php
/**
 * شريط تنقل موحّد لإعدادات النظام / البريد / واتساب — روابط خفيفة بدون دمج النماذج.
 *
 * @var string $settingsHubActive org|numbering|approval_outgoing|approval_incoming|approval_internal|storage|email|whatsapp
 */
$settingsHubActive = (string) ($settingsHubActive ?? 'org');
$settingsHubItems = [
    [
        'key' => 'org',
        'href' => base_url('admin/settings.php?tab=org'),
        'icon' => 'bi-building',
        'label' => __('settings.tab_org'),
        'mode' => 'settings',
    ],
    [
        'key' => 'numbering',
        'href' => base_url('admin/settings.php?tab=numbering'),
        'icon' => 'bi-hash',
        'label' => __('settings.tab_numbering'),
        'mode' => 'settings',
    ],
    [
        'key' => 'approval_outgoing',
        'href' => base_url('admin/settings.php?tab=approval_outgoing'),
        'icon' => 'bi-shield-check',
        'label' => __('settings.tab_approval'),
        'mode' => 'settings',
    ],
    [
        'key' => 'approval_incoming',
        'href' => base_url('admin/settings.php?tab=approval_incoming'),
        'icon' => 'bi-inbox',
        'label' => __('settings.tab_approval_incoming'),
        'mode' => 'settings',
    ],
    [
        'key' => 'approval_internal',
        'href' => base_url('admin/settings.php?tab=approval_internal'),
        'icon' => 'bi-arrow-left-right',
        'label' => __('settings.tab_approval_internal'),
        'mode' => 'settings',
    ],
    [
        'key' => 'storage',
        'href' => base_url('admin/settings.php?tab=storage'),
        'icon' => 'bi-signpost-split',
        'label' => __('settings.tab_storage'),
        'mode' => 'settings',
    ],
    [
        'key' => 'email',
        'href' => base_url('admin/email_settings.php'),
        'icon' => 'bi-envelope-at',
        'label' => __('settings.tab_email'),
        'mode' => 'link',
    ],
    [
        'key' => 'whatsapp',
        'href' => base_url('admin/whatsapp_settings.php'),
        'icon' => 'bi-whatsapp',
        'label' => __('settings.tab_whatsapp'),
        'mode' => 'link',
    ],
];
$onSettingsPage = in_array($settingsHubActive, ['org', 'numbering', 'approval_outgoing', 'approval_incoming', 'approval_internal', 'storage'], true);
?>
<ul class="nav nav-tabs dms-import-tabs settings-tabs flex-nowrap overflow-auto mb-3 page-narrow" id="settingsHubNav" role="tablist">
    <?php foreach ($settingsHubItems as $item):
        $isActive = $settingsHubActive === $item['key'];
        $usePane = $onSettingsPage && ($item['mode'] ?? '') === 'settings';
        ?>
        <li class="nav-item" role="presentation">
            <?php if ($usePane): ?>
                <button class="nav-link <?= $isActive ? 'active' : '' ?>"
                        id="tab-<?= e($item['key']) ?>-btn"
                        data-bs-toggle="tab"
                        data-bs-target="#tab-<?= e($item['key']) ?>"
                        type="button"
                        role="tab"
                        data-settings-tab="<?= e($item['key']) ?>"
                        aria-controls="tab-<?= e($item['key']) ?>"
                        aria-selected="<?= $isActive ? 'true' : 'false' ?>">
                    <i class="bi <?= e($item['icon']) ?> me-1"></i><?= e($item['label']) ?>
                </button>
            <?php else: ?>
                <a class="nav-link <?= $isActive ? 'active' : '' ?>"
                   href="<?= e($item['href']) ?>"
                   role="tab"
                   aria-selected="<?= $isActive ? 'true' : 'false' ?>">
                    <i class="bi <?= e($item['icon']) ?> me-1"></i><?= e($item['label']) ?>
                </a>
            <?php endif; ?>
        </li>
    <?php endforeach; ?>
</ul>
