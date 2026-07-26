<?php
/**
 * @var array $block
 * @var array $config
 * @var array $data
 */

$title = esc($data['title'] ?? '');
$description = esc($data['description'] ?? '');
$features = $data['features'] ?? [];
$features = is_array($features) ? $features : [];
$columns = esc($config['columns'] ?? '3');
$cssClass = esc(trim($config['css_class'] ?? ''));

$colClasses = [
    '2' => 'grid-cols-1 sm:grid-cols-2',
    '3' => 'grid-cols-1 sm:grid-cols-2 md:grid-cols-3',
    '4' => 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-4',
];
$colClass = $colClasses[$columns] ?? $colClasses['3'];
?>

<section id="features" class="section-lg bg-white scroll-mt-16 <?= $cssClass ?>">
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

        <?php if ($features === []): ?>
            <div class="rounded-3xl border border-dashed border-slate-200 bg-slate-50 p-8 text-center text-sm text-slate-400">
                No hay características registradas.
            </div>
        <?php else: ?>
            <div class="grid gap-8 <?= $colClass ?>">
                <?php foreach ($features as $f): 
                    $icon = esc($f['icon_name'] ?? 'check');
                    $fTitle = esc($f['title'] ?? '');
                    $fDesc = esc($f['description'] ?? '');
                    if ($fTitle === '') continue;
                ?>
                    <div class="flex flex-col items-start p-6 rounded-2xl border border-slate-100 hover:border-slate-200 hover:bg-slate-50/50 hover:shadow-sm transition-all duration-300 group">
                        <div class="shrink-0 flex items-center justify-center w-12 h-12 rounded-2xl bg-violet-50 text-violet-600 mb-5 group-hover:bg-violet-600 group-hover:text-white transition-all duration-300">
                            <i data-lucide="<?= $icon ?>" class="w-6 h-6"></i>
                        </div>
                        <h3 class="text-lg font-bold text-slate-800 tracking-tight mb-2 group-hover:text-violet-600 transition-colors">
                            <?= $fTitle ?>
                        </h3>
                        <?php if ($fDesc !== ''): ?>
                            <p class="text-sm text-slate-500 leading-relaxed">
                                <?= $fDesc ?>
                            </p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
