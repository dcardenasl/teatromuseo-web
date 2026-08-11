<?php
/**
 * cards_slider block — all variables prepared by CardsSliderViewModel
 * (registered in BlockRenderer::VIEW_MODELS).
 *
 * @var list<array{eyebrow: string, title: string, body: string, meta_title: string, meta_description: string, image: array{source_kind: string, file_id: int|null, url: string}, rating: int, link_url: string, link_label: string}> $cards
 * @var string    $sectionTitle
 * @var string    $sectionSubtitle
 * @var bool      $isSlider
 * @var bool      $autoplay
 * @var int       $interval
 * @var int       $visibleCount
 * @var string    $cardVariant
 * @var string    $cssClass
 * @var float|int $slideBasis
 * @var int       $dotCount
 * @var string    $sliderWidthClass
 */

if ($cards === []) {
    return;
}
?>

<section class="section <?= esc($cssClass) ?>">
    <?php if ($sectionTitle !== '' || $sectionSubtitle !== ''): ?>
        <div class="container-base mb-8 text-center">
            <?php if ($sectionTitle !== ''): ?>
                <h2 class="section-title text-2xl sm:text-3xl"><?= esc($sectionTitle) ?></h2>
            <?php endif; ?>
            <?php if ($sectionSubtitle !== ''): ?>
                <p class="section-copy mx-auto mt-3 max-w-2xl text-base"><?= esc($sectionSubtitle) ?></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($isSlider): ?>
        <div 
            class="relative <?= esc($sliderWidthClass) ?> mx-auto overflow-hidden group/slider"
            data-cards-slider
            data-autoplay="<?= $autoplay ? '1' : '0' ?>"
            data-interval="<?= esc((string) $interval) ?>"
            data-visible-count="<?= esc((string) $visibleCount) ?>"
        >
            <div class="slides-container flex transition-transform duration-500 ease-out">
                <?php foreach ($cards as $index => $t): ?>
                    <div class="flex-shrink-0 px-3" style="flex-basis: <?= esc((string) $slideBasis) ?>%;">
                        <div class="h-full bg-white border border-slate-100 rounded-3xl p-6 md:p-8 shadow-sm flex flex-col <?= $cardVariant === 'testimonial' ? 'text-center items-center' : '' ?>">
                            <?php if (($t['image']['url'] ?? '') !== ''): ?>
                                <?= view('components/responsive-image', [
                                    'src'              => $t['image']['url'],
                                    'alt'              => $t['title'] ?: $t['meta_title'],
                                    'class'            => 'mb-5 h-36 w-full rounded-2xl object-cover',
                                    'variants'         => $t['image']['variants'] ?? null,
                                    'preferredVariant' => 'sd',
                                    'sizes'            => $visibleCount === 1
                                        ? '(max-width: 1023px) calc(100vw - 1.5rem), 896px'
                                        : '(max-width: 639px) calc(100vw - 1.5rem), (max-width: 1023px) 50vw, 33vw',
                                    'maxVariantWidth'  => 640,
                                ], ['saveData' => false]) ?>
                            <?php endif; ?>
                            <?php if ($t['rating'] > 0): ?>
                                <div class="flex gap-1 mb-4 text-amber-400">
                                    <?php for ($i = 0; $i < 5; $i++): ?>
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="<?= $i < $t['rating'] ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                                    <?php endfor; ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($t['eyebrow'] !== ''): ?>
                                <p class="section-eyebrow mb-2"><?= esc($t['eyebrow']) ?></p>
                            <?php endif; ?>
                            <?php if ($t['title'] !== ''): ?>
                                <h3 class="text-xl font-bold leading-tight text-slate-900"><?= esc($t['title']) ?></h3>
                            <?php endif; ?>
                            <?php if ($t['body'] !== ''): ?>
                                <p class="section-copy mt-3 flex-grow text-sm leading-relaxed"><?= esc($t['body']) ?></p>
                            <?php endif; ?>
                            <?php if ($t['meta_title'] !== '' || $t['meta_description'] !== ''): ?>
                                <div class="mt-6 border-t border-slate-100 pt-4">
                                    <?php if ($t['meta_title'] !== ''): ?>
                                        <p class="font-bold text-slate-900"><?= esc($t['meta_title']) ?></p>
                                    <?php endif; ?>
                                    <?php if ($t['meta_description'] !== ''): ?>
                                        <p class="text-xs font-medium text-slate-400"><?= esc($t['meta_description']) ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($t['link_url'] !== '' && $t['link_label'] !== ''): ?>
                                <a href="<?= esc($t['link_url']) ?>" class="mt-5 inline-flex text-sm font-semibold text-primary hover:underline">
                                    <?= esc($t['link_label']) ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (count($cards) > $visibleCount): ?>
                <button 
                    data-slider-prev
                    class="absolute left-2 top-1/2 -translate-y-1/2 bg-white/80 hover:bg-white text-slate-700 w-10 h-10 rounded-full flex items-center justify-center shadow-md hover:scale-105 border border-slate-100 transition-all focus:outline-none opacity-0 group-hover/slider:opacity-100"
                    aria-label="<?= esc(lang('Site.carousel_previous')) ?>"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                </button>
                <button 
                    data-slider-next
                    class="absolute right-2 top-1/2 -translate-y-1/2 bg-white/80 hover:bg-white text-slate-700 w-10 h-10 rounded-full flex items-center justify-center shadow-md hover:scale-105 border border-slate-100 transition-all focus:outline-none opacity-0 group-hover/slider:opacity-100"
                    aria-label="<?= esc(lang('Site.carousel_next')) ?>"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                </button>

                <div class="flex justify-center gap-2 mt-6" data-slider-dots>
                    <?php for ($index = 0; $index < $dotCount; $index++): ?>
                        <button 
                            data-dot="<?= $index ?>"
                            class="w-2.5 h-2.5 rounded-full transition-all duration-300 <?= $index === 0 ? 'bg-violet-600 w-6' : 'bg-slate-300 hover:bg-slate-400' ?>"
                            aria-label="<?= esc(lang('Site.carousel_go_to_slide', [$index + 1])) ?>"
                        ></button>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php // Slider behavior lives in src/js/components/cardsSlider.js (data-cards-slider). ?>

    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 max-w-6xl mx-auto">
            <?php foreach ($cards as $t): ?>
                <div class="bg-white border border-slate-100 rounded-2xl p-6 shadow-sm hover:shadow-md transition-all duration-300 flex flex-col">
                    <?php if (($t['image']['url'] ?? '') !== ''): ?>
                        <?= view('components/responsive-image', [
                            'src'              => $t['image']['url'],
                            'alt'              => $t['title'] ?: $t['meta_title'],
                            'class'            => 'mb-4 h-32 w-full rounded-xl object-cover',
                            'variants'         => $t['image']['variants'] ?? null,
                            'preferredVariant' => 'sd',
                            'sizes'            => '(max-width: 767px) calc(100vw - 3rem), (max-width: 1023px) 50vw, 33vw',
                            'maxVariantWidth'  => 640,
                        ], ['saveData' => false]) ?>
                    <?php endif; ?>
                    <?php if ($t['rating'] > 0): ?>
                        <div class="flex gap-1 mb-4 text-amber-400">
                            <?php for ($i = 0; $i < 5; $i++): ?>
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="<?= $i < $t['rating'] ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($t['eyebrow'] !== ''): ?>
                        <p class="section-eyebrow mb-2"><?= esc($t['eyebrow']) ?></p>
                    <?php endif; ?>
                    <?php if ($t['title'] !== ''): ?>
                        <h3 class="text-lg font-bold text-slate-900"><?= esc($t['title']) ?></h3>
                    <?php endif; ?>
                    <?php if ($t['body'] !== ''): ?>
                        <p class="section-copy mt-3 flex-grow text-sm leading-relaxed"><?= esc($t['body']) ?></p>
                    <?php endif; ?>
                    <div class="mt-auto pt-4">
                        <?php if ($t['meta_title'] !== ''): ?>
                            <p class="font-bold text-slate-800 text-sm"><?= esc($t['meta_title']) ?></p>
                        <?php endif; ?>
                        <?php if ($t['meta_description'] !== ''): ?>
                            <p class="text-xs text-slate-400 font-medium"><?= esc($t['meta_description']) ?></p>
                        <?php endif; ?>
                        <?php if ($t['link_url'] !== '' && $t['link_label'] !== ''): ?>
                            <a href="<?= esc($t['link_url']) ?>" class="mt-3 inline-flex text-sm font-semibold text-primary hover:underline">
                                <?= esc($t['link_label']) ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
