<?php
/**
 * Public Museum Collection detail scientific sheet view.
 *
 * @var string $title
 * @var array $item
 * @var string $categoryName
 * @var string $lang
 */
$coverUrl = $item['cover_image']['url'] ?? '';
$coverVariants = $item['cover_image']['variants'] ?? null;
$gallery = $item['gallery_images'] ?? [];
$techniques = $item['techniques'] ?? [];
?>
<main class="min-h-screen bg-slate-900 text-slate-100 font-sans pb-20">
    <!-- Breadcrumb Header -->
    <div class="bg-slate-950 py-6 border-b border-slate-800/80">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <nav class="flex text-sm text-slate-500 font-medium">
                <a href="<?= lang_url('museo/coleccion') ?>" class="hover:text-sky-400 transition-colors">Colección de Museo</a>
                <span class="mx-2 text-slate-700">/</span>
                <span class="text-slate-300 line-clamp-1"><?= esc($item['name'] ?? '') ?></span>
            </nav>
        </div>
    </div>

    <!-- Main Container -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-12">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-12">
            <!-- Left Column: Visual Assets & Physical Metadata -->
            <div class="lg:col-span-5 space-y-8">
                <!-- Cover Image Card -->
                <div class="bg-slate-950 border border-slate-800 p-4 rounded-3xl shadow-xl">
                    <div class="relative rounded-2xl overflow-hidden aspect-[4/3] bg-slate-900 shadow-inner">
                        <?php if ($coverUrl !== ''): ?>
                            <?= view('components/responsive-image', [
                                'src' => $coverUrl,
                                'alt' => $item['name'] ?? '',
                                'class' => 'w-full h-full object-cover',
                                'variants' => $coverVariants
                            ], ['saveData' => false]) ?>
                        <?php else: ?>
                            <div class="w-full h-full flex items-center justify-center bg-slate-900 text-slate-700">
                                <svg class="h-20 w-20 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.2" d="M8.25 21v-4.875c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125V21m0 0h4.5V3.545M12.75 21h7.5V10.75M2.25 21h1.5m18 0h-18M2.25 9l4.5-1.636M18.75 3l-1.5.545m0 6.205l3 1M5.25 10.75a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" />
                                </svg>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Gallery Carousel / Grid -->
                    <?php if (!empty($gallery)): ?>
                        <div class="grid grid-cols-4 gap-2 mt-4">
                            <?php foreach ($gallery as $img): ?>
                                <div class="aspect-square rounded-lg overflow-hidden bg-slate-900 border border-slate-800 hover:border-sky-500/50 transition-colors cursor-pointer">
                                    <img src="<?= esc($img['url']) ?>" alt="Galería" class="w-full h-full object-cover hover:scale-105 transition-transform duration-300">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Technical Details Table -->
                <div class="bg-slate-950/40 backdrop-blur-md border border-slate-800/80 rounded-3xl p-6 shadow-xl space-y-6">
                    <h3 class="text-lg font-bold text-slate-200 border-b border-slate-800 pb-3 tracking-wide">Ficha Física</h3>
                    <dl class="space-y-4 text-sm">
                        <?php if (!empty($item['dimensions'])): ?>
                            <div class="flex justify-between py-1 border-b border-slate-900">
                                <dt class="text-slate-400 font-medium">Dimensiones</dt>
                                <dd class="text-slate-200 text-right"><?= esc($item['dimensions']) ?></dd>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($item['materials'])): ?>
                            <div class="flex justify-between py-1 border-b border-slate-900">
                                <dt class="text-slate-400 font-medium">Materiales</dt>
                                <dd class="text-slate-200 text-right"><?= esc($item['materials']) ?></dd>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($item['physical_description'])): ?>
                            <div class="py-1">
                                <dt class="text-slate-400 font-medium mb-1">Descripción Física</dt>
                                <dd class="text-slate-300 bg-slate-900/50 p-3 rounded-xl border border-slate-800/40 leading-relaxed"><?= esc($item['physical_description']) ?></dd>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($item['ubicacion'])): ?>
                            <div class="flex justify-between py-1 border-b border-slate-900">
                                <dt class="text-slate-400 font-medium">Ubicación Actual</dt>
                                <dd class="text-slate-200 text-right"><?= esc($item['ubicacion']) ?></dd>
                            </div>
                        <?php endif; ?>
                    </dl>
                </div>
            </div>

            <!-- Right Column: Curatorial/Scientific Sheet Details -->
            <div class="lg:col-span-7 space-y-8">
                <!-- Header Info -->
                <div class="bg-slate-950 border border-slate-800 p-8 rounded-3xl shadow-xl relative">
                    <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
                        <?php if (!empty($categoryName)): ?>
                            <span class="bg-sky-500/10 text-sky-400 border border-sky-500/20 text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wider">
                                <?= esc($categoryName) ?>
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($item['inventory_code'])): ?>
                            <span class="text-xs font-mono text-slate-500 uppercase tracking-widest bg-slate-900 px-3 py-1 rounded border border-slate-800">
                                Registro: <?= esc($item['inventory_code']) ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <h1 class="text-3xl sm:text-4xl font-extrabold text-slate-100 tracking-tight leading-tight">
                        <?= esc($item['name'] ?? '') ?>
                    </h1>

                    <div class="grid grid-cols-2 gap-6 mt-6 pt-6 border-t border-slate-800/80">
                        <?php if (!empty($item['creator'])): ?>
                            <div>
                                <h4 class="text-xs uppercase tracking-wider text-slate-500 font-semibold">Creador / Autor</h4>
                                <p class="text-sm font-bold text-slate-200 mt-1"><?= esc($item['creator']) ?></p>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($item['period'])): ?>
                            <div>
                                <h4 class="text-xs uppercase tracking-wider text-slate-500 font-semibold">Época / Período</h4>
                                <p class="text-sm font-bold text-slate-200 mt-1"><?= esc($item['period']) ?></p>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($item['origin'])): ?>
                            <div>
                                <h4 class="text-xs uppercase tracking-wider text-slate-500 font-semibold">Origen Geográfico</h4>
                                <p class="text-sm font-bold text-slate-200 mt-1"><?= esc($item['origin']) ?></p>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($item['collection_number'])): ?>
                            <div>
                                <h4 class="text-xs uppercase tracking-wider text-slate-500 font-semibold">Número de Colección</h4>
                                <p class="text-sm font-bold text-slate-200 mt-1"><?= esc($item['collection_number']) ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Text Blocks / Scientific Content -->
                <div class="space-y-6">
                    <!-- Summary -->
                    <?php if (!empty($item['summary'])): ?>
                        <div class="bg-slate-950/40 backdrop-blur-md border border-slate-800/80 p-8 rounded-3xl shadow-xl">
                            <h3 class="text-lg font-bold text-slate-200 mb-3 tracking-wide">Resumen Curatorial</h3>
                            <p class="text-slate-300 leading-relaxed text-base font-light">
                                <?= nl2br(esc($item['summary'])) ?>
                            </p>
                        </div>
                    <?php endif; ?>

                    <!-- Curiosity (Highlighted card with distinctive accent) -->
                    <?php if (!empty($item['curiosidad'])): ?>
                        <div class="relative overflow-hidden bg-gradient-to-r from-amber-500/10 to-amber-600/5 border border-amber-500/20 p-8 rounded-3xl shadow-xl">
                            <div class="absolute top-0 right-0 p-4 opacity-10">
                                <svg class="h-24 w-24 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.813 15.904L9 21l8.904-4.437M21 11.25c0-4.142-3.358-7.5-7.5-7.5S6 7.108 6 11.25c0 1.942.738 3.712 1.95 5.048L9 21M13.5 13.5h.008v.008H13.5v-.008z" />
                                </svg>
                            </div>
                            <h3 class="text-lg font-bold text-amber-400 mb-3 tracking-wide flex items-center gap-2">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                ¿Sabías qué?
                            </h3>
                            <p class="text-amber-200/90 leading-relaxed font-light">
                                <?= esc($item['curiosidad']) ?>
                            </p>
                        </div>
                    <?php endif; ?>

                    <!-- History / Background -->
                    <?php if (!empty($item['company_history'])): ?>
                        <div class="bg-slate-950/40 backdrop-blur-md border border-slate-800/80 p-8 rounded-3xl shadow-xl">
                            <h3 class="text-lg font-bold text-slate-200 mb-3 tracking-wide">Historia y Contexto</h3>
                            <p class="text-slate-300 leading-relaxed font-light">
                                <?= nl2br(esc($item['company_history'])) ?>
                            </p>
                        </div>
                    <?php endif; ?>

                    <!-- Associated Techniques -->
                    <?php if (!empty($techniques)): ?>
                        <div class="bg-slate-950/40 backdrop-blur-md border border-slate-800/80 p-8 rounded-3xl shadow-xl">
                            <h3 class="text-lg font-bold text-slate-200 mb-4 tracking-wide">Técnicas Relacionadas</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <?php foreach ($techniques as $tech): ?>
                                    <div class="bg-slate-900 border border-slate-800 p-4 rounded-2xl flex flex-col justify-between hover:border-sky-500/30 transition-colors">
                                        <div>
                                            <h4 class="font-bold text-slate-200 text-sm"><?= esc($tech['name'] ?? '') ?></h4>
                                            <?php if (!empty($tech['summary'])): ?>
                                                <p class="text-xs text-slate-400 mt-2 line-clamp-2 leading-relaxed">
                                                    <?= esc($tech['summary']) ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($tech['video_url'])): ?>
                                            <a href="<?= esc($tech['video_url']) ?>" target="_blank" class="inline-flex items-center text-xs font-bold text-sky-400 mt-3 hover:underline gap-1">
                                                Ver Demostración
                                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                                </svg>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Detailed Content (Longtext) -->
                    <?php if (!empty($item['contenido'])): ?>
                        <div class="bg-slate-950/40 backdrop-blur-md border border-slate-800/80 p-8 rounded-3xl shadow-xl">
                            <h3 class="text-lg font-bold text-slate-200 mb-3 tracking-wide">Estudio Científico Detallado</h3>
                            <div class="text-slate-300 leading-relaxed font-light space-y-4">
                                <?= nl2br(esc($item['contenido'])) ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>
