<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Libraries\WebApiClient;
use App\Services\SitePageService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class SitePageServiceTest extends CIUnitTestCase
{
    private function makeService(array $getReturn): SitePageService
    {
        // createMock bypasses WebApiClient constructor (which needs env vars)
        $apiClient = $this->createMock(WebApiClient::class);
        $apiClient->method('get')->willReturn($getReturn);

        return new SitePageService($apiClient);
    }

    public function testGetBySlugReturnsPageDataOnSuccess(): void
    {
        $pageData = ['id' => 1, 'title' => 'About Us', 'slug' => 'about'];

        $service = $this->makeService(['ok' => true, 'data' => $pageData]);
        $result  = $service->getBySlug('es', 'about');

        $this->assertSame($pageData, $result);
    }

    public function testGetBySlugReturnsNullWhenApiFails(): void
    {
        $service = $this->makeService(['ok' => false, 'data' => null]);
        $result  = $service->getBySlug('es', 'non-existent');

        $this->assertNull($result);
    }

    public function testGetBySlugReturnsNullWhenApiReturnsNoData(): void
    {
        $service = $this->makeService(['ok' => true, 'data' => null]);
        $result  = $service->getBySlug('es', 'empty-page');

        $this->assertNull($result);
    }

    public function testGetHomepageResolvesLocalizedSlugFromPageListing(): void
    {
        $homepage = ['id' => 1, 'page_type' => 'home', 'title' => 'Accueil', 'slug' => 'accueil'];
        $apiClient = $this->createMock(WebApiClient::class);
        $apiClient->method('get')->willReturnCallback(static function (string $path): array {
            return match ($path) {
                'public-read/fr/pages/home',
                'public-read/fr/pages/inicio' => ['ok' => false, 'data' => null],
                'public-read/fr/pages' => ['ok' => true, 'data' => [[
                    'page_type' => 'home',
                    'slug' => 'accueil',
                ]]],
                'public-read/fr/pages/accueil' => ['ok' => true, 'data' => [
                    'id' => 1,
                    'page_type' => 'home',
                    'title' => 'Accueil',
                    'slug' => 'accueil',
                ]],
                default => ['ok' => false, 'data' => null],
            };
        });

        $service = new SitePageService($apiClient);

        $this->assertSame($homepage, $service->getHomepage('fr'));
    }

    public function testListAllReturnsPagesOnSuccess(): void
    {
        $pages = [
            ['id' => 1, 'slug' => 'home'],
            ['id' => 2, 'slug' => 'about'],
        ];

        $service = $this->makeService(['ok' => true, 'data' => $pages]);
        $result  = $service->listAll('es');

        $this->assertCount(2, $result);
        $this->assertSame('home', $result[0]['slug']);
    }

    public function testListAllReturnsEmptyArrayWhenApiFails(): void
    {
        $service = $this->makeService(['ok' => false, 'data' => null]);
        $result  = $service->listAll('es');

        $this->assertSame([], $result);
    }

    public function testGetBySlugPassesCorrectEndpointToApiClient(): void
    {
        $apiClient = $this->createMock(WebApiClient::class);
        $apiClient
            ->expects($this->once())
            ->method('get')
            ->with('public-read/es/pages/contact', [], 300)
            ->willReturn(['ok' => true, 'data' => ['slug' => 'contact']]);

        $service = new SitePageService($apiClient);
        $service->getBySlug('es', 'contact');
    }

    public function testListAllPassesCorrectEndpointToApiClient(): void
    {
        $apiClient = $this->createMock(WebApiClient::class);
        $apiClient
            ->expects($this->once())
            ->method('get')
            ->with('public-read/en/pages', [], 600)
            ->willReturn(['ok' => true, 'data' => []]);

        $service = new SitePageService($apiClient);
        $service->listAll('en');
    }
}
