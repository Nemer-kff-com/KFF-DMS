<?php
/**
 * حقول نموذج المعاملة الداخلية.
 * يتطلب: $allDepartments, $fromDepartments, $canChooseAll,
 *         $postedFrom, $postedTo, $priorityRows, $confidentialityRows,
 *         $categories, $user
 */
?>
                <div class="admin-form-section">
                    <div class="admin-form-section-title"><?= e(__('corr.section_basic')) ?></div>
                    <div class="mb-3">
                        <label class="form-label" for="corr_subject"><?= e(__('corr.subject_required')) ?></label>
                        <input type="text" name="subject" id="corr_subject" class="form-control" required value="<?= e((string) ($_POST['subject'] ?? '')) ?>">
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="corr-field-card corr-sign-card">
                            <div class="corr-field-card-title"><i class="bi bi-box-arrow-up me-1"></i><?= e(__('internal.from_department')) ?> *</div>
                            <div class="corr-field-card-body">
                            <label class="visually-hidden" for="from_department_id"><?= e(__('internal.from_department')) ?></label>
                            <?php if ($canChooseAll): ?>
                                <select name="from_department_id" id="from_department_id" class="form-select corr-officer-pick" required>
                                    <option value=""><?= e(__('corr.select_directed')) ?></option>
                                    <?php foreach ($allDepartments as $d): ?>
                                        <option value="<?= (int) $d['id'] ?>" title="<?= e(department_display_name($d)) ?>" data-office="<?= e(department_officer_subtitle($d)) ?>" <?= $postedFrom === (string) $d['id'] ? 'selected' : '' ?>><?= e(department_person_option_label($d)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php elseif (count($fromDepartments) > 1): ?>
                                <select name="from_department_id" id="from_department_id" class="form-select corr-officer-pick" required>
                                    <?php foreach ($fromDepartments as $d): ?>
                                        <option value="<?= (int) $d['id'] ?>" title="<?= e(department_display_name($d)) ?>" data-office="<?= e(department_officer_subtitle($d)) ?>" <?= $postedFrom === (string) $d['id'] ? 'selected' : '' ?>><?= e(department_person_option_label($d)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <?php
                                $lockedName = '';
                                $lockedOffice = '';
                                foreach ($fromDepartments as $d) {
                                    if ((int) $d['id'] === (int) $postedFrom) {
                                        $lockedName = department_person_option_label($d);
                                        $lockedOffice = department_officer_subtitle($d);
                                        break;
                                    }
                                }
                                if ($lockedName === '') {
                                    $lockedName = department_display_name([
                                        'name' => (string) ($user['department_name'] ?? ''),
                                        'name_en' => (string) ($user['department_name_en'] ?? ''),
                                    ]);
                                }
                                ?>
                                <input type="hidden" name="from_department_id" id="from_department_id" value="<?= (int) $postedFrom ?>">
                                <div class="form-control corr-officer-locked bg-light"><?php render_officer_with_office($lockedName, $lockedOffice); ?></div>
                            <?php endif; ?>
                            </div>
                            </div>
                        </div>
                        <div class="col-md-6 d-flex flex-column gap-3">
                            <div class="corr-field-card corr-sign-card">
                            <div class="corr-field-card-title" title="<?= e(__('internal.route_hint')) ?>"><i class="bi bi-box-arrow-in-down me-1"></i><?= e(__('internal.to_department')) ?> *</div>
                            <div class="corr-field-card-body">
                            <label class="visually-hidden" for="to_department_id"><?= e(__('internal.to_department')) ?></label>
                            <select name="to_department_id" id="to_department_id" class="form-select corr-officer-pick" required title="<?= e(__('internal.route_hint')) ?>">
                                <option value=""><?= e(__('corr.select_directed')) ?></option>
                                <?php foreach ($allDepartments as $d): ?>
                                    <option value="<?= (int) $d['id'] ?>" title="<?= e(department_display_name($d)) ?>" data-office="<?= e(department_officer_subtitle($d)) ?>" <?= $postedTo === (string) $d['id'] ? 'selected' : '' ?>><?= e(department_person_option_label($d)) ?></option>
                                <?php endforeach; ?>
                            </select>
                            </div>
                            </div>
                            <div class="corr-field-card corr-sign-card">
                                <?php
                                $ccHintKey = (function_exists('corr_cc_waits_for_approval') && corr_cc_waits_for_approval('internal'))
                                    ? 'cc.field_hint'
                                    : 'cc.field_hint_now';
                                ?>
                                <div class="corr-field-card-title" title="<?= e(__($ccHintKey)) ?>"><i class="bi bi-eye me-1"></i><?= e(__('cc.field')) ?></div>
                                <div class="corr-field-card-body">
                                    <?php
                                    $ccType = 'internal';
                                    $ccExcludeUserId = (int) $user['id'];
                                    require __DIR__ . '/corr_cc_field.php';
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="admin-form-section">
                    <div class="admin-form-section-title"><?= e(__('corr.section_meta')) ?></div>
                    <div class="row g-3 corr-form-details">
                        <div class="col-md-6 col-xl-4">
                            <div class="corr-field-card h-100">
                                <div class="corr-field-card-title"><i class="bi bi-calendar3 me-1"></i><?= e(__('corr.group_dates')) ?></div>
                                <div class="row g-2 corr-field-card-body">
                                    <div class="col-6">
                                        <label class="form-label" for="corr_date-display"><?= e(__('corr.date')) ?> *</label>
                                        <?php
                                        render_dms_gregorian_date_field([
                                            'name' => 'corr_date',
                                            'id' => 'corr_date',
                                            'value' => (string) ($_POST['corr_date'] ?? date('Y-m-d')),
                                            'required' => true,
                                        ]);
                                        ?>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label" for="reply_due-display"><?= e(__('corr.reply_due')) ?></label>
                                        <?php
                                        render_dms_gregorian_date_field([
                                            'name' => 'reply_due',
                                            'id' => 'reply_due',
                                            'value' => (string) ($_POST['reply_due'] ?? ''),
                                            /* فارغ افتراضياً */
                                        ]);
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-xl-4">
                            <div class="corr-field-card h-100">
                                <div class="corr-field-card-title"><i class="bi bi-shield-lock me-1"></i><?= e(__('corr.group_priority')) ?></div>
                                <div class="row g-2 corr-field-card-body">
                                    <div class="col-6">
                                        <label class="form-label" for="priority"><?= e(__('corr.priority')) ?></label>
                                        <select name="priority" id="priority" class="form-select">
                                            <?php foreach ($priorityRows as $p): ?>
                                                <option value="<?= e($p['code']) ?>" <?= ((string) ($_POST['priority'] ?? 'normal') === (string) $p['code']) ? 'selected' : '' ?>><?= e(bilingual_label($p)) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label" for="confidentiality"><?= e(__('corr.confidentiality')) ?></label>
                                        <select name="confidentiality" id="confidentiality" class="form-select">
                                            <?php foreach ($confidentialityRows as $c): ?>
                                                <option value="<?= e($c['code']) ?>" <?= ((string) ($_POST['confidentiality'] ?? 'normal') === (string) $c['code']) ? 'selected' : '' ?>><?= e(bilingual_label($c)) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12 col-xl-4">
                            <div class="corr-field-card h-100">
                                <div class="corr-field-card-title"><i class="bi bi-tags me-1"></i><?= e(__('corr.group_classification')) ?></div>
                                <div class="row g-2 corr-field-card-body">
                                    <div class="col-6">
                                        <label class="form-label" for="category_id"><?= e(__('corr.category')) ?></label>
                                        <select name="category_id" id="category_id" class="form-select">
                                            <option value=""><?= e(__('common.none')) ?></option>
                                            <?php foreach ($categories as $cat): ?>
                                                <option value="<?= (int) $cat['id'] ?>" <?= ((string) ($_POST['category_id'] ?? ($categories[0]['id'] ?? '')) === (string) $cat['id']) ? 'selected' : '' ?>><?= e(category_display_name($cat)) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label" for="status"><?= e(__('corr.status')) ?></label>
                                        <input type="hidden" name="status" value="new">
                                        <input type="text" id="status" class="form-control" value="<?= e(status_label('new')) ?>" readonly
                                               title="<?= e(__('corr.status_locked_internal')) ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="admin-form-section admin-form-section-last">
                    <div class="row g-3">
                        <div class="col-lg-6">
                            <div class="corr-field-card h-100">
                                <?php require __DIR__ . '/corr_attachments_card_title.php'; ?>
                                <div class="corr-field-card-body">
                                <?php
                                $dmsFile = [
                                    'name' => 'attachments[]',
                                    'id' => 'attachment',
                                    'multiple' => true,
                                    'accept' => '.pdf,.jpg,.jpeg,.png,.gif,.webp',
                                    'label' => __('corr.attach'),
                                    'hint' => '',
                                    'browse' => __('file.browse'),
                                    'placeholder' => __('file.placeholder'),
                                    'selected' => __('file.selected'),
                                ];
                                require __DIR__ . '/corr_attachments_upload_block.php';
                                ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="corr-field-card h-100">
                                <div class="corr-field-card-title"><i class="bi bi-journal-text me-1"></i><?= e(__('corr.notes')) ?></div>
                                <div class="corr-field-card-body">
                                    <label class="visually-hidden" for="notes"><?= e(__('corr.notes')) ?></label>
                                    <textarea name="notes" id="notes" class="form-control" rows="1"><?= e((string) ($_POST['notes'] ?? '')) ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
