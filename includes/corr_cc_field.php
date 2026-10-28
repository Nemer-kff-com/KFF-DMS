<?php
/**
 * منتقي «للاطلاع» بأسلوب اختيار الجهة (مربع عرض + زر بحث، ونافذة قائمة طولية: بحث، تصفية بالقسم، صور صغيرة)
 * — إنشاء الوارد/الداخلية ونموذج إعادة الإرسال بعد الإعادة.
 * يتطلب: $ccType، واختيارياً $ccSelectedIds و $ccExcludeUserId و $ccInputId و $ccSmall
 */
$ccType = (string) ($ccType ?? 'incoming');
$ccInputId = (string) ($ccInputId ?? 'ccUserSelect');
$ccSmall = !empty($ccSmall);
$ccEnabled = corr_cc_enabled_for($ccType);
$ccExcludeUserId = (int) ($ccExcludeUserId ?? 0);
if (!isset($ccSelectedIds) || !is_array($ccSelectedIds)) {
    $ccSelectedIds = $_SERVER['REQUEST_METHOD'] === 'POST'
        ? corr_cc_ids_from_request($_POST, $ccExcludeUserId)
        : [];
}
$ccSelectedIds = array_map('intval', $ccSelectedIds);
$ccOptions = corr_cc_user_options($ccExcludeUserId);
$ccGroups = [];
foreach ($ccOptions as $opt) {
    $key = $opt['dept_id'] > 0 ? (string) $opt['dept_id'] : '0';
    if (!isset($ccGroups[$key])) {
        $ccGroups[$key] = ['name' => $opt['dept'] !== '' ? $opt['dept'] : __('cc.no_dept'), 'users' => []];
    }
    $ccGroups[$key]['users'][] = $opt;
}
if (isset($ccGroups['0'])) {
    $noDept = $ccGroups['0'];
    unset($ccGroups['0']);
    $ccGroups['0'] = $noDept;
}
$ccSorted = $ccOptions;
$ccCollator = class_exists('Collator') ? new Collator(dms_lang_code() === 'en' ? 'en' : 'ar') : null;
$ccSortKey = static function (string $name): string {
    return mb_strtolower(strtr(trim($name), ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا', 'ة' => 'ه', 'ى' => 'ي']));
};
usort($ccSorted, static function (array $a, array $b) use ($ccCollator, $ccSortKey): int {
    $ka = $ccSortKey($a['name']);
    $kb = $ccSortKey($b['name']);
    return $ccCollator ? $ccCollator->compare($ka, $kb) : strcmp($ka, $kb);
});
$ccModalId = $ccInputId . 'Modal';
$ccInitials = static function (string $name): string {
    $parts = preg_split('/\s+/u', trim($name)) ?: [];
    $out = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        $out .= mb_substr($p, 0, 1);
    }
    return $out !== '' ? $out : '?';
};
$ccAvatar = static function (array $opt, string $cls) use ($ccInitials): string {
    if ($opt['photo'] !== '') {
        return '<img src="' . e($opt['photo']) . '" alt="" class="cc-avatar ' . e($cls) . '" loading="lazy">';
    }
    return '<span class="cc-avatar is-initials ' . e($cls) . '" aria-hidden="true">' . e($ccInitials($opt['name'])) . '</span>';
};
?>
<div class="cc-picker<?= $ccSmall ? ' is-sm' : '' ?>" data-cc-picker data-modal="<?= e($ccModalId) ?>"
     data-empty-label="<?= e(__('cc.pick_user')) ?>"
     data-count-label="<?= e(__('cc.selected_count')) ?>"
     data-remove-label="<?= e(__('action.delete')) ?>">
    <input type="hidden" name="cc_present" value="1">
    <div data-cc-inputs hidden>
        <?php foreach ($ccSelectedIds as $uid): ?>
            <input type="hidden" name="cc_users[]" value="<?= (int) $uid ?>">
        <?php endforeach; ?>
    </div>
    <?php if ($ccEnabled): ?>
    <div class="entity-action-row">
        <div class="entity-picked-box" id="<?= e($ccInputId) ?>" data-cc-open role="button" tabindex="0"
             aria-haspopup="dialog" title="<?= e(__('cc.modal_title')) ?>" aria-label="<?= e(__('cc.field')) ?>">
            <span class="entity-picker-name is-placeholder" data-cc-summary><?= e(__('cc.pick_user')) ?></span>
        </div>
        <div class="entity-picker-btns">
            <button type="button" class="btn btn-kff entity-picker-btn" data-cc-open title="<?= e(__('cc.modal_title')) ?>">
                <i class="bi bi-search"></i>
            </button>
            <button type="button" class="btn btn-outline-secondary entity-picker-btn" data-cc-reset title="<?= e(__('cc.clear')) ?>" hidden>
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
    </div>
    <?php else: ?>
    <div class="entity-action-row" tabindex="0" data-bs-toggle="tooltip" title="<?= e(__('cc.requires_approval')) ?>">
        <div class="entity-picked-box is-disabled" id="<?= e($ccInputId) ?>" aria-disabled="true" aria-label="<?= e(__('cc.field')) ?>">
            <span class="entity-picker-name is-placeholder" data-cc-summary><?= e(__('cc.pick_user')) ?></span>
        </div>
        <div class="entity-picker-btns">
            <button type="button" class="btn btn-kff entity-picker-btn" disabled><i class="bi bi-search"></i></button>
        </div>
    </div>
    <?php endif; ?>
    <div class="cc-chip-list" data-cc-list></div>

    <?php if ($ccEnabled): ?>
    <div class="modal fade cc-modal" id="<?= e($ccModalId) ?>" tabindex="-1" aria-labelledby="<?= e($ccModalId) ?>Title" aria-hidden="true" data-cc-modal>
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable cc-modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="<?= e($ccModalId) ?>Title"><i class="bi bi-eye me-1"></i><?= e(__('cc.modal_title')) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(__('action.cancel')) ?>"></button>
                </div>
                <div class="modal-body entity-picker-body">
                    <div class="cc-modal-tools">
                        <div class="entity-picker-search-wrap">
                            <i class="bi bi-search" aria-hidden="true"></i>
                            <input type="search" class="form-control" data-cc-search autocomplete="off"
                                   placeholder="<?= e(__('cc.search')) ?>" aria-label="<?= e(__('cc.search')) ?>">
                        </div>
                        <?php if (count($ccGroups) > 1): ?>
                        <select class="form-select form-select-sm" data-cc-dept aria-label="<?= e(__('cc.all_departments')) ?>">
                            <option value=""><?= e(__('cc.all_departments')) ?></option>
                            <?php foreach ($ccGroups as $gKey => $g): ?>
                                <option value="<?= e((string) $gKey) ?>"><?= e($g['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php endif; ?>
                    </div>
                    <div class="entity-picker-list cc-modal-list mt-3">
                        <?php foreach ($ccSorted as $opt): ?>
                            <?php $gKey = $opt['dept_id'] > 0 ? (string) $opt['dept_id'] : '0'; $g = $ccGroups[$gKey]; ?>
                                <label class="cc-row" data-cc-item data-cc-dept-id="<?= e($gKey) ?>"
                                       data-search="<?= e(mb_strtolower($opt['name'] . ' ' . $opt['dept'] . ' ' . $opt['job'])) ?>">
                                    <input type="checkbox" class="form-check-input mt-0" data-cc-box
                                           value="<?= (int) $opt['id'] ?>"
                                           data-label="<?= e($opt['name']) ?>"
                                           data-photo="<?= e($opt['photo']) ?>"
                                           data-initials="<?= e($ccInitials($opt['name'])) ?>"
                                           data-dept="<?= e($opt['dept']) ?>">
                                    <?= $ccAvatar($opt, 'cc-avatar-sm') ?>
                                    <span class="cc-row-text">
                                        <span class="cc-row-name"><?= e($opt['name']) ?></span>
                                        <?php if ($opt['job'] !== ''): ?>
                                            <span class="cc-row-sub"><?= e($opt['job']) ?></span>
                                        <?php endif; ?>
                                    </span>
                                    <span class="cc-row-dept" title="<?= e($g['name']) ?>"><?= e($g['name']) ?></span>
                                </label>
                        <?php endforeach; ?>
                        <div class="entity-picker-empty" data-cc-nomatch hidden>
                            <i class="bi bi-search d-block mb-2"></i><?= e(__('cc.no_match')) ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer entity-picker-footer">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge text-bg-light text-dark border" data-cc-live-count>0</span>
                        <button type="button" class="btn btn-link btn-sm text-danger p-0" data-cc-clear><?= e(__('cc.clear')) ?></button>
                    </div>
                    <div class="entity-picker-footer-actions">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(__('action.cancel')) ?></button>
                        <button type="button" class="btn btn-kff" data-cc-done><?= e(__('cc.done')) ?></button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
<script>
(function () {
  document.querySelectorAll('[data-cc-picker]:not([data-ready])').forEach(function (root) {
    root.setAttribute('data-ready', '1');
    var summary = root.querySelector('[data-cc-summary]');
    var chips = root.querySelector('[data-cc-list]');
    var inputs = root.querySelector('[data-cc-inputs]');
    var openers = Array.prototype.slice.call(root.querySelectorAll('[data-cc-open]'));
    var openBtn = openers[0] || null;
    var resetBtn = root.querySelector('[data-cc-reset]');
    var modalEl = root.querySelector('[data-cc-modal]');
    var removeLabel = root.getAttribute('data-remove-label') || '';
    var countLabel = root.getAttribute('data-count-label') || ':n';
    var info = {};

    function selectedIds() {
      return Array.prototype.map.call(inputs.querySelectorAll('input'), function (i) { return i.value; });
    }
    function avatar(meta, cls) {
      if (meta.photo) {
        var img = document.createElement('img');
        img.src = meta.photo; img.alt = ''; img.className = 'cc-avatar ' + cls;
        return img;
      }
      var s = document.createElement('span');
      s.className = 'cc-avatar is-initials ' + cls;
      s.textContent = meta.initials || '?';
      return s;
    }
    function renderSelected() {
      var ids = selectedIds();
      summary.textContent = ids.length ? countLabel.replace(':n', String(ids.length)) : (root.getAttribute('data-empty-label') || '');
      summary.classList.toggle('is-placeholder', !ids.length);
      if (resetBtn) resetBtn.hidden = !ids.length;
      chips.innerHTML = '';
      ids.forEach(function (id) {
        var meta = info[id];
        if (!meta) return;
        var chip = document.createElement('span');
        chip.className = 'cc-chip';
        chip.title = meta.dept || '';
        chip.appendChild(avatar(meta, 'cc-avatar-xs'));
        var name = document.createElement('span');
        name.textContent = meta.label;
        chip.appendChild(name);
        if (openBtn) {
          var btn = document.createElement('button');
          btn.type = 'button'; btn.className = 'cc-chip-remove';
          btn.title = removeLabel; btn.setAttribute('aria-label', removeLabel);
          btn.innerHTML = '<i class="bi bi-x"></i>';
          btn.addEventListener('click', function () { setSelected(selectedIds().filter(function (x) { return x !== id; })); });
          chip.appendChild(btn);
        }
        chips.appendChild(chip);
      });
    }
    function setSelected(ids) {
      inputs.innerHTML = '';
      ids.forEach(function (id) {
        var i = document.createElement('input');
        i.type = 'hidden'; i.name = 'cc_users[]'; i.value = id;
        inputs.appendChild(i);
      });
      renderSelected();
    }

    if (!modalEl) {
      renderSelected();
      return;
    }
    // النافذة خارج البطاقة/النموذج حتى لا تُقصّ ولا تُرسَل مربعاتها
    document.body.appendChild(modalEl);
    var boxes = Array.prototype.slice.call(modalEl.querySelectorAll('[data-cc-box]'));
    boxes.forEach(function (b) {
      info[b.value] = { label: b.getAttribute('data-label'), photo: b.getAttribute('data-photo'), initials: b.getAttribute('data-initials'), dept: b.getAttribute('data-dept') };
    });
    var search = modalEl.querySelector('[data-cc-search]');
    var dept = modalEl.querySelector('[data-cc-dept]');
    var live = modalEl.querySelector('[data-cc-live-count]');
    var nomatch = modalEl.querySelector('[data-cc-nomatch]');
    var items = Array.prototype.slice.call(modalEl.querySelectorAll('[data-cc-item]'));

    function refreshCounts() {
      var total = boxes.filter(function (b) { return b.checked; }).length;
      live.textContent = countLabel.replace(':n', String(total));
    }
    function applyFilter() {
      var q = (search.value || '').trim().toLowerCase();
      var d = dept ? dept.value : '';
      var shown = 0;
      items.forEach(function (it) {
        var ok = (d === '' || it.getAttribute('data-cc-dept-id') === d)
          && (q === '' || (it.getAttribute('data-search') || '').indexOf(q) !== -1);
        it.hidden = !ok;
        if (ok) shown++;
      });
      nomatch.hidden = shown > 0;
      refreshCounts();
    }

    boxes.forEach(function (b) { b.addEventListener('change', refreshCounts); });
    search.addEventListener('input', applyFilter);
    search.addEventListener('keydown', function (e) { if (e.key === 'Enter') e.preventDefault(); });
    if (dept) dept.addEventListener('change', applyFilter);
    modalEl.querySelector('[data-cc-clear]').addEventListener('click', function () {
      boxes.forEach(function (b) { b.checked = false; });
      refreshCounts();
    });
    modalEl.querySelector('[data-cc-done]').addEventListener('click', function () {
      setSelected(boxes.filter(function (b) { return b.checked; }).map(function (b) { return b.value; }));
      bootstrap.Modal.getOrCreateInstance(modalEl).hide();
    });
    function openPicker() {
      var ids = selectedIds();
      boxes.forEach(function (b) { b.checked = ids.indexOf(b.value) !== -1; });
      search.value = '';
      if (dept) dept.value = '';
      applyFilter();
      bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }
    openers.forEach(function (o) {
      o.addEventListener('click', openPicker);
      o.addEventListener('keydown', function (e) {
        if (o.tagName !== 'BUTTON' && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); openPicker(); }
      });
    });
    if (resetBtn) resetBtn.addEventListener('click', function () { setSelected([]); });
    modalEl.addEventListener('shown.bs.modal', function () { search.focus(); });
    renderSelected();
  });
})();
</script>
