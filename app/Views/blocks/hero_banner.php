<?php
/**
 * hero_banner block — variables prepared by HeroBannerViewModel.
 *
 * @var array{source_kind: string, file_id: int|null, url: string} $image The background image reference
 * @var string $alt Alt text for the background image
 * @var string $heading Primary heading text
 * @var string $subheading Secondary heading text
 * @var string $cta_label Call-to-action button label
 * @var string $cta_url Call-to-action button URL (localized)
 * @var string $cssClass Additional CSS classes
 * @var string $text_color Hex color for heading/subheading text (default: #ffffff)
 * @var string $overlay_color RGBA color for the overlay (default: rgba(15, 23, 42, 0.4))
 */

$image = is_array($image ?? null) ? $image : ['source_kind' => 'external_url', 'file_id' => null, 'url' => ''];
$alt = $alt ?? '';
$heading = $heading ?? '';
$subheading = $subheading ?? '';
$cta_label = $cta_label ?? '';
$cta_url = $cta_url ?? '';
$cssClass = $cssClass ?? '';
$text_color = $text_color ?? '#ffffff';
$overlay_color = $overlay_color ?? 'rgba(15, 23, 42, 0.4)';
?>
<section class="relative h-96 flex items-center justify-center overflow-hidden <?= esc($cssClass) ?>">
    <?php if (!empty($image['url'])): ?>
        <?= view('components/responsive-image', [
            'src'      => $image['url'],
            'alt'      => $alt,
            'class'    => 'absolute inset-0 w-full h-full object-cover',
            'variants' => $image['variants'] ?? null,
        ], ['saveData' => false]) ?>
        <div class="absolute inset-0" style="background-color: <?= esc($overlay_color) ?>;"></div>
    <?php endif; ?>

    <div class="relative z-10 text-center px-4" style="color: <?= esc($text_color) ?>;">
        <?php if ($heading !== ''): ?>
            <h1 class="text-4xl md:text-5xl font-bold mb-4" style="color: <?= esc($text_color) ?>;">
                <?= esc($heading) ?>
            </h1>
        <?php endif; ?>

        <?php if ($subheading !== ''): ?>
            <p class="text-lg md:text-xl mb-6" style="color: <?= esc($text_color) ?>;">
                <?= esc($subheading) ?>
            </p>
        <?php endif; ?>

        <?php if ($cta_label !== '' && $cta_url !== ''): ?>
            <a href="<?= esc($cta_url) ?>" class="inline-block bg-white text-black px-6 py-3 rounded-lg font-semibold hover:bg-gray-100 transition">
                <?= esc($cta_label) ?>
            </a>
        <?php endif; ?>
    </div>
</section>
