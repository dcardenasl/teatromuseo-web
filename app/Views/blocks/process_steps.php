<?php
/**
 * @var array $block
 * @var array $config
 * @var array $data
 */

$title = esc($data['title'] ?? '');
$description = esc($data['description'] ?? '');
$steps = $data['steps'] ?? [];
$steps = is_array($steps) ? $steps : [];
$cssClass = esc(trim($config['css_class'] ?? ''));
?>

<section id="process" class="section-lg bg-slate-50/50 scroll-mt-16 <?= $cssClass ?>">
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

        <?php if ($steps === []): ?>
            <div class="rounded-3xl border border-dashed border-slate-200 bg-white p-8 text-center text-sm text-slate-400">
                No hay pasos registrados.
            </div>
        <?php else: ?>
            <div class="relative">
                <div class="grid gap-6 md:gap-8 grid-cols-1 md:grid-cols-<?= count($steps) ?> relative z-10">
                    <?php foreach ($steps as $idx => $step): 
                        $stepNum = esc($step['step_number'] ?? ($idx + 1));
                        $sTitle = esc($step['title'] ?? '');
                        $sDesc = esc($step['description'] ?? '');
                        if ($sTitle === '') continue;
                    ?>
                        <div class="flex flex-row md:flex-col gap-4 md:gap-6 items-start md:items-center text-left md:text-center bg-white p-6 md:p-8 rounded-2xl border border-slate-100 hover:border-slate-200 shadow-sm hover:shadow-md hover:-translate-y-1 transition-all duration-300 group">
                            <!-- Circle Indicator -->
                            <div class="shrink-0 flex items-center justify-center w-10 h-10 md:w-12 md:h-12 rounded-full bg-violet-600 text-white font-extrabold text-base md:text-lg shadow-sm border border-violet-500">
                                <?= $stepNum ?>
                            </div>
                            
                            <!-- Content -->
                            <div class="min-w-0 flex-1">
                                <h3 class="text-lg font-bold text-slate-800 tracking-tight mb-2 group-hover:text-violet-600 transition-colors">
                                    <?= $sTitle ?>
                                </h3>
                                <?php if ($sDesc !== ''): ?>
                                    <p class="text-sm text-slate-500 leading-relaxed max-w-xs md:mx-auto">
                                        <?= $sDesc ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>
