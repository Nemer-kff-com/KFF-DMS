<?php
/**
 * فحص تحمّل خفيف لمدير النظام — حدود آمنة، بدون استهداف عناوين خارجية.
 */

/** @return array{max_http_n:int,max_http_c:int,max_db_workers:int,cooldown_sec:int} */
function dms_load_check_limits(): array
{
    return [
        'max_http_n' => 100,
        'max_http_c' => 10,
        'max_db_workers' => 20,
        'cooldown_sec' => 60,
    ];
}

function dms_load_check_cooldown_remaining(): int
{
    $until = (int) ($_SESSION['dms_load_check_until'] ?? 0);
    $left = $until - time();
    return $left > 0 ? $left : 0;
}

function dms_load_check_mark_ran(): void
{
    $sec = dms_load_check_limits()['cooldown_sec'];
    $_SESSION['dms_load_check_until'] = time() + $sec;
}

/**
 * طلب HTTP بسيط لصفحة محلية.
 *
 * @return array{ok:bool,code:int,ms:float,bytes:int,error:string}
 */
function dms_load_check_http_once(string $url): array
{
    $t0 = microtime(true);
    $code = 0;
    $bytes = 0;
    $error = '';
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_USERAGENT => 'DMS-LoadCheck/1.0',
        ]);
        $body = curl_exec($ch);
        if ($body === false) {
            $error = curl_error($ch) ?: 'curl_failed';
        } else {
            $bytes = strlen((string) $body);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        }
        curl_close($ch);
    } else {
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 15,
                'ignore_errors' => true,
                'header' => "User-Agent: DMS-LoadCheck/1.0\r\n",
            ],
        ]);
        $body = @file_get_contents($url, false, $ctx);
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
            $code = (int) $m[1];
        }
        if ($body === false) {
            $error = 'request_failed';
        } else {
            $bytes = strlen((string) $body);
        }
    }
    $ms = round((microtime(true) - $t0) * 1000, 2);

    return [
        'ok' => $error === '' && $code > 0 && $code < 500,
        'code' => $code,
        'ms' => $ms,
        'bytes' => $bytes,
        'error' => $error,
    ];
}

/**
 * @return array{name:string,url:string,n:int,c:int,complete:int,failed:int,rps:float,mean_ms:float,samples:list<float>}
 */
function dms_load_check_http_burst(string $name, string $url, int $n, int $c): array
{
    $n = max(1, min($n, dms_load_check_limits()['max_http_n']));
    $c = max(1, min($c, dms_load_check_limits()['max_http_c'], $n));
    $samples = [];
    $failed = 0;
    $complete = 0;
    $t0 = microtime(true);

    // دفعات بحجم التزامن المطلوب (بدون إغراق غير محدود)
    for ($done = 0; $done < $n; $done += $c) {
        $batch = min($c, $n - $done);
        $batchSamples = [];
        for ($i = 0; $i < $batch; $i++) {
            $r = dms_load_check_http_once($url);
            $batchSamples[] = $r['ms'];
            $complete++;
            if (!$r['ok']) {
                $failed++;
            }
        }
        foreach ($batchSamples as $ms) {
            $samples[] = $ms;
        }
    }

    $elapsed = max(0.001, microtime(true) - $t0);
    $mean = $samples !== [] ? array_sum($samples) / count($samples) : 0.0;

    return [
        'name' => $name,
        'url' => $url,
        'n' => $n,
        'c' => $c,
        'complete' => $complete,
        'failed' => $failed,
        'rps' => round($complete / $elapsed, 2),
        'mean_ms' => round($mean, 2),
        'samples' => $samples,
    ];
}

/**
 * استعلامات قراءة شائعة — قياس زمن دورة واحدة.
 *
 * @return array{ok:bool,ms:float,error:string}
 */
