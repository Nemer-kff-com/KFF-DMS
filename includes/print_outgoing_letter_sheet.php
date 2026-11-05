<?php
/**
 * ورقة طباعة خطاب صادر (نص الخطاب مع الاستبدال الفردي).
 * يتطلب: $corr مصفوفة المعاملة، ويفضّل $qrId فريد.
 */
$corrId = (int) ($corr['id'] ?? 0);
$qrId = (string) ($qrId ?? ('letter-thermal-qr-' . $corrId));
$thermalRootId = 'letter-thermal-' . $corrId;
$lang = correspondence_letter_channel_lang($corr);
$isEn = $lang === 'en';
$body = correspondence_letter_text($corr, $lang);
$thermalQrUri = function_exists('dms_corr_qr_data_uri') ? dms_corr_qr_data_uri($corr, 0, false, true) : '';
if (!isset($letterThermalQrMap) || !is_array($letterThermalQrMap)) {
    $letterThermalQrMap = [];
}
if ($thermalQrUri !== '') {
    $letterThermalQrMap[$qrId] = $thermalQrUri;
}
?>
<div class="print-sheet letter-sheet<?= $isEn ? ' is-ltr' : '' ?>" lang="<?= $isEn ? 'en' : 'ar' ?>" dir="<?= $isEn ? 'ltr' : 'rtl' ?>">
    <div class="letter-thermal-host print-thermal-page is-50x25" title="<?= e(__('thermal.title')) ?>" dir="ltr">
        <?php render_thermal_sticker($corr, 0, $thermalRootId, $qrId, true); ?>
    </div>
    <div class="print-header">
        <?php render_print_logo('print'); ?>
    </div>

    <div class="letter-content <?= e(letter_font_css_class(letter_body_font_code($body))) ?>">
    <?php
    $bodyPrint = function_exists('letter_strip_link_qr_token') ? letter_strip_link_qr_token($body) : $body;
    if ($bodyPrint !== ''): ?>
        <div class="letter-body"><?= render_letter_body_html($bodyPrint, false, $corr) ?></div>
    <?php endif; ?>
    <?php
    if (function_exists('render_letter_end_actions')) {
        render_letter_end_actions($corr, $lang);
    } else {
        render_letter_rsvp_notice($corr, $lang);
    }
    ?>
    </div>
    <?php render_print_page_footer($corr, ['show_users' => false]); ?>
</div>
