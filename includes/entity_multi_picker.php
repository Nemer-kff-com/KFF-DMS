<?php
/**
 * اختيار مستلمين متعددين بنافذة بحث كالواردة/الصادر المفرد.
 * يتطلب: $selectedPayloads (list)
 * اختياري: $inputName, $pickerId, $groups, $groupMembers, $preGroup,
 *          $hideGroupSelect (bool), $omitGroupHidden (bool), $showSelectedNames (bool),
 *          $pickerTitle, $pickLabel, $editLabel, $emptyText, $hintText
 */
$selectedPayloads = is_array($selectedPayloads ?? null) ? $selectedPayloads : [];
$inputName = $inputName ?? 'entity_ids[]';
$pickerId = $pickerId ?? 'bulk-recipients';
$groups = is_array($groups ?? null) ? $groups : [];
$groupMembers = is_array($groupMembers ?? null) ? $groupMembers : [];
$preGroup = (int) ($preGroup ?? 0);
$hideGroupSelect = !empty($hideGroupSelect);
$omitGroupHidden = !empty($omitGroupHidden);
$showSelectedNames = !empty($showSelectedNames);
$pickerTitle = (string) ($pickerTitle ?? __('bulk.picker_title'));
$pickLabel = (string) ($pickLabel ?? __('bulk.add_recipients'));
$editLabel = (string) ($editLabel ?? __('bulk.edit_recipients'));
$emptyText = (string) ($emptyText ?? __('bulk.no_recipients'));
$hintText = (string) ($hintText ?? __('bulk.or_pick'));
$groupNames = [];
foreach ($groups as $g) {
    $groupNames[(string) (int) $g['id']] = (string) $g['name'];
}
$modalId = $pickerId . '-modal';
?>
<div class="bulk-recipients<?= $showSelectedNames ? ' is-named' : '' ?>" id="<?= e($pickerId) ?>">
    <?php if (!$omitGroupHidden): ?>
        <input type="hidden" name="entity_group_id" id="entity_group_id" value="<?= $preGroup > 0 ? $preGroup : '' ?>">
    <?php endif; ?>
    <p class="admin-form-hint"<?= $hintText === '' ? ' hidden' : '' ?>><?= e($hintText) ?></p>
    <div data-bulk-list></div>
    <div data-bulk-inputs></div>
</div>

