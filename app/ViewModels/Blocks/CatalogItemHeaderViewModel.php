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

        $image = $item['cover_image'] ?? $item['featured_image'] ?? null;
        $imageUrl = is_array($image) ? ($image['url'] ?? '') : (is_string($image) ? $image : '');
        if ($imageUrl === '') {
            $imageUrl = $this->configString('fallback_image_url', '');
        }

        return [
            'hasItem' => true,
            'title' => $title,
            'summary' => $summary,
            'categoryName' => $categoryName,
            'imageUrl' => $imageUrl,
            'homeLabel' => lang('Site.breadcrumb_home') ?: 'Inicio',
            'breadcrumbUrl' => lang_url(\App\Support\PublicPaths::CATALOG),
            'breadcrumbLabel' => 'Colección del Museo',
        ];
    }
}
