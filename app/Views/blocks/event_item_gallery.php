<?php
/**
 * Bloque: Galería Dinámica de Evento
 * Usa EventItemGalleryViewModel
 */

if (!($hasEvent ?? false)):
    $fallbackTitle = $fallbackTitle ?? lang('Site.event_gallery_preview_title');
?>
<div class="p-8 my-8 bg-slate-50 text-center border border-dashed border-slate-300">
    <h3 class="text-xl font-bold text-slate-500"><?= esc($fallbackTitle) ?> (<?= esc(lang('Site.preview_label')) ?>)</h3>
    <p class="text-slate-500">Este bloque mostrará las imágenes secundarias del evento.</p>
</div>
<?php else:
    if (!empty($gallery)):
        $renderedChildren = '';
        foreach ($gallery as $image) {
            $imageReference = is_array($image) ? $image : ['url' => is_string($image) ? $image : ''];
            $url = (string) ($imageReference['url'] ?? '');
            if ($url === '') continue;
            
            $renderedChildren .= view('blocks/gallery_item', [
                'config' => ['image' => $imageReference],
                'data'   => ['alt' => '']
            ], ['saveData' => false]);
        }
?>
<section class="section py-0 mb-12">
    <div class="container-base">
        <h2 class="text-2xl font-bold text-slate-900 mb-2"><?= esc(lang('Site.event_gallery_title')) ?></h2>
        <?= view('blocks/gallery', [
            'config' => ['presentation_mode' => 'inline_preview'],
            'renderedChildren' => $renderedChildren
        ], ['saveData' => false]) ?>
    </div>
</section>
<?php 
    endif;
endif; ?>
