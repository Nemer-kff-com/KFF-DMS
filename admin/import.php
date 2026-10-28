<?php
declare(strict_types=1);

$kind = trim((string) ($_GET['kind'] ?? 'entities'));
if (($_GET['action'] ?? '') === 'template') {
    require_once __DIR__ . '/../config/auth.php';
    require_once __DIR__ . '/../config/dms_import.php';
    require_permission('import.data');
    if (!dms_import_kind($kind)) {
        $kind = 'entities';
    }
    dms_import_stream_template($kind);
}

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/dms_import.php';

$user = require_permission('import.data');
$pageTitle = __('import.title');
$kinds = dms_import_kinds();
if (!isset($kinds[$kind])) {
    $kind = 'entities';
}

$preview = null;
$selfUrl = base_url('admin/import.php?kind=' . urlencode($kind));
$templateUrl = $selfUrl . '&action=template';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $postKind = trim((string) ($_POST['kind'] ?? $kind));
    if (!isset($kinds[$postKind])) {
        $postKind = $kind;
    }
    $kind = $postKind;
    $sessionKey = 'dms_import_pending_' . $kind;

    if ($action === 'cancel') {
        unset($_SESSION[$sessionKey]);
        flash('warning', __('import.cancelled'));
        redirect(base_url('admin/import.php?kind=' . urlencode($kind)));
    }

    if ($action === 'upload') {
        if (empty($_FILES['import_file']['tmp_name']) || !is_uploaded_file((string) $_FILES['import_file']['tmp_name'])) {
            flash('danger', __('import.choose_file'));
        } else {
            $parsed = dms_import_parse_upload(
                $kind,
                (string) $_FILES['import_file']['tmp_name'],
                (string) ($_FILES['import_file']['name'] ?? '')
            );
            if (!$parsed['ok']) {
                flash('danger', (string) ($parsed['error'] ?? __('import.upload_read_failed')));
            } else {
                $validated = dms_import_validate_rows($kind, $parsed['rows'], $user);
                $_SESSION[$sessionKey] = [
                    'token' => bin2hex(random_bytes(16)),
                    'kind' => $kind,
                    'created_at' => time(),
                    'valid' => $validated['valid'],
                    'invalid' => $validated['invalid'],
                    'summary' => $validated['summary'],
                    'filename' => (string) ($_FILES['import_file']['name'] ?? ''),
                ];
            }
        }
        redirect(base_url('admin/import.php?kind=' . urlencode($kind)));
    }

    if ($action === 'confirm') {
        $sessionKey = 'dms_import_pending_' . $kind;
        $pending = $_SESSION[$sessionKey] ?? null;
        $confirmToken = (string) ($_POST['import_token'] ?? '');
        if (!is_array($pending) || $confirmToken === '' || !hash_equals((string) ($pending['token'] ?? ''), $confirmToken)) {
            unset($_SESSION[$sessionKey]);
            flash('danger', __('import.preview_expired'));
            redirect(base_url('admin/import.php?kind=' . urlencode($kind)));
        }
        if (empty($pending['valid']) || !is_array($pending['valid'])) {
            unset($_SESSION[$sessionKey]);
            flash('danger', __('import.nothing_to_import'));
            redirect(base_url('admin/import.php?kind=' . urlencode($kind)));
        }

        $result = dms_import_commit($kind, $pending['valid'], $user);
        unset($_SESSION[$sessionKey]);
        if ($result['ok']) {
            flash('success', str_replace(':count', (string) (int) $result['inserted'], __('import.done')));
        } else {
            flash('danger', (string) ($result['error'] ?? __('import.commit_failed')));
        }
        redirect(base_url('admin/import.php?kind=' . urlencode($kind)));
    }
}

$sessionKey = 'dms_import_pending_' . $kind;
if (!empty($_SESSION[$sessionKey]) && is_array($_SESSION[$sessionKey])) {
    $age = time() - (int) ($_SESSION[$sessionKey]['created_at'] ?? 0);
    if ($age > 3600) {
        unset($_SESSION[$sessionKey]);
    } else {
        $preview = $_SESSION[$sessionKey];
    }
}

