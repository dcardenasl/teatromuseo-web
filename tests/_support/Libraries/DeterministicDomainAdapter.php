<?php

declare(strict_types=1);

namespace Tests\Support\Libraries;

use App\Libraries\WebApiClientInterface;

/**
 * In-memory Domain adapter for hermetic Web feature tests.
 */
final class DeterministicDomainAdapter implements WebApiClientInterface
{
    /** @var list<array{path: string, query: array<string, mixed>, cacheTtl: int, scope: string}> */
    public array $calls = [];

    /** @var list<string> */
    private array $locales;

    /** @var array<string, array{ok: bool, status: int, data: mixed, meta: array<string, mixed>, messages: list<string>}> */
    private array $responses = [];

    /** @param list<string> $locales */
    public function __construct(array $locales = ['l01', 'l02', 'l03'])
    {
        $this->locales = array_values(array_unique(array_filter(
            $locales,
            static fn (mixed $locale): bool => is_string($locale) && $locale !== '',
        )));
    }

    /** @return list<string> */
    public function locales(): array
    {
        return $this->locales;
    }

    /** @param array<string, mixed> $meta */
    public function fakeGet(string $path, mixed $data, array $meta = []): void
    {
        $this->responses[$path] = $this->response($data, $meta);
    }

    public function fakeGetFailure(string $path, int $status = 404): void
    {
        $this->responses[$path] = [
            'ok' => false,
            'status' => $status,
            'data' => null,
            'meta' => [],
            'messages' => ['Not found'],
        ];
    }

    public function get(string $path, array $query = [], int $cacheTtl = 300, string $scope = 'general'): array
    {
        $this->calls[] = [
            'path'     => $path,
            'query'    => $query,
            'cacheTtl' => $cacheTtl,
            'scope'    => $scope,
        ];

        if (isset($this->responses[$path])) {
            return $this->responses[$path];
        }

        $normalizedPath = preg_replace('#^public-read/#', 'public/', $path);
        if (isset($this->responses[$normalizedPath])) {
            return $this->responses[$normalizedPath];
        }

        // Composite bootstrap endpoints (ADR 006 in the CMS domain) — Web's
        // LayoutDataPrefetchService/PageResolverService each make one request
        // instead of the three/two they used to make. Compose from the same
        // per-resource resolution the individual paths below still serve, so
        // `fakeGet()` calls for the old individual paths keep working
        // transparently for tests that predate the composite endpoints.
        if (preg_match('#^public-read/([^/]+)/layout$#', $path, $matches) === 1) {
            return $this->response([
                'navigation' => $this->navigationData(),
                'collections' => $this->collectionsData($matches[1]),
                'settings' => $this->settingsData($matches[1]),
            ]);
        }

        if (preg_match('#^public-read/([^/]+)/page-bootstrap/(.+)$#', $path, $matches) === 1) {
            return $this->response([
                'redirect' => $this->redirectData($matches[2]),
                'page' => $this->pageData($matches[1], $matches[2]),
            ]);
        }

        if ($path === 'public/settings' || preg_match('#^public-read/([^/]+)/settings$#', $path) === 1) {
            $locale = preg_match('#^public-read/([^/]+)/settings$#', $path, $matches) === 1 ? $matches[1] : '';

            return $this->response($this->settingsData($locale));
        }

        if ($path === 'cms/public/languages') {
            return $this->response(array_map(
                static fn (string $locale): array => [
                    'code' => $locale,
                    'name' => 'Fixture ' . $locale,
                    'native_name' => 'Fixture ' . $locale,
                    'is_default' => false,
                ],
                $this->locales,
            ));
        }

        if (preg_match('#^public-read/([^/]+)/navigation$#', $path) === 1) {
            return $this->response($this->navigationData());
        }

        if (str_starts_with($path, 'public/menus/')) {
            return $this->response(['items' => []]);
        }

        if (preg_match('#^public(?:-read)?/([^/]+)/pages/(.+)$#', $path, $matches) === 1) {
            $page = $this->pageData($matches[1], $matches[2]);

            return $page !== null ? $this->response($page) : [
                'ok' => false,
                'status' => 404,
                'data' => null,
                'meta' => [],
                'messages' => ['Not found'],
            ];
        }

        if (preg_match('#^public/([^/]+)/pages$#', $path, $matches) === 1 || preg_match('#^public-read/([^/]+)/pages$#', $path, $matches) === 1) {
            return $this->response([$this->homePage($matches[1])]);
        }

        if (preg_match('#^public/([^/]+)/collections$#', $path, $matches) === 1) {
            return $this->response($this->collectionsData($matches[1]));
        }

        return [
            'ok' => false,
            'status' => 404,
            'data' => null,
            'meta' => [],
            'messages' => ['Not found'],
        ];
    }

