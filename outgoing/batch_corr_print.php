<?php
/**
 * طباعة جماعية لورق المعاملات — نفس قالب outgoing/print.php
 */
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/correspondence.php';
$user = require_login();
require_outgoing_bulk($user);
ensure_entity_groups_schema();

$id = (int) ($_GET['id'] ?? 0);
$batch = $id ? get_outgoing_batch($id) : null;
$items = $id ? outgoing_batch_items($id, $user) : [];
$items = array_values(array_filter($items, static fn(array $row): bool => !correspondence_is_cancelled($row)));
if (!$batch) {
    flash('danger', __('corr.not_found'));
    redirect(base_url('outgoing/index.php'));
}
if (!$items) {
    flash('warning', __('cancel.batch_print_empty'));
    redirect(base_url('outgoing/batch.php?id=' . $id));
}
if (function_exists('outgoing_batch_can_issue') && !outgoing_batch_can_issue($items)) {
    $sum = function_exists('outgoing_batch_approval_summary') ? outgoing_batch_approval_summary($items) : [];
    $msg = (string) ($sum['block_reason'] ?? '');
    flash('warning', $msg !== '' ? $msg : __('issue_appr.batch_blocked_pending'));
    redirect(base_url('outgoing/batch.php?id=' . $id));
}
if (function_exists('outgoing_require_print_perm')) {
    outgoing_require_print_perm(corr_print_perm('outgoing', 'form'), $user, base_url('outgoing/batch.php?id=' . $id));
}

$dirOrder = (($_GET['dir'] ?? '') === 'from') ? 'from' : 'to';
$dirForced = isset($_GET['dir']);
$backUrl = base_url('outgoing/batch.php?id=' . $id);
$typeLabel = __('print.type_outgoing');
$cssVer = (int) (@filemtime(__DIR__ . '/../assets/css/app.css') ?: 90);
$jsVer = (int) (@filemtime(__DIR__ . '/../assets/js/app.js') ?: 23);
?>
<!DOCTYPE html>
<html lang="<?= e(dms_lang_code()) ?>" dir="<?= e(dms_page_dir()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(__('bulk.print_corr')) ?> — <?= e($batch['subject']) ?></title>
    <link rel="icon" href="<?= e(base_url('assets/img/favicon.ico')) ?>">
    <link href="<?= e(dms_bootstrap_css()) ?>" rel="stylesheet">
    <link href="<?= e(dms_bootstrap_icons_css()) ?>" rel="stylesheet">
    <link href="<?= e(base_url('assets/css/app.css')) ?>?v=<?= $cssVer ?>" rel="stylesheet">
    <link href="<?= e(dms_print_fast_css()) ?>" rel="stylesheet">
    <style>
        @page { size: A4 portrait; margin: 0; }
        @media print {
            .print-sheet {
                padding: 1.5cm 2.5cm 1.8cm !important;
                box-sizing: border-box !important;
            }
        }
    </style>
</head>
<body class="print-page print-corr print-corr-batch" dir="rtl">
<div class="print-toolbar print-label-toolbar print-corr-toolbar no-print">
    <div class="print-label-toolbar-main">
        <div class="print-label-toolbar-top">
            <div class="print-label-toolbar-actions">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="swapDirectionBtn"
                        title="<?= e(__('print.swap_direction_hint')) ?>" aria-label="<?= e(__('print.swap_direction_hint')) ?>">
                    <i class="bi bi-arrow-down-up" aria-hidden="true"></i>
                    <span id="swapDirectionLabel" class="visually-hidden"></span>
                </button>
                <button type="button" class="btn btn-kff btn-sm" onclick="window.print()">
                    <i class="bi bi-printer me-1"></i><?= e(__('action.print')) ?>
                </button>
                <a class="btn btn-outline-secondary btn-sm" href="<?= e($backUrl) ?>">
                    <i class="bi <?= dms_is_rtl() ? 'bi-arrow-right' : 'bi-arrow-left' ?> me-1"></i><?= e(__('action.back')) ?>
                </a>
            </div>
            <div class="print-toolbar-title">
                <?= e(__('bulk.print_corr')) ?> — <?= e($batch['subject']) ?>
                <span class="text-muted">(<?= count($items) ?>)</span>
            </div>
            <div class="print-label-toolbar-actions print-label-toolbar-actions-ghost" aria-hidden="true">
                <button type="button" class="btn btn-outline-secondary btn-sm" tabindex="-1" disabled>
                    <i class="bi bi-arrow-down-up" aria-hidden="true"></i>
                </button>
                <button type="button" class="btn btn-kff btn-sm" tabindex="-1" disabled>
                    <i class="bi bi-printer me-1"></i><?= e(__('action.print')) ?>
                </button>
                <a class="btn btn-outline-secondary btn-sm" tabindex="-1"><?= e(__('action.back')) ?></a>
            </div>
        </div>
    </div>
</div>

<?php foreach ($items as $corr): ?>
    <?php
    $qrDomId = 'qr-' . (int) $corr['id'];
    $directionTableId = 'printDirection-' . (int) $corr['id'];
    require __DIR__ . '/../includes/print_correspondence_sheet.php';
    ?>
<?php endforeach; ?>

<script src="<?= e(dms_bootstrap_js()) ?>"></script>
<script src="<?= e(base_url('assets/js/app.js')) ?>?v=<?= $jsVer ?>"></script>
<script>
(function () {
  var STORE_KEY = 'dms.print.dirOrder';
  var LABELS = <?= json_encode(['to' => __('print.order_to_first'), 'from' => __('print.order_from_first')], JSON_UNESCAPED_UNICODE) ?>;
  var tables = Array.prototype.slice.call(document.querySelectorAll('.print-direction'));
  var button = document.getElementById('swapDirectionBtn');
  var label = document.getElementById('swapDirectionLabel');
  if (!tables.length || !button) { return; }

  function currentOrder() {
    var first = tables[0].querySelector('tr[data-dir-row]');
    return first ? first.getAttribute('data-dir-row') : 'to';
  }

  function applyOrder(order) {
    tables.forEach(function (table) {
      var row = table.querySelector('tr[data-dir-row="' + order + '"]');
      if (row && row.parentNode.firstElementChild !== row) {
        row.parentNode.insertBefore(row, row.parentNode.firstElementChild);
      }
    });
    var text = LABELS[order] || LABELS.to;
    if (label) label.textContent = text;
    var tip = <?= json_encode(__('print.swap_direction_hint'), JSON_UNESCAPED_UNICODE) ?>;
    button.setAttribute('title', tip);
    button.setAttribute('aria-label', tip);
    if (typeof window.dmsSetBtnTip === 'function') {
      window.dmsSetBtnTip(button, tip);
    }
  }

  var saved = null;
  try { saved = localStorage.getItem(STORE_KEY); } catch (e) {}
  var forced = <?= json_encode($dirForced) ?>;
  var initial = <?= json_encode($dirOrder) ?>;
  applyOrder(!forced && (saved === 'to' || saved === 'from') ? saved : initial);

  button.addEventListener('click', function () {
    var next = currentOrder() === 'to' ? 'from' : 'to';
    applyOrder(next);
    try { localStorage.setItem(STORE_KEY, next); } catch (e) {}
  });
})();

</script>
</body>
</html>
