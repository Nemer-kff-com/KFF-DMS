<?php
/**
 * مسميات القوائم — صفحة واحدة بتبويبات
 */
require_once __DIR__ . '/../config/auth.php';
$user = require_permission('lookups.manage');
ensure_lookups_schema();
if (function_exists('archive_ensure_extra_schema')) {
    archive_ensure_extra_schema();
}

$tabs = [
    'categories' => [
        'label' => __('nav.categories'),
        'icon' => 'bi-tags',
        'title' => __('categories.title'),
        'sub' => __('categories.subtitle'),
    ],
    'priorities' => [
        'label' => __('nav.priorities'),
        'icon' => 'bi-flag',
        'title' => __('lookups.priorities_title'),
        'sub' => __('lookups.priorities_sub'),
    ],
    'confidentiality' => [
        'label' => __('nav.confidentiality'),
        'icon' => 'bi-shield-lock',
        'title' => __('lookups.conf_title'),
        'sub' => __('lookups.conf_sub'),
    ],
    'statuses' => [
        'label' => __('nav.statuses'),
        'icon' => 'bi-diagram-3',
        'title' => __('lookups.statuses_title'),
        'sub' => __('lookups.statuses_sub'),
    ],
    'archive_types' => [
        'label' => __('nav.archive_types'),
        'icon' => 'bi-bookmark',
        'title' => __('lookups.archive_types_title'),
        'sub' => __('lookups.archive_types_sub'),
    ],
    'attachment_types' => [
        'label' => __('nav.attachment_types'),
        'icon' => 'bi-paperclip',
        'title' => __('lookups.attachment_types_title'),
        'sub' => __('lookups.attachment_types_sub'),
    ],
    'legacy_ref' => [
        'label' => __('lookups.legacy_ref'),
        'icon' => 'bi-table',
        'title' => __('lookups.legacy_ref_title'),
        'sub' => '',
    ],
];

