<?php
/**
 * مراجع المساعدة — فهرس وقراءة آمنة لملفات doc/*.md
 * - مساعدة المستخدم التشغيلي: dms_user_help_docs_catalog()
 * - مرجع مدير النظام: dms_help_docs_catalog()
 */

/** @return list<array{group_ar:string,group_en:string,icon:string,docs:list<array<string,string>>}> */
function dms_user_help_docs_catalog(): array
{
    // دليل العمل اليومي للمستخدمين — بلا تثبيت/نسخ احتياطي/إعدادات تقنية
    return [
        [
            'group_ar' => 'دليل العمل',
            'group_en' => 'How to work',
            'icon' => 'bi-journal-text',
            'docs' => [
                ['id' => '31-help-quick', 'file' => '31-help-quick.md', 'title_ar' => 'مساعدة سريعة', 'title_en' => 'Quick help', 'summary_ar' => 'أين تجد كل شيء في سطور قليلة', 'summary_en' => 'Where to find everything in a few lines'],
                ['id' => '19-user-guide', 'file' => '19-user-guide.md', 'title_ar' => 'دليل المستخدم', 'title_en' => 'User guide', 'summary_ar' => 'الوارد والصادر والداخلي خطوة بخطوة', 'summary_en' => 'Incoming, outgoing, and internal step by step'],
                ['id' => '22-actions-queue', 'file' => '22-actions-queue.md', 'title_ar' => 'يحتاج إجراء', 'title_en' => 'Actions queue', 'summary_ar' => 'معنى القوائم وكيف تُغلق المعاملة', 'summary_en' => 'What the queues mean and how to clear them'],
            ],
        ],
        [
            'group_ar' => 'أنواع المعاملات',
            'group_en' => 'Correspondence types',
            'icon' => 'bi-folder2-open',
            'docs' => [
                ['id' => '30-outgoing', 'file' => '30-outgoing.md', 'title_ar' => 'الصادر', 'title_en' => 'Outgoing', 'summary_ar' => 'مفرد وجماعي باختصار', 'summary_en' => 'Single and bulk, briefly'],
                ['id' => '27-incoming', 'file' => '27-incoming.md', 'title_ar' => 'الوارد', 'title_en' => 'Incoming', 'summary_ar' => 'تسجيل ومتابعة باختصار', 'summary_en' => 'Register and follow up, briefly'],
                ['id' => '28-internal', 'file' => '28-internal.md', 'title_ar' => 'المعاملات الداخلية', 'title_en' => 'Internal', 'summary_ar' => 'بين الأقسام باختصار', 'summary_en' => 'Between departments, briefly'],
                ['id' => '29-circulars', 'file' => '29-circulars.md', 'title_ar' => 'التعاميم', 'title_en' => 'Circulars', 'summary_ar' => 'تعميم داخلي باختصار', 'summary_en' => 'Internal circular, briefly'],
                ['id' => '32-referrals', 'file' => '32-referrals.md', 'title_ar' => 'الإحالة', 'title_en' => 'Referrals', 'summary_ar' => 'تحويل المعاملة بين الأقسام واستلامها', 'summary_en' => 'Transfer between departments and receipt'],
            ],
        ],
        [
            'group_ar' => 'الصادر والإرسال',
            'group_en' => 'Outgoing & sending',
            'icon' => 'bi-send',
            'docs' => [
                ['id' => '12-sending', 'file' => '12-sending.md', 'title_ar' => 'إرسال الخطاب', 'title_en' => 'Sending', 'summary_ar' => 'بريد وواتساب للمفرد والجماعي (الإلكتروني فقط)', 'summary_en' => 'Email and WhatsApp for single and bulk'],
                ['id' => '09-outgoing-receipt', 'file' => '09-outgoing-receipt.md', 'title_ar' => 'استلام الصادر', 'title_en' => 'Outgoing receipt', 'summary_ar' => 'التسليم والمستلم والتاريخ والسند', 'summary_en' => 'Delivery, recipient, date, and proof'],
                ['id' => '26-bulk-mixed-delivery', 'file' => '26-bulk-mixed-delivery.md', 'title_ar' => 'صادر جماعي مختلط', 'title_en' => 'Bulk mixed delivery', 'summary_ar' => 'طباعة للبعض وإرسال إلكتروني للمحددين', 'summary_en' => 'Print for some, electronic send for selected'],
                ['id' => '13-rsvp', 'file' => '13-rsvp.md', 'title_ar' => 'تأكيد الحضور', 'title_en' => 'RSVP', 'summary_ar' => 'الحضور أو الاعتذار من الرابط أو النظام', 'summary_en' => 'Attend or decline via link or system'],
            ],
        ],
        [
            'group_ar' => 'الجهات والاعتماد',
            'group_en' => 'Entities & approval',
            'icon' => 'bi-people',
            'docs' => [
                ['id' => '10-entities', 'file' => '10-entities.md', 'title_ar' => 'جهات المراسلة', 'title_en' => 'Entities', 'summary_ar' => 'اللقب والتكريم والجوال والبريد', 'summary_en' => 'Title, honorific, mobile, and email'],
                ['id' => '24-approval-workflow', 'file' => '24-approval-workflow.md', 'title_ar' => 'دورة المراجعة والاعتماد', 'title_en' => 'Approval workflow', 'summary_ar' => 'المراحل وما يُطلب منك كمعتمد أو مراجع', 'summary_en' => 'Stages and what approvers/reviewers do'],
                ['id' => '25-confidential-correspondence', 'file' => '25-confidential-correspondence.md', 'title_ar' => 'المعاملات السرية', 'title_en' => 'Confidential items', 'summary_ar' => 'من يراها ومن يفتح الملفات', 'summary_en' => 'Who sees it and who opens files'],
                ['id' => '21-signing-office', 'file' => '21-signing-office.md', 'title_ar' => 'مكتب التوقيع', 'title_en' => 'Signing office', 'summary_ar' => 'معنى المكتب في الصادر', 'summary_en' => 'What the signing office means'],
            ],
        ],
    ];
}

