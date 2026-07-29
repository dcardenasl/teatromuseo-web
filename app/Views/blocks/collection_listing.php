<?php
/**
 * collection_listing block — all variables prepared by CollectionListingViewModel
 *
 * @var bool $isValid
 * @var array<string, mixed>|null $collection
 * @var string $collectionKey
 * @var string $collectionUrlPath
 * @var array<string, string> $localizedUrls
 * @var list<array<string, mixed>> $entries
 * @var array<string, mixed> $pagination
 * @var int $currentPage
 * @var string $currentCategory
 * @var string $currentTag
 * @var string $currentQuery
 * @var string $orderBy
 * @var string $orderDirection
 * @var string $layoutVariant
 * @var string $cssClass
 * @var string $sectionLabel
 * @var bool $showSearch
 * @var bool $showCategories
 * @var bool $showTags
 * @var bool $showExcerpt
 * @var bool $showDate
 * @var bool $showButton
 * @var bool $showItemCategories
 * @var bool $showExtraRichtext
 * @var bool $showExtraLink
 * @var bool $showExtraImage
 * @var string $emptyMessage
 * @var string $introTitle
 * @var string $introText
 * @var string $itemLabel
 * @var string $featuredItemLabel
 * @var string $countLabel
 * @var list<array<string, mixed>> $categories
 * @var list<array<string, mixed>> $tags
 * @var string $pageTitle
 * @var string $metaDescription
 */

if (! $isValid || $collection === null) {
    return;
}

$totalPages = (int) ($pagination['total_pages'] ?? 1);
$currentPage = max(1, (int) $currentPage);
$basePath = $collectionUrlPath !== '' ? $collectionUrlPath : '';

$buildUrl = static function (array $params) use ($basePath): string {
    $clean = array_filter(
        $params,
        static fn ($value) => $value !== null && $value !== '' && $value !== 0 && $value !== false
    );

    $query = http_build_query($clean);

    return $basePath !== ''
        ? lang_url($basePath) . ($query !== '' ? '?' . $query : '')
        : '#';
};

$filterLabel = lang('Site.collection_filter');
$resetLabel = lang('Site.collection_reset');
$allLabel = lang('Site.collection_all');
$categoriesLabel = lang('Site.collection_categories');
$tagsLabel = lang('Site.collection_tags');
$previousLabel = lang('Site.collection_previous');
$nextLabel = lang('Site.collection_next');
$noResultsLabel = lang('Site.collection_empty');
$headerTitle = $introTitle !== '' ? $introTitle : collection_display_title($collection ?? []);
$headerIntro = $introText !== '' ? $introText : collection_display_intro($collection ?? []);
$sectionLabel = trim((string) ($sectionLabel ?? ($collection['section_label'] ?? lang('Site.collection_index_label'))));
$itemLabel = trim((string) ($itemLabel ?? ($collection['item_label'] ?? lang('Site.collection_listing_item'))));
$featuredItemLabel = trim((string) ($featuredItemLabel ?? ($collection['featured_item_label'] ?? lang('Site.collection_listing_featured'))));
$countLabel = trim((string) ($countLabel ?? ($collection['count_label'] ?? lang('Site.collection_listing_count'))));
// COL-002: editable per-collection CTA label (Collections.field_entry_cta_label in the admin)
// takes priority; the collection_type fallback only covers the two shipped starter presets so
// custom collection types get a neutral label instead of an assumed "Ver proyecto".
$entryCtaLabelRaw = trim((string) ($collection['entry_cta_label'] ?? ''));
$entryCtaLabel = $entryCtaLabelRaw !== '' ? $entryCtaLabelRaw : match ($collection['collection_type'] ?? '') {
    'news' => lang('Site.view_article'),
    'portfolio' => lang('Site.view_project'),
    default => lang('Site.view_more'),
};

$gridClass = match ($layoutVariant) {
    'list' => 'space-y-6',
    'compact' => 'grid gap-4 sm:grid-cols-2 lg:grid-cols-4',
    'portfolio' => 'grid gap-8 sm:grid-cols-2 lg:grid-cols-3',
    default => 'grid gap-6 md:grid-cols-2 xl:grid-cols-3',
};

