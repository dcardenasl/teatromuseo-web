<?php
/** @var array<string, mixed> $block */
/** @var array<string, mixed> $config */
/** @var array<string, mixed> $data */

$cards = [];
foreach ($block['children'] ?? [] as $child) {
    if (($child['block_key'] ?? '') !== 'card_item') {
        continue;
    }
    $childData = $child['block_data'] ?? [];
    $image = is_array($childData['image'] ?? null) ? $childData['image'] : [];
    
    $cards[] = [
        'image'      => [
            'source_kind' => (string) ($image['source_kind'] ?? 'external_url'),
            'file_id'     => is_numeric($image['file_id'] ?? null) ? (int) $image['file_id'] : null,
            'url'         => (string) ($image['url'] ?? ''),
        ],
        'title'      => (string) ($childData['title'] ?? ''),
        'description'=> (string) ($childData['description'] ?? ''),
        'link_url'   => (string) ($childData['link_url'] ?? ''),
        'link_label' => (string) ($childData['link_label'] ?? ''),
    ];
}

if ($cards === []) {
    return;
}

$columnsDesktop = (string) ($config['columns_desktop'] ?? '3');
$gridColsClass = 'md:grid-cols-3';
if ($columnsDesktop === '2') {
    $gridColsClass = 'md:grid-cols-2';
} elseif ($columnsDesktop === '4') {
    $gridColsClass = 'md:grid-cols-2 lg:grid-cols-4';
}

$variant = (string) ($config['variant'] ?? 'bordered');
$cssClass = trim((string) ($config['css_class'] ?? ''));

// Map variants to CSS classes
$cardBaseClass = 'flex flex-col h-full rounded-2xl p-6 transition-all duration-300 ';
if ($variant === 'bordered') {
    $cardVariantClass = 'bg-white border border-slate-200 shadow-sm hover:shadow-md hover:border-violet-300 hover:-translate-y-1';
} elseif ($variant === 'flat') {
    $cardVariantClass = 'bg-slate-50 border border-transparent hover:bg-slate-100/80 hover:-translate-y-1';
} else { // minimal
    $cardVariantClass = 'bg-transparent border border-transparent hover:border-slate-100 hover:bg-slate-50/50';
}
?>

<section class="section <?= esc($cssClass) ?>">
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 <?= esc($gridColsClass) ?>">
        <?php foreach ($cards as $card): ?>
        <div class="<?= esc($cardBaseClass . $cardVariantClass) ?>">
                <?php if (($card['image']['url'] ?? '') !== ''): ?>
                    <div class="mb-4 flex items-center justify-start h-12 w-12 rounded-xl bg-violet-50 text-violet-600 overflow-hidden p-2.5">
                        <?= view('components/responsive-image', [
                            'src'      => $card['image']['url'],
                            'alt'      => $card['title'],
                            'class'    => 'h-full w-full object-contain',
                            'variants' => $card['image']['variants'] ?? null,
                        ], ['saveData' => false]) ?>
                    </div>
                <?php endif; ?>
                
                <h3 class="text-lg md:text-xl font-bold text-slate-900 mb-2 leading-tight">
                    <?= esc($card['title']) ?>
                </h3>
                
                <?php if ($card['description'] !== ''): ?>
                    <p class="text-slate-600 text-sm md:text-base leading-relaxed mb-4 flex-grow">
                        <?= esc($card['description']) ?>
                    </p>
                <?php endif; ?>
                
                <?php if ($card['link_url'] !== ''): ?>
                    <div class="mt-auto">
                        <a 
                            href="<?= esc($card['link_url']) ?>" 
                            class="inline-flex items-center text-sm font-semibold text-violet-600 hover:text-violet-700 transition-colors duration-150 group"
                        >
                            <span><?= esc($card['link_label'] !== '' ? $card['link_label'] : 'Saber más') ?></span>
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-right ml-1 transform transition-transform group-hover:translate-x-1"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</section>
