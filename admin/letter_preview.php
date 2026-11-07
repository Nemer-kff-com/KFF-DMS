<?php
require_once __DIR__ . '/../config/auth.php';

$user = require_login();
header('Content-Type: application/json; charset=utf-8');

if (!can_edit_correspondence($user) && !user_can('templates.edit', $user)
    && !user_can('outgoing.bulk', $user) && !user_can('outgoing.create', $user) && !user_can('outgoing.edit', $user)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => __('auth.forbidden')], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method'], JSON_UNESCAPED_UNICODE);
    exit;
}

verify_csrf();
$body = (string) ($_POST['body'] ?? '');
$lang = strtolower(trim((string) ($_POST['lang'] ?? 'ar'))) === 'en' ? 'en' : 'ar';

$prepId = (int) ($_POST['department_id'] ?? 0);
$issuingId = (int) ($_POST['issuing_department_id'] ?? 0);
$deptId = $issuingId > 0 ? $issuingId : $prepId;

$row = function_exists('letter_preview_form_row')
    ? letter_preview_form_row($_POST, $user, $lang, $deptId)
    : null;
if (!is_array($row) || $row === []) {
    $row = function_exists('correspondence_outgoing_draft_row')
        ? correspondence_outgoing_draft_row($_POST, $user, true)
        : letter_preview_sample_row($lang, $deptId);
}
if (!is_array($row)) {
    $row = letter_preview_sample_row($lang, $deptId);
}
if ((string) ($_POST['omit_parties'] ?? '') === '1') {
    foreach ([
        'department_id', 'issuing_department_id', 'entity_id',
        'snap_title_prefix', 'snap_person_name', 'snap_honorific', 'snap_job_title', 'snap_org_name',
        'snap_from_prefix', 'snap_from_person', 'snap_from_job', 'snap_from_org', 'snap_from_signature',
        'party_name', 'entity_name', 'entity_name_en', 'entity_directed_to', 'entity_title_prefix',
        'entity_job_title', 'entity_honorific', 'entity_email',
    ] as $key) {
        $row[$key] = '';
    }
}
// احتياط: رابط الخطاب ورمز QR من الدفعة المحفوظة
$batchId = (int) ($_POST['batch_id'] ?? 0);
if ($batchId > 0 && trim((string) ($row['letter_link_url'] ?? '')) === '') {
    $bst = db()->prepare('SELECT letter_link_label, letter_link_url, requires_rsvp, rsvp_allow_remote FROM `tbloutgoing_batches` WHERE id = ? LIMIT 1');
    $bst->execute([$batchId]);
    $brow = $bst->fetch(PDO::FETCH_ASSOC) ?: [];
    if (trim((string) ($brow['letter_link_url'] ?? '')) !== '') {
        $row['letter_link_label'] = $brow['letter_link_label'] ?? null;
        $row['letter_link_url'] = $brow['letter_link_url'] ?? null;
    }
    if (!isset($_POST['requires_rsvp']) && !isset($_POST['rsvp_required']) && isset($brow['requires_rsvp'])) {
        $row['rsvp_required'] = (int) $brow['requires_rsvp'];
        $row['rsvp_allow_remote'] = (int) ($brow['rsvp_allow_remote'] ?? 1);
    }
}
if (function_exists('letter_preview_ensure_letter_link')) {
    $row = letter_preview_ensure_letter_link($row, $_POST, $batchId);
}
$itemToken = strtolower(trim((string) ($row['qr_token'] ?? '')));
if (!preg_match('/^[a-f0-9]{32}$/', $itemToken)) {
    $tok = '';
    if ($batchId > 0) {
        $tst = db()->prepare(
            "SELECT qr_token FROM `tblcorrespondence`
             WHERE batch_id = ? AND type = 'outgoing' AND status <> 'cancelled'
               AND qr_token IS NOT NULL AND qr_token <> ''
             ORDER BY id LIMIT 1"
        );
        $tst->execute([$batchId]);
        $tok = strtolower(trim((string) $tst->fetchColumn()));
    }
    $row['qr_token'] = preg_match('/^[a-f0-9]{32}$/', $tok) ? $tok : 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
}

echo json_encode([
    'ok' => true,
    'html' => letter_preview_html($body, $lang, $deptId, $row),
], JSON_UNESCAPED_UNICODE);