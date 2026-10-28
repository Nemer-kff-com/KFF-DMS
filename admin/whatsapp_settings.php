<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/whatsapp_settings.php';

$user = require_permission('admin.settings');
$pageTitle = __('nav.whatsapp_settings');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    verify_csrf();
    try {
        dms_save_whatsapp_settings($_POST, user_actor_name($user));
        flash('success', __('wa.updated_ok'));
    } catch (Throwable $e) {
        flash('danger', $e->getMessage());
    }
    redirect(base_url('admin/whatsapp_settings.php'));
}

$form = dms_whatsapp_settings_for_form();
$cfg = $form['provider_config_public'] ?? [];
$custom = is_array($cfg['custom'] ?? null) ? $cfg['custom'] : dms_whatsapp_custom_defaults();
$currentProvider = (string) ($form['provider'] ?? 'twilio');
$templatesByProvider = dms_whatsapp_templates_map(dms_get_whatsapp_settings());
$templates = $templatesByProvider[$currentProvider] ?? dms_whatsapp_empty_templates();
$ready = dms_whatsapp_readiness();
$full = dms_get_whatsapp_settings();

$stateLabel = match ($ready['state']) {
    'ready' => __('wa.ready'),
    'disabled' => __('wa.disabled'),
    default => __('wa.incomplete'),
};
$stateBadge = match ($ready['state']) {
    'ready' => 'text-bg-success',
    'disabled' => 'text-bg-secondary',
    default => 'text-bg-warning',
};

require __DIR__ . '/../includes/header.php';
?>
<div class="page-header page-narrow">
    <div>
        <h1 class="page-title"><?= e(__('wa.title')) ?></h1>
        <p class="page-sub"><?= e(__('wa.subtitle')) ?></p>
    </div>
    <div class="page-header-actions">
        <button type="submit" name="update_settings" value="1" form="waSettingsForm" class="btn btn-kff btn-sm">
            <i class="bi bi-check2-circle me-1"></i><?= e(__('wa.save')) ?>
        </button>
    </div>
</div>

<?php
$settingsHubActive = 'whatsapp';
require __DIR__ . '/../includes/settings_hub_nav.php';
?>

