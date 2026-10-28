<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/correspondence.php';
$user = require_login();
require_outgoing_batch_view($user);
ensure_entity_groups_schema();
ensure_corr_rsvp_schema();

$id = (int) ($_GET['id'] ?? $_POST['batch_id'] ?? 0);
$batch = $id ? get_outgoing_batch($id) : null;
$items = ($batch && $id) ? outgoing_batch_items($id, $user) : [];
if (!$batch) {
    flash('danger', __('bulk.batch_not_found'));
    redirect(base_url('outgoing/batches.php'));
}
if (!$items) {
    flash('danger', __('bulk.batch_no_access'));
    redirect(base_url('outgoing/batches.php'));
}

$canRsvp = outgoing_batch_can_enable_rsvp($id, $user);
$rsvpConfirm = outgoing_batch_rsvp_in_cycle($id)
    ? __('rsvp.enable_confirm') . ' ' . __('rsvp.enable_confirm_cycle')
    : __('rsvp.enable_confirm');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enable_rsvp'])) {
    verify_csrf();
    try {
        $resetErr = outgoing_batch_enable_rsvp($id, $user);
        if ($resetErr !== '') {
            flash('warning', __('rsvp.enabled_ok') . ' ' . $resetErr);
        } else {
            flash('success', __('rsvp.enabled_ok'));
        }
    } catch (Throwable $e) {
        flash('danger', $e->getMessage());
    }
    redirect(base_url('outgoing/batch.php?id=' . $id));
}

$rsvpOn = !empty($batch['requires_rsvp']) || correspondence_rsvp_required($items[0] ?? []);
$rsvpSummary = batch_rsvp_summary($items);

$itemIds = array_map(static fn(array $row): int => (int) $row['id'], $items);
$lastMap = dms_corr_last_deliveries_map($itemIds);
$sendMsgUi = dms_perm_ui('outgoing.send', $user);
if ($sendMsgUi !== 'allow' && user_can('outgoing.bulk', $user)) {
    $sendMsgUi = 'allow';
}
$deliverUi = dms_perm_ui('outgoing.receipt', $user);
$canSendMsg = $sendMsgUi === 'allow';
$canDeliver = $deliverUi === 'allow';
$canSend = $canSendMsg || $canDeliver;
$showSendGroup = $sendMsgUi !== 'hide' || $deliverUi !== 'hide';
$batchElectronic = $items !== []
    && function_exists('correspondence_intend_electronic')
    && correspondence_intend_electronic($items[0]);
$apprSummary = function_exists('outgoing_batch_approval_summary')
    ? outgoing_batch_approval_summary($items, $user)
    : ['configured' => false, 'can_issue' => true, 'block_reason' => '', 'can_request' => false, 'can_decide' => false];
$batchCanIssue = !empty($apprSummary['can_issue']);
$batchBlockReason = (string) ($apprSummary['block_reason'] ?? '');
$canPrintForm = function_exists('user_can') && user_can('outgoing.print_form', $user);
$canPrintEnvelope = function_exists('user_can') && user_can('outgoing.print_envelope', $user);
$canPrintThermal = function_exists('user_can') && user_can('outgoing.print_thermal', $user);
$waReady = (dms_whatsapp_readiness()['state'] ?? '') === 'ready';
$emailReady = false;
$emailCount = 0;
$mobileCount = 0;
$emailSent = 0;
$emailPending = 0;
$noEmail = 0;
$anySent = 0;
$deliverySent = 0;
$issuableAny = 0;
$issuableEmail = 0;
$issuableMobile = 0;
foreach ($items as $row) {
    $last = $lastMap[(int) $row['id']] ?? ['email' => null, 'whatsapp' => null, 'delivery' => null];
    $hasEmail = dms_corr_email_address($row) !== '';
    $hasMobile = dms_corr_mobile_number($row) !== '';
    $rowIssuable = !correspondence_is_cancelled($row)
        && (!function_exists('outgoing_can_issue') || outgoing_can_issue($row));
    if ($rowIssuable) {
        $issuableAny++;
        if ($hasEmail) {
            $issuableEmail++;
        }
        if ($hasMobile) {
            $issuableMobile++;
        }
    }
    if ($hasEmail) {
        $emailCount++;
        if (dms_mail_ready((int) ($row['department_id'] ?? 0) ?: null)) {
            $emailReady = true;
        }
        if (($last['email']['status'] ?? '') === 'sent') {
            $emailSent++;
        } else {
            $emailPending++;
        }
    } else {
        $noEmail++;
    }
    if ($hasMobile) {
        $mobileCount++;
    }
    $sent = (($last['email']['status'] ?? '') === 'sent')
        || (($last['whatsapp']['status'] ?? '') === 'sent')
        || (($last['delivery']['status'] ?? '') === 'sent');
    if ($sent) {
        $anySent++;
    }
    if (($last['delivery']['status'] ?? '') === 'sent') {
        $deliverySent++;
    }
}

$pageTitle = __('bulk.result_title');
require __DIR__ . '/../includes/header.php';

