<?php

declare(strict_types=1);

namespace App\ViewModels\Blocks\Listing;

class ListingResult
{
    /**
     * @param list<array<string, mixed>> $data
     * @param array<string, mixed> $pagination
     */
    public function __construct(
        public array $data = [],
        public array $pagination = [],
    ) {
    }
}
