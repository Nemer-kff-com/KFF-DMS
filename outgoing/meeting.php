<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/correspondence.php';
require_once __DIR__ . '/../config/correspondence_send.php';

$user = require_login();
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    redirect(base_url('outgoing/index.php'));
}
verify_csrf();

$batchId = (int) ($_POST['batch_id'] ?? 0);
$corrId = (int) ($_POST['correspondence_id'] ?? 0);
$action = trim((string) ($_POST['meeting_action'] ?? 'save'));
$resend = !empty($_POST['meeting_resend']);

$goBatch = static function (int $id): void {
    redirect(base_url('outgoing/batch.php?id=' . $id));
};
$goView = static function (int $id): void {
    redirect(base_url('outgoing/view.php?id=' . $id));
};

$canSend = user_can('outgoing.send', $user) || ($batchId > 0 && user_can('outgoing.bulk', $user));
if (!$canSend) {
    flash('danger', __('auth.forbidden'));
    $batchId > 0 ? $goBatch($batchId) : $goView($corrId);
}

if ($batchId > 0) {
    $batch = get_outgoing_batch($batchId);
    $items = $batch ? outgoing_batch_items($batchId, $user) : [];
    if (!$batch || $items === []) {
        flash('danger', __('corr.not_found'));
        redirect(base_url('outgoing/index.php'));
    }
    $sample = $items[0];
    if (function_exists('outgoing_is_comms_print_stage') && function_exists('outgoing_user_can_dispatch')) {
        $comms = null;
        foreach ($items as $row) {
            if (outgoing_is_comms_print_stage($row)) {
                $comms = $row;
                break;
            }
        }
        if ($comms && !outgoing_user_can_dispatch($user, $comms)) {
            flash('danger', __('issue_appr.comms_only_issue'));
            $goBatch($batchId);
        }
    }
    try {
        if ($action === 'send') {
            $meeting = correspondence_meeting_data($sample, $batch);
            if (!correspondence_meeting_has_url($meeting)) {
                throw new RuntimeException(__('meeting.send_need_url'));
            }
            $targets = [];
            foreach ($items as $row) {
                if (correspondence_is_remote_attendee($row)) {
                    $targets[] = $row;
                }
            }
            if ($targets === []) {
                throw new RuntimeException(__('meeting.send_need_remote'));
            }
            $summary = dms_send_meeting_links_for_items($targets, $user, $resend);
            $msg = str_replace(
                [':sent', ':failed', ':skipped'],
                [(string) $summary['sent'], (string) $summary['failed'], (string) $summary['skipped']],
                __('meeting.sent_ok')
            );
            if ((int) $summary['sent'] > 0 && (int) $summary['failed'] === 0) {
                flash('success', $msg);
            } elseif ((int) $summary['sent'] > 0) {
                flash('warning', $msg);
            } else {
                flash('warning', $msg);
            }
        } else {
            correspondence_save_meeting($batchId, null, $_POST);
            flash('success', __('meeting.saved'));
        }
    } catch (Throwable $e) {
        flash('danger', $e->getMessage());
    }
    $goBatch($batchId);
}

$corr = $corrId ? get_correspondence($corrId) : null;
if (!$corr || ($corr['type'] ?? '') !== 'outgoing' || !user_can_access_correspondence($corr, $user)) {
    flash('danger', __('corr.not_found'));
    redirect(base_url('outgoing/index.php'));
}
if (function_exists('outgoing_user_can_dispatch')
    && function_exists('outgoing_is_comms_print_stage')
    && outgoing_is_comms_print_stage($corr)
    && !outgoing_user_can_dispatch($user, $corr)
) {
    flash('danger', __('issue_appr.comms_only_issue'));
    $goView($corrId);
}

try {
    $batchOf = (int) ($corr['batch_id'] ?? 0);
    if ($action === 'send') {
        $meeting = correspondence_meeting_data($corr);
        if (!correspondence_meeting_has_url($meeting)) {
            throw new RuntimeException(__('meeting.send_need_url'));
        }
        $result = dms_send_meeting_link_to_corr($corr, $user, $resend);
        if (!empty($result['ok'])) {
            flash('success', __('meeting.sent_one_ok'));
        } else {
            flash('warning', (string) ($result['error'] ?? __('meeting.send_none')));
        }
    } else {
        correspondence_save_meeting($batchOf > 0 ? $batchOf : null, $batchOf > 0 ? null : $corrId, $_POST);
        flash('success', __('meeting.saved'));
    }
} catch (Throwable $e) {
    flash('danger', $e->getMessage());
}
$goView($corrId);
