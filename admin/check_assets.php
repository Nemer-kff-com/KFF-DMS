<?php
/**
 * فحص أصول التنسيق بعد نقل النظام — للمدير فقط.
 */
require_once __DIR__ . '/../config/auth.php';
$user = require_permission('admin.settings');

$status = dms_critical_assets_status();
$missing = array_values(array_filter($status, static fn(array $r): bool => empty($r['ok'])));
$okCount = count($status) - count($missing);

$pageTitle = 'فحص ملفات التنسيق';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title"><?= e($pageTitle) ?></h1>
        <p class="page-sub text-muted mb-0">يتحقق من CSS والخطوط والأيقونات حتى يبقى الشكل ثابتاً بعد النقل لجهاز أو سيرفر آخر.</p>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-outline-secondary btn-sm" href="<?= e(base_url('admin/settings.php')) ?>"><i class="bi bi-gear"></i> <?= e(__('nav.settings')) ?></a>
    </div>
</div>

<div class="card-panel mb-3">
    <div class="card-body">
        <p class="mb-2">
            <strong>مسار المشروع:</strong>
            <code dir="ltr"><?= e(dms_project_base() === '' ? '/' : dms_project_base()) ?></code>
            &nbsp;|&nbsp;
            <strong>أساس الروابط:</strong>
            <code dir="ltr"><?= e(dms_detect_base_url()) ?></code>
        </p>
        <p class="mb-0">
            <?php if (!$missing): ?>
                <span class="text-success"><i class="bi bi-check-circle-fill"></i> جميع الملفات الأساسية موجودة (<?= (int) $okCount ?>/<?= (int) count($status) ?>).</span>
            <?php else: ?>
                <span class="text-danger"><i class="bi bi-exclamation-triangle-fill"></i> ناقص <?= (int) count($missing) ?> ملف — انسخ مجلدات <code>assets/css</code> و <code>assets/fonts</code> و <code>assets/vendor</code> كاملة.</span>
            <?php endif; ?>
        </p>
    </div>
</div>

<div class="card-panel">
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead>
            <tr>
                <th>الملف</th>
                <th>الحالة</th>
                <th dir="ltr">URL</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($status as $row): ?>
                <tr>
                    <td dir="ltr"><code><?= e($row['path']) ?></code></td>
                    <td>
                        <?php if (!empty($row['ok'])): ?>
                            <span class="badge text-bg-success">موجود</span>
                        <?php else: ?>
                            <span class="badge text-bg-danger">ناقص</span>
                        <?php endif; ?>
                    </td>
                    <td dir="ltr" class="small"><code><?= e(dms_web_path($row['path'])) ?></code></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