/** @return list<array{group_ar:string,group_en:string,icon:string,docs:list<array<string,string>>}> */
function dms_help_docs_catalog(): array
{
    // مرجع مدير النظام — يُستبعد ما يخص المطوّر فقط (يبقى في مجلد doc/)
    return [
        [
            'group_ar' => 'البدء والأساسيات',
            'group_en' => 'Getting started',
            'icon' => 'bi-rocket-takeoff',
            'docs' => [
                ['id' => '00-readme', 'file' => '00-readme.md', 'title_ar' => 'بدء سريع', 'title_en' => 'Quick start', 'summary_ar' => 'المتطلبات والتثبيت والوحدات والأدوار', 'summary_en' => 'Requirements, install, modules, roles'],
                ['id' => '02-installation', 'file' => '02-installation.md', 'title_ar' => 'التثبيت والنشر', 'title_en' => 'Installation', 'summary_ar' => 'التثبيت على السيرفر والتشغيل', 'summary_en' => 'Server install and go-live'],
                ['id' => '03-settings', 'file' => '03-settings.md', 'title_ar' => 'الإعدادات', 'title_en' => 'Settings', 'summary_ar' => 'الجهة والعرض والعنوان', 'summary_en' => 'Org, display, address'],
            ],
        ],
        [
            'group_ar' => 'مسار المعاملة والسرية',
            'group_en' => 'Workflow & confidentiality',
            'icon' => 'bi-shield-lock',
            'docs' => [
                ['id' => '24-approval-workflow', 'file' => '24-approval-workflow.md', 'title_ar' => 'دورة المراجعة والاعتماد', 'title_en' => 'Approval workflow', 'summary_ar' => 'المراحل، التصعيد، قفل التعديل، دور المدير', 'summary_en' => 'Stages, escalation, edit lock, admin'],
                ['id' => '25-confidential-correspondence', 'file' => '25-confidential-correspondence.md', 'title_ar' => 'المعاملات السرية', 'title_en' => 'Confidential items', 'summary_ar' => 'من يراها ومن يفتح الملفات', 'summary_en' => 'Who sees it and who opens files'],
                ['id' => '22-actions-queue', 'file' => '22-actions-queue.md', 'title_ar' => 'يحتاج إجراء', 'title_en' => 'Actions queue', 'summary_ar' => 'معنى القوائم وكيف تُغلق المعاملة', 'summary_en' => 'Queue meaning and closing items'],
                ['id' => '21-signing-office', 'file' => '21-signing-office.md', 'title_ar' => 'مكتب التوقيع', 'title_en' => 'Signing office', 'summary_ar' => 'معنى المكتب وكيفية ضبطه في الأقسام', 'summary_en' => 'What it means and how to configure'],
            ],
        ],
        [
            'group_ar' => 'التشغيل اليومي',
            'group_en' => 'Daily operations',
            'icon' => 'bi-briefcase',
            'docs' => [
                ['id' => '19-user-guide', 'file' => '19-user-guide.md', 'title_ar' => 'دليل المستخدم', 'title_en' => 'User guide', 'summary_ar' => 'دليل شامل للوارد والصادر والداخلي', 'summary_en' => 'Full end-user guide'],
                ['id' => '30-outgoing', 'file' => '30-outgoing.md', 'title_ar' => 'الصادر', 'title_en' => 'Outgoing', 'summary_ar' => 'صادر مفرد وجماعي من الإنشاء حتى الإصدار', 'summary_en' => 'Single and bulk from create to issue'],
                ['id' => '27-incoming', 'file' => '27-incoming.md', 'title_ar' => 'الوارد', 'title_en' => 'Incoming', 'summary_ar' => 'تسجيل وارد ومتابعته والإحالة والرد', 'summary_en' => 'Register, follow up, refer, and reply'],
                ['id' => '28-internal', 'file' => '28-internal.md', 'title_ar' => 'المعاملات الداخلية', 'title_en' => 'Internal', 'summary_ar' => 'مراسلة بين الأقسام مع إحالة تلقائية', 'summary_en' => 'Between departments with auto referral'],
                ['id' => '29-circulars', 'file' => '29-circulars.md', 'title_ar' => 'التعاميم', 'title_en' => 'Circulars', 'summary_ar' => 'تعميم داخلي للجمهور أو لأقسام محددة', 'summary_en' => 'Internal circular to all or selected departments'],
                ['id' => '32-referrals', 'file' => '32-referrals.md', 'title_ar' => 'الإحالة', 'title_en' => 'Referrals', 'summary_ar' => 'تحويل المعاملة بين الأقسام واستلامها', 'summary_en' => 'Transfer between departments and receipt'],
                ['id' => '10-entities', 'file' => '10-entities.md', 'title_ar' => 'جهات المراسلة', 'title_en' => 'Entities', 'summary_ar' => 'الجوال والبريد والتكريم (يحفظه الله) وإدارة القائمة', 'summary_en' => 'Mobile, email, honorifics catalog'],
                ['id' => '11-email-whatsapp', 'file' => '11-email-whatsapp.md', 'title_ar' => 'البريد وواتساب', 'title_en' => 'Email & WhatsApp', 'summary_ar' => 'إعداد القنوات الخارجية', 'summary_en' => 'External channel setup'],
                ['id' => '12-sending', 'file' => '12-sending.md', 'title_ar' => 'إرسال الخطاب', 'title_en' => 'Sending', 'summary_ar' => 'إرسال إلكتروني للمفرد والجماعي (الورقي عبر التسليم)', 'summary_en' => 'Electronic send; paper via delivery'],
                ['id' => '26-bulk-mixed-delivery', 'file' => '26-bulk-mixed-delivery.md', 'title_ar' => 'صادر جماعي مختلط (ورقي + إلكتروني)', 'title_en' => 'Bulk mixed delivery (print + electronic)', 'summary_ar' => 'دعوة اجتماع بنص ثابت: طباعة رسمية للبعض وإرسال للمحددين', 'summary_en' => 'Fixed letter: official print for some, send to selected only'],
                ['id' => '13-rsvp', 'file' => '13-rsvp.md', 'title_ar' => 'تأكيد الحضور', 'title_en' => 'RSVP', 'summary_ar' => 'الحضور أو الاعتذار', 'summary_en' => 'Attend or decline'],
                ['id' => '09-outgoing-receipt', 'file' => '09-outgoing-receipt.md', 'title_ar' => 'استلام الصادر', 'title_en' => 'Outgoing receipt', 'summary_ar' => 'سند التسليم والمستلم والتاريخ', 'summary_en' => 'Delivery slip, recipient, date'],
            ],
        ],
        [
            'group_ar' => 'الإدارة والأمان',
            'group_en' => 'Admin & security',
            'icon' => 'bi-people',
            'docs' => [
                ['id' => '14-roles-and-permissions-guide', 'file' => '14-roles-and-permissions-guide.md', 'title_ar' => 'الأدوار والصلاحيات', 'title_en' => 'Roles & permissions', 'summary_ar' => 'دليل الأدوار وما يملكه كل دور', 'summary_en' => 'Role guide and capabilities'],
                ['id' => '16-users-and-login', 'file' => '16-users-and-login.md', 'title_ar' => 'المستخدمون وتسجيل الدخول', 'title_en' => 'Users & login', 'summary_ar' => 'البريد للدخول والأسماء', 'summary_en' => 'Email login and names'],
                ['id' => '15-backup', 'file' => '15-backup.md', 'title_ar' => 'النسخ الاحتياطي', 'title_en' => 'Backup', 'summary_ar' => 'SQL/ZIP وCron وOneDrive', 'summary_en' => 'SQL/ZIP, Cron, OneDrive'],
                ['id' => '20-upload-storage', 'file' => '20-upload-storage.md', 'title_ar' => 'تخزين الملفات', 'title_en' => 'File storage', 'summary_ar' => 'أماكن حفظ المرفقات والصور', 'summary_en' => 'Where uploads and photos live'],
                ['id' => '08-security', 'file' => '08-security.md', 'title_ar' => 'الأمان', 'title_en' => 'Security', 'summary_ar' => 'الجلسات والسجلات والحماية', 'summary_en' => 'Sessions, logs, hardening'],
            ],
        ],
    ];
}

