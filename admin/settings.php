<?php
require_once __DIR__ . '/../config/auth.php';
$user = require_permission('admin.settings');
ensure_org_settings();
ensure_users_profile_schema();
$pageTitle = __('nav.settings');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? 'save';

    try {
        if ($action === 'remove_logo') {
            $old = org_logo_path();
            if ($old !== '') {
                $oldPath = __DIR__ . '/../' . $old;
                if (is_file($oldPath)) {
                    @unlink($oldPath);
                }
            }
            set_setting('org_logo', '');
            flash('success', __('settings.logo_removed'));
            $tab = trim((string) ($_POST['settings_tab'] ?? 'org'));
            if ($tab === 'approval') {
                $tab = 'approval_outgoing';
            }
            if (in_array($tab, ['security', 'display'], true)) {
                $tab = 'org';
            }
            if (!in_array($tab, ['org', 'numbering', 'approval_outgoing', 'approval_incoming', 'approval_internal', 'storage'], true)) {
                $tab = 'org';
            }
            redirect(base_url('admin/settings.php?tab=' . rawurlencode($tab)));
        }

        $orgNameAr = trim($_POST['org_name_ar'] ?? '');
        $orgNameEn = trim($_POST['org_name_en'] ?? '');
        if ($orgNameAr === '') {
            throw new InvalidArgumentException(__('settings.org_name_required'));
        }

        set_setting('org_name_ar', $orgNameAr);
        set_setting('org_name', $orgNameAr);
        set_setting('org_name_en', $orgNameEn);
        set_setting('org_phone', trim($_POST['org_phone'] ?? ''));
        set_setting('org_email', trim($_POST['org_email'] ?? ''));
        set_setting('org_address', trim($_POST['org_address'] ?? ''));
        set_setting('org_address_en', trim($_POST['org_address_en'] ?? ''));
        set_setting('letter_email_font', 'arial');
        set_setting('show_org_name_ar_pages', isset($_POST['show_org_name_ar_pages']) ? '1' : '0');
        set_setting('show_org_name_en_pages', isset($_POST['show_org_name_en_pages']) ? '1' : '0');
        set_setting('show_org_name_ar_login', isset($_POST['show_org_name_ar_login']) ? '1' : '0');
        set_setting('show_org_name_en_login', isset($_POST['show_org_name_en_login']) ? '1' : '0');
        set_setting('show_org_name_ar_sidebar', isset($_POST['show_org_name_ar_sidebar']) ? '1' : '0');
        set_setting('show_org_name_en_sidebar', isset($_POST['show_org_name_en_sidebar']) ? '1' : '0');
        set_setting('show_org_name_ar_print', isset($_POST['show_org_name_ar_print']) ? '1' : '0');
        set_setting('show_org_name_en_print', isset($_POST['show_org_name_en_print']) ? '1' : '0');
        set_setting('show_org_name_ar_verify', isset($_POST['show_org_name_ar_verify']) ? '1' : '0');
        set_setting('show_org_name_en_verify', isset($_POST['show_org_name_en_verify']) ? '1' : '0');

        $timeoutMinutes = (int) ($_POST['session_timeout_minutes'] ?? 30);
        if ($timeoutMinutes < 5 || $timeoutMinutes > 1440) {
            throw new InvalidArgumentException(__('settings.timeout_invalid'));
        }
        set_setting('session_timeout_minutes', (string) $timeoutMinutes);

        $alertDays = (int) ($_POST['outgoing_receipt_alert_days'] ?? 7);
        if ($alertDays < 1 || $alertDays > 365) {
            throw new InvalidArgumentException(__('settings.receipt_alert_invalid'));
        }
        set_setting('outgoing_receipt_alert_days', (string) $alertDays);

        set_setting('outgoing_approval_enabled', isset($_POST['outgoing_approval_enabled']) ? '1' : '0');

        $parseTimeout = static function (string $valueKey, string $unitKey): array {
            $unit = strtolower(trim((string) ($_POST[$unitKey] ?? 'hours')));
            if ($unit !== 'minutes' && $unit !== 'hours') {
                $unit = 'hours';
            }
            $value = (int) ($_POST[$valueKey] ?? ($unit === 'minutes' ? 120 : 2));
            if ($unit === 'hours') {
                if ($value < 1 || $value > 72) {
                    throw new InvalidArgumentException(__('settings.outgoing_approval_timeout_invalid'));
                }
                $minutes = $value * 60;
            } else {
                if ($value < 1 || $value > 4320) {
                    throw new InvalidArgumentException(__('settings.outgoing_approval_timeout_invalid'));
                }
                $minutes = $value;
            }
            return [$minutes, $unit];
        };
        $saveApprovalChain = static function (string $p, bool $withFirstUser) use ($parseTimeout): int {
            [$primaryMinutes, $primaryUnit] = $parseTimeout($p . '_approval_value', $p . '_approval_unit');
            [$deputyMinutes, $deputyUnit] = $parseTimeout($p . '_approval_deputy_value', $p . '_approval_deputy_unit');
            [$reviewMinutes, $reviewUnit] = $parseTimeout($p . '_review_value', $p . '_review_unit');
            $userKeys = [$p . '_reviewer_primary_user_id', $p . '_reviewer_alternate_user_id'];
            foreach ($withFirstUser ? ['sg', 'deputy', 'alternate'] : ['deputy', 'alternate'] as $role) {
                $userKeys[] = $p . '_approver_' . $role . '_user_id';
            }
            $userIds = [];
            foreach ($userKeys as $key) {
                $id = max(0, (int) ($_POST[$key] ?? 0));
                if ($id > 0 && in_array($id, $userIds, true)) {
                    throw new InvalidArgumentException(__('settings.outgoing_approval_same_user'));
                }
                $userIds[$key] = $id;
            }
            set_setting($p . '_approval_minutes', (string) $primaryMinutes);
            set_setting($p . '_approval_unit', $primaryUnit);
            set_setting($p . '_approval_deputy_minutes', (string) $deputyMinutes);
            set_setting($p . '_approval_deputy_unit', $deputyUnit);
            set_setting($p . '_review_minutes', (string) $reviewMinutes);
            set_setting($p . '_review_unit', $reviewUnit);
            foreach ($userIds as $key => $id) {
                set_setting($key, (string) $id);
            }
            foreach ([$p . '_approver_deputy_enabled', $p . '_approver_alternate_enabled', $p . '_reviewer_alternate_enabled'] as $flag) {
                set_setting($flag, isset($_POST[$flag]) ? '1' : '0');
            }
            return $primaryMinutes;
        };
        $outgoingPrimaryMinutes = $saveApprovalChain('outgoing', true);
        set_setting('outgoing_approval_hours', (string) max(1, (int) ceil($outgoingPrimaryMinutes / 60)));

        set_setting('incoming_approval_enabled', isset($_POST['incoming_approval_enabled']) ? '1' : '0');
        $saveApprovalChain('incoming', true);
        set_setting('incoming_approval_scope_all', ($_POST['incoming_approval_scope_all'] ?? '0') === '1' ? '1' : '0');
        $scopeCodes = static function (string $field, array $levels): string {
            $valid = array_map(static fn (array $r): string => (string) ($r['code'] ?? ''), $levels);
            $picked = array_map('strval', (array) ($_POST[$field] ?? []));
            return implode(',', array_values(array_intersect($valid, $picked)));
        };
        set_setting('incoming_approval_confidentiality', $scopeCodes(
            'incoming_approval_confidentiality',
            function_exists('confidentiality_levels_list') ? confidentiality_levels_list() : []
        ));
        set_setting('incoming_approval_priority', $scopeCodes(
            'incoming_approval_priority',
            function_exists('priority_levels_list') ? priority_levels_list() : []
        ));

        set_setting('outgoing_prep_enabled', isset($_POST['outgoing_prep_enabled']) ? '1' : '0');
        set_setting('outgoing_review_enabled', isset($_POST['outgoing_review_enabled']) ? '1' : '0');
        set_setting('incoming_review_enabled', isset($_POST['incoming_review_enabled']) ? '1' : '0');
        set_setting('internal_review_enabled', isset($_POST['internal_review_enabled']) ? '1' : '0');

        set_setting('internal_approval_enabled', isset($_POST['internal_approval_enabled']) ? '1' : '0');
        $saveApprovalChain('internal', true);

        $incoming = corr_sanitize_prefix((string) ($_POST['incoming_prefix'] ?? ''));
        $outgoing = corr_sanitize_prefix((string) ($_POST['outgoing_prefix'] ?? ''));
        $circular = corr_sanitize_prefix((string) ($_POST['circular_prefix'] ?? ''));
        $internal = corr_sanitize_prefix((string) ($_POST['internal_prefix'] ?? ''));
        $archivePrefix = corr_sanitize_prefix((string) ($_POST['archive_prefix'] ?? ''));
        set_setting('incoming_prefix', $incoming);
        set_setting('outgoing_prefix', $outgoing);
        set_setting('circular_prefix', $circular);
        set_setting('internal_prefix', $internal);
        set_setting('archive_prefix', $archivePrefix);

        $yearMode = strtolower(trim((string) ($_POST['numbering_year_mode'] ?? 'hijri')));
        if (!in_array($yearMode, ['hijri', 'gregorian', 'none'], true)) {
            $yearMode = 'hijri';
        }
        set_setting('numbering_year_mode', $yearMode);

        $qrBase = trim((string) ($_POST['qr_scan_base_url'] ?? ''));
        if ($qrBase !== '') {
            $qrBase = rtrim($qrBase, '/');
            if (!preg_match('#^https?://#i', $qrBase)) {
                throw new InvalidArgumentException(__('settings.qr_base_invalid'));
            }
        }
        set_setting('qr_scan_base_url', $qrBase);

        set_setting('print_receipt_message', trim($_POST['print_receipt_message'] ?? ''));

        $storageRoot = trim(str_replace('\\', '/', (string) ($_POST['storage_root'] ?? '')));
        $storageSave = dms_storage_save_root($storageRoot);
        if (!$storageSave['ok']) {
            throw new InvalidArgumentException($storageSave['error'] !== '' ? $storageSave['error'] : __('settings.storage_invalid'));
        }
        $archiveRoot = trim(str_replace('\\', '/', (string) ($_POST['archive_disk_root'] ?? '')));
        $archiveSave = archive_disk_save_root($archiveRoot);
        if (!$archiveSave['ok']) {
            throw new InvalidArgumentException($archiveSave['error'] !== '' ? $archiveSave['error'] : __('settings.storage_invalid'));
        }

        if (!empty($_FILES['org_logo']['name'])) {
            $logoPath = upload_org_logo($_FILES['org_logo']);
            set_setting('org_logo', $logoPath);
        }

        flash('success', __('settings.saved'));
    } catch (Throwable $e) {
        flash('danger', $e->getMessage());
    }
    $tab = trim((string) ($_POST['settings_tab'] ?? 'org'));
    if ($tab === 'approval') {
        $tab = 'approval_outgoing';
    }
    if (in_array($tab, ['security', 'display'], true)) {
        $tab = 'org';
    }
    $allowedTabs = ['org', 'numbering', 'approval_outgoing', 'approval_incoming', 'approval_internal', 'storage'];
    if (!in_array($tab, $allowedTabs, true)) {
        $tab = 'org';
    }
    redirect(base_url('admin/settings.php?tab=' . rawurlencode($tab)));
}