$canBulkOps = user_can('outgoing.bulk', $user);
/** @var list<array{kind:string,label:string,icon:string,href?:string,class?:string,form?:string}> */
$batchSecondary = [];
if ($canRsvp && !$rsvpOn) {
    $batchSecondary[] = [
        'kind' => 'rsvp',
        'label' => __('rsvp.enable'),
        'icon' => 'bi-check2-circle',
        'class' => 'btn-outline-secondary',
    ];
}
if ($canBulkOps) {
    $batchSecondary[] = [
        'kind' => 'link',
        'label' => __('bulk.export_word'),
        'icon' => 'bi-file-earmark-word',
        'href' => base_url('outgoing/batch_word.php?id=' . $id),
        'class' => 'btn-outline-secondary',
    ];
    $batchSecondary[] = [
        'kind' => 'link',
        'label' => __('bulk.export_merge'),
        'icon' => 'bi-filetype-csv',
        'href' => base_url('outgoing/batch_csv.php?id=' . $id),
        'class' => 'btn-outline-secondary',
    ];
}
$hasBatchSecondary = $batchSecondary !== [];
?>
<div class="page-header no-print">
    <div class="corr-view-title-wrap">
        <h1 class="page-title"><?= e((string) ($batch['subject'] ?? __('bulk.result_title'))) ?></h1>
        <p class="page-sub"><?= e(__('bulk.result_title')) ?> · <?= e(str_replace(':count', (string) count($items), __('bulk.result_count'))) ?></p>
    </div>
    <div class="page-header-actions corr-view-actions">
        <div class="corr-actions-group corr-actions-primary">
            <a class="btn btn-outline-secondary corr-action-primary" href="<?= e(base_url('outgoing/batches.php')) ?>">
                <i class="bi <?= dms_is_rtl() ? 'bi-arrow-right' : 'bi-arrow-left' ?> me-1"></i><?= e(__('action.back')) ?>
            </a>

            <?php if ($canBulkOps): ?>
            <div class="corr-actions-print dropdown">
                <button type="button" class="btn btn-outline-secondary dropdown-toggle corr-action-primary" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-printer me-1"></i><?= e(__('corr.print_menu')) ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <?php if ($batchCanIssue): ?>
                    <?php if ($canPrintForm): ?>
                    <li>
                        <a class="dropdown-item" href="<?= e(base_url('outgoing/batch_corr_print.php?id=' . $id)) ?>" target="_blank" rel="noopener">
                            <i class="bi bi-clipboard-check me-2"></i><?= e(__('bulk.print_corr')) ?>
                        </a>
                    </li>
                    <?php endif; ?>
                    <li>
                        <a class="dropdown-item" href="<?= e(base_url('outgoing/batch_print.php?id=' . $id)) ?>" target="_blank" rel="noopener">
                            <i class="bi bi-file-text me-2"></i><?= e(__('bulk.print_letters')) ?>
                        </a>
                    </li>
                    <?php if ($canPrintEnvelope && !$batchElectronic): ?>
                    <li>
                        <a class="dropdown-item" href="<?= e(base_url('outgoing/batch_labels.php?id=' . $id)) ?>" target="_blank" rel="noopener">
                            <i class="bi bi-sticky me-2"></i><?= e(__('bulk.print_labels')) ?>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if ($canPrintThermal && !$batchElectronic): ?>
                    <li>
                        <a class="dropdown-item" href="<?= e(base_url('outgoing/batch_thermal.php?id=' . $id)) ?>" target="_blank" rel="noopener">
                            <i class="bi bi-upc me-2"></i><?= e(__('thermal.print')) ?>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php else: ?>
                    <li>
                        <span class="dropdown-item disabled text-wrap" title="<?= e($batchBlockReason) ?>">
                            <i class="bi bi-shield-lock me-2"></i><?= e($batchBlockReason !== '' ? $batchBlockReason : __('issue_appr.blocked_pending')) ?>
                        </span>
                    </li>
                    <?php endif; ?>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item" href="<?= e(base_url('outgoing/batch_recipients.php?id=' . $id)) ?>" target="_blank" rel="noopener">
                            <i class="bi bi-table me-2"></i><?= e(__('bulk.print_results')) ?>
                        </a>
                    </li>
                </ul>
            </div>
            <?php else: ?>
            <a class="btn btn-outline-secondary corr-action-primary" href="<?= e(base_url('outgoing/batch_recipients.php?id=' . $id)) ?>" target="_blank" rel="noopener">
                <i class="bi bi-printer me-1"></i><?= e(__('bulk.print_results')) ?>
            </a>
            <?php endif; ?>

            <?php if ($hasBatchSecondary): ?>
            <div class="corr-actions-more dropdown d-md-none">
                <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" aria-label="<?= e(__('corr.more_actions')) ?>">
                    <i class="bi bi-three-dots me-1"></i><?= e(__('corr.more_actions')) ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <?php foreach ($batchSecondary as $action): ?>
                        <li>
                            <?php if (($action['kind'] ?? '') === 'rsvp'): ?>
                                <form method="post" data-confirm="<?= e($rsvpConfirm) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="batch_id" value="<?= (int) $id ?>">
                                    <input type="hidden" name="enable_rsvp" value="1">
                                    <button class="dropdown-item" type="submit">
                                        <i class="bi <?= e((string) $action['icon']) ?> me-2"></i><?= e((string) $action['label']) ?>
                                    </button>
                                </form>
                            <?php else: ?>
                                <a class="dropdown-item" href="<?= e((string) ($action['href'] ?? '#')) ?>">
                                    <i class="bi <?= e((string) $action['icon']) ?> me-2"></i><?= e((string) $action['label']) ?>
                                </a>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($showSendGroup): ?>
        <div class="corr-actions-group corr-actions-sensitive batch-send-actions" role="group" aria-label="<?= e(__('corr.sensitive_actions')) ?>">
            <form method="post" action="<?= e(base_url('outgoing/send.php')) ?>" id="batch-send-form" class="corr-actions-group" data-none-selected="<?= e(__('send.none_selected')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="batch_id" value="<?= (int) $id ?>">
                <?php
                $emailBtnOk = $emailReady && $issuableEmail > 0;
                $waBtnOk = $waReady && $issuableMobile > 0;
                $deliveryBtnOk = $issuableAny > 0;
                $sendBlockTip = $issuableAny > 0 ? '' : $batchBlockReason;
                ?>
                <?php if ($canSendMsg && $batchElectronic): ?>
                <button class="btn btn-kff" type="submit" name="channel" value="email" data-batch-send data-need-ready="<?= $emailBtnOk ? '1' : '0' ?>" data-confirm="<?= e(__('send.confirm_batch_email')) ?>" <?= $emailBtnOk ? '' : 'disabled' ?> title="<?= e($sendBlockTip) ?>">
                    <i class="bi bi-envelope me-1"></i><?= e(__('send.batch_email')) ?>
                </button>
                <button class="btn btn-outline-secondary" type="button" id="batch-email-preview-btn" <?= $emailCount > 0 ? '' : 'disabled' ?> title="<?= e(__('send.email_preview')) ?>">
                    <i class="bi bi-eye me-1"></i><?= e(__('send.email_preview')) ?>
                </button>
                <button class="btn btn-success" type="submit" name="channel" value="whatsapp" data-batch-send data-need-ready="<?= $waBtnOk ? '1' : '0' ?>" data-confirm="<?= e(__('send.confirm_batch_wa')) ?>" <?= $waBtnOk ? '' : 'disabled' ?> title="<?= e($sendBlockTip) ?>">
                    <i class="bi bi-whatsapp me-1"></i><?= e(__('send.batch_wa')) ?>
                </button>
                <?php elseif ($batchElectronic && $sendMsgUi === 'deny'): ?>
                <?= dms_perm_denied_btn(__('send.batch_email'), 'bi-envelope', 'btn-kff') ?>
                <?= dms_perm_denied_btn(__('send.batch_wa'), 'bi-whatsapp', 'btn-success') ?>
                <?php endif; ?>
                <?php if ($canDeliver): ?>
                <button class="btn btn-outline-warning" type="submit" name="channel" value="delivery" data-batch-send data-need-ready="<?= $deliveryBtnOk ? '1' : '0' ?>" data-confirm="<?= e(__('send.confirm_batch_delivery')) ?>" <?= $deliveryBtnOk ? '' : 'disabled' ?> title="<?= e($sendBlockTip) ?>">
                    <i class="bi bi-clipboard-check me-1"></i><?= e(__('send.batch_delivery')) ?>
                </button>
                <?php elseif ($deliverUi === 'deny'): ?>
                <?= dms_perm_denied_btn(__('send.batch_delivery'), 'bi-clipboard-check', 'btn-outline-warning') ?>
                <?php endif; ?>
            </form>
        </div>
        <?php endif; ?>

        <?php if ($hasBatchSecondary): ?>
        <div class="corr-actions-group corr-actions-secondary corr-actions-desktop-only">
            <?php foreach ($batchSecondary as $action): ?>
                <?php if (($action['kind'] ?? '') === 'rsvp'): ?>
                    <form method="post" class="d-inline" data-confirm="<?= e($rsvpConfirm) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="batch_id" value="<?= (int) $id ?>">
                        <input type="hidden" name="enable_rsvp" value="1">
                        <button class="btn <?= e((string) ($action['class'] ?? 'btn-outline-secondary')) ?>" type="submit">
                            <i class="bi <?= e((string) $action['icon']) ?> me-1"></i><?= e((string) $action['label']) ?>
                        </button>
                    </form>
                <?php else: ?>
                    <a class="btn <?= e((string) ($action['class'] ?? 'btn-outline-secondary')) ?>" href="<?= e((string) ($action['href'] ?? '#')) ?>">
                        <i class="bi <?= e((string) $action['icon']) ?> me-1"></i><?= e((string) $action['label']) ?>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($apprSummary['configured'])): ?>
