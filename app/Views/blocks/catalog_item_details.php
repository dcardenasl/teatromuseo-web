<?php
/**
 * Bloque: Detalles Dinámicos de Obra (Ficha Técnica)
 * Usa CatalogItemDetailsViewModel
 */

if (!($hasItem ?? false)):
    $fallbackTitle = $fallbackTitle ?? lang('Site.catalog_details_preview_title');
?>
<div class="p-8 bg-amber-50 text-center border border-dashed border-amber-300">
    <h2 class="text-2xl font-bold text-amber-500"><?= esc($fallbackTitle) ?> (<?= esc(lang('Site.preview_label')) ?>)</h2>
    <p class="text-amber-600"><?= esc(lang('Site.catalog_details_preview_description')) ?></p>
</div>
<?php else: ?>
<section class="section pt-0">
    <div class="container-narrow">
        <h3 class="text-xl font-bold text-slate-900 mb-6 uppercase tracking-wider text-sm border-b border-slate-100 pb-3"><?= esc(lang('Site.catalog_technical_details_title')) ?></h3>
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-6">
            <?php if (!empty($techniques ?? [])): ?>
            <div class="sm:col-span-1">
                <dt class="text-sm font-medium text-text-muted"><?= esc(lang('Site.catalog_techniques_label')) ?></dt>
                <dd class="mt-1">
                    <ul class="flex flex-wrap gap-2">
                        <?php foreach ($techniques as $techniqueLabel): ?>
                            <li class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-sm text-amber-900">
                                <?= esc((string) $techniqueLabel) ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </dd>
            </div>
            <?php elseif (($technique ?? '') !== ''): ?>
            <div class="sm:col-span-1">
                <dt class="text-sm font-medium text-text-muted"><?= esc(lang('Site.catalog_techniques_label')) ?></dt>
                <dd class="mt-1 text-base text-text-primary"><?= esc($technique) ?></dd>
            </div>
            <?php endif; ?>

            <?php if (($period ?? '') !== ''): ?>
            <div class="sm:col-span-1">
                <dt class="text-sm font-medium text-text-muted"><?= esc(lang('Site.catalog_period_label')) ?></dt>
                <dd class="mt-1 text-base text-text-primary"><?= esc($period) ?></dd>
            </div>
            <?php endif; ?>

            <?php if (($dimensions ?? '') !== ''): ?>
            <div class="sm:col-span-1">
                <dt class="text-sm font-medium text-text-muted"><?= esc(lang('Site.catalog_dimensions_label')) ?></dt>
                <dd class="mt-1 text-base text-text-primary"><?= esc($dimensions) ?></dd>
            </div>
            <?php endif; ?>

            <?php if (($location ?? '') !== ''): ?>
            <div class="sm:col-span-1">
                <dt class="text-sm font-medium text-text-muted"><?= esc(lang('Site.catalog_location_label')) ?></dt>
                <dd class="mt-1 text-base text-text-primary"><?= esc($location) ?></dd>
            </div>
            <?php endif; ?>
        </dl>
        
        <?php if (($curiosity ?? '') !== ''): ?>
        <div class="mt-8 pt-6 border-t border-slate-100">
            <h4 class="text-sm font-bold text-amber-600 uppercase tracking-wider mb-2 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                </svg>
                <?= esc(lang('Site.catalog_curiosity_label')) ?>
            </h4>
            <p class="text-text-secondary italic"><?= esc($curiosity) ?></p>
        </div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>
