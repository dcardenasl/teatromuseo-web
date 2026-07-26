<?php
/**
 * accordion block — variables prepared by AccordionViewModel.
 *
 * @var list<array{title: string, content: string, is_open: bool}> $items List of accordion items
 * @var string $cssClass Additional CSS classes
 */

if ($items === []) {
    return;
}
?>

<section class="section <?= esc($cssClass) ?>">
    <div class="max-w-4xl mx-auto space-y-4">
        <?php foreach ($items as $item): ?>
            <details 
                class="group border border-slate-200/80 rounded-xl bg-white shadow-sm transition-all duration-300 hover:border-primary/50 hover:shadow-md group-open:border-primary/50 group-open:shadow-md overflow-hidden"
                <?= $item['is_open'] ? 'open' : '' ?>
            >
                <summary class="flex justify-between items-center font-semibold text-slate-800 p-5 cursor-pointer select-none hover:text-primary transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/50">
                    <span class="text-base md:text-lg pr-4"><?= esc($item['title']) ?></span>
                    <span class="transition-transform duration-300 transform group-open:rotate-180 text-slate-400 group-hover:text-primary group-open:text-primary flex-shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-down"><path d="m6 9 6 6 6-6"/></svg>
                    </span>
                </summary>
                <div class="px-5 pb-5 pt-1 text-slate-600 leading-relaxed border-t border-slate-100 bg-slate-50/30">
                    <div class="prose prose-slate max-w-none prose-sm md:prose-base">
                        <?= $item['content'] ?>
                    </div>
                </div>
            </details>
        <?php endforeach; ?>
    </div>
</section>