    /**
     * @param list<array{path: string, query?: array<string, mixed>, cacheTtl?: int, scope?: string}> $requests
     * @return list<array{ok: bool, status: int, data: mixed, meta: array<string, mixed>, messages: list<string>}>
     */
    public function multiGet(array $requests): array
    {
        $results = [];
        foreach ($requests as $req) {
            $path = $req['path'] ?? '';
            $query = is_array($req['query'] ?? null) ? $req['query'] : [];
            $cacheTtl = (int) ($req['cacheTtl'] ?? 300);
            $scope = (string) ($req['scope'] ?? 'general');
            $results[] = $this->get($path, $query, $cacheTtl, $scope);
        }
        return $results;
    }

    public function post(string $path, array $data = []): array
    {
        unset($path, $data);

        return $this->response([]);
    }

    /** @return list<string> */
    public function requestedPaths(): array
    {
        return array_map(
            static fn (array $call): string => $call['path'],
            $this->calls,
        );
    }

    /** @param array<string, mixed> $meta */
    private function response(mixed $data, array $meta = []): array
    {
        return [
            'ok' => true,
            'status' => 200,
            'data' => $data,
            'meta' => $meta,
            'messages' => [],
        ];
    }

    /** @return array{main: mixed, footer: mixed, legal: mixed} */
    private function navigationData(): array
    {
        return [
            'main' => isset($this->responses['public/menus/main']) ? $this->responses['public/menus/main']['data'] : ['items' => []],
            'footer' => isset($this->responses['public/menus/footer']) ? $this->responses['public/menus/footer']['data'] : ['items' => []],
            'legal' => isset($this->responses['public/menus/legal']) ? $this->responses['public/menus/legal']['data'] : ['items' => []],
        ];
    }

    private function settingsData(string $locale): mixed
    {
        if ($locale !== '' && isset($this->responses["public-read/{$locale}/settings"])) {
            return $this->responses["public-read/{$locale}/settings"]['data'];
        }
        if (isset($this->responses['public/settings'])) {
            return $this->responses['public/settings']['data'];
        }

        return [
            'site_name' => 'Deterministic Fixture Site',
            'site_description' => 'Synthetic settings for hermetic feature tests.',
            'site_logo_url' => 'https://example.com/assets/fixture-logo.png',
        ];
    }

    /** @return list<mixed> */
    private function collectionsData(string $locale): array
    {
        $key = "public/{$locale}/collections";

        return isset($this->responses[$key]) && is_array($this->responses[$key]['data'])
            ? $this->responses[$key]['data']
            : [];
    }

    /** @return array{new_url: string, redirect_type: int}|null */
    private function redirectData(string $path): ?array
    {
        $key = 'public/redirects/' . $path;

        return isset($this->responses[$key]) && ($this->responses[$key]['ok'] ?? false) && is_array($this->responses[$key]['data'])
            ? $this->responses[$key]['data']
            : null;
    }

    /**
     * The individual page-by-path fake keys accept both `public-read/...`
     * and `public/...` prefixes (tests predate the `public-read` migration
     * for some fixtures); the composite `page-bootstrap` route only exists
     * under `public-read`, so it checks both when composing.
     *
     * @return array<string, mixed>|null
     */
    private function pageData(string $locale, string $path): ?array
    {
        foreach (["public-read/{$locale}/pages/{$path}", "public/{$locale}/pages/{$path}"] as $key) {
            if (isset($this->responses[$key]) && ($this->responses[$key]['ok'] ?? false) && is_array($this->responses[$key]['data'])) {
                return $this->responses[$key]['data'];
            }
        }

        if (in_array($path, ['home', 'inicio'], true)) {
            return $this->homePage($locale);
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function homePage(string $locale): array
    {
        $localizedSlugs = array_fill_keys($this->locales, 'home');
        $localizedSlugs[$locale] = 'home';

        return [
            'page_type' => 'home',
            'title' => 'Fixture homepage ' . $locale,
            'slug' => 'home',
            'excerpt' => 'Synthetic content for hermetic public markup tests in ' . $locale . '.',
            'meta_title' => 'Deterministic fixture site ' . $locale,
            'meta_description' => 'Synthetic homepage metadata used to validate public markup for an arbitrary locale.',
            'canonical_url' => '',
            'robots' => 'index, follow',
            'is_in_sitemap' => true,
            'updated_at' => '2026-01-01T00:00:00+00:00',
            'sitemap_changefreq' => 'weekly',
            'sitemap_priority' => '1.0',
            'blocks' => [],
            'localized_slugs' => $localizedSlugs,
        ];
    }
}
