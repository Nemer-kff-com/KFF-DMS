<?php
$item = $item ?? [];
$categories = $categories ?? (function_exists('categories_list') ? categories_list() : []);
$v = static function (string $key, $default = '') use ($item) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST[$key])) {
        return $_POST[$key];
    }
    return $item[$key] ?? $default;
};
$linkedSelected = (int) $v('correspondence_id');
$linkedPayload = null;
if ($linkedSelected > 0 && function_exists('get_correspondence')) {
    $linkedCorr = get_correspondence($linkedSelected);
    $viewer = $user ?? (function_exists('current_user') ? current_user() : null);
    if (is_array($linkedCorr) && (!$viewer || !function_exists('user_can_access_correspondence') || user_can_access_correspondence($linkedCorr, $viewer))) {
        $linkedPayload = [
            'id' => (int) $linkedCorr['id'],
            'ref' => function_exists('format_ref_number') ? format_ref_number($linkedCorr['ref_number'] ?? '') : (string) ($linkedCorr['ref_number'] ?? ''),
            'subject' => (string) ($linkedCorr['subject'] ?? ''),
        ];
    } else {
        $linkedSelected = 0;
    }
}
$hasLinked = $linkedSelected > 0 && $linkedPayload;
?>
<div class="admin-form-section">
    <div class="admin-form-section-title"><?= e(__('archive.section_basic')) ?></div>
    <div class="row g-3">
        <?php if (!empty($item['archive_no'])): ?>
        <div class="col-md-4">
            <label class="form-label"><?= e(__('archive.number')) ?></label>
            <input type="text" class="form-control" dir="ltr" value="<?= e((string) $item['archive_no']) ?>" readonly>
        </div>
        <?php endif; ?>
        <div class="col-md-<?= !empty($item['archive_no']) ? '8' : '6' ?>">
            <label class="form-label" for="archive_title"><?= e(__('archive.doc_title')) ?> *</label>
            <input type="text" name="title" id="archive_title" class="form-control" required value="<?= e((string) $v('title')) ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label" for="archive_title_en"><?= e(__('archive.doc_title_en')) ?></label>
            <input type="text" name="title_en" id="archive_title_en" class="form-control" dir="ltr" value="<?= e((string)$v('title_en')) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="archive_doc_type"><?= e(__('archive.doc_type')) ?> *</label>
            <select name="doc_type" id="archive_doc_type" class="form-select" required>
                <?php
                $selectedDocType = (string) $v('doc_type', 'other');
                ?>
                <?php foreach (archive_doc_types_list() as $t): ?>
                    <option value="<?= e($t['code']) ?>" <?= $selectedDocType === $t['code'] ? 'selected' : '' ?>><?= e(bilingual_label($t)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="archive_category"><?= e(__('corr.category')) ?></label>
            <select name="category_id" id="archive_category" class="form-select">
                <option value=""><?= e(__('common.none')) ?></option>
                <?php $selectedCategory = (string) $v('category_id', $item === [] && $categories ? (string) $categories[0]['id'] : ''); ?>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= (int) $c['id'] ?>" <?= $selectedCategory === (string) $c['id'] ? 'selected' : '' ?>><?= e(category_display_name($c)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="archive_doc_date"><?= e(__('archive.doc_date')) ?></label>
            <input type="date" name="doc_date" id="archive_doc_date" class="form-control" value="<?= e((string)$v('doc_date')) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="archive_doc_year"><?= e(__('archive.doc_year')) ?></label>
            <input type="text" name="doc_year" id="archive_doc_year" class="form-control" dir="ltr" maxlength="4" inputmode="numeric" value="<?= e((string) $v('doc_year')) ?>" placeholder="<?= e(__('archive.doc_year_ph')) ?>">
            <div class="form-text"><?= e(__('archive.doc_year_hint')) ?></div>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="archive_old_ref"><?= e(__('archive.old_ref')) ?></label>
            <input type="text" name="old_ref" id="archive_old_ref" class="form-control" dir="ltr" value="<?= e((string)$v('old_ref')) ?>">
        </div>
        <div class="col-md-8">
            <label class="form-label" for="archive_party"><?= e(__('archive.party')) ?></label>
            <input type="text" name="party_name" id="archive_party" class="form-control" value="<?= e((string)$v('party_name')) ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label" for="archive_department"><?= e(__('corr.department')) ?></label>
            <select name="department_id" id="archive_department" class="form-select">
                <option value=""><?= e(__('common.none')) ?></option>
                <?php foreach ($departments as $d): ?>
                    <option value="<?= (int)$d['id'] ?>" <?= (string)$v('department_id', function_exists('user_primary_department_id') ? (string) user_primary_department_id($user) : ($user['department_id'] ?? '')) === (string)$d['id'] ? 'selected' : '' ?>><?= e(department_display_name($d)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="archive_confidentiality"><?= e(__('corr.confidentiality')) ?></label>
            <select name="confidentiality" id="archive_confidentiality" class="form-select">
                <?php foreach (confidentiality_levels_list() as $c): ?>
                    <option value="<?= e($c['code']) ?>" <?= (string)$v('confidentiality', 'normal') === $c['code'] ? 'selected' : '' ?>><?= e(bilingual_label($c)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php
        $locationValue = $_SERVER['REQUEST_METHOD'] === 'POST'
            ? (string) ($_POST['location'] ?? '')
            : archive_location_label($item);
        ?>
        <div class="col-12">
            <label class="form-label" for="archive_location"><?= e(__('archive.location')) ?></label>
            <input type="text" name="location" id="archive_location" class="form-control" value="<?= e($locationValue) ?>" placeholder="<?= e(__('archive.location_ph')) ?>">
            <div class="form-text"><?= e(__('archive.location_hint')) ?></div>
        </div>
    </div>
</div>

<?php
$extraVals = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['extra']) && is_array($_POST['extra'])) {
    $extraVals = archive_decode_extra($_POST['extra']);
} else {
    $extraVals = archive_item_extra(is_array($item) ? $item : []);
}
$currentTypeForExtra = (string) $v('doc_type', 'other');
?>
<div class="admin-form-section" id="archive_type_fields_wrap">
    <div class="admin-form-section-title"><?= e(__('archive.section_type_fields')) ?></div>
    <p class="form-text mb-3"><?= e(__('archive.section_type_hint')) ?></p>
    <div class="row g-3">
        <?php foreach (archive_extra_field_defs() as $xf): ?>
            <?php
            $xfKey = $xf['key'];
            $xfVal = (string) ($extraVals[$xfKey] ?? '');
            $xfTypes = implode(' ', $xf['types']);
            $showNow = in_array($currentTypeForExtra, $xf['types'], true);
            $fid = 'archive_xf_' . $xfKey;
            ?>
            <div class="<?= e($xf['col']) ?> archive-xfield" data-types="<?= e($xfTypes) ?>" <?= $showNow ? '' : 'hidden' ?>>
                <label class="form-label" for="<?= e($fid) ?>"><?= e(__($xf['label'])) ?></label>
                <?php if (($xf['type'] ?? '') === 'textarea'): ?>
                    <textarea name="extra[<?= e($xfKey) ?>]" id="<?= e($fid) ?>" class="form-control" rows="<?= (int) ($xf['rows'] ?? 3) ?>"><?= e($xfVal) ?></textarea>
                <?php elseif (($xf['type'] ?? '') === 'lookup' && !empty($xf['lookup'])): ?>
                    <?php $opts = archive_lookups((string) $xf['lookup']); ?>
                    <?php if ($opts): ?>
                    <select name="extra[<?= e($xfKey) ?>]" id="<?= e($fid) ?>" class="form-select">
                        <option value=""><?= e(__('common.none')) ?></option>
                        <?php foreach ($opts as $opt): ?>
                            <?php
                            $optAr = trim((string) ($opt['name_ar'] ?? ''));
                            $optEn = trim((string) ($opt['name_en'] ?? ''));
                            $sel = ($xfVal !== '' && ($xfVal === (string) $opt['id'] || $xfVal === $optAr || $xfVal === $optEn));
                            ?>
                            <option value="<?= e((string) $opt['id']) ?>" <?= $sel ? 'selected' : '' ?>><?= e(bilingual_label(['name' => $optAr, 'name_ar' => $optAr, 'name_en' => $optEn])) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php else: ?>
                    <input type="text" name="extra[<?= e($xfKey) ?>]" id="<?= e($fid) ?>" class="form-control" value="<?= e($xfVal) ?>">
                    <?php endif; ?>
                <?php else: ?>
                    <input type="text" name="extra[<?= e($xfKey) ?>]" id="<?= e($fid) ?>" class="form-control" value="<?= e($xfVal) ?>">
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var typeSel = document.getElementById('archive_doc_type');
  var fields = document.querySelectorAll('.archive-xfield');
  var wrap = document.getElementById('archive_type_fields_wrap');
  function sync() {
    var t = typeSel ? String(typeSel.value || '') : '';
    var any = false;
    fields.forEach(function (el) {
      var types = (el.getAttribute('data-types') || '').split(/\s+/);
      var on = types.indexOf(t) !== -1;
      el.hidden = !on;
      if (on) any = true;
      el.querySelectorAll('input, select, textarea').forEach(function (ctl) {
        ctl.disabled = !on;
      });
    });
    if (wrap) wrap.hidden = !any;
  }
  if (typeSel) typeSel.addEventListener('change', sync);
  sync();
});
</script>

