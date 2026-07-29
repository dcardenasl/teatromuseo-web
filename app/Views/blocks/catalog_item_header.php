<?php
/**
 * Bloque: Cabecera Dinámica de Catálogo
 * Muestra el título y la imagen principal de la obra extraída del contexto.
 */

$item = $context['catalog_item'] ?? null;
if (!$item):
    // Fallback in case it's previewed without context in the CMS
?>
<div class="p-8 bg-gray-100 text-center border border-dashed border-gray-300">
    <h2 class="text-2xl font-bold text-gray-400">Cabecera de Obra (Previsualización)</h2>
    <p class="text-gray-500">Este bloque mostrará el título y la imagen de la obra del catálogo.</p>
</div>
<?php else: 
    $title = $item['name'] ?? 'Obra sin título';
    $summary = $item['summary'] ?? '';
    $categoryName = trim((string) ($context['category_name'] ?? ''));
    $image = $item['cover_image'] ?? $item['featured_image'] ?? null;
    $imageUrl = is_array($image) ? ($image['url'] ?? '') : (is_string($image) ? $image : '');
?>
<header class="relative w-full h-[50vh] min-h-[400px] flex items-end justify-start bg-gray-900 mb-12">
    <?php if ($imageUrl !== ''): ?>
        <img src="<?= esc($imageUrl) ?>" alt="<?= esc($title) ?>" class="absolute inset-0 w-full h-full object-cover opacity-60 mix-blend-overlay">
    <?php endif; ?>
    
    <div class="relative z-10 p-8 md:p-16 max-w-4xl text-white">
        <?php if ($categoryName !== ''): ?>
            <div class="mb-4">
                <span class="inline-flex items-center rounded-full border border-white/20 bg-white/10 px-4 py-1.5 text-xs font-semibold uppercase tracking-[0.18em] text-white/85">
                    <?= esc($categoryName) ?>
                </span>
            </div>
        <?php endif; ?>
        <h1 class="text-4xl md:text-6xl font-extrabold tracking-tight mb-4"><?= esc($title) ?></h1>
        <?php if ($summary !== ''): ?>
            <p class="text-lg md:text-xl font-medium text-gray-200"><?= esc($summary) ?></p>
        <?php endif; ?>
    </div>
</header>
<?php endif; ?>
