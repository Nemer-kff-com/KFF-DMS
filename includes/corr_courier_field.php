<?php
/**
 * اختيار المراسل بقائمة مثل «موجهة من». الإضافة من شاشة المراسلين في القائمة الجانبية.
 * الصادر المُصدر يعرض الاسم المحفوظ على المعاملة، لا الاسم الحالي في الدليل.
 */
if (!function_exists('outgoing_couriers_list') && is_file(__DIR__ . '/../config/couriers.php')) {
    require_once __DIR__ . '/../config/couriers.php';
}
if (isset($v) && is_callable($v)) {
    $courierNameVal = trim((string) $v('courier_name'));
    $courierPhoneVal = trim((string) $v('courier_phone'));
    $courierIdVal = (int) $v('courier_id');
} else {
    $courierNameVal = trim((string) ($_POST['courier_name'] ?? ($corr['courier_name'] ?? '')));
    $courierPhoneVal = trim((string) ($_POST['courier_phone'] ?? ($corr['courier_phone'] ?? '')));
    $courierIdVal = (int) ($_POST['courier_id'] ?? ($corr['courier_id'] ?? 0));
}
$courierItems = function_exists('outgoing_couriers_list') ? outgoing_couriers_list() : [];
$courierFrozen = false;
if (!isset($_POST['courier_name']) && !empty($corr) && is_array($corr) && ($corr['type'] ?? '') === 'outgoing') {
    $storedName = trim((string) ($corr['courier_name'] ?? ''));
    $stillOpen = function_exists('outgoing_prep_is_open') && outgoing_prep_is_open($corr);
    $courierFrozen = $storedName !== '' && !$stillOpen;
}
if (!$courierFrozen && !isset($_POST['courier_name']) && $courierIdVal > 0) {
    foreach ($courierItems as $ci) {
        if ((int) ($ci['id'] ?? 0) === $courierIdVal) {
            $courierNameVal = trim((string) ($ci['name'] ?? ''));
            $courierPhoneVal = trim((string) ($ci['phone'] ?? ''));
            break;
        }
    }
}
$courierMatched = false;
?>
<div class="corr-field-card h-100">
    <div class="corr-field-card-title" title="<?= e(__('corr.courier_tip')) ?>"><i class="bi bi-person-badge me-1"></i><?= e(__('corr.courier')) ?></div>
    <div class="corr-field-card-body">
        <label class="visually-hidden" for="courier_pick"><?= e(__('corr.courier')) ?></label>
        <input type="hidden" name="courier_id" id="courier_id" value="<?= $courierIdVal > 0 ? (int) $courierIdVal : '' ?>">
        <input type="hidden" name="courier_name" id="courier_name" value="<?= e($courierNameVal) ?>">
        <input type="hidden" name="courier_phone" id="courier_phone" value="<?= e($courierPhoneVal) ?>">
        <select id="courier_pick" class="form-select" data-courier-select="1" title="<?= e(__('corr.courier_tip')) ?>">
            <option value="" data-name="" data-phone=""><?= e(__('corr.select_directed')) ?></option>
            <?php foreach ($courierItems as $opt): ?>
                <?php
                $optId = (int) ($opt['id'] ?? 0);
                $optName = trim((string) ($opt['name'] ?? ''));
                $optPhone = trim((string) ($opt['phone'] ?? ''));
                $isStored = $courierIdVal > 0 && $optId === $courierIdVal;
                if ($isStored && $courierFrozen) {
                    $optName = $courierNameVal;
                    $optPhone = $courierPhoneVal;
                }
                $optSel = $isStored || ($courierIdVal === 0 && $optName !== '' && $optName === $courierNameVal);
                if ($optSel) {
                    $courierMatched = true;
                }
                ?>
                <option value="<?= $optId ?>" data-name="<?= e($optName) ?>" data-phone="<?= e($optPhone) ?>"
                        title="<?= e($optPhone) ?>" <?= $optSel ? 'selected' : '' ?>>
                    <?= e($optName) ?>
                </option>
            <?php endforeach; ?>
            <?php if ($courierNameVal !== '' && !$courierMatched): ?>
                <option value="<?= $courierIdVal > 0 ? (int) $courierIdVal : '' ?>" data-name="<?= e($courierNameVal) ?>" data-phone="<?= e($courierPhoneVal) ?>"
                        title="<?= e($courierPhoneVal) ?>" selected><?= e($courierNameVal) ?></option>
            <?php endif; ?>
        </select>
    </div>
</div>

<?php if (empty($GLOBALS['_dms_courier_js'])): $GLOBALS['_dms_courier_js'] = true; ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var select = document.querySelector('select[data-courier-select="1"]');
  var idHidden = document.getElementById('courier_id');
  var nameHidden = document.getElementById('courier_name');
  var phoneHidden = document.getElementById('courier_phone');
  var card = document.getElementById('corr-courier-card');
  var elec = document.getElementById('intend_electronic');
  if (!select || !nameHidden) return;

  function syncFromSelect() {
    var opt = select.options[select.selectedIndex];
    if (idHidden) {
      idHidden.value = opt && opt.value ? opt.value : '';
    }
    nameHidden.value = opt ? (opt.getAttribute('data-name') || '') : '';
    if (phoneHidden) {
      phoneHidden.value = opt ? (opt.getAttribute('data-phone') || '') : '';
    }
  }

  select.addEventListener('change', syncFromSelect);

  function syncCourierCard() {
    if (!card || !elec) return;
    card.hidden = !!elec.checked;
    if (elec.checked) {
      select.selectedIndex = 0;
      syncFromSelect();
    }
  }
  if (elec) {
    elec.addEventListener('change', syncCourierCard);
    syncCourierCard();
  }
});
</script>
<?php endif; ?>
