<?php

declare(strict_types=1);

namespace App\ViewModels\Blocks;

class CatalogItemContentViewModel extends AbstractBlockViewModel
{
    public function vars(): array
    {
        $item = $this->context['catalog_item'] ?? null;
        if (!is_array($item)) {
            return [
                'hasItem' => false,
                'fallbackTitle' => $this->dataString('fallback_title', lang('Site.catalog_content_preview_title')),
            ];
        }

        $content = $item['localized']['contenido'] ?? $item['contenido'] ?? '';

        return [
            'hasItem' => true,
            'content' => $content,
        ];
    }
}
