<?php

declare(strict_types=1);

namespace App\ViewModels\Blocks\Listing;

interface ListingSourceInterface
{
    public function fetch(ListingQuery $query, string $lang): ListingResult;

    /**
     * @return array{categories?: list<array<string, mixed>>, tags?: list<array<string, mixed>>}
     */
    public function facets(ListingQuery $query, string $lang): array;

    /**
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    public function normalizeEntry(array $entry): array;

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array;

    /**
     * @return ListingResult
     */
    public function previewResult(): ListingResult;
}
