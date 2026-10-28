<?php
/**
 * طباعة بطاقة جهة العمل — جميع البيانات + QR للموقع (Google Maps)
 */
require_once __DIR__ . '/../config/auth.php';
$user = require_permission('entities.manage');
ensure_entities_schema();

$id = (int) ($_GET['id'] ?? 0);
$entity = $id ? get_entity($id) : null;
if (!$entity || !entity_visible_to_user($id, $user)) {
    flash('danger', __('common.not_found'));
    redirect(base_url('admin/entities.php'));
}

$googleMapsUrl = entity_google_maps_url($entity);
$hasLocationQr = $googleMapsUrl !== '';

$printField = static function (string $label, string $value, bool $ltr = false): void {
    if (trim($value) === '') {
        return;
    }
    echo '<tr><th>' . e($label) . '</th><td';
    if ($ltr) {
        echo ' dir="ltr"';
    }
    echo '>' . e($value) . '</td></tr>';
};

$cssVer = @filemtime(__DIR__ . '/../assets/css/app.css') ?: time();
$jsVer = @filemtime(__DIR__ . '/../assets/js/app.js') ?: 9;
?>
<!DOCTYPE html>
<html lang="<?= e(dms_lang_code()) ?>" dir="<?= e(dms_page_dir()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(__('entities.print_card')) ?> — <?= e((string) ($entity['name_ar'] ?? '')) ?></title>
    <link rel="icon" href="<?= e(base_url('assets/img/favicon.ico')) ?>">
    <link href="<?= e(dms_bootstrap_css()) ?>" rel="stylesheet">
    <link href="<?= e(dms_bootstrap_icons_css()) ?>" rel="stylesheet">
    <link href="<?= e(base_url('assets/css/app.css')) ?>?v=<?= (int) $cssVer ?>" rel="stylesheet">
    <link href="<?= e(dms_print_fast_css()) ?>" rel="stylesheet">
    <style>@page { size: A4 portrait; margin: 0; }</style>
</head>
<body class="print-page print-entity-card-page">
<div class="print-toolbar no-print">
    <div class="print-toolbar-title"><?= e(__('entities.print_card')) ?></div>
    <div class="d-flex flex-wrap gap-2">
        <button type="button" class="btn btn-kff btn-sm" onclick="window.print()">
            <i class="bi bi-printer me-1"></i><?= e(__('action.print')) ?>
        </button>
        <a class="btn btn-outline-secondary btn-sm" href="<?= e(base_url('admin/entities.php')) ?>">
            <i class="bi <?= dms_is_rtl() ? 'bi-arrow-right' : 'bi-arrow-left' ?> me-1"></i><?= e(__('action.back')) ?>
        </a>
    </div>
</div>

<div class="print-sheet print-entity-card-sheet<?= $hasLocationQr ? ' print-entity-card-sheet--with-qr' : '' ?>">
    <div class="print-entity-card-header<?= $hasLocationQr ? ' print-entity-card-header--with-qr' : '' ?>">
        <?php if ($hasLocationQr): ?>
            <a class="print-entity-card-qr-link"
               href="<?= e($googleMapsUrl) ?>"
               target="_blank"
               rel="noopener noreferrer"
               title="<?= e(__('entities.location_qr_label')) ?>">
                <span class="print-entity-card-qr-float" id="entityLocationQr" aria-label="<?= e(__('entities.location_qr_label')) ?>"></span>
            </a>
            <div class="print-entity-card-header-spacer" aria-hidden="true"></div>
        <?php endif; ?>
        <div class="print-entity-card-brand-col">
            <?php render_print_logo('print'); ?>
            <h1 class="print-entity-card-title"><?= e(__('entities.print_card_title')) ?></h1>
        </div>
    </div>

    <table class="table table-bordered print-fields print-entity-card-fields">
        <?php
        $printField(__('entities.title_prefix'), (string) ($entity['title_prefix'] ?? ''));
        $printField(__('entities.directed_to'), (string) ($entity['directed_to'] ?? ''));
        $printField(__('entities.job_title'), (string) ($entity['job_title'] ?? ''));
        $printField(__('entities.section_basic'), (string) ($entity['name_ar'] ?? ''));
        $printField(__('entities.mobile'), (string) ($entity['mobile'] ?? ''), true);
        $printField(__('entities.email'), (string) ($entity['email'] ?? ''), true);
        $printField(__('entities.phone_landline'), (string) ($entity['phone'] ?? ''), true);
        $printField(__('entities.address'), (string) ($entity['address'] ?? ''));
        $printField(__('entities.notes'), (string) ($entity['notes'] ?? ''));
        ?>
    </table>

    <?php render_print_page_footer(); ?>
</div>

<?php if ($hasLocationQr): ?>
<script src="<?= e(base_url('assets/js/qrcode.min.js')) ?>"></script>
<script src="<?= e(base_url('assets/js/app.js')) ?>?v=<?= (int) $jsVer ?>"></script>
<script>
(function () {
    var url = <?= json_encode($googleMapsUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    var qrPx = Math.round(2.5 / 2.54 * 96);
    renderQr('entityLocationQr', url, qrPx);
})();
</script>
<?php else: ?>

<?php endif; ?>
</body>
</html>
