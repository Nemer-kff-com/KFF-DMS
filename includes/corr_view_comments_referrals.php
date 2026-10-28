        <?php if (!$isOutgoing): ?>
        <?php if (empty($inHeld)): ?>
        <div class="card-panel mb-3">
            <div class="card-header"><i class="bi bi-chat-left-text me-1"></i><?= e(__('corr.comments')) ?></div>
            <div class="card-body p-4">
                <?php if (empty($comments)): ?>
                    <p class="text-muted"><?= e(__('corr.no_comments')) ?></p>
                <?php else: ?>
                    <div class="corr-comment-list mb-3">
                        <?php foreach ($comments as $cm): ?>
                            <div class="corr-comment-item">
                                <div class="d-flex justify-content-between gap-2">
                                    <strong><?= e(user_display_name($cm, 'user_name', 'user_name_en') ?: __('common.user')) ?></strong>
                                    <span class="small text-muted"><?= e(format_datetime($cm['created_at'])) ?></span>
                                </div>
                                <div class="mt-1"><?= nl2br(e($cm['body'])) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if ($canAddComment): ?>
                <form method="post" action="<?= e(base_url('comments/add.php')) ?>" class="no-print">
                    <?= csrf_field() ?>
                    <input type="hidden" name="correspondence_id" value="<?= (int)$corr['id'] ?>">
                    <input type="hidden" name="return" value="<?= e($typeFolder.'/view.php?id='.(int)$corr['id']) ?>">
                    <label class="form-label"><?= e(__('corr.quick_comment')) ?></label>
                    <div class="mb-2 d-flex flex-wrap gap-1">
                        <?php foreach ($quickComments as $quick): ?>
                            <button type="button" class="btn btn-sm btn-outline-secondary comment-quick" data-text="<?= e($quick) ?>"><?= e($quick) ?></button>
                        <?php endforeach; ?>
                    </div>
                    <textarea name="body" id="comment_body" class="form-control mb-2" rows="2" required placeholder="<?= e(__('corr.comment_placeholder')) ?>"></textarea>
                    <button class="btn btn-kff btn-sm" type="submit"><?= e(__('corr.add_comment')) ?></button>
                </form>
                <?php elseif ($commentDenied): ?>
                <div class="no-print"><?= dms_perm_denied_btn(__('corr.add_comment'), 'bi-chat-left-text', 'btn-kff btn-sm') ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="card-panel mb-3">
            <div class="card-header"><i class="bi bi-share me-1"></i><?= e(__('corr.referrals')) ?></div>
            <div class="card-body p-4">
                <?php if ($canRefer && $inHeld): ?>
                <div class="alert alert-warning py-2 small mb-3 no-print"><i class="bi bi-hourglass-split me-1"></i><?= e(corr_held_text($corr, 'refer_blocked')) ?></div>
                <?php elseif ($canRefer): ?>
                <form method="post" action="<?= e(base_url('referrals/create.php')) ?>" class="row g-2 align-items-end mb-3 no-print corr-refer-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="correspondence_id" value="<?= (int)$corr['id'] ?>">
                    <input type="hidden" name="return" value="<?= e($typeFolder.'/view.php?id='.(int)$corr['id']) ?>">
                    <?php $referOff = $showCloseOpen ? ' disabled' : ''; ?>
                    <div class="col-md-4">
                        <label class="form-label"><?= e(__('corr.refer_to')) ?></label>
                        <select name="to_department_id" class="form-select corr-officer-pick" required<?= $referOff ?>>
                            <option value=""><?= e(__('corr.select_directed')) ?></option>
                            <?php foreach ($departments as $d):
                                $referPerson = department_person_option_label($d);
                                $referOffice = department_display_name($d);
                                $referOfficeSub = ($referOffice !== '' && $referOffice !== $referPerson) ? $referOffice : '';
                            ?>
                                <option value="<?= (int) $d['id'] ?>" data-office="<?= e($referOfficeSub) ?>"><?= e($referPerson) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label"><?= e(__('corr.refer_notes')) ?></label>
                        <input type="text" name="notes" class="form-control"<?= $referOff ?>>
                    </div>
                    <div class="col-md-3">
                        <?php if ($showCloseOpen): ?>
                        <span class="d-block" tabindex="0" data-bs-toggle="tooltip" data-bs-placement="top" title="<?= e(__('reopen.refer_blocked')) ?>">
                            <button class="btn btn-gold w-100" type="button" disabled><i class="bi bi-lock me-1"></i><?= e(__('corr.refer')) ?></button>
                        </span>
                        <?php else: ?>
                        <button class="btn btn-gold w-100" type="submit"><?= e(__('corr.refer')) ?></button>
                        <?php endif; ?>
                    </div>
                </form>
                <?php elseif ($referDenied && !$inHeld): ?>
                <div class="mb-3 no-print"><?= dms_perm_denied_btn(__('corr.refer'), 'bi-share', 'btn-gold') ?></div>
                <?php endif; ?>
                <?php if (!$referrals): ?>
                    <p class="text-muted mb-0"><?= e(__('corr.no_referrals')) ?></p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0 corr-ref-table">
                            <thead><tr><th><?= e(__('ref.from')) ?></th><th><?= e(__('ref.to')) ?></th><th><?= e(__('ref.by')) ?></th><th><?= e(__('ref.date')) ?></th><th><?= e(__('corr.status')) ?></th><th class="no-print"></th></tr></thead>
                            <tbody>
                            <?php foreach ($referrals as $ref): ?>
                                <tr>
                                    <td><?php render_referral_party($ref, 'from'); ?></td>
                                    <td><?php render_referral_party($ref, 'to'); ?></td>
                                    <td><?= e(user_display_name($ref, 'from_user_name', 'from_user_name_en')) ?></td>
                                    <td><?= e(format_datetime($ref['created_at'])) ?></td>
                                    <td><?= status_badge($ref['status']) ?></td>
                                    <td class="no-print">
                                        <?php if ($canRefer && $ref['status'] === 'pending'): ?>
                                        <form method="post" action="<?= e(base_url('referrals/update.php')) ?>" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int)$ref['id'] ?>">
                                            <input type="hidden" name="status" value="received">
                                            <input type="hidden" name="return" value="<?= e($typeFolder.'/view.php?id='.(int)$corr['id']) ?>">
                                            <button class="btn btn-sm btn-outline-success" type="submit"><?= e(__('corr.receive')) ?></button>
                                        </form>
                                        <?php elseif ($referDenied && $ref['status'] === 'pending'): ?>
                                        <?= dms_perm_denied_btn(__('corr.receive'), '', 'btn-sm btn-outline-success') ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php if ($ref['notes']): ?>
                                <tr><td colspan="6" class="small text-muted"><?= e($ref['notes']) ?></td></tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

