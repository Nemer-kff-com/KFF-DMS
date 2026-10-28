<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/operational_reset.php';

$user = require_permission('admin.reset');
$pageTitle = __('nav.reset_all');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $maintAction = (string) ($_POST['action'] ?? '');
    if (in_array($maintAction, ['purge_notifications', 'purge_security', 'purge_sessions'], true)) {
        try {
            $password = (string) ($_POST['admin_password'] ?? '');
            if ($password === '') {
                throw new InvalidArgumentException(__('reset.need_password'));
            }
            $stmt = db()->prepare('SELECT id, password FROM `tblusers` WHERE id = ? AND is_active = 1 LIMIT 1');
            $stmt->execute([(int) $user['id']]);
            $row = $stmt->fetch();
            if (!$row || !password_verify($password, (string) $row['password'])) {
                if (function_exists('log_security')) {
                    log_security('MAINT_PURGE_DENIED', 'WARNING', $maintAction, (int) $user['id']);
                }
                throw new InvalidArgumentException(__('reset.bad_password'));
            }

            if ($maintAction === 'purge_notifications') {
                $stats = dms_notifications_purge_finished_read();
                if (function_exists('log_security')) {
                    log_security(
                        'NOTIF_PURGE_READ',
                        'INFO',
                        'deleted=' . (int) $stats['deleted'] . ';kept=' . (int) $stats['kept'],
                        (int) $user['id']
                    );
                }
                flash('success', str_replace(
                    [':n', ':kept'],
                    [(string) (int) $stats['deleted'], (string) (int) $stats['kept']],
                    __('reset.purge_notif_done')
                ));
            } elseif ($maintAction === 'purge_security') {
                $n = dms_security_log_purge_old();
                if (function_exists('log_security')) {
                    log_security('SECURITY_LOG_PURGE_OLD', 'WARNING', 'deleted=' . $n, (int) $user['id']);
                }
                flash('success', str_replace(':n', (string) $n, __('reset.purge_sec_done')));
            } else {
                $n = dms_purge_expired_sessions();
                if (function_exists('log_security')) {
                    log_security('SESSION_PURGE_EXPIRED', 'INFO', 'deleted=' . $n, (int) $user['id']);
                }
                flash('success', str_replace(':n', (string) $n, __('reset.purge_sess_done')));
            }
        } catch (Throwable $e) {
            flash('danger', $e->getMessage());
        }
        redirect(base_url('admin/reset_all.php'));
    }
    try {
        $password = (string) ($_POST['admin_password'] ?? '');
        $confirmWord = trim((string) ($_POST['confirm_word'] ?? ''));
        $understood = !empty($_POST['understood']);

        if (!$understood) {
            throw new InvalidArgumentException(__('reset.need_checkbox'));
        }

        $expectedWords = ['RESET', 'إعادة ضبط', 'اعادة ضبط'];
        $okWord = false;
        foreach ($expectedWords as $word) {
            if (mb_strtolower($confirmWord) === mb_strtolower($word)) {
                $okWord = true;
                break;
            }
        }
        if (!$okWord) {
            throw new InvalidArgumentException(__('reset.bad_confirm_word'));
        }

        if ($password === '') {
            throw new InvalidArgumentException(__('reset.need_password'));
        }

        $stmt = db()->prepare('SELECT id, password FROM `tblusers` WHERE id = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([(int) $user['id']]);
        $row = $stmt->fetch();
        if (!$row || !password_verify($password, (string) $row['password'])) {
            if (function_exists('log_security')) {
                log_security('RESET_ALL_DENIED', 'WARNING', 'bad_password', (int) $user['id']);
            }
            throw new InvalidArgumentException(__('reset.bad_password'));
        }

        $incomingStart = (int) ($_POST['incoming_start'] ?? 1);
        $outgoingStart = (int) ($_POST['outgoing_start'] ?? 1);
        $internalStart = (int) ($_POST['internal_start'] ?? 1);
        $circularStart = (int) ($_POST['circular_start'] ?? 1);
        if ($incomingStart < 1 || $outgoingStart < 1 || $internalStart < 1 || $circularStart < 1) {
            throw new InvalidArgumentException(__('reset.number_start_invalid'));
        }
        if ($incomingStart > 999999 || $outgoingStart > 999999 || $internalStart > 999999 || $circularStart > 999999) {
            throw new InvalidArgumentException(__('reset.number_start_invalid'));
        }

        $stats = dms_operational_reset([
            'incoming_start' => $incomingStart,
            'outgoing_start' => $outgoingStart,
            'internal_start' => $internalStart,
            'circular_start' => $circularStart,
        ]);
        $rows = array_sum($stats['tables']);
        $files = (int) $stats['files_removed'];
        $num = $stats['numbering'] ?? [];
        $year = (int) ($num['year'] ?? date('Y'));

        if (function_exists('log_security')) {
            log_security(
                'RESET_ALL',
                'CRITICAL',
                'rows=' . $rows . ';files=' . $files
                . ';storage=' . (function_exists('dms_storage_root') ? dms_storage_root() : '')
                . ';in=' . (int) ($num['incoming_start'] ?? $incomingStart)
                . ';out=' . (int) ($num['outgoing_start'] ?? $outgoingStart)
                . ';int=' . (int) ($num['internal_start'] ?? $internalStart)
                . ';circ=' . (int) ($num['circular_start'] ?? $circularStart),
                (int) $user['id']
            );
        }

        flash('success', str_replace(
            [':rows', ':files', ':year', ':int', ':circ', ':out', ':in'],
            [
                (string) $rows,
                (string) $files,
                (string) $year,
                (string) (int) ($num['internal_start'] ?? $internalStart),
                (string) (int) ($num['circular_start'] ?? $circularStart),
                (string) (int) ($num['outgoing_start'] ?? $outgoingStart),
                (string) (int) ($num['incoming_start'] ?? $incomingStart),
            ],
            __('reset.done')
        ));
    } catch (Throwable $e) {
        flash('danger', $e->getMessage());
    }
    redirect(base_url('admin/reset_all.php'));
}

