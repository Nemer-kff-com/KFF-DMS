<?php
$prepSuperseded = ($corr['type'] ?? '') === 'outgoing'
    && function_exists('outgoing_prep_is_superseded')
    && outgoing_prep_is_superseded($corr);
if (($corr['type'] ?? '') !== 'outgoing' || !function_exists('outgoing_prep_is_open') || (!outgoing_prep_is_open($corr) && !$prepSuperseded)) {
    return;
}
$prepStatus = outgoing_prep_status_of($corr);
$canSubmit = outgoing_user_can_prep_submit($user, $corr);
$canDecide = outgoing_user_can_prep_decide($user, $corr);
$wasReturned = outgoing_prep_was_returned($corr);
$showSubmit = $canSubmit && !$wasReturned;
$selfConvert = $showSubmit && outgoing_user_is_prep_signer($user, $corr);
$prepNote = trim((string) ($corr['outgoing_prep_note'] ?? ''));
$prepLatestId = function_exists('outgoing_prep_latest_id') ? outgoing_prep_latest_id($corr) : (int) ($corr['id'] ?? 0);
$prepPrevious = function_exists('outgoing_prep_previous_rows') ? outgoing_prep_previous_rows($corr) : [];
?>
<div class="card-panel mb-3 no-print">
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
        <span><i class="bi bi-pencil-square me-1"></i><?= e(__('out_prep.panel_title')) ?></span>
        <span class="badge <?= $prepStatus === 'pending_signer' ? 'text-bg-warning' : 'text-bg-secondary' ?>">
            <?= e(outgoing_prep_list_label($corr)) ?>
        </span>
    </div>
    <div class="card-body">
        <?php if ($prepSuperseded): ?>
            <p class="small text-muted mb-3"><?= e(__('out_prep.superseded_hint')) ?></p>
            <?php if ($prepLatestId > 0 && $prepLatestId !== (int) $corr['id']): ?>
                <a class="btn btn-kff" href="<?= e(base_url('outgoing/view.php?id=' . $prepLatestId)) ?>">
                    <i class="bi bi-file-earmark-text me-1"></i><?= e(__('out_prep.open_latest')) ?>
                </a>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($prepPrevious): ?>
            <div class="mb-3">
                <div class="small fw-semibold mb-2"><?= e(__('out_prep.previous_title')) ?></div>
                <ul class="list-unstyled mb-0">
                    <?php foreach ($prepPrevious as $prev): ?>
                        <?php $prevId = (int) ($prev['id'] ?? 0); ?>
                        <?php if ($prevId <= 0) { continue; } ?>
                        <li class="d-flex flex-wrap justify-content-between gap-2 py-1 border-bottom">
                            <span class="small">
                                <?= e(trim((string) ($prev['subject'] ?? ''))) ?>
                                <?php if (!empty($prev['outgoing_prep_decided_at'])): ?>
                                    <span class="text-muted">— <?= e(format_datetime($prev['outgoing_prep_decided_at'])) ?></span>
                                <?php endif; ?>
                            </span>
                            <a class="small" href="<?= e(base_url('outgoing/view.php?id=' . $prevId)) ?>"><?= e(__('action.view')) ?></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($prepNote !== '' && $prepStatus === 'draft'): ?>
            <div class="alert alert-warning py-2 small mb-3">
                <div class="fw-semibold"><?= e(__('out_prep.returned_note')) ?></div>
                <div class="mt-1"><?= e($prepNote) ?></div>
            </div>
        <?php endif; ?>

        <?php if ($prepStatus === 'pending_signer'): ?>
            <p class="small text-muted mb-3"><?= e(__('out_prep.pending_hint')) ?></p>
            <?php if (($user['role'] ?? '') === 'admin'): ?>
                <?php $unstickDept = function_exists('outgoing_issuing_department_id') ? outgoing_issuing_department_id($corr) : 0; ?>
                <div class="alert alert-secondary py-2 small mb-3">
                    <?= e(__('out_prep.admin_unstick_hint')) ?>
                    <?php if ($unstickDept > 0 && user_can('admin.departments', $user)): ?>
                        <a href="<?= e(base_url('admin/departments.php?edit=' . $unstickDept)) ?>"><?= e(__('out_prep.admin_unstick_link')) ?></a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php elseif ($showSubmit): ?>
            <p class="small text-muted mb-3"><?= e($selfConvert ? __('out_prep.submit_self_hint') : __('out_prep.submit_hint')) ?></p>
        <?php elseif ($canSubmit && $wasReturned): ?>
            <p class="small text-muted mb-3"><?= e(__('out_prep.resend_hint')) ?></p>
            <a class="btn btn-outline-secondary" href="<?= e(base_url('outgoing/edit.php?id=' . (int) $corr['id'])) ?>">
                <i class="bi bi-pencil-square me-1"></i><?= e(__('action.edit')) ?>
            </a>
        <?php endif; ?>

        <?php if ($showSubmit): ?>
            <form method="post" action="<?= e(base_url('outgoing/prep_submit.php')) ?>" class="mb-0">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $corr['id'] ?>">
                <button type="submit" class="btn btn-kff"
                        data-confirm="<?= e($selfConvert ? __('out_prep.confirm_submit_self') : __('out_prep.confirm_submit')) ?>">
                    <i class="bi bi-send me-1"></i><?= e($selfConvert ? __('out_prep.submit_self') : __('out_prep.submit')) ?>
                </button>
            </form>
        <?php endif; ?>

        <?php if ($canDecide): ?>
            <form method="post" action="<?= e(base_url('outgoing/prep_decide.php')) ?>" class="corr-issue-decide">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $corr['id'] ?>">
                <div>
                    <label class="form-label small mb-1" for="out-prep-note"><?= e(__('out_prep.note')) ?></label>
                    <textarea name="note" id="out-prep-note" class="form-control form-control-sm" rows="2" placeholder="<?= e(__('out_prep.return_hint')) ?>"></textarea>
                </div>
                <div class="corr-decide-actions">
                    <button type="submit" name="decision" value="approve" class="btn btn-success btn-sm"
                            data-confirm="<?= e(__('out_prep.confirm_approve')) ?>">
                        <i class="bi bi-check2 me-1"></i><?= e(__('out_prep.approve')) ?>
                    </button>
                    <button type="submit" name="decision" value="return" class="btn btn-orange btn-sm"
                            data-confirm="<?= e(__('out_prep.confirm_return')) ?>" data-prep-return="1">
                        <i class="bi bi-arrow-counterclockwise me-1"></i><?= e(__('out_prep.return')) ?>
                    </button>
                </div>
            </form>
            <script>
            (function () {
                var btn = document.querySelector('[data-prep-return]');
                if (!btn) return;
                btn.addEventListener('click', function (e) {
                    var note = document.getElementById('out-prep-note');
                    if (note && String(note.value || '').replace(/\s+/g, ' ').trim() !== '') return;
                    e.preventDefault();
                    var msg = <?= json_encode(__('out_prep.return_note_required'), JSON_UNESCAPED_UNICODE) ?>;
                    if (window.DmsSwal) window.DmsSwal.warning(msg);
                });
            })();
            </script>
        <?php endif; ?>
    </div>
</div>
