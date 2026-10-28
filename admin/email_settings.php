<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/email_settings.php';

$user = require_permission('admin.settings');
$pageTitle = __('nav.email_settings');
$departments = departments_list(true);
$deptIds = [];
foreach ($departments as $department) {
    $deptIds[(int) $department['id']] = $department;
}
$scopeDeptId = (int) ($_POST['department_id'] ?? $_GET['department_id'] ?? 0);
if ($scopeDeptId > 0 && !isset($deptIds[$scopeDeptId])) {
    flash('danger', __('email.invalid_department'));
    redirect(base_url('admin/email_settings.php'));
}
$isDeptScope = $scopeDeptId > 0;
$scopeReturn = $isDeptScope ? ('?department_id=' . $scopeDeptId) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    verify_csrf();
    try {
        $updatedBy = user_actor_name($user);
        if ($isDeptScope) {
            dms_save_department_email_settings($scopeDeptId, $_POST, $updatedBy);
        } else {
            dms_save_email_settings($_POST, $updatedBy);
        }
        flash('success', __('email.updated_ok'));
    } catch (Throwable $e) {
        flash('danger', $e->getMessage());
    }
    redirect(base_url('admin/email_settings.php' . $scopeReturn));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['copy_system'])) {
    verify_csrf();
    if (!$isDeptScope) {
        flash('danger', __('email.invalid_department'));
        redirect(base_url('admin/email_settings.php'));
    }
    try {
        dms_copy_system_email_settings_to_department($scopeDeptId, user_actor_name($user));
        flash('success', __('email.copy_system_ok'));
    } catch (Throwable $e) {
        flash('danger', $e->getMessage());
    }
    redirect(base_url('admin/email_settings.php' . $scopeReturn));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_invitations'])) {
    verify_csrf();
    require_once __DIR__ . '/../config/mailer.php';
    if (dms_import_invitations_smtp()) {
        flash('success', __('email.import_ok'));
    } else {
        flash('danger', __('email.import_fail'));
    }
    redirect(base_url('admin/email_settings.php'));
}

$currentConfig = dms_email_settings_for_form($isDeptScope ? $scopeDeptId : null);
$summary = $isDeptScope
    ? dms_get_department_email_settings($scopeDeptId)
    : dms_get_email_settings();
$resolved = dms_resolve_email_settings($isDeptScope ? $scopeDeptId : null);
$scopeDeptName = $isDeptScope ? department_display_name($deptIds[$scopeDeptId]) : '';

require __DIR__ . '/../includes/header.php';
?>
<div class="page-header page-narrow">
    <div>
        <h1 class="page-title"><?= e(__('email.title')) ?></h1>
        <p class="page-sub"><?= e($isDeptScope ? __('email.subtitle_department') : __('email.subtitle')) ?></p>
    </div>
    <div class="page-header-actions">
        <button type="submit" name="update_settings" value="1" form="emailConfigForm" class="btn btn-kff btn-sm">
            <i class="bi bi-check2-circle me-1"></i><?= e(__('email.save')) ?>
        </button>
    </div>
</div>

<?php
$settingsHubActive = 'email';
require __DIR__ . '/../includes/settings_hub_nav.php';
?>

