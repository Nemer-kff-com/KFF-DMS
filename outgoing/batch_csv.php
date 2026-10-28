<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/correspondence.php';
$user = require_login();
require_outgoing_bulk($user);
ensure_entity_groups_schema();

$id = (int) ($_GET['id'] ?? 0);
$batch = $id ? get_outgoing_batch($id) : null;
$items = $id ? outgoing_batch_items($id, $user) : [];
if (!$batch || !$items) {
    flash('danger', __('corr.not_found'));
    redirect(base_url('outgoing/index.php'));
}

$filename = 'outgoing-batch-' . $id . '-' . date('Ymd') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
echo "\xEF\xBB\xBF";
$out = fopen('php://output', 'w');
fputcsv($out, [
    __('corr.party_to'),
    __('entities.title_prefix'),
    __('entities.directed_to'),
    __('entities.honorific'),
    __('entities.letter_preview'),
    __('entities.job_title'),
    __('entities.mobile'),
    __('entities.email'),
    __('corr.number'),
    __('corr.subject'),
    __('corr.date'),
    __('rsvp.status'),
    __('rsvp.note'),
    __('corr.verify_page'),
]);
foreach ($items as $r) {
    $verifyUrl = dms_corr_public_verify_url($r);
    $party = correspondence_archived_party($r);
    $org = trim((string) ($party['org'] ?? ''));
    $person = trim((string) ($party['person'] ?? ''));
    if ($org !== '' && $person !== '' && $org === $person) {
        $org = '';
    }
    fputcsv($out, [
        $org,
        $party['prefix'],
        $person !== '' ? $person : trim((string) ($r['party_name'] ?? '')),
        $party['honorific'],
        format_entity_honored_name($r) ?: trim((string) ($r['party_name'] ?? '')),
        $party['job'],
        $r['entity_mobile'] ?? '',
        $r['entity_email'] ?? '',
        $r['ref_number'],
        $r['subject'],
        format_date($r['corr_date']),
        rsvp_response_label($r['rsvp_response'] ?? null),
        $r['rsvp_note'] ?? '',
        $verifyUrl,
    ]);
}
fclose($out);
exit;
