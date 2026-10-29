<?php
/**
 * جدول معاملات مرتبطة أو نتائج موضوع.
 * يتطلب: $relatedRows, اختياري $relatedHighlightId
 */
$relatedRows = is_array($relatedRows ?? null) ? $relatedRows : [];
$relatedHighlightId = (int) ($relatedHighlightId ?? 0);
?>
<div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead>
            <tr>
                <th class="corr-meta-num"><?= e(__('corr.number')) ?></th>
                <th><?= e(__('corr.subject')) ?></th>
                <th><?= e(__('corr.party_from')) ?></th>
                <th><?= e(__('corr.party_to')) ?></th>
                <th><?= e(__('corr.date')) ?></th>
                <th><?= e(__('corr.status')) ?></th>
                <th><?= e(__('corr.type')) ?></th>
                <th class="text-center"><?= e(__('common.actions')) ?></th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$relatedRows): ?>
            <tr><td colspan="8" class="text-center text-muted py-3"><?= e(__('corr.related_empty')) ?></td></tr>
        <?php endif; ?>
        <?php foreach ($relatedRows as $rr): ?>
            <?php
            $rid = (int) ($rr['id'] ?? 0);
            $rtype = (string) ($rr['type'] ?? '');
            $isHi = $relatedHighlightId > 0 && $rid === $relatedHighlightId;
            ?>
            <tr class="<?= $isHi ? 'table-primary' : '' ?>">
                <td class="fw-semibold corr-meta-num" dir="ltr">
                    <?= e(function_exists('correspondence_ref_display') ? correspondence_ref_display($rr) : ($rr['ref_number'] ?? '')) ?>
                </td>
                <td><?= e((string) ($rr['subject'] ?? '')) ?></td>
                <td>
                    <?php
                    if ($rtype === 'incoming') {
                        $fromParts = correspondence_archived_party($rr);
                        if (trim((string) ($fromParts['person'] ?? '')) === '') {
                            $fromParts['person'] = trim((string) ($rr['party_name'] ?? ''));
                        }
                        render_corr_list_person($fromParts);
                    } else {
                        render_correspondence_department_officer($rr, 'issuing');
                    }
                    ?>
                </td>
                <td>
                    <?php
                    if ($rtype === 'incoming') {
                        render_correspondence_department_officer($rr, 'receiving');
                    } elseif ($rtype === 'internal') {
                        $toParts = correspondence_archived_party($rr);
                        render_corr_list_person($toParts);
                        render_officer_office_line((string) ($toParts['person'] ?? ''), (string) ($toParts['org'] ?? ''));
                    } else {
                        $toParts = correspondence_archived_party($rr);
                        if (trim((string) ($toParts['person'] ?? '')) === '') {
                            $toParts['person'] = trim((string) ($rr['party_name'] ?? ''));
                        }
                        render_corr_list_person($toParts);
                    }
                    ?>
                </td>
                <td><?php render_corr_date_cell($rr['corr_date'] ?? null); ?></td>
                <td><?= status_badge((string) ($rr['status'] ?? 'new'), $rr['cancel_kind'] ?? null) ?></td>
                <td>
                    <span class="badge <?= e(correspondence_type_badge_class($rtype)) ?>"><?= e(type_label($rtype)) ?></span>
                </td>
                <td class="text-center">
                    <?php if ($rid > 0): ?>
                        <div class="d-inline-flex align-items-center justify-content-center gap-1">
                            <a class="btn btn-sm btn-outline-primary" href="<?= e(correspondence_view_url($rr)) ?>" title="<?= e(__('action.view')) ?>">
                                <?= e(__('action.view')) ?>
                            </a>
                            <a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('correspondence/related.php?id=' . $rid)) ?>" title="<?= e(__('corr.related_open')) ?>">
                                <?= e(__('corr.related_open')) ?>
                            </a>
                        </div>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
