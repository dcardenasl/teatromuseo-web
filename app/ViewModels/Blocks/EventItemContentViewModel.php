<?php

declare(strict_types=1);

namespace App\ViewModels\Blocks;

class EventItemContentViewModel extends AbstractBlockViewModel
{
    public function vars(): array
    {
        $event = $this->context['event_item'] ?? null;
        if (!is_array($event)) {
            return [
                'hasEvent' => false,
                'fallbackTitle' => $this->dataString('fallback_title', 'Contenido de Evento'),
            ];
        }

        $content = $event['localized']['description'] ?? $event['description'] ?? $event['content'] ?? '';

        return [
            'hasEvent' => true,
            'content' => $content,
        ];
    }
}
