<?php

declare(strict_types=1);

namespace App\ViewModels\Blocks\Listing;

class ListingQuery
{
    public function __construct(
        public int $page = 1,
        public int $perPage = 12,
        public string $category = '',
        public int $categoryId = 0,
        public string $tag = '',
        public string $query = '',
        public string $orderBy = '',
        public string $orderDirection = 'asc',
        public string $filterBy = '',
        public string $filterValue = '',
        public string $filterOperator = 'equals',
        /**
         * Explicit `fields=` projection to request from the domain
         * (e.g. SiteCatalogService::GRID_FIELDS). Empty means "let the
         * source use its own default" (SiteCatalogService/SiteEventService's
         * LIST_FIELDS, sized for collection_listing's richer card).
         */
        public string $fields = '',
    ) {
    }
}
