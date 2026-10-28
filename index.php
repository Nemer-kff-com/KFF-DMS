<?php
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/correspondence.php';
$user = require_login();
$pageTitle = __('dash.title');
$dashPerPage = 10;
$data = correspondence_dashboard_data($user, [
    'per_page' => $dashPerPage,
    'action_in_page' => max(1, (int) ($_GET['action_in_page'] ?? 1)),
    'action_out_page' => max(1, (int) ($_GET['action_out_page'] ?? 1)),
    'action_internal_page' => max(1, (int) ($_GET['action_internal_page'] ?? 1)),
    'action_approval_page' => max(1, (int) ($_GET['action_approval_page'] ?? 1)),
    'recent_in_page' => max(1, (int) ($_GET['recent_in_page'] ?? 1)),
    'recent_out_page' => max(1, (int) ($_GET['recent_out_page'] ?? 1)),
    'recent_internal_page' => max(1, (int) ($_GET['recent_internal_page'] ?? 1)),
    'overdue_in_page' => max(1, (int) ($_GET['overdue_in_page'] ?? 1)),
    'overdue_out_page' => max(1, (int) ($_GET['overdue_out_page'] ?? 1)),
    'overdue_internal_page' => max(1, (int) ($_GET['overdue_internal_page'] ?? 1)),
]);

$todayIn = (int) $data['today_in'];
$todayOut = (int) $data['today_out'];
$todayInternal = (int) ($data['today_internal'] ?? 0);
$inProgressIn = (int) ($data['in_progress_in'] ?? 0);
$inProgressOut = (int) ($data['in_progress_out'] ?? 0);
$inProgressInternal = (int) ($data['in_progress_internal'] ?? 0);
$completed = (int) $data['completed'];
$overdueIn = (int) ($data['overdue_in'] ?? 0);
$overdueOut = (int) ($data['overdue_out'] ?? 0);
$overdueInternal = (int) ($data['overdue_internal'] ?? 0);
$todayDate = date('Y-m-d');
$createRevoked = static fn(string $perm): bool => dms_perm_ui($perm, $user) === 'deny';
$menuVisible = static function (string $key) use ($user): bool {
    return !function_exists('dms_sidebar_menu_key_visible') || dms_sidebar_menu_key_visible($key, $user);
};
$showIncoming = $menuVisible('incoming');
$showOutgoing = $menuVisible('outgoing');
$showInternal = $menuVisible('internal');
$showArchive = $menuVisible('archive')
    && function_exists('user_can_view_archive_module')
    && user_can_view_archive_module($user);
$heroCount = (int) $showIncoming + (int) $showOutgoing + (int) $showInternal + (int) $showArchive;
$heroCol = $heroCount >= 4 ? '3' : ($heroCount >= 3 ? '4' : ($heroCount === 2 ? '6' : '12'));
$corrCount = (int) $showIncoming + (int) $showOutgoing + (int) $showInternal;
$corrCol = $corrCount >= 3 ? '4' : ($corrCount === 2 ? '6' : '12');
$actionInTotal = (int) ($data['action_in_pagination']['total'] ?? 0);
$actionOutTotal = (int) ($data['action_out_pagination']['total'] ?? 0);
$actionInternalTotal = (int) ($data['action_internal_pagination']['total'] ?? 0);
$actionApprovalTotal = (int) ($data['action_approval_pagination']['total'] ?? 0);
$showApprovalQueue = function_exists('outgoing_user_may_see_approval_queue')
    && outgoing_user_may_see_approval_queue($user);
$actionTotal = ($showIncoming ? $actionInTotal : 0)
    + ($showOutgoing ? $actionOutTotal : 0)
    + ($showInternal ? $actionInternalTotal : 0);
// الموافقة قد تكون خارج نطاق الأقسام في الإجمالي الموحد أصلاً؛ لا نضاعف العدد إن ظهرت ضمن الصادر
if ($showApprovalQueue && $actionApprovalTotal > 0 && !$showOutgoing) {
    $actionTotal += $actionApprovalTotal;
}
$overdue = ($showIncoming ? $overdueIn : 0)
    + ($showOutgoing ? $overdueOut : 0)
    + ($showInternal ? $overdueInternal : 0);
