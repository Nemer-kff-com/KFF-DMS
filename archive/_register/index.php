<?php
require_once __DIR__ . '/../../config/auth.php';
$user = require_archive_section($kind);
ensure_archive_schema();
$urls = archive_register_pages()[$kind] ?? null;
$def = archive_register_def($kind);
if (!$urls || !$def) {
    flash('danger', __('common.not_found'));
    redirect(base_url('archive/index.php'));
}
$filters = archive_register_request_filters($def);
$q = (string) ($filters['q'] ?? '');
$list = archive_register_list($kind, $filters, dms_request_page());
$rows = $list['rows'];
$pagination = $list['pagination'];
$listTotal = $list['total'];
$pageTitle = __($def['label']);
$pageScripts = dms_list_page_scripts();
$searchPh = __((string) ($def['search_ph'] ?? 'action.search'));
$addLabel = __((string) ($def['add_label'] ?? 'action.add'));
$showFilesCol = ($def['folder_kinds'] ?? []) !== [];
$reportQs = archive_register_filter_query($filters);
$reportQs = $reportQs !== '' ? '?' . $reportQs : '';
$hasDate = trim((string) ($def['date_col'] ?? '')) !== '';
$extraFilters = $def['filters'] ?? [];
require __DIR__ . '/../../includes/header.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title"><i class="bi <?= e((string) ($def['icon'] ?? 'bi-archive')) ?> me-1"></i><?= e(__($def['label'])) ?></h1>
        <p class="page-sub"><?= e(__($def['sub'])) ?></p>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-outline-secondary" href="<?= e(base_url('archive/index.php')) ?>">
            <i class="bi <?= dms_is_rtl() ? 'bi-arrow-right' : 'bi-arrow-left' ?> me-1"></i><?= e(__('action.back')) ?>
        </a>
        <a class="btn btn-outline-secondary" href="<?= e(archive_register_url($kind, 'list_print') . $reportQs) ?>" target="_blank" rel="noopener">
            <i class="bi bi-printer"></i> <?= e(__('action.print')) ?>
        </a>
        <?php if (!empty(archive_register_pages()[$kind]['export'])): ?>
            <a class="btn btn-outline-secondary" href="<?= e(archive_register_url($kind, 'export') . $reportQs) ?>">
                <i class="bi bi-file-earmark-excel"></i> <?= e(__('action.export_excel')) ?>
            </a>
        <?php endif; ?>
        <?php $addBtn = dms_perm_add_btn(archive_section_perm($kind, 'create'), archive_register_url($kind, 'form'), $addLabel, 'bi-plus-lg', $user); ?>
        <?php if ($addBtn !== ''): ?>
            <?= $addBtn ?>
        <?php endif; ?>
    </div>
</div>
<div class="card-panel mb-3">
    <div class="card-body p-3">
        <form class="list-filters" method="get">
            <div class="list-filters-main">
                <div class="list-filters-item list-filters-search">
                    <label class="form-label"><?= e(__('action.search')) ?></label>
                    <input type="search" name="q" class="form-control" placeholder="<?= e($searchPh) ?>" value="<?= e($q) ?>">
                </div>
                <?php foreach ($extraFilters as $flt): ?>
                    <?php
                    $fname = (string) ($flt['name'] ?? '');
                    if ($fname === '') {
                        continue;
                    }
                    $selected = (string) ($filters[$fname] ?? '');
                    $ftype = (string) ($flt['type'] ?? '');
                    $lookup = (string) ($flt['lookup'] ?? '');
                    if ($ftype !== 'text' && $lookup === '') {
                        continue;
                    }
                    ?>
                    <div class="list-filters-item">
                        <label class="form-label"><?= e(__($flt['label'])) ?></label>
                        <?php if ($ftype === 'text'): ?>
                            <input type="search" name="<?= e($fname) ?>" class="form-control" value="<?= e($selected) ?>">
                        <?php else: ?>
                        <select name="<?= e($fname) ?>" class="form-select">
                            <option value=""><?= e(__('common.all')) ?></option>
                            <?php foreach (archive_lookups($lookup, false) as $opt): ?>
                                <option value="<?= (int) $opt['id'] ?>" <?= $selected === (string) $opt['id'] ? 'selected' : '' ?>>
                                    <?= e(bilingual_label(['name_ar' => $opt['name_ar'] ?? '', 'name_en' => $opt['name_en'] ?? '', 'name' => $opt['name_ar'] ?? ''])) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="list-filters-footer">
                <?php if ($hasDate): ?>
                    <?php render_filter_date_range($filters); ?>
                <?php endif; ?>
                <div class="list-filters-actions">
                    <button class="btn btn-outline-secondary w-100" type="submit"><?= e(__('action.filter')) ?></button>
                </div>
            </div>
        </form>
    </div>
