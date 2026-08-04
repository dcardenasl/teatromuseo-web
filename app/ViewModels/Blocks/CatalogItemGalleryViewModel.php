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
                'fallbackTitle' => $this->dataString('fallback_title', lang('Site.catalog_gallery_preview_title')),
                'gallery' => [],
            ];
        }

        // No fallback to configArray('fallback_gallery_images', []) here on purpose: that
        // config key holds admin-authored placeholder/demo images meant only for the
        // block-editor preview (the !hasItem branch above), not for a real catalog item that
        // simply has no gallery of its own — showing generic stock photos as if they belonged
        // to a specific piece was misleading. The section only renders when real images exist,
        // via the view's `!empty($gallery)` guard.
        $gallery = $item['gallery_images'] ?? $item['gallery'] ?? $item['images'] ?? [];

        return [
            'hasItem' => true,
            'gallery' => $gallery,
        ];
    }
}
