<?php

require_once __DIR__ . '/../config/auth.php';

$user = require_permission('entities.manage');

$pageTitle = __('nav.entities');

ensure_entities_schema();

$entityScopeReady = entities_department_scope_available();

$canManageAllEntities = function_exists('user_manages_all_entities') && user_manages_all_entities($user);

$isSystemAdmin = can_manage_users($user);

$departments = $canManageAllEntities ? departments_list() : [];

$entityNextSortOrder = 10;
try {
    $entityNextSortOrder = (int) db()->query('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM `tblentities`')->fetchColumn();
} catch (Throwable $e) {
    $entityNextSortOrder = 10;
}



if (!$canManageAllEntities && !(function_exists('user_has_assigned_departments') && user_has_assigned_departments($user))) {

    flash('danger', __('entities.department_required'));

    redirect(base_url('index.php'));

}



if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_honorific_preset') {
    verify_csrf();
    header('Content-Type: application/json; charset=utf-8');
    $value = trim((string) ($_POST['value'] ?? ''));
    if ($value === '') {
        echo json_encode(['ok' => false, 'message' => __('entities.honorific_add_empty')], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!entity_honorific_save_extra_preset($value)) {
        echo json_encode(['ok' => false, 'message' => __('entities.honorific_add_invalid')], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $options = [];
    foreach (entity_honorific_presets() as $presetValue => $presetLabel) {
        $options[] = ['value' => $presetValue, 'label' => $presetLabel];
    }
    echo json_encode(['ok' => true, 'value' => $value, 'options' => $options], JSON_UNESCAPED_UNICODE);
    exit;
}



$isEdit = false;

$createError = '';

$openCreateModal = isset($_GET['add']);

$createValues = [
    'name_ar' => '',
    'directed_to' => '',

    'title_prefix' => '',

    'job_title' => '',

    'honorific' => '',

    'phone' => '',

    'mobile' => '',

    'email' => '',

    'address' => '',

    'location' => '',

    'notes' => '',

    'department_id' => $canManageAllEntities ? '' : (function_exists('user_primary_department_id') ? user_primary_department_id($user) : (int) $user['department_id']),

    'sort_order' => (string) $entityNextSortOrder,

];



if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {

    verify_csrf();

    if (!$entityScopeReady) {

        flash('danger', __('entities.migration_pending'));

        redirect(base_url('admin/entities.php'));

    }

    foreach (array_keys($createValues) as $field) {

        $createValues[$field] = trim((string) ($_POST[$field] ?? ''));

    }

    $createValues['location'] = entity_maps_url_normalize($createValues['location']);



    $departmentId = null;

    if ($canManageAllEntities) {

        $rawDept = trim((string) ($_POST['department_id'] ?? ''));
        $departmentId = ($rawDept === '' || $rawDept === '0') ? null : (int) $rawDept;
        if ($departmentId !== null && $departmentId < 1) {
            $departmentId = null;
        }

        if ($departmentId && !department_exists($departmentId, $departments)) {

            $createError = __('entities.invalid_department');

        }

    } else {

        $departmentId = function_exists('resolve_assigned_department_id')
            ? resolve_assigned_department_id($user, $_POST['department_id'] ?? 0, false)
            : (int) $user['department_id'];

        $createValues['department_id'] = $departmentId;

    }



    if ($createError === '' && $createValues['name_ar'] !== '' && entity_name_exists_in_scope($createValues['name_ar'], $departmentId)) {

        $createError = __('entities.duplicate');

    }

    if ($createError === '' && $createValues['email'] !== '' && !filter_var($createValues['email'], FILTER_VALIDATE_EMAIL)) {

        $createError = __('entities.email_invalid');

    }

    if ($createError === '' && $createValues['location'] !== '' && !entity_maps_url_valid($createValues['location'])) {

        $createError = __('entities.location_invalid');

    }



    $honorific = entity_honorific_from_input($_POST);

    $titlePrefix = entity_title_prefix_from_input($_POST);

    $createValues['honorific'] = $honorific;

    $createValues['title_prefix'] = $titlePrefix;

    $sortOrder = $isSystemAdmin
        ? (int) ($_POST['sort_order'] ?? $entityNextSortOrder)
        : $entityNextSortOrder;
    $createValues['sort_order'] = (string) $sortOrder;

    if ($createError === '' && entity_sort_order_taken($sortOrder)) {

        $createError = __('entities.sort_duplicate');

    }



    if ($createError === '') {

        db()->prepare(

            "INSERT INTO `tblentities`

             (name_ar, directed_to, title_prefix, job_title, honorific, phone, mobile, email, address, location, notes, department_id, sort_order)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)"
        )->execute([
            $createValues['name_ar'],
            $createValues['directed_to'] ?: null,

            $titlePrefix !== '' ? $titlePrefix : null,

            $createValues['job_title'] ?: null,

            $honorific !== '' ? $honorific : null,

            $createValues['phone'] ?: null,

            $createValues['mobile'] ?: null,

            $createValues['email'] ?: null,

            $createValues['address'] ?: null,

            $createValues['location'] ?: null,

            $createValues['notes'] ?: null,

            $departmentId,

            $sortOrder,

        ]);

        flash('success', __('entities.created'));

        redirect(base_url('admin/entities.php'));

    }

    $openCreateModal = true;

}



if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {

    verify_csrf();

    $id = (int) ($_POST['id'] ?? 0);

    $entity = $id ? get_entity($id) : null;

    if (!$entity) {

        flash('danger', __('common.not_found'));

        redirect(base_url('admin/entities.php'));

    }

    if (!user_can_manage_entity($entity, $user)) {

        flash('danger', __('auth.forbidden'));

        redirect(base_url('admin/entities.php'));

    }

    try {

        db()->prepare("DELETE FROM `tblentities` WHERE id=?")->execute([$id]);

        flash('success', __('entities.deleted'));

    } catch (PDOException $e) {

        flash('danger', __('entities.cannot_delete'));

    }

    redirect(base_url('admin/entities.php'));

}



// توافق مع الروابط القديمة: admin/entities.php?edit=5

$legacyEditId = (int) ($_GET['edit'] ?? 0);

if ($legacyEditId) {

    redirect(base_url('admin/entities_edit.php?id=' . $legacyEditId));

}



$listFilters = entities_list_filters_from_request($user);

$listSearch = (string) ($listFilters['q'] ?? '');

$entitiesPage = entities_list_page(false, $user, [

    'filters' => $listFilters,

    'with_stats' => true,

], dms_request_page());

$rows = $entitiesPage['rows'];

$pagination = $entitiesPage['pagination'];

$listTotal = $entitiesPage['total'];

$pageScripts = dms_list_page_scripts();

$entitiesFilterQuery = entities_filter_query_string($listFilters);

$entitiesExportCsvUrl = base_url('admin/entities_export.php' . entities_filter_query_string($listFilters, ['format' => 'csv']));

$entitiesExportExcelUrl = base_url('admin/entities_export.php' . entities_filter_query_string($listFilters, ['format' => 'excel']));

$entitiesPrintUrl = base_url('admin/entities_print.php' . $entitiesFilterQuery);



require __DIR__ . '/../includes/header.php';

?>

<div class="page-header page-narrow">

    <div>

        <h1 class="page-title"><?= e(__('entities.title')) ?></h1>

        <p class="page-sub"><?= e(__('entities.subtitle')) ?></p>

    </div>

    <div class="page-header-actions">

        <span class="admin-count-badge"><i class="bi bi-briefcase me-1"></i><?= count($rows) ?></span>

        <a class="btn btn-outline-secondary btn-sm"
           href="<?= e($entitiesPrintUrl) ?>"
           target="_blank"
           rel="noopener"
           title="<?= e(__('action.print')) ?>">
            <i class="bi bi-printer"></i>
        </a>

        <a class="btn btn-outline-secondary btn-sm"
           href="<?= e($entitiesExportCsvUrl) ?>"
           title="<?= e(__('action.export_csv')) ?>">
            <i class="bi bi-filetype-csv"></i>
        </a>

        <a class="btn btn-outline-secondary btn-sm"
           href="<?= e($entitiesExportExcelUrl) ?>"
           title="<?= e(__('action.export_excel')) ?>">
            <i class="bi bi-file-earmark-excel"></i>
        </a>

        <?php if ($entityScopeReady): ?>

            <button type="button"

                    class="btn btn-kff btn-icon-add"

                    data-bs-toggle="modal"

                    data-bs-target="#entityCreateModal"

                    title="<?= e(__('entities.new')) ?>"

                    aria-label="<?= e(__('entities.new')) ?>"

                    data-bs-placement="top">

                <i class="bi bi-plus-lg"></i>

            </button>

        <?php endif; ?>

    </div>

</div>



<?php if (!$entityScopeReady): ?>

    <div class="alert alert-warning page-narrow">

        <i class="bi bi-exclamation-triangle me-1"></i><?= e(__('entities.migration_pending')) ?>

    </div>

<?php endif; ?>



<div class="entities-list-page page-narrow">

    <div class="card-panel" id="list-main" data-list-panel>

        <form method="get" class="entities-list-filters" id="entitiesFiltersForm">

            <div class="entities-list-filters-row">

                <div class="entities-list-filter-item entities-list-filter-search">

                    <label class="form-label" for="entitiesSearch"><?= e(__('entities.search')) ?></label>

                    <div class="input-group input-group-sm">

                        <span class="input-group-text bg-white"><i class="bi bi-search" aria-hidden="true"></i></span>

                        <input type="search"
                               name="q"
                               id="entitiesSearch"
                               class="form-control form-control-sm"
                               value="<?= e($listSearch) ?>"
                               placeholder="<?= e(__('entities.search')) ?>"
                               autocomplete="off">

                    </div>

                </div>

                <div class="entities-list-filter-item">

                    <label class="form-label" for="entitiesFilterScope"><?= e(__('entities.filter_scope')) ?></label>

                    <select name="scope" id="entitiesFilterScope" class="form-select form-select-sm entities-filter-auto">

                        <option value="" <?= ($listFilters['scope'] ?? '') === '' ? 'selected' : '' ?>><?= e(__('entities.filter_scope_all')) ?></option>

                        <option value="public" <?= ($listFilters['scope'] ?? '') === 'public' ? 'selected' : '' ?>><?= e(__('entities.filter_scope_public')) ?></option>

                        <option value="department" <?= ($listFilters['scope'] ?? '') === 'department' ? 'selected' : '' ?>><?= e(__('entities.filter_scope_department')) ?></option>

                    </select>

                </div>

                <?php if ($canManageAllEntities && $departments): ?>

                <div class="entities-list-filter-item">

                    <label class="form-label" for="entitiesFilterDepartment"><?= e(__('entities.filter_department')) ?></label>

                    <select name="department_id" id="entitiesFilterDepartment" class="form-select form-select-sm entities-filter-auto">

                        <option value="0" <?= (int) ($listFilters['department_id'] ?? 0) === 0 ? 'selected' : '' ?>><?= e(__('entities.filter_department_all')) ?></option>

                        <?php foreach ($departments as $department): ?>

                            <option value="<?= (int) $department['id'] ?>" <?= (int) ($listFilters['department_id'] ?? 0) === (int) $department['id'] ? 'selected' : '' ?>>

                                <?= e(department_display_name($department)) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <?php endif; ?>

                <div class="entities-list-filter-item">

                    <label class="form-label" for="entitiesFilterStatus"><?= e(__('entities.filter_status')) ?></label>

                    <select name="status" id="entitiesFilterStatus" class="form-select form-select-sm entities-filter-auto">

                        <option value="" <?= ($listFilters['status'] ?? '') === '' ? 'selected' : '' ?>><?= e(__('entities.filter_status_all')) ?></option>

                        <option value="active" <?= ($listFilters['status'] ?? '') === 'active' ? 'selected' : '' ?>><?= e(__('common.active')) ?></option>

                        <option value="inactive" <?= ($listFilters['status'] ?? '') === 'inactive' ? 'selected' : '' ?>><?= e(__('common.inactive')) ?></option>

                    </select>

                </div>

                <div class="entities-list-filter-item">

                    <label class="form-label" for="entitiesFilterContact"><?= e(__('entities.filter_contact')) ?></label>

                    <select name="contact" id="entitiesFilterContact" class="form-select form-select-sm entities-filter-auto">

                        <option value="" <?= ($listFilters['contact'] ?? '') === '' ? 'selected' : '' ?>><?= e(__('entities.filter_contact_all')) ?></option>

                        <option value="has_email" <?= ($listFilters['contact'] ?? '') === 'has_email' ? 'selected' : '' ?>><?= e(__('entities.filter_contact_has_email')) ?></option>

                        <option value="has_mobile" <?= ($listFilters['contact'] ?? '') === 'has_mobile' ? 'selected' : '' ?>><?= e(__('entities.filter_contact_has_mobile')) ?></option>

                        <option value="has_both" <?= ($listFilters['contact'] ?? '') === 'has_both' ? 'selected' : '' ?>><?= e(__('entities.filter_contact_has_both')) ?></option>

                        <option value="missing" <?= ($listFilters['contact'] ?? '') === 'missing' ? 'selected' : '' ?>><?= e(__('entities.filter_contact_missing')) ?></option>

                    </select>

                </div>

            </div>

        </form>

        <div class="list-panel-body">

        <?php if (!$rows): ?>

            <div class="admin-empty">

                <i class="bi bi-briefcase"></i>

                <p><?= e($listSearch !== '' ? __('common.no_results') : __('common.no_data')) ?></p>

                <?php if ($entityScopeReady && $listSearch === ''): ?>

                    <button type="button" class="btn btn-kff btn-sm mt-2" data-bs-toggle="modal" data-bs-target="#entityCreateModal">

                        <i class="bi bi-plus-lg me-1"></i><?= e(__('entities.new')) ?>

                    </button>

                <?php endif; ?>

            </div>

        <?php else: ?>

        <div class="table-responsive">

            <table class="table admin-table entities-table mb-0 align-middle" id="entitiesTable">

                <thead>

                    <tr>

                        <th class="entities-col-num">#</th>

                        <?php if ($isSystemAdmin): ?>
                        <th class="entities-col-sort"><?= e(__('entities.sort')) ?></th>
                        <?php endif; ?>

                        <th class="entities-col-letter"><?= e(__('entities.directed_to')) ?></th>

                        <th class="entities-col-name"><?= e(__('entities.section_basic')) ?></th>

                        <th class="entities-col-scope"><?= e(__('entities.scope')) ?></th>

                        <th class="entities-col-contact"><?= e(__('entities.contact')) ?></th>

                        <th class="entities-col-corr-count"><?= e(__('entities.corr_count')) ?></th>

                        <th class="entities-col-last-corr"><?= e(__('entities.last_corr')) ?></th>

                        <th class="entities-col-status"><?= e(__('common.status')) ?></th>

                        <th><?= e(__('common.actions')) ?></th>

                    </tr>

                </thead>

                <tbody style="counter-reset: entities-row <?= (int) $pagination['offset'] ?>;">

                <?php foreach ($rows as $r):

                    $isPublicScope = empty($r['department_id']);

                    $scopeLabel = $isPublicScope

                        ? __('entities.scope_public')

                        : department_display_name($r, 'department_name', 'department_name_en');

                    $scopeIcon = $isPublicScope ? 'bi-globe2' : 'bi-building';

                    $scopeModifier = $isPublicScope ? 'is-public' : 'is-department';

                    $scopeTooltip = $isPublicScope

                        ? __('entities.scope_public')

                        : __('entities.scope_department') . ': ' . ($scopeLabel !== '' ? $scopeLabel : __('common.none'));

                    $canManageRow = user_can_manage_entity($r, $user);
                    $entityDetail = [
                        'id' => (int) $r['id'],
                        'title_prefix' => (string) ($r['title_prefix'] ?? ''),
                        'directed_to' => (string) ($r['directed_to'] ?? ''),
                        'honorific' => (string) ($r['honorific'] ?? ''),
                        'job_title' => (string) ($r['job_title'] ?? ''),
                        'name_ar' => (string) ($r['name_ar'] ?? ''),
                        'scope' => $scopeTooltip,
                        'mobile' => (string) ($r['mobile'] ?? ''),
                        'email' => (string) ($r['email'] ?? ''),
                        'phone' => (string) ($r['phone'] ?? ''),
                        'address' => (string) ($r['address'] ?? ''),
                        'location' => (string) ($r['location'] ?? ''),
                        'notes' => (string) ($r['notes'] ?? ''),
                        'sort_order' => (string) ((int) ($r['sort_order'] ?? 0)),
                        'corr_count' => (string) ((int) ($r['corr_count'] ?? 0)),
                        'last_corr' => !empty($r['last_corr_date']) ? format_date($r['last_corr_date']) : '',
                        'status' => (int) ($r['is_active'] ?? 0)
                            ? __('common.active')
                            : __('common.inactive'),
                        'can_manage' => $canManageRow,
                        'edit_url' => base_url('admin/entities_edit.php?id=' . (int) $r['id']),
                        'print_url' => base_url('admin/entities_card_print.php?id=' . (int) $r['id']),
                    ];
                ?>

                    <tr class="entities-row-clickable"
                        tabindex="0"
                        role="button"
                        data-entity="<?= e(json_encode($entityDetail, JSON_UNESCAPED_UNICODE)) ?>"
                        title="<?= e(__('entities.details_hint')) ?>">

                        <td class="entities-col-num"></td>

                        <?php if ($isSystemAdmin): ?>
                        <td class="entities-col-sort text-center"><?= (int) ($r['sort_order'] ?? 0) ?></td>
                        <?php endif; ?>

                        <td class="entities-col-letter">
                            <?php if (!empty($r['title_prefix'])): ?>
                                <div class="admin-entity-sub"><?= e((string) $r['title_prefix']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($r['directed_to'])): ?>
                                <div class="entities-letter-line"><?= e((string) $r['directed_to']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($r['honorific'])): ?>
                                <div class="admin-entity-sub entities-honorific"><?= e((string) $r['honorific']) ?></div>
                            <?php endif; ?>
                        </td>

                        <td class="entities-col-name">

                            <?php if (!empty($r['job_title'])): ?>

                                <div class="admin-entity-sub"><?= e($r['job_title']) ?></div>

                            <?php endif; ?>

                            <div class="admin-entity-name"><?= e($r['name_ar']) ?></div>

                        </td>

                        <td class="entities-col-scope">
                            <span class="entities-scope-icon <?= $scopeModifier ?>"
                                  data-bs-toggle="tooltip" data-bs-placement="top"
                                  title="<?= e($scopeTooltip) ?>">
                                <i class="bi <?= $scopeIcon ?>" aria-hidden="true"></i>
                                <span class="visually-hidden"><?= e($scopeTooltip) ?></span>
                            </span>
                        </td>

                        <td class="entities-col-contact">

                            <?php if (!empty($r['mobile']) || !empty($r['email']) || (!empty($r['phone']) && $r['phone'] !== ($r['mobile'] ?? ''))): ?>

                                <div class="entities-contact-chips">

                                    <?php if (!empty($r['mobile'])): ?>

                                        <span class="entities-contact-chip" dir="ltr" title="<?= e(__('entities.mobile')) ?>">

                                            <i class="bi bi-phone"></i><?= e($r['mobile']) ?>

                                        </span>

                                    <?php endif; ?>

                                    <?php if (!empty($r['email'])): ?>

                                        <span class="entities-contact-chip" dir="ltr" title="<?= e(__('entities.email')) ?>">

                                            <i class="bi bi-envelope"></i><?= e($r['email']) ?>

                                        </span>

                                    <?php endif; ?>

                                    <?php if (!empty($r['phone']) && $r['phone'] !== ($r['mobile'] ?? '')): ?>

                                        <span class="entities-contact-chip" dir="ltr" title="<?= e(__('entities.phone_landline')) ?>">

                                            <i class="bi bi-telephone"></i><?= e($r['phone']) ?>

                                        </span>

                                    <?php endif; ?>

                                </div>

                            <?php endif; ?>

                        </td>

                        <td class="entities-col-corr-count text-center">

                            <?= (int) ($r['corr_count'] ?? 0) > 0 ? (int) $r['corr_count'] : '' ?>

                        </td>

                        <td class="entities-col-last-corr">

                            <?php if (!empty($r['last_corr_date'])): ?>

                                <?= e(format_date($r['last_corr_date'])) ?>

                            <?php endif; ?>

                        </td>

                        <td class="entities-col-status">

                            <?= (int)$r['is_active']

                                ? '<span class="badge text-bg-success">' . e(__('common.active')) . '</span>'

                                : '<span class="badge text-bg-secondary">' . e(__('common.inactive')) . '</span>' ?>

                        </td>

                        <td class="text-end text-nowrap">

                            <div class="admin-actions">

                                <a class="btn btn-sm btn-outline-secondary"
                                   href="<?= e(base_url('admin/entities_card_print.php?id=' . (int) $r['id'])) ?>"
                                   target="_blank"
                                   rel="noopener"
                                   title="<?= e(__('entities.print_card')) ?>">
                                    <i class="bi bi-printer"></i>
                                </a>

                            <?php if ($canManageRow): ?>

                                <a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('admin/entities_edit.php?id=' . (int) $r['id'])) ?>" title="<?= e(__('action.edit')) ?>">

                                    <i class="bi bi-pencil"></i>

                                </a>

                                <form method="post" class="d-inline" data-confirm="<?= e(__('entities.confirm_delete')) ?>">

                                    <?= csrf_field() ?>

                                    <input type="hidden" name="action" value="delete">

                                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">

                                    <button class="btn btn-sm btn-outline-danger" type="submit" title="<?= e(__('action.delete')) ?>">

                                        <i class="bi bi-trash"></i>

                                    </button>

                                </form>

                            <?php endif; ?>

                            </div>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

        <?php $pageParam = 'page'; require __DIR__ . '/../includes/list_pagination.php'; ?>

        <?php endif; ?>

        </div>

    </div>

