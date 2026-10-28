<?php

/**

 * بطاقة موحّدة: التسليم + تغيير الحالة — للصادر فقط

 * يتطلب: $corr, $canEdit, $hasReceipt

 */

if (($corr['type'] ?? '') !== 'outgoing') {

    return;

}

if (isset($canMutate)) {
    $canEdit = $canMutate;
}
$receiptUi = dms_perm_ui('outgoing.receipt', $user);
$receiptDenied = $canEdit && $receiptUi === 'deny';
$canEdit = $canEdit && $receiptUi === 'allow';

$isElectronicDelivery = function_exists('correspondence_intend_electronic')
    && correspondence_intend_electronic($corr);
$deliveryTab = ($_GET['delivery_tab'] ?? '') === 'status'
    ? 'status'
    : (($_GET['delivery_tab'] ?? '') === 'delivery'
        ? 'delivery'
        : ($isElectronicDelivery ? 'status' : 'delivery'));

?>

<div class="card-panel mb-3 corr-delivery-card corr-receipt-card" id="corr-delivery">

    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">

        <span><i class="bi bi-<?= $isElectronicDelivery ? 'envelope-at' : 'truck' ?> me-1"></i><?= e($isElectronicDelivery ? __('delivery.section_electronic') : __('receipt.section')) ?></span>

        <?php if ($isElectronicDelivery): ?>

            <span class="badge text-bg-info"><?= e(__('corr.electronic_badge')) ?></span>

        <?php elseif ($hasReceipt): ?>

            <span class="badge text-bg-success"><?= e(__('receipt.recorded')) ?></span>

        <?php else: ?>

            <span class="badge text-bg-secondary"><?= e(__('receipt.pending')) ?></span>

        <?php endif; ?>

    </div>

    <div class="card-body p-0">

        <?php if ($canEdit): ?>

        <ul class="nav nav-tabs corr-delivery-tabs px-3 pt-2" role="tablist">

            <li class="nav-item" role="presentation">

                <button class="nav-link<?= $deliveryTab === 'delivery' ? ' active' : '' ?>" id="delivery-tab-delivery"

                        data-bs-toggle="tab" data-bs-target="#delivery-pane-delivery" type="button" role="tab"

                        aria-controls="delivery-pane-delivery" aria-selected="<?= $deliveryTab === 'delivery' ? 'true' : 'false' ?>">

                    <i class="bi bi-pen me-1"></i><?= e(__('receipt.tab')) ?>

                </button>

            </li>

            <li class="nav-item" role="presentation">

                <button class="nav-link<?= $deliveryTab === 'status' ? ' active' : '' ?>" id="delivery-tab-status"

                        data-bs-toggle="tab" data-bs-target="#delivery-pane-status" type="button" role="tab"

                        aria-controls="delivery-pane-status" aria-selected="<?= $deliveryTab === 'status' ? 'true' : 'false' ?>">

                    <i class="bi bi-arrow-repeat me-1"></i><?= e(__('delivery.tab_status')) ?>

                </button>

            </li>

        </ul>

        <?php endif; ?>



        <div class="tab-content p-4">

            <div class="tab-pane fade<?= $deliveryTab === 'delivery' || !$canEdit ? ' show active' : '' ?>" id="delivery-pane-delivery" role="tabpanel" aria-labelledby="delivery-tab-delivery">

                <?php if ($hasReceipt): ?>

                <div class="corr-receipt-summary mb-3">

                    <div class="row g-3">

                        <div class="col-md-4">

                            <div class="corr-receipt-stat">

                                <i class="bi bi-person"></i>

                                <div>

                                    <div class="corr-meta-label"><?= e(__('print.receiver_name')) ?></div>

                                    <div class="fw-semibold"><?= e($corr['receipt_name']) ?></div>

                                </div>

                            </div>

                        </div>

                        <div class="col-md-4">

                            <div class="corr-receipt-stat">

                                <i class="bi bi-calendar3"></i>

                                <div>

                                    <div class="corr-meta-label"><?= e(__('print.receive_date')) ?></div>

                                    <div class="fw-semibold"><?= e(format_date($corr['receipt_date'])) ?></div>

                                </div>

                            </div>

                        </div>

                        <div class="col-md-4">

                            <div class="corr-receipt-stat">

                                <i class="bi bi-telephone"></i>

                                <div>

                                    <div class="corr-meta-label"><?= e(__('print.receiver_phone')) ?></div>

                                    <div class="fw-semibold" dir="ltr"><?= e($corr['receipt_phone'] ?: __('common.none')) ?></div>

                                </div>

                            </div>

                        </div>

                    </div>

                    <div class="corr-receipt-file mt-3">

                        <div class="corr-meta-label mb-1"><?= e(__('receipt.slip')) ?></div>

                        <?php if (!empty($corr['receipt_file_path'])): ?>

                            <a href="<?= e(file_download_url('receipt', (int) $corr['id'])) ?>" target="_blank" class="corr-file-link">

                                <i class="bi bi-file-earmark-image"></i>

                                <span><?= e($corr['receipt_file_name'] ?: __('receipt.open_file')) ?></span>

                            </a>

                            <?php

                            $ext = strtolower(pathinfo((string) $corr['receipt_file_path'], PATHINFO_EXTENSION));

                            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)):

                            ?>

                                <div class="mt-2">

                                    <img src="<?= e(file_download_url('receipt', (int) $corr['id'])) ?>" alt="" class="receipt-preview-img">

                                </div>

                            <?php endif; ?>

                        <?php else: ?>

                            <div class="text-muted small"><?= e(__('receipt.no_file')) ?></div>

                        <?php endif; ?>

                        <div class="small text-muted mt-2">

                            <?= e(__('receipt.recorded_meta')) ?>:

                            <?= e(user_display_name($corr, 'receipt_recorded_by_name', 'receipt_recorded_by_name_en') ?: __('common.none')) ?>

                            <?php if (!empty($corr['receipt_recorded_at'])): ?>

                                — <?= e(format_datetime($corr['receipt_recorded_at'])) ?>

                            <?php endif; ?>

                        </div>

                    </div>

                </div>

                <?php else: ?>

                    <p class="text-muted small mb-3"><?= e(__('receipt.hint')) ?></p>

                <?php endif; ?>



                <?php if ($canEdit): ?>

                <form method="post" enctype="multipart/form-data" action="<?= e(base_url('outgoing/receipt.php')) ?>"

                      class="no-print corr-receipt-form <?= $hasReceipt ? 'mt-3' : '' ?>"

                      data-confirm="<?= e(__('delivery.confirm_save')) ?>">

                    <?= csrf_field() ?>

                    <input type="hidden" name="correspondence_id" value="<?= (int) $corr['id'] ?>">

                    <input type="hidden" name="action" value="save">

                    <?php if ($hasReceipt): ?>

                        <div class="corr-meta-label mb-2"><?= e(__('receipt.update_title')) ?></div>

                    <?php endif; ?>

                    <div class="row g-3">

                        <div class="col-md-4">

                            <label class="form-label" for="receipt_name"><?= e(__('print.receiver_name')) ?> *</label>

                            <input type="text" name="receipt_name" id="receipt_name" class="form-control" required

                                   value="<?= e((string) ($corr['receipt_name'] ?? '')) ?>">

                        </div>

                        <div class="col-md-4">

                            <label class="form-label" for="receipt_date-display"><?= e(__('print.receive_date')) ?> *</label>
                            <?php
                            render_dms_gregorian_date_field([
                                'name' => 'receipt_date',
                                'id' => 'receipt_date',
                                'value' => (string) ($corr['receipt_date'] ?? date('Y-m-d')),
                                'required' => true,
                            ]);
                            ?>

                        </div>

                        <div class="col-md-4">

                            <label class="form-label" for="receipt_phone"><?= e(__('print.receiver_phone')) ?></label>

                            <input type="text" name="receipt_phone" id="receipt_phone" class="form-control" dir="ltr"

                                   value="<?= e((string) ($corr['receipt_phone'] ?? '')) ?>">

                        </div>

                        <div class="col-12">
                            <label class="form-label" for="receipt_file"><?= e(__('receipt.upload_slip')) ?></label>
                            <div class="corr-receipt-file-row">
                                <?php
                                $dmsFile = [
                                    'name' => 'receipt_file',
                                    'id' => 'receipt_file',
                                    'accept' => '.jpg,.jpeg,.png,.gif,.webp,.pdf,image/*,application/pdf',
                                    'browse' => __('file.choose_one'),
                                    'placeholder' => __('file.placeholder_one'),
                                    'wrap_class' => 'mb-0 corr-receipt-file-field',
                                    'field_class' => 'corr-receipt-file-input',
                                ];
                                require __DIR__ . '/dms_file_field.php';
                                ?>
                                <button class="btn btn-kff corr-receipt-save-btn" type="submit"
                                        data-bs-toggle="tooltip" data-bs-placement="top"
                                        title="<?= e(__('delivery.save_tip')) ?>"
                                        aria-label="<?= e(__('delivery.save_tip')) ?>">
                                    <i class="bi bi-check2-circle" aria-hidden="true"></i>
                                </button>
                            </div>
                            <div class="form-text"><?= e(__('receipt.upload_hint')) ?></div>
                        </div>

                    </div>

                </form>

                <?php if (!empty($corr['receipt_file_path'])): ?>

                <form method="post" action="<?= e(base_url('outgoing/receipt.php')) ?>" class="no-print mt-2"

                      data-confirm="<?= e(__('receipt.confirm_remove_file')) ?>">

                    <?= csrf_field() ?>

                    <input type="hidden" name="correspondence_id" value="<?= (int) $corr['id'] ?>">

                    <input type="hidden" name="action" value="remove_file">

                    <button class="btn btn-outline-danger btn-sm" type="submit">

                        <i class="bi bi-trash me-1"></i><?= e(__('receipt.remove_file')) ?>

                    </button>

                </form>

                <?php endif; ?>

                <?php elseif (!$hasReceipt): ?>

                    <p class="text-muted mb-0"><?= e(__('receipt.empty_readonly')) ?></p>

                <?php endif; ?>

                <?php if ($receiptDenied): ?>
                    <div class="no-print mt-3"><?= dms_perm_denied_btn(__('receipt.tab'), 'bi-pen', 'btn-kff') ?></div>
                <?php endif; ?>

            </div>



            <?php if ($canEdit): ?>

            <div class="tab-pane fade<?= $deliveryTab === 'status' ? ' show active' : '' ?>" id="delivery-pane-status" role="tabpanel" aria-labelledby="delivery-tab-status">

                <p class="text-muted small mb-3"><?= e($isElectronicDelivery ? __('delivery.status_hint_electronic') : __('delivery.status_hint')) ?></p>

                <?php if ($isElectronicDelivery): ?>
                    <div class="alert alert-info py-2 small mb-3">
                        <div class="mb-1">
                            <strong><?= e(__('status.sent')) ?></strong>
                            — <?= e(__('delivery.electronic_sent_hint')) ?>
                        </div>
                        <div class="mb-1">
                            <strong><?= e(__('status.delivered')) ?></strong>
                            — <?= e(__('delivery.electronic_choose_delivered')) ?>
                        </div>
                        <div>
                            <strong><?= e(__('status.in_progress')) ?></strong>
                            — <?= e(__('delivery.electronic_failed_hint')) ?>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="post" action="<?= e(base_url('outgoing/status.php')) ?>" class="no-print corr-auto-status-form"

                      data-auto-status="1"

                      data-current-status="<?= e((string) ($corr['status'] ?? '')) ?>"

                      data-status-confirm="<?= e(__('corr.confirm_status_change')) ?>">

                    <?= csrf_field() ?>

                    <input type="hidden" name="id" value="<?= (int) $corr['id'] ?>">

                    <label class="form-label" for="delivery_status"><?= e(__('corr.status')) ?></label>

                    <?php $deliveryLockTip = correspondence_is_closed($corr) ? __('reopen.use_reopen') : ''; ?>
                    <?php if ($deliveryLockTip !== ''): ?><span class="d-block" tabindex="0" data-bs-toggle="tooltip" data-bs-placement="top" title="<?= e($deliveryLockTip) ?>"><?php endif; ?>
                    <select name="status" id="delivery_status" class="form-select"<?= $deliveryLockTip !== '' ? ' disabled' : '' ?>>
                        <?php
                        $deliveryStatusOpts = correspondence_assignable_status_codes();
                        $deliveryCurrent = (string) ($corr['status'] ?? '');
                        if ($deliveryCurrent !== '' && !in_array($deliveryCurrent, $deliveryStatusOpts, true)) {
                            $deliveryStatusOpts[] = $deliveryCurrent;
                        }
                        foreach ($deliveryStatusOpts as $s):
                        ?>
                            <option value="<?= $s ?>" <?= $deliveryCurrent === $s ? 'selected' : '' ?>><?= e(status_label($s)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($deliveryLockTip !== ''): ?></span><?php endif; ?>

                </form>

            </div>

            <?php endif; ?>

        </div>

    </div>

</div>

