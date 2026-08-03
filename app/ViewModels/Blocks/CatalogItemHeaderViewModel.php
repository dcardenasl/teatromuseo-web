<?php

declare(strict_types=1);

namespace App\ViewModels\Blocks;

class CatalogItemHeaderViewModel extends AbstractBlockViewModel
{
    public function vars(): array
    {
        $item = $this->context['catalog_item'] ?? null;
        if (!is_array($item)) {
            return [
                'hasItem' => false,
                'fallbackTitle' => $this->dataString('fallback_title', 'Cabecera de Obra'),
            ];
        }

        $title = $item['name'] ?? 'Obra sin título';
        $summary = $item['summary'] ?? '';
        $categoryName = trim((string) ($this->context['category_name'] ?? ''));

        // No fallback to configString('fallback_image_url', '') here on purpose: that config
        // key holds an admin-authored placeholder meant only for the block-editor preview (the
        // !hasItem branch above), not for a real item that simply has no cover of its own —
        // showing a generic stock photo as if it belonged to a specific piece was misleading.
        // The view already hides the <figure> entirely when imageUrl is empty.
        $image = $item['cover_image'] ?? $item['featured_image'] ?? null;
        $imageUrl = is_array($image) ? ($image['url'] ?? '') : (is_string($image) ? $image : '');

        return [
            'hasItem' => true,
            'title' => $title,
            'summary' => $summary,
            'categoryName' => $categoryName,
            'imageUrl' => $imageUrl,
            'homeLabel' => lang('Site.breadcrumb_home') ?: 'Inicio',
            'breadcrumbUrl' => lang_url(\App\Support\PublicPaths::catalogSegment($this->lang), $this->lang),
            'breadcrumbLabel' => 'Colección del Museo',
        ];
    }
}