$tab = (string) ($_GET['tab'] ?? 'categories');
if (!isset($tabs[$tab])) {
    $tab = 'categories';
}
$catalogKey = (string) ($_GET['list'] ?? 'countries');
$catalogDef = function_exists('archive_catalog_def') ? archive_catalog_def($catalogKey) : null;
if ($tab === 'legacy_ref' && !$catalogDef) {
    $catalogKey = 'countries';
    $catalogDef = archive_catalog_def('countries');
}
$meta = $tabs[$tab];
if ($tab === 'legacy_ref' && $catalogDef) {
    $meta['title'] = __($catalogDef['label']) . ' — ' . $catalogDef['table'];
    $meta['icon'] = $catalogDef['icon'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $postTab = (string) ($_POST['tab'] ?? $tab);
    if (!isset($tabs[$postTab])) {
        $postTab = 'categories';
    }
    $id = (int) ($_POST['id'] ?? 0);
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'delete' && $id > 0) {
        if ($postTab === 'categories') {
            try {
                db()->prepare('DELETE FROM `tblcategories` WHERE id=?')->execute([$id]);
                flash('success', __('categories.deleted'));
            } catch (PDOException $e) {
                flash('danger', __('categories.cannot_delete'));
            }
        } elseif ($postTab === 'archive_types') {
            $row = db()->prepare('SELECT code FROM `tblarchive_doc_types` WHERE id=?');
            $row->execute([$id]);
            $code = $row->fetchColumn();
            if ($code) {
                $used = db()->prepare('SELECT COUNT(*) FROM `tblarchive_items` WHERE doc_type=?');
                $used->execute([$code]);
                if ((int) $used->fetchColumn() > 0) {
                    flash('danger', __('lookups.cannot_delete_used'));
                } else {
                    db()->prepare('DELETE FROM `tblarchive_doc_types` WHERE id=?')->execute([$id]);
                    flash('success', __('lookups.deleted'));
                }
            }
        } elseif ($postTab === 'attachment_types') {
            if (attachment_type_in_use($id)) {
                flash('danger', __('lookups.cannot_delete_used_attachment'));
            } else {
                db()->prepare('DELETE FROM `tblattachment_types` WHERE id=?')->execute([$id]);
                flash('success', __('lookups.deleted'));
            }
        } elseif ($postTab === 'legacy_ref') {
            $delDef = archive_catalog_def((string) ($_POST['list'] ?? $catalogKey));
            $delTable = $delDef ? archive_catalog_table($delDef['key']) : null;
            if ($delTable && $id > 0) {
                db()->prepare("DELETE FROM `{$delTable}` WHERE id=?")->execute([$id]);
                flash('success', __('lookups.deleted'));
            }
            redirect(base_url('admin/lookups.php?tab=legacy_ref&list=' . urlencode((string) ($delDef['key'] ?? 'countries'))));
        } elseif (in_array($postTab, ['priorities', 'confidentiality', 'statuses'], true)) {
            $table = match ($postTab) {
                'priorities' => 'tblpriority_levels',
                'confidentiality' => 'tblconfidentiality_levels',
                default => 'tblstatus_levels',
            };
            $row = admin_row($table, $id);
            if (!$row) {
                flash('danger', __('common.not_found'));
            } elseif (!empty($row['is_system'])) {
                // العناصر النظامية لا تُحذف من القاعدة (مرتبطة بسير العمل) — يُكتفى بتعطيلها
                db()->prepare("UPDATE `{$table}` SET is_active = 0 WHERE id = ?")->execute([$id]);
                flash('success', __('lookups.deactivated_system'));
            } else {
                $code = (string) ($row['code'] ?? '');
                $used = 0;
                if ($code !== '') {
                    if ($postTab === 'priorities') {
                        $st = db()->prepare('SELECT COUNT(*) FROM `tblcorrespondence` WHERE priority = ?');
                        $st->execute([$code]);
                        $used = (int) $st->fetchColumn();
                    } elseif ($postTab === 'confidentiality') {
                        $st = db()->prepare('SELECT COUNT(*) FROM `tblcorrespondence` WHERE confidentiality = ?');
                        $st->execute([$code]);
                        $used = (int) $st->fetchColumn();
                        if ($used === 0 && dms_db_table_exists('tblarchive_items') && dms_db_column_exists('tblarchive_items', 'confidentiality')) {
                            $st = db()->prepare('SELECT COUNT(*) FROM `tblarchive_items` WHERE confidentiality = ?');
                            $st->execute([$code]);
                            $used = (int) $st->fetchColumn();
                        }
                    } else {
                        $st = db()->prepare('SELECT COUNT(*) FROM `tblcorrespondence` WHERE status = ?');
                        $st->execute([$code]);
                        $used = (int) $st->fetchColumn();
                    }
                }
                if ($used > 0) {
                    flash('danger', __('lookups.cannot_delete_used_level'));
                } else {
                    db()->prepare("DELETE FROM `{$table}` WHERE id=?")->execute([$id]);
                    flash('success', __('lookups.deleted'));
                }
            }
        }
    }

    redirect(base_url('admin/lookups.php?tab=' . urlencode($postTab) . ($postTab === 'legacy_ref' ? '&list=' . urlencode($catalogKey) : '')));
}

$pageTitle = __('lookups.hub_title');
$listUrl = static function (string $t) use ($catalogKey): string {
    $url = base_url('admin/lookups.php?tab=' . urlencode($t));
    if ($t === 'legacy_ref') {
        $url .= '&list=' . urlencode($catalogKey !== '' ? $catalogKey : 'countries');
    }
    return $url;
};

$categoryRows = $tab === 'categories' ? categories_list(false) : [];
$priorityRows = $tab === 'priorities' ? priority_levels_list(false) : [];
$confRows = $tab === 'confidentiality' ? confidentiality_levels_list(false) : [];
$statusRows = $tab === 'statuses' ? status_levels_list(false) : [];
$archiveTypeRows = $tab === 'archive_types' ? archive_doc_types_list(false) : [];
$attachmentTypeRows = $tab === 'attachment_types' ? attachment_types_list(false) : [];

$catalogRows = ($tab === 'legacy_ref' && $catalogDef) ? archive_lookups($catalogDef['key'], false) : [];
$catalogCountryOptions = [];
$catalogCountryNames = [];
if ($tab === 'legacy_ref' && ($catalogDef['key'] ?? '') === 'city') {
    foreach (archive_lookups('countries', false) as $cr) {
        $catalogCountryOptions[] = [
            'id' => (int) $cr['id'],
            'name' => bilingual_label(['name_ar' => $cr['name_ar'] ?? '', 'name_en' => $cr['name_en'] ?? '', 'name' => $cr['name_ar'] ?? '']),
        ];
        $catalogCountryNames[(int) $cr['id']] = bilingual_label(['name_ar' => $cr['name_ar'] ?? '', 'name_en' => $cr['name_en'] ?? '', 'name' => $cr['name_ar'] ?? '']);
    }
}

