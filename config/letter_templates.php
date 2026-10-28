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
        ['kind' => 'underline', 'wrap' => ['{u}', '{/u}'], 'label' => __('tpl.token_underline'), 'tip' => __('tpl.token_underline_tip'), 'icon' => 'bi-type-underline'],
        ['kind' => 'big', 'wrap' => ['{big}', '{/big}'], 'label' => __('tpl.token_big'), 'tip' => __('tpl.token_big_tip'), 'icon' => 'bi-type-h2'],
        ['token' => '{number}', 'label' => __('tpl.token_number'), 'tip' => __('tpl.token_number_tip'), 'icon' => 'bi-hash'],
        ['token' => '{title}', 'label' => __('tpl.token_title'), 'tip' => __('tpl.token_title_tip'), 'icon' => 'bi-award'],
        ['token' => '{name}', 'label' => __('tpl.token_name'), 'tip' => __('tpl.token_name_tip'), 'icon' => 'bi-person'],
        ['token' => '{honorific}', 'label' => __('tpl.token_honorific'), 'tip' => __('tpl.token_honorific_tip'), 'icon' => 'bi-heart'],
        ['token' => '{honored_name}', 'label' => __('tpl.token_honored'), 'tip' => __('tpl.token_honored_tip'), 'icon' => 'bi-person-badge'],
        ['token' => '{job}', 'label' => __('tpl.token_job'), 'tip' => __('tpl.token_job_tip'), 'icon' => 'bi-briefcase'],
        ['token' => '{org}', 'label' => __('tpl.token_org'), 'tip' => __('tpl.token_org_tip'), 'icon' => 'bi-building'],
        ['token' => '{subject}', 'label' => __('tpl.token_subject'), 'tip' => __('tpl.token_subject_tip'), 'icon' => 'bi-chat-text'],
        ['token' => '{logo}', 'label' => __('tpl.token_logo'), 'tip' => __('tpl.token_logo_tip'), 'icon' => 'bi-image'],
        ['token' => '{signature}', 'label' => __('tpl.token_signature'), 'tip' => __('tpl.token_signature_tip'), 'icon' => 'bi-pen'],
    ];
}

