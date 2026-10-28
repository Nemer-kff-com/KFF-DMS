<?php if ($showCancel && $cancelUi === 'allow'): ?>
<?php $suggestKind = correspondence_suggested_cancel_kind($corr); ?>
<div class="modal fade" id="cancelOutgoingModal" tabindex="-1" aria-labelledby="cancelOutgoingModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="<?= e(base_url('outgoing/cancel.php')) ?>" data-confirm="<?= e(__('cancel.confirm')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $corr['id'] ?>">
                <div class="modal-header">
                    <h5 class="modal-title" id="cancelOutgoingModalLabel"><?= e(__('cancel.title')) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(__('action.cancel')) ?>"></button>
                </div>
                <div class="modal-body">
                    <p class="admin-form-hint"><?= e(__('cancel.number_kept')) ?> — <span dir="ltr"><?= e($corr['ref_number']) ?></span></p>
                    <div class="mb-3">
                        <div class="form-label"><?= e(__('cancel.kind')) ?></div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="cancel_kind" id="cancel_kind_void" value="void"<?= $suggestKind !== 'withdrawn' ? ' checked' : '' ?>>
                            <label class="form-check-label" for="cancel_kind_void">
                                <strong><?= e(__('cancel.kind_void')) ?></strong>
                                <span class="d-block small text-muted"><?= e(__('cancel.kind_void_hint')) ?></span>
                            </label>
                        </div>
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="radio" name="cancel_kind" id="cancel_kind_withdrawn" value="withdrawn"<?= $suggestKind === 'withdrawn' ? ' checked' : '' ?>>
                            <label class="form-check-label" for="cancel_kind_withdrawn">
                                <strong><?= e(__('cancel.kind_withdrawn')) ?></strong>
                                <span class="d-block small text-muted"><?= e(__('cancel.kind_withdrawn_hint')) ?></span>
                            </label>
                        </div>
                    </div>
                    <label class="form-label" for="cancel_reason"><?= e(__('cancel.reason')) ?> *</label>
                    <textarea name="cancel_reason" id="cancel_reason" class="form-control" rows="3" required minlength="5" maxlength="500" placeholder="<?= e(__('cancel.reason_ph')) ?>"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(__('action.cancel')) ?></button>
                    <button type="submit" class="btn btn-danger"><?= e(__('cancel.submit')) ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
<?php if ($closeReq && $canCloseDirect): ?>
<div class="modal fade" id="rejectCloseReqModal" tabindex="-1" aria-labelledby="rejectCloseReqModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="<?= e(base_url('correspondence/close_request.php')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="request_id" value="<?= (int) $closeReq['id'] ?>">
                <div class="modal-header">
                    <h5 class="modal-title" id="rejectCloseReqModalLabel"><?= e(__('close_req.reject')) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(__('action.cancel')) ?>"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label" for="close_req_note"><?= e(__('close_req.row_note')) ?> *</label>
                    <textarea name="decision_note" id="close_req_note" class="form-control" rows="3" required minlength="3" maxlength="500"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(__('action.cancel')) ?></button>
                    <button type="submit" class="btn btn-danger"><?= e(__('close_req.reject')) ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
<?php if ($canReopen): ?>
<div class="modal fade" id="reopenCorrModal" tabindex="-1" aria-labelledby="reopenCorrModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="<?= e(base_url('correspondence/reopen.php')) ?>" data-confirm="<?= e(__('reopen.confirm')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $corr['id'] ?>">
                <div class="modal-header">
                    <h5 class="modal-title" id="reopenCorrModalLabel"><?= e(__('reopen.title')) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(__('action.cancel')) ?>"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label" for="reopen_reason">
                        <?= e(__('reopen.reason')) ?> *
                        <i class="bi bi-info-circle text-muted ms-1" tabindex="0" data-bs-toggle="tooltip" data-bs-placement="top" title="<?= e(__('reopen.hint')) ?>"></i>
                    </label>
                    <textarea name="reopen_reason" id="reopen_reason" class="form-control" rows="3" required minlength="5" maxlength="500" placeholder="<?= e(__('reopen.reason_ph')) ?>"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(__('action.cancel')) ?></button>
                    <button type="submit" class="btn btn-success"><i class="bi bi-unlock me-1"></i><?= e(__('reopen.submit')) ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