<?php
$apprLine = str_replace(
    [':approved', ':pending', ':rejected', ':none'],
    [
        (string) (int) ($apprSummary['approved'] ?? 0),
        (string) (int) ($apprSummary['pending'] ?? 0),
        (string) (int) ($apprSummary['rejected'] ?? 0),
        (string) (int) ($apprSummary['none'] ?? 0),
    ],
    __('issue_appr.batch_summary')
);
$batchReviewN = (int) ($apprSummary['in_review'] ?? 0) + (int) ($apprSummary['returned'] ?? 0);
$batchApproveN = (int) ($apprSummary['pending'] ?? 0) - (int) ($apprSummary['in_review'] ?? 0);
$batchPanelTitle = __('issue_appr.panel_title');
if ($batchReviewN > 0 && $batchApproveN <= 0) {
    $batchPanelTitle = __('issue_appr.panel_title_review');
} elseif ($batchApproveN > 0 && $batchReviewN === 0) {
    $batchPanelTitle = __('issue_appr.panel_title_approve');
}
?>
<div class="card-panel mb-3 no-print">
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
        <span><i class="bi bi-shield-check me-1"></i><?= e($batchPanelTitle) ?></span>
        <span class="badge <?= $batchCanIssue ? 'text-bg-success' : 'text-bg-warning' ?>">
            <?= e($batchCanIssue ? __('issue_appr.status_comms') : (function_exists('outgoing_appr_text') ? outgoing_appr_text('issue_appr.status_pending_sg') : __('issue_appr.status_pending_sg'))) ?>
        </span>
    </div>
    <div class="card-body">
        <p class="small text-muted mb-2"><?= e($apprLine) ?></p>
        <?php if (!$batchCanIssue && $batchBlockReason !== ''): ?>
            <div class="alert alert-warning py-2 small mb-2"><?= e($batchBlockReason) ?></div>
        <?php endif; ?>
        <?php if (!empty($apprSummary['can_request'])): ?>
            <form method="post" action="<?= e(base_url('outgoing/issue_approval.php')) ?>" class="d-inline">
                <?= csrf_field() ?>
                <input type="hidden" name="batch_id" value="<?= (int) $id ?>">
                <input type="hidden" name="action" value="request">
                <button type="submit" class="btn btn-kff btn-sm">
                    <i class="bi bi-send-check me-1"></i><?= e(function_exists('outgoing_review_enabled') && outgoing_review_enabled() ? __('issue_review.batch_request') : __('issue_appr.batch_request')) ?>
                </button>
            </form>
        <?php endif; ?>
        <?php if ((int) ($apprSummary['in_review'] ?? 0) > 0 || (int) ($apprSummary['returned'] ?? 0) > 0): ?>
            <p class="small text-muted mb-2"><?= e(str_replace(
                [':review', ':returned'],
                [(string) (int) ($apprSummary['in_review'] ?? 0), (string) (int) ($apprSummary['returned'] ?? 0)],
                __('issue_review.batch_summary')
            )) ?></p>
        <?php endif; ?>
        <?php if (!empty($apprSummary['can_review'])): ?>
            <form method="post" action="<?= e(base_url('outgoing/issue_approval.php')) ?>" class="row g-2 align-items-end mt-2">
                <?= csrf_field() ?>
                <input type="hidden" name="batch_id" value="<?= (int) $id ?>">
                <div class="col-md-6">
                    <label class="form-label small mb-1" for="batch_issue_review_note"><?= e(__('issue_review.note')) ?></label>
                    <input type="text" name="note" id="batch_issue_review_note" class="form-control form-control-sm" maxlength="500"
                           placeholder="<?= e(__('issue_review.note_placeholder')) ?>">
                </div>
                <div class="col-md-6 d-flex flex-wrap gap-2">
                    <button type="submit" name="action" value="review_pass" class="btn btn-success btn-sm">
                        <i class="bi bi-check2-circle me-1"></i><?= e(__('issue_review.batch_pass')) ?>
                    </button>
                    <button type="submit" name="action" value="review_return" class="btn btn-outline-warning btn-sm"
                            data-note-required="batch_issue_review_note"
                            data-note-required-msg="<?= e(__('issue_review.note_required')) ?>">
                        <i class="bi bi-arrow-counterclockwise me-1"></i><?= e(__('issue_review.batch_return')) ?>
                    </button>
                </div>
            </form>
        <?php endif; ?>
        <?php if (!empty($apprSummary['can_decide'])): ?>
            <?php
            $batchIsAlternate = array_filter(
                $items,
                static fn (array $r): bool => outgoing_user_can_decide_approval($user, $r)
                    && !appr_is_admin_decide_override($user, $r)
                    && outgoing_user_is_alternate_approver($user, $r)
            ) !== [];
            $batchNoteLabel = $batchIsAlternate ? __('issue_appr.alternate_reason') : __('issue_appr.note');
            ?>
            <?php if ($batchIsAlternate): ?>
                <div class="alert alert-info py-2 small mb-2"><?= e(function_exists('outgoing_appr_text') ? outgoing_appr_text('issue_appr.alternate_decide_hint') : __('issue_appr.alternate_decide_hint')) ?></div>
            <?php endif; ?>
            <form method="post" action="<?= e(base_url('outgoing/issue_approval.php')) ?>" class="row g-2 align-items-end mt-2">
                <?= csrf_field() ?>
                <input type="hidden" name="batch_id" value="<?= (int) $id ?>">
                <div class="col-md-6">
                    <label class="form-label small mb-1" for="batch_issue_appr_note"><?= e($batchNoteLabel) ?><?= $batchIsAlternate ? ' *' : '' ?></label>
                    <input type="text" name="note" id="batch_issue_appr_note" class="form-control form-control-sm" maxlength="500"
                           <?= $batchIsAlternate ? 'required' : '' ?>
                           placeholder="<?= e($batchIsAlternate ? __('issue_appr.alternate_reason_placeholder') : '') ?>">
                </div>
                <div class="col-md-6 d-flex flex-wrap gap-2">
                    <button type="submit" name="action" value="approve" class="btn btn-success btn-sm">
                        <i class="bi bi-check2-circle me-1"></i><?= e(__('issue_appr.batch_approve')) ?>
                    </button>
                    <button type="submit" name="action" value="reject" class="btn btn-outline-danger btn-sm" title="<?= e(__('issue_appr.reject_hint')) ?>">
                        <i class="bi bi-x-circle me-1"></i><?= e(__('issue_appr.batch_reject')) ?>
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php
$batchTotal = count($items);
$batchUnsent = $batchTotal - (int) $anySent;
?>
<div class="card-panel mb-3 no-print batch-summary-panel">
    <div class="batch-summary-bar">
        <span class="batch-summary-title" title="<?= e(__('send.batch_hint')) ?>"><?= e(__('bulk.summary')) ?></span>
        <div class="batch-chips">
            <span class="batch-chip"><?= e(__('send.selected_count')) ?> <b><span data-batch-selected-count><?= (int) $batchTotal ?></span>/<?= (int) $batchTotal ?></b></span>
            <span class="batch-chip"><?= e(__('entities.email')) ?> <b><?= (int) $emailCount ?>/<?= (int) $batchTotal ?></b></span>
            <span class="batch-chip"><?= e(__('entities.mobile')) ?> <b><?= (int) $mobileCount ?>/<?= (int) $batchTotal ?></b></span>
            <span class="batch-chip is-ok"><?= e(__('send.stat_sent')) ?> <b><?= (int) $anySent ?></b></span>
            <span class="batch-chip<?= $batchUnsent > 0 ? ' is-warn' : '' ?>"><?= e(__('send.stat_unsent')) ?> <b><?= (int) $batchUnsent ?></b></span>
            <span class="batch-chip"><?= e(__('send.email')) ?> <b><?= (int) $emailSent ?></b> · <?= e(__('send.stat_unsent')) ?> <?= (int) $emailPending ?></span>
            <span class="batch-chip"><?= e(__('send.delivery')) ?> <b><?= (int) $deliverySent ?></b></span>
            <?php if ((int) $noEmail > 0): ?>
            <span class="batch-chip is-muted"><?= e(__('send.stat_no_email')) ?> <b><?= (int) $noEmail ?></b></span>
            <?php endif; ?>
            <?php if ($rsvpOn): ?>
            <span class="batch-chip is-ok"><?= e(__('rsvp.attending')) ?> <b><?= (int) $rsvpSummary['attending'] ?></b></span>
            <span class="batch-chip is-danger"><?= e(__('rsvp.declined')) ?> <b><?= (int) $rsvpSummary['declined'] ?></b></span>
            <span class="batch-chip is-muted"><?= e(__('rsvp.pending')) ?> <b><?= (int) $rsvpSummary['pending'] ?></b></span>
            <?php endif; ?>
        </div>
        <?php if (!$emailReady || !$waReady): ?>
        <div class="batch-summary-notes">
            <?php if (!$emailReady): ?><span><?= e(__('send.need_email_settings')) ?></span><?php endif; ?>
            <?php if (!$waReady): ?><span><?= e(__('send.need_wa_settings')) ?></span><?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="card-panel">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="batch-results-table">
            <thead>
                <tr>
                    <?php if ($canSend): ?>
                        <th class="batch-select-col">
                            <input type="checkbox" class="form-check-input" data-batch-select-all checked title="<?= e(__('groups.select_all')) ?>">
                        </th>
                    <?php endif; ?>
                    <th><?= e(__('corr.number')) ?></th>
                    <th><?= e(__('corr.party_to')) ?></th>
                    <th><?= e(__('entities.section_basic')) ?></th>
                    <th class="text-center"><?= e(__('send.email')) ?></th>
                    <th class="text-center batch-send-icon-col" title="<?= e(__('send.do_email')) ?>"><i class="bi bi-envelope"></i></th>
                    <th class="text-center"><?= e(__('send.whatsapp')) ?></th>
                    <th class="text-center batch-send-icon-col" title="<?= e(__('send.do_wa')) ?>"><i class="bi bi-whatsapp"></i></th>
                    <th><?= e(__('corr.status')) ?></th>
                    <?php if (!empty($apprSummary['configured'])): ?>
                    <th><?= e(__('issue_appr.col')) ?></th>
                    <?php endif; ?>
                    <th class="text-center batch-send-icon-col" title="<?= e(__('send.do_delivery')) ?>"><i class="bi bi-clipboard-check"></i></th>
                    <?php if ($rsvpOn): ?>
                        <th><?= e(__('rsvp.status')) ?></th>
                    <?php endif; ?>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $r): ?>
                <?php
                $last = $lastMap[(int) $r['id']] ?? ['email' => null, 'whatsapp' => null, 'delivery' => null];
                $email = dms_corr_email_address($r);
                $mobile = dms_corr_mobile_number($r);
                $rowMailReady = dms_mail_ready((int) ($r['department_id'] ?? 0) ?: null);
                $rowCancelled = correspondence_is_cancelled($r);
                $rowCanIssue = $rowCancelled
                    ? false
                    : (!function_exists('outgoing_can_issue') || outgoing_can_issue($r));
                $rowBlock = (!$rowCanIssue && function_exists('outgoing_approval_block_reason'))
                    ? outgoing_approval_block_reason($r)
                    : '';
                ?>
                <tr<?= $rowCancelled ? ' class="table-secondary"' : '' ?>>
                    <?php if ($canSend): ?>
                        <td class="batch-select-col">
                            <input type="checkbox" class="form-check-input batch-select-row" name="ids[]" value="<?= (int) $r['id'] ?>" form="batch-send-form"<?= ($rowCancelled || !$rowCanIssue) ? ' disabled' : ' checked' ?> data-can-issue="<?= ($rowCancelled || !$rowCanIssue) ? '0' : '1' ?>" data-has-email="<?= $email !== '' ? '1' : '0' ?>" data-has-mobile="<?= $mobile !== '' ? '1' : '0' ?>">
                        </td>
                    <?php endif; ?>
                    <td class="fw-semibold" dir="ltr"><?= e($r['ref_number']) ?></td>
                    <?php
                    $partyArchived = correspondence_archived_party($r);
                    render_entity_party_columns($partyArchived);
                    ?>
                    <td class="batch-channel-cell">
                        <div class="batch-channel-stack">
                        <?php if ($email !== ''): ?>
                            <div class="batch-channel-value" dir="ltr"><?= e($email) ?></div>
                        <?php else: ?>
                            <span class="text-muted small"><?= e(__('send.missing_email')) ?></span>
                        <?php endif; ?>
                        <div class="batch-channel-status small <?= (($last['email']['status'] ?? '') === 'sent') ? 'text-success' : ((($last['email']['status'] ?? '') === 'failed') ? 'text-danger' : 'text-muted') ?>">
                            <?= e(dms_delivery_status_label($last['email'] ?? null)) ?>
                        </div>
                        </div>
                    </td>
                    <td class="batch-send-icon-col text-center">
                        <?php if ($canSendMsg && $rowCanIssue && $email !== '' && $rowMailReady): ?>
                            <form method="post" action="<?= e(base_url('outgoing/send.php')) ?>" class="d-inline" data-confirm="<?= e(__('send.confirm_email')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="correspondence_id" value="<?= (int) $r['id'] ?>">
                                <input type="hidden" name="channel" value="email">
                                <input type="hidden" name="return_batch" value="<?= (int) $id ?>">
                                <button type="submit" class="batch-send-icon batch-send-icon--email<?= (($last['email']['status'] ?? '') === 'sent') ? ' is-sent' : '' ?>" title="<?= e(__('send.do_email')) ?>">
                                    <i class="bi <?= (($last['email']['status'] ?? '') === 'sent') ? 'bi-envelope-check' : 'bi-envelope' ?>"></i>
                                </button>
                            </form>
                        <?php else: ?>
                            <span class="batch-send-icon batch-send-icon--email is-disabled" title="<?= e(!$canSendMsg ? __('perm.denied_tip') : (!$rowCanIssue ? ($rowBlock !== '' ? $rowBlock : __('issue_appr.blocked_pending')) : ($email === '' ? __('send.need_entity_email') : __('send.need_email_settings')))) ?>">
                                <i class="bi bi-envelope"></i>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="batch-channel-cell">
                        <div class="batch-channel-stack">
                        <?php if ($mobile !== ''): ?>
                            <div class="batch-channel-value" dir="ltr">+<?= e($mobile) ?></div>
                        <?php else: ?>
                            <span class="text-muted small"><?= e(__('send.missing_mobile')) ?></span>
                        <?php endif; ?>
                        <div class="batch-channel-status small <?= (($last['whatsapp']['status'] ?? '') === 'sent') ? 'text-success' : ((($last['whatsapp']['status'] ?? '') === 'failed') ? 'text-danger' : 'text-muted') ?>">
                            <?= e(dms_delivery_status_label($last['whatsapp'] ?? null)) ?>
                        </div>
                        </div>
                    </td>
                    <td class="batch-send-icon-col text-center">
                        <?php if ($canSendMsg && $rowCanIssue && $mobile !== '' && $waReady): ?>
                            <form method="post" action="<?= e(base_url('outgoing/send.php')) ?>" class="d-inline" data-confirm="<?= e(__('send.confirm_wa')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="correspondence_id" value="<?= (int) $r['id'] ?>">
                                <input type="hidden" name="channel" value="whatsapp">
                                <input type="hidden" name="return_batch" value="<?= (int) $id ?>">
                                <button type="submit" class="batch-send-icon batch-send-icon--wa<?= (($last['whatsapp']['status'] ?? '') === 'sent') ? ' is-sent' : '' ?>" title="<?= e(__('send.do_wa')) ?>">
                                    <i class="bi bi-whatsapp"></i>
                                </button>
                            </form>
                        <?php else: ?>
                            <span class="batch-send-icon batch-send-icon--wa is-disabled" title="<?= e(!$canSendMsg ? __('perm.denied_tip') : (!$rowCanIssue ? ($rowBlock !== '' ? $rowBlock : __('issue_appr.blocked_pending')) : ($mobile === '' ? __('send.need_entity_mobile') : __('send.need_wa_settings')))) ?>">
                                <i class="bi bi-whatsapp"></i>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td><?= status_badge((string) ($r['status'] ?? ''), $r['cancel_kind'] ?? null) ?></td>
                    <?php if (!empty($apprSummary['configured'])): ?>
                    <td><?= function_exists('outgoing_approval_badge') ? outgoing_approval_badge($r) : '' ?></td>
                    <?php endif; ?>
                    <td class="batch-send-icon-col text-center">
                        <?php if ($canDeliver && $rowCanIssue): ?>
                            <form method="post" action="<?= e(base_url('outgoing/send.php')) ?>" class="d-inline" data-confirm="<?= e(__('send.confirm_delivery')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="correspondence_id" value="<?= (int) $r['id'] ?>">
                                <input type="hidden" name="channel" value="delivery">
                                <input type="hidden" name="return_batch" value="<?= (int) $id ?>">
                                <button type="submit" class="batch-send-icon batch-send-icon--delivery<?= (($last['delivery']['status'] ?? '') === 'sent') ? ' is-sent' : '' ?>" title="<?= e(__('send.do_delivery')) ?>">
                                    <i class="bi <?= (($last['delivery']['status'] ?? '') === 'sent') ? 'bi-clipboard-check-fill' : 'bi-clipboard-check' ?>"></i>
                                </button>
                            </form>
                        <?php else: ?>
                            <span class="batch-send-icon batch-send-icon--delivery is-disabled" title="<?= e(!$canDeliver ? __('perm.denied_tip') : (!$rowCanIssue ? ($rowBlock !== '' ? $rowBlock : __('issue_appr.blocked_pending')) : '')) ?>">
                                <i class="bi bi-clipboard-check"></i>
                            </span>
                        <?php endif; ?>
                    </td>
                    <?php if ($rsvpOn): ?>
                        <td>
                            <?= rsvp_response_badge($r['rsvp_response'] ?? null) ?>
                            <?php if (!empty($r['rsvp_at'])): ?>
                                <div class="small text-muted"><?= e(format_datetime($r['rsvp_at'])) ?></div>
                            <?php endif; ?>
                        </td>
                    <?php endif; ?>
                    <td class="text-end text-nowrap">
                        <?php if ($email !== ''): ?>
                            <button type="button"
                                    class="btn btn-sm btn-outline-secondary me-1"
                                    data-email-preview="<?= (int) $r['id'] ?>"
                                    title="<?= e(__('send.email_preview')) ?>">
                                <i class="bi bi-envelope-open me-1"></i><?= e(__('send.email_preview')) ?>
                            </button>
                        <?php endif; ?>
                        <a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('outgoing/view.php?id=' . (int) $r['id'])) ?>"><?= e(__('action.view')) ?></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="batchEmailPreviewModal" tabindex="-1" aria-labelledby="batchEmailPreviewTitle" aria-hidden="true"
     data-preview-url="<?= e(base_url('outgoing/email_preview.php')) ?>"
     data-preview-fail="<?= e(__('send.email_preview_fail')) ?>"
     data-preview-loading="<?= e(__('send.email_preview_loading')) ?>"
     data-none-selected="<?= e(__('send.none_selected')) ?>">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="batchEmailPreviewTitle">
                    <i class="bi bi-envelope-open me-1"></i><?= e(__('send.email_preview_title')) ?>
                    <span class="text-muted fw-normal small" data-email-preview-ref></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(__('send.email_preview_close')) ?>"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <div class="small text-muted"><?= e(__('send.email_preview_recipient')) ?></div>
                    <div class="fw-semibold" data-email-preview-recipient>—</div>
                </div>
                <div class="mb-3">
                    <div class="small text-muted"><?= e(__('send.email_preview_to')) ?></div>
                    <div class="fw-semibold email-preview-to" data-email-preview-to>—</div>
                </div>
                <div class="mb-3">
                    <div class="small text-muted"><?= e(__('send.email_preview_subject')) ?></div>
                    <div class="fw-semibold" data-email-preview-subject>—</div>
                </div>
                <div>
                    <div class="small text-muted mb-2"><?= e(__('send.email_preview_body')) ?></div>
                    <div class="border rounded p-3 bg-white batch-email-preview-body" data-email-preview-body>
                        <span class="text-muted"><?= e(__('send.email_preview_loading')) ?></span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(__('send.email_preview_close')) ?></button>
            </div>
        </div>
    </div>
