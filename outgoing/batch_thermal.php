<?php
/**
 * ملصقات حرارية لكل معاملات الصادر الجماعي — نفس قالب صفحة المعاملة.
 */
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/correspondence.php';
$user = require_login();
require_outgoing_bulk($user);
ensure_entity_groups_schema();

$id = (int) ($_GET['id'] ?? 0);
$batch = $id ? get_outgoing_batch($id) : null;
$items = $id ? outgoing_batch_items($id, $user) : [];
$items = array_values(array_filter($items, static function (array $row): bool {
    if (correspondence_is_cancelled($row)) {
        return false;
    }
    if (function_exists('correspondence_intend_electronic') && correspondence_intend_electronic($row)) {
        return false;
    }
    return !function_exists('outgoing_can_print_thermal') || outgoing_can_print_thermal($row);
}));
if (!$batch) {
    flash('danger', __('corr.not_found'));
    redirect(base_url('outgoing/index.php'));
}
if (!$items) {
    flash('warning', function_exists('outgoing_thermal_wait_message') ? outgoing_thermal_wait_message() : __('cancel.batch_print_empty'));
    redirect(base_url('outgoing/batch.php?id=' . $id));
}
if (function_exists('outgoing_require_print_perm')) {
    outgoing_require_print_perm(corr_print_perm('outgoing', 'thermal'), $user, base_url('outgoing/batch.php?id=' . $id));
}

$thermalSizes = [
    '44x22' => ['w' => 44, 'h' => 22, 'name_key' => 'thermal.size_44x22'],
    '50x25' => ['w' => 50, 'h' => 25, 'name_key' => 'thermal.size_50x25'],
];
$thermalDefaultSize = '44x22';
$copiesMax = 5;
require __DIR__ . '/../includes/thermal_print_setup.php';
$hMark = __('corr.cal_h_mark');
$gMark = __('corr.cal_g_mark');
/** إظهار التاريخ الهجري على الملصق الحراري — عطّل مؤقتاً؛ أعد true لإظهاره. */
$showThermalHijri = false;
$logoUrl = function_exists('org_logo_url') ? org_logo_url() : '';
$hasLogo = $logoUrl !== '';
$qrPx = $size === '44x22' ? ($hasLogo ? 176 : 192) : ($hasLogo ? 192 : 208);
$backUrl = base_url('outgoing/batch.php?id=' . $id);
$formAction = base_url('outgoing/batch_thermal.php');
$cssVer = (int) (@filemtime(__DIR__ . '/../assets/css/thermal-label.css') ?: 1);
$jsVer = (int) (@filemtime(__DIR__ . '/../assets/js/app.js') ?: 23);

$renderThermalDate = static function (string $dmy, string $mark): void {
    $parts = explode('/', $dmy);
    if (count($parts) !== 3) {
        return;
    }
    echo '<span class="print-thermal-date" dir="rtl">';
    echo '<span class="print-thermal-date-num" dir="ltr">' . e($parts[0]) . '</span>';
    echo '<span class="print-thermal-date-slash">/</span>';
    echo '<span class="print-thermal-date-num" dir="ltr">' . e($parts[1]) . '</span>';
    echo '<span class="print-thermal-date-slash">/</span>';
    echo '<span class="print-thermal-date-num" dir="ltr">' . e($parts[2]) . '</span>';
    echo '<span class="print-label-mark">' . e($mark) . '</span>';
    echo '</span>';
};

