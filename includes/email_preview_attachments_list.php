<?php
/**
 * قائمة مرفقات معاينة البريد (روابط تفتح عند النقر — بدون iframe تلقائي).
 *
 * قبل التضمين:
 * - $emailPreviewAttachments list<array{name?:string,url?:string,kind?:string}>
 * - $emailPreviewOfficialUrl string (اختياري) احتياط لرابط الخطاب الرسمي
 */
$emailPreviewAttachments = is_array($emailPreviewAttachments ?? null) ? $emailPreviewAttachments : [];
$emailPreviewOfficialUrl = trim((string) ($emailPreviewOfficialUrl ?? ''));
if ($emailPreviewAttachments === []) {
    return;
}
?>
<div class="mt-4">
    <div class="small text-muted mb-2"><?= e(__('send.email_preview_attachments')) ?></div>
    <ul class="list-unstyled mb-0 email-preview-attachments">
        <?php foreach ($emailPreviewAttachments as $att): ?>
            <?php
            if (!is_array($att) || trim((string) ($att['name'] ?? '')) === '') {
                continue;
            }
            $attName = (string) $att['name'];
            $attUrl = trim((string) ($att['url'] ?? ''));
            if ($attUrl === '' && ($att['kind'] ?? '') === 'official_letter') {
                $attUrl = $emailPreviewOfficialUrl;
            }
            $isOfficial = ($att['kind'] ?? '') === 'official_letter';
            $icon = $isOfficial || str_ends_with(strtolower($attName), '.pdf')
                ? 'bi-file-earmark-pdf'
                : 'bi-paperclip';
            ?>
            <li class="email-preview-attachment-item">
                <?php if ($attUrl !== ''): ?>
                    <a class="email-preview-attachment-link" href="<?= e($attUrl) ?>" target="_blank" rel="noopener">
                        <i class="bi <?= $icon ?>" aria-hidden="true"></i>
                        <span dir="ltr"><?= e($attName) ?></span>
                    </a>
                <?php else: ?>
                    <span class="email-preview-attachment-link email-preview-attachment-link--muted">
                        <i class="bi <?= $icon ?>" aria-hidden="true"></i>
                        <span dir="ltr"><?= e($attName) ?></span>
                    </span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
<?php
unset($emailPreviewAttachments, $emailPreviewOfficialUrl);
?>
