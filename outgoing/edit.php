<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/correspondence.php';
$user = require_login();
if (!user_can('outgoing.edit', $user)) {
    flash('danger', __('corr.no_perm_edit'));
    redirect(base_url('outgoing/index.php'));
}
$id = (int) ($_GET['id'] ?? 0);
$corr = get_correspondence($id);
if (!$corr || $corr['type'] !== 'outgoing' || !user_can_access_correspondence($corr, $user)) {
    flash('danger', __('corr.not_found'));
    redirect(base_url('outgoing/index.php'));
}
if (correspondence_is_cancelled($corr)) {
    flash('danger', __('cancel.locked'));
    redirect(base_url('outgoing/view.php?id=' . $id));
}
if (function_exists('outgoing_prep_is_superseded') && outgoing_prep_is_superseded($corr)) {
    $latestId = function_exists('outgoing_prep_latest_id') ? outgoing_prep_latest_id($corr) : 0;
    $latest = $latestId > 0 && $latestId !== $id ? get_correspondence($latestId) : null;
    flash('warning', __('out_prep.edit_latest_only'));
    if (
        $latest
        && user_can_access_correspondence($latest, $user)
        && corr_user_can_edit_in_cycle($user, $latest)
    ) {
        redirect(base_url('outgoing/edit.php?id=' . (int) $latest['id']));
    }
    redirect(base_url('outgoing/view.php?id=' . ($latest ? (int) $latest['id'] : $id)));
}
if (!corr_user_can_edit_in_cycle($user, $corr)) {
    flash('danger', corr_edit_cycle_block_message($corr));
    redirect(base_url('outgoing/view.php?id=' . $id));
}
$pageTitle = __('corr.outgoing_edit');
$type = 'outgoing';
$departments = departments_list();
$categories = categories_list();
$linkables = [];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        save_correspondence('outgoing', $_POST, $_FILES['attachments'] ?? $_FILES['attachment'] ?? null, $user, $id);
        if (!empty($_POST['out_prep_resend']) && function_exists('outgoing_prep_submit')) {
            $result = outgoing_prep_submit($id, $user);
            flash('success', $result === 'converted' ? outgoing_prep_converted_message($id) : __('out_prep.submitted_ok'));
        } else {
            flash('success', __('corr.updated_out'));
        }
        redirect(base_url('outgoing/view.php?id=' . $id));
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$prepReturned = function_exists('outgoing_prep_was_returned') && outgoing_prep_was_returned($corr);
$prepCanResend = $prepReturned
    && function_exists('outgoing_user_can_prep_submit')
    && outgoing_user_can_prep_submit($user, $corr);
$prepResendSelf = $prepCanResend && outgoing_user_is_prep_signer($user, $corr);

$pageScripts = dms_page_scripts_with_date_picker($pageScripts ?? '');
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header page-narrow">
    <div>
        <h1 class="page-title"><?= e(__('corr.outgoing_edit')) ?></h1>
        <p class="page-sub"><?= e(function_exists('correspondence_ref_display') ? correspondence_ref_display($corr) : (string) $corr['ref_number']) ?></p>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-outline-secondary" href="<?= e(base_url('outgoing/view.php?id=' . $id)) ?>">
            <i class="bi <?= dms_is_rtl() ? 'bi-arrow-right' : 'bi-arrow-left' ?> me-1"></i><?= e(__('action.back')) ?>
        </a>
    </div>
</div>

<div class="admin-editor page-narrow">
    <div class="card-panel admin-form-panel is-editing">
        <div class="card-header">
            <div class="admin-editor-header">
                <span class="admin-editor-icon"><i class="bi bi-pencil-square"></i></span>
                <span><?= e(__('corr.outgoing_edit')) ?></span>
            </div>
        </div>
        <div class="card-body">
            <?php require __DIR__ . '/../includes/corr_form_error.php'; ?>
            <form method="post" enctype="multipart/form-data" class="admin-form"
                  data-corr-id="<?= (int) $id ?>"
                  data-corr-ref="<?= e((string) ($corr['ref_number'] ?? '')) ?>">
                <?= csrf_field() ?>
                <?php require __DIR__ . '/../includes/corr_form_fields.php'; ?>
                <?php
                $attachLabel = __('corr.add_attach');
                $electronicChecked = $_SERVER['REQUEST_METHOD'] === 'POST'
                    ? !empty($_POST['intend_electronic'])
                    : !empty($corr['intend_electronic']);
                $rsvpChecked = $_SERVER['REQUEST_METHOD'] === 'POST'
                    ? !empty($_POST['rsvp_required'])
                    : !empty($corr['rsvp_required']);
                $notesValue = (string) ($_POST['notes'] ?? ($corr['notes'] ?? ''));
                require __DIR__ . '/../includes/corr_outgoing_attach_options.php';
                ?>
                <?php require __DIR__ . '/../includes/corr_outgoing_letter_fields.php'; ?>

                <div class="admin-form-actions">
                    <?php if ($prepCanResend): ?>
                    <button class="btn btn-kff" type="submit" name="out_prep_resend" value="1"
                            data-confirm="<?= e($prepResendSelf ? __('out_prep.confirm_resend_self') : __('out_prep.confirm_resend')) ?>">
                        <i class="bi bi-send me-1"></i><?= e($prepResendSelf ? __('out_prep.save_and_resend_self') : __('out_prep.save_and_resend')) ?>
                    </button>
                    <?php endif; ?>
                    <button class="btn <?= $prepCanResend ? 'btn-outline-secondary' : 'btn-kff' ?>" type="submit"><i class="bi bi-check2 me-1"></i><?= e(__('action.save_changes')) ?></button>
                    <a href="<?= e(base_url('outgoing/view.php?id=' . $id)) ?>" class="btn btn-outline-secondary"><?= e(__('action.cancel')) ?></a>
                </div>
            </form>
            <?php if (!empty($dmsEditDocForms)) {
                echo $dmsEditDocForms;
            } ?>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/corr_entity_email_hint_js.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
