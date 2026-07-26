<?php
/**
 * Collection index — blog-style listing.
 *
 * @var array<string, mixed> $collection
 * @var array<int, array<string, mixed>> $data   Entries list
 * @var array<string, mixed> $meta               API meta
 * @var array<int, array<string, mixed>> $categories
 * @var string $currentCategory  Active category slug
 * @var int $currentPage
 * @var array<string, mixed> $pagination
 * @var string $lang
 */
$urlPath         = $collectionUrlPath ?? collection_url_path($collection);
$listingTitle    = collection_display_title($collection);
$listingIntro    = collection_display_intro($collection);
$pagination      = $pagination ?? $meta['pagination'] ?? [];
$totalPages      = (int) ($pagination['total_pages'] ?? 1);
$allLabel        = lang('Site.collection_all');
$prevLabel       = lang('Site.collection_previous');
$nextLabel       = lang('Site.collection_next');
$emptyMsg        = lang('Site.collection_empty');

// Build query string helper
$buildUrl = static function (array $params) use ($urlPath): string {
    $qs = http_build_query(array_filter($params, static fn ($v) => $v !== '' && $v !== null && $v !== 0 && $v !== 1 || $v === 1));
    return lang_url($urlPath) . ($qs !== '' ? '?' . $qs : '');
};
?>

<!-- ── Page Header ─────────────────────────────────────────────────── -->
<section class="section-sm bg-white border-b border-slate-100">
    <div class="container-base">
        <h1 class="section-title text-3xl sm:text-4xl">
            <?= esc($listingTitle !== '' ? $listingTitle : lang('Site.collection_index_label')) ?>
        </h1>
        <?php if ($listingIntro): ?>
            <div class="section-copy mt-3 prose max-w-none">
                <?= $listingIntro ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- ── Category Filter ────────────────────────────────────────────── -->
<?php if (!empty($categories)): ?>
    <nav class="bg-white border-b border-slate-100 sticky top-0 z-10 shadow-sm" aria-label="Filtro por categoría">
        <div class="container-base">
            <div class="flex gap-2 overflow-x-auto py-3 scrollbar-none">
                <a href="<?= esc($buildUrl(['page' => null])) ?>"
                   class="inline-flex items-center px-4 py-1.5 rounded-full text-sm font-medium whitespace-nowrap transition-colors
                          <?= $currentCategory === '' ? 'bg-primary text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">
                    <?= esc($allLabel) ?>
                </a>
                <?php foreach ($categories as $cat): ?>
                    <?php $catSlug = $cat['slug'] ?? ''; ?>
                    <a href="<?= esc($buildUrl(['category' => $catSlug, 'page' => null])) ?>"
                       class="inline-flex items-center px-4 py-1.5 rounded-full text-sm font-medium whitespace-nowrap transition-colors
                              <?= $currentCategory === $catSlug ? 'bg-primary text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">
                        <?= esc($cat['name'] ?? $catSlug) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </nav>
<?php endif; ?>

<div class="section bg-background">
    <div class="container-base">

        <!-- ── Entries Grid ───────────────────────────────────────────── -->
        <?php if (!empty($data)): ?>
            <div class="grid-cols-blog grid gap-6 mb-10">
                <?php foreach ($data as $entry): ?>
                    <?= view('collection/partials/entry_card', [
                        'entry'               => $entry,
                        'collectionUrlPath' => $urlPath,
                        'lang'                => $lang,
                    ], ['saveData' => false]) ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="surface-default border-dashed text-center py-16 text-text-muted">
                <svg class="mx-auto mb-4 h-12 w-12 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <?= esc($emptyMsg) ?>
            </div>
        <?php endif; ?>

        <!-- ── Pagination ──────────────────────────────────────────────── -->
        <?php if ($totalPages > 1): ?>
            <nav class="flex items-center justify-center gap-3 mt-8" aria-label="Paginación">
                <?php if ($currentPage > 1): ?>
                    <a href="<?= esc($buildUrl(['page' => $currentPage - 1, 'category' => $currentCategory ?: null])) ?>"
                       class="btn btn-secondary btn-sm">
                        <?= esc($prevLabel) ?>
                    </a>
                <?php else: ?>
                    <span class="btn btn-secondary btn-sm opacity-40 cursor-not-allowed"><?= esc($prevLabel) ?></span>
                <?php endif; ?>

                <span class="text-sm text-text-muted">
                    <?= esc(lang('Site.pagination_page_of', [$currentPage, $totalPages])) ?>
                </span>

                <?php if ($currentPage < $totalPages): ?>
                    <a href="<?= esc($buildUrl(['page' => $currentPage + 1, 'category' => $currentCategory ?: null])) ?>"
                       class="btn btn-secondary btn-sm">
                        <?= esc($nextLabel) ?>
                    </a>
                <?php else: ?>
                    <span class="btn btn-secondary btn-sm opacity-40 cursor-not-allowed"><?= esc($nextLabel) ?></span>
                <?php endif; ?>
            </nav>
        <?php endif; ?>

    </div>
</div>
