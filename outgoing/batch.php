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
// إذا تغيّر مسار الإعدادات: أخرج الدفعة العالقة كما في الصادر المفرد
$batchPathReleased = false;
if (function_exists('outgoing_batch_prep_release_if_disabled')
    && function_exists('outgoing_prep_enabled')
    && !outgoing_prep_enabled()
) {
    $batchPathReleased = outgoing_batch_prep_release_if_disabled($id, $user) > 0 || $batchPathReleased;
}
if (function_exists('outgoing_batch_issue_flow_release_if_inactive')
    && function_exists('outgoing_issue_flow_active')
    && !outgoing_issue_flow_active()
) {
    $batchPathReleased = outgoing_batch_issue_flow_release_if_inactive($id, $user) > 0 || $batchPathReleased;
}
if ($batchPathReleased) {
    $items = outgoing_batch_items($id, $user);
}

$canRsvp = outgoing_batch_can_enable_rsvp($id, $user);
$rsvpConfirm = outgoing_batch_rsvp_in_cycle($id)
    ? __('rsvp.enable_confirm') . ' ' . __('rsvp.enable_confirm_cycle')
    : __('rsvp.enable_confirm');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['batch_prep_submit'])) {
    verify_csrf();
    try {
        $result = outgoing_batch_prep_submit($id, $user);
        flash('success', $result === 'converted' ? __('bulk.converted') : __('bulk.submitted'));
    } catch (Throwable $e) {
        flash('danger', $e->getMessage());
    }
    redirect(base_url('outgoing/batch.php?id=' . $id));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['batch_prep_decision'])) {
    verify_csrf();
    $prepApprove = (string) $_POST['batch_prep_decision'] === 'approve';
    try {
        outgoing_batch_prep_decide($id, $user, $prepApprove, (string) ($_POST['note'] ?? ''));
        flash('success', $prepApprove ? __('bulk.converted') : __('bulk.returned'));
        if (!$prepApprove && function_exists('outgoing_batch_draft_editable') && outgoing_batch_draft_editable($user, $id)) {
            redirect(base_url('outgoing/bulk.php?id=' . $id));
        }
    } catch (Throwable $e) {
        flash('danger', $e->getMessage());
    }
    redirect(base_url('outgoing/batch.php?id=' . $id));
}

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['recipient_delivery']) && is_array($_POST['recipient_delivery'])) {
    $deliveryAjax = (string) ($_POST['ajax'] ?? '') === '1';
    verify_csrf();
    try {
        if (!function_exists('outgoing_delivery_edit_block')) {
            throw new RuntimeException(__('auth.forbidden'));
        }
        outgoing_batch_set_recipient_modes($id, $_POST['recipient_delivery']);
        if ($deliveryAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => true, 'message' => __('bulk.delivery_saved')], JSON_UNESCAPED_UNICODE);
            exit;
        }
        flash('success', __('bulk.delivery_saved'));
    } catch (Throwable $e) {
        if ($deliveryAjax) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
            exit;
        }
        flash('danger', $e->getMessage());
    }
    redirect(base_url('outgoing/batch.php?id=' . $id));
}

$rsvpOn = !empty($batch['requires_rsvp']) || correspondence_rsvp_required($items[0] ?? []);
$rsvpSummary = batch_rsvp_summary($items);

$itemIds = array_map(static fn(array $row): int => (int) $row['id'], $items);
$lastMap = dms_corr_last_deliveries_map($itemIds);
$sendMsgUi = dms_perm_ui('outgoing.send', $user);
$deliverUi = dms_perm_ui('outgoing.receipt', $user);
$canSendMsg = $sendMsgUi === 'allow';
$canDeliver = $deliverUi === 'allow';
$canSend = $canSendMsg || $canDeliver;
$showSendGroup = $sendMsgUi !== 'hide' || $deliverUi !== 'hide';
$batchElectronic = false;
$batchPaper = false;
foreach ($items as $row) {
    if (function_exists('correspondence_intend_electronic') && correspondence_intend_electronic($row)) {
        $batchElectronic = true;
    }
    if (function_exists('correspondence_intend_paper') && correspondence_intend_paper($row)) {
        $batchPaper = true;
    }
}
$apprSummary = function_exists('outgoing_batch_approval_summary')
    ? outgoing_batch_approval_summary($items, $user)
    : ['configured' => false, 'can_issue' => true, 'block_reason' => '', 'can_request' => false, 'can_decide' => false];
$batchPrepPhase = function_exists('outgoing_batch_prep_phase') ? outgoing_batch_prep_phase($items) : '';
$batchInPrep = in_array($batchPrepPhase, ['draft', 'pending_signer'], true);
$batchDraftEdit = $batchPrepPhase === 'draft'
    && function_exists('outgoing_batch_draft_editable')
    && outgoing_batch_draft_editable($user, $id);
$batchPrepSample = null;
foreach ($items as $prepRow) {
    if (($prepRow['type'] ?? '') === 'outgoing' && (!function_exists('correspondence_is_cancelled') || !correspondence_is_cancelled($prepRow))) {
        $batchPrepSample = $prepRow;
        break;
    }
}
$batchPrepCanSubmit = $batchPrepPhase === 'draft'
    && $batchPrepSample
    && function_exists('outgoing_user_can_prep_submit')
    && outgoing_user_can_prep_submit($user, $batchPrepSample);
$batchPrepSelf = $batchPrepCanSubmit
    && function_exists('outgoing_user_is_prep_signer')
    && outgoing_user_is_prep_signer($user, $batchPrepSample);
$batchPrepCanDecide = $batchPrepPhase === 'pending_signer'
    && $batchPrepSample
    && function_exists('outgoing_user_can_prep_decide')
    && outgoing_user_can_prep_decide($user, $batchPrepSample);
$batchPrepNote = trim((string) ($batchPrepSample['outgoing_prep_note'] ?? ''));
$batchCanIssue = !empty($apprSummary['can_issue']);
$batchBlockReason = (string) ($apprSummary['block_reason'] ?? '');
$printSample = $batchPrepSample ?? ($items[0] ?? null);
$commsSample = null;
foreach ($items as $stageRow) {
    if (($stageRow['type'] ?? '') !== 'outgoing') {
        continue;
    }
    if (function_exists('correspondence_is_cancelled') && correspondence_is_cancelled($stageRow)) {
        continue;
    }
    if (function_exists('outgoing_is_comms_print_stage') && outgoing_is_comms_print_stage($stageRow)) {
        $commsSample = $stageRow;
        break;
    }
}
// مرحلة الاتصالات مستقلة: الإرسال/الطباعة للاتصالات الإدارية فقط
$issuedToComms = $commsSample !== null;
$canRunIssued = !$issuedToComms
    || (function_exists('outgoing_user_can_dispatch') && outgoing_user_can_dispatch($user, $commsSample));
