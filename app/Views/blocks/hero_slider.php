<?php
/**
 * hero_slider block — all variables prepared by HeroSliderViewModel
 * (registered in BlockRenderer::VIEW_MODELS).
 *
 * @var list<array{image: array{source_kind: string, file_id: int|null, url: string, variants?: array<string, mixed>|null}, image_alt_text: string, heading: string, subtitle: string, cta_label: string, cta_url: string}> $slides
 * @var string $captionPosition
 * @var string $controlsPosition
 * @var string $transition
 * @var string $cssClass
 * @var bool   $autoplay
 * @var int    $intervalMs
 * @var int    $overlayPct
 * @var string $jsonSlides
 * @var bool   $captionIsBelow
 * @var bool   $captionIsOverlay
 * @var bool   $controlsIsOverlay
 */

if ($slides === []) {
    return;
}

// Inline color must land on the heading itself, not just the wrapping card: the
// global `h1, h2, h3, h4, h5, h6 { color: #0f172a }` base rule (public/assets/css/app.css)
// directly targets <h2>, and a directly-matching rule always wins over an inherited
// value regardless of specificity — an inline style on the parent alone is silently
// overridden.
//
// $text_color is configured per-slide for the OVERLAY caption, which always sits on
// a dark `bg-slate-950/65` card regardless of the image — white is safe there. The
// BELOW caption instead sits directly on the plain page background (no overlay, no
// dark card), so it must never reuse that overlay-tuned color: it always uses the
// same dark tone as the rest of the page's below-hero copy.
$captionTextColorOverlay = !empty($slides[0]['text_color']) ? $slides[0]['text_color'] : '#ffffff';
$captionTextColorBelow   = 'rgb(15, 23, 42)';
?>