<div class="email-settings-page page-narrow">
    <form method="get" class="card-panel email-scope-bar mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-8">
                <label class="form-label" for="email_scope"><?= e(__('email.scope')) ?></label>
                <select class="form-select" id="email_scope" name="department_id" onchange="this.form.submit()">
                    <option value=""><?= e(__('email.scope_system')) ?></option>
                    <?php foreach ($departments as $department): ?>
                        <option value="<?= (int) $department['id'] ?>" <?= $scopeDeptId === (int) $department['id'] ? 'selected' : '' ?>>
                            <?= e(department_display_name($department)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text"><?= e($isDeptScope ? __('email.scope_hint_department') : __('email.scope_hint_system')) ?></div>
            </div>
        </div>
    </form>

    <div class="security-warning">
        <h5 class="text-warning mb-2"><i class="bi bi-exclamation-triangle me-1"></i> <?= e(__('email.security_title')) ?></h5>
        <p class="mb-2"><?= e($isDeptScope ? __('email.security_body_department') : __('email.security_body')) ?></p>
        <div class="d-flex flex-wrap gap-2 mb-2">
            <?php if ($isDeptScope): ?>
                <span class="dms-email-chip"><i class="bi bi-envelope"></i> <?= e(__('email.chip_letters')) ?></span>
                <span class="dms-email-chip"><i class="bi bi-building"></i> <?= e($scopeDeptName) ?></span>
            <?php else: ?>
                <span class="dms-email-chip"><i class="bi bi-key"></i> <?= e(__('email.chip_forgot')) ?></span>
                <span class="dms-email-chip"><i class="bi bi-envelope"></i> <?= e(__('email.chip_notify')) ?></span>
                <span class="dms-email-chip"><i class="bi bi-send"></i> <?= e(__('email.chip_fallback')) ?></span>
            <?php endif; ?>
        </div>
        <p class="small mb-0 text-muted"><?= e($isDeptScope ? __('email.security_note_department') : __('email.security_note')) ?></p>
    </div>

    <?php if ($isDeptScope): ?>
    <form method="post" class="mb-3">
        <?= csrf_field() ?>
        <input type="hidden" name="department_id" value="<?= (int) $scopeDeptId ?>">
        <button type="submit" name="copy_system" value="1" class="btn btn-outline-secondary btn-sm"
                data-confirm="<?= e(__('email.copy_system_confirm')) ?>">
            <i class="bi bi-copy me-1"></i> <?= e(__('email.copy_system')) ?>
        </button>
        <div class="form-text"><?= e(__('email.copy_system_hint')) ?></div>
    </form>
    <?php else: ?>
    <form method="post" class="mb-3">
        <?= csrf_field() ?>
        <button type="submit" name="import_invitations" value="1" class="btn btn-outline-secondary btn-sm"
                data-confirm="<?= e(__('email.import_confirm')) ?>">
            <i class="bi bi-download me-1"></i> <?= e(__('email.import_invitations')) ?>
        </button>
    </form>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="card-panel mb-0">
                <div class="card-header">
                    <div class="admin-editor-header">
                        <span class="admin-editor-icon"><i class="bi bi-envelope-at"></i></span>
                        <span><?= e(__('email.smtp_section')) ?><?= $isDeptScope ? ' — ' . e($scopeDeptName) : '' ?></span>
                    </div>
                </div>
                <div class="card-body">
                    <form method="post" id="emailConfigForm" class="admin-form" autocomplete="off">
                        <?= csrf_field() ?>
                        <?php if ($isDeptScope): ?>
                            <input type="hidden" name="department_id" value="<?= (int) $scopeDeptId ?>">
                        <?php endif; ?>
                        <div class="visually-hidden" aria-hidden="true">
                            <input type="text" name="fake_user" tabindex="-1" autocomplete="username">
                            <input type="password" name="fake_pass" tabindex="-1" autocomplete="current-password">
                        </div>
                        <div class="admin-form-section admin-form-section-last">
                            <div class="row g-3">
                                <div class="col-md-6 ltr-field">
                                    <label class="form-label" for="smtp_host"><?= e(__('email.smtp_host')) ?> <span class="text-danger">*</span></label>
                                    <input class="form-control" type="text" id="smtp_host" name="smtp_host" required
                                           value="<?= e($currentConfig['smtp_host']) ?>">
                                </div>
                                <div class="col-md-6 ltr-field">
                                    <label class="form-label" for="smtp_port"><?= e(__('email.port')) ?> <span class="text-danger">*</span></label>
                                    <input class="form-control" type="number" id="smtp_port" name="smtp_port" min="1" max="65535" required
                                           value="<?= (int) $currentConfig['smtp_port'] ?>">
                                </div>
                                <div class="col-md-6 ltr-field">
                                    <label class="form-label" for="smtp_encryption"><?= e(__('email.encryption')) ?></label>
                                    <select class="form-select" id="smtp_encryption" name="smtp_encryption">
                                        <option value="tls" <?= $currentConfig['smtp_encryption'] === 'tls' ? 'selected' : '' ?>>TLS</option>
                                        <option value="ssl" <?= $currentConfig['smtp_encryption'] === 'ssl' ? 'selected' : '' ?>>SSL</option>
                                        <option value="" <?= $currentConfig['smtp_encryption'] === '' ? 'selected' : '' ?>><?= e(__('email.no_encryption')) ?></option>
                                    </select>
                                </div>
                                <div class="col-md-6 ltr-field">
                                    <label class="form-label" for="timeout_seconds"><?= e(__('email.timeout')) ?></label>
                                    <input class="form-control" type="number" id="timeout_seconds" name="timeout_seconds" min="5" max="300"
                                           value="<?= (int) $currentConfig['timeout_seconds'] ?>">
                                </div>
                                <div class="col-md-6 ltr-field">
                                    <label class="form-label" for="smtp_username"><?= e(__('email.username')) ?> <span class="text-danger">*</span></label>
                                    <input class="form-control" type="text" id="smtp_username" name="smtp_username" required
                                           autocomplete="off" value="<?= e($currentConfig['smtp_username']) ?>">
                                </div>
                                <div class="col-md-6 ltr-field">
                                    <label class="form-label" for="smtp_password"><?= e(__('email.password')) ?></label>
                                    <div class="input-group">
                                        <input class="form-control" type="password" id="smtp_password" name="smtp_password"
                                               autocomplete="new-password" value=""
                                               placeholder="<?= e(!empty($currentConfig['has_smtp_password']) ? __('email.password_keep') : '') ?>">
                                        <button class="btn btn-outline-secondary" type="button" id="togglePassword" title="show/hide">
                                            <i class="bi bi-eye" id="togglePasswordIcon"></i>
                                        </button>
                                    </div>
                                    <div class="form-text <?= !empty($currentConfig['has_smtp_password']) ? 'text-success' : 'text-muted' ?>">
                                        <?= e(!empty($currentConfig['has_smtp_password']) ? __('email.password_saved') : __('email.password_not_saved')) ?>
                                    </div>
                                </div>
                                <div class="col-md-6 ltr-field">
                                    <label class="form-label" for="from_email"><?= e(__('email.from_email')) ?> <span class="text-danger">*</span></label>
                                    <input class="form-control" type="email" id="from_email" name="from_email" required
                                           value="<?= e($currentConfig['from_email']) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="from_name"><?= e(__('email.from_name')) ?></label>
                                    <input class="form-control" type="text" id="from_name" name="from_name"
                                           value="<?= e($currentConfig['from_name']) ?>">
                                    <div class="form-text"><?= e(__('email.from_name_hint')) ?></div>
                                </div>
                                <div class="col-12">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                                            <?= !empty($summary['is_active']) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="is_active"><?= e(__('email.enable')) ?></label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="admin-form-actions">
                            <button type="submit" name="update_settings" value="1" class="btn btn-kff">
                                <i class="bi bi-save me-1"></i> <?= e(__('email.save')) ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card-panel mt-4">
                <div class="card-header"><i class="bi bi-send me-1"></i> <?= e(__('email.test_section')) ?></div>
                <div class="card-body">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-8 ltr-field">
                            <label class="form-label" for="test_email"><?= e(__('email.test_address')) ?></label>
                            <input class="form-control" type="email" id="test_email" placeholder="example@domain.com" value="">
                        </div>
                        <div class="col-md-4">
                            <button type="button" class="btn btn-outline-success w-100" id="sendTestBtn">
                                <i class="bi bi-send me-1"></i> <?= e(__('email.send_test')) ?>
                            </button>
                        </div>
                    </div>
                    <div id="testResult" class="mt-3" style="display:none;"></div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card-panel">
                <div class="card-header"><i class="bi bi-info-circle me-1"></i> <?= e(__('email.current_section')) ?></div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr><td><strong><?= e(__('email.host_label')) ?></strong></td><td class="dms-email-data"><?= e($summary['smtp_host']) ?></td></tr>
                        <tr><td><strong><?= e(__('email.port_label')) ?></strong></td><td class="dms-email-data"><?= (int) $summary['smtp_port'] ?></td></tr>
                        <tr><td><strong><?= e(__('email.enc_label')) ?></strong></td><td class="dms-email-data"><?= e($summary['smtp_encryption'] !== '' ? $summary['smtp_encryption'] : __('email.none')) ?></td></tr>
                        <tr><td><strong><?= e(__('email.from_email_label')) ?></strong></td><td class="dms-email-data"><?= e($summary['from_email'] ?: __('common.none')) ?></td></tr>
                        <tr><td><strong><?= e(__('email.from_name_label')) ?></strong></td><td><?= e($summary['from_name'] ?: __('common.none')) ?></td></tr>
                        <tr>
                            <td><strong><?= e(__('email.password')) ?>:</strong></td>
                            <td>
                                <?= !empty($currentConfig['has_smtp_password'])
                                    ? '<span class="badge text-bg-success">' . e(__('email.password_stored')) . '</span>'
                                    : '<span class="badge text-bg-warning">' . e(__('email.password_not_saved')) . '</span>' ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong><?= e(__('email.status_label')) ?></strong></td>
                            <td>
                                <?= !empty($summary['is_active'])
                                    ? '<span class="badge text-bg-success">' . e(__('common.enabled')) . '</span>'
                                    : '<span class="badge text-bg-secondary">' . e(__('common.inactive')) . '</span>' ?>
                            </td>
                        </tr>
                    </table>
                    <?php if ($isDeptScope): ?>
                        <div class="small mt-3 <?= (($resolved['resolved_from'] ?? '') === 'department') ? 'text-success' : 'text-muted' ?>">
                            <?= (($resolved['resolved_from'] ?? '') === 'department')
                                ? e(__('email.send_uses_department'))
                                : e(__('email.send_uses_system')) ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($summary['updated_at'])): ?>
                        <div class="small text-muted mt-3">
                            <?= e(__('email.last_updated')) ?> <?= e(format_datetime($summary['updated_at'])) ?>
                            <?php if (!empty($summary['updated_by'])): ?> — <?= e(__('email.by')) ?> <?= e($summary['updated_by']) ?><?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$jsNeedEmail = json_encode(__('email.js_need_email'), JSON_UNESCAPED_UNICODE);
