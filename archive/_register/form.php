<?php
require_once __DIR__ . '/../../config/auth.php';
$user = require_archive_section($kind);
ensure_archive_schema();
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$def = archive_register_def($kind);
if (!$def) {
    flash('danger', __('common.not_found'));
    redirect(base_url('archive/index.php'));
}
if ($id > 0 && !user_can_archive_section($kind, 'edit', $user)) {
    flash('danger', __('auth.no_permission'));
    redirect(archive_register_url($kind, 'list'));
}
if ($id < 1 && !user_can_archive_section($kind, 'create', $user)) {
    flash('danger', __('auth.no_permission'));
    redirect(archive_register_url($kind, 'list'));
}
$row = $id > 0 ? (archive_register_get($kind, $id) ?: []) : [];
$existingFiles = ($id > 0 && $row)
    ? archive_register_folder_files(archive_register_guess_folder($def, $row))
    : [];
$groups = archive_register_form_groups($def);
$addLabel = __((string) ($def['add_label'] ?? $def['label']));
$pageTitle = $id > 0 ? __('archive.edit') : $addLabel;
$archiveNo = $id > 0 ? archive_register_display_no((string) ($row['archive_no'] ?? '')) : '';
$cancelUrl = $id > 0
    ? archive_register_url($kind, 'view', ['id' => $id])
    : archive_register_url($kind, 'list');
$v = static function (string $key, $default = '') use ($row) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST[$key])) {
        return $_POST[$key];
    }
    $got = archive_register_row_val($row, $key);
    return $got !== '' ? $got : $default;
};
$pageScripts = dms_hijri_picker_boot(1356);
$gMin = '1900-01-01';
$gMax = date('Y-12-31');
$gPh = __('corr.gregorian_ph');
$hPh = __('corr.hijri_ph');
$pickG = __('corr.gregorian_pick');
$pickH = __('corr.hijri_pick');
$sectionIcons = [
    'archive.reg.sec.record' => 'bi-hash',
    'archive.reg.sec.student' => 'bi-person',
    'archive.reg.sec.study' => 'bi-mortarboard',
    'archive.reg.sec.grant' => 'bi-award',
    'archive.reg.sec.project' => 'bi-building',
    'archive.reg.sec.place' => 'bi-geo-alt',
    'archive.reg.sec.voucher' => 'bi-receipt',
    'archive.reg.sec.party' => 'bi-people',
    'archive.reg.sec.notes' => 'bi-journal-text',
];
require __DIR__ . '/../../includes/header.php';
?>
<div class="page-header page-narrow">
    <div>
        <h1 class="page-title"><?= e($pageTitle) ?></h1>
        <p class="page-sub">
            <?php if ($archiveNo !== ''): ?>
                <span dir="ltr"><?= e($archiveNo) ?></span>
                <span class="text-muted"> — </span>
            <?php endif; ?>
            <?= e(__($def['label'])) ?>
        </p>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-outline-secondary" href="<?= e($cancelUrl) ?>">
            <i class="bi <?= dms_is_rtl() ? 'bi-arrow-right' : 'bi-arrow-left' ?> me-1"></i><?= e(__('action.cancel')) ?>
        </a>
    </div>
