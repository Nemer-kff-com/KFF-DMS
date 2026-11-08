<?php
/**
 * قوالب نص الخطاب للصادر الجماعي والمفرد
 */

function ensure_letter_templates_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $pdo = db();
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `tblletter_templates` (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(40) NULL DEFAULT NULL,
                name_ar VARCHAR(160) NOT NULL,
                name_en VARCHAR(160) NULL DEFAULT NULL,
                body TEXT NOT NULL,
                department_id INT UNSIGNED NULL DEFAULT NULL COMMENT 'NULL = قالب عام',
                created_by INT UNSIGNED NULL DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_tpl_dept (department_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $cols = dms_db_column_map('tblletter_templates');
        if (empty($cols['body_en'])) {
            $pdo->exec('ALTER TABLE `tblletter_templates` ADD COLUMN `body_en` TEXT NULL DEFAULT NULL AFTER `body`');
            $cols['body_en'] = true;
        }
        foreach ([
            'show_addressee_title' => 'TINYINT(1) NOT NULL DEFAULT 1',
            'show_addressee_job' => 'TINYINT(1) NOT NULL DEFAULT 1',
            'show_addressee_org' => 'TINYINT(1) NOT NULL DEFAULT 1',
            'show_signature' => 'TINYINT(1) NOT NULL DEFAULT 1',
        ] as $col => $def) {
            if (empty($cols[$col])) {
                $pdo->exec('ALTER TABLE `tblletter_templates` ADD COLUMN `' . $col . '` ' . $def);
            }
        }
        $batchCols = dms_db_column_map('tbloutgoing_batches');
        if ($batchCols && empty($batchCols['letter_template_id'])) {
            $pdo->exec('ALTER TABLE `tbloutgoing_batches` ADD COLUMN `letter_template_id` INT UNSIGNED NULL DEFAULT NULL');
        }
        dms_seed_letter_templates();
    } catch (Throwable $e) {
        error_log('DMS letter templates schema: ' . $e->getMessage());
    }
}

function dms_seed_letter_templates(): void
{
    try {
        $count = (int) db()->query('SELECT COUNT(*) FROM `tblletter_templates`')->fetchColumn();
        if ($count > 0) {
            return;
        }
        $rows = [
            [
                'invitation',
                'دعوة',
                'Invitation',
                "السلام عليكم ورحمة الله وبركاته\n\nيسرنا دعوة {honored_name} لحضور {subject}.\n\nوتفضلوا بقبول فائق الاحترام والتقدير.",
                "Peace be upon you\n\nWe are pleased to invite {honored_name} to attend {subject}.\n\nWith our highest regards.",
            ],
            [
                'circular',
                'تعميم',
                'Circular',
                "السلام عليكم ورحمة الله وبركاته\n\nتعميم إلى {honored_name}\n\nنود إشعاركم بشأن {subject}، ونأمل الاطلاع واتخاذ ما يلزم.\n\nوتفضلوا بقبول فائق الاحترام.",
                "Peace be upon you\n\nCircular to {honored_name}\n\nPlease be informed regarding {subject}. We kindly ask you to review and take the necessary action.\n\nWith our highest regards.",
            ],
            [
                'reply',
                'رد على خطاب',
                'Reply',
                "السلام عليكم ورحمة الله وبركاته\n\nإشارة إلى خطابكم، نفيد {honored_name} بما يلي بخصوص {subject}.\n\nوتفضلوا بقبول فائق الاحترام والتقدير.",
                "Peace be upon you\n\nWith reference to your letter, we inform {honored_name} of the following regarding {subject}.\n\nWith our highest regards.",
            ],
        ];
        $stmt = db()->prepare(
            "INSERT INTO `tblletter_templates` (code, name_ar, name_en, body, body_en, department_id)
             VALUES (?, ?, ?, ?, ?, NULL)"
        );
        foreach ($rows as $row) {
            $stmt->execute($row);
        }
    } catch (Throwable $e) {
        error_log('DMS seed templates: ' . $e->getMessage());
    }
}

