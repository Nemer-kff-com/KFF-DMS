<?php
/**
 * ورقة طباعة خطاب صادر (نص الخطاب مع الاستبدال الفردي).
 * يتطلب: $corr مصفوفة المعاملة، ويفضّل $qrId فريد.
 */
$qrId = (string) ($qrId ?? ('qr-' . (int) ($corr['id'] ?? 0)));
$parts = print_direction_parts($corr);
$toPart = $parts['to'];
$lang = correspondence_letter_channel_lang($corr);
$isEn = $lang === 'en';
$body = correspondence_letter_text($corr, $lang);
?>
<div class="print-sheet letter-sheet<?= $isEn ? ' is-ltr' : '' ?>" lang="<?= $isEn ? 'en' : 'ar' ?>" dir="<?= $isEn ? 'ltr' : 'rtl' ?>">
    <div class="print-header">
        <div class="print-qr-block">
            <?php render_print_qr_box($qrId, $corr, 'letter-qr'); ?>
            <div class="print-doc-ref"><?= e(format_ref_caption((string) ($corr['type'] ?? ''), $corr['ref_number'] ?? '')) ?></div>
            <?php if (!empty($corr['corr_date'])): ?>
                <?php $printHijriDate = format_hijri_date((string) $corr['corr_date']); ?>
                <?php if ($printHijriDate !== ''): ?>
                <div class="print-doc-date-hijri">
                    <span><?= e($printHijriDate) ?></span>
                    <span class="print-doc-date-mark">هـ</span>
                </div>
                <?php endif; ?>
                <div class="print-doc-date-value">
                    <span><?= e(date('Y/m/d', strtotime((string) $corr['corr_date']))) ?></span>
                    <span class="print-doc-date-mark">م</span>
                </div>
            <?php endif; ?>
        </div>
        <?php render_print_logo('print'); ?>
    </div>

    <div class="letter-content <?= e(letter_font_css_class(letter_body_font_code($body))) ?>">
    <h2 class="letter-subject"><?= e($corr['subject']) ?></h2>
    <?php if (print_direction_part_has_content($toPart)): ?>
    <div class="letter-to">
        <?php render_print_party_stack($toPart); ?>
    </div>
    <?php endif; ?>
    <?php if ($body !== ''): ?>
        <div class="letter-body"><?= render_letter_body_html($body, false, $corr) ?></div>
    <?php endif; ?>
    <?php render_letter_rsvp_notice($corr, $lang); ?>
    </div>
    <?php render_print_page_footer($corr, ['show_users' => false]); ?>
</div>
