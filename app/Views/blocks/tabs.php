<?php
/** @var array<string, mixed> $block */
/** @var array<string, mixed> $config */
/** @var array<string, mixed> $data */

$layout = (string) ($config['layout'] ?? 'horizontal');
$cssClass = trim((string) ($config['css_class'] ?? ''));

$tabs = [];
foreach ($block['children'] ?? [] as $index => $child) {
    if (($child['block_key'] ?? '') !== 'tab_item') {
        continue;
    }
    $childData = $child['block_data'] ?? [];
    $tabs[] = [
        'index' => $index,
        'title' => (string) ($childData['title'] ?? lang('Site.tab_default_label', [$index + 1])),
        'content' => block_text_content($childData, ''),
    ];
}

if ($tabs === []) {
    return;
}
?>

<div 
    x-data="{ activeTab: 0 }" 
    class="max-w-4xl mx-auto my-8 border border-slate-200/80 rounded-2xl bg-white shadow-sm overflow-hidden <?= esc($cssClass) ?>"
>
    <div class="<?= $layout === 'vertical' ? 'md:flex' : '' ?>">
        <!-- Tab Headers -->
        <div class="<?= $layout === 'vertical' ? 'md:w-1/4 border-r border-slate-200 bg-slate-50/50 p-2 space-y-1' : 'border-b border-slate-200 bg-slate-50/50 p-2 flex gap-1 overflow-x-auto scrollbar-none' ?>">
            <?php foreach ($tabs as $idx => $tab): ?>
                <button 
                    @click="activeTab = <?= $idx ?>"
                    :class="activeTab === <?= $idx ?> ? 'bg-primary text-white shadow-sm' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/80'"
                    class="w-full text-left font-medium text-sm px-4 py-2.5 rounded-xl transition-all duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/50 <?= $layout === 'horizontal' ? 'whitespace-nowrap md:w-auto text-center' : '' ?>"
                >
                    <?= esc($tab['title']) ?>
                </button>
            <?php endforeach; ?>
        </div>

        <!-- Tab Contents -->
        <div class="p-6 flex-1 min-w-0">
            <?php foreach ($tabs as $idx => $tab): ?>
                <div 
                    x-show="activeTab === <?= $idx ?>"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 translate-y-1"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    class="space-y-4"
                >
                    <div class="prose prose-slate max-w-none text-slate-600 leading-relaxed">
                        <?= $tab['content'] ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
