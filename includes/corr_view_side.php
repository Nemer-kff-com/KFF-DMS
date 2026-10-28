    <div class="col-xl-4">
        <div class="corr-side">
            <?php if (!empty($showOfficialRef)): ?>
            <?php
            $stickerFile = preg_replace('/[^\p{L}\p{N}\-_]+/u', '-', (string) ($corr['ref_number'] ?? 'sticker'));
            $stickerFile = trim((string) $stickerFile, '-');
            if ($stickerFile === '') {
                $stickerFile = 'sticker';
            }
            $stickerFile .= '.png';
            ?>
            <div class="card-panel mb-3">
                <div class="card-header"><i class="bi bi-upc me-1"></i><?= e(__('thermal.title')) ?></div>
                <div class="card-body text-center p-3">
                    <div class="corr-sticker-host print-thermal-page is-50x25">
                        <?php render_thermal_sticker($corr, 0, 'corr-thermal-sticker', 'corr-sticker-qr'); ?>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary mt-3 no-print" data-keep-label="1" data-copy-sticker="corr-thermal-sticker" data-sticker-filename="<?= e($stickerFile) ?>" data-copy-ok="<?= e(__('corr.copied_sticker')) ?>" data-copy-fail="<?= e(__('corr.copy_failed')) ?>" data-download-ok="<?= e(__('corr.sticker_downloaded')) ?>">
                        <i class="bi bi-clipboard me-1"></i><?= e(__('corr.copy_sticker')) ?>
                    </button>
                </div>
            </div>
            <?php else: ?>
            <div class="card-panel mb-3">
                <div class="card-header"><i class="bi bi-qr-code me-1"></i><?= e(__('corr.qr')) ?></div>
                <div class="card-body text-center p-4">
                    <p class="text-muted small mb-0"><?= e(function_exists('outgoing_qr_hidden_message') ? outgoing_qr_hidden_message() : __('out_prep.qr_hidden')) ?></p>
                </div>
            </div>
            <?php endif; ?>
            <?php if (($canQuickStatus || $quickStatusDenied) && ($isIncoming || $isInternal) && !$inHeld): ?>
            <?php $statusLockTip = $closedLockTip !== '' ? $closedLockTip : ($quickStatusDenied ? dms_perm_denied_tip(__('corr.quick_status')) : ''); ?>
            <div class="card-panel no-print mb-3">
                <div class="card-header"><i class="bi bi-arrow-repeat me-1"></i><?= e(__('corr.quick_status')) ?></div>
                <div class="card-body p-4">
                    <form method="post" action="<?= e(base_url($typeFolder.'/status.php')) ?>" class="corr-auto-status-form"
                          data-auto-status="1"
                          data-current-status="<?= e((string) ($corr['status'] ?? '')) ?>"
                          data-status-confirm="<?= e(__('corr.confirm_status_change')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int)$corr['id'] ?>">
                        <?php if ($statusLockTip !== ''): ?><span class="d-block" tabindex="0" data-bs-toggle="tooltip" data-bs-placement="top" title="<?= e($statusLockTip) ?>"><?php endif; ?>
                        <select name="status" class="form-select"<?= $statusLockTip !== '' ? ' disabled' : '' ?>>
                            <?php
                            $quickStatusOpts = correspondence_assignable_status_codes();
                            $quickCurrent = (string) ($corr['status'] ?? '');
                            if ($quickCurrent !== '' && !in_array($quickCurrent, $quickStatusOpts, true)) {
                                $quickStatusOpts[] = $quickCurrent;
                            }
                            foreach ($quickStatusOpts as $s):
                            ?>
                                <option value="<?= $s ?>" <?= $quickCurrent === $s ? 'selected' : '' ?>><?= e(status_label($s)) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($statusLockTip !== ''): ?></span><?php endif; ?>
                    </form>
                </div>
            </div>
            <?php endif; ?>
            <?php require __DIR__ . '/corr_activity_card.php'; ?>
        </div>
    </div>
</div>
