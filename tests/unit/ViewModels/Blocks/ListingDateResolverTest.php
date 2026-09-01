<?php

declare(strict_types=1);

namespace Tests\Unit\ViewModels\Blocks;

use App\ViewModels\Blocks\Listing\ListingDateResolver;
use PHPUnit\Framework\TestCase;

final class ListingDateResolverTest extends TestCase
{
    public function testResolvesDeclaredListingDate(): void
    {
        $entry = [
            'published_at' => '2026-08-04 12:00:00',
            'listing_content' => ['date_fields' => ['start_date' => '2026-09-15']],
        ];

        self::assertSame('2026-09-15', ListingDateResolver::resolve($entry, 'listing.start_date'));
    }

    public function testUnknownDeclaredDateFallsBackToEmpty(): void
    {
        $entry = ['published_at' => '2026-08-04 12:00:00', 'listing_content' => ['date_fields' => []]];

        self::assertSame('', ListingDateResolver::resolve($entry, 'listing.start_date'));
        self::assertSame('2026-08-04 12:00:00', ListingDateResolver::resolve($entry, 'auto'));
    }
}
