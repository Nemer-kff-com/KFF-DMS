<?php
require_once __DIR__ . '/../config/auth.php';
$user = require_login();
$pageTitle = __('search.title');
$q = trim((string) ($_GET['q'] ?? ''));
$results = $q !== '' ? dms_unified_search($q, $user) : ['correspondence' => [], 'archive' => []];
$corrRows = $results['correspondence'];
$archRows = $results['archive'];

require __DIR__ . '/../includes/header.php';
?>
<?php if ($q === ''): ?>
<div class="page-header">
    <div>
        <h1 class="page-title"><?= e(__('search.title')) ?></h1>
        <p class="page-sub"><?= e(__('search.subtitle')) ?></p>
    </div>
</div>

<div class="card-panel mb-3">
    <div class="card-body p-3">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-9">
                <label class="form-label" for="q"><?= e(__('action.search')) ?></label>
                <input type="search" name="q" id="q" class="form-control" value=""
                       placeholder="<?= e(__('search.placeholder')) ?>" autofocus>
            </div>
            <div class="col-md-3">
                <button class="btn btn-kff w-100" type="submit"><i class="bi bi-search me-1"></i><?= e(__('action.search')) ?></button>
            </div>
        </form>
    </div>
</div>
<?php else: ?>
<div class="card-panel mb-3 d-md-none">
    <div class="card-body p-3">
        <form method="get" class="d-flex gap-2">
            <input type="search" name="q" id="q" class="form-control" value="<?= e($q) ?>"
                   placeholder="<?= e(__('search.placeholder')) ?>" aria-label="<?= e(__('search.placeholder')) ?>">
            <button class="btn btn-kff flex-shrink-0" type="submit"><i class="bi bi-search"></i></button>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($q !== '' && mb_strlen($q) < 2): ?>
    <div class="alert alert-warning"><?= e(__('search.min')) ?></div>
<?php elseif ($q !== ''): ?>
    <div class="card-panel mb-3">
        <div class="card-header"><?= e(__('nav.transactions')) ?> (<?= count($corrRows) ?>)</div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th><?= e(__('corr.number')) ?></th>
                        <th><?= e(__('corr.type')) ?></th>
                        <th><?= e(__('corr.subject')) ?></th>
                        <th><?= e(__('corr.party')) ?></th>
                        <th><?= e(__('corr.date')) ?></th>
                        <th><?= e(__('corr.status')) ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$corrRows): ?>
                    <tr><td colspan="7" class="text-center text-muted py-3"><?= e(__('common.no_results')) ?></td></tr>
                <?php endif; ?>
                <?php foreach ($corrRows as $r): ?>
                    <tr>
                        <td class="fw-semibold"><?= e($r['ref_number']) ?></td>
                        <td>
                            <span class="badge <?= ($r['type'] ?? '') === 'outgoing' ? 'text-bg-secondary' : 'text-bg-primary' ?>">
                                <?= e(type_label($r['type'] ?? '')) ?>
                            </span>
                        </td>
                        <td><?= e($r['subject']) ?></td>
                        <td><?= e(correspondence_party_display_name($r)) ?></td>
                        <td><?= e(format_date($r['corr_date'] ?? null)) ?></td>
                        <td><?= status_badge($r['status'] ?? 'new', $r['cancel_kind'] ?? null) ?></td>
                        <td><a class="btn btn-sm btn-outline-primary" href="<?= e(correspondence_view_url($r)) ?>"><?= e(__('action.view')) ?></a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card-panel">
        <div class="card-header"><?= e(__('nav.archive')) ?> (<?= count($archRows) ?>)</div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th><?= e(__('archive.number')) ?></th>
                        <th><?= e(__('archive.doc_title')) ?></th>
                        <th><?= e(__('archive.party')) ?></th>
                        <th><?= e(__('archive.doc_date')) ?></th>
                        <th><?= e(__('departments.col_officer')) ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$archRows): ?>
                    <tr><td colspan="6" class="text-center text-muted py-3"><?= e(__('common.no_results')) ?></td></tr>
                <?php endif; ?>
                <?php foreach ($archRows as $r): ?>
                    <tr>
                        <td class="fw-semibold" dir="ltr"><?= e(trim((string) ($r['archive_no'] ?? '')) !== '' ? (string) $r['archive_no'] : '—') ?></td>
                        <td><?= e(archive_title($r)) ?></td>
                        <td><?= e($r['party_name'] ?? '') ?></td>
                        <td><?= e(format_date($r['doc_date'] ?? null)) ?></td>
                        <td><?php render_correspondence_officer($r); ?></td>
                        <td><a class="btn btn-sm btn-outline-primary" href="<?= e(!empty($r['view_url']) ? (string) $r['view_url'] : archive_general_url('view', ['id' => (int) $r['id']])) ?>"><?= e(__('action.view_archive')) ?></a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
