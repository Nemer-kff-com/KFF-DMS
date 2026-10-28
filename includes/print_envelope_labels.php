<?php
/**
 * طباعة ملصقات ظرف المراسلات — صفحة مستقلة (لا تغيّر طباعة الخطاب).
 *
 * يتطلب من المستدعي:
 *   $labelCorrList     list<array>
 *   $labelBackUrl      string
 *   $labelTitle        string
 *   $labelFormAction   string
 *   $labelHidden       array<string,scalar>
 *   $labelAllowCopies  bool
 */
require_once __DIR__ . '/../config/label_print.php';
require_once __DIR__ . '/../config/letter_templates.php';

$tpl = label_print_resolve_template($_GET);
$start = label_print_start($_GET, $tpl);
$per = label_print_slots($tpl);
$slotsLeft = max(1, $per - $start + 1);
// عدد النسخ = تكرار على نفس الورقة فقط (لا يتجاوز الخانات المتبقية)
$copies = !empty($labelAllowCopies) ? label_print_copies($_GET) : 1;
$copies = min($copies, $slotsLeft);
$compact = (float) $tpl['h'] < 60;

$payloads = [];
foreach ($labelCorrList as $row) {
    $payload = envelope_label_payload($row);
    for ($i = 0; $i < $copies; $i++) {
        $payloads[] = $payload;
    }
}

$pages = label_print_paginate($payloads, $tpl, $start);
// بعد التقييد بنفس الصفحة يجب أن تبقى ورقة واحدة
if (count($pages) > 1) {
    $pages = [array_slice($pages[0], 0, $per)];
}
$nextStart = label_print_next_start($start, count($payloads), $per);
$cssVer = (int) (@filemtime(__DIR__ . '/../assets/css/app.css') ?: 90);
$passthrough = ['w', 'h', 'cols', 'rows', 'mt', 'mr', 'mb', 'ml', 'gx', 'gy'];
?>
<!DOCTYPE html>
<html lang="<?= e(dms_lang_code()) ?>" dir="<?= e(dms_page_dir()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($labelTitle) ?></title>
    <link rel="icon" href="<?= e(base_url('assets/img/favicon.ico')) ?>">
    <link href="<?= e(dms_bootstrap_css()) ?>" rel="stylesheet">
    <link href="<?= e(dms_bootstrap_icons_css()) ?>" rel="stylesheet">
    <link href="<?= e(base_url('assets/css/app.css')) ?>?v=<?= $cssVer ?>" rel="stylesheet">
    <link href="<?= e(dms_print_fast_css()) ?>" rel="stylesheet">
    <style>
        @page { size: letter portrait; margin: 0; }
        @media print {
            @page { size: letter portrait; margin: 0; }
        }
    </style>
