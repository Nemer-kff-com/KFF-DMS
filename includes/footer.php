</div><!-- /.content -->
</div><!-- /.main-wrap -->
</div><!-- /.app-shell -->
<script src="<?= e(dms_bootstrap_js()) ?>"></script>
<?php if (!empty($includeQrcodeJs)): ?>
<script src="<?= e(function_exists('dms_asset_url') ? dms_asset_url('assets/js/qrcode.min.js', true) : base_url('assets/js/qrcode.min.js')) ?>"></script>
<?php endif; ?>
<?php if (!empty($_SESSION['user_id'])): ?>
<script>
window.__DMS_IDLE_MS__ = <?= (int) (session_timeout_minutes() * 60 * 1000) ?>;
window.__DMS_LOGOUT_URL__ = <?= json_encode(base_url('auth/logout.php?reason=timeout'), JSON_UNESCAPED_SLASHES) ?>;
</script>
<?php endif; ?>
<script>window.__DMS_REQ_TIP__ = <?= json_encode(__('common.required_field'), JSON_UNESCAPED_UNICODE) ?>;</script>
<?php require __DIR__ . '/swal_boot.php'; ?>
<script src="<?= e(function_exists('dms_asset_url') ? dms_asset_url('assets/js/app.js', true) : (base_url('assets/js/app.js') . '?v=24')) ?>"></script>
<?php if (!empty($pageScripts)): ?>
<?= $pageScripts ?>
<?php endif; ?>
</body>
</html>
