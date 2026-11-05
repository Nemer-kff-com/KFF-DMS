        <?php if ($isOutgoing && $rsvpRequired): ?>
        <div class="card-panel mb-3 no-print">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-check2-circle me-1"></i><?= e(__('rsvp.title')) ?></span>
                <?= rsvp_response_badge($corr['rsvp_response'] ?? null, $corr['rsvp_mode'] ?? null) ?>
            </div>
            <div class="card-body">
                <?php if (!empty($corr['rsvp_at'])): ?>
                    <p class="small text-muted mb-2"><?= e(__('rsvp.last')) ?>: <?= e(format_datetime($corr['rsvp_at'])) ?></p>
                <?php endif; ?>
                <?php if (!empty($corr['rsvp_note'])): ?>
                    <p class="mb-3"><?= e($corr['rsvp_note']) ?></p>
                <?php endif; ?>
                <?php if ($canMutateIssued): ?>
                <form method="post" action="<?= e(base_url('outgoing/rsvp.php')) ?>" class="corr-issue-decide" data-rsvp-form>
                    <?= csrf_field() ?>
                    <input type="hidden" name="correspondence_id" value="<?= (int) $corr['id'] ?>">
                    <input type="hidden" name="rsvp_mode" value="">
                    <div>
                        <label class="form-label small mb-1" for="staff_rsvp_note"><?= e(__('rsvp.note')) ?></label>
                        <input type="text" name="rsvp_note" id="staff_rsvp_note" class="form-control form-control-sm" maxlength="400"
                               value="<?= e((string) ($corr['rsvp_note'] ?? '')) ?>">
                    </div>
                    <?php
                    $staffAllowRemote = function_exists('correspondence_rsvp_allow_remote') ? correspondence_rsvp_allow_remote($corr) : true;
                    $staffModesOpen = $staffAllowRemote && (string) ($corr['rsvp_response'] ?? '') === 'attending';
                    ?>
                    <div class="d-grid gap-2 mt-3">
                        <?php if ($staffAllowRemote): ?>
                        <button class="btn verify-rsvp-choice verify-rsvp-choice--here<?= $staffModesOpen ? ' d-none' : '' ?>" type="button" data-rsvp-show-modes>
                            <?= e(__('rsvp.attend')) ?>
                        </button>
                        <div class="d-grid gap-2<?= $staffModesOpen ? '' : ' d-none' ?>" data-rsvp-modes>
                            <button class="btn verify-rsvp-choice verify-rsvp-choice--here" type="submit" name="rsvp_response" value="attending" data-rsvp-mode="in_person">
                                <?= e(__('rsvp.public_in_person')) ?>
                            </button>
                            <button class="btn verify-rsvp-choice verify-rsvp-choice--remote" type="submit" name="rsvp_response" value="attending" data-rsvp-mode="remote">
                                <?= e(__('rsvp.public_remote')) ?>
                            </button>
                        </div>
                        <?php else: ?>
                        <button class="btn verify-rsvp-choice verify-rsvp-choice--here" type="submit" name="rsvp_response" value="attending" data-rsvp-mode="in_person">
                            <?= e(__('rsvp.public_in_person')) ?>
                        </button>
                        <?php endif; ?>
                        <button class="btn verify-rsvp-choice verify-rsvp-choice--decline" type="submit" name="rsvp_response" value="declined">
                            <?= e(__('rsvp.public_decline')) ?>
                        </button>
                    </div>
                </form>
                <script>
                (function () {
                  document.querySelectorAll('[data-rsvp-form]').forEach(function (form) {
                    if (form.getAttribute('data-rsvp-bound') === '1') return;
                    form.setAttribute('data-rsvp-bound', '1');
                    var open = form.querySelector('[data-rsvp-show-modes]');
                    var modes = form.querySelectorAll('[data-rsvp-modes]');
                    var modeInput = form.querySelector('[name="rsvp_mode"]');
                    if (open && modes.length) {
                      open.addEventListener('click', function () {
                        modes.forEach(function (el) { el.classList.remove('d-none'); });
                        open.classList.add('d-none');
                      });
                    }
                    form.querySelectorAll('[data-rsvp-mode]').forEach(function (btn) {
                      btn.addEventListener('click', function () {
                        if (modeInput) modeInput.value = btn.getAttribute('data-rsvp-mode') || '';
                      });
                    });
                  });
                })();
                </script>
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

