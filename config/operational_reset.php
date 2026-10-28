<?php
/**
 * إعادة ضبط بيانات العمل التشغيلية (معاملات / أرشيف / تعاميم / مرفقات)
 * مع الإبقاء على المستخدمين والإعدادات وجهات المراسلة.
 */
declare(strict_types=1);

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
    http_response_code(403);
    exit('Forbidden');
}

/**
 * حذف شجرة ملفات داخل uploads مع احتساب عدد الملفات المحذوفة.
 */
function dms_rm_tree_count(string $path): int
{
    if (!is_dir($path) && !is_file($path) && !is_link($path)) {
        return 0;
    }
    if (is_file($path) || is_link($path)) {
        return @unlink($path) ? 1 : 0;
    }
    $count = 0;
    $items = @scandir($path);
    if (!is_array($items)) {
        return 0;
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $count += dms_rm_tree_count($path . DIRECTORY_SEPARATOR . $item);
    }
    @rmdir($path);
    return $count;
}

/** مسح مجلدات مرفقات المعاملات مع الإبقاء على الشعارات والأفاتار في assets/uploads. */
function dms_wipe_operational_upload_dirs(): int
{
    $removed = 0;

    // 1) جذر التخزين الجديد فقط — يُعاد إنشاء مجلدات المعاملات فيه
    if (function_exists('dms_storage_root')) {
        $storageRoot = realpath(dms_storage_root());
        if ($storageRoot !== false && is_dir($storageRoot)) {
            $entries = @scandir($storageRoot);
            if (is_array($entries)) {
                foreach ($entries as $entry) {
                    if ($entry === '.' || $entry === '..' || $entry === '.htaccess') {
                        continue;
                    }
                    $full = $storageRoot . DIRECTORY_SEPARATOR . $entry;
                    // ملفات العلامة مثل .migrated_from_assets تُحذف؛ المجلدات تُفحص بـ realpath
                    if (is_file($full) || is_link($full)) {
                        $removed += @unlink($full) ? 1 : 0;
                        continue;
                    }
                    $real = realpath($full);
                    if ($real === false || !str_starts_with($real, $storageRoot)) {
                        continue;
                    }
                    $removed += dms_rm_tree_count($real);
                }
            }
            foreach (['incoming', 'outgoing', 'internal', 'archive', 'circulars', 'receipts'] as $dir) {
                @mkdir($storageRoot . DIRECTORY_SEPARATOR . $dir, 0755, true);
            }
        }
    }

    // 2) الأصول القديمة: احذف مجلدات المعاملات فقط، ولا تُعد إنشاؤها هنا
    $legacy = realpath(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'uploads');
    if ($legacy !== false && is_dir($legacy)) {
        foreach (['incoming', 'outgoing', 'internal', 'archive', 'circulars', 'receipts'] as $dir) {
            $full = $legacy . DIRECTORY_SEPARATOR . $dir;
            if (!is_dir($full) && !is_file($full)) {
                continue;
            }
            $real = realpath($full);
            if ($real === false || !str_starts_with($real, $legacy)) {
                continue;
            }
            $removed += dms_rm_tree_count($real);
        }
    }

    return $removed;
}

/**
 * جداول بيانات العمل التي تُمسح عند إعادة الضبط.
 * @return list<string>
 */
function dms_operational_reset_tables(): array
{
    return [
        'tblcorr_rsvp_log',
        'tblcorr_deliveries',
        'tblcomments',
        'tblreferrals',
        'tbldocuments',
        'tblcorr_attachments',
        'tblcorrespondence_briefs',
        'tblcorrespondence',
        'tbloutgoing_batches',
        'tblcircular_files',
        'tblcircular_departments',
        'tblcirculars',
        'tblarchive_files',
        'tblarchive_items',
        'tblarchive_seq',
        'tblnotifications',
        'tblactivity_log',
        'tblsequences',
        'tblcircular_sequences',
    ];
}

/**
 * نطاقات عدّاد الترقيم لنوع معاملة (العام + بادئات الأقسام الخاصة).
 * @return list<string>
 */
function dms_operational_sequence_scopes(string $type): array
{
    if ($type === 'internal') {
        return [''];
    }
    $type = $type === 'outgoing' ? 'outgoing' : 'incoming';
    $scopes = [''];
    if (!function_exists('corr_global_prefix') || !function_exists('corr_sanitize_prefix')) {
        return $scopes;
    }
    $global = corr_global_prefix($type);
    $col = $type === 'incoming' ? 'incoming_prefix' : 'outgoing_prefix';
    try {
        if (!dms_db_table_exists('tbldepartments') || !dms_db_column_exists('tbldepartments', $col)) {
            return $scopes;
        }
        $rows = db()->query(
            "SELECT DISTINCT `$col` AS p FROM `tbldepartments`
             WHERE `$col` IS NOT NULL AND TRIM(`$col`) <> ''"
        )->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $prefix = corr_sanitize_prefix((string) ($row['p'] ?? ''));
            if ($prefix === '' || $prefix === $global) {
                continue;
            }
            $scopes[] = mb_substr($prefix, 0, 40);
        }
    } catch (Throwable $e) {
        // ignore
    }
    return array_values(array_unique($scopes));
}

