<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Libraries\WebApiClientInterface;
use App\Services\SiteEntryService;
use PHPUnit\Framework\TestCase;

/** @internal */
final class SiteEntryServiceTest extends TestCase
{
    public function testListReturnsEntriesWithNormalizedPagination(): void
    {
        $client = $this->createMock(WebApiClientInterface::class);
        $client->expects($this->once())
            ->method('get')
            ->with(
                'public-read/es/entries/news',
                ['page' => 2, 'per_page' => 2, 'fields' => 'slug,is_published,updated_at'],
                180,
                'entries',
            )
            ->willReturn([
                'ok' => true,
                'data' => [['slug' => 'first'], ['slug' => 'second']],
                'meta' => ['total' => 5, 'page' => 2, 'per_page' => 2],
            ]);

        $result = (new SiteEntryService($client))->list('es', 'news', [
            'page' => 2,
            'per_page' => 2,
            'fields' => 'slug,is_published,updated_at',
        ]);

        $this->assertSame(['slug' => 'first'], $result['data'][0]);
        $this->assertSame(5, $result['meta']['pagination']['total']);
        $this->assertTrue($result['meta']['pagination']['has_next_page']);
    }

    public function testListReturnsEmptyResultWhenBffFails(): void
    {
        $client = $this->createMock(WebApiClientInterface::class);
        $client->method('get')->willReturn(['ok' => false, 'data' => null]);

        $this->assertSame(
            ['data' => [], 'meta' => ['pagination' => []]],
            (new SiteEntryService($client))->list('es', 'news'),
        );
    }
}
