<?php
/**
 * صفحة عرض مبسطة عند مسح QR — بدون تسجيل دخول
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/helpers.php';
require_once __DIR__ . '/config/security.php';
dms_apply_runtime_security();
dms_send_security_headers();
dms_configure_session();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config/i18n.php';
require_once __DIR__ . '/config/entity_groups.php';
ensure_org_settings();
if (function_exists('ensure_entity_groups_schema')) {
    ensure_entity_groups_schema();
}
if (function_exists('ensure_corr_rsvp_schema')) {
    ensure_corr_rsvp_schema();
}
dms_lang_code();

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$corr = null;
$valid = false;
$letterText = '';
$rsvpRequired = false;
$rsvpFlash = null;
$showFull = dms_http_host_is_public();
$focusRsvp = isset($_GET['rsvp']) && (string) $_GET['rsvp'] !== '0' && (string) $_GET['rsvp'] !== '';

if ($token !== '' && preg_match('/^[a-f0-9]{32}$/', $token)) {
    $stmt = db()->prepare('SELECT id FROM `tblcorrespondence` WHERE qr_token = ? LIMIT 1');
    $stmt->execute([$token]);
    $id = (int) $stmt->fetchColumn();
    $corr = $id ? get_correspondence($id) : null;
    $valid = (bool) $corr;
    if ($valid && ($corr['type'] ?? '') === 'outgoing') {
        if (!isset($_GET['lang']) && empty($_SESSION['dms_lang'])) {
            $_SESSION['dms_lang'] = correspondence_letter_channel_lang($corr);
        }
        $letterText = correspondence_letter_text($corr, dms_lang_code());
        $rsvpRequired = correspondence_rsvp_required($corr);
    }
}

$isCancelled = $valid && correspondence_is_cancelled($corr);
if ($isCancelled) {
    $letterText = '';
    $rsvpRequired = false;
}

if ($valid && $rsvpRequired && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['rsvp_response'])) {
    try {
        verify_csrf();
        save_correspondence_rsvp(
            $corr,
            (string) ($_POST['rsvp_response'] ?? ''),
            (string) ($_POST['rsvp_note'] ?? ''),
            'public'
        );
        flash('success', __('rsvp.saved'));
    } catch (Throwable $e) {
        flash('danger', $e->getMessage());
    }
    $redir = 'verify.php?token=' . rawurlencode($token);
    if ($focusRsvp) {
        $redir .= '&rsvp=1';
    }
    redirect(base_url($redir));
}

$rsvpFlash = function_exists('get_flash') ? get_flash() : null;
$lang = dms_lang_code();
$dir = dms_page_dir();
$orgName = dms_org_display_name();
$verifyOrgNames = function_exists('org_visible_names') ? org_visible_names('verify') : [];
$typeLabel = $valid
    ? (($corr['type'] ?? '') === 'outgoing' ? __('print.type_outgoing') : __('print.type_incoming'))
    : '';
$cssVer = (int) (@filemtime(__DIR__ . '/assets/css/app.css') ?: 1);

$renderRsvpBlock = static function () use ($rsvpRequired, $rsvpFlash, $corr, $token): void {
    if (!$rsvpRequired) {
        return;
    }
    ?>
            <div class="verify-rsvp mt-4" id="verify-rsvp">
                <div class="verify-letter-title"><?= e(__('rsvp.title')) ?></div>
                <p class="small text-muted mb-3"><?= e(__('rsvp.public_hint')) ?></p>
                <?php if ($rsvpFlash): ?>
                    <div class="alert alert-<?= $rsvpFlash['type'] === 'success' ? 'success' : 'danger' ?> py-2">
                        <?= e($rsvpFlash['message']) ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($corr['rsvp_response'])): ?>
                    <div class="mb-3">
                        <?= rsvp_response_badge($corr['rsvp_response']) ?>
                        <?php if (!empty($corr['rsvp_at'])): ?>
                            <span class="small text-muted"><?= e(format_datetime($corr['rsvp_at'])) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($corr['rsvp_note'])): ?>
                            <div class="small mt-1"><?= e($corr['rsvp_note']) ?></div>
                        <?php endif; ?>
                    </div>
                    <p class="small text-muted mb-3"><?= e(__('rsvp.change_hint')) ?></p>
                <?php endif; ?>
                <form method="post" action="<?= e(base_url('verify.php?token=' . urlencode($token) . '&rsvp=1')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="token" value="<?= e($token) ?>">
                    <label class="form-label" for="rsvp_note"><?= e(__('rsvp.note')) ?></label>
                    <input type="text" name="rsvp_note" id="rsvp_note" class="form-control mb-3" maxlength="400"
                           value="<?= e((string) ($corr['rsvp_note'] ?? '')) ?>" placeholder="<?= e(__('rsvp.note_ph')) ?>">
                    <div class="d-grid gap-2">
                        <button class="btn btn-kff" type="submit" name="rsvp_response" value="attending">
                            <?= e(__('rsvp.attend')) ?>
                        </button>
                        <button class="btn btn-outline-danger" type="submit" name="rsvp_response" value="declined">
                            <?= e(__('rsvp.decline')) ?>
                        </button>
                    </div>
                </form>
            </div>
    <?php
};
?>
<!DOCTYPE html>
<html lang="<?= e($lang) ?>" dir="<?= e($dir) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= e($rsvpRequired && $focusRsvp ? __('rsvp.title') : __('verify.page_title')) ?> | <?= e($orgName) ?></title>
    <link href="<?= e(dms_bootstrap_css()) ?>" rel="stylesheet">
    <link href="<?= e(base_url('assets/css/app.css')) ?>?v=<?= $cssVer ?>" rel="stylesheet">
</head>
<body class="verify-simple-page<?= $focusRsvp && $rsvpRequired ? ' verify-focus-rsvp' : '' ?>">
<div class="verify-simple-lang no-print">
    <a href="<?= e(dms_lang_url('ar')) ?>" class="<?= $lang === 'ar' ? 'is-active' : '' ?>">ع</a>
    <a href="<?= e(dms_lang_url('en')) ?>" class="<?= $lang === 'en' ? 'is-active' : '' ?>">EN</a>
</div>

<div class="verify-simple-card">
    <?php if ($token === ''): ?>
        <h1 class="verify-simple-title"><?= e(__('verify.title')) ?></h1>
        <p class="verify-simple-hint"><?= e(__('verify.no_token')) ?></p>
        <form method="get" class="verify-simple-form">
            <input type="text" name="token" class="form-control" required placeholder="<?= e(__('verify.enter_token')) ?>">
            <button class="btn btn-kff w-100" type="submit"><?= e(__('verify.btn')) ?></button>
        </form>
    <?php elseif (!$valid): ?>
        <div class="verify-simple-status is-fail"><?= e(__('verify.fail')) ?></div>
        <p class="verify-simple-hint mb-0"><?= e(__('verify.not_found')) ?></p>
    <?php else: ?>
        <?php if (org_logo_url()): ?>
            <img src="<?= e(org_logo_url()) ?>" alt="" class="verify-simple-logo">
        <?php endif; ?>
        <?php if (!empty($verifyOrgNames['primary'])): ?>
            <div class="verify-simple-org"><?= e($verifyOrgNames['primary']) ?></div>
        <?php endif; ?>
        <?php if (!empty($verifyOrgNames['secondary'])): ?>
            <div class="verify-simple-org-secondary"><?= e($verifyOrgNames['secondary']) ?></div>
        <?php endif; ?>
        <?php if ($isCancelled): ?>
            <div class="verify-simple-status is-fail"><?= e(__('cancel.verify')) ?></div>
        <?php else: ?>
            <div class="verify-simple-status is-ok"><?= e(__('verify.ok')) ?></div>
        <?php endif; ?>

        <div class="verify-simple-type"><?= e($typeLabel) ?></div>
        <div class="verify-simple-number" dir="ltr"><?= e(format_ref_caption((string) ($corr['type'] ?? ''), $corr['ref_number'] ?? '')) ?></div>
        <?php
        $verifyParty = function_exists('correspondence_archived_party')
            ? correspondence_archived_party($corr)
            : ['prefix' => '', 'person' => ''];
        $verifyPrefix = trim((string) ($verifyParty['prefix'] ?? ''));
        $verifyPerson = trim((string) ($verifyParty['person'] ?? ''));
        if ($verifyPerson === '') {
            $verifyPerson = trim((string) ($corr['party_name'] ?? ''));
        }
        if ($verifyPrefix !== '' || $verifyPerson !== ''):
        ?>
            <div class="verify-simple-party mt-3">
                <?php if ($verifyPrefix !== ''): ?>
                    <div class="verify-simple-party-prefix"><?= e($verifyPrefix) ?></div>
                <?php endif; ?>
                <?php if ($verifyPerson !== ''): ?>
                    <div class="verify-simple-party-name"><?= e($verifyPerson) ?></div>
                <?php endif; ?>
            </div>
        <?php
        endif;
        $verifySubject = trim((string) ($corr['subject'] ?? ''));
        if ($verifySubject !== ''):
        ?>
            <div class="verify-simple-subject mt-3">
                <div class="verify-simple-subject-text"><?= e($verifySubject) ?></div>
            </div>
        <?php endif; ?>

        <?php if ($focusRsvp && $rsvpRequired): ?>
            <?php $renderRsvpBlock(); ?>
        <?php endif; ?>

        <?php if ($showFull && $letterText !== ''): ?>
            <div class="verify-letter mt-4">
                <div class="verify-letter-title"><?= e(__('corr.letter_body')) ?></div>
                <div class="verify-letter-text" dir="<?= $lang === 'en' ? 'ltr' : 'rtl' ?>"><?= render_letter_body_html($letterText, false, $corr) ?></div>
            </div>
        <?php endif; ?>

        <?php if (!$focusRsvp && $rsvpRequired): ?>
            <?php $renderRsvpBlock(); ?>
        <?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>
