<?php
/**
 * @var array $block
 * @var array $config
 * @var array $data
 */

$dateLabel = esc($data['date_label'] ?? '');
$title = esc($data['title'] ?? '');
$description = $data['description'] ?? ''; // rich text
$image = is_array($config['image'] ?? null) ? $config['image'] : [];
$linkUrl = esc($data['link_url'] ?? '');
$linkLabel = esc($data['link_label'] ?? '');
?>

<div class="timeline-item flex items-start md:items-center relative w-full group">
    <!-- Timeline Dot -->
    <div class="timeline-dot-wrapper absolute -left-5 md:left-1/2 md:-translate-x-1/2 flex items-center justify-center w-8 h-8 rounded-full bg-white border-4 border-violet-500 shadow-sm group-hover:scale-110 transition-transform duration-300 z-10">
        <span class="w-2.5 h-2.5 rounded-full bg-violet-600"></span>
    </div>
    
    <!-- Left Column (Date on desktop alternating) -->
    <div class="timeline-left-col hidden md:block w-1/2 pr-12 text-right">
        <span class="text-3xl font-extrabold text-violet-600 tracking-tight group-hover:text-violet-500 transition-colors duration-300">
            <?= $dateLabel ?>
        </span>
    </div>
    
    <!-- Right Column (Card) -->
    <div class="timeline-right-col w-full md:w-1/2 pl-12 md:pl-8">
        <div class="bg-white border border-slate-200/80 p-6 md:p-8 rounded-3xl shadow-sm hover:shadow-md transition-all duration-300 relative overflow-hidden group-hover:border-slate-300">
            <span class="timeline-date-mobile inline-block md:hidden text-sm font-bold text-violet-600 mb-2">
                <?= $dateLabel ?>
            </span>
            <!-- Date header for left-aligned desktop -->
            <span class="timeline-date-desktop-left hidden text-sm font-bold text-violet-600 mb-2">
                <?= $dateLabel ?>
            </span>
            
            <h3 class="text-xl font-bold text-slate-800 tracking-tight mb-3">
                <?= $title ?>
            </h3>
            
            <div class="prose prose-slate prose-sm max-w-none text-slate-600">
                <?= $description ?>
            </div>
            
            <?php if (! empty($image['url'])): ?>
                <div class="mt-4 overflow-hidden rounded-2xl border border-slate-100">
                    <?= view('components/responsive-image', [
                        'src'      => $image['url'],
                        'alt'      => $title,
                        'class'    => 'w-full h-auto object-cover max-h-60 hover:scale-[1.02] transition-transform duration-500',
                        'variants' => $image['variants'] ?? null,
                        'preferredVariant' => 'md',
                        'sizes'    => '(max-width: 767px) calc(100vw - 4rem), (max-width: 1023px) calc(50vw - 2rem), 480px',
                        'maxVariantWidth' => 800,
                    ], ['saveData' => false]) ?>
                </div>
            <?php endif; ?>
            
            <?php if ($linkUrl !== ''): ?>
                <div class="mt-5">
                    <a href="<?= $linkUrl ?>" class="inline-flex items-center gap-1.5 text-sm font-semibold text-violet-600 hover:text-violet-700 transition-colors group/btn">
                        <span><?= $linkLabel !== '' ? $linkLabel : 'Saber más' ?></span>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-3.5 h-3.5 group-hover/btn:translate-x-0.5 transition-transform">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                        </svg>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
