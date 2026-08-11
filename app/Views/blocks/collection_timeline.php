<?php
/** @var string $sectionTitle */
/** @var string $description */
/** @var string $emptyMessage */
/** @var string $documentLabel */
/** @var string $entryLabel */
/** @var string $layout */
/** @var bool $showExcerpt */
/** @var bool $showDocuments */
/** @var bool $showEntryLink */
/** @var bool $openInNewTab */
/** @var string $cssClass */
/** @var list<array<string, mixed>> $entries */
?>
<section class="section-lg <?= esc($cssClass) ?>">
    <div class="max-w-5xl mx-auto px-4">
        <?php if ($sectionTitle !== '' || $description !== ''): ?>
            <header class="text-center max-w-2xl mx-auto mb-14">
                <?php if ($sectionTitle !== ''): ?><h2 class="text-3xl md:text-4xl font-extrabold tracking-tight text-slate-900 mb-4"><?= esc($sectionTitle) ?></h2><?php endif; ?>
                <?php if ($description !== ''): ?><p class="text-lg text-slate-600"><?= esc($description) ?></p><?php endif; ?>
            </header>
        <?php endif; ?>

        <?php if ($entries === []): ?>
            <div class="rounded-3xl border border-dashed border-slate-300 bg-slate-50 p-10 text-center text-slate-500"><?= esc($emptyMessage) ?></div>
        <?php else: ?>
            <div class="relative timeline-container" data-layout="<?= esc($layout) ?>">
                <div class="absolute left-4 md:left-1/2 md:-translate-x-1/2 top-0 bottom-0 w-px bg-gradient-to-b from-violet-500 via-indigo-400 to-slate-200" aria-hidden="true"></div>

                <div class="relative space-y-8 md:space-y-10">
                    <?php foreach ($entries as $entry):
                        $date = trim((string) ($entry['display_date'] ?? ''));
                        $year = trim((string) ($entry['display_year'] ?? '')) ?: '—';
                        $title = trim((string) ($entry['title'] ?? ''));
                        $titleIsYear = $title !== '' && preg_match('/^(?:19|20)\d{2}(?:\s+(?:19|20)\d{2})*$/', $title) === 1;
                        $excerpt = trim((string) ($entry['excerpt'] ?? ''));
                        $documents = is_array($entry['documents'] ?? null) ? $entry['documents'] : [];
                        $entryUrl = trim((string) ($entry['entry_url'] ?? ''));
                    ?>
                        <article class="timeline-item relative flex w-full items-start md:items-center">
                            <div class="timeline-dot-wrapper absolute left-4 top-6 -translate-x-1/2 md:left-1/2 md:-translate-x-1/2 flex h-8 w-8 items-center justify-center rounded-full border-4 border-violet-500 bg-white shadow-sm z-10" aria-hidden="true">
                                <span class="h-2.5 w-2.5 rounded-full bg-violet-600"></span>
                            </div>

                            <div class="timeline-left-col hidden w-1/2 pr-12 text-right md:block md:pt-5">
                                <time class="inline-flex items-center rounded-full border border-violet-200 bg-violet-50 px-4 py-2 text-xl font-extrabold tracking-tight text-violet-700 shadow-sm md:text-2xl" datetime="<?= esc($date) ?>">
                                    <?= esc($year) ?>
                                </time>
                            </div>

                            <div class="timeline-right-col w-full min-w-0 pl-10 md:w-1/2 md:pl-8">
                                <div class="rounded-3xl border border-slate-200/80 bg-white p-5 shadow-sm transition-shadow hover:shadow-lg sm:p-6">
                                    <time class="mb-3 inline-flex items-center rounded-full border border-violet-200 bg-violet-50 px-3 py-1 text-sm font-extrabold tracking-tight text-violet-700 md:hidden" datetime="<?= esc($date) ?>">
                                        <?= esc($year) ?>
                                    </time>
                                    <?php if ($title !== '' && ! $titleIsYear): ?><h3 class="text-xl font-bold tracking-tight text-slate-800"><?= esc($title) ?></h3><?php endif; ?>
                                    <?php if ($showExcerpt && $excerpt !== ''): ?><p class="<?= $title !== '' && ! $titleIsYear ? 'mt-3' : '' ?> text-sm leading-6 text-slate-600"><?= esc($excerpt) ?></p><?php endif; ?>

                                    <?php if ($showDocuments && $documents !== []): ?>
                                        <div class="<?= ($title !== '' && ! $titleIsYear) || $excerpt !== '' ? 'mt-5' : '' ?> space-y-3">
                                            <?php foreach ($documents as $document):
                                                $url = trim((string) ($document['url'] ?? ''));
                                                if ($url === '') continue;
                                                $semesterLabel = trim((string) ($document['semester_label'] ?? '')) ?: $documentLabel;
                                                $extension = trim((string) ($document['extension'] ?? 'PDF')) ?: 'PDF';
                                                $isPdf = strtolower((string) ($document['doc_type'] ?? '')) === 'pdf';
                                                $documentTitle = trim((string) ($document['title'] ?? ''));
                                                $downloadLabel = $isPdf ? 'Descargar PDF' : $documentLabel;
                                            ?>
                                                <div class="flex flex-col items-stretch gap-3 rounded-2xl border border-slate-200 bg-gradient-to-r from-white to-slate-50/70 p-3.5 transition-colors hover:border-violet-200 hover:bg-violet-50/30 lg:flex-row lg:items-center lg:p-4">
                                                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border <?= $isPdf ? 'border-red-100 bg-red-50 text-red-600' : 'border-slate-200 bg-slate-50 text-slate-600' ?>" aria-hidden="true">
                                                        <?php if ($isPdf): ?>
                                                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5A3.375 3.375 0 0 0 10.125 2.25H8.25m.75 12 3 3m0 0 3-3m-3 3v-6m-1.5-9H5.625C5.004 2.25 4.5 2.754 4.5 3.375v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
                                                        <?php else: ?>
                                                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5A3.375 3.375 0 0 0 10.125 2.25H8.25m.75 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
                                                        <?php endif; ?>
                                                    </div>

                                                    <div class="min-w-0 flex-1">
                                                        <div class="flex flex-wrap items-center gap-2">
                                                            <h4 class="break-words text-sm font-bold leading-5 text-slate-800 sm:text-base"><?= esc($semesterLabel) ?></h4>
                                                            <span class="inline-flex items-center rounded-full border <?= $isPdf ? 'border-red-200 bg-red-100/80 text-red-800' : 'border-slate-200 bg-slate-100 text-slate-700' ?> px-2 py-0.5 text-[9px] font-bold uppercase tracking-wider"><?= esc($extension) ?></span>
                                                        </div>
                                                        <?php if ($documentTitle !== '' && $documentTitle !== $semesterLabel && ! preg_match('/^(?:19|20)\d{2}(?:[-–](?:19|20)\d{2})?$/', $documentTitle)): ?>
                                                            <p class="mt-0.5 break-words text-xs leading-5 text-slate-500"><?= esc($documentTitle) ?></p>
                                                        <?php endif; ?>
                                                    </div>

                                                    <a href="<?= esc($url) ?>" <?= $openInNewTab ? 'target="_blank" rel="noopener noreferrer"' : '' ?> aria-label="<?= esc($downloadLabel . ' - ' . $semesterLabel) ?>" class="inline-flex w-full shrink-0 items-center justify-center gap-1.5 rounded-xl bg-violet-600 px-3 py-2 text-center text-xs font-semibold text-white shadow-sm transition-colors hover:bg-violet-700 lg:w-auto lg:px-4 lg:py-2.5 lg:text-sm">
                                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                                                        <span><?= esc($downloadLabel) ?></span>
                                                    </a>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($showEntryLink && $entryUrl !== ''): ?><a href="<?= esc($entryUrl) ?>" class="mt-4 inline-flex text-sm font-semibold text-violet-700 hover:text-violet-900"><?= esc($entryLabel) ?> →</a><?php endif; ?>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<style <?= csp_style_nonce() ?>>
[data-layout="alternating"] .timeline-item:nth-child(even) {
    flex-direction: row-reverse;
}

[data-layout="alternating"] .timeline-item:nth-child(even) .timeline-left-col {
    padding-right: 0;
    padding-left: 3rem;
    text-align: left;
}

[data-layout="alternating"] .timeline-item:nth-child(even) .timeline-right-col {
    padding-left: 0;
    padding-right: 3rem;
}

@media (max-width: 767px) {
    [data-layout="alternating"] .timeline-item,
    [data-layout="alternating"] .timeline-item:nth-child(even) {
        flex-direction: row;
    }
}

[data-layout="left_aligned"] .timeline-left-col {
    display: none !important;
}

[data-layout="left_aligned"] .timeline-right-col {
    width: 100% !important;
    padding-left: 3rem !important;
}
</style>
