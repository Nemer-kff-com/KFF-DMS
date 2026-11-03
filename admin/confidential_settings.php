<?php
require_once __DIR__ . '/../config/auth.php';

$user = require_permission('admin.settings');
ensure_org_settings();
$pageTitle = __('settings.tab_confidential');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        set_setting(
            'confidentiality_restricts_visibility',
            isset($_POST['confidentiality_restricts_visibility']) ? '1' : '0'
        );
        foreach (['prep_signer', 'reviewer', 'approver', 'comms', 'cc'] as $confStage) {
            set_setting('conf_attach_' . $confStage, isset($_POST['conf_attach_' . $confStage]) ? '1' : '0');
        }
        set_setting(
            'conf_external_notify_mode',
            isset($_POST['conf_external_notify_redacted']) ? 'redacted' : 'full'
        );
        flash('success', __('settings.saved'));
    } catch (Throwable $e) {
        flash('danger', $e->getMessage());
    }
    redirect(base_url('admin/confidential_settings.php'));
}

$settings = [
    'confidentiality_restricts_visibility' => setting('confidentiality_restricts_visibility', '1'),
    'conf_attach_prep_signer' => setting('conf_attach_prep_signer', '1'),
    'conf_attach_reviewer' => setting('conf_attach_reviewer', '1'),
    'conf_attach_approver' => setting('conf_attach_approver', '1'),
    'conf_attach_comms' => setting('conf_attach_comms', '1'),
    'conf_attach_cc' => setting('conf_attach_cc', '1'),
    'conf_external_notify_mode' => setting('conf_external_notify_mode', 'redacted'),
];

require __DIR__ . '/../includes/header.php';
?>
<div class="page-header page-narrow">
    <div>
        <h1 class="page-title"><?= e(__('settings.conf_section_title')) ?></h1>
        <p class="page-sub mb-0"><?= e(__('settings.conf_section_desc')) ?></p>
    </div>
    <div class="page-header-actions">
        <button class="btn btn-kff btn-sm" type="submit" form="confidentialSettingsForm">
            <i class="bi bi-check2-circle me-1"></i><?= e(__('settings.save')) ?>
        </button>
    </div>
</div>

<?php
$settingsHubActive = 'confidential';
require __DIR__ . '/../includes/settings_hub_nav.php';
?>

<form method="post" id="confidentialSettingsForm" class="settings-page page-narrow">
    <?= csrf_field() ?>
    <div class="card-panel mb-0">
        <div class="card-header">
            <i class="bi bi-eye-slash me-1"></i><?= e(__('settings.conf_section_title')) ?>
        </div>
        <div class="card-body p-4">
            <?php require __DIR__ . '/../includes/settings_confidential.php'; ?>
        </div>
    </div>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