<div class="modal fade" id="<?= e($modalId) ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable entity-picker-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-people me-1"></i><?= e($pickerTitle) ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(__('action.cancel')) ?>"></button>
            </div>
            <div class="modal-body entity-picker-body">
                <?php if (!$hideGroupSelect): ?>
                <div class="bulk-picker-group">
                    <label class="form-label" for="<?= e($pickerId) ?>_group"><?= e(__('bulk.choose_group')) ?></label>
                    <select id="<?= e($pickerId) ?>_group" class="form-select" data-bulk-group>
                        <option value=""><?= e(__('common.none')) ?></option>
                        <?php foreach ($groups as $g): ?>
                            <option value="<?= (int) $g['id'] ?>" <?= $preGroup === (int) $g['id'] ? 'selected' : '' ?>>
                                <?= e($g['name'] . ' (' . (int) $g['member_count'] . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($groups): ?>
                        <p class="admin-form-hint mb-0 mt-2"><?= e(__('bulk.picker_group_hint')) ?></p>
                    <?php else: ?>
                        <p class="admin-form-hint mb-0 mt-2"><?= e(__('bulk.no_groups')) ?></p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <div class="entity-picker-search-wrap">
                    <i class="bi bi-search" aria-hidden="true"></i>
                    <input type="search" class="form-control" data-bulk-search
                           placeholder="<?= e(__('corr.entity_picker_search')) ?>" autocomplete="off">
                </div>
                <div class="bulk-picker-filters" role="group" aria-label="<?= e(__('groups.filter_selected')) ?>">
                    <button type="button" class="btn btn-sm btn-outline-secondary is-active" data-bulk-view="all">
                        <?= e(__('groups.filter_all')) ?>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bulk-view="selected">
                        <?= e(__('groups.filter_selected')) ?>
                    </button>
                </div>
                <p class="admin-form-hint entity-picker-hint">
                    <i class="bi bi-info-circle me-1"></i><?= e(__('bulk.picker_hint')) ?>
                </p>
                <div class="entity-picker-list">
                    <table class="table table-hover entity-picker-table">
                        <colgroup>
                            <col class="entity-picker-col-check">
                            <col class="entity-picker-col-name">
                            <col class="entity-picker-col-org">
                            <col class="entity-picker-col-scope">
                            <col class="entity-picker-col-contact">
                        </colgroup>
                        <thead>
                            <tr>
                                <th class="entity-picker-check"></th>
                                <th><?= e(__('entities.directed_to')) ?></th>
                                <th><?= e(__('entities.section_basic')) ?></th>
                                <th><?= e(__('entities.scope')) ?></th>
                                <th><?= e(__('entities.contact')) ?></th>
                            </tr>
                        </thead>
                        <tbody data-bulk-rows></tbody>
                    </table>
                    <div class="entity-picker-empty" data-bulk-empty hidden>
                        <i class="bi bi-search d-block mb-2"></i><?= e(__('corr.entity_picker_empty')) ?>
                    </div>
                    <div class="entity-picker-empty" data-bulk-loading hidden>
                        <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span><?= e(__('common.loading')) ?>
                    </div>
                </div>
                <div class="bulk-picker-chosen" data-bulk-chosen hidden>
                    <div class="bulk-picker-chosen-head">
                        <span data-bulk-chosen-label></span>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bulk-clear-draft>
                            <?= e(__('bulk.picker_clear_draft')) ?>
                        </button>
                    </div>
                </div>
            </div>
            <div class="modal-footer entity-picker-footer">
                <span class="badge text-bg-light text-dark border" data-bulk-result-count></span>
                <span class="admin-form-hint m-0" data-bulk-more hidden></span>
                <div class="entity-picker-footer-actions">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(__('action.cancel')) ?></button>
                    <button type="button" class="btn btn-kff" data-bulk-done><?= e(__('bulk.picker_done')) ?></button>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
  var root = document.getElementById(<?= json_encode($pickerId) ?>);
  var modalEl = document.getElementById(<?= json_encode($modalId) ?>);
  if (!root || !modalEl) return;
  if (modalEl.parentNode !== document.body) document.body.appendChild(modalEl);

  var inputName = <?= json_encode($inputName, JSON_UNESCAPED_UNICODE) ?>;
  var endpoint = <?= json_encode(base_url('api/entities_search.php'), JSON_UNESCAPED_UNICODE) ?>;
  var countTpl = <?= json_encode(__('corr.entity_picker_count'), JSON_UNESCAPED_UNICODE) ?>;
  var moreTpl = <?= json_encode(__('corr.entity_picker_more'), JSON_UNESCAPED_UNICODE) ?>;
  var chosenTpl = <?= json_encode(__('bulk.picker_chosen'), JSON_UNESCAPED_UNICODE) ?>;
  var emptyText = <?= json_encode($emptyText, JSON_UNESCAPED_UNICODE) ?>;
  var summaryCountTpl = <?= json_encode(__('bulk.summary_count'), JSON_UNESCAPED_UNICODE) ?>;
  var summaryGroupTpl = <?= json_encode(__('bulk.summary_group'), JSON_UNESCAPED_UNICODE) ?>;
  var summaryReview = <?= json_encode(__('bulk.summary_review'), JSON_UNESCAPED_UNICODE) ?>;
  var noEmailTpl = <?= json_encode(__('bulk.no_email_count'), JSON_UNESCAPED_UNICODE) ?>;
  var showNames = <?= $showSelectedNames ? 'true' : 'false' ?>;
  var initial = <?= json_encode($selectedPayloads, JSON_UNESCAPED_UNICODE) ?>;
  var groupMap = <?= json_encode($groupMembers, JSON_UNESCAPED_UNICODE) ?>;
  var groupNames = <?= json_encode($groupNames, JSON_UNESCAPED_UNICODE) ?>;

  var listEl = root.querySelector('[data-bulk-list]');
  var inputsEl = root.querySelector('[data-bulk-inputs]');
  var groupHidden = document.getElementById('entity_group_id');
  var groupSel = modalEl.querySelector('[data-bulk-group]');
  var search = modalEl.querySelector('[data-bulk-search]');
  var rowsBody = modalEl.querySelector('[data-bulk-rows]');
  var emptyBox = modalEl.querySelector('[data-bulk-empty]');
  var loadingBox = modalEl.querySelector('[data-bulk-loading]');
  var countBox = modalEl.querySelector('[data-bulk-result-count]');
  var moreBox = modalEl.querySelector('[data-bulk-more]');
  var doneBtn = modalEl.querySelector('[data-bulk-done]');
  var chosenBox = modalEl.querySelector('[data-bulk-chosen]');
  var chosenLabel = modalEl.querySelector('[data-bulk-chosen-label]');
  var clearDraftBtn = modalEl.querySelector('[data-bulk-clear-draft]');
  var viewBtns = modalEl.querySelectorAll('[data-bulk-view]');

  var selected = {};
  var order = [];
  var draft = {};
  var draftOrder = [];
  var draftGroup = groupHidden ? groupHidden.value : '';
  var applyOnClose = false;
  var loadedQuery = null;
  var requestToken = 0;
  var searchTimer = null;
  var viewMode = 'all';

  function modal() {
    return (window.bootstrap && bootstrap.Modal) ? bootstrap.Modal.getOrCreateInstance(modalEl) : null;
  }
  function el(tag, className, text) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    if (text) node.textContent = text;
    return node;
  }
  function keyOf(id) { return String(id); }
  function hasCommitted(id) { return Object.prototype.hasOwnProperty.call(selected, keyOf(id)); }
  function hasDraft(id) { return Object.prototype.hasOwnProperty.call(draft, keyOf(id)); }
  function itemLabel(item) {
    return (item && (item.person || item.display || item.org || item.party)) || '';
  }
  function itemSearchText(item) {
    return [
      item.prefix || '',
      item.person || '',
      item.job || '',
      item.org || '',
      item.mobile || '',
      item.email || '',
      item.display || ''
    ].join(' ').toLowerCase();
  }
  function setViewMode(mode) {
    viewMode = mode === 'selected' ? 'selected' : 'all';
    Array.prototype.forEach.call(viewBtns, function (btn) {
      btn.classList.toggle('is-active', btn.getAttribute('data-bulk-view') === viewMode);
    });
    if (viewMode === 'all') loadedQuery = null;
    refreshList();
  }
  function refreshList() {
    var q = search ? search.value.trim() : '';
    if (viewMode === 'selected') {
      renderSelectedView(q);
      return;
    }
    load(q);
  }

  function snapshotDraft() {
    draft = {};
    draftOrder = order.slice();
    order.forEach(function (key) {
      if (selected[key]) draft[key] = selected[key];
    });
    draftGroup = groupHidden ? String(groupHidden.value || '') : '';
    if (groupSel) groupSel.value = draftGroup;
    applyOnClose = false;
  }
  function applyDraft() {
    selected = {};
    order = draftOrder.slice();
    draftOrder.forEach(function (key) {
      if (draft[key]) selected[key] = draft[key];
    });
    if (groupHidden) groupHidden.value = draftGroup || '';
    renderSelected();
  }

  function addDraft(item, skipRender) {
    if (!item || !item.id) return;
    var key = keyOf(item.id);
    if (!hasDraft(key)) draftOrder.push(key);
    draft[key] = item;
    if (!skipRender) {
      renderDraftUi();
      if (viewMode === 'selected') renderSelectedView(search ? search.value.trim() : '');
      else markRows();
    }
  }
  function removeDraft(id) {
    var key = keyOf(id);
    if (!hasDraft(key)) return;
    delete draft[key];
    draftOrder = draftOrder.filter(function (k) { return k !== key; });
    renderDraftUi();
    if (viewMode === 'selected') renderSelectedView(search ? search.value.trim() : '');
    else markRows();
  }
  function toggleDraft(item) {
    if (hasDraft(item.id)) removeDraft(item.id);
    else addDraft(item);
  }
  function clearDraft() {
    draft = {};
    draftOrder = [];
    draftGroup = '';
    if (groupSel) groupSel.value = '';
    renderDraftUi();
    if (viewMode === 'selected') renderSelectedView(search ? search.value.trim() : '');
    else markRows();
  }

  function addCommitted(item, skipRender) {
    if (!item || !item.id) return;
    var key = keyOf(item.id);
    if (!hasCommitted(key)) order.push(key);
    selected[key] = item;
    if (!skipRender) renderSelected();
  }

  function openPicker() {
    snapshotDraft();
    renderDraftUi();
    var m = modal();
    if (m) m.show();
  }

  function groupNameOf(id) {
    if (!id) return '';
    return groupNames[String(id)] || '';
  }

  function renderSelected() {
    listEl.textContent = '';
    inputsEl.textContent = '';
    var n = 0;
    order.forEach(function (key) {
      if (selected[key] && !(selected[key].email || '').trim()) n++;
      if (selected[key]) {
        var hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = inputName;
        hidden.value = String(selected[key].id);
        inputsEl.appendChild(hidden);
      }
    });
    if (!order.length) {
      var empty = el('p', 'bulk-selected-empty mb-0', emptyText);
      empty.addEventListener('click', openPicker);
      listEl.appendChild(empty);
    } else if (showNames) {
      var wrap = el('div', 'bulk-selected-names');
      order.forEach(function (key) {
        var item = selected[key];
        if (!item) return;
        var chip = el('button', 'bulk-selected-chip');
        chip.type = 'button';
        var title = itemLabel(item);
        chip.appendChild(el('span', 'bulk-selected-chip-name', title));
        if (item.job) chip.appendChild(el('span', 'bulk-selected-chip-sub', item.job));
        else if (item.org && item.org !== title) chip.appendChild(el('span', 'bulk-selected-chip-sub', item.org));
        chip.addEventListener('click', openPicker);
        wrap.appendChild(chip);
      });
      listEl.appendChild(wrap);
    } else {
      var card = el('button', 'bulk-selected-summary');
      card.type = 'button';
      var iconWrap = el('span', 'bulk-selected-summary-icon');
      iconWrap.innerHTML = '<i class="bi bi-people-fill" aria-hidden="true"></i>';
      card.appendChild(iconWrap);
      var body = el('div', 'bulk-selected-summary-body');
      body.appendChild(el('strong', 'bulk-selected-summary-title', summaryCountTpl.replace(':count', String(order.length))));
      var gName = groupNameOf(groupHidden ? groupHidden.value : '');
      if (gName) body.appendChild(el('span', 'bulk-selected-summary-meta', summaryGroupTpl.replace(':name', gName)));
      if (n) body.appendChild(el('span', 'bulk-selected-summary-warn', noEmailTpl + ': ' + n));
      body.appendChild(el('span', 'bulk-selected-summary-hint', summaryReview));
      card.appendChild(body);
      card.addEventListener('click', openPicker);
      listEl.appendChild(card);
    }
  }

  function renderDraftUi() {
    if (chosenLabel) chosenLabel.textContent = chosenTpl.replace(':count', String(draftOrder.length));
    if (chosenBox) chosenBox.hidden = draftOrder.length === 0;
    if (doneBtn) {
      doneBtn.disabled = false;
      doneBtn.textContent = <?= json_encode(__('bulk.picker_done'), JSON_UNESCAPED_UNICODE) ?> +
        (draftOrder.length ? ' (' + draftOrder.length + ')' : '');
    }
  }

  function markRows() {
    if (!rowsBody) return;
    Array.prototype.forEach.call(rowsBody.children, function (tr) {
      var on = hasDraft(tr.dataset.id);
      tr.classList.toggle('is-selected', on);
      var cb = tr.querySelector('input[type="checkbox"]');
      if (cb) cb.checked = on;
    });
  }

  function buildRow(item) {
    var tr = el('tr', 'entity-picker-row');
    tr.tabIndex = 0;
    tr.dataset.id = String(item.id);
    if (hasDraft(item.id)) tr.classList.add('is-selected');

    var checkCell = el('td', 'entity-picker-check');
    var cb = document.createElement('input');
    cb.type = 'checkbox';
    cb.tabIndex = -1;
    cb.checked = hasDraft(item.id);
    cb.setAttribute('aria-hidden', 'true');
    checkCell.appendChild(cb);
    tr.appendChild(checkCell);

    var nameCell = el('td');
    if (item.prefix) nameCell.appendChild(el('span', 'entity-picker-sub', item.prefix));
    if (item.person) nameCell.appendChild(el('span', 'entity-picker-name', item.person));
    else if (item.org) nameCell.appendChild(el('span', 'entity-picker-name', item.org));
    tr.appendChild(nameCell);

    var orgCell = el('td');
    if (item.job) orgCell.appendChild(el('span', 'entity-picker-sub', item.job));
    if (item.org) orgCell.appendChild(el('span', 'entity-picker-name', item.org));
    tr.appendChild(orgCell);

    var scopeCell = el('td', 'entity-picker-scope');
    var badge = el('span', 'entity-picker-scope-badge ' + (item.scope_modifier || ''));
    badge.setAttribute('title', item.scope_title || '');
    var icon = el('i', 'bi ' + (item.scope_icon || ''));
    icon.setAttribute('aria-hidden', 'true');
    badge.appendChild(icon);
    scopeCell.appendChild(badge);
    scopeCell.appendChild(el('span', 'entity-picker-scope-text', item.scope_text || ''));
    tr.appendChild(scopeCell);

    var contactCell = el('td', 'entity-picker-contact');
    ['mobile', 'email'].forEach(function (k) {
      if (!item[k]) return;
      var line = el('span', 'entity-picker-contact-item', item[k]);
      line.setAttribute('dir', 'ltr');
      contactCell.appendChild(line);
    });
    tr.appendChild(contactCell);

    tr.addEventListener('click', function () { toggleDraft(item); });
    tr.addEventListener('keydown', function (ev) {
      if (ev.key === 'Enter' || ev.key === ' ') {
        ev.preventDefault();
        toggleDraft(item);
      }
    });
    return tr;
  }

  function renderResults(data) {
    var items = (data && data.items) || [];
    var total = data && typeof data.total === 'number' ? data.total : items.length;
    rowsBody.textContent = '';
    items.forEach(function (item) { rowsBody.appendChild(buildRow(item)); });
    if (countBox) countBox.textContent = countTpl.replace(':count', String(total));
    if (emptyBox) emptyBox.hidden = items.length !== 0;
    if (moreBox) {
      var truncated = !!(data && data.truncated) || total > items.length;
      moreBox.hidden = !truncated;
      if (truncated) moreBox.textContent = moreTpl.replace(':count', String(items.length));
    }
    if (loadingBox) loadingBox.hidden = true;
  }

  function renderSelectedView(q) {
    requestToken++;
    if (loadingBox) loadingBox.hidden = true;
    var needle = (q || '').toLowerCase();
    var items = [];
    draftOrder.forEach(function (key) {
      var item = draft[key];
      if (!item) return;
      if (needle && itemSearchText(item).indexOf(needle) === -1) return;
      items.push(item);
    });
    renderResults({ items: items, total: items.length, truncated: false });
  }

  function load(q) {
    if (viewMode === 'selected') {
      renderSelectedView(q);
      return;
    }
    var token = ++requestToken;
    if (loadingBox) loadingBox.hidden = false;
    if (emptyBox) emptyBox.hidden = true;
    var url = endpoint + '?type=outgoing&q=' + encodeURIComponent(q);
    fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (res) { return res.ok ? res.json() : Promise.reject(res.status); })
      .then(function (data) {
        if (token !== requestToken) return;
        loadedQuery = q;
        renderResults(data);
      })
      .catch(function () {
        if (token !== requestToken) return;
        renderResults({ items: [], total: 0 });
      })
      .then(function () {
        if (token === requestToken && loadingBox) loadingBox.hidden = true;
      });
  }

  function fetchByIds(ids, done) {
    ids = (ids || []).map(function (n) { return parseInt(n, 10); }).filter(function (n) { return n > 0; });
    if (!ids.length) {
      done([]);
      return;
    }
    var url = endpoint + '?type=outgoing&ids=' + encodeURIComponent(ids.join(','));
    fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (res) { return res.ok ? res.json() : Promise.reject(res.status); })
      .then(function (data) { done((data && data.items) || []); })
      .catch(function () { done([]); });
  }

  function loadGroupIntoDraft(groupId) {
    draftGroup = String(groupId || '');
    var ids = groupMap[draftGroup] || [];
    fetchByIds(ids, function (items) {
      draft = {};
      draftOrder = [];
      items.forEach(function (item) { addDraft(item, true); });
      renderDraftUi();
      refreshList();
    });
  }

  window.DmsBulkRecipients = {
    replaceWithIds: function (ids) {
      fetchByIds(ids, function (items) {
        selected = {};
        order = [];
        items.forEach(function (item) { addCommitted(item, true); });
        renderSelected();
      });
    }
  };

  (initial || []).forEach(function (item) { addCommitted(item, true); });
  function boot() { renderSelected(); }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }

  if (clearDraftBtn) clearDraftBtn.addEventListener('click', clearDraft);
  if (doneBtn) {
    doneBtn.addEventListener('click', function () {
      applyOnClose = true;
      applyDraft();
      var m = modal();
      if (m) m.hide();
    });
  }
  if (groupSel) {
    groupSel.addEventListener('change', function () {
      if (!groupSel.value) {
        draftGroup = '';
        return;
      }
      loadGroupIntoDraft(groupSel.value);
    });
  }
  if (search) {
    search.addEventListener('input', function () {
      window.clearTimeout(searchTimer);
      var q = search.value.trim();
      searchTimer = window.setTimeout(function () {
        if (viewMode === 'selected') renderSelectedView(q);
        else if (q !== loadedQuery) load(q);
      }, 300);
    });
  }
  Array.prototype.forEach.call(viewBtns, function (btn) {
    btn.addEventListener('click', function () {
      setViewMode(btn.getAttribute('data-bulk-view') || 'all');
    });
  });
  modalEl.addEventListener('shown.bs.modal', function () {
    if (search) search.focus();
    refreshList();
  });
  modalEl.addEventListener('hidden.bs.modal', function () {
    if (!applyOnClose) snapshotDraft();
    applyOnClose = false;
  });
})();
</script>
