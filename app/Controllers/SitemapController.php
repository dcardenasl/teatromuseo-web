<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

class SitemapController extends BasePublicWebController
{
    private const CACHE_TTL = 3600;
    private const CACHE_SCHEMA_VERSION = 3;

    /**
     * Generate XML sitemap.
     */
    public function index(): ResponseInterface
    {
        $lang = service('request')->getLocale();

        // Try to get from cache
        $cache = service('cache');
        $cacheKey = 'sitemap_v' . self::CACHE_SCHEMA_VERSION . "_{$lang}";
        $xml = $cache->get($cacheKey);

        if ($xml === null) {
            try {
                $xml = $this->generateSitemap($lang);
            } catch (\Throwable $exception) {
                log_message('error', sprintf(
                    'Sitemap projection unavailable for locale %s: %s: %s',
                    $lang,
                    $exception::class,
                    $exception->getMessage(),
                ));

                return $this->response
                    ->setStatusCode(503)
                    ->setContentType('application/xml')
                    ->setHeader('Retry-After', '60')
                    ->setBody('<?xml version="1.0" encoding="UTF-8"?><sitemap-unavailable/>');
            }
            $cache->save($cacheKey, $xml, self::CACHE_TTL);
        }

        return $this->response
            ->setContentType('application/xml')
            ->removeHeader('Cache-Control')
            ->setHeader('Cache-Control', 'public, max-age=3600, stale-while-revalidate=300')
            ->setHeader('ETag', '"' . sha1($xml) . '"')
            ->setHeader('Vary', 'Accept-Language')
            ->setBody($xml);
    }

    /**
     * Generate the XML sitemap content.
     */
    private function generateSitemap(string $lang): string
    {
        $projection = Services::siteSitemapService()->get($lang);
        if ($projection === null) {
            throw new \RuntimeException('Public sitemap projection is unavailable.');
        }

        $urls = [];

        // Add homepage
        $urls[] = [
            'loc'        => base_url('/' . $lang . \App\Support\PublicPaths::homepagePath($lang)),
            'lastmod'    => date('c'),
            'changefreq' => 'weekly',
            'priority'   => '1.0',
        ];

        // Add pages from the single BFF projection.
        $pages = is_array($projection['pages'] ?? null) ? $projection['pages'] : [];
        foreach ($pages as $page) {
            // The BFF sitemap projection is already filtered by publication and
            // sitemap visibility. Keep accepting the legacy flag when present,
            // but do not require an internal source field at this boundary.
            if (!isset($page['slug'])
                || (array_key_exists('is_in_sitemap', $page) && ! $page['is_in_sitemap'])) {
                continue;
            }

            // The CMS page type is `home`, but its public slug is localized.
            // The homepage is emitted once above using that canonical slug.
            $slug = trim((string) $page['slug'], '/');
            if (\App\Support\PublicPaths::isHomepageSlug($slug, $lang)
                || (string) ($page['page_type'] ?? '') === 'home') {
                continue;
            }

            $urls[] = [
                'loc'        => base_url('/' . $lang . '/' . $slug),
                'lastmod'    => $page['updated_at'] ?? date('c'),
                'changefreq' => $page['sitemap_changefreq'] ?? 'monthly',
                'priority'   => $page['sitemap_priority'] ?? '0.8',
            ];
        }

        $collections = is_array($projection['collections'] ?? null) ? $projection['collections'] : [];
        $entriesByCollection = [];
        foreach (is_array($projection['entries'] ?? null) ? $projection['entries'] : [] as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $key = (string) ($entry['collection_key'] ?? '');
            if ($key !== '') {
                $entriesByCollection[$key][] = $entry;
            }
        }

        foreach ($collections as $collection) {
            if (! is_array($collection)) {
                continue;
            }
            $collectionKey = (string) ($collection['collection_key'] ?? '');
            $collection['index_page'] = [
                'localized_slugs' => is_array($collection['localized_slugs'] ?? null)
                    ? $collection['localized_slugs']
                    : [],
            ];
            $urlPath = localized_collection_url_path($collection, $lang);
            if ($urlPath === '' && trim((string) ($collection['slug'] ?? '')) !== '') {
                $urlPath = '/' . trim((string) $collection['slug'], '/');
            }
            if ($urlPath === '') {
                continue;
            }

            foreach ($entriesByCollection[$collectionKey] ?? [] as $entry) {
                $slug = trim((string) ($entry['slug'] ?? ''), '/');
                if ($slug === '') {
                    continue;
                }
                $urls[] = [
                    'loc'        => base_url('/' . $lang . $urlPath . '/' . $slug),
                    'lastmod'    => $entry['updated_at'] ?? date('c'),
                    'changefreq' => $entry['sitemap_changefreq'] ?? 'weekly',
                    'priority'   => $entry['sitemap_priority'] ?? '0.7',
                ];
            }
        }

        return $this->buildSitemapXml($urls);
    }

    /**
     * Build the XML sitemap structure.
     *
     * @param array<array<string, string>> $urls
     */
    private function buildSitemapXml(array $urls): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;

        foreach ($urls as $url) {
            $xml .= '  <url>' . PHP_EOL;
            $xml .= '    <loc>' . esc($url['loc']) . '</loc>' . PHP_EOL;

            $lastmod = $url['lastmod'] ?? '';
            if (is_array($lastmod)) {
                $lastmod = $lastmod['date'] ?? '';
            }
            if (!empty($lastmod)) {
                $ts = strtotime((string) $lastmod);
                $xml .= '    <lastmod>' . esc(date('c', $ts !== false ? $ts : time())) . '</lastmod>' . PHP_EOL;
            }

            if (!empty($url['changefreq'])) {
                $xml .= '    <changefreq>' . esc($url['changefreq']) . '</changefreq>' . PHP_EOL;
            }

            if (!empty($url['priority'])) {
                $xml .= '    <priority>' . esc($url['priority']) . '</priority>' . PHP_EOL;
            }

            $xml .= '  </url>' . PHP_EOL;
        }

        $xml .= '</urlset>';

        return $xml;
    }
}
