<?php

declare(strict_types=1);

namespace App\PageDelivery;

use App\Libraries\PublicListingPageBuilder;
use App\Services\BlockPrefetchService;
use App\Services\LayoutDataPrefetchService;
use App\Services\PageCompositionService;
use App\Services\SitePageService;
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
    ) {
    }

    public function deliver(PageDeliveryRequest $request): PageDeliveryResponse
    {
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
