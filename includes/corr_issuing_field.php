<?php
/**
 * حقل «جهة التوقيع (موجهة من)» للصادر — القسم المعدّ يُحدَّد منه تلقائياً (department_id مخفي).
 * يتطلب: $user, $departments. اختياري: $v, $corr
 */
$user = $user ?? (function_exists('current_user') ? current_user() : null);
$departments = $departments ?? [];
$signCorr = isset($corr) && is_array($corr) ? $corr : null;
$signOptions = outgoing_sign_from_options($user, $departments, $signCorr);

$signPosted = isset($v) ? (string) $v('issuing_department_id', '') : (string) ($_POST['issuing_department_id'] ?? '');
if ($signPosted === '' && $signCorr) {
    $signPosted = (string) (correspondence_issuing_from_row($signCorr) ?? '');
}
if ($signPosted === '') {
    $signPrimary = function_exists('user_primary_department_id') ? user_primary_department_id($user) : 0;
    $signPosted = $signPrimary > 0 ? (string) $signPrimary : '';
}
if ($signPosted === '' && count($signOptions) === 1) {
    $signPosted = (string) $signOptions[0]['id'];
}
$signSelected = null;
foreach ($signOptions as $opt) {
    if ((string) $opt['id'] === $signPosted) {
        $signSelected = $opt;
        break;
    }
}
?>
<label class="visually-hidden" for="issuing_department_id"><?= e(__('corr.issuing_department')) ?></label>
<?php if (count($signOptions) === 1): ?>
    <?php $signSelected = $signOptions[0]; ?>
    <input type="hidden" name="issuing_department_id" id="issuing_department_id" value="<?= (int) $signSelected['id'] ?>">
    <div class="form-control corr-officer-locked bg-light"><?php render_officer_with_office((string) $signSelected['label'], (string) ($signSelected['office'] ?? '')); ?></div>
<?php else: ?>
    <select name="issuing_department_id" id="issuing_department_id" class="form-select corr-officer-pick" required data-sign-from="1"
            title="<?= e(__('corr.issuing_department_hint')) ?>">
        <?php if (!$signSelected): ?>
            <option value=""><?= e(__('corr.select_directed')) ?></option>
        <?php endif; ?>
        <?php foreach ($signOptions as $opt):
            $signOffice = (string) ($opt['office'] ?? '');
            if ($signOffice === (string) $opt['label']) {
                $signOffice = '';
            }
        ?>
            <option value="<?= (int) $opt['id'] ?>" data-prep="<?= (int) $opt['prep'] ?>"
                    data-office="<?= e($signOffice) ?>"
                    title="<?= e((string) ($opt['office'] ?? '')) ?>"
                    <?= $signSelected && $signSelected['id'] === $opt['id'] ? 'selected' : '' ?>>
                <?= e($opt['label']) ?>
            </option>
        <?php endforeach; ?>
    </select>
<?php endif; ?>
<input type="hidden" name="department_id" id="department_id" value="<?= $signSelected ? (int) $signSelected['prep'] : '' ?>">
<?php if (empty($GLOBALS['_dms_sign_from_js'])): $GLOBALS['_dms_sign_from_js'] = true; ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var select = document.querySelector('select[data-sign-from="1"]');
  var prep = document.getElementById('department_id');
  if (!select || !prep) return;
  select.addEventListener('change', function () {
    var opt = select.options[select.selectedIndex];
    prep.value = opt ? (opt.getAttribute('data-prep') || '') : '';
  });
});
</script>
<?php endif; ?>