<div class="admin-form-section<?= empty($includeFiles) ? ' admin-form-section-last' : '' ?>">
    <div class="admin-form-section-title"><?= e(__('archive.section_extra')) ?></div>
    <div class="row g-3">
        <div class="col-md-8">
            <label class="form-label" for="archive_keywords"><?= e(__('archive.keywords')) ?></label>
            <input type="text" name="keywords" id="archive_keywords" class="form-control" value="<?= e((string)$v('keywords')) ?>" placeholder="<?= e(__('archive.keywords_ph')) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label"><?= e(__('archive.link_corr')) ?></label>
            <input type="hidden" name="correspondence_id" id="archive_correspondence_id" value="<?= $hasLinked ? (int) $linkedSelected : '' ?>">
            <div class="entity-picker-control">
                <div class="entity-picked-stack">
                    <div class="entity-picked-box" id="archive_linked_box" role="button" tabindex="0">
                        <span class="entity-picker-sub" id="archive_linked_ref" <?= $hasLinked ? '' : 'hidden' ?> dir="ltr"><?= e((string) ($linkedPayload['ref'] ?? '')) ?></span>
                        <span class="entity-picker-name<?= $hasLinked ? '' : ' is-placeholder' ?>" id="archive_linked_subject"><?= e($hasLinked ? (string) $linkedPayload['subject'] : __('archive.link_picker_placeholder')) ?></span>
                    </div>
                </div>
                <div class="entity-picker-btns">
                    <button type="button" class="btn btn-kff entity-picker-btn" id="archive_linked_pick_btn" title="<?= e(__('archive.link_picker_open')) ?>">
                        <i class="bi bi-search"></i>
                    </button>
                    <button type="button" class="btn btn-outline-secondary entity-picker-btn" id="archive_linked_clear_btn" title="<?= e(__('archive.link_picker_clear')) ?>" <?= $hasLinked ? '' : 'hidden' ?>>
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>
            <div class="form-text"><?= e(__('archive.link_corr_hint')) ?></div>
        </div>
        <div class="col-12">
            <label class="form-label" for="archive_notes"><?= e(__('corr.notes')) ?></label>
            <textarea name="notes" id="archive_notes" class="form-control" rows="3"><?= e((string)$v('notes')) ?></textarea>
        </div>
    </div>
