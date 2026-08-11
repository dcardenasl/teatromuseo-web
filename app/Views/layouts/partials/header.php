<header class="site-header sticky top-0 z-50 bg-white/95 backdrop-blur-md transition-all duration-200">
    <?php
    $siteLogoUrl = is_array($settings['site_logo'] ?? null)
        ? (string) ($settings['site_logo']['url'] ?? '')
        : '';
    if ($siteLogoUrl === '') {
        $siteLogoUrl = (string) ($settings['site_logo_url'] ?? '');
    }
    $publicLocale = (string) service('request')->getLocale();
    $localizedMenuUrl = static function (mixed $value) use ($publicLocale): string {
        $candidate = is_scalar($value) ? (string) $value : '#';
        $parsed = parse_url($candidate);
        if (is_array($parsed) && is_string($parsed['host'] ?? null)) {
            $currentHost = service('request')->getUri()->getHost();
            if (strcasecmp($parsed['host'], $currentHost) === 0) {
                $candidate = is_string($parsed['path'] ?? null) ? $parsed['path'] : '/';
            }
        }

        $trimmed = trim($candidate, '/');
        if ($trimmed === '' || strcasecmp($trimmed, $publicLocale) === 0) {
            return lang_url(\App\Support\PublicPaths::homepagePath($publicLocale), $publicLocale);
        }

        $normalized = \App\Support\PublicPaths::normalizeLocalizedPath($candidate, $publicLocale);

        return lang_url($normalized ?? $candidate, $publicLocale);
    };
    ?>
    <nav class="container-base flex items-center justify-between py-2.5 sm:py-4">
        <!-- Logo / Site Title -->
        <a href="<?= esc(lang_url(\App\Support\PublicPaths::homepagePath(service('request')->getLocale()))) ?>" class="flex items-center gap-3 text-slate-900 transition-colors hover:text-primary">
            <?php if ($siteLogoUrl !== ''): ?>
                <?= view('components/responsive-image', [
                    'src'      => $siteLogoUrl,
                    'alt'      => $settings['site_name'] ?? lang('Site.site_logo_alt'),
                    'class'    => 'h-8 w-auto sm:h-10',
                    'variants' => $settings['site_logo']['variants'] ?? null,
                    'preferredVariant' => 'thumb',
                    'sizes'    => '10rem',
                    'maxVariantWidth' => 200,
                ], ['saveData' => false]) ?>
                <span class="text-xl font-bold text-primary"><?= esc($settings['site_name'] ?? lang('Site.site_default_name')) ?></span>
            <?php else: ?>
                <span class="text-xl font-bold text-primary"><?= esc($settings['site_name'] ?? lang('Site.site_default_name')) ?></span>
            <?php endif; ?>
        </a>

        <!-- Desktop Navigation & Language Switcher Wrapper -->
        <div class="hidden xl:flex items-center gap-4">
            <!-- Desktop Navigation Links -->
            <ul class="flex gap-1.5 items-center">
                <?php foreach (($menu['items'] ?? []) as $item): ?>
                    <li class="relative group">
                        <a href="<?= esc($localizedMenuUrl($item['custom_url'] ?? '#')) ?>"
                           class="inline-flex items-center gap-1 px-4 py-2 text-sm font-medium text-slate-600 hover:text-primary hover:bg-slate-50/80 rounded-lg transition-all duration-200">
                            <?= esc($item['label'] ?? '') ?>
                            <?php if (!empty($item['children'])): ?>
                                <svg class="w-3.5 h-3.5 opacity-60 group-hover:rotate-180 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            <?php endif; ?>
                        </a>

                        <!-- Dropdown Menu -->
                        <?php if (!empty($item['children'])): ?>
                            <div class="absolute left-0 mt-1.5 w-52 bg-white/95 backdrop-blur-md border border-slate-100 rounded-xl shadow-xl shadow-slate-100/50 opacity-0 invisible group-hover:opacity-100 group-hover:visible translate-y-1 group-hover:translate-y-0 transition-all duration-300 py-1.5 z-50">
                                <?php foreach ($item['children'] as $subitem): ?>
                                    <a href="<?= esc($localizedMenuUrl($subitem['custom_url'] ?? '#')) ?>"
                                       class="block px-4 py-2 text-sm font-medium text-slate-600 hover:text-primary hover:bg-slate-50 transition-colors">
                                        <?= esc($subitem['label'] ?? '') ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>

            <!-- Desktop Language Dropdown (Supports multiple languages) -->
            <div class="relative group border-l border-slate-200 pl-4 h-6 flex items-center">
                <button class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-600 hover:text-primary transition-colors focus:outline-none uppercase">
                    <svg class="w-4 h-4 opacity-75" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path>
                    </svg>
                    <span><?= esc(service('request')->getLocale()) ?></span>
                    <svg class="w-3 h-3 opacity-60 group-hover:rotate-180 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </button>
                
                <div class="absolute right-0 top-full mt-1.5 w-32 bg-white/95 backdrop-blur-md border border-slate-100 rounded-xl shadow-xl shadow-slate-100/50 opacity-0 invisible group-hover:opacity-100 group-hover:visible translate-y-1 group-hover:translate-y-0 transition-all duration-300 py-1.5 z-50">
                    <?php foreach (config('App')->supportedLocales as $locale): ?>
                        <a href="<?= esc(current_lang_url($locale, $localized_urls ?? null)) ?>"
                           class="block px-4 py-2 text-sm font-semibold uppercase text-center transition-colors <?= $locale === service('request')->getLocale() ? 'text-primary bg-slate-50' : 'text-slate-600 hover:text-primary hover:bg-slate-50' ?>">
                            <?= esc($locale) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Mobile Menu Toggle Button (Visible on Mobile Only) -->
        <button
            id="mobile-menu-toggle"
            data-mobile-menu-toggle
            class="xl:hidden rounded-lg p-2 text-slate-600 transition-all hover:bg-slate-50 hover:text-primary focus:outline-none"
            aria-label="<?= esc(lang('Site.menu_toggle')) ?>"
            aria-expanded="false"
        >
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path id="menu-icon-path" data-mobile-menu-icon stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
            </svg>
        </button>
    </nav>

    <!-- Mobile Navigation Drawer (Hidden on Desktop) -->
    <div id="mobile-drawer" data-mobile-drawer class="site-drawer fixed top-[48px] z-40 flex h-[calc(100dvh-48px)] w-full flex-col overflow-hidden bg-white opacity-0 pointer-events-none translate-y-4 transition duration-200 ease-in-out xl:hidden">
        <div class="site-drawer-scroll flex-1 min-h-0 space-y-6 overflow-y-auto overscroll-contain px-6 py-6 touch-pan-y">
            <ul class="space-y-4">
                <?php foreach (($menu['items'] ?? []) as $item): ?>
                    <li class="border-b border-slate-100/50 pb-3 last:border-0 last:pb-0">
                        <?php if (!empty($item['children'])): ?>
                            <!-- Clickable Row for Items with Children -->
                            <div class="mobile-submenu-row flex justify-between items-center cursor-pointer py-1" data-target="submenu-<?= $item['id'] ?>">
                                <span class="text-base font-semibold text-slate-800 hover:text-primary transition-colors">
                                    <?= esc($item['label'] ?? '') ?>
                                </span>
                                <button class="text-slate-400 hover:text-primary focus:outline-none pointer-events-none" aria-hidden="true" tabindex="-1">
                                    <svg class="w-4.5 h-4.5 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </button>
                            </div>
                        <?php else: ?>
                            <!-- Standard Link for Leaf Items -->
                            <div class="flex justify-between items-center py-1">
                                <a href="<?= esc($localizedMenuUrl($item['custom_url'] ?? '#')) ?>" class="text-base font-semibold text-slate-800 hover:text-primary transition-colors w-full">
                                    <?= esc($item['label'] ?? '') ?>
                                </a>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($item['children'])): ?>
                            <ul id="submenu-<?= $item['id'] ?>" class="hidden mt-2 pl-4 border-l border-slate-100 space-y-3">
                                <?php foreach ($item['children'] as $subitem): ?>
                                    <li>
                                        <a href="<?= esc($localizedMenuUrl($subitem['custom_url'] ?? '#')) ?>" class="block text-sm font-medium text-slate-500 hover:text-primary transition-colors">
                                            <?= esc($subitem['label'] ?? '') ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>

            <!-- Mobile Language Selector -->
            <div class="mt-8 pt-6 border-t border-slate-100">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-400 block mb-3">Idioma / Language</span>
                <div class="grid grid-cols-3 gap-2">
                    <?php foreach (config('App')->supportedLocales as $locale): ?>
                        <a href="<?= esc(current_lang_url($locale, $localized_urls ?? null)) ?>"
                           class="text-center py-2.5 rounded-lg text-sm font-semibold border uppercase transition-colors <?= $locale === service('request')->getLocale() ? 'bg-slate-900 text-white border-slate-900' : 'bg-slate-50 text-slate-600 border-slate-100 hover:bg-slate-100/50' ?>">
                            <?= esc($locale) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- Mobile Drawer Footer info -->
        <div class="flex-shrink-0 bg-slate-50 p-6 border-t border-slate-100 text-center">
            <p class="text-xs text-slate-400">
                <?= esc($settings['site_tagline'] ?? '') ?>
            </p>
        </div>
    </div>
</header>
