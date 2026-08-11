<?php
/**
 * collection_grid block — all variables prepared by CollectionGridViewModel
 * (registered in BlockRenderer::VIEW_MODELS).
 *
 * @var string                     $sectionTitle
 * @var string                     $sectionSubtitle
 * @var string                     $viewAllLabel
 * @var string                     $emptyMessage
 * @var string                     $collectionKey
 * @var string                     $layoutVariant
 * @var string                     $imageAspectRatio
 * @var string                     $imageAspectRatioClass
 * @var string                     $cssClass
 * @var string                     $canonicalViewAllUrl
 * @var list<array<string, mixed>> $entries
 * @var string                     $sectionClass
 * @var string                     $containerClass
 * @var string                     $gridClass
 */

if ($collectionKey === '' || ($entries === [] && $sectionTitle === '')) {
    return;
}

$cardImageSizes = match ($layoutVariant) {
    'list' => '(max-width: 767px) 100vw, 20rem',
    'compact' => '(max-width: 639px) 100vw, (max-width: 1023px) 50vw, 25vw',
    'portfolio' => '(max-width: 639px) 100vw, (max-width: 1023px) 50vw, 33vw',
    default => '(max-width: 767px) 100vw, 33vw',
};
?>
<section class="<?= esc($sectionClass) ?> <?= esc((string) $cssClass) ?>">
    <div class="<?= esc($containerClass) ?>">
        <?php if ($sectionTitle || $sectionSubtitle || $viewAllLabel): ?>
            <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <?php if ($sectionTitle): ?>
                        <h2 class="section-title text-2xl sm:text-3xl"><?= esc($sectionTitle) ?></h2>
                    <?php endif; ?>
                    <?php if ($sectionSubtitle): ?>
                        <p class="section-copy mt-2"><?= esc($sectionSubtitle) ?></p>
                    <?php endif; ?>
                </div>
                <?php if ($viewAllLabel && $canonicalViewAllUrl !== ''): ?>
                    <a href="<?= esc(lang_url($canonicalViewAllUrl)) ?>"
                       class="text-sm font-medium text-slate-600 transition-colors hover:text-primary">
                        <?= esc($viewAllLabel) ?> &rarr;
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($entries !== []): ?>
            <div class="<?= esc($gridClass) ?>">
                <?php foreach ($entries as $entry):
                    $entryTitle   = $entry['title'] ?? '';
                    $entryExcerpt = $entry['excerpt'] ?? '';
                    $entryDate    = $entry['display_date'] ?? $entry['published_at'] ?? $entry['created_at'] ?? '';
                    $entryImage   = is_array($entry['featured_image'] ?? null) ? ($entry['featured_image']['url'] ?? '') : '';
                    $entryNavigation = is_array($entry['navigation'] ?? null) ? $entry['navigation'] : [];
                    $entryUrl = (string) ($entryNavigation['url'] ?? '');
                    $articleClass = $layoutVariant === 'portfolio'
                        ? 'bg-white rounded-3xl border border-slate-200/60 shadow-sm overflow-hidden flex flex-col group hover:shadow-lg transition-all duration-300'
                        : 'surface-card overflow-hidden transition-colors hover:border-slate-300 group';
                    $imageClass = $imageAspectRatioClass;
                    if ($layoutVariant === 'list') {
                        $imageClass .= ' md:aspect-auto md:w-80 md:shrink-0';
                    }
                    $bodyClass = $layoutVariant === 'portfolio' ? 'p-6' : 'p-5';
                ?>
                    <article class="<?= esc($articleClass) ?>">
                        <?php if ($entryImage): ?>
                            <?php if ($entryUrl !== ''): ?>
                            <a href="<?= esc($entryUrl) ?>" class="block overflow-hidden <?= esc($imageClass) ?>" tabindex="-1">
                            <?php else: ?>
                            <div class="block overflow-hidden <?= esc($imageClass) ?>" aria-hidden="true">
                            <?php endif; ?>
                                <?= view('components/responsive-image', [
                                    'src'      => $entryImage,
                                    'alt'      => $entryTitle,
                                    'class'    => 'h-full w-full object-cover transition-transform duration-300 group-hover:scale-105',
                                    'variants' => $entry['featured_image']['variants'] ?? null,
                                    'preferredVariant' => 'sd',
                                    'sizes' => $cardImageSizes,
                                    'maxVariantWidth' => 640,
                                ], ['saveData' => false]) ?>
                            <?= $entryUrl !== '' ? '</a>' : '</div>' ?>
                        <?php endif; ?>
                        <div class="<?= esc($bodyClass) ?>">
                            <?php if ($entryDate): ?>
                                <p class="text-xs uppercase tracking-[0.2em] text-slate-500">
                                    <?= esc(format_localized_date($entryDate, $lang)) ?>
                                </p>
                            <?php endif; ?>
                            <h3 class="mt-2 text-lg font-semibold leading-tight text-slate-900">
                                <?php if ($entryUrl !== ''): ?>
                                <a href="<?= esc($entryUrl) ?>" class="transition-colors hover:text-primary">
                                <?php else: ?>
                                <span>
                                <?php endif; ?>
                                    <?= esc($entryTitle) ?>
                                <?= $entryUrl !== '' ? '</a>' : '</span>' ?>
                            </h3>
                            <?php if ($entryExcerpt): ?>
                                <p class="section-copy mt-2 text-sm <?= $layoutVariant === 'compact' ? 'line-clamp-1' : 'line-clamp-3' ?>">
                                    <?= esc($entryExcerpt) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php elseif ($emptyMessage): ?>
            <div class="surface-default border-dashed px-5 py-8 text-slate-500">
                <?= esc($emptyMessage) ?>
            </div>
        <?php endif; ?>
    </div>
</section>