function letter_templates_list(?array $user = null, bool $usableOnly = true): array
{
    ensure_letter_templates_schema();
    $user = $user ?? current_user();
    try {
        $sql = "SELECT t.*, d.name AS department_name, d.name_en AS department_name_en
                FROM `tblletter_templates` t
                LEFT JOIN `tbldepartments` d ON d.id = t.department_id
                WHERE 1=1";
        $params = [];
        if (
            $usableOnly
            && $user
            && ($user['role'] ?? '') === 'department'
            && function_exists('user_department_ids')
            && user_department_ids($user)
        ) {
            [$inSql, $inParams] = dms_sql_in('t.department_id', user_department_ids($user));
            $sql .= ' AND (t.department_id IS NULL OR ' . $inSql . ')';
            $params = array_merge($params, $inParams);
        }
        $sql .= ' ORDER BY t.department_id IS NOT NULL, t.id';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function get_letter_template(int $id): ?array
{
    ensure_letter_templates_schema();
    $stmt = db()->prepare('SELECT * FROM `tblletter_templates` WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function user_can_edit_letter_template(?array $tpl, ?array $user = null): bool
{
    $user = $user ?? current_user();
    if (!$user || !user_can('templates.edit', $user)) {
        return false;
    }
    if (!$tpl) {
        return true;
    }
    $dept = (int) ($tpl['department_id'] ?? 0);
    if ($dept === 0) {
        return can_delete($user);
    }
    if (can_delete($user) || can_view_all_departments($user)) {
        return true;
    }
    return function_exists('user_can_access_department') && user_can_access_department($dept, $user);
}

function save_letter_template(array $data, array $user, ?int $id = null): int
{
    ensure_letter_templates_schema();
    if (!user_can('templates.edit', $user)) {
        throw new RuntimeException(__('auth.forbidden'));
    }
    $nameAr = trim((string) ($data['name_ar'] ?? ''));
    $nameEn = trim((string) ($data['name_en'] ?? '')) ?: null;
    $body = trim((string) ($data['body'] ?? ''));
    $bodyEn = trim((string) ($data['body_en'] ?? '')) ?: null;
    $code = preg_replace('/[^a-z0-9_]/', '', strtolower(trim((string) ($data['code'] ?? '')))) ?: null;
    $deptId = (int) ($data['department_id'] ?? 0) ?: null;

    if ($nameAr === '' || $body === '') {
        throw new InvalidArgumentException(__('tpl.invalid'));
    }

    if (!can_choose_correspondence_department($user)) {
        $deptId = resolve_assigned_department_id($user, $deptId, false);
        if (!$deptId) {
            throw new RuntimeException(__('corr.department_account_required'));
        }
    } elseif ($deptId === null && !can_delete($user) && $id) {
        $existing = get_letter_template($id);
        if ($existing && (int) ($existing['department_id'] ?? 0) > 0) {
            $deptId = (int) $existing['department_id'];
        }
    }

    if ($id) {
        $existing = get_letter_template($id);
        if (!$existing || !user_can_edit_letter_template($existing, $user)) {
            throw new RuntimeException(__('auth.forbidden'));
        }
        if ((int) ($existing['department_id'] ?? 0) === 0 && !can_delete($user)) {
            throw new RuntimeException(__('auth.forbidden'));
        }
        db()->prepare(
            "UPDATE `tblletter_templates` SET code=?, name_ar=?, name_en=?, body=?, body_en=?, department_id=? WHERE id=?"
        )->execute([$code, $nameAr, $nameEn, $body, $bodyEn, $deptId, $id]);
        return $id;
    }

    if ($deptId === null && !can_delete($user)) {
        $deptId = resolve_assigned_department_id($user, 0, false);
        if (!$deptId) {
            throw new RuntimeException(__('corr.department_account_required'));
        }
    }

    db()->prepare(
        "INSERT INTO `tblletter_templates` (code, name_ar, name_en, body, body_en, department_id, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    )->execute([$code, $nameAr, $nameEn, $body, $bodyEn, $deptId, (int) $user['id']]);
    return (int) db()->lastInsertId();
}

function delete_letter_template(int $id, array $user): void
{
    $tpl = get_letter_template($id);
    if (!$tpl || !user_can_edit_letter_template($tpl, $user)) {
        throw new RuntimeException(__('auth.forbidden'));
    }
    db()->prepare('DELETE FROM `tblletter_templates` WHERE id = ?')->execute([$id]);
}

function letter_template_label(array $tpl): string
{
    return bilingual_label($tpl, 'name_ar', 'name_en');
}

/**
 * ما يُطبع ويُرسل من بيانات المستلم والتوقيع، حسب قالب الخطاب المرتبط.
 *
 * @return array{title:bool,job:bool,org:bool,signature:bool}
 */
function letter_display_flags(array $corr): array
{
    return ['title' => true, 'job' => true, 'org' => true, 'signature' => true];
}

/** يخفي اللقب أو الوظيفة أو جهة العمل من طرف الموجّه إليه. */
function letter_apply_addressee_flags(array $part, array $flags): array
{
    if (empty($flags['title'])) {
        $part['prefix'] = '';
    }
    if (empty($flags['job'])) {
        $part['job'] = '';
        $part['titles'] = [];
    }
    if (empty($flags['org'])) {
        $part['org'] = '';
    }

    return $part;
}

/**
 * حقول الإدراج في نص القالب — تُستبدل لكل مستلم عند الإرسال/التحقق/الطباعة
 * @return list<array{token:string,label:string,tip:string,icon:string}>
 */
function letter_template_placeholder_tokens(): array
{
    return [
        ['kind' => 'undo', 'label' => __('tpl.token_undo'), 'tip' => __('tpl.token_undo_tip'), 'icon' => 'bi-arrow-counterclockwise'],
        ['kind' => 'newline', 'label' => __('tpl.token_newline'), 'tip' => __('tpl.token_newline_tip'), 'icon' => 'bi-text-paragraph'],
        ['kind' => 'right', 'wrap' => ['{right}', '{/right}'], 'label' => __('tpl.token_right'), 'tip' => __('tpl.token_right_tip'), 'icon' => 'bi-text-right'],
        ['kind' => 'center', 'wrap' => ['{center}', '{/center}'], 'label' => __('tpl.token_center'), 'tip' => __('tpl.token_center_tip'), 'icon' => 'bi-text-center'],
        ['kind' => 'left', 'wrap' => ['{left}', '{/left}'], 'label' => __('tpl.token_left'), 'tip' => __('tpl.token_left_tip'), 'icon' => 'bi-text-left'],
        ['kind' => 'justify', 'wrap' => ['{justify}', '{/justify}'], 'label' => __('tpl.token_justify'), 'tip' => __('tpl.token_justify_tip'), 'icon' => 'bi-justify'],
        ['kind' => 'ul', 'wrap' => ['{ul}', '{/ul}'], 'para' => true, 'label' => __('tpl.token_ul'), 'tip' => __('tpl.token_ul_tip'), 'icon' => 'bi-list-ul'],
        ['kind' => 'bold', 'wrap' => ['{b}', '{/b}'], 'label' => __('tpl.token_bold'), 'tip' => __('tpl.token_bold_tip'), 'icon' => 'bi-type-bold'],
        ['kind' => 'italic', 'wrap' => ['{i}', '{/i}'], 'label' => __('tpl.token_italic'), 'tip' => __('tpl.token_italic_tip'), 'icon' => 'bi-type-italic'],
        ['kind' => 'underline', 'wrap' => ['{u}', '{/u}'], 'label' => __('tpl.token_underline'), 'tip' => __('tpl.token_underline_tip'), 'icon' => 'bi-type-underline'],
        ['kind' => 'big', 'wrap' => ['{big}', '{/big}'], 'label' => __('tpl.token_big'), 'tip' => __('tpl.token_big_tip'), 'icon' => 'bi-type-h2'],
        ['token' => '{number}', 'label' => __('tpl.token_number'), 'tip' => __('tpl.token_number_tip'), 'icon' => 'bi-hash'],
        ['token' => '{title}', 'label' => __('tpl.token_title'), 'tip' => __('tpl.token_title_tip'), 'icon' => 'bi-award'],
        ['token' => '{name}', 'label' => __('tpl.token_name'), 'tip' => __('tpl.token_name_tip'), 'icon' => 'bi-person'],
        ['token' => '{title} {name}', 'label' => __('tpl.token_title_name'), 'tip' => __('tpl.token_title_name_tip'), 'icon' => 'bi-person-vcard'],
        ['token' => '{honored_name}', 'label' => __('tpl.token_honored'), 'tip' => __('tpl.token_honored_tip'), 'icon' => 'bi-person-badge'],
        ['token' => '{honorific}', 'label' => __('tpl.token_honorific'), 'tip' => __('tpl.token_honorific_tip'), 'icon' => 'bi-heart'],
        ['token' => '{job}', 'label' => __('tpl.token_job'), 'tip' => __('tpl.token_job_tip'), 'icon' => 'bi-briefcase'],
        ['token' => '{org}', 'label' => __('tpl.token_org'), 'tip' => __('tpl.token_org_tip'), 'icon' => 'bi-building'],
        ['token' => '{subject}', 'label' => __('tpl.token_subject'), 'tip' => __('tpl.token_subject_tip'), 'icon' => 'bi-chat-text'],
        ['token' => '{logo}', 'label' => __('tpl.token_logo'), 'tip' => __('tpl.token_logo_tip'), 'icon' => 'bi-image'],
        ['token' => '{signature}', 'label' => __('tpl.token_signature'), 'tip' => __('tpl.token_signature_tip'), 'icon' => 'bi-pen'],
    ];
}

/** لقب صاحب الشأن واسمه ووظيفته، كل حقل في زر. */
function letter_signer_insert_tokens(): array
{
    return [
        ['token' => '{from_title}', 'label' => __('tpl.token_from_title'), 'tip' => __('tpl.token_from_title_tip'), 'icon' => 'bi-award'],
        ['token' => '{from_name}', 'label' => __('tpl.token_from_name'), 'tip' => __('tpl.token_from_name_tip'), 'icon' => 'bi-person'],
        ['token' => '{from_job}', 'label' => __('tpl.token_from_job'), 'tip' => __('tpl.token_from_job_tip'), 'icon' => 'bi-briefcase'],
    ];
}

function letter_font_options(): array
{
    // خطوط محلية من assets/fonts فقط
    $font = static function (string $rel, int $weight = 400): array {
        return ['rel' => $rel, 'weight' => $weight];
    };

    return [
        'greta' => [
            'label' => __('tpl.font_greta'),
            'family' => 'GretaArabic',
            'stack' => "'GretaArabic', 'Amiri', sans-serif",
            'email' => "'GretaArabic', Arial, Helvetica, sans-serif",
            'mso' => 'Arial',
            'files' => [
                $font('assets/fonts/GretaArabic-Regular.otf', 400),
                $font('assets/fonts/GretaArabic-Bold.otf', 700),
            ],
        ],
        'amiri' => [
            'label' => __('tpl.font_amiri'),
            'family' => 'Amiri',
            'stack' => "'Amiri', 'GretaArabic', serif",
            'email' => "'Amiri', 'Traditional Arabic', 'Times New Roman', serif",
            'mso' => 'Traditional Arabic',
            'files' => [
                $font('assets/fonts/Amiri-Regular.ttf', 400),
                $font('assets/fonts/Amiri-Bold.ttf', 700),
            ],
        ],
        'tajawal' => [
            'label' => __('tpl.font_tajawal'),
            'family' => 'Tajawal',
            'stack' => "'Tajawal', 'GretaArabic', sans-serif",
            'email' => "'Tajawal', Arial, Helvetica, sans-serif",
            'mso' => 'Arial',
            'files' => [
                $font('assets/fonts/Tajawal-Regular.ttf', 400),
                $font('assets/fonts/Tajawal-Bold.ttf', 700),
            ],
        ],
        'arial' => [
            'label' => __('tpl.font_arial'),
            'family' => 'Arial',
            'stack' => "Arial, 'GretaArabic', sans-serif",
            'email' => "Arial, Helvetica, sans-serif",
            'mso' => 'Arial',
            'files' => [
                $font('assets/fonts/Arial-Regular.ttf', 400),
                $font('assets/fonts/Arial-Bold.ttf', 700),
            ],
        ],
        'calibri' => [
            'label' => __('tpl.font_calibri'),
            'family' => 'Calibri',
            'stack' => "Calibri, 'GretaArabic', sans-serif",
            'email' => "Calibri, 'Segoe UI', Arial, sans-serif",
            'mso' => 'Calibri',
            'files' => [
                $font('assets/fonts/Calibri-Regular.ttf', 400),
                $font('assets/fonts/Calibri-Bold.ttf', 700),
            ],
        ],
        'aldhabi' => [
            'label' => __('tpl.font_aldhabi'),
            'family' => 'Aldhabi',
            'stack' => "'Aldhabi', 'Amiri', serif",
            'email' => "'Aldhabi', 'Traditional Arabic', 'Times New Roman', serif",
            'mso' => 'Traditional Arabic',
            'files' => [
                $font('assets/fonts/Aldhabi-Regular.ttf', 400),
            ],
        ],
        'majalla' => [
            'label' => __('tpl.font_majalla'),
            'family' => 'SakkalMajalla',
            'stack' => "'SakkalMajalla', 'Amiri', serif",
            'email' => "'SakkalMajalla', 'Traditional Arabic', 'Times New Roman', serif",
            'mso' => 'Traditional Arabic',
            'files' => [
                $font('assets/fonts/SakkalMajalla-Regular.ttf', 400),
                $font('assets/fonts/SakkalMajalla-Bold.ttf', 700),
            ],
        ],
        'mohanad' => [
            'label' => __('tpl.font_mohanad'),
            'family' => 'AL-Mohanad',
            'stack' => "'AL-Mohanad', 'GretaArabic', sans-serif",
            'email' => "'AL-Mohanad', 'Arial Black', Arial, sans-serif",
            'mso' => 'Arial',
            'files' => [
                $font('assets/fonts/AL-Mohanad-Regular.ttf', 400),
                $font('assets/fonts/AL-Mohanad-Bold.ttf', 700),
            ],
        ],
        'fanan' => [
            'label' => __('tpl.font_fanan'),
            'family' => 'Fanan',
            'stack' => "'Fanan', 'GretaArabic', sans-serif",
            'email' => "'Fanan', Verdana, Geneva, sans-serif",
            'mso' => 'Verdana',
            'files' => [
                $font('assets/fonts/Fanan-Regular.ttf', 400),
            ],
        ],
    ];
}

/** رموز قديمة → أقرب خط محلي متاح */
function letter_font_normalize_code(string $code): string
{
    $code = strtolower(trim($code));
    $map = [
        'greta' => 'greta',
        'amiri' => 'amiri',
        'tajawal' => 'tajawal',
        'arial' => 'arial',
        'calibri' => 'calibri',
        'calbari' => 'calibri',
        'aldhabi' => 'aldhabi',
        'aldahbi' => 'aldhabi',
        'majalla' => 'majalla',
        'sakkal' => 'majalla',
        'sakkalmajalla' => 'majalla',
        'mohanad' => 'mohanad',
        'al-mohanad' => 'mohanad',
        'almohanad' => 'mohanad',
        'fanan' => 'fanan',
        'andalus' => 'majalla',
        'andalsi' => 'majalla',
        'trad' => 'amiri',
        'times' => 'amiri',
        'typeset' => 'amiri',
        'simp' => 'tajawal',
        'tahoma' => 'arial',
        'segoe' => 'calibri',
    ];
    return $map[$code] ?? 'greta';
}

/** أول خط محدد في نص الخطاب (للمستلم والموضوع أيضاً) */
function letter_body_font_code(string $text): string
{
    if (preg_match('/\{font:([a-z0-9_]+)\}/i', $text, $m) === 1) {
        return letter_font_normalize_code((string) $m[1]);
    }

    return 'greta';
}

function letter_email_font_code_setting(): string
{
    // البريد ومعاينة الإرسال: Arial فقط — أي خط آخر لا يظهر عند المستلم
    return 'arial';
}

function letter_email_forced_family(): string
{
    $opt = letter_font_option(letter_email_font_code_setting());
    $email = trim((string) ($opt['email'] ?? ''));

    return $email !== '' ? $email : 'Arial, Helvetica, sans-serif';
}

function letter_email_font_family(string $text): string
{
    return letter_email_forced_family();
}

/** عائلة خط البريد من وسم {font:...} في نص الخطاب/القالب */
function letter_font_option(string $code): array
{
    $code = letter_font_normalize_code($code);
    $fonts = letter_font_options();

    return $fonts[$code] ?? $fonts['greta'];
}

function letter_email_mso_family(string $text): string
{
    $opt = letter_font_option(letter_email_font_code_setting());

    return trim((string) ($opt['mso'] ?? 'Arial')) ?: 'Arial';
}

/**
 * @return array{css:string,embeds:list<array{path:string,cid:string,name:string}>,stack:string,mso:string}
 */
function letter_email_font_pack(string $text): array
{
    $opt = letter_font_option(letter_email_font_code_setting());
    $stack = trim((string) ($opt['email'] ?? '')) ?: 'Arial, Helvetica, sans-serif';
    $mso = trim((string) ($opt['mso'] ?? 'Arial')) ?: 'Arial';
    $msoQuoted = str_contains($mso, ' ') ? '"' . $mso . '"' : $mso;

    return [
        'css' => '<!--[if mso]><style type="text/css">body,table,td,div,p,span,a,li,strong{font-family:'
            . $msoQuoted . ',sans-serif !important;}</style><![endif]-->',
        'embeds' => [],
        'stack' => $stack,
        'mso' => $mso,
    ];
}

function letter_email_mso_style(string $mso): string
{
    $mso = trim($mso);
    if ($mso === '') {
        return '';
    }

    return 'mso-ascii-font-family:' . $mso . ';mso-hansi-font-family:' . $mso . ';mso-bidi-font-family:' . $mso . ';';
}

function letter_email_font_stack(array $row = []): string
{
    return letter_email_forced_family();
}

function letter_email_style(array $row, string $extra = ''): string
{
    $style = 'font-family:' . letter_email_font_stack($row) . ';';
    $style .= letter_email_mso_style('Arial');
    $extra = trim($extra, ';');
    if (!preg_match('/font-size\s*:/i', $extra)) {
        $style .= 'font-size:14pt;';
    }
    $style .= 'line-height:1.65;';

    $dir = strtolower(trim((string) ($row['_email_dir'] ?? '')));
    if ($dir !== 'ltr' && $dir !== 'rtl') {
        $lang = 'ar';
        if (function_exists('correspondence_letter_channel_lang')) {
            $lang = correspondence_letter_channel_lang($row);
        } elseif (!empty($row['_email_lang'])) {
            $lang = strtolower((string) $row['_email_lang']) === 'en' ? 'en' : 'ar';
        }
        $dir = $lang === 'en' ? 'ltr' : 'rtl';
    }
    // عربي: يمين افتراضياً لكل النص؛ الإنجليزي يسار — ما لم يُمرَّر text-align في $extra
    if (!preg_match('/text-align\s*:/i', $extra)) {
        $style .= 'text-align:' . ($dir === 'ltr' ? 'left' : 'right') . ';';
    }
    if (!preg_match('/direction\s*:/i', $extra)) {
        $style .= 'direction:' . $dir . ';';
    }
    if ($extra !== '') {
        $style .= $extra . ';';
    }

    return $style;
}

function letter_font_css_class(string $code): string
{
    return 'letter-font-' . letter_font_normalize_code($code);
}

function letter_line_spacing_options(): array
{
    return [
        '10' => ['label' => '1', 'css' => '1.15'],
        '15' => ['label' => '1.5', 'css' => '1.5'],
        '20' => ['label' => '2', 'css' => '2'],
        '25' => ['label' => '2.5', 'css' => '2.5'],
        '30' => ['label' => '3', 'css' => '3'],
    ];
}

/**
 * إزاحة من بداية السطر (يمين في العربي) دون محاذاة يسار كاملة.
 * القيمة بالسنتيمتر ≈ على عرض منطقة النص.
 */
function letter_indent_options(): array
{
    return [
        '3' => ['label' => '3', 'css' => '3cm'],
        '5' => ['label' => '5', 'css' => '5cm'],
        '7' => ['label' => '7', 'css' => '7cm'],
        '9' => ['label' => '9', 'css' => '9cm'],
        '11' => ['label' => '11', 'css' => '11cm'],
        '13' => ['label' => '13', 'css' => '13cm'],
    ];
}

function letter_indent_normalize_code(string $code): string
{
    $code = preg_replace('/[^0-9]/', '', $code) ?? '';
    $opts = letter_indent_options();

    return isset($opts[$code]) ? $code : '';
}

/** مسافة رأسية قبل/بعد السطر (سم) دون ضغط Enter. */
function letter_vspace_options(): array
{
    return [
        '1' => ['label' => '0.1', 'css' => '0.1cm'],
        '2' => ['label' => '0.2', 'css' => '0.2cm'],
        '3' => ['label' => '0.3', 'css' => '0.3cm'],
        '4' => ['label' => '0.4', 'css' => '0.4cm'],
        '5' => ['label' => '0.5', 'css' => '0.5cm'],
        '10' => ['label' => '1', 'css' => '1cm'],
        '15' => ['label' => '1.5', 'css' => '1.5cm'],
        '20' => ['label' => '2', 'css' => '2cm'],
        '30' => ['label' => '3', 'css' => '3cm'],
    ];
}

function letter_vspace_normalize_code(string $code): string
{
    $code = preg_replace('/[^0-9]/', '', $code) ?? '';
    $opts = letter_vspace_options();

    return isset($opts[$code]) ? $code : '';
}

/** CSS مسافة قبل/بعد من نفس قائمة الخيارات (سنتيمتر مطابق للوسم). */
function letter_vspace_css(): string
{
    $css = '.letter-vspace{display:block;box-sizing:border-box;margin:0;padding:0;}';
    foreach (letter_vspace_options() as $code => $opt) {
        $len = (string) ($opt['css'] ?? '');
        if ($len === '') {
            continue;
        }
        $css .= '.letter-vspace-sb-' . $code . '{padding-top:' . $len . ';}';
        $css .= '.letter-vspace-sa-' . $code . '{padding-bottom:' . $len . ';}';
    }

    return $css;
}

/** نص التحية/التكريم من صف المعاملة أو الجهة. */
function letter_row_honorific_text(array $row): string
{
    if (function_exists('correspondence_archived_party')
        && in_array((string) ($row['type'] ?? ''), ['incoming', 'outgoing'], true)
    ) {
        $party = correspondence_archived_party($row);

        return trim((string) ($party['honorific'] ?? ''));
    }
    if (function_exists('entity_honorific_value')) {
        return trim((string) entity_honorific_value($row));
    }

    return trim((string) ($row['snap_honorific'] ?? $row['honorific'] ?? ''));
}

/**
 * أنماط التحية → توزيع في سطر واحد (بدون كسر سطر):
 * - {indent:N}{title} {name} {honorific}{/indent}
 * - {title} {name} {indent:N}{honorific}{/indent}
 */
function letter_normalize_indent_honorific_spread(string $text, array $row = []): string
{
    $honorific = letter_row_honorific_text($row);
    $honorificToken = '{honorific}';

    // لقب/اسم ثم إزاحة التكريم فقط — يبقى سطراً واحداً عبر spread
    $text = preg_replace_callback(
        '/(\{(?:title|title_prefix|directed_to|name)\}(?:\s+\{(?:title|title_prefix|directed_to|name)\})*)\s*\{indent:(\d+)\}\s*\{honorific\}\s*\{\/indent\}/iu',
        static function (array $m): string {
            $code = letter_indent_normalize_code((string) $m[2]);
            if ($code === '') {
                return $m[0];
            }

            return '{spread:' . $code . '}' . trim((string) $m[1]) . '{spread_sep}{honorific}{/spread}';
        },
        $text
    ) ?? $text;

    return preg_replace_callback(
        '/\{indent:(\d+)\}(.*?)\{\/indent\}/su',
        static function (array $m) use ($honorific, $honorificToken): string {
            $code = letter_indent_normalize_code((string) $m[1]);
            $inner = (string) $m[2];
            if ($code === '' || preg_match('/\{(?:indent|spread):/i', $inner)) {
                return $m[0];
            }
            $main = '';
            $end = '';
            if ($honorific !== ''
                && preg_match('/^(.*?)\s+' . preg_quote($honorific, '/') . '\s*$/su', $inner, $parts) === 1
            ) {
                $main = rtrim((string) $parts[1]);
                $end = $honorific;
            } elseif (preg_match('/^(.*?)\s+' . preg_quote($honorificToken, '/') . '\s*$/su', $inner, $parts) === 1) {
                $main = rtrim((string) $parts[1]);
                $end = $honorificToken;
            }
            if ($main === '' || $end === '') {
                return $m[0];
            }

            return '{spread:' . $code . '}' . $main . '{spread_sep}' . $end . '{/spread}';
        },
        $text
    ) ?? $text;
}

function render_letter_value_picker(string $icon, string $label, string $tip, string $dataAttr, array $options): void
{
    ?>
    <label class="letter-token-btn is-format is-icon letter-value-picker"
           data-bs-toggle="tooltip" data-bs-placement="top"
           title="<?= e($tip) ?>">
        <i class="bi <?= e($icon) ?>" aria-hidden="true"></i>
        <select class="letter-value-native" <?= $dataAttr ?> dir="ltr" aria-label="<?= e($label) ?>">
            <option value=""><?= e($label) ?></option>
            <?php foreach ($options as $code => $opt): ?>
                <option value="<?= e((string) $code) ?>"><?= e((string) ($opt['label'] ?? $code)) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <?php
}

function render_letter_line_spacing_picker(): void
{
    render_letter_value_picker(
        'bi-distribute-vertical',
        __('tpl.token_ls'),
        __('tpl.token_ls_tip'),
        'data-letter-ls',
        letter_line_spacing_options()
    );
}

function render_letter_indent_picker(): void
{
    render_letter_value_picker(
        'bi-text-indent-right',
        __('tpl.token_indent'),
        __('tpl.token_indent_tip'),
        'data-letter-indent',
        letter_indent_options()
    );
}

function render_letter_vspace_picker(string $kind): void
{
    $kind = $kind === 'sb' ? 'sb' : 'sa';
    render_letter_value_picker(
        $kind === 'sb' ? 'bi-align-top' : 'bi-align-bottom',
        __($kind === 'sb' ? 'tpl.token_space_before' : 'tpl.token_space_after'),
        __($kind === 'sb' ? 'tpl.token_space_before_tip' : 'tpl.token_space_after_tip'),
        $kind === 'sb' ? 'data-letter-sb' : 'data-letter-sa',
        letter_vspace_options()
    );
}

function letter_template_token_is_format(array $item): bool
{
    $kind = (string) ($item['kind'] ?? 'token');
    return $kind === 'newline' || $kind === 'undo' || isset($item['wrap']);
}

function render_letter_token_button(array $item): void
{
    $kind = (string) ($item['kind'] ?? 'token');
    $wrap = $item['wrap'] ?? null;
    $isFormat = letter_template_token_is_format($item);
    $label = trim((string) ($item['label'] ?? ''));
    $tip = trim((string) ($item['tip'] ?? ''));
    if ($tip === '') {
        $tip = $label;
    }
    $attrs = '';
    if ($kind === 'undo') {
        $attrs = 'data-letter-undo="1" aria-disabled="true"';
    } elseif ($kind === 'newline') {
        $attrs = 'data-insert-nl="1"';
    } elseif (is_array($wrap) && count($wrap) === 2) {
        $attrs = 'data-wrap-open="' . e((string) $wrap[0]) . '" data-wrap-close="' . e((string) $wrap[1]) . '"';
        if (!empty($item['para'])) {
            $attrs .= ' data-wrap-para="1"';
        }
    } else {
        $attrs = 'data-insert-token="' . e((string) ($item['token'] ?? '')) . '"';
    }
    ?>
    <button type="button" class="letter-token-btn is-icon<?= $isFormat ? ' is-format' : '' ?>"
            <?= $attrs ?>
            data-bs-toggle="tooltip" data-bs-placement="top"
            title="<?= e($tip) ?>"
            aria-label="<?= e($label !== '' ? $label : $tip) ?>">
        <i class="bi <?= e($item['icon']) ?>" aria-hidden="true"></i>
    </button>
    <?php
}

function render_letter_channel_preview_buttons(array $channelPreviews): void
{
    $templateId = (int) ($channelPreviews['template_id'] ?? 0);
    $arId = trim((string) ($channelPreviews['ar'] ?? 'body'));
    $enId = trim((string) ($channelPreviews['en'] ?? 'body_en'));
    if ($arId === '' || !function_exists('base_url')) {
        return;
    }
    $buttons = [
        [
            'url' => base_url('admin/letter_print_preview.php'),
            'icon' => 'bi-file-earmark-font',
            'label' => __('tpl.preview_letter'),
        ],
    ];
    if (!array_key_exists('email', $channelPreviews) || !empty($channelPreviews['email'])) {
        $buttons[] = [
            'url' => base_url('admin/letter_email_preview.php'),
            'icon' => 'bi-envelope-open',
            'label' => __('tpl.preview_email'),
        ];
    }
    ?>
    <div class="letter-token-preview-panel" role="group" aria-label="<?= e(__('tpl.preview')) ?>">
        <?php foreach ($buttons as $button): ?>
            <button type="button" class="letter-token-btn is-preview is-icon"
                    data-letter-preview="<?= e($arId) ?>"
                    data-letter-preview-active="1"
                    data-letter-preview-en="<?= e($enId) ?>"
                    data-letter-preview-print="1"
                    data-letter-preview-print-url="<?= e($button['url']) ?>"
                    data-template-id="<?= $templateId ?>"
                    data-bs-toggle="tooltip"
                    data-bs-placement="top"
                    title="<?= e($button['label']) ?>"
                    aria-label="<?= e($button['label']) ?>">
                <i class="bi <?= e($button['icon']) ?>" aria-hidden="true"></i>
            </button>
        <?php endforeach; ?>
    </div>
    <?php
}

function render_letter_placeholder_toolbar(string $textareaId, string $previewLang = 'ar', string $initialText = '', array $tokenTips = [], array $hideTokens = [], array $extraTokens = [], array $channelPreviews = []): void
{
    $hidden = [];
    foreach ($hideTokens as $token) {
        $token = trim((string) $token);
        if ($token !== '') {
            $hidden[$token] = true;
        }
    }
    $format = [];
    $fields = [];
    foreach (letter_template_placeholder_tokens() as $item) {
        $token = (string) ($item['token'] ?? '');
        if ($token !== '' && isset($hidden[$token])) {
            continue;
        }
        if ($token !== '' && isset($tokenTips[$token]) && trim((string) $tokenTips[$token]) !== '') {
            $item['tip'] = (string) $tokenTips[$token];
        }
        if (letter_template_token_is_format($item)) {
            $format[] = $item;
        } else {
            $fields[] = $item;
        }
    }
    $extras = [];
    foreach ($extraTokens as $item) {
        if (!is_array($item)) {
            continue;
        }
        $token = trim((string) ($item['token'] ?? ''));
        if ($token === '' || isset($hidden[$token])) {
            continue;
        }
        $item['token'] = $token;
        if (isset($tokenTips[$token]) && trim((string) $tokenTips[$token]) !== '') {
            $item['tip'] = (string) $tokenTips[$token];
        }
        $extras[] = $item;
    }
    if ($extras) {
        $ordered = [];
        $placed = false;
        foreach ($fields as $item) {
            if (!$placed && (string) ($item['token'] ?? '') === '{signature}') {
                foreach ($extras as $extra) {
                    $ordered[] = $extra;
                }
                $placed = true;
            }
            $ordered[] = $item;
        }
        if (!$placed) {
            $ordered = array_merge($ordered, $extras);
        }
        $fields = $ordered;
    }
    ?>
    <div class="letter-token-toolbar" data-insert-target="<?= e($textareaId) ?>" role="toolbar" aria-label="<?= e(__('tpl.tokens_toolbar')) ?>">
        <div class="letter-token-row is-format<?= $channelPreviews !== [] ? ' has-preview' : '' ?>">
            <div class="letter-token-tools">
                <?php foreach ($format as $item): ?>
                    <?php render_letter_token_button($item); ?>
                <?php endforeach; ?>
                <?php render_letter_line_spacing_picker(); ?>
                <?php render_letter_indent_picker(); ?>
                <?php render_letter_vspace_picker('sb'); ?>
                <?php render_letter_vspace_picker('sa'); ?>
                <button type="button" class="letter-token-btn is-format is-icon"
                        data-letter-clear-format="1"
                        data-bs-toggle="tooltip" data-bs-placement="top"
                        title="<?= e(__('tpl.token_clear_format_tip')) ?>"
                        aria-label="<?= e(__('tpl.token_clear_format')) ?>">
                    <i class="bi bi-eraser" aria-hidden="true"></i>
                </button>
            </div>
            <?php if ($channelPreviews !== []): ?>
                <?php render_letter_channel_preview_buttons($channelPreviews); ?>
            <?php endif; ?>
        </div>
        <div class="letter-token-row is-fields">
            <?php foreach ($fields as $item): ?>
                <?php render_letter_token_button($item); ?>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
    render_letter_preview_modal();
}

/**
 * مسطرة أفقية بنمط Word لعرض هوامش ورقة A4 (2.5 سم يمين/يسار).
 */
function render_letter_body_ruler(): void
{
    $pageCm = 21.0;
    $marginCm = 2.5;
    ?>
    <div class="letter-ruler" dir="ltr" aria-hidden="true" title="<?= e(__('tpl.margin_hint')) ?>">
        <div class="letter-ruler-track">
            <span class="letter-ruler-margin-zone is-left"></span>
            <span class="letter-ruler-margin-zone is-right"></span>
            <?php for ($half = 0; $half <= (int) round($pageCm * 2); $half++): ?>
                <?php
                $cm = $half / 2;
                $pct = ($cm / $pageCm) * 100;
                $major = ($half % 2) === 0;
                $cmInt = (int) round($cm);
                $pctCss = rtrim(rtrim(sprintf('%.4F', $pct), '0'), '.');
                $edge = $major && $cmInt === 0 ? ' is-edge-start' : ($major && $cmInt === (int) $pageCm ? ' is-edge-end' : '');
                ?>
                <span class="letter-ruler-mark<?= $major ? ' is-major' : ' is-minor' ?><?= $edge ?>"
                      style="left: <?= e($pctCss) ?>%">
                    <?php if ($major): ?>
                        <span class="letter-ruler-num"><?= $cmInt ?></span>
                    <?php endif; ?>
                </span>
            <?php endfor; ?>
            <span class="letter-ruler-marker is-left" title="<?= e(__('tpl.margin_left')) ?>"></span>
            <span class="letter-ruler-marker is-right" title="<?= e(__('tpl.margin_right')) ?>"></span>
        </div>
    </div>
    <?php
}

function render_letter_lang_editor(string $arId, string $enId, string $arValue, string $enValue, int $rows = 10, bool $arRequired = false, array $tokenTips = [], array $hideTokens = [], string $hint = '', array $extraTokens = [], array $channelPreviews = [], string $barEndHtml = ''): void
{
    $paneAr = $arId . '-pane';
    $paneEn = $enId . '-pane';
    ?>
    <div class="letter-lang-bar">
        <ul class="nav nav-tabs letter-lang-tabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" type="button" data-bs-toggle="tab" data-bs-target="#<?= e($paneAr) ?>" role="tab">
                    <?= e(__('tpl.body_ar')) ?>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" type="button" data-bs-toggle="tab" data-bs-target="#<?= e($paneEn) ?>" role="tab">
                    <?= e(__('tpl.body_en')) ?>
                </button>
            </li>
        </ul>
        <?php if ($barEndHtml !== ''): ?>
            <div class="letter-lang-bar-end"><?= $barEndHtml ?></div>
        <?php endif; ?>
    </div>
    <div class="tab-content letter-lang-panes">
        <div class="tab-pane fade show active" id="<?= e($paneAr) ?>" role="tabpanel">
            <?php render_letter_placeholder_toolbar($arId, 'ar', $arValue, $tokenTips, $hideTokens, $extraTokens, $channelPreviews); ?>
            <div class="letter-body-frame" dir="rtl">
                <?php render_letter_body_ruler(); ?>
                <textarea name="<?= e($arId) ?>" id="<?= e($arId) ?>" class="form-control letter-body-input" rows="<?= (int) $rows ?>" dir="rtl"<?= $arRequired ? ' required' : '' ?>><?= e($arValue) ?></textarea>
            </div>
        </div>
        <div class="tab-pane fade" id="<?= e($paneEn) ?>" role="tabpanel">
            <?php render_letter_placeholder_toolbar($enId, 'en', $enValue, $tokenTips, $hideTokens, $extraTokens, $channelPreviews); ?>
            <div class="letter-body-frame" dir="ltr">
                <?php render_letter_body_ruler(); ?>
                <textarea name="<?= e($enId) ?>" id="<?= e($enId) ?>" class="form-control letter-body-input" rows="<?= (int) $rows ?>" dir="ltr"><?= e($enValue) ?></textarea>
            </div>
        </div>
    </div>
    <p class="admin-form-hint mb-0 mt-1"><?= e($hint !== '' ? $hint : __('tpl.body_bilingual_hint')) ?></p>
    <?php
}

function letter_preview_sample_row(string $lang = 'ar', int $deptId = 0): array
{
    $lang = $lang === 'en' ? 'en' : 'ar';
    $t = static fn(string $key): string => function_exists('__lang') ? __lang($key, $lang) : __($key);

    $row = [
        'type' => 'outgoing',
        'ref_number' => function_exists('corr_ref_sample') ? corr_ref_sample('') : (date('y') . '/0001'),
        'subject' => $t('tpl.preview_subject'),
        'corr_date' => date('Y-m-d'),
        // رمز معاينة ثابت 32 hex حتى يظهر QR تأكيد الحضور في المعاينة
        'qr_token' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        'snap_title_prefix' => $t('tpl.preview_title'),
        'snap_person_name' => $t('tpl.preview_name'),
        'snap_honorific' => $t('tpl.preview_honorific'),
        'snap_job_title' => $t('tpl.preview_job'),
        'snap_org_name' => $t('tpl.preview_org'),
        'entity_name_en' => $t('tpl.preview_org'),
        'party_name' => $t('tpl.preview_name'),
        'department_id' => $deptId > 0 ? $deptId : null,
    ];
    if ($deptId > 0 && function_exists('admin_row')) {
        $dept = admin_row('tbldepartments', $deptId);
        if (is_array($dept)) {
            $officer = function_exists('department_officer_fields') ? department_officer_fields($dept) : [
                'title_prefix' => trim((string) ($dept['title_prefix'] ?? '')),
                'directed_from' => trim((string) ($dept['directed_from'] ?? '')),
                'job_title' => trim((string) ($dept['job_title'] ?? '')),
            ];
            $row['snap_from_prefix'] = $officer['title_prefix'];
            $row['snap_from_person'] = $officer['directed_from'];
            $row['snap_from_job'] = $officer['job_title'];
            $row['snap_from_signature'] = function_exists('department_principal_signature_path')
                ? department_principal_signature_path($dept)
                : '';
        }
    }

    return $row;
}

/**
 * خطاب واحد لمعاينة القالب: نص المحرر غير المحفوظ، وصاحب الشأن من «موجهة من».
 *
 * @return list<array<string,mixed>>
 */
function letter_template_preview_items(int $templateId, string $lang, string $body, int $postedDeptId, ?array $user = null): array
{
    $lang = $lang === 'en' ? 'en' : 'ar';
    $items = [];
    if ($templateId > 0 && function_exists('outgoing_batch_items')) {
        ensure_letter_templates_schema();
        if (function_exists('ensure_corr_electronic_schema')) {
            ensure_corr_electronic_schema();
        }
        $st = db()->prepare(
            'SELECT id FROM `tbloutgoing_batches` WHERE letter_template_id = ? ORDER BY id DESC LIMIT 1'
        );
        $st->execute([$templateId]);
        $batchId = (int) $st->fetchColumn();
        if ($batchId > 0) {
            $items = array_values(array_filter(
                outgoing_batch_items($batchId, $user),
                static function (array $row): bool {
                    if (function_exists('correspondence_is_cancelled') && correspondence_is_cancelled($row)) {
                        return false;
                    }

                    return function_exists('correspondence_intend_paper') && correspondence_intend_paper($row);
                }
            ));
            if ($items !== []) {
                $items = [$items[0]];
            }
        }
    }
    if ($items === []) {
        $sample = letter_preview_sample_row($lang, $postedDeptId);
        $sample['rsvp_required'] = 1;
        $sample['intend_paper'] = 1;
        $sample['intend_electronic'] = 0;
        $items = [$sample];
    }
    $fromDeptId = $postedDeptId;
    if ($fromDeptId <= 0 && $templateId > 0) {
        $tplRow = get_letter_template($templateId);
        $fromDeptId = (int) ($tplRow['department_id'] ?? 0);
    }
    $fromOfficer = null;
    if ($fromDeptId > 0 && function_exists('admin_row')) {
        $fromDept = admin_row('tbldepartments', $fromDeptId);
        if (is_array($fromDept) && function_exists('department_officer_fields')) {
            $fromOfficer = department_officer_fields($fromDept);
            $fromOfficer['signature'] = function_exists('department_principal_signature_path')
                ? department_principal_signature_path($fromDept)
                : '';
        }
    }
    foreach ($items as &$row) {
        $row['batch_letter_template_id'] = 0;
        $row['letter_template_id'] = 0;
        if (is_array($fromOfficer)) {
            $row['department_id'] = $fromDeptId;
            $row['issuing_department_id'] = $fromDeptId;
            $row['snap_from_prefix'] = $fromOfficer['title_prefix'];
            $row['snap_from_person'] = $fromOfficer['directed_from'];
            $row['snap_from_job'] = $fromOfficer['job_title'];
            $row['snap_from_org'] = '';
            $row['snap_from_signature'] = $fromOfficer['signature'];
        }
        if ($lang === 'en') {
            $row['letter_body_en'] = $body;
            $row['batch_letter_body_en'] = '';
            $row['letter_body'] = '';
            $row['batch_letter_body'] = '';
        } else {
            $row['letter_body'] = $body;
            $row['batch_letter_body'] = '';
            $row['letter_body_en'] = '';
            $row['batch_letter_body_en'] = '';
        }
    }
    unset($row);

    return $items;
}

function letter_preview_html(string $body, string $lang = 'ar', int $deptId = 0, ?array $row = null): string
{
    $lang = $lang === 'en' ? 'en' : 'ar';
    $body = trim($body);
    if (!is_array($row) || $row === []) {
        $row = letter_preview_sample_row($lang, $deptId);
    }
    $t = static fn(string $key): string => function_exists('__lang') ? __lang($key, $lang) : __($key);
    $subject = trim((string) ($row['subject'] ?? ''));
    $email = '';
    if (function_exists('entity_email_address')) {
        $email = entity_email_address($row);
    }
    if ($email === '') {
        $email = trim((string) ($row['entity_email'] ?? $row['email'] ?? ''));
    }
    $needsRsvp = function_exists('correspondence_rsvp_required')
        ? correspondence_rsvp_required($row)
        : !empty($row['rsvp_required']);
    $empty = '<span class="text-muted">—</span>';
    $meta = '<div class="letter-preview-meta">'
        . '<div class="letter-preview-meta-row"><span class="letter-preview-meta-label">' . e($t('corr.subject')) . '</span>'
        . '<span class="letter-preview-meta-value">' . ($subject !== '' ? e($subject) : $empty) . '</span></div>'
        . '<div class="letter-preview-meta-row"><span class="letter-preview-meta-label">' . e($t('tpl.preview_recipient_email')) . '</span>'
        . '<span class="letter-preview-meta-value letter-preview-email">' . ($email !== '' ? '<span dir="ltr">' . e($email) . '</span>' : $empty) . '</span></div>'
        . '</div>';
    if ($body === '' && !$needsRsvp) {
        return $meta . '<p class="text-muted mb-0">' . e($t('tpl.preview_empty')) . '</p>';
    }
    $body = preg_replace('/\{font:[a-z0-9_]+\}/i', '', $body) ?? $body;
    $body = str_ireplace('{/font}', '', $body);
    $filled = $body !== '' && function_exists('apply_letter_placeholders')
        ? apply_letter_placeholders($body, $row, $lang)
        : $body;
    $dir = $lang === 'en' ? 'ltr' : 'rtl';
    $fontClass = 'letter-font-arial';
    $fontStyle = 'Arial,Helvetica,sans-serif';
    if (function_exists('letter_email_font_code_setting') && function_exists('letter_font_css_class')) {
        $code = letter_email_font_code_setting();
        $fontClass = letter_font_css_class($code);
        if (function_exists('letter_font_option')) {
            $opt = letter_font_option($code);
            $stack = trim((string) ($opt['stack'] ?? ''));
            if ($stack !== '') {
                $fontStyle = $stack;
            }
        }
    }

    $number = (function_exists('outgoing_show_official_ref') && !outgoing_show_official_ref($row))
        ? ''
        : (function_exists('format_ref_number') ? format_ref_number((string) ($row['ref_number'] ?? '')) : (string) ($row['ref_number'] ?? ''));
    $date = function_exists('format_date') ? format_date($row['corr_date'] ?? null) : (string) ($row['corr_date'] ?? '');
    $inner = '<div class="letter-preview-head">';
    if ($number !== '') {
        $inner .= '<div class="letter-preview-head-row"><strong>' . e($t('corr.number')) . ': <span dir="ltr">' . e($number) . '</span></strong></div>';
    }
    if ($date !== '') {
        $inner .= '<div class="letter-preview-head-row"><strong>' . e($t('corr.date')) . ': ' . e($date) . '</strong></div>';
    }
    $inner .= '</div>';
    $filledPrint = function_exists('letter_strip_link_qr_token') ? letter_strip_link_qr_token($filled) : $filled;
    if ($filledPrint !== '') {
        $inner .= render_letter_body_html($filledPrint, false, $row);
    }
    $linkBlock = letter_link_qr_markup(false, $row);
    if ($linkBlock !== '' || $needsRsvp) {
        $inner .= '<div class="letter-end-actions">';
        if ($linkBlock !== '') {
            $inner .= $linkBlock;
        }
        if ($needsRsvp) {
            $askKey = function_exists('correspondence_rsvp_ask_key')
                ? correspondence_rsvp_ask_key($row, 'print')
                : (!empty($row['intend_electronic']) ? 'rsvp.email_ask' : 'rsvp.print_ask');
            $ask = $t($askKey);
            $ask = function_exists('letter_end_note_text') ? letter_end_note_text($ask) : (($ask !== '' && !str_starts_with($ask, '*')) ? ('* ' . $ask) : $ask);
            if (function_exists('letter_end_with_action_cta')) {
                $ask = letter_end_with_action_cta($ask, 'print');
            }
            $ask = e($ask);
            $token = strtolower(trim((string) ($row['qr_token'] ?? '')));
            if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
                $row = array_merge($row, ['qr_token' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa']);
            }
            $qrUri = function_exists('dms_corr_qr_data_uri') ? dms_corr_qr_data_uri($row, 0, true) : '';
            $inner .= '<div class="letter-qr-row letter-rsvp-block letter-preview-rsvp">';
            $inner .= '<div class="letter-qr-text letter-rsvp-note">' . $ask . '</div>';
            if ($qrUri !== '') {
                $img = function_exists('dms_qr_img_tag') ? dms_qr_img_tag($qrUri) : ('<img src="' . e($qrUri) . '" alt="" width="640" height="640" decoding="sync">');
                $inner .= '<div class="letter-qr-code letter-rsvp-qr" aria-hidden="true">' . $img . '</div>';
            }
            $inner .= '</div>';
        }
        $inner .= '</div>';
    }

    return $meta
        . '<div class="letter-preview-inner ' . e($fontClass) . '" dir="' . $dir . '" style="font-family:' . e($fontStyle) . '">'
        . $inner
        . '</div>';
}

function render_letter_preview_modal(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    ?>
    <style id="letter-vspace-css"><?= letter_vspace_css() ?></style>
    <div class="modal fade" id="letterPreviewModal" tabindex="-1" aria-labelledby="letterPreviewModalLabel" aria-hidden="true"
         data-preview-url="<?= e(base_url('admin/letter_preview.php')) ?>"
         data-preview-fail="<?= e(__('tpl.preview_fail')) ?>">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content letter-preview-modal">
                <div class="modal-header">
                    <h5 class="modal-title" id="letterPreviewModalLabel">
                        <i class="bi bi-eye me-1"></i><?= e(__('tpl.preview')) ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(__('action.cancel')) ?>"></button>
                </div>
                <div class="modal-body letter-preview-modal-body">
                    <p class="admin-form-hint mb-3"><?= e(__('tpl.preview_sample')) ?></p>
                    <div class="letter-preview-paper">
                        <div class="letter-preview-sheet" data-letter-preview-body></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(__('tpl.preview_close')) ?></button>
                </div>
            </div>
        </div>
    </div>
    <?php
}

function letter_body_plain(string $text, array $row = []): string
{
    $text = preg_replace('#\{b\}(.*?)\{/b\}#s', '*$1*', $text) ?? $text;
    $text = preg_replace('#\{i\}(.*?)\{/i\}#s', '_$1_', $text) ?? $text;
    $text = preg_replace('#\{big\}(.*?)\{/big\}#s', '*$1*', $text) ?? $text;
    $text = preg_replace('#\{u\}(.*?)\{/u\}#s', '$1', $text) ?? $text;
    $text = preg_replace_callback('#\{ul\}(.*?)\{/ul\}#s', static function (array $m): string {
        $lines = preg_split("/\r\n|\n|\r/", (string) $m[1]) ?: [];
        $out = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== '') {
                $out[] = '• ' . $line;
            }
        }
        return implode("\n", $out);
    }, $text) ?? $text;
    $text = preg_replace('#\{font:[a-z0-9_]+\}#', '', $text) ?? $text;
    $text = preg_replace('#\{ls:[0-9]+\}#', '', $text) ?? $text;
    $text = preg_replace('#\{indent:[0-9]+\}#', '', $text) ?? $text;
    $text = preg_replace('#\{spread:[0-9]+\}#', '', $text) ?? $text;
    $text = preg_replace('#\{sb:[0-9]+\}#', '', $text) ?? $text;
    $text = preg_replace('#\{sa:[0-9]+\}#', '', $text) ?? $text;
    $text = str_replace(['{spread_sep}', '{/spread}', '{/sb}', '{/sa}'], [' ', '', '', ''], $text);
    $text = str_replace('{signature}', '', $text);
    if (str_contains($text, '{link_qr}')) {
        $url = function_exists('correspondence_letter_link_url') ? correspondence_letter_link_url($row) : '';
        $label = function_exists('correspondence_letter_link_label') ? correspondence_letter_link_label($row) : '';
        $plainLink = '';
        if ($url !== '') {
            if ($label !== '' && $label !== $url) {
                $label = function_exists('letter_end_note_text') ? letter_end_note_text($label) : ('* ' . ltrim($label, "* \t"));
                $plainLink = $label . "\n" . $url;
            } else {
                $plainLink = $url;
            }
        }
        $text = str_replace('{link_qr}', $plainLink, $text);
    }
    $text = str_replace(['{center}', '{/center}', '{right}', '{/right}', '{left}', '{/left}', '{justify}', '{/justify}', '{ul}', '{/ul}', '{/font}', '{/ls}', '{/indent}', '{/spread}', '{/sb}', '{/sa}', '{logo}', '{i}', '{/i}'], '', $text);
    return letter_strip_from_marks($text);
}

/** إزالة رمز {link_qr} من نص الخطاب (يُعرض أسفل النص تلقائياً في الورقي/المرفق). */
function letter_strip_link_qr_token(string $text): string
{
    if ($text === '' || !str_contains($text, '{link_qr}')) {
        return $text;
    }
    $text = str_replace('{link_qr}', '', $text);
    $text = preg_replace("/[ \t]+\r?\n/", "\n", $text) ?? $text;
    $text = preg_replace("/(\r?\n){3,}/", "\n\n", $text) ?? $text;

    return $text;
}

/** بادئة ملاحظة أسفل الخطاب (* مكان الاجتماع / * تأكيد الحضور). */
function letter_end_note_text(string $text): string
{
    $text = trim($text);
    if ($text === '') {
        return '';
    }
    if (str_starts_with($text, '*')) {
        return $text;
    }

    return '* ' . $text;
}

/**
 * عبارة الإجراء الموحّدة أسفل الخطاب:
 * بريد/واتساب إلكتروني → «انقر هنا» | ورق/مرفق → «امسح رمز QR».
 */
function letter_end_action_cta(string $surface = 'email'): string
{
    $surface = $surface === 'print' || $surface === 'pdf' ? 'print' : 'email';
    $key = $surface === 'print' ? 'print.scan_here' : 'email.click_here';
    $fallback = $surface === 'print' ? 'امسح رمز QR' : 'انقر هنا';
    if (function_exists('__')) {
        $s = trim((string) __($key));
        if ($s !== '' && $s !== $key) {
            return $s;
        }
    }

    return $fallback;
}

/** يلحق « (انقر هنا) / (امسح رمز QR)» حسب السطح — أقواس بدل المطّة لتفادي كسر ربط العربية في PDF. */
function letter_end_with_action_cta(string $text, string $surface = 'email'): string
{
    $text = trim($text);
    $cta = letter_end_action_cta($surface);
    if ($cta === '') {
        return $text;
    }
    if ($text === '') {
        return '(' . $cta . ')';
    }
    $wrapped = '(' . $cta . ')';
    if (str_ends_with($text, $wrapped) || str_ends_with($text, $cta)
        || str_contains($text, ' — ' . $cta) || str_contains($text, ' - ' . $cta)
        || str_contains($text, $wrapped)
    ) {
        return $text;
    }
    // تجنّب خلط عبارتي البريد والورق إن وُجدت الأخرى مسبقاً
    foreach ([letter_end_action_cta('email'), letter_end_action_cta('print')] as $other) {
        if ($other === '' || $other === $cta) {
            continue;
        }
        if (str_ends_with($text, $other) || str_contains($text, '(' . $other . ')')
            || str_contains($text, ' — ' . $other) || str_contains($text, ' - ' . $other)
        ) {
            return $text;
        }
    }

    return $text . ' ' . $wrapped;
}

/** @deprecated استخدم letter_end_action_cta('email') */
function letter_email_click_here(): string
{
    return letter_end_action_cta('email');
}

/** @deprecated استخدم letter_end_with_action_cta($text, 'email') */
function letter_email_with_click_here(string $text): string
{
    return letter_end_with_action_cta($text, 'email');
}

/**
 * رابط يُرمَّز في QR الخطاب: مختصر /l/{token} إن أمكن ليطابق كثافة رمز الحضور.
 */
function letter_link_qr_encode_url(array $row): string
{
    $target = function_exists('correspondence_letter_link_url')
        ? correspondence_letter_link_url($row)
        : trim((string) ($row['letter_link_url'] ?? ''));
    if ($target === '' || !preg_match('#^https?://#i', $target)) {
        return '';
    }
    $token = strtolower(trim((string) ($row['qr_token'] ?? '')));
    if ($token !== '' && preg_match('/^[a-f0-9]{32}$/', $token) && function_exists('dms_corr_qr_base_url')) {
        return dms_corr_qr_base_url() . '/l/' . $token;
    }

    return $target;
}

/** HTML كتلة رابط الخطاب: في البريد نص قابل للنقر؛ في الورق/PDF مع QR. */
function letter_link_qr_markup(bool $email, array $row): string
{
    $targetUrl = function_exists('correspondence_letter_link_url')
        ? correspondence_letter_link_url($row)
        : trim((string) ($row['letter_link_url'] ?? ''));
    if ($targetUrl === '' || !preg_match('#^https?://#i', $targetUrl)) {
        return '';
    }
    $qrPayload = letter_link_qr_encode_url($row);
    $clickUrl = $email ? $targetUrl : $qrPayload;
    $label = function_exists('correspondence_letter_link_label')
        ? correspondence_letter_link_label($row)
        : trim((string) ($row['letter_link_label'] ?? ''));
    if ($label === '') {
        $label = $targetUrl;
    }
    $label = letter_end_note_text($label);
    $label = letter_end_with_action_cta($label, $email ? 'email' : 'print');
    $href = e($clickUrl);
    $labelHtml = e($label);
    if ($email) {
        // بريد: النص نفسه الرابط (بدون QR) — نفس خط/لون نص الخطاب
        $font = trim((string) ($row['_email_font'] ?? ''));
        if ($font === '' && function_exists('letter_email_forced_family')) {
            $font = letter_email_forced_family();
        }
        if ($font === '') {
            $font = 'Tahoma, Arial, sans-serif';
        }
        $mso = trim((string) ($row['_email_mso'] ?? 'Arial'));
        $msoStyle = function_exists('letter_email_mso_style') ? letter_email_mso_style($mso) : '';
        $textStyle = 'font-family:' . $font . ';' . $msoStyle
            . 'font-size:14pt;font-weight:normal;line-height:1.5;color:#000 !important;'
            . 'text-decoration:underline;border:0;outline:0;';

        return '<div style="margin:12px 0;font-family:' . $font . ';' . $msoStyle
            . 'font-size:14pt;color:#000;line-height:1.5">'
            . '<a href="' . $href . '" target="_blank" rel="noopener noreferrer" style="' . $textStyle . '">'
            . $labelHtml . '</a></div>';
    }
    $qrUri = function_exists('dms_qr_data_uri') ? dms_qr_data_uri($qrPayload) : '';

    $html = '<div class="letter-qr-row letter-link-qr">';
    $html .= '<span class="letter-qr-text letter-link-qr-label">' . $labelHtml . '</span>';
    if ($qrUri !== '') {
        $img = function_exists('dms_qr_img_tag') ? dms_qr_img_tag($qrUri, $label) : ('<img src="' . e($qrUri) . '" alt="' . $labelHtml . '" width="640" height="640" decoding="sync">');
        $html .= '<a class="letter-qr-code letter-link-qr-code" href="' . $href . '" target="_blank" rel="noopener noreferrer">'
            . $img
            . '</a>';
    }
    $html .= '</div>';

    return $html;
}

function letter_from_field_mark(): string
{
    return "\u{E000}";
}

function letter_mark_from_field(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    return letter_from_field_mark() . $value . letter_from_field_mark();
}

function letter_strip_from_marks(string $text): string
{
    return str_replace(letter_from_field_mark(), '', $text);
}

/**
 * يبقي الحدث الأخير فقط عند تكدس وسوم من نفس النوع
 * مثل {indent:9}{indent:5}نص{/indent}{/indent} → {indent:5}نص{/indent}
 */
function letter_collapse_redundant_wraps(string $text): string
{
    $patterns = [
        // إزاحة: آخر وسم فتح هو المعتمد
        '/((?:\{indent:\d+\})+)(.*?)((?:\{\/indent\})+)/su',
        '/((?:\{ls:\d+\})+)(.*?)((?:\{\/ls\})+)/su',
        '/((?:\{sb:\d+\})+)(.*?)((?:\{\/sb\})+)/su',
        '/((?:\{sa:\d+\})+)(.*?)((?:\{\/sa\})+)/su',
        '/((?:\{font:[a-z0-9_]+\})+)(.*?)((?:\{\/font\})+)/siu',
    ];
    foreach ($patterns as $pattern) {
        $text = preg_replace_callback($pattern, static function (array $m): string {
            $opens = $m[1];
            $inner = $m[2];
            if (preg_match_all('/\{((?:indent|ls|sb|sa):\d+|font:[a-z0-9_]+)\}/i', $opens, $tags) && $tags[1] !== []) {
                $last = (string) end($tags[1]);

                return '{' . $last . '}' . $inner . '{/' . (str_contains($last, ':') ? explode(':', $last, 2)[0] : $last) . '}';
            }

            return $m[0];
        }, $text) ?? $text;
    }

    // محاذاة متداخلة/متتابعة: آخر محاذاة تفوز
    $text = preg_replace_callback(
        '/((?:\{(?:left|right|center|justify)\})+)(.*?)((?:\{\/(?:left|right|center|justify)\})+)/su',
        static function (array $m): string {
            if (preg_match_all('/\{(left|right|center|justify)\}/i', $m[1], $tags) && $tags[1] !== []) {
                $last = strtolower((string) end($tags[1]));
                $inner = preg_replace('/\{\/?(?:left|right|center|justify)\}/i', '', $m[2]) ?? $m[2];

                return '{' . $last . '}' . $inner . '{/' . $last . '}';
            }

            return $m[0];
        },
        $text
    ) ?? $text;

    return $text;
}

/**
 * يحوّل الأسطر المُزاحة بمسافات كثيرة إلى وسم إزاحة (وليس محاذاة يسار كاملة).
 */
function letter_normalize_space_indents(string $text): string
{
    $parts = preg_split("/(\r\n|\n|\r)/", $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    if ($parts === false || $parts === []) {
        return $text;
    }
    $out = [];
    foreach ($parts as $part) {
        if ($part === "\r\n" || $part === "\n" || $part === "\r") {
            $out[] = $part;
            continue;
        }
        if (preg_match('/^( {20,})(\S.*)$/u', $part, $m) === 1) {
            $content = trim($m[2]);
            if ($content !== ''
                && !preg_match('/\{(?:left|right|center|justify|indent:\d+)\}/i', $content)) {
                // إزاحة متوسطة (~9 سم) بدل لصق النص على الهامش الأيسر
                $out[] = '{indent:9}' . $content . '{/indent}';
                continue;
            }
        }
        $out[] = $part;
    }

    return implode('', $out);
}

function render_letter_body_html(string $text, bool $email = false, array $row = []): string
{
    $text = letter_normalize_indent_honorific_spread(
        letter_collapse_redundant_wraps(letter_normalize_space_indents($text)),
        $row
    );
    if ($email) {
        $row['_email_font'] = letter_email_forced_family();
        $row['_email_mso'] = 'Arial';
        if (empty($row['_email_dir'])) {
            $lang = 'ar';
            if (function_exists('correspondence_letter_channel_lang')) {
                $lang = correspondence_letter_channel_lang($row);
            } elseif (!empty($row['_email_lang'])) {
                $lang = strtolower((string) $row['_email_lang']) === 'en' ? 'en' : 'ar';
            }
            $row['_email_dir'] = $lang === 'en' ? 'ltr' : 'rtl';
            $row['_email_lang'] = $lang;
        }
    }

    return letter_strip_from_marks(letter_format_segment($text, $email, $row));
}

function letter_relative_disk_path(string $rel): string
{
    $rel = trim(str_replace('\\', '/', $rel));
    $rel = ltrim($rel, '/');
    if ($rel === '' || str_contains($rel, '..')) {
        return '';
    }
    $full = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);

    return is_file($full) ? $full : '';
}

/**
 * @return array{w:int,h:int}
 */
function letter_image_fit_px(string $disk, int $maxW, int $maxH): array
{
    $maxW = max(1, $maxW);
    $maxH = max(1, $maxH);
    $info = @getimagesize($disk);
    if (!is_array($info) || (int) $info[0] < 1 || (int) $info[1] < 1) {
        return ['w' => $maxW, 'h' => $maxH];
    }
    $srcW = (int) $info[0];
    $srcH = (int) $info[1];
    $scale = min($maxW / $srcW, $maxH / $srcH, 1.0);
    return [
        'w' => max(1, (int) round($srcW * $scale)),
        'h' => max(1, (int) round($srcH * $scale)),
    ];
}

function letter_email_resample_png(string $disk, int $width, int $height): string
{
    if ($disk === '' || !is_file($disk) || $width < 1 || $height < 1 || !function_exists('imagecreatetruecolor')) {
        return '';
    }
    $info = @getimagesize($disk);
    if (!is_array($info)) {
        return '';
    }
    $src = match ((int) ($info[2] ?? 0)) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($disk),
        IMAGETYPE_PNG => @imagecreatefrompng($disk),
        IMAGETYPE_GIF => @imagecreatefromgif($disk),
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($disk) : false,
        default => false,
    };
    if ($src === false) {
        return '';
    }
    $dst = imagecreatetruecolor($width, $height);
    if ($dst === false) {
        imagedestroy($src);
        return '';
    }
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
    if ($transparent !== false) {
        imagefilledrectangle($dst, 0, 0, $width, $height, $transparent);
    }
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $width, $height, imagesx($src), imagesy($src));
    $tmp = tempnam(sys_get_temp_dir(), 'dms_sig_');
    if ($tmp === false) {
        imagedestroy($src);
        imagedestroy($dst);
        return '';
    }
    $png = $tmp . '.png';
    @unlink($tmp);
    $ok = imagepng($dst, $png, 6);
    imagedestroy($src);
    imagedestroy($dst);
    if (!$ok || !is_file($png)) {
        @unlink($png);
        return '';
    }

    return $png;
}

/**
 * @return array{path:string,w:int,h:int,cleanup:string}
 */
function letter_email_prepared_image(string $disk, int $maxW, int $maxH): array
{
    $fit = letter_image_fit_px($disk, $maxW, $maxH);
    $prepared = [
        'path' => $disk,
        'w' => $fit['w'],
        'h' => $fit['h'],
        'cleanup' => '',
    ];
    $tmp = letter_email_resample_png($disk, $fit['w'], $fit['h']);
    if ($tmp !== '') {
        $prepared['path'] = $tmp;
        $prepared['cleanup'] = $tmp;
    }

    return $prepared;
}

function letter_email_img_tag(string $src, string $alt, int $width, int $height): string
{
    $width = max(1, $width);
    $height = max(1, $height);

    return '<img src="' . e($src) . '" alt="' . $alt . '" width="' . $width . '" height="' . $height . '" '
        . 'style="width:' . $width . 'px;height:' . $height . 'px;max-width:' . $width . 'px;max-height:' . $height . 'px;'
        . 'display:block;border:0;outline:none;">';
}

function letter_logo_markup(bool $email = false, array $row = []): string
{
    $cid = trim((string) ($row['logo_cid'] ?? ''));
    $url = function_exists('org_logo_url') ? org_logo_url() : '';
    $src = ($email && $cid !== '') ? ('cid:' . $cid) : $url;
    if ($src === '') {
        return '';
    }
    $alt = e(function_exists('dms_org_display_name') ? dms_org_display_name() : '');
    if ($email) {
        $w = (int) ($row['_logo_img_w'] ?? 0);
        $h = (int) ($row['_logo_img_h'] ?? 0);
        if ($w < 1 || $h < 1) {
            $disk = function_exists('dms_org_logo_disk_path') ? dms_org_logo_disk_path() : '';
            $fit = $disk !== '' ? letter_image_fit_px($disk, 160, 56) : ['w' => 160, 'h' => 56];
            $w = $fit['w'];
            $h = $fit['h'];
        }
        return letter_email_img_tag($src, $alt, $w, $h);
    }
    return '<img src="' . e($src) . '" alt="' . $alt . '" class="letter-logo">';
}

/**
 * @return array{path:string,disk:string,url:string,cid:string,name:string,titles:list<string>}
 */
function letter_signature_context(array $row = []): array
{
    $path = trim((string) ($row['snap_from_signature'] ?? ''));
    $prefix = trim((string) ($row['snap_from_prefix'] ?? ''));
    $person = trim((string) ($row['snap_from_person'] ?? ''));
    $job = trim((string) ($row['snap_from_job'] ?? ''));

    $deptId = (int) ($row['issuing_department_id'] ?? 0);
    if ($deptId <= 0) {
        $deptId = (int) ($row['department_id'] ?? 0);
    }
    if ($deptId > 0 && ($path === '' || $person === '' || $prefix === '' || $job === '')) {
        $dept = function_exists('admin_row') ? admin_row('tbldepartments', $deptId) : null;
        if (is_array($dept)) {
            if ($path === '' && function_exists('department_principal_signature_path')) {
                $path = department_principal_signature_path($dept);
            }
            $officer = function_exists('department_officer_fields') ? department_officer_fields($dept) : [
                'title_prefix' => trim((string) ($dept['title_prefix'] ?? '')),
                'directed_from' => trim((string) ($dept['directed_from'] ?? '')),
                'job_title' => trim((string) ($dept['job_title'] ?? '')),
            ];
            if ($person === '') {
                $person = $officer['directed_from'];
            }
            if ($prefix === '') {
                $prefix = $officer['title_prefix'];
            }
            if ($job === '') {
                $job = $officer['job_title'];
            }
        }
    }

    $url = '';
    if ($path !== '' && function_exists('base_url')) {
        $url = base_url($path);
    }

    return [
        'path' => $path,
        'disk' => $path !== '' ? letter_relative_disk_path($path) : '',
        'url' => $url,
        'cid' => trim((string) ($row['signature_cid'] ?? '')),
        'prefix' => $prefix,
        'person' => $person,
        'job' => $job,
        'name' => trim($prefix . ' ' . $person),
        'titles' => $job !== '' ? [$job] : [],
    ];
}

/** اللقب، ثم الاسم، ثم الوظيفة — كلٌ في سطر. */
function letter_signer_lines(array $row): array
{
    $ctx = letter_signature_context($row);
    $lines = [];
    foreach (['prefix', 'person', 'job'] as $key) {
        $line = trim((string) ($ctx[$key] ?? ''));
        if ($line !== '') {
            $lines[] = $line;
        }
    }

    return $lines;
}

function letter_body_has_signature(string $text): bool
{
    return stripos($text, '{signature}') !== false;
}

function letter_signature_markup(bool $email = false, array $row = []): string
{
    $ctx = letter_signature_context($row);
    $showSignature = letter_display_flags($row)['signature'];
    $prefix = '';
    $person = '';
    $job = '';
    $src = '';
    if ($showSignature && $email && $ctx['cid'] !== '') {
        $src = 'cid:' . $ctx['cid'];
    } elseif ($showSignature && $ctx['url'] !== '') {
        $src = $ctx['url'];
    }
    $img = '';
    if ($src !== '') {
        $altName = trim((string) ($ctx['name'] ?? ''));
        $alt = e($altName !== '' ? $altName : (function_exists('dms_org_display_name') ? dms_org_display_name() : ''));
        if ($email) {
            $w = (int) ($row['_sig_img_w'] ?? 0);
            $h = (int) ($row['_sig_img_h'] ?? 0);
            if ($w < 1 || $h < 1) {
                $fit = $ctx['disk'] !== '' ? letter_image_fit_px($ctx['disk'], 140, 48) : ['w' => 140, 'h' => 48];
                $w = $fit['w'];
                $h = $fit['h'];
            }
            $img = letter_email_img_tag($src, $alt, $w, $h);
        } else {
            $img = '<img src="' . e($src) . '" alt="' . $alt . '" class="letter-signature-img">';
        }
    }
    $ff = $email ? letter_email_style($row) : '';
    $lines = '';
    if ($prefix !== '') {
        $lines .= $email
            ? '<div style="' . $ff . 'margin:0;color:#000">' . e($prefix) . '</div>'
            : '<div class="letter-signature-title">' . e($prefix) . '</div>';
    }
    if ($person !== '') {
        $lines .= $email
            ? '<div style="' . $ff . 'font-weight:700;margin:0;color:#000">' . e($person) . '</div>'
            : '<div class="letter-signature-name">' . e($person) . '</div>';
    }
    if ($job !== '') {
        $lines .= $email
            ? '<div style="' . $ff . 'margin:0;color:#000">' . e($job) . '</div>'
            : '<div class="letter-signature-title">' . e($job) . '</div>';
    }
    if ($img === '' && $lines === '') {
        return '';
    }
    if ($email) {
        $mark = $img !== '' ? '<div style="margin:0 0 18px">' . $img . '</div>' : '';

        return '<div style="' . $ff . 'margin:18px 0 8px;display:inline-block;max-width:240px">' . $mark . $lines . '</div>';
    }
    $mark = $img !== '' ? '<div class="letter-signature-mark">' . $img . '</div>' : '';
    $identity = $lines !== '' ? '<div class="letter-signature-identity">' . $lines . '</div>' : '';

    return '<div class="letter-signature">' . $mark . $identity . '</div>';
}

/**
 * يحافظ على المسافات في HTML (المتصفح يطويها وإلا تُلغى محاذاة مثل «الأمانة العامة»).
 */
function letter_html_preserve_spaces(string $escapedHtml): string
{
    // مسافات بادئة بعد بداية السطر أو <br>
    $escapedHtml = preg_replace_callback(
        '/(^|<br\s*\/?>)( +)/i',
        static function (array $m): string {
            return $m[1] . str_repeat('&nbsp;', strlen($m[2]));
        },
        $escapedHtml
    ) ?? $escapedHtml;

    return preg_replace_callback('/ {2,}/', static function (array $m): string {
        return str_repeat('&nbsp;', strlen($m[0]));
    }, $escapedHtml) ?? $escapedHtml;
}

function letter_text_html(string $text, bool $email = false, array $row = []): string
{
    // وسوم تنسيق يتيمة (مثل {/font} بلا فتح) لا تُعرض كنص
    $text = preg_replace(
        '#\{/(?:font|ls|indent|spread|sb|sa|center|right|left|justify|ul|b|u|i|big)\}#i',
        '',
        $text
    ) ?? $text;
    $text = preg_replace(
        '#\{(?:font:[a-z0-9_]+|ls:[0-9]+|indent:[0-9]+|spread:[0-9]+|sb:[0-9]+|sa:[0-9]+|spread_sep|center|right|left|justify|ul|b|u|i|big)\}#i',
        '',
        $text
    ) ?? $text;

    $parts = preg_split('/(\{logo\}|\{signature\}|\{link_qr\})/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    if ($parts === false) {
        return letter_html_preserve_spaces(nl2br(e($text)));
    }
    $html = [];
    foreach ($parts as $part) {
        if ($part === '{logo}') {
            $html[] = letter_logo_markup($email, $row);
            continue;
        }
        if ($part === '{signature}') {
            $html[] = letter_signature_markup($email, $row);
            continue;
        }
        if ($part === '{link_qr}') {
            $html[] = letter_link_qr_markup($email, $row);
            continue;
        }
        $chunk = letter_html_preserve_spaces(nl2br(e($part)));
        if ($email) {
            $html[] = '<span style="' . letter_email_style($row) . '">' . $chunk . '</span>';
        } else {
            $html[] = $chunk;
        }
    }
    return implode('', $html);
}

function letter_ul_html(string $innerHtml, bool $email, array $row = []): string
{
    $parts = preg_split('#<br\s*/?>#i', $innerHtml) ?: [$innerHtml];
    $items = [];
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '') {
            continue;
        }
        $items[] = $email
            ? '<li style="' . letter_email_style($row) . '">' . $part . '</li>'
            : '<li>' . $part . '</li>';
    }
    if (!$items) {
        return $innerHtml;
    }
    if ($email) {
        return '<ul style="' . letter_email_style($row, 'margin:8px 0;padding-inline-start:22px') . '">' . implode('', $items) . '</ul>';
    }

    return '<ul class="letter-ul">' . implode('', $items) . '</ul>';
}

