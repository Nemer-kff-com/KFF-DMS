<?php
/**
 * تحميل صفحة عرض معاملة. يتطلب: $corrType = incoming|outgoing|internal
 */
require_once __DIR__ . '/../config/auth.php';
$user = require_login();
$corrType = (string) ($corrType ?? '');
$typeFolder = $corrType;
$id = (int) ($_GET['id'] ?? 0);
$corr = get_correspondence($id);
$ccViewerOnly = false;
$allowCcViewer = in_array($corrType, ['incoming', 'internal'], true);
if ($allowCcViewer && $corr && ($corr['type'] ?? '') === $corrType && !user_can_access_correspondence($corr, $user)) {
    $ccViewerOnly = corr_cc_user_can_view($corr, $user);
}
if (!$corr || ($corr['type'] ?? '') !== $corrType || (!$ccViewerOnly && !user_can_access_correspondence($corr, $user))) {
    flash('danger', __('corr.not_found'));
    redirect(base_url($typeFolder . '/index.php'));
}
$ref = ($corrType === 'outgoing' && function_exists('correspondence_ref_display'))
    ? correspondence_ref_display($corr)
    : (string) ($corr['ref_number'] ?? '');
$pageTitle = $ref . ' — ' . __('type.' . $corrType);
$documents = get_documents($id);
$referrals = get_referrals($id);
$comments = get_comments($id);
$activity = get_correspondence_activity($id);
require __DIR__ . '/header.php';
require __DIR__ . '/correspondence_view.php';
require __DIR__ . '/footer.php';
