<?php
/**
 * Canonical MySQL table names — always lowercase (Linux / HostGator).
 *
 * Column names may keep mixed case. Table identifiers must stay fully lowercase
 * in SQL, CREATE TABLE, and SHOW TABLES / SHOW COLUMNS.
 */
declare(strict_types=1);

final class DmsDbTable
{
    public const USERS = 'tblusers';
    public const USER_DEPARTMENTS = 'tbluser_departments';
    public const DEPARTMENTS = 'tbldepartments';
    public const DEPARTMENT_SIGNING_ACCESS = 'tbldepartment_signing_access';
    public const SETTINGS = 'tblsettings';
    public const SEQUENCES = 'tblsequences';
    public const CORRESPONDENCE = 'tblcorrespondence';
    public const CORRESPONDENCE_BRIEFS = 'tblcorrespondence_briefs';
    public const DOCUMENTS = 'tbldocuments';
    public const REFERRALS = 'tblreferrals';
    public const COMMENTS = 'tblcomments';
    public const ACTIVITY_LOG = 'tblactivity_log';
    public const ENTITIES = 'tblentities';
    public const ORGANIZATIONS_LEGACY = 'tblorganizations';
    public const ENTITY_GROUPS = 'tblentity_groups';
    public const ENTITY_GROUP_MEMBERS = 'tblentity_group_members';
    public const CATEGORIES = 'tblcategories';
    public const ARCHIVE_ITEMS = 'tblarchive_items';
    public const ARCHIVE_FILES = 'tblarchive_files';
    public const ARCHIVE_DOC_TYPES = 'tblarchive_doc_types';
    public const ARCHIVE_COUNTRIES = 'tblarchive_countries';
    public const ARCHIVE_DEGREE = 'tblarchive_degree';
    public const ARCHIVE_UNIVERSITY = 'tblarchive_university';
    public const ARCHIVE_CURRENCY = 'tblarchive_currency';
    public const ARCHIVE_SCHOLARSHIPS = 'tblarchive_scholarships';
    public const ARCHIVE_PROJECTS = 'tblarchive_projects';
    public const ARCHIVE_FINANCE = 'tblarchive_finance';
    public const CIRCULARS = 'tblcirculars';
    public const CIRCULAR_DEPARTMENTS = 'tblcircular_departments';
    public const CIRCULAR_FILES = 'tblcircular_files';
    public const CIRCULAR_SEQUENCES = 'tblcircular_sequences';
    public const SIDEBAR_MENU = 'tblsidebar_menu';
    public const OUTGOING_BATCHES = 'tbloutgoing_batches';
    public const CORR_DELIVERIES = 'tblcorr_deliveries';
    public const CORR_RSVP_LOG = 'tblcorr_rsvp_log';
    public const CORR_ATTACHMENTS = 'tblcorr_attachments';
    public const ATTACHMENT_TYPES = 'tblattachment_types';
    public const PRIORITY_LEVELS = 'tblpriority_levels';
    public const CONFIDENTIALITY_LEVELS = 'tblconfidentiality_levels';
    public const STATUS_LEVELS = 'tblstatus_levels';
    public const LETTER_TEMPLATES = 'tblletter_templates';
    public const NOTIFICATIONS = 'tblnotifications';
    public const EMAIL_SETTINGS = 'tblemail_settings';
    public const EMAIL_DEPARTMENT_SETTINGS = 'tblemail_department_settings';
    public const WHATSAPP_SETTINGS = 'tblwhatsapp_settings';
    public const PASSWORD_RESETS = 'tblpassword_resets';
    public const SECURITY_LOG = 'tblsecurity_log';
    public const USER_SESSIONS = 'tbluser_sessions';
    public const SESSION_KILLS = 'tblsession_kills';

    private function __construct()
    {
    }
}

/** Normalize a table identifier for SQL / SHOW TABLES (HostGator-safe). */
function dms_db_table_name(string $table): string
{
    return strtolower(trim($table, " `"));
}

