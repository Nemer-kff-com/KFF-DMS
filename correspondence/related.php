<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/correspondence.php';
$user = require_login();

$id = (int) ($_GET['id'] ?? 0);
$q = trim((string) ($_GET['q'] ?? ''));
$corr = $id > 0 ? get_correspondence($id) : null;
if ($id > 0 && (!$corr || !user_can_access_correspondence($corr, $user))) {
    flash('danger', __('corr.not_found'));
    redirect(base_url('correspondence/related.php'));
}

$pageTitle = __('corr.related_title');
$chain = [];
$topicRows = [];
$pairs = [];
if ($corr) {
    $chain = correspondence_link_group((int) $corr['id'], $user);
    $exclude = array_map(static fn(array $r): int => (int) ($r['id'] ?? 0), $chain);
    if ($q !== '') {
        $topicRows = array_values(array_filter(
            correspondence_topic_search($q, $user),
            static fn(array $r): bool => !in_array((int) ($r['id'] ?? 0), $exclude, true)
        ));
    } else {
        $topicRows = correspondence_same_subject_rows((string) ($corr['subject'] ?? ''), $user, $exclude);
    }
} elseif ($q !== '') {
    $topicRows = correspondence_topic_search($q, $user);
} else {
    $pairs = correspondence_linked_pair_list($user);
    $seenIds = [];
    foreach ($pairs as $pair) {
        foreach (['left', 'right'] as $side) {
            $sid = (int) ($pair[$side]['id'] ?? 0);
            if ($sid <= 0 || isset($seenIds[$sid])) {
                continue;
            }
            $seenIds[$sid] = true;
            $full = get_correspondence($sid);
            if (
                $full
                && in_array((string) ($full['type'] ?? ''), ['incoming', 'outgoing'], true)
                && user_can_access_correspondence($full, $user)
            ) {
                $topicRows[] = $full;
            }
        }
    }
}

if ($topicRows !== []) {
    $hydrated = [];
    foreach ($topicRows as $row) {
        $hid = (int) ($row['id'] ?? 0);
        if ($hid <= 0) {
            continue;
        }
        if (!empty($row['department_directed_from']) || !empty($row['issuing_directed_from']) || !empty($row['snap_person_name'])) {
            $hydrated[] = $row;
            continue;
        }
        $full = get_correspondence($hid);
        $hydrated[] = $full ?: $row;
    }
    $topicRows = $hydrated;
}

require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title"><?= e(__('corr.related_title')) ?></h1>
        <p class="page-sub"><?= e(__('corr.related_sub')) ?></p>
    </div>
    <?php if ($corr): ?>
        <div class="page-header-actions">
            <a class="btn btn-outline-secondary" href="<?= e(base_url('correspondence/related.php')) ?>">
                <i class="bi bi-link-45deg me-1"></i><?= e(__('corr.related_all')) ?>
            </a>
        </div>
    <?php endif; ?>
</div>

<div class="card-panel mb-3">
    <div class="card-body p-3">
        <form method="get" class="row g-2 align-items-end">
            <?php if ($corr): ?>
                <input type="hidden" name="id" value="<?= (int) $corr['id'] ?>">
            <?php endif; ?>
            <div class="col-md-9">
                <label class="form-label" for="related-q"><?= e(__('corr.related_topic')) ?></label>
                <input type="search" name="q" id="related-q" class="form-control"
                       value="<?= e($q !== '' ? $q : (string) ($corr['subject'] ?? '')) ?>"
                       placeholder="<?= e(__('corr.related_topic_ph')) ?>">
            </div>
            <div class="col-md-3">
                <button class="btn btn-kff w-100" type="submit">
                    <i class="bi bi-search me-1"></i><?= e(__('action.search')) ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?php if ($corr): ?>
    <div class="card-panel mb-3">
        <div class="card-header">
            <i class="bi bi-link-45deg me-1"></i><?= e(__('corr.related_chain')) ?>
            <span class="text-muted">(<?= count($chain) ?>)</span>
        </div>
        <?php $relatedRows = $chain; $relatedHighlightId = (int) $corr['id']; require __DIR__ . '/../includes/corr_related_table.php'; ?>
    </div>
    <?php if ($topicRows): ?>
    <div class="card-panel mb-3">
        <div class="card-header">
            <i class="bi bi-chat-square-text me-1"></i><?= e(__('corr.related_topic')) ?>
            <span class="text-muted">(<?= count($topicRows) ?>)</span>
        </div>
        <?php $relatedRows = $topicRows; $relatedHighlightId = 0; require __DIR__ . '/../includes/corr_related_table.php'; ?>
    </div>
    <?php endif; ?>
<?php elseif ($q !== '' && mb_strlen($q) < 2): ?>
    <div class="alert alert-warning"><?= e(__('search.min')) ?></div>
<?php else: ?>
    <div class="card-panel mb-3">
        <div class="card-header">
            <i class="bi bi-link-45deg me-1"></i><?= e($q !== '' ? __('corr.related_topic') : __('corr.related_all')) ?>
            <span class="text-muted">(<?= count($topicRows) ?>)</span>
        </div>
        <?php $relatedRows = $topicRows; $relatedHighlightId = 0; require __DIR__ . '/../includes/corr_related_table.php'; ?>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
