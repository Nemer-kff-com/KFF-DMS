<?php
/** تنبيه غياب بريد الجهة عند الإرسال الإلكتروني — مرة واحدة في الصفحة. */
$bindInvalid = !empty($bindInvalid);
?>
<script>
(function () {
  var sel = document.getElementById('entity_id');
  var box = document.getElementById('intend_electronic');
  var hint = document.getElementById('entity-email-hint');
  if (!sel || !box || !hint) return;
  var missing = <?= json_encode(__('corr.entity_no_email'), JSON_UNESCAPED_UNICODE) ?>;
  function refresh() {
    var email = (sel.dataset.email || '').trim();
    var showWarn = box.checked && sel.value && email === '';
    if (showWarn) {
      hint.textContent = missing;
      hint.setAttribute('title', missing);
      hint.classList.add('text-danger');
      hint.classList.remove('d-none');
    } else {
      hint.textContent = '';
      hint.removeAttribute('title');
      hint.classList.remove('text-danger');
      hint.classList.add('d-none');
    }
  }
  sel.addEventListener('change', refresh);
  box.addEventListener('change', refresh);
  var obs = new MutationObserver(refresh);
  obs.observe(sel, { attributes: true, attributeFilter: ['data-email', 'value'] });
  refresh();
  <?php if ($bindInvalid): ?>
  var form = sel.closest('form');
  if (form && !form.dataset.invalidBound) {
    form.dataset.invalidBound = '1';
    form.addEventListener('invalid', function (e) {
      var field = e.target;
      if (!field || window.__dmsInvalidShown) return;
      window.__dmsInvalidShown = true;
      window.setTimeout(function () { window.__dmsInvalidShown = false; }, 500);
      var msg = field.validationMessage || '';
      field.classList.add('is-invalid');
      if (typeof field.focus === 'function') {
        try { field.focus({ preventScroll: true }); } catch (err) { field.focus(); }
      }
      if (typeof field.scrollIntoView === 'function') {
        field.scrollIntoView({ block: 'center', behavior: 'smooth' });
      }
      if (window.Swal && msg) {
        Swal.fire({ icon: 'warning', text: msg, confirmButtonColor: '#3B4F6E' });
      }
    }, true);
  }
  <?php endif; ?>
})();
</script>
