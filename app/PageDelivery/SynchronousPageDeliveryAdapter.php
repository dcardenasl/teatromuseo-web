<?php

declare(strict_types=1);

namespace App\PageDelivery;

use App\Libraries\PublicListingPageBuilder;
use App\Libraries\WebApiClientInterface;
use App\Services\BlockPrefetchService;
use App\Services\LayoutDataPrefetchService;
use App\Services\PageCompositionService;
use App\Services\SitePageService;
use App\Services\SiteRedirectService;
use App\Support\PublicPaths;
use DateTimeInterface;

/**
 * Composes an explicitly requested public page before the renderer starts.
 * This is the controlled path for preview, regeneration and explicitly
 * enabled fallback traffic.
 */
final class SynchronousPageDeliveryAdapter implements PageDeliveryInterface
{
    public function __construct(
        private readonly SitePageService $pageService,
        private readonly LayoutDataPrefetchService $layoutPrefetch,
        private readonly BlockPrefetchService $blockPrefetch,
        private readonly ClockInterface $clock,
        private readonly ?PageCompositionService $composition = null,
        private readonly ?PublicListingPageBuilder $listingBuilder = null,
        private readonly ?SiteRedirectService $redirects = null,
        private readonly ?WebApiClientInterface $bff = null,
    ) {
    }

    public function deliver(PageDeliveryRequest $request): PageDeliveryResponse
    {
        // WEB-PAGE-01 rolls out the new full-page contract for the homepage
        // first. Other routes keep the existing synchronous seam until their
        // renderer-specific migration tasks are verified.
        if ($this->bff !== null && $request->route === 'home') {
            return $this->deliverFromBff($request);
        }

        // A CMS redirect always wins over a configured route's own content —
        // the same precedence the legacy resolver uses
        // (PageResolverService::resolveRedirectAndPage()). Checked
        // here rather than at the manifest gate so a snapshot HIT never pays
        // for this lookup: it only runs on preview, sync mode, and the
        // (infrequent) synchronous build that refreshes a snapshot.
        $redirect = $this->findRedirect($request);
        if ($redirect !== null) {
            $target = PublicPaths::resolveRedirectTarget($redirect, $request->locale);

            return PageDeliveryResponse::redirect($target['path'], $target['status'], [
                'locale' => $request->locale,
                'route' => $request->route,
            ]);
        }

        $page = $this->findPage($request);
        if ($page === null) {
            return PageDeliveryResponse::failure(404, ['Public page was not found.'], [
                'locale' => $request->locale,
                'route' => $request->route,
            ]);
        }

        $blocks = $this->blocks($page);
        [$layout, $blockContext] = $this->compose($blocks, $request);
        $now = $this->clock->now();
        $stale = $this->containsStaleSource($blockContext);

        return PageDeliveryResponse::success(
            page: $page,
            layout: $layout,
            blockContext: $blockContext,
            meta: [
                'locale' => $request->locale,
                'route' => $request->route,
                'generated_at' => $now->format(DateTimeInterface::ATOM),
                'expires_at' => null,
                'query' => $request->query,
                'instances' => $this->instances($blockContext),
            ],
            source: [
                'domain' => 'web',
                'state' => $stale ? 'stale' : 'fresh',
                'stale' => $stale,
            ],
        );
    }

    private function deliverFromBff(PageDeliveryRequest $request): PageDeliveryResponse
    {
        $path = 'public-read/' . rawurlencode($request->locale) . '/page-resolve/' . $this->encodedRoute($request->route);
        $query = array_merge($request->query, $request->previewQuery());
        $result = $this->bff?->get($path, $query, 300, 'page-resolve');
        if (! is_array($result)) {
            return PageDeliveryResponse::failure(503, ['Public page delivery is temporarily unavailable.'], [
                'locale' => $request->locale,
                'route' => $request->route,
                'query' => $request->query,
            ]);
        }

        $body = is_array($result['data'] ?? null) ? $result['data'] : [];
        $meta = is_array($body['meta'] ?? null) ? $body['meta'] : [
            'locale' => $request->locale,
            'route' => $request->route,
            'query' => $request->query,
        ];
        $messages = is_array($body['messages'] ?? null)
            ? array_values(array_filter($body['messages'], 'is_string'))
            : (is_array($result['messages'] ?? null) ? $result['messages'] : []);
        $outcome = (string) ($body['outcome'] ?? '');

        if ($outcome === 'redirect' && is_array($body['redirect'] ?? null)) {
            return PageDeliveryResponse::redirect(
                (string) ($body['redirect']['path'] ?? '/'),
                (int) ($body['redirect']['status'] ?? 301),
                $meta,
            );
        }

        if ($outcome === 'not_found') {
            return PageDeliveryResponse::failure(404, $messages !== [] ? $messages : ['Public page was not found.'], $meta);
        }

        $page = is_array($body['page'] ?? null) ? $body['page'] : null;
        if ($outcome !== 'page' || $page === null) {
            return PageDeliveryResponse::failure(
                max(500, (int) ($result['status'] ?? 503)),
                $messages !== [] ? $messages : ['Public page delivery is temporarily unavailable.'],
                $meta,
            );
        }

        $layout = is_array($body['layout'] ?? null) ? $body['layout'] : [];
        $blockContext = is_array($body['block_context'] ?? null) ? $body['block_context'] : [];
        $source = is_array($body['source'] ?? null) ? $body['source'] : [];
        if (($result['meta']['stale'] ?? false) === true) {
            $source['state'] = 'stale';
            $source['stale'] = true;
        }

        return PageDeliveryResponse::success(
            page: $page,
            layout: $layout,
            blockContext: $blockContext,
            meta: $meta,
            source: $source,
        );
    }

