<?php
/**
 * Entry detail — article view.
 *
 * @var string $title
 * @var string $excerpt
 * @var string $published_at
 * @var array<string, mixed> $featured_image
 * @var array<string, mixed> $collection
 * @var array<int, array<string, mixed>> $categories
 * @var array<int, array<string, mixed>> $tags
 * @var string $collectionUrlPath
 * @var string $collectionName
 * @var string $renderedBlocks
 * @var bool $showEntryHeading
 * @var bool $showFeaturedImage
 */
$collectionLabel = collection_display_title($collection ?? []);
$collectionBreadcrumbLabel = $collectionLabel !== ''
    ? $collectionLabel
    : (trim((string) ($collectionName ?? '')) !== '' ? trim((string) ($collectionName ?? '')) : lang('Site.collection_index_label'));
$isPortfolio  = ($collection['collection_key'] ?? '') === 'portafolio';
$backLabel    = $collectionLabel !== ''
    ? lang('Site.back_to_collection', [$collectionLabel])
    : lang('Site.back_to_list');
$homeLabel    = lang('Site.breadcrumb_home');
$tagsLabel    = lang('Site.tags_label');
$publishedLabel = lang('Site.published_label');
$shareLabel   = lang('Site.share_label');
$copyLabel    = lang('Site.copy_link');
$copiedLabel  = lang('Site.copied_label');
$relatedLabel = lang('Site.related_stories');
$shareUrl     = $canonicalUrl ?? '';
$featuredImage = is_array($featured_image ?? null) ? $featured_image : [];
$featuredImageUrl = is_string($featuredImage['url'] ?? null) ? trim((string) $featuredImage['url']) : '';
?>

<!-- ── Breadcrumb ─────────────────────────────────────────────────── -->
<div class="bg-white border-b border-slate-100">
    <div class="container-narrow py-3">
        <nav class="flex items-center gap-2 text-sm text-text-muted" aria-label="Breadcrumb">
            <a href="<?= lang_url('/') ?>" class="hover:text-primary transition-colors">
                <?= esc($homeLabel) ?>
            </a>
            <span aria-hidden="true">/</span>
            <a href="<?= lang_url($collectionUrlPath ?? '/') ?>"
               class="hover:text-primary transition-colors">
                <?= esc($collectionBreadcrumbLabel) ?>
            </a>
            <span aria-hidden="true">/</span>
            <span class="text-text-primary line-clamp-1 max-w-xs" aria-current="page">
                <?= esc($title) ?>
            </span>
        </nav>
    </div>
</div>

<!-- ── Article ────────────────────────────────────────────────────── -->
<article class="section bg-background">
    <div class="container-narrow">

        <!-- Header -->
        <header class="mb-8">
            <?php if (!empty($categories)): ?>
                <div class="flex flex-wrap gap-1.5 mb-4">
                    <?php foreach ($categories as $cat): ?>
                        <span class="badge badge-secondary"><?= esc($cat['name'] ?? '') ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($showEntryHeading ?? true): ?>
                <h1 class="section-title text-3xl sm:text-4xl leading-tight mb-4">
                    <?= esc($title) ?>
                </h1>
            <?php endif; ?>

            <div class="flex items-center gap-4 text-sm text-text-muted">
                <?php if (!empty($published_at) && !$isPortfolio): ?>
                    <time datetime="<?= esc($published_at) ?>">
                        <span class="sr-only"><?= esc($publishedLabel) ?>: </span>
                        <?= esc(format_localized_date($published_at, $lang)) ?>
                    </time>
                <?php endif; ?>
            </div>
        </header>

        <!-- Featured image -->
        <?php if ($featuredImageUrl !== '' && ($showFeaturedImage ?? true)): ?>
            <figure class="mb-8 -mx-4 sm:mx-0 overflow-hidden sm:rounded-xl">
                <?= view('components/responsive-image', [
                    'src'           => $featuredImageUrl,
                    'alt'           => $title,
                    'class'         => 'w-full aspect-video object-cover',
                    'variants'      => $featuredImage['variants'] ?? null,
                    'loading'       => 'eager',
                    'fetchPriority' => 'high',
                ], ['saveData' => false]) ?>
            </figure>
        <?php endif; ?>

        <!-- Content blocks -->
        <div class="prose prose-slate max-w-none mb-10">
            <?= $renderedBlocks ?? '' ?>
        </div>

        <!-- Tags -->
        <?php if (!empty($tags)): ?>
            <div class="divider mb-6"></div>
            <div class="flex flex-wrap items-center gap-2 mb-8">
                <span class="text-sm font-medium text-text-secondary"><?= esc($tagsLabel) ?>:</span>
                <?php foreach ($tags as $tag): ?>
                    <span class="badge"><?= esc($tag['name'] ?? '') ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Share -->
        <div class="divider mb-6"></div>
        <div data-share-buttons
             data-share-url="<?= esc($shareUrl) ?>"
             data-share-title="<?= esc($title) ?>"
             data-copy-label="<?= esc($copyLabel) ?>"
             data-copied-label="<?= esc($copiedLabel) ?>"
             class="flex flex-wrap items-center gap-2 mb-8">
            <span class="text-sm font-medium text-text-secondary mr-1"><?= esc($shareLabel) ?>:</span>
            <a href="https://wa.me/?text=<?= rawurlencode($title . ' ' . $shareUrl) ?>"
               target="_blank" rel="noopener noreferrer"
               class="btn btn-outline btn-sm">WhatsApp</a>
            <a href="https://twitter.com/intent/tweet?text=<?= rawurlencode($title) ?>&url=<?= rawurlencode($shareUrl) ?>"
               target="_blank" rel="noopener noreferrer"
               class="btn btn-outline btn-sm">X</a>
            <a href="https://www.facebook.com/sharer/sharer.php?u=<?= rawurlencode($shareUrl) ?>"
               target="_blank" rel="noopener noreferrer"
               class="btn btn-outline btn-sm">Facebook</a>
            <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= rawurlencode($shareUrl) ?>"
               target="_blank" rel="noopener noreferrer"
               class="btn btn-outline btn-sm">LinkedIn</a>
            <button type="button" data-share-copy class="btn btn-outline btn-sm">
                <span data-share-copy-label><?= esc($copyLabel) ?></span>
            </button>
        </div>

        <!-- Back link -->
        <div class="divider mb-8"></div>
        <a href="<?= lang_url($collectionUrlPath ?? '/') ?>"
           class="link inline-flex items-center gap-1 font-medium">
            &larr; <?= esc($backLabel) ?>
        </a>

    </div>
</article>

<!-- ── Related entries ────────────────────────────────────────────── -->
<?php if (!empty($relatedEntries)): ?>
    <section class="section bg-white border-t border-slate-100">
        <div class="container-narrow">
            <h2 class="section-title text-2xl mb-6"><?= esc($relatedLabel) ?></h2>
            <div class="grid gap-6 md:grid-cols-3">
                <?php foreach ($relatedEntries as $relatedEntry): ?>
                    <?= view('collection/partials/entry_card', [
                        'entry'              => $relatedEntry,
                        'collectionUrlPath'  => $collectionUrlPath,
                        'lang'               => $lang,
                    ], ['saveData' => false]) ?>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>
