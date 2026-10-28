<?php
require_once __DIR__ . '/../config/auth.php';
$user = require_permission('admin.sidebar');
$pageTitle = __('nav.sidebar_menu');
$userId = (int) ($user['id'] ?? 0);
ensure_sidebar_menu_schema();
dms_sidebar_menu_bootstrap();

$openEditKey = trim((string) ($_GET['open_edit'] ?? $_GET['edit'] ?? ''));
$openGroupModal = isset($_GET['open_group']);
$groupOptions = dms_sidebar_menu_group_options();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'reset') {
            dms_sidebar_menu_reset($userId);
            flash('success', __('sidebar_menu.reset_done'));
            redirect(base_url('admin/sidebar_menu.php'));
        }

        if ($action === 'move') {
            $id = (string) ($_POST['id'] ?? '');
            $direction = (string) ($_POST['direction'] ?? '');
            if ($id !== '' && in_array($direction, ['up', 'down'], true) && dms_sidebar_menu_move($id, $direction, $userId)) {
                flash('success', __('sidebar_menu.saved'));
            }
            redirect(base_url('admin/sidebar_menu.php'));
        }

        if ($action === 'bulk_visibility') {
            $visible = ($_POST['visible'] ?? '') === '1';
            $ids = $_POST['ids'] ?? [];
            if (!is_array($ids)) {
                $ids = [];
            }
            dms_sidebar_menu_bulk_visible($ids, $visible, $userId);
            flash('success', __('sidebar_menu.saved'));
            redirect(base_url('admin/sidebar_menu.php'));
        }

        if ($action === 'toggle') {
            $id = (string) ($_POST['id'] ?? '');
            if ($id !== '') {
                $row = dms_sidebar_menu_row_by_key($id);
                if ($row) {
                    dms_sidebar_menu_set_visible($id, !(bool) (int) ($row['is_visible'] ?? 1), $userId);
                    flash('success', __('sidebar_menu.saved'));
                }
            }
            redirect(base_url('admin/sidebar_menu.php'));
        }

        if ($action === 'save_item') {
            $id = (string) ($_POST['id'] ?? '');
            if ($id === '') {
                throw new InvalidArgumentException(__('sidebar_menu.item_missing'));
            }
            dms_sidebar_menu_update_item($id, [
                'label_ar' => (string) ($_POST['label_ar'] ?? ''),
                'label_en' => (string) ($_POST['label_en'] ?? ''),
                'icon' => (string) ($_POST['icon'] ?? ''),
                'roles' => $_POST['roles'] ?? [],
            ], $userId);
            flash('success', __('sidebar_menu.saved'));
            redirect(base_url('admin/sidebar_menu.php'));
        }

        if ($action === 'create_group') {
            dms_sidebar_menu_create_group([
                'label_ar' => (string) ($_POST['label_ar'] ?? ''),
                'label_en' => (string) ($_POST['label_en'] ?? ''),
                'icon' => (string) ($_POST['icon'] ?? 'bi-folder2'),
            ], $userId);
            flash('success', __('sidebar_menu.group_created'));
            redirect(base_url('admin/sidebar_menu.php'));
        }

        if ($action === 'move_to') {
            $id = (string) ($_POST['id'] ?? '');
            $parent = trim((string) ($_POST['parent_key'] ?? ''));
            if ($id !== '') {
                dms_sidebar_menu_move_to_parent($id, $parent !== '' ? $parent : null, $userId);
                flash('success', __('sidebar_menu.saved'));
            }
            redirect(base_url('admin/sidebar_menu.php'));
        }

        if ($action === 'delete_group') {
            $id = (string) ($_POST['id'] ?? '');
            if ($id !== '') {
                dms_sidebar_menu_delete_group($id, $userId);
                flash('success', __('sidebar_menu.group_deleted'));
            }
            redirect(base_url('admin/sidebar_menu.php'));
        }
    } catch (Throwable $e) {
        flash('danger', $e->getMessage());
        if ($action === 'save_item') {
            $id = (string) ($_POST['id'] ?? '');
            redirect(base_url('admin/sidebar_menu.php?open_edit=' . urlencode($id)));
        }
        if ($action === 'create_group') {
            redirect(base_url('admin/sidebar_menu.php?open_group=1'));
        }
        redirect(base_url('admin/sidebar_menu.php'));
    }
}

