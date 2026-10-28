<div class="modal fade" id="entityPickerModal" tabindex="-1" aria-labelledby="entityPickerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable entity-picker-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="entityPickerModalLabel">
                    <i class="bi bi-building me-1"></i><?= e(__('corr.entity_picker_title')) ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(__('action.cancel')) ?>"></button>
            </div>
            <div class="modal-body entity-picker-body">
                <div class="entity-picker-search-wrap">
                    <i class="bi bi-search" aria-hidden="true"></i>
                    <input type="search" id="entityPickerSearch" class="form-control"
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
                        <tbody id="entityPickerRows"></tbody>
                    </table>
                    <div class="entity-picker-empty" id="entityPickerEmpty" hidden>
                        <i class="bi bi-search d-block mb-2"></i><?= e(__('corr.entity_picker_empty')) ?>
                    </div>
                    <div class="entity-picker-empty" id="entityPickerLoading" hidden>
                        <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span><?= e(__('common.loading')) ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer entity-picker-footer">
                <span class="badge text-bg-light text-dark border" id="entityPickerCount"></span>
                <span class="admin-form-hint m-0" id="entityPickerMore" hidden></span>
                <div class="entity-picker-footer-actions">
                    <?php if (user_can_create_entity()): ?>
                    <button type="button" class="btn btn-kff" id="entityPickerAddBtn">
                        <i class="bi bi-plus-lg me-1"></i><?= e(__('corr.entity_picker_add')) ?>
                    </button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(__('action.cancel')) ?></button>
                </div>
            </div>
        </div>
    </div>
</div>
<?php if (user_can_create_entity()):
    $eqcUser = $user ?? (function_exists('current_user') ? current_user() : null);
    $eqcDepartmentsBackup = isset($departments) && is_array($departments) ? $departments : null;
    $canManageAllEntities = function_exists('user_manages_all_entities') && user_manages_all_entities($eqcUser);
    $entityScopeReady = entities_department_scope_available();
    $isEdit = false;
    $isSystemAdmin = function_exists('can_manage_users') && can_manage_users($eqcUser);
    // لا تغيّر $departments المستخدم في نموذج المعاملة؛ مرّر قائمة مستقلة للنموذج المشترك.
    $eqcDepartmentsForForm = $canManageAllEntities
        ? ($eqcDepartmentsBackup ?? departments_list())
        : [];
    $eqcNextSort = 10;
    // لا تُحسب MAX(sort_order) هنا — تُحسب عند الحفظ في api/entity_create.php
    $values = [
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
        'department_id' => $canManageAllEntities
            ? ''
            : (function_exists('user_primary_department_id') ? user_primary_department_id($eqcUser) : (int) ($eqcUser['department_id'] ?? 0)),
        'sort_order' => '',
    ];
?>
<?php
// لا تستخدم <form> هنا: الملف يُضمَّن داخل نموذج الصادر/الوارد، والمتصفح يتجاهل form المتداخل.
?>
<div class="modal fade" id="entityQuickCreateModal" tabindex="-1" aria-labelledby="entityQuickCreateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable entity-create-modal-dialog">
        <div class="modal-content">
            <div id="entityQuickCreateForm"
                 class="admin-form"
                 data-csrf-name="csrf_token"
                 data-csrf-value="<?= e(csrf_token()) ?>"
                 data-default-sort="<?= e((string) $eqcNextSort) ?>"
                 autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="corr_type" value="<?= $isIncoming ? 'incoming' : 'outgoing' ?>">
                <?php if (!$canManageAllEntities): ?>
                <input type="hidden" name="department_id" id="eqc_department_id" value="<?= e((string) ($values['department_id'] ?? '')) ?>">
                <?php endif; ?>
                <div class="modal-header">
                    <h5 class="modal-title" id="entityQuickCreateModalLabel">
                        <i class="bi bi-plus-circle me-1"></i><?= e(__('entities.new')) ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(__('action.close')) ?>"></button>
                </div>
                <div class="modal-body entity-create-modal-body entity-create-modal-body-compact">
                    <p class="admin-form-hint mb-3"><?= e(__('corr.entity_picker_add_hint')) ?></p>
                    <div class="alert alert-danger d-none" id="entityQuickCreateError" role="alert"></div>
                    <?php
                    $user = $eqcUser;
                    $departments = $eqcDepartmentsForForm;
                    $entityFormPrefix = 'eqc_';
                    $entityFormInModal = true;
                    $entityFormModalCompact = true;
                    $entityFormHideActions = true;
                    require __DIR__ . '/../admin/_entity_form.php';
                    ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(__('action.cancel')) ?></button>
                    <button type="button" class="btn btn-kff" id="entityQuickCreateSave" <?= $entityScopeReady ? '' : 'disabled' ?>>
                        <i class="bi bi-check2 me-1"></i><?= e(__('action.save')) ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
    $user = $eqcUser;
    if ($eqcDepartmentsBackup !== null) {
        $departments = $eqcDepartmentsBackup;
    }