</div>

<div class="modal fade" id="archiveLinkedPickerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= e(__('archive.link_corr')) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(__('action.cancel')) ?>"></button>
            </div>
            <div class="modal-body">
                <input type="search" class="form-control mb-3" id="archiveLinkedPickerSearch" placeholder="<?= e(__('archive.link_picker_placeholder')) ?>" autocomplete="off">
                <div id="archiveLinkedPickerLoading" class="text-muted small mb-2" hidden><?= e(__('common.loading')) ?></div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th><?= e(__('corr.number')) ?></th>
                                <th><?= e(__('corr.subject')) ?></th>
                                <th><?= e(__('corr.party')) ?></th>
                                <th><?= e(__('corr.type')) ?></th>
                            </tr>
                        </thead>
                        <tbody id="archiveLinkedPickerRows"></tbody>
                    </table>
                </div>
                <p class="text-muted small mt-2 mb-0" id="archiveLinkedPickerEmpty" hidden><?= e(__('archive.link_picker_empty')) ?></p>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var hidden = document.getElementById('archive_correspondence_id');
  var pickBtn = document.getElementById('archive_linked_pick_btn');
  var clearBtn = document.getElementById('archive_linked_clear_btn');
  var box = document.getElementById('archive_linked_box');
  var refEl = document.getElementById('archive_linked_ref');
  var subjectEl = document.getElementById('archive_linked_subject');
  var modalEl = document.getElementById('archiveLinkedPickerModal');
  var search = document.getElementById('archiveLinkedPickerSearch');
  var rowsBody = document.getElementById('archiveLinkedPickerRows');
  var emptyBox = document.getElementById('archiveLinkedPickerEmpty');
  var loadingBox = document.getElementById('archiveLinkedPickerLoading');
  var placeholder = <?= json_encode(__('archive.link_picker_placeholder'), JSON_UNESCAPED_UNICODE) ?>;
  var endpoint = <?= json_encode(base_url('api/archive_corr_search.php'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  var timer = null;
  var token = 0;
  function modal() {
    return (window.bootstrap && bootstrap.Modal) ? bootstrap.Modal.getOrCreateInstance(modalEl) : null;
  }
  function apply(item) {
    item = item || {};
    hidden.value = item.id ? String(item.id) : '';
    if (refEl) {
      refEl.textContent = item.ref || '';
      refEl.hidden = !item.ref;
    }
    if (subjectEl) {
      subjectEl.textContent = item.subject || placeholder;
      subjectEl.classList.toggle('is-placeholder', !item.subject);
    }
    if (clearBtn) clearBtn.hidden = !item.id;
  }
  function load(q) {
    var my = ++token;
    if (loadingBox) loadingBox.hidden = false;
    fetch(endpoint + '?q=' + encodeURIComponent(q || ''), { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (my !== token) return;
        if (loadingBox) loadingBox.hidden = true;
        rowsBody.innerHTML = '';
        var items = (data && data.items) ? data.items : [];
        if (!items.length) {
          emptyBox.hidden = false;
          return;
        }
        emptyBox.hidden = true;
        items.forEach(function (item) {
          var tr = document.createElement('tr');
          tr.style.cursor = 'pointer';
          tr.innerHTML = '<td dir="ltr"></td><td></td><td></td><td></td>';
          tr.children[0].textContent = item.ref || '';
          tr.children[1].textContent = item.subject || '';
          tr.children[2].textContent = item.party || '';
          tr.children[3].textContent = item.type || '';
          tr.addEventListener('click', function () {
            apply(item);
            var m = modal();
            if (m) m.hide();
          });
          rowsBody.appendChild(tr);
        });
      })
      .catch(function () {
        if (loadingBox) loadingBox.hidden = true;
      });
  }
  function openPicker() {
    var m = modal();
    if (m) m.show();
    load(search.value || '');
    setTimeout(function () { search.focus(); }, 200);
  }
  if (pickBtn) pickBtn.addEventListener('click', openPicker);
  if (box) box.addEventListener('click', openPicker);
  if (clearBtn) clearBtn.addEventListener('click', function (e) {
    e.stopPropagation();
    apply({});
  });
  if (search) search.addEventListener('input', function () {
    clearTimeout(timer);
    timer = setTimeout(function () { load(search.value); }, 250);
  });
});
</script>
                