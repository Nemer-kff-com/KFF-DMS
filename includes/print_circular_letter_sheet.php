<?php
/**
 * ورقة خطاب التعميم الإلكتروني.
 * يتطلب: $item ، $audienceLine ، واختيارياً $circularLetterLang.
 */
$circularLetterLang = circular_letter_lang($item, $circularLetterLang ?? null);
$isEn = $circularLetterLang === 'en';
$bodyHtml = circular_letter_body_html($item, $circularLetterLang);
$headerDate = trim((string) ($item['circular_date'] ?? ''));
$printHijriDate = $headerDate !== '' ? format_hijri_date($headerDate) : '';
$audienceLine = trim((string) ($audienceLine ?? ''));
$toClass = mb_strlen($audienceLine) > 48 ? 'circular-letter-to-many' : 'letter-to-name';
$rawLetter = function_exists('correspondence_letter_raw')
    ? correspondence_letter_raw(circular_as_letter_row($item), $circularLetterLang)
    : '';
$fontClass = function_exists('letter_font_css_class')
    ? letter_font_css_class(letter_body_font_code($rawLetter))
    : '';
?>
<div class="print-sheet letter-sheet<?= $isEn ? ' is-ltr' : '' ?>" lang="<?= $isEn ? 'en' : 'ar' ?>" dir="<?= $isEn ? 'ltr' : 'rtl' ?>">
    <div class="print-header">
        <div class="print-qr-block">
            <div class="print-doc-ref"><?= e(format_ref_caption('circular', $item['ref_number'] ?? '')) ?></div>
            <?php if ($printHijriDate !== ''): ?>
                <div class="print-doc-date-hijri">
                    <span><?= e($printHijriDate) ?></span>
                    <span class="print-doc-date-mark">هـ</span>
                </div>
            <?php endif; ?>
            <?php if ($headerDate !== ''): ?>
                <div class="print-doc-date-value">
                    <span><?= e(date('Y/m/d', strtotime($headerDate))) ?></span>
                    <span class="print-doc-date-mark">م</span>
                </div>
            <?php endif; ?>
        </div>
        <?php render_print_logo('print'); ?>
    </div>
    <div class="letter-content <?= e($fontClass) ?>">
        <h2 class="letter-subject"><?= e((string) ($item['subject'] ?? '')) ?></h2>
        <?php if ($audienceLine !== ''): ?>
            <div class="letter-to">
                <div class="letter-to-label"><?= e(__('circular.letter_to')) ?></div>
                <div class="<?= e($toClass) ?>"><?= e($audienceLine) ?></div>
            </div>
        <?php endif; ?>
        <?php if ($bodyHtml !== ''): ?>
            <div class="letter-body"><?= $bodyHtml ?></div>
        <?php endif; ?>
    </div>
    <?php render_print_page_footer(circular_as_letter_row($item), ['show_users' => false]); ?>
</div>
