<?php
/**
 * حقول مشتركة لنموذج وارد/صادر
 * يتطلب: $departments, $entities, $categories, $type, وقيم اختيارية من $corr أو $_POST
 */
$v = function (string $key, $default = '') use ($corr) {
    if (isset($_POST[$key])) {
        return $_POST[$key];
    }
    if (!empty($corr) && array_key_exists($key, $corr) && $corr[$key] !== null) {
        return $corr[$key];
    }
    return $default;
};
$isIncoming = ($type ?? 'incoming') === 'incoming';
$priorityRows = priority_levels_list();
$confidentialityRows = confidentiality_levels_list();
$selectedEntityId = (int) $v('entity_id');

// تُجلب الجهة المختارة وحدها؛ باقي الجهات تُبحث على الخادم عند فتح القائمة.
$selectedEntityRow = $selectedEntityId > 0 ? get_entity($selectedEntityId) : null;
$selectedEntity = $selectedEntityRow ? entity_picker_payload($selectedEntityRow, $isIncoming) : null;

$selectedEntityEmail = $selectedEntity['email'] ?? '';
$selectedPrefix = $selectedEntity['prefix'] ?? '';
$selectedPerson = $selectedEntity['person'] ?? '';
$selectedJob = $selectedEntity['job'] ?? '';
$selectedOrg = $selectedEntity['org'] ?? '';
$hasPickedEntity = $selectedEntityId > 0;
if (
    !empty($corr)
    && is_array($corr)
    && correspondence_has_party_snapshot($corr)
    && (int) ($corr['entity_id'] ?? 0) === $selectedEntityId
    && $selectedEntityId > 0
) {
    $archivedParty = correspondence_archived_party($corr);
    $selectedPrefix = $archivedParty['prefix'];
    $selectedPerson = $archivedParty['person'];
    $selectedJob = $archivedParty['job'];
    $selectedOrg = $archivedParty['org'];
}
?>
<div class="admin-form-section">
    <div class="admin-form-section-title"><?= e(__('corr.section_basic')) ?></div>
    <div class="mb-3">
        <label class="form-label" for="corr_subject"><?= e(__('corr.subject_required')) ?></label>
        <input type="text" name="subject" id="corr_subject" class="form-control" required value="<?= e((string)$v('subject')) ?>">
    </div>
    <?php ob_start(); ?>
            <div class="corr-field-card">
            <div class="corr-field-card-title"><i class="bi bi-link-45deg me-1"></i><?= e(__('corr.group_ref')) ?></div>
            <div class="corr-field-card-body">
            <?php
            $externalRefLabel = $isIncoming ? __('corr.external_ref') : __('corr.external_ref_out');
            $externalDateLabel = $isIncoming ? __('corr.external_date') : __('corr.external_date_out');
            ?>
            <div class="corr-external-ref-row">
                <div class="corr-external-ref-no">
                    <label class="visually-hidden" for="external_ref"><?= e($externalRefLabel) ?></label>
                    <input type="text" name="external_ref" id="external_ref" class="form-control" value="<?= e((string)$v('external_ref')) ?>"
                           placeholder="<?= e($externalRefLabel) ?>"
                           title="<?= e($isIncoming ? __('corr.external_ref_hint') : __('corr.external_ref_out_hint')) ?>">
                </div>
                <div class="corr-external-ref-date">
                    <label class="visually-hidden" for="external_date-display"><?= e($externalDateLabel) ?></label>
                    <?php
                    /* عند الإنشاء اترك تاريخ المرجع فارغاً؛ عند التعديل اعرض المحفوظ فقط */
                    $externalDateVal = '';
                    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                        $externalDateVal = corr_external_date_value($v('external_date')) ?? '';
                    } elseif (!empty($corr['id'])) {
                        $externalDateVal = corr_external_date_value($v('external_date')) ?? '';
                    }
                    render_dms_gregorian_date_field([
                        'name' => 'external_date',
                        'id' => 'external_date',
                        'value' => $externalDateVal,
                        'title' => $externalDateLabel . ' — ' . __('corr.external_date_hint'),
                    ]);
                    ?>
                </div>
            </div>
            <?php if (isset($linkables) && is_array($linkables)): ?>
                <?php
                $linkedSelected = 0;
                if (isset($_POST['linked_id'])) {
                    $linkedSelected = (int) $_POST['linked_id'];
                } elseif (is_array($corr ?? null) && isset($corr['linked_id'])) {
                    $linkedSelected = (int) $corr['linked_id'];
                } elseif (!empty($prelink)) {
                    $linkedSelected = (int) $prelink;
                }
                $needLinkType = $isIncoming ? 'outgoing' : 'incoming';
                $linkedPayload = null;
                if ($linkedSelected > 0 && function_exists('get_correspondence')) {
                    $linkedCorr = get_correspondence($linkedSelected);
                    $viewer = function_exists('current_user') ? current_user() : null;
                    if (
                        is_array($linkedCorr)
                        && ($linkedCorr['type'] ?? '') === $needLinkType
                        && (!$viewer || !function_exists('user_can_access_correspondence') || user_can_access_correspondence($linkedCorr, $viewer))
                    ) {
                        $linkedPayload = linkable_correspondence_payload($linkedCorr);
                    } else {
                        $linkedSelected = 0;
                    }
                }
                $linkedRef = (string) ($linkedPayload['ref'] ?? '');
                $linkedSubject = (string) ($linkedPayload['subject'] ?? '');
                $hasLinked = $linkedSelected > 0 && $linkedRef !== '';
                $linkHint = $isIncoming ? __('corr.link_out_hint') : __('corr.link_in_hint');
                ?>
                <div class="mt-2" title="<?= e($linkHint) ?>">
                <div class="entity-picker-control">
                    <input type="hidden" name="linked_id" id="linked_id" value="<?= $hasLinked ? (int) $linkedSelected : '' ?>">
                    <div class="entity-picked-stack">
                        <div class="entity-picked-box" id="linked_box" role="button" tabindex="0"
                             title="<?= e(__('corr.link_picker_open')) ?>"
                             aria-label="<?= e($isIncoming ? __('corr.link_out') : __('corr.link_in')) ?>">
                            <span class="entity-picker-sub" id="linked_ref" <?= $hasLinked ? '' : 'hidden' ?> dir="ltr"><?= e($linkedRef) ?></span>
                            <span class="entity-picker-name<?= $hasLinked ? '' : ' is-placeholder' ?>" id="linked_subject"><?= e($hasLinked ? $linkedSubject : ($isIncoming ? __('corr.link_picker_placeholder_out') : __('corr.link_picker_placeholder_in'))) ?></span>
                        </div>
                    </div>
                    <div class="entity-picker-btns">
                        <button type="button" class="btn btn-kff entity-picker-btn" id="linked_pick_btn"
                                title="<?= e(__('corr.link_picker_open')) ?>">
                            <i class="bi bi-search"></i>
                        </button>
                        <button type="button" class="btn btn-outline-secondary entity-picker-btn" id="linked_clear_btn"
                                title="<?= e(__('corr.link_picker_clear')) ?>" <?= $hasLinked ? '' : 'hidden' ?>>
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>
                </div>
            <?php endif; ?>
            </div>
            </div>
    <?php $refCardHtml = ob_get_clean(); ?>
    <div class="row g-3">
        <?php ob_start(); ?>
        <div class="col-md-6<?= $isIncoming ? ' d-flex flex-column gap-3' : '' ?>">
            <div class="corr-field-card<?= $isIncoming ? ' corr-sign-card' : ' h-100' ?>">
            <div class="corr-field-card-title"><i class="bi bi-person-lines-fill me-1"></i><?php if ($isIncoming): ?><span title="<?= e(__('corr.party_from_tip')) ?>" data-bs-toggle="tooltip" data-bs-placement="top"><?= e(__('corr.party_from')) ?></span><?php else: ?><?= e(__('corr.party_to')) ?><?php endif; ?> *</div>
            <div class="corr-field-card-body">
            <div class="entity-picker-control entity-picker-control-stacked">
                <input type="hidden" name="entity_id" id="entity_id"
                       value="<?= $hasPickedEntity ? (int) $selectedEntityId : '' ?>"
                       data-email="<?= e($selectedEntityEmail) ?>">
                <div class="entity-picked-stack">
                    <div class="entity-action-row">
                        <div class="entity-picked-box" id="entity_person_box" role="button" tabindex="0"
                             title="<?= e(__('corr.entity_picker_open')) ?>"
                             aria-label="<?= e($isIncoming ? __('corr.party_from') : __('corr.party_to')) ?>">
                            <span class="entity-picker-sub" id="entity_prefix" <?= $selectedPrefix !== '' ? '' : 'hidden' ?>><?= e($selectedPrefix) ?></span>
                            <span class="entity-picker-name<?= $selectedPerson === '' ? ' is-placeholder' : '' ?>" id="entity_person"><?= e($selectedPerson !== '' ? $selectedPerson : __('corr.entity_picker_placeholder')) ?></span>
                        </div>
                        <div class="entity-picker-btns">
                            <button type="button" class="btn btn-kff entity-picker-btn" id="entity_pick_btn"
                                    title="<?= e(__('corr.entity_picker_open')) ?>" <?= $hasPickedEntity ? 'hidden' : '' ?>>
                                <i class="bi bi-search"></i>
                            </button>
                            <button type="button" class="btn btn-outline-secondary entity-picker-btn" id="entity_clear_btn"
                                    title="<?= e(__('corr.entity_picker_clear')) ?>" <?= $hasPickedEntity ? '' : 'hidden' ?>>
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                    </div>
                    <div class="entity-picked-box" id="entity_org_box" role="button" tabindex="0"
                         title="<?= e(__('corr.entity_picker_open')) ?>">
                        <span class="entity-picker-sub" id="entity_job" <?= $selectedJob !== '' ? '' : 'hidden' ?>><?= e($selectedJob) ?></span>
                        <span class="entity-picker-name<?= $selectedOrg === '' ? ' is-placeholder' : '' ?>" id="entity_org"><?= e($selectedOrg !== '' ? $selectedOrg : __('corr.entity_org_placeholder')) ?></span>
                    </div>
                </div>
            </div>
            <input type="text" name="party_name" id="party_name" class="form-control mt-2" placeholder="<?= e(__('corr.party_manual')) ?>" value="<?= e((string)$v('party_name')) ?>" data-value-missing="<?= e(__('corr.party_required')) ?>" <?= $hasPickedEntity ? 'hidden' : 'required' ?>>
            </div>
            </div>
            <?php if ($isIncoming) { echo $refCardHtml; } ?>
        </div>
        <?php $partyColHtml = ob_get_clean(); ?>
        <?php if ($isIncoming) { echo $partyColHtml; } ?>
        <div class="col-md-6 d-flex flex-column gap-3">
            <?php if ($isIncoming): ?>
            <div class="corr-field-card corr-sign-card">
                <div class="corr-field-card-title"><i class="bi bi-person-lines-fill me-1"></i><?= e(__('corr.dept_responsible')) ?> *</div>
                <div class="corr-field-card-body">
                    <label class="visually-hidden" for="department_id"><?= e(__('corr.dept_responsible')) ?></label>
                    <?php $deptRequired = true; $deptOptionAsPerson = true; require __DIR__ . '/corr_department_field.php'; ?>
                </div>
            </div>
            <?php if (empty($corr) && function_exists('corr_cc_enabled_for')): ?>
            <div class="corr-field-card corr-sign-card">
                <div class="corr-field-card-title" title="<?= e(__('cc.field_hint')) ?>"><i class="bi bi-eye me-1"></i><?= e(__('cc.field')) ?></div>
                <div class="corr-field-card-body">
                    <?php
                    $ccType = 'incoming';
                    $ccExcludeUserId = (int) ($user['id'] ?? 0);
                    require __DIR__ . '/corr_cc_field.php';
                    ?>
                </div>
            </div>
            <?php endif; ?>
            <?php else: ?>
            <div class="corr-field-card corr-sign-card">
                <div class="corr-field-card-title" title="<?= e(__('corr.issuing_department_hint')) ?>"><i class="bi bi-person-lines-fill me-1"></i><?= e(__('corr.issuing_department')) ?> *</div>
                <div class="corr-field-card-body">
                    <?php require __DIR__ . '/corr_issuing_field.php'; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php if (!$isIncoming) { echo $refCardHtml; } ?>
        </div>
        <?php if (!$isIncoming) { echo $partyColHtml; } ?>
    </div>