$dashOtherLinks = [
    ['key' => 'circulars', 'href' => base_url('circulars/index.php'), 'icon' => 'bi-megaphone', 'label' => __('nav.circulars')],
    ['key' => 'referrals', 'href' => base_url('referrals/index.php'), 'icon' => 'bi-share', 'label' => __('nav.referrals')],
    ['key' => 'reports', 'href' => base_url('reports/index.php'), 'icon' => 'bi-bar-chart-line', 'label' => __('nav.reports')],
];
$dashOtherLinks = array_values(array_filter(
    $dashOtherLinks,
    static fn(array $link): bool => $menuVisible($link['key'])
));
$archiveDashSections = [];
if ($showArchive) {
    if (function_exists('ensure_archive_schema')) {
        ensure_archive_schema();
    }
    $archiveShort = [
        'scholarships' => __('dash.archive_scholarships'),
        'projects' => __('dash.archive_projects'),
        'finance' => __('dash.archive_finance'),
        'general' => __('dash.archive_general'),
    ];
    if (function_exists('archive_register_defs')) {
        foreach (archive_register_defs() as $kind => $def) {
            if (!user_can_archive_section($kind, 'view', $user)) {
                continue;
            }
            $full = __($def['label']);
            $archiveDashSections[] = [
                'kind' => (string) $kind,
                'label' => (string) ($archiveShort[$kind] ?? $full),
                'title' => $full,
                'icon' => (string) ($def['icon'] ?? 'bi-archive'),
                'count' => function_exists('archive_register_count') ? archive_register_count($kind) : 0,
                'href' => archive_register_url($kind, 'list'),
            ];
        }
    }
    if (function_exists('user_can_archive_section') && user_can_archive_section('general', 'view', $user)) {
        $full = __('archive.general');
        $archiveDashSections[] = [
            'kind' => 'general',
            'label' => (string) ($archiveShort['general'] ?? $full),
            'title' => $full,
            'icon' => 'bi-archive',
            'count' => function_exists('archive_list_page') ? (int) archive_list_page([], $user, 1)['total'] : 0,
            'href' => function_exists('archive_general_url') ? archive_general_url('list') : base_url('archive/general/index.php'),
        ];
    }
}

$flowStats = [
    [
        'label' => __('dash.completed'),
        'value' => $completed,
        'icon' => 'bi-check2-circle',
        'href' => base_url('correspondence/index.php?status_group=done'),
        'tone' => 'ok',
    ],
    [
        'label' => __('dash.action_today'),
        'value' => $actionTotal,
        'icon' => 'bi-list-check',
        'href' => base_url('correspondence/actions.php'),
        'tone' => $actionTotal > 0 ? 'warn' : 'ok',
    ],
    [
        'label' => __('dash.overdue'),
        'value' => $overdue,
        'icon' => 'bi-exclamation-triangle',
        'href' => base_url('correspondence/index.php?overdue=1'),
        'tone' => 'danger',
    ],
];

require __DIR__ . '/includes/header.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title"><i class="bi bi-house-door me-1"></i> <?= e(__('dash.title')) ?></h1>
        <p class="page-sub"><?= e(__('dash.welcome')) ?> <?= e(user_display_name($user)) ?> — <?= e(__('dash.summary')) ?></p>
    </div>
</div>