$count = match ($tab) {
    'categories' => count($categoryRows),
    'priorities' => count($priorityRows),
    'confidentiality' => count($confRows),
    'statuses' => count($statusRows),
    'archive_types' => count($archiveTypeRows),
    'legacy_ref' => count($catalogRows),
    default => count($attachmentTypeRows),
};

require __DIR__ . '/../includes/header.php';
?>
<div class="page-header page-narrow">
    <div>
        <h1 class="page-title"><?= e(__('lookups.hub_title')) ?></h1>
        <p class="page-sub"><?= e(__('lookups.hub_sub')) ?></p>
    </div>
    <div class="page-header-actions">
        <span class="admin-count-badge"><i class="bi <?= e($meta['icon']) ?> me-1"></i><?= (int) $count ?></span>
        <?php
        $addLabel = match ($tab) {
            'categories' => __('categories.new'),
            'priorities' => __('lookups.new_priority'),
            'confidentiality' => __('lookups.new_confidentiality'),
            'statuses' => __('lookups.new_status'),
            'archive_types' => __('lookups.new_type'),
            'legacy_ref' => __('lookups.new_catalog'),
            default => __('lookups.new_attachment_type'),
        };
        $addKind = $tab === 'legacy_ref' && $catalogDef ? 'catalog_' . $catalogDef['key'] : $tab;
        ?>
        <button type="button" class="btn btn-kff btn-icon-add"
                data-lookup-modal
                data-kind="<?= e($addKind) ?>"
                data-id="0"
                title="<?= e($addLabel) ?>"
                aria-label="<?= e($addLabel) ?>">
            <i class="bi bi-plus-lg"></i>
        </button>
    </div>
</div>

