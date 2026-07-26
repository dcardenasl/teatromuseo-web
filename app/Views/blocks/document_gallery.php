<?php
/**
 * @var array $block
 * @var string $title
 * @var string $description
 * @var array $documents
 * @var string $layout
 * @var bool $showFileMeta
 * @var bool $openInNewTab
 * @var string $cssClass
 */

$typeConfigs = [
    'pdf' => [
        'bg' => 'bg-red-50 border-red-100',
        'iconColor' => 'text-red-600',
        'badge' => 'bg-red-100/80 text-red-800 border-red-200',
        'svg' => '<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m.75 12l3 3m0 0l3-3m-3 3v-6m-1.5-9H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>',
    ],
    'word' => [
        'bg' => 'bg-blue-50 border-blue-100',
        'iconColor' => 'text-blue-600',
        'badge' => 'bg-blue-100/80 text-blue-800 border-blue-200',
        'svg' => '<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9zM9 13h6m-6 3h6m-6-6h3"/></svg>',
    ],
    'excel' => [
        'bg' => 'bg-emerald-50 border-emerald-100',
        'iconColor' => 'text-emerald-600',
        'badge' => 'bg-emerald-100/80 text-emerald-800 border-emerald-200',
        'svg' => '<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z"/></svg>',
    ],
    'powerpoint' => [
        'bg' => 'bg-orange-50 border-orange-100',
        'iconColor' => 'text-orange-600',
        'badge' => 'bg-orange-100/80 text-orange-800 border-orange-200',
        'svg' => '<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0v3.75m0-3.75a1.125 1.125 0 01-1.125-1.125V13.5m7.5 3v3.75m0-3.75a1.125 1.125 0 001.125-1.125V13.5m-12 0h12"/></svg>',
    ],
    'archive' => [
        'bg' => 'bg-purple-50 border-purple-100',
        'iconColor' => 'text-purple-600',
        'badge' => 'bg-purple-100/80 text-purple-800 border-purple-200',
        'svg' => '<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"/></svg>',
    ],
    'generic' => [
        'bg' => 'bg-slate-50 border-slate-100',
        'iconColor' => 'text-slate-600',
        'badge' => 'bg-slate-100/80 text-slate-800 border-slate-200',
        'svg' => '<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>',
    ],
];
?>

<section class="section <?= esc($cssClass) ?>">
    <div class="max-w-5xl mx-auto px-4">
        <?php if ($title !== ''): ?>
            <div class="max-w-2xl mb-8">
                <h2 class="text-2xl md:text-3xl font-bold text-slate-900 tracking-tight mb-2 bg-gradient-to-r from-slate-900 to-slate-700 bg-clip-text text-transparent">
                    <?= esc($title) ?>
                </h2>
                <?php if ($description !== ''): ?>
                    <p class="text-sm text-slate-500">
                        <?= esc($description) ?>
                    </p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($documents === []): ?>
            <div class="rounded-3xl border border-dashed border-slate-200 bg-slate-50 p-8 text-center text-sm text-slate-400">
                No hay documentos registrados en esta galería.
            </div>
        <?php else: ?>
            <?php if ($layout === 'simple_list'): ?>
                <!-- Simple List Layout -->
                <div class="overflow-hidden border border-slate-200/80 rounded-2xl bg-white shadow-sm">
                    <ul class="divide-y divide-slate-100">
                        <?php foreach ($documents as $doc): 
                            $cfg = $typeConfigs[$doc['docType']] ?? $typeConfigs['generic'];
                            if ($doc['fileUrl'] === '') continue;
                        ?>
                            <li class="p-4 sm:p-5 flex items-center justify-between gap-4 hover:bg-slate-50 transition-colors group">
                                <div class="flex items-center gap-3.5 min-w-0">
                                    <div class="shrink-0 rounded-xl border p-2.5 <?= $cfg['bg'] ?> <?= $cfg['iconColor'] ?>">
                                        <?= $cfg['svg'] ?>
                                    </div>
                                    <div class="min-w-0">
                                        <h4 class="text-sm font-semibold text-slate-800 group-hover:text-violet-600 transition-colors truncate">
                                            <?= esc($doc['title']) ?>
                                        </h4>
                                        <?php if ($doc['description'] !== ''): ?>
                                            <p class="text-xs text-slate-500 truncate mt-0.5"><?= esc($doc['description']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <?php if ($showFileMeta): ?>
                                        <span class="hidden sm:inline-flex items-center rounded-full border px-2 py-0.5 text-[9px] font-bold uppercase tracking-wider <?= $cfg['badge'] ?>">
                                            <?= esc($doc['ext']) ?>
                                        </span>
                                    <?php endif; ?>
                                    <a href="<?= esc($doc['fileUrl']) ?>"
                                       <?= $openInNewTab ? 'target="_blank" rel="noopener noreferrer"' : '' ?>
                                       class="shrink-0 flex items-center justify-center p-2 rounded-xl bg-slate-50 hover:bg-violet-50 text-slate-500 hover:text-violet-600 border border-slate-100 hover:border-violet-100 transition-all">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                        </svg>
                                    </a>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php else: ?>
                <!-- Grid Cards Layout -->
                <div class="grid gap-6 grid-cols-1 md:grid-cols-2">
                    <?php foreach ($documents as $doc): 
                        $cfg = $typeConfigs[$doc['docType']] ?? $typeConfigs['generic'];
                        if ($doc['fileUrl'] === '') continue;
                    ?>
                        <div class="flex items-start gap-4 p-5 rounded-2xl border border-slate-200/80 bg-white shadow-sm hover:shadow-md transition-all duration-300 group">
                            <div class="shrink-0 rounded-2xl border p-3.5 <?= $cfg['bg'] ?> <?= $cfg['iconColor'] ?>">
                                <?= $cfg['svg'] ?>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-1.5 mb-1.5">
                                    <?php if ($showFileMeta): ?>
                                        <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-[8px] font-bold uppercase tracking-wider <?= $cfg['badge'] ?>">
                                            <?= esc($doc['ext']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <h3 class="text-base font-bold text-slate-800 group-hover:text-violet-600 transition-colors line-clamp-1">
                                    <?= esc($doc['title']) ?>
                                </h3>
                                <?php if ($doc['description'] !== ''): ?>
                                    <p class="text-xs text-slate-500 mt-1 line-clamp-2 leading-relaxed">
                                        <?= esc($doc['description']) ?>
                                    </p>
                                <?php endif; ?>
                                <div class="mt-4">
                                    <a href="<?= esc($doc['fileUrl']) ?>"
                                       <?= $openInNewTab ? 'target="_blank" rel="noopener noreferrer"' : '' ?>
                                       class="inline-flex items-center gap-1.5 text-xs font-semibold text-violet-600 hover:text-violet-700 transition-colors">
                                        <span>Descargar archivo</span>
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-3.5 h-3.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                        </svg>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
