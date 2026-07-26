<?php
/**
 * @var array $block
 * @var array $config
 * @var array $data
 * @var string $renderedChildren
 */

$title = esc($data['title'] ?? '');
$description = esc($data['description'] ?? '');
$columns = esc($config['columns'] ?? '3');
$cssClass = esc(trim($config['css_class'] ?? ''));

$colClasses = [
    '2' => 'grid-cols-1 sm:grid-cols-2',
    '3' => 'grid-cols-1 sm:grid-cols-2 md:grid-cols-3',
    '4' => 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-4',
];
$colClass = $colClasses[$columns] ?? $colClasses['3'];
?>

<section id="team" class="section-lg bg-white scroll-mt-16 <?= $cssClass ?>">
    <div class="max-w-5xl mx-auto px-4">
        <?php if ($title !== ''): ?>
            <div class="text-center max-w-2xl mx-auto mb-16">
                <h2 class="text-3xl font-extrabold text-slate-900 tracking-tight mb-4 bg-gradient-to-r from-slate-900 to-slate-700 bg-clip-text text-transparent">
                    <?= $title ?>
                </h2>
                <?php if ($description !== ''): ?>
                    <p class="text-lg text-slate-600">
                        <?= $description ?>
                    </p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (trim($renderedChildren) === ''): ?>
            <div class="rounded-3xl border border-dashed border-slate-200 bg-slate-50 p-8 text-center text-sm text-slate-400">
                No hay integrantes del equipo registrados.
            </div>
        <?php else: ?>
            <div class="grid gap-8 <?= $colClass ?>">
                <?= $renderedChildren ?>
            </div>
        <?php endif; ?>
    </div>
</section>
