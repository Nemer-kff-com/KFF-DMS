<?php
$activity = $activity ?? [];
$activityCollapsed = !empty($activityCollapsed);
$activityCollapseId = (string) ($activityCollapseId ?? 'corr-activity-body');
?>
<div class="card-panel mb-3">
    <?php if ($activityCollapsed): ?>
    <button type="button" class="card-header corr-activity-toggle" data-bs-toggle="collapse"
            data-bs-target="#<?= e($activityCollapseId) ?>" aria-expanded="false" aria-controls="<?= e($activityCollapseId) ?>">
        <span><i class="bi bi-journal-text me-1"></i><?= e($activityTitle ?? __('corr.activity_title')) ?></span>
        <i class="bi bi-chevron-down corr-activity-chevron" aria-hidden="true"></i>
    </button>
    <?php else: ?>
    <div class="card-header"><i class="bi bi-journal-text me-1"></i><?= e($activityTitle ?? __('corr.activity_title')) ?></div>
    <?php endif; ?>
    <div<?= $activityCollapsed ? ' class="collapse" id="' . e($activityCollapseId) . '"' : '' ?>>
    <div class="card-body p-3">
        <?php if (!$activity): ?>
            <p class="text-muted small mb-0"><?= e(__('corr.activity_empty')) ?></p>
        <?php else: ?>
            <ul class="list-unstyled corr-activity mb-0">
                <?php foreach ($activity as $ev): ?>
                    <li class="corr-activity-item">
                        <div class="d-flex justify-content-between gap-2">
                            <strong class="small"><?= e(activity_action_label((string) ($ev['action'] ?? ''))) ?></strong>
                            <span class="small text-muted"><?= format_datetime_html($ev['created_at'] ?? null) ?></span>
                        </div>
                        <div class="small text-muted"><?= e(user_display_name($ev, 'user_name', 'user_name_en') ?: __('common.none')) ?></div>
                        <?php
                        $activityDetails = activity_details_for_display(
                            (string) ($ev['action'] ?? ''),
                            (string) ($ev['details'] ?? '')
                        );
                        ?>
                        <?php if ($activityDetails !== ''): ?>
                            <div class="small"><?= e($activityDetails) ?></div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
    </div>
</div>
