<?php
/**
 * Reusable entry card for blog/news listings.
 *
 * @var array<string, mixed> $entry
 * @var string $collectionUrlPath
 * @var string $lang
 */
$slug       = $entry['slug'] ?? '';
$title      = $entry['title'] ?? '';
$excerpt    = $entry['excerpt'] ?? '';
$date       = $entry['published_at'] ?? $entry['created_at'] ?? '';
$image      = is_array($entry['featured_image'] ?? null) ? $entry['featured_image'] : [];
$imageUrl   = is_string($image['url'] ?? null) ? trim((string) $image['url']) : '';
$categories = array_slice($entry['categories'] ?? [], 0, 2);
$entryUrl   = lang_url($collectionUrlPath . '/' . $slug);
$readMore   = lang('Site.read_more');
?>
<article class="surface-card overflow-hidden flex flex-col group transition-shadow hover:shadow-md">

    <?php if ($imageUrl): ?>
        <a href="<?= esc($entryUrl) ?>" class="block overflow-hidden aspect-video" tabindex="-1" aria-hidden="true">
            <?= view('components/responsive-image', [
                'src'      => $imageUrl,
                'alt'      => $title,
                'class'    => 'w-full h-full object-cover transition-transform duration-300 group-hover:scale-105',
                'variants' => $image['variants'] ?? null,
            ], ['saveData' => false]) ?>
        </a>
    <?php else: ?>
        <div class="aspect-video bg-gradient-to-br from-slate-100 to-slate-200 flex items-center justify-center">
            <svg class="w-12 h-12 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/>
            </svg>
        </div>
    <?php endif; ?>

    <div class="p-5 flex flex-col flex-1">

        <?php if (!empty($categories)): ?>
            <div class="flex flex-wrap gap-1.5 mb-3">
                <?php foreach ($categories as $cat): ?>
                    <span class="badge badge-secondary text-xs">
                        <?= esc($cat['name'] ?? '') ?>
                    </span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($date): ?>
            <p class="text-xs text-text-muted uppercase tracking-widest mb-2">
                <?= esc(date('d M Y', strtotime($date))) ?>
            </p>
        <?php endif; ?>

        <h3 class="text-lg font-semibold leading-snug text-text-primary mb-2 flex-1">
            <a href="<?= esc($entryUrl) ?>"
               class="hover:text-primary transition-colors line-clamp-2">
                <?= esc($title) ?>
            </a>
        </h3>

        <?php if ($excerpt): ?>
            <p class="text-sm text-text-secondary mb-4 line-clamp-3">
                <?= esc($excerpt) ?>
            </p>
        <?php endif; ?>

        <a href="<?= esc($entryUrl) ?>"
           class="link text-sm font-medium mt-auto inline-flex items-center gap-1 group-hover:gap-2 transition-all">
            <?= esc($readMore) ?>
            <span aria-hidden="true">&rarr;</span>
        </a>

    </div>
</article>
