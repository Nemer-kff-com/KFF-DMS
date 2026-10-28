<?php
/**
 * قالب طباعة معاملة (وارد/صادر)
 * يتطلب: $corr, $documents, $qrContent
 * سلوك الطباعة مطابق للنسخة الاحتياطية العاملة (بدون تعطيل CSS قبل print).
 */
$isOutgoing = ($corr['type'] ?? '') === 'outgoing';
$isInternal = ($corr['type'] ?? '') === 'internal';
$typeLabel = $isInternal
    ? __('print.type_internal')
    : ($isOutgoing ? __('print.type_outgoing') : __('print.type_incoming'));
$dirOrder = (($_GET['dir'] ?? '') === 'from') ? 'from' : 'to';
$dirForced = isset($_GET['dir']);
$folder = function_exists('correspondence_type_folder')
    ? correspondence_type_folder((string) ($corr['type'] ?? ''))
    : ($isOutgoing ? 'outgoing' : 'incoming');
$backUrl = base_url($folder . '/view.php?id=' . (int) $corr['id']);
$isCancelled = correspondence_is_cancelled($corr);
$cssVer = (int) (@filemtime(__DIR__ . '/../assets/css/app.css') ?: 90);
$jsVer = (int) (@filemtime(__DIR__ . '/../assets/js/app.js') ?: 23);
$qrDomId = 'qrcode';
$directionTableId = 'printDirection';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(__('action.print')) ?> <?= e($corr['ref_number']) ?></title>
    <link rel="icon" href="<?= e(base_url('assets/img/favicon.ico')) ?>">
    <link href="<?= e(dms_bootstrap_css()) ?>" rel="stylesheet">
    <link href="<?= e(dms_bootstrap_icons_css()) ?>" rel="stylesheet">
    <link href="<?= e(base_url('assets/css/app.css')) ?>?v=<?= $cssVer ?>" rel="stylesheet">
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
<body class="print-page print-corr<?= $isCancelled ? ' is-cancelled' : '' ?>" dir="rtl">
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
            <div class="print-toolbar-title"><?= e($typeLabel) ?> — <span dir="ltr"><?= e($corr['ref_number']) ?></span></div>
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

<?php require __DIR__ . '/print_correspondence_sheet.php'; ?>
<script src="<?= e(dms_bootstrap_js()) ?>"></script>
<script src="<?= e(base_url('assets/js/app.js')) ?>?v=<?= $jsVer ?>"></script>
<script>
/* ترتيب «موجهة إلى / موجهة من» حسب أهمية الطرفين — يُحفظ للمرات القادمة */
(function () {
    var STORE_KEY = 'dms.print.dirOrder';
    var LABELS = <?= json_encode(['to' => __('print.order_to_first'), 'from' => __('print.order_from_first')], JSON_UNESCAPED_UNICODE) ?>;
    var table = document.getElementById('printDirection');
    var button = document.getElementById('swapDirectionBtn');
    var label = document.getElementById('swapDirectionLabel');
    if (!table || !button) { return; }

    function currentOrder() {
        var first = table.querySelector('tr[data-dir-row]');
        return first ? first.getAttribute('data-dir-row') : 'to';
    }

    function applyOrder(order) {
        var row = table.querySelector('tr[data-dir-row="' + order + '"]');
        if (row && row.parentNode.firstElementChild !== row) {
            row.parentNode.insertBefore(row, row.parentNode.firstElementChild);
        }
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
    try { saved = localStorage.getItem(STORE_KEY); } catch (e) { /* تخزين محلي معطّل */ }
    var forced = <?= json_encode($dirForced) ?>;
    var initial = <?= json_encode($dirOrder) ?>;
    applyOrder(!forced && (saved === 'to' || saved === 'from') ? saved : initial);

    button.addEventListener('click', function () {
        var next = currentOrder() === 'to' ? 'from' : 'to';
        applyOrder(next);
        try { localStorage.setItem(STORE_KEY, next); } catch (e) { /* تخزين محلي معطّل */ }
    });
})();
</script>
</body>
</html>
