<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

class EventController extends BasePublicWebController
{
    /**
     * Render the public listing of events.
     */
    public function index(): ResponseInterface
    {
        $lang = $this->request->getLocale();
        return $this->renderCmsPageOrFallbackListing(
            $lang,
            \App\Support\PublicPaths::EVENTS,
            static fn (string $language): array => Services::publicListingPageBuilder()->event($language)
        );
    }

    /**
     * Render details of a single event/show.
     */
    public function show(string $slug): ResponseInterface
    {
        $lang = $this->request->getLocale();
        $eventService = Services::siteEventService();

        $event = $eventService->getEvent($lang, $slug);

        if (!$event) {
            return $this->notFound(lang('Site.event_not_found') ?: 'Evento no encontrado');
        }

        $pageTitle = (string) ($event['localized']['title'] ?? $event['title'] ?? '');
        $pageExcerpt = (string) ($event['localized']['description'] ?? $event['description'] ?? '');

        $featuredImage = $event['cover_image'] ?? $event['featured_image'] ?? $event['main_image'] ?? null;
        $ogImageUrl = is_array($featuredImage) ? (string) ($featuredImage['url'] ?? '') : '';
        if ($ogImageUrl === '' && is_string($featuredImage)) {
            $ogImageUrl = $featuredImage;
        }

        $canonicalUrl = site_url('/' . $lang . '/' . \App\Support\PublicPaths::EVENTS . '/' . $slug);

        $localizedUrls = [];
        $apiSlugs = is_array($event['slugs'] ?? null) ? $event['slugs'] : [];
        foreach (config('App')->supportedLocales as $locale) {
            if (isset($apiSlugs[$locale]) && is_string($apiSlugs[$locale]) && $apiSlugs[$locale] !== '') {
                $localizedUrls[$locale] = site_url('/' . $locale . '/' . \App\Support\PublicPaths::EVENTS . '/' . ltrim($apiSlugs[$locale], '/'));
            }
        }

        return $this->renderTemplatePage('template_event_item', $lang, [
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
            'event_item' => $event,
        ]);
    }
}
