<?php
require_once __DIR__ . '/../../config/auth.php';
$user = require_archive_section($kind);
ensure_archive_schema();
$id = (int) ($_GET['id'] ?? 0);
$def = archive_register_def($kind);
$row = $def ? archive_register_get($kind, $id) : null;
if (!$def || !$row) {
    flash('danger', __('archive.not_found'));
    redirect(base_url('archive/index.php'));
}
$folder = archive_register_guess_folder($def, $row);
$files = archive_register_folder_files($folder);
$showFiles = ($def['folder_kinds'] ?? []) !== [] || $files !== [];
$canEdit = user_can_archive_section($kind, 'edit', $user);
$canDelete = user_can_archive_section($kind, 'delete', $user);
$editDenied = dms_perm_ui(archive_section_perm($kind, 'edit'), $user) === 'deny';
$deleteDenied = dms_perm_ui(archive_section_perm($kind, 'delete'), $user) === 'deny';
$pageTitle = archive_register_title($def, $row);
$groups = archive_register_form_groups($def);
$kindTipParts = [];
foreach ($def['list_cols'] as $col) {
    $key = (string) ($col['key'] ?? '');
    if (!in_array($key, ['archive_no', 'ARCHIVE_NUM', 'ARCHIVE_NUMBER', 'KAIDNUMBER'], true)) {
        continue;
    }
    $bit = archive_register_list_cell_text($col, $row);
    if ($bit !== '') {
        $kindTipParts[] = $bit;
    }
}
$gregCol = (string) ($def['date_greg_col'] ?? '');
$hijriCol = (string) ($def['date_col'] ?? '');
if ($gregCol !== '' || $hijriCol !== '') {
    $dateParts = archive_register_date_pair_parts([
        'greg_key' => $gregCol !== '' ? $gregCol : 'APPLICATION_DATE_GREG',
        'key' => $hijriCol !== '' ? $hijriCol : 'APPLICATION_DATE_HIJRI',
    ], $row);
    foreach (['greg', 'hijri'] as $dk) {
        if (trim((string) ($dateParts[$dk] ?? '')) !== '') {
            $kindTipParts[] = (string) $dateParts[$dk];
        }
    }
}
$kindTip = implode(' آ· ', $kindTipParts);
require __DIR__ . '/../../includes/header.php';
?>
<div class="page-header page-narrow">
    <div>
        <h1 class="page-title"><?= e($pageTitle) ?></h1>
        <p class="page-sub">
            <span class="badge text-bg-light border"<?php if ($kindTip !== ''): ?> title="<?= e($kindTip) ?>" data-bs-toggle="tooltip"<?php endif; ?>><?= e(__($def['label'])) ?></span>
        </p>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-outline-secondary" href="<?= e(archive_register_url($kind, 'list')) ?>">
            <i class="bi <?= dms_is_rtl() ? 'bi-arrow-right' : 'bi-arrow-left' ?> me-1"></i><?= e(__('action.back')) ?>
        </a>
        <a class="btn btn-outline-secondary" href="<?= e(archive_register_url($kind, 'print', ['id' => $id])) ?>" target="_blank" rel="noopener">
            <i class="bi bi-printer me-1"></i><?= e(__('action.print')) ?>
        </a>
        <?php if ($canEdit): ?>
            <a class="btn btn-outline-primary" href="<?= e(archive_register_url($kind, 'form', ['id' => $id])) ?>"><?= e(__('action.edit')) ?></a>
        <?php elseif ($editDenied): ?>
            <?= dms_perm_denied_btn(__('action.edit'), '', 'btn-outline-primary') ?>
        <?php endif; ?>
        <?php if ($canDelete): ?>
            <form method="post" action="<?= e(archive_register_url($kind, 'delete')) ?>" class="d-inline" data-confirm="<?= e(__('archive.confirm_delete')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $id ?>">
                <button type="submit" class="btn btn-outline-danger"><?= e(__('action.delete')) ?></button>
            </form>
        <?php elseif ($deleteDenied): ?>
            <?= dms_perm_denied_btn(__('action.delete'), '', 'btn-outline-danger') ?>
        <?php endif; ?>
    </div>