</div>
<div class="card-panel" id="list-main" data-list-panel>
    <div class="list-panel-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <?php foreach ($def['list_cols'] as $col): ?>
                            <th><?= e(__($col['label'])) ?></th>
                        <?php endforeach; ?>
                        <?php if ($showFilesCol): ?>
                            <th class="archive-list-files"></th>
                        <?php endif; ?>
                        <th class="archive-list-actions"></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="<?= count($def['list_cols']) + ($showFilesCol ? 2 : 1) ?>" class="text-center text-muted py-4"><?= e(__('archive.no_items')) ?></td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $r): ?>
                    <?php
                    $viewUrl = archive_register_url($kind, 'view', ['id' => (int) $r['id']]);
                    $nFiles = 0;
                    if ($showFilesCol) {
                        $nFiles = count(archive_register_folder_files(archive_register_guess_folder($def, $r)));
                    }
                    ?>
                    <tr>
                        <?php foreach ($def['list_cols'] as $i => $col): ?>
                            <?php
                            $colType = (string) ($col['type'] ?? '');
                            $raw = trim((string) ($r[$col['key']] ?? ''));
                            $isTitle = in_array($col['key'] ?? '', ['SUBJECT_AR', 'SUBJECT', 'PROJECT_NAME_AR'], true);
                            $cellHtml = '';
                            $useLtrInner = !empty($col['ltr']) || $colType === 'hijri' || $colType === 'ref';
                            if ($colType === 'ref' || ($col['key'] ?? '') === 'archive_no') {
                                $fmt = archive_register_list_cell_text($col, $r);
                                $cellHtml = $fmt !== '' ? e($fmt) : '';
                            } elseif ($colType === 'date_pair') {
                                $cellHtml = archive_register_date_pair_html($col, $r);
                                $useLtrInner = false;
                            } elseif ($colType === 'hijri') {
                                $fmt = archive_format_compact_date($raw);
                                $cellHtml = $fmt !== '' ? e($fmt) : '';
                            } elseif ($colType === 'country' || (($col['lookup'] ?? '') === 'countries')) {
                                $cellHtml = archive_country_cell_html((int) $raw);
                            } elseif ($colType === 'city') {
                                $label = archive_register_city_label($r);
                                $cellHtml = $label !== '' ? e($label) : '';
                            } elseif ($colType === 'lookup' && !empty($col['lookup'])) {
                                $label = archive_lookup_label((string) $col['lookup'], $raw);
                                $cellHtml = $label !== '' ? e($label) : '';
                            } elseif ($raw !== '') {
                                $cellHtml = e($raw);
                            }
                            if ($cellHtml === '' && !$isTitle && $colType !== 'country' && $colType !== 'hijri' && $colType !== 'lookup' && $colType !== 'date_pair' && $colType !== 'ref') {
                                $cellHtml = '—';
                            }
                            ?>
                            <td class="<?= $colType === 'hijri' || $colType === 'date_pair' ? 'text-nowrap' : '' ?>">
                                <?php if ($i === 0 && $colType !== 'country' && $cellHtml !== '' && $cellHtml !== '—'): ?>
                                    <a href="<?= e($viewUrl) ?>" class="fw-semibold text-decoration-none"<?= $useLtrInner ? ' dir="ltr"' : '' ?>><?= $cellHtml ?></a>
                                <?php elseif ($cellHtml !== ''): ?>
                                    <?php if ($useLtrInner): ?><span dir="ltr"><?= $cellHtml ?></span><?php else: ?><?= $cellHtml ?><?php endif; ?>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                        <?php if ($showFilesCol): ?>
                            <td class="archive-list-files text-center">
                                <?php if ($nFiles > 0): ?>
                                    <span class="badge text-bg-success" title="<?= e(__('archive.files')) ?>"><i class="bi bi-paperclip"></i></span>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                        <td class="archive-list-actions text-nowrap">
                            <a class="btn btn-sm btn-outline-primary" href="<?= e($viewUrl) ?>"><?= e(__('action.view_archive')) ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php $pageParam = 'page'; require __DIR__ . '/../../includes/list_pagination.php'; ?>
    </div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
