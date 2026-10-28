<?php
/**
 * حقل القسم المختص: كل الأقسام للمدير/الأرشيف، قائمة المسندة إن تعددت، ثابت إن كان قسماً واحداً.
 * يتطلب: $user, $departments. اختياري: $v, $corr, $deptRequired
 */
$user = $user ?? (function_exists('current_user') ? current_user() : null);
$departments = $departments ?? [];
$canChooseAll = function_exists('can_choose_correspondence_department') && can_choose_correspondence_department($user);
$postedDept = isset($v) ? (string) $v('department_id', '') : (string) ($_POST['department_id'] ?? '');
$assignedDepts = function_exists('departments_visible_to_user')
    ? departments_visible_to_user($user, $departments)
    : $departments;
$primaryId = function_exists('user_primary_department_id')
    ? user_primary_department_id($user)
    : (int) ($user['department_id'] ?? 0);
$deptOptionAsPerson = !empty($deptOptionAsPerson);
$deptPersonMap = ($deptOptionAsPerson && function_exists('department_person_option_map'))
    ? department_person_option_map($canChooseAll ? $departments : $assignedDepts)
    : [];
$deptOptionLabel = static function (array $d) use ($deptPersonMap): string {
    $id = (int) ($d['id'] ?? 0);
    if ($id > 0 && isset($deptPersonMap[$id])) {
        return $deptPersonMap[$id];
    }
    return department_display_name($d);
};
$deptOptionTitle = static function (array $d): string {
    return department_display_name($d);
};

if ($postedDept === '' && !empty($corr['department_id'])) {
    $postedDept = (string) $corr['department_id'];
}
if ($postedDept === '' && $primaryId > 0) {
    $postedDept = (string) $primaryId;
}

if ($canChooseAll):
?>
    <select name="department_id" id="department_id" class="form-select<?= $deptOptionAsPerson ? ' corr-officer-pick' : '' ?>"<?= !empty($deptRequired) ? ' required' : '' ?>>
        <option value=""><?= e($deptOptionAsPerson ? __('corr.select_directed') : __('corr.select_dept')) ?></option>
        <?php foreach ($departments as $d): ?>
            <option value="<?= (int) $d['id'] ?>" data-sort="<?= (int) ($d['sort_order'] ?? 0) ?>" title="<?= e($deptOptionTitle($d)) ?>"<?= $deptOptionAsPerson ? ' data-office="' . e(department_officer_subtitle($d)) . '"' : '' ?> <?= $postedDept === (string) $d['id'] ? 'selected' : '' ?>>
                <?= e($deptOptionAsPerson ? department_person_option_label($d) : $deptOptionLabel($d)) ?>
            </option>
        <?php endforeach; ?>
    </select>
<?php elseif (count($assignedDepts) > 1): ?>
    <select name="department_id" id="department_id" class="form-select<?= $deptOptionAsPerson ? ' corr-officer-pick' : '' ?>">
        <?php foreach ($assignedDepts as $d): ?>
            <option value="<?= (int) $d['id'] ?>" data-sort="<?= (int) ($d['sort_order'] ?? 0) ?>" title="<?= e($deptOptionTitle($d)) ?>"<?= $deptOptionAsPerson ? ' data-office="' . e(department_officer_subtitle($d)) . '"' : '' ?> <?= $postedDept === (string) $d['id'] ? 'selected' : '' ?>>
                <?= e($deptOptionAsPerson ? department_person_option_label($d) : $deptOptionLabel($d)) ?>
            </option>
        <?php endforeach; ?>
    </select>
<?php else:
    $lockedId = $primaryId;
    if (!empty($corr['department_id'])) {
        $lockedId = (int) $corr['department_id'];
    }
    $lockedName = department_display_name([
        'name' => (string) ($user['department_name'] ?? ''),
        'name_en' => (string) ($user['department_name_en'] ?? ''),
    ]);
    if (!empty($corr['department_name']) || !empty($corr['department_name_en'])) {
        $fromCorr = department_display_name($corr, 'department_name', 'department_name_en');
        if ($fromCorr !== '') {
            $lockedName = $fromCorr;
        }
    }
    if ($lockedName === '' && $lockedId > 0) {
        foreach ($assignedDepts as $d) {
            if ((int) $d['id'] === $lockedId) {
                $lockedName = $deptOptionLabel($d);
                break;
            }
        }
    }
    $lockedOffice = '';
    if ($deptOptionAsPerson && $lockedId > 0) {
        foreach ($assignedDepts as $d) {
            if ((int) $d['id'] === $lockedId) {
                $lockedName = department_person_option_label($d);
                $lockedOffice = department_officer_subtitle($d);
                break;
            }
        }
        if ($lockedName === '' && isset($deptPersonMap[$lockedId])) {
            $lockedName = $deptPersonMap[$lockedId];
        }
    }
?>
    <input type="hidden" name="department_id" id="department_id" value="<?= (int) $lockedId ?>">
    <?php if ($deptOptionAsPerson): ?>
    <div class="form-control corr-officer-locked bg-light"><?php render_officer_with_office($lockedName, $lockedOffice); ?></div>
    <?php else: ?>
    <input type="text" class="form-control" value="<?= e($lockedName !== '' ? $lockedName : __('common.none')) ?>" readonly>
    <?php endif; ?>
<?php endif; ?>
