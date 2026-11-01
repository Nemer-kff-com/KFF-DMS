<?php
/**
 * السجلات والجلسات — تبويبات موحّدة لمدير النظام.
 */
require_once __DIR__ . '/../config/auth.php';

$user = require_permission('admin.logs');
ensure_security_schema();

$tab = strtolower(trim((string) ($_GET['tab'] ?? $_POST['tab'] ?? 'activity')));
if (!in_array($tab, ['activity', 'security', 'sessions'], true)) {
    $tab = 'activity';
}

// معالجة POST قبل أي مخرجات HTML (تفادي headers already sent)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($tab === 'activity' && $action === 'clear_all') {
        $deleted = (int) db()->exec('DELETE FROM `tblactivity_log`');
        log_security('CLEAR_ACTIVITY_LOG', 'WARNING', 'deleted=' . $deleted, (int) $user['id']);
        flash('success', __('activity.cleared') . ' (' . $deleted . ')');
        redirect(base_url('admin/system_logs.php?tab=activity'));
    }

    if ($tab === 'security' && $action === 'clear_all') {
        $deleted = (int) db()->exec('DELETE FROM `tblsecurity_log`');
        log_security('CLEAR_SECURITY_LOG', 'CRITICAL', 'deleted=' . $deleted, (int) $user['id']);
        flash('success', __('security.cleared') . ' (' . $deleted . ')');
        redirect(base_url('admin/system_logs.php?tab=security'));
    }

    if ($tab === 'sessions') {
        $currentSid = session_id();
        if ($action === 'disconnect_one') {
            $sid = (string) ($_POST['session_id'] ?? '');
            if ($sid === $currentSid) {
                flash('warning', __('sessions.cannot_disconnect_self'));
            } elseif (disconnect_user_session($sid)) {
                log_security('SESSION_DISCONNECT', 'WARNING', 'session=' . substr($sid, 0, 16) . '…', (int) $user['id']);
                flash('success', __('sessions.disconnected'));
            } else {
                flash('danger', __('sessions.disconnect_fail'));
            }
        } elseif ($action === 'clear_others') {
            $stmt = db()->query('SELECT session_id FROM `tbluser_sessions`');
            $count = 0;
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $sid) {
                if ($sid === $currentSid) {
                    continue;
                }
                if (disconnect_user_session($sid)) {
                    $count++;
                }
            }
            log_security('SESSION_DISCONNECT_ALL', 'WARNING', 'count=' . $count, (int) $user['id']);
            flash('success', __('sessions.disconnected_others') . ' (' . $count . ')');
        } elseif ($action === 'purge_stale') {
            $count = purge_stale_user_sessions($currentSid);
            log_security('SESSION_PURGE_STALE', 'INFO', 'count=' . $count, (int) $user['id']);
            flash('success', __('sessions.purged_stale') . ' (' . $count . ')');
        }
        redirect(base_url('admin/system_logs.php?tab=sessions'));
    }
}

$pageTitle = __('nav.system_logs');
require __DIR__ . '/../includes/header.php';

$logsHubActive = $tab;
require __DIR__ . '/../includes/logs_hub_nav.php';

if ($tab === 'security') {
    require __DIR__ . '/../includes/admin_logs_security_panel.php';
} elseif ($tab === 'sessions') {
    require __DIR__ . '/../includes/admin_logs_sessions_panel.php';
} else {
    require __DIR__ . '/../includes/admin_logs_activity_panel.php';
}

require __DIR__ . '/../includes/footer.php';
