<?php
/** أصول الرأس المشتركة — محلية بالكامل ومسارات نسبية للجذر (لا تتأثر بنقل الجهاز/الدومين) */
$asset = static function (string $rel) {
    return function_exists('dms_asset_url') ? dms_asset_url($rel, true) : base_url($rel);
};
$web = static function (string $rel) {
    return function_exists('dms_web_path') ? dms_web_path($rel) : base_url($rel);
};
$biWoff2 = $asset('assets/vendor/bootstrap-icons/fonts/bootstrap-icons.woff2');
$biWoff = $asset('assets/vendor/bootstrap-icons/fonts/bootstrap-icons.woff');
$faSolid = $asset('assets/vendor/fontawesome/webfonts/fa-solid-900.woff2');
$faRegular = $asset('assets/vendor/fontawesome/webfonts/fa-regular-400.woff2');
$gretaReg = $asset('assets/fonts/GretaArabic-Regular.otf');
$gretaLight = $asset('assets/fonts/GretaArabic-Light.otf');
$gretaBold = $asset('assets/fonts/GretaArabic-Bold.otf');
$amiriReg = $asset('assets/fonts/Amiri-Regular.ttf');
$amiriBold = $asset('assets/fonts/Amiri-Bold.ttf');
$appCss = $asset('assets/css/app.css');
$swalCss = $asset('assets/css/swal-dms.css');
$favicon = $web('assets/img/favicon.ico');
?>
<link rel="icon" href="<?= e($favicon) ?>">
<link href="<?= e(dms_brand_fonts_css()) ?>" rel="stylesheet">
<link href="<?= e(dms_bootstrap_icons_css()) ?>" rel="stylesheet">
<link href="<?= e(dms_fontawesome_css()) ?>" rel="stylesheet">
<link href="<?= e($appCss) ?>" rel="stylesheet">
<link href="<?= e($swalCss) ?>" rel="stylesheet">
<style id="dms-portable-fonts">
/* مسارات خطوط من جذر الموقع — تمنع اختفاء الأيقونات/الخطوط بعد النقل */
@font-face {
  font-family: "GretaArabic";
  font-weight: 400;
  font-style: normal;
  font-display: swap;
  src: url("<?= e($gretaReg) ?>") format("opentype");
}
@font-face {
  font-family: "GretaArabic";
  font-weight: 300;
  font-style: normal;
  font-display: swap;
  src: url("<?= e($gretaLight) ?>") format("opentype");
}
@font-face {
  font-family: "GretaArabic";
  font-weight: 700;
  font-style: normal;
  font-display: swap;
  src: url("<?= e($gretaBold) ?>") format("opentype");
}
@font-face {
  font-family: "Amiri";
  font-weight: 400;
  font-style: normal;
  font-display: swap;
  src: url("<?= e($amiriReg) ?>") format("truetype");
}
@font-face {
  font-family: "Amiri";
  font-weight: 700;
  font-style: normal;
  font-display: swap;
  src: url("<?= e($amiriBold) ?>") format("truetype");
}
@font-face {
  font-family: "bootstrap-icons";
  font-display: swap;
  src: url("<?= e($biWoff2) ?>") format("woff2"),
       url("<?= e($biWoff) ?>") format("woff");
}
@font-face {
  font-family: "Font Awesome 6 Free";
  font-style: normal;
  font-weight: 900;
  font-display: swap;
  src: url("<?= e($faSolid) ?>") format("woff2");
}
@font-face {
  font-family: "Font Awesome 6 Free";
  font-style: normal;
  font-weight: 400;
  font-display: swap;
  src: url("<?= e($faRegular) ?>") format("woff2");
}
</style>