endif; ?>
<script>
(function(){
  var hidden = document.getElementById('entity_id');
  var personBox = document.getElementById('entity_person_box');
  var orgBox = document.getElementById('entity_org_box');
  var party = document.getElementById('party_name');
  var modalEl = document.getElementById('entityPickerModal');
  if (!hidden || !personBox || !orgBox || !party || !modalEl) return;

  // خارج النموذج لتفادي مشاكل التراكب مع بطاقة المحرر
  if (modalEl.parentNode !== document.body) document.body.appendChild(modalEl);

  var openBtn = document.getElementById('entity_pick_btn');
  var clearBtn = document.getElementById('entity_clear_btn');
  var search = document.getElementById('entityPickerSearch');
  var rowsBody = document.getElementById('entityPickerRows');
  var emptyBox = document.getElementById('entityPickerEmpty');
  var loadingBox = document.getElementById('entityPickerLoading');
  var countBox = document.getElementById('entityPickerCount');
  var moreBox = document.getElementById('entityPickerMore');
  var prefixEl = document.getElementById('entity_prefix');
  var personEl = document.getElementById('entity_person');
  var jobEl = document.getElementById('entity_job');
  var orgEl = document.getElementById('entity_org');
  var addBtn = document.getElementById('entityPickerAddBtn');
  var createModalEl = document.getElementById('entityQuickCreateModal');
  var createForm = document.getElementById('entityQuickCreateForm');
  var createError = document.getElementById('entityQuickCreateError');
  var createSaveBtn = document.getElementById('entityQuickCreateSave');
  var countTpl = <?= json_encode(__('corr.entity_picker_count'), JSON_UNESCAPED_UNICODE) ?>;
  var moreTpl = <?= json_encode(__('corr.entity_picker_more'), JSON_UNESCAPED_UNICODE) ?>;
  var personEmpty = <?= json_encode(__('corr.entity_picker_placeholder'), JSON_UNESCAPED_UNICODE) ?>;
  var orgEmpty = <?= json_encode(__('corr.entity_org_placeholder'), JSON_UNESCAPED_UNICODE) ?>;
  var createSavingLabel = <?= json_encode(__('corr.entity_picker_add_saving'), JSON_UNESCAPED_UNICODE) ?>;
  var createErrorFallback = <?= json_encode(__('corr.entity_picker_add_error'), JSON_UNESCAPED_UNICODE) ?>;
  var endpoint = <?= json_encode(base_url('api/entities_search.php'), JSON_UNESCAPED_UNICODE) ?>;
  var createEndpoint = <?= json_encode(base_url('api/entity_create.php'), JSON_UNESCAPED_UNICODE) ?>;
  var corrType = <?= json_encode($isIncoming ? 'incoming' : 'outgoing', JSON_UNESCAPED_UNICODE) ?>;
  var createSaveHtml = createSaveBtn ? createSaveBtn.innerHTML : '';

  // البحث يجري على الخادم، فلا تُحمَّل الجهات مسبقًا مهما بلغ عددها.
  var loadedQuery = null;
  var requestToken = 0;
  var searchTimer = null;

  function modal() {
    return (window.bootstrap && bootstrap.Modal) ? bootstrap.Modal.getOrCreateInstance(modalEl) : null;
  }

  function createModal() {
    if (!createModalEl || !window.bootstrap || !bootstrap.Modal) return null;
    return bootstrap.Modal.getOrCreateInstance(createModalEl);
  }

  function el(tag, className, text) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    if (text) node.textContent = text;
    return node;
  }

  function buildRow(item) {
    var tr = el('tr', 'entity-picker-row');
    tr.tabIndex = 0;
    tr.dataset.id = String(item.id);
    tr.dataset.party = item.party || '';
    tr.dataset.email = item.email || '';
    tr.dataset.prefix = item.prefix || '';
    tr.dataset.person = item.person || '';
    tr.dataset.job = item.job || '';
    tr.dataset.org = item.org || '';
    if (String(item.id) === String(hidden.value)) tr.classList.add('is-selected');

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
    badge.setAttribute('data-bs-toggle', 'tooltip');
    badge.setAttribute('data-bs-placement', 'top');
    badge.setAttribute('title', item.scope_title || '');
    var icon = el('i', 'bi ' + (item.scope_icon || ''));
    icon.setAttribute('aria-hidden', 'true');
    badge.appendChild(icon);
    badge.appendChild(el('span', 'visually-hidden', item.scope_title || ''));
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

    if (window.bootstrap && bootstrap.Tooltip) bootstrap.Tooltip.getOrCreateInstance(badge);

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
    var url = endpoint + '?type=' + encodeURIComponent(corrType) + '&q=' + encodeURIComponent(q);
    if (hidden.value) url += '&include_id=' + encodeURIComponent(hidden.value);

    fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
      .then(function (res) { return res.ok ? res.json() : Promise.reject(res.status); })
      .then(function (data) {
        if (token !== requestToken) return;
        loadedQuery = q;
        render(data);
      })
      .catch(function () {
        if (token !== requestToken) return;
        rowsBody.textContent = '';
        render({ items: [], total: 0 });
      })
      .then(function () {
        if (token === requestToken && loadingBox) loadingBox.hidden = true;
      });
  }

  function setPickedLine(subEl, nameEl, subText, nameText, emptyText) {
    var sub = subText || '';
    var name = nameText || '';
    if (subEl) {
      subEl.textContent = sub;
      subEl.hidden = sub === '';
    }
    if (nameEl) {
      nameEl.textContent = name !== '' ? name : emptyText;
      nameEl.classList.toggle('is-placeholder', name === '');
    }
  }

  function applyPicked(data) {
    data = data || {};
    setPickedLine(prefixEl, personEl, data.prefix, data.person, personEmpty);
    setPickedLine(jobEl, orgEl, data.job, data.org, orgEmpty);
  }

  function setPartyHidden(hide) {
    party.hidden = !!hide;
    if (hide) {
      party.removeAttribute('required');
    } else {
      party.setAttribute('required', 'required');
    }
  }

  function partyFromRow(row) {
    return (row.dataset.party || row.dataset.person || row.dataset.org || '').trim();
  }

  function select(row) {
    var id = row.dataset.id || '';
    hidden.value = id;
    hidden.dataset.email = row.dataset.email || '';
    party.value = partyFromRow(row);
    setPartyHidden(true);
    if (openBtn) openBtn.hidden = true;
    if (clearBtn) clearBtn.hidden = false;
    personBox.classList.remove('is-invalid');
    Array.prototype.forEach.call(rowsBody.children, function (r) {
      r.classList.toggle('is-selected', r === row);
    });
    applyPicked({
      prefix: row.dataset.prefix,
      person: row.dataset.person,
      job: row.dataset.job,
      org: row.dataset.org
    });
    hidden.dispatchEvent(new Event('change', { bubbles: true }));
    var m = modal();
    if (m) m.hide();
  }

  function selectItem(item) {
    if (!item || !item.id) return;
    hidden.value = String(item.id);
    hidden.dataset.email = item.email || '';
    party.value = (item.party || item.person || item.org || '').trim();
    setPartyHidden(true);
    if (openBtn) openBtn.hidden = true;
    if (clearBtn) clearBtn.hidden = false;
    personBox.classList.remove('is-invalid');
    applyPicked(item);
    hidden.dispatchEvent(new Event('change', { bubbles: true }));
    var cm = createModal();
    if (cm) cm.hide();
    var m = modal();
    if (m) m.hide();
  }

  function clearSelection() {
    hidden.value = '';
    hidden.dataset.email = '';
    party.value = '';
    setPartyHidden(false);
    if (openBtn) openBtn.hidden = false;
    if (clearBtn) clearBtn.hidden = true;
    Array.prototype.forEach.call(rowsBody.children, function (r) {
      r.classList.remove('is-selected');
    });
    applyPicked({});
    hidden.dispatchEvent(new Event('change', { bubbles: true }));
    party.focus();
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

  var reopenPickerAfterCreate = false;
  var createSubmitting = false;

  function resetQuickCreateFields() {
    if (!createForm) return;
    createForm.querySelectorAll('input, select, textarea').forEach(function (field) {
      if (!field.name) return;
      if (field.name === 'corr_type' || field.name === 'csrf_token') return;
      if (field.id === 'eqc_department_id') return;
      if (field.type === 'hidden' && field.name === 'department_id') return;
      if (field.type === 'checkbox' || field.type === 'radio') {
        field.checked = false;
        return;
      }
      if (field.tagName === 'SELECT') {
        field.selectedIndex = 0;
        return;
      }
      field.value = '';
    });
    var sortField = document.getElementById('eqc_entity_sort_order');
    if (sortField) {
      sortField.value = createForm.getAttribute('data-default-sort') || '10';
    }
    var honorWrap = document.getElementById('eqc_entityHonorificCustomWrap');
    if (honorWrap) honorWrap.classList.add('d-none');
    var honorAddRow = document.getElementById('eqc_entityHonorificAddRow');
    if (honorAddRow) honorAddRow.classList.add('d-none');
    var honorAddError = document.getElementById('eqc_entityHonorificAddError');
    if (honorAddError) {
      honorAddError.textContent = '';
      honorAddError.classList.add('d-none');
    }
  }

  function syncCreateDepartment() {
    if (!createForm) return;
    var scopeSel = document.getElementById('eqc_entity_department');
    var corrDept = document.getElementById('department_id');
    if (scopeSel && corrDept && corrDept.value) {
      var wanted = String(corrDept.value);
      var hasOption = Array.prototype.some.call(scopeSel.options, function (opt) {
        return String(opt.value) === wanted;
      });
      if (hasOption) scopeSel.value = wanted;
    }
  }

  function buildCreateFormData() {
    var fd = new FormData();
    var csrfName = createForm.getAttribute('data-csrf-name') || 'csrf_token';
    var csrfValue = createForm.getAttribute('data-csrf-value') || '';
    if (csrfValue) fd.append(csrfName, csrfValue);
    setQuickCreateFieldsActive(true);
    createForm.querySelectorAll('input, select, textarea').forEach(function (field) {
      var fieldName = field.name || field.dataset.eqcName || '';
      if (!fieldName || field.disabled) return;
      if (fieldName === 'csrf_token') return;
      if ((field.type === 'checkbox' || field.type === 'radio') && !field.checked) return;
      fd.append(fieldName, field.value);
    });
    return fd;
  }

  function showCreateModal() {
    if (!createForm || !createModalEl) return;
    setQuickCreateFieldsActive(true);
    resetQuickCreateFields();
    if (createError) {
      createError.textContent = '';
      createError.classList.add('d-none');
    }
    syncCreateDepartment();
    var cm = createModal();
    if (cm) cm.show();
    window.setTimeout(function () {
      var firstInput = document.getElementById('eqc_entity_title_prefix');
      if (firstInput) firstInput.focus();
    }, 200);
  }

  function openQuickCreate() {
    if (!createForm || !createModalEl) return;
    var picker = modal();
    if (picker && modalEl.classList.contains('show')) {
      reopenPickerAfterCreate = true;
      modalEl.addEventListener('hidden.bs.modal', function onPickerHidden() {
        modalEl.removeEventListener('hidden.bs.modal', onPickerHidden);
        showCreateModal();
      });
      picker.hide();
      return;
    }
    reopenPickerAfterCreate = false;
    showCreateModal();
  }

  function submitQuickCreate() {
    if (!createForm || createSubmitting) return;
    if (createError) {
      createError.textContent = '';
      createError.classList.add('d-none');
    }
    var nameInput = document.getElementById('eqc_entity_name_ar');
    if (nameInput && !String(nameInput.value || '').trim()) {
      nameInput.focus();
      if (createError) {
        createError.textContent = <?= json_encode(__('entities.name_required'), JSON_UNESCAPED_UNICODE) ?>;
        createError.classList.remove('d-none');
      }
      return;
    }
    createSubmitting = true;
    if (createSaveBtn) {
      createSaveBtn.disabled = true;
      createSaveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>' + createSavingLabel;
    }
    fetch(createEndpoint, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: buildCreateFormData()
    })
      .then(function (res) {
        return res.json().then(function (data) {
          if (!res.ok || !data || !data.ok) {
            throw new Error((data && data.error) || createErrorFallback);
          }
          return data;
        });
      })
      .then(function (data) {
        reopenPickerAfterCreate = false;
        selectItem(data.item);
        loadedQuery = null;
      })
      .catch(function (err) {
        if (createError) {
          createError.textContent = err && err.message ? err.message : createErrorFallback;
          createError.classList.remove('d-none');
        }
      })
      .then(function () {
        createSubmitting = false;
        if (createSaveBtn) {
          createSaveBtn.disabled = false;
          createSaveBtn.innerHTML = createSaveHtml;
        }
      });
  }

  personBox.addEventListener('click', open);
  personBox.addEventListener('keydown', onBoxKey);
  orgBox.addEventListener('click', open);
  orgBox.addEventListener('keydown', onBoxKey);
  if (openBtn) openBtn.addEventListener('click', open);
  if (clearBtn) clearBtn.addEventListener('click', clearSelection);
  if (addBtn) addBtn.addEventListener('click', openQuickCreate);

  if (createModalEl && createModalEl.parentNode !== document.body) {
    document.body.appendChild(createModalEl);
  }

  /** عزل حقول الإضافة السريعة حتى لا تُرسل/تُتحقق مع نموذج المعاملة */
  function setQuickCreateFieldsActive(on) {
    if (!createForm) return;
    createForm.querySelectorAll('input, select, textarea').forEach(function (field) {
      if (on) {
        if (field.dataset.eqcName && !field.getAttribute('name')) {
          field.setAttribute('name', field.dataset.eqcName);
        }
        field.disabled = false;
      } else {
        var n = field.getAttribute('name');
        if (n) {
          field.dataset.eqcName = n;
          field.removeAttribute('name');
        }
        field.disabled = true;
      }
    });
  }
  setQuickCreateFieldsActive(false);

  if (createSaveBtn) {
    createSaveBtn.addEventListener('click', submitQuickCreate);
  }
  if (createForm) {
    createForm.addEventListener('keydown', function (ev) {
      if (ev.key !== 'Enter' || !ev.target || ev.target.tagName === 'TEXTAREA') return;
      if (ev.target.closest && ev.target.closest('.entity-honorific-add')) return;
      ev.preventDefault();
      submitQuickCreate();
    });
  }
  if (createModalEl) {
    createModalEl.addEventListener('shown.bs.modal', function () {
      setQuickCreateFieldsActive(true);
    });
    createModalEl.addEventListener('hidden.bs.modal', function () {
      setQuickCreateFieldsActive(false);
      if (!reopenPickerAfterCreate || createSubmitting) return;
      reopenPickerAfterCreate = false;
      var m = modal();
      if (m) m.show();
    });
  }

  var form = hidden.closest('form');
  if (form) {
    form.addEventListener('submit', function (ev) {
      // تأكد أن حقول الإضافة السريعة لا تشارك في الإرسال حتى لو بقيت داخل النموذج
      setQuickCreateFieldsActive(false);
      if (hidden.value) {
        if (!party.value.trim()) {
          var personText = personEl && !personEl.classList.contains('is-placeholder') ? personEl.textContent.trim() : '';
          var orgText = orgEl && !orgEl.classList.contains('is-placeholder') ? orgEl.textContent.trim() : '';
          party.value = personText || orgText;
        }
        setPartyHidden(true);
        return;
      }
      setPartyHidden(false);
      if (!party.value.trim()) {
        ev.preventDefault();
        personBox.classList.add('is-invalid');
        party.hidden = false;
        party.setAttribute('required', 'required');
        party.focus();
        if (window.Swal) {
          Swal.fire({
            icon: 'warning',
            text: <?= json_encode(__('corr.party_required'), JSON_UNESCAPED_UNICODE) ?>,
            confirmButtonColor: '#3B4F6E'
          });
        }
      }
    });
  }

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