function dms_help_docs_root(): string
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'doc';
}

/**
 * @param 'admin'|'user' $audience
 * @return array<string,array<string,mixed>>
 */
function dms_help_docs_by_id(string $audience = 'admin'): array
{
    $catalog = $audience === 'user' ? dms_user_help_docs_catalog() : dms_help_docs_catalog();
    $map = [];
    foreach ($catalog as $group) {
        foreach ($group['docs'] as $doc) {
            $map[(string) $doc['id']] = $doc + [
                'group_ar' => $group['group_ar'],
                'group_en' => $group['group_en'],
                'group_icon' => $group['icon'],
                'audience' => $audience,
            ];
        }
    }

    return $map;
}

/** @param 'admin'|'user' $audience */
function dms_help_doc_resolve(string $id, string $audience = 'admin'): ?array
{
    $id = trim($id);
    if ($id === '' || !preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._-]{0,80}$/', $id)) {
        return null;
    }
    $meta = dms_help_docs_by_id($audience)[$id] ?? null;
    if (!$meta) {
        return null;
    }
    $file = (string) ($meta['file'] ?? '');
    if ($file === '' || str_contains($file, '..') || str_contains($file, '/') || str_contains($file, '\\')) {
        return null;
    }
    if (!str_ends_with(strtolower($file), '.md')) {
        return null;
    }
    $path = dms_help_docs_root() . DIRECTORY_SEPARATOR . $file;
    $realRoot = realpath(dms_help_docs_root());
    $realFile = realpath($path);
    if ($realRoot === false || $realFile === false || !str_starts_with($realFile, $realRoot) || !is_file($realFile)) {
        return null;
    }
    $meta['path'] = $realFile;

    return $meta;
}

