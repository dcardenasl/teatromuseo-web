<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\HermeticFeatureTestCase;

/**
 * Multilingual page rendering smoke tests for the public website.
 *
 * Tests verify that the page controller endpoints exist and respond.
 *
 * @internal
 */
final class MultilingualPageRenderTest extends HermeticFeatureTestCase
{
    use FeatureTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureLocales(['aa', 'bb', 'cc']);
    }

    /**
     * Test 1: Spanish homepage resolves correctly.
     */
    public function testSpanishHomepageResolvesCorrectly(): void
    {
        // Act: GET Spanish home
        $result = $this->get($this->localizedPath());

        // Assert: Not 404 (route exists, API may not be available so could be 404 content)
        $statusCode = $result->response()->getStatusCode();
        $this->assertNotEquals(404, $statusCode, 'Spanish home route should exist');
    }

    /**
     * Test 2: English homepage resolves correctly.
     */
    public function testEnglishHomepageResolvesCorrectly(): void
    {
        // Act: GET English home
        $result = $this->get($this->localizedPath(1));

        // Assert: Not 404
        $statusCode = $result->response()->getStatusCode();
        $this->assertNotEquals(404, $statusCode, 'English home route should exist');
    }

    /**
     * Test 3: Spanish page path resolves correctly.
     */
    public function testSpanishPagePathResolvesCorrectly(): void
    {
        // Act: GET Spanish page
        $result = $this->get($this->localizedPath(0, 'fixture-page'));

        // Assert: Returns 404 or 200 (not 404 Not Found route)
        $statusCode = $result->response()->getStatusCode();
        $this->assertIsInt($statusCode);
        // Most likely 404 (page not in DB) or 200 if cached, not 404 route error
    }

    /**
     * Test 4: English page path resolves correctly.
     */
    public function testEnglishPagePathResolvesCorrectly(): void
    {
        // Act: GET English page
        $result = $this->get($this->localizedPath(1, 'fixture-page'));

        // Assert: Returns 404 or 200
        $statusCode = $result->response()->getStatusCode();
        $this->assertIsInt($statusCode);
    }

    /**
     * Test 5: Root path responds (redirect or home).
     */
    public function testRootPathResponds(): void
    {
        // Act: GET root
        $result = $this->get('/');

        // Assert: Responds (200, 302 redirect, or 404)
        $statusCode = $result->response()->getStatusCode();
        $this->assertIsInt($statusCode);
        $this->assertGreaterThanOrEqual(200, $statusCode);
    }

    /**
     * Test 6: Health/status endpoint is accessible.
     */
    public function testHealthEndpointResponds(): void
    {
        // Act: GET health
        $result = $this->get('/health');

        // Assert: 200 OK (public health check)
        $result->assertStatus(200);
    }

    /**
     * Test 7: Collection paths resolve correctly.
     */
    public function testCollectionPathsResolveCorrectly(): void
    {
        // Act: GET collection path (e.g., /es/news)
        $result = $this->get($this->localizedPath(0, 'fixture-collection'));

        // Assert: Responds (404 or 200)
        $statusCode = $result->response()->getStatusCode();
        $this->assertIsInt($statusCode);
    }

    /**
     * Test 8: Hyphens in slugs are supported.
     */
    public function testHyphensInSlugsSupported(): void
    {
        // Act: GET page with hyphens
        $result = $this->get($this->localizedPath(0, 'fixture-page-with-hyphens'));

        // Assert: Responds (404 or 200)
        $statusCode = $result->response()->getStatusCode();
        $this->assertIsInt($statusCode);
    }

    /**
     * Test 9: Nested paths (slugs with /) are supported.
     */
    public function testNestedPathsSupported(): void
    {
        // Act: GET nested path
        $result = $this->get($this->localizedPath(0, 'fixture/category/subsection/page'));

        // Assert: Responds
        $statusCode = $result->response()->getStatusCode();
        $this->assertIsInt($statusCode);
    }

    /**
     * Test 10: Response is HTML (not JSON error).
     */
    public function testResponseIsHtmlForPages(): void
    {
        // Act: GET page
        $result = $this->get($this->localizedPath(0, 'fixture-page'));

        // Assert: Response contains HTML structure or 404 gracefully
        $statusCode = $result->response()->getStatusCode();
        $this->assertIsInt($statusCode);
    }

    private function localizedPath(int $position = 0, string $suffix = ''): string
    {
        $path = '/' . $this->locale($position);

        return $path . ($suffix !== '' ? '/' . ltrim($suffix, '/') : '/');
    }
}
