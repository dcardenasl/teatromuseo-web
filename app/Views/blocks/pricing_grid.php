<?php
/**
 * @var array $block
 * @var array $config
 * @var array $data
 * @var string $renderedChildren
 */

$title = esc($data['title'] ?? '');
$description = esc($data['description'] ?? '');
$cssClass = esc(trim($config['css_class'] ?? ''));
?>

<section id="pricing" class="section-lg scroll-mt-16 <?= $cssClass ?>">
    <div class="max-w-5xl mx-auto px-4">
        <?php if ($title !== ''): ?>
            <div class="text-center max-w-2xl mx-auto mb-16">
                <h2 class="text-3xl md:text-4xl font-extrabold tracking-tight text-slate-900 mb-4 bg-gradient-to-r from-slate-900 to-slate-700 bg-clip-text text-transparent">
                    <?= $title ?>
                </h2>
                <?php if ($description !== ''): ?>
                    <p class="text-lg text-slate-600 mb-12">
                        <?= $description ?>
                    </p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="grid gap-8 gap-y-20 grid-cols-1 md:grid-cols-2 lg:grid-cols-3 justify-center items-stretch">
            <?= $renderedChildren ?>
        </div>
    </div>
</section>
