<?php
require_once __DIR__ . '/config/auth.php';
echo 'review=' . setting('outgoing_review_enabled') . PHP_EOL;
echo 'appr=' . setting('outgoing_approval_enabled') . PHP_EOL;
echo 'prep=' . setting('outgoing_prep_enabled') . PHP_EOL;
echo 'comms_pri=' . setting('outgoing_comms_primary_user_id')
    . ' alt=' . setting('outgoing_comms_alternate_user_id')
    . ' alt_on=' . setting('outgoing_comms_alternate_enabled')
    . ' mins=' . setting('outgoing_comms_minutes') . PHP_EOL;
echo 'sg=' . setting('outgoing_approver_sg_user_id')
    . ' dep=' . setting('outgoing_approver_deputy_user_id')
    . ' alt_ap=' . setting('outgoing_approver_alternate_user_id') . PHP_EOL;
echo 'rev_pri=' . setting('outgoing_reviewer_primary_user_id')
    . ' rev_alt=' . setting('outgoing_reviewer_alternate_user_id') . PHP_EOL;
$cols = db()->query('SHOW COLUMNS FROM tblcorrespondence')->fetchAll(PDO::FETCH_COLUMN);
echo 'has_prep=' . (in_array('outgoing_prep_status', $cols, true) ? '1' : '0') . PHP_EOL;
$st = db()->query("SELECT id, name_ar, role FROM tblusers WHERE is_active=1 AND id IN (1,2,4,5,70) ORDER BY id");
foreach ($st as $u) {
    echo $u['id'] . '|' . $u['role'] . '|' . $u['name_ar'] . PHP_EOL;
}
