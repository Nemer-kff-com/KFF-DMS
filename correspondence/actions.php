<?php

require_once __DIR__ . '/../config/auth.php';

require_once __DIR__ . '/../config/correspondence.php';

$user = require_login();

$pageTitle = __('dash.action_today');



$counts = correspondence_action_counts($user);

$kindFilter = trim((string) ($_GET['kind'] ?? ''));

if ($kindFilter !== '' && !in_array($kindFilter, correspondence_action_kinds($user), true)) {

    $kindFilter = '';

}



$groups = [];

$kinds = correspondence_action_kinds($user);

if ($kindFilter !== '') {

    $kinds = [$kindFilter];

}



foreach ($kinds as $kind) {

    $pageParam = $kindFilter !== '' ? 'page' : ('page_' . $kind);

    $list = correspondence_list_page('all', ['action' => $kind], $user, dms_request_page($pageParam));

    $groups[$kind] = [

        'rows' => $list['rows'],

        'pagination' => $list['pagination'],

        'total' => $list['total'],

        'page_param' => $pageParam,

    ];

}



$pageScripts = dms_list_page_scripts();



require __DIR__ . '/../includes/header.php';

?>

<div class="page-header">

    <div>

        <h1 class="page-title"><?= e(__('dash.action_today')) ?></h1>

        <p class="page-sub"><?= e(__('actionq.subtitle')) ?> — <?= (int) ($counts['total'] ?? 0) ?></p>

    </div>

</div>



<div class="d-flex flex-wrap gap-2 mb-3">

    <a class="btn btn-sm <?= $kindFilter === '' ? 'btn-kff' : 'btn-outline-secondary' ?>" href="<?= e(base_url('correspondence/actions.php')) ?>">

        <?= e(__('common.all')) ?> (<?= (int) ($counts['total'] ?? 0) ?>)

    </a>

    <?php foreach (correspondence_action_kinds($user) as $kind): ?>

        <a class="btn btn-sm <?= $kindFilter === $kind ? 'btn-kff' : 'btn-outline-secondary' ?>"

           href="<?= e(base_url('correspondence/actions.php?kind=' . urlencode($kind))) ?>">

            <?= e(__('actionq.' . $kind)) ?> (<?= (int) ($counts[$kind] ?? 0) ?>)

        </a>

    <?php endforeach; ?>

</div>



<div data-list-panels>

<?php foreach ($groups as $kind => $group): ?>

<?php

    $rows = $group['rows'];

    $pagination = $group['pagination'];

    $pageParam = $group['page_param'];

?>

<div class="card-panel mb-3" id="list-action-<?= e($kind) ?>" data-list-panel>

    <div class="card-header d-flex justify-content-between align-items-center">

        <span><?= e(__('actionq.' . $kind)) ?></span>

        <span class="badge text-bg-light text-dark"><?= (int) ($group['total'] ?? 0) ?></span>

    </div>

    <div class="list-panel-body">

    <div class="table-responsive">

        <table class="table table-sm align-middle mb-0 table-hover">

            <thead>

                <tr>

                    <th><?= e(__('corr.number')) ?></th>

                    <th><?= e(__('corr.type')) ?></th>

                    <th><?= e(__('corr.subject')) ?></th>

                    <th><?= e(__('corr.party')) ?></th>

                    <th><?= e(__('corr.date')) ?></th>

                    <th><?= e(__('corr.reply_due')) ?></th>

                    <th><?= e(__('corr.status')) ?></th>

                    <th></th>

                </tr>

            </thead>

            <tbody>

            <?php if (!$rows): ?>

                <tr><td colspan="8" class="text-center text-muted py-3"><?= e(__('corr.no_items')) ?></td></tr>

            <?php endif; ?>

            <?php foreach ($rows as $r): ?>

                <tr class="<?= is_overdue($r['reply_due'] ?? null, $r['status'] ?? '') ? 'table-danger' : '' ?>">

                    <td class="fw-semibold"><?= e(function_exists('correspondence_ref_display') ? correspondence_ref_display($r) : $r['ref_number']) ?></td>

                    <td>
                        <span class="badge <?= ($r['type'] ?? '') === 'outgoing' ? 'text-bg-secondary' : 'text-bg-primary' ?>">
                            <?= e(type_label($r['type'] ?? '')) ?>
                        </span>
                    </td>

                    <td><?= e($r['subject']) ?></td>

                    <td><?= e(correspondence_party_display_name($r)) ?></td>

                    <td><?= e(format_date($r['corr_date'] ?? null)) ?></td>

                    <td><?= e(format_date($r['reply_due'] ?? null)) ?></td>

                    <td><?= status_badge($r['status'] ?? 'new', $r['cancel_kind'] ?? null) ?></td>

                    <td><a class="btn btn-sm btn-outline-primary" href="<?= e(correspondence_view_url($r)) ?>"><?= e(__('action.view')) ?></a></td>

                </tr>

            <?php endforeach; ?>

            </tbody>

        </table>

    </div>

    <?php require __DIR__ . '/../includes/list_pagination.php'; ?>

    </div>

</div>

<?php endforeach; ?>

</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