<?php if ($heroCount > 0): ?>
<div class="dash-section-title"><i class="bi bi-grid"></i> <?= e(__('dash.section_modules')) ?></div>
<div class="row g-3 mb-4 dash-hero-stats dash-type-row">
    <?php if ($showOutgoing): ?>
    <div class="col-lg-<?= e($heroCol) ?> dash-col-out">
        <?php
        $tone = 'outgoing';
        $iconClass = 'bi-mailbox';
        $sectionLabel = __('dash.section_outgoing');
        $createHref = base_url('outgoing/create.php');
        $createIcon = 'bi-send-plus';
        $createLabel = __('dash.new_outgoing');
        $listHref = base_url('outgoing/index.php');
        $listIcon = 'bi-mailbox';
        $listLabel = __('nav.outgoing');
        $todayValue = $todayOut;
        $todayLabel = __('dash.out_today');
        $todayHref = base_url('outgoing/index.php?from=' . $todayDate . '&to=' . $todayDate);
        $inProgressValue = $inProgressOut;
        $inProgressHref = base_url('outgoing/index.php?status_group=open');
        $canCreate = user_can('outgoing.create', $user);
        $createDisabled = $createRevoked('outgoing.create');
        $showListAction = true;
        require __DIR__ . '/includes/dash_type_card.php';
        ?>
    </div>
    <?php endif; ?>
    <?php if ($showIncoming): ?>
    <div class="col-lg-<?= e($heroCol) ?> dash-col-in">
        <?php
        $tone = 'incoming';
        $iconClass = 'bi-inbox';
        $sectionLabel = __('dash.section_incoming');
        $createHref = base_url('incoming/create.php');
        $createIcon = 'bi-envelope-open';
        $createLabel = __('dash.new_incoming');
        $listHref = base_url('incoming/index.php');
        $listIcon = 'bi-inbox';
        $listLabel = __('nav.incoming');
        $todayValue = $todayIn;
        $todayLabel = __('dash.in_today');
        $todayHref = base_url('incoming/index.php?from=' . $todayDate . '&to=' . $todayDate);
        $inProgressValue = $inProgressIn;
        $inProgressHref = base_url('incoming/index.php?status_group=open');
        $canCreate = user_can('incoming.create', $user);
        $createDisabled = $createRevoked('incoming.create');
        $showListAction = true;
        require __DIR__ . '/includes/dash_type_card.php';
        ?>
    </div>
    <?php endif; ?>
    <?php if ($showInternal): ?>
    <div class="col-lg-<?= e($heroCol) ?> dash-col-internal">
        <?php
        $tone = 'internal';
        $iconClass = 'bi-building-gear';
        $sectionLabel = __('dash.section_internal');
        $createHref = base_url('internal/create.php');
        $createIcon = 'bi-plus-circle';
        $createLabel = __('dash.new_internal');
        $listHref = base_url('internal/index.php');
        $listIcon = 'bi-building-gear';
        $listLabel = __('nav.internal');
        $todayValue = $todayInternal;
        $todayLabel = __('dash.internal_today');
        $todayHref = base_url('internal/index.php?from=' . $todayDate . '&to=' . $todayDate);
        $inProgressValue = $inProgressInternal;
        $inProgressHref = base_url('internal/index.php?status_group=open');
        $canCreate = user_can('internal.create', $user);
        $createDisabled = $createRevoked('internal.create');
        $showListAction = true;
        require __DIR__ . '/includes/dash_type_card.php';
        ?>
    </div>
    <?php endif; ?>
    <?php if ($showArchive): ?>
    <div class="col-lg-<?= e($heroCol) ?> dash-col-archive">
        <?php
        $tone = 'archive';
        $iconClass = 'bi-archive';
        $sectionLabel = __('dash.section_archive');
        $listHref = base_url('archive/index.php');
        $canCreate = false;
        $createDisabled = false;
        $showListAction = $archiveDashSections === [];
        $listLabel = __('dash.open_archive');
        $listIcon = 'bi-archive';
        $hideMetrics = true;
        $headCount = null;
        $headCountStatic = false;
        $todayValue = 0;
        $todayLabel = '';
        $todayHref = $listHref;
        $inProgressValue = 0;
        $inProgressHref = $listHref;
        $actionItems = [];
        foreach ($archiveDashSections as $sec) {
            $actionItems[] = [
                'kind' => (string) ($sec['kind'] ?? ''),
                'href' => (string) $sec['href'],
                'icon' => (string) $sec['icon'],
                'label' => (string) $sec['label'],
            ];
        }
        require __DIR__ . '/includes/dash_type_card.php';
        unset(
            $hideMetrics,
            $headCount,
            $headCountStatic,
            $sectionSub,
            $actionItems
        );
        ?>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="dash-followup-wrap mb-4">
    <div class="dash-section-title"><i class="bi bi-graph-up"></i> <?= e(__('dash.section_followup')) ?></div>
    <div class="dash-followup-card">
        <div class="stat-grid dash-flow-stats">
            <?php foreach ($flowStats as $s): ?>
                <a class="stat-card stat-card-link tone-<?= e($s['tone']) ?><?= (int) $s['value'] > 0 ? ' has-value' : '' ?>" href="<?= e($s['href']) ?>">
                    <div class="stat-card-top">
                        <span class="stat-icon"><i class="bi <?= e($s['icon']) ?>"></i></span>
                    </div>
                    <div class="label"><?= e($s['label']) ?></div>
                    <div class="value"><?= (int) $s['value'] ?></div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php if ($dashOtherLinks !== []): ?>
