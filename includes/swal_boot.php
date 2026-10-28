<?php
/**
 * SweetAlert2 — مثل نظام الدعوات (نافذة مركزية)
 */
if (!isset($flash)) {
    $flash = function_exists('get_flash') ? get_flash() : null;
}
if (empty($flash) && !empty($pageAlert) && is_array($pageAlert)) {
    $flash = $pageAlert;
}

// توحيد أنواع الرسائل مع الدعوات
if (!empty($flash['type'])) {
    $t = strtolower((string) $flash['type']);
    if (in_array($t, ['danger', 'fail'], true)) {
        $flash['type'] = 'error';
    }
}

$swalI18n = [
    'ok' => __('common.ok'),
    'yes' => __('common.yes'),
    'cancel' => __('action.cancel'),
    'confirmTitle' => __('common.confirm_title'),
    'successTitle' => __('common.success_title'),
    'errorTitle' => __('common.error_title'),
    'warningTitle' => __('common.warning_title'),
    'infoTitle' => __('common.info_title'),
    'uploadingFiles' => __('file.uploading'),
    'validation' => [
        'valueMissing' => __('validation.value_missing'),
        'valueMissingNamed' => __('validation.value_missing_named'),
        'valueMissingSelect' => __('validation.value_missing_select'),
        'valueMissingSelectNamed' => __('validation.value_missing_select_named'),
        'valueMissingFile' => __('validation.value_missing_file'),
        'valueMissingFileNamed' => __('validation.value_missing_file_named'),
        'typeMismatchEmail' => __('validation.type_email'),
        'typeMismatchUrl' => __('validation.type_url'),
        'typeMismatch' => __('validation.type_mismatch'),
        'patternMismatch' => __('validation.pattern'),
        'tooShort' => __('validation.too_short'),
        'tooLong' => __('validation.too_long'),
        'rangeUnderflow' => __('validation.range_underflow'),
        'rangeOverflow' => __('validation.range_overflow'),
        'stepMismatch' => __('validation.step'),
        'badInput' => __('validation.bad_input'),
    ],
];
?>
<script src="<?= e(dms_sweetalert_js()) ?>"></script>
<script>
window.__DMS_SWAL__ = <?= json_encode([
    'flash' => $flash ? ['type' => $flash['type'], 'message' => $flash['message']] : null,
    'i18n' => $swalI18n,
    'lang' => function_exists('dms_lang_code') ? dms_lang_code() : 'ar',
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>;
</script>
<script src="<?= e(function_exists('dms_asset_url') ? dms_asset_url('assets/js/swal-dms.js', true) : (base_url('assets/js/swal-dms.js') . '?v=5')) ?>"></script>
