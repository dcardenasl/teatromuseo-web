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
                'fallbackTitle' => $this->dataString('fallback_title', lang('Site.catalog_header_preview_title')),
            ];
        }

        $localized = is_array($item['localized'] ?? null) ? $item['localized'] : [];
        $title = $localized['name'] ?? $item['name'] ?? lang('Site.catalog_untitled_item');
        $summary = $localized['summary'] ?? $item['summary'] ?? '';
        $categoryName = trim((string) ($this->context['category_name'] ?? ''));

        // No fallback to configString('fallback_image_url', '') here on purpose: that config
        // key holds an admin-authored placeholder meant only for the block-editor preview (the
        // !hasItem branch above), not for a real item that simply has no cover of its own —
        // showing a generic stock photo as if it belonged to a specific piece was misleading.
        // The view already hides the <figure> entirely when imageUrl is empty.
        $image = $item['cover_image'] ?? $item['featured_image'] ?? null;
        $imageReference = $this->normalizeMediaReference($image);
        $imageUrl = $imageReference['url'];

        return [
            'hasItem' => true,
            'title' => $title,
            'summary' => $summary,
            'categoryName' => $categoryName,
            'image' => $imageReference,
            'imageUrl' => $imageUrl,
            'homeLabel' => lang('Site.breadcrumb_home'),
            'breadcrumbUrl' => lang_url(\App\Support\PublicPaths::catalogSegment($this->lang), $this->lang),
            'breadcrumbLabel' => lang('Site.museum_collection_title'),
        ];
    }
}
