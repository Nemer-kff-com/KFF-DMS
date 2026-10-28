<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/briefing.php';
$user = require_login();
$id = (int) ($_GET['id'] ?? 0);
$corr = get_correspondence($id);
if (!$corr || !user_can_access_correspondence($corr, $user)) {
    exit(__('common.not_found'));
}
briefing_require_incoming($corr);
$brief = get_correspondence_brief($id);
if (!$brief) {
    exit(__('common.not_found'));
}

$folder = briefing_corr_folder($corr);
$backUrl = base_url($folder . '/view.php?id=' . $id);
$editUrl = base_url('correspondence/brief.php?id=' . $id);
$title = briefing_title_for_type((string) $corr['type']);
$cssVer = (int) (@filemtime(__DIR__ . '/../assets/css/app.css') ?: 90);
$jsVer = (int) (@filemtime(__DIR__ . '/../assets/js/app.js') ?: 23);
$isOutgoing = ($corr['type'] ?? '') === 'outgoing';
$options = briefing_normalize_options($brief['options'] ?? []);
$basisLine = briefing_basis_line($brief);
$dirState = briefing_dir_order_from_request();
$headerDate = trim((string) ($corr['corr_date'] ?? ''));
$receiptDate = trim((string) ($brief['receipt_date'] ?? ''));
$printHijriDate = $headerDate !== '' ? format_hijri_date($headerDate) : '';
?>
<!DOCTYPE html>
<html lang="<?= e(dms_lang_code()) ?>" dir="<?= e(dms_page_dir()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> — <?= e($corr['ref_number']) ?></title>
    <link rel="icon" href="<?= e(base_url('assets/img/favicon.ico')) ?>">
    <link href="<?= e(dms_bootstrap_css()) ?>" rel="stylesheet">
    <link href="<?= e(dms_bootstrap_icons_css()) ?>" rel="stylesheet">
    <link href="<?= e(base_url('assets/css/app.css')) ?>?v=<?= $cssVer ?>" rel="stylesheet">
    <link href="<?= e(dms_print_fast_css()) ?>" rel="stylesheet">
    <style>
        @page {
            size: A4 portrait;
            margin: 0;
        }
    </style>
</head>
<body class="print-page print-corr print-brief-page" dir="rtl">
<div class="print-toolbar print-label-toolbar print-corr-toolbar no-print">
    <div class="print-label-toolbar-main">
        <div class="print-label-toolbar-top">
            <div class="print-label-toolbar-actions">
                <?= briefing_swap_dir_button('btn btn-outline-secondary btn-sm') ?>
                <button type="button" class="btn btn-kff btn-sm" onclick="window.print()">
                    <i class="bi bi-printer me-1"></i><?= e(__('action.print')) ?>
                </button>
                <a class="btn btn-outline-secondary btn-sm" href="<?= e(briefing_word_url($id)) ?>">
                    <i class="bi bi-file-earmark-word me-1"></i><?= e(__('brief.word')) ?>
                </a>
                <a class="btn btn-outline-secondary btn-sm" href="<?= e($editUrl) ?>"><?= e(__('brief.page')) ?></a>
                <a class="btn btn-outline-secondary btn-sm" href="<?= e($backUrl) ?>">
                    <i class="bi <?= dms_is_rtl() ? 'bi-arrow-right' : 'bi-arrow-left' ?> me-1"></i><?= e(__('action.back')) ?>
                </a>
            </div>
            <div class="print-toolbar-title"><?= e($title) ?> — <span dir="ltr"><?= e($corr['ref_number']) ?></span></div>
            <div class="print-label-toolbar-actions print-label-toolbar-actions-ghost" aria-hidden="true">
                <button type="button" class="btn btn-outline-secondary btn-sm" tabindex="-1" disabled>
                    <i class="bi bi-arrow-down-up" aria-hidden="true"></i>
                </button>
                <button type="button" class="btn btn-kff btn-sm" tabindex="-1" disabled>
                    <i class="bi bi-printer me-1"></i><?= e(__('action.print')) ?>
                </button>
                <a class="btn btn-outline-secondary btn-sm" tabindex="-1"><?= e(__('brief.word')) ?></a>
                <a class="btn btn-outline-secondary btn-sm" tabindex="-1"><?= e(__('brief.page')) ?></a>
                <a class="btn btn-outline-secondary btn-sm" tabindex="-1"><?= e(__('action.back')) ?></a>
            </div>
        </div>
    </div>
