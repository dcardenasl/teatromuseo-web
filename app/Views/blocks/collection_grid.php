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
<section class="<?= esc($sectionClass) ?> <?= esc((string) $cssClass) ?>"
         data-video-listing
         data-video-close-label="<?= esc(lang('Site.video_modal_close'), 'attr') ?>"
         data-video-player-label="<?= esc(lang('Site.video_player_title'), 'attr') ?>">
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
                    $listingContent = is_array($entry['listing_content'] ?? null) ? $entry['listing_content'] : [];
                    $video = is_array($listingContent['video'] ?? null) ? $listingContent['video'] : [];
                    $videoEmbedUrl = trim((string) ($video['embed_url'] ?? ''));
                    $videoPosterUrl = trim((string) ($video['poster_url'] ?? ''));
                    $isPlayableVideo = $videoEmbedUrl !== '';
                ?>
                    <article class="<?= esc($articleClass) ?>">
                        <?php if ($entryImage !== '' || $videoPosterUrl !== '' || $isPlayableVideo): ?>
                            <?php if ($isPlayableVideo): ?>
                            <button type="button"
                                    class="relative block w-full overflow-hidden <?= esc($imageClass) ?> text-left focus:outline-none focus-visible:ring-4 focus-visible:ring-primary/40"
                                    data-video-trigger
                                    data-video-embed-url="<?= esc($videoEmbedUrl, 'attr') ?>"
                                    data-video-title="<?= esc((string) $entryTitle, 'attr') ?>"
                                    aria-label="<?= esc(lang('Site.video_play_label', [$entryTitle]), 'attr') ?>">
                            <?php elseif ($entryUrl !== ''): ?>
                            <a href="<?= esc($entryUrl) ?>" class="block overflow-hidden <?= esc($imageClass) ?>" tabindex="-1" aria-hidden="true">
                            <?php else: ?>
                            <div class="block overflow-hidden <?= esc($imageClass) ?>" aria-hidden="true">
                            <?php endif; ?>
                                <?php if ($videoPosterUrl !== ''): ?>
                                    <img src="<?= esc($videoPosterUrl, 'attr') ?>"
                                         alt="<?= esc((string) $entryTitle, 'attr') ?>"
                                         class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                                         loading="lazy"
                                         decoding="async">
                                <?php elseif ($entryImage !== ''): ?>
                                    <?= view('components/responsive-image', [
                                        'src'      => $entryImage,
                                        'alt'      => $entryTitle,
                                        'class'    => 'h-full w-full object-cover transition-transform duration-300 group-hover:scale-105',
                                        'variants' => $entry['featured_image']['variants'] ?? null,
                                        'preferredVariant' => 'sd',
                                        'sizes' => $cardImageSizes,
                                        'maxVariantWidth' => 640,
                                    ], ['saveData' => false]) ?>
                                <?php else: ?>
                                    <span class="absolute inset-0 bg-slate-900" aria-hidden="true"></span>
                                <?php endif; ?>
                                <?php if ($isPlayableVideo): ?>
                                    <span class="absolute inset-0 bg-slate-950/20 transition-colors group-hover:bg-slate-950/35" aria-hidden="true"></span>
                                    <span class="absolute inset-0 flex items-center justify-center" aria-hidden="true">
                                        <span class="flex h-14 w-14 items-center justify-center rounded-full bg-white text-primary shadow-lg transition-transform duration-300 group-hover:scale-110">
                                            <svg class="ml-1 h-6 w-6" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5.14v13.72a1 1 0 0 0 1.5.86l10-6.86a1 1 0 0 0 0-1.72l-10-6.86A1 1 0 0 0 8 5.14Z"/></svg>
                                        </span>
                                    </span>
                                <?php endif; ?>
                            <?= $isPlayableVideo ? '</button>' : ($entryUrl !== '' ? '</a>' : '</div>') ?>
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