<div class="row g-3 mb-4 dash-hero-stats dash-other-row">
    <div class="col-12">
        <div class="dash-type-card tone-neutral dash-other-card">
            <div class="dash-type-card-row dash-type-card-row-main dash-type-card-row-single">
                <div class="dash-summary-head">
                    <div class="dash-hero-top">
                        <span class="dash-hero-icon"><i class="bi bi-link-45deg"></i></span>
                        <span class="dash-hero-label"><?= e(__('dash.other_actions')) ?></span>
                    </div>
                </div>
                <div class="dash-actions no-print dash-actions-other">
                    <?php foreach ($dashOtherLinks as $link): ?>
                    <a class="dash-action" href="<?= e($link['href']) ?>">
                        <span class="dash-action-icon"><i class="bi <?= e($link['icon']) ?>"></i></span>
                        <span class="dash-action-label"><?= e($link['label']) ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div data-dash-panels>
    <?php if ($showApprovalQueue): ?>
    <div class="dash-section-title"><i class="bi bi-shield-check"></i> <?= e(__('dash.action_approval')) ?></div>
    <div class="row g-3 mb-4">
        <div class="col-12">
            <?php
            $panelId = 'dash-action-approval';
            $title = __('dash.action_approval');
            $icon = 'bi-shield-check';
            $headerClass = '';
            $viewHref = base_url('correspondence/actions.php?kind=issue_approval');
            $rows = $data['action_approval_items'] ?? [];
            $pagination = $data['action_approval_pagination'] ?? [];
            $pageParam = 'action_approval_page';
            $mode = 'action';
            $emptyMsg = __('dash.action_empty');
            $badgeCount = (int) ($pagination['total'] ?? 0) > 0 ? (int) $pagination['total'] : null;
            require __DIR__ . '/includes/dash_panel.php';
            ?>
        </div>
    </div>
    <?php endif; ?>
    <?php if ($showOutgoing || $showIncoming || $showInternal): ?>
    <div class="dash-section-title"><i class="bi bi-list-check"></i> <?= e(__('dash.section_actions')) ?></div>
    <div class="row g-3 mb-4 dash-type-row">
        <?php if ($showOutgoing): ?>
        <div class="col-lg-<?= e($corrCol) ?> dash-col-out">
            <?php
            $panelId = 'dash-action-out';
            $title = __('dash.action_out');
            $icon = 'bi-send';
            $headerClass = '';
            $viewHref = base_url('correspondence/actions.php');
            $rows = $data['action_out_items'] ?? [];
            $pagination = $data['action_out_pagination'] ?? [];
            $pageParam = 'action_out_page';
            $mode = 'action';
            $emptyMsg = __('dash.action_empty');
            $badgeCount = (int) ($pagination['total'] ?? 0) > 0 ? (int) $pagination['total'] : null;
            require __DIR__ . '/includes/dash_panel.php';
            ?>
        </div>
        <?php endif; ?>
        <?php if ($showIncoming): ?>
        <div class="col-lg-<?= e($corrCol) ?> dash-col-in">
            <?php
            $panelId = 'dash-action-in';
            $title = __('dash.action_in');
            $icon = 'bi-inbox';
            $headerClass = '';
            $viewHref = base_url('correspondence/actions.php');
            $rows = $data['action_in_items'] ?? [];
            $pagination = $data['action_in_pagination'] ?? [];
            $pageParam = 'action_in_page';
            $mode = 'action';
            $emptyMsg = __('dash.action_empty');
            $badgeCount = (int) ($pagination['total'] ?? 0) > 0 ? (int) $pagination['total'] : null;
            require __DIR__ . '/includes/dash_panel.php';
            ?>
        </div>
        <?php endif; ?>
        <?php if ($showInternal): ?>
        <div class="col-lg-<?= e($corrCol) ?> dash-col-internal">
            <?php
            $panelId = 'dash-action-internal';
            $title = __('dash.action_internal');
            $icon = 'bi-building-gear';
            $headerClass = '';
            $viewHref = base_url('correspondence/actions.php');
            $rows = $data['action_internal_items'] ?? [];
            $pagination = $data['action_internal_pagination'] ?? [];
            $pageParam = 'action_internal_page';
            $mode = 'action';
            $emptyMsg = __('dash.action_empty');
            $badgeCount = (int) ($pagination['total'] ?? 0) > 0 ? (int) $pagination['total'] : null;
            require __DIR__ . '/includes/dash_panel.php';
            ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="dash-section-title"><i class="bi bi-clock-history"></i> <?= e(__('dash.section_recent')) ?></div>
    <div class="row g-3 mb-4 dash-type-row">
        <?php if ($showOutgoing): ?>
        <div class="col-lg-<?= e($corrCol) ?> dash-col-out">
            <?php
            $panelId = 'dash-recent-out';
            $title = __('dash.recent_out');
            $icon = 'bi-send';
            $headerClass = '';
            $viewHref = base_url('outgoing/index.php');
            $rows = $data['recent_out'] ?? [];
            $pagination = $data['recent_out_pagination'] ?? [];
            $pageParam = 'recent_out_page';
            $mode = 'recent';
            $emptyMsg = __('dash.no_data');
            $badgeCount = null;
            require __DIR__ . '/includes/dash_panel.php';
            ?>
        </div>
        <?php endif; ?>
        <?php if ($showIncoming): ?>
        <div class="col-lg-<?= e($corrCol) ?> dash-col-in">
            <?php
            $panelId = 'dash-recent-in';
            $title = __('dash.recent_in');
            $icon = 'bi-inbox';
            $headerClass = '';
            $viewHref = base_url('incoming/index.php');
            $rows = $data['recent_in'] ?? [];
            $pagination = $data['recent_in_pagination'] ?? [];
            $pageParam = 'recent_in_page';
            $mode = 'recent';
            $emptyMsg = __('dash.no_data');
            $badgeCount = null;
            require __DIR__ . '/includes/dash_panel.php';
            ?>
        </div>
        <?php endif; ?>
        <?php if ($showInternal): ?>
        <div class="col-lg-<?= e($corrCol) ?> dash-col-internal">
            <?php
            $panelId = 'dash-recent-internal';
            $title = __('dash.recent_internal');
            $icon = 'bi-building-gear';
            $headerClass = '';
            $viewHref = base_url('internal/index.php');
            $rows = $data['recent_internal'] ?? [];
            $pagination = $data['recent_internal_pagination'] ?? [];
            $pageParam = 'recent_internal_page';
            $mode = 'recent';
            $emptyMsg = __('dash.no_data');
            $badgeCount = null;
            require __DIR__ . '/includes/dash_panel.php';
            ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="dash-section-title text-danger"><i class="bi bi-exclamation-triangle"></i> <?= e(__('dash.section_overdue')) ?></div>
    <div class="row g-3 mb-4 dash-type-row">
        <?php if ($showOutgoing): ?>
        <div class="col-lg-<?= e($corrCol) ?> dash-col-out">
            <?php
            $panelId = 'dash-overdue-out';
            $title = __('dash.overdue_out');
            $icon = 'bi-send';
            $headerClass = 'text-danger';
            $viewHref = base_url('correspondence/index.php?overdue=1&type=outgoing');
            $rows = $data['overdue_out_rows'] ?? [];
            $pagination = $data['overdue_out_pagination'] ?? [];
            $pageParam = 'overdue_out_page';
            $mode = 'overdue';
            $emptyMsg = __('dash.no_overdue');
            $badgeCount = $overdueOut > 0 ? $overdueOut : null;
            require __DIR__ . '/includes/dash_panel.php';
            ?>
        </div>
        <?php endif; ?>
        <?php if ($showIncoming): ?>
        <div class="col-lg-<?= e($corrCol) ?> dash-col-in">
            <?php
            $panelId = 'dash-overdue-in';
            $title = __('dash.overdue_in');
            $icon = 'bi-inbox';
            $headerClass = 'text-danger';
            $viewHref = base_url('correspondence/index.php?overdue=1&type=incoming');
            $rows = $data['overdue_in_rows'] ?? [];
            $pagination = $data['overdue_in_pagination'] ?? [];
            $pageParam = 'overdue_in_page';
            $mode = 'overdue';
            $emptyMsg = __('dash.no_overdue');
            $badgeCount = $overdueIn > 0 ? $overdueIn : null;
            require __DIR__ . '/includes/dash_panel.php';
            ?>
        </div>
        <?php endif; ?>
        <?php if ($showInternal): ?>
        <div class="col-lg-<?= e($corrCol) ?> dash-col-internal">
            <?php
            $panelId = 'dash-overdue-internal';
            $title = __('dash.overdue_internal');
            $icon = 'bi-building-gear';
            $headerClass = 'text-danger';
            $viewHref = base_url('correspondence/index.php?overdue=1&type=internal');
            $rows = $data['overdue_internal_rows'] ?? [];
            $pagination = $data['overdue_internal_pagination'] ?? [];
            $pageParam = 'overdue_internal_page';
            $mode = 'overdue';
            $emptyMsg = __('dash.no_overdue');
            $badgeCount = $overdueInternal > 0 ? $overdueInternal : null;
            require __DIR__ . '/includes/dash_panel.php';
            ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
<?php
require __DIR__ . '/includes/footer.php';