function letter_format_segment(string $text, bool $email, array $row = []): string
{
    if (!preg_match('#\{(center|right|left|justify|ul|b|u|i|big|font:[a-z0-9_]+|ls:[0-9]+|indent:[0-9]+|spread:[0-9]+|sb:[0-9]+|sa:[0-9]+)\}#', $text, $m, PREG_OFFSET_CAPTURE)) {
        return letter_text_html($text, $email, $row);
    }
    $openRaw = $m[0][0];
    $tag = $m[1][0];
    $pos = (int) $m[0][1];
    $close = '{/' . $tag . '}';
    if (strncmp($tag, 'font:', 5) === 0) {
        $close = '{/font}';
    } elseif (strncmp($tag, 'ls:', 3) === 0) {
        $close = '{/ls}';
    } elseif (strncmp($tag, 'indent:', 7) === 0) {
        $close = '{/indent}';
    } elseif (strncmp($tag, 'spread:', 7) === 0) {
        $close = '{/spread}';
    } elseif (strncmp($tag, 'sb:', 3) === 0) {
        $close = '{/sb}';
    } elseif (strncmp($tag, 'sa:', 3) === 0) {
        $close = '{/sa}';
    }
    $innerStart = $pos + strlen($openRaw);
    $closePos = strpos($text, $close, $innerStart);
    if ($closePos === false) {
        // وسم فتح بلا إغلاق: تجاهل الفتحة وأكمل بالمحتوى بعده
        return letter_text_html(substr($text, 0, $pos), $email, $row)
            . letter_format_segment(substr($text, $innerStart), $email, $row);
    }

    $innerRow = $row;
    if (strncmp($tag, 'font:', 5) === 0) {
        $code = letter_font_normalize_code(substr($tag, 5));
        $fonts = letter_font_options();
        if (isset($fonts[$code])) {
            $innerRow['_email_font'] = (string) $fonts[$code]['email'];
            $innerRow['_email_mso'] = (string) ($fonts[$code]['mso'] ?? 'Arial');
        }
    }

    $rawInner = substr($text, $innerStart, $closePos - $innerStart);
    $before = substr($text, 0, $pos);
    $after = substr($text, $closePos + strlen($close));
    // لا تضف سطر Enter فوق مسافة قبل/بعد — القيمة بالسنتيمتر وحدها
    if (strncmp($tag, 'sa:', 3) === 0 || strncmp($tag, 'sb:', 3) === 0) {
        $after = preg_replace('/^\r?\n/', '', $after, 1) ?? $after;
        if (strncmp($tag, 'sb:', 3) === 0) {
            $before = preg_replace('/\r?\n$/', '', $before, 1) ?? $before;
        }
    }
    if (strncmp($tag, 'spread:', 7) === 0) {
        $bits = explode('{spread_sep}', $rawInner, 2);
        $mainHtml = letter_format_segment((string) ($bits[0] ?? ''), $email, $innerRow);
        $endHtml = letter_format_segment((string) ($bits[1] ?? ''), $email, $innerRow);

        return letter_text_html($before, $email, $row)
            . letter_format_wrap($tag, $mainHtml . '{spread_sep}' . $endHtml, $email, $innerRow)
            . letter_format_segment($after, $email, $row);
    }

    return letter_text_html($before, $email, $row)
        . letter_format_wrap(
            $tag,
            letter_format_segment($rawInner, $email, $innerRow),
            $email,
            $innerRow
        )
        . letter_format_segment($after, $email, $row);
}

