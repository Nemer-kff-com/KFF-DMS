<?php
/**
 * حقول نموذج التعميم — يتطلب $departments و $item اختياري و $allDepartments للجمهور
 */
$item = $item ?? [];
$lockedIssued = (($item['status'] ?? '') === 'issued');
$v = static function (string $key, $default = '') use ($item) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST[$key])) {
        return $_POST[$key];
    }
    return $item[$key] ?? $default;
};
$allDepartments = $allDepartments ?? $departments;
$selectedAudienceDepts = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['audience_department_ids']) && is_array($_POST['audience_department_ids'])) {
    $selectedAudienceDepts = circular_normalize_department_ids($_POST['audience_department_ids']);
} elseif (!empty($item['id'])) {
    $selectedAudienceDepts = get_circular_department_ids((int) $item['id']);
}
$audience = (string) $v('audience', 'all');
if (!in_array($audience, ['all', 'departments'], true)) {
    $audience = 'all';
}
$corr = $item;
?>
<div class="admin-form-section">
    <div class="admin-form-section-title"><?= e(__('circular.section_basic')) ?></div>
    <div class="mb-3">
        <label class="form-label" for="circular_subject"><?= e(__('circular.subject')) ?> *</label>
        <input type="text" name="subject" id="circular_subject" class="form-control" required maxlength="500"
               value="<?= e((string) $v('subject')) ?>" <?= $lockedIssued ? 'readonly' : '' ?>>
    </div>
    <div class="row g-3">
        <?php if (!empty($item['ref_number'])): ?>
        <div class="col-lg-6">
            <div class="corr-field-card h-100">
                <div class="corr-field-card-title"><i class="bi bi-hash me-1"></i><?= e(__('circular.ref')) ?></div>
                <div class="corr-field-card-body">
                    <input type="text" class="form-control" dir="ltr" value="<?= e((string) $item['ref_number']) ?>" readonly>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <div class="col-lg-6">
            <div class="corr-field-card h-100">
                <div class="corr-field-card-title"><i class="bi bi-calendar3 me-1"></i><?= e(__('circular.date')) ?> *</div>
                <div class="corr-field-card-body">
                    <label class="visually-hidden" for="circular_date-display"><?= e(__('circular.date')) ?></label>
                    <?php
                    render_dms_gregorian_date_field([
                        'name' => 'circular_date',
                        'id' => 'circular_date',
                        'value' => (string) $v('circular_date', date('Y-m-d')),
                        'required' => true,
                        'readonly' => $lockedIssued,
                    ]);
                    ?>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="corr-field-card h-100">
                <div class="corr-field-card-title"><i class="bi bi-person-lines-fill me-1"></i><?= e(__('circular.issuing_dept')) ?> *</div>
                <div class="corr-field-card-body">
                    <label class="visually-hidden" for="department_id"><?= e(__('circular.issuing_dept')) ?></label>
                    <?php if ($lockedIssued): ?>
                        <?php
                        $lockedDeptId = (int) $v('department_id');
                        $lockedDept = null;
                        foreach ($allDepartments as $d) {
                            if ((int) ($d['id'] ?? 0) === $lockedDeptId) {
                                $lockedDept = $d;
                                break;
                            }
                        }
                        if (circular_has_officer_snapshot($item)) {
                            $snapRow = circular_officer_display_row($item);
                            $lockedName = correspondence_department_officer_label($snapRow);
                            $lockedOffice = correspondence_officer_subtitle($snapRow);
                        } else {
                            $lockedName = $lockedDept ? department_person_option_label($lockedDept) : department_display_name($item, 'department_name', 'department_name_en');
                            $lockedOffice = $lockedDept ? department_officer_subtitle($lockedDept) : '';
                        }
                        ?>
                        <input type="hidden" name="department_id" id="department_id" value="<?= $lockedDeptId ?>">
                        <div class="form-control corr-officer-locked bg-light"><?php render_officer_with_office($lockedName, $lockedOffice); ?></div>
                    <?php else: ?>
                        <?php
                        $deptOptionAsPerson = true;
                        $deptRequired = true;
                        require __DIR__ . '/../includes/corr_department_field.php';
                        ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="admin-form-section">
    <div class="row g-3">
        <div class="col-12">
            <div class="corr-field-card h-100">
                <div class="corr-field-card-title"><i class="bi bi-box-arrow-in-down me-1"></i><?= e(__('circular.audience')) ?></div>
                <div class="corr-field-card-body">
                    <?php if ($lockedIssued): ?>
                        <input type="hidden" name="audience" value="<?= e($audience) ?>">
                        <?php foreach ($selectedAudienceDepts as $did): ?>
                            <input type="hidden" name="audience_department_ids[]" value="<?= (int) $did ?>">
                        <?php endforeach; ?>
                        <div class="fw-semibold"><?= e(circular_audience_label($audience)) ?></div>
                        <?php if ($audience === 'departments'): ?>
                            <div class="d-flex flex-column gap-2 mt-2">
                                <?php foreach ($allDepartments as $d): ?>
                                    <?php if (!in_array((int) $d['id'], $selectedAudienceDepts, true)) continue; ?>
                                    <?php render_officer_with_office(department_person_option_label($d), department_officer_subtitle($d)); ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="corr-option-grid corr-option-grid-compact">
                            <label class="corr-option-card" for="audience_all">
                                <input class="form-check-input" type="radio" name="audience" id="audience_all" value="all" <?= $audience === 'all' ? 'checked' : '' ?>>
                                <span class="corr-option-title"><?= e(__('circular.audience.all')) ?></span>
                            </label>
                            <label class="corr-option-card" for="audience_departments">
                                <input class="form-check-input" type="radio" name="audience" id="audience_departments" value="departments" <?= $audience === 'departments' ? 'checked' : '' ?>>
                                <span class="corr-option-title"><?= e(__('circular.audience.departments')) ?></span>
                            </label>
                        </div>
                        <div id="circular-dept-targets" class="circular-audience-targets mt-3 <?= $audience === 'departments' ? '' : 'd-none' ?>">
                            <p class="admin-form-hint mb-2"><?= e(__('circular.audience_hint')) ?></p>
                            <div class="row g-2">
                                <?php foreach ($allDepartments as $d): ?>
                                    <div class="col-md-6">
                                        <div class="form-check circular-audience-check">
                                            <input class="form-check-input" type="checkbox" name="audience_department_ids[]"
                                                   id="aud_dept_<?= (int) $d['id'] ?>" value="<?= (int) $d['id'] ?>"
                                                   <?= in_array((int) $d['id'], $selectedAudienceDepts, true) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="aud_dept_<?= (int) $d['id'] ?>"><?php
                                                render_officer_with_office(department_person_option_label($d), department_officer_subtitle($d));
                                            ?></label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/circular_letter_fields.php'; ?>
<?php if (!$lockedIssued): ?>
<script>
(function () {
    var all = document.getElementById('audience_all');
    var depts = document.getElementById('audience_departments');
    var box = document.getElementById('circular-dept-targets');
    function sync() {
        if (!box) return;
        box.classList.toggle('d-none', !(depts && depts.checked));
    }
    if (all) all.addEventListener('change', sync);
    if (depts) depts.addEventListener('change', sync);
})();
</script>
<?php endif; ?>
