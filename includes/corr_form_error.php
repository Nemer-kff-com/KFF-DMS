<?php
if (($error ?? '') === '') {
    return;
}
?>
<div class="alert alert-danger"<?= !empty($errorAlertId) ? ' id="' . e((string) $errorAlertId) . '"' : '' ?>><i class="bi bi-exclamation-circle me-1"></i><?= e($error) ?></div>
<script>
document.addEventListener('DOMContentLoaded', function () {
  if (window.Swal) {
    Swal.fire({
      icon: 'error',
      title: <?= json_encode(__('common.error') ?: 'خطأ', JSON_UNESCAPED_UNICODE) ?>,
      text: <?= json_encode($error, JSON_UNESCAPED_UNICODE) ?>,
      confirmButtonColor: '#3B4F6E'
    });
  }
});
</script>
