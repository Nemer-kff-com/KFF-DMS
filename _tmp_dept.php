<?php
require_once __DIR__ . '/config/auth.php';
$cols = db()->query('SHOW COLUMNS FROM tbldepartments')->fetchAll(PDO::FETCH_COLUMN);
echo 'dept_cols: ' . implode(',', $cols) . PHP_EOL;
foreach (db()->query('SELECT * FROM tbldepartments ORDER BY id LIMIT 3') as $d) {
    echo json_encode($d, JSON_UNESCAPED_UNICODE) . PHP_EOL;
}
$u = db()->query('SELECT id, department_id, role FROM tblusers WHERE id IN (2,7)')->fetchAll();
print_r($u);
echo "---required corr---\n";
foreach (db()->query('SHOW COLUMNS FROM tblcorrespondence') as $c) {
    if ($c['Null'] === 'NO' && $c['Default'] === null && strpos($c['Extra'], 'auto_increment') === false) {
        echo 'REQ ' . $c['Field'] . PHP_EOL;
    }
}
