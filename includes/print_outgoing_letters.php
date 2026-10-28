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
?>
<!DOCTYPE html>
<html lang="<?= e(dms_lang_code()) ?>" dir="<?= e(dms_page_dir()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($letterPrintTitle) ?></title>
    <link rel="icon" href="<?= e(base_url('assets/img/favicon.ico')) ?>">
    <link href="<?= e(dms_bootstrap_css()) ?>" rel="stylesheet">
    <link href="<?= e(dms_bootstrap_icons_css()) ?>" rel="stylesheet">
    <link href="<?= e(base_url('assets/css/app.css')) ?>?v=<?= $cssVer ?>" rel="stylesheet">
    <link href="<?= e(dms_brand_fonts_extra_css()) ?>" rel="stylesheet">
    <link href="<?= e(dms_print_fast_css()) ?>" rel="stylesheet">
    <style>
        @page { size: A4 portrait; margin: 0; }
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
                padding: 8mm 2.5cm 22mm 2.5cm !important;
                overflow: visible !important;
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
                bottom: 8mm !important;
                margin: 0 !important;
                width: auto !important;
                z-index: 5;
            }
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
                <button type="button" class="btn btn-kff btn-sm" onclick="window.print()">
                    <i class="bi bi-printer me-1"></i><?= e(__('action.print')) ?>
                </button>
                <a class="btn btn-outline-secondary btn-sm" href="<?= e($letterPrintBackUrl) ?>">
                    <i class="bi <?= dms_is_rtl() ? 'bi-arrow-right' : 'bi-arrow-left' ?> me-1"></i><?= e(__('action.back')) ?>
                </a>
            </div>
            <div class="print-toolbar-title">
                <?= e($letterPrintTitle) ?>
                <?php if (count($letterPrintItems) > 1): ?>
                    <span class="text-muted">(<?= count($letterPrintItems) ?>)</span>
                <?php endif; ?>
            </div>
            <div class="print-label-toolbar-actions print-label-toolbar-actions-ghost" aria-hidden="true">
                <select class="form-select form-select-sm letter-print-font-select" tabindex="-1" disabled></select>
                <button type="button" class="btn btn-kff btn-sm" tabindex="-1" disabled>
                    <i class="bi bi-printer me-1"></i><?= e(__('action.print')) ?>
                </button>
                <a class="btn btn-outline-secondary btn-sm" tabindex="-1"><?= e(__('action.back')) ?></a>
            </div>
        </div>
    </div>
</div>

<?php foreach ($letterPrintItems as $corr): ?>
    <?php
    $qrId = 'qr-' . (int) ($corr['id'] ?? 0);
    require __DIR__ . '/print_outgoing_letter_sheet.php';
    ?>
<?php endforeach; ?>
<script>
(function () {
  var FONT_KEY = 'dms-letter-print-font';
  var fontSelect = document.getElementById('letter-print-font');
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

  var initial = 'greta';
  try {
    var saved = localStorage.getItem(FONT_KEY) || '';
    if (saved && fontCodes.indexOf(saved) !== -1) {
      initial = saved;
    }
  } catch (e) {}
  applyLetterPrintFont(initial);

  if (fontSelect) {
    fontSelect.addEventListener('change', function () {
      var code = fontSelect.value || 'greta';
      applyLetterPrintFont(code);
      try { localStorage.setItem(FONT_KEY, code); } catch (e) {}
    });
  }
})();
</script>
</body>
</html>
