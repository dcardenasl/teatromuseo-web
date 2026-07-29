<?php

declare(strict_types=1);

namespace App\ViewModels\Blocks\Listing;

class ListingQuery
{
    public function __construct(
        public int $page = 1,
        public int $perPage = 12,
        public string $category = '',
        public string $tag = '',
        public string $query = '',
        public string $orderBy = '',
        public string $orderDirection = 'asc',
    ) {
    }
}
