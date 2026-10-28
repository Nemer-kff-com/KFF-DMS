<?php
require_once __DIR__ . '/../config/auth.php';
$user = require_login();
$pageTitle = __('notify.title');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all'])) {
    verify_csrf();
    dms_notifications_mark_all_read($user);
    flash('success', __('notify.marked_all'));
    redirect(base_url('notifications/index.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_selected'])) {
    verify_csrf();
    $ids = is_array($_POST['ids'] ?? null) ? $_POST['ids'] : [];
    $deleted = dms_notifications_delete($ids, $user);
    if ($deleted > 0) {
        flash('success', str_replace(':n', (string) $deleted, __('notify.deleted')));
    } else {
        flash('warning', __('notify.delete_none'));
    }
    redirect(base_url('notifications/index.php'));
}

$rows = dms_notifications_list($user, 100);

require __DIR__ . '/../includes/header.php';
?>
<div class="page-header page-narrow">
    <div>
        <h1 class="page-title"><?= e(__('notify.title')) ?></h1>
        <p class="page-sub"><?= e(__('notify.subtitle')) ?></p>
    </div>
    <div class="page-header-actions">
        <form method="post">
            <?= csrf_field() ?>
            <button class="btn btn-outline-secondary" type="submit" name="mark_all" value="1">
                <i class="bi bi-check2-all me-1"></i><?= e(__('notify.mark_all')) ?>
            </button>
        </form>
    </div>
</div>

<form method="post" class="card-panel page-narrow" id="notifyListForm">
    <?= csrf_field() ?>
    <?php if ($rows): ?>
    <div class="notify-toolbar">
        <div class="form-check mb-0">
            <input class="form-check-input" type="checkbox" id="notifySelectAll">
            <label class="form-check-label" for="notifySelectAll"><?= e(__('notify.select_all')) ?></label>
        </div>
        <span class="small text-muted" id="notifySelectedCount" hidden></span>
        <span class="d-inline-block ms-auto" tabindex="0" title="<?= e(__('notify.delete_tip')) ?>" data-bs-toggle="tooltip" data-bs-placement="top">
            <button type="submit" name="delete_selected" value="1" class="btn btn-sm btn-outline-danger" id="notifyDeleteBtn" disabled
                    data-confirm="<?= e(__('notify.delete_confirm')) ?>">
                <i class="bi bi-trash3 me-1"></i><?= e(__('notify.delete_selected')) ?>
            </button>
        </span>
    </div>
    <?php endif; ?>
    <div class="list-group list-group-flush">
        <?php if (!$rows): ?>
            <div class="text-muted text-center py-4"><?= e(__('notify.empty')) ?></div>
        <?php endif; ?>
        <?php foreach ($rows as $n): ?>
            <?php
            $nTitle = function_exists('dms_notification_display_text')
                ? dms_notification_display_text((string) ($n['title'] ?? ''))
                : (string) ($n['title'] ?? '');
            $nBody = function_exists('dms_notification_display_text')
                ? dms_notification_display_text((string) ($n['body'] ?? ''))
                : (string) ($n['body'] ?? '');
            ?>
            <div class="list-group-item list-group-item-action d-flex gap-3 align-items-start notify-row <?= empty($n['is_read']) ? 'notify-unread' : '' ?>">
                <input class="form-check-input notify-check flex-shrink-0" type="checkbox" name="ids[]" value="<?= (int) $n['id'] ?>"
                       aria-label="<?= e(__('notify.select_one') . ': ' . $nTitle) ?>">
                <a class="notify-row-link d-flex gap-3 flex-grow-1" href="<?= e(base_url('notifications/go.php?id=' . (int) $n['id'])) ?>">
                    <span class="notify-icon"><i class="bi <?= e(dms_notification_icon((string) $n['type'])) ?>"></i></span>
                    <span class="flex-grow-1">
                        <span class="fw-semibold d-block"><?= e($nTitle) ?></span>
                        <?php if ($nBody !== ''): ?>
                            <span class="small text-muted d-block"><?= e($nBody) ?></span>
                        <?php endif; ?>
                        <span class="small text-muted"><?= e(format_datetime($n['created_at'] ?? null)) ?></span>
                    </span>
                    <?php if (empty($n['is_read'])): ?>
                        <span class="badge text-bg-danger align-self-center"><?= e(__('notify.new')) ?></span>
                    <?php endif; ?>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
</form>
<?php if ($rows): ?>
<script>
(function () {
  var form = document.getElementById('notifyListForm');
  var all = document.getElementById('notifySelectAll');
  var btn = document.getElementById('notifyDeleteBtn');
  var countEl = document.getElementById('notifySelectedCount');
  if (!form || !all || !btn) return;
  var countTpl = <?= json_encode(__('notify.selected_count'), JSON_UNESCAPED_UNICODE) ?>;
  var checks = Array.prototype.slice.call(form.querySelectorAll('.notify-check'));

  function sync() {
    var n = checks.filter(function (c) { return c.checked; }).length;
    all.checked = n > 0 && n === checks.length;
    all.indeterminate = n > 0 && n < checks.length;
    btn.disabled = n === 0;
    if (countEl) {
      countEl.hidden = n === 0;
      countEl.textContent = countTpl.replace(':n', String(n));
    }
    checks.forEach(function (c) {
      c.closest('.notify-row').classList.toggle('is-selected', c.checked);
    });
  }

  all.addEventListener('change', function () {
    checks.forEach(function (c) { c.checked = all.checked; });
    sync();
  });
  checks.forEach(function (c) { c.addEventListener('change', sync); });
  sync();
})();
</script>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
