<?php
/**
 * ملصق حراري لحفظ المعاملة فيزيائياً — ليس ملصق الظرف.
 */
require_once __DIR__ . '/../config/auth.php';
$user = require_login();
$id = (int) ($_GET['id'] ?? 0);
$corr = get_correspondence($id);
if (!$corr || !user_can_access_correspondence($corr, $user)) {
    exit(__('common.not_found'));
}
$thermalType = (string) ($corr['type'] ?? '');
if (in_array($thermalType, ['incoming', 'internal'], true)) {
    $folder = function_exists('correspondence_type_folder')
        ? correspondence_type_folder($thermalType)
        : $thermalType;
    $back = base_url($folder . '/view.php?id=' . $id);
    if ($thermalType !== 'incoming' && function_exists('corr_approval_is_held') && corr_approval_is_held($corr)) {
        flash('warning', function_exists('corr_held_text') ? corr_held_text($corr, 'print_blocked') : __('auth.no_permission'));
        redirect($back);
    }
    if (function_exists('corr_print_perm') && function_exists('outgoing_require_print_perm')) {
        outgoing_require_print_perm(corr_print_perm($thermalType, 'thermal'), $user, $back);
    }
}
if ($thermalType === 'outgoing') {
    $folder = function_exists('correspondence_type_folder')
        ? correspondence_type_folder('outgoing')
        : 'outgoing';
    $back = base_url($folder . '/view.php?id=' . $id);
    if (function_exists('correspondence_intend_electronic') && correspondence_intend_electronic($corr)) {
        flash('warning', __('out_prep.print_not_electronic'));
        redirect($back);
    }
    if (function_exists('outgoing_can_print_thermal') && !outgoing_can_print_thermal($corr)) {
        flash('warning', function_exists('outgoing_thermal_wait_message') ? outgoing_thermal_wait_message() : __('out_prep.thermal_blocked'));
        redirect($back);
    }
    if (function_exists('outgoing_require_print_perm')) {
        outgoing_require_print_perm(corr_print_perm('outgoing', 'thermal'), $user, $back);
    }
}

require __DIR__ . '/../includes/thermal_print_setup.php';
$qrContent = dms_corr_qr_content($corr);
$hasQr = $qrContent !== '';
$logoUrl = function_exists('org_logo_url') ? org_logo_url() : '';
$hasLogo = $logoUrl !== '';
$qrPx = $size === '44x22' ? ($hasLogo ? 176 : 192) : ($hasLogo ? 192 : 208);
$folder = function_exists('correspondence_type_folder')
    ? correspondence_type_folder((string) ($corr['type'] ?? ''))
    : correspondence_folder_for_type((string) ($corr['type'] ?? 'incoming'));
$backUrl = base_url($folder . '/view.php?id=' . $id);
$formAction = base_url('correspondence/thermal_label.php');
$cssVer = (int) (@filemtime(__DIR__ . '/../assets/css/thermal-label.css') ?: 1);
$jsVer = (int) (@filemtime(__DIR__ . '/../assets/js/app.js') ?: 23);
?>
<!DOCTYPE html>
<html lang="<?= e(dms_lang_code()) ?>" dir="<?= e(dms_page_dir()) ?>" class="print-thermal-html is-<?= e($size) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(__('thermal.title')) ?> — <?= e($corr['ref_number']) ?></title>
    <link rel="icon" href="<?= e(base_url('assets/img/favicon.ico')) ?>">
    <link href="<?= e(dms_bootstrap_css()) ?>" rel="stylesheet">
    <link href="<?= e(dms_bootstrap_icons_css()) ?>" rel="stylesheet">
    <!-- ملصق حراري: Greta فقط — بدون app.css / Amiri / خطوط القوالب -->
    <link href="<?= e(base_url('assets/css/thermal-label.css')) ?>?v=<?= $cssVer ?>" rel="stylesheet">
    <style>
        @page { size: <?= $pageW ?> <?= $pageH ?>; margin: 0; }
        @media print {
            @page { size: <?= $pageW ?> <?= $pageH ?>; margin: 0; }
        }
        .print-thermal-page .print-thermal-sticker,
        .print-thermal-page .print-thermal-text,
        .print-thermal-page .print-thermal-meta {
            direction: rtl;
        }
    </style>
