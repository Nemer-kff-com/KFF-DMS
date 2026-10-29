<?php
/**
 * استيراد من نسخة SQL للسيرفر إلى قاعدة النظام الحالية (بدون استبدال كامل للقاعدة).
 *
 * يستورد فقط:
 *   - جهات المراسلة (tblentities) — استبدال كامل
 *   - معاملات الوارد والصادر (tblcorrespondence) — استبدال صفوف الوارد/الصادر
 *   - المرفقات (tbldocuments + tblcorr_attachments) المرتبطة بها
 * ثم يعلّم الوارد/الصادر كـ approved (معاملات قديمة بلا مسار اعتماد):
 *   - الشارة في القوائم: «معتمد» (وليس الاتصالات)
 *   - لا يُخترع decided_by من المنشئ/المدير حتى لا يظهر اسم مضلّل تحت الشارة
 *   - يُصفَّر assignee حتى لا تُعامل كـ with_comms / طباعة اتصالات
 *
 * لا يستورد: المستخدمين، الأدوار، الإعدادات، الداخلي، الأرشيف، التعاميم…
 *
 * الاستخدام (من سطر الأوامر على جهاز فيه XAMPP):
 *
 *   C:\xampp\php\php.exe tools\import_server_dump.php C:\1\dms-server.sql
 *
 * أو مع مسار mysql صريح:
 *
 *   C:\xampp\php\php.exe tools\import_server_dump.php C:\1\dms-server.sql --mysql=C:\xampp\mysql\bin\mysql.exe
 *
 * متطلبات:
 *   - ملف .sql من السيرفر (mysqldump)
 *   - قاعدة dms المحلية موجودة بإعدادات config/db.local.php
 *   - مجلد المرفقات منسوخ مسبقاً إلى مسار storage_root (مثل D:/dms-data/uploads)
 *     بنفس البنية: incoming/… outgoing/… receipts/…
 *
 * بعد التشغيل: حدّث صفحات القوائم/العرض وتأكد أن المرفقات تظهر.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

$root = dirname(__DIR__);
require_once $root . '/config/db.php';

$dump = $argv[1] ?? '';
$mysqlBin = 'C:\\xampp\\mysql\\bin\\mysql.exe';
foreach (array_slice($argv, 2) as $arg) {
    if (str_starts_with($arg, '--mysql=')) {
        $mysqlBin = substr($arg, 8);
    }
}

if ($dump === '' || !is_file($dump)) {
    fwrite(STDERR, "Usage: php tools/import_server_dump.php <path-to-dms-server.sql> [--mysql=path]\n");
    fwrite(STDERR, "Example: php tools/import_server_dump.php C:\\1\\dms-server.sql\n");
    exit(1);
}

$db = require $root . '/config/db.local.php';
$host = (string) ($db['host'] ?? 'localhost');
$name = (string) ($db['name'] ?? 'dms');
$user = (string) ($db['user'] ?? 'root');
$pass = (string) ($db['pass'] ?? '');
$tmpDb = 'dms_import_src';

function sh(string $cmd): array
{
    $out = [];
    $code = 0;
    exec($cmd . ' 2>&1', $out, $code);
    return [$code, implode("\n", $out)];
}

function mysql_cli(string $bin, string $user, string $pass, string $extra): array
{
    $auth = '-u ' . escapeshellarg($user);
    if ($pass !== '') {
        $auth .= ' -p' . escapeshellarg($pass);
    }
    return sh('"' . $bin . '" ' . $auth . ' --default-character-set=utf8mb4 ' . $extra);
}

echo "== 1) temp database ==\n";
[$c, $o] = mysql_cli($mysqlBin, $user, $pass, '-e ' . escapeshellarg(
    "DROP DATABASE IF EXISTS `{$tmpDb}`; CREATE DATABASE `{$tmpDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
));
if ($c !== 0) {
    fwrite(STDERR, $o . "\n");
    exit(1);
}

echo "== 2) load dump into {$tmpDb} ==\n";
$dumpEsc = str_replace('/', '\\', $dump);
[$c, $o] = sh('cmd /c "' . $mysqlBin . ' -u ' . $user
    . ($pass !== '' ? ' -p' . $pass : '')
    . ' --default-character-set=utf8mb4 ' . $tmpDb . ' < "' . $dumpEsc . '""');
if ($c !== 0) {
    fwrite(STDERR, $o . "\n");
    exit(1);
}

$dsn = "mysql:host={$host};charset=utf8mb4";
$pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('SET NAMES utf8mb4');
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');

echo "== 3) entities (replace) ==\n";
$pdo->exec("DELETE FROM `{$name}`.tblentity_group_members");
$pdo->exec("DELETE FROM `{$name}`.tblentities");
$pdo->exec(
    "INSERT INTO `{$name}`.tblentities
     (id, name_ar, name_en, directed_to, title_prefix, job_title, honorific, phone, mobile, email, address, location, notes, department_id, sort_order, is_active, created_at)
     SELECT id, name_ar, name_en, directed_to, title_prefix, job_title, honorific, phone, mobile, email, address, location, notes, department_id, sort_order, is_active, created_at
     FROM `{$tmpDb}`.tblentities"
);
$entities = (int) $pdo->query("SELECT COUNT(*) FROM `{$name}`.tblentities")->fetchColumn();
echo "entities={$entities}\n";

echo "== 4) correspondence incoming/outgoing ==\n";
$localCols = $pdo->query("SHOW COLUMNS FROM `{$name}`.tblcorrespondence")->fetchAll(PDO::FETCH_COLUMN);
$srcCols = $pdo->query("SHOW COLUMNS FROM `{$tmpDb}`.tblcorrespondence")->fetchAll(PDO::FETCH_COLUMN);
$common = array_values(array_intersect($localCols, $srcCols));
if ($common === []) {
    fwrite(STDERR, "No common correspondence columns\n");
    exit(1);
}
$colList = '`' . implode('`, `', $common) . '`';

// حذف مرفقات/ملاحظات مرتبطة بالوارد/الصادر الحالي ثم الصفوف
foreach (['tbldocuments', 'tblcorr_attachments', 'tblcomments', 'tblreferrals', 'tblcorr_deliveries'] as $child) {
    $exists = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=" . $pdo->quote($name) . " AND table_name=" . $pdo->quote($child)
    )->fetchColumn();
    if (!$exists) {
        continue;
    }
    try {
        $pdo->exec(
            "DELETE ch FROM `{$name}`.`{$child}` ch
             INNER JOIN `{$name}`.tblcorrespondence c ON c.id = ch.correspondence_id
             WHERE c.type IN ('incoming','outgoing')"
        );
    } catch (Throwable $e) {
        // تجاهل إن اختلف اسم العمود
    }
}
$pdo->exec("DELETE FROM `{$name}`.tblcorrespondence WHERE type IN ('incoming','outgoing')");
$pdo->exec(
    "INSERT INTO `{$name}`.tblcorrespondence ({$colList})
     SELECT {$colList} FROM `{$tmpDb}`.tblcorrespondence
     WHERE type IN ('incoming','outgoing')"
);
$corr = (int) $pdo->query("SELECT COUNT(*) FROM `{$name}`.tblcorrespondence WHERE type IN ('incoming','outgoing')")->fetchColumn();
echo "correspondence={$corr}\n";

echo "== 5) mark approved (legacy — list shows معتمد, no fake decider name) ==\n";
// متوافق مع عمود الاعتماد في القوائم:
// approved → شارة «معتمد»؛ الاسم تحتها من issue_approval_decided_by فقط إن وُجد في المصدر.
// لا نضع created_by/1 كصاحب قرار، ولا نترك assignee فيُعرض مسار الاتصالات.
$pdo->exec(
    "UPDATE `{$name}`.tblcorrespondence
     SET
       issue_approval_status = 'approved',
       issue_approval_assignee_id = NULL,
       issue_approval_requested_at = NULL,
       issue_approval_requested_by = NULL,
       issue_approval_decided_at = COALESCE(NULLIF(issue_approval_decided_at, '0000-00-00 00:00:00'), updated_at, created_at, TIMESTAMP(corr_date)),
       issue_approval_decided_by = NULLIF(issue_approval_decided_by, 0),
       issue_reviewed_at = COALESCE(NULLIF(issue_reviewed_at, '0000-00-00 00:00:00'), updated_at, created_at, TIMESTAMP(corr_date)),
       issue_review_by = NULLIF(issue_review_by, 0),
       issue_review_note = NULL,
       outgoing_prep_status = NULL,
       outgoing_prep_note = NULL,
       outgoing_prep_decided_by = NULL,
       outgoing_prep_decided_at = NULL
     WHERE type IN ('incoming', 'outgoing')"
);

echo "== 6) documents + enclosure rows ==\n";
$pdo->exec("DELETE FROM `{$name}`.tbldocuments");
$pdo->exec("DELETE FROM `{$name}`.tblcorr_attachments");
$pdo->exec(
    "INSERT INTO `{$name}`.tbldocuments
     (id, correspondence_id, file_name, file_path, file_type, file_size, doc_kind, uploaded_by, uploaded_at)
     SELECT d.id, d.correspondence_id, d.file_name, d.file_path, d.file_type, d.file_size, d.doc_kind, d.uploaded_by, d.uploaded_at
     FROM `{$tmpDb}`.tbldocuments d
     INNER JOIN `{$name}`.tblcorrespondence c ON c.id = d.correspondence_id
     WHERE c.type IN ('incoming','outgoing')"
);
$pdo->exec(
    "INSERT INTO `{$name}`.tblcorr_attachments (id, correspondence_id, attachment_type_id, quantity)
     SELECT a.id, a.correspondence_id, a.attachment_type_id, a.quantity
     FROM `{$tmpDb}`.tblcorr_attachments a
     INNER JOIN `{$name}`.tblcorrespondence c ON c.id = a.correspondence_id
     WHERE c.type IN ('incoming','outgoing')"
);
$docs = (int) $pdo->query("SELECT COUNT(*) FROM `{$name}`.tbldocuments")->fetchColumn();
$atts = (int) $pdo->query("SELECT COUNT(*) FROM `{$name}`.tblcorr_attachments")->fetchColumn();
echo "documents={$docs} corr_attachments={$atts}\n";

$maxE = (int) $pdo->query("SELECT COALESCE(MAX(id),0) FROM `{$name}`.tblentities")->fetchColumn();
$maxC = (int) $pdo->query("SELECT COALESCE(MAX(id),0) FROM `{$name}`.tblcorrespondence")->fetchColumn();
$maxD = (int) $pdo->query("SELECT COALESCE(MAX(id),0) FROM `{$name}`.tbldocuments")->fetchColumn();
$maxA = (int) $pdo->query("SELECT COALESCE(MAX(id),0) FROM `{$name}`.tblcorr_attachments")->fetchColumn();
$pdo->exec("ALTER TABLE `{$name}`.tblentities AUTO_INCREMENT=" . ($maxE + 1));
$pdo->exec("ALTER TABLE `{$name}`.tblcorrespondence AUTO_INCREMENT=" . ($maxC + 1));
$pdo->exec("ALTER TABLE `{$name}`.tbldocuments AUTO_INCREMENT=" . ($maxD + 1));
$pdo->exec("ALTER TABLE `{$name}`.tblcorr_attachments AUTO_INCREMENT=" . ($maxA + 1));

$pdo->exec('SET FOREIGN_KEY_CHECKS=1');
$pdo->exec("DROP DATABASE IF EXISTS `{$tmpDb}`");

$byType = $pdo->query(
    "SELECT type, COUNT(*) c FROM `{$name}`.tblcorrespondence WHERE type IN ('incoming','outgoing') GROUP BY type"
)->fetchAll(PDO::FETCH_ASSOC);

echo "== done ==\n";
echo "entities={$entities} documents={$docs} attachments={$atts}\n";
foreach ($byType as $row) {
    echo $row['type'] . '=' . $row['c'] . "\n";
}
echo "Reminder: copy server upload files into storage_root (see setting storage_root) before or after this script.\n";