<section class="py-0 <?= esc($cssClass) ?>">
    <div
        class="relative bg-transparent text-slate-900"
        data-hero-carousel
        data-autoplay="<?= $autoplay ? '1' : '0' ?>"
        data-interval="<?= esc((string) $intervalMs) ?>"
        data-caption-position="<?= esc($captionPosition, 'attr') ?>"
        data-controls-position="<?= esc($controlsPosition, 'attr') ?>"
        data-transition="<?= esc($transition, 'attr') ?>"
        data-slides='<?= esc($jsonSlides, 'attr') ?>'
        data-overlay-pct="<?= esc((string) $overlayPct) ?>"
    >
        <div
            class="hero-shell relative overflow-hidden bg-transparent"
        >
            <?= view('components/responsive-image', [
                'src'        => $slides[0]['image']['url'] ?? '',
                'alt'        => $slides[0]['image_alt_text'] ?? $slides[0]['heading'] ?? '',
                'class'      => 'absolute inset-0 h-full w-full object-cover',
                'variants'   => $slides[0]['image']['variants'] ?? null,
                'preferredVariant' => 'lg',
                'sizes'      => '100vw',
                'attributes' => 'data-hero-image',
            ], ['saveData' => false]) ?>

            <div
                data-hero-overlay
                class="absolute inset-0"
                style="background: <?= !empty($slides[0]['overlay_color']) ? esc($slides[0]['overlay_color']) : 'linear-gradient(to bottom, rgba(15, 23, 42, '.number_format($overlayPct / 100, 2, '.', '').') 0%, rgba(15, 23, 42, 0) 42%, rgba(15, 23, 42, '.number_format($overlayPct / 100, 2, '.', '').') 100%)' ?>;<?= $captionIsOverlay ? '' : ' display: none;' ?>"
            ></div>

            <?php if ($captionIsOverlay): ?>
                <div class="absolute inset-x-0 <?= $captionPosition === 'overlay_top' ? 'top-0' : 'bottom-0' ?> z-20 p-4 sm:p-6">
                    <div class="max-w-3xl">
                        <div data-hero-caption-card class="surface-overlay rounded-2xl bg-slate-950/65 px-4 py-3 shadow-2xl shadow-slate-950/20 ring-1 ring-white/10 backdrop-blur-md sm:px-5 sm:py-4" style="color: <?= esc($captionTextColorOverlay) ?>;">
                            <?php if (($slides[0]['heading'] ?? '') !== ''): ?>
                                <h2 data-hero-caption-title class="text-lg font-semibold tracking-tight sm:text-[1.45rem]" style="color: <?= esc($captionTextColorOverlay) ?>;"><?= esc($slides[0]['heading']) ?></h2>
                            <?php endif; ?>
                            <?php if (($slides[0]['subtitle'] ?? '') !== ''): ?>
                                <p data-hero-caption-subtitle class="mt-1 text-sm leading-relaxed text-white/85 sm:text-base"><?= esc($slides[0]['subtitle']) ?></p>
                            <?php endif; ?>
                            <?php if (($slides[0]['cta_label'] ?? '') !== ''): ?>
                                <span data-hero-caption-cta class="mt-2 inline-flex items-center rounded-full border border-white/40 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.12em] text-white/95">
                                    <?= esc($slides[0]['cta_label']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <a
                href="<?= esc($slides[0]['cta_url'] ?? '') ?>"
                data-hero-link
                class="absolute inset-0 z-10 block <?= !empty($slides[0]['cta_url']) ? '' : 'pointer-events-none' ?>"
                <?= !empty($slides[0]['cta_url']) ? 'aria-label="' . esc($slides[0]['heading'] ?? '') . '"' : 'aria-hidden="true" tabindex="-1"' ?>
            ></a>

            <?php if ($controlsIsOverlay): ?>
                <div class="absolute inset-x-0 bottom-4 z-30 flex justify-center px-4">
                    <div class="control-pill surface-overlay shadow-lg shadow-slate-950/20">
                        <button type="button" data-hero-prev class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-700 transition-colors hover:bg-slate-50" aria-label="<?= esc(lang('Site.carousel_previous')) ?>">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                            </svg>
                        </button>
                        <div class="flex items-center gap-2" aria-label="<?= esc(lang('Site.carousel_slides_label')) ?>">
                            <?php foreach ($slides as $index => $slide): ?>
                                <button
                                    type="button"
                                    data-hero-dot="<?= $index ?>"
                                    class="flex h-6 w-6 items-center justify-center rounded-full"
                                    aria-label="<?= esc(lang('Site.carousel_go_to_slide', [$index + 1])) ?>"
                                >
                                    <span data-hero-dot-visual class="flex h-2 w-2 items-stretch overflow-hidden rounded-full border border-slate-300 <?= $index === 0 ? 'bg-slate-100' : 'bg-slate-200' ?>">
                                        <span data-hero-dot-fill class="block h-full w-full bg-slate-900"></span>
                                    </span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" data-hero-next class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-700 transition-colors hover:bg-slate-50" aria-label="<?= esc(lang('Site.carousel_next')) ?>">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                            </svg>
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($captionIsBelow || $controlsPosition === 'below'): ?>
            <div class="container-base py-4">
                <div class="flex flex-col gap-3">
                    <?php if ($captionIsBelow): ?>
                        <div class="max-w-2xl">
                            <div data-hero-caption-card class="px-0 py-0" style="color: <?= esc($captionTextColorBelow) ?>;">
                                <?php if (($slides[0]['heading'] ?? '') !== ''): ?>
                                    <h2 data-hero-caption-title class="section-title text-xl sm:text-[1.6rem]" style="color: <?= esc($captionTextColorBelow) ?>;"><?= esc($slides[0]['heading']) ?></h2>
                                <?php endif; ?>
                                <?php if (($slides[0]['subtitle'] ?? '') !== ''): ?>
                                    <p data-hero-caption-subtitle class="section-copy mt-1 max-w-2xl text-sm sm:text-[0.98rem]"><?= esc($slides[0]['subtitle']) ?></p>
                                <?php endif; ?>
                                <?php if (($slides[0]['cta_label'] ?? '') !== ''): ?>
                                    <span data-hero-caption-cta class="mt-2 inline-flex items-center text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-700">
                                        <?= esc($slides[0]['cta_label']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (! $controlsIsOverlay): ?>
                        <div class="flex justify-center">
                            <div class="control-pill surface-default bg-white/80">
                                <button type="button" data-hero-prev class="inline-flex h-7 w-7 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-700 transition-colors hover:bg-slate-50" aria-label="<?= esc(lang('Site.carousel_previous')) ?>">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                                    </svg>
                                </button>
                                <div class="flex items-center gap-2" aria-label="<?= esc(lang('Site.carousel_slides_label')) ?>">
                                    <?php foreach ($slides as $index => $slide): ?>
                                        <button
                                            type="button"
                                            data-hero-dot="<?= $index ?>"
                                            class="flex h-6 w-6 items-center justify-center rounded-full"
                                            aria-label="<?= esc(lang('Site.carousel_go_to_slide', [$index + 1])) ?>"
                                        >
                                            <span data-hero-dot-visual class="flex h-2 w-2 items-stretch overflow-hidden rounded-full border border-slate-300 <?= $index === 0 ? 'bg-slate-100' : 'bg-slate-200' ?>">
                                                <span data-hero-dot-fill class="block h-full w-full bg-slate-900"></span>
                                            </span>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                                <button type="button" data-hero-next class="inline-flex h-7 w-7 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-700 transition-colors hover:bg-slate-50" aria-label="<?= esc(lang('Site.carousel_next')) ?>">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>
