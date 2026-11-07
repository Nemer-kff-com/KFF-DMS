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
<?php
if (!isset($guideAnchor)) {
    $guideAnchor = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($_GET['guide_anchor'] ?? ''));
}
if ($guideAnchor !== ''):
    $aid = $guideAnchor;
?>
<style id="guide-shot-focus">
<?php if ($aid === 'corr-priority'): ?>
/* نموذج إنشاء: أظهر بطاقة الأولوية/السرية فقط */
body.guide-shot .content .corr-field-card:not(:has(#corr-priority)) { display: none !important; }
body.guide-shot .content .corr-field-card:has(#corr-priority) {
  display: block !important;
  outline: 3px solid #0d6efd;
  outline-offset: 6px;
  max-width: 520px;
}
body.guide-shot .content .alert { display: none !important; }
<?php elseif ($aid === 'outgoing-letter-section'): ?>
body.guide-shot .admin-form-section:not(#outgoing-letter-section) { display: none !important; }
body.guide-shot .alert { display: none !important; }
body.guide-shot .content > .d-flex,
body.guide-shot .page-title,
body.guide-shot .page-sub,
body.guide-shot .corr-issue-decide { display: none !important; }
body.guide-shot #outgoing-letter-section {
  display: block !important;
  outline: 3px solid #0d6efd;
  outline-offset: 6px;
  margin: .25rem 0 !important;
  padding-bottom: .35rem !important;
}
body.guide-shot #outgoing-letter-section .letter-body-frame textarea {
  min-height: 5rem !important;
  max-height: 5.5rem !important;
}
body.guide-shot #outgoing-letter-section .letter-ruler { display: none !important; }
body.guide-shot #outgoing-letter-section .admin-form-hint { display: none !important; }
<?php else: ?>
/* عرض المعاملة: أخفِ باقي البطاقات */
body.guide-shot .content .card-panel:not(#<?= htmlspecialchars($aid, ENT_QUOTES, 'UTF-8') ?>),
body.guide-shot .content .corr-send-panel:not(#<?= htmlspecialchars($aid, ENT_QUOTES, 'UTF-8') ?>) {
  display: none !important;
}
body.guide-shot #<?= htmlspecialchars($aid, ENT_QUOTES, 'UTF-8') ?> {
  display: block !important;
  outline: 3px solid #0d6efd;
  outline-offset: 6px;
  margin-top: .5rem !important;
}
<?php if ($aid === 'corr-qr'): ?>
body.guide-shot .content .row > [class*="col-"]:not(:has(#corr-qr)) { display: none !important; }
body.guide-shot .content .row > [class*="col-"]:has(#corr-qr) { width: 100% !important; max-width: 420px !important; }
<?php elseif (in_array($aid, ['corr-referrals', 'corr-comments', 'corr-attachments', 'corr-send', 'corr-delivery'], true)): ?>
body.guide-shot .content .row > [class*="col-"]:has(#corr-qr),
body.guide-shot .content .row > [class*="col-"]:has(.corr-timeline),
body.guide-shot .content aside,
body.guide-shot .corr-view-side { display: none !important; }
<?php endif; ?>
body.guide-shot .corr-view-toolbar,
body.guide-shot .corr-view-actions { display: none !important; }
<?php endif; ?>
</style>
<script>
(function () {
  var id = <?= json_encode($aid, JSON_UNESCAPED_UNICODE) ?>;
  function go() {
    var el = document.getElementById(id);
    if (!el) return false;
    try {
      window.scrollTo(0, 0);
      var wrap = document.querySelector('.main-wrap');
      if (wrap) wrap.scrollTop = 0;
      el.scrollIntoView({ block: 'start' });
    } catch (e) {}
    return true;
  }
  go();
  var n = 0;
  var t = setInterval(function () { if (go() || ++n > 40) clearInterval(t); }, 80);
})();
</script>
<?php endif; ?>
</body>
</html>