$jsSending = json_encode(__('email.js_sending'), JSON_UNESCAPED_UNICODE);
$jsSent = json_encode(__('email.js_sent'), JSON_UNESCAPED_UNICODE);
$jsFail = json_encode(__('email.js_fail'), JSON_UNESCAPED_UNICODE);
$jsNetwork = json_encode(__('email.js_network'), JSON_UNESCAPED_UNICODE);
$jsDeptId = json_encode($scopeDeptId);
$pageScripts = <<<JS
<script>
document.addEventListener('DOMContentLoaded', function () {
  var toggle = document.getElementById('togglePassword');
  if (toggle) {
    toggle.addEventListener('click', function () {
      var input = document.getElementById('smtp_password');
      var icon = document.getElementById('togglePasswordIcon');
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      icon.classList.toggle('bi-eye', !show);
      icon.classList.toggle('bi-eye-slash', show);
    });
  }

  var btn = document.getElementById('sendTestBtn');
  if (!btn) return;
  btn.addEventListener('click', function () {
    var testEmail = (document.getElementById('test_email').value || '').trim();
    var resultDiv = document.getElementById('testResult');
    if (!testEmail) {
      if (window.DmsSwal) DmsSwal.warning({$jsNeedEmail});
      else { resultDiv.style.display = 'block'; resultDiv.className = 'alert alert-warning mt-3'; resultDiv.textContent = {$jsNeedEmail}; }
      return;
    }
    if (window.DmsSwal) DmsSwal.flash('info', {$jsSending});
    else { resultDiv.style.display = 'block'; resultDiv.className = 'alert alert-info mt-3'; resultDiv.textContent = {$jsSending}; }
    btn.disabled = true;

    var body = 'test_email=' + encodeURIComponent(testEmail)
      + '&department_id=' + encodeURIComponent({$jsDeptId})
      + '&smtp_password=' + encodeURIComponent((document.getElementById('smtp_password').value || '').trim())
      + '&csrf_token=' + encodeURIComponent(document.querySelector('#emailConfigForm input[name="csrf_token"]').value);

    fetch('test_email.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: body
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      var msg = data.message || (data.success ? {$jsSent} : {$jsFail});
      if (window.DmsSwal) DmsSwal.flash(data.success ? 'success' : 'danger', msg);
      else {
        resultDiv.className = 'alert mt-3 ' + (data.success ? 'alert-success' : 'alert-danger');
        resultDiv.style.whiteSpace = 'pre-wrap';
        resultDiv.textContent = msg;
      }
    })
    .catch(function (err) {
      var msg = {$jsNetwork} + err.message;
      if (window.DmsSwal) DmsSwal.error(msg);
      else { resultDiv.className = 'alert alert-danger mt-3'; resultDiv.textContent = msg; }
    })
    .finally(function () { btn.disabled = false; });
  });
});
</script>
JS;
require __DIR__ . '/../includes/footer.php';
?>