function dms_help_doc_title(array $meta): string
{
    $ar = (string) ($meta['title_ar'] ?? '');
    $en = (string) ($meta['title_en'] ?? '');
    if (function_exists('dms_is_rtl') && !dms_is_rtl()) {
        return $en !== '' ? $en : $ar;
    }

    return $ar !== '' ? $ar : $en;
}

function dms_help_doc_summary(array $meta): string
{
    $ar = (string) ($meta['summary_ar'] ?? '');
    $en = (string) ($meta['summary_en'] ?? '');
    if (function_exists('dms_is_rtl') && !dms_is_rtl()) {
        return $en !== '' ? $en : $ar;
    }

    return $ar !== '' ? $ar : $en;
}

function dms_help_doc_group_title(array $group): string
{
    $ar = (string) ($group['group_ar'] ?? '');
    $en = (string) ($group['group_en'] ?? '');
    if (function_exists('dms_is_rtl') && !dms_is_rtl()) {
        return $en !== '' ? $en : $ar;
    }

    return $ar !== '' ? $ar : $en;
}

function dms_help_page_base(string $audience = 'admin'): string
{
    return $audience === 'user' ? 'help/index.php' : 'admin/help.php';
}

/**
 * محوّل Markdown مبسّط وآمن للوثائق الداخلية.
 * @param 'admin'|'user' $audience
 */
