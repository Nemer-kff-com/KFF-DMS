<?php
/**
 * لوحة «للاطلاع» على صفحة العرض: المستلمون وحالة اطلاعهم، وزر «اطّلعت» للمستلم.
 * يتطلب: $corr, $user، واختيارياً $ccViewerOnly
 */
if (!function_exists('corr_cc_list') || !in_array((string) ($corr['type'] ?? ''), corr_cc_types(), true)) {
    return;
}
$ccRows = corr_cc_list((int) $corr['id']);
$ccMine = corr_cc_user_row((int) $corr['id'], (int) ($user['id'] ?? 0));
if ($ccRows === [] && !$ccMine) {
    return;
}
$ccShowList = empty($ccViewerOnly) && $ccRows !== [];
$ccSeenCount = count(array_filter($ccRows, static fn (array $r): bool => $r['seen_at'] !== null));
?>
<div class="card-panel mb-3 no-print" id="corr-cc-panel">
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
        <span><i class="bi bi-eye me-1"></i><?= e(__('cc.panel_title')) ?></span>
        <?php if ($ccShowList): ?>
            <span class="badge text-bg-light text-dark" title="<?= e(__('cc.seen_count_hint')) ?>"><?= (int) $ccSeenCount ?> / <?= count($ccRows) ?></span>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if ($ccMine): ?>
            <div class="alert <?= empty($ccMine['seen_at']) ? 'alert-info' : 'alert-success' ?> py-2 mb-<?= $ccShowList ? '3' : '0' ?> d-flex flex-wrap align-items-center justify-content-between gap-2">
                <?php if (empty($ccMine['seen_at'])): ?>
                    <span><i class="bi bi-info-circle me-1"></i><?= e(__('cc.you_received')) ?></span>
                    <form method="post" action="<?= e(base_url('cc/ack.php')) ?>" class="m-0">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $corr['id'] ?>">
                        <button type="submit" class="btn btn-success btn-sm" title="<?= e(__('cc.ack_hint')) ?>">
                            <i class="bi bi-check2-circle me-1"></i><?= e(__('cc.ack')) ?>
                        </button>
                    </form>
                <?php else: ?>
                    <span><i class="bi bi-check2-circle me-1"></i><?= e(__('cc.you_acked')) ?> — <span dir="ltr"><?= e(format_datetime((string) $ccMine['seen_at'])) ?></span></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($ccShowList): ?>
            <ul class="cc-status-list">
                <?php foreach ($ccRows as $r): ?>
                    <li>
                        <span title="<?= e($r['dept']) ?>"><i class="bi bi-person me-1 text-muted"></i><?= e($r['name']) ?></span>
                        <?php if ($r['status'] === 'pending'): ?>
                            <span class="badge text-bg-secondary" title="<?= e(__('cc.status_pending_hint')) ?>"><?= e(__('cc.status_pending')) ?></span>
                        <?php elseif ($r['seen_at'] === null): ?>
                            <span class="badge text-bg-warning" title="<?= e(__('cc.released_at') . ': ' . format_datetime((string) $r['released_at'])) ?>"><?= e(__('cc.status_unseen')) ?></span>
                        <?php else: ?>
                            <span class="badge text-bg-success" title="<?= e(format_datetime((string) $r['seen_at'])) ?>"><?= e(__('cc.status_seen')) ?></span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>
