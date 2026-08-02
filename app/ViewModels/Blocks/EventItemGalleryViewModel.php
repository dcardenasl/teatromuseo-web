<?php

declare(strict_types=1);

namespace App\ViewModels\Blocks;

class EventItemGalleryViewModel extends AbstractBlockViewModel
{
    public function vars(): array
    {
        $event = $this->context['event_item'] ?? null;
        if (!is_array($event)) {
            return [
                'hasEvent' => false,
                'fallbackTitle' => $this->dataString('fallback_title', 'Galería de Evento'),
                'gallery' => [],
            ];
        }

        // No fallback to configArray('fallback_gallery_images', []) here on purpose: that
        // config key holds admin-authored placeholder/demo images meant only for the
        // block-editor preview (the !hasEvent branch above), not for a real event that simply
        // has no gallery of its own — showing generic stock photos as if they belonged to a
        // specific event was misleading. The section only renders when real images exist,
        // via the view's `!empty($gallery)` guard.
        $gallery = $event['gallery_images'] ?? $event['gallery'] ?? $event['images'] ?? [];

        return [
            'hasEvent' => true,
            'gallery' => $gallery,
        ];
    }
}