/** اسما صاحب الشأن ووظيفته، منفصلان عن صورة التوقيع. */
function letter_signer_insert_tokens(): array
{
    return [
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
        $style .= 'font-size:12pt;';
    }
    $style .= 'line-height:1.5;';
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

function render_letter_line_spacing_picker(): void
{
    ?>
    <label class="letter-token-btn is-format is-font"
           data-bs-toggle="tooltip" data-bs-placement="top"
           title="<?= e(__('tpl.token_ls_tip')) ?>">
        <i class="bi bi-distribute-vertical" aria-hidden="true"></i>
        <select class="letter-font-select letter-ls-select" data-letter-ls dir="ltr" aria-label="<?= e(__('tpl.token_ls')) ?>">
            <option value=""><?= e(__('tpl.token_ls')) ?></option>
            <?php foreach (letter_line_spacing_options() as $code => $opt): ?>
                <option value="<?= e($code) ?>"><?= e($opt['label']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <?php
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
    $attrs = '';
    if ($kind === 'undo') {
        $attrs = 'data-letter-undo="1" disabled';
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
    <button type="button" class="letter-token-btn<?= $isFormat ? ' is-format' : '' ?>"
            <?= $attrs ?>
            data-bs-toggle="tooltip" data-bs-placement="top"
            title="<?= e($item['tip']) ?>">
        <i class="bi <?= e($item['icon']) ?>" aria-hidden="true"></i>
        <span><?= e($item['label']) ?></span>
    </button>
    <?php
}

function render_letter_placeholder_toolbar(string $textareaId, string $previewLang = 'ar', string $initialText = '', array $tokenTips = [], array $hideTokens = [], array $extraTokens = []): void
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
        <div class="letter-token-row is-format">
            <?php foreach ($format as $item): ?>
                <?php render_letter_token_button($item); ?>
            <?php endforeach; ?>
            <?php render_letter_line_spacing_picker(); ?>
            <button type="button" class="letter-token-btn is-format"
                    data-letter-clear-format="1"
                    data-bs-toggle="tooltip" data-bs-placement="top"
                    title="<?= e(__('tpl.token_clear_format_tip')) ?>">
                <i class="bi bi-eraser" aria-hidden="true"></i>
                <span><?= e(__('tpl.token_clear_format')) ?></span>
            </button>
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

function render_letter_lang_editor(string $arId, string $enId, string $arValue, string $enValue, int $rows = 10, bool $arRequired = false, array $tokenTips = [], array $hideTokens = [], string $hint = '', array $extraTokens = []): void
{
    $paneAr = $arId . '-pane';
    $paneEn = $enId . '-pane';
    ?>
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
    <div class="tab-content letter-lang-panes">
        <div class="tab-pane fade show active" id="<?= e($paneAr) ?>" role="tabpanel">
            <?php render_letter_placeholder_toolbar($arId, 'ar', $arValue, $tokenTips, $hideTokens, $extraTokens); ?>
            <textarea name="<?= e($arId) ?>" id="<?= e($arId) ?>" class="form-control" rows="<?= (int) $rows ?>"<?= $arRequired ? ' required' : '' ?>><?= e($arValue) ?></textarea>
        </div>
        <div class="tab-pane fade" id="<?= e($paneEn) ?>" role="tabpanel">
            <?php render_letter_placeholder_toolbar($enId, 'en', $enValue, $tokenTips, $hideTokens, $extraTokens); ?>
            <textarea name="<?= e($enId) ?>" id="<?= e($enId) ?>" class="form-control" rows="<?= (int) $rows ?>" dir="ltr"><?= e($enValue) ?></textarea>
        </div>
    </div>
    <p class="admin-form-hint mb-0 mt-2"><?= e($hint !== '' ? $hint : __('tpl.body_bilingual_hint')) ?></p>
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
    if ($subject !== '') {
        $inner .= '<h2 class="letter-preview-subject">' . e($subject) . '</h2>';
    }
    if ($filled !== '') {
        $inner .= render_letter_body_html($filled, false, $row);
    }
    if ($needsRsvp) {
        $ask = e($t(!empty($row['intend_electronic']) ? 'rsvp.email_ask' : 'rsvp.print_ask'));
        $qrUri = function_exists('dms_corr_qr_data_uri') ? dms_corr_qr_data_uri($row, 160, true) : '';
        $inner .= '<div class="letter-rsvp-block letter-preview-rsvp">';
        $inner .= '<div class="letter-rsvp-note">' . $ask . '</div>';
        if ($qrUri !== '') {
            $inner .= '<div class="letter-rsvp-qr" aria-hidden="true">'
                . '<img src="' . e($qrUri) . '" alt="" width="160" height="160">'
                . '</div>';
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
    <div class="modal fade" id="letterPreviewModal" tabindex="-1" aria-labelledby="letterPreviewModalLabel" aria-hidden="true"
         data-preview-url="<?= e(base_url('admin/letter_preview.php')) ?>"
         data-preview-fail="<?= e(__('tpl.preview_fail')) ?>">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content letter-preview-modal">
                <div class="modal-header">
                    <h5 class="modal-title" id="letterPreviewModalLabel">
                        <i class="bi bi-eye me-1"></i><?= e(__('tpl.preview')) ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(__('action.cancel')) ?>"></button>
                </div>
                <div class="modal-body">
                    <p class="admin-form-hint mb-3"><?= e(__('tpl.preview_sample')) ?></p>
                    <div class="letter-preview-sheet" data-letter-preview-body></div>
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
    $ctx = letter_signature_context($row);
    $signaturePlain = in_array((string) ($row['type'] ?? ''), ['outgoing', 'circular'], true) ? '' : $ctx['name'];
    $text = str_replace('{signature}', $signaturePlain, $text);
    $text = str_replace(['{center}', '{/center}', '{right}', '{/right}', '{left}', '{/left}', '{justify}', '{/justify}', '{ul}', '{/ul}', '{/font}', '{/ls}', '{logo}'], '', $text);
    return $text;
}

function render_letter_body_html(string $text, bool $email = false, array $row = []): string
{
    if ($email) {
        $row['_email_font'] = letter_email_forced_family();
        $row['_email_mso'] = 'Arial';
    }

    return letter_format_segment($text, $email, $row);
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
        'name' => trim($prefix . ' ' . $person),
        'titles' => $job !== '' ? [$job] : [],
    ];
}

function letter_signature_markup(bool $email = false, array $row = []): string
{
    $ctx = letter_signature_context($row);
    $imageOnly = in_array((string) ($row['type'] ?? ''), ['outgoing', 'circular'], true);
    $name = $imageOnly ? '' : $ctx['name'];
    $titles = $imageOnly ? [] : $ctx['titles'];
    $src = '';
    if ($email && $ctx['cid'] !== '') {
        $src = 'cid:' . $ctx['cid'];
    } elseif ($ctx['url'] !== '') {
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
    if ($name !== '') {
        $lines .= $email
            ? '<div style="' . $ff . 'font-weight:700;margin:0 0 2px">' . e($name) . '</div>'
            : '<div class="letter-signature-name">' . e($name) . '</div>';
    }
    foreach ($titles as $title) {
        $title = trim((string) $title);
        if ($title === '') {
            continue;
        }
        $lines .= $email
            ? '<div style="' . $ff . 'font-size:11pt;color:#445;margin:8px 0 0">' . e($title) . '</div>'
            : '<div class="letter-signature-title">' . e($title) . '</div>';
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

function letter_text_html(string $text, bool $email = false, array $row = []): string
{
    // وسوم تنسيق يتيمة (مثل {/font} بلا فتح) لا تُعرض كنص
    $text = preg_replace(
        '#\{/(?:font|ls|center|right|left|justify|ul|b|u|big)\}#i',
        '',
        $text
    ) ?? $text;
    $text = preg_replace(
        '#\{(?:font:[a-z0-9_]+|ls:[0-9]+|center|right|left|justify|ul|b|u|big)\}#i',
        '',
        $text
    ) ?? $text;

    $parts = preg_split('/(\{logo\}|\{signature\})/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    if ($parts === false) {
        return nl2br(e($text));
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
        if ($email) {
            $html[] = '<span style="' . letter_email_style($row) . '">' . nl2br(e($part)) . '</span>';
        } else {
            $html[] = nl2br(e($part));
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
    if (!preg_match('#\{(center|right|left|justify|ul|b|u|big|font:[a-z0-9_]+|ls:[0-9]+)\}#', $text, $m, PREG_OFFSET_CAPTURE)) {
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

    return letter_text_html(substr($text, 0, $pos), $email, $row)
        . letter_format_wrap(
            $tag,
            letter_format_segment(substr($text, $innerStart, $closePos - $innerStart), $email, $innerRow),
            $email,
            $innerRow
        )
        . letter_format_segment(substr($text, $closePos + strlen($close)), $email, $row);
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

    if ($tag === 'ul') {
        return letter_ul_html($innerHtml, $email, $row);
    }

    if ($email) {
        return match ($tag) {
            'center' => '<div style="' . letter_email_style($row, 'text-align:center;margin:0 0 8px') . '">' . $innerHtml . '</div>',
            'right' => '<div style="' . letter_email_style($row, 'text-align:right;margin:0 0 8px') . '">' . $innerHtml . '</div>',
            'left' => '<div style="' . letter_email_style($row, 'text-align:left;margin:0 0 8px') . '">' . $innerHtml . '</div>',
            'justify' => '<div style="' . letter_email_style($row, 'text-align:justify;text-align-last:start;margin:0 0 8px') . '">' . $innerHtml . '</div>',
            'b' => '<strong style="' . letter_email_style($row, 'font-weight:700') . '">' . $innerHtml . '</strong>',
            'u' => '<span style="' . letter_email_style($row, 'text-decoration:underline') . '">' . $innerHtml . '</span>',
            'big' => '<span style="' . letter_email_style($row, 'font-size:14.5pt;font-weight:600') . '">' . $innerHtml . '</span>',
            default => $innerHtml,
        };
    }

    $class = match ($tag) {
        'center' => 'letter-align-center',
        'right' => 'letter-align-right',
        'left' => 'letter-align-left',
        'justify' => 'letter-align-justify',
        'b' => 'letter-fmt-b',
        'u' => 'letter-fmt-u',
        'big' => 'letter-fmt-big',
        default => '',
    };
    $el = in_array($tag, ['center', 'right', 'left', 'justify'], true) ? 'div' : 'span';

    return '<' . $el . ' class="' . $class . '">' . $innerHtml . '</' . $el . '>';
}
