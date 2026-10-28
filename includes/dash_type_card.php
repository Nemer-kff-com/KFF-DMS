<?php
/**
 * بطاقة موحّدة: عنوان + إجراءات (صف 1) + ملخص (صف 2)
 *
 * @var string $tone incoming|outgoing
 * @var string $iconClass bi icon on title
 * @var string $sectionLabel
 * @var string $createHref
 * @var string $createIcon
 * @var string $createLabel
 * @var string $listHref
 * @var string $listIcon
 * @var string $listLabel
 * @var int $todayValue
 * @var string $todayLabel
 * @var string $todayHref
 * @var int $inProgressValue
 * @var string $inProgressHref
 * @var bool $createDisabled زر الإنشاء يظهر معطّلاً بدل إخفائه حين لا يملك المستخدم الصلاحية
 */
$todayHasValue = (int) $todayValue > 0;
$inProgressHasValue = (int) $inProgressValue > 0;
$canCreate = !isset($canCreate) ? true : (bool) $canCreate;
$showListAction = !isset($showListAction) ? true : (bool) $showListAction;
$hideMetrics = !empty($hideMetrics);
$sectionSub = trim((string) ($sectionSub ?? ''));
$headCount = isset($headCount) ? (int) $headCount : null;
$headCountStatic = !empty($headCountStatic) || !empty($headCountBelow);
$inProgressLabel = $inProgressLabel ?? __('dash.in_progress');
$inProgressIcon = $inProgressIcon ?? 'bi-hourglass-split';
$todayIcon = $todayIcon ?? 'bi-calendar-day';
$todayHint = $todayHint ?? __('dash.hint_today_list');
$inProgressHint = $inProgressHint ?? __('dash.hint_in_progress');
$inProgressWarn = !isset($inProgressWarn) ? true : (bool) $inProgressWarn;
$actionItems = isset($actionItems) && is_array($actionItems) ? $actionItems : [];
?>
<div class="dash-type-card tone-<?= e($tone) ?><?= $hideMetrics ? ' is-simple' : '' ?>">
    <div class="dash-type-card-row dash-type-card-row-main">
        <div class="dash-summary-head<?= $headCountStatic ? ' has-count-static' : '' ?>">
            <div class="dash-hero-top">
                <a class="dash-summary-head-link" href="<?= e($listHref) ?>" title="<?= e(__('dash.hint_section_list')) ?>">
                    <span class="dash-hero-icon"><i class="bi <?= e($iconClass) ?>"></i></span>
                    <span class="dash-hero-label"><?= e($sectionLabel) ?></span>
                </a>
                <?php if ($headCount !== null && $headCountStatic): ?>
                    <span class="dash-hero-count is-static" dir="ltr"><?= (int) $headCount ?></span>
                <?php elseif ($headCount !== null): ?>
                    <span class="dash-hero-count" dir="ltr"><?= (int) $headCount ?></span>
                <?php endif; ?>
            </div>
            <?php if ($sectionSub !== ''): ?>
                <div class="dash-hero-sub"><?= e($sectionSub) ?></div>
            <?php endif; ?>
        </div>
        <div class="dash-actions no-print">
            <?php if ($actionItems !== []): ?>
            <?php foreach ($actionItems as $act): ?>
            <?php
            $actKind = preg_replace('/[^a-z]/', '', strtolower((string) ($act['kind'] ?? ''))) ?? '';
            $actClass = 'dash-action' . ($actKind !== '' ? ' kind-' . $actKind : '');
            ?>
            <a class="<?= e($actClass) ?>" href="<?= e((string) ($act['href'] ?? '')) ?>">
                <span class="dash-action-icon"><i class="bi <?= e((string) ($act['icon'] ?? 'bi-folder')) ?>"></i></span>
                <span class="dash-action-label"><?= e((string) ($act['label'] ?? '')) ?></span>
            </a>
            <?php endforeach; ?>
            <?php else: ?>
            <?php if ($canCreate): ?>
            <a class="dash-action is-primary" href="<?= e($createHref) ?>">
                <span class="dash-action-icon"><i class="bi <?= e($createIcon) ?>"></i></span>
                <span class="dash-action-label"><?= e($createLabel) ?></span>
            </a>
            <?php elseif (!empty($createDisabled)): ?>
            <span class="dash-action is-disabled" role="link" aria-disabled="true" tabindex="0"
                  title="<?= e(__('perm.denied_tip')) ?>" data-bs-toggle="tooltip">
                <span class="dash-action-icon"><i class="bi <?= e($createIcon) ?>"></i></span>
                <span class="dash-action-label"><?= e($createLabel) ?></span>
            </span>
            <?php endif; ?>
            <?php if ($showListAction): ?>
            <a class="dash-action dash-action-list" href="<?= e($listHref) ?>">
                <span class="dash-action-icon"><i class="bi <?= e($listIcon) ?>"></i></span>
                <span class="dash-action-label"><?= e($listLabel) ?></span>
            </a>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php if (!$hideMetrics): ?>
    <div class="dash-type-card-row dash-summary-metrics">
        <a class="dash-summary-card dash-summary-card-today<?= $todayHasValue ? ' has-value' : ' is-zero' ?>"
           href="<?= e($todayHref) ?>"
           title="<?= e($todayHint) ?>">
            <span class="dash-summary-card-icon"><i class="bi <?= e($todayIcon) ?>"></i></span>
            <span class="dash-summary-card-value"><?= $todayHasValue ? (int) $todayValue : '—' ?></span>
            <span class="dash-summary-card-label"><?= e($todayLabel) ?></span>
        </a>
        <a class="dash-summary-card<?= $inProgressHasValue ? ' has-value' . ($inProgressWarn ? ' is-warn' : '') : ' is-zero' ?>"
           href="<?= e($inProgressHref) ?>"
           title="<?= e($inProgressHint) ?>">
            <span class="dash-summary-card-icon"><i class="bi <?= e($inProgressIcon) ?>"></i></span>
            <span class="dash-summary-card-value"><?= (int) $inProgressValue ?></span>
            <span class="dash-summary-card-label"><?= e($inProgressLabel) ?></span>
        </a>
    </div>
    <?php endif; ?>
</div>
