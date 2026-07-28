<?php
/**
 * Public Museum Collection list view.
 *
 * @var string $title
 * @var array $categories
 * @var string $currentCategory
 * @var string $search
 * @var array $data
 * @var int $currentPage
 * @var array $pagination
 * @var string $lang
 */
?>
<main class="min-h-screen bg-slate-900 text-slate-100 font-sans pb-20">
    <!-- Hero Banner with Sleek Dark Aesthetics -->
    <div class="relative overflow-hidden bg-slate-950 py-24 px-4 sm:px-6 lg:px-8 border-b border-slate-800">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_30%_30%,rgba(14,165,233,0.1),transparent)] pointer-events-none"></div>
        <div class="relative max-w-7xl mx-auto text-center">
            <h1 class="text-4xl sm:text-6xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-sky-400 to-indigo-400 tracking-tight mb-4">
                <?= esc($title) ?>
            </h1>
            <p class="max-w-2xl mx-auto text-lg text-slate-400">
                Explora el catálogo científico de piezas curatoriales, títeres, máscaras, vestuarios y objetos históricos del Teatro Museo.
            </p>
        </div>
    </div>

    <!-- Controls (Search and Filters) with Glassmorphic Design -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-12">
        <form method="GET" action="<?= current_url() ?>" class="bg-slate-950/40 backdrop-blur-md border border-slate-800 p-6 rounded-2xl shadow-xl flex flex-col md:flex-row md:items-center justify-between gap-6">
            <!-- Search Bar -->
            <div class="relative flex-1 max-w-md">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </span>
                <input 
                    type="text" 
                    name="search" 
                    value="<?= esc($search) ?>" 
                    placeholder="Buscar pieza..." 
                    class="block w-full pl-10 pr-4 py-2.5 bg-slate-900 border border-slate-800 rounded-xl text-slate-100 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent transition-all"
                />
            </div>

            <!-- Categories Select & Badges -->
            <div class="flex flex-wrap items-center gap-2">
                <a href="<?= lang_url('museo/coleccion') ?><?= $search !== '' ? '?search=' . urlencode($search) : '' ?>" 
                   class="px-4 py-2 rounded-xl text-sm font-semibold transition-all border <?= $currentCategory === '' ? 'bg-sky-500 border-sky-400 text-white shadow-lg shadow-sky-500/20' : 'bg-slate-900 border-slate-800 text-slate-400 hover:text-slate-200 hover:border-slate-700' ?>">
                    Todos
                </a>
                <?php foreach ($categories as $cat): ?>
                    <?php 
                    $catId = (string) ($cat['id'] ?? ''); 
                    $active = ($currentCategory === $catId);
                    $queryStr = '?category=' . urlencode($catId) . ($search !== '' ? '&search=' . urlencode($search) : '');
                    ?>
                    <a href="<?= lang_url('museo/coleccion') . $queryStr ?>" 
                       class="px-4 py-2 rounded-xl text-sm font-semibold transition-all border <?= $active ? 'bg-sky-500 border-sky-400 text-white shadow-lg shadow-sky-500/20' : 'bg-slate-900 border-slate-800 text-slate-400 hover:text-slate-200 hover:border-slate-700' ?>">
                        <?= esc($cat['name'] ?? '') ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </form>

        <!-- Collection Grid -->
        <?php if (empty($data)): ?>
            <div class="text-center py-20 bg-slate-950/20 border border-slate-800/50 rounded-2xl mt-12">
                <svg class="mx-auto h-12 w-12 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                </svg>
                <h3 class="mt-4 text-lg font-semibold text-slate-300">No se encontraron piezas</h3>
                <p class="mt-2 text-sm text-slate-500">Prueba ajustando los filtros o el término de búsqueda.</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8 mt-12">
                <?php foreach ($data as $item): ?>
                    <?php 
                    $itemId = $item['inventory_code'] ?? (string) $item['id'];
                    $detailUrl = lang_url('museo/coleccion/' . $itemId);
                    $imageUrl = $item['cover_image']['url'] ?? '';
                    $variants = $item['cover_image']['variants'] ?? null;
                    ?>
                    <article class="group bg-slate-950 border border-slate-800/80 rounded-2xl overflow-hidden shadow-lg transition-all duration-300 hover:-translate-y-1 hover:border-sky-500/50 hover:shadow-2xl hover:shadow-sky-500/5 flex flex-col">
                        <!-- Card Image -->
                        <div class="relative aspect-[4/3] bg-slate-900 overflow-hidden">
                            <?php if ($imageUrl !== ''): ?>
                                <?= view('components/responsive-image', [
                                    'src' => $imageUrl,
                                    'alt' => $item['name'] ?? '',
                                    'class' => 'w-full h-full object-cover transition-transform duration-500 group-hover:scale-105',
                                    'variants' => $variants
                                ], ['saveData' => false]) ?>
                            <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-slate-900 to-slate-950 text-slate-700">
                                    <svg class="h-16 w-16 opacity-40" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8.25 21v-4.875c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125V21m0 0h4.5V3.545M12.75 21h7.5V10.75M2.25 21h1.5m18 0h-18M2.25 9l4.5-1.636M18.75 3l-1.5.545m0 6.205l3 1M5.25 10.75a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" />
                                    </svg>
                                </div>
                            <?php endif; ?>
                            <!-- Inventory Code Tag -->
                            <?php if (!empty($item['inventory_code'])): ?>
                                <div class="absolute top-4 left-4 bg-slate-950/80 backdrop-blur-md text-sky-400 text-xs font-semibold px-3 py-1.5 rounded-lg border border-slate-700/50">
                                    <?= esc($item['inventory_code']) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Card Body -->
                        <div class="p-6 flex-1 flex flex-col justify-between">
                            <div>
                                <h2 class="text-xl font-bold text-slate-100 tracking-tight leading-snug group-hover:text-sky-400 transition-colors">
                                    <a href="<?= esc($detailUrl) ?>">
                                        <?= esc($item['name'] ?? '') ?>
                                    </a>
                                </h2>
                                <?php if (!empty($item['creator'])): ?>
                                    <p class="text-xs text-slate-500 mt-1 uppercase tracking-widest font-semibold">
                                        Por: <?= esc($item['creator']) ?>
                                    </p>
                                <?php endif; ?>
                                <?php if (!empty($item['summary'])): ?>
                                    <p class="text-sm text-slate-400 mt-4 line-clamp-3 leading-relaxed">
                                        <?= esc($item['summary']) ?>
                                    </p>
                                <?php endif; ?>
                            </div>

                            <div class="mt-6 pt-4 border-t border-slate-800/80 flex items-center justify-between">
                                <span class="text-xs font-medium text-slate-400">
                                    <?= esc($item['origin'] ?? '') ?> <?= !empty($item['period']) ? ' (' . esc($item['period']) . ')' : '' ?>
                                </span>
                                <a href="<?= esc($detailUrl) ?>" class="inline-flex items-center text-xs font-bold text-sky-400 hover:text-sky-300 transition-all gap-1 group-hover:gap-2">
                                    Ver Ficha Científica
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.3" d="M9 5l7 7-7 7" />
                                    </svg>
                                </a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if (isset($pagination['last_page']) && (int)$pagination['last_page'] > 1): ?>
                <div class="flex items-center justify-center gap-2 mt-16">
                    <?php for ($p = 1; $p <= (int)$pagination['last_page']; $p++): ?>
                        <?php 
                        $queryArgs = $_GET;
                        $queryArgs['page'] = $p;
                        $pageUrl = current_url() . '?' . http_build_query($queryArgs);
                        ?>
                        <a href="<?= esc($pageUrl) ?>" 
                           class="w-10 h-10 flex items-center justify-center rounded-xl font-bold transition-all border <?= (int)$currentPage === $p ? 'bg-sky-500 border-sky-400 text-white shadow-lg shadow-sky-500/20' : 'bg-slate-900 border-slate-800 text-slate-400 hover:text-slate-200 hover:border-slate-700' ?>">
                            <?= $p ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</main>
