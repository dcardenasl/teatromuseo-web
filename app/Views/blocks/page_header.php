<?php
/** @var array<string, mixed> $config */
/** @var array<string, mixed> $data */
$heading         = $data['heading'] ?? '';
$subheading      = $data['subheading'] ?? '';
$breadcrumbLabel = $data['breadcrumb_label'] ?? '';
$navigation      = is_array($block['navigation'] ?? null) ? $block['navigation'] : [];
$breadcrumbUrl   = (string) ($navigation['url'] ?? '');
// Background color is now a simple config value (e.g., 'bg-gray-100' or 'bg-slate-50')
// Can be easily changed via the CMS config
$bgColor         = $config['bg_color'] ?? 'bg-gray-100';
$cssClass        = $config['css_class'] ?? '';

if ($heading === '') {
    return;
}
?>
<section class="<?= esc($bgColor) ?> py-10 sm:py-12 border-b border-slate-200 <?= esc($cssClass) ?>">
    <div class="container-base">
        <div class="max-w-4xl">
            <?php if ($breadcrumbLabel && $breadcrumbUrl !== ''): ?>
                <nav class="mb-4 flex items-center gap-2 text-sm text-slate-500" aria-label="Breadcrumb">
                    <a href="<?= esc($breadcrumbUrl) ?>" class="font-medium text-slate-600 transition-colors hover:text-primary">
                        <?= esc($breadcrumbLabel) ?>
                    </a>
                    <svg class="h-3 w-3 shrink-0 text-slate-300" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m9 5 7 7-7 7"/>
                    </svg>
                    <span class="text-slate-700" aria-current="page"><?= esc($heading) ?></span>
                </nav>
            <?php endif; ?>

            <h1 class="section-title text-4xl sm:text-5xl">
                <?= esc($heading) ?>
            </h1>
            <?php if ($subheading): ?>
                <p class="section-copy mt-4 max-w-2xl text-lg">
                    <?= esc($subheading) ?>
                </p>
            <?php endif; ?>
        </div>
    </div>
</section>
