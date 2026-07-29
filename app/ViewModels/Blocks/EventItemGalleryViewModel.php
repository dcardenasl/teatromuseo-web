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

        $gallery = $event['gallery_images'] ?? $event['gallery'] ?? $event['images'] ?? [];
        if (empty($gallery)) {
            $gallery = $this->configArray('fallback_gallery_images', []);
        }

        return [
            'hasEvent' => true,
            'gallery' => $gallery,
        ];
    }
}
