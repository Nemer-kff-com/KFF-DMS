<?php
require_once __DIR__ . '/../config/auth.php';
$user = require_archive_view();
ensure_archive_schema();
$pageTitle = __('nav.archive');
$tones = ['incoming', 'outgoing', 'internal', 'archive'];
$hubCards = [];
$i = 0;
foreach (archive_register_defs() as $kind => $def) {
    if (!user_can_archive_section($kind, 'view', $user)) {
        continue;
    }
    $createState = dms_perm_ui(archive_section_perm($kind, 'create'), $user);
    $hubCards[] = [
        'tone' => $tones[$i % count($tones)],
        'icon' => $def['icon'],
        'label' => __($def['label']),
        'sub' => __((string) ($def['sub'] ?? '')),
        'listHref' => archive_register_url($kind, 'list'),
        'createHref' => archive_register_url($kind, 'form'),
        'count' => archive_register_count($kind),
        'canCreate' => $createState === 'allow',
        'createDisabled' => $createState === 'deny',
    ];
    $i++;
}
if (user_can_archive_section('general', 'view', $user)) {
    $generalTotal = function_exists('archive_list_page') ? (int) archive_list_page([], $user, 1)['total'] : 0;
    $createState = dms_perm_ui(archive_section_perm('general', 'create'), $user);
    $hubCards[] = [
        'tone' => $tones[$i % count($tones)],
        'icon' => 'bi-archive',
        'label' => __('archive.general'),
        'sub' => __('archive.general_sub'),
        'listHref' => archive_general_url('list'),
        'createHref' => archive_general_url('create'),
        'count' => $generalTotal,
        'canCreate' => $createState === 'allow',
        'createDisabled' => $createState === 'deny',
    ];
}
$hubQ = trim((string) ($_GET['q'] ?? ''));
$hubKind = trim((string) ($_GET['kind'] ?? 'all'));
$hubKinds = archive_hub_kinds();
if (!isset($hubKinds[$hubKind])) {
    $hubKind = 'all';
}
$hubRows = archive_hub_feed($user, $hubQ, $hubKind, $hubQ === '' ? 12 : 20);
$hubSearching = $hubQ !== '';
$hubMoreHref = '';
if ($hubSearching) {
    if ($hubKind === 'general') {
        $hubMoreHref = archive_general_url('list', ['q' => $hubQ]);
    } elseif ($hubKind !== 'all' && archive_register_def($hubKind)) {
        $hubMoreHref = archive_register_url($hubKind, 'list', ['q' => $hubQ]);
    }
}
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title"><i class="bi bi-archive me-1"></i> <?= e(__('archive.title')) ?></h1>
        <p class="page-sub"><?= e(__('archive.subtitle')) ?></p>
    </div>
</div>

<div class="row g-3 mb-4 dash-hero-stats archive-hub-stats">
    <?php foreach ($hubCards as $card): ?>
    <div class="col-12 col-md-6 col-lg-6">
        <?php
        $canCreate = !empty($card['canCreate']);
        $createDisabled = !empty($card['createDisabled']);
        $tone = $card['tone'];
        $iconClass = $card['icon'];
        $sectionLabel = $card['label'];
        $sectionSub = '';
        $createHref = $card['createHref'];
        $createIcon = 'bi-plus-lg';
        $createLabel = __('action.add');
        $listHref = $card['listHref'];
        $listIcon = $card['icon'];
        $listLabel = __('action.view_all');
        $headCount = (int) $card['count'];
        $headCountStatic = true;
        $hideMetrics = true;
        $todayValue = 0;
        $todayLabel = '';
        $todayHref = $card['listHref'];
        $inProgressValue = 0;
        $inProgressHref = $card['listHref'];
        $showListAction = true;
        require __DIR__ . '/../includes/dash_type_card.php';
        unset($hideMetrics, $sectionSub, $headCount, $headCountStatic, $headCountBelow, $canCreate, $createDisabled);
        ?>
    </div>
    <?php endforeach; ?>
</div>

<div class="card-panel mb-3">
    <div class="card-body p-3">
        <form class="list-filters" method="get" action="<?= e(base_url('archive/index.php')) ?>">
            <div class="list-filters-main">
                <div class="list-filters-item list-filters-search">
                    <label class="form-label" for="archive-hub-q"><?= e(__('action.search')) ?></label>
                    <input type="search" name="q" id="archive-hub-q" class="form-control"
                           placeholder="<?= e(__('archive.hub.search_ph')) ?>" value="<?= e($hubQ) ?>"
                           title="<?= e(__('archive.hub.search_ph')) ?>">
                </div>
                <div class="list-filters-item">
                    <label class="form-label" for="archive-hub-kind"><?= e(__('archive.hub.kind')) ?></label>
                    <select name="kind" id="archive-hub-kind" class="form-select" title="<?= e(__('archive.hub.kind')) ?>">
                        <?php foreach ($hubKinds as $k => $labelKey): ?>
                            <option value="<?= e($k) ?>" <?= $hubKind === $k ? 'selected' : '' ?>><?= e(__($labelKey)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="list-filters-item list-filters-actions">
                    <button class="btn btn-kff" type="submit"><i class="bi bi-search me-1"></i><?= e(__('action.search')) ?></button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card-panel mb-4 archive-hub-feed">
    <div class="card-header d-flex align-items-center justify-content-between gap-2">
        <span>
            <i class="bi <?= $hubSearching ? 'bi-search' : 'bi-clock-history' ?> me-1"></i>
            <?= e($hubSearching ? __('archive.hub.results') : __('archive.hub.recent')) ?>
            <?php if ($hubRows): ?>
                <span class="badge text-bg-light border ms-1"><?= count($hubRows) ?></span>
            <?php endif; ?>
        </span>
        <?php if ($hubMoreHref !== ''): ?>
            <a class="btn btn-sm btn-outline-secondary" href="<?= e($hubMoreHref) ?>"><?= e(__('archive.hub.more')) ?></a>
        <?php endif; ?>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th><?= e(__('archive.number')) ?></th>
                    <th><?= e(__('archive.hub.kind')) ?></th>
                    <th><?= e(__('archive.doc_title')) ?></th>
                    <th><?= e(__('archive.party')) ?></th>
                    <th><?= e(__('archive.doc_date')) ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$hubRows): ?>
                <tr><td colspan="6" class="text-center text-muted py-4"><?= e($hubSearching ? __('archive.hub.empty') : __('archive.no_items')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($hubRows as $r): ?>
                <tr>
                    <td class="fw-semibold">
                        <?php if (trim((string) $r['archive_no']) !== ''): ?>
                            <a href="<?= e($r['view_url']) ?>" class="text-decoration-none" dir="ltr"><?= e($r['archive_no']) ?></a>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="archive-hub-kind kind-<?= e($r['kind']) ?>">
                            <i class="bi <?= e($r['icon']) ?>"></i>
                            <?= e($r['kind_label']) ?>
                        </span>
                    </td>
                    <td><?= e($r['title']) ?></td>
                    <td><?= e($r['party']) ?></td>
                    <td class="text-nowrap"><?= $r['date_html'] ?></td>
                    <td class="text-nowrap">
                        <a class="btn btn-sm btn-outline-primary" href="<?= e($r['view_url']) ?>"><?= e(__('action.view_archive')) ?></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
