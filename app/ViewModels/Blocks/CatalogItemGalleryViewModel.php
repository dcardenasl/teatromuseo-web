<?php

declare(strict_types=1);

namespace App\ViewModels\Blocks;

class CatalogItemGalleryViewModel extends AbstractBlockViewModel
{
    public function vars(): array
    {
        $item = $this->context['catalog_item'] ?? null;
        if (!is_array($item)) {
            return [
                'hasItem' => false,
                'fallbackTitle' => $this->dataString('fallback_title', 'Galería de Obra'),
                'gallery' => [],
            ];
        }

        $gallery = $item['gallery_images'] ?? $item['gallery'] ?? $item['images'] ?? [];
        if (empty($gallery)) {
            $gallery = $this->configArray('fallback_gallery_images', []);
        }

        return [
            'hasItem' => true,
            'gallery' => $gallery,
        ];
    }
}