require __DIR__ . '/../includes/header.php';
$storageRootDisplay = function_exists('dms_storage_root') ? dms_storage_root() : (dirname(__DIR__) . '/storage/uploads');
?>
<div class="page-header page-narrow">
    <div>
        <h1 class="page-title text-danger"><i class="bi bi-exclamation-octagon me-2"></i><?= e(__('reset.title')) ?></h1>
        <p class="page-sub"><?= e(__('reset.subtitle')) ?></p>
    </div>
</div>

<div class="row g-3 page-narrow">
    <div class="col-lg-5">
        <div class="card-panel">
            <div class="card-header fw-bold"><?= e(__('reset.confirm_section')) ?></div>
            <div class="card-body">
                <p class="text-danger small mb-3"><?= e(__('reset.irreversible')) ?></p>
                <form method="post"
                      data-confirm="<?= e(__('reset.swal_confirm')) ?>"
                      autocomplete="off">
                    <?= csrf_field() ?>
                    <!-- يمنع المتصفح من وضع البريد في حقل عبارة التأكيد -->
                    <input type="text" name="dms_reset_username" value="" autocomplete="username" tabindex="-1" aria-hidden="true"
                           style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0">
                    <input type="password" name="dms_reset_password_decoy" value="" autocomplete="current-password" tabindex="-1" aria-hidden="true"
                           style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0">
                    <div class="mb-3">
                        <div class="fw-semibold mb-2"><i class="bi bi-hash me-1"></i><?= e(__('reset.numbering_section')) ?></div>
                        <p class="form-text mt-0 mb-2"><?= e(__('reset.numbering_hint')) ?></p>
                        <div class="row g-2 text-center">
                            <div class="col-6 col-md-3">
                                <label class="form-label d-block" for="outgoing_start"><?= e(__('reset.outgoing_start')) ?></label>
                                <input type="number" class="form-control text-center" name="outgoing_start" id="outgoing_start"
                                       dir="ltr" required min="1" max="999999" step="1" value="1" autocomplete="off">
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label d-block" for="incoming_start"><?= e(__('reset.incoming_start')) ?></label>
                                <input type="number" class="form-control text-center" name="incoming_start" id="incoming_start"
                                       dir="ltr" required min="1" max="999999" step="1" value="1" autocomplete="off">
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label d-block" for="internal_start"><?= e(__('reset.internal_start')) ?></label>
                                <input type="number" class="form-control text-center" name="internal_start" id="internal_start"
                                       dir="ltr" required min="1" max="999999" step="1" value="1" autocomplete="off">
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label d-block" for="circular_start"><?= e(__('reset.circular_start')) ?></label>
                                <input type="number" class="form-control text-center" name="circular_start" id="circular_start"
                                       dir="ltr" required min="1" max="999999" step="1" value="1" autocomplete="off">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="d-flex align-items-start gap-2">
                            <input class="form-check-input m-0 flex-shrink-0" type="checkbox" name="understood" id="understood" value="1" required>
                            <label class="form-check-label mb-0" for="understood"><?= e(__('reset.checkbox')) ?></label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="confirm_word"><?= e(__('reset.confirm_word_label')) ?></label>
                        <input type="text" class="form-control" name="confirm_word" id="confirm_word" required
                               placeholder="<?= e(__('reset.confirm_word_ph')) ?>"
                               value=""
                               autocomplete="off"
                               data-lpignore="true"
                               data-1p-ignore="true"
                               data-form-type="other"
                               readonly
                               onfocus="this.removeAttribute('readonly')">
                        <div class="form-text"><?= e(__('reset.confirm_word_hint')) ?></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="admin_password"><?= e(__('reset.password_label')) ?></label>
                        <div class="password-field">
                            <input type="password" class="form-control" name="admin_password" id="admin_password" required
                                   autocomplete="new-password">
                            <button type="button" class="password-field-toggle" data-password-toggle
                                    data-bs-toggle="tooltip" data-bs-placement="top"
                                    title="<?= e(__('profile.password_show')) ?>" aria-label="<?= e(__('profile.password_show')) ?>">
                                <i class="bi bi-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                        <div class="form-text"><?= e(__('reset.password_hint')) ?></div>
                    </div>
                    <button type="submit" class="btn btn-danger w-100" data-bs-placement="top">
                        <i class="bi bi-arrow-counterclockwise me-1"></i><?= e(__('reset.submit')) ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card-panel border border-danger border-opacity-50">
            <div class="card-header bg-danger bg-opacity-10 text-danger fw-bold">
                <i class="bi bi-trash3 me-1"></i><?= e(__('reset.will_delete')) ?>
            </div>
            <div class="card-body">
                <ul class="mb-0">
                    <li><?= e(__('reset.del_corr')) ?></li>
                    <li><?= e(__('reset.del_docs')) ?></li>
                    <li>
                        <?= e(__('reset.del_storage')) ?>:
                        <code dir="ltr" class="small"><?= e($storageRootDisplay) ?></code>
                        <span class="text-muted small">(<?= e(__('reset.del_storage_path')) ?>)</span>
                    </li>
                    <li><?= e(__('reset.del_archive')) ?></li>
                    <li><?= e(__('reset.del_circulars')) ?></li>
                    <li><?= e(__('reset.del_logs')) ?></li>
                    <li><?= e(__('reset.del_sequences')) ?></li>
                </ul>
            </div>
        </div>
        <div class="card-panel mt-3">
            <div class="card-header fw-bold text-success">
                <i class="bi bi-shield-check me-1"></i><?= e(__('reset.will_keep')) ?>
            </div>
            <div class="card-body">
                <ul class="mb-0">
                    <li><?= e(__('reset.keep_users')) ?></li>
                    <li><?= e(__('reset.keep_settings')) ?></li>
                    <li><?= e(__('reset.keep_entities')) ?></li>
                    <li><?= e(__('reset.keep_branding')) ?></li>
                </ul>
            </div>
        </div>
        <div class="card-panel mt-3">
            <div class="card-header fw-bold">
                <i class="bi bi-eraser me-1"></i><?= e(__('reset.maint_title')) ?>
            </div>
            <div class="card-body">
                <form method="post" autocomplete="off">
                    <?= csrf_field() ?>
                    <div class="reset-maint-actions">
                        <div class="reset-maint-pass">
                            <label class="form-label" for="maint_password"><?= e(__('reset.password_label')) ?></label>
                            <div class="password-field">
                                <input type="password" class="form-control" name="admin_password" id="maint_password" required autocomplete="new-password">
                                <button type="button" class="password-field-toggle" data-password-toggle
                                        data-bs-toggle="tooltip" data-bs-placement="top"
                                        title="<?= e(__('profile.password_show')) ?>" aria-label="<?= e(__('profile.password_show')) ?>">
                                    <i class="bi bi-eye" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>
                        <button type="submit" name="action" value="purge_notifications" class="btn btn-danger" data-bs-placement="top"
                                data-confirm="<?= e(__('reset.purge_notif_confirm')) ?>"><?= e(__('reset.purge_notif_btn')) ?></button>
                        <button type="submit" name="action" value="purge_security" class="btn btn-danger" data-bs-placement="top"
                                data-confirm="<?= e(__('reset.purge_sec_confirm')) ?>"><?= e(__('reset.purge_sec_btn')) ?></button>
                        <button type="submit" name="action" value="purge_sessions" class="btn btn-danger" data-bs-placement="top"
                                data-confirm="<?= e(__('reset.purge_sess_confirm')) ?>"><?= e(__('reset.purge_sess_btn')) ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    var passShow = <?= json_encode(__('profile.password_show'), JSON_UNESCAPED_UNICODE) ?>;
    var passHide = <?= json_encode(__('profile.password_hide'), JSON_UNESCAPED_UNICODE) ?>;
    document.querySelectorAll('[data-password-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var wrap = btn.closest('.password-field');
            var input = wrap && wrap.querySelector('input');
            if (!input) return;
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            var icon = btn.querySelector('i');
            if (icon) {
                icon.classList.toggle('bi-eye', !show);
                icon.classList.toggle('bi-eye-slash', show);
            }
            var label = show ? passHide : passShow;
            btn.setAttribute('title', label);
            btn.setAttribute('data-bs-original-title', label);
            btn.setAttribute('aria-label', label);
            if (window.bootstrap && bootstrap.Tooltip) {
                var tip = bootstrap.Tooltip.getInstance(btn);
                if (tip && typeof tip.setContent === 'function') {
                    tip.setContent({ '.tooltip-inner': label });
                }
            }
        });
    });
})();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
