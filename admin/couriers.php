<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/couriers.php';
$user = require_permission('entities.manage');
ensure_outgoing_courier_schema();
$pageTitle = __('nav.couriers');

$editId = (int) ($_GET['id'] ?? 0);
$editing = $editId ? outgoing_courier_get($editId) : null;
if ($editId && !$editing) {
    flash('danger', __('couriers.not_found'));
    redirect(base_url('admin/couriers.php'));
}

$error = '';
$modalMode = 'create';
$values = [
    'id' => 0,
    'name' => '',
    'phone' => '',
    'is_active' => '1',
];
$openModal = false;

if ($editing) {
    $modalMode = 'edit';
    $values = [
        'id' => (int) $editing['id'],
        'name' => (string) $editing['name'],
        'phone' => (string) $editing['phone'],
        'is_active' => (string) ($editing['is_active'] ?? 1),
    ];
    $openModal = true;
} elseif (isset($_GET['new'])) {
    $openModal = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'delete') {
    verify_csrf();
    $delId = (int) ($_POST['id'] ?? 0);
    if ($delId <= 0 || !outgoing_courier_get($delId)) {
        flash('danger', __('couriers.not_found'));
    } else {
        outgoing_courier_delete($delId);
        flash('success', __('couriers.deleted'));
    }
    redirect(base_url('admin/couriers.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $postedId = (int) ($_POST['id'] ?? 0);
    $values = [
        'id' => $postedId,
        'name' => (string) ($_POST['name'] ?? ''),
        'phone' => (string) ($_POST['phone'] ?? ''),
        'is_active' => !empty($_POST['is_active']) ? '1' : '0',
    ];
    $modalMode = $postedId > 0 ? 'edit' : 'create';
    $openModal = true;
    try {
        $existing = $postedId > 0 ? outgoing_courier_get($postedId) : null;
        if ($postedId > 0 && !$existing) {
            throw new InvalidArgumentException(__('couriers.not_found'));
        }
        outgoing_courier_save($values, $existing ? $postedId : null);
        flash('success', $existing ? __('couriers.updated') : __('couriers.created'));
        redirect(base_url('admin/couriers.php'));
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$rows = outgoing_couriers_list(false);
$listUrl = base_url('admin/couriers.php');

require __DIR__ . '/../includes/header.php';
?>
<div class="page-header page-narrow">
    <div>
        <h1 class="page-title"><?= e(__('couriers.title')) ?></h1>
        <p class="page-sub"><?= e(__('couriers.subtitle')) ?></p>
    </div>
    <div class="page-header-actions">
        <button type="button"
                class="btn btn-kff btn-icon btn-icon-add"
                title="<?= e(__('couriers.new')) ?>"
                aria-label="<?= e(__('couriers.new')) ?>"
                data-bs-toggle="modal"
                data-bs-target="#courierFormModal"
                data-courier-mode="create">
            <i class="bi bi-plus-lg" aria-hidden="true"></i>
        </button>
    </div>
</div>

<div class="card-panel page-narrow">
    <div class="card-header d-flex justify-content-between">
        <span><?= e(__('couriers.title')) ?></span>
        <span class="badge text-bg-light text-dark"><?= count($rows) ?></span>
    </div>
    <?php if (!$rows): ?>
        <div class="admin-empty"><i class="bi bi-person-badge"></i><p><?= e(__('common.no_data')) ?></p></div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table admin-table mb-0 align-middle">
            <thead>
                <tr>
                    <th><?= e(__('corr.courier_name')) ?></th>
                    <th><?= e(__('corr.courier_phone')) ?></th>
                    <th><?= e(__('corr.status')) ?></th>
                    <th class="text-end"><?= e(__('common.actions')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <?php
                $payload = [
                    'id' => (int) $r['id'],
                    'name' => (string) $r['name'],
                    'phone' => (string) $r['phone'],
                    'is_active' => (int) ($r['is_active'] ?? 1),
                ];
                ?>
                <tr>
                    <td class="fw-semibold"><?= e($r['name']) ?></td>
                    <td dir="ltr"><?= e($r['phone'] !== '' ? $r['phone'] : '—') ?></td>
                    <td>
                        <?php if ((int) ($r['is_active'] ?? 1) === 1): ?>
                            <span class="badge text-bg-success"><?= e(__('common.active')) ?></span>
                        <?php else: ?>
                            <span class="badge text-bg-secondary"><?= e(__('common.inactive')) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end text-nowrap">
                        <div class="admin-actions">
                            <button type="button"
                                    class="btn btn-sm btn-outline-primary btn-courier-edit"
                                    title="<?= e(__('action.edit')) ?>"
                                    data-bs-toggle="modal"
                                    data-bs-target="#courierFormModal"
                                    data-courier="<?= e(json_encode($payload, JSON_UNESCAPED_UNICODE)) ?>">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form method="post" class="d-inline" data-confirm="<?= e(__('couriers.confirm_delete')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" type="submit" title="<?= e(__('action.delete')) ?>">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<div class="modal fade" id="courierFormModal" tabindex="-1" aria-labelledby="courierFormModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" class="admin-form" id="courierForm">
                <?= csrf_field() ?>
                <input type="hidden" name="id" id="courier_form_id" value="<?= (int) $values['id'] ?>">
                <div class="modal-header">
                    <h5 class="modal-title" id="courierFormModalLabel">
                        <i class="bi <?= $modalMode === 'edit' ? 'bi-pencil-square' : 'bi-plus-circle' ?> me-1" id="courierFormIcon"></i>
                        <span id="courierFormTitle"><?= e($modalMode === 'edit' ? __('couriers.edit') : __('couriers.new')) ?></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(__('action.close')) ?>"></button>
                </div>
                <div class="modal-body">
                    <?php if ($error !== ''): ?>
                        <div class="alert alert-danger" id="courierFormError">
                            <i class="bi bi-exclamation-circle me-1"></i><?= e($error) ?>
                        </div>
                    <?php endif; ?>
                    <div class="admin-form-section admin-form-section-last mb-0">
                        <div class="admin-form-section-title"><i class="bi bi-person-badge me-1"></i><?= e(__('corr.courier')) ?></div>
                        <p class="admin-form-hint<?= $modalMode === 'edit' ? '' : ' d-none' ?>" id="courierSnapshotHint"><?= e(__('couriers.snapshot_hint')) ?></p>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label" for="courier_admin_name"><?= e(__('corr.courier_name')) ?> *</label>
                                <input type="text" name="name" id="courier_admin_name" class="form-control" required value="<?= e($values['name']) ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="courier_admin_phone"><?= e(__('corr.courier_phone')) ?></label>
                                <input type="text" name="phone" id="courier_admin_phone" class="form-control" dir="ltr" value="<?= e($values['phone']) ?>" inputmode="tel" autocomplete="tel">
                            </div>
                            <div class="col-12<?= $modalMode === 'edit' ? '' : ' d-none' ?>" id="courierActiveWrap">
                                <input type="hidden" name="is_active" id="courier_active_hidden" value="0"<?= $modalMode === 'edit' ? '' : ' disabled' ?>>
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" name="is_active" id="courier_admin_active" value="1"<?= $values['is_active'] === '1' ? ' checked' : '' ?><?= $modalMode === 'edit' ? '' : ' disabled' ?>>
                                    <label class="form-check-label" for="courier_admin_active"><?= e(__('common.active')) ?></label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(__('action.cancel')) ?></button>
                    <button type="submit" class="btn btn-kff" id="courier_save_btn">
                        <i class="bi bi-check2 me-1"></i><span id="courier_save_label"><?= e($modalMode === 'edit' ? __('action.save_changes') : __('action.save')) ?></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    var modalEl = document.getElementById('courierFormModal');
    var listUrl = <?= json_encode($listUrl, JSON_UNESCAPED_UNICODE) ?>;
    var openOnLoad = <?= $openModal ? 'true' : 'false' ?>;
    var initialMode = <?= json_encode($modalMode, JSON_UNESCAPED_UNICODE) ?>;
    var initial = <?= json_encode([
        'id' => (int) $values['id'],
        'name' => (string) $values['name'],
        'phone' => (string) $values['phone'],
        'is_active' => (int) $values['is_active'],
    ], JSON_UNESCAPED_UNICODE) ?>;
    var titleCreate = <?= json_encode(__('couriers.new'), JSON_UNESCAPED_UNICODE) ?>;
    var titleEdit = <?= json_encode(__('couriers.edit'), JSON_UNESCAPED_UNICODE) ?>;
    var saveCreate = <?= json_encode(__('action.save'), JSON_UNESCAPED_UNICODE) ?>;
    var saveEdit = <?= json_encode(__('action.save_changes'), JSON_UNESCAPED_UNICODE) ?>;

    function parseCourier(raw) {
        if (!raw) {
            return null;
        }
        if (typeof raw === 'object') {
            return raw;
        }
        try {
            return JSON.parse(String(raw));
        } catch (err) {
            return null;
        }
    }

    function setSaveLabel(text) {
        var btn = document.getElementById('courier_save_btn');
        var label = document.getElementById('courier_save_label');
        if (label) {
            label.textContent = text;
        }
        if (!btn) {
            return;
        }
        if (btn.classList.contains('dms-iconified')) {
            btn.classList.remove('dms-iconified');
            btn.innerHTML = '<i class="bi bi-check2 me-1" aria-hidden="true"></i><span id="courier_save_label">' + text + '</span>';
            if (window.dmsIconifyButtons) {
                window.dmsIconifyButtons(btn);
            }
        }
    }

    function fillCourier(data, mode) {
        data = data || {};
        var isEdit = mode === 'edit';
        var idEl = document.getElementById('courier_form_id');
        var nameEl = document.getElementById('courier_admin_name');
        var phoneEl = document.getElementById('courier_admin_phone');
        var wrap = document.getElementById('courierActiveWrap');
        var hidden = document.getElementById('courier_active_hidden');
        var active = document.getElementById('courier_admin_active');
        var title = document.getElementById('courierFormTitle');
        var icon = document.getElementById('courierFormIcon');
        if (idEl) {
            idEl.value = isEdit ? String(data.id || '') : '';
        }
        if (nameEl) {
            nameEl.value = data.name || '';
        }
        if (phoneEl) {
            phoneEl.value = data.phone || '';
        }
        if (wrap) {
            wrap.classList.toggle('d-none', !isEdit);
        }
        if (hidden) {
            hidden.disabled = !isEdit;
        }
        if (active) {
            active.disabled = !isEdit;
            active.checked = isEdit ? Number(data.is_active) === 1 : true;
        }
        if (title) {
            title.textContent = isEdit ? titleEdit : titleCreate;
        }
        if (icon) {
            icon.className = (isEdit ? 'bi bi-pencil-square' : 'bi bi-plus-circle') + ' me-1';
        }
        var hint = document.getElementById('courierSnapshotHint');
        if (hint) {
            hint.classList.toggle('d-none', !isEdit);
        }
        setSaveLabel(isEdit ? saveEdit : saveCreate);
    }

    if (modalEl) {
        modalEl.addEventListener('show.bs.modal', function (event) {
            var btn = event.relatedTarget;
            if (!btn) {
                return;
            }
            var err = document.getElementById('courierFormError');
            if (err) {
                err.remove();
            }
            if (btn.classList.contains('btn-courier-edit')) {
                fillCourier(parseCourier(btn.getAttribute('data-courier')), 'edit');
                return;
            }
            fillCourier({ id: 0, name: '', phone: '', is_active: 1 }, 'create');
        });
        modalEl.addEventListener('hidden.bs.modal', function () {
            if (window.history && window.history.replaceState) {
                window.history.replaceState(null, '', listUrl);
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (!openOnLoad || !modalEl || !window.bootstrap) {
            return;
        }
        fillCourier(initial, initialMode);
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    });
})();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