$cardClass = match ($layoutVariant) {
    'list' => 'surface-card overflow-hidden group flex flex-col md:flex-row transition-colors hover:border-slate-300',
    'portfolio' => 'bg-white rounded-3xl border border-slate-200/60 shadow-sm overflow-hidden flex flex-col group hover:shadow-lg transition-all duration-300',
    default => 'surface-card overflow-hidden transition-colors hover:border-slate-300 group flex flex-col',
};

$imageClass = match ($layoutVariant) {
    'portfolio' => 'aspect-[4/3]',
    'list' => 'aspect-video md:aspect-auto md:w-80 md:shrink-0',
    default => 'aspect-video',
};
$bodyClass = $layoutVariant === 'portfolio' ? 'p-7' : 'p-5';
$sectionClass = trim($cssClass . ' section');
?>
<section class="<?= esc($sectionClass) ?>" data-ajax-listing>
    <div class="container-base">
        
        <!-- ── 1. Block Header ────────────────────────────────────────────── -->
        <?php if ($headerTitle !== '' || $headerIntro !== ''): ?>
            <header class="max-w-4xl mb-8">
                <p class="text-xs font-bold uppercase tracking-[0.24em] text-primary mb-2">
                    <?= esc($sectionLabel !== '' ? $sectionLabel : lang('Site.collection_index_label')) ?>
                </p>
                <h2 class="text-3xl sm:text-4xl font-extrabold tracking-tight text-slate-900">
                    <?= esc($headerTitle !== '' ? $headerTitle : ($sectionLabel !== '' ? $sectionLabel : lang('Site.collection_index_label'))) ?>
                </h2>
                <?php if ($headerIntro !== ''): ?>
                    <div class="mt-4 text-slate-500 max-w-2xl leading-relaxed text-base">
                        <?= $headerIntro ?>
                    </div>
                <?php endif; ?>
            </header>
        <?php endif; ?>

        <!-- ── 2. Filters & Controls ──────────────────────────────────────── -->
        <?php if ($showSearch || $showCategories || $showTags): ?>
            <form method="get" action="<?= esc(lang_url($basePath)) ?>" class="mb-10 rounded-2xl border border-slate-200/70 bg-slate-50/80 p-5 shadow-sm">
                <div class="flex flex-col md:flex-row gap-3">
                    <?php if ($showSearch): ?>
                        <div class="relative flex-1">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </span>
                            <input type="search"
                                   name="q"
                                   value="<?= esc($currentQuery, 'attr') ?>"
                                   placeholder="<?= esc(lang('Site.search')) ?>..."
                                   class="w-full rounded-xl border border-slate-200 bg-white pl-10 pr-4 py-2.5 text-sm shadow-sm transition placeholder:text-slate-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/15">
                        </div>
                    <?php endif; ?>
                    
                    <div class="flex items-center gap-2">
                        <?php if ($showSearch): ?>
                            <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition-all hover:bg-primary-dark cursor-pointer">
                                <?= esc($filterLabel) ?>
                            </button>
                        <?php endif; ?>
                        <a href="<?= esc(lang_url($basePath)) ?>" class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-600 shadow-sm transition-all hover:border-slate-300 hover:bg-slate-100">
                            <?= esc($resetLabel) ?>
                        </a>
                    </div>
                </div>

                <input type="hidden" name="order_by" value="<?= esc($orderBy, 'attr') ?>">
                <input type="hidden" name="order_direction" value="<?= esc($orderDirection, 'attr') ?>">
                <input type="hidden" name="per_page" value="<?= esc((string) ((int) ($pagination['per_page'] ?? 12)), 'attr') ?>">

                <!-- Horizontal Category Filter Tab-like Pills -->
                <?php if ($showCategories && !empty($categories)): ?>
                    <div class="mt-5 pt-4 border-t border-slate-200/60">
                        <span class="block text-xs font-bold uppercase tracking-[0.15em] text-slate-500 mb-3"><?= esc($categoriesLabel) ?></span>
                        <div class="flex gap-2 overflow-x-auto pb-1.5 scrollbar-none -mx-1 px-1" data-listing-pills>
                            <a href="<?= esc($buildUrl(['q' => $currentQuery !== '' ? $currentQuery : null, 'tag' => $currentTag !== '' ? $currentTag : null, 'order_by' => $orderBy, 'order_direction' => $orderDirection, 'per_page' => (int) ($pagination['per_page'] ?? 12)])) ?>"
                               class="inline-flex items-center px-4 py-1.5 rounded-full text-xs font-bold tracking-wider uppercase transition-all duration-200 border <?= $currentCategory === '' ? '!bg-primary !border-primary !text-white hover:!text-white !no-underline shadow-sm' : 'bg-white border-slate-200 !text-slate-500 hover:!text-slate-700 hover:!bg-slate-100 hover:!border-slate-300 !no-underline' ?>">
                                <?= esc($allLabel) ?>
                            </a>
                            <?php foreach ($categories as $category): ?>
                                <?php $active = $currentCategory === (string) ($category['slug'] ?? ''); ?>
                                <a href="<?= esc((string) ($category['url'] ?? '#')) ?>"
                                   class="inline-flex items-center px-4 py-1.5 rounded-full text-xs font-bold tracking-wider uppercase transition-all duration-200 border <?= $active ? '!bg-primary !border-primary !text-white hover:!text-white !no-underline shadow-sm' : 'bg-white border-slate-200 !text-slate-500 hover:!text-slate-700 hover:!bg-slate-100 hover:!border-slate-300 !no-underline' ?>">
                                    <?= esc((string) ($category['name'] ?? $category['slug'] ?? '')) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Tag Cloud Filters -->
                <?php if ($showTags && !empty($tags)): ?>
                    <div class="mt-4 pt-3 border-t border-slate-200/40">
                        <span class="block text-xs font-bold uppercase tracking-[0.15em] text-slate-500 mb-2.5"><?= esc($tagsLabel) ?></span>
                        <div class="flex flex-wrap gap-2" data-listing-pills>
                            <?php foreach ($tags as $tag): ?>
                                <?php $active = $currentTag === (string) ($tag['slug'] ?? ''); ?>
                                <a href="<?= esc((string) ($tag['url'] ?? '#')) ?>"
                                   class="inline-flex items-center px-3 py-1 rounded-md text-xs font-medium transition-all duration-200 border <?= $active ? '!bg-slate-800 !border-slate-900 !text-white hover:!text-white !no-underline' : 'bg-white border-slate-200 !text-slate-500 hover:!text-slate-700 hover:!bg-slate-100 !no-underline' ?>">
                                    #<?= esc((string) ($tag['name'] ?? $tag['slug'] ?? '')) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </form>
        <?php endif; ?>

        <!-- ── 3. Entries Grid ────────────────────────────────────────────── -->
        <?php if ($entries !== []): ?>
            <div>
                <!-- List Metadata Bar -->
                <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-6">
                    <span class="text-xs font-bold uppercase tracking-[0.15em] text-slate-400" data-listing-count>
                        <?= esc(str_replace('{count}', (string) count($entries), $countLabel !== '' ? $countLabel : lang('Site.collection_listing_count'))) ?>
                    </span>
                </div>

                <!-- Grid -->
                <div class="<?= esc($gridClass) ?> <?= $layoutVariant !== 'list' ? 'gap-y-8' : '' ?>" data-listing-grid>
                <?php foreach ($entries as $index => $entry):
                    $entryTitle = (string) ($entry['title'] ?? '');
                    $entryExcerpt = (string) ($entry['excerpt'] ?? '');
                    $entryDate = (string) ($entry['published_at'] ?? $entry['created_at'] ?? '');
                    $entrySlug = (string) ($entry['slug'] ?? '');
                    if ($entrySlug === '' && is_array($entry['localized_slugs'] ?? null)) {
                        $entrySlug = (string) ($entry['localized_slugs'][$lang] ?? '');
                        if ($entrySlug === '') {
                            foreach ($entry['localized_slugs'] as $candidateSlug) {
                                $candidateSlug = trim((string) $candidateSlug);
                                if ($candidateSlug !== '') {
                                    $entrySlug = $candidateSlug;
                                    break;
                                }
                            }
                        }
                    }
                    $imageArr = is_array($entry['featured_image'] ?? null) ? $entry['featured_image'] : (is_array($entry['cover_image'] ?? null) ? $entry['cover_image'] : (is_array($entry['main_image'] ?? null) ? $entry['main_image'] : []));
                    $entryImage = is_string($imageArr['url'] ?? null) ? (string) $imageArr['url'] : '';
                    if ($entryImage === '') {
                        $entryImage = $fallbackImageUrl ?? '';
                    }
                    $listingContent = is_array($entry['listing_content'] ?? null) ? $entry['listing_content'] : [];
                    $extraImage = is_array($listingContent['image'] ?? null) ? $listingContent['image'] : null;
                    $extraAction = is_array($listingContent['secondary_action'] ?? null) ? $listingContent['secondary_action'] : null;
                    $extraRichtext = (string) ($listingContent['rich_text'] ?? '');
                    $entryUrl = $basePath !== '' && $entrySlug !== ''
                        ? lang_url(rtrim($basePath, '/') . '/' . ltrim($entrySlug, '/'))
                        : '#';
                ?>
                    <article class="<?= esc($cardClass) ?> animate-fade-in-up" style="animation-delay: <?= $index * 60 ?>ms; animation-fill-mode: both;">
                        <!-- Image Container with Zoom effect on hover -->
                        <?php if ($entryImage !== ''): ?>
                            <a href="<?= esc($entryUrl) ?>" class="block overflow-hidden <?= esc($imageClass) ?>" tabindex="-1" aria-hidden="true">
                                <?php if (str_starts_with($entryImage, 'http')): ?>
                                    <img src="<?= esc($entryImage) ?>" alt="<?= esc($entryTitle) ?>" class="h-full w-full object-cover transition-transform duration-500 ease-out group-hover:scale-105" loading="lazy">
                                <?php else: ?>
                                    <?= view('components/responsive-image', [
                                        'src'      => $entryImage,
                                        'alt'      => $entryTitle,
                                        'class'    => 'h-full w-full object-cover transition-transform duration-500 ease-out group-hover:scale-105',
                                        'variants' => $imageArr['variants'] ?? null,
                                    ], ['saveData' => false]) ?>
                                <?php endif; ?>
                            </a>
                        <?php else: ?>
                            <div class="relative overflow-hidden <?= esc($imageClass) ?> bg-gradient-to-br from-slate-100 via-slate-50 to-slate-200">
                                <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(255,255,255,0.95),transparent_55%)]"></div>
                                <div class="relative flex h-full w-full items-end p-5">
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400"><?= esc($itemLabel !== '' ? $itemLabel : lang('Site.collection_listing_item')) ?></p>
                                        <p class="mt-1 text-base font-semibold text-slate-700 line-clamp-2"><?= esc($entryTitle !== '' ? $entryTitle : ($featuredItemLabel !== '' ? $featuredItemLabel : lang('Site.collection_listing_featured'))) ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Body Content Container -->
                        <div class="<?= esc($bodyClass) ?> flex flex-col flex-1">
                            <!-- Categories badges -->
                            <?php if ($showItemCategories && !empty($entry['categories'])): ?>
                                <div class="flex flex-wrap gap-1.5 mb-3.5">
                                    <?php foreach (array_slice($entry['categories'], 0, 2) as $cat): ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold tracking-wider uppercase bg-primary/10 text-primary">
                                            <?= esc($cat['name'] ?? '') ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <!-- Entry Date -->
                            <?php if ($showDate && $entryDate !== ''): ?>
                                <time datetime="<?= esc($entryDate) ?>" class="text-[10px] uppercase tracking-[0.2em] font-semibold text-slate-500 block mb-2">
                                    <?= esc(date('d M Y', strtotime($entryDate))) ?>
                                </time>
                            <?php endif; ?>

                            <!-- Entry Title -->
                            <h3 class="text-lg font-bold leading-tight text-slate-900 group-hover:text-primary transition-colors duration-200">
                                <a href="<?= esc($entryUrl) ?>" class="!text-slate-900 group-hover:!text-primary !no-underline hover:!no-underline">
                                    <?= esc($entryTitle) ?>
                                </a>
                            </h3>

                            <!-- Excerpt -->
                            <?php if ($showExcerpt && $entryExcerpt !== ''): ?>
                                <p class="mt-3 text-sm text-slate-500 leading-relaxed line-clamp-3">
                                    <?= esc($entryExcerpt) ?>
                                </p>
                            <?php endif; ?>

                            <?php if ($showExtraRichtext && $extraRichtext !== ''): ?>
                                <div class="prose prose-sm mt-4 border-l-2 border-primary pl-3 text-slate-600 max-w-none">
                                    <?= $extraRichtext ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($showExtraImage && $extraImage !== null): ?>
                                <div class="mt-4 overflow-hidden rounded-lg">
                                    <?= view('components/responsive-image', [
                                        'src'      => (string) $extraImage['url'],
                                        'alt'      => (string) ($extraImage['alt'] ?: $entryTitle),
                                        'class'    => 'h-32 w-full object-cover',
                                        'variants' => $extraImage['variants'] ?? null,
                                    ], ['saveData' => false]) ?>
                                </div>
                            <?php endif; ?>

                            <!-- Call to Action Link -->
                            <?php if ($showButton || ($showExtraLink && $extraAction !== null)): ?>
                                <div class="mt-auto pt-5 border-t border-slate-100 flex flex-wrap items-center gap-x-5 gap-y-3">
                                    <?php if ($showButton): ?>
                                        <a href="<?= esc($entryUrl) ?>" class="inline-flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider !text-primary group-hover:!text-primary-dark !no-underline">
                                            <?= esc($entryCtaLabel) ?>
                                            <svg class="w-3.5 h-3.5 transform group-hover:translate-x-1 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
                                            </svg>
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($showExtraLink && $extraAction !== null): ?>
                                        <a href="<?= esc((string) $extraAction['url']) ?>" class="inline-flex items-center text-xs font-semibold !text-slate-600 hover:!text-slate-900 !no-underline">
                                            <?= esc((string) $extraAction['label']) ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="surface-default mt-6 border-dashed px-5 py-10 text-slate-500 text-center">
                <svg class="mx-auto mb-4 h-12 w-12 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <?= esc($emptyMessage !== '' ? $emptyMessage : $noResultsLabel) ?>
            </div>
        <?php endif; ?>

        <!-- ── 4. Pagination ──────────────────────────────────────────────── -->
        <?php if ($totalPages > 1): ?>
            <nav class="mt-12 flex flex-wrap items-center justify-center gap-3" aria-label="<?= esc(lang('Site.pagination')) ?>" data-listing-pagination>
                <?php if ($currentPage > 1): ?>
                                    <a href="<?= esc($buildUrl(['page' => $currentPage - 1, 'category' => $currentCategory !== '' ? $currentCategory : null, 'tag' => $currentTag !== '' ? $currentTag : null, 'q' => $currentQuery !== '' ? $currentQuery : null, 'order_by' => $orderBy, 'order_direction' => $orderDirection, 'per_page' => (int) ($pagination['per_page'] ?? 12)])) ?>"
                                       class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold !text-slate-600 shadow-sm transition-colors hover:border-slate-300 hover:!bg-slate-100 !no-underline">
                                        <?= esc($previousLabel) ?>
                                    </a>
                                <?php endif; ?>

                                <span class="text-sm font-medium text-slate-500 px-3">
                                    <?= esc((string) ($pagination['current_page'] ?? $currentPage)) ?> / <?= esc((string) $totalPages) ?>
                                </span>

                                <?php if ($currentPage < $totalPages): ?>
                                    <a href="<?= esc($buildUrl(['page' => $currentPage + 1, 'category' => $currentCategory !== '' ? $currentCategory : null, 'tag' => $currentTag !== '' ? $currentTag : null, 'q' => $currentQuery !== '' ? $currentQuery : null, 'order_by' => $orderBy, 'order_direction' => $orderDirection, 'per_page' => (int) ($pagination['per_page'] ?? 12)])) ?>"
                                       class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold !text-slate-600 shadow-sm transition-colors hover:border-slate-300 hover:!bg-slate-100 !no-underline">
                                        <?= esc($nextLabel) ?>
                                    </a>
                                <?php endif; ?>
            </nav>
        <?php endif; ?>
    </div>
</section>
