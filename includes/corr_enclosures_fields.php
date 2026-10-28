<?php
/**
 * نوع المرفقات المادية — يُختار أولاً ثم يُرفع الملف؛ يُسجَّل النوع تلقائياً عند الرفع.
 * يُضمَّن من الصادر/الوارد/الداخلي ودفعة الصادر.
 */
$formCorrId = (is_array($corr ?? null) && !empty($corr['id'])) ? (int) $corr['id'] : 0;
$enclosureTypes = attachment_types_for_form($formCorrId ?: null);
$enclosureStartEmpty = !empty($enclosureStartEmpty);
if ($enclosureStartEmpty) {
    $selectedEnclosures = [];
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedEnclosures = correspondence_enclosures_from_request($_POST);
} elseif ($formCorrId > 0) {
    $selectedEnclosures = correspondence_enclosures_map($formCorrId);
} else {
    $selectedEnclosures = [];
}

$defaultEnclosureId = 0;
foreach ($enclosureTypes as $type) {
    $code = strtolower(trim((string) ($type['code'] ?? '')));
    $nameAr = trim((string) ($type['name_ar'] ?? ''));
    if ($code === 'letters' || $nameAr === 'خطاب' || $nameAr === 'خطابات') {
        $defaultEnclosureId = (int) $type['id'];
        break;
    }
}
$enclosureTypeOptions = [];
$enclosureTypeLabelById = [];
foreach ($enclosureTypes as $type) {
    $tid = (int) $type['id'];
    $label = bilingual_label($type);
    $enclosureTypeOptions[] = [
        'id' => $tid,
        'label' => $label,
        'sort' => (int) ($type['sort_order'] ?? 0),
    ];
    $enclosureTypeLabelById[$tid] = $label;
}
$canAddEnclosureType = isset($user) && user_can('lookups.manage', $user);
if (!$enclosureTypes && !$canAddEnclosureType) {
    return;
}
$enclosureInDetailsGrid = !empty($enclosureInDetailsGrid);
$enclosureFieldClass = !empty($enclosureFieldClass)
    ? (string) $enclosureFieldClass
    : ($enclosureInDetailsGrid ? 'col-md-6 col-lg-3' : 'col-md-4');
static $enclosurePickerSeq = 0;
$enclosurePickerSeq++;
$enclosurePickerId = 'enclosurePicker-' . $enclosurePickerSeq;

// القائمة تعرض المحفوظ فقط؛ الافتراضي «خطاب» يظهر في القائمة المنسدلة دون تسجيل حتى يُرفع ملف
$activeTypeId = $defaultEnclosureId;
if ($selectedEnclosures !== []) {
    $activeTypeId = (int) array_key_last($selectedEnclosures);
}
if ($activeTypeId <= 0 && $enclosureTypeOptions !== []) {
    $activeTypeId = (int) $enclosureTypeOptions[0]['id'];
}
?>
<?php if (!$enclosureInDetailsGrid): ?>
<div class="<?= e(!empty($enclosureWrapClass) ? (string) $enclosureWrapClass : 'row g-3 align-items-end mt-1') ?>" id="<?= e($enclosurePickerId) ?>" data-enclosure-picker>
<?php endif; ?>
    <div class="<?= e($enclosureFieldClass) ?>"<?= $enclosureInDetailsGrid ? ' id="' . e($enclosurePickerId) . '" data-enclosure-picker' : '' ?>>
        <label class="form-label" for="<?= e($enclosurePickerId) ?>-type"><?= e(__('corr.enclosures')) ?></label>
        <div class="enclosure-inline">
            <select class="form-select" id="<?= e($enclosurePickerId) ?>-type" data-enclosure-type
                    data-default-type="<?= (int) $defaultEnclosureId ?>">
                <option value=""><?= e(__('corr.enclosure_select')) ?></option>
                <?php foreach ($enclosureTypeOptions as $opt): ?>
                    <option value="<?= (int) $opt['id'] ?>"<?= $activeTypeId === (int) $opt['id'] ? ' selected' : '' ?>><?= e($opt['label']) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="number" min="1" max="9999" value="1"
                   class="form-control enclosure-qty-field"
                   id="<?= e($enclosurePickerId) ?>-qty"
                   data-enclosure-qty
                   aria-label="<?= e(__('corr.enclosure_qty')) ?>"
                   title="<?= e(__('corr.enclosure_qty')) ?>">
        </div>
        <ul class="enclosure-list mt-2" data-enclosure-list<?= $selectedEnclosures === [] ? ' hidden' : '' ?>>
            <?php foreach ($selectedEnclosures as $tid => $qty):
                $tid = (int) $tid;
                if ($tid <= 0) {
                    continue;
                }
                $label = $enclosureTypeLabelById[$tid] ?? ('#' . $tid);
                $qty = max(1, (int) $qty);
                /* صف لكل وحدة — لا ندمج الأنواع المتكررة في سطر واحد */
                for ($qi = 0; $qi < $qty; $qi++):
                ?>
            <li class="enclosure-list-item" data-enclosure-id="<?= $tid ?>">
                <span class="enclosure-list-name"><?= e($label) ?></span>
                <input type="hidden" name="enclosures[]" value="<?= $tid ?>">
                <input type="hidden" class="enclosure-list-qty" name="enclosure_qty[]" value="1">
            </li>
                <?php endfor; ?>
            <?php endforeach; ?>
        </ul>
    </div>
