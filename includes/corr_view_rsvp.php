        <?php if ($isOutgoing && $rsvpRequired): ?>
        <div class="card-panel mb-3 no-print">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-check2-circle me-1"></i><?= e(__('rsvp.title')) ?></span>
                <?= rsvp_response_badge($corr['rsvp_response'] ?? null) ?>
            </div>
            <div class="card-body">
                <?php if (!empty($corr['rsvp_at'])): ?>
                    <p class="small text-muted mb-2"><?= e(__('rsvp.last')) ?>: <?= e(format_datetime($corr['rsvp_at'])) ?></p>
                <?php endif; ?>
                <?php if (!empty($corr['rsvp_note'])): ?>
                    <p class="mb-3"><?= e($corr['rsvp_note']) ?></p>
                <?php endif; ?>
                <?php if ($canMutateIssued): ?>
                <form method="post" action="<?= e(base_url('outgoing/rsvp.php')) ?>" class="corr-issue-decide">
                    <?= csrf_field() ?>
                    <input type="hidden" name="correspondence_id" value="<?= (int) $corr['id'] ?>">
                    <div>
                        <label class="form-label small mb-1" for="staff_rsvp_note"><?= e(__('rsvp.note')) ?></label>
                        <input type="text" name="rsvp_note" id="staff_rsvp_note" class="form-control form-control-sm" maxlength="400"
                               value="<?= e((string) ($corr['rsvp_note'] ?? '')) ?>">
                    </div>
                    <div class="corr-decide-actions">
                        <button class="btn btn-kff btn-sm" type="submit" name="rsvp_response" value="attending">
                            <i class="bi bi-check2-circle me-1"></i><?= e(__('rsvp.attend')) ?>
                        </button>
                        <button class="btn btn-danger btn-sm" type="submit" name="rsvp_response" value="declined">
                            <i class="bi bi-x-circle me-1"></i><?= e(__('rsvp.decline')) ?>
                        </button>
                    </div>
                    <p class="admin-form-hint mb-0"><?= e(__('rsvp.staff_hint')) ?></p>
                </form>
                <?php elseif ($canMutate && $issueBlockPost !== ''): ?>
                    <div class="alert alert-warning py-2 small mb-0">
                        <i class="bi bi-lock me-1"></i><?= e($issueBlockPost) ?>
                    </div>
                <?php elseif (!$rsvpRequired): ?>
                    <p class="text-muted mb-0"><?= e(__('rsvp.not_enabled')) ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

