<?php if (isset($linkables) && is_array($linkables)): ?>
<div class="modal fade" id="linkedPickerModal" tabindex="-1" aria-labelledby="linkedPickerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable entity-picker-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="linkedPickerModalLabel">
                    <i class="bi <?= $isIncoming ? 'bi-send' : 'bi-inbox' ?> me-1"></i><?= e($isIncoming ? __('corr.link_out') : __('corr.link_in')) ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(__('action.cancel')) ?>"></button>
            </div>
            <div class="modal-body entity-picker-body">
                <div class="entity-picker-search-wrap">
                    <i class="bi bi-search" aria-hidden="true"></i>
                    <input type="search" id="linkedPickerSearch" class="form-control"
                           placeholder="<?= e(__('corr.link_picker_search')) ?>" autocomplete="off"
                           title="<?= e(__('corr.link_picker_hint')) ?>">
                </div>
                <div class="entity-picker-list mt-3">
                    <table class="table table-hover entity-picker-table">
                        <colgroup>
                            <col class="linked-picker-col-ref">
                            <col class="linked-picker-col-subject">
                            <col class="linked-picker-col-party">
                            <col class="linked-picker-col-date">
                        </colgroup>
                        <thead>
                            <tr>
                                <th><?= e(__('corr.number')) ?></th>
                                <th><?= e(__('corr.subject')) ?></th>
                                <th><?= e($isIncoming ? __('corr.party_to') : __('corr.party_from')) ?></th>
                                <th><?= e(__('corr.date')) ?></th>
                            </tr>
                        </thead>
                        <tbody id="linkedPickerRows"></tbody>
                    </table>
                    <div class="entity-picker-empty" id="linkedPickerEmpty" hidden>
                        <i class="bi bi-search d-block mb-2"></i><?= e(__('corr.link_picker_empty')) ?>
                    </div>
                    <div class="entity-picker-empty" id="linkedPickerLoading" hidden>
                        <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span><?= e(__('common.loading')) ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer entity-picker-footer">
                <span class="badge text-bg-light text-dark border" id="linkedPickerCount"></span>
                <span class="admin-form-hint m-0" id="linkedPickerMore" hidden></span>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(__('action.cancel')) ?></button>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
  var hidden = document.getElementById('linked_id');
  var box = document.getElementById('linked_box');
  var modalEl = document.getElementById('linkedPickerModal');
  if (!hidden || !box || !modalEl) return;
  if (modalEl.parentNode !== document.body) document.body.appendChild(modalEl);

  var openBtn = document.getElementById('linked_pick_btn');
  var clearBtn = document.getElementById('linked_clear_btn');
  var search = document.getElementById('linkedPickerSearch');
  var rowsBody = document.getElementById('linkedPickerRows');
  var emptyBox = document.getElementById('linkedPickerEmpty');
  var loadingBox = document.getElementById('linkedPickerLoading');
  var countBox = document.getElementById('linkedPickerCount');
  var moreBox = document.getElementById('linkedPickerMore');
  var refEl = document.getElementById('linked_ref');
  var subjectEl = document.getElementById('linked_subject');
  var placeholder = <?= json_encode($isIncoming ? __('corr.link_picker_placeholder_out') : __('corr.link_picker_placeholder_in'), JSON_UNESCAPED_UNICODE) ?>;
  var countTpl = <?= json_encode(__('corr.entity_picker_count'), JSON_UNESCAPED_UNICODE) ?>;
  var moreTpl = <?= json_encode(__('corr.entity_picker_more'), JSON_UNESCAPED_UNICODE) ?>;
  var endpoint = <?= json_encode(base_url('api/correspondence_search.php'), JSON_UNESCAPED_UNICODE) ?>;
  var forType = <?= json_encode($isIncoming ? 'incoming' : 'outgoing', JSON_UNESCAPED_UNICODE) ?>;
  var excludeId = <?= is_array($corr ?? null) ? (int) ($corr['id'] ?? 0) : 0 ?>;
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
  function applyPicked(data) {
    data = data || {};
    var ref = data.ref || '';
    var subject = data.subject || '';
    if (refEl) {
      refEl.textContent = ref;
      refEl.hidden = ref === '';
    }
    if (subjectEl) {
      subjectEl.textContent = subject !== '' ? subject : placeholder;
      subjectEl.classList.toggle('is-placeholder', subject === '');
    }
    if (clearBtn) clearBtn.hidden = !data.id;
  }
  function select(item) {
    hidden.value = String(item.id || '');
    applyPicked(item);
    var extDate = document.getElementById('external_date');
    if (extDate && item.letter_date) {
      extDate.value = item.letter_date;
      extDate.dispatchEvent(new Event('change', { bubbles: true }));
    }
    Array.prototype.forEach.call(rowsBody.children, function (r) {
      r.classList.toggle('is-selected', String(r.dataset.id) === String(item.id));
    });
    var m = modal();
    if (m) m.hide();
  }
  function clearSelection() {
    hidden.value = '';
    applyPicked({});
    Array.prototype.forEach.call(rowsBody.children, function (r) {
      r.classList.remove('is-selected');
    });
  }
  function buildRow(item) {
    var tr = el('tr', 'entity-picker-row');
    tr.tabIndex = 0;
    tr.dataset.id = String(item.id);
    if (String(item.id) === String(hidden.value)) tr.classList.add('is-selected');
    var refCell = el('td');
    var ref = el('span', 'linked-picker-ref', item.ref || '');
    ref.setAttribute('dir', 'ltr');
    refCell.appendChild(ref);
    tr.appendChild(refCell);
    tr.appendChild(el('td', '', item.subject || ''));
    tr.appendChild(el('td', '', item.party || ''));
    var dateCell = el('td', 'linked-picker-date');
    dateCell.appendChild(el('span', '', item.date || ''));
    tr.appendChild(dateCell);
    tr.addEventListener('click', function () { select(item); });
    tr.addEventListener('keydown', function (ev) {
      if (ev.key === 'Enter' || ev.key === ' ') {
        ev.preventDefault();
        select(item);
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
      var truncated = total > items.length;
      moreBox.hidden = !truncated;
      if (truncated) moreBox.textContent = moreTpl.replace(':count', String(items.length));
    }
  }
  function load(q) {
    var token = ++requestToken;
    if (loadingBox) loadingBox.hidden = false;
    if (emptyBox) emptyBox.hidden = true;
    var url = endpoint + '?for=' + encodeURIComponent(forType) + '&q=' + encodeURIComponent(q);
    if (excludeId) url += '&exclude_id=' + encodeURIComponent(String(excludeId));
    fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
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
  function open() {
    var m = modal();
    if (m) m.show();
  }
  function onBoxKey(ev) {
    if (ev.key === 'Enter' || ev.key === ' ') {
      ev.preventDefault();
      open();
    }
  }
  box.addEventListener('click', open);
  box.addEventListener('keydown', onBoxKey);
  if (openBtn) openBtn.addEventListener('click', open);
  if (clearBtn) clearBtn.addEventListener('click', clearSelection);
  if (search) {
    search.addEventListener('input', function () {
      window.clearTimeout(searchTimer);
      var q = search.value.trim();
      searchTimer = window.setTimeout(function () {
        if (q !== loadedQuery) load(q);
      }, 300);
    });
  }
  modalEl.addEventListener('shown.bs.modal', function () {
    if (search) search.focus();
    var q = search ? search.value.trim() : '';
    if (q !== loadedQuery) load(q);
  });
})();
</script>
<?php endif; ?>
