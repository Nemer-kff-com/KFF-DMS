<?php

$current = basename($_SERVER['SCRIPT_NAME'] ?? '');

$dirName = basename(dirname($_SERVER['SCRIPT_NAME'] ?? ''));

$here = $dirName . '/' . $current;



/**

 * @param list<array{href:string,label:string,icon:string,active?:bool}> $items

 */

function render_nav_group(string $label, string $icon, array $items): void

{

    $open = false;

    foreach ($items as $item) {

        if (!empty($item['active'])) {

            $open = true;

            break;

        }

    }

    ?>

    <div class="nav-group<?= $open ? ' open' : '' ?>" data-nav-group>

        <button type="button"

                class="nav-link nav-group-toggle<?= $open ? ' active' : '' ?>"

                data-nav-toggle

                aria-expanded="<?= $open ? 'true' : 'false' ?>">

            <i class="bi <?= e($icon) ?>"></i>

            <span class="nav-group-label"><?= e($label) ?></span>

            <i class="bi bi-chevron-down nav-group-caret"></i>

        </button>

        <div class="nav-submenu"<?= $open ? '' : ' hidden' ?>>

            <?php foreach ($items as $item): ?>

                <a class="nav-link nav-sublink<?= !empty($item['active']) ? ' active' : '' ?>"

                   href="<?= e($item['href']) ?>">

                    <i class="bi <?= e($item['icon']) ?>"></i> <?= e($item['label']) ?>
                    <?php if (!empty($item['badge'])): ?><span class="badge rounded-pill text-bg-danger ms-auto"><?= (int) $item['badge'] > 99 ? '99+' : (int) $item['badge'] ?></span><?php endif; ?>

                </a>

            <?php endforeach; ?>

        </div>

    </div>

    <?php

}



$sidebarCtx = ['current' => $current, 'dirName' => $dirName, 'here' => $here];

$sidebarNav = dms_sidebar_menu_build(current_user(), $sidebarCtx);

?>

<aside class="sidebar" id="sidebar">

    <button type="button" class="sidebar-close" id="sidebarClose" aria-label="Close">

        <i class="bi bi-x-lg"></i>

    </button>

    <div class="sidebar-brand">

        <?php if (org_logo_url()): ?>

            <div class="brand-logo mb-2">

                <img src="<?= e(org_logo_url()) ?>" alt="<?= e(dms_org_display_name()) ?>">

            </div>

        <?php endif; ?>

        <?php render_org_brand_text('sidebar', 'org', 'org-en'); ?>

        <div class="sys"><?= e(__('app.name')) ?></div>

    </div>

    <nav class="nav flex-column py-2">

        <?php foreach ($sidebarNav as $entry): ?>

            <?php if (($entry['type'] ?? '') === 'group'): ?>

                <?php render_nav_group($entry['label'], $entry['icon'], $entry['items']); ?>

            <?php else: ?>

                <a class="nav-link <?= !empty($entry['active']) ? 'active' : '' ?>" href="<?= e($entry['href']) ?>">

                    <i class="bi <?= e($entry['icon']) ?>"></i> <?= e($entry['label']) ?>
                    <?php if (!empty($entry['badge'])): ?><span class="badge rounded-pill text-bg-danger ms-auto"><?= (int) $entry['badge'] > 99 ? '99+' : (int) $entry['badge'] ?></span><?php endif; ?>

                </a>

            <?php endif; ?>

        <?php endforeach; ?>

    </nav>

</aside>