$prepareItem = static function (array $corr) use ($hMark, $gMark): array {
    $corrDate = $corr['corr_date'] ?? null;
    $hijriStr = '';
    $gregorianStr = '';
    if ($corrDate) {
        $ts = strtotime((string) $corrDate);
        if ($ts !== false) {
            $gregorianStr = date('d/m/Y', $ts);
            $hParts = gregorian_to_hijri_parts((int) date('Y', $ts), (int) date('n', $ts), (int) date('j', $ts));
            if ($hParts !== null) {
                $hijriStr = sprintf('%02d/%02d/%04d', $hParts[2], $hParts[1], $hParts[0]);
            }
        }
    }
    $qrContent = dms_corr_qr_content($corr);
    return [
        'id' => (int) $corr['id'],
        'type' => (string) ($corr['type'] ?? 'outgoing'),
        'ref_number' => $corr['ref_number'] ?? '',
        'qr' => $qrContent,
        'hijri' => $hijriStr,
        'greg' => ($gregorianStr !== '' && $gregorianStr !== '—') ? $gregorianStr : '',
        'h_mark' => $hMark,
        'g_mark' => $gMark,
    ];
};

$prepared = array_map($prepareItem, $items);
$qrJobs = [];
?>
<!DOCTYPE html>
<html lang="<?= e(dms_lang_code()) ?>" dir="<?= e(dms_page_dir()) ?>" class="print-thermal-html is-<?= e($size) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(__('thermal.print')) ?> — <?= e($batch['subject']) ?></title>
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
                <?= e(__('thermal.print')) ?> — <?= e($batch['subject']) ?>
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
<?php foreach ($prepared as $row): ?>
    <?php
    $hasHijriDate = $showThermalHijri && $row['hijri'] !== '';
    $hasGregorianDate = $row['greg'] !== '';
    $hasQr = $row['qr'] !== '';
    for ($c = 0; $c < $copies; $c++):
        $qrDomId = 'qr-' . $row['id'] . '-' . $c;
        if ($hasQr) {
            $qrJobs[] = ['id' => $qrDomId, 'text' => $row['qr']];
        }
    ?>
    <div class="print-thermal-sheet">
        <div class="print-thermal-sticker<?= $hasQr ? ' has-qr' : '' ?><?= $hasLogo ? ' has-logo' : '' ?>" dir="rtl">
            <?php if ($hasLogo): ?>
                <div class="print-thermal-logo">
                    <img src="<?= e($logoUrl) ?>" alt="" class="print-thermal-logo-img">
                </div>
            <?php endif; ?>
            <div class="print-thermal-body">
                <div class="print-thermal-text" dir="rtl">
                    <?php render_thermal_ref_caption($row['type'] ?? 'outgoing', $row['ref_number'] ?? ''); ?>
                    <?php if ($hasHijriDate || $hasGregorianDate): ?>
                        <div class="print-thermal-meta print-label-dates" dir="rtl">
                            <?php if ($hasHijriDate) {
                                $renderThermalDate($row['hijri'], $row['h_mark']);
                            } ?>
                            <?php if ($hasHijriDate && $hasGregorianDate): ?>
                                <span class="print-thermal-date-sep" aria-hidden="true">-</span>
                            <?php endif; ?>
                            <?php if ($hasGregorianDate) {
                                $renderThermalDate($row['greg'], $row['g_mark']);
                            } ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if ($hasQr): ?>
                    <div class="print-thermal-qr" id="<?= e($qrDomId) ?>" aria-hidden="true"></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endfor; ?>
<?php endforeach; ?>
</div>

<script src="<?= e(base_url('assets/js/qrcode.min.js')) ?>"></script>
<script src="<?= e(base_url('assets/js/app.js')) ?>?v=<?= $jsVer ?>"></script>
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
        tpl: tpl ? tpl.value : '44x22'
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

  var jobs = <?= json_encode($qrJobs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
  var qrPx = <?= (int) $qrPx ?>;
  if (jobs.length && typeof renderThermalQr === 'function') {
    jobs.forEach(function (job) {
      renderThermalQr(job.id, job.text, qrPx);
    });
  } else if (jobs.length && typeof renderQr === 'function') {
    jobs.forEach(function (job) {
      renderQr(job.id, job.text, qrPx, (typeof QRCode !== 'undefined' && QRCode.CorrectLevel) ? QRCode.CorrectLevel.L : 1);
    });
  }

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
