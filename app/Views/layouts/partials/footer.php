<footer class="custom-footer border-t py-16 mt-20">
    <?php
    $siteFooterLogoUrl = is_array($settings['site_logo'] ?? null)
        ? (string) ($settings['site_logo']['url'] ?? '')
        : (string) ($settings['site_logo_url'] ?? '');
    ?>
    <?php
    $footerLayout = in_array(($settings['footer_menu_layout'] ?? null), ['horizontal', 'vertical'], true)
        ? (string) $settings['footer_menu_layout']
        : 'vertical';
    $legalLayout = in_array(($settings['footer_legal_menu_layout'] ?? null), ['horizontal', 'vertical'], true)
        ? (string) $settings['footer_legal_menu_layout']
        : 'horizontal';

    $isFooterVertical = ($footerLayout === 'vertical');
    $isLegalVertical = ($legalLayout === 'vertical');
    $footerMenuUrl = static function (mixed $item): ?string {
        return is_array($item) ? public_menu_item_url($item) : null;
    };

    // Menu items with children render as their own labeled column (e.g. the
    // "Explora"/"Institución"/"Prensa y Medios" groups); items without
    // children fall back into a single flat "menu name" column, same as
    // before grouping existed.
    $footerMenuGroups = [];
    $footerMenuFlatItems = [];
    foreach (($menu['items'] ?? []) as $item) {
        if (!empty($item['children'])) {
            $footerMenuGroups[] = $item;
        } else {
            $footerMenuFlatItems[] = $item;
        }
    }
    $footerMenuBlockCount = count($footerMenuGroups) + ($footerMenuFlatItems !== [] ? 1 : 0);

    // A flat, ungrouped list of every leaf link — used by the horizontal
    // layout, which has no room to render nested dropdown-style groups.
    $flattenMenuItems = static function (array $items) use (&$flattenMenuItems): array {
        $out = [];
        foreach ($items as $item) {
            if (!empty($item['children'])) {
                $out = array_merge($out, $flattenMenuItems($item['children']));
            } else {
                $out[] = $item;
            }
        }

        return $out;
    };
    $footerMenuFlatAll = $flattenMenuItems($menu['items'] ?? []);

    $verticalCols = 2; // Site Info y Social Links siempre son columnas verticales
    if ($isFooterVertical) {
        $verticalCols += max($footerMenuBlockCount, 1);
    }
    if ($isLegalVertical) {
        $verticalCols++;
    }

    if ($verticalCols <= 2) {
        $gridColsClass = 'grid-cols-1 md:grid-cols-2';
    } elseif ($verticalCols === 3) {
        $gridColsClass = 'grid-cols-1 md:grid-cols-3';
    } else {
        $gridColsClass = 'grid-cols-1 sm:grid-cols-2 md:grid-cols-4';
    }
    ?>
    <div class="container-base">
        <div class="grid <?= $gridColsClass ?> gap-12 mb-12">
            <!-- Site Info -->
            <div class="space-y-3">
                <div class="flex items-center gap-2">
                    <?php if ($siteFooterLogoUrl !== ''): ?>
                        <?= view('components/responsive-image', [
                            'src'      => $siteFooterLogoUrl,
                            'alt'      => $settings['site_name'] ?? lang('Site.site_logo_alt'),
                            'class'    => 'h-10 w-auto',
                            'variants' => ($settings['site_logo']['variants'] ?? null),
                            'preferredVariant' => 'thumb',
                            'sizes'    => '10rem',
                            'maxVariantWidth' => 200,
                        ], ['saveData' => false]) ?>
                    <?php else: ?>
                        <span class="text-lg font-bold text-primary"><?= esc($settings['site_name'] ?? lang('Site.site_default_name')) ?></span>
                    <?php endif; ?>
                </div>
                <p class="section-copy text-sm max-w-sm">
                    <?= esc($settings['site_tagline'] ?? lang('Site.footer_default_tagline')) ?>
                </p>
            </div>

            <?php if ($isFooterVertical): ?>
                <!-- Navigation Menu Links (Vertical) — one column per group -->
                <?php foreach ($footerMenuGroups as $group): ?>
                    <div class="space-y-4">
                        <p class="section-eyebrow"><?= esc($group['label'] ?? '') ?></p>
                            <ul class="space-y-2.5">
                            <?php foreach ($group['children'] as $child): ?>
                                <?php $childUrl = $footerMenuUrl($child); ?>
                                <li>
                                    <?php if ($childUrl !== null): ?>
                                    <a href="<?= esc($childUrl) ?>" class="text-sm font-medium transition-colors duration-150">
                                    <?php else: ?>
                                    <span class="text-sm font-medium">
                                    <?php endif; ?>
                                        <?= esc($child['label'] ?? '') ?>
                                    <?= $childUrl !== null ? '</a>' : '</span>' ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>

                <?php if ($footerMenuFlatItems !== []): ?>
                    <div class="space-y-4">
                        <p class="section-eyebrow"><?= esc($menu['name'] ?? lang('Site.footer_menu_label')) ?></p>
                        <ul class="space-y-2.5">
                            <?php foreach ($footerMenuFlatItems as $item): ?>
                                <?php $itemUrl = $footerMenuUrl($item); ?>
                                <li>
                                    <?php if ($itemUrl !== null): ?>
                                    <a href="<?= esc($itemUrl) ?>" class="text-sm font-medium transition-colors duration-150">
                                    <?php else: ?>
                                    <span class="text-sm font-medium">
                                    <?php endif; ?>
                                        <?= esc($item['label'] ?? '') ?>
                                    <?= $itemUrl !== null ? '</a>' : '</span>' ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($isLegalVertical): ?>
                <!-- Legal Menu Links (Vertical Layout) -->
                <div class="space-y-4">
                        <p class="section-eyebrow"><?= esc($legalMenu['name'] ?? lang('Site.footer_legal_label')) ?></p>
                        <ul class="space-y-2.5">
                            <?php foreach (($legalMenu['items'] ?? []) as $item): ?>
                                <?php $itemUrl = $footerMenuUrl($item); ?>
                                <li>
                                <?php if ($itemUrl !== null): ?>
                                <a href="<?= esc($itemUrl) ?>" class="text-sm font-medium transition-colors duration-150">
                                <?php else: ?>
                                <span class="text-sm font-medium">
                                <?php endif; ?>
                                        <?= esc($item['label'] ?? '') ?>
                                <?= $itemUrl !== null ? '</a>' : '</span>' ?>
                                </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Social Links -->
            <?php $socialLinks = is_array($socialLinks ?? null) ? $socialLinks : []; ?>
            <?php if (!empty($socialLinks)): ?>
                <div class="space-y-4">
                    <p class="section-eyebrow"><?= lang('Site.footer_social_label') ?></p>
                    <div class="flex flex-col gap-2.5">
                        <?php foreach ($socialLinks as $link): ?>
                            <a href="<?= esc($link['url']) ?>" target="_blank" rel="noopener" class="text-sm font-medium transition-colors duration-150 flex items-center gap-2">
                                <?= esc($link['label']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Copyright & Secondary Menu -->
        <div class="border-t pt-8 mt-8 space-y-6">
            <?php if (!$isFooterVertical && !empty($footerMenuFlatAll)): ?>
                <!-- Horizontal Main Menu — grouped items are flattened to their leaf links -->
                <div class="flex flex-wrap justify-center items-center text-sm border-b pb-6 mb-4">
                    <?php foreach ($footerMenuFlatAll as $idx => $item): ?>
                        <?php if ($idx > 0): ?>
                            <span class="opacity-40 select-none hidden sm:inline mx-3" aria-hidden="true">•</span>
                        <?php endif; ?>
                        <?php $itemUrl = $footerMenuUrl($item); ?>
                        <?php if ($itemUrl !== null): ?>
                        <a href="<?= esc($itemUrl) ?>" class="inline-block mx-2 my-1 font-semibold transition-colors duration-150">
                        <?php else: ?>
                        <span class="inline-block mx-2 my-1 font-semibold">
                        <?php endif; ?>
                            <?= esc($item['label'] ?? '') ?>
                        <?= $itemUrl !== null ? '</a>' : '</span>' ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!$isLegalVertical && !empty($legalMenu['items'])): ?>
                <!-- Horizontal Layout for Legal Menu -->
                <div class="flex flex-col md:flex-row items-center justify-between gap-6">
                    <div class="text-xs opacity-75 order-2 md:order-1 text-center md:text-left">
                        <p><?= esc($settings['site_copyright'] ?? lang('Site.footer_default_copyright', [date('Y'), $settings['site_name'] ?? lang('Site.site_default_name')])) ?></p>
                    </div>
                    <div class="flex flex-wrap justify-center items-center text-xs order-1 md:order-2">
                        <?php foreach ($legalMenu['items'] as $idx => $item): ?>
                            <?php if ($idx > 0): ?>
                                <span class="opacity-40 select-none hidden sm:inline mx-2" aria-hidden="true">|</span>
                            <?php endif; ?>
                            <?php $itemUrl = $footerMenuUrl($item); ?>
                            <?php if ($itemUrl !== null): ?>
                            <a href="<?= esc($itemUrl) ?>" class="inline-block mx-2 my-1 font-medium transition-colors duration-150">
                            <?php else: ?>
                            <span class="inline-block mx-2 my-1 font-medium">
                            <?php endif; ?>
                                <?= esc($item['label'] ?? '') ?>
                            <?= $itemUrl !== null ? '</a>' : '</span>' ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="text-center text-xs opacity-75">
                    <p><?= esc($settings['site_copyright'] ?? lang('Site.footer_default_copyright', [date('Y'), $settings['site_name'] ?? lang('Site.site_default_name')])) ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</footer>
<?php
$siteJsPath = FCPATH . 'assets/js/site.js';
$siteJsVersion = is_file($siteJsPath)
    ? (string) (md5_file($siteJsPath) ?: filemtime($siteJsPath))
    : (string) time();
?>
<script src="<?= base_url('assets/js/alpine.min.js') ?>" defer></script>
<script src="<?= base_url('assets/js/site.js?v=' . $siteJsVersion) ?>" defer></script>