$rows = dms_sidebar_menu_admin_rows();
$previewRoles = dms_sidebar_menu_preview_roles();
$roleMeta = dms_sidebar_menu_role_meta();
$openEditDefaults = $openEditKey !== '' ? dms_sidebar_menu_edit_defaults($openEditKey) : null;
if ($openEditKey !== '' && !$openEditDefaults) {
    $openEditKey = '';
}

require __DIR__ . '/../includes/header.php';
?>
<div class="sidebar-menu-admin">
<div class="page-header">
    <div>
        <h1 class="page-title"><?= e(__('sidebar_menu.title')) ?></h1>
        <p class="page-sub"><?= e(__('sidebar_menu.subtitle')) ?></p>
    </div>
    <div class="page-header-actions d-flex flex-wrap align-items-center gap-2">
        <span class="d-inline-flex" data-bs-toggle="tooltip" data-bs-placement="top" title="<?= e(__('sidebar_menu.add_group')) ?>">
            <button type="button"
                    class="btn btn-kff btn-icon-add"
                    aria-label="<?= e(__('sidebar_menu.add_group')) ?>"
                    data-bs-toggle="modal"
                    data-bs-target="#sidebarMenuGroupModal">
                <i class="bi bi-folder-plus"></i>
            </button>
        </span>
        <form method="post" class="d-inline" onsubmit="return confirm('<?= e(__('sidebar_menu.reset_confirm')) ?>');">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="reset">
            <button type="submit"
                    class="btn btn-outline-secondary btn-icon-add"
                    title="<?= e(__('sidebar_menu.reset')) ?>"
                    aria-label="<?= e(__('sidebar_menu.reset')) ?>"
                    data-bs-toggle="tooltip"
                    data-bs-placement="top">
                <i class="bi bi-arrow-counterclockwise"></i>
            </button>
        </form>
    </div>
</div>

<form id="sidebar-menu-bulk" method="post" class="d-none">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="bulk_visibility">
    <input type="hidden" name="visible" id="bulk-visible" value="1">
</form>

