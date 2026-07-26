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
                    $entryDate    = $entry['published_at'] ?? $entry['created_at'] ?? '';
                    $entrySlug    = $entry['slug'] ?? '';
                    $entryImage   = is_array($entry['featured_image'] ?? null) ? ($entry['featured_image']['url'] ?? '') : '';
                    $entryUrl     = $canonicalViewAllUrl !== '' && $entrySlug !== ''
                        ? lang_url(rtrim($canonicalViewAllUrl, '/') . '/' . $entrySlug)
                        : '#';
                    $articleClass = $layoutVariant === 'portfolio'
                        ? 'bg-white rounded-3xl border border-slate-200/60 shadow-sm overflow-hidden flex flex-col group hover:shadow-lg transition-all duration-300'
                        : 'surface-card overflow-hidden transition-colors hover:border-slate-300 group';
                    $imageClass = $layoutVariant === 'portfolio' ? 'aspect-[4/3]' : 'aspect-video';
                    $bodyClass = $layoutVariant === 'portfolio' ? 'p-6' : 'p-5';
                ?>
                    <article class="<?= esc($articleClass) ?>">
                        <?php if ($entryImage): ?>
                            <a href="<?= esc($entryUrl) ?>" class="block overflow-hidden <?= esc($imageClass) ?>" tabindex="-1">
                                <?= view('components/responsive-image', [
                                    'src'      => $entryImage,
                                    'alt'      => $entryTitle,
                                    'class'    => 'h-full w-full object-cover transition-transform duration-300 group-hover:scale-105',
                                    'variants' => $entry['featured_image']['variants'] ?? null,
                                ], ['saveData' => false]) ?>
                            </a>
                        <?php endif; ?>
                        <div class="<?= esc($bodyClass) ?>">
                            <?php if ($entryDate): ?>
                                <p class="text-xs uppercase tracking-[0.2em] text-slate-500">
                                    <?= esc(date('d M Y', strtotime($entryDate))) ?>
                                </p>
                            <?php endif; ?>
                            <h3 class="mt-2 text-lg font-semibold leading-tight text-slate-900">
                                <a href="<?= esc($entryUrl) ?>" class="transition-colors hover:text-primary">
                                    <?= esc($entryTitle) ?>
                                </a>
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
