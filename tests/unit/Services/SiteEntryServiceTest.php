<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Libraries\WebApiClientInterface;
use App\Services\SiteEntryService;
use PHPUnit\Framework\TestCase;

/** @internal */
final class SiteEntryServiceTest extends TestCase
{
    public function testRelatedEntriesPreferCategoryMatchesAndFillFromBoundedFallback(): void
    {
        $client = $this->createMock(WebApiClientInterface::class);
        $queries = [];
        $client->expects($this->exactly(2))
            ->method('get')
            ->with(
                'public-read/es/entries/news',
                $this->callback(static function (array $query) use (&$queries): bool {
                    $queries[] = $query;

                    return ($query['per_page'] ?? 0) === 4
                        && ($query['fields'] ?? '') === 'id,slug,title,excerpt,published_at,featured_image,categories,localized';
                }),
                180,
                'entries',
            )
            ->willReturnOnConsecutiveCalls(
                [
                    'ok' => true,
                    'status' => 200,
                    'data' => [
                        ['slug' => 'same-category', 'categories' => [['slug' => 'arte']]],
                    ],
                    'meta' => [],
                    'messages' => [],
                ],
                [
                    'ok' => true,
                    'status' => 200,
                    'data' => [
                        ['slug' => 'other-category', 'categories' => [['slug' => 'historia']]],
                        ['slug' => 'current', 'categories' => [['slug' => 'arte']]],
                    ],
                    'meta' => [],
                    'messages' => [],
                ],
            );

        $result = (new SiteEntryService($client))->related('es', 'news', [
            'slug' => 'current',
            'categories' => [['slug' => 'arte']],
        ], 3);

        $this->assertSame(['same-category', 'other-category'], array_column($result, 'slug'));
        $this->assertSame('arte', $queries[0]['category']);
        $this->assertArrayNotHasKey('category', $queries[1]);
    }
}
