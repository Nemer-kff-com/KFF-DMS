<?php
require_once __DIR__ . '/../config/auth.php';
$user = require_login();
$id = (int) ($_GET['id'] ?? 0);
$corr = get_correspondence($id);
if (!$corr || !user_can_access_correspondence($corr, $user)) {
    exit(__('common.not_found'));
}
if (!correspondence_is_closed($corr) || trim((string) ($corr['close_action'] ?? '')) === '') {
    exit(__('close.print_unavailable'));
}

$folder = correspondence_folder_for_type((string) ($corr['type'] ?? 'incoming'));
$backUrl = base_url($folder . '/view.php?id=' . $id);
$editUrl = base_url('correspondence/close.php?id=' . $id);
$cssVer = (int) (@filemtime(__DIR__ . '/../assets/css/app.css') ?: 90);
$formDate = !empty($corr['closed_at']) ? substr((string) $corr['closed_at'], 0, 10) : date('Y-m-d');
$deptName = department_display_name($corr, 'department_name', 'department_name_en');
$result = (string) ($corr['close_result'] ?? '');
$closeDocs = get_closure_documents($id);

$mark = static function (bool $on): string {
    $cls = $on ? 'print-check is-checked' : 'print-check';
    $sym = $on ? '✓' : '';
    return '<span class="' . $cls . '" aria-hidden="true">' . $sym . '</span>';
};
?>
<!DOCTYPE html>
<html lang="<?= e(dms_lang_code()) ?>" dir="<?= e(dms_page_dir()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(__('close.print_title')) ?> — <?= e($corr['ref_number']) ?></title>
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
<body class="print-page print-corr print-close-page" dir="<?= e(dms_page_dir()) ?>">
<div class="print-toolbar no-print">
    <div class="print-toolbar-title"><?= e(__('close.print_title')) ?> — <span dir="ltr"><?= e($corr['ref_number']) ?></span></div>
    <div class="d-flex flex-wrap gap-2 align-items-center">
        <button type="button" class="btn btn-kff btn-sm" onclick="window.print()">
            <i class="bi bi-printer me-1"></i><?= e(__('action.print')) ?>
        </button>
        <a class="btn btn-outline-secondary btn-sm" href="<?= e($editUrl) ?>"><?= e(__('close.page')) ?></a>
        <a class="btn btn-outline-secondary btn-sm" href="<?= e($backUrl) ?>">
            <i class="bi <?= dms_is_rtl() ? 'bi-arrow-right' : 'bi-arrow-left' ?> me-1"></i><?= e(__('action.back')) ?>
        </a>
    </div>
</div>

<div class="print-sheet print-close-sheet">
    <div class="print-header">
        <?php render_print_logo('print'); ?>
        <div class="print-doc-type"><?= e(__('close.print_title')) ?></div>
        <div class="print-close-form-date">
            <span><?= e(__('corr.date')) ?>:</span>
            <strong><?= e(format_date($formDate)) ?></strong>
        </div>
    </div>

    <table class="table table-bordered print-fields">
        <tr>
            <th><?= e(__('corr.number')) ?></th>
            <td class="print-close-ref" dir="ltr"><?= e($corr['ref_number']) ?></td>
        </tr>
        <tr>
            <th><?= e(__('corr.date')) ?></th>
            <td><?= e(format_date($corr['corr_date'])) ?></td>
        </tr>
        <tr>
            <th><?= e(__('corr.subject')) ?></th>
            <td><?= e($corr['subject']) ?></td>
        </tr>
        <tr>
            <th><?= e(__('close.action')) ?></th>
            <td class="print-close-action"><?= nl2br(e((string) $corr['close_action'])) ?></td>
        </tr>
        <tr>
            <th><?= e(__('close.decision_no')) ?></th>
            <td><?= e((string) ($corr['close_decision_no'] ?? '') ?: '—') ?></td>
        </tr>
        <tr>
            <th><?= e(__('close.decision_date')) ?></th>
            <td><?= e(!empty($corr['close_decision_date']) ? format_date($corr['close_decision_date']) : '—') ?></td>
        </tr>
        <tr>
            <th><?= e(__('close.result')) ?></th>
            <td class="print-close-checks">
                <label class="print-check-item"><?= $mark($result === 'completed') ?><span><?= e(__('close.result_completed')) ?></span></label>
                <label class="print-check-item"><?= $mark($result === 'executed') ?><span><?= e(__('close.result_executed')) ?></span></label>
                <label class="print-check-item"><?= $mark($result === 'for_archive') ?><span><?= e(__('close.result_for_archive')) ?></span></label>
            </td>
        </tr>
        <tr>
            <th><?= e(__('close.attach_section')) ?></th>
            <td class="print-close-checks">
                <label class="print-check-item"><?= $mark(!empty($corr['close_attach_decision'])) ?><span><?= e(__('close.attach_decision')) ?></span></label>
                <label class="print-check-item"><?= $mark(!empty($corr['close_attach_letter'])) ?><span><?= e(__('close.attach_letter')) ?></span></label>
                <label class="print-check-item"><?= $mark(!empty($corr['close_attach_proof'])) ?><span><?= e(__('close.attach_proof')) ?></span></label>
                <label class="print-check-item">
                    <?= $mark(!empty($corr['close_attach_other'])) ?>
                    <span>
                        <?= e(__('close.attach_other')) ?>
                        <?php if (!empty($corr['close_attach_other_text'])): ?>
                            : <?= e((string) $corr['close_attach_other_text']) ?>
                        <?php endif; ?>
                    </span>
                </label>
                <?php if ($closeDocs !== []): ?>
                    <div class="print-close-file-list">
                        <?php foreach ($closeDocs as $doc): ?>
                            <div>• <?= e((string) $doc['file_name']) ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <th><?= e(__('close.notes')) ?></th>
            <td><?= nl2br(e((string) ($corr['close_notes'] ?? '') ?: '—')) ?></td>
        </tr>
        <tr>
            <th><?= e(__('close.department')) ?></th>
            <td><?= e($deptName !== '' ? $deptName : '—') ?></td>
        </tr>
    </table>

    <?php render_print_page_footer($corr, ['side' => 'closed']); ?>
</div>
</body>
</html>