<div class="wa-settings-page page-narrow">
    <div class="security-warning">
        <h5 class="text-warning mb-2"><i class="bi bi-exclamation-triangle me-1"></i> <?= e(__('wa.security_title')) ?></h5>
        <p class="mb-2"><?= e(__('wa.security_body')) ?></p>
        <div class="d-flex flex-wrap gap-2 mb-2">
            <span class="dms-wa-chip"><i class="bi bi-send"></i> <?= e(__('wa.chip_letters')) ?></span>
            <span class="dms-wa-chip"><i class="bi bi-people"></i> <?= e(__('wa.chip_bulk')) ?></span>
            <span class="dms-wa-chip"><i class="bi bi-check2-circle"></i> <?= e(__('wa.chip_rsvp')) ?></span>
        </div>
        <p class="small mb-0 text-muted"><?= e(__('wa.security_note')) ?></p>
    </div>

    <div class="row g-4">
        <div class="col-xl-8">
            <form method="post" id="waSettingsForm" class="admin-form" autocomplete="off">
                <?= csrf_field() ?>

                <div class="card-panel mb-4">
                    <div class="card-header">
                        <div class="admin-editor-header">
                            <span class="admin-editor-icon"><i class="bi bi-whatsapp"></i></span>
                            <span><?= e(__('wa.provider_section')) ?></span>
                        </div>
                    </div>
                    <div class="card-body">
                        <p class="admin-form-hint mb-3"><?= e(__('wa.provider_hint')) ?></p>
                        <div class="wa-provider-grid">
                            <?php
                            $providers = [
                                'twilio' => ['name' => 'Twilio', 'icon' => 'bi-broadcast', 'hint' => __('wa.provider_twilio_hint')],
                                'meta' => ['name' => 'Meta Cloud API', 'icon' => 'bi-meta', 'hint' => __('wa.provider_meta_hint')],
                                'custom' => ['name' => __('wa.provider_custom'), 'icon' => 'bi-sliders', 'hint' => __('wa.provider_custom_hint')],
                            ];
                            foreach ($providers as $code => $meta):
                            ?>
                            <label class="wa-provider-card">
                                <input type="radio" name="provider" value="<?= e($code) ?>" <?= $currentProvider === $code ? 'checked' : '' ?>>
                                <span class="wa-provider-card-inner">
                                    <i class="bi <?= e($meta['icon']) ?>"></i>
                                    <strong><?= e($meta['name']) ?></strong>
                                    <small><?= e($meta['hint']) ?></small>
                                </span>
                            </label>
                            <?php endforeach; ?>
                        </div>

                        <div class="row g-3 mt-1">
                            <div class="col-md-6 ltr-field">
                                <label class="form-label" for="default_cc"><?= e(__('wa.default_cc')) ?></label>
                                <input class="form-control" type="text" id="default_cc" name="default_cc" value="<?= e($form['default_cc'] ?? '966') ?>">
                                <div class="form-text"><?= e(__('wa.default_cc_hint')) ?></div>
                            </div>
                            <div class="col-md-6 d-flex align-items-end">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?= !empty($form['is_active']) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="is_active"><?= e(__('wa.enable')) ?></label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-panel mb-4 wa-provider-panel" data-provider="twilio">
                    <div class="card-header"><i class="bi bi-key me-1"></i><?= e(__('wa.twilio_creds')) ?></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6 ltr-field">
                                <label class="form-label" for="from_number"><?= e(__('wa.from_number')) ?></label>
                                <input class="form-control" type="text" id="from_number" name="from_number" placeholder="<?= e(__('wa.from_ph')) ?>" value="<?= e((string) ($form['from_number'] ?? '')) ?>">
                            </div>
                            <div class="col-md-6 ltr-field">
                                <label class="form-label" for="account_sid">Account SID</label>
                                <input class="form-control" type="text" id="account_sid" name="account_sid" value="<?= e((string) ($cfg['account_sid'] ?? '')) ?>">
                            </div>
                            <div class="col-md-12 ltr-field">
                                <label class="form-label" for="auth_token">Auth Token</label>
                                <div class="input-group">
                                    <input class="form-control" type="password" id="auth_token" name="auth_token" placeholder="<?= !empty($form['auth_token_set']) ? e(__('wa.keep_secret')) : '' ?>">
                                    <button class="btn btn-outline-secondary" type="button" data-toggle-secret="auth_token"><i class="bi bi-eye"></i></button>
                                </div>
                                <?php if (!empty($form['auth_token_set'])): ?>
                                    <div class="form-text font-monospace"><?= e((string) $form['auth_token_masked']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-panel mb-4 wa-provider-panel" data-provider="meta">
                    <div class="card-header"><i class="bi bi-key me-1"></i><?= e(__('wa.meta_creds')) ?></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6 ltr-field">
                                <label class="form-label" for="phone_number_id">Phone Number ID</label>
                                <input class="form-control" type="text" id="phone_number_id" name="phone_number_id" value="<?= e((string) ($cfg['phone_number_id'] ?? '')) ?>">
                            </div>
                            <div class="col-md-6 ltr-field">
                                <label class="form-label" for="api_version">Graph API version</label>
                                <input class="form-control" type="text" id="api_version" name="api_version" placeholder="v21.0" value="<?= e((string) ($cfg['api_version'] ?? 'v21.0')) ?>">
                            </div>
                            <div class="col-md-6 ltr-field">
                                <label class="form-label" for="business_account_id"><?= e(dms_is_rtl() ? 'معرّف حساب واتساب Business' : 'Business Account ID (WABA)') ?></label>
                                <input class="form-control" type="text" id="business_account_id" name="business_account_id" value="<?= e((string) ($cfg['business_account_id'] ?? '')) ?>">
                            </div>
                            <div class="col-md-6 ltr-field">
                                <label class="form-label" for="phone_display"><?= e(dms_is_rtl() ? 'رقم الهاتف (عرض)' : 'Phone number (display)') ?></label>
                                <input class="form-control" type="text" id="phone_display" name="phone_display" placeholder="+966 11 463 0950" value="<?= e((string) ($cfg['phone_display'] ?? '')) ?>">
                            </div>
                            <div class="col-12 ltr-field">
                                <label class="form-label" for="access_token">Access Token</label>
                                <div class="input-group">
                                    <input class="form-control" type="password" id="access_token" name="access_token" placeholder="<?= !empty($form['access_token_set']) ? e(__('wa.keep_secret')) : '' ?>">
                                    <button class="btn btn-outline-secondary" type="button" data-toggle-secret="access_token"><i class="bi bi-eye"></i></button>
                                </div>
                                <?php if (!empty($form['access_token_set'])): ?>
                                    <div class="form-text font-monospace"><?= e((string) $form['access_token_masked']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-panel mb-4 wa-provider-panel" data-provider="custom">
                    <div class="card-header"><i class="bi bi-key me-1"></i><?= e(__('wa.custom_creds')) ?></div>
                    <div class="card-body">
                        <p class="admin-form-hint"><?= e(__('wa.custom_help')) ?></p>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="custom_provider_name"><?= e(__('wa.custom_name')) ?></label>
                                <input class="form-control" type="text" id="custom_provider_name" name="custom_provider_name" value="<?= e((string) ($custom['provider_name'] ?? '')) ?>">
                            </div>
                            <div class="col-md-6 ltr-field">
                                <label class="form-label" for="custom_sender_id"><?= e(__('wa.custom_sender')) ?></label>
                                <input class="form-control" type="text" id="custom_sender_id" name="custom_sender_id" value="<?= e((string) ($custom['sender_id'] ?? '')) ?>">
                            </div>
                            <div class="col-12 ltr-field">
                                <label class="form-label" for="custom_api_url"><?= e(__('wa.custom_api_url')) ?></label>
                                <input class="form-control" type="text" id="custom_api_url" name="custom_api_url" placeholder="https://api.example.com/v1/messages" value="<?= e((string) ($custom['api_url'] ?? '')) ?>">
                            </div>
                            <div class="col-md-6 ltr-field">
                                <label class="form-label" for="custom_auth_method"><?= e(__('wa.custom_auth')) ?></label>
                                <select class="form-select" id="custom_auth_method" name="custom_auth_method">
                                    <?php foreach (dms_whatsapp_auth_methods() as $method): ?>
                                        <option value="<?= e($method) ?>" <?= (($custom['auth_method'] ?? 'token') === $method) ? 'selected' : '' ?>><?= e(dms_whatsapp_auth_method_label($method)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 ltr-field wa-auth-panel" data-auth="api_key">
                                <label class="form-label" for="custom_api_key_header"><?= e(__('wa.custom_header')) ?></label>
                                <input class="form-control" type="text" id="custom_api_key_header" name="custom_api_key_header" value="<?= e((string) ($custom['api_key_header'] ?? 'X-API-Key')) ?>">
                            </div>
                            <div class="col-md-6 ltr-field wa-auth-panel" data-auth="token">
                                <label class="form-label" for="custom_auth_token">Token</label>
                                <div class="input-group">
                                    <input class="form-control" type="password" id="custom_auth_token" name="custom_auth_token" placeholder="<?= !empty($form['custom_auth_token_set']) ? e(__('wa.keep_secret')) : '' ?>">
                                    <button class="btn btn-outline-secondary" type="button" data-toggle-secret="custom_auth_token"><i class="bi bi-eye"></i></button>
                                </div>
                                <?php if (!empty($form['custom_auth_token_set'])): ?>
                                    <div class="form-text font-monospace"><?= e((string) $form['custom_auth_token_masked']) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6 ltr-field wa-auth-panel" data-auth="api_key">
                                <label class="form-label" for="custom_api_key">API Key</label>
                                <div class="input-group">
                                    <input class="form-control" type="password" id="custom_api_key" name="custom_api_key" placeholder="<?= !empty($form['custom_api_key_set']) ? e(__('wa.keep_secret')) : '' ?>">
                                    <button class="btn btn-outline-secondary" type="button" data-toggle-secret="custom_api_key"><i class="bi bi-eye"></i></button>
                                </div>
                                <?php if (!empty($form['custom_api_key_set'])): ?>
                                    <div class="form-text font-monospace"><?= e((string) $form['custom_api_key_masked']) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6 ltr-field wa-auth-panel" data-auth="basic">
                                <label class="form-label" for="custom_auth_username"><?= e(__('email.username')) ?></label>
                                <input class="form-control" type="text" id="custom_auth_username" name="custom_auth_username" value="<?= e((string) ($custom['auth_username'] ?? '')) ?>">
                            </div>
                            <div class="col-md-6 ltr-field wa-auth-panel" data-auth="basic">
                                <label class="form-label" for="custom_auth_password"><?= e(__('email.password')) ?></label>
                                <input class="form-control" type="password" id="custom_auth_password" name="custom_auth_password" placeholder="<?= !empty($form['custom_auth_password_set']) ? e(__('wa.keep_secret')) : '' ?>">
                            </div>
                            <div class="col-12 ltr-field">
                                <label class="form-label" for="custom_http_headers"><?= e(__('wa.custom_headers')) ?></label>
                                <textarea class="form-control font-monospace" id="custom_http_headers" name="custom_http_headers" rows="2" placeholder='{"X-Custom":"value"}'><?= e((string) ($custom['http_headers'] ?? '')) ?></textarea>
                            </div>
                            <div class="col-12 ltr-field">
                                <label class="form-label" for="custom_text_request_body"><?= e(__('wa.custom_text_body')) ?></label>
                                <textarea class="form-control font-monospace" id="custom_text_request_body" name="custom_text_request_body" rows="6"><?= e((string) ($custom['text_request_body'] ?? '')) ?></textarea>
                            </div>
                            <div class="col-12 ltr-field">
                                <label class="form-label" for="custom_template_request_body"><?= e(__('wa.custom_tpl_body')) ?></label>
                                <textarea class="form-control font-monospace" id="custom_template_request_body" name="custom_template_request_body" rows="6"><?= e((string) ($custom['template_request_body'] ?? '')) ?></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="custom_api_examples"><?= e(__('wa.custom_examples')) ?></label>
                                <textarea class="form-control" id="custom_api_examples" name="custom_api_examples" rows="4"><?= e((string) ($custom['api_examples'] ?? '')) ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-panel mb-4">
                    <div class="card-header"><i class="bi bi-file-earmark-text me-1"></i><?= e(__('wa.templates')) ?></div>
                    <div class="card-body">
                        <p class="admin-form-hint mb-3">
                            <span data-provider-help="twilio"><?= e(__('wa.templates_twilio')) ?></span>
                            <span data-provider-help="meta" hidden><?= e(__('wa.templates_meta')) ?></span>
                            <span data-provider-help="custom" hidden><?= e(__('wa.templates_custom')) ?></span>
                        </p>
                        <div class="row g-3">
                            <div class="col-md-6 ltr-field">
                                <label class="form-label" for="template_letter_ar"><?= e(__('wa.tpl_ar')) ?></label>
                                <input class="form-control" type="text" id="template_letter_ar" name="template_letter_ar" value="<?= e((string) ($templates['letter_ar'] ?? '')) ?>">
                            </div>
                            <div class="col-md-6 ltr-field">
                                <label class="form-label" for="template_letter_en"><?= e(__('wa.tpl_en')) ?></label>
                                <input class="form-control" type="text" id="template_letter_en" name="template_letter_en" value="<?= e((string) ($templates['letter_en'] ?? '')) ?>">
                            </div>
                        </div>
                        <div class="admin-form-actions mt-3 mb-0">
                            <button type="submit" name="update_settings" value="1" class="btn btn-kff">
                                <i class="bi bi-save me-1"></i><?= e(__('wa.save')) ?>
                            </button>
                        </div>
                    </div>
                </div>
            </form>

            <div class="card-panel">
                <div class="card-header"><i class="bi bi-send me-1"></i><?= e(__('wa.test_section')) ?></div>
                <div class="card-body">
                    <p class="small text-muted mb-3"><?= e(__('wa.test_hint')) ?></p>
                    <p class="small text-muted mb-3" id="metaTestHint" <?= $currentProvider === 'meta' ? '' : 'hidden' ?>><?= e(__('wa.test_meta_hint')) ?></p>
                    <div class="row g-3 align-items-end">
                        <div class="col-md-6 ltr-field">
                            <label class="form-label" for="test_mobile"><?= e(__('wa.test_mobile')) ?></label>
                            <input class="form-control" type="text" id="test_mobile" placeholder="<?= e(__('wa.test_mobile_ph')) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="test_lang"><?= e(__('wa.test_lang')) ?></label>
                            <select class="form-select" id="test_lang">
                                <option value="ar"><?= e(dms_is_rtl() ? 'عربي' : 'Arabic') ?></option>
                                <option value="en">English</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button type="button" class="btn btn-success w-100" id="sendWhatsappTestBtn">
                                <i class="bi bi-whatsapp me-1"></i><?= e(__('wa.send_test')) ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card-panel wa-status-card">
                <div class="card-header"><i class="bi bi-info-circle me-1"></i><?= e(__('wa.current_section')) ?></div>
                <div class="card-body">
                    <div class="wa-status-hero">
                        <span class="badge <?= e($stateBadge) ?> fs-6"><?= e($stateLabel) ?></span>
                    </div>
                    <table class="table table-sm mb-0">
                        <tr>
                            <td><strong><?= e(__('wa.provider_label')) ?></strong></td>
                            <td><?= e(dms_whatsapp_provider_label($currentProvider, $full)) ?></td>
                        </tr>
                        <tr>
                            <td><strong><?= e(__('wa.status_label')) ?></strong></td>
                            <td><?= !empty($form['is_active']) ? e(__('common.enabled')) : e(__('wa.disabled')) ?></td>
                        </tr>
                        <?php if ($ready['missing']): ?>
                        <tr>
                            <td><strong><?= e(__('wa.missing')) ?></strong></td>
                            <td><?= e(implode('، ', $ready['missing'])) ?></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                    <?php if (!empty($form['updated_at'])): ?>
                        <div class="small text-muted mt-3">
                            <?= e(__('wa.last_updated')) ?> <?= e(format_datetime($form['updated_at'])) ?>
                            <?php if (!empty($form['updated_by'])): ?> — <?= e(__('wa.by')) ?> <?= e($form['updated_by']) ?><?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
$jsNeed = json_encode(__('wa.js_need_mobile'), JSON_UNESCAPED_UNICODE);
$jsSending = json_encode(__('wa.js_sending'), JSON_UNESCAPED_UNICODE);
$jsNetwork = json_encode(__('wa.js_network'), JSON_UNESCAPED_UNICODE);
$csrf = json_encode(csrf_token());
$tplJson = json_encode($templatesByProvider, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$pageScripts = <<<JS
<script>
(function () {
  var templatesByProvider = {$tplJson};
  var lastProvider = (document.querySelector('input[name="provider"]:checked') || {}).value || 'twilio';

  function currentProvider() {
    var el = document.querySelector('input[name="provider"]:checked');
    return el ? el.value : 'twilio';
  }
  function readTemplates() {
    return {
      letter_ar: (document.getElementById('template_letter_ar') || {}).value || '',
      letter_en: (document.getElementById('template_letter_en') || {}).value || ''
    };
  }
  function writeTemplates(values) {
    values = values || {};
    var ar = document.getElementById('template_letter_ar');
    var en = document.getElementById('template_letter_en');
    if (ar) ar.value = values.letter_ar || '';
    if (en) en.value = values.letter_en || '';
  }
  function syncProviderUi() {
    var provider = currentProvider();
    document.querySelectorAll('.wa-provider-panel').forEach(function (el) {
      el.hidden = el.getAttribute('data-provider') !== provider;
    });
    document.querySelectorAll('[data-provider-help]').forEach(function (el) {
      el.hidden = el.getAttribute('data-provider-help') !== provider;
    });
    var metaHint = document.getElementById('metaTestHint');
    if (metaHint) metaHint.hidden = provider !== 'meta';
    syncAuthUi();
  }
  function syncAuthUi() {
    var method = (document.getElementById('custom_auth_method') || {}).value || 'token';
    var provider = currentProvider();
    document.querySelectorAll('.wa-auth-panel').forEach(function (el) {
      el.hidden = !(provider === 'custom' && el.getAttribute('data-auth') === method);
    });
  }
  document.querySelectorAll('input[name="provider"]').forEach(function (radio) {
    radio.addEventListener('change', function () {
      templatesByProvider[lastProvider] = readTemplates();
      lastProvider = currentProvider();
      writeTemplates(templatesByProvider[lastProvider] || {});
      syncProviderUi();
    });
  });
  var authSel = document.getElementById('custom_auth_method');
  if (authSel) authSel.addEventListener('change', syncAuthUi);
  syncProviderUi();

  document.querySelectorAll('[data-toggle-secret]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var input = document.getElementById(btn.getAttribute('data-toggle-secret'));
      if (!input) return;
      var icon = btn.querySelector('i');
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      if (icon) {
        icon.classList.toggle('bi-eye', !show);
        icon.classList.toggle('bi-eye-slash', show);
      }
    });
  });

  var testBtn = document.getElementById('sendWhatsappTestBtn');
  if (!testBtn) return;
  testBtn.addEventListener('click', function () {
    var mobile = ((document.getElementById('test_mobile') || {}).value || '').trim();
    if (!mobile) {
      if (window.DmsSwal) DmsSwal.warning({$jsNeed});
      return;
    }
    testBtn.disabled = true;
    if (window.DmsSwal) DmsSwal.flash('info', {$jsSending});
    var body = 'test_mobile=' + encodeURIComponent(mobile)
      + '&test_lang=' + encodeURIComponent((document.getElementById('test_lang') || {}).value || 'ar')
      + '&csrf_token=' + encodeURIComponent({$csrf});
    fetch('test_whatsapp.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
      body: body
    }).then(function (r) {
      return r.text().then(function (text) {
        var data;
        try { data = JSON.parse(text); } catch (e) { data = { success: false, message: text || 'Unexpected response' }; }
        return data;
      });
    }).then(function (data) {
      var msg = data.message || '';
      if (data.details) msg += (msg ? '\\n' : '') + data.details;
      if (data.meta_hint) msg += (msg ? '\\n\\n' : '') + data.meta_hint;
      if (window.DmsSwal) DmsSwal.flash(data.success ? 'success' : 'danger', msg);
    }).catch(function (err) {
      if (window.DmsSwal) DmsSwal.error({$jsNetwork} + String(err));
    }).finally(function () { testBtn.disabled = false; });
  });
})();
</script>
JS;
require __DIR__ . '/../includes/footer.php';