    private function encodedRoute(string $route): string
    {
        $segments = array_values(array_filter(explode('/', trim($route, '/')), static fn (string $segment): bool => $segment !== ''));

        return implode('/', array_map('rawurlencode', $segments));
    }

    /**
     * @param list<array<string, mixed>> $blocks
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function compose(array $blocks, PageDeliveryRequest $request): array
    {
        if ($this->composition !== null) {
            try {
                $composition = $this->composition->compose($blocks, $request->locale);

                return [$composition['layout'], $composition['block_context']];
            } catch (\Throwable $exception) {
                log_message('warning', 'Synchronous page composition failed: {message}', [
                    'message' => $exception->getMessage(),
                    'locale' => $request->locale,
                ]);
            }
        }

        return [$this->layout($request), $this->blockContext($blocks, $request)];
    }

    /** @return array<string, mixed>|null */
    private function findRedirect(PageDeliveryRequest $request): ?array
    {
        if ($this->redirects === null) {
            return null;
        }

        $path = $request->route === 'home'
            ? PublicPaths::homepageSegment($request->locale)
            : $request->route;

        return $this->redirects->resolve($path);
    }

    /** @return array<string, mixed>|null */
    private function findPage(PageDeliveryRequest $request): ?array
    {
        if ($request->route === 'home') {
            return $this->findHomepage($request);
        }

        $query = $request->previewQuery();
        $page = $this->pageService->getBySlug(
            $request->locale,
            $request->route,
            $request->preview,
            $query['preview_expires'] ?? null,
            $query['preview_sig'] ?? null,
        );
        if ($page !== null) {
            return $page;
        }

        return $this->fallbackListing($request);
    }

    /** @return array<string, mixed>|null */
    private function findHomepage(PageDeliveryRequest $request): ?array
    {
        $query = $request->previewQuery();
        $page = $this->pageService->getBySlug(
            $request->locale,
            PublicPaths::homepageSegment($request->locale),
            $request->preview,
            $query['preview_expires'] ?? null,
            $query['preview_sig'] ?? null,
        );
        if ($page !== null) {
            return $page;
        }

        return $this->pageService->getByType($request->locale, 'home');
    }

    /** @return array<string, mixed>|null */
    private function fallbackListing(PageDeliveryRequest $request): ?array
    {
        if ($this->listingBuilder === null) {
            return null;
        }

        $pageType = $request->route === PublicPaths::eventsSegment($request->locale)
            ? 'events'
            : ($request->route === PublicPaths::catalogSegment($request->locale) ? 'catalog_listing' : null);
        if ($pageType === null) {
            return null;
        }

        $listing = $pageType === 'events'
            ? $this->listingBuilder->event($request->locale)
            : $this->listingBuilder->museum($request->locale);
        $page = $listing['page'];
        $page['page_type'] = $pageType;
        $page['blocks'] = $listing['blocks'];

        return $page;
    }

    /**
     * @param array<string, mixed> $page
     * @return list<array<string, mixed>>
     */
    private function blocks(array $page): array
    {
        return is_array($page['blocks'] ?? null)
            ? array_values(array_filter($page['blocks'], 'is_array'))
            : [];
    }

    /** @return array<string, mixed> */
    private function layout(PageDeliveryRequest $request): array
    {
        try {
            return $this->layoutPrefetch->prefetchLayoutData([], $request->locale);
        } catch (\Throwable $exception) {
            log_message('warning', 'Synchronous page layout composition failed: {message}', [
                'message' => $exception->getMessage(),
                'locale' => $request->locale,
            ]);

            return [
                'mainMenu' => ['items' => []],
                'footerMenu' => ['items' => []],
                'legalMenu' => ['items' => []],
                'settings' => [],
                'socialLinks' => [],
            ];
        }
    }

    /**
     * @param list<array<string, mixed>> $blocks
     * @return array<string, mixed>
     */
    private function blockContext(array $blocks, PageDeliveryRequest $request): array
    {
        try {
            return $this->blockPrefetch->prefetchContext($blocks, $request->locale);
        } catch (\Throwable $exception) {
            log_message('warning', 'Synchronous page block composition failed: {message}', [
                'message' => $exception->getMessage(),
                'locale' => $request->locale,
            ]);

            return [
                'block_prefetch' => [],
                'block_prefetch_complete' => true,
                'form_definitions' => [],
            ];
        }
    }

    /** @param array<string, mixed> $blockContext */
    private function containsStaleSource(array $blockContext): bool
    {
        foreach (($blockContext['block_prefetch'] ?? []) as $result) {
            if (is_array($result) && (($result['stale'] ?? false) === true || ($result['meta']['stale'] ?? false) === true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $blockContext
     * @return list<array<string, mixed>>
     */
    private function instances(array $blockContext): array
    {
        $instances = [];
        foreach (($blockContext['block_prefetch'] ?? []) as $path => $result) {
            if (! is_array($result) || ! is_array($result['instance'] ?? null)) {
                continue;
            }

            $instances[] = array_merge(['path' => (string) $path], $result['instance']);
        }

        return $instances;
    }
}