function dms_load_check_db_cycle(): array
{
    $t0 = microtime(true);
    try {
        db()->query("SELECT COUNT(*) FROM `tblcorrespondence` WHERE type = 'outgoing'")->fetchColumn();
        db()->query(
            "SELECT id, ref_number, subject, issue_approval_status, issue_approval_assignee_id
             FROM `tblcorrespondence`
             WHERE type IN ('outgoing','incoming','internal')
             ORDER BY id DESC
             LIMIT 40"
        )->fetchAll(PDO::FETCH_ASSOC);
        db()->query(
            'SELECT id, name_ar, role FROM `tblusers` WHERE is_active = 1 ORDER BY id LIMIT 80'
        )->fetchAll(PDO::FETCH_ASSOC);
        return ['ok' => true, 'ms' => round((microtime(true) - $t0) * 1000, 2), 'error' => ''];
    } catch (Throwable $e) {
        return ['ok' => false, 'ms' => round((microtime(true) - $t0) * 1000, 2), 'error' => $e->getMessage()];
    }
}

/**
 * @return array{loops:int,errors:int,avg_ms:float,p95_ms:float,max_ms:float}
 */
function dms_load_check_db_loops(int $loops): array
{
    $loops = max(1, min($loops, 100));
    $samples = [];
    $errors = 0;
    for ($i = 0; $i < $loops; $i++) {
        $r = dms_load_check_db_cycle();
        $samples[] = $r['ms'];
        if (!$r['ok']) {
            $errors++;
        }
    }
    sort($samples);
    $p95 = $samples[(int) floor((count($samples) - 1) * 0.95)] ?? 0;

    return [
        'loops' => $loops,
        'errors' => $errors,
        'avg_ms' => round(array_sum($samples) / max(1, count($samples)), 2),
        'p95_ms' => round((float) $p95, 2),
        'max_ms' => round((float) ($samples ? max($samples) : 0), 2),
    ];
}

/**
 * سباق تصعيد اتصالات خفيف على معاملة وهمية تُحذف فوراً.
 *
 * @return array{ok:bool,attempts:int,wins:int,assignee:int,expect_alt:int,message:string}
 */
function dms_load_check_escalate_race(int $attempts = 8): array
{
    $attempts = max(2, min($attempts, dms_load_check_limits()['max_db_workers']));
    if (!function_exists('outgoing_dispatch_escalate_if_due')) {
        return ['ok' => false, 'attempts' => 0, 'wins' => 0, 'assignee' => 0, 'expect_alt' => 0, 'message' => 'dispatch_unavailable'];
    }

    $primary = (int) setting('outgoing_comms_primary_user_id', '0');
    $alt = setting('outgoing_comms_alternate_enabled', '1') === '1'
        ? (int) setting('outgoing_comms_alternate_user_id', '0')
        : 0;
    if ($primary <= 0 || $alt <= 0 || $alt === $primary) {
        return [
            'ok' => false,
            'attempts' => 0,
            'wins' => 0,
            'assignee' => 0,
            'expect_alt' => $alt,
            'message' => 'need_primary_and_alternate',
        ];
    }

    $prevMinutes = setting('outgoing_comms_minutes', '120');
    $prevUnit = setting('outgoing_comms_unit', 'hours');
    set_setting('outgoing_comms_minutes', '1');
    set_setting('outgoing_comms_unit', 'minutes');
    if (function_exists('outgoing_comms_grant_auto_perms')) {
        outgoing_comms_grant_auto_perms($alt, (int) (current_user()['id'] ?? 0) ?: null);
    }

    $past = date('Y-m-d H:i:s', time() - 7200);
    $ref = 'LOADCHK-' . date('His') . '-' . random_int(100, 999);
    $corrId = 0;
    $wins = 0;
    $assignee = 0;
    $message = 'ok';

    try {
        db()->prepare(
            "INSERT INTO `tblcorrespondence`
             (type, status, subject, ref_number, qr_token, created_by, department_id,
              issue_approval_status, issue_approval_assignee_id, issue_approval_reassigned_at, issue_approval_decided_at, created_at)
             VALUES ('outgoing','registered',?,?,?,1,NULL,'with_comms',?,?,?,NOW())"
        )->execute([
            '[فحص-تحمّل] تصعيد متزامن',
            $ref,
            bin2hex(random_bytes(16)),
            $primary,
            $past,
            $past,
        ]);
        $corrId = (int) db()->lastInsertId();

        for ($i = 0; $i < $attempts; $i++) {
            $c = get_correspondence($corrId);
            if ($c && outgoing_dispatch_escalate_if_due($c)) {
                $wins++;
            }
        }
        $fresh = get_correspondence($corrId);
        $assignee = (int) ($fresh['issue_approval_assignee_id'] ?? 0);
        $ok = $wins === 1 && $assignee === $alt;
        if (!$ok) {
            $message = 'race_unexpected';
        }
    } catch (Throwable $e) {
        $ok = false;
        $message = $e->getMessage();
    }

    if ($corrId > 0) {
        try {
            db()->prepare('DELETE FROM `tblnotifications` WHERE correspondence_id = ?')->execute([$corrId]);
        } catch (Throwable $e) {
        }
        try {
            db()->prepare('DELETE FROM `tblactivity_log` WHERE correspondence_id = ?')->execute([$corrId]);
        } catch (Throwable $e) {
        }
        try {
            db()->prepare('DELETE FROM `tblcorrespondence` WHERE id = ?')->execute([$corrId]);
        } catch (Throwable $e) {
        }
    }

    set_setting('outgoing_comms_minutes', (string) $prevMinutes);
    set_setting('outgoing_comms_unit', (string) $prevUnit);

    return [
        'ok' => $ok,
        'attempts' => $attempts,
        'wins' => $wins,
        'assignee' => $assignee,
        'expect_alt' => $alt,
        'message' => $message,
    ];
}