</div>

<div class="print-sheet" dir="rtl">
    <div class="print-header">
        <div class="print-qr-block" dir="rtl">
            <div class="print-doc-ref"><?= e($corr['ref_number']) ?></div>
            <?php if ($headerDate !== ''): ?>
            <?php if ($printHijriDate !== ''): ?>
            <div class="print-doc-date-hijri">
                <span><?= e($printHijriDate) ?></span>
                <span class="print-doc-date-mark">هـ</span>
            </div>
            <?php endif; ?>
            <div class="print-doc-date-value">
                <span><?= e(date('Y/m/d', strtotime($headerDate))) ?></span>
                <span class="print-doc-date-mark">م</span>
            </div>
            <?php endif; ?>
        </div>
        <?php render_print_logo('print'); ?>
        <div class="print-doc-type"><?= e($title) ?></div>
    </div>

    <table class="table print-fields print-fields-subject">
        <tr>
            <th><?= e(__('corr.subject')) ?></th>
            <td class="print-subject-cell"><?= e($corr['subject']) ?></td>
        </tr>
    </table>

    <?php
    $briefDirParts = function_exists('print_direction_parts') ? print_direction_parts($corr) : ['to' => [], 'from' => []];
    $briefDirFallback = static function (array $part, string $stored): void {
        $fields = function_exists('briefing_party_fields')
            ? briefing_party_fields($part, $stored)
            : ['name' => $stored, 'job' => '', 'org' => ''];
        $stack = [
            'prefix' => '',
            'person' => $fields['name'],
            'honorific' => '',
            'job' => $fields['job'],
            'org' => $fields['org'],
            'titles' => [],
        ];
        if (function_exists('print_direction_part_has_content') && print_direction_part_has_content($stack)) {
            render_print_party_stack($stack);
            return;
        }
        echo '<span class="print-dir-name">' . e($stored !== '' ? $stored : '—') . '</span>';
    };
    ?>
    <table class="table print-direction" id="briefDirection">
        <tbody>
        <tr data-dir-row="to">
            <th><?= e(__('print.directed_to')) ?></th>
            <td><?php $briefDirFallback($briefDirParts['to'] ?? [], trim((string) ($brief['directed_to'] ?? ''))); ?></td>
        </tr>
        <tr data-dir-row="from">
            <th><?= e(__('print.directed_from')) ?></th>
            <td><?php $briefDirFallback($briefDirParts['from'] ?? [], trim((string) ($brief['directed_from'] ?? ''))); ?></td>
        </tr>
        </tbody>
    </table>

    <?php
    $priorityCode = strtolower(trim((string) ($corr['priority'] ?? '')));
    $confidentialityCode = strtolower(trim((string) ($corr['confidentiality'] ?? '')));
    $printFlags = [];
    if ($confidentialityCode !== '' && $confidentialityCode !== 'normal') {
        $printFlags[] = confidentiality_label($confidentialityCode);
    }
    if ($priorityCode !== '' && $priorityCode !== 'normal') {
        $printFlags[] = priority_label($priorityCode);
    }
    $metaRows = [];
    if ($basisLine !== '') {
        $metaRows[] = [__('brief.basis_label'), $basisLine];
    }
    if ($receiptDate !== '' && $receiptDate !== $headerDate) {
        $metaRows[] = [__('brief.receipt_date'), briefing_dual_date($receiptDate)];
    }
    $attach = trim((string) ($brief['attachments_desc'] ?? ''));
    if ($attach !== '') {
        $metaRows[] = [__('brief.attachments'), $attach, ''];
    }
    $requestText = trim((string) ($brief['request_text'] ?? ''));
    if ($requestText !== '') {
        $metaRows[] = [__('brief.request'), $requestText, 'brief-print-red print-request-cell'];
    }
    ?>
    <?php if ($printFlags || $metaRows): ?>
    <table class="table print-fields print-fields-subject">
        <?php if ($printFlags): ?>
        <tr>
            <td class="print-corr-flags-cell" colspan="2">
                <?php foreach ($printFlags as $flag): ?>
                    <span class="print-corr-flag"><?= e($flag) ?></span>
                <?php endforeach; ?>
            </td>
        </tr>
        <?php endif; ?>
        <?php foreach ($metaRows as $metaRow):
            [$label, $value] = $metaRow;
            $cellClass = (string) ($metaRow[2] ?? '');
        ?>
        <tr>
            <th><?= e($label) ?></th>
            <td<?= $cellClass !== '' ? ' class="' . e($cellClass) . '"' : '' ?>><?= nl2br(e($value)) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
    <hr class="brief-meta-rule">

    <?php
    $blocks = [
        ['k' => __('brief.basis'), 'v' => $brief['basis_text'] ?? '', 'red' => false, 'justify' => true],
        ['k' => __('brief.study'), 'v' => $brief['study_text'] ?? '', 'red' => false, 'justify' => false],
    ];
    foreach ($blocks as $block):
        $text = trim((string) $block['v']);
        if ($text === '') {
            continue;
        }
        $vClass = 'brief-block-v';
        if (!empty($block['red'])) {
            $vClass .= ' brief-print-red';
        }
        $vClass .= !empty($block['justify']) ? ' brief-block-justify' : ' brief-block-pre';
    ?>
        <div class="brief-block">
            <div class="brief-block-k"><?= e($block['k']) ?></div>
            <div class="<?= e($vClass) ?>"><?= nl2br(e($text)) ?></div>
        </div>
    <?php endforeach; ?>

    <?php
    $rec = is_array($brief['recommendation'] ?? null)
        ? $brief['recommendation']
        : briefing_parse_recommendation($brief['recommendation_text'] ?? '');
    if (briefing_recommendation_has_content($rec)):
    ?>
        <div class="brief-block">
            <div class="brief-block-k"><?= e(__('brief.recommendation')) ?></div>
            <div class="brief-block-v brief-print-red brief-rec-body">
                <?php if (trim((string) ($rec['intro'] ?? '')) !== ''): ?>
                    <div class="brief-rec-intro"><?= e($rec['intro']) ?></div>
                <?php endif; ?>
                <?php
                $recItemsPrint = array_values(array_filter(
                    $rec['items'] ?? [],
                    static fn($item): bool => trim((string) $item) !== ''
                ));
                if ($recItemsPrint !== []):
                ?>
                    <ol class="brief-rec-points"><?php foreach ($recItemsPrint as $item): ?><li><?= e(trim((string) $item)) ?></li><?php endforeach; ?></ol>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="brief-block brief-direction">
        <div class="brief-block-k"><?= e(__('brief.direction')) ?></div>
        <?php if (trim((string) ($brief['direction_intro'] ?? '')) !== ''): ?>
            <div class="brief-block-v mb-2 brief-block-pre"><?= nl2br(e($brief['direction_intro'])) ?></div>
        <?php endif; ?>
        <ul class="brief-options">
            <?php foreach ($options as $opt): ?>
                <li>
                    <span class="brief-check<?= !empty($opt['selected']) ? ' is-on' : '' ?>"></span>
                    <span><?= e($opt['label']) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if (trim((string) ($brief['decision_note'] ?? '')) !== ''): ?>
            <div class="brief-block-v mt-2"><?= e($brief['decision_note']) ?></div>
        <?php endif; ?>
    </div>

    <div class="brief-sign">
        <div class="brief-sign-name"><?= e(trim((string) ($brief['approver_name'] ?? '')) ?: '—') ?></div>
        <?php if (trim((string) ($brief['approver_title'] ?? '')) !== ''): ?>
            <div class="brief-sign-title"><?= e($brief['approver_title']) ?></div>
        <?php endif; ?>
    </div>
</div>
<script src="<?= e(dms_bootstrap_js()) ?>"></script>
<script src="<?= e(base_url('assets/js/app.js')) ?>?v=<?= $jsVer ?>"></script>
<?php briefing_dir_order_script($dirState['order'], $dirState['forced']); ?>
</body>
</html>
