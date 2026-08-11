<?php
/** @var list<array{logo: array{source_kind: string, file_id: int|null, url: string}, name: string, link_url: string}> $logos */
/** @var string $layout */
/** @var string $speed */
/** @var bool $grayscale */
/** @var bool $isMarquee */
/** @var string $duration */
/** @var string $logoStyleClass */
/** @var string $cssClass */

if ($logos === []) {
    return;
}
?>

<section class="section-sm overflow-hidden <?= esc($cssClass) ?>">
    <?php if ($isMarquee): ?>
        <style <?= csp_style_nonce() ?>>
            @keyframes marquee {
                0% { transform: translateX(0%); }
                100% { transform: translateX(-50%); }
            }
            .marquee-track {
                display: flex;
                width: max-content;
                animation: marquee <?= $duration ?> linear infinite;
            }
            .marquee-track:hover {
                animation-play-state: paused;
            }
        </style>
        
        <div class="relative w-full overflow-hidden flex items-center py-4 mask-gradient-h">
            <!-- Fade masks for smooth edges -->
            <div class="absolute left-0 top-0 bottom-0 w-16 bg-gradient-to-r from-slate-50 to-transparent pointer-events-none z-10"></div>
            <div class="absolute right-0 top-0 bottom-0 w-16 bg-gradient-to-l from-slate-50 to-transparent pointer-events-none z-10"></div>
            
            <div class="marquee-track flex gap-12 items-center">
                <!-- Double the array to ensure seamless looping -->
                <?php 
                $loopLogos = array_merge($logos, $logos, $logos); 
                foreach ($loopLogos as $logo): 
                ?>
                    <div class="flex-shrink-0 h-10 w-32 flex items-center justify-center">
                        <?php if ($logo['link_url'] !== ''): ?>
                            <a href="<?= esc($logo['link_url']) ?>" target="_blank" rel="noopener noreferrer" class="block">
                        <?php endif; ?>
                        
                        <?php if (($logo['logo']['url'] ?? '') !== ''): ?>
                            <?= view('components/responsive-image', [
                                'src'        => $logo['logo']['url'],
                                'alt'        => $logo['name'],
                                'class'      => 'max-h-full max-w-full object-contain ' . $logoStyleClass,
                                'attributes' => 'title="' . esc($logo['name']) . '"',
                                'variants'   => $logo['logo']['variants'] ?? null,
                                'preferredVariant' => 'thumb',
                                'sizes'      => '8rem',
                                'maxVariantWidth' => 160,
                            ], ['saveData' => false]) ?>
                        <?php else: ?>
                            <span class="text-sm font-semibold text-slate-500"><?= esc($logo['name']) ?></span>
                        <?php endif; ?>
                        
                        <?php if ($logo['link_url'] !== ''): ?>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

    <?php else: ?>
        <!-- Grid Layout -->
        <div class="max-w-6xl mx-auto grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-8 items-center justify-items-center py-4">
            <?php foreach ($logos as $logo): ?>
                <div class="h-12 w-32 flex items-center justify-center">
                    <?php if ($logo['link_url'] !== ''): ?>
                        <a href="<?= esc($logo['link_url']) ?>" target="_blank" rel="noopener noreferrer" class="block">
                    <?php endif; ?>
                    
                    <?php if (($logo['logo']['url'] ?? '') !== ''): ?>
                        <?= view('components/responsive-image', [
                            'src'        => $logo['logo']['url'],
                            'alt'        => $logo['name'],
                            'class'      => 'max-h-full max-w-full object-contain ' . $logoStyleClass,
                            'attributes' => 'title="' . esc($logo['name']) . '"',
                            'variants'   => $logo['logo']['variants'] ?? null,
                            'preferredVariant' => 'thumb',
                            'sizes'      => '8rem',
                            'maxVariantWidth' => 160,
                        ], ['saveData' => false]) ?>
                    <?php else: ?>
                        <span class="text-sm font-semibold text-slate-500"><?= esc($logo['name']) ?></span>
                    <?php endif; ?>
                    
                    <?php if ($logo['link_url'] !== ''): ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