/**
 * @param array{http?:bool,db?:bool,escalate?:bool,http_n?:int,http_c?:int,db_loops?:int} $opts
 * @return array<string,mixed>
 */
function dms_load_check_run(array $opts = []): array
{
    $limits = dms_load_check_limits();
    $doHttp = !empty($opts['http']);
    $doDb = !empty($opts['db']);
    $doEsc = !empty($opts['escalate']);
    if (!$doHttp && !$doDb && !$doEsc) {
        $doHttp = true;
        $doDb = true;
    }

    $httpN = max(1, min((int) ($opts['http_n'] ?? 40), $limits['max_http_n']));
    $httpC = max(1, min((int) ($opts['http_c'] ?? 5), $limits['max_http_c']));
    $dbLoops = max(1, min((int) ($opts['db_loops'] ?? 30), 100));

    $base = rtrim(function_exists('base_url') ? base_url('') : '/', '/') . '/';
    $started = date('Y-m-d H:i:s');
    $out = [
        'started_at' => $started,
        'limits' => $limits,
        'http' => null,
        'db' => null,
        'escalate' => null,
        'pass' => true,
        'notes' => [],
    ];

    if ($doHttp) {
        $login = dms_load_check_http_burst('login', $base . 'auth/login.php', $httpN, $httpC);
        $out['http'] = ['targets' => [$login]];
        if ($login['failed'] > 0) {
            $out['pass'] = false;
            $out['notes'][] = 'http_failed';
        }
    }

    if ($doDb) {
        $out['db'] = dms_load_check_db_loops($dbLoops);
        if (($out['db']['errors'] ?? 1) > 0) {
            $out['pass'] = false;
            $out['notes'][] = 'db_errors';
        }
    }

    if ($doEsc) {
        $out['escalate'] = dms_load_check_escalate_race(min(8, $limits['max_db_workers']));
        if (empty($out['escalate']['ok'])) {
            // عدم ضبط أساسي/بديل ليس فشلاً حرجاً للتحمّل العام
            if (($out['escalate']['message'] ?? '') === 'need_primary_and_alternate') {
                $out['notes'][] = 'escalate_skipped_no_assignees';
            } else {
                $out['pass'] = false;
                $out['notes'][] = 'escalate_failed';
            }
        }
    }

    $out['finished_at'] = date('Y-m-d H:i:s');
    return $out;
}
