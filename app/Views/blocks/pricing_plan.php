<?php
/**
 * @var array $block
 * @var array $config
 * @var array $data
 */

$name = esc($data['name'] ?? '');
$price = esc($data['price'] ?? '');
$period = esc($data['period'] ?? '');
$description = esc($data['description'] ?? '');
$features = $data['features'] ?? ''; // rich text
$ctaLabel = esc($data['cta_label'] ?? lang('Site.pricing_plan_cta_default'));
$ctaUrl = esc($data['cta_url'] ?? '#');
$featured = (bool) ($config['featured'] ?? false);
?>

<div class="flex flex-col rounded-3xl border <?= $featured ? 'border-violet-500 ring-4 ring-violet-500/5 bg-white relative' : 'border-slate-200 bg-white' ?> p-8 shadow-sm hover:shadow-md transition-all duration-300">
    <?php if ($featured): ?>
        <span class="absolute top-0 right-8 -translate-y-1/2 rounded-full bg-violet-600 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-white shadow-sm">
            <?= esc(lang('Site.pricing_plan_featured')) ?>
        </span>
    <?php endif; ?>

    <div class="mb-6">
        <h3 class="text-xl font-bold text-slate-800 tracking-tight"><?= $name ?></h3>
        <?php if ($description !== ''): ?>
            <p class="mt-2 text-xs text-slate-500"><?= $description ?></p>
        <?php endif; ?>
        
        <div class="mt-4 flex items-baseline gap-1">
            <span class="text-4xl font-extrabold text-slate-900 tracking-tight"><?= $price ?></span>
            <?php if ($period !== ''): ?>
                <span class="text-sm font-semibold text-slate-500"><?= $period ?></span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Features -->
    <div class="flex-1 flex flex-col justify-between">
        <div class="prose prose-slate prose-sm max-w-none text-slate-600 mb-8 pricing-features">
            <?= $features ?>
        </div>

        <div>
            <a href="<?= $ctaUrl ?>" 
               class="btn w-full inline-flex items-center justify-center rounded-xl py-3 px-4 text-center text-sm font-semibold shadow-sm transition-all duration-300 <?= $featured ? 'bg-violet-600 hover:bg-violet-700 text-white shadow-violet-100 hover:shadow-violet-200' : 'bg-slate-50 hover:bg-slate-100 text-slate-700 border border-slate-200 hover:border-slate-300' ?>">
                <?= $ctaLabel ?>
            </a>
        </div>
    </div>
</div>

<style <?= csp_style_nonce() ?>>
.pricing-features ul {
    list-style-type: none !important;
    padding-left: 0 !important;
}
.pricing-features li {
    position: relative !important;
    padding-left: 1.75rem !important;
    margin-bottom: 0.5rem !important;
}
.pricing-features li::before {
    content: "" !important;
    position: absolute !important;
    left: 0.25rem !important;
    top: 0.35rem !important;
    width: 0.85rem !important;
    height: 0.5rem !important;
    border-left: 2px solid #8b5cf6 !important;
    border-bottom: 2px solid #8b5cf6 !important;
    transform: rotate(-45deg) !important;
}
</style>