</head>
<body class="print-thermal-page letter-font-greta is-<?= e($size) ?>" dir="<?= e(dms_page_dir()) ?>">
<div class="print-label-toolbar print-thermal-toolbar no-print">
    <div class="print-label-toolbar-main">
        <div class="print-label-toolbar-top">
            <div class="print-label-toolbar-actions">
                <button type="button" class="btn btn-kff btn-sm" id="thermal-print-btn">
                    <i class="bi bi-printer me-1"></i><?= e(__('action.print')) ?>
                </button>
                <a class="btn btn-outline-secondary btn-sm" href="<?= e($backUrl) ?>">
                    <i class="bi <?= dms_is_rtl() ? 'bi-arrow-right' : 'bi-arrow-left' ?> me-1"></i><?= e(__('action.back')) ?>
                </a>
            </div>
            <div class="print-toolbar-title">
                <?= e(__('thermal.title')) ?> — <span dir="ltr"><?= e($corr['ref_number']) ?></span>
            </div>
            <div class="print-label-toolbar-actions print-label-toolbar-actions-ghost" aria-hidden="true">
                <button type="button" class="btn btn-kff btn-sm" tabindex="-1" disabled>
                    <i class="bi bi-printer me-1"></i><?= e(__('action.print')) ?>
                </button>
                <a class="btn btn-outline-secondary btn-sm" tabindex="-1"><?= e(__('action.back')) ?></a>
            </div>
        </div>

        <div class="print-label-toolbar-controls">
            <form class="print-label-form" id="thermal-label-form" method="get" action="<?= e($formAction) ?>">
                <input type="hidden" name="id" value="<?= (int) $id ?>">

                <label class="print-label-field print-label-field-tpl">
                    <span><?= e(__('thermal.template')) ?></span>
                    <select name="size" class="form-select form-select-sm" id="thermal-tpl">
                        <?php foreach ($thermalSizes as $sid => $item): ?>
                            <option value="<?= e($sid) ?>"<?= $size === $sid ? ' selected' : '' ?>>
                                <?= e(__($item['name_key'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="print-label-field print-label-field-copies">
                    <span><?= e(__('label.copies')) ?></span>
                    <input type="number"
                           class="form-control form-control-sm"
                           name="copies"
                           min="1"
                           max="<?= (int) $copiesMax ?>"
                           value="<?= (int) $copies ?>"
                           id="thermal-copies"
                           title="<?= e(str_replace(':n', (string) $copiesMax, __('thermal.copies_hint'))) ?>">
                </label>

                <div class="print-label-form-actions">
                    <button type="submit" class="btn btn-outline-secondary btn-sm"><?= e(__('label.apply')) ?></button>
                </div>
            </form>
        </div>
    </div>
    <p class="print-label-hint mb-0"><?= e(__('thermal.hint')) ?></p>
</div>

<div class="print-thermal-preview">
<?php for ($i = 0; $i < $copies; $i++): ?>
    <div class="print-thermal-sheet">
        <?php render_thermal_sticker($corr, $i); ?>
    </div>
<?php endfor; ?>
</div>

<?php if ($hasQr): ?>
<script src="<?= e(base_url('assets/js/qrcode.min.js')) ?>"></script>
<?php endif; ?>
<?php if ($hasQr || $hasLogo): ?>
<script src="<?= e(base_url('assets/js/app.js')) ?>?v=<?= $jsVer ?>"></script>
<?php endif; ?>
<script>
(function () {
  var KEY = 'dms-thermal-label';
  var form = document.getElementById('thermal-label-form');
  var tpl = document.getElementById('thermal-tpl');
  var copiesInput = document.getElementById('thermal-copies');
  var printBtn = document.getElementById('thermal-print-btn');
  var params = new URLSearchParams(window.location.search);
  var copiesMax = <?= (int) $copiesMax ?>;

  function savePrefs() {
    try {
      localStorage.setItem(KEY, JSON.stringify({
        tpl: tpl ? tpl.value : '50x25'
      }));
    } catch (e) {}
  }

  if (!params.has('size') && !params.has('tpl')) {
    try {
      var saved = JSON.parse(localStorage.getItem(KEY) || '{}');
      if (saved && saved.tpl) {
        var savedTpl = saved.tpl === '22x40' || saved.tpl === '22x44' ? '44x22' : saved.tpl;
        if (savedTpl === '50x25' || savedTpl === '44x22') {
          params.set('size', savedTpl);
          if (!params.has('copies')) params.set('copies', '1');
          window.location.replace(window.location.pathname + '?' + params.toString());
          return;
        }
      }
    } catch (e) {}
  }

  var autoPrint = params.get('autoprint') === '1';

  function clampCopies(val) {
    val = parseInt(val, 10) || 1;
    if (val < 1) val = 1;
    if (val > copiesMax) val = copiesMax;
    return val;
  }

  if (tpl) {
    tpl.addEventListener('change', function () {
      savePrefs();
      form.submit();
    });
  }
  if (copiesInput) {
    copiesInput.addEventListener('change', function () {
      copiesInput.value = String(clampCopies(copiesInput.value));
      form.submit();
    });
  }
  if (printBtn) {
    printBtn.addEventListener('click', function () {
      savePrefs();
      if (copiesInput) {
        var urlCopies = parseInt(params.get('copies') || '1', 10) || 1;
        var fieldCopies = clampCopies(copiesInput.value);
        if (fieldCopies !== urlCopies) {
          var h = document.createElement('input');
          h.type = 'hidden';
          h.name = 'autoprint';
          h.value = '1';
          form.appendChild(h);
          form.submit();
          return;
        }
      }
      window.print();
    });
  }
  if (autoPrint) {
    params.delete('autoprint');
    var clean = window.location.pathname + '?' + params.toString();
    window.history.replaceState({}, '', clean);
    setTimeout(function () { window.print(); }, 250);
  }

  var qrText = <?= $hasQr ? json_encode($qrContent, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '""' ?>;
  var qrPx = <?= (int) $qrPx ?>;
  if (qrText && typeof renderThermalQr === 'function') {
    document.querySelectorAll('.print-thermal-qr').forEach(function (el) {
      renderThermalQr(el.id, qrText, qrPx);
    });
  } else if (qrText && typeof renderQr === 'function') {
    document.querySelectorAll('.print-thermal-qr').forEach(function (el) {
      renderQr(el.id, qrText, qrPx, (typeof QRCode !== 'undefined' && QRCode.CorrectLevel) ? QRCode.CorrectLevel.L : 1);
    });
  }

  /* تحويل الشعار إلى أسود صلب مع الحفاظ على شفافية PNG */
  function toThermalBlack(img) {
    if (typeof renderThermalLogo === 'function') {
      renderThermalLogo(img);
      return;
    }
    if (!img || img.dataset.thermalBlack === '1') return;
    img.dataset.thermalBlack = '1';
    img.classList.add('is-thermal-ready');
    img.style.filter = 'none';
  }
  document.querySelectorAll('.print-thermal-logo-img').forEach(toThermalBlack);
})();
</script>
</body>
</html>
