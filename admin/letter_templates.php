<?php
require_once __DIR__ . '/../config/auth.php';
$user = require_login();
if (!user_can('templates.edit', $user)) {
    flash('danger', __('auth.forbidden'));
    redirect(base_url('index.php'));
}
ensure_letter_templates_schema();
$pageTitle = __('nav.letter_templates');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    if (($_POST['action'] ?? '') === 'delete' && $id) {
        try {
            delete_letter_template($id, $user);
            flash('success', __('tpl.deleted'));
        } catch (Throwable $e) {
            flash('danger', $e->getMessage());
        }
        redirect(base_url('admin/letter_templates.php'));
    }
}

$editId = (int) ($_GET['id'] ?? 0);
$editing = $editId ? get_letter_template($editId) : null;
if ($editId && (!$editing || !user_can_edit_letter_template($editing, $user))) {
    flash('danger', __('auth.forbidden'));
    redirect(base_url('admin/letter_templates.php'));
}

$error = '';
$values = [
    'name_ar' => $editing['name_ar'] ?? '',
    'name_en' => $editing['name_en'] ?? '',
    'code' => $editing['code'] ?? '',
    'body' => $editing['body'] ?? '',
    'body_en' => $editing['body_en'] ?? '',
    'department_id' => $editing['department_id'] ?? (can_choose_correspondence_department($user) ? '' : (function_exists('user_primary_department_id') ? user_primary_department_id($user) : ($user['department_id'] ?? ''))),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') !== 'delete') {
    foreach (array_keys($values) as $field) {
        $values[$field] = (string) ($_POST[$field] ?? '');
    }
    try {
        $savedId = save_letter_template($_POST, $user, $editing ? (int) $editing['id'] : null);
        flash('success', $editing ? __('tpl.updated') : __('tpl.created'));
        redirect(base_url('admin/letter_templates.php'));
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$rows = letter_templates_list($user, true);
$departments = departments_list();
$isForm = $editing || isset($_GET['new']) || $error !== '';

require __DIR__ . '/../includes/header.php';
?>
<div class="page-header page-narrow">
    <div>
        <h1 class="page-title"><?= e(__('tpl.title')) ?></h1>
        <p class="page-sub"><?= e(__('tpl.subtitle')) ?></p>
    </div>
    <div class="page-header-actions">
        <?php if (!$isForm): ?>
            <?= btn_icon_add(base_url('admin/letter_templates.php?new=1'), __('tpl.new')) ?>
        <?php else: ?>
            <a class="btn btn-outline-secondary" href="<?= e(base_url('admin/letter_templates.php')) ?>"><?= e(__('action.back')) ?></a>
        <?php endif; ?>
    </div>
</div>

<?php if ($isForm): ?>
<div class="admin-editor page-narrow mb-4">
    <div class="card-panel">
        <div class="card-header">
            <div class="admin-editor-header">
                <span class="admin-editor-icon"><i class="bi bi-file-earmark-text"></i></span>
                <span><?= e($editing ? __('tpl.edit') : __('tpl.new')) ?></span>
            </div>
        </div>
        <div class="card-body">
            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><?= e($error) ?></div>
            <?php endif; ?>
            <form method="post" class="admin-form">
                <?= csrf_field() ?>
                <?php if ($editing): ?>
                    <input type="hidden" name="id" value="<?= (int) $editing['id'] ?>">
                <?php endif; ?>
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label" for="name_ar"><?= e(__('lookups.name_ar')) ?> *</label>
                        <input type="text" name="name_ar" id="name_ar" class="form-control" required value="<?= e($values['name_ar']) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="name_en"><?= e(__('lookups.name_en')) ?></label>
                        <input type="text" name="name_en" id="name_en" class="form-control" value="<?= e($values['name_en']) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="code"><?= e(__('lookups.code')) ?></label>
                        <input type="text" name="code" id="code" class="form-control" dir="ltr" value="<?= e($values['code']) ?>">
                    </div>
                    <?php if (can_choose_correspondence_department($user)): ?>
                    <div class="col-md-6">
                        <label class="form-label" for="department_id"><?= e(__('corr.department')) ?></label>
                        <select name="department_id" id="department_id" class="form-select">
                            <option value=""><?= e(__('tpl.public')) ?></option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= (int) $d['id'] ?>" <?= (string) $values['department_id'] === (string) $d['id'] ? 'selected' : '' ?>><?= e(department_display_name($d)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php else: ?>
                        <input type="hidden" name="department_id" id="department_id" value="<?= e((string) $values['department_id']) ?>">
                    <?php endif; ?>
                </div>
                <div class="mb-3">
                    <label class="form-label"><?= e(__('tpl.body')) ?> *</label>
                    <?php render_letter_lang_editor('body', 'body_en', (string) $values['body'], (string) $values['body_en'], 10, true, [], [], '', letter_signer_insert_tokens()); ?>
                </div>
                <div class="admin-form-actions">
                    <button class="btn btn-kff" type="submit"><?= e(__('action.save')) ?></button>
                    <a class="btn btn-outline-secondary" href="<?= e(base_url('admin/letter_templates.php')) ?>"><?= e(__('action.cancel')) ?></a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card-panel page-narrow">
    <div class="card-header d-flex justify-content-between">
        <span><?= e(__('tpl.title')) ?></span>
        <span class="badge text-bg-light text-dark"><?= count($rows) ?></span>
    </div>
    <?php if (!$rows): ?>
        <div class="admin-empty"><i class="bi bi-file-earmark-text"></i><p><?= e(__('common.no_data')) ?></p></div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table admin-table mb-0 align-middle">
            <thead>
                <tr>
                    <th><?= e(__('lookups.name_ar')) ?></th>
                    <th><?= e(__('corr.department')) ?></th>
                    <th class="text-end"><?= e(__('common.actions')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td>
                        <div class="admin-entity-name"><?= e(letter_template_label($r)) ?></div>
                        <div class="small text-muted"><?= e(mb_substr(preg_replace('/\s+/', ' ', (string) $r['body']), 0, 80)) ?>…</div>
                    </td>
                    <td><?= e((int) ($r['department_id'] ?? 0) ? department_display_name($r, 'department_name', 'department_name_en') : __('tpl.public')) ?></td>
                    <td class="text-end text-nowrap">
                        <?php if (user_can_edit_letter_template($r, $user)): ?>
                            <a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('admin/letter_templates.php?id=' . (int) $r['id'])) ?>"><i class="bi bi-pencil"></i></a>
                            <form method="post" class="d-inline" data-confirm="<?= e(__('common.confirm_delete')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
