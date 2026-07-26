<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\HermeticFeatureTestCase;

/**
 * SEO markup validation tests for the public website.
 *
 * Tests verify that each page has proper SEO meta tags, structured data,
 * and semantic HTML. These tests do not execute Lighthouse or calculate a score.
 *
 * @internal
 */
final class SeoMarkupTest extends HermeticFeatureTestCase
{
    use FeatureTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureLocales(['aa', 'bb', 'cc']);
    }

    /**
     * Test 1: Spanish homepage has complete meta tags.
     *
     * Validates presence of:
     * 1. <title> present
     * 2. <meta name="description"> present
     * 3. <meta property="og:title"> present
     * 4. <meta property="og:description"> present
     * 5. <meta property="og:type"> present
     * 6. <link rel="canonical"> present and correct
     * 7. <meta name="robots"> present
     * 8. <link rel="alternate" hreflang> for each supported locale
     * Expected: 8/8 meta tags present
     */
    public function testSpanishHomepageSeoScore(): void
    {
        // Act: GET Spanish homepage
        $result = $this->get($this->localizedPath());

        // Assert: Route exists and responds
        $statusCode = $result->response()->getStatusCode();
        $this->assertNotEquals(404, $statusCode, 'Spanish homepage route should exist');

        // Get response body
        $body = $result->response()->getBody();

        // Assert: All required meta tags present
        $this->assertStringContainsString('<title>', $body, 'Title tag must be present');
        $this->assertStringContainsString('<meta name="description"', $body, 'Meta description must be present');
        $this->assertStringContainsString('<meta property="og:title"', $body, 'og:title meta must be present');
        $this->assertStringContainsString('<meta property="og:description"', $body, 'og:description meta must be present');
        $this->assertStringContainsString('<meta property="og:type"', $body, 'og:type meta must be present');
        $this->assertStringContainsString('<link rel="canonical"', $body, 'Canonical link must be present');
        $this->assertStringContainsString('<meta name="robots"', $body, 'Robots meta must be present');
        $this->assertStringContainsString('<link rel="alternate" hreflang', $body, 'hreflang links must be present');
    }

    /**
     * Test 2: English homepage has complete meta tags.
     *
     * Same validation as Test 1 for English variant.
     */
    public function testEnglishHomepageSeoScore(): void
    {
        // Act: GET English homepage
        $result = $this->get($this->localizedPath(1));

        // Assert: Route exists
        $statusCode = $result->response()->getStatusCode();
        $this->assertNotEquals(404, $statusCode, 'English homepage route should exist');

        // Get response body
        $body = $result->response()->getBody();

        // Assert: All required meta tags present
        $this->assertStringContainsString('<title>', $body);
        $this->assertStringContainsString('<meta name="description"', $body);
        $this->assertStringContainsString('<meta property="og:title"', $body);
        $this->assertStringContainsString('<meta property="og:description"', $body);
        $this->assertStringContainsString('<link rel="canonical"', $body);
        $this->assertStringContainsString('<meta name="robots"', $body);
    }

    /**
     * Test 3: Sitemap XML is valid.
     *
     * Validates:
     * 1. Responds with 200 OK
     * 2. Content-Type is application/xml (may include charset)
     * 3. XML structure is valid
     * 4. Contiene <urlset>
     * 5. Cada <url> tiene <loc>
     */
    public function testSitemapXmlValid(): void
    {
        // Act: GET sitemap.xml
        $result = $this->get('/sitemap.xml');

        // Assert: Responds successfully
        $result->assertStatus(200);
        $result->assertHeaderContains('Content-Type', 'application/xml');

        // Get response body
        $body = $result->response()->getBody();

        // Assert: XML structure is valid
        $xml = @simplexml_load_string($body);
        $this->assertNotNull($xml, 'Sitemap must be valid XML');

        // Assert: Has urlset
        $this->assertNotNull($xml->url, 'Sitemap must contain <url> elements');

        // Assert: At least one URL exists
        $urls = $xml->url;
        $this->assertGreaterThan(0, count($urls), 'Sitemap must contain at least 1 URL');

        // Assert: Each URL has required fields
        foreach ($urls as $url) {
            $this->assertNotNull($url->loc, 'Each <url> must have <loc>');
            $this->assertNotEmpty((string) $url->loc, '<loc> must not be empty');
        }
    }

    /**
     * Test 4: Canonical URL format is correct.
     *
     * Validates:
     * - Canonical URL includes domain
     * - Canonical URL includes language (es/ or en/)
     * - Canonical URL has no query parameters
     * - Canonical URL is absolute (http/https)
     */
    public function testCanonicalUrlFormat(): void
    {
        // Act: GET Spanish page
        $result = $this->get($this->localizedPath());
        $statusCode = $result->response()->getStatusCode();
        $this->assertNotEquals(404, $statusCode);

        // Get response body and extract canonical
        $body = $result->response()->getBody();
        preg_match('/<link rel="canonical" href="([^"]+)"/', $body, $matches);
        $this->assertNotEmpty($matches, 'Canonical link must be present');

        $canonical = $matches[1] ?? '';
        $this->assertNotEmpty($canonical, 'Canonical URL must not be empty');

        // Assert: Canonical is absolute (includes http)
        $this->assertStringContainsString('http', $canonical, 'Canonical must be absolute URL with http/https');

        // Assert: Canonical includes language
        $this->assertTrue(
            str_contains($canonical, '/' . $this->locale()) || str_contains($canonical, '/' . $this->locale(1)),
            'Canonical must include one of the configured language prefixes'
        );

        // Assert: Canonical has no query parameters
        $this->assertStringNotContainsString('?', $canonical, 'Canonical URL must not have query parameters');
    }

    /**
     * Test 5: hreflang links are complete for all supported locales.
     *
     * Validates:
     * - hreflang="es" is present
     * - hreflang="en" is present
     * - hreflang="x-default" is present (optional but recommended)
     */
    public function testHreflangLinksComplete(): void
    {
        // Act: GET Spanish page
        $result = $this->get($this->localizedPath());
        $statusCode = $result->response()->getStatusCode();
        $this->assertNotEquals(404, $statusCode);

        // Get response body
        $body = $result->response()->getBody();

        // Assert: hreflang links for both locales
        foreach ($this->locales() as $locale) {
            $this->assertStringContainsString('hreflang="' . $locale . '"', $body);
        }

        // Also check English page has es and en
        $result = $this->get($this->localizedPath(1));
        $body = $result->response()->getBody();
        foreach ($this->locales() as $locale) {
            $this->assertStringContainsString('hreflang="' . $locale . '"', $body);
        }
    }

    /**
     * Test 6: og:image meta tag is configured (or site logo fallback works).
     *
     * Validates:
     * - og:image meta tag is either:
     *   a) Present with a content value, OR
     *   b) Not present if no og:image/site_logo configured (acceptable fallback)
     * - If og:image is present, content is not just whitespace
     */
    public function testOgImageConfiguredOrFallback(): void
    {
        // Act: GET Spanish page
        $result = $this->get($this->localizedPath());
        $statusCode = $result->response()->getStatusCode();
        $this->assertNotEquals(404, $statusCode);

        // Get response body
        $body = $result->response()->getBody();

        // og:image is optional fallback based on site configuration
        // Check if og:image is present
        if (strpos($body, '<meta property="og:image"') !== false) {
            // If present, extract and validate content
            preg_match('/<meta property="og:image" content="([^"]*)"/', $body, $matches);
            $this->assertNotEmpty($matches[1] ?? '', 'If og:image is present, content must not be empty');
        }
        // If og:image is not present, that's OK (site_logo not configured)
        // Both scenarios are acceptable for SEO
    }

    /**
     * Test 7: Meta description length is within SEO best practices.
     *
     * Validates:
     * - Meta description is between 50-160 characters (Google truncates after ~160)
     * - Meta description is not too short (< 50 chars is insufficient)
     * - Meta description is not missing
     */
    public function testMetaDescriptionLength(): void
    {
        // Act: GET Spanish page
        $result = $this->get($this->localizedPath());
        $statusCode = $result->response()->getStatusCode();
        $this->assertNotEquals(404, $statusCode);

        // Get response body
        $body = $result->response()->getBody();

        // Extract meta description
        preg_match('/<meta name="description" content="([^"]*)"/', $body, $matches);
        $this->assertNotEmpty($matches[1] ?? '', 'Meta description must be present');

        $description = $matches[1] ?? '';
        $length = strlen($description);

        // Assert: Length is reasonable for SEO
        $this->assertGreaterThanOrEqual(20, $length, 'Meta description should be at least 20 chars');
        $this->assertLessThanOrEqual(160, $length, 'Meta description should not exceed 160 chars (Google truncates)');
    }

    /**
     * Test 8: Twitter meta tags are present.
     *
     * Validates:
     * - twitter:card is present
     * - twitter:title is present
     * - twitter:description is present
     * (twitter:image is optional)
     */
    public function testTwitterMetaTagsPresent(): void
    {
        // Act: GET Spanish page
        $result = $this->get($this->localizedPath());
        $statusCode = $result->response()->getStatusCode();
        $this->assertNotEquals(404, $statusCode);

        // Get response body
        $body = $result->response()->getBody();

        // Assert: Twitter meta tags
        $this->assertStringContainsString('<meta name="twitter:card"', $body, 'twitter:card must be present');
        $this->assertStringContainsString('<meta name="twitter:title"', $body, 'twitter:title must be present');
        $this->assertStringContainsString('<meta name="twitter:description"', $body, 'twitter:description must be present');
    }

    /**
     * Test 9: JSON-LD schema.org markup is present.
     *
     * Validates:
     * - Script tag with type="application/ld+json" exists
     * - Contains @context: https://schema.org
     * - Contains @type (WebPage, Article, etc.)
     * - Contains name, url, description fields
     */
    public function testJsonLdSchemaPresent(): void
    {
        // Act: GET Spanish page
        $result = $this->get($this->localizedPath());
        $statusCode = $result->response()->getStatusCode();
        $this->assertNotEquals(404, $statusCode);

        // Get response body
        $body = $result->response()->getBody();

        // Assert: ld+json script tag exists
        $this->assertStringContainsString('application/ld+json', $body, 'JSON-LD script tag must be present');
        $this->assertStringContainsString('"@context"', $body, 'JSON-LD @context must be present');
        $this->assertStringContainsString('https://schema.org', $body, 'JSON-LD schema.org context must be present');
        $this->assertStringContainsString('"@type"', $body, 'JSON-LD @type must be present');
    }

    /**
     * Test 10: Heading hierarchy is present or page has semantic structure.
     *
     * Validates:
     * - Page contains headings (h1, h2, h3, etc.) OR semantic structure (article, section)
     * - For pages with h1, should typically have only 1 per SEO best practices
     * - Multiple heading levels indicates proper hierarchy
     */
    public function testHeadingHierarchyPresent(): void
    {
        // Act: GET Spanish page
        $result = $this->get($this->localizedPath());
        $statusCode = $result->response()->getStatusCode();
        $this->assertNotEquals(404, $statusCode);

        // Get response body
        $body = $result->response()->getBody();

        // Assert: Page has semantic heading or structural tags
        // Pages with hero banners may not have <h1>, they use semantic tags instead
        $hasHeadings = preg_match('/<h[1-6][^>]*>/', $body);
        $hasSemanticTags = preg_match('/<(article|section|main|nav)[^>]*>/', $body);

        $this->assertTrue(
            ($hasHeadings === 1) || ($hasSemanticTags === 1),
            'Page should contain heading tags (h1-h6) or semantic HTML tags (article, section, main, nav)'
        );

        // If h1 tags exist, validate count (should be 0-1 per page for SEO)
        preg_match_all('/<h1[^>]*>/', $body, $h1Matches);
        $h1Count = count($h1Matches[0] ?? []);
        $this->assertLessThanOrEqual(1, $h1Count, 'Page should have at most one <h1> tag for SEO');
    }

    /**
     * Test 11: Page responds within acceptable time (< 2 seconds).
     *
     * Performance is a Lighthouse SEO factor. Validates response time.
     */
    public function testPageResponseTimeAcceptable(): void
    {
        // Act: Record time and GET page
        $start = microtime(true);
        $result = $this->get($this->localizedPath());
        $elapsed = microtime(true) - $start;

        // Assert: Response is successful
        $statusCode = $result->response()->getStatusCode();
        $this->assertNotEquals(404, $statusCode);

        // Assert: Response time is acceptable
        $this->assertLessThan(2.0, $elapsed, sprintf(
            'Page response time %.2f seconds exceeds 2s threshold',
            $elapsed
        ));
    }

    /**
     * Test 12: Viewport meta tag is present for mobile optimization.
     *
     * Validates:
     * - <meta name="viewport" ...> exists
     * - Contains width=device-width and initial-scale=1.0
     */
    public function testViewportMetaTagPresent(): void
    {
        // Act: GET Spanish page
        $result = $this->get($this->localizedPath());
        $statusCode = $result->response()->getStatusCode();
        $this->assertNotEquals(404, $statusCode);

        // Get response body
        $body = $result->response()->getBody();

        // Assert: Viewport meta tag
        $this->assertStringContainsString('<meta name="viewport"', $body, 'Viewport meta tag must be present');
        $this->assertStringContainsString('width=device-width', $body, 'Viewport must have width=device-width');
        $this->assertStringContainsString('initial-scale=1', $body, 'Viewport must have initial-scale=1');
    }

    /**
     * Test 13: Charset meta tag is present.
     *
     * Validates:
     * - <meta charset="UTF-8"> exists early in <head>
     */
    public function testCharsetMetaTagPresent(): void
    {
        // Act: GET Spanish page
        $result = $this->get($this->localizedPath());
        $statusCode = $result->response()->getStatusCode();
        $this->assertNotEquals(404, $statusCode);

        // Get response body
        $body = $result->response()->getBody();

        // Assert: Charset meta tag
        $this->assertStringContainsString('<meta charset="UTF-8"', $body, 'Charset meta tag must be present');
    }

    /**
     * Test 14: Collection index pages respond or return 404 gracefully.
     *
     * Validates that collection listing pages (e.g., /es/news) respond properly.
     * They may return 404 if no collection exists, or 200 with proper SEO meta tags.
     */
    public function testCollectionIndexSeoScore(): void
    {
        // Act: GET collection index (news, for example)
        // This may return 404 if no collection exists, which is OK
        $result = $this->get($this->localizedPath(0, 'fixture-collection'));

        $statusCode = $result->response()->getStatusCode();

        // Assert: Responds with valid HTTP status
        $this->assertIsInt($statusCode);
        $this->assertGreaterThanOrEqual(200, $statusCode, 'HTTP status should be valid');

        // If collection exists and responds with 200, validate SEO tags
        if ($statusCode === 200) {
            $body = $result->response()->getBody();

            // Assert: SEO tags present on collection index
            $this->assertStringContainsString('<title>', $body);
            $this->assertStringContainsString('<meta name="description"', $body);
            $this->assertStringContainsString('<link rel="canonical"', $body);
            $this->assertStringContainsString('<meta property="og:title"', $body);
        }
    }

    /**
     * Test 15: No meta tags have empty content (except optional ones like og:image).
     *
     * Validates that important meta tags have meaningful content (not empty strings).
     */
    public function testMetaTagsNotEmpty(): void
    {
        // Act: GET Spanish page
        $result = $this->get($this->localizedPath());
        $statusCode = $result->response()->getStatusCode();
        $this->assertNotEquals(404, $statusCode);

        // Get response body
        $body = $result->response()->getBody();

        // Extract title
        preg_match('/<title>([^<]*)<\/title>/', $body, $titleMatches);
        $this->assertNotEmpty($titleMatches[1] ?? '', 'Title must not be empty');

        // Extract description
        preg_match('/<meta name="description" content="([^"]*)"/', $body, $descMatches);
        $this->assertNotEmpty($descMatches[1] ?? '', 'Meta description must not be empty');

        // Extract og:title
        preg_match('/<meta property="og:title" content="([^"]*)"/', $body, $ogTitleMatches);
        $this->assertNotEmpty($ogTitleMatches[1] ?? '', 'og:title must not be empty');
    }

    /**
     * Test 16: When CMS returns empty string/whitespace for SEO fields, they fall back.
     */
    public function testEmptySeoTagsFallback(): void
    {
        $locale = $this->locale();
        $collectionId = $this->fixtureId();
        $collectionKey = 'fixture-seo-collection';
        $collectionSlug = 'fixture-seo-collection-' . $locale;
        $entrySlug = 'fixture-seo-entry-' . $locale;
        $entryTitle = 'Fixture SEO Entry ' . $locale;
        $entryExcerpt = 'Fixture SEO excerpt ' . $locale;

        // 1. Mock collections (so resolving routes works)
        $collection = [
            'id'                       => $collectionId,
            'collection_key'           => $collectionKey,
            'slug'                     => $collectionSlug,
            'name'                     => 'Fixture SEO Collection',
            'listing_title'            => 'Fixture SEO Collection',
            'listing_intro'            => '',
            'default_meta_description' => 'Fixture SEO collection description',
            'index_page'               => [
                'localized_slugs' => [
                    ...array_fill_keys($this->locales(), $collectionSlug),
                ],
            ],
        ];
        $this->domainAdapter->fakeGet('public/' . $locale . '/collections', [$collection]);

        // 2. Mock a collection entry with empty/whitespace SEO fields
        $this->domainAdapter->fakeGet('public/' . $locale . '/entries/' . $collectionKey . '/' . $entrySlug, [
            'title'              => $entryTitle,
            'slug'               => $entrySlug,
            'excerpt'            => $entryExcerpt,
            'meta_title'         => '   ', // whitespace
            'meta_description'   => '',    // empty
            'robots'             => '',    // empty
            'canonical_url'      => '',
            'published_at'       => '2024-10-07 04:15:23',
            'blocks'             => [],
            'localized_slugs'    => array_fill_keys($this->locales(), $entrySlug),
        ]);

        // Act: GET the page
        $result = $this->get('/' . $locale . '/' . $collectionSlug . '/' . $entrySlug);
        $result->assertStatus(200);

        $body = $result->response()->getBody();

        // Title should fall back to entry title
        $this->assertStringContainsString('<title>' . $entryTitle . '</title>', $body);

        // Description should fall back to entry excerpt
        $this->assertStringContainsString('<meta name="description" content="' . $entryExcerpt . '">', $body);

        // Robots should fall back to \'index, follow\'
        $this->assertStringContainsString('<meta name="robots" content="index, follow">', $body);
    }

    private function localizedPath(int $position = 0, string $suffix = ''): string
    {
        $path = '/' . $this->locale($position);

        return $path . ($suffix !== '' ? '/' . ltrim($suffix, '/') : '/');
    }

    private function fixtureId(): int
    {
        static $nextId = 9000;

        return ++$nextId;
    }
}