<div class="card-panel admin-lookups-hub page-narrow">
    <ul class="nav nav-tabs dms-import-tabs admin-lookups-tabs px-3 pt-2 flex-wrap" role="tablist">
        <?php foreach ($tabs as $key => $info): ?>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?= $tab === $key ? 'active' : '' ?>"
                   href="<?= e($listUrl($key)) ?>"
                   role="tab"
                   aria-selected="<?= $tab === $key ? 'true' : 'false' ?>">
                    <i class="bi <?= e($info['icon']) ?> me-1"></i><?= e($info['label']) ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>

    <div class="card-header d-flex align-items-center justify-content-between border-top-0">
        <div>
            <div class="fw-semibold"><?= e($meta['title']) ?></div>
            <?php if (trim((string) ($meta['sub'] ?? '')) !== ''): ?>
            <div class="admin-form-hint mb-0 mt-1"><?= e($meta['sub']) ?></div>
            <?php endif; ?>
        </div>
        <span class="badge text-bg-light text-dark"><?= (int) $count ?></span>
    </div>

    <?php if ($tab === 'legacy_ref'): ?>
        <div class="px-3 pt-3 pb-2">
            <div class="row g-2 align-items-end">
                <div class="col-md-5">
                    <label class="form-label mb-1" for="catalogListPick"><?= e(__('lookups.catalog_pick')) ?></label>
                    <select id="catalogListPick" class="form-select" onchange="if (this.value) location.href=this.value">
                        <?php foreach (archive_catalog_defs() as $ck => $cdef): ?>
                            <?php if (empty($cdef['admin'])) { continue; } ?>
                            <option value="<?= e(base_url('admin/lookups.php?tab=legacy_ref&list=' . urlencode($ck))) ?>" <?= $catalogKey === $ck ? 'selected' : '' ?>>
                                <?= e(__($cdef['label'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-7">
                    <label class="form-label mb-1" for="catalogFilter"><?= e(__('lookups.catalog_search')) ?></label>
                    <input type="search" id="catalogFilter" class="form-control" placeholder="<?= e(__('lookups.catalog_search_ph')) ?>" autocomplete="off">
                </div>
            </div>
        </div>
        <?php if (!$catalogRows): ?>
            <div class="admin-empty">
                <i class="bi <?= e($meta['icon']) ?>"></i>
                <p><?= e(__('common.no_data')) ?></p>
            </div>
        <?php else: ?>
            <?php
            $showIso = ($catalogDef['key'] ?? '') === 'countries';
            $showCityCountry = ($catalogDef['key'] ?? '') === 'city';
            ?>
            <div class="table-responsive">
                <table class="table admin-table mb-0 align-middle" id="catalogRowsTable">
                    <thead>
                        <tr>
                            <th><?= e(__('lookups.id')) ?></th>
                            <th><?= e(__('lookups.sort')) ?></th>
                            <th><?= e(__('lookups.name_ar')) ?></th>
                            <th><?= e(__('lookups.name_en')) ?></th>
                            <?php if ($showIso): ?><th dir="ltr">ISO</th><?php endif; ?>
                            <?php if ($showCityCountry): ?><th><?= e(__('archive.xf.country')) ?></th><?php endif; ?>
                            <th><?= e(__('common.status')) ?></th>
                            <th class="text-end"><?= e(__('common.actions')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($catalogRows as $r): ?>
                        <tr>
                            <td dir="ltr"><?= (int) ($r['id'] ?? 0) ?></td>
                            <td><?= (int) ($r['sort_order'] ?? 0) ?></td>
                            <td><div class="admin-entity-name"><?= e($r['name_ar']) ?></div></td>
                            <td dir="ltr"><?= e($r['name_en'] ?: __('common.none')) ?></td>
                            <?php if ($showIso): ?><td dir="ltr"><?= e((string) ($r['iso2'] ?? '')) ?></td><?php endif; ?>
                            <?php if ($showCityCountry): ?>
                                <td><?= e($catalogCountryNames[(int) ($r['country_id'] ?? 0)] ?? __('common.none')) ?></td>
                            <?php endif; ?>
                            <td>
                                <?= (int) ($r['is_active'] ?? 1)
                                    ? '<span class="badge text-bg-success">' . e(__('common.active')) . '</span>'
                                    : '<span class="badge text-bg-secondary">' . e(__('common.inactive')) . '</span>' ?>
                            </td>
                            <td class="text-end text-nowrap">
                                <div class="admin-actions">
                                    <button type="button" class="btn btn-sm btn-outline-primary"
                                            data-lookup-modal
                                            data-kind="catalog_<?= e($catalogDef['key']) ?>"
                                            data-id="<?= (int) $r['id'] ?>"
                                            data-sort="<?= (int) ($r['sort_order'] ?? 0) ?>"
                                            data-name-ar="<?= e($r['name_ar']) ?>"
                                            data-name-en="<?= e((string) ($r['name_en'] ?? '')) ?>"
                                            data-iso2="<?= e((string) ($r['iso2'] ?? '')) ?>"
                                            data-region-id="<?= e((string) ($r['region_id'] ?? '')) ?>"
                                            data-country-id="<?= (int) ($r['country_id'] ?? 0) ?>"
                                            data-active="<?= (int) ($r['is_active'] ?? 1) ?>"
                                            title="<?= e(__('action.edit')) ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="post" class="d-inline" data-confirm="<?= e(__('common.confirm_delete')) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="tab" value="legacy_ref">
                                        <input type="hidden" name="list" value="<?= e($catalogDef['key']) ?>">
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
            <script>
            document.getElementById('catalogFilter')?.addEventListener('input', function () {
              var q = (this.value || '').toLowerCase();
              document.querySelectorAll('#catalogRowsTable tbody tr').forEach(function (tr) {
                tr.hidden = q !== '' && tr.textContent.toLowerCase().indexOf(q) === -1;
              });
            });
            </script>
        <?php endif; ?>

    <?php elseif ($tab === 'categories'): ?>
        <?php if (!$categoryRows): ?>
            <div class="admin-empty">
                <i class="bi bi-tags"></i>
                <p><?= e(__('common.no_data')) ?></p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table admin-table mb-0 align-middle">
                    <thead>
                        <tr>
                            <th><?= e(__('lookups.sort')) ?></th>
                            <th><?= e(__('lookups.name_ar')) ?></th>
                            <th><?= e(__('lookups.name_en')) ?></th>
                            <th><?= e(__('common.status')) ?></th>
                            <th class="text-end"><?= e(__('common.actions')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($categoryRows as $r): ?>
                        <tr>
                            <td><?= (int) ($r['sort_order'] ?? 0) ?></td>
                            <td><div class="admin-entity-name"><?= e($r['name']) ?></div></td>
                            <td dir="ltr"><?= e($r['name_en'] ?: __('common.none')) ?></td>
                            <td>
                                <?= (int) $r['is_active']
                                    ? '<span class="badge text-bg-success">' . e(__('common.active')) . '</span>'
                                    : '<span class="badge text-bg-secondary">' . e(__('common.inactive')) . '</span>' ?>
                            </td>
                            <td class="text-end text-nowrap">
                                <div class="admin-actions">
                                    <button type="button" class="btn btn-sm btn-outline-primary"
                                            data-lookup-modal
                                            data-kind="categories"
                                            data-id="<?= (int) $r['id'] ?>"
                                            data-sort="<?= (int) ($r['sort_order'] ?? 0) ?>"
                                            data-name-ar="<?= e($r['name']) ?>"
                                            data-name-en="<?= e((string) ($r['name_en'] ?? '')) ?>"
                                            data-active="<?= (int) $r['is_active'] ?>"
                                            title="<?= e(__('action.edit')) ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="post" class="d-inline" data-confirm="<?= e(__('common.confirm_delete')) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="tab" value="categories">
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

    <?php else:
        $lookupRows = match ($tab) {
            'priorities' => $priorityRows,
            'confidentiality' => $confRows,
            'statuses' => $statusRows,
            'archive_types' => $archiveTypeRows,
            default => $attachmentTypeRows,
        };
        $emptyIcon = $meta['icon'];
        ?>
        <?php if (!$lookupRows): ?>
            <div class="admin-empty">
                <i class="bi <?= e($emptyIcon) ?>"></i>
                <p><?= e(__('common.no_data')) ?></p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table admin-table mb-0 align-middle">
                    <thead>
                        <tr>
                            <th><?= e(__('lookups.sort')) ?></th>
                            <th><?= e(__('lookups.name_ar')) ?></th>
                            <th><?= e(__('lookups.name_en')) ?></th>
                            <th><?= e(__('lookups.code')) ?></th>
                            <th><?= e(__('common.status')) ?></th>
                            <th class="text-end"><?= e(__('common.actions')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($lookupRows as $r): ?>
                        <tr>
                            <td><?= (int) $r['sort_order'] ?></td>
                            <td><div class="admin-entity-name"><?= e($r['name_ar']) ?></div></td>
                            <td dir="ltr"><?= e($r['name_en']) ?></td>
                            <td dir="ltr"><code><?= e($r['code']) ?></code></td>
                            <td>
                                <?= (int) $r['is_active']
                                    ? '<span class="badge text-bg-success">' . e(__('common.active')) . '</span>'
                                    : '<span class="badge text-bg-secondary">' . e(__('common.inactive')) . '</span>' ?>
                            </td>
                            <td class="text-end text-nowrap">
                                <div class="admin-actions">
                                    <button type="button" class="btn btn-sm btn-outline-primary"
                                            data-lookup-modal
                                            data-kind="<?= e($tab) ?>"
                                            data-id="<?= (int) $r['id'] ?>"
                                            data-sort="<?= (int) $r['sort_order'] ?>"
                                            data-name-ar="<?= e($r['name_ar']) ?>"
                                            data-name-en="<?= e($r['name_en']) ?>"
                                            data-code="<?= e($r['code']) ?>"
                                            data-active="<?= (int) $r['is_active'] ?>"
                                            title="<?= e(__('action.edit')) ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="post" class="d-inline" data-confirm="<?= e(__('common.confirm_delete')) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="tab" value="<?= e($tab) ?>">
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
    <?php endif; ?>
</div>
<?php if ($tab === 'legacy_ref'): ?>
<script>window.dmsCatalogCountries = <?= json_encode($catalogCountryOptions, JSON_UNESCAPED_UNICODE) ?>;</script>
<?php endif; ?>
<?php require __DIR__ . '/../includes/lookup_form_modal.php'; ?>
<?php if (!empty($_GET['new'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
  if (window.dmsOpenLookupModal) {
    window.dmsOpenLookupModal({ kind: <?= json_encode($addKind, JSON_UNESCAPED_UNICODE) ?>, id: 0 });
  }
});
</script>
<?php elseif (!empty($_GET['edit'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var btn = document.querySelector('[data-lookup-modal][data-id="<?= (int) $_GET['edit'] ?>"]');
  if (btn) btn.click();
});
</script>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