function letter_format_wrap(string $tag, string $innerHtml, bool $email, array $row = []): string
{
    if (strncmp($tag, 'font:', 5) === 0) {
        $code = letter_font_normalize_code(substr($tag, 5));
        $fonts = letter_font_options();
        if (!isset($fonts[$code])) {
            return $innerHtml;
        }
        if ($email) {
            return '<span style="' . letter_email_style($row) . '">' . $innerHtml . '</span>';
        }

        return '<span class="letter-font-' . $code . '">' . $innerHtml . '</span>';
    }

    if (strncmp($tag, 'ls:', 3) === 0) {
        $code = substr($tag, 3);
        $opts = letter_line_spacing_options();
        if (!isset($opts[$code])) {
            return $innerHtml;
        }
        if ($email) {
            return '<div style="' . letter_email_style($row, 'line-height:' . $opts[$code]['css'] . ';margin:0 0 8px') . '">' . $innerHtml . '</div>';
        }

        return '<div class="letter-ls-' . $code . '">' . $innerHtml . '</div>';
    }

    if (strncmp($tag, 'spread:', 7) === 0) {
        $code = letter_indent_normalize_code(substr($tag, 7));
        $opts = letter_indent_options();
        $bits = explode('{spread_sep}', $innerHtml, 2);
        $mainHtml = (string) ($bits[0] ?? '');
        $endHtml = (string) ($bits[1] ?? '');
        if ($mainHtml === '' || $endHtml === '') {
            return $mainHtml . $endHtml;
        }
        $gap = ($code !== '' && isset($opts[$code])) ? (string) $opts[$code]['css'] : '2cm';
        if ($email) {
            return '<table class="letter-spread" width="100%" cellpadding="0" cellspacing="0" role="presentation" style="'
                . letter_email_style($row, 'width:100%;margin:0 0 8px;border-collapse:collapse') . '"><tr>'
                . '<td style="' . letter_email_style($row, 'text-align:right;vertical-align:baseline;padding:0') . '">' . $mainHtml . '</td>'
                . '<td style="' . letter_email_style($row, 'width:' . $gap . ';min-width:' . $gap . ';padding:0;font-size:1px;line-height:1px') . '">&nbsp;</td>'
                . '<td style="' . letter_email_style($row, 'text-align:left;vertical-align:baseline;padding:0;white-space:nowrap') . '">' . $endHtml . '</td>'
                . '</tr></table>';
        }

        return '<div class="letter-spread letter-spread-' . ($code !== '' ? $code : '9') . '">'
            . '<span class="letter-spread-main">' . $mainHtml . '</span>'
            . '<span class="letter-spread-gap" aria-hidden="true"></span>'
            . '<span class="letter-spread-end">' . $endHtml . '</span>'
            . '</div>';
    }

    if (strncmp($tag, 'sb:', 3) === 0 || strncmp($tag, 'sa:', 3) === 0) {
        $kind = strncmp($tag, 'sb:', 3) === 0 ? 'sb' : 'sa';
        $code = letter_vspace_normalize_code(substr($tag, 3));
        $opts = letter_vspace_options();
        if ($code === '' || !isset($opts[$code])) {
            return $innerHtml;
        }
        $css = (string) $opts[$code]['css'];
        if ($email) {
            $pad = ($kind === 'sb' ? 'padding-top:' : 'padding-bottom:') . $css . ';margin:0';

            return '<div style="' . letter_email_style($row, $pad) . '">' . $innerHtml . '</div>';
        }

        return '<div class="letter-vspace letter-vspace-' . $kind . ' letter-vspace-' . $kind . '-' . $code . '">' . $innerHtml . '</div>';
    }

    if (strncmp($tag, 'indent:', 7) === 0) {
        // الإزاحة للخطاب المطبوع فقط — البريد بمحاذاة موحّدة
        if ($email) {
            return $innerHtml;
        }
        $code = letter_indent_normalize_code(substr($tag, 7));
        $opts = letter_indent_options();
        if ($code === '' || !isset($opts[$code])) {
            return $innerHtml;
        }

        // فجوة قبل النص داخل نفس السطر (ليس <div> ولا سطر جديد)
        return '<span class="letter-indent letter-indent-' . $code . '">'
            . '<span class="letter-indent-gap" aria-hidden="true"></span>'
            . $innerHtml
            . '</span>';
    }

    if ($tag === 'ul') {
        return letter_ul_html($innerHtml, $email, $row);
    }

    if ($email) {
        // المحاذاة/الإزاحة للخطاب فقط؛ البريد يبقى بمحاذاة واحدة لكل النص
        return match ($tag) {
            'center', 'right', 'left', 'justify' => $innerHtml,
            'b' => '<strong style="' . letter_email_style($row, 'font-weight:700') . '">' . $innerHtml . '</strong>',
            'i' => '<em style="' . letter_email_style($row, 'font-style:italic') . '">' . $innerHtml . '</em>',
            'u' => '<span style="' . letter_email_style($row, 'text-decoration:underline') . '">' . $innerHtml . '</span>',
            'big' => '<span style="' . letter_email_style($row, 'font-size:16pt;font-weight:600') . '">' . $innerHtml . '</span>',
            default => $innerHtml,
        };
    }

    $class = match ($tag) {
        'center' => 'letter-align-center',
        'right' => 'letter-align-right',
        'left' => 'letter-align-left',
        'justify' => 'letter-align-justify',
        'b' => 'letter-fmt-b',
        'i' => 'letter-fmt-i',
        'u' => 'letter-fmt-u',
        'big' => 'letter-fmt-big',
        default => '',
    };
    $el = in_array($tag, ['center', 'right', 'left', 'justify'], true) ? 'div' : 'span';

    return '<' . $el . ' class="' . $class . '">' . $innerHtml . '</' . $el . '>';
}