if (function_exists('archive_prefix_empty_default_once')) {
    archive_prefix_empty_default_once();
}

$settings = [
    'org_name_ar' => org_name_ar(),
    'org_name_en' => org_name_en(),
    'org_phone' => org_phone(),
    'org_email' => org_email(),
    'org_address' => org_address(),
    'org_address_en' => org_address_en(),
    'org_logo' => org_logo_path(),
    'show_org_name_ar_pages' => org_show_name_lang('ar', 'pages'),
    'show_org_name_en_pages' => org_show_name_lang('en', 'pages'),
    'show_org_name_ar_login' => org_show_name_lang('ar', 'login'),
    'show_org_name_en_login' => org_show_name_lang('en', 'login'),
    'show_org_name_ar_sidebar' => org_show_name_lang('ar', 'sidebar'),
    'show_org_name_en_sidebar' => org_show_name_lang('en', 'sidebar'),
    'show_org_name_ar_print' => org_show_name_lang('ar', 'print'),
    'show_org_name_en_print' => org_show_name_lang('en', 'print'),
    'show_org_name_ar_verify' => org_show_name_lang('ar', 'verify'),
    'show_org_name_en_verify' => org_show_name_lang('en', 'verify'),
    'session_timeout_minutes' => session_timeout_minutes(),
    'outgoing_receipt_alert_days' => outgoing_receipt_alert_days(),
    'outgoing_approval_enabled' => setting('outgoing_approval_enabled', '1'),
    'outgoing_prep_enabled' => setting('outgoing_prep_enabled', '1'),
    'outgoing_approval_minutes' => (string) (function_exists('outgoing_approval_minutes_for') ? outgoing_approval_minutes_for('primary') : 120),
    'outgoing_approval_unit' => function_exists('outgoing_approval_unit_for') ? outgoing_approval_unit_for('primary') : 'hours',
    'outgoing_approval_deputy_minutes' => (string) (function_exists('outgoing_approval_minutes_for') ? outgoing_approval_minutes_for('deputy') : 120),
    'outgoing_approval_deputy_unit' => function_exists('outgoing_approval_unit_for') ? outgoing_approval_unit_for('deputy') : 'hours',
    'outgoing_approver_sg_user_id' => setting('outgoing_approver_sg_user_id', '0'),
    'outgoing_approver_deputy_user_id' => setting('outgoing_approver_deputy_user_id', '0'),
    'outgoing_approver_alternate_user_id' => setting('outgoing_approver_alternate_user_id', '0'),
    'outgoing_approver_deputy_enabled' => setting('outgoing_approver_deputy_enabled', '1'),
    'outgoing_approver_alternate_enabled' => setting('outgoing_approver_alternate_enabled', '1'),
    'incoming_prefix' => setting('incoming_prefix', ''),
    'outgoing_prefix' => setting('outgoing_prefix', ''),
    'circular_prefix' => setting('circular_prefix', ''),
    'internal_prefix' => setting('internal_prefix', ''),
    'archive_prefix' => setting('archive_prefix', ''),
    'numbering_year_mode' => function_exists('corr_numbering_year_mode') ? corr_numbering_year_mode() : setting('numbering_year_mode', 'hijri'),
    'qr_scan_base_url' => setting('qr_scan_base_url', ''),
    'print_receipt_message' => print_receipt_message(),
    'storage_root' => setting('storage_root', ''),
    'archive_disk_root' => setting('archive_disk_root', ''),
    'incoming_approval_enabled' => setting('incoming_approval_enabled', '0'),
    'incoming_approval_scope_all' => setting('incoming_approval_scope_all', '0'),
    'incoming_approval_confidentiality' => setting('incoming_approval_confidentiality', 'confidential'),
    'incoming_approval_priority' => setting('incoming_approval_priority', 'urgent'),
    'internal_approval_enabled' => setting('internal_approval_enabled', '0'),
    'outgoing_review_enabled' => setting('outgoing_review_enabled', '0'),
    'incoming_review_enabled' => setting('incoming_review_enabled', '0'),
    'internal_review_enabled' => setting('internal_review_enabled', '0'),
];
foreach (['outgoing', 'incoming', 'internal'] as $chainPrefix) {
    $settings[$chainPrefix . '_review_minutes'] = setting($chainPrefix . '_review_minutes', '120');
    $settings[$chainPrefix . '_review_unit'] = setting($chainPrefix . '_review_unit', 'hours');
    $settings[$chainPrefix . '_reviewer_alternate_enabled'] = setting($chainPrefix . '_reviewer_alternate_enabled', '1');
    foreach (['primary', 'alternate'] as $roleKey) {
        $settings[$chainPrefix . '_reviewer_' . $roleKey . '_user_id'] = setting($chainPrefix . '_reviewer_' . $roleKey . '_user_id', '0');
    }
}
foreach (['incoming' => 60, 'internal' => 120] as $chainPrefix => $chainDefaultMinutes) {
    foreach (['approval', 'approval_deputy'] as $stageKey) {
        $settings[$chainPrefix . '_' . $stageKey . '_minutes'] = setting($chainPrefix . '_' . $stageKey . '_minutes', (string) $chainDefaultMinutes);
        $settings[$chainPrefix . '_' . $stageKey . '_unit'] = setting($chainPrefix . '_' . $stageKey . '_unit', 'hours');
    }
    foreach (['sg', 'deputy', 'alternate'] as $roleKey) {
        $settings[$chainPrefix . '_approver_' . $roleKey . '_user_id'] = setting($chainPrefix . '_approver_' . $roleKey . '_user_id', '0');
    }
    foreach (['deputy', 'alternate'] as $roleKey) {
        $settings[$chainPrefix . '_approver_' . $roleKey . '_enabled'] = setting($chainPrefix . '_approver_' . $roleKey . '_enabled', '1');
    }
}
$lookupName = static function (array $row): string {
    $lang = function_exists('dms_lang_code') ? dms_lang_code() : 'ar';
    $name = trim((string) ($row['name_' . $lang] ?? ''));
    return $name !== '' ? $name : (string) ($row['name_ar'] ?? $row['code'] ?? '');
};
$incomingScopeLevels = [
    'confidentiality' => array_values(array_filter(
        function_exists('confidentiality_levels_list') ? confidentiality_levels_list() : [],
        static fn (array $r): bool => (string) ($r['code'] ?? '') !== 'normal'
    )),
    'priority' => array_values(array_filter(
        function_exists('priority_levels_list') ? priority_levels_list() : [],
        static fn (array $r): bool => (string) ($r['code'] ?? '') !== 'normal'
    )),
];
$incomingScopeSelected = [
    'confidentiality' => array_filter(explode(',', $settings['incoming_approval_confidentiality'])),
    'priority' => array_filter(explode(',', $settings['incoming_approval_priority'])),
];
$storageEffective = dms_storage_root();
$storageWritable = is_dir($storageEffective) && is_writable($storageEffective);
$archiveStorageEffective = archive_disk_root();
$archiveStorageWritable = is_dir($archiveStorageEffective) && is_writable($archiveStorageEffective);
$approvalUsers = function_exists('outgoing_approval_active_users') ? outgoing_approval_active_users() : [];
$approvalUsersById = [];
foreach ($approvalUsers as $au) {
    $approvalUsersById[(int) $au['id']] = $au;
}
$approverPhotoUrl = static function (array $au): string {
    return function_exists('user_photo_url') ? user_photo_url($au) : '';
};
$approverPersonHtml = static function (string $selectId, int $userId) use ($approvalUsersById, $approverPhotoUrl): string {
    $roleKey = 'settings.outgoing_approver_picker_title';
    if (preg_match('/_approver_(sg|deputy|alternate)_user_id$/', $selectId, $m)) {
        $roleKey = [
            'sg' => 'settings.outgoing_approver_primary_heading',
            'deputy' => 'settings.outgoing_approver_escalation_heading',
            'alternate' => 'settings.outgoing_approver_alternate_heading',
        ][$m[1]];
    } elseif (preg_match('/_reviewer_(primary|alternate)_user_id$/', $selectId, $m)) {
        $roleKey = $m[1] === 'primary' ? 'settings.review_primary_heading' : 'settings.review_alternate_heading';
    }
    $roleLabel = __($roleKey);
    $changeTip = str_replace(':role', $roleLabel, __('settings.outgoing_approver_change_role'));
    $au = $approvalUsersById[$userId] ?? null;
    $photo = $au ? $approverPhotoUrl($au) : '';
    $name = $au ? user_display_name($au) : __('settings.outgoing_approver_none');
    $job = $au ? user_job_title($au) : '';
    $avatar = $photo !== ''
        ? '<img src="' . e($photo) . '" alt="" class="settings-approver-avatar">'
        : '<span class="settings-approver-avatar is-fallback"><i class="bi bi-person" aria-hidden="true"></i></span>';
    return '<div class="settings-approver-person' . ($au ? '' : ' is-empty') . '" data-approver-person-for="' . e($selectId) . '">'
        . '<span class="settings-approver-avatar-wrap">' . $avatar . '</span>'
        . '<div class="settings-approver-person-text">'
        . '<div class="settings-approver-person-name">' . e($name) . '</div>'
        . '<div class="settings-approver-person-job' . ($job === '' ? ' d-none' : '') . '">' . e($job) . '</div>'
        . '</div>'
        . '<div class="settings-approver-actions">'
        . '<button type="button" class="btn settings-approver-change-btn" data-keep-label'
        . ' data-approver-pick="' . e($selectId) . '" data-role-label="' . e($roleLabel) . '"'
        . ' data-bs-toggle="tooltip" data-bs-trigger="hover" data-bs-placement="top"'
        . ' title="' . e($changeTip) . '" aria-label="' . e($changeTip) . '">'
        . '<i class="bi bi-person-gear" aria-hidden="true"></i></button>'
        . '</div>'
        . '</div>';
};