</div>
<div class="admin-editor page-narrow">
    <div class="card-panel">
        <div class="card-header">
            <div class="admin-editor-header">
                <span class="admin-editor-icon"><i class="bi <?= e((string) ($def['icon'] ?? 'bi-archive')) ?>"></i></span>
                <span><?= e($pageTitle) ?></span>
            </div>
        </div>
        <div class="card-body">
            <form method="post" action="<?= e(archive_register_url($kind, 'save')) ?>" class="admin-form archive-kind-form" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $id ?>">
                <?php foreach ($groups as $secKey => $fields): ?>
                    <?php $icon = $sectionIcons[$secKey] ?? 'bi-folder'; ?>
                    <div class="corr-field-card mb-3">
                        <div class="corr-field-card-title"><i class="bi <?= e($icon) ?> me-1"></i><?= e(__($secKey)) ?></div>
                        <div class="row g-3 corr-field-card-body">
                            <?php foreach ($fields as $f): ?>
                                <div class="<?= e($f['col'] ?? 'col-md-6') ?>">
                                    <label class="form-label" for="f_<?= e($f['key']) ?>"><?= e(__($f['label'])) ?><?= !empty($f['required']) ? ' *' : '' ?></label>
                                    <?php if (($f['type'] ?? '') === 'lookup'): ?>
                                        <select name="<?= e($f['key']) ?>" id="f_<?= e($f['key']) ?>" class="form-select" <?= !empty($f['required']) ? 'required' : '' ?>>
                                            <option value=""><?= e(__('common.none')) ?></option>
                                            <?php foreach (archive_lookups((string) ($f['lookup'] ?? ''), false) as $opt): ?>
                                                <?php
                                                $stored = (string) $v($f['key']);
                                                $matched = archive_lookup_row((string) ($f['lookup'] ?? ''), $stored);
                                                $sel = $matched && (int) ($matched['id'] ?? 0) === (int) ($opt['id'] ?? 0);
                                                ?>
                                                <option value="<?= (int) $opt['id'] ?>" <?= $sel ? 'selected' : '' ?>>
                                                    <?= e(bilingual_label(['name_ar' => $opt['name_ar'] ?? '', 'name_en' => $opt['name_en'] ?? '', 'name' => $opt['name_ar'] ?? ''])) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php elseif (($f['type'] ?? '') === 'textarea'): ?>
                                        <textarea name="<?= e($f['key']) ?>" id="f_<?= e($f['key']) ?>" class="form-control" rows="3" <?= !empty($f['required']) ? 'required' : '' ?>><?= e((string) $v($f['key'])) ?></textarea>
                                    <?php elseif (($f['type'] ?? '') === 'readonly' || ($f['type'] ?? '') === 'ref'): ?>
                                        <?php
                                        $refVal = (string) $v($f['key']);
                                        if (($f['key'] ?? '') === 'archive_no' && $refVal !== '') {
                                            $refVal = archive_register_display_no($refVal);
                                        }
                                        ?>
                                        <input type="text" id="f_<?= e($f['key']) ?>" class="form-control bg-light" value="<?= e($refVal) ?>" dir="ltr" readonly>
                                    <?php elseif (($f['type'] ?? '') === 'date'): ?>
                                        <?php
                                        $fid = 'f_' . $f['key'];
                                        $pair = (string) ($f['pair'] ?? '');
                                        $pairId = $pair !== '' ? 'f_' . $pair : '';
                                        $rawDate = (string) $v($f['key']);
                                        $keyUp = strtoupper((string) ($f['key'] ?? ''));
                                        $isGreg = str_contains($keyUp, 'GREG');
                                        if ($isGreg) {
                                            $native = archive_register_parse_greg($rawDate);
                                            $display = $native !== '' ? format_date_input_value($native) : archive_format_compact_date($rawDate);
                                            if ($display === '') {
                                                $display = $rawDate;
                                            }
                                            ?>
                                        <div class="gregorian-date-field">
                                            <input id="<?= e($fid) ?>-display" type="text" class="form-control gregorian-date-display" dir="ltr"
                                                   inputmode="numeric" autocomplete="off" placeholder="<?= e($gPh) ?>"
                                                   data-date-input="<?= e($fid) ?>" value="<?= e($display) ?>">
                                            <input id="<?= e($fid) ?>" type="date" name="<?= e($f['key']) ?>" class="gregorian-date-native"
                                                   min="<?= e($gMin) ?>" max="<?= e($gMax) ?>"
                                                   <?= $pairId !== '' ? ' data-hijri-target="' . e($pairId) . '"' : '' ?>
                                                   value="<?= e($native) ?>">
                                            <button type="button" class="gregorian-date-btn" aria-label="<?= e($pickG) ?>" title="<?= e($pickG) ?>">
                                                <i class="bi bi-calendar3"></i>
                                            </button>
                                        </div>
                                            <?php
                                        } else {
                                            $hVal = archive_format_compact_date($rawDate);
                                            if ($hVal === '') {
                                                $hVal = $rawDate;
                                            }
                                            ?>
                                        <div class="hijri-date-field">
                                            <input id="<?= e($fid) ?>" type="text" name="<?= e($f['key']) ?>" class="form-control hijri-date-input"
                                                   dir="ltr" inputmode="numeric" autocomplete="off" placeholder="<?= e($hPh) ?>"
                                                   <?= $pairId !== '' ? ' data-gregorian-target="' . e($pairId) . '"' : '' ?>
                                                   value="<?= e($hVal) ?>">
                                            <button type="button" class="hijri-date-btn" aria-label="<?= e($pickH) ?>" title="<?= e($pickH) ?>">
                                                <i class="bi bi-calendar3"></i>
                                            </button>
                                        </div>
                                            <?php
                                        }
                                        ?>
                                    <?php else: ?>
                                        <?php
                                        $inputVal = (string) $v($f['key']);
                                        $keyUp = strtoupper((string) ($f['key'] ?? ''));
                                        if (str_contains($keyUp, 'DATE') || str_contains($keyUp, 'HIJRI')) {
                                            $fmt = archive_format_compact_date($inputVal);
                                            if ($fmt !== '') {
                                                $inputVal = $fmt;
                                            }
                                        }
                                        ?>
                                        <input type="text" name="<?= e($f['key']) ?>" id="f_<?= e($f['key']) ?>" class="form-control" value="<?= e($inputVal) ?>" <?= !empty($f['ltr']) ? 'dir="ltr"' : '' ?> <?= !empty($f['required']) ? 'required' : '' ?>>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <div class="admin-form-section admin-form-section-last">
                    <div class="row g-3">
                        <div class="col-lg-6">
                            <div class="corr-field-card h-100">
                                <div class="corr-field-card-title">
                                    <span><i class="bi bi-paperclip me-1"></i><?= e(__('archive.scan_files')) ?></span>
                                </div>
                                <div class="corr-field-card-body">
                    <?php if ($existingFiles): ?>
                        <div class="corr-file-list mb-3">
                            <?php foreach ($existingFiles as $i => $path): ?>
                                <?php
                                $fileName = archive_register_file_display_name($row, $existingFiles, (int) $i);
                                $isPdf = str_ends_with(strtolower($fileName), '.pdf');
                                $canOpenFile = !function_exists('user_can_view_register_files') || user_can_view_register_files($kind, $row ?? [], $user);
                                ?>
                                <div class="corr-file-row">
                                    <div class="corr-file-icon"><i class="bi bi-file-earmark<?= $isPdf ? '-pdf' : '' ?>"></i></div>
                                    <div class="corr-file-meta">
                                        <?php if ($canOpenFile): ?>
                                        <a href="<?= e(archive_register_url($kind, 'file', ['id' => $id, 'n' => (int) $i])) ?>" target="_blank" rel="noopener" dir="ltr"><?= e($fileName) ?></a>
                                        <?php else: ?>
                                        <span class="text-muted" tabindex="0" data-bs-toggle="tooltip" data-bs-placement="top" title="<?= e(__('conf.archive_file_locked')) ?>"><i class="bi bi-lock-fill me-1"></i><span dir="ltr"><?= e($fileName) ?></span></span>
                                        <?php endif; ?>
                                        <span class="dms-file-list-type ms-1"><?= e(dms_upload_file_type_label($fileName, '')) ?></span>
                                    </div>
                                    <button type="submit" form="archive-file-del-<?= (int) $i ?>" class="btn btn-sm btn-icon btn-outline-danger" title="<?= e(__('action.delete')) ?>" aria-label="<?= e(__('action.delete')) ?>">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <?php
                    $showEnclosure = false;
                    $dmsFile = [
                        'name' => 'attachments[]',
                        'id' => 'archive_kind_attachments',
                        'multiple' => true,
                        'accept' => '.pdf,.jpg,.jpeg,.png,.gif,.webp',
                        'label' => __('corr.attach'),
                        'hint' => '',
                        'browse' => __('file.browse'),
                        'placeholder' => $existingFiles ? __('file.placeholder_more') : __('file.placeholder'),
                        'selected' => __('file.selected'),
                    ];
                    require __DIR__ . '/../../includes/corr_attachments_upload_block.php';
                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="corr-issue-decide">
                    <div class="corr-decide-actions">
                    <button class="btn btn-kff" type="submit" data-keep-label data-confirm="<?= e($id > 0 ? __('archive.confirm_save_edit') : __('archive.confirm_save')) ?>"><i class="bi bi-check2 me-1"></i><?= e(__('action.save')) ?></button>
                    <a class="btn btn-kff-soft" href="<?= e($cancelUrl) ?>" data-keep-label data-confirm="<?= e($id > 0 ? __('archive.confirm_cancel_edit') : __('archive.confirm_cancel_create')) ?>"><?= e(__('action.cancel')) ?></a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<?php foreach ($existingFiles as $i => $path): ?>
<form id="archive-file-del-<?= (int) $i ?>" method="post" action="<?= e(archive_register_url($kind, 'file_delete')) ?>" class="d-none" data-confirm="<?= e(__('corr.delete_attach_confirm')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $id ?>">
    <input type="hidden" name="n" value="<?= (int) $i ?>">
    <input type="hidden" name="back" value="form">
</form>
<?php endforeach; ?>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
