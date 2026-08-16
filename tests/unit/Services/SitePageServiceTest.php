<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Libraries\WebApiClientInterface;
use App\Services\SitePageService;
use PHPUnit\Framework\TestCase;

/** @internal */
final class SitePageServiceTest extends TestCase
{
    public function testListAllReturnsPublishedPages(): void
    {
        $client = $this->createMock(WebApiClientInterface::class);
        $pages = [
            ['id' => 1, 'slug' => 'home'],
            ['id' => 2, 'slug' => 'about'],
        ];
        $client->expects($this->once())
            ->method('get')
            ->with('public-read/es/pages', ['fields' => 'slug'], 600, 'pages')
            ->willReturn(['ok' => true, 'data' => $pages]);

        $this->assertSame($pages, (new SitePageService($client))->listAll('es', ['fields' => 'slug']));
    }

    public function testListAllReturnsEmptyArrayWhenBffFails(): void
    {
        $client = $this->createMock(WebApiClientInterface::class);
        $client->method('get')->willReturn(['ok' => false, 'data' => null]);

        $this->assertSame([], (new SitePageService($client))->listAll('es'));
    }
}
