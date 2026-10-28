<?php
require_once __DIR__ . '/../../config/auth.php';
$user = require_archive_section('general');
$pageTitle = __('archive.general');

$filters = [
    'q' => trim($_GET['q'] ?? ''),
    'doc_type' => $_GET['doc_type'] ?? '',
    'from' => $_GET['from'] ?? '',
    'to' => $_GET['to'] ?? '',
    'hijri_from' => trim((string) ($_GET['hijri_from'] ?? '')),
    'hijri_to' => trim((string) ($_GET['hijri_to'] ?? '')),
    'department_id' => $_GET['department_id'] ?? '',
    'category_id' => $_GET['category_id'] ?? '',
    'confidentiality' => $_GET['confidentiality'] ?? '',
    'doc_year' => trim((string) ($_GET['doc_year'] ?? '')),
];
$list = archive_list_page($filters, $user, dms_request_page());
$rows = $list['rows'];
$pagination = $list['pagination'];
$listTotal = $list['total'];
$departments = departments_list();
if (function_exists('departments_visible_to_user')) {
    $departments = departments_visible_to_user($user, $departments);
}
$pageScripts = dms_list_page_scripts();
$reportQuery = http_build_query(array_filter($filters, static fn($v) => $v !== '' && $v !== null));
$reportQs = $reportQuery !== '' ? '?' . $reportQuery : '';

require __DIR__ . '/../../includes/header.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title"><?= e(__('archive.general')) ?></h1>
        <p class="page-sub"><?= e(__('archive.general_sub')) ?></p>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-outline-secondary" href="<?= e(base_url('archive/index.php')) ?>">
            <i class="bi <?= dms_is_rtl() ? 'bi-arrow-right' : 'bi-arrow-left' ?> me-1"></i><?= e(__('action.back')) ?>
        </a>
        <a class="btn btn-outline-secondary" href="<?= e(base_url('archive/general/list_print.php' . $reportQs)) ?>" target="_blank" rel="noopener">
            <i class="bi bi-printer"></i> <?= e(__('action.print')) ?>
        </a>
        <?php $addBtn = dms_perm_add_btn(archive_section_perm('general', 'create'), base_url('archive/general/create.php'), __('archive.new'), 'bi-plus-lg', $user); ?>
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
                    <input type="text" name="q" class="form-control" placeholder="<?= e(__('archive.search_ph')) ?>" value="<?= e($filters['q']) ?>">
                </div>
                <div class="list-filters-item">
                    <label class="form-label"><?= e(__('archive.doc_type')) ?></label>
                    <select name="doc_type" class="form-select">
                        <option value=""><?= e(__('common.all')) ?></option>
                        <?php foreach (archive_doc_types_list() as $t): ?>
                            <option value="<?= e($t['code']) ?>" <?= $filters['doc_type'] === $t['code'] ? 'selected' : '' ?>><?= e(bilingual_label($t)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="list-filters-item">
                    <label class="form-label"><?= e(__('archive.doc_year')) ?></label>
                    <select name="doc_year" class="form-select">
                        <option value=""><?= e(__('common.all')) ?></option>
                        <?php foreach (archive_years_list($user) as $y): ?>
                            <option value="<?= e($y) ?>" <?= (string) $filters['doc_year'] === (string) $y ? 'selected' : '' ?>><?= e($y) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="list-filters-item">
                    <label class="form-label"><?= e(__('corr.category')) ?></label>
                    <select name="category_id" class="form-select">
                        <option value=""><?= e(__('common.all')) ?></option>
                        <?php foreach (categories_list() as $c): ?>
                            <option value="<?= (int) $c['id'] ?>" <?= (string) $filters['category_id'] === (string) $c['id'] ? 'selected' : '' ?>><?= e(category_display_name($c)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php render_filter_department_field($user, $departments, $filters['department_id']); ?>
                <div class="list-filters-item">
                    <label class="form-label"><?= e(__('corr.confidentiality')) ?></label>
                    <select name="confidentiality" class="form-select">
                        <option value=""><?= e(__('common.all')) ?></option>
                        <?php foreach (confidentiality_levels_list() as $c): ?>
                            <option value="<?= e($c['code']) ?>" <?= (string) $filters['confidentiality'] === (string) $c['code'] ? 'selected' : '' ?>><?= e(bilingual_label($c)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="list-filters-footer">
                <?php render_filter_date_range($filters); ?>
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
                    <th><?= e(__('archive.number')) ?></th>
                    <th><?= e(__('archive.doc_title')) ?></th>
                    <th><?= e(__('archive.doc_type')) ?></th>
                    <th><?= e(__('corr.category')) ?></th>
                    <th><?= e(__('archive.party')) ?></th>
                    <th><?= e(__('archive.doc_date')) ?></th>
                    <th><?= e(__('archive.location')) ?></th>
                    <th><?= e(__('departments.col_officer')) ?></th>
                    <th class="archive-list-files"></th>
                    <th class="archive-list-actions"></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="10" class="text-center text-muted py-4"><?= e(__('archive.no_items')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="fw-semibold" dir="ltr"><?= e(trim((string) ($r['archive_no'] ?? '')) !== '' ? (string) $r['archive_no'] : '—') ?></td>
                    <td>
                        <?= e(archive_title($r)) ?>
                        <?php if (!empty($r['old_ref'])): ?>
                            <div class="small text-muted" dir="ltr"><?= e($r['old_ref']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge text-bg-light border"><?= e(archive_doc_type_label($r['doc_type'])) ?></span></td>
                    <td><?= e(category_display_name(['name' => $r['category_name'] ?? '', 'name_en' => $r['category_name_en'] ?? '']) ?: __('common.none')) ?></td>
                    <td><?= e($r['party_name'] ?? __('common.none')) ?></td>
                    <td><?php render_corr_date_cell($r['doc_date'] ?? null); ?></td>
                    <td class="small"><?php $loc = archive_location_label($r); echo $loc !== '' ? e($loc) : '—'; ?></td>
                    <td><?php render_correspondence_officer($r); ?></td>
                    <td class="archive-list-files text-center">
                        <?php if ((int) ($r['files_count'] ?? 0) > 0): ?>
                            <span class="badge text-bg-success" title="<?= e(__('archive.files')) ?>"><i class="bi bi-paperclip"></i></span>
                        <?php endif; ?>
                    </td>
                    <td class="archive-list-actions text-nowrap"><a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('archive/general/view.php?id='.(int)$r['id'])) ?>"><?= e(__('action.view_archive')) ?></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php $pageParam = 'page'; require __DIR__ . '/../../includes/list_pagination.php'; ?>
    </div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
