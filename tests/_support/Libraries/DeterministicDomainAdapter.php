<?php

declare(strict_types=1);

namespace Tests\Support\Libraries;

use App\Libraries\WebApiClientInterface;

/**
 * In-memory Domain adapter for hermetic Web feature tests.
 */
final class DeterministicDomainAdapter implements WebApiClientInterface
{
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
        unset($query, $cacheTtl, $scope);

        if (isset($this->responses[$path])) {
            return $this->responses[$path];
        }

        if ($path === 'public/settings') {
            return $this->response([
                'site_name' => 'Deterministic Fixture Site',
                'site_description' => 'Synthetic settings for hermetic feature tests.',
                'site_logo_url' => 'https://example.com/assets/fixture-logo.png',
            ]);
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

        if (str_starts_with($path, 'public/menus/')) {
            return $this->response(['items' => []]);
        }

        if (preg_match('#^public/([^/]+)/pages/home$#', $path, $matches) === 1) {
            return $this->response($this->homePage($matches[1]));
        }

        if (preg_match('#^public/([^/]+)/pages$#', $path, $matches) === 1) {
            return $this->response([$this->homePage($matches[1])]);
        }

        if (preg_match('#^public/([^/]+)/collections$#', $path) === 1) {
            return $this->response([]);
        }

        return [
            'ok' => false,
            'status' => 404,
            'data' => null,
            'meta' => [],
            'messages' => ['Not found'],
        ];
    }

    public function post(string $path, array $data = []): array
    {
        unset($path, $data);

        return $this->response([]);
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

    /** @return array<string, mixed> */
    private function homePage(string $locale): array
    {
        $localizedSlugs = array_fill_keys($this->locales, 'home');
        $localizedSlugs[$locale] = 'home';

        return [
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