</div>
<div class="page-narrow">
<div class="row g-4 corr-view">
    <div class="<?= $showFiles ? 'col-xl-8' : 'col-12' ?>">
        <?php foreach ($groups as $secKey => $fields): ?>
            <?php
            $shown = [];
            $hasCityId = false;
            foreach ($fields as $xf) {
                if ((string) ($xf['key'] ?? '') === 'CITY_ID') {
                    $hasCityId = true;
                    break;
                }
            }
            foreach ($fields as $f) {
                if ($hasCityId && (string) ($f['key'] ?? '') === 'CITY_AR') {
                    continue;
                }
                $isCountry = ($f['type'] ?? '') === 'lookup' && ($f['lookup'] ?? '') === 'countries';
                $isDate = ($f['type'] ?? '') === 'date' || ($f['type'] ?? '') === 'date_pair';
                if ((string) ($f['key'] ?? '') === 'CITY_ID') {
                    $disp = archive_register_city_label($row);
                    $html = false;
                    if ($disp === '') {
                        $disp = '—';
                    }
                } elseif ($isCountry) {
                    $disp = archive_country_cell_html((int) archive_register_row_val($row, (string) ($f['key'] ?? '')));
                    $html = $disp !== '';
                } elseif ($isDate) {
                    $disp = archive_register_field_display($f, $row);
                    $html = false;
                    if ($disp === '') {
                        $disp = '—';
                    }
                } else {
                    $disp = archive_register_field_display($f, $row);
                    $html = false;
                }
                if ($disp !== '') {
                    $shown[] = [$f, $disp, $html];
                }
            }
            if (!$shown) {
                continue;
            }
            ?>
            <div class="card-panel mb-3">
                <div class="card-header"><i class="bi bi-info-circle me-1"></i><?= e(__($secKey)) ?></div>
                <div class="card-body p-0">
                    <div class="corr-meta-grid">
                        <?php foreach ($shown as [$f, $disp, $html]): ?>
                            <div class="corr-meta-item <?= ($f['type'] ?? '') === 'textarea' ? 'corr-meta-span-2' : '' ?>">
                                <span class="corr-meta-label"><?= e(__($f['label'])) ?></span>
                                <span class="corr-meta-value"<?= !empty($f['ltr']) && !$html ? ' dir="ltr"' : '' ?>><?= $html ? $disp : e($disp) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php if ($showFiles): ?>
    <div class="col-xl-4">
        <div class="card-panel mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-paperclip me-1"></i><?= e(__('archive.files')) ?></span>
                <span class="badge text-bg-light text-dark"><?= count($files) ?></span>
            </div>
            <div class="card-body p-4">
                <?php if (!$files): ?>
                    <p class="text-muted mb-0"><?= e(__('archive.no_files')) ?></p>
                <?php else: ?>
                    <div class="corr-file-list">
                        <?php foreach ($files as $i => $path): ?>
                            <?php
                            $fileName = archive_register_file_display_name($row, $files, (int) $i);
                            $isPdf = str_ends_with(strtolower($fileName), '.pdf');
                            $canOpenFile = !function_exists('user_can_view_register_files') || user_can_view_register_files($kind, $row, $user);
                            ?>
                            <div class="corr-file-row">
                                <div class="corr-file-icon"><i class="bi bi-file-earmark<?= $isPdf ? '-pdf' : '' ?>"></i></div>
                                <div class="corr-file-meta">
                                    <?php if ($canOpenFile): ?>
                                    <a href="<?= e(archive_register_url($kind, 'file', ['id' => $id, 'n' => $i])) ?>" target="_blank" rel="noopener" dir="ltr"><?= e($fileName) ?></a>
                                    <?php else: ?>
                                    <span class="text-muted" tabindex="0" data-bs-toggle="tooltip" data-bs-placement="top" title="<?= e(__('conf.archive_file_locked')) ?>"><i class="bi bi-lock-fill me-1"></i><span dir="ltr"><?= e($fileName) ?></span></span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($canEdit): ?>
                                    <form method="post" action="<?= e(archive_register_url($kind, 'file_delete')) ?>" class="d-inline" data-confirm="<?= e(__('corr.delete_attach_confirm')) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int) $id ?>">
                                        <input type="hidden" name="n" value="<?= (int) $i ?>">
                                        <input type="hidden" name="back" value="view">
                                        <button class="btn btn-sm btn-icon btn-outline-danger" type="submit" title="<?= e(__('action.delete')) ?>" aria-label="<?= e(__('action.delete')) ?>">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
