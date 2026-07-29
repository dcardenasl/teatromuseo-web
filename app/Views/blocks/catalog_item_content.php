<?php
/**
 * Bloque: Contenido Dinámico de Obra
 * Usa CatalogItemContentViewModel
 */

if (!($hasItem ?? false)):
    $fallbackTitle = $fallbackTitle ?? 'Contenido de Obra';
?>
<div class="p-8 bg-amber-50 text-center border border-dashed border-amber-300">
    <h2 class="text-2xl font-bold text-amber-500"><?= esc($fallbackTitle) ?> (Previsualización)</h2>
    <p class="text-amber-600">Este bloque mostrará la descripción completa de la pieza.</p>
</div>
<?php else: ?>
    <?php if (($content ?? '') !== ''): ?>
    <section class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="prose prose-lg prose-gray max-w-none prose-headings:font-bold prose-a:text-amber-600 hover:prose-a:text-amber-700">
            <?= $content ?>
        </div>
    </section>
    <?php endif; ?>
<?php endif; ?>