<div class="card-panel">
    <div class="card-header admin-index-toolbar">
        <div class="admin-index-summary">
            <span><i class="bi bi-table me-1"></i><?= e(__('sidebar_menu.list_title')) ?></span>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="submit"
                    form="sidebar-menu-bulk"
                    class="btn btn-sm btn-outline-success btn-icon-add"
                    title="<?= e(__('sidebar_menu.show_selected')) ?>"
                    aria-label="<?= e(__('sidebar_menu.show_selected')) ?>"
                    data-bs-toggle="tooltip"
                    data-bs-placement="top"
                    onclick="document.getElementById('bulk-visible').value='1';">
                <i class="bi bi-eye"></i>
            </button>
            <button type="submit"
                    form="sidebar-menu-bulk"
                    class="btn btn-sm btn-outline-secondary btn-icon-add"
                    title="<?= e(__('sidebar_menu.hide_selected')) ?>"
                    aria-label="<?= e(__('sidebar_menu.hide_selected')) ?>"
                    data-bs-toggle="tooltip"
                    data-bs-placement="top"
                    onclick="document.getElementById('bulk-visible').value='0';">
                <i class="bi bi-eye-slash"></i>
            </button>
        </div>
    </div>

    <div class="table-responsive sidebar-menu-table-wrap">
        <table class="table admin-table sidebar-menu-table mb-0 align-middle">
            <thead>
                <tr>
                    <th style="width:2.5rem">
                        <input type="checkbox" class="form-check-input" id="check-all" aria-label="<?= e(__('sidebar_menu.select_all')) ?>">
                    </th>
                    <th><?= e(__('sidebar_menu.item')) ?></th>
                    <th><?= e(__('common.status')) ?></th>
                    <?php foreach ($previewRoles as $role): ?>
                        <?php $meta = $roleMeta[$role] ?? ['icon' => 'bi-person', 'tone' => $role]; ?>
                        <th class="sidebar-role-th" title="<?= e(__('role.' . $role)) ?>">
                            <span class="sidebar-role-head">
                                <i class="bi <?= e($meta['icon']) ?>"></i>
                                <span class="sidebar-role-label"><?= e(__('sidebar_menu.role_col_' . $role)) ?></span>
                            </span>
                        </th>
                    <?php endforeach; ?>
                    <th class="text-end"><?= e(__('common.actions')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php
                    $rowClass = [];
                    if (!$row['visible']) {
                        $rowClass[] = 'sidebar-menu-row-hidden';
                    }
                    if ($row['item_type'] === 'group' && $row['depth'] === 0) {
                        $rowClass[] = 'sidebar-menu-row-group';
                    } elseif ($row['depth'] > 0) {
                        $rowClass[] = 'sidebar-menu-row-child';
                    }
                    ?>
                    <tr class="<?= e(implode(' ', $rowClass)) ?>">
                        <td>
                            <input type="checkbox" class="form-check-input row-check" form="sidebar-menu-bulk" name="ids[]" value="<?= e($row['id']) ?>">
                        </td>
                        <td>
                            <div class="sidebar-menu-item-cell" style="padding-inline-start: <?= (int) $row['depth'] ?>rem">
                                <span class="sidebar-menu-item-icon"><i class="bi <?= e($row['icon']) ?>"></i></span>
                                <div>
                                    <div class="sidebar-menu-item-name">
                                        <?= e($row['label']) ?>
                                        <?php if ($row['item_type'] === 'group' && $row['depth'] === 0): ?>
                                            <span class="badge text-bg-light text-dark ms-1"><?= e(__('sidebar_menu.group_badge')) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="sidebar-menu-item-key"><?= e($row['id']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php if ($row['visible']): ?>
                                <span class="badge text-bg-success"><?= e(__('sidebar_menu.visible')) ?></span>
                            <?php else: ?>
                                <span class="badge text-bg-secondary"><?= e(__('sidebar_menu.hidden')) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($row['scopes'])): ?>
                                <div class="sidebar-scope-list">
                                    <?php foreach ($row['scopes'] as $scope): ?>
                                        <span class="sidebar-scope-badge is-<?= e($scope) ?>"><?= e(dms_sidebar_menu_scope_label($scope)) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <?php foreach ($previewRoles as $role): ?>
                            <td class="text-center">
                                <?php if (!empty($row['role_visibility'][$role])): ?>
                                    <span class="sidebar-role-pill is-yes" title="<?= e(__('sidebar_menu.sees_item') . ' — ' . __('role.' . $role)) ?>">
                                        <i class="bi bi-check2" aria-hidden="true"></i>
                                        <span class="visually-hidden"><?= e(__('role.' . $role)) ?></span>
                                    </span>
                                <?php else: ?>
                                    <span class="sidebar-role-pill is-no" title="<?= e(__('sidebar_menu.does_not_see') . ' — ' . __('role.' . $role)) ?>">
                                        <i class="bi bi-x" aria-hidden="true"></i>
                                        <span class="visually-hidden"><?= e(__('role.' . $role)) ?></span>
                                    </span>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                        <td class="text-end text-nowrap">
                            <div class="sidebar-menu-actions">
                                <button type="button"
                                        class="btn btn-sm btn-outline-primary btn-sidebar-edit"
                                        title="<?= e(__('action.edit')) ?>"
                                        data-bs-toggle="modal"
                                        data-bs-target="#sidebarMenuEditModal"
                                        data-key="<?= e($row['id']) ?>"
                                        data-label-ar="<?= e($row['label_ar']) ?>"
                                        data-label-en="<?= e($row['label_en']) ?>"
                                        data-icon="<?= e($row['icon']) ?>"
                                        data-roles="<?= e(implode(',', $row['roles'] ?? [])) ?>"
                                        data-title="<?= e($row['label']) ?>">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <form method="post" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="move">
                                    <input type="hidden" name="id" value="<?= e($row['id']) ?>">
                                    <input type="hidden" name="direction" value="up">
                                    <button type="submit" class="btn btn-sm btn-outline-secondary" title="<?= e(__('sidebar_menu.move_up')) ?>">
                                        <i class="bi bi-arrow-up"></i>
                                    </button>
                                </form>
                                <form method="post" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="move">
                                    <input type="hidden" name="id" value="<?= e($row['id']) ?>">
                                    <input type="hidden" name="direction" value="down">
                                    <button type="submit" class="btn btn-sm btn-outline-secondary" title="<?= e(__('sidebar_menu.move_down')) ?>">
                                        <i class="bi bi-arrow-down"></i>
                                    </button>
                                </form>
                                <form method="post" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?= e($row['id']) ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-secondary" title="<?= e($row['visible'] ? __('sidebar_menu.hide') : __('sidebar_menu.show')) ?>">
                                        <i class="bi <?= $row['visible'] ? 'bi-eye-slash' : 'bi-eye' ?>"></i>
                                    </button>
                                </form>
                                <?php if ($row['item_type'] === 'link'): ?>
                                    <form method="post" class="d-inline-flex align-items-center">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="move_to">
                                        <input type="hidden" name="id" value="<?= e($row['id']) ?>">
                                        <select name="parent_key" class="form-select form-select-sm sidebar-menu-move-select" title="<?= e(__('sidebar_menu.move_group')) ?>" onchange="this.form.submit()">
                                            <option value=""><?= e(__('sidebar_menu.top_level')) ?></option>
                                            <?php foreach ($groupOptions as $opt): ?>
                                                <option value="<?= e($opt['id']) ?>" <?= ($row['group'] ?? '') === $opt['id'] ? 'selected' : '' ?>>
                                                    <?= e($opt['label']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                <?php elseif (!$row['is_system']): ?>
                                    <form method="post" class="d-inline" onsubmit="return confirm('<?= e(__('sidebar_menu.delete_group_confirm')) ?>');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_group">
                                        <input type="hidden" name="id" value="<?= e($row['id']) ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="<?= e(__('action.delete')) ?>">
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
</div>
</div>

<div class="modal fade" id="sidebarMenuEditModal" tabindex="-1" aria-labelledby="sidebarMenuEditModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" id="sidebar-menu-edit-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_item">
                <input type="hidden" name="id" id="edit-menu-key" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="sidebarMenuEditModalLabel">
                        <i class="bi bi-pencil-square me-1"></i><?= e(__('sidebar_menu.edit_item')) ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(__('action.cancel')) ?>"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small" id="edit-menu-subtitle"></p>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label"><?= e(__('lookups.name_ar')) ?></label>
                            <input type="text" name="label_ar" id="edit-label-ar" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label"><?= e(__('lookups.name_en')) ?></label>
                            <input type="text" name="label_en" id="edit-label-en" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label"><?= e(__('sidebar_menu.icon')) ?></label>
                            <input type="text" name="icon" id="edit-icon" class="form-control" placeholder="bi-speedometer2">
                            <div class="form-text"><?= e(__('sidebar_menu.edit_hint')) ?></div>
                        </div>
                        <div class="col-12">
                            <div class="fw-semibold mb-2"><?= e(__('sidebar_menu.visible_for_roles')) ?></div>
                            <p class="text-muted small mb-2"><?= e(__('sidebar_menu.visible_for_roles_hint')) ?></p>
                            <div class="d-flex flex-column gap-2" id="edit-roles-list">
                                <?php foreach ($previewRoles as $role): ?>
                                    <div class="d-flex align-items-start gap-2">
                                        <input class="form-check-input m-0 flex-shrink-0 edit-role-check"
                                               type="checkbox"
                                               id="edit-role-<?= e($role) ?>"
                                               name="roles[]"
                                               value="<?= e($role) ?>">
                                        <label class="form-check-label mb-0" for="edit-role-<?= e($role) ?>">
                                            <?= e(__('role.' . $role)) ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(__('action.cancel')) ?></button>
                    <button type="submit" class="btn btn-kff">
                        <i class="bi bi-check-lg me-1"></i><?= e(__('action.save')) ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="sidebarMenuGroupModal" tabindex="-1" aria-labelledby="sidebarMenuGroupModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" id="sidebar-menu-group-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create_group">
                <div class="modal-header">
                    <h5 class="modal-title" id="sidebarMenuGroupModalLabel">
                        <i class="bi bi-folder-plus me-1"></i><?= e(__('sidebar_menu.add_group')) ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(__('action.cancel')) ?>"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label"><?= e(__('lookups.name_ar')) ?></label>
                            <input type="text" name="label_ar" id="group-label-ar" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label"><?= e(__('lookups.name_en')) ?></label>
                            <input type="text" name="label_en" id="group-label-en" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label"><?= e(__('sidebar_menu.icon')) ?></label>
                            <input type="text" name="icon" id="group-icon" class="form-control" value="bi-folder2" placeholder="bi-folder2">
                            <div class="form-text"><?= e(__('sidebar_menu.edit_hint')) ?></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(__('action.cancel')) ?></button>
                    <button type="submit" class="btn btn-kff">
                        <i class="bi bi-check-lg me-1"></i><?= e(__('action.add')) ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    var all = document.getElementById('check-all');
    var rows = document.querySelectorAll('.row-check');
    if (all) {
        all.addEventListener('change', function () {
            rows.forEach(function (cb) { cb.checked = all.checked; });
        });
    }

    var editModal = document.getElementById('sidebarMenuEditModal');

    function setEditRoles(rolesCsv) {
        var selected = {};
        String(rolesCsv || '').split(',').forEach(function (r) {
            r = String(r || '').trim();
            if (r) {
                selected[r] = true;
            }
        });
        document.querySelectorAll('.edit-role-check').forEach(function (cb) {
            cb.checked = !!selected[cb.value];
        });
    }

    function fillEditForm(key, labelAr, labelEn, icon, title, rolesCsv) {
        document.getElementById('edit-menu-key').value = key || '';
        document.getElementById('edit-label-ar').value = labelAr || '';
        document.getElementById('edit-label-en').value = labelEn || '';
        document.getElementById('edit-icon').value = icon || '';
        var sub = document.getElementById('edit-menu-subtitle');
        if (sub) {
            sub.textContent = title || '';
        }
        setEditRoles(rolesCsv);
    }

    if (editModal) {
        editModal.addEventListener('show.bs.modal', function (event) {
            var btn = event.relatedTarget;
            if (!btn || !btn.classList.contains('btn-sidebar-edit')) {
                return;
            }
            fillEditForm(
                btn.getAttribute('data-key') || '',
                btn.getAttribute('data-label-ar') || '',
                btn.getAttribute('data-label-en') || '',
                btn.getAttribute('data-icon') || '',
                btn.getAttribute('data-title') || '',
                btn.getAttribute('data-roles') || ''
            );
        });
    }

    <?php if ($openEditKey !== '' && $openEditDefaults): ?>
    fillEditForm(
        <?= json_encode($openEditKey, JSON_UNESCAPED_UNICODE) ?>,
        <?= json_encode($openEditDefaults['label_ar'], JSON_UNESCAPED_UNICODE) ?>,
        <?= json_encode($openEditDefaults['label_en'], JSON_UNESCAPED_UNICODE) ?>,
        <?= json_encode($openEditDefaults['icon'], JSON_UNESCAPED_UNICODE) ?>,
        <?= json_encode(dms_sidebar_menu_item_label($openEditKey), JSON_UNESCAPED_UNICODE) ?>,
        <?= json_encode(implode(',', $openEditDefaults['roles'] ?? []), JSON_UNESCAPED_UNICODE) ?>
    );
    if (editModal && window.bootstrap) {
        bootstrap.Modal.getOrCreateInstance(editModal).show();
    }
    <?php endif; ?>

    <?php if ($openGroupModal): ?>
    var groupModal = document.getElementById('sidebarMenuGroupModal');
    if (groupModal && window.bootstrap) {
        bootstrap.Modal.getOrCreateInstance(groupModal).show();
    }
    <?php endif; ?>
})();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
