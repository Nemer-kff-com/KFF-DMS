<?php
/**
 * نافذة بحث جهات المراسلة لفلاتر قوائم الصادر/الوارد.
 * تُضمَّن مرة واحدة من render_filter_entity_field().
 */
?>
<div class="modal fade" id="filterEntityPickerModal" tabindex="-1" aria-labelledby="filterEntityPickerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable entity-picker-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="filterEntityPickerModalLabel">
                    <i class="bi bi-building me-1"></i><?= e(__('corr.entity_picker_title')) ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(__('action.cancel')) ?>"></button>
            </div>
            <div class="modal-body entity-picker-body">
                <div class="entity-picker-search-wrap">
                    <i class="bi bi-search" aria-hidden="true"></i>
                    <input type="search" id="filterEntityPickerSearch" class="form-control"
                           placeholder="<?= e(__('corr.entity_picker_search')) ?>" autocomplete="off"
                           title="<?= e(__('corr.entity_picker_hint')) ?>">
                </div>
                <div class="entity-picker-list mt-3">
                    <table class="table table-hover entity-picker-table">
                        <colgroup>
                            <col class="entity-picker-col-name">
                            <col class="entity-picker-col-org">
                            <col class="entity-picker-col-scope">
                            <col class="entity-picker-col-contact">
                        </colgroup>
                        <thead>
                            <tr>
                                <th><?= e(__('entities.directed_to')) ?></th>
                                <th><?= e(__('entities.section_basic')) ?></th>
                                <th><?= e(__('entities.scope')) ?></th>
                                <th><?= e(__('entities.contact')) ?></th>
                            </tr>
                        </thead>
                        <tbody id="filterEntityPickerRows"></tbody>
                    </table>
                    <div class="entity-picker-empty" id="filterEntityPickerEmpty" hidden>
                        <i class="bi bi-search d-block mb-2"></i><?= e(__('corr.entity_picker_empty')) ?>
                    </div>
                    <div class="entity-picker-empty" id="filterEntityPickerLoading" hidden>
                        <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span><?= e(__('common.loading')) ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer entity-picker-footer">
                <span class="badge text-bg-light text-dark border" id="filterEntityPickerCount"></span>
                <span class="admin-form-hint m-0" id="filterEntityPickerMore" hidden></span>
                <div class="entity-picker-footer-actions">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(__('action.cancel')) ?></button>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
  if (window.__dmsFilterEntityPickerReady) return;
  window.__dmsFilterEntityPickerReady = true;

  var modalEl = document.getElementById('filterEntityPickerModal');
  if (!modalEl) return;
  if (modalEl.parentNode !== document.body) document.body.appendChild(modalEl);

  var search = document.getElementById('filterEntityPickerSearch');
  var rowsBody = document.getElementById('filterEntityPickerRows');
  var emptyBox = document.getElementById('filterEntityPickerEmpty');
  var loadingBox = document.getElementById('filterEntityPickerLoading');
  var countBox = document.getElementById('filterEntityPickerCount');
  var moreBox = document.getElementById('filterEntityPickerMore');
  var endpoint = <?= json_encode(base_url('api/entities_search.php'), JSON_UNESCAPED_UNICODE) ?>;
  var countTpl = <?= json_encode(__('corr.entity_picker_count'), JSON_UNESCAPED_UNICODE) ?>;
  var moreTpl = <?= json_encode(__('corr.entity_picker_more'), JSON_UNESCAPED_UNICODE) ?>;
  var emptyLabelFallback = <?= json_encode(__('common.all'), JSON_UNESCAPED_UNICODE) ?>;

  var activeRoot = null;
  var loadedQuery = null;
  var requestToken = 0;
  var searchTimer = null;

  function modal() {
    return (window.bootstrap && bootstrap.Modal) ? bootstrap.Modal.getOrCreateInstance(modalEl) : null;
  }

  function el(tag, className, text) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    if (text) node.textContent = text;
    return node;
  }

  function corrType() {
    return (activeRoot && activeRoot.getAttribute('data-corr-type')) || 'outgoing';
  }

  function hiddenInput() {
    return activeRoot ? activeRoot.querySelector('[data-filter-entity-id]') : null;
  }

  function labelEl() {
    return activeRoot ? activeRoot.querySelector('[data-filter-entity-label]') : null;
  }

  function displayEl() {
    return activeRoot ? activeRoot.querySelector('[data-filter-entity-open]') : null;
  }

  function clearBtn() {
    return activeRoot ? activeRoot.querySelector('[data-filter-entity-clear]') : null;
  }

  function pickBtn() {
    return activeRoot ? activeRoot.querySelector('[data-filter-entity-pick]') : null;
  }

  function emptyLabel() {
    return (activeRoot && activeRoot.getAttribute('data-empty-label')) || emptyLabelFallback;
  }

  function setLabel(text) {
    var label = labelEl();
    var box = displayEl();
    if (!label) return;
    var value = (text || '').trim();
    var empty = value === '';
    label.textContent = empty ? emptyLabel() : value;
    if (box) box.classList.toggle('is-empty', empty);
  }

  function syncButtons(hasValue) {
    var pick = pickBtn();
    var clear = clearBtn();
    if (pick) pick.hidden = !!hasValue;
    if (clear) clear.hidden = !hasValue;
  }

  function applySelection(id, display) {
    var hidden = hiddenInput();
    if (!hidden) return;
    hidden.value = id ? String(id) : '';
    setLabel(display || '');
    syncButtons(!!id);
  }

  function buildRow(item) {
    var tr = el('tr', 'entity-picker-row');
    tr.tabIndex = 0;
    tr.dataset.id = String(item.id);
    tr.dataset.display = item.display || item.party || item.person || item.org || '';
    var hidden = hiddenInput();
    if (hidden && String(item.id) === String(hidden.value)) tr.classList.add('is-selected');

    var nameCell = el('td');
    if (item.prefix) nameCell.appendChild(el('span', 'entity-picker-sub', item.prefix));
    if (item.person) nameCell.appendChild(el('span', 'entity-picker-name', item.person));
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
    ['mobile', 'email'].forEach(function (key) {
      if (!item[key]) return;
      var line = el('span', 'entity-picker-contact-item', item[key]);
      line.setAttribute('dir', 'ltr');
      contactCell.appendChild(line);
    });
    tr.appendChild(contactCell);

    tr.addEventListener('click', function () { select(tr); });
    tr.addEventListener('keydown', function (ev) {
      if (ev.key === 'Enter' || ev.key === ' ') {
        ev.preventDefault();
        select(tr);
      }
    });
    return tr;
  }

  function render(data) {
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
  }

  function load(q) {
    var token = ++requestToken;
    if (loadingBox) loadingBox.hidden = false;
    if (emptyBox) emptyBox.hidden = true;
    var hidden = hiddenInput();
    var url = endpoint + '?type=' + encodeURIComponent(corrType()) + '&q=' + encodeURIComponent(q);
    if (hidden && hidden.value) url += '&include_id=' + encodeURIComponent(hidden.value);

    fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
      .then(function (res) { return res.ok ? res.json() : Promise.reject(res.status); })
      .then(function (data) {
        if (token !== requestToken) return;
        loadedQuery = q;
        render(data);
      })
      .catch(function () {
        if (token !== requestToken) return;
        render({ items: [], total: 0 });
      })
      .then(function () {
        if (token === requestToken && loadingBox) loadingBox.hidden = true;
      });
  }

  function select(row) {
    applySelection(row.dataset.id || '', row.dataset.display || '');
    var m = modal();
    if (m) m.hide();
  }

  function openFor(root) {
    activeRoot = root;
    loadedQuery = null;
    if (search) search.value = '';
    rowsBody.textContent = '';
    if (emptyBox) emptyBox.hidden = true;
    if (moreBox) moreBox.hidden = true;
    var m = modal();
    if (m) m.show();
    load('');
    setTimeout(function () { if (search) search.focus(); }, 200);
  }

  function clearFor(root) {
    activeRoot = root;
    applySelection('', '');
  }

  document.addEventListener('click', function (ev) {
    var openEl = ev.target.closest('[data-filter-entity-open], [data-filter-entity-pick]');
    if (openEl) {
      var root = openEl.closest('[data-filter-entity]');
      if (root) {
        ev.preventDefault();
        openFor(root);
      }
      return;
    }
    var clearEl = ev.target.closest('[data-filter-entity-clear]');
    if (clearEl) {
      var clearRoot = clearEl.closest('[data-filter-entity]');
      if (clearRoot) {
        ev.preventDefault();
        clearFor(clearRoot);
      }
    }
  });

  document.addEventListener('keydown', function (ev) {
    if (ev.key !== 'Enter' && ev.key !== ' ') return;
    var box = ev.target.closest('[data-filter-entity-open]');
    if (!box) return;
    var root = box.closest('[data-filter-entity]');
    if (!root) return;
    ev.preventDefault();
    openFor(root);
  });

  if (search) {
    search.addEventListener('input', function () {
      clearTimeout(searchTimer);
      searchTimer = setTimeout(function () {
        var q = search.value.trim();
        if (q === loadedQuery) return;
        load(q);
      }, 220);
    });
  }

  modalEl.addEventListener('hidden.bs.modal', function () {
    activeRoot = null;
  });
})();
</script>
