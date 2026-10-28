<?php
/**
 * تدخّل مدير النظام في معاملة متعطّلة: نقلها للمرحلة التالية أو لمستخدم آخر (سبب إلزامي)
 * يتطلب: $corr, $user, $admActionUrl
 */
if (!function_exists('appr_admin_can_reassign') || !appr_admin_can_reassign($user, $corr)) {
    return;
}
$admNext = appr_with(appr_type_of($corr), static fn () => appr_admin_next_stage($corr));
$admUsers = appr_admin_reassign_candidates($corr);
$admKey = 'adm_move_' . (int) $corr['id'];
?>
<div class="corr-admin-override mt-3 pt-2 border-top">
    <div class="small fw-semibold mb-1 text-danger" title="<?= e(__('issue_appr.admin_panel_hint')) ?>" data-bs-toggle="tooltip">
        <i class="bi bi-person-gear me-1"></i><?= e(__('issue_appr.admin_panel_title')) ?>
    </div>
    <form method="post" action="<?= e($admActionUrl) ?>" class="row g-2 align-items-end">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $corr['id'] ?>">
        <input type="hidden" name="action" value="admin_reassign">
        <div class="col-md-4">
            <label class="form-label small mb-1" for="<?= e($admKey) ?>_target"><?= e(__('issue_appr.admin_move_to')) ?> *</label>
            <select name="target_user_id" id="<?= e($admKey) ?>_target" class="form-select form-select-sm" required>
                <?php if ($admNext): ?>
                    <option value="0" selected><?= e(__('issue_appr.admin_next_stage')) ?>: <?= e(appr_with(appr_type_of($corr), static fn () => outgoing_approval_label($admNext['status']))) ?> — <?= e(outgoing_user_name_by_id($admNext['assignee'])) ?></option>
                <?php else: ?>
                    <option value="" selected disabled><?= e(__('issue_appr.admin_pick_user')) ?></option>
                <?php endif; ?>
                <?php foreach ($admUsers as $admU): ?>
                    <option value="<?= (int) $admU['id'] ?>"><?= e($admU['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-5">
            <label class="form-label small mb-1" for="<?= e($admKey) ?>_note"><?= e(__('issue_appr.admin_reason')) ?> *</label>
            <input type="text" name="note" id="<?= e($admKey) ?>_note" class="form-control form-control-sm" maxlength="450" required
                   placeholder="<?= e(__('issue_appr.admin_reason_placeholder')) ?>">
        </div>
        <div class="col-md-3">
            <button type="submit" class="btn btn-outline-danger btn-sm w-100" data-confirm="<?= e(__('issue_appr.confirm_admin_move')) ?>">
                <i class="bi bi-arrow-left-right me-1"></i><?= e(__('issue_appr.admin_move')) ?>
            </button>
        </div>
    </form>
</div>
