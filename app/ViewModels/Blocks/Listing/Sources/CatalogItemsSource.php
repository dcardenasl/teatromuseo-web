<?php

declare(strict_types=1);

namespace App\ViewModels\Blocks\Listing\Sources;

use App\Services\SiteCatalogService;
use App\Support\Slug;
use App\ViewModels\Blocks\Listing\ListingQuery;
use App\ViewModels\Blocks\Listing\ListingResult;
use App\ViewModels\Blocks\Listing\ListingSourceInterface;
use Closure;

class CatalogItemsSource implements ListingSourceInterface
{
    /** @var array<string, array<string, mixed>> */
    private array $categoryLookup = [];

    public function __construct(
        private ?SiteCatalogService $catalogService,
        private Closure $urlBuilder,
        private Closure $mediaNormalizer
    ) {
    }

    public function fetch(ListingQuery $query, string $lang): ListingResult
    {
        if ($this->catalogService === null) {
            return new ListingResult();
        }
        $catalogService = $this->catalogService;

        $apiQuery = [
            'page' => $query->page,
            'per_page' => $query->perPage,
            'sort' => ($query->orderDirection === 'desc' ? '-' : '') . $this->apiSortField($query->orderBy),
        ];

        if ($query->fields !== '') {
            $apiQuery['fields'] = $query->fields;
        }

        if ($query->query !== '') {
            $apiQuery['search'] = $query->query;
        }

        if ($query->category !== '') {
            $this->loadCategoryLookup($lang);
            if (isset($this->categoryLookup[$query->category])) {
                $apiQuery['filter'] = [
                    'is_active' => '1',
                    'category_id' => $this->categoryLookup[$query->category]['id'],
                ];
            } else {
                $apiQuery['filter'] = ['is_active' => '1'];
            }
        } else {
            $apiQuery['filter'] = ['is_active' => '1'];
        }

        try {
            $result = $catalogService->listItems($lang, $apiQuery);
            return new ListingResult($result['data'] ?? [], $result['meta']['pagination'] ?? []);
        } catch (\Throwable) {
            return new ListingResult();
        }
    }

    public function facets(ListingQuery $query, string $lang): array
    {
        if ($this->catalogService === null) {
            return [];
        }

        $categories = [];

        try {
            $categoriesData = $this->catalogService->listCategories($lang);
            foreach ($categoriesData as $category) {
                $slug = trim((string) ($category['slug'] ?? ''), '/');
                if ($slug === '') {
                    continue;
                }

                $categoryQuery = clone $query;
                $categoryQuery->category = $slug;
                $categoryQuery->tag = '';
                $categoryQuery->page = 1;

                $category['url'] = ($this->urlBuilder)($categoryQuery);
                $categories[] = $category;

                $this->categoryLookup[$slug] = $category;
            }
        } catch (\Throwable) {
        }

        return ['categories' => $categories, 'tags' => []];
    }

    public function normalizeEntry(array $entry): array
    {
        $categoryId = (int) ($entry['category_id'] ?? 0);
        $category = null;
        foreach ($this->categoryLookup as $candidate) {
            if ((int) ($candidate['id'] ?? 0) === $categoryId) {
                $category = $candidate;
                break;
            }
        }

        $localized = is_array($entry['localized'] ?? null) ? $entry['localized'] : [];
        $title = (string) ($localized['name'] ?? $entry['name'] ?? $entry['title'] ?? '');
        $entry['title'] = $title;
        $entry['slug'] = trim((string) ($localized['slug'] ?? $entry['slug'] ?? $entry['inventory_code'] ?? ''));
        if ($entry['slug'] === '') {
            $entry['slug'] = Slug::slugify($title);
        }
        if ($entry['slug'] === '') {
            $entry['slug'] = (string) ($entry['id'] ?? '');
        }
        $entry['excerpt'] = (string) ($localized['summary'] ?? $entry['summary'] ?? '');
        $entry['published_at'] = (string) ($entry['created_at'] ?? $entry['updated_at'] ?? '');

        $featuredImage = $entry['cover_image'] ?? $entry['featured_image'] ?? $entry['main_image'] ?? null;
        if (is_array($featuredImage)) {
            $entry['featured_image'] = ($this->mediaNormalizer)($featuredImage);
        } elseif (is_string($featuredImage) && trim($featuredImage) !== '') {
            $entry['featured_image'] = [
                'source_kind' => 'external_url',
                'file_id' => null,
                'url' => trim($featuredImage),
            ];
        } else {
            $entry['featured_image'] = null;
        }

        $entry['categories'] = $category !== null ? [[
            'slug' => (string) ($category['slug'] ?? ''),
            'name' => (string) ((is_array($category['localized'] ?? null) ? $category['localized']['name'] ?? null : null) ?? $category['name'] ?? ''),
        ]] : [];
        $entry['tags'] = [];

        return $entry;
    }

    public function defaults(): array
    {
        return [
            'order_by' => 'name',
            'order_direction' => 'asc',
            'page_title' => lang('Site.museum_collection_title'),
            'intro_text' => lang('Site.museum_collection_intro'),
            'section_label' => lang('Site.museum_collection_section'),
            'item_label' => lang('Site.museum_item_label'),
            'featured_item_label' => lang('Site.museum_featured_item_label'),
            'count_label' => lang('Site.museum_listing_count'),
            'entry_cta_label' => lang('Site.view_sheet'),
            'show_categories' => true,
            'show_tags' => false,
            'show_date' => false,
        ];
    }

    public function previewResult(): ListingResult
    {
        return new ListingResult();
    }

    private function loadCategoryLookup(string $lang): void
    {
        if ($this->catalogService === null) {
            return;
        }

        if ($this->categoryLookup !== []) {
            return;
        }

        try {
            $categoriesData = $this->catalogService->listCategories($lang);
            foreach ($categoriesData as $category) {
                $slug = trim((string) ($category['slug'] ?? ''), '/');
                if ($slug !== '') {
                    $this->categoryLookup[$slug] = $category;
                }
            }
        } catch (\Throwable) {
        }
    }

    private function apiSortField(string $field): string
    {
        return match (trim($field)) {
            'entry.title', 'title', 'name' => 'name',
            'entry.slug', 'slug' => 'slug',
            'entry.inventory_code', 'inventory_code' => 'inventory_code',
            'entry.origin', 'origin' => 'origin',
            'entry.period', 'period' => 'period',
            'entry.creator', 'creator' => 'creator',
            'entry.ubicacion', 'ubicacion' => 'ubicacion',
            'entry.collection_number', 'collection_number' => 'collection_number',
            'entry.collection_group', 'collection_group' => 'collection_group',
            'entry.created_at', 'created_at' => 'created_at',
            'entry.updated_at', 'updated_at' => 'updated_at',
            'entry.excerpt', 'summary' => 'summary',
            default => 'name',
        };
    }
}