$stageToggleTips = static function (string $roleKey): array {
    $role = __($roleKey);
    return [
        'on' => str_replace(':role', $role, __('settings.outgoing_approver_stage_tip_on')),
        'off' => str_replace(':role', $role, __('settings.outgoing_approver_stage_tip_off')),
    ];
};

$displayRows = [
    'pages' => [
        'label' => __('settings.show_name_pages'),
        'hint' => __('settings.show_name_pages_hint'),
        'icon' => 'bi-layout-text-window-reverse',
    ],
    'login' => [
        'label' => __('settings.show_name_login'),
        'hint' => __('settings.show_name_login_hint'),
        'icon' => 'bi-box-arrow-in-right',
    ],
    'sidebar' => [
        'label' => __('settings.show_name_sidebar'),
        'hint' => __('settings.show_name_sidebar_hint'),
        'icon' => 'bi-layout-sidebar-inset-reverse',
    ],
    'print' => [
        'label' => __('settings.show_name_print'),
        'hint' => __('settings.show_name_print_hint'),
        'icon' => 'bi-printer',
    ],
    'verify' => [
        'label' => __('settings.show_name_verify'),
        'hint' => __('settings.show_name_verify_hint'),
        'icon' => 'bi-qr-code-scan',
    ],
];

$previewNames = org_visible_names('pages');

$allowedTabs = ['org', 'numbering', 'approval_outgoing', 'approval_incoming', 'approval_internal', 'storage'];
$activeTab = trim((string) ($_GET['tab'] ?? 'org'));
if ($activeTab === 'approval') {
    redirect(base_url('admin/settings.php?tab=approval_outgoing'));
}
if (in_array($activeTab, ['security', 'display'], true)) {
    $activeTab = 'org';
}
if (!in_array($activeTab, $allowedTabs, true)) {
    $activeTab = 'org';
}

require __DIR__ . '/../includes/header.php';
?>
<div class="page-header page-narrow">
    <div>
        <h1 class="page-title"><?= e(__('settings.title')) ?></h1>
        <p class="page-sub"><?= e(__('settings.subtitle_short')) ?></p>
    </div>
    <div class="page-header-actions">
        <a href="<?= e(base_url('admin/check_assets.php')) ?>" class="btn btn-outline-secondary btn-sm" title="فحص ملفات التنسيق">
            <i class="bi bi-palette2 me-1"></i>فحص التنسيق
        </a>
        <button class="btn btn-kff btn-sm" type="submit" form="settingsForm" name="action" value="save">
            <i class="bi bi-check2-circle me-1"></i><?= e(__('settings.save')) ?>
        </button>
    </div>
</div>

