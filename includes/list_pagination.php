<?php
/** @var array $pagination */
/** @var string $pageParam */
if (empty($pagination) || (int) ($pagination['total'] ?? 0) <= 0) {
    return;
}
$pageParam = $pageParam ?? 'page';
?>
<div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2 py-2 px-3 bg-transparent border-top">
    <span class="small text-muted"><?= e(dms_pagination_summary($pagination)) ?></span>
    <?= dms_render_pagination($pagination, $pageParam) ?>
</div>