/**
 * تهيئة عدادات الترقيم بعد المسح بحيث يكون الرقم التالي = القيمة المطلوبة.
 */
function dms_operational_reset_seed_numbering(
    int $incomingStart,
    int $outgoingStart,
    int $circularStart,
    int $internalStart = 1,
    int $archiveStart = 1
): array {
    $incomingStart = max(1, min(999999, $incomingStart));
    $outgoingStart = max(1, min(999999, $outgoingStart));
    $circularStart = max(1, min(999999, $circularStart));
    $internalStart = max(1, min(999999, $internalStart));
    $archiveStart = max(1, min(999999, $archiveStart));
    $year = function_exists('corr_numbering_year') ? corr_numbering_year() : ((int) date('Y'));
    $pdo = db();

    if (function_exists('ensure_sequences_schema')) {
        ensure_sequences_schema();
    }
    if (function_exists('ensure_internal_correspondence_schema')) {
        ensure_internal_correspondence_schema();
    }
    if (dms_db_table_exists('tblsequences')) {
        $ins = $pdo->prepare(
            'INSERT INTO `tblsequences` (seq_type, seq_year, seq_scope, last_number)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE last_number = VALUES(last_number)'
        );
        foreach (dms_operational_sequence_scopes('incoming') as $scope) {
            $ins->execute(['incoming', $year, $scope, $incomingStart - 1]);
        }
        foreach (dms_operational_sequence_scopes('outgoing') as $scope) {
            $ins->execute(['outgoing', $year, $scope, $outgoingStart - 1]);
        }
        foreach (dms_operational_sequence_scopes('internal') as $scope) {
            $ins->execute(['internal', $year, $scope, $internalStart - 1]);
        }
        $ins->execute(['archive', $year, '', $archiveStart - 1]);
        $ins->execute(['circular', $year, '', $circularStart - 1]);
    }

    if (function_exists('ensure_circulars_schema')) {
        ensure_circulars_schema();
    }
    if (dms_db_table_exists('tblcircular_sequences')) {
        $pdo->prepare(
            'INSERT INTO `tblcircular_sequences` (seq_year, last_number)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE last_number = VALUES(last_number)'
        )->execute([$year, $circularStart - 1]);
    }

    return [
        'year' => $year,
        'incoming_start' => $incomingStart,
        'outgoing_start' => $outgoingStart,
        'internal_start' => $internalStart,
        'circular_start' => $circularStart,
        'archive_start' => $archiveStart,
    ];
}

/**
 * تنفيذ إعادة ضبط بيانات العمل.
 * @param array{incoming_start?:int,outgoing_start?:int,internal_start?:int,circular_start?:int} $options
 * @return array{tables: array<string,int>, files_removed: int, numbering: array{year:int,incoming_start:int,outgoing_start:int,internal_start:int,circular_start:int}}
 */
function dms_operational_reset(array $options = []): array
{
    $pdo = db();
    $stats = ['tables' => [], 'files_removed' => 0, 'numbering' => []];

    $incomingStart = max(1, min(999999, (int) ($options['incoming_start'] ?? 1)));
    $outgoingStart = max(1, min(999999, (int) ($options['outgoing_start'] ?? 1)));
    $internalStart = max(1, min(999999, (int) ($options['internal_start'] ?? 1)));
    $circularStart = max(1, min(999999, (int) ($options['circular_start'] ?? 1)));
    $archiveStart = max(1, min(999999, (int) ($options['archive_start'] ?? 1)));

    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    try {
        foreach (dms_operational_reset_tables() as $table) {
            $table = dms_db_table_name($table);
            if ($table === '' || !dms_db_table_exists($table)) {
                continue;
            }
            $deleted = (int) $pdo->exec('DELETE FROM `' . str_replace('`', '``', $table) . '`');
            $stats['tables'][$table] = $deleted;
            try {
                $pdo->exec('ALTER TABLE `' . str_replace('`', '``', $table) . '` AUTO_INCREMENT = 1');
            } catch (Throwable $e) {
                // بعض الجداول بلا AUTO_INCREMENT
            }
        }
    } finally {
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    }

    $stats['numbering'] = dms_operational_reset_seed_numbering(
        $incomingStart,
        $outgoingStart,
        $circularStart,
        $internalStart,
        $archiveStart
    );
    $stats['files_removed'] = dms_wipe_operational_upload_dirs();
    return $stats;
}