/** @return array<string,string> lowercase => actual SHOW TABLES name */
function dms_db_tables_map(bool $refresh = false): array
{
    static $map = null;
    if ($refresh) {
        $map = null;
    }
    if (is_array($map)) {
        return $map;
    }
    $map = [];
    try {
        foreach (db()->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM) as $row) {
            $actual = (string) $row[0];
            $map[strtolower($actual)] = $actual;
        }
    } catch (Throwable $e) {
        $map = [];
    }
    return $map;
}

function dms_db_table_exists(string $table): bool
{
    $table = dms_db_table_name($table);
    return $table !== '' && isset(dms_db_tables_map()[$table]);
}

/** Case-insensitive table check on any PDO (install/index.php, etc.). */
function dms_pdo_table_exists(PDO $pdo, string $table): bool
{
    return dms_pdo_table_ident($pdo, $table) !== null;
}

/** Actual table name as stored, or null if missing. */
function dms_pdo_table_ident(PDO $pdo, string $table): ?string
{
    $want = dms_db_table_name($table);
    if ($want === '') {
        return null;
    }
    try {
        foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM) as $row) {
            $actual = (string) $row[0];
            if (strtolower($actual) === $want) {
                return $actual;
            }
        }
    } catch (Throwable $e) {
        return null;
    }
    return null;
}

/** Actual table name as stored (may differ in case before normalize). */
function dms_db_table_ident(string $table): string
{
    $table = dms_db_table_name($table);
    $map = dms_db_tables_map();
    $actual = $map[$table] ?? $table;
    return str_replace('`', '', $actual);
}

function dms_sql_table(string $table): string
{
    return '`' . str_replace('`', '', dms_db_table_ident($table)) . '`';
}

/** @return list<array<string,mixed>> */
function dms_db_show_columns(string $table): array
{
    if (!dms_db_table_exists($table)) {
        return [];
    }
    try {
        return db()->query('SHOW COLUMNS FROM ' . dms_sql_table($table))->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

/** @return list<string> */
function dms_db_column_names(string $table): array
{
    $names = [];
    foreach (dms_db_show_columns($table) as $col) {
        $names[] = (string) ($col['Field'] ?? '');
    }
    return $names;
}

/** @return array<string,true> lowercase field => true */
function dms_db_column_map(string $table): array
{
    $cols = [];
    foreach (dms_db_show_columns($table) as $col) {
        $name = strtolower((string) ($col['Field'] ?? ''));
        if ($name !== '') {
            $cols[$name] = true;
        }
    }
    return $cols;
}

function dms_db_column_exists(string $table, string $column): bool
{
    $column = trim($column);
    if ($column === '') {
        return false;
    }
    return isset(dms_db_column_map($table)[strtolower($column)]);
}

/** @return array<string,mixed>|null */
function dms_db_column_meta(string $table, string $column): ?array
{
    $tableName = dms_db_table_name($table);
    $column = trim($column);
    if ($tableName === '' || $column === '' || !dms_db_table_exists($tableName)) {
        return null;
    }
    try {
        $stmt = db()->prepare('SHOW COLUMNS FROM ' . dms_sql_table($tableName) . ' LIKE ?');
        $stmt->execute([$column]);
        $row = $stmt->fetch();
        return $row ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * Rename mixed-case tbl* tables to lowercase so Linux/HostGator matches Windows.
 */
function dms_db_normalize_table_names(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $pdo = db();
        $byLower = [];
        foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM) as $row) {
            $actual = (string) $row[0];
            if (!preg_match('/^[A-Za-z0-9_]+$/', $actual)) {
                continue;
            }
            $byLower[strtolower($actual)][] = $actual;
        }
        foreach ($byLower as $lower => $names) {
            $names = array_values(array_unique($names));
            $hasLower = in_array($lower, $names, true);
            $mixed = array_values(array_filter($names, static fn(string $n): bool => $n !== $lower));
            if ($hasLower || count($mixed) !== 1 || !preg_match('/^[a-z0-9_]+$/', $lower)) {
                continue;
            }
            try {
                $pdo->exec('RENAME TABLE `' . $mixed[0] . '` TO `' . $lower . '`');
            } catch (Throwable $e) {
                // skip this table
            }
        }
        dms_db_tables_map(true);
    } catch (Throwable $e) {
        $done = false;
        dms_db_tables_map(true);
    }
}
