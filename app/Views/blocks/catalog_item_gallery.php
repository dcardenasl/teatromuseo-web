<?php
/**
 * Bloque: Galería Dinámica de Catálogo
 * Muestra la lista de imágenes (media) asociadas a la obra.
 */

$item = $context['catalog_item'] ?? null;
if (!$item):
?>
<div class="p-8 my-8 bg-gray-100 text-center border border-dashed border-gray-300">
    <h3 class="text-xl font-bold text-gray-400">Galería de Obra (Previsualización)</h3>
    <p class="text-gray-500">Este bloque mostrará las imágenes secundarias de la obra.</p>
</div>
<?php else:
    $gallery = $item['gallery_images'] ?? $item['gallery'] ?? $item['images'] ?? [];
    if (!empty($gallery)):
?>
<section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <h2 class="text-2xl font-bold text-gray-900 mb-6">Galería de Imágenes</h2>
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
        <?php foreach ($gallery as $image): 
            $url = is_array($image) ? ($image['url'] ?? '') : (is_string($image) ? $image : '');
            if ($url === '') continue;
        ?>
        <div class="aspect-w-1 aspect-h-1 w-full overflow-hidden rounded-lg bg-gray-200">
            <img src="<?= esc($url) ?>" alt="" class="h-full w-full object-cover object-center hover:opacity-75 transition-opacity">
        </div>
        <?php endforeach; ?>
    </div>
</section>
<?php 
    endif;
endif; ?>
