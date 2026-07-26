<?php
/**
 * @var array $block
 * @var array $config
 * @var array $data
 */

$title = esc($data['title'] ?? '');
$description = esc($data['description'] ?? '');
$links = $data['links'] ?? [];
$links = is_array($links) ? $links : [];

$layoutCols = esc($config['layout_columns'] ?? '2');
$openInNewTab = (bool) ($config['open_in_new_tab'] ?? true);
$cssClass = esc(trim($config['css_class'] ?? ''));

$colClasses = [
    '1' => 'grid-cols-1',
    '2' => 'grid-cols-1 md:grid-cols-2',
    '3' => 'grid-cols-1 sm:grid-cols-2 md:grid-cols-3',
];
$colClass = $colClasses[$layoutCols] ?? $colClasses['2'];
?>

<section class="section <?= esc($cssClass) ?>">
    <div class="max-w-5xl mx-auto px-4">
        <?php if ($title !== ''): ?>
            <div class="max-w-2xl mb-8">
                <h2 class="text-2xl md:text-3xl font-bold text-slate-900 tracking-tight mb-2 bg-gradient-to-r from-slate-900 to-slate-700 bg-clip-text text-transparent">
                    <?= $title ?>
                </h2>
                <?php if ($description !== ''): ?>
                    <p class="text-sm text-slate-500">
                        <?= $description ?>
                    </p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($links === []): ?>
            <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-8 text-center text-sm text-slate-400">
                No hay enlaces registrados.
            </div>
        <?php else: ?>
            <div class="grid gap-4 <?= $colClass ?>">
                <?php foreach ($links as $link): 
                    $label = esc($link['label'] ?? '');
                    $url = esc($link['url'] ?? '');
                    $linkDesc = esc($link['description'] ?? '');
                    $icon = esc($link['icon_name'] ?? 'external-link');
                    if ($label === '' || $url === '') continue;
                ?>
                    <a href="<?= $url ?>" 
                       <?= $openInNewTab ? 'target="_blank" rel="noopener noreferrer"' : '' ?>
                       class="flex items-start gap-4 p-5 rounded-2xl border border-slate-200/80 bg-white hover:border-violet-300 hover:shadow-sm transition-all duration-300 group">
                        
                        <div class="shrink-0 flex items-center justify-center w-10 h-10 rounded-xl bg-slate-50 text-slate-400 group-hover:bg-violet-50 group-hover:text-violet-600 transition-colors">
                            <i data-lucide="<?= $icon ?>" class="w-5 h-5"></i>
                        </div>
                        
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-1.5">
                                <h3 class="text-base font-semibold text-slate-800 group-hover:text-violet-600 transition-colors truncate">
                                    <?= $label ?>
                                </h3>
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-3.5 h-3.5 text-slate-400 group-hover:translate-x-0.5 group-hover:-translate-y-0.5 transition-transform flex-shrink-0">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 19.5 15-15m0 0H8.25m11.25 0v11.25" />
                                </svg>
                            </div>
                            <?php if ($linkDesc !== ''): ?>
                                <p class="text-xs text-slate-500 mt-1 line-clamp-2">
                                    <?= $linkDesc ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
