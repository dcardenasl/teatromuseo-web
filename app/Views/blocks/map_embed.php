<?php
/** @var array<string, mixed> $config */
/** @var array<string, mixed> $data */

$title       = trim((string) ($data['title'] ?? ''));
$caption     = trim((string) ($data['caption'] ?? ''));
$embedUrl    = trim((string) ($config['embed_url'] ?? ''));
$aspectRatio = (string) ($config['aspect_ratio'] ?? '16/9');
$height      = max(220, (int) ($config['height'] ?? 360));
$cssClass    = trim((string) ($config['css_class'] ?? ''));

if ($embedUrl === '') {
    return;
}

$ratioClass = match ($aspectRatio) {
    '4/3' => 'aspect-[4/3]',
    '1/1' => 'aspect-square',
    default => 'aspect-video',
};
?>
<section class="section-sm <?= esc($cssClass) ?>">
    <div class="container-base">
        <?php if ($title !== '' || $caption !== ''): ?>
            <div class="mb-5 max-w-3xl">
                <?php if ($title !== ''): ?>
                    <h2 class="section-title text-2xl sm:text-3xl"><?= esc($title) ?></h2>
                <?php endif; ?>
                <?php if ($caption !== ''): ?>
                    <p class="section-copy mt-2 text-base"><?= esc($caption) ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="surface-card overflow-hidden">
            <iframe
                src="<?= esc($embedUrl) ?>"
                title="<?= esc($title !== '' ? $title : 'Mapa embebido') ?>"
                width="100%"
                height="100%"
                class="<?= esc($ratioClass) ?> w-full"
                style="border:0; min-height: <?= esc((string) $height) ?>px;"
                allowfullscreen=""
                loading="lazy"
                referrerpolicy="no-referrer-when-downgrade"></iframe>
        </div>
    </div>
</section>
