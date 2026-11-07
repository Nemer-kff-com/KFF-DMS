<?php
/**
 * إعادة التقاط لقطات دليل المستخدم لتطابق كل عنوان/قسم.
 * التشغيل (جلسة متصفح مسجّلة):
 *   php help/capture_guide_shots.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

$root = dirname(__DIR__);
$chrome = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
if (!is_file($chrome)) {
    fwrite(STDERR, "Chrome not found\n");
    exit(1);
}

require_once $root . '/config/db.php';
$pdo = db();

$adminId = 0;
try {
    $adminId = (int) $pdo->query(
        "SELECT id FROM tblusers WHERE role='admin' AND COALESCE(is_active,1)=1 ORDER BY id ASC LIMIT 1"
    )->fetchColumn();
} catch (Throwable $e) {
    $adminId = 0;
}

$sid = '';
$best = 0;
$adminSid = '';
$adminBest = 0;
foreach (glob($root . '/sessions/sess_*') ?: [] as $f) {
    if (filesize($f) < 80) {
        continue;
    }
    $raw = (string) @file_get_contents($f);
    $mtime = filemtime($f);
    $id = substr(basename($f), 5);
    if ($adminId > 0 && $raw !== '' && str_contains($raw, 'user_id|i:' . $adminId . ';')) {
        if ($mtime > $adminBest) {
            $adminBest = $mtime;
            $adminSid = $id;
        }
    }
    if (filesize($f) >= 200 && $mtime > $best) {
        $best = $mtime;
        $sid = $id;
    }
}
if ($adminSid !== '') {
    $sid = $adminSid;
    @touch($root . '/sessions/sess_' . $sid);
}
if ($sid === '') {
    fwrite(STDERR, "No live session. Open the app as admin first.\n");
    exit(1);
}
echo "session={$sid}" . ($adminSid !== '' ? " (admin#{$adminId})" : '') . "\n";

$outId = (int) $pdo->query(
    "SELECT id FROM tblcorrespondence WHERE type='outgoing' AND (cancel_kind IS NULL OR cancel_kind='') ORDER BY id DESC LIMIT 1"
)->fetchColumn();
$outElec = 0;
try {
    $outElec = (int) $pdo->query(
        "SELECT id FROM tblcorrespondence WHERE type='outgoing' AND COALESCE(intend_electronic,0)=1
         AND (cancel_kind IS NULL OR cancel_kind='') ORDER BY id DESC LIMIT 1"
    )->fetchColumn();
} catch (Throwable $e) {
    $outElec = 0;
}
if ($outElec <= 0) {
    $outElec = $outId;
}
$inId = (int) $pdo->query(
    "SELECT id FROM tblcorrespondence WHERE type='incoming' ORDER BY id DESC LIMIT 1"
)->fetchColumn();
echo "out={$outId} outElec={$outElec} in={$inId}\n";

$outDir = $root . '/doc/images/user-guide';
$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dms_guide_shots';
if (!is_dir($tmp)) {
    mkdir($tmp, 0700, true);
}

$bootUrl = static function (string $path, string $anchor = '') use ($sid): string {
    $q = 'sid=' . rawurlencode($sid) . '&to=' . rawurlencode(ltrim($path, '/'));
    if ($anchor !== '') {
        $q .= '&anchor=' . rawurlencode($anchor);
    }
    return 'http://localhost/dms/help/snap_boot.php?' . $q;
};

/** قصّ اللقطة إلى ارتفاع النافذة المطلوب وإزالة شريط فارغ سفلي إن وُجد */
$cropShot = static function (string $path, int $maxH): void {
    if (!function_exists('imagecreatefrompng') || !is_file($path) || $maxH < 200) {
        return;
    }
    $src = @imagecreatefrompng($path);
    if (!$src) {
        return;
    }
    $w = imagesx($src);
    $h = imagesy($src);
    if ($w < 10 || $h < 10) {
        imagedestroy($src);
        return;
    }
    $limit = min($h, $maxH);
    // ابحث عن آخر صف غير فارغ داخل الحد
    $last = 0;
    for ($y = $limit - 1; $y >= 0; $y--) {
        $rowBusy = false;
        for ($x = 0; $x < $w; $x += 6) {
            $rgb = imagecolorat($src, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;
            // خلفية كريمية/رمادية فاتحة ≈ فارغ
            if ($r < 235 || $g < 235 || $b < 230) {
                $rowBusy = true;
                break;
            }
        }
        if ($rowBusy) {
            $last = $y;
            break;
        }
    }
    $newH = min($limit, max($last + 20, 360));
    if ($newH >= $h - 2 && $h <= $maxH) {
        imagedestroy($src);
        return;
    }
    $dst = imagecreatetruecolor($w, $newH);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    imagecopy($dst, $src, 0, 0, 0, 0, $w, $newH);
    imagedestroy($src);
    imagepng($dst, $path);
    imagedestroy($dst);
};

$capture = static function (string $url, string $dest, int $w, int $h) use ($chrome, $tmp, $cropShot): bool {
    $png = $tmp . DIRECTORY_SEPARATOR . 'c_' . md5($dest . microtime(true)) . '.png';
    $prof = $tmp . DIRECTORY_SEPARATOR . 'prof_' . substr(md5($dest), 0, 10);
    if (!is_dir($prof)) {
        mkdir($prof, 0700, true);
    }
    @unlink($png);
    $args = [
        $chrome,
        '--headless=new',
        '--disable-gpu',
        '--hide-scrollbars',
        '--no-first-run',
        '--window-size=' . $w . ',' . $h,
        '--virtual-time-budget=40000',
        '--user-data-dir=' . $prof,
        '--screenshot=' . $png,
        $url,
    ];
    $des = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $proc = proc_open($args, $des, $pipes, null, null, ['bypass_shell' => true]);
    if (is_resource($proc)) {
        fclose($pipes[0]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);
    }
    clearstatcache(true, $png);
    if (!is_file($png) || filesize($png) < 12000) {
        echo 'FAIL ' . basename($dest) . ' (' . (is_file($png) ? filesize($png) : 0) . ")\n";
        return false;
    }
    $cropShot($png, $h);
    @unlink($dest);
    for ($i = 0; $i < 10; $i++) {
        if (@rename($png, $dest) || @copy($png, $dest)) {
            @unlink($png);
            echo 'OK ' . basename($dest) . ' ' . filesize($dest) . "\n";
            return true;
        }
        usleep(300000);
    }
    echo 'FAIL copy ' . basename($dest) . "\n";
    return false;
};

/** @var list<array{file:string,url:string,w:int,h:int}> $shots */
$shots = [
    ['file' => '01-login.png', 'url' => 'http://localhost/dms/auth/login.php', 'w' => 1280, 'h' => 820],
    ['file' => '06-topbar.png', 'url' => $bootUrl('index.php'), 'w' => 1440, 'h' => 220],
    ['file' => '02-home.png', 'url' => $bootUrl('index.php'), 'w' => 1440, 'h' => 900],
    ['file' => '23-menu.png', 'url' => $bootUrl('outgoing/index.php'), 'w' => 1440, 'h' => 900],
    ['file' => '07-lists.png', 'url' => $bootUrl('outgoing/index.php'), 'w' => 1440, 'h' => 900],
    ['file' => '03-outgoing.png', 'url' => $bootUrl('outgoing/create.php'), 'w' => 1440, 'h' => 900],
    ['file' => '25-priority.png', 'url' => $bootUrl('outgoing/create.php', 'corr-priority'), 'w' => 1440, 'h' => 900],
    // أعلى النموذج فقط (بدون مربع نص طويل فارغ)
    ['file' => '08-bulk.png', 'url' => $bootUrl('outgoing/bulk.php'), 'w' => 1440, 'h' => 900],
    // قسم نص الخطاب فقط
    ['file' => '08-bulk-letter.png', 'url' => $bootUrl('outgoing/bulk.php', 'outgoing-letter-section'), 'w' => 1440, 'h' => 820],
    ['file' => '05-incoming.png', 'url' => $bootUrl('incoming/create.php'), 'w' => 1440, 'h' => 900],
    ['file' => '29-internal.png', 'url' => $bootUrl('internal/create.php'), 'w' => 1440, 'h' => 900],
    ['file' => '30-cc.png', 'url' => $bootUrl('cc/index.php'), 'w' => 1440, 'h' => 900],
    ['file' => '13-reports.png', 'url' => $bootUrl('reports/index.php'), 'w' => 1440, 'h' => 900],
    ['file' => '14-circulars.png', 'url' => $bootUrl('circulars/index.php'), 'w' => 1440, 'h' => 900],
    ['file' => '15-archive.png', 'url' => $bootUrl('archive/index.php'), 'w' => 1440, 'h' => 900],
    ['file' => '19-actions.png', 'url' => $bootUrl('correspondence/actions.php'), 'w' => 1440, 'h' => 900],
    ['file' => '16-entities.png', 'url' => $bootUrl('admin/entities.php'), 'w' => 1440, 'h' => 900],
    ['file' => '17-groups.png', 'url' => $bootUrl('admin/entity_groups.php'), 'w' => 1440, 'h' => 900],
    ['file' => '18-templates.png', 'url' => $bootUrl('admin/letter_templates.php'), 'w' => 1440, 'h' => 900],
    ['file' => '20-verify.png', 'url' => 'http://localhost/dms/verify.php', 'w' => 1200, 'h' => 800],
    ['file' => '26-profile.png', 'url' => $bootUrl('auth/profile.php'), 'w' => 1440, 'h' => 900],
    ['file' => '28-referrals-list.png', 'url' => $bootUrl('referrals/index.php'), 'w' => 1440, 'h' => 900],
];

if ($inId > 0) {
    $shots[] = ['file' => '22-refer-form.png', 'url' => $bootUrl('incoming/view.php?id=' . $inId, 'corr-referrals'), 'w' => 1440, 'h' => 1100];
    $shots[] = ['file' => '10-comments.png', 'url' => $bootUrl('incoming/view.php?id=' . $inId, 'corr-comments'), 'w' => 1440, 'h' => 1100];
    $shots[] = ['file' => '11-brief.png', 'url' => $bootUrl('correspondence/brief.php?id=' . $inId), 'w' => 1440, 'h' => 900];
}
if ($outId > 0) {
    $shots[] = ['file' => '04-attach-send.png', 'url' => $bootUrl('outgoing/view.php?id=' . $outId, 'corr-attachments'), 'w' => 1440, 'h' => 1100];
    $shots[] = ['file' => '09-receipt.png', 'url' => $bootUrl('outgoing/view.php?id=' . $outId, 'corr-delivery'), 'w' => 1440, 'h' => 1100];
    $shots[] = ['file' => '12-print-qr.png', 'url' => $bootUrl('outgoing/view.php?id=' . $outId, 'corr-qr'), 'w' => 1440, 'h' => 900];
    $shots[] = ['file' => '27-cancel.png', 'url' => $bootUrl('outgoing/view.php?id=' . $outId), 'w' => 1440, 'h' => 720];
}
if ($outElec > 0) {
    $shots[] = ['file' => '24-send.png', 'url' => $bootUrl('outgoing/view.php?id=' . $outElec, 'corr-send'), 'w' => 1440, 'h' => 1100];
}

$ok = 0;
$fail = 0;
foreach ($shots as $spec) {
    $dest = $outDir . DIRECTORY_SEPARATOR . $spec['file'];
    if ($capture($spec['url'], $dest, (int) $spec['w'], (int) $spec['h'])) {
        $ok++;
    } else {
        $fail++;
    }
}

// أزل نسخة قديمة مكررة إن وُجدت
@unlink($outDir . '/21-documents.png');
@unlink($outDir . '/22-refer-close.png');
@unlink($root . '/help/_guide_jump.html');

echo "Done ok={$ok} fail={$fail}\n";
exit($fail > 0 ? 2 : 0);