</div>



<div class="modal fade" id="entityDetailModal" tabindex="-1" aria-labelledby="entityDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="entityDetailModalLabel">
                    <i class="bi bi-building me-1"></i><span id="entityDetailTitle"><?= e(__('entities.details')) ?></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(__('action.cancel')) ?>"></button>
            </div>
            <div class="modal-body" id="entityDetailBody"></div>
            <div class="modal-footer">
                <a id="entityDetailPrint" class="btn btn-outline-secondary" href="#" target="_blank" rel="noopener">
                    <i class="bi bi-printer me-1"></i><?= e(__('entities.print_card')) ?>
                </a>
                <a id="entityDetailEdit" class="btn btn-outline-primary d-none" href="#">
                    <i class="bi bi-pencil me-1"></i><?= e(__('action.edit')) ?>
                </a>
                <button type="button" class="btn btn-kff" data-bs-dismiss="modal"><?= e(__('action.close')) ?></button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var filterForm = document.getElementById('entitiesFiltersForm');
    if (filterForm) {
        filterForm.querySelectorAll('.entities-filter-auto').forEach(function (el) {
            el.addEventListener('change', function () {
                filterForm.submit();
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var detailModalEl = document.getElementById('entityDetailModal');
        if (!detailModalEl || !window.bootstrap) {
            return;
        }
        var detailModal = bootstrap.Modal.getOrCreateInstance(detailModalEl);
        var detailTitle = document.getElementById('entityDetailTitle');
        var detailBody = document.getElementById('entityDetailBody');
        var detailEdit = document.getElementById('entityDetailEdit');
        var detailPrint = document.getElementById('entityDetailPrint');
        var fieldLabels = <?= json_encode([
            'title_prefix' => __('entities.title_prefix'),
            'directed_to' => __('entities.directed_to'),
            'honorific' => __('entities.honorific'),
            'job_title' => __('entities.job_title'),
            'name_ar' => __('entities.section_basic'),
            'scope' => __('entities.scope'),
            'mobile' => __('entities.mobile'),
            'email' => __('entities.email'),
            'phone' => __('entities.phone_landline'),
            'address' => __('entities.address'),
            'location' => __('entities.location'),
            'notes' => __('entities.notes'),
            'sort_order' => __('entities.sort'),
            'corr_count' => __('entities.corr_count'),
            'last_corr' => __('entities.last_corr'),
            'status' => __('common.status'),
        ], JSON_UNESCAPED_UNICODE) ?>;
        var showSort = <?= $isSystemAdmin ? 'true' : 'false' ?>;
        var emptyLabel = <?= json_encode(__('common.none'), JSON_UNESCAPED_UNICODE) ?>;

        function esc(text) {
            return String(text == null ? '' : text)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function renderDetail(data) {
            var order = [
                'title_prefix', 'directed_to', 'honorific', 'job_title', 'name_ar', 'scope',
                'mobile', 'email', 'phone', 'address', 'location', 'notes'
            ];
            if (showSort) {
                order.push('sort_order');
            }
            order.push('corr_count', 'last_corr', 'status');

            var html = '<dl class="entity-detail-list mb-0">';
            order.forEach(function (key) {
                var value = String(data[key] == null ? '' : data[key]).trim();
                if (value === '' && key !== 'status' && key !== 'corr_count') {
                    return;
                }
                if (key === 'corr_count' && value === '0') {
                    value = '';
                }
                var display;
                if (key === 'location' && value !== '') {
                    display = '<a href="' + esc(value) + '" target="_blank" rel="noopener noreferrer" dir="ltr">' +
                        '<i class="bi bi-geo-alt me-1"></i>' + esc(value) + '</a>';
                } else if (key === 'mobile' || key === 'email' || key === 'phone') {
                    display = value !== '' ? '<span dir="ltr">' + esc(value) + '</span>' : esc(emptyLabel);
                } else {
                    display = value !== '' ? esc(value) : esc(emptyLabel);
                }
                html += '<div class="entity-detail-row">' +
                    '<dt>' + esc(fieldLabels[key] || key) + '</dt>' +
                    '<dd>' + display + '</dd>' +
                    '</div>';
            });
            html += '</dl>';
            detailBody.innerHTML = html;
            detailTitle.textContent = data.name_ar || <?= json_encode(__('entities.details'), JSON_UNESCAPED_UNICODE) ?>;
            if (detailPrint) {
                detailPrint.href = data.print_url || '#';
            }
            if (detailEdit) {
                if (data.can_manage && data.edit_url) {
                    detailEdit.href = data.edit_url;
                    detailEdit.classList.remove('d-none');
                } else {
                    detailEdit.classList.add('d-none');
                }
            }
            detailModal.show();
        }

        function openFromRow(row) {
            var raw = row.getAttribute('data-entity') || '';
            if (!raw) {
                return;
            }
            try {
                renderDetail(JSON.parse(raw));
            } catch (err) {
                return;
            }
        }

        document.querySelectorAll('.entities-row-clickable').forEach(function (row) {
            row.addEventListener('click', function (event) {
                if (event.target.closest('a, button, form, input, select, textarea, .admin-actions, .entities-scope-icon')) {
                    return;
                }
                openFromRow(row);
            });
            row.addEventListener('keydown', function (event) {
                if (event.key !== 'Enter' && event.key !== ' ') {
                    return;
                }
                if (event.target !== row) {
                    return;
                }
                event.preventDefault();
                openFromRow(row);
            });
        });
    });
})();
</script>



<?php if ($entityScopeReady): ?>

<div class="modal fade" id="entityCreateModal" tabindex="-1" aria-labelledby="entityCreateModalLabel" aria-hidden="true">

    <div class="modal-dialog modal-dialog-scrollable entity-create-modal-dialog">

        <div class="modal-content">

            <form method="post" id="entity-create-form" class="admin-form">

                <?= csrf_field() ?>

                <input type="hidden" name="action" value="create">

                <div class="modal-header">

                    <h5 class="modal-title" id="entityCreateModalLabel">

                        <i class="bi bi-plus-circle me-1"></i><?= e(__('entities.new')) ?>

                    </h5>

                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(__('action.cancel')) ?>"></button>

                </div>

                <div class="modal-body entity-create-modal-body entity-create-modal-body-compact">

                    <?php if ($createError !== ''): ?>

                        <div class="alert alert-danger mb-3">

                            <i class="bi bi-exclamation-circle me-1"></i><?= e($createError) ?>

                        </div>

                    <?php endif; ?>

                    <?php

                    $values = $createValues;

                    $entityFormPrefix = 'create_';

                    $entityFormInModal = true;

                    $entityFormModalCompact = true;

                    require __DIR__ . '/_entity_form.php';

                    ?>

                </div>

                <div class="modal-footer">

                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(__('action.cancel')) ?></button>

                    <button type="submit" class="btn btn-kff">

                        <i class="bi bi-check2 me-1"></i><?= e(__('action.save')) ?>

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>

<script>

(function () {

    var modalEl = document.getElementById('entityCreateModal');

    var searchEl = document.getElementById('entitiesSearch');

    var filtersForm = document.getElementById('entitiesFiltersForm');



    // البحث يُنفَّذ على الخادم، فنؤجّل الإرسال حتى يتوقف المستخدم عن الكتابة.

    if (searchEl && filtersForm) {

        var searchTimer = null;

        searchEl.addEventListener('input', function () {

            window.clearTimeout(searchTimer);

            searchTimer = window.setTimeout(function () {

                filtersForm.submit();

            }, 450);

        });

    }



    if (!modalEl) return;



    <?php if ($openCreateModal): ?>

    document.addEventListener('DOMContentLoaded', function () {

        if (window.bootstrap && modalEl) {

            bootstrap.Modal.getOrCreateInstance(modalEl).show();

        }

    });

    <?php endif; ?>



    modalEl.addEventListener('hidden.bs.modal', function () {

        var form = document.getElementById('entity-create-form');

        if (!form || <?= $createError !== '' ? 'true' : 'false' ?>) return;

        form.reset();

        var wrap = document.getElementById('create_entityHonorificCustomWrap');

        if (wrap) wrap.classList.add('d-none');

    });

})();

</script>

<?php endif; ?>



<?php require __DIR__ . '/../includes/footer.php'; ?>

