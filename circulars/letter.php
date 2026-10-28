<?php
/**
 * طباعة خطاب التعميم الإلكتروني.
 */
require_once __DIR__ . '/../config/auth.php';
$user = require_login();
$id = (int) ($_GET['id'] ?? 0);
$item = get_circular($id);
$backUrl = base_url('circulars/view.php?id=' . $id);
if (!$item || !user_can_access_circular($item, $user)) {
    exit(__('circular.not_found'));
}
if (!circular_is_electronic($item)) {
    redirect(base_url('circulars/print.php?id=' . $id));
}
if (function_exists('outgoing_require_print_perm')) {
    outgoing_require_print_perm('circulars.print_form', $user, $backUrl);
}
$lang = strtolower(trim((string) ($_GET['lang'] ?? '')));
$circularLetterLang = circular_letter_lang($item, $lang === 'en' ? 'en' : ($lang === 'ar' ? 'ar' : null));
$audienceLine = circular_audience_line($item, get_circular_departments($id));
$letterPrintTitle = format_ref_caption('circular', $item['ref_number'] ?? '');
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
                margin: 0 !important;
                box-shadow: none !important;
                min-height: 297mm !important;
                height: 297mm !important;
                box-sizing: border-box !important;
                padding: 8mm 2.5cm 22mm 2.5cm !important;
                overflow: visible !important;
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
                <a class="btn btn-outline-secondary btn-sm" href="<?= e($backUrl) ?>">
                    <i class="bi <?= dms_is_rtl() ? 'bi-arrow-right' : 'bi-arrow-left' ?> me-1"></i><?= e(__('action.back')) ?>
                </a>
            </div>
            <div class="print-toolbar-title"><?= e($letterPrintTitle) ?></div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/print_circular_letter_sheet.php'; ?>
<script>
(function () {
  var FONT_KEY = 'dms-letter-print-font';
  var fontSelect = document.getElementById('letter-print-font');
  var fontCodes = <?= json_encode(array_keys(letter_font_options()), JSON_UNESCAPED_UNICODE) ?>;
  function applyLetterPrintFont(code) {
    if (!fontCodes || fontCodes.indexOf(code) === -1) code = 'greta';
    document.querySelectorAll('.letter-sheet, .letter-content').forEach(function (el) {
      (fontCodes || []).forEach(function (c) { el.classList.remove('letter-font-' + c); });
      el.classList.add('letter-font-' + code);
    });
    if (fontSelect) fontSelect.value = code;
  }
  var initial = 'greta';
  try {
    var saved = localStorage.getItem(FONT_KEY) || '';
    if (saved && fontCodes.indexOf(saved) !== -1) initial = saved;
  } catch (e) {}
  applyLetterPrintFont(initial);
  if (fontSelect) {
    fontSelect.addEventListener('change', function () {
      applyLetterPrintFont(fontSelect.value || 'greta');
      try { localStorage.setItem(FONT_KEY, fontSelect.value || 'greta'); } catch (e) {}
    });
  }
})();
</script>
</body>
</html>
