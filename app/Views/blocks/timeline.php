<?php
/**
 * @var array $block
 * @var array $config
 * @var array $data
 * @var string $renderedChildren
 */

$sectionTitle = esc($data['section_title'] ?? '');
$description = esc($data['description'] ?? '');
$layout = esc($config['layout'] ?? 'alternating');
$cssClass = esc(trim($config['css_class'] ?? ''));
?>

<section class="section-lg <?= $cssClass ?>">
    <div class="max-w-5xl mx-auto px-4">
        <?php if ($sectionTitle !== ''): ?>
            <div class="text-center max-w-2xl mx-auto mb-16">
                <h2 class="text-3xl md:text-4xl font-extrabold tracking-tight text-slate-900 mb-4 bg-gradient-to-r from-slate-900 to-slate-700 bg-clip-text text-transparent">
                    <?= $sectionTitle ?>
                </h2>
                <?php if ($description !== ''): ?>
                    <p class="text-lg text-slate-600">
                        <?= $description ?>
                    </p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="relative">
            <!-- Timeline Center Line -->
            <div class="absolute left-0 md:<?= $layout === 'alternating' ? 'left-1/2 -translate-x-1/2' : 'left-8' ?> top-0 bottom-0 w-1 md:w-0.5 bg-gradient-to-b from-violet-500 via-indigo-400 to-slate-200"></div>

            <div class="space-y-8 md:space-y-12 relative timeline-container" data-layout="<?= $layout ?>">
                <?= $renderedChildren ?>
            </div>
        </div>
    </div>
</section>
