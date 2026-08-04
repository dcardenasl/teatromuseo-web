<?php
/**
 * Bloque: Galería Dinámica de Colección
 * Usa CatalogItemGalleryViewModel
 */

if (!($hasItem ?? false)):
    $fallbackTitle = $fallbackTitle ?? lang('Site.catalog_gallery_preview_title');
?>
<div class="p-8 my-8 bg-slate-50 text-center border border-dashed border-slate-300">
    <h3 class="text-xl font-bold text-slate-500"><?= esc($fallbackTitle) ?> (<?= esc(lang('Site.preview_label')) ?>)</h3>
    <p class="text-slate-500"><?= esc(lang('Site.catalog_gallery_preview_description')) ?></p>
</div>
<?php else:
    if (!empty($gallery)):
        $renderedChildren = '';
        foreach ($gallery as $image) {
            $url = is_array($image) ? ($image['url'] ?? '') : (is_string($image) ? $image : '');
            if ($url === '') continue;

            $renderedChildren .= view('blocks/gallery_item', [
                'config' => ['image' => ['url' => $url]],
                'data'   => ['alt' => '']
            ], ['saveData' => false]);
        }
?>
<section class="section py-0 mb-12">
    <div class="container-base">
        <h2 class="text-2xl font-bold text-slate-900 mb-2"><?= esc(lang('Site.catalog_gallery_title')) ?></h2>
        <?= view('blocks/gallery', [
            'config' => ['presentation_mode' => 'inline_preview'],
            'renderedChildren' => $renderedChildren
        ], ['saveData' => false]) ?>
    </div>
</section>
<?php 
    endif;
endif; ?>
