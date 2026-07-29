<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

class MuseumController extends BasePublicWebController
{
    /**
     * Render the public listing of the museum catalog collection.
     */
    public function index(): ResponseInterface
    {
        $lang = $this->request->getLocale();
        return $this->renderCmsPageOrFallbackListing(
            $lang,
            \App\Support\PublicPaths::CATALOG,
            static fn (string $language): array => Services::publicListingPageBuilder()->museum($language)
        );
    }

    /**
     * Render details of a single collection item (scientific sheet).
     */
    public function show(string $idOrCode): ResponseInterface
    {
        $lang = $this->request->getLocale();
        $catalogService = Services::siteCatalogService();

        $item = $catalogService->getItem($lang, $idOrCode);

        if (!$item) {
            return $this->notFound(lang('Site.collection_item_not_found') ?: 'Pieza de colección no encontrada');
        }

        // Fetch all categories to match the category name
        $categories = $catalogService->listCategories($lang);
        $categoryName = '';
        foreach ($categories as $cat) {
            if ((int) ($cat['id'] ?? 0) === (int) ($item['category_id'] ?? 0)) {
                $categoryName = (string) ($cat['name'] ?? '');
                break;
            }
        }

        $pageTitle = (string) ($item['localized']['name'] ?? $item['name'] ?? '');
        $pageExcerpt = (string) ($item['localized']['summary'] ?? $item['summary'] ?? '');

        $featuredImage = $item['cover_image'] ?? $item['featured_image'] ?? $item['main_image'] ?? null;
        $ogImageUrl = is_array($featuredImage) ? (string) ($featuredImage['url'] ?? '') : '';
        if ($ogImageUrl === '' && is_string($featuredImage)) {
            $ogImageUrl = $featuredImage;
        }

        // Use the actual localized slug for canonical if available, else fallback
        $canonicalSlug = (string) ($item['slug'] ?? $idOrCode);
        $canonicalUrl = site_url('/' . $lang . '/' . \App\Support\PublicPaths::CATALOG . '/' . $canonicalSlug);

        $localizedUrls = [];
        $apiSlugs = is_array($item['slugs'] ?? null) ? $item['slugs'] : [];
        foreach (config('App')->supportedLocales as $locale) {
            if (isset($apiSlugs[$locale]) && is_string($apiSlugs[$locale]) && $apiSlugs[$locale] !== '') {
                $localizedUrls[$locale] = site_url('/' . $locale . '/' . \App\Support\PublicPaths::CATALOG . '/' . ltrim($apiSlugs[$locale], '/'));
            }
        }

        return $this->renderTemplatePage('template_catalog_item', $lang, [
            'title'              => $pageTitle,
            'excerpt'            => $pageExcerpt,
            'showPageHeading'    => false,
            'pageTitle'          => $pageTitle,
            'metaDescription'    => $pageExcerpt,
            'canonicalUrl'       => $canonicalUrl,
            'ogImage'            => $ogImageUrl,
            'metaRobots'         => 'index, follow',
            'schemaData'         => null,
            'localized_urls'     => $localizedUrls,
        ], [
            'catalog_item' => $item,
            'category_name' => $categoryName,
        ]);
    }
}
