<?php
require_once __DIR__ . '/../config/auth.php';
$user = require_login();
$id = (int) ($_GET['id'] ?? 0);
$item = get_circular($id);
if (!$item || !user_can_access_circular($item, $user)) {
    flash('danger', __('circular.not_found'));
    redirect(base_url('circulars/index.php'));
}
$files = get_circular_files($id);
$targetDepts = get_circular_departments($id);
$canEdit = user_can_edit_circular($item, $user);
$canDeleteItem = user_can_delete_circular($item, $user);
$inScope = circular_user_in_scope($item, $user);
$isDraft = ($item['status'] ?? '') === 'draft';
$issueDenied = $inScope && $isDraft && dms_perm_ui('circulars.issue', $user) === 'deny';
$editDenied = $inScope && dms_perm_ui('circulars.edit', $user) === 'deny';
$deleteDenied = !$canDeleteItem && $isDraft && $editDenied;
$pageTitle = format_ref_caption('circular', $item['ref_number'] ?? '');

require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title"><?= e((string) $item['subject']) ?></h1>
        <p class="page-sub">
            <span dir="ltr"><?= e(format_ref_caption('circular', $item['ref_number'] ?? '')) ?></span>
            <?= circular_status_badge((string) $item['status']) ?>
            <?php if (circular_is_electronic($item)): ?>
                <span class="badge text-bg-light border"><?= e(__('circular.badge_electronic')) ?></span>
            <?php endif; ?>
        </p>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-outline-secondary" href="<?= e(base_url('circulars/index.php')) ?>"><?= e(__('action.back')) ?></a>
        <?php
        $canPrintCircular = function_exists('user_can') && user_can('circulars.print_form', $user);
        $canPrintCircularThermal = function_exists('user_can') && user_can('circulars.print_thermal', $user);
        ?>
        <?php if ($canPrintCircular || $canPrintCircularThermal): ?>
        <div class="dropdown d-inline-block">
            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-printer me-1"></i><?= e(__('corr.print_menu')) ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <?php if ($canPrintCircular && circular_is_electronic($item) && circular_has_letter($item)): ?>
                <li>
                    <a class="dropdown-item" href="<?= e(base_url('circulars/letter.php?id='.$id)) ?>" target="_blank" rel="noopener">
                        <i class="bi bi-file-earmark-text me-2"></i><?= e(__('circular.print_letter')) ?>
                    </a>
                </li>
                <?php if (trim((string) ($item['letter_body'] ?? '')) !== '' && trim((string) ($item['letter_body_en'] ?? '')) !== ''): ?>
                <li>
                    <a class="dropdown-item" href="<?= e(base_url('circulars/letter.php?id='.$id.'&lang=en')) ?>" target="_blank" rel="noopener">
                        <i class="bi bi-file-earmark-text me-2"></i><?= e(__('circular.print_letter_en')) ?>
                    </a>
                </li>
                <?php endif; ?>
                <?php endif; ?>
                <?php if ($canPrintCircular): ?>
                <li>
                    <a class="dropdown-item" href="<?= e(base_url('circulars/print.php?id='.$id)) ?>" target="_blank" rel="noopener">
                        <i class="bi bi-printer me-2"></i><?= e(__('circular.print')) ?>
                    </a>
                </li>
                <?php endif; ?>
                <?php if ($canPrintCircularThermal): ?>
                <li>
                    <a class="dropdown-item" href="<?= e(base_url('circulars/thermal_label.php?id='.$id)) ?>" target="_blank" rel="noopener">
                        <i class="bi bi-upc me-2"></i><?= e(__('thermal.print')) ?>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </div>
        <?php endif; ?>
        <?php if (user_can_issue_circular($item, $user) && ($item['status'] ?? '') === 'draft'): ?>
            <?php $issueNeedsFile = !circular_is_electronic($item) && !$files; ?>
            <?php if ($issueNeedsFile): ?>
            <span class="d-inline-block" tabindex="0" data-bs-toggle="tooltip" title="<?= e(__('circular.issue_need_file')) ?>">
                <button type="button" class="btn btn-kff" disabled style="pointer-events:none"><i class="bi bi-send me-1"></i><?= e(__('circular.issue')) ?></button>
            </span>
            <?php else: ?>
            <form method="post" action="<?= e(base_url('circulars/issue.php')) ?>" class="d-inline">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $id ?>">
                <button type="submit" class="btn btn-kff"><i class="bi bi-send me-1"></i><?= e(__('circular.issue')) ?></button>
            </form>
            <?php endif; ?>
        <?php elseif ($issueDenied): ?>
            <?= dms_perm_denied_btn(__('circular.issue'), 'bi-send', 'btn-kff') ?>
        <?php endif; ?>
        <?php if ($canEdit): ?>
            <a class="btn btn-outline-primary" href="<?= e(base_url('circulars/edit.php?id='.$id)) ?>"><?= e(__('action.edit')) ?></a>
        <?php elseif ($editDenied): ?>
            <?= dms_perm_denied_btn(__('action.edit'), '', 'btn-outline-primary') ?>
        <?php endif; ?>
        <?php if ($canDeleteItem): ?>
            <form method="post" action="<?= e(base_url('circulars/delete.php')) ?>" class="d-inline" data-confirm="<?= e(__('circular.confirm_delete')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $id ?>">
                <button type="submit" class="btn btn-outline-danger"><?= e(__('action.delete')) ?></button>
            </form>
        <?php elseif ($deleteDenied): ?>
            <?= dms_perm_denied_btn(__('action.delete'), '', 'btn-outline-danger') ?>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <?php
        $audienceOfficers = [];
        if (($item['audience'] ?? '') === 'departments' && $targetDepts) {
            foreach ($targetDepts as $d) {
                if (function_exists('department_officer_fields')) {
                    $officer = department_officer_fields($d);
                    $d['title_prefix'] = $officer['title_prefix'];
                    $d['directed_from'] = $officer['directed_from'];
                }
                $audienceOfficers[] = $d;
            }
        }
        ?>
        <div class="card-panel mb-3">
            <div class="card-header"><i class="bi bi-info-circle me-1"></i><?= e(__('circular.details')) ?></div>
            <div class="card-body p-0">
                <div class="corr-meta-grid">
                    <div class="corr-meta-item">
                        <span class="corr-meta-label"><?= e(__('corr.ref_label_circular')) ?></span>
                        <span class="corr-meta-value fw-semibold corr-meta-num" dir="ltr"><?= e((string) $item['ref_number']) ?></span>
                    </div>
                    <div class="corr-meta-item">
                        <span class="corr-meta-label"><?= e(__('circular.date')) ?></span>
                        <span class="corr-meta-value"><?= e(format_date($item['circular_date'])) ?></span>
                    </div>
                    <div class="corr-meta-item">
                        <span class="corr-meta-label"><?= e(__('circular.issuing_dept')) ?></span>
                        <span class="corr-meta-value"><?php
                            $fromRow = circular_officer_display_row($item);
                            render_officer_with_office(
                                correspondence_department_officer_label($fromRow),
                                correspondence_officer_subtitle($fromRow)
                            );
                        ?></span>
                    </div>
                    <div class="corr-meta-item">
                        <span class="corr-meta-label"><?= e(__('circular.audience')) ?></span>
                        <span class="corr-meta-value">
                            <?php if ($audienceOfficers): ?>
                                <span class="d-flex flex-column gap-2">
                                    <?php foreach ($audienceOfficers as $d): ?>
                                        <?php render_officer_with_office(department_person_option_label($d), department_officer_subtitle($d)); ?>
                                    <?php endforeach; ?>
                                </span>
                            <?php else: ?>
                                <?= e(circular_audience_label((string) ($item['audience'] ?? ''))) ?>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="corr-meta-item corr-meta-span-full">
                        <span class="corr-meta-label"><?= e(__('circular.subject')) ?></span>
                        <span class="corr-meta-value fw-semibold"><?= e((string) $item['subject']) ?></span>
                    </div>
                    <?php if (!empty($item['notes'])): ?>
                    <div class="corr-meta-item corr-meta-span-full">
                        <span class="corr-meta-label"><?= e(__('corr.notes')) ?></span>
                        <span class="corr-meta-value"><?= nl2br(e((string) $item['notes'])) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if (circular_is_electronic($item)): ?>
        <?php
        $letterHasAr = trim((string) ($item['letter_body'] ?? '')) !== '';
        $letterHasEn = trim((string) ($item['letter_body_en'] ?? '')) !== '';
        ?>
        <div class="card-panel mb-3">
            <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                <span><i class="bi bi-file-earmark-text me-1"></i><?= e(__('circular.letter_section')) ?></span>
                <?php if ($canPrintCircular && circular_has_letter($item)): ?>
                    <a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('circulars/letter.php?id='.$id)) ?>" target="_blank" rel="noopener">
                        <i class="bi bi-printer me-1"></i><?= e(__('circular.print_letter')) ?>
                    </a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (!circular_has_letter($item)): ?>
                    <p class="text-muted mb-0"><?= e(__('circular.letter_empty')) ?></p>
                <?php elseif ($letterHasAr && $letterHasEn): ?>
                    <ul class="nav nav-tabs letter-lang-tabs mb-3" role="tablist">
                        <li class="nav-item"><button class="nav-link active" type="button" data-bs-toggle="tab" data-bs-target="#circular-letter-ar"><?= e(__('tpl.body_ar')) ?></button></li>
                        <li class="nav-item"><button class="nav-link" type="button" data-bs-toggle="tab" data-bs-target="#circular-letter-en"><?= e(__('tpl.body_en')) ?></button></li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="circular-letter-ar">
                            <div class="circular-letter-paper"><?= circular_letter_preview_html($item, $targetDepts, 'ar') ?></div>
                        </div>
                        <div class="tab-pane fade" id="circular-letter-en">
                            <div class="circular-letter-paper"><?= circular_letter_preview_html($item, $targetDepts, 'en') ?></div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="circular-letter-paper"><?= circular_letter_preview_html($item, $targetDepts) ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="card-panel mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-paperclip me-1"></i><?= e(circular_is_electronic($item) ? __('circular.annex_files') : __('circular.files')) ?></span>
                <?php if (!circular_is_electronic($item)): ?>
                <span class="badge text-bg-light text-dark"><?= count($files) ?></span>
                <?php endif; ?>
            </div>
            <div class="card-body p-4">
                <?php if (!$files): ?>
                    <p class="text-muted mb-0"><?= e(__('circular.no_files')) ?></p>
                <?php else: ?>
                    <div class="corr-file-list">
                        <?php foreach ($files as $f):
                            $isPdf = str_contains(strtolower((string) ($f['file_type'] ?? '')), 'pdf')
                                || str_ends_with(strtolower((string) ($f['file_name'] ?? '')), '.pdf');
                            $uploader = user_display_name($f, 'uploader_name', 'uploader_name_en');
                        ?>
                        <div class="corr-file-row">
                            <div class="corr-file-icon"><i class="bi bi-file-earmark<?= $isPdf ? '-pdf' : '' ?>"></i></div>
                            <div class="corr-file-meta">
                                <a class="corr-file-name" href="<?= e(file_download_url('circular', (int) $f['id'])) ?>" target="_blank"><?= e($f['file_name']) ?></a>
                                <span class="dms-file-list-type ms-1"><?= e(dms_upload_file_type_label((string) ($f['file_name'] ?? ''), (string) ($f['file_type'] ?? ''))) ?></span>
                                <div class="small text-muted"><?= e($uploader !== '' ? $uploader : __('common.none')) ?> — <?= e(format_datetime($f['uploaded_at'])) ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <?php
        $activity = get_circular_activity($id);
        require __DIR__ . '/../includes/corr_activity_card.php';
        ?>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
