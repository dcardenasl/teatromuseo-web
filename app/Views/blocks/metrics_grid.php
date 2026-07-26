<?php
/**
 * metrics_grid block — all variables prepared by MetricsGridViewModel
 * (registered in BlockRenderer::VIEW_MODELS).
 *
 * @var list<array{prefix: string, number: string, suffix: string, label: string, description: string, source_label: string, source_url: string, icon: string, num_only: int, display_suffix: string, display_value: string}> $stats
 * @var string $cssClass
 * @var string $columnsClass
 * @var string $sectionClass
 * @var string $numColorClass
 * @var string $lblColorClass
 * @var string $iconColorClass
 * @var string $dividerClass
 */

if ($stats === []) {
    return;
}
?>

<section class="section <?= esc($cssClass) ?>">
    <div class="<?= esc($sectionClass) ?>">
        <div class="grid grid-cols-1 gap-8 sm:grid-cols-2 <?= esc($columnsClass) ?> divide-y sm:divide-y-0 sm:divide-x <?= esc($dividerClass) ?>">
            <?php foreach ($stats as $stat): ?>
                <div class="flex flex-col items-center text-center p-4 first:pt-0 sm:first:pt-4">
                    <?php if ($stat['icon'] !== ''): ?>
                        <div class="mb-4 h-12 w-12 rounded-2xl flex items-center justify-center <?= esc($iconColorClass) ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>
                        </div>
                    <?php endif; ?>
                    
                    <span 
                        class="text-4xl md:text-5xl font-bold tracking-tight <?= esc($numColorClass) ?> mb-2"
                        data-stat-counter
                        data-target-value="<?= esc((string) $stat['num_only']) ?>"
                        data-prefix="<?= esc($stat['prefix']) ?>"
                        data-suffix="<?= esc($stat['display_suffix']) ?>"
                    >
                        <?= esc($stat['display_value']) ?>
                    </span>
                    
                    <span class="text-sm md:text-base font-semibold tracking-wide uppercase <?= esc($lblColorClass) ?>">
                        <?= esc($stat['label']) ?>
                    </span>
                    <?php if ($stat['description'] !== ''): ?>
                        <span class="mt-2 max-w-xs text-xs leading-relaxed <?= esc($lblColorClass) ?>">
                            <?= esc($stat['description']) ?>
                        </span>
                    <?php endif; ?>
                    <?php if ($stat['source_label'] !== ''): ?>
                        <?php if ($stat['source_url'] !== ''): ?>
                            <a href="<?= esc($stat['source_url']) ?>" class="mt-2 text-[11px] font-medium underline-offset-4 hover:underline <?= esc($lblColorClass) ?>">
                                <?= esc($stat['source_label']) ?>
                            </a>
                        <?php else: ?>
                            <span class="mt-2 text-[11px] <?= esc($lblColorClass) ?>">
                                <?= esc($stat['source_label']) ?>
                            </span>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php // Counter animation lives in src/js/components/metricsCounter.js (data-stat-counter). ?>