<form method="post" enctype="multipart/form-data" id="settingsForm" class="settings-page page-narrow">
    <?= csrf_field() ?>
    <input type="hidden" name="settings_tab" id="settings_tab" value="<?= e($activeTab) ?>">

    <?php
    $settingsHubActive = $activeTab;
    require __DIR__ . '/../includes/settings_hub_nav.php';
    ?>

    <div class="tab-content" id="settingsTabsContent">

        <div class="tab-pane fade <?= $activeTab === 'org' ? 'show active' : '' ?>" id="tab-org" role="tabpanel" aria-labelledby="tab-org-btn" tabindex="0">
            <div class="settings-org-layout">
                <div class="settings-org-col">
                    <div class="card-panel">
                        <div class="card-header">
                            <i class="bi bi-building me-1"></i><?= e(__('settings.org_section')) ?>
                        </div>
                        <div class="card-body p-4">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="org_name_ar"><?= e(__('settings.org_name_ar')) ?></label>
                                    <input type="text" name="org_name_ar" id="org_name_ar" class="form-control" value="<?= e($settings['org_name_ar']) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="org_name_en"><?= e(__('settings.org_name_en')) ?></label>
                                    <input type="text" name="org_name_en" id="org_name_en" class="form-control" dir="ltr" value="<?= e($settings['org_name_en']) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="org_phone"><?= e(__('settings.phone')) ?></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                                        <input type="text" name="org_phone" id="org_phone" class="form-control" dir="ltr" placeholder="+966 ..." value="<?= e($settings['org_phone']) ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="org_email"><?= e(__('settings.email')) ?></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                        <input type="email" name="org_email" id="org_email" class="form-control" dir="ltr" placeholder="info@example.com" value="<?= e($settings['org_email']) ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="org_address"><?= e(__('settings.address_ar')) ?></label>
                                    <textarea name="org_address" id="org_address" class="form-control" rows="2"><?= e($settings['org_address']) ?></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="org_address_en"><?= e(__('settings.address_en')) ?></label>
                                    <textarea name="org_address_en" id="org_address_en" class="form-control" dir="ltr" rows="2"><?= e($settings['org_address_en']) ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card-panel settings-org-stretch">
                        <div class="card-header">
                            <i class="bi bi-hourglass-split me-1"></i><?= e(__('settings.security_section')) ?>
                        </div>
                        <div class="card-body p-4">
                            <div class="settings-stack-fields">
                                <div>
                                    <label class="form-label" for="session_timeout_minutes"><?= e(__('settings.session_timeout')) ?></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-hourglass-split"></i></span>
                                        <input type="number" name="session_timeout_minutes" id="session_timeout_minutes"
                                               class="form-control" dir="ltr" min="5" max="1440" step="1"
                                               value="<?= (int) $settings['session_timeout_minutes'] ?>">
                                        <span class="input-group-text"><?= e(__('settings.minutes')) ?></span>
                                    </div>
                                    <div class="form-text"><?= e(__('settings.session_timeout_hint')) ?></div>
                                </div>
                                <div>
                                    <label class="form-label" for="outgoing_receipt_alert_days"><?= e(__('settings.receipt_alert_days')) ?></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-bell"></i></span>
                                        <input type="number" name="outgoing_receipt_alert_days" id="outgoing_receipt_alert_days"
                                               class="form-control" dir="ltr" min="1" max="365" step="1"
                                               value="<?= (int) $settings['outgoing_receipt_alert_days'] ?>">
                                        <span class="input-group-text"><?= e(__('settings.days')) ?></span>
                                    </div>
                                    <div class="form-text"><?= e(__('settings.receipt_alert_hint')) ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="settings-org-col">
                    <div class="card-panel">
                        <div class="card-header">
                            <i class="bi bi-image me-1"></i><?= e(__('settings.logo_section')) ?>
                        </div>
                        <div class="card-body p-4">
                            <div class="settings-logo-stage <?= $settings['org_logo'] ? '' : 'is-empty' ?>">
                                <?php if ($settings['org_logo']): ?>
                                    <img src="<?= e(org_logo_url()) ?>" alt="<?= e(__('settings.logo_section')) ?>">
                                <?php else: ?>
                                    <div class="settings-logo-empty">
                                        <i class="bi bi-image"></i>
                                        <span><?= e(__('settings.no_logo')) ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="mt-3">
                                <?php
                                $dmsFile = [
                                    'name' => 'org_logo',
                                    'id' => 'org_logo',
                                    'accept' => '.jpg,.jpeg,.png,.webp,.gif,.svg,image/*',
                                    'label' => __('settings.upload_logo'),
                                    'hint' => __('settings.logo_hint'),
                                    'browse' => __('file.choose_one'),
                                    'placeholder' => __('file.placeholder_one'),
                                ];
                                require __DIR__ . '/../includes/dms_file_field.php';
                                ?>
                            </div>
                            <?php if ($settings['org_logo']): ?>
                                <button type="submit" class="btn btn-outline-danger btn-sm w-100 mt-3"
                                        name="action" value="remove_logo"
                                        data-confirm="<?= e(__('settings.confirm_remove_logo')) ?>">
                                    <i class="bi bi-trash me-1"></i><?= e(__('settings.remove_logo')) ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card-panel settings-org-stretch">
                        <div class="card-header">
                            <i class="bi bi-eye-fill me-1"></i><?= e(__('settings.preview')) ?>
                        </div>
                        <div class="card-body p-4">
                            <div class="settings-preview-card">
                                <?php if ($settings['org_logo']): ?>
                                    <div class="settings-preview-logo">
                                        <img src="<?= e(org_logo_url()) ?>" alt="">
                                    </div>
                                <?php endif; ?>
                                <div class="settings-preview-names">
                                    <?php if ($previewNames): ?>
                                        <?php if (!empty($previewNames['primary'])): ?>
                                            <div class="settings-preview-primary"><?= e($previewNames['primary']) ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($previewNames['secondary'])): ?>
                                            <div class="settings-preview-secondary" dir="ltr"><?= e($previewNames['secondary']) ?></div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <div class="settings-preview-muted"><?= e(__('settings.name_hidden_preview')) ?></div>
                                    <?php endif; ?>
                                </div>
                                <?php if ($settings['org_phone'] || $settings['org_email']): ?>
                                    <div class="settings-preview-contact">
                                        <?php if ($settings['org_phone']): ?>
                                            <span dir="ltr"><i class="bi bi-telephone"></i><?= e($settings['org_phone']) ?></span>
                                        <?php endif; ?>
                                        <?php if ($settings['org_phone'] && $settings['org_email']): ?>
                                            <span class="settings-preview-sep">|</span>
                                        <?php endif; ?>
                                        <?php if ($settings['org_email']): ?>
                                            <span dir="ltr"><i class="bi bi-envelope"></i><?= e($settings['org_email']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($settings['org_address'] || $settings['org_address_en']): ?>
                                    <div class="settings-preview-address">
                                        <?php if ($settings['org_address']): ?>
                                            <div><?= e($settings['org_address']) ?></div>
                                        <?php endif; ?>
                                        <?php if ($settings['org_address_en']): ?>
                                            <div dir="ltr"><?= e($settings['org_address_en']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="settings-org-layout settings-org-layout-bottom">
                <div class="card-panel">
                    <div class="card-header">
                        <i class="bi bi-eye me-1"></i><?= e(__('settings.display_section')) ?>
                    </div>
                    <div class="card-body p-0">
                        <p class="settings-section-hint mb-0"><?= e(__('settings.display_hint')) ?></p>
                        <div class="settings-toggle-list">
                            <div class="settings-toggle-head">
                                <span><?= e(__('settings.display_place')) ?></span>
                                <span class="settings-toggle-langs">
                                    <span><?= e(__('settings.show_name_ar')) ?></span>
                                    <span><?= e(__('settings.show_name_en')) ?></span>
                                </span>
                            </div>
                            <?php foreach ($displayRows as $ctx => $meta):
                                $arKey = 'show_org_name_ar_' . $ctx;
                                $enKey = 'show_org_name_en_' . $ctx;
                            ?>
                            <div class="settings-toggle-row">
                                <div class="settings-toggle-meta">
                                    <span class="settings-toggle-icon"><i class="bi <?= e($meta['icon']) ?>"></i></span>
                                    <div>
                                        <div class="settings-toggle-title"><?= e($meta['label']) ?></div>
                                        <?php if (trim((string) ($meta['hint'] ?? '')) !== ''): ?>
                                        <div class="settings-toggle-hint"><?= e($meta['hint']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="settings-toggle-langs">
                                    <div class="form-check form-switch m-0">
                                        <input class="form-check-input" type="checkbox" role="switch"
                                               id="<?= e($arKey) ?>" name="<?= e($arKey) ?>" value="1"
                                               <?= !empty($settings[$arKey]) ? 'checked' : '' ?>
                                               aria-label="<?= e($meta['label'] . ' — ' . __('settings.show_name_ar')) ?>">
                                    </div>
                                    <div class="form-check form-switch m-0">
                                        <input class="form-check-input" type="checkbox" role="switch"
                                               id="<?= e($enKey) ?>" name="<?= e($enKey) ?>" value="1"
                                               <?= !empty($settings[$enKey]) ? 'checked' : '' ?>
                                               aria-label="<?= e($meta['label'] . ' — ' . __('settings.show_name_en')) ?>">
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="card-panel settings-org-stretch">
                    <div class="card-header">
                        <i class="bi bi-pen me-1"></i><?= e(__('settings.print_receipt_section')) ?>
                    </div>
                    <div class="card-body p-4">
                        <label class="form-label" for="print_receipt_message"><?= e(__('settings.print_receipt_message')) ?></label>
                        <textarea name="print_receipt_message" id="print_receipt_message" class="form-control settings-org-fill-input" rows="6"><?= e($settings['print_receipt_message']) ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade <?= $activeTab === 'numbering' ? 'show active' : '' ?>" id="tab-numbering" role="tabpanel" aria-labelledby="tab-numbering-btn" tabindex="0">
            <div class="card-panel mb-0">
                <div class="card-header">
                    <i class="bi bi-hash me-1"></i><?= e(__('settings.numbering_section')) ?>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="form-label mb-2"><?= e(__('settings.numbering_year')) ?></div>
                            <?php
                            $yearMode = (string) ($settings['numbering_year_mode'] ?? 'hijri');
                            $hijriYy = sprintf('%02d', abs(corr_numbering_hijri_year()) % 100);
                            $gregYy = sprintf('%02d', ((int) date('Y')) % 100);
                            $yearModeCards = [
                                'hijri' => [
                                    'label' => __('settings.numbering_year_hijri'),
                                    'desc' => __('settings.numbering_year_hijri_desc'),
                                    'sample' => $hijriYy . '/0001',
                                ],
                                'gregorian' => [
                                    'label' => __('settings.numbering_year_gregorian'),
                                    'desc' => __('settings.numbering_year_gregorian_desc'),
                                    'sample' => $gregYy . '/0001',
                                ],
                                'none' => [
                                    'label' => __('settings.numbering_year_none'),
                                    'desc' => __('settings.numbering_year_none_desc'),
                                    'sample' => '0001',
                                ],
                            ];
                            ?>
                            <div class="settings-year-mode" role="radiogroup" aria-label="<?= e(__('settings.numbering_year')) ?>"
                                 data-hijri-yy="<?= e($hijriYy) ?>" data-gregorian-yy="<?= e($gregYy) ?>">
                                <?php foreach ($yearModeCards as $mode => $card): ?>
                                <?php $optId = 'numbering_year_mode_' . $mode; ?>
                                <label class="settings-year-option<?= $yearMode === $mode ? ' is-selected' : '' ?>" for="<?= e($optId) ?>">
                                    <span class="settings-year-option-head">
                                        <input class="form-check-input" type="radio" name="numbering_year_mode" id="<?= e($optId) ?>" value="<?= e($mode) ?>"<?= $yearMode === $mode ? ' checked' : '' ?>>
                                        <span class="settings-year-option-title"><?= e($card['label']) ?></span>
                                    </span>
                                    <span class="settings-year-option-desc"><?= e($card['desc']) ?></span>
                                    <span class="settings-year-option-sample" dir="ltr"><?= e($card['sample']) ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                            <div class="form-text mt-2 mb-0"><?= e(__('settings.numbering_year_hint')) ?></div>
                        </div>
                        <div class="col-12">
                            <div class="form-text mt-0 mb-1"><?= e(__('settings.numbering_hijri_hint')) ?></div>
                            <div class="fw-semibold mb-0" dir="ltr" id="numbering-format-sample"><?= e(corr_ref_sample('')) ?></div>
                        </div>
                        <div class="col-12">
                            <div class="settings-prefix-cards">
                                <?php
                                $prefixCards = [
                                    ['key' => 'outgoing_prefix', 'label' => __('settings.outgoing_prefix'), 'ref' => __('corr.ref_label_outgoing')],
                                    ['key' => 'incoming_prefix', 'label' => __('settings.incoming_prefix'), 'ref' => __('corr.ref_label_incoming')],
                                    ['key' => 'circular_prefix', 'label' => __('settings.circular_prefix'), 'ref' => __('corr.ref_label_circular')],
                                    ['key' => 'internal_prefix', 'label' => __('settings.internal_prefix'), 'ref' => __('corr.ref_label_internal')],
                                    ['key' => 'archive_prefix', 'label' => __('settings.archive_prefix'), 'ref' => __('corr.ref_label_archive')],
                                ];
                                foreach ($prefixCards as $card):
                                    $pkey = $card['key'];
                                    $pval = (string) ($settings[$pkey] ?? '');
                                ?>
                                <div class="settings-prefix-card">
                                    <label class="form-label" for="<?= e($pkey) ?>"><?= e($card['label']) ?></label>
                                    <input type="text" name="<?= e($pkey) ?>" id="<?= e($pkey) ?>" class="form-control form-control-sm settings-prefix-field" maxlength="20" value="<?= e($pval) ?>" autocomplete="off">
                                    <div class="form-text mb-0"><span dir="ltr" class="numbering-prefix-sample"><?= e(corr_ref_sample($pval)) ?></span> — <?= e($card['ref']) ?></div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-text mb-0">
                                <?= e(__('settings.prefix_dept_hint')) ?>
                                <a href="<?= e(base_url('admin/departments.php')) ?>"><?= e(__('nav.departments')) ?></a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade <?= $activeTab === 'approval_outgoing' ? 'show active' : '' ?>" id="tab-approval_outgoing" role="tabpanel" aria-labelledby="tab-approval_outgoing-btn" tabindex="0">
            <div class="card-panel mb-0">
                <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <span><i class="bi bi-shield-check me-1"></i><?= e(__('settings.outgoing_approval_section')) ?></span>
                    <span class="d-flex flex-wrap gap-2">
                        <a class="btn btn-outline-secondary btn-sm" href="<?= e(base_url('admin/settings_path_print.php?path=outgoing')) ?>" target="_blank" rel="noopener">
                            <i class="bi bi-printer me-1"></i><?= e(__('settings.path_print')) ?>
                        </a>
                        <a class="btn btn-outline-secondary btn-sm" href="<?= e(base_url('admin/settings_path_print.php?path=all')) ?>" target="_blank" rel="noopener">
                            <i class="bi bi-collection me-1"></i><?= e(__('settings.path_print_all')) ?>
                        </a>
                    </span>
                </div>
                <div class="card-body p-4">
                    <?php
                    $prepOn = ($settings['outgoing_prep_enabled'] ?? '1') === '1';
                    $prepTip = $stageToggleTips('settings.outgoing_prep_stage_name');
                    ?>
                    <div class="settings-approver-block settings-review-block <?= $prepOn ? '' : 'is-stage-off' ?>" data-approver-stage>
                        <div class="settings-approver-block-head">
                            <span class="settings-approver-step settings-prep-step"><i class="bi bi-pencil-square" aria-hidden="true"></i></span>
                            <div class="flex-grow-1 min-w-0">
                                <div class="settings-approver-block-title"><?= e(__('settings.outgoing_prep_title')) ?></div>
                                <div class="settings-review-desc"><?= e(__('settings.outgoing_prep_desc')) ?></div>
                            </div>
                            <div class="form-check form-switch mb-0 settings-approver-toggle"
                                 data-bs-toggle="tooltip" data-bs-trigger="hover" data-bs-placement="top"
                                 title="<?= e($prepOn ? $prepTip['on'] : $prepTip['off']) ?>"
                                 data-tip-on="<?= e($prepTip['on']) ?>" data-tip-off="<?= e($prepTip['off']) ?>">
                                <input class="form-check-input" type="checkbox" role="switch" name="outgoing_prep_enabled" id="outgoing_prep_enabled" value="1"
                                       data-approver-stage-toggle <?= $prepOn ? 'checked' : '' ?>>
                                <label class="form-check-label" for="outgoing_prep_enabled"><?= e(__('settings.outgoing_approver_stage_enable')) ?></label>
                            </div>
                        </div>
                        <div class="alert alert-secondary py-2 px-3 small mb-3 settings-approver-off-note"><?= e(__('settings.outgoing_prep_stage_off_hint')) ?></div>
                        <div class="settings-approver-stage-body">
                            <div class="form-text mb-0"><?= e(__('settings.outgoing_prep_signer_hint')) ?></div>
                        </div>
                    </div>
                    <?php
                    $review = [
                        'prefix' => 'outgoing',
                        'title' => 'settings.outgoing_review_title',
                        'desc' => 'settings.outgoing_review_desc',
                    ];
                    require __DIR__ . '/../includes/settings_review_stage.php';
                    $chain = ['prefix' => 'outgoing', 'first' => 'user'];
                    require __DIR__ . '/../includes/settings_approval_chain.php';
                    ?>
                </div>
            </div>
        </div>

        <div class="tab-pane fade <?= $activeTab === 'approval_incoming' ? 'show active' : '' ?>" id="tab-approval_incoming" role="tabpanel" aria-labelledby="tab-approval_incoming-btn" tabindex="0">
            <div class="card-panel mb-0">
                <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <span><i class="bi bi-inbox me-1"></i><?= e(__('settings.incoming_approval_section')) ?></span>
                    <span class="d-flex flex-wrap gap-2">
                        <a class="btn btn-outline-secondary btn-sm" href="<?= e(base_url('admin/settings_path_print.php?path=incoming')) ?>" target="_blank" rel="noopener">
                            <i class="bi bi-printer me-1"></i><?= e(__('settings.path_print')) ?>
                        </a>
                        <a class="btn btn-outline-secondary btn-sm" href="<?= e(base_url('admin/settings_path_print.php?path=all')) ?>" target="_blank" rel="noopener">
                            <i class="bi bi-collection me-1"></i><?= e(__('settings.path_print_all')) ?>
                        </a>
                    </span>
                </div>
                <div class="card-body p-4">
                    <?php
                    $review = [
                        'prefix' => 'incoming',
                        'title' => 'settings.incoming_review_title',
                        'desc' => 'settings.incoming_review_desc',
                        'then' => 'settings.incoming_review_then_approval',
                    ];
                    require __DIR__ . '/../includes/settings_review_stage.php';
                    $incomingScopeAll = $settings['incoming_approval_scope_all'] === '1';
                    $incomingApprovalIntro = static function () use ($incomingScopeLevels, $incomingScopeSelected, $lookupName, $incomingScopeAll): void {
                    ?>
                    <div class="settings-approval-scope mb-3">
                        <div class="settings-approval-scope-title"><i class="bi bi-funnel me-1"></i><?= e(__('settings.incoming_approval_scope')) ?></div>
                        <div class="d-flex flex-wrap gap-3 mt-2">
                            <?php foreach (['1' => 'settings.incoming_approval_scope_all', '0' => 'settings.incoming_approval_scope_levels'] as $scopeVal => $scopeText):
                                $scopeVal = (string) $scopeVal;
                                ?>
                                <div class="form-check mb-0">
                                    <input class="form-check-input" type="radio" name="incoming_approval_scope_all" id="incoming_approval_scope_all_<?= e($scopeVal) ?>" value="<?= e($scopeVal) ?>"
                                           data-scope-all-choice <?= ($incomingScopeAll ? '1' : '0') === $scopeVal ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="incoming_approval_scope_all_<?= e($scopeVal) ?>"><?= e(__($scopeText)) ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="form-text mb-2"><?= e(__('settings.incoming_approval_scope_hint')) ?></div>
                        <div class="row g-3 mt-0 settings-approval-scope-levels<?= $incomingScopeAll ? ' is-scope-all' : '' ?>" data-scope-levels<?= $incomingScopeAll ? ' inert' : '' ?>>
                            <?php foreach (['confidentiality' => 'settings.incoming_approval_scope_confidentiality', 'priority' => 'settings.incoming_approval_scope_priority'] as $scopeKey => $scopeLabel): ?>
                                <div class="col-md-6">
                                    <div class="settings-approval-scope-group">
                                        <div class="settings-approval-scope-label"><?= e(__($scopeLabel)) ?></div>
                                        <?php if ($incomingScopeLevels[$scopeKey] === []): ?>
                                            <div class="form-text"><?= e(__('settings.incoming_approval_scope_none')) ?></div>
                                        <?php endif; ?>
                                        <?php foreach ($incomingScopeLevels[$scopeKey] as $level):
                                            $levelCode = (string) $level['code'];
                                            $levelId = 'incoming_scope_' . $scopeKey . '_' . preg_replace('/[^a-z0-9_]/i', '_', $levelCode);
                                            ?>
                                            <div class="form-check form-check-inline mb-0">
                                                <input class="form-check-input" type="checkbox" name="incoming_approval_<?= e($scopeKey) ?>[]" id="<?= e($levelId) ?>" value="<?= e($levelCode) ?>"
                                                    <?= in_array($levelCode, $incomingScopeSelected[$scopeKey], true) ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="<?= e($levelId) ?>"><?= e($lookupName($level)) ?></label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php
                    };
                    $chain = [
                        'prefix' => 'incoming',
                        'first' => 'user',
                        'intro' => $incomingApprovalIntro,
                    ];
                    require __DIR__ . '/../includes/settings_approval_chain.php';
                    ?>
                </div>
            </div>
        </div>

        <div class="tab-pane fade <?= $activeTab === 'approval_internal' ? 'show active' : '' ?>" id="tab-approval_internal" role="tabpanel" aria-labelledby="tab-approval_internal-btn" tabindex="0">
            <div class="card-panel mb-0">
                <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <span><i class="bi bi-arrow-left-right me-1"></i><?= e(__('settings.internal_approval_section')) ?></span>
                    <span class="d-flex flex-wrap gap-2">
                        <a class="btn btn-outline-secondary btn-sm" href="<?= e(base_url('admin/settings_path_print.php?path=internal')) ?>" target="_blank" rel="noopener">
                            <i class="bi bi-printer me-1"></i><?= e(__('settings.path_print')) ?>
                        </a>
                        <a class="btn btn-outline-secondary btn-sm" href="<?= e(base_url('admin/settings_path_print.php?path=all')) ?>" target="_blank" rel="noopener">
                            <i class="bi bi-collection me-1"></i><?= e(__('settings.path_print_all')) ?>
                        </a>
                    </span>
                </div>
                <div class="settings-section-hint"><?= e(__('settings.internal_approval_hint')) ?></div>
                <div class="card-body p-4">
                    <?php
                    $review = [
                        'prefix' => 'internal',
                        'title' => 'settings.internal_review_title',
                        'desc' => 'settings.internal_review_desc',
                        'then' => 'settings.internal_review_then_approval',
                    ];
                    require __DIR__ . '/../includes/settings_review_stage.php';
                    $chain = ['prefix' => 'internal', 'first' => 'user'];
                    require __DIR__ . '/../includes/settings_approval_chain.php';
                    ?>
                </div>
            </div>
        </div>

        <div class="tab-pane fade <?= $activeTab === 'storage' ? 'show active' : '' ?>" id="tab-storage" role="tabpanel" aria-labelledby="tab-storage-btn" tabindex="0">
            <div class="settings-org-col">
            <div class="card-panel">
                <div class="card-header">
                    <i class="bi bi-hdd-network me-1"></i><?= e(__('settings.qr_scan_base_url')) ?>
                </div>
                <div class="card-body p-4">
                    <input type="url" name="qr_scan_base_url" id="qr_scan_base_url" class="form-control" dir="ltr" maxlength="255"
                           value="<?= e($settings['qr_scan_base_url']) ?>"
                           aria-label="<?= e(__('settings.qr_scan_base_url')) ?>"
                           placeholder="<?= e('http://' . (dms_detect_lan_ipv4() ?: '192.168.1.10') . dms_project_base()) ?>">
                    <div class="form-text"><?= e(__('settings.qr_scan_base_url_hint')) ?></div>
                    <?php if (dms_detect_lan_ipv4() !== ''): ?>
                    <div class="form-text mb-0" dir="ltr"><?= e(__('settings.qr_scan_detected')) ?>: http://<?= e(dms_detect_lan_ipv4()) ?><?= e(dms_project_base()) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-panel">
                <div class="card-header">
                    <i class="bi bi-folder2-open me-1"></i><?= e(__('settings.storage_section')) ?>
                </div>
                <div class="card-body p-4">
                    <div class="mb-3">
                        <label class="form-label" for="storage_root"><?= e(__('settings.storage_root')) ?></label>
                        <input type="text" name="storage_root" id="storage_root" class="form-control" dir="ltr"
                               value="<?= e($settings['storage_root']) ?>"
                               placeholder="<?= e(dms_storage_default_root()) ?>">
                        <div class="form-text"><?= e(__('settings.storage_root_hint')) ?></div>
                    </div>
                    <div class="small text-muted">
                        <div><strong><?= e(__('settings.storage_effective')) ?>:</strong>
                            <code dir="ltr"><?= e($storageEffective) ?></code>
                        </div>
                        <div class="mt-1">
                            <?php if ($storageWritable): ?>
                                <span class="text-success"><i class="bi bi-check-circle"></i> <?= e(__('settings.storage_writable')) ?></span>
                            <?php else: ?>
                                <span class="text-danger"><i class="bi bi-x-circle"></i> <?= e(__('settings.storage_not_writable')) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="mt-2"><?= e(__('settings.storage_scope_note')) ?></div>
                    </div>
                </div>
            </div>
            <div class="card-panel">
                <div class="card-header">
                    <i class="bi bi-archive me-1"></i><?= e(__('settings.archive_storage_section')) ?>
                </div>
                <div class="card-body p-4">
                    <div class="mb-3">
                        <label class="form-label" for="archive_disk_root"><?= e(__('settings.archive_storage_root')) ?></label>
                        <input type="text" name="archive_disk_root" id="archive_disk_root" class="form-control" dir="ltr"
                               value="<?= e($settings['archive_disk_root']) ?>"
                               placeholder="<?= e(archive_disk_default_root()) ?>">
                        <div class="form-text"><?= e(__('settings.archive_storage_root_hint')) ?></div>
                    </div>
                    <div class="small text-muted">
                        <div><strong><?= e(__('settings.storage_effective')) ?>:</strong>
                            <code dir="ltr"><?= e($archiveStorageEffective) ?></code>
                        </div>
                        <div class="mt-1">
                            <?php if ($archiveStorageWritable): ?>
                                <span class="text-success"><i class="bi bi-check-circle"></i> <?= e(__('settings.storage_writable')) ?></span>
                            <?php else: ?>
                                <span class="text-danger"><i class="bi bi-x-circle"></i> <?= e(__('settings.storage_not_writable')) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="mt-2"><?= e(__('settings.archive_storage_scope_note')) ?></div>
                    </div>
                </div>
            </div>
            </div>
        </div>

    </div>

    <div class="settings-save-bar">
        <div class="settings-save-bar-inner">
            <span class="text-muted small d-none d-md-inline"><?= e(__('settings.save_hint')) ?></span>
            <button class="btn btn-kff px-4" type="submit" name="action" value="save">
                <i class="bi bi-check2-circle me-1"></i><?= e(__('settings.save')) ?>
            </button>
        </div>
    </div>
</form>

<div class="modal fade" id="approverPickerModal" tabindex="-1" aria-labelledby="approverPickerTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content approver-picker">
            <div class="modal-header">
                <h5 class="modal-title" id="approverPickerTitle">
                    <i class="bi bi-person-check me-1"></i><span data-approver-picker-title><?= e(__('settings.outgoing_approver_picker_title')) ?></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(__('action.close')) ?>"></button>
            </div>
            <div class="approver-picker-search">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="search" class="form-control" id="approverPickerSearch" autocomplete="off"
                           placeholder="<?= e(__('settings.outgoing_approver_picker_search')) ?>">
                </div>
            </div>
            <div class="modal-body p-0">
                <div class="list-group list-group-flush" id="approverPickerList"></div>
                <div class="approver-picker-empty text-muted d-none" id="approverPickerEmpty">
                    <i class="bi bi-person-x d-block mb-1"></i><?= e(__('settings.outgoing_approver_picker_empty')) ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var group = document.querySelector('.settings-year-mode');
    if (!group) return;
    var hijriYy = group.getAttribute('data-hijri-yy') || '';
    var gregYy = group.getAttribute('data-gregorian-yy') || '';
    function selectedMode() {
        var on = group.querySelector('input[name="numbering_year_mode"]:checked');
        return on ? on.value : 'hijri';
    }
    function yearToken(mode) {
        if (mode === 'none') return '';
        if (mode === 'gregorian') return gregYy;
        return hijriYy;
    }
    function buildSample(prefix, mode) {
        var pad = '0001';
        var yy = yearToken(mode);
        var p = (prefix || '').trim();
        if (yy === '') return p ? (p + '/' + pad) : pad;
        return p ? (p + '/' + yy + '/' + pad) : (yy + '/' + pad);
    }
    function sync() {
        var mode = selectedMode();
        group.querySelectorAll('.settings-year-option').forEach(function (card) {
            var input = card.querySelector('input[name="numbering_year_mode"]');
            card.classList.toggle('is-selected', !!(input && input.checked));
        });
        var sample = document.getElementById('numbering-format-sample');
        if (sample) sample.textContent = buildSample('', mode);
        document.querySelectorAll('.settings-prefix-card').forEach(function (card) {
            var input = card.querySelector('.settings-prefix-field');
            var out = card.querySelector('.numbering-prefix-sample');
            if (out) out.textContent = buildSample(input ? input.value : '', mode);
        });
    }
    group.addEventListener('change', sync);
    document.querySelectorAll('.settings-prefix-field').forEach(function (el) {
        el.addEventListener('input', sync);
    });
})();
(function () {
    var tabInput = document.getElementById('settings_tab');
    var tabs = document.getElementById('settingsHubNav');
    if (!tabInput || !tabs) return;
    tabs.addEventListener('shown.bs.tab', function (ev) {
        var btn = ev.target;
        var key = btn && btn.getAttribute('data-settings-tab');
        if (key) {
            tabInput.value = key;
            try {
                var url = new URL(window.location.href);
                url.searchParams.set('tab', key);
                window.history.replaceState({}, '', url.toString());
            } catch (e) {}
        }
    });
})();
(function () {
    var missing = <?= json_encode(__('settings.outgoing_approver_job_title_missing'), JSON_UNESCAPED_UNICODE) ?>;
    var noneText = <?= json_encode(__('settings.outgoing_approver_none'), JSON_UNESCAPED_UNICODE) ?>;
    var editText = <?= json_encode(__('settings.outgoing_approver_job_title_edit'), JSON_UNESCAPED_UNICODE) ?>;
    var editBase = <?= json_encode(base_url('admin/users_edit.php?id='), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var pickText = <?= json_encode(__('settings.outgoing_approver_pick'), JSON_UNESCAPED_UNICODE) ?>;
    var changeText = <?= json_encode(__('settings.outgoing_approver_change'), JSON_UNESCAPED_UNICODE) ?>;
    var pickerTitle = <?= json_encode(__('settings.outgoing_approver_picker_title'), JSON_UNESCAPED_UNICODE) ?>;
    var takenText = <?= json_encode(__('settings.outgoing_approver_picker_taken'), JSON_UNESCAPED_UNICODE) ?>;
    var currentText = <?= json_encode(__('settings.outgoing_approver_picker_current'), JSON_UNESCAPED_UNICODE) ?>;

    function renderPerson(person, opt, selected) {
        if (!person) return;
        var name = selected ? (opt.getAttribute('data-name') || '') : noneText;
        var job = selected ? (opt.getAttribute('data-job-title') || '') : '';
        var photo = selected ? (opt.getAttribute('data-photo') || '') : '';
        person.classList.toggle('is-empty', !selected);
        var wrap = person.querySelector('.settings-approver-avatar-wrap');
        if (wrap) {
            wrap.textContent = '';
            if (photo) {
                var img = document.createElement('img');
                img.src = photo;
                img.alt = '';
                img.className = 'settings-approver-avatar';
                wrap.appendChild(img);
            } else {
                var span = document.createElement('span');
                span.className = 'settings-approver-avatar is-fallback';
                var ic = document.createElement('i');
                ic.className = 'bi bi-person';
                ic.setAttribute('aria-hidden', 'true');
                span.appendChild(ic);
                wrap.appendChild(span);
            }
        }
        var nameEl = person.querySelector('.settings-approver-person-name');
        if (nameEl) nameEl.textContent = name;
        var jobEl = person.querySelector('.settings-approver-person-job');
        if (jobEl) {
            jobEl.textContent = job;
            jobEl.classList.toggle('d-none', job === '');
        }
    }

    document.querySelectorAll('select[data-approver-select]').forEach(function (sel) {
        var out = document.querySelector('[data-approver-title-for="' + sel.id + '"]');
        var person = document.querySelector('[data-approver-person-for="' + sel.id + '"]');
        var pickLabel = document.querySelector('[data-approver-pick="' + sel.id + '"] [data-approver-pick-label]');
        function refresh() {
            var opt = sel.options[sel.selectedIndex];
            var selected = !!opt && sel.value !== '0';
            renderPerson(person, opt, selected);
            if (pickLabel) pickLabel.textContent = selected ? changeText : pickText;
            if (!out) return;
            if (window.bootstrap && bootstrap.Tooltip) {
                out.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
                    var t = bootstrap.Tooltip.getInstance(el);
                    if (t) t.dispose();
                });
            }
            out.textContent = '';
            if (!selected) {
                out.classList.add('d-none');
                return;
            }
            var title = opt.getAttribute('data-job-title') || '';
            if (title) {
                out.classList.add('d-none');
                return;
            }
            out.classList.remove('d-none');
            out.classList.add('text-danger');
            out.appendChild(document.createTextNode(missing + ' '));
            var link = document.createElement('a');
            link.href = editBase + encodeURIComponent(sel.value);
            link.target = '_blank';
            link.rel = 'noopener';
            link.className = 'settings-approver-edit-link';
            link.title = editText;
            link.setAttribute('aria-label', editText);
            link.setAttribute('data-bs-toggle', 'tooltip');
            link.setAttribute('data-bs-trigger', 'hover');
            var icon = document.createElement('i');
            icon.className = 'bi bi-pencil-square';
            icon.setAttribute('aria-hidden', 'true');
            link.appendChild(icon);
            out.appendChild(link);
            if (window.bootstrap && bootstrap.Tooltip) {
                bootstrap.Tooltip.getOrCreateInstance(link);
            }
        }
        sel.addEventListener('change', refresh);
        refresh();
    });
    function refreshStages() {
        document.querySelectorAll('[data-approver-stage]').forEach(function (block) {
            var own = block.querySelector(':scope > .settings-approver-block-head input[data-approver-stage-toggle]');
            block.classList.toggle('is-stage-off', !!own && !own.checked);
        });
        document.querySelectorAll('.settings-approver-stage-body').forEach(function (body) {
            body.inert = !!body.closest('.is-stage-off');
        });
        document.querySelectorAll('[data-approver-pick]').forEach(function (btn) {
            btn.disabled = !!btn.closest('.is-stage-off');
        });
    }
    refreshStages();
    document.querySelectorAll('input[data-approver-stage-toggle]').forEach(function (chk) {
        var block = chk.closest('[data-approver-stage]');
        if (!block) return;
        var tipHost = chk.closest('.settings-approver-toggle');
        chk.addEventListener('change', function () {
            refreshStages();
            if (!tipHost) return;
            var tipText = tipHost.getAttribute(chk.checked ? 'data-tip-on' : 'data-tip-off') || '';
            tipHost.setAttribute('aria-label', tipText);
            var tip = window.bootstrap && bootstrap.Tooltip ? bootstrap.Tooltip.getInstance(tipHost) : null;
            if (tip && typeof tip.setContent === 'function') {
                tipHost.setAttribute('data-bs-original-title', tipText);
                tip.setContent({ '.tooltip-inner': tipText });
            } else {
                tipHost.setAttribute('title', tipText);
            }
        });
    });

    document.querySelectorAll('input[data-scope-all-choice]').forEach(function (radio) {
        var levels = radio.closest('.settings-approval-scope').querySelector('[data-scope-levels]');
        if (!levels) return;
        radio.addEventListener('change', function () {
            var all = radio.checked && radio.value === '1';
            levels.inert = all;
            levels.classList.toggle('is-scope-all', all);
        });
    });

    var modalEl = document.getElementById('approverPickerModal');
    var listEl = document.getElementById('approverPickerList');
    var searchEl = document.getElementById('approverPickerSearch');
    var emptyEl = document.getElementById('approverPickerEmpty');
    var titleEl = modalEl ? modalEl.querySelector('[data-approver-picker-title]') : null;
    var activeSelect = null;
    var allSelects = Array.prototype.slice.call(document.querySelectorAll('select[data-approver-select]'));

    function roleLabelFor(sel) {
        var btn = document.querySelector('[data-approver-pick="' + sel.id + '"]');
        return btn ? (btn.getAttribute('data-role-label') || '') : '';
    }

    function avatarNode(photo) {
        if (photo) {
            var img = document.createElement('img');
            img.src = photo;
            img.alt = '';
            img.className = 'settings-approver-avatar';
            return img;
        }
        var span = document.createElement('span');
        span.className = 'settings-approver-avatar is-fallback';
        var ic = document.createElement('i');
        ic.className = 'bi bi-person';
        ic.setAttribute('aria-hidden', 'true');
        span.appendChild(ic);
        return span;
    }

    function choose(value) {
        if (!activeSelect) return;
        activeSelect.value = value;
        activeSelect.dispatchEvent(new Event('change', { bubbles: true }));
        if (window.bootstrap && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(modalEl).hide();
        }
    }

    function buildList() {
        listEl.textContent = '';
        if (!activeSelect) return;
        var takenBy = {};
        var chainKey = activeSelect.getAttribute('data-approver-chain') || '';
        allSelects.forEach(function (other) {
            if (other !== activeSelect && other.value !== '0'
                && (other.getAttribute('data-approver-chain') || '') === chainKey) {
                takenBy[other.value] = roleLabelFor(other);
            }
        });
        Array.prototype.forEach.call(activeSelect.options, function (opt) {
            var isNone = opt.value === '0';
            var item = document.createElement('button');
            item.type = 'button';
            item.className = 'list-group-item list-group-item-action approver-picker-item';
            item.setAttribute('data-keep-label', '');
            item.setAttribute('data-value', opt.value);
            var name = isNone ? opt.textContent.trim() : (opt.getAttribute('data-name') || '');
            var job = isNone ? '' : (opt.getAttribute('data-job-title') || '');
            var email = isNone ? '' : (opt.textContent.split('—').slice(1).join('—').trim());
            item.setAttribute('data-search', (name + ' ' + job + ' ' + email).toLowerCase());
            if (isNone) item.classList.add('is-none');

            item.appendChild(avatarNode(isNone ? '' : (opt.getAttribute('data-photo') || '')));
            var text = document.createElement('div');
            text.className = 'approver-picker-text';
            var n = document.createElement('div');
            n.className = 'approver-picker-name';
            n.textContent = name;
            text.appendChild(n);
            if (job || email) {
                var meta = document.createElement('div');
                meta.className = 'approver-picker-meta';
                meta.textContent = job && email ? job + ' · ' + email : (job || email);
                text.appendChild(meta);
            }
            item.appendChild(text);

            if (opt.value === activeSelect.value) {
                item.classList.add('active');
                item.setAttribute('aria-current', 'true');
                var cur = document.createElement('span');
                cur.className = 'badge approver-picker-badge is-current';
                cur.textContent = currentText;
                item.appendChild(cur);
            } else if (!isNone && takenBy[opt.value]) {
                item.disabled = true;
                var tb = document.createElement('span');
                tb.className = 'badge approver-picker-badge';
                tb.textContent = takenText.replace(':role', takenBy[opt.value]);
                item.appendChild(tb);
            }
            item.addEventListener('click', function () {
                choose(opt.value);
            });
            listEl.appendChild(item);
        });
        filterList();
    }

    function filterList() {
        var q = (searchEl.value || '').trim().toLowerCase();
        var shown = 0;
        Array.prototype.forEach.call(listEl.children, function (item) {
            var match = q === '' || (item.getAttribute('data-search') || '').indexOf(q) !== -1;
            if (item.classList.contains('is-none') && q !== '') match = false;
            item.classList.toggle('d-none', !match);
            if (match) shown++;
        });
        emptyEl.classList.toggle('d-none', shown > 0);
    }

    if (modalEl && listEl && searchEl) {
        document.querySelectorAll('[data-approver-pick]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (btn.disabled || btn.closest('.is-stage-off')) return;
                activeSelect = document.getElementById(btn.getAttribute('data-approver-pick'));
                if (!activeSelect || !window.bootstrap || !bootstrap.Modal) return;
                if (bootstrap.Tooltip) {
                    var tip = bootstrap.Tooltip.getInstance(btn);
                    if (tip) tip.hide();
                }
                if (titleEl) {
                    var role = btn.getAttribute('data-role-label') || '';
                    titleEl.textContent = role ? pickerTitle + ' — ' + role : pickerTitle;
                }
                searchEl.value = '';
                buildList();
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            });
        });
        searchEl.addEventListener('input', filterList);
        searchEl.addEventListener('keydown', function (ev) {
            if (ev.key !== 'Enter') return;
            ev.preventDefault();
            var first = listEl.querySelector('.approver-picker-item:not(.d-none):not(:disabled)');
            if (first) first.click();
        });
        modalEl.addEventListener('shown.bs.modal', function () {
            searchEl.focus();
        });
    }
})();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
