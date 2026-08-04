<?php
/**
 * Bloque: Contenido Dinámico de Obra
 * Usa CatalogItemContentViewModel
 */

if (!($hasItem ?? false)):
    $fallbackTitle = $fallbackTitle ?? lang('Site.catalog_content_preview_title');
?>
<div class="p-8 bg-amber-50 text-center border border-dashed border-amber-300">
    <h2 class="text-2xl font-bold text-amber-500"><?= esc($fallbackTitle) ?> (<?= esc(lang('Site.preview_label')) ?>)</h2>
    <p class="text-amber-600"><?= esc(lang('Site.catalog_content_preview_description')) ?></p>
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