</div>

<div class="admin-form-section">
    <div class="admin-form-section-title"><?= e(__('corr.section_meta')) ?></div>
    <div class="row g-3 corr-form-details">
        <div class="col-md-6 col-xl-4">
            <div class="corr-field-card">
                <div class="corr-field-card-title"><i class="bi bi-calendar3 me-1"></i><?= e(__('corr.group_dates')) ?></div>
                <div class="row g-2 corr-field-card-body">
                    <div class="col-6">
                        <label class="form-label" for="corr_date-display"><?= e($isIncoming ? __('corr.date_in') : __('corr.date_out')) ?> *</label>
                        <?php
                        render_dms_gregorian_date_field([
                            'name' => 'corr_date',
                            'id' => 'corr_date',
                            'value' => (string) $v('corr_date', date('Y-m-d')),
                            'required' => true,
                        ]);
                        ?>
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="reply_due-display"><?= e(__('corr.reply_due')) ?></label>
                        <?php
                        /* موعد الرد اختياري — فارغ عند الإنشاء */
                        $replyDueVal = '';
                        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                            $replyDueVal = trim((string) $v('reply_due'));
                        } elseif (!empty($corr['id'])) {
                            $replyDueVal = trim((string) $v('reply_due'));
                        }
                        render_dms_gregorian_date_field([
                            'name' => 'reply_due',
                            'id' => 'reply_due',
                            'value' => $replyDueVal,
                        ]);
                        ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-4">
            <div class="corr-field-card">
                <div class="corr-field-card-title"><i class="bi bi-shield-lock me-1"></i><?= e(__('corr.group_priority')) ?></div>
                <div class="row g-2 corr-field-card-body">
                    <div class="col-6">
                        <label class="form-label" for="priority"><?= e(__('corr.priority')) ?></label>
                        <select name="priority" id="priority" class="form-select">
                            <?php foreach ($priorityRows as $p): ?>
                                <option value="<?= e($p['code']) ?>" <?= ((string)$v('priority', 'normal') === (string)$p['code']) ? 'selected' : '' ?>><?= e(bilingual_label($p)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="confidentiality"><?= e(__('corr.confidentiality')) ?></label>
                        <select name="confidentiality" id="confidentiality" class="form-select">
                            <?php foreach ($confidentialityRows as $c): ?>
                                <option value="<?= e($c['code']) ?>" <?= ((string)$v('confidentiality', 'normal') === (string)$c['code']) ? 'selected' : '' ?>><?= e(bilingual_label($c)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-12 col-xl-4">
            <div class="corr-field-card">
                <div class="corr-field-card-title"><i class="bi bi-tags me-1"></i><?= e(__('corr.group_classification')) ?></div>
                <div class="row g-2 corr-field-card-body">
                    <div class="col-6">
                        <label class="form-label" for="category_id"><?= e(__('corr.category')) ?></label>
                        <select name="category_id" id="category_id" class="form-select">
                            <option value=""><?= e(__('common.none')) ?></option>
                            <?php $selectedCategory = (string) $v('category_id', empty($corr) && $categories ? (string) $categories[0]['id'] : ''); ?>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?= (int)$c['id'] ?>" <?= ($selectedCategory === (string)$c['id']) ? 'selected' : '' ?>><?= e(category_display_name($c)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="status"><?= e(__('corr.status')) ?></label>
                        <?php
                        $lockIssueStatus = !$isIncoming
                            && (
                                empty($corr)
                                || (function_exists('outgoing_can_issue') && !outgoing_can_issue($corr))
                            );
                        $lockIncomingNewStatus = $isIncoming && empty($corr);
                        ?>
                        <?php if ($lockIssueStatus || $lockIncomingNewStatus): ?>
                        <input type="hidden" name="status" value="new">
                        <input type="text" id="status" class="form-control" value="<?= e(status_label('new')) ?>" readonly
                               title="<?= e($lockIncomingNewStatus ? __('corr.status_locked_incoming') : __('corr.status_locked_issue')) ?>">
                        <?php else: ?>
                        <select name="status" id="status" class="form-select">
                            <?php
                            $statusOpts = !isset($corr)
                                ? ['new', 'in_progress', $isIncoming ? 'awaiting_reply' : 'delivered']
                                : correspondence_assignable_status_codes();
                            $currentStatus = (string) $v('status', 'new');
                            if (isset($corr) && $currentStatus !== '' && !in_array($currentStatus, $statusOpts, true)) {
                                $statusOpts[] = $currentStatus;
                            }
                            if (function_exists('status_levels_list')) {
                                foreach (status_levels_list(true) as $stRow) {
                                    $code = (string) ($stRow['code'] ?? '');
                                    if ($code === '' || in_array($code, ['closed', 'cancelled'], true)) {
                                        continue;
                                    }
                                    if (!in_array($code, $statusOpts, true)) {
                                        $statusOpts[] = $code;
                                    }
                                }
                                $activeStatusCodes = array_column(status_levels_list(true), 'code');
                                if ($activeStatusCodes !== []) {
                                    $statusOpts = array_values(array_filter(
                                        $statusOpts,
                                        static fn(string $s): bool => in_array($s, $activeStatusCodes, true) || $s === $currentStatus
                                    ));
                                }
                            }
                            foreach ($statusOpts as $s):
                            ?>
                                <option value="<?= $s ?>" <?= ($currentStatus === $s) ? 'selected' : '' ?>><?= e(status_label($s)) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/corr_entity_picker.php'; ?>
<?php require __DIR__ . '/corr_link_picker.php'; ?>