</head>
<body class="print-label-page letter-font-greta<?= $compact ? ' is-compact' : '' ?>" style="<?= e(label_print_sheet_style($tpl)) ?>">
<div class="print-label-toolbar no-print">
    <div class="print-label-toolbar-main">
        <div class="print-label-toolbar-top">
            <div class="print-label-toolbar-actions">
                <button type="button" class="btn btn-kff btn-sm" id="label-print-btn" form="label-print-form">
                    <i class="bi bi-printer me-1"></i><?= e(__('action.print')) ?>
                </button>
                <a class="btn btn-outline-secondary btn-sm" href="<?= e($labelBackUrl) ?>">
                    <i class="bi <?= dms_is_rtl() ? 'bi-arrow-right' : 'bi-arrow-left' ?> me-1"></i><?= e(__('action.back')) ?>
                </a>
            </div>
            <div class="print-toolbar-title"><?= e($labelTitle) ?></div>
            <div class="print-label-toolbar-actions print-label-toolbar-actions-ghost" aria-hidden="true">
                <button type="button" class="btn btn-kff btn-sm" tabindex="-1" disabled>
                    <i class="bi bi-printer me-1"></i><?= e(__('action.print')) ?>
                </button>
                <a class="btn btn-outline-secondary btn-sm" tabindex="-1"><?= e(__('action.back')) ?></a>
            </div>
        </div>

        <div class="print-label-toolbar-controls">
            <form class="print-label-form" id="label-print-form" method="get" action="<?= e($labelFormAction) ?>"
                  data-next-start="<?= (int) $nextStart ?>"
                  data-slots="<?= (int) $per ?>">
                <?php foreach ($labelHidden as $name => $value): ?>
                    <input type="hidden" name="<?= e((string) $name) ?>" value="<?= e((string) $value) ?>">
                <?php endforeach; ?>
                <?php foreach ($passthrough as $key): ?>
                    <?php if (isset($_GET[$key]) && $_GET[$key] !== ''): ?>
                        <input type="hidden" name="<?= e($key) ?>" value="<?= e((string) $_GET[$key]) ?>">
                    <?php endif; ?>
                <?php endforeach; ?>

                <label class="print-label-field print-label-field-tpl">
                    <span><?= e(__('label.template')) ?></span>
                    <select name="tpl" class="form-select form-select-sm" id="label-tpl">
                        <?php foreach (label_print_templates() as $id => $item): ?>
                            <option value="<?= e($id) ?>"<?= ($tpl['id'] === $id || ($tpl['id'] === 'custom' && $id === label_print_default_id())) ? ' selected' : '' ?>>
                                <?= e(__($item['name_key'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="print-label-field print-label-field-start">
                    <span><?= e(__('label.start')) ?></span>
                    <select name="start" class="form-select form-select-sm" id="label-start">
                        <?php for ($n = 1; $n <= $per; $n++): ?>
                            <option value="<?= $n ?>"<?= $start === $n ? ' selected' : '' ?>><?= $n ?></option>
                        <?php endfor; ?>
                    </select>
                </label>

                <?php if (!empty($labelAllowCopies)): ?>
                <label class="print-label-field print-label-field-copies">
                    <span><?= e(__('label.copies')) ?></span>
                    <input type="number" class="form-control form-control-sm" name="copies" min="1" max="<?= (int) $slotsLeft ?>" value="<?= (int) $copies ?>" id="label-copies" title="<?= e(str_replace(':n', (string) $slotsLeft, __('label.copies_same_page'))) ?>">
                </label>
                <?php endif; ?>

                <div class="print-label-form-actions">
                    <button type="submit" class="btn btn-outline-secondary btn-sm"><?= e(__('label.apply')) ?></button>
                </div>

                <span class="print-label-sep" aria-hidden="true"></span>

                <label class="print-label-field print-label-field-font">
                    <span><?= e(__('label.font')) ?></span>
                    <select class="form-select form-select-sm" id="label-font" dir="ltr">
                        <?php foreach (letter_font_options() as $code => $opt): ?>
                            <option value="<?= e($code) ?>"<?= $code === 'greta' ? ' selected' : '' ?>><?= e($opt['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <span class="print-label-sizes-title"><?= e(__('label.sizes')) ?></span>
                <div class="print-label-sizes-fields" id="label-field-sizes">
                    <label class="print-label-field print-label-size-field">
                        <span><?= e(__('label.size_prefix')) ?></span>
                        <input type="number" class="form-control form-control-sm" id="ls-prefix" min="70" max="160" step="5" value="100" inputmode="numeric">
                    </label>
                    <label class="print-label-field print-label-size-field">
                        <span><?= e(__('label.size_name')) ?></span>
                        <input type="number" class="form-control form-control-sm" id="ls-name" min="70" max="160" step="5" value="100" inputmode="numeric">
                    </label>
                    <label class="print-label-field print-label-size-field">
                        <span><?= e(__('label.size_job')) ?></span>
                        <input type="number" class="form-control form-control-sm" id="ls-job" min="70" max="160" step="5" value="100" inputmode="numeric">
                    </label>
                    <label class="print-label-field print-label-size-field">
                        <span><?= e(__('label.size_org')) ?></span>
                        <input type="number" class="form-control form-control-sm" id="ls-org" min="70" max="160" step="5" value="100" inputmode="numeric">
                    </label>
                </div>
            </form>
        </div>
    </div>

    <?php
    $tb = rtrim(rtrim(number_format((float) $tpl['mt'], 1, '.', ''), '0'), '.');
    $tbB = rtrim(rtrim(number_format((float) $tpl['mb'], 1, '.', ''), '0'), '.');
    $tbHint = ($tb === $tbB) ? $tb : ($tb . '/' . $tbB);
    $lrL = rtrim(rtrim(number_format((float) $tpl['ml'], 1, '.', ''), '0'), '.');
    $lrR = rtrim(rtrim(number_format((float) $tpl['mr'], 1, '.', ''), '0'), '.');
    $lrHint = ($lrL === $lrR) ? $lrL : ($lrL . '/' . $lrR);
    $gxHint = rtrim(rtrim(number_format((float) $tpl['gx'], 1, '.', ''), '0'), '.');
    $labelHint = str_replace(
        [':n', ':tb', ':lr', ':gx'],
        [(string) $nextStart, $tbHint, $lrHint, $gxHint],
        __('label.hint')
    );
    ?>
    <p class="print-label-hint mb-0"><?= e($labelHint) ?></p>
</div>

<?php foreach ($pages as $pageIndex => $slots): ?>
<div class="print-label-sheet<?= count($pages) === 1 ? ' is-only-sheet' : '' ?>">
    <?php foreach ($slots as $slotIndex => $item): ?>
        <?php if ($item === null): ?>
            <div class="print-label-slot is-empty" aria-hidden="true"></div>
        <?php else: ?>
            <div class="print-label-slot">
                <div class="print-label-inner">
                    <div class="print-label-body print-label-fit">
                        <?php render_envelope_label_party($item['party']); ?>
                    </div>
                    <div class="print-label-meta">
                        <div class="print-label-footer-line">
                            <span class="print-label-footer-ref" dir="rtl"><?= e((string) ($item['ref'] ?? '')) ?></span>
                            <?php if (!empty($item['flags']) && is_array($item['flags'])): ?>
                            <span class="print-label-footer-flags">
                                <?php foreach ($item['flags'] as $flag): ?>
                                    <span class="print-label-footer-flag"><?= e((string) $flag) ?></span>
                                <?php endforeach; ?>
                            </span>
                            <?php endif; ?>
                            <span class="print-label-footer-date">
                                <?php
                                $dateBits = [];
                                if (($item['date_hijri'] ?? '') !== '') {
                                    $dateBits[] = $item['date_hijri'] . ' هـ';
                                }
                                if (($item['date'] ?? '') !== '') {
                                    $dateBits[] = $item['date'] . ' م';
                                }
                                echo e(implode('  ·  ', $dateBits));
                                ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
</div>
<?php endforeach; ?>

<script>
(function () {
  var KEY = 'dms-label-print';
  var form = document.getElementById('label-print-form');
  var tpl = document.getElementById('label-tpl');
  var start = document.getElementById('label-start');
  var printBtn = document.getElementById('label-print-btn');
  var params = new URLSearchParams(window.location.search);

  function savePrefs(nextStart) {
    try {
      localStorage.setItem(KEY, JSON.stringify({
        tpl: tpl ? tpl.value : '',
        start: String(nextStart || (start ? start.value : '1'))
      }));
    } catch (e) {}
  }

  if (!params.has('tpl') || !params.has('start')) {
    try {
      var saved = JSON.parse(localStorage.getItem(KEY) || '{}');
      var changed = false;
      if (saved && saved.tpl && !params.has('tpl')) {
        params.set('tpl', saved.tpl);
        changed = true;
      }
      if (saved && saved.start && !params.has('start')) {
        params.set('start', saved.start);
        changed = true;
      }
      if (changed) {
        window.location.replace(window.location.pathname + '?' + params.toString());
        return;
      }
    } catch (e) {}
  }

  var copiesInput = document.getElementById('label-copies');
  var autoPrint = params.get('autoprint') === '1';
  var SIZE_KEY = 'dms-label-field-scale';
  var sizeIds = {
    prefix: 'ls-prefix',
    name: 'ls-name',
    job: 'ls-job',
    org: 'ls-org'
  };

  function clampSize(n) {
    n = parseInt(n, 10);
    if (!isFinite(n)) n = 100;
    if (n < 70) n = 70;
    if (n > 160) n = 160;
    return n;
  }

  function readSizes() {
    var out = { prefix: 100, name: 100, job: 100, org: 100 };
    Object.keys(sizeIds).forEach(function (k) {
      var el = document.getElementById(sizeIds[k]);
      if (el) out[k] = clampSize(el.value);
    });
    return out;
  }

  function writeSizeInputs(sizes) {
    Object.keys(sizeIds).forEach(function (k) {
      var el = document.getElementById(sizeIds[k]);
      if (el) el.value = String(clampSize(sizes[k]));
    });
  }

  function applyFieldSizes(sizes) {
    var root = document.body;
    if (!root) return;
    root.style.setProperty('--ls-prefix', (clampSize(sizes.prefix) / 100).toFixed(3));
    root.style.setProperty('--ls-name', (clampSize(sizes.name) / 100).toFixed(3));
    root.style.setProperty('--ls-job', (clampSize(sizes.job) / 100).toFixed(3));
    root.style.setProperty('--ls-org', (clampSize(sizes.org) / 100).toFixed(3));
  }

  function saveFieldSizes(sizes) {
    try {
      localStorage.setItem(SIZE_KEY, JSON.stringify(sizes));
    } catch (e) {}
  }

  try {
    var savedSizes = JSON.parse(localStorage.getItem(SIZE_KEY) || '{}');
    if (savedSizes && typeof savedSizes === 'object') {
      writeSizeInputs({
        prefix: savedSizes.prefix || 100,
        name: savedSizes.name || 100,
        job: savedSizes.job || 100,
        org: savedSizes.org || 100
      });
    }
  } catch (e) {}
  applyFieldSizes(readSizes());

  Object.keys(sizeIds).forEach(function (k) {
    var el = document.getElementById(sizeIds[k]);
    if (!el) return;
    el.addEventListener('change', function () {
      var sizes = readSizes();
      writeSizeInputs(sizes);
      applyFieldSizes(sizes);
      saveFieldSizes(sizes);
      fitAllLabels();
    });
    el.addEventListener('input', function () {
      applyFieldSizes(readSizes());
      fitAllLabels();
    });
  });

  var FONT_KEY = 'dms-label-font';
  var fontSelect = document.getElementById('label-font');
  var fontCodes = <?= json_encode(array_keys(letter_font_options()), JSON_UNESCAPED_UNICODE) ?>;

  function applyLabelFont(code) {
    if (!fontCodes || fontCodes.indexOf(code) === -1) code = 'greta';
    var root = document.body;
    if (!root) return;
    (fontCodes || []).forEach(function (c) {
      root.classList.remove('letter-font-' + c);
    });
    root.classList.add('letter-font-' + code);
    if (fontSelect) fontSelect.value = code;
  }

  function saveLabelFont(code) {
    try { localStorage.setItem(FONT_KEY, code); } catch (e) {}
  }

  var initialFont = 'greta';
  try {
    var savedFont = localStorage.getItem(FONT_KEY) || '';
    if (savedFont && fontCodes.indexOf(savedFont) !== -1) initialFont = savedFont;
  } catch (e) {}
  applyLabelFont(initialFont);

  if (fontSelect) {
    fontSelect.addEventListener('change', function () {
      var code = fontSelect.value || 'greta';
      applyLabelFont(code);
      saveLabelFont(code);
      if (document.fonts && document.fonts.ready) {
        document.fonts.ready.then(function () {
          fitAllLabels();
          requestAnimationFrame(fitAllLabels);
        });
      } else {
        fitAllLabels();
      }
    });
  }

  if (tpl) {
    tpl.addEventListener('change', function () {
      savePrefs(1);
      if (start) start.value = '1';
      form.submit();
    });
  }
  if (start) {
    start.addEventListener('change', function () { form.submit(); });
  }
  if (copiesInput) {
    copiesInput.addEventListener('change', function () {
      var max = parseInt(copiesInput.getAttribute('max') || '1', 10) || 1;
      var val = parseInt(copiesInput.value || '1', 10) || 1;
      if (val < 1) val = 1;
      if (val > max) val = max;
      copiesInput.value = String(val);
      form.submit();
    });
  }
  if (printBtn) {
    printBtn.addEventListener('click', function () {
      savePrefs(form.getAttribute('data-next-start'));
      // إن تغيّر عدد النسخ دون تطبيق، أعد التحميل ثم اطبع حتى تظهر التكرارات على نفس الصفحة
      if (copiesInput) {
        var urlCopies = parseInt(params.get('copies') || '1', 10) || 1;
        var fieldCopies = parseInt(copiesInput.value || '1', 10) || 1;
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
      fitAllLabels();
      window.print();
    });
  }
  if (autoPrint) {
    params.delete('autoprint');
    var clean = window.location.pathname + '?' + params.toString();
    window.history.replaceState({}, '', clean);
    setTimeout(function () {
      fitAllLabels();
      window.print();
    }, 250);
  }

  function labelOverflows(bodyEl, stack) {
    var buffer = 1;
    var availH = bodyEl.clientHeight;
    var availW = bodyEl.clientWidth;
    if (availH < 4 || availW < 4) return false;
    if (stack.scrollHeight > availH - buffer) return true;
    if (stack.scrollWidth > availW - buffer) return true;
    var lines = stack.children;
    for (var i = 0; i < lines.length; i++) {
      if (lines[i].scrollWidth > availW - buffer) return true;
    }
    return false;
  }

  function fitLabel(bodyEl) {
    if (!bodyEl) return;
    var stack = bodyEl.querySelector('.print-label-stack');
    if (!stack) return;

    // حجم كبير افتراضياً؛ يُصغَّر فقط عند تجاوز مساحة الملصق
    stack.style.fontSize = '';
    stack.style.transform = '';
    var base = parseFloat(window.getComputedStyle(stack).fontSize) || 22;
    var min = Math.max(12, base * 0.62);
    if (!labelOverflows(bodyEl, stack)) return;

    var lo = min;
    var hi = base;
    var best = min;
    for (var step = 0; step < 28; step++) {
      var mid = (lo + hi) / 2;
      stack.style.fontSize = mid + 'px';
      if (labelOverflows(bodyEl, stack)) {
        hi = mid;
      } else {
        best = mid;
        lo = mid;
      }
    }
    stack.style.fontSize = best + 'px';
  }

  function fitAllLabels() {
    document.querySelectorAll('.print-label-fit').forEach(fitLabel);
  }

  var fontWaitTimer = setTimeout(fitAllLabels, 800);
  if (document.fonts && document.fonts.ready) {
    document.fonts.ready.then(function () {
      clearTimeout(fontWaitTimer);
      fitAllLabels();
      requestAnimationFrame(fitAllLabels);
    }).catch(function () {
      clearTimeout(fontWaitTimer);
      fitAllLabels();
    });
  } else {
    window.addEventListener('load', function () {
      clearTimeout(fontWaitTimer);
      fitAllLabels();
    });
  }
  fitAllLabels();
  window.addEventListener('resize', fitAllLabels);
  window.addEventListener('beforeprint', fitAllLabels);
  if (window.matchMedia) {
    try {
      window.matchMedia('print').addListener(fitAllLabels);
    } catch (e) {}
  }
})();
</script>
</body>
</html>