function dms_help_markdown_to_html(string $markdown, string $docId = '', string $audience = 'admin'): string
{
    $GLOBALS['_dms_help_page_base'] = dms_help_page_base($audience);
    $markdown = str_replace(["\r\n", "\r"], "\n", $markdown);
    $markdown = preg_replace("/^---\n.*?\n---\n/s", '', $markdown) ?? $markdown;

    $lines = explode("\n", $markdown);
    $html = [];
    $inCode = false;
    $codeBuf = [];
    $inUl = false;
    $inOl = false;
    $inTable = false;
    $tableBuf = [];
    $para = [];

    $flushPara = static function () use (&$html, &$para): void {
        if ($para === []) {
            return;
        }
        $text = trim(implode("\n", $para));
        $para = [];
        if ($text === '') {
            return;
        }
        $html[] = '<p>' . dms_help_inline_md($text) . '</p>';
    };
    $closeLists = static function () use (&$html, &$inUl, &$inOl): void {
        if ($inUl) {
            $html[] = '</ul>';
            $inUl = false;
        }
        if ($inOl) {
            $html[] = '</ol>';
            $inOl = false;
        }
    };
    $flushTable = static function () use (&$html, &$inTable, &$tableBuf): void {
        if (!$inTable || $tableBuf === []) {
            $tableBuf = [];
            $inTable = false;
            return;
        }
        $html[] = '<div class="table-responsive"><table class="table table-sm table-bordered help-doc-table">';
        foreach ($tableBuf as $i => $row) {
            $cells = array_map('trim', explode('|', trim($row, "| \t")));
            if ($i === 1 && preg_match('/^:?-+:?$/', str_replace([' ', '|'], '', $row))) {
                continue;
            }
            $tag = $i === 0 ? 'th' : 'td';
            $html[] = '<tr>';
            foreach ($cells as $cell) {
                if ($cell === '' && count($cells) === 1) {
                    continue;
                }
                $html[] = '<' . $tag . '>' . dms_help_inline_md($cell) . '</' . $tag . '>';
            }
            $html[] = '</tr>';
        }
        $html[] = '</table></div>';
        $tableBuf = [];
        $inTable = false;
    };

    foreach ($lines as $line) {
        if (preg_match('/^```/', $line)) {
            $flushPara();
            $closeLists();
            $flushTable();
            if ($inCode) {
                $html[] = '<pre class="help-doc-code"><code>' . htmlspecialchars(implode("\n", $codeBuf), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</code></pre>';
                $codeBuf = [];
                $inCode = false;
            } else {
                $inCode = true;
            }
            continue;
        }
        if ($inCode) {
            $codeBuf[] = $line;
            continue;
        }

        if (preg_match('/^\|(.+)\|$/', trim($line)) || (str_contains($line, '|') && preg_match('/^\s*\|.*\|\s*$/', $line))) {
            $flushPara();
            $closeLists();
            $inTable = true;
            $tableBuf[] = trim($line);
            continue;
        }
        if ($inTable) {
            $flushTable();
        }

        if (trim($line) === '') {
            $flushPara();
            $closeLists();
            continue;
        }

        if (preg_match('/^(#{1,6})\s+(.+)$/', $line, $m)) {
            $flushPara();
            $closeLists();
            $level = strlen($m[1]);
            $html[] = '<h' . $level . '>' . dms_help_inline_md(trim($m[2])) . '</h' . $level . '>';
            continue;
        }

        if (preg_match('/^>\s?(.*)$/', $line, $m)) {
            $flushPara();
            $closeLists();
            $html[] = '<blockquote>' . dms_help_inline_md(trim($m[1])) . '</blockquote>';
            continue;
        }

        if (preg_match('/^[-*]\s+(.+)$/', $line, $m)) {
            $flushPara();
            if ($inOl) {
                $html[] = '</ol>';
                $inOl = false;
            }
            if (!$inUl) {
                $html[] = '<ul>';
                $inUl = true;
            }
            $html[] = '<li>' . dms_help_inline_md(trim($m[1])) . '</li>';
            continue;
        }

        if (preg_match('/^\d+\.\s+(.+)$/', $line, $m)) {
            $flushPara();
            if ($inUl) {
                $html[] = '</ul>';
                $inUl = false;
            }
            if (!$inOl) {
                $html[] = '<ol>';
                $inOl = true;
            }
            $html[] = '<li>' . dms_help_inline_md(trim($m[1])) . '</li>';
            continue;
        }

        if (preg_match('/^!\[([^\]]*)\]\(([^)]+)\)/', $line, $m)) {
            $flushPara();
            $closeLists();
            $alt = htmlspecialchars($m[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $src = dms_help_doc_asset_url($m[2], $docId);
            if ($src !== '') {
                $html[] = '<figure class="help-doc-figure"><img src="' . htmlspecialchars($src, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" alt="' . $alt . '" loading="lazy"></figure>';
            }
            continue;
        }

        $closeLists();
        $para[] = $line;
    }

    if ($inCode) {
        $html[] = '<pre class="help-doc-code"><code>' . htmlspecialchars(implode("\n", $codeBuf), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</code></pre>';
    }
    $flushPara();
    $closeLists();
    $flushTable();

    return implode("\n", $html);
}

function dms_help_inline_md(string $text): string
{
    $text = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text) ?? $text;
    $text = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $text) ?? $text;
    $text = preg_replace('/(?<!\*)\*([^*]+)\*(?!\*)/', '<em>$1</em>', $text) ?? $text;
    // رموز الاتجاه/القوائم — Greta لا ترسمها؛ نص عربي/ASCII يظهر دائماً (بدون خطوط رموز)
    $text = strtr($text, [
        '←' => ' ثم ',
        '→' => ' ثم ',
        '↓' => ' ثم ',
        '↑' => ' ثم ',
        '↘' => ' ثم ',
        '✓' => ' نعم ',
        '✗' => ' لا ',
        '·' => ' - ',
        '•' => ' - ',
        '▾' => ' (قائمة) ',
        '▸' => ' > ',
        '◀' => ' < ',
        '▶' => ' > ',
        '⚠' => ' تنبيه: ',
        '×' => 'x',
        '↔' => ' / ',
        '⇒' => ' ثم ',
        '⇐' => ' ثم ',
        '«' => '"',
        '»' => '"',
    ]);
    $text = preg_replace('/[←→↓↑↘✓✗·•▾▸◀▶⚠×↔⇒⇐⟵⟶«»‹›]/u', ' ', $text) ?? $text;
    $text = preg_replace('/\s{2,}/u', ' ', $text) ?? $text;
    $text = preg_replace_callback(
        '/\[([^\]]+)\]\(([^)]+)\)/',
        static function (array $m): string {
            $label = $m[1];
            $href = trim($m[2]);
            if (preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._-]*\.md(?:#[^)\s]*)?$/', $href)) {
                $parts = explode('#', $href, 2);
                $id = preg_replace('/\.md$/i', '', $parts[0]) ?? $parts[0];
                $base = (string) ($GLOBALS['_dms_help_page_base'] ?? 'help/index.php');
                $url = base_url($base . '?doc=' . rawurlencode($id));
                if (isset($parts[1]) && $parts[1] !== '') {
                    $url .= '#' . rawurlencode($parts[1]);
                }

                return '<a href="' . htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">' . $label . '</a>';
            }
            if (preg_match('#^(https?:)?//#i', $href) || str_starts_with($href, 'mailto:')) {
                return '<a href="' . htmlspecialchars($href, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" target="_blank" rel="noopener">' . $label . '</a>';
            }
            if (str_starts_with($href, 'admin/') || str_starts_with($href, 'incoming/') || str_starts_with($href, 'outgoing/') || str_starts_with($href, 'archive/')) {
                return '<a href="' . htmlspecialchars(base_url($href), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">' . $label . '</a>';
            }

            return $label;
        },
        $text
    ) ?? $text;

    return $text;
}

function dms_help_doc_asset_url(string $rel, string $docId = ''): string
{
    $rel = str_replace('\\', '/', trim($rel));
    if ($rel === '' || str_contains($rel, '..')) {
        return '';
    }
    if (preg_match('#^(https?:)?//#i', $rel)) {
        return $rel;
    }
    $rel = ltrim($rel, '/');
    if (!preg_match('#^images/#', $rel)) {
        return '';
    }
    $path = dms_help_docs_root() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    if (!is_file($path)) {
        return '';
    }

    return base_url('help/asset.php?f=' . rawurlencode($rel));
}
