<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/correspondence.php';
$user = require_login();

$id = (int) ($_GET['id'] ?? 0);
$q = trim((string) ($_GET['q'] ?? ''));
$type = (string) ($_GET['type'] ?? '');
$type = in_array($type, ['incoming', 'outgoing'], true) ? $type : '';
$relatedPage = 15;
$relatedMax = 80;
$relatedStep = 15;
$n = (int) ($_GET['n'] ?? $relatedPage);
$n = max($relatedPage, min($relatedMax, $n));
$fetchLimit = min($relatedMax, $type === '' ? (int) ceil(($n + 1) * 1.5) : $n + 1);

$corr = $id > 0 ? get_correspondence($id) : null;
if ($id > 0 && (!$corr || !user_can_access_correspondence($corr, $user))) {
    flash('danger', __('corr.not_found'));
    redirect(base_url('correspondence/related.php'));
}

$pageTitle = __('corr.related_title');
$chain = [];
$topicRows = [];
$pairs = [];
$topicHasMore = false;
$isTopicSearch = $q !== '';
$isAllLinks = false;

$keepRelatedType = static function (array $rows, string $type): array {
    $allowed = $type !== '' ? [$type] : ['incoming', 'outgoing'];

    return array_values(array_filter(
        $rows,
        static fn(array $r): bool => in_array((string) ($r['type'] ?? ''), $allowed, true)
    ));
};

if ($corr) {
    $chain = correspondence_link_group((int) $corr['id'], $user);
    $exclude = array_map(static fn(array $r): int => (int) ($r['id'] ?? 0), $chain);
    if ($isTopicSearch) {
        $topicRows = array_values(array_filter(
            correspondence_topic_search($q, $user, $fetchLimit, $type),
            static fn(array $r): bool => !in_array((int) ($r['id'] ?? 0), $exclude, true)
        ));
    } else {
        $topicRows = correspondence_same_subject_rows((string) ($corr['subject'] ?? ''), $user, $exclude, $fetchLimit, $type);
    }
    $topicRows = $keepRelatedType($topicRows, $type);
} elseif ($isTopicSearch) {
    $topicRows = $keepRelatedType(correspondence_topic_search($q, $user, $fetchLimit, $type), $type);
} else {
    $isAllLinks = true;
    $pairs = correspondence_linked_pair_list($user, min(120, $fetchLimit * 2));
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
    $topicRows = $keepRelatedType($topicRows, $type);
    usort($topicRows, static function (array $a, array $b): int {
        $da = (string) ($a['corr_date'] ?? '');
        $db = (string) ($b['corr_date'] ?? '');
        if ($da !== $db) {
            return $db <=> $da;
        }

        return ((int) ($b['id'] ?? 0)) <=> ((int) ($a['id'] ?? 0));
    });
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

if (count($topicRows) > $n) {
    $topicHasMore = true;
    $topicRows = array_slice($topicRows, 0, $n);
}

$moreUrl = static function (int $nextN) use ($corr, $q, $type): string {
    $params = ['n' => $nextN];
    if ($corr) {
        $params['id'] = (int) $corr['id'];
    }
    if ($q !== '') {
        $params['q'] = $q;
    }
    if ($type !== '') {
        $params['type'] = $type;
    }

    return base_url('correspondence/related.php?' . http_build_query($params));
};

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
            <div class="col-md-6">
                <label class="form-label" for="related-q"><?= e(__('corr.related_topic')) ?></label>
                <input type="search" name="q" id="related-q" class="form-control"
                       value="<?= e($q !== '' ? $q : (string) ($corr['subject'] ?? '')) ?>"
                       placeholder="<?= e(__('corr.related_topic_ph')) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="related-type"><?= e(__('corr.type')) ?></label>
                <select name="type" id="related-type" class="form-select">
                    <option value=""><?= e(__('common.all')) ?></option>
                    <option value="incoming"<?= $type === 'incoming' ? ' selected' : '' ?>><?= e(__('type.incoming')) ?></option>
                    <option value="outgoing"<?= $type === 'outgoing' ? ' selected' : '' ?>><?= e(__('type.outgoing')) ?></option>
                </select>
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
        <p class="small text-muted px-3 pt-2 mb-0"><?= e(__('corr.related_chain_hint')) ?></p>
        <?php $relatedRows = $chain; $relatedHighlightId = (int) $corr['id']; require __DIR__ . '/../includes/corr_related_table.php'; ?>
    </div>
    <?php if ($topicRows || $isTopicSearch || $type !== ''): ?>
    <div class="card-panel mb-3">
        <div class="card-header">
            <i class="bi bi-chat-square-text me-1"></i><?= e(__('corr.related_similar')) ?>
            <span class="text-muted">(<?= count($topicRows) ?>)</span>
        </div>
        <p class="small text-muted px-3 pt-2 mb-0"><?= e(__('corr.related_similar_hint')) ?></p>
        <?php $relatedRows = $topicRows; $relatedHighlightId = 0; require __DIR__ . '/../includes/corr_related_table.php'; ?>
        <?php if ($topicHasMore && $n < $relatedMax): ?>
            <div class="card-footer bg-transparent border-0 text-center pb-3">
                <a class="btn btn-outline-secondary btn-sm" href="<?= e($moreUrl(min($relatedMax, $n + $relatedStep))) ?>">
                    <i class="bi bi-chevron-down me-1"></i><?= e(__('corr.related_show_more')) ?>
                </a>
                <div class="small text-muted mt-1"><?= e(str_replace(':shown', (string) count($topicRows), __('corr.related_showing'))) ?></div>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
<?php elseif ($q !== '' && mb_strlen($q) < 2): ?>
    <div class="alert alert-warning"><?= e(__('search.min')) ?></div>
<?php else: ?>
    <div class="card-panel mb-3">
        <div class="card-header">
            <i class="bi bi-<?= $isTopicSearch ? 'chat-square-text' : 'link-45deg' ?> me-1"></i>
            <?= e($isTopicSearch ? __('corr.related_similar') : __('corr.related_all')) ?>
            <span class="text-muted">(<?= count($topicRows) ?>)</span>
        </div>
        <?php if ($isTopicSearch): ?>
            <p class="small text-muted px-3 pt-2 mb-0"><?= e(__('corr.related_similar_hint')) ?></p>
        <?php endif; ?>
        <?php $relatedRows = $topicRows; $relatedHighlightId = 0; require __DIR__ . '/../includes/corr_related_table.php'; ?>
        <?php if ($topicHasMore && $n < $relatedMax): ?>
            <div class="card-footer bg-transparent border-0 text-center pb-3">
                <a class="btn btn-outline-secondary btn-sm" href="<?= e($moreUrl(min($relatedMax, $n + $relatedStep))) ?>">
                    <i class="bi bi-chevron-down me-1"></i><?= e(__('corr.related_show_more')) ?>
                </a>
                <div class="small text-muted mt-1"><?= e(str_replace(':shown', (string) count($topicRows), __('corr.related_showing'))) ?></div>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