</div>
<?php
$previewUrlJson = json_encode(base_url('outgoing/email_preview.php'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$pageScripts = <<<JS
<script>
(function () {
  var form = document.getElementById('batch-send-form');
  var table = document.getElementById('batch-results-table');
  if (!form || !table) return;
  var master = table.querySelector('[data-batch-select-all]');
  var countEl = document.querySelector('[data-batch-selected-count]');
  var noneMsg = form.getAttribute('data-none-selected') || '';
  var previewBtn = document.getElementById('batch-email-preview-btn');
  var modalEl = document.getElementById('batchEmailPreviewModal');
  var previewUrl = (modalEl && modalEl.getAttribute('data-preview-url')) || {$previewUrlJson};
  var previewFail = (modalEl && modalEl.getAttribute('data-preview-fail')) || '';
  var previewLoading = (modalEl && modalEl.getAttribute('data-preview-loading')) || '';

  function boxes() {
    return Array.prototype.slice.call(table.querySelectorAll('.batch-select-row'));
  }
  function selectedFor(channel) {
    return boxes().filter(function (b) {
      if (!b.checked || b.disabled || b.getAttribute('data-can-issue') === '0') return false;
      if (channel === 'email') return b.getAttribute('data-has-email') === '1';
      if (channel === 'whatsapp') return b.getAttribute('data-has-mobile') === '1';
      return true;
    }).length;
  }
  function refresh() {
    var all = boxes();
    var enabled = all.filter(function (b) { return !b.disabled; });
    var n = all.filter(function (b) { return b.checked && !b.disabled; }).length;
    if (countEl) countEl.textContent = String(n);
    if (master) {
      master.checked = enabled.length > 0 && n === enabled.length;
      master.indeterminate = n > 0 && n < enabled.length;
    }
    form.querySelectorAll('[data-batch-send]').forEach(function (btn) {
      var ch = btn.getAttribute('value') || '';
      btn.disabled = selectedFor(ch) === 0 || btn.getAttribute('data-need-ready') !== '1';
    });
    if (previewBtn) {
      previewBtn.disabled = selectedFor('email') === 0;
    }
  }
  if (master) {
    master.addEventListener('change', function () {
      var on = master.checked;
      boxes().forEach(function (b) {
        if (!b.disabled) b.checked = on;
      });
      refresh();
    });
  }
  table.addEventListener('change', function (e) {
    if (e.target && e.target.classList.contains('batch-select-row')) refresh();
  });
  form.querySelectorAll('[data-batch-send]').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      if (boxes().some(function (b) { return b.checked; })) return;
      e.preventDefault();
      e.stopImmediatePropagation();
      if (window.DmsSwal && typeof window.DmsSwal.warning === 'function') {
        window.DmsSwal.warning(noneMsg);
      } else {
        window.alert(noneMsg);
      }
    });
  });

  function fillPreview(data) {
    if (!modalEl) return;
    var nameEl = modalEl.querySelector('[data-email-preview-recipient]');
    var toEl = modalEl.querySelector('[data-email-preview-to]');
    var subEl = modalEl.querySelector('[data-email-preview-subject]');
    var bodyEl = modalEl.querySelector('[data-email-preview-body]');
    var refEl = modalEl.querySelector('[data-email-preview-ref]');
    if (nameEl) nameEl.textContent = data.recipient || '—';
    if (toEl) toEl.textContent = data.to || '—';
    if (subEl) subEl.textContent = data.subject || '—';
    if (refEl) refEl.textContent = data.ref ? ('— ' + data.ref) : '';
    if (bodyEl) bodyEl.innerHTML = data.html || '';
  }

  function showPreview(id) {
    if (!modalEl || !id) return;
    var bodyEl = modalEl.querySelector('[data-email-preview-body]');
    if (bodyEl) bodyEl.innerHTML = '<span class="text-muted">' + (previewLoading || '') + '</span>';
    var nameEl = modalEl.querySelector('[data-email-preview-recipient]');
    var toEl = modalEl.querySelector('[data-email-preview-to]');
    var subEl = modalEl.querySelector('[data-email-preview-subject]');
    var refEl = modalEl.querySelector('[data-email-preview-ref]');
    if (nameEl) nameEl.textContent = '—';
    if (toEl) toEl.textContent = '—';
    if (subEl) subEl.textContent = '—';
    if (refEl) refEl.textContent = '';
    var modal = window.bootstrap && bootstrap.Modal ? bootstrap.Modal.getOrCreateInstance(modalEl) : null;
    if (modal) modal.show();
    fetch(previewUrl + (previewUrl.indexOf('?') >= 0 ? '&' : '?') + 'id=' + encodeURIComponent(String(id)), {
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' }
    }).then(function (res) {
      return res.json().then(function (data) {
        return { ok: res.ok, data: data };
      });
    }).then(function (pack) {
      if (!pack.ok || !pack.data || !pack.data.ok) {
        throw new Error((pack.data && pack.data.error) || previewFail);
      }
      fillPreview(pack.data);
    }).catch(function (err) {
      if (bodyEl) {
        bodyEl.innerHTML = '<span class="text-danger">' + (err && err.message ? err.message : previewFail) + '</span>';
      }
    });
  }

  table.addEventListener('click', function (e) {
    var btn = e.target && e.target.closest ? e.target.closest('[data-email-preview]') : null;
    if (!btn) return;
    e.preventDefault();
    showPreview(btn.getAttribute('data-email-preview'));
  });

  if (previewBtn) {
    previewBtn.addEventListener('click', function () {
      var selected = boxes().filter(function (b) { return b.checked && !b.disabled; });
      if (!selected.length) {
        if (window.DmsSwal && typeof window.DmsSwal.warning === 'function') {
          window.DmsSwal.warning(noneMsg);
        } else {
          window.alert(noneMsg);
        }
        return;
      }
      showPreview(selected[0].value);
    });
  }

  refresh();
})();
</script>
JS;
require __DIR__ . '/../includes/footer.php';
?>

