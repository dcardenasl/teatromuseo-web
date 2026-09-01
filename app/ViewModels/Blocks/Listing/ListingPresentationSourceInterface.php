<?php

declare(strict_types=1);

namespace App\ViewModels\Blocks\Listing;

interface ListingPresentationSourceInterface
{
    /**
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    public function normalizeEntry(array $entry): array;

    /** @return array<string, mixed> */
    public function defaults(): array;
}
