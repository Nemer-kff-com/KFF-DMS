<?php
/**
 * مساعدة المستخدم — دليل العمل اليومي من مجلد doc/
 */
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/help_docs.php';

$user = require_login();

$docId = trim((string) ($_GET['doc'] ?? ''));
$meta = $docId !== '' ? dms_help_doc_resolve($docId, 'user') : null;
$catalog = dms_user_help_docs_catalog();
$helpIndexUrl = base_url('help/index.php');

if ($docId !== '' && !$meta) {
    flash('danger', __('help.doc_not_found'));
    redirect($helpIndexUrl);
}

$pageTitle = $meta ? dms_help_doc_title($meta) : __('help.user_title');
$htmlBody = '';
if ($meta) {
    $raw = (string) file_get_contents($meta['path']);
    $htmlBody = dms_help_markdown_to_html($raw, (string) $meta['id'], 'user');
}

require __DIR__ . '/../includes/header.php';
?>
<div class="page-header page-narrow">
    <div>
        <h1 class="page-title"><?= e($meta ? dms_help_doc_title($meta) : __('help.user_title')) ?></h1>
        <p class="page-sub"><?= e($meta ? dms_help_doc_summary($meta) : __('help.user_subtitle')) ?></p>
    </div>
    <div class="page-header-actions no-print">
        <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
            <i class="bi bi-printer me-1"></i><?= e(__('help.print')) ?>
        </button>
        <?php if ($meta): ?>
            <a class="btn btn-outline-secondary" href="<?= e($helpIndexUrl) ?>">
                <i class="bi bi-grid me-1"></i><?= e(__('help.index')) ?>
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="page-narrow help-page">
    <?php if ($meta): ?>
        <nav class="help-breadcrumb no-print" aria-label="breadcrumb">
            <a href="<?= e($helpIndexUrl) ?>"><?= e(__('help.user_title')) ?></a>
            <span class="help-breadcrumb-sep">/</span>
            <span><?= e(dms_help_doc_group_title(['group_ar' => $meta['group_ar'] ?? '', 'group_en' => $meta['group_en'] ?? ''])) ?></span>
            <span class="help-breadcrumb-sep">/</span>
            <span class="text-muted"><?= e(dms_help_doc_title($meta)) ?></span>
        </nav>
        <article class="card-panel help-doc-article">
            <div class="card-body help-doc-body">
                <?= $htmlBody ?>
            </div>
        </article>
        <div class="help-doc-footer no-print">
            <?= dms_back_icon($helpIndexUrl, __('help.back_index')) ?>
            <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
                <i class="bi bi-printer me-1"></i><?= e(__('help.print')) ?>
            </button>
        </div>
    <?php else: ?>
        <p class="help-intro"><?= e(__('help.user_intro')) ?></p>
        <div class="help-catalog">
            <?php foreach ($catalog as $group): ?>
                <section class="help-group card-panel">
                    <div class="help-group-head">
                        <span class="help-group-icon"><i class="bi <?= e((string) ($group['icon'] ?? 'bi-folder')) ?>"></i></span>
                        <h2 class="help-group-title"><?= e(dms_help_doc_group_title($group)) ?></h2>
                    </div>
                    <div class="help-doc-grid">
                        <?php foreach ($group['docs'] as $doc): ?>
                            <a class="help-doc-card" href="<?= e($helpIndexUrl . '?doc=' . rawurlencode((string) $doc['id'])) ?>">
                                <span class="help-doc-card-title"><?= e(dms_help_doc_title($doc)) ?></span>
                                <span class="help-doc-card-summary"><?= e(dms_help_doc_summary($doc)) ?></span>
                                <span class="help-doc-card-go"><i class="bi <?= e((function_exists('dms_is_rtl') && dms_is_rtl()) ? 'bi-arrow-left' : 'bi-arrow-right') ?>"></i></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
