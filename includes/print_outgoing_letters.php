<?php
/**
 * صفحة طباعة خطاب/خطابات صادر (نمط batch_print).
 * المتغيرات:
 * - $letterPrintItems list<array> معاملات الصادر
 * - $letterPrintTitle string
 * - $letterPrintBackUrl string
 */
$letterPrintItems = array_values(array_filter(
    is_array($letterPrintItems ?? null) ? $letterPrintItems : [],
    static fn($row): bool => is_array($row)
));
$letterPrintTitle = (string) ($letterPrintTitle ?? __('bulk.print_letters'));
$letterPrintBackUrl = (string) ($letterPrintBackUrl ?? base_url('outgoing/index.php'));
$cssVer = (int) (@filemtime(__DIR__ . '/../assets/css/app.css') ?: 90);
$thermalCssVer = (int) (@filemtime(__DIR__ . '/../assets/css/thermal-label.css') ?: 1);
$jsVer = (int) (@filemtime(__DIR__ . '/../assets/js/app.js') ?: 1);
?>
<!DOCTYPE html>
<html lang="<?= e(dms_lang_code()) ?>" dir="<?= e(dms_page_dir()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($letterPrintTitle) ?></title>
    <?= function_exists('dms_favicon_link_html') ? dms_favicon_link_html() : '' ?>
    <link href="<?= e(dms_bootstrap_css()) ?>" rel="stylesheet">
    <link href="<?= e(dms_bootstrap_icons_css()) ?>" rel="stylesheet">
    <link href="<?= e(base_url('assets/css/app.css')) ?>?v=<?= $cssVer ?>" rel="stylesheet">
    <link href="<?= e(base_url('assets/css/thermal-label.css')) ?>?v=<?= $thermalCssVer ?>" rel="stylesheet">
    <link href="<?= e(dms_brand_fonts_extra_css()) ?>" rel="stylesheet">
    <link href="<?= e(dms_print_fast_css()) ?>" rel="stylesheet">
    <style>
        @page { size: A4 portrait; margin: 0; }
        /* ملصق حراري: يسار الورقة فعلياً + إطار رمادي + محتوى أسود */
        .letter-batch-print .letter-sheet {
            position: relative;
        }
        .letter-batch-print .letter-thermal-host {
            position: absolute !important;
            top: 17mm !important;
            left: 1.4cm !important;
            right: auto !important;
            inset: auto auto auto 1.4cm !important;
            display: block !important;
            width: 50mm !important;
            height: 25mm !important;
            min-width: 50mm !important;
            min-height: 25mm !important;
            margin: 0 !important;
            padding: 0 !important;
            background: transparent !important;
            overflow: visible !important;
            z-index: 6 !important;
            direction: ltr !important;
        }
        .letter-batch-print .letter-thermal-host.is-44x22,
        .letter-batch-print .letter-thermal-host.is-50x25 {
            width: 50mm !important;
            height: 25mm !important;
        }
        .letter-batch-print .letter-thermal-host .print-thermal-sticker {
            width: 50mm !important;
            height: 25mm !important;
            min-width: 50mm !important;
            min-height: 25mm !important;
            max-width: 50mm !important;
            max-height: 25mm !important;
            flex: none !important;
            aspect-ratio: 50 / 25 !important;
            box-shadow: none !important;
            border: 0.2mm solid #9aa3ad !important;
            border-radius: 1.2mm !important;
            outline: none !important;
            background: #fff !important;
            color: #000 !important;
            box-sizing: border-box !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .letter-batch-print .letter-thermal-host .print-thermal-ref,
        .letter-batch-print .letter-thermal-host .print-thermal-text,
        .letter-batch-print .letter-thermal-host .print-thermal-meta,
        .letter-batch-print .letter-thermal-host .print-thermal-date,
        .letter-batch-print .letter-thermal-host .print-label-mark,
        .letter-batch-print .letter-thermal-host .print-thermal-sticker * {
            color: #000 !important;
            -webkit-text-fill-color: #000 !important;
            box-shadow: none !important;
        }
        .letter-batch-print .letter-thermal-host .print-thermal-body {
            align-items: flex-start !important;
            padding-top: 0 !important;
        }
        /* أوضح قليلاً من الحراري الافتراضي (11 ملم) مع الإبقاء داخل 50×25 */
        .letter-batch-print .letter-thermal-host .print-thermal-qr,
        .letter-batch-print .letter-thermal-host .print-thermal-sticker.has-logo .print-thermal-qr {
            width: 13.5mm !important;
            height: 13.5mm !important;
            min-width: 13.5mm !important;
            min-height: 13.5mm !important;
            align-self: flex-start !important;
            margin-top: -0.8mm !important;
        }
        .letter-batch-print .letter-thermal-host .print-thermal-qr img {
            width: 100% !important;
            height: 100% !important;
            object-fit: contain;
            filter: none;
        }
        .letter-batch-print .letter-thermal-host .print-thermal-logo-img:not(.is-thermal-ready) {
            filter: grayscale(1) brightness(0) contrast(1.35);
        }
        .letter-batch-print .letter-thermal-host .print-thermal-logo-img.is-thermal-ready {
            filter: none;
        }
        .letter-batch-print .print-header {
            min-height: 32mm;
        }
        .letter-batch-print .print-brand-top .print-org-logo {
            max-height: 118px !important;
            max-width: 260px !important;
        }
        .letter-batch-print .print-brand-top .print-system-icon {
            width: 52px !important;
            height: 52px !important;
        }
        .letter-batch-print .letter-sheet {
            padding-top: 14mm;
            padding-bottom: 10mm;
        }
        .letter-batch-print .print-page-footer {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 4mm;
            margin: 0;
            padding: 0 2.5cm;
            box-sizing: border-box;
            width: auto;
            z-index: 5;
        }
        .letter-batch-print .letter-thermal-host {
            top: 17mm !important;
        }
        @media print {
            html, body.letter-batch-print {
                background: #fff !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .letter-batch-print .letter-sheet {
                position: relative !important;
                display: block !important;
                page-break-after: always !important;
                break-after: page !important;
                margin: 0 !important;
                box-shadow: none !important;
                min-height: 297mm !important;
                height: 297mm !important;
                box-sizing: border-box !important;
                padding: 14mm 2.5cm 10mm 2.5cm !important;
                overflow: visible !important;
            }
            .letter-batch-print .letter-thermal-host {
                top: 17mm !important;
                left: 1.4cm !important;
                right: auto !important;
                width: 50mm !important;
                height: 25mm !important;
                min-width: 50mm !important;
                min-height: 25mm !important;
            }
            .letter-batch-print .print-brand-top .print-org-logo {
                max-height: 118px !important;
                max-width: 260px !important;
            }
            .letter-batch-print .letter-thermal-host .print-thermal-sticker {
                width: 50mm !important;
                height: 25mm !important;
                min-width: 50mm !important;
                min-height: 25mm !important;
                box-shadow: none !important;
                border: 0.2mm solid #9aa3ad !important;
                border-radius: 1.2mm !important;
            }
            .letter-batch-print .letter-thermal-host .print-thermal-logo-img:not(.is-thermal-ready) {
                filter: grayscale(1) brightness(0) contrast(1.35) !important;
            }
            .letter-batch-print .letter-sheet + .letter-sheet {
                page-break-before: always !important;
                break-before: page !important;
            }
            .letter-batch-print .letter-sheet:last-child {
                page-break-after: auto !important;
                break-after: auto !important;
            }
            .letter-batch-print .print-page-footer {
                position: absolute !important;
                left: 0 !important;
                right: 0 !important;
                bottom: 4mm !important;
                margin: 0 !important;
                width: auto !important;
                z-index: 5;
            }
            .letter-batch-print .print-header {
                border-bottom: none !important;
            }
            .letter-batch-print .print-page-footer .print-contact,
            .letter-batch-print .print-contact {
                border-top: none !important;
                padding-top: 0 !important;
            }
            /* إخفاء بصري فقط — المساحة تبقى كما هي حتى لا يرتفع نص الخطاب */
            .letter-batch-print.is-official-paper .print-brand-top,
            .letter-batch-print.is-official-paper .print-page-footer,
            .letter-batch-print.is-official-paper .print-meta-footer,
            .letter-batch-print.is-hide-thermal .letter-thermal-host {
                visibility: hidden !important;
            }
        }
        .letter-batch-print.is-official-paper .print-brand-top,
        .letter-batch-print.is-official-paper .print-page-footer,
        .letter-batch-print.is-official-paper .print-meta-footer {
            visibility: hidden !important;
        }
        .letter-batch-print.is-hide-thermal .letter-thermal-host {
            visibility: hidden !important;
        }
        .letter-print-switches {
            display: flex;
            flex-direction: row;
            flex-wrap: wrap;
            align-items: stretch;
            gap: .5rem;
            width: 100%;
            margin-top: .55rem;
        }
        .letter-print-switches .letter-official-switch {
            flex: 1 1 16rem;
            min-width: 0;
        }
        .letter-official-switch {
            display: flex;
            align-items: center;
            gap: .75rem;
            width: 100%;
            margin: 0;
            padding: .65rem .85rem;
            border: 1px solid var(--kff-border, #dee2e6);
            border-radius: .65rem;
            background: color-mix(in srgb, var(--kff-surface, #fff) 92%, var(--kff-primary, #0d6efd) 8%);
            cursor: pointer;
            user-select: none;
        }
        .letter-official-switch:hover {
            border-color: color-mix(in srgb, var(--kff-primary, #0d6efd) 40%, var(--kff-border, #dee2e6));
        }
        .letter-official-switch:has(input:checked) {
            border-color: color-mix(in srgb, var(--kff-primary, #0d6efd) 55%, transparent);
            background: color-mix(in srgb, var(--kff-primary, #0d6efd) 12%, #fff);
        }
        .letter-official-switch-icon {
            display: inline-flex;
            width: 2.15rem;
            height: 2.15rem;
            flex: 0 0 auto;
            align-items: center;
            justify-content: center;
            border-radius: .5rem;
            background: #fff;
            color: var(--kff-primary, #0d6efd);
            border: 1px solid var(--kff-border, #e9ecef);
            font-size: 1.05rem;
        }
        .letter-official-switch-text {
            display: flex;
            flex-direction: column;
            gap: .1rem;
            min-width: 0;
            flex: 1 1 auto;
        }
        .letter-official-switch-title {
            font-weight: 650;
            font-size: .92rem;
            line-height: 1.3;
        }
        .letter-official-switch-state {
            font-size: .8rem;
            color: var(--kff-muted, #6c757d);
            line-height: 1.35;
        }
        .letter-official-switch .form-check-input {
            width: 1.15rem;
            height: 1.15rem;
            margin: 0;
            flex: 0 0 auto;
            cursor: pointer;
        }
    </style>
</head>
<body class="print-page letter-batch-print">
<div class="print-toolbar print-label-toolbar no-print">
    <div class="print-label-toolbar-main">
        <div class="print-label-toolbar-top">
            <div class="print-label-toolbar-actions">
                <select class="form-select form-select-sm letter-print-font-select" id="letter-print-font" dir="ltr"
                        title="<?= e(__('label.font')) ?>" aria-label="<?= e(__('label.font')) ?>">
                    <?php foreach (letter_font_options() as $code => $opt): ?>
                        <option value="<?= e($code) ?>"<?= $code === 'greta' ? ' selected' : '' ?>><?= e($opt['label']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= dms_print_icon() ?>
                <?= dms_back_icon($letterPrintBackUrl) ?>
            </div>
            <div class="print-toolbar-title">
                <?= e($letterPrintTitle) ?>
                <?php if (count($letterPrintItems) > 1): ?>
                    <span class="text-muted">(<?= count($letterPrintItems) ?>)</span>
                <?php endif; ?>
            </div>
            <div class="print-label-toolbar-actions print-label-toolbar-actions-ghost" aria-hidden="true">
                <select class="form-select form-select-sm letter-print-font-select" tabindex="-1" disabled></select>
                <?= dms_print_icon_ghost() ?>
                <?= dms_back_icon() ?>
            </div>
        </div>
        <div class="letter-print-switches">
        <label class="letter-official-switch" for="letter-official-paper" title="<?= e(__('print.official_paper_hint')) ?>">
            <span class="letter-official-switch-icon" aria-hidden="true"><i class="bi bi-file-earmark-ruled"></i></span>
            <span class="letter-official-switch-text">
                <span class="letter-official-switch-title"><?= e(__('print.official_paper')) ?></span>
                <span class="letter-official-switch-state" data-official-state><?= e(__('print.official_paper_off')) ?></span>
            </span>
            <input type="checkbox" class="form-check-input" id="letter-official-paper" value="1">
        </label>
        <label class="letter-official-switch" for="letter-thermal-sticker" title="<?= e(__('print.thermal_on_letter_hint')) ?>">
            <span class="letter-official-switch-icon" aria-hidden="true"><i class="bi bi-upc"></i></span>
            <span class="letter-official-switch-text">
                <span class="letter-official-switch-title"><?= e(__('print.thermal_on_letter')) ?></span>
                <span class="letter-official-switch-state" data-thermal-state><?= e(__('print.thermal_on_letter_on')) ?></span>
            </span>
            <input type="checkbox" class="form-check-input" id="letter-thermal-sticker" value="1" checked>
        </label>
        </div>
    </div>
</div>

<?php
$letterThermalQrMap = [];
foreach ($letterPrintItems as $corr) {
    $qrId = 'letter-thermal-qr-' . (int) ($corr['id'] ?? 0);
    require __DIR__ . '/print_outgoing_letter_sheet.php';
}
?>
<script src="<?= e(base_url('assets/js/app.js')) ?>?v=<?= $jsVer ?>"></script>
<script>
(function () {
  var thermalQrs = <?= json_encode($letterThermalQrMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?> || {};
  Object.keys(thermalQrs).forEach(function (id) {
    var el = document.getElementById(id);
    if (!el || el.querySelector('img') || !thermalQrs[id]) return;
    var img = document.createElement('img');
    img.src = thermalQrs[id];
    img.alt = '';
    img.width = 640;
    img.height = 640;
    img.style.imageRendering = 'pixelated';
    img.decoding = 'sync';
    el.appendChild(img);
  });

  function toThermalBlack(img) {
    if (typeof renderThermalLogo === 'function') {
      renderThermalLogo(img);
      return;
    }
    if (!img || img.dataset.thermalBlack === '1') return;
    img.dataset.thermalBlack = '1';
    img.classList.add('is-thermal-ready');
  }
  document.querySelectorAll('.letter-thermal-host .print-thermal-logo-img').forEach(toThermalBlack);

  var FONT_KEY = 'dms-letter-print-font';
  var PAPER_KEY = 'dms-letter-official-paper';
  var THERMAL_KEY = 'dms-letter-thermal-sticker';
  var fontSelect = document.getElementById('letter-print-font');
  var paperBox = document.getElementById('letter-official-paper');
  var thermalBox = document.getElementById('letter-thermal-sticker');
  var fontCodes = <?= json_encode(array_keys(letter_font_options()), JSON_UNESCAPED_UNICODE) ?>;

  function applyLetterPrintFont(code) {
    if (!fontCodes || fontCodes.indexOf(code) === -1) {
      code = 'greta';
    }
    document.querySelectorAll('.letter-sheet, .letter-content').forEach(function (el) {
      (fontCodes || []).forEach(function (c) {
        el.classList.remove('letter-font-' + c);
      });
      el.classList.add('letter-font-' + code);
    });
    if (fontSelect) {
      fontSelect.value = code;
    }
  }

  var stateEl = document.querySelector('[data-official-state]');
  var stateOn = <?= json_encode(__('print.official_paper_on'), JSON_UNESCAPED_UNICODE) ?>;
  var stateOff = <?= json_encode(__('print.official_paper_off'), JSON_UNESCAPED_UNICODE) ?>;

  function applyOfficialPaper(on) {
    document.body.classList.toggle('is-official-paper', !!on);
    if (paperBox) {
      paperBox.checked = !!on;
    }
    if (stateEl) {
      stateEl.textContent = on ? stateOn : stateOff;
    }
  }

  var initial = 'greta';
  try {
    var saved = localStorage.getItem(FONT_KEY) || '';
    if (saved && fontCodes.indexOf(saved) !== -1) {
      initial = saved;
    }
  } catch (e) {}
  applyLetterPrintFont(initial);

  var officialOn = false;
  try {
    officialOn = localStorage.getItem(PAPER_KEY) === '1';
  } catch (e2) {}
  applyOfficialPaper(officialOn);

  var thermalStateEl = document.querySelector('[data-thermal-state]');
  var thermalOnText = <?= json_encode(__('print.thermal_on_letter_on'), JSON_UNESCAPED_UNICODE) ?>;
  var thermalOffText = <?= json_encode(__('print.thermal_on_letter_off'), JSON_UNESCAPED_UNICODE) ?>;

  function applyThermalSticker(on) {
    document.body.classList.toggle('is-hide-thermal', !on);
    if (thermalBox) {
      thermalBox.checked = !!on;
    }
    if (thermalStateEl) {
      thermalStateEl.textContent = on ? thermalOnText : thermalOffText;
    }
  }

  var thermalOn = true;
  try {
    var savedThermal = localStorage.getItem(THERMAL_KEY);
    if (savedThermal === '0') thermalOn = false;
    if (savedThermal === '1') thermalOn = true;
  } catch (e3) {}
  applyThermalSticker(thermalOn);

  if (thermalBox) {
    thermalBox.addEventListener('change', function () {
      var on = !!thermalBox.checked;
      applyThermalSticker(on);
      try { localStorage.setItem(THERMAL_KEY, on ? '1' : '0'); } catch (e) {}
    });
  }

  if (fontSelect) {
    fontSelect.addEventListener('change', function () {
      var code = fontSelect.value || 'greta';
      applyLetterPrintFont(code);
      try { localStorage.setItem(FONT_KEY, code); } catch (e) {}
    });
  }
  if (paperBox) {
    paperBox.addEventListener('change', function () {
      var on = !!paperBox.checked;
      applyOfficialPaper(on);
      try { localStorage.setItem(PAPER_KEY, on ? '1' : '0'); } catch (e) {}
    });
  }
})();
</script>
</body>
</html>