<?php if (!$enclosureInDetailsGrid): ?>
</div>
<?php endif; ?>
<script>
(function () {
  var root = document.getElementById(<?= json_encode($enclosurePickerId, JSON_UNESCAPED_UNICODE) ?>);
  if (!root || root.dataset.ready === '1') { return; }
  root.dataset.ready = '1';

  var typeSel = root.querySelector('[data-enclosure-type]');
  var qtyInp = root.querySelector('[data-enclosure-qty]');
  var list = root.querySelector('[data-enclosure-list]');
  if (!typeSel || !list) { return; }

  function readQty(fallback) {
    var qty = qtyInp ? parseInt(qtyInp.value, 10) : NaN;
    if (!isFinite(qty) || qty < 1) qty = parseInt(fallback, 10) || 1;
    if (qty > 9999) qty = 9999;
    return qty;
  }

  function optionLabel(id) {
    var opt = typeSel.querySelector('option[value="' + id + '"]');
    return opt ? (opt.textContent || '').trim() : ('#' + id);
  }

  function currentType() {
    var id = String(typeSel.value || '');
    if (!id) {
      var def = String(typeSel.getAttribute('data-default-type') || '');
      if (def && typeSel.querySelector('option[value="' + def + '"]')) {
        typeSel.value = def;
        id = def;
      }
    }
    return id;
  }

  function syncListVisibility() {
    list.hidden = list.querySelectorAll('[data-enclosure-id]').length === 0;
  }

  function appendEnclosureRow(id, qty) {
    id = String(id || '');
    if (!id) return null;
    qty = parseInt(qty, 10);
    if (!isFinite(qty) || qty < 1) qty = 1;
    if (qty > 9999) qty = 9999;
    var label = optionLabel(id);

    var li = document.createElement('li');
    li.className = 'enclosure-list-item';
    li.setAttribute('data-enclosure-id', id);

    var name = document.createElement('span');
    name.className = 'enclosure-list-name';
    name.textContent = label;

    var hid = document.createElement('input');
    hid.type = 'hidden';
    hid.name = 'enclosures[]';
    hid.value = id;

    var qHid = document.createElement('input');
    qHid.type = 'hidden';
    qHid.className = 'enclosure-list-qty';
    qHid.name = 'enclosure_qty[]';
    qHid.value = String(qty);

    li.appendChild(name);
    if (qty > 1) {
      var qtyLabel = document.createElement('span');
      qtyLabel.className = 'enclosure-list-qty-label';
      qtyLabel.textContent = String(qty);
      li.appendChild(qtyLabel);
    }
    li.appendChild(hid);
    li.appendChild(qHid);
    list.appendChild(li);
    return { id: id, label: label };
  }

  function removeEnclosure(id, qty) {
    id = String(id || '');
    if (!id) return;
    qty = parseInt(qty, 10);
    if (!isFinite(qty) || qty < 1) qty = 1;
    var rows = list.querySelectorAll('[data-enclosure-id="' + id + '"]');
    var i;
    for (i = 0; i < qty && rows.length; i++) {
      /* احذف آخر صف مطابق حتى يبقى الترتيب متسقاً مع آخر رفع */
      rows[rows.length - 1].remove();
      rows = list.querySelectorAll('[data-enclosure-id="' + id + '"]');
    }
    syncListVisibility();
  }

  root._dmsAddEnclosure = function (fileCount) {
    var id = currentType();
    var n = parseInt(fileCount, 10);
    if (!isFinite(n) || n < 1) n = 1;
    var per = readQty(1);
    var info = null;
    var i;
    for (i = 0; i < n; i++) {
      info = appendEnclosureRow(id, per);
    }
    syncListVisibility();
    if (qtyInp) qtyInp.value = '1';
    return info;
  };
  root._dmsRemoveEnclosure = function (id, qty) {
    removeEnclosure(id, qty);
  };
  root._dmsEnclosureLabel = function () {
    var id = currentType();
    return id ? optionLabel(id) : '';
  };
  root._dmsEnclosureId = function () {
    return currentType();
  };

  if (!window._dmsEnclosureTypeCreateBound) {
    window._dmsEnclosureTypeCreateBound = true;
    document.addEventListener('click', function (e) {
      var openCreate = e.target.closest('[data-enclosure-open-create]');
      if (!openCreate || openCreate.disabled) return;
      var modalEl = document.getElementById('attachmentTypeCreateModal');
      if (modalEl && window.bootstrap) {
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
      }
    });
  }
  document.addEventListener('dms-attachment-type-created', function (e) {
    if (!e.detail || !e.detail.id) return;
    var id = String(e.detail.id);
    if (!typeSel.querySelector('option[value="' + id + '"]')) {
      var opt = document.createElement('option');
      opt.value = id;
      opt.textContent = e.detail.label || '';
      typeSel.appendChild(opt);
    }
    typeSel.value = id;
  });

})();
</script>
<?php
unset($enclosureStartEmpty);
if ($canAddEnclosureType) {
    require __DIR__ . '/attachment_type_create_modal.php';
}
?>