$pageExtraHead = <<<'CSS'
<style>
.dms-import-hub {
    box-sizing: border-box;
    width: 100%;
    max-width: 640px;
    margin-inline: auto;
    padding-inline: 1rem;
}
.dms-import-hub.is-kind-incoming { --dms-import-accent: #0b5130; --dms-import-accent-hover: #084327; --dms-import-border: rgba(11, 81, 48, 0.22); }
.dms-import-hub.is-kind-outgoing,
.dms-import-hub.is-kind-entities { --dms-import-accent: #3B4F6E; --dms-import-accent-hover: #2c3e50; --dms-import-border: rgba(59, 79, 110, 0.22); }
.dms-import-tabs { border-bottom-color: #e6e2da; margin-bottom: 1rem !important; }
.dms-import-tabs .nav-link {
    font-weight: 700;
    color: #6b7280;
    border: 0;
    border-bottom: 2px solid transparent;
    border-radius: 0;
    padding: .55rem .85rem;
}
.dms-import-tabs .nav-link:hover { color: #3B4F6E; border-bottom-color: rgba(59, 79, 110, 0.25); }
.dms-import-tabs .nav-link.active {
    color: var(--dms-import-accent, #3B4F6E);
    border-bottom-color: var(--dms-import-accent, #3B4F6E);
    background: transparent;
}
.dms-import .card {
    border: 1px solid var(--dms-import-border, rgba(59, 79, 110, 0.22));
    border-radius: 0.85rem;
    background: #fff;
    box-shadow: 0 8px 24px rgba(59, 79, 110, 0.06);
    overflow: hidden;
    margin-bottom: 1.25rem;
}
.dms-import .card-header {
    background: #fff;
    border-bottom: 2px solid var(--dms-import-accent, #3B4F6E);
    font-weight: 700;
    color: var(--dms-import-accent, #3B4F6E);
    padding: 0.85rem 1.1rem;
}
.dms-import .card-body { padding: 1.1rem 1.15rem 1.25rem; }
.dms-import .lead-text {
    color: #6a7077;
    font-size: 0.92rem;
    line-height: 1.7;
    margin-bottom: 1rem;
}
.dms-import .steps {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.75rem;
    margin-bottom: 1.25rem;
}
.dms-import .step {
    border: 1px solid #e7e9ec;
    border-radius: 0.65rem;
    background: #fafbfc;
    padding: 0.85rem 0.9rem;
    text-align: center;
}
.dms-import .step .num {
    display: inline-flex;
    width: 1.7rem;
    height: 1.7rem;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: var(--dms-import-accent, #3B4F6E);
    color: #fff;
    font-weight: 800;
    font-size: 0.85rem;
    margin-bottom: 0.4rem;
}
.dms-import .step .label { font-weight: 700; color: #54595F; font-size: 0.9rem; }
.dms-import .btn-dms-import {
    background: var(--dms-import-accent, #3B4F6E);
    border-color: var(--dms-import-accent, #3B4F6E);
    color: #fff;
}
.dms-import .btn-dms-import:hover,
.dms-import .btn-dms-import:focus {
    background: var(--dms-import-accent-hover, #2c3e50);
    border-color: var(--dms-import-accent-hover, #2c3e50);
    color: #fff;
}
.dms-import .dms-import-btn-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.35rem;
    height: 2.35rem;
    padding: 0;
    line-height: 1;
    flex-shrink: 0;
    border-radius: 50%;
}
.dms-import .dms-import-btn-icon i { margin: 0 !important; font-size: 0.95rem; }
.dms-import .stats-row {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.65rem;
    margin-bottom: 1rem;
}
.dms-import .stat {
    border: 1px solid #e7e9ec;
    border-radius: 0.65rem;
    background: #fafbfc;
    padding: 0.7rem;
    text-align: center;
}
.dms-import .stat .label { display: block; color: #7a8188; font-size: 0.85rem; font-weight: 700; }
.dms-import .stat .value { display: block; font-size: 1.35rem; font-weight: 800; color: #54595F; }
.dms-import .stat.is-ok { background: #f0fdf4; border-color: #bbf7d0; }
.dms-import .stat.is-ok .value { color: #15803d; }
.dms-import .stat.is-bad { background: #fef2f2; border-color: #fecaca; }
.dms-import .stat.is-bad .value { color: #b91c1c; }
.dms-import .table-wrap {
    max-height: 22rem;
    overflow: auto;
    border: 1px solid #e7e9ec;
    border-radius: 0.65rem;
}
.dms-import table { margin: 0; font-size: 0.88rem; }
.dms-import .err-list { margin: 0; padding-inline-start: 1.1rem; color: #b91c1c; }
@media (max-width: 768px) {
    .dms-import .steps,
    .dms-import .stats-row { grid-template-columns: 1fr; }
}
</style>
CSS;

require __DIR__ . '/../includes/header.php';
$headers = dms_import_column_headers($kind);
$previewLabelKey = $kind === 'entities' ? 'name_ar' : 'subject';
?>
<div class="page-header">
    <div>
        <h1 class="page-title"><?= e(__('import.title')) ?></h1>
        <p class="page-sub"><?= e(__('import.subtitle')) ?></p>
    </div>
</div>

<div class="dms-import-hub is-kind-<?= e($kind) ?>">
<ul class="nav nav-tabs mb-3 dms-import-tabs">
    <?php foreach ($kinds as $key => $meta): ?>
        <li class="nav-item">
            <a class="nav-link<?= $kind === $key ? ' active' : '' ?>" href="<?= e(base_url('admin/import.php?kind=' . urlencode($key))) ?>">
                <?= e($meta['label']) ?>
            </a>
        </li>
    <?php endforeach; ?>
</ul>

<div class="dms-import is-kind-<?= e($kind) ?> py-3">
    <div class="card">
        <div class="card-header">
            <i class="bi bi-file-earmark-spreadsheet me-1"></i>
            <?= e($kinds[$kind]['label']) ?>
        </div>
        <div class="card-body">
            <p class="lead-text"><?= e(__('import.lead_' . $kind)) ?></p>

            <div class="steps">
                <div class="step">
                    <div class="num">1</div>
                    <div class="label"><?= e(__('import.step_template')) ?></div>
                </div>
                <div class="step">
                    <div class="num">2</div>
                    <div class="label"><?= e(__('import.step_upload')) ?></div>
                </div>
                <div class="step">
                    <div class="num">3</div>
                    <div class="label"><?= e(__('import.step_confirm')) ?></div>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 mb-3">
                <a class="btn btn-dms-import dms-import-btn-icon"
                   href="<?= e($templateUrl) ?>"
                   data-bs-toggle="tooltip"
                   title="<?= e(__('import.download_template')) ?>"
                   aria-label="<?= e(__('import.download_template')) ?>">
                    <i class="bi bi-download" aria-hidden="true"></i>
                </a>
            </div>

            <form method="post" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="upload">
                <input type="hidden" name="kind" value="<?= e($kind) ?>">
                <label class="form-label fw-bold" for="import_file"><?= e(__('import.file_label')) ?> (.xlsx)</label>
                <div class="d-flex flex-wrap align-items-start gap-2">
                    <?php
                    $dmsFile = [
                        'name' => 'import_file',
                        'id' => 'import_file',
                        'required' => true,
                        'accept' => '.xlsx,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv',
                        'browse' => __('file.choose_one'),
                        'placeholder' => __('file.placeholder_one'),
                        'wrap_class' => 'mb-0 flex-grow-1',
                    ];
                    require __DIR__ . '/../includes/dms_file_field.php';
                    ?>
                    <button type="submit" class="btn btn-dms-import dms-import-btn-icon"
                            data-bs-toggle="tooltip"
                            title="<?= e(__('import.upload_preview')) ?>"
                            aria-label="<?= e(__('import.upload_preview')) ?>">
                        <i class="bi bi-upload" aria-hidden="true"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if (is_array($preview)): ?>
        <?php
        $summary = $preview['summary'] ?? ['total' => 0, 'valid' => 0, 'invalid' => 0];
        $validRows = is_array($preview['valid'] ?? null) ? $preview['valid'] : [];
        $invalidRows = is_array($preview['invalid'] ?? null) ? $preview['invalid'] : [];
        ?>
        <div class="card">
            <div class="card-header">
                <?= e(__('import.preview_title')) ?>
                <?php if (!empty($preview['filename'])): ?>
                    <span class="text-muted fw-normal">— <?= e((string) $preview['filename']) ?></span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <div class="stats-row">
                    <div class="stat">
                        <span class="label"><?= e(__('import.stat_total')) ?></span>
                        <span class="value"><?= (int) ($summary['total'] ?? 0) ?></span>
                    </div>
                    <div class="stat is-ok">
                        <span class="label"><?= e(__('import.stat_valid')) ?></span>
                        <span class="value"><?= (int) ($summary['valid'] ?? 0) ?></span>
                    </div>
                    <div class="stat is-bad">
                        <span class="label"><?= e(__('import.stat_invalid')) ?></span>
                        <span class="value"><?= (int) ($summary['invalid'] ?? 0) ?></span>
                    </div>
                </div>

                <?php if ($invalidRows !== []): ?>
                    <h6 class="fw-bold text-danger mb-2"><?= e(__('import.invalid_rows')) ?></h6>
                    <div class="table-wrap mb-3">
                        <table class="table table-sm table-striped mb-0">
                            <thead>
                                <tr>
                                    <th style="width:4rem">#</th>
                                    <th><?= e($kind === 'entities' ? __('import.col_name_ar') : __('import.col_subject')) ?></th>
                                    <th><?= e(__('import.errors')) ?></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($invalidRows as $bad): ?>
                                <tr>
                                    <td><?= (int) ($bad['row_num'] ?? 0) ?></td>
                                    <td><?= e((string) (($bad['data'][$previewLabelKey] ?? $bad['data']['subject'] ?? $bad['data']['name_ar'] ?? ''))) ?></td>
                                    <td>
                                        <ul class="err-list">
                                            <?php foreach (($bad['errors'] ?? []) as $err): ?>
                                                <li><?= e((string) $err) ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <?php if ($validRows !== []): ?>
                    <h6 class="fw-bold mb-2 text-success"><?= e(__('import.valid_rows')) ?></h6>
                    <div class="table-wrap mb-3">
                        <table class="table table-sm table-striped mb-0">
                            <thead>
                                <tr>
                                    <th style="width:4rem">#</th>
                                    <?php foreach (array_slice(array_keys($headers), 0, 5) as $hk): ?>
                                        <th><?= e($headers[$hk]) ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach (array_slice($validRows, 0, 50) as $good): ?>
                                <?php $p = $good['payload'] ?? []; ?>
                                <tr>
                                    <td><?= (int) ($good['row_num'] ?? 0) ?></td>
                                    <?php foreach (array_slice(array_keys($headers), 0, 5) as $hk): ?>
                                        <td><?= e((string) ($p[$hk] ?? '')) ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if (count($validRows) > 50): ?>
                        <p class="small text-muted"><?= e(__('import.preview_truncated')) ?></p>
                    <?php endif; ?>
                <?php endif; ?>

                <div class="d-flex flex-wrap gap-2">
                    <?php if ($validRows !== []): ?>
                        <?php $confirmCount = count($validRows); ?>
                        <form method="post" id="dmsImportConfirmForm" class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="confirm">
                            <input type="hidden" name="kind" value="<?= e($kind) ?>">
                            <input type="hidden" name="import_token" value="<?= e((string) ($preview['token'] ?? '')) ?>">
                            <button type="button" class="btn btn-dms-import dms-import-btn-icon" id="dmsImportConfirmBtn"
                                    data-bs-toggle="tooltip"
                                    title="<?= e(str_replace(':count', (string) $confirmCount, __('import.confirm_tip'))) ?>"
                                    aria-label="<?= e(__('import.confirm_import')) ?>">
                                <i class="bi bi-check-lg" aria-hidden="true"></i>
                            </button>
                        </form>
                    <?php endif; ?>
                    <form method="post" class="d-inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="cancel">
                        <input type="hidden" name="kind" value="<?= e($kind) ?>">
                        <button type="submit" class="btn btn-secondary dms-import-btn-icon"
                                data-bs-toggle="tooltip"
                                title="<?= e(__('import.cancel_preview')) ?>"
                                aria-label="<?= e(__('import.cancel')) ?>">
                            <i class="bi bi-x-lg" aria-hidden="true"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
</div>
<?php
$importConfirmColor = $kind === 'incoming' ? '#0b5130' : '#3B4F6E';
?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var btn = document.getElementById('dmsImportConfirmBtn');
    var form = document.getElementById('dmsImportConfirmForm');
    if (!btn || !form) return;
    btn.addEventListener('click', function () {
        var runConfirm = function (confirmed) {
            if (confirmed) form.submit();
        };
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'warning',
                title: <?= json_encode(__('import.confirm_swal_title'), JSON_UNESCAPED_UNICODE) ?>,
                text: <?= json_encode(__('import.confirm_swal_text'), JSON_UNESCAPED_UNICODE) ?>,
                showCancelButton: true,
                confirmButtonText: <?= json_encode(__('import.confirm_swal_yes'), JSON_UNESCAPED_UNICODE) ?>,
                cancelButtonText: <?= json_encode(__('import.cancel'), JSON_UNESCAPED_UNICODE) ?>,
                confirmButtonColor: <?= json_encode($importConfirmColor) ?>,
                cancelButtonColor: '#6c757d'
            }).then(function (result) {
                if (result.isConfirmed) form.submit();
            });
            return;
        }
        if (window.DmsSwal && typeof window.DmsSwal.confirm === 'function') {
            window.DmsSwal.confirm(
                <?= json_encode(__('import.confirm_swal_text'), JSON_UNESCAPED_UNICODE) ?>,
                <?= json_encode(__('import.confirm_swal_title'), JSON_UNESCAPED_UNICODE) ?>
            ).then(runConfirm);
            return;
        }
        if (window.confirm(<?= json_encode(__('import.confirm_swal_text'), JSON_UNESCAPED_UNICODE) ?>)) {
            form.submit();
        }
    });
});
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