$actionSample = $commsSample ?? $printSample;
if ($issuedToComms && !$canRunIssued) {
    $batchBlockReason = __('issue_appr.comms_only_issue');
}
if (!$canRunIssued) {
    $canSendMsg = false;
    $canDeliver = false;
    $canSend = false;
    $showSendGroup = false;
    $sendMsgUi = 'hide';
    $deliverUi = 'hide';
}
$canPrintForm = $actionSample && $canRunIssued && function_exists('corr_user_can_print') && corr_user_can_print('outgoing', 'form', $user, $actionSample);
$canPrintEnvelope = $actionSample && $canRunIssued && function_exists('corr_user_can_print') && corr_user_can_print('outgoing', 'envelope', $user, $actionSample);
$canPrintThermal = $actionSample && $canRunIssued && function_exists('corr_user_can_print') && corr_user_can_print('outgoing', 'thermal', $user, $actionSample);
$canOfficialPrint = $canPrintForm || ($canPrintEnvelope && $batchPaper) || ($canPrintThermal && $batchPaper);
// كشف نتائج الدفعة/الحضور: للمنشئ والاتصالات وكل من يرى الدفعة — ليس من حصر مرحلة الإصدار
$canRecipientsSheet = $items !== [];
$batchCreatorId = (int) ($batch['created_by'] ?? ($items[0]['batch_created_by'] ?? 0));
$isBatchCreator = $batchCreatorId > 0 && $batchCreatorId === (int) ($user['id'] ?? 0);
// عمود التحديد + المحدد: لمن ينفّذ الإرسال/الطباعة فقط — لا للمنشئ المتابع (بدون canRunIssued)
$canSelectRows = $batchCanIssue && $canRunIssued && ($canSend || $canOfficialPrint);
$canBatchDispatch = $canSendMsg || $canOfficialPrint;
$waReady = (dms_whatsapp_readiness()['state'] ?? '') === 'ready';
$emailReady = false;
$emailCount = 0;
$mobileCount = 0;
$emailSent = 0;
$emailPending = 0;
$noEmail = 0;
$anySent = 0;
$deliverySent = 0;
$electronicCount = 0;
$paperCount = 0;
$issuableAny = 0;
$issuablePaper = 0;
$issuableEmail = 0;
$issuableMobile = 0;
foreach ($items as $row) {
    $last = $lastMap[(int) $row['id']] ?? ['email' => null, 'whatsapp' => null, 'delivery' => null];
    $hasEmail = dms_corr_email_address($row) !== '';
    $hasMobile = dms_corr_mobile_number($row) !== '';
    $rowCancelled = correspondence_is_cancelled($row);
    $rowIssuable = !$rowCancelled
        && (function_exists('outgoing_user_may_issue')
            ? outgoing_user_may_issue($user, $row)
            : (!function_exists('outgoing_can_issue') || outgoing_can_issue($row)));
    $rowElectronic = function_exists('correspondence_intend_electronic') && correspondence_intend_electronic($row);
    $rowPaper = function_exists('correspondence_intend_paper') && correspondence_intend_paper($row);
    if (!$rowCancelled) {
        if ($rowElectronic) {
            $electronicCount++;
        }
        if ($rowPaper) {
            $paperCount++;
        }
    }
    if ($rowIssuable) {
        $issuableAny++;
        if ($rowPaper) {
            $issuablePaper++;
        }
        if ($rowElectronic && $hasEmail) {
            $issuableEmail++;
        }
        // واتساب: يكفي الجوال (حتى للتجربة على رقم شخصي دون إلكتروني)
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
if ($batchCanIssue && $canRsvp && !$rsvpOn) {
    $batchSecondary[] = [
        'kind' => 'rsvp',
        'label' => __('rsvp.enable'),
        'icon' => 'bi-check2-circle',
        'class' => 'btn-outline-secondary',
    ];
}
if ($batchCanIssue && $canBatchDispatch) {
    $batchSecondary[] = [
        'kind' => 'link',
        'label' => __('bulk.export_word'),
        'icon' => 'bi-file-earmark-word',
        'href' => base_url('outgoing/batch_word.php?id=' . $id),
        'class' => 'btn-outline-secondary',
        'icon_only' => true,
    ];
    $batchSecondary[] = [
        'kind' => 'link',
        'label' => __('bulk.export_merge'),
        'icon' => 'bi-filetype-csv',
        'href' => base_url('outgoing/batch_csv.php?id=' . $id),
        'class' => 'btn-outline-secondary',
        'icon_only' => true,
    ];
}
$hasBatchSecondary = $batchSecondary !== [];
?>
<div class="page-header no-print">
    <div class="corr-view-title-wrap">
        <h1 class="page-title"><?= e((string) ($batch['subject'] ?? __('bulk.result_title'))) ?></h1>
        <?php
        $batchFrom = isset($items[0]) && function_exists('letter_signature_context')
            ? letter_signature_context($items[0])
            : [];
        $batchFromName = trim(trim((string) ($batchFrom['prefix'] ?? '')) . ' ' . trim((string) ($batchFrom['person'] ?? '')));
        $batchFromJob = trim((string) ($batchFrom['job'] ?? ''));
        ?>
        <?php if ($batchFromName !== '' || $batchFromJob !== ''): ?>
        <p class="batch-from-once">
            <span class="batch-from-label"><?= e(__('corr.issuing_department')) ?>:</span>
            <?php if ($batchFromName !== ''): ?><span class="batch-from-name"><?= e($batchFromName) ?></span><?php endif; ?>
            <?php if ($batchFromJob !== ''): ?><span class="batch-from-job"><?= $batchFromName !== '' ? ' — ' : '' ?><?= e($batchFromJob) ?></span><?php endif; ?>
        </p>
        <?php endif; ?>
    </div>
    <div class="page-header-actions corr-view-actions">
        <div class="corr-actions-group corr-actions-primary">
            <?php if ($batchDraftEdit): ?>
            <?= btn_icon(__('bulk.edit_link'), [
                'href' => base_url('outgoing/bulk.php?id=' . $id),
                'icon' => 'bi-pencil-square',
                'class' => 'btn-kff',
            ]) ?>
            <?php endif; ?>
            <?php if ($batchPrepCanSubmit): ?>
            <form method="post" class="mb-0 d-inline-flex" data-confirm="<?= e($batchPrepSelf ? __('out_prep.confirm_submit_self') : __('out_prep.confirm_submit')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="batch_id" value="<?= (int) $id ?>">
                <?= btn_icon($batchPrepSelf ? __('out_prep.submit_self') : __('out_prep.submit'), [
                    'icon' => 'bi-send',
                    'class' => 'btn-kff',
                    'type' => 'submit',
                    'attrs' => 'name="batch_prep_submit" value="1"',
                ]) ?>
            </form>
            <?php endif; ?>

            <?php if ($canOfficialPrint || $canRecipientsSheet): ?>
            <div class="corr-actions-print dropdown" data-bs-toggle="tooltip" data-bs-placement="top" title="<?= e(__('corr.print_menu')) ?>">
                <button type="button" class="btn btn-icon btn-outline-secondary dropdown-toggle batch-print-toggle" data-bs-toggle="dropdown" aria-expanded="false" aria-label="<?= e(__('corr.print_menu')) ?>">
                    <i class="bi bi-printer" aria-hidden="true"></i>
                    <i class="bi bi-chevron-down batch-print-caret" aria-hidden="true"></i>
                </button>
                <ul class="dropdown-menu batch-print-menu">
                    <?php if ($canPrintForm): ?>
                    <li>
                        <a class="dropdown-item" href="<?= e(base_url('outgoing/batch_corr_print.php?id=' . $id)) ?>" target="_blank" rel="noopener">
                            <i class="bi bi-clipboard-check" aria-hidden="true"></i><span><?= e(__('bulk.print_corr')) ?></span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if ($canOfficialPrint && $batchPaper): ?>
                    <li>
                        <a class="dropdown-item" href="<?= e(base_url('outgoing/batch_print.php?id=' . $id)) ?>" target="_blank" rel="noopener"
                           data-batch-print-selected
                           data-print-paper="1"
                           data-print-empty="<?= e(__('cancel.batch_print_empty')) ?>"
                           data-print-need-paper="<?= e(__('bulk.print_need_paper_selected')) ?>"
                           data-print-base="<?= e(base_url('outgoing/batch_print.php?id=' . $id)) ?>"
                           title="<?= e(__('bulk.print_selected_hint')) ?>">
                            <i class="bi bi-file-earmark-font" aria-hidden="true"></i><span><?= e(__('bulk.print_letters')) ?></span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if ($canPrintEnvelope && $batchPaper): ?>
                    <li>
                        <a class="dropdown-item" href="<?= e(base_url('outgoing/batch_labels.php?id=' . $id)) ?>" target="_blank" rel="noopener"
                           data-batch-print-selected
                           data-print-paper="1"
                           data-print-empty="<?= e(__('cancel.batch_print_empty')) ?>"
                           data-print-need-paper="<?= e(__('bulk.print_need_paper_selected')) ?>"
                           data-print-base="<?= e(base_url('outgoing/batch_labels.php?id=' . $id)) ?>"
                           title="<?= e(__('bulk.print_selected_hint')) ?>">
                            <i class="bi bi-sticky" aria-hidden="true"></i><span><?= e(__('bulk.print_labels')) ?></span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if ($canPrintThermal && $batchPaper): ?>
                    <li>
                        <a class="dropdown-item" href="<?= e(base_url('outgoing/batch_thermal.php?id=' . $id)) ?>" target="_blank" rel="noopener"
                           data-batch-print-selected
                           data-print-paper="1"
                           data-print-empty="<?= e(__('cancel.batch_print_empty')) ?>"
                           data-print-need-paper="<?= e(__('bulk.print_need_paper_selected')) ?>"
                           data-print-base="<?= e(base_url('outgoing/batch_thermal.php?id=' . $id)) ?>"
                           title="<?= e(__('bulk.thermal_selected_hint')) ?>">
                            <i class="bi bi-upc" aria-hidden="true"></i><span><?= e(__('thermal.print')) ?></span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if ($canOfficialPrint && $canRecipientsSheet): ?>
                    <li><hr class="dropdown-divider"></li>
                    <?php endif; ?>
                    <?php if ($canRecipientsSheet): ?>
                    <li>
                        <a class="dropdown-item" href="<?= e(base_url('outgoing/batch_recipients.php?id=' . $id)) ?>" target="_blank" rel="noopener">
                            <i class="bi bi-table" aria-hidden="true"></i><span><?= e(__('bulk.print_results')) ?></span>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
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

        <?php if ($batchCanIssue && $showSendGroup): ?>
        <div class="corr-actions-group corr-actions-sensitive batch-send-actions" role="group" aria-label="<?= e(__('corr.sensitive_actions')) ?>">
            <form method="post" action="<?= e(base_url('outgoing/send.php')) ?>" id="batch-send-form" class="corr-actions-group" data-none-selected="<?= e(__('send.none_selected')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="batch_id" value="<?= (int) $id ?>">
                <?php
                $emailBtnOk = $emailReady && $issuableEmail > 0;
                $waBtnOk = $waReady && $issuableMobile > 0;
                $deliveryBtnOk = $issuablePaper > 0;
                $sendBlockTip = $issuableAny > 0 ? '' : $batchBlockReason;
                ?>
                <?php if ($canSendMsg && $batchElectronic): ?>
                <?php
                $emailTip = (!$emailBtnOk && $sendBlockTip !== '') ? $sendBlockTip : __('send.batch_email');
                $waTip = (!$waBtnOk && $sendBlockTip !== '') ? $sendBlockTip : __('send.batch_wa');
                ?>
                <span class="d-inline-block" tabindex="0" data-bs-toggle="tooltip" data-bs-placement="top" title="<?= e($emailTip) ?>">
                    <button class="btn btn-icon btn-kff" type="submit" name="channel" value="email" data-batch-send data-need-ready="<?= $emailBtnOk ? '1' : '0' ?>" data-confirm-template="<?= e(__('send.confirm_batch_email')) ?>" <?= $emailBtnOk ? '' : 'disabled' ?> aria-label="<?= e(__('send.batch_email')) ?>">
                        <i class="bi bi-envelope" aria-hidden="true"></i>
                    </button>
                </span>
                <span class="d-inline-block" tabindex="0" data-bs-toggle="tooltip" data-bs-placement="top" title="<?= e($waTip) ?>">
                    <button class="btn btn-icon btn-success" type="submit" name="channel" value="whatsapp" data-batch-send data-need-ready="<?= $waBtnOk ? '1' : '0' ?>" data-confirm-template="<?= e(__('send.confirm_batch_wa')) ?>" <?= $waBtnOk ? '' : 'disabled' ?> aria-label="<?= e(__('send.batch_wa')) ?>">
                        <i class="bi bi-whatsapp" aria-hidden="true"></i>
                    </button>
                </span>
                <?php elseif ($batchElectronic && $sendMsgUi === 'deny'): ?>
                <?= dms_perm_denied_wrap('<button type="button" class="btn btn-icon btn-kff" disabled aria-disabled="true" aria-label="' . e(__('send.batch_email')) . '"><i class="bi bi-envelope" aria-hidden="true"></i></button>', __('send.batch_email')) ?>
                <?= dms_perm_denied_wrap('<button type="button" class="btn btn-icon btn-success" disabled aria-disabled="true" aria-label="' . e(__('send.batch_wa')) . '"><i class="bi bi-whatsapp" aria-hidden="true"></i></button>', __('send.batch_wa')) ?>
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
                <?php elseif (!empty($action['icon_only'])): ?>
                    <a class="btn btn-icon <?= e((string) ($action['class'] ?? 'btn-outline-secondary')) ?>" href="<?= e((string) ($action['href'] ?? '#')) ?>"
                       data-bs-toggle="tooltip" data-bs-placement="top"
                       title="<?= e((string) $action['label']) ?>"
                       aria-label="<?= e((string) $action['label']) ?>">
                        <i class="bi <?= e((string) $action['icon']) ?>" aria-hidden="true"></i>
                    </a>
                <?php else: ?>
                    <a class="btn <?= e((string) ($action['class'] ?? 'btn-outline-secondary')) ?>" href="<?= e((string) ($action['href'] ?? '#')) ?>">
                        <i class="bi <?= e((string) $action['icon']) ?> me-1"></i><?= e((string) $action['label']) ?>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?= dms_back_icon(base_url('outgoing/batches.php')) ?>
    </div>
</div>

<?php if ($batchInPrep): ?>
<div class="card-panel mb-3 no-print">
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
        <span class="d-flex flex-wrap align-items-center gap-2">
            <span><i class="bi bi-pencil-square me-1"></i><?= e(__('out_prep.panel_title')) ?></span>
            <span class="badge text-bg-secondary"><?= e(__('bulk.result_title')) ?> · <?= e(str_replace(':count', (string) count($items), __('bulk.result_count'))) ?></span>
            <span class="batch-prep-hint"><?= e(__('bulk.prep_hint')) ?></span>
        </span>
        <span class="badge <?= $batchPrepPhase === 'pending_signer' ? 'text-bg-warning' : ($batchPrepSample && function_exists('outgoing_prep_was_returned') && outgoing_prep_was_returned($batchPrepSample) ? 'text-bg-info' : 'text-bg-secondary') ?>">
            <?= e($batchPrepSample ? outgoing_prep_list_label($batchPrepSample) : ($batchPrepPhase === 'pending_signer' ? __('out_prep.list_pending_signer') : __('out_prep.list_draft'))) ?>
        </span>
    </div>
    <?php if ($batchPrepPhase === 'draft' && $batchPrepNote !== ''): ?>
    <div class="card-body">
        <div class="alert alert-warning py-2 small mb-0">
            <div class="fw-semibold"><?= e(__('out_prep.returned_note')) ?></div>
            <div class="mt-1"><?= e($batchPrepNote) ?></div>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>
<?php if (!$batchInPrep && !empty($apprSummary['configured'])): ?>
<?php
$batchReviewN = (int) ($apprSummary['in_review'] ?? 0) + (int) ($apprSummary['returned'] ?? 0);
$batchApproveN = (int) ($apprSummary['pending'] ?? 0) - (int) ($apprSummary['in_review'] ?? 0);
$batchPanelTitle = __('issue_appr.panel_title');
if ($batchReviewN > 0 && $batchApproveN <= 0) {
    $batchPanelTitle = __('issue_appr.panel_title_review');
} elseif ($batchApproveN > 0 && $batchReviewN === 0) {
    $batchPanelTitle = __('issue_appr.panel_title_approve');
}
$batchStage = '';
foreach ($items as $corr) {
    if (($corr['type'] ?? '') !== 'outgoing' || (function_exists('correspondence_is_cancelled') && correspondence_is_cancelled($corr))) {
        continue;
    }
    $stage = function_exists('outgoing_approval_status_of') ? outgoing_approval_status_of($corr) : '';
    if ($stage === 'with_comms') {
        $stage = 'approved';
    }
    if ($batchStage === '') {
        $batchStage = $stage;
    } elseif ($batchStage !== $stage) {
        $batchStage = 'mixed';
        break;
    }
}
$batchBadgeText = match (true) {
    $batchCanIssue => __('issue_appr.status_comms'),
    $batchStage === 'pending_review' => __('issue_review.list_pending'),
    $batchStage === 'returned' => __('issue_review.list_returned'),
    $batchStage === 'pending_copy' => __('issue_appr.list_pending_copy'),
    $batchStage === 'pending_sg' => function_exists('outgoing_appr_text') ? outgoing_appr_text('issue_appr.status_pending_sg') : __('issue_appr.status_pending_sg'),
    $batchStage === 'pending_deputy' => function_exists('outgoing_appr_text') ? outgoing_appr_text('issue_appr.status_pending_deputy') : __('issue_appr.status_pending_deputy'),
    $batchStage === 'pending_alternate' => function_exists('outgoing_appr_text') ? outgoing_appr_text('issue_appr.status_pending_alternate') : __('issue_appr.status_pending_alternate'),
    $batchStage === 'rejected' => __('issue_appr.list_rejected'),
    default => __('issue_appr.filter_pending'),
};
?>
<div class="card-panel mb-3 no-print">
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
        <span class="d-flex flex-wrap align-items-center gap-2">
            <span><i class="bi bi-shield-check me-1"></i><?= e($batchPanelTitle) ?></span>
            <span class="badge text-bg-secondary"><?= e(__('bulk.result_title')) ?> · <?= e(str_replace(':count', (string) count($items), __('bulk.result_count'))) ?></span>
        </span>
        <span class="badge <?= $batchCanIssue ? 'text-bg-success' : 'text-bg-warning' ?>">
            <?= e($batchBadgeText) ?>
        </span>
    </div>
    <?php if ((!$batchCanIssue && $batchBlockReason !== '') || !empty($apprSummary['can_request'])): ?>
    <div class="card-body">
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
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php
$batchTotal = count($items);
$batchUnsent = $batchTotal - (int) $anySent;
// ملخص الدفعة كاملاً لكل من يرى النتائج (منشئ واتصالات) — بدون عمود/عداد المحدد للمنشئ
$showBatchSummary = $batchCanIssue || $isBatchCreator;
?>
<?php if ($showBatchSummary): ?>
<div class="card-panel mb-3 no-print batch-summary-panel">
    <div class="batch-summary-bar">
        <div class="batch-summary-head">
            <div class="batch-summary-metric" title="<?= e(__('send.batch_hint')) ?>">
                <span class="batch-summary-title"><?= e(__('bulk.summary')) ?></span>
                <span class="batch-summary-total" aria-label="<?= e(__('bulk.recipients')) ?>: <?= (int) $batchTotal ?>">
                    <span class="batch-summary-total-num"><?= (int) $batchTotal ?></span>
                </span>
            </div>
            <?php if ($canSelectRows): ?>
            <div class="batch-summary-metric is-selected" title="<?= e(__('send.selected_count')) ?>">
                <span class="batch-summary-title"><?= e(__('send.selected_count')) ?></span>
                <span class="batch-summary-selected" aria-label="<?= e(__('send.selected_count')) ?>">
                    <span class="batch-summary-selected-num" data-batch-selected-count><?= (int) $batchTotal ?></span>
                </span>
            </div>
            <?php endif; ?>
        </div>
        <div class="batch-summary-groups">
            <div class="batch-summary-group" role="group" aria-label="<?= e(__('bulk.delivery_mode')) ?>">
                <span class="batch-summary-group-label"><?= e(__('bulk.delivery_mode')) ?></span>
                <div class="batch-chips">
                    <span class="batch-chip is-electronic"><?= e(__('bulk.delivery_electronic')) ?> <b><?= (int) $electronicCount ?></b></span>
                    <span class="batch-chip is-paper"><?= e(__('bulk.delivery_paper')) ?> <b><?= (int) $paperCount ?></b></span>
                </div>
            </div>
            <div class="batch-summary-group" role="group" aria-label="<?= e(__('bulk.recipients')) ?>">
                <span class="batch-summary-group-label"><?= e(__('bulk.recipients')) ?></span>
                <div class="batch-chips">
                    <span class="batch-chip"><?= e(__('entities.email')) ?> <b><?= (int) $emailCount ?>/<?= (int) $batchTotal ?></b></span>
                    <span class="batch-chip"><?= e(__('entities.mobile')) ?> <b><?= (int) $mobileCount ?>/<?= (int) $batchTotal ?></b></span>
                </div>
            </div>
            <div class="batch-summary-group" role="group" aria-label="<?= e(__('common.status')) ?>">
                <span class="batch-summary-group-label"><?= e(__('common.status')) ?></span>
                <div class="batch-chips">
                    <span class="batch-chip is-ok"><?= e(__('send.stat_sent')) ?> <b><?= (int) $anySent ?></b></span>
                    <span class="batch-chip<?= $batchUnsent > 0 ? ' is-warn' : '' ?>"><?= e(__('send.stat_unsent')) ?> <b><?= (int) $batchUnsent ?></b></span>
                    <span class="batch-chip"><?= e(__('send.email')) ?> <b><?= (int) $emailSent ?></b> · <?= e(__('send.stat_unsent')) ?> <?= (int) $emailPending ?></span>
                    <span class="batch-chip"><?= e(__('send.delivery')) ?> <b><?= (int) $deliverySent ?></b></span>
                </div>
            </div>
            <?php if ($rsvpOn): ?>
            <div class="batch-summary-group" role="group" aria-label="<?= e(__('rsvp.replies')) ?>">
                <span class="batch-summary-group-label"><?= e(__('rsvp.replies')) ?></span>
                <div class="batch-chips">
                    <span class="batch-chip is-ok"><?= e(__('rsvp.mode_in_person_short')) ?> <b><?= (int) ($rsvpSummary['in_person'] ?? 0) ?></b></span>
                    <span class="batch-chip is-ok"><?= e(__('rsvp.mode_remote')) ?> <b><?= (int) ($rsvpSummary['remote'] ?? 0) ?></b></span>
                    <span class="batch-chip is-danger"><?= e(__('rsvp.declined')) ?> <b><?= (int) $rsvpSummary['declined'] ?></b></span>
                    <span class="batch-chip is-muted"><?= e(__('rsvp.pending')) ?> <b><?= (int) $rsvpSummary['pending'] ?></b></span>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php if ($canRunIssued && $canSendMsg && (!$emailReady || !$waReady)): ?>
        <div class="batch-summary-notes">
            <?php if (!$emailReady): ?><span><?= e(__('send.need_email_settings')) ?></span><?php endif; ?>
            <?php if (!$waReady): ?><span><?= e(__('send.need_wa_settings')) ?></span><?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($canBulkOps): ?>
<form method="post" id="batch-delivery-form" class="d-none">
    <?= csrf_field() ?>
    <input type="hidden" name="batch_id" value="<?= (int) $id ?>">
</form>
<?php endif; ?>
<?php
$batchHasReceipt = false;
foreach ($items as $receiptRow) {
    if (trim((string) ($receiptRow['receipt_name'] ?? '')) !== ''
        || trim((string) ($receiptRow['receipt_phone'] ?? '')) !== ''
        || trim((string) ($receiptRow['receipt_date'] ?? '')) !== ''
        || trim((string) ($receiptRow['receipt_file_path'] ?? '')) !== '') {
        $batchHasReceipt = true;
        break;
    }
}
?>
<div class="card-panel">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="batch-results-table">
            <thead>
                <tr>
                    <?php if ($canSelectRows): ?>
                        <th class="batch-select-col">
                            <input type="checkbox" class="form-check-input" data-batch-select-all title="<?= e(__('groups.select_all')) ?>">
                        </th>
                    <?php endif; ?>
                    <th class="text-center"><?= e(__('corr.number')) ?></th>
                    <th><?= e(__('corr.party_to')) ?></th>
                    <th><?= e(__('entities.col_job_org')) ?></th>
                    <th class="text-center"><?= e(__('bulk.delivery_electronic_col')) ?></th>
                    <th class="text-center"><?= e(__('entities.email')) ?></th>
                    <?php if ($batchCanIssue && $canSendMsg): ?>
                    <th class="text-center batch-send-icon-col" title="<?= e(__('send.do_email')) ?>"><i class="bi bi-envelope"></i></th>
                    <th class="text-center"><?= e(__('send.whatsapp')) ?></th>
                    <th class="text-center batch-send-icon-col" title="<?= e(__('send.do_wa')) ?>"><i class="bi bi-whatsapp"></i></th>
                    <?php endif; ?>
                    <th><?= e(__('corr.status')) ?></th>
                    <?php if ($batchHasReceipt): ?>
                    <th class="batch-receipt-cell"><?= e(__('bulk.receipt_data')) ?></th>
                    <?php endif; ?>
                    <?php if (!empty($apprSummary['configured'])): ?>
                    <th><?= e(__('issue_appr.col')) ?></th>
                    <?php endif; ?>
                    <?php if ($batchCanIssue && $canDeliver): ?>
                    <th class="text-center batch-send-icon-col" title="<?= e(__('send.do_delivery')) ?>"><i class="bi bi-clipboard-check"></i></th>
                    <?php endif; ?>
                    <?php if ($batchCanIssue && $rsvpOn): ?>
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
                    : (function_exists('outgoing_user_may_issue')
                        ? outgoing_user_may_issue($user, $r)
                        : (!function_exists('outgoing_can_issue') || outgoing_can_issue($r)));
                $rowBlock = !$rowCanIssue
                    ? (function_exists('outgoing_user_issue_block_reason')
                        ? outgoing_user_issue_block_reason($user, $r)
                        : (function_exists('outgoing_approval_block_reason') ? outgoing_approval_block_reason($r) : ''))
                    : '';
                // التحديد للمتابعة/الطباعة متاح ما دامت المعاملة غير ملغاة؛ الإرسال يبقى عبر data-can-issue
                $rowSelectable = !$rowCancelled;
                $rowElectronic = function_exists('correspondence_intend_electronic') && correspondence_intend_electronic($r);
                $rowPaper = function_exists('correspondence_intend_paper') && correspondence_intend_paper($r);
                $rowMode = function_exists('correspondence_delivery_mode') ? correspondence_delivery_mode($r) : ($rowElectronic ? 'electronic' : 'paper');
                $receiptName = trim((string) ($r['receipt_name'] ?? ''));
                $receiptPhone = trim((string) ($r['receipt_phone'] ?? ''));
                $receiptDate = trim((string) ($r['receipt_date'] ?? ''));
                $receiptFile = trim((string) ($r['receipt_file_path'] ?? ''));
                ?>
                <tr<?= $rowCancelled ? ' class="table-secondary"' : '' ?>>
                    <?php if ($canSelectRows): ?>
                        <td class="batch-select-col">
                            <input type="checkbox" class="form-check-input batch-select-row" name="ids[]" value="<?= (int) $r['id'] ?>"<?= $canSend ? ' form="batch-send-form"' : '' ?><?= $rowSelectable ? ' checked' : ' disabled' ?> data-can-issue="<?= $rowCanIssue ? '1' : '0' ?>" data-has-email="<?= $email !== '' ? '1' : '0' ?>" data-has-mobile="<?= $mobile !== '' ? '1' : '0' ?>" data-electronic="<?= $rowElectronic ? '1' : '0' ?>" data-paper="<?= $rowPaper ? '1' : '0' ?>">
                        </td>
                    <?php endif; ?>
                    <td class="fw-semibold text-center" dir="ltr"><?= e($r['ref_number']) ?></td>
                    <?php
                    $partyArchived = correspondence_archived_party($r);
                    render_entity_party_columns($partyArchived);
                    ?>
                    <td class="batch-delivery-cell">
                        <?php
                        $deliveryBlock = function_exists('outgoing_delivery_edit_block')
                            ? outgoing_delivery_edit_block($user, $r)
                            : '';
                        ?>
                        <?php
                        $deliveryStored = in_array($rowMode, ['electronic', 'paper', 'both'], true) ? $rowMode : 'paper';
                        $deliveryChecked = $email !== '' && ($deliveryStored === 'electronic' || $deliveryStored === 'both');
                        $deliveryEditable = !$rowCancelled && $deliveryBlock === '';
                        $deliveryTitle = $deliveryBlock !== ''
                            ? $deliveryBlock
                            : ($email === '' ? __('bulk.delivery_needs_email') : __('bulk.delivery_electronic_col'));
                        ?>
                        <label class="bulk-delivery-check" title="<?= e($deliveryTitle) ?>">
                            <input type="checkbox" class="form-check-input" aria-label="<?= e(__('bulk.delivery_electronic_col')) ?>"<?= $deliveryEditable ? ' data-delivery-toggle' : '' ?><?= $deliveryChecked ? ' checked' : '' ?><?= ($deliveryEditable && $email !== '') ? '' : ' disabled' ?>>
                        </label>
                        <?php if ($deliveryEditable): ?>
                            <input type="hidden" name="recipient_delivery[<?= (int) $r['id'] ?>]" form="batch-delivery-form" value="<?= e($email === '' ? 'paper' : $deliveryStored) ?>" data-delivery-value>
                        <?php endif; ?>
                    </td>
                    <td class="batch-email-cell">
                        <?php if ($email !== ''): ?>
                            <div class="batch-delivery-email" dir="ltr"><?= e($email) ?></div>
                            <?php if ($batchCanIssue && $canSendMsg): ?>
                            <div class="batch-channel-status small <?= (($last['email']['status'] ?? '') === 'sent') ? 'text-success' : ((($last['email']['status'] ?? '') === 'failed') ? 'text-danger' : 'text-muted') ?>">
                                <?= e(dms_delivery_status_label($last['email'] ?? null)) ?>
                            </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <?php if ($batchCanIssue && $canSendMsg): ?>
                    <td class="batch-send-icon-col text-center">
                        <?php if ($email === ''): ?>
                        <?php elseif ($canSendMsg && $rowCanIssue && $rowMailReady): ?>
                            <form method="post" action="<?= e(base_url('outgoing/send.php')) ?>" class="d-inline" data-confirm="<?= e(__('send.confirm_email')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="correspondence_id" value="<?= (int) $r['id'] ?>">
                                <input type="hidden" name="channel" value="email">
                                <input type="hidden" name="return_batch" value="<?= (int) $id ?>">
                                <button type="submit" class="batch-send-icon batch-send-icon--email<?= (($last['email']['status'] ?? '') === 'sent') ? ' is-sent' : '' ?><?= $rowElectronic ? '' : ' is-disabled' ?>" data-channel-toggle="email" title="<?= e(__('send.do_email')) ?>"<?= $rowElectronic ? '' : ' disabled' ?>>
                                    <i class="bi <?= (($last['email']['status'] ?? '') === 'sent') ? 'bi-envelope-check' : 'bi-envelope' ?>"></i>
                                </button>
                            </form>
                        <?php else: ?>
                            <span class="batch-send-icon batch-send-icon--email is-disabled" title="<?= e(!$canSendMsg ? __('perm.denied_tip') : (!$rowCanIssue ? ($rowBlock !== '' ? $rowBlock : __('issue_appr.blocked_pending')) : (!$rowElectronic ? __('send.skip_not_electronic') : ($email === '' ? __('send.need_entity_email') : __('send.need_email_settings'))))) ?>">
                                <i class="bi bi-envelope"></i>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="batch-channel-cell">
                        <?php if ($mobile !== ''): ?>
                        <div class="batch-channel-stack">
                            <div class="batch-channel-value" dir="ltr">+<?= e($mobile) ?></div>
                            <div class="batch-channel-status small <?= (($last['whatsapp']['status'] ?? '') === 'sent') ? 'text-success' : ((($last['whatsapp']['status'] ?? '') === 'failed') ? 'text-danger' : 'text-muted') ?>">
                                <?= e(dms_delivery_status_label($last['whatsapp'] ?? null)) ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td class="batch-send-icon-col text-center">
                        <?php if ($mobile === ''): ?>
                        <?php elseif ($canSendMsg && $rowCanIssue && $waReady): ?>
                            <form method="post" action="<?= e(base_url('outgoing/send.php')) ?>" class="d-inline" data-confirm="<?= e(__('send.confirm_wa')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="correspondence_id" value="<?= (int) $r['id'] ?>">
                                <input type="hidden" name="channel" value="whatsapp">
                                <input type="hidden" name="return_batch" value="<?= (int) $id ?>">
                                <button type="submit" class="batch-send-icon batch-send-icon--wa<?= (($last['whatsapp']['status'] ?? '') === 'sent') ? ' is-sent' : '' ?>" data-channel-toggle="whatsapp" title="<?= e(__('send.do_wa')) ?>">
                                    <i class="bi bi-whatsapp"></i>
                                </button>
                            </form>
                        <?php else: ?>
                            <span class="batch-send-icon batch-send-icon--wa is-disabled" title="<?= e(!$canSendMsg ? __('perm.denied_tip') : (!$rowCanIssue ? ($rowBlock !== '' ? $rowBlock : __('issue_appr.blocked_pending')) : ($mobile === '' ? __('send.need_entity_mobile') : __('send.need_wa_settings')))) ?>">
                                <i class="bi bi-whatsapp"></i>
                            </span>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                    <td><?= status_badge((string) ($r['status'] ?? ''), $r['cancel_kind'] ?? null) ?></td>
                    <?php if ($batchHasReceipt): ?>
                    <td class="batch-receipt-cell">
                        <?php if ($receiptName !== '' || $receiptPhone !== '' || $receiptDate !== '' || $receiptFile !== ''): ?>
                            <?php if ($receiptName !== ''): ?><div><?= e($receiptName) ?></div><?php endif; ?>
                            <?php if ($receiptPhone !== ''): ?><div dir="ltr"><?= e($receiptPhone) ?></div><?php endif; ?>
                            <?php if ($receiptDate !== ''): ?><div class="text-muted"><?= e(format_date($receiptDate)) ?></div><?php endif; ?>
                            <?php if ($receiptFile !== ''): ?>
                                <a href="<?= e(file_download_url('receipt', (int) $r['id'])) ?>" target="_blank" rel="noopener"><?= e(__('receipt.slip')) ?></a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                    <?php if (!empty($apprSummary['configured'])): ?>
                    <td><?= function_exists('outgoing_approval_badge') ? outgoing_approval_badge($r) : '' ?></td>
                    <?php endif; ?>
                    <?php if ($batchCanIssue && $canDeliver): ?>
                    <td class="batch-send-icon-col text-center">
                        <?php if ($canDeliver && $rowCanIssue && $rowPaper): ?>
                            <button type="button"
                                    class="batch-send-icon batch-send-icon--delivery<?= $receiptName !== '' ? ' is-sent' : '' ?>"
                                    data-batch-receipt
                                    data-id="<?= (int) $r['id'] ?>"
                                    data-ref="<?= e((string) $r['ref_number']) ?>"
                                    data-name="<?= e($receiptName) ?>"
                                    data-phone="<?= e($receiptPhone) ?>"
                                    data-date="<?= e($receiptDate !== '' ? $receiptDate : date('Y-m-d')) ?>"
                                    data-date-display="<?= e(format_date_input_value($receiptDate !== '' ? $receiptDate : date('Y-m-d'))) ?>"
                                    data-file-url="<?= $receiptFile !== '' ? e(file_download_url('receipt', (int) $r['id'])) : '' ?>"
                                    data-file-name="<?= e(trim((string) ($r['receipt_file_name'] ?? ''))) ?>"
                                    data-status="<?= e((string) ($r['status'] ?? '')) ?>"
                                    data-closed="<?= correspondence_is_closed($r) ? '1' : '0' ?>"
                                    title="<?= e(__('receipt.section')) ?>">
                                <i class="bi <?= $receiptName !== '' ? 'bi-clipboard-check-fill' : 'bi-clipboard-check' ?>"></i>
                            </button>
                        <?php elseif ($canDeliver && $rowCanIssue): ?>
                            <span class="batch-send-icon batch-send-icon--delivery is-disabled" title="<?= e(__('send.skip_not_paper')) ?>">
                                <i class="bi bi-clipboard-check"></i>
                            </span>
                        <?php else: ?>
                            <span class="batch-send-icon batch-send-icon--delivery is-disabled" title="<?= e(!$canDeliver ? __('perm.denied_tip') : (!$rowCanIssue ? ($rowBlock !== '' ? $rowBlock : __('issue_appr.blocked_pending')) : __('send.skip_not_paper'))) ?>">
                                <i class="bi bi-clipboard-check"></i>
                            </span>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                    <?php if ($batchCanIssue && $rsvpOn): ?>
                        <td>
                            <?= rsvp_response_badge($r['rsvp_response'] ?? null, $r['rsvp_mode'] ?? null) ?>
                            <?php $rsvpNote = trim((string) ($r['rsvp_note'] ?? '')); ?>
                            <?php if ($rsvpNote !== ''): ?>
                                <div class="small mt-1"><?= e($rsvpNote) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($r['rsvp_at'])): ?>
                                <div class="small text-muted"><?= e(format_datetime($r['rsvp_at'])) ?></div>
                            <?php endif; ?>
                        </td>
                    <?php endif; ?>
                    <td class="text-end text-nowrap">
                        <?php if (!$rowCancelled): ?>
                        <span class="batch-preview-icons">
                            <a class="letter-token-btn is-format is-icon"
                               href="<?= e(base_url('outgoing/print.php?id=' . (int) $r['id'] . '&preview=1')) ?>"
                               target="_blank" rel="noopener"
                               data-bs-toggle="tooltip" data-bs-placement="top"
                               title="<?= e(__('tpl.preview_letter')) ?>"
                               aria-label="<?= e(__('tpl.preview_letter')) ?>">
                                <i class="bi bi-file-earmark-font" aria-hidden="true"></i>
                            </a>
                            <?php if ($email !== ''): ?>
                            <a class="letter-token-btn is-format is-icon<?= $rowElectronic ? '' : ' d-none' ?>"
                               href="<?= e(base_url('outgoing/email_preview_view.php?id=' . (int) $r['id'])) ?>"
                               target="_blank" rel="noopener"
                               data-preview-channel="electronic"
                               data-bs-toggle="tooltip" data-bs-placement="top"
                               title="<?= e(__('tpl.preview_email')) ?>"
                               aria-label="<?= e(__('tpl.preview_email')) ?>">
                                <i class="bi bi-envelope-open" aria-hidden="true"></i>
                            </a>
                            <?php endif; ?>
                        </span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($batchInPrep && $batchPrepCanDecide): ?>
<div class="card-panel mb-3 no-print">
    <div class="card-body">
        <form method="post" class="corr-issue-decide is-standalone">
            <?= csrf_field() ?>
            <input type="hidden" name="batch_id" value="<?= (int) $id ?>">
            <div>
                <label class="form-label small mb-1" for="batch-prep-note"><?= e(__('out_prep.note')) ?></label>
                <textarea name="note" id="batch-prep-note" class="form-control form-control-sm" rows="2" placeholder="<?= e(__('out_prep.return_hint')) ?>"></textarea>
            </div>
            <div class="corr-decide-actions">
                <button type="submit" name="batch_prep_decision" value="approve" class="btn btn-success btn-sm"
                        data-confirm="<?= e(__('out_prep.confirm_approve')) ?>">
                    <i class="bi bi-check2 me-1"></i><?= e(__('out_prep.approve')) ?>
                </button>
                <button type="submit" name="batch_prep_decision" value="return" class="btn btn-orange btn-sm"
                        data-confirm="<?= e(__('out_prep.confirm_return')) ?>" data-batch-prep-return="1">
                    <i class="bi bi-arrow-counterclockwise me-1"></i><?= e(__('out_prep.return')) ?>
                </button>
            </div>
        </form>
        <script>
        (function () {
            var btn = document.querySelector('[data-batch-prep-return]');
            if (!btn) return;
            btn.addEventListener('click', function (e) {
                var note = document.getElementById('batch-prep-note');
                if (note && String(note.value || '').replace(/\s+/g, ' ').trim() !== '') return;
                e.preventDefault();
                e.stopPropagation();
                var msg = <?= json_encode(__('out_prep.return_note_required'), JSON_UNESCAPED_UNICODE) ?>;
                if (window.Swal) Swal.fire({ icon: 'warning', text: msg, confirmButtonColor: '#3B4F6E' });
            }, true);
        })();
        </script>
    </div>
</div>
<?php endif; ?>

<?php if (!$batchInPrep && !empty($apprSummary['can_review'])): ?>
<div class="card-panel mb-3 no-print">
    <div class="card-body">
        <form method="post" action="<?= e(base_url('outgoing/issue_approval.php')) ?>" class="corr-issue-decide is-standalone">
            <?= csrf_field() ?>
            <input type="hidden" name="batch_id" value="<?= (int) $id ?>">
            <div>
                <label class="form-label small mb-1" for="batch_issue_review_note"><?= e(__('issue_review.note')) ?></label>
                <input type="text" name="note" id="batch_issue_review_note" class="form-control form-control-sm" maxlength="500"
                       placeholder="<?= e(__('issue_review.note_placeholder')) ?>">
            </div>
            <div class="corr-decide-actions">
                <button type="submit" name="action" value="review_pass" class="btn btn-success btn-sm"
                        data-confirm="<?= e(__('issue_review.confirm_pass')) ?>">
                    <i class="bi bi-check2-circle me-1"></i><?= e(__('issue_review.batch_pass')) ?>
                </button>
                <button type="submit" name="action" value="review_return" class="btn btn-orange btn-sm"
                        data-confirm="<?= e(__('issue_review.confirm_return')) ?>"
                        data-note-required="batch_issue_review_note"
                        data-note-required-msg="<?= e(__('issue_review.note_required')) ?>">
                    <i class="bi bi-arrow-counterclockwise me-1"></i><?= e(__('issue_review.batch_return')) ?>
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if (!$batchInPrep && !empty($apprSummary['can_decide'])): ?>
<?php
$batchIsAlternate = array_filter(
    $items,
    static fn (array $r): bool => outgoing_user_can_decide_approval($user, $r)
        && !appr_is_admin_decide_override($user, $r)
        && outgoing_user_is_alternate_approver($user, $r)
) !== [];
$batchNoteLabel = $batchIsAlternate ? __('issue_appr.alternate_reason') : __('issue_appr.note');
?>
<div class="card-panel mb-3 no-print">
    <div class="card-body">
        <?php if ($batchIsAlternate): ?>
            <div class="alert alert-info py-2 small mb-0"><?= e(function_exists('outgoing_appr_text') ? outgoing_appr_text('issue_appr.alternate_decide_hint') : __('issue_appr.alternate_decide_hint')) ?></div>
        <?php endif; ?>
        <form method="post" action="<?= e(base_url('outgoing/issue_approval.php')) ?>" class="corr-issue-decide<?= $batchIsAlternate ? '' : ' is-standalone' ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="batch_id" value="<?= (int) $id ?>">
            <div>
                <label class="form-label small mb-1" for="batch_issue_appr_note"><?= e($batchNoteLabel) ?><?= $batchIsAlternate ? ' *' : '' ?></label>
                <input type="text" name="note" id="batch_issue_appr_note" class="form-control form-control-sm" maxlength="500"
                       <?= $batchIsAlternate ? 'required' : '' ?>
                       placeholder="<?= e($batchIsAlternate ? __('issue_appr.alternate_reason_placeholder') : '') ?>">
            </div>
            <div class="corr-decide-actions">
                <button type="submit" name="action" value="approve" class="btn btn-success btn-sm"
                        data-confirm="<?= e(__('issue_appr.confirm_approve')) ?>">
                    <i class="bi bi-check2-circle me-1"></i><?= e(__('issue_appr.batch_approve')) ?>
                </button>
                <button type="submit" name="action" value="reject" class="btn btn-danger btn-sm"
                        data-confirm="<?= e(__('issue_appr.confirm_reject')) ?>"
                        data-note-required="batch_issue_appr_note"
                        data-note-required-msg="<?= e(__('issue_appr.reject_note_required')) ?>"
                        title="<?= e(__('issue_appr.reject_hint')) ?>">
                    <i class="bi bi-x-circle me-1"></i><?= e(__('issue_appr.batch_reject')) ?>
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if (!$batchCanIssue && function_exists('get_outgoing_batch_activity')): ?>
<?php
    $activity = get_outgoing_batch_activity(array_map(static fn (array $row): int => (int) $row['id'], $items));
    $activityTitle = __('bulk.activity_title');
    $activityCollapsed = true;
    $activityCollapseId = 'batch-activity-log';
    require __DIR__ . '/../includes/corr_activity_card.php';
    unset($activityCollapsed, $activityCollapseId, $activityTitle);
?>
<?php endif; ?>

<?php if ($batchCanIssue && $canDeliver): ?>
<?php
$batchDeliveryStatusOpts = correspondence_assignable_status_codes();
?>
<div class="modal fade" id="batchReceiptModal" tabindex="-1" aria-labelledby="batchReceiptTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="batchReceiptTitle"><?= e(__('receipt.section')) ?></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(__('action.close')) ?>"></button>
            </div>
            <div class="modal-body pt-2">
                <p class="text-muted small mb-2" id="batchReceiptRef"></p>
                <ul class="nav nav-tabs corr-delivery-tabs mb-3" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="batch-delivery-tab-receipt" data-bs-toggle="tab"
                                data-bs-target="#batch-delivery-pane-receipt" type="button" role="tab"
                                aria-controls="batch-delivery-pane-receipt" aria-selected="true">
                            <i class="bi bi-pen me-1"></i><?= e(__('receipt.tab')) ?>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="batch-delivery-tab-status" data-bs-toggle="tab"
                                data-bs-target="#batch-delivery-pane-status" type="button" role="tab"
                                aria-controls="batch-delivery-pane-status" aria-selected="false">
                            <i class="bi bi-arrow-repeat me-1"></i><?= e(__('delivery.tab_status')) ?>
                        </button>
                    </li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="batch-delivery-pane-receipt" role="tabpanel" aria-labelledby="batch-delivery-tab-receipt">
                        <form method="post" enctype="multipart/form-data" action="<?= e(base_url('outgoing/receipt.php')) ?>" data-confirm="<?= e(__('delivery.confirm_save')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="correspondence_id" id="batchReceiptCorr" value="">
                            <input type="hidden" name="return_batch" value="<?= (int) $id ?>">
                            <input type="hidden" name="action" value="save">
                            <p class="text-muted small"><?= e(__('receipt.hint')) ?></p>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label" for="batchReceiptName"><?= e(__('print.receiver_name')) ?> *</label>
                                    <input type="text" name="receipt_name" id="batchReceiptName" class="form-control" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="batch_receipt_date-display"><?= e(__('print.receive_date')) ?> *</label>
                                    <?php
                                    render_dms_gregorian_date_field([
                                        'name' => 'receipt_date',
                                        'id' => 'batch_receipt_date',
                                        'value' => date('Y-m-d'),
                                        'required' => true,
                                    ]);
                                    ?>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="batchReceiptPhone"><?= e(__('print.receiver_phone')) ?></label>
                                    <input type="text" name="receipt_phone" id="batchReceiptPhone" class="form-control" dir="ltr">
                                </div>
                                <div class="col-12">
                                    <div id="batchReceiptCurrent" class="small mb-2" hidden></div>
                                    <label class="form-label" for="batch_receipt_file"><?= e(__('receipt.upload_slip')) ?></label>
                                    <?php
                                    $dmsFile = [
                                        'name' => 'receipt_file',
                                        'id' => 'batch_receipt_file',
                                        'accept' => '.jpg,.jpeg,.png,.gif,.webp,.pdf,image/*,application/pdf',
                                        'browse' => __('file.choose_one'),
                                        'placeholder' => __('file.placeholder_one'),
                                        'wrap_class' => 'mb-0',
                                    ];
                                    require __DIR__ . '/../includes/dms_file_field.php';
                                    ?>
                                    <div class="form-text"><?= e(__('receipt.upload_hint')) ?></div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-end gap-2 mt-3">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(__('action.close')) ?></button>
                                <button class="btn btn-kff" type="submit"><?= e(__('receipt.save')) ?></button>
                            </div>
                        </form>
                    </div>
                    <div class="tab-pane fade" id="batch-delivery-pane-status" role="tabpanel" aria-labelledby="batch-delivery-tab-status">
                        <p class="text-muted small mb-3"><?= e(__('delivery.status_hint')) ?></p>
                        <form method="post" action="<?= e(base_url('outgoing/status.php')) ?>" class="corr-auto-status-form"
                              data-auto-status="1"
                              data-current-status=""
                              id="batchReceiptStatusForm"
                              data-status-confirm="<?= e(__('corr.confirm_status_change')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" id="batchReceiptStatusCorr" value="">
                            <input type="hidden" name="return_batch" value="<?= (int) $id ?>">
                            <label class="form-label" for="batchReceiptStatus"><?= e(__('corr.status')) ?></label>
                            <span id="batchReceiptStatusLockWrap" class="d-block">
                                <select name="status" id="batchReceiptStatus" class="form-select">
                                    <?php foreach ($batchDeliveryStatusOpts as $s): ?>
                                        <option value="<?= e($s) ?>"><?= e(status_label($s)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </span>
                        </form>
                        <div class="d-flex justify-content-end gap-2 mt-3">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(__('action.close')) ?></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
<?php
$deliverySavedJson = json_encode(__('bulk.delivery_saved'), JSON_UNESCAPED_UNICODE);
$selectionSavedJson = json_encode(__('bulk.selection_saved'), JSON_UNESCAPED_UNICODE);
$reopenTipJson = json_encode(__('reopen.use_reopen'), JSON_UNESCAPED_UNICODE);
$batchIdJson = (int) $id;
$pageScripts = <<<JS
<script>
(function () {
  var form = document.getElementById('batch-send-form');
  var table = document.getElementById('batch-results-table');
  var batchId = String({$batchIdJson} || '');
  function boxes() {
    if (!table) return [];
    return Array.prototype.slice.call(table.querySelectorAll('.batch-select-row'));
  }

  var deliveryForm = document.getElementById('batch-delivery-form');
  var deliverySaved = {$deliverySavedJson};
  var selectionSaved = {$selectionSavedJson};
  var reopenTip = {$reopenTipJson};
  function deliveryToast(message, isError) {
    if (window.DmsSwal && typeof window.DmsSwal.flash === 'function') {
      window.DmsSwal.flash(isError ? 'danger' : 'success', message, isError ? 0 : 700);
      return;
    }
    if (typeof dmsNotify === 'function') dmsNotify(isError ? 'danger' : 'success', message);
  }
  function applyDeliveryRow(sel, mode) {
    var tr = sel.closest('tr');
    if (!tr) return;
    var electronic = mode !== 'paper';
    var paper = mode !== 'electronic';
    // التحديد مستقل عن إلكتروني/ورقي — نحدّث سمات القناة فقط دون تغيير checked
    var box = tr.querySelector('.batch-select-row');
    if (box) {
      box.setAttribute('data-electronic', electronic ? '1' : '0');
      box.setAttribute('data-paper', paper ? '1' : '0');
    }
    tr.querySelectorAll('[data-preview-channel]').forEach(function (el) {
      var show = el.getAttribute('data-preview-channel') === 'paper' ? paper : electronic;
      el.classList.toggle('d-none', !show);
    });
    tr.querySelectorAll('[data-channel-toggle]').forEach(function (btn) {
      var ch = btn.getAttribute('data-channel-toggle');
      // واتساب يعتمد على وجود الجوال لا على الإلكتروني؛ البريد إلكتروني؛ التسليم ورقي
      var on = ch === 'delivery' ? paper : (ch === 'whatsapp' ? true : electronic);
      btn.disabled = !on;
      btn.classList.toggle('is-disabled', !on);
    });
    var emailHead = document.querySelector('[data-batch-email-preview]');
    function anyDelivery(mode) {
      var inputs = table ? table.querySelectorAll('[data-delivery-value]') : [];
      if (inputs.length) {
        return Array.prototype.some.call(inputs, function (input) {
          return mode === 'paper' ? input.value !== 'electronic' : input.value !== 'paper';
        });
      }
      return boxes().some(function (b) {
        return b.getAttribute(mode === 'paper' ? 'data-paper' : 'data-electronic') === '1';
      });
    }
    if (emailHead) emailHead.classList.toggle('d-none', !anyDelivery('electronic'));
    if (typeof refresh === 'function') {
      refresh({ save: false });
    }
  }
  if (table && deliveryForm) {
    table.addEventListener('change', function (e) {
      var box = e.target && e.target.closest ? e.target.closest('[data-delivery-toggle]') : null;
      if (!box) return;
      var cell = box.closest('td');
      var hidden = cell ? cell.querySelector('[data-delivery-value]') : null;
      if (!hidden) return;
      var prev = hidden.getAttribute('data-prev') || hidden.value || 'paper';
      var mode = box.checked ? 'electronic' : 'paper';
      if (prev === mode) return;
      var idMatch = (hidden.name || '').match(/\[(\d+)\]/);
      if (!idMatch) return;
      var seq = (Number(hidden.dataset.seq) || 0) + 1;
      hidden.dataset.seq = String(seq);
      hidden.value = mode;
      hidden.setAttribute('data-prev', mode);
      applyDeliveryRow(box, mode);
      deliveryToast(deliverySaved);
      var body = new URLSearchParams();
      var csrf = deliveryForm.querySelector('input[name="csrf_token"]');
      var batchInput = deliveryForm.querySelector('input[name="batch_id"]');
      if (csrf) body.set('csrf_token', csrf.value);
      if (batchInput) body.set('batch_id', batchInput.value);
      body.set('ajax', '1');
      body.set('recipient_delivery[' + idMatch[1] + ']', mode);
      fetch(window.location.href, {
        method: 'POST',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: body
      }).then(function (res) {
        return res.json().then(function (data) {
          return { ok: res.ok, data: data };
        });
      }).then(function (pack) {
        if (hidden.dataset.seq !== String(seq)) return;
        if (!pack.ok || !pack.data || !pack.data.ok) {
          throw new Error((pack.data && pack.data.error) || '');
        }
      }).catch(function (err) {
        if (hidden.dataset.seq !== String(seq)) return;
        hidden.value = prev;
        hidden.setAttribute('data-prev', prev);
        box.checked = prev === 'electronic' || prev === 'both';
        applyDeliveryRow(box, prev);
        deliveryToast((err && err.message) || deliverySaved, true);
      });
    });
    table.querySelectorAll('[data-delivery-value]').forEach(function (hidden) {
      hidden.setAttribute('data-prev', hidden.value);
    });
  }

  document.querySelectorAll('[data-batch-print-selected]').forEach(function (link) {
    link.addEventListener('click', function (e) {
      var base = link.getAttribute('data-print-base') || link.getAttribute('href') || '';
      if (!base) return;
      var paperOnly = link.getAttribute('data-print-paper') === '1';
      var requireIssue = link.getAttribute('data-print-paper') === '1' || /batch_(print|labels|thermal|corr_print)/.test(base);
      var anySelected = boxes().some(function (b) { return b.checked && !b.disabled; });
      var picked = boxes().filter(function (b) {
        if (!b.checked || b.disabled) return false;
        if (requireIssue && b.getAttribute('data-can-issue') === '0') return false;
        return true;
      });
      var ids = picked.filter(function (b) {
        return !paperOnly || b.getAttribute('data-paper') === '1';
      }).map(function (b) { return b.value; });
      var paperBoxes = boxes().filter(function (b) {
        if (b.getAttribute('data-paper') !== '1') return false;
        if (requireIssue && b.getAttribute('data-can-issue') === '0') return false;
        return !b.disabled;
      });
      function warnPrint(msg) {
        if (!msg) return;
        if (window.DmsSwal && typeof window.DmsSwal.warning === 'function') {
          window.DmsSwal.warning(msg);
        } else {
          window.alert(msg);
        }
      }
      if (paperOnly && !paperBoxes.length) {
        e.preventDefault();
        warnPrint(link.getAttribute('data-print-empty') || '');
        return;
      }
      // محددون إلكترونيون فقط → رسالة أوضح (لا «لا خطابات في الدفعة»)
      if (paperOnly && anySelected && !ids.length) {
        e.preventDefault();
        warnPrint(link.getAttribute('data-print-need-paper') || link.getAttribute('data-print-empty') || '');
        return;
      }
      var url = base;
      // بلا تحديد → كل الورقيين؛ مع تحديد → الورقيون المحددون فقط
      if (ids.length) {
        url += (base.indexOf('?') >= 0 ? '&' : '?') + ids.map(function (id) {
          return 'ids[]=' + encodeURIComponent(id);
        }).join('&');
      }
      link.setAttribute('href', url);
    });
  });

  if (!table) return;
  var noneMsg = form ? (form.getAttribute('data-none-selected') || '') : '';
  var master = table.querySelector('[data-batch-select-all]');
  var countEl = document.querySelector('[data-batch-selected-count]');
  var storageKey = batchId ? ('dms.batch.selected.' + batchId) : '';

  function channelMatch(b, channel) {
    if (channel === 'email') return b.getAttribute('data-electronic') === '1' && b.getAttribute('data-has-email') === '1';
    if (channel === 'whatsapp') return b.getAttribute('data-has-mobile') === '1';
    if (channel === 'delivery') return b.getAttribute('data-paper') === '1';
    return true;
  }
  function selectedFor(channel) {
    return boxes().filter(function (b) {
      if (!b.checked || b.disabled || b.getAttribute('data-can-issue') === '0') return false;
      return channelMatch(b, channel);
    }).length;
  }
  var selectionStore = window.localStorage || window.sessionStorage || null;
  var selectionToastTimer = null;
  var selectionReady = false;
  function persistSelection(showToast) {
    if (!storageKey || !selectionStore) return;
    try {
      var enabled = boxes().filter(function (b) { return !b.disabled; });
      // بلا مربعات تحديد: لا تحفظ فراغاً حتى لا يظهر المحدد 0 بالخطأ
      if (!enabled.length) return;
      var ids = enabled.filter(function (b) { return b.checked; }).map(function (b) { return b.value; });
      selectionStore.setItem(storageKey, JSON.stringify({ v: 1, ids: ids }));
      if (showToast && selectionReady) {
        if (selectionToastTimer) window.clearTimeout(selectionToastTimer);
        selectionToastTimer = window.setTimeout(function () {
          deliveryToast(selectionSaved, false);
        }, 250);
      }
    } catch (err) {}
  }
  function restoreSelection() {
    var all = boxes().filter(function (b) { return !b.disabled; });
    if (!all.length) return;
    var saved = null;
    if (storageKey && selectionStore) {
      try {
        saved = JSON.parse(selectionStore.getItem(storageKey) || 'null');
      } catch (err) {
        saved = null;
      }
    }
    var ids = null;
    if (saved && typeof saved === 'object' && !Array.isArray(saved) && Number(saved.v) === 1 && Array.isArray(saved.ids)) {
      ids = saved.ids;
    } else if (Array.isArray(saved) && saved.length > 0) {
      // صيغة قديمة فيها معرفات فقط
      ids = saved;
    }
    if (ids) {
      var map = {};
      ids.forEach(function (id) { map[String(id)] = true; });
      all.forEach(function (b) { b.checked = !!map[b.value]; });
    } else {
      // بلا حفظ أو [] قديم من جلسة بلا عمود → الكل محدد
      all.forEach(function (b) { b.checked = true; });
    }
  }
  function refresh(opts) {
    opts = opts || {};
    var all = boxes();
    var enabled = all.filter(function (b) { return !b.disabled; });
    var n = all.filter(function (b) { return b.checked && !b.disabled; }).length;
    // لا تستبدل قيمة الخادم بصفر إن لم يوجد عمود تحديد
    if (countEl && all.length) countEl.textContent = String(n);
    if (master) {
      master.checked = enabled.length > 0 && n === enabled.length;
      master.indeterminate = n > 0 && n < enabled.length;
    }
    if (form) {
      form.querySelectorAll('[data-batch-send]').forEach(function (btn) {
        var ch = btn.getAttribute('value') || '';
        btn.disabled = selectedFor(ch) === 0 || btn.getAttribute('data-need-ready') !== '1';
      });
    }
    persistSelection(!!opts.save);
  }
  function prepareSubmitNames(channel) {
    boxes().forEach(function (b) {
      if (!b.getAttribute('data-name-was')) {
        b.setAttribute('data-name-was', b.getAttribute('name') || 'ids[]');
      }
      var keep = b.checked && !b.disabled && b.getAttribute('data-can-issue') !== '0' && channelMatch(b, channel);
      if (keep) {
        b.setAttribute('name', b.getAttribute('data-name-was') || 'ids[]');
      } else {
        b.removeAttribute('name');
      }
    });
  }
  function restoreNames() {
    boxes().forEach(function (b) {
      var n = b.getAttribute('data-name-was') || 'ids[]';
      b.setAttribute('name', n);
    });
  }

  boxes().forEach(function (b) {
    if (b.disabled) b.setAttribute('data-locked', '1');
  });
  restoreSelection();

  if (master) {
    master.addEventListener('change', function () {
      var on = master.checked;
      boxes().forEach(function (b) {
        if (!b.disabled) b.checked = on;
      });
      refresh({ save: true });
    });
  }
  table.addEventListener('change', function (e) {
    if (e.target && e.target.classList.contains('batch-select-row')) {
      refresh({ save: true });
    }
  });

  if (form) {
    form.querySelectorAll('[data-batch-send]').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        restoreNames();
        var channel = btn.getAttribute('value') || '';
        var count = selectedFor(channel);
        if (count > 0) {
          form.dataset.pendingChannel = channel;
          var tpl = btn.getAttribute('data-confirm-template') || '';
          if (tpl) btn.setAttribute('data-confirm', tpl.replace(':count', String(count)));
          return;
        }
        e.preventDefault();
        e.stopImmediatePropagation();
        if (window.DmsSwal && typeof window.DmsSwal.warning === 'function') {
          window.DmsSwal.warning(noneMsg);
        } else {
          window.alert(noneMsg);
        }
      });
    });
    form.addEventListener('submit', function (e) {
      var channel = form.dataset.pendingChannel
        || ((e.submitter && e.submitter.getAttribute('value')) || '');
      prepareSubmitNames(channel);
    });
  }

  refresh();
  selectionReady = true;
  document.querySelectorAll('[data-batch-receipt]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var modalEl = document.getElementById('batchReceiptModal');
      if (!modalEl || !window.bootstrap) return;
      var idInput = document.getElementById('batchReceiptCorr');
      var statusIdInput = document.getElementById('batchReceiptStatusCorr');
      var nameInput = document.getElementById('batchReceiptName');
      var phoneInput = document.getElementById('batchReceiptPhone');
      var dateNative = document.getElementById('batch_receipt_date');
      var dateDisplay = document.getElementById('batch_receipt_date-display');
      var ref = document.getElementById('batchReceiptRef');
      var current = document.getElementById('batchReceiptCurrent');
      var file = document.getElementById('batch_receipt_file');
      var statusForm = document.getElementById('batchReceiptStatusForm');
      var statusSelect = document.getElementById('batchReceiptStatus');
      var statusWrap = document.getElementById('batchReceiptStatusLockWrap');
      var corrId = btn.getAttribute('data-id') || '';
      var statusCode = btn.getAttribute('data-status') || '';
      var isClosed = btn.getAttribute('data-closed') === '1';
      if (idInput) idInput.value = corrId;
      if (statusIdInput) statusIdInput.value = corrId;
      if (nameInput) nameInput.value = btn.getAttribute('data-name') || '';
      if (phoneInput) phoneInput.value = btn.getAttribute('data-phone') || '';
      if (dateNative) dateNative.value = btn.getAttribute('data-date') || '';
      if (dateDisplay) dateDisplay.value = btn.getAttribute('data-date-display') || '';
      if (ref) ref.textContent = btn.getAttribute('data-ref') || '';
      if (file) file.value = '';
      if (statusForm) statusForm.setAttribute('data-current-status', statusCode);
      if (statusSelect) {
        if (statusCode && !Array.prototype.some.call(statusSelect.options, function (opt) { return opt.value === statusCode; })) {
          var extra = document.createElement('option');
          extra.value = statusCode;
          extra.textContent = statusCode;
          statusSelect.appendChild(extra);
        }
        statusSelect.value = statusCode;
        statusSelect.disabled = isClosed;
      }
      if (statusWrap) {
        if (isClosed) {
          statusWrap.setAttribute('tabindex', '0');
          statusWrap.setAttribute('data-bs-toggle', 'tooltip');
          statusWrap.setAttribute('title', reopenTip);
          if (window.bootstrap && bootstrap.Tooltip) {
            bootstrap.Tooltip.getOrCreateInstance(statusWrap);
          }
        } else {
          statusWrap.removeAttribute('tabindex');
          statusWrap.removeAttribute('data-bs-toggle');
          statusWrap.removeAttribute('title');
          if (window.bootstrap && bootstrap.Tooltip) {
            var tip = bootstrap.Tooltip.getInstance(statusWrap);
            if (tip) tip.dispose();
          }
        }
      }
      if (current) {
        var url = btn.getAttribute('data-file-url') || '';
        var label = btn.getAttribute('data-file-name') || '';
        if (url) {
          current.hidden = false;
          current.innerHTML = '';
          var link = document.createElement('a');
          link.href = url;
          link.target = '_blank';
          link.rel = 'noopener';
          link.textContent = label || url;
          current.appendChild(link);
        } else {
          current.hidden = true;
          current.textContent = '';
        }
      }
      var receiptTab = document.getElementById('batch-delivery-tab-receipt');
      if (receiptTab && window.bootstrap && bootstrap.Tab) {
        bootstrap.Tab.getOrCreateInstance(receiptTab).show();
      }
      window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
    });
  });
})();
</script>
JS;
if (function_exists('dms_page_scripts_with_date_picker')) {
    $pageScripts = dms_page_scripts_with_date_picker($pageScripts);
}
require __DIR__ . '/../includes/footer.php';
?>

