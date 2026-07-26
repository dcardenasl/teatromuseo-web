<?php
/** @var string $title */
/** @var string $description */
/** @var string $buttonLabel */
/** @var string $documentUrl */
/** @var string $docType */
/** @var string $ext */
/** @var bool $openInNewTab */
/** @var string $cssClass */

if ($documentUrl === '') {
    return;
}

// Map document types to design colors & SVG icons.
$typeConfigs = [
    'pdf' => [
        'bg' => 'bg-red-50 border-red-100',
        'iconColor' => 'text-red-600',
        'badge' => 'bg-red-100/80 text-red-800 border-red-200',
        'svg' => '<svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m.75 12l3 3m0 0l3-3m-3 3v-6m-1.5-9H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>',
    ],
    'word' => [
        'bg' => 'bg-blue-50 border-blue-100',
        'iconColor' => 'text-blue-600',
        'badge' => 'bg-blue-100/80 text-blue-800 border-blue-200',
        'svg' => '<svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9zM9 13h6m-6 3h6m-6-6h3"/></svg>',
    ],
    'excel' => [
        'bg' => 'bg-emerald-50 border-emerald-100',
        'iconColor' => 'text-emerald-600',
        'badge' => 'bg-emerald-100/80 text-emerald-800 border-emerald-200',
        'svg' => '<svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z"/></svg>',
    ],
    'powerpoint' => [
        'bg' => 'bg-orange-50 border-orange-100',
        'iconColor' => 'text-orange-600',
        'badge' => 'bg-orange-100/80 text-orange-800 border-orange-200',
        'svg' => '<svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0v3.75m0-3.75a1.125 1.125 0 01-1.125-1.125V13.5m7.5 3v3.75m0-3.75a1.125 1.125 0 001.125-1.125V13.5m-12 0h12"/></svg>',
    ],
    'archive' => [
        'bg' => 'bg-purple-50 border-purple-100',
        'iconColor' => 'text-purple-600',
        'badge' => 'bg-purple-100/80 text-purple-800 border-purple-200',
        'svg' => '<svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"/></svg>',
    ],
    'generic' => [
        'bg' => 'bg-slate-50 border-slate-100',
        'iconColor' => 'text-slate-600',
        'badge' => 'bg-slate-100/80 text-slate-800 border-slate-200',
        'svg' => '<svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>',
    ],
];

$config = $typeConfigs[$docType] ?? $typeConfigs['generic'];
$shellClass = trim($cssClass);
?>

<section class="section-sm <?= esc($shellClass) ?>">
    <div class="container-base">
        <article class="surface-card overflow-hidden">
            <div class="bg-gradient-to-br from-white via-white to-slate-50/70 p-5 sm:p-6 lg:p-7">
                <div class="flex flex-col gap-8 lg:flex-row lg:items-center lg:justify-between lg:gap-12">
                    <div class="flex min-w-0 flex-1 items-start gap-4 sm:gap-5 lg:gap-6">
                        <div class="shrink-0 rounded-2xl border p-3 <?= $config['bg'] ?> <?= $config['iconColor'] ?>">
                            <?= $config['svg'] ?>
                        </div>

                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.2em] <?= $config['badge'] ?>">
                                    <?= esc($ext !== '' ? $ext : 'DOC') ?>
                                </span>
                                <span class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
                                    <?= esc('Descarga') ?>
                                </span>
                            </div>

                            <h3 class="mt-3 text-2xl font-semibold tracking-tight text-slate-900 sm:text-[1.6rem] lg:text-3xl">
                                <?= esc($title) ?>
                            </h3>

                            <?php if ($description !== ''): ?>
                                <p class="section-copy mt-3 max-w-3xl text-sm sm:text-base lg:text-base">
                                    <?= esc($description) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="shrink-0 w-full sm:w-auto lg:flex-shrink-0">
                        <a
                            href="<?= esc($documentUrl) ?>"
                            <?= $openInNewTab ? 'target="_blank" rel="noopener noreferrer"' : '' ?>
                            class="btn btn-primary inline-flex w-full items-center justify-center gap-2 rounded-xl px-5 py-3 text-sm font-semibold shadow-sm sm:w-auto lg:px-10 lg:py-5 lg:text-base"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4 lg:h-5 lg:w-5 flex-shrink-0">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                            </svg>
                            <span><?= esc($buttonLabel ?: 'Descargar') ?></span>
                        </a>
                    </div>
                </div>
            </div>
        </article>
    </div>
</section>
