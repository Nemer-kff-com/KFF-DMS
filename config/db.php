<?php
/**
 * اتصال قاعدة البيانات - DMS
 *
 * محمول لأي جهاز أو استضافة — بدون أسماء سيرفر ثابتة:
 *   1) الافتراضي: localhost / dms / root / كلمة مرور فارغة (XAMPP)
 *   2) اختياري: config/db.local.php لتخصيص هذا الجهاز فقط
 *   3) أو متغيرات البيئة: DMS_DB_HOST, DMS_DB_NAME, DMS_DB_USER, DMS_DB_PASS
 *
 * على HostGator ضع بيانات cPanel في db.local.php أو عبر البيئة.
 */
if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
    http_response_code(403);
    exit('Forbidden');
}

$dmsDb = [
    'host' => 'localhost',
    'name' => 'dms',
    'user' => 'root',
    'pass' => '',
    'charset' => 'utf8mb4',
];

foreach (['host' => 'DMS_DB_HOST', 'name' => 'DMS_DB_NAME', 'user' => 'DMS_DB_USER', 'pass' => 'DMS_DB_PASS'] as $key => $env) {
    $val = getenv($env);
    if ($val !== false) {
        $dmsDb[$key] = (string) $val;
    }
}

if (is_file(__DIR__ . '/db.local.php')) {
    $local = require __DIR__ . '/db.local.php';
    if (is_array($local)) {
        $dmsDb = array_merge($dmsDb, $local);
    }
}

define('DB_HOST', (string) $dmsDb['host']);
define('DB_NAME', (string) $dmsDb['name']);
define('DB_USER', (string) $dmsDb['user']);
define('DB_PASS', (string) $dmsDb['pass']);
define('DB_CHARSET', (string) ($dmsDb['charset'] ?? 'utf8mb4'));

function db_is_gone_away(Throwable $e): bool
{
    if (!$e instanceof PDOException) {
        return false;
    }
    $code = (int) ($e->errorInfo[1] ?? 0);
    if ($code === 2006 || $code === 2013) {
        return true;
    }
    $msg = $e->getMessage();
    return str_contains($msg, '2006')
        || str_contains($msg, '2013')
        || str_contains($msg, 'server has gone away')
        || str_contains($msg, 'Lost connection');
}

function db_reconnect(): PDO
{
    $GLOBALS['_dms_pdo'] = null;
    return db();
}

function db(): PDO
{
    if (!empty($GLOBALS['_dms_pdo']) && $GLOBALS['_dms_pdo'] instanceof PDO) {
        return $GLOBALS['_dms_pdo'];
    }

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
    ]);
    $pdo->exec("SET CHARACTER SET utf8mb4");
    $pdo->exec("SET collation_connection = utf8mb4_unicode_ci");
    $GLOBALS['_dms_pdo'] = $pdo;
    return $pdo;
}

require_once __DIR__ . '/db_tables.php';
