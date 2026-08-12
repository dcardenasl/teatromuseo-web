<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

class SitemapController extends BasePublicWebController
{
    private const CACHE_TTL = 3600;
    private const CACHE_SCHEMA_VERSION = 2;

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
            $xml = $this->generateSitemap($lang);
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
        $pageService = Services::sitePageService();
        $collectionService = Services::siteCollectionService();
        $entryService = Services::siteEntryService();

        $urls = [];

        // Add homepage
        $urls[] = [
            'loc'        => base_url('/' . $lang . \App\Support\PublicPaths::homepagePath($lang)),
            'lastmod'    => date('c'),
            'changefreq' => 'weekly',
            'priority'   => '1.0',
        ];

        // Add pages
        $pages = $pageService->listAll($lang, [
            'fields' => 'slug,page_type,is_in_sitemap,updated_at,sitemap_changefreq,sitemap_priority',
        ]);
        foreach ($pages as $page) {
            if (!isset($page['slug']) || !$page['is_in_sitemap']) {
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

        // Add collections and their entries
        $collections = $collectionService->getAll($lang, [
            'fields' => 'id,collection_key,slug,localized_slugs,index_page',
        ]);
        foreach ($collections as $collection) {
            $collectionKey = $collection['collection_key'] ?? '';
            $urlPath       = collection_url_path($collection);
            if ($urlPath === '') {
                continue;
            }

            // Add entries. Use bounded page traversal so a large collection
            // cannot be silently truncated at the old single 500-item call.
            $page = 1;
            $perPage = 100;
            $maxPages = 1000;
            do {
                $result = $entryService->list($lang, $collectionKey, [
                    'page' => $page,
                    'per_page' => $perPage,
                    'fields' => 'slug,is_published,updated_at',
                ]);
                $entries = is_array($result['data'] ?? null) ? $result['data'] : [];
                foreach ($entries as $entry) {
                    if (!isset($entry['slug']) || !($entry['is_published'] ?? true)) {
                        continue;
                    }

                    $urls[] = [
                        'loc'        => base_url('/' . $lang . $urlPath . '/' . $entry['slug']),
                        'lastmod'    => $entry['updated_at'] ?? date('c'),
                        'changefreq' => 'weekly',
                        'priority'   => '0.7',
                    ];
                }

                $pagination = is_array($result['meta']['pagination'] ?? null)
                    ? $result['meta']['pagination']
                    : [];
                $hasNext = ($pagination['has_next_page'] ?? false) === true
                    || count($entries) >= $perPage;
                $page++;
            } while ($hasNext && $entries !== [] && $page <= $maxPages);
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
