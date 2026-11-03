<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/correspondence.php';

$user = require_login();
if (!can_edit_correspondence($user) && !user_can('templates.edit', $user)) {
    flash('danger', __('auth.forbidden'));
    redirect(base_url('admin/letter_templates.php'));
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    redirect(base_url('admin/letter_templates.php'));
}
verify_csrf();

$lang = strtolower(trim((string) ($_POST['lang'] ?? 'ar'))) === 'en' ? 'en' : 'ar';
$body = (string) ($_POST['body'] ?? '');
$templateId = (int) ($_POST['template_id'] ?? 0);
$deptId = (int) ($_POST['department_id'] ?? 0);
$backUrl = base_url('admin/letter_templates.php' . ($templateId > 0 ? '?id=' . $templateId : ''));

$letterPrintItems = letter_template_preview_items($templateId, $lang, $body, $deptId, $user);
$letterPrintTitle = __('tpl.preview_letter');
$letterPrintBackUrl = $backUrl;
require __DIR__ . '/../includes/print_outgoing_letters.php';
