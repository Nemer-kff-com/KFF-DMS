<?php
/**
 * ورقة طباعة بسيطة (تعميم / أرشيف عام / سجل أرشيفي).
 * يتطلب: $typeLabel, $subject, $backUrl
 * اختياري: $printHtmlLang, $printHtmlDir, $printPageTitle, $printDocRef,
 * $printHijriDate, $printGregDate, $toNames, $fromDept, $fromOrg,
 * $metaRows, $fileNames, $fileNamesDir, $notes, $printFooterItem, $cssVer, $jsVer
 */
$printHtmlLang = (string) ($printHtmlLang ?? 'ar');
$printHtmlDir = (string) ($printHtmlDir ?? 'rtl');
$printDocRef = (string) ($printDocRef ?? '');
$printToolbarRef = (string) ($printToolbarRef ?? $printDocRef);
$printPageTitle = (string) ($printPageTitle ?? ($printToolbarRef !== '' ? $printToolbarRef : ($printDocRef !== '' ? $printDocRef : $subject)));
$printHijriDate = (string) ($printHijriDate ?? '');
$printGregDate = (string) ($printGregDate ?? '');
$toNames = is_array($toNames ?? null) ? $toNames : [];
$fromDept = (string) ($fromDept ?? '');
$fromOrg = (string) ($fromOrg ?? '');
$metaRows = is_array($metaRows ?? null) ? $metaRows : [];
$fileNames = is_array($fileNames ?? null) ? $fileNames : [];
$fileNamesDir = (string) ($fileNamesDir ?? '');
$notes = (string) ($notes ?? '');
$printFooterItem = $printFooterItem ?? null;
$cssVer = (int) ($cssVer ?? (@filemtime(__DIR__ . '/../assets/css/app.css') ?: 90));
$jsVer = (int) ($jsVer ?? (@filemtime(__DIR__ . '/../assets/js/app.js') ?: 23));
$showDirection = $toNames || $fromDept !== '' || $fromOrg !== '';
?>
<!DOCTYPE html>
<html lang="<?= e($printHtmlLang) ?>" dir="<?= e($printHtmlDir) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(__('action.print')) ?> <?= e($printPageTitle) ?></title>
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
<body class="print-page print-corr" dir="<?= e($printHtmlDir) ?>">
<div class="print-toolbar no-print">
    <div class="print-toolbar-title"><?= e($typeLabel) ?><?php if ($printToolbarRef !== ''): ?> — <span dir="ltr"><?= e($printToolbarRef) ?></span><?php endif; ?></div>
    <div class="d-flex flex-wrap gap-2 align-items-center">
        <button type="button" class="btn btn-kff btn-sm" onclick="window.print()">
            <i class="bi bi-printer me-1"></i><?= e(__('action.print')) ?>
        </button>
        <a class="btn btn-outline-secondary btn-sm" href="<?= e($backUrl) ?>">
            <i class="bi <?= dms_is_rtl() ? 'bi-arrow-right' : 'bi-arrow-left' ?> me-1"></i><?= e(__('action.back')) ?>
        </a>
    </div>
</div>

<div class="print-sheet" dir="<?= e($printHtmlDir) ?>">
    <div class="print-header">
        <div class="print-qr-block" dir="<?= e($printHtmlDir) ?>">
            <div class="print-qr" aria-hidden="true"></div>
            <?php if ($printDocRef !== ''): ?>
            <div class="print-doc-ref"><?= e($printDocRef) ?></div>
            <?php endif; ?>
            <?php if ($printHijriDate !== ''): ?>
            <div class="print-doc-date-hijri">
                <span><?= e($printHijriDate) ?></span>
                <span class="print-doc-date-mark">هـ</span>
            </div>
            <?php endif; ?>
            <?php if ($printGregDate !== ''): ?>
            <div class="print-doc-date-value">
                <span><?= e($printGregDate) ?></span>
                <span class="print-doc-date-mark">م</span>
            </div>
            <?php endif; ?>
        </div>
        <?php render_print_logo('print'); ?>
        <div class="print-doc-type"><?= e($typeLabel) ?></div>
    </div>

    <table class="table table-bordered print-fields print-fields-subject">
        <tr>
            <th><?= e(__('corr.subject')) ?></th>
            <td class="print-subject-cell"><?= e($subject) ?></td>
        </tr>
    </table>

    <?php if ($showDirection): ?>
    <table class="table table-bordered print-direction">
        <?php if ($toNames): ?>
        <tr data-dir-row="to">
            <th><?= e(__('print.directed_to')) ?></th>
            <td>
                <div class="print-dir-stack">
                    <div class="print-dir-group">
                        <?php foreach ($toNames as $toName): ?>
                        <div class="print-dir-name"><?= e($toName) ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </td>
        </tr>
        <?php endif; ?>
        <?php if ($fromDept !== '' || $fromOrg !== ''): ?>
        <tr data-dir-row="from">
            <th><?= e(__('print.directed_from')) ?></th>
            <td>
                <div class="print-dir-stack">
                    <?php if ($fromDept !== ''): ?>
                    <div class="print-dir-group">
                        <div class="print-dir-name"><?= e($fromDept) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($fromOrg !== '' && $fromOrg !== $fromDept): ?>
                    <div class="print-dir-group">
                        <div class="print-dir-sub"><?= e($fromOrg) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
        <?php endif; ?>
    </table>
    <?php endif; ?>

    <?php if ($metaRows): ?>
    <table class="table table-bordered print-fields print-fields-grid">
        <?php foreach (array_chunk($metaRows, 2) as $pair): ?>
        <tr>
            <?php foreach ($pair as [$label, $value]): ?>
            <th><?= e($label) ?></th><td><?= e($value) ?></td>
            <?php endforeach; ?>
            <?php if (count($pair) === 1): ?>
            <th class="print-cell-blank"></th><td class="print-cell-blank"></td>
            <?php endif; ?>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>

    <?php if ($fileNames): ?>
    <table class="table table-bordered print-fields print-fields-subject">
        <tr>
            <th><?= e(__('corr.enclosures')) ?></th>
            <td<?= $fileNamesDir !== '' ? ' dir="' . e($fileNamesDir) . '"' : '' ?>><?= e(implode('، ', $fileNames)) ?></td>
        </tr>
    </table>
    <?php endif; ?>

    <?php if ($notes !== ''): ?>
    <table class="table table-bordered print-fields print-fields-subject">
        <tr>
            <th><?= e(__('corr.notes')) ?></th>
            <td><?= nl2br(e($notes)) ?></td>
        </tr>
    </table>
    <?php endif; ?>

    <?php render_print_page_footer($printFooterItem); ?>
</div>
<script src="<?= e(dms_bootstrap_js()) ?>"></script>
<script src="<?= e(base_url('assets/js/app.js')) ?>?v=<?= $jsVer ?>"></script>
<script>
</script>
</body>
</html>
