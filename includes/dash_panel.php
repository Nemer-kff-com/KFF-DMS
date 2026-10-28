<?php
/**
 * لوحة فرعية في الرئيسية (صادر / وارد / داخلي)
 * @var string $panelId
 * @var string $title
 * @var string $icon
 * @var string $headerClass
 * @var string $viewHref
 * @var list<array<string,mixed>> $rows
 * @var array<string,mixed> $pagination
 * @var string $pageParam
 * @var string $mode recent|overdue|action
 * @var string $emptyMsg
 * @var int|null $badgeCount
 */
$headerClass = $headerClass ?? '';
$badgeCount = isset($badgeCount) ? (int) $badgeCount : null;
$colspan = 4;
$partyColLabel = $partyColLabel ?? '';
if ($partyColLabel === '') {
    $panelKey = (string) ($panelId ?? '');
    if (str_contains($panelKey, 'internal')) {
        $partyColLabel = __('corr.party_to');
    } elseif (preg_match('/(?:^|-)in(?:$|-)/', $panelKey) === 1) {
        $partyColLabel = __('corr.party_from');
    } else {
        // الصادر وطابور الموافقة: الطرف الظاهر هو المستلم
        $partyColLabel = __('corr.party_to');
    }
}
?>
<div class="card-panel h-100 mb-0" id="<?= e($panelId) ?>">
    <div class="card-header d-flex justify-content-between align-items-center <?= e($headerClass) ?>">
        <span><i class="bi <?= e($icon) ?> me-1"></i> <?= e($title) ?></span>
        <span class="d-flex align-items-center gap-2">
            <?php if ($badgeCount !== null && $badgeCount > 0): ?>
                <span class="badge text-bg-danger"><?= $badgeCount ?></span>
            <?php endif; ?>
            <a class="small" href="<?= e($viewHref) ?>"><?= e(__('action.view_all')) ?> <i class="bi bi-arrow-left-short"></i></a>
        </span>
    </div>
    <div class="dash-panel-body">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0 table-hover dash-panel-table">
                <thead>
                    <tr>
                        <th class="dash-th-ref"><?= e(__('corr.number')) ?></th>
                        <th class="dash-th-party"><?= e($partyColLabel) ?></th>
                        <th class="dash-th-subject"><?= e(__('corr.subject')) ?></th>
                        <th class="dash-th-meta">
                            <?php if ($mode === 'action'): ?>
                                <?= e(__('actionq.kind')) ?>
                            <?php elseif ($mode === 'overdue'): ?>
                                <?= e(__('corr.reply_due')) ?>
                            <?php else: ?>
                                <?= e(__('corr.status')) ?>
                            <?php endif; ?>
                        </th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="<?= (int) $colspan ?>" class="text-muted text-center py-4"><?= e($emptyMsg) ?></td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $r): ?>
                    <?php
                    $viewUrl = correspondence_view_url($r);
                    $partyParts = function_exists('correspondence_archived_party')
                        ? correspondence_archived_party($r)
                        : [];
                    $partyPrefix = trim((string) ($partyParts['prefix'] ?? ''));
                    $partyName = trim((string) ($partyParts['person'] ?? ''));
                    if ($partyPrefix === '' && $partyName === '') {
                        $partyName = correspondence_party_display_name($r);
                        if ($partyName === '') {
                            $partyName = trim((string) ($r['party_name'] ?? ''));
                        }
                    }
                    $partyOffice = '';
                    if ($partyPrefix === '' && $partyName === '' && ($r['type'] ?? '') === 'internal') {
                        $partyName = correspondence_department_officer_label($r);
                        $partyOffice = correspondence_officer_subtitle($r);
                    }
                    $partyFull = trim($partyPrefix . ($partyPrefix !== '' && $partyName !== '' ? ' ' : '') . $partyName);
                    $subjectFull = trim((string) ($r['subject'] ?? ''));
                    $rowClass = 'dash-row-link';
                    if ($mode === 'overdue' || $mode === 'action') {
                        $rowClass .= is_overdue($r['reply_due'] ?? null, $r['status'] ?? '') ? ' table-danger' : '';
                    }
                    ?>
                    <tr class="<?= e(trim($rowClass)) ?>" onclick="location.href='<?= e($viewUrl) ?>'">
                        <td class="dash-td-ref">
                            <a href="<?= e($viewUrl) ?>" onclick="event.stopPropagation()"><?= e($r['ref_number']) ?></a>
                        </td>
                        <td class="dash-td-party">
                            <?php if ($partyFull !== ''): ?>
                                <span class="dash-party-text" title="<?= e($partyFull) ?>">
                                    <?php if ($partyPrefix !== ''): ?>
                                        <span class="dash-party-prefix"><?= e($partyPrefix) ?></span>
                                    <?php endif; ?>
                                    <?php if ($partyName !== ''): ?>
                                        <span class="dash-party-name"><?= e($partyName) ?></span>
                                    <?php endif; ?>
                                    <?php if ($partyOffice !== ''): ?>
                                        <span class="corr-officer-pick-office"><?= e($partyOffice) ?></span>
                                    <?php endif; ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="dash-td-subject">
                            <?php if ($subjectFull !== ''): ?>
                                <span class="dash-subject-text" title="<?= e($subjectFull) ?>"><?= e($subjectFull) ?></span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="dash-td-meta">
                            <?php if ($mode === 'action'): ?>
                                <span class="badge text-bg-light text-dark border text-wrap"><?= e(__('actionq.' . ($r['action_kind'] ?? ''))) ?></span>
                            <?php elseif ($mode === 'overdue'): ?>
                                <span class="text-danger fw-semibold text-nowrap"><i class="bi bi-calendar-x"></i> <?= e(format_date($r['reply_due'] ?? null)) ?></span>
                            <?php else: ?>
                                <?= status_badge($r['status'] ?? 'new', $r['cancel_kind'] ?? null) ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php
unset($partyColLabel);
?>
