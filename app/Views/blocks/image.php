<?php
$image = is_array($config['image'] ?? null) ? $config['image'] : [];
$imageUrl = (string) ($image['url'] ?? '');
$alt = (string) ($data['alt'] ?? '');
$caption = $data['caption'] ?? null;
$figureClass = trim((string) ($config['css_class'] ?? ''));
$cssClass = esc(trim($config['css_class'] ?? ''));
?>
<section class="section-sm <?= $cssClass ?>">
    <div class="max-w-5xl mx-auto px-4">
        <?php if ($imageUrl === ''): ?>
            <figure class="<?= esc($figureClass) ?> rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-6 py-10 text-center text-sm text-slate-500">
                <p class="font-medium text-slate-600"><?= esc($alt !== '' ? $alt : lang('Site.block_image_placeholder')) ?></p>
                <?php if (! empty($caption)): ?>
                    <p class="mt-2 text-slate-500"><?= esc((string) $caption) ?></p>
                <?php endif; ?>
            </figure>
        <?php else: ?>
            <figure class="<?= esc($figureClass) ?>">
                <?= view('components/responsive-image', [
                    'src'      => $imageUrl,
                    'alt'      => $alt,
                    'class'    => 'w-full h-auto rounded-3xl border border-slate-200/40 shadow-sm transition-all duration-300 hover:shadow-md',
                    'variants' => $image['variants'] ?? null,
                    'preferredVariant' => 'lg',
                    'sizes'    => '(max-width: 1023px) calc(100vw - 2rem), 1024px',
                ], ['saveData' => false]) ?>
                <?php if (! empty($caption)): ?>
                    <figcaption class="mt-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-500">
                        <?= esc((string) $caption) ?>
                    </figcaption>
                <?php endif; ?>
            </figure>
        <?php endif; ?>
    </div>
</section>
