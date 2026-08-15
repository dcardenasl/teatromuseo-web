<?php

declare(strict_types=1);

namespace App\PageDelivery;

use App\Libraries\WebApiClientInterface;

/**
 * Delivers a public page by delegating the complete read/composition contract
 * to the BFF. The Web owns presentation; it does not recompose domain data.
 */
final class SynchronousPageDeliveryAdapter implements PageDeliveryInterface
{
    public function __construct(
        private readonly WebApiClientInterface $bff,
    ) {
    }

    public function deliver(PageDeliveryRequest $request): PageDeliveryResponse
    {
        $path = 'public-read/' . rawurlencode($request->locale) . '/page-resolve/' . $this->encodedRoute($request->route);
        $query = array_merge($request->query, $request->previewQuery());
        $result = $this->bff->get($path, $query, 300, 'page-resolve');

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

        $source = is_array($body['source'] ?? null) ? $body['source'] : [];
        if (($result['meta']['stale'] ?? false) === true) {
            $source['state'] = 'stale';
            $source['stale'] = true;
        }

        return PageDeliveryResponse::success(
            page: $page,
            layout: is_array($body['layout'] ?? null) ? $body['layout'] : [],
            blockContext: is_array($body['block_context'] ?? null) ? $body['block_context'] : [],
            meta: $meta,
            source: $source,
        );
    }

    private function encodedRoute(string $route): string
    {
        $segments = array_values(array_filter(
            explode('/', trim($route, '/')),
            static fn (string $segment): bool => $segment !== '',
        ));

        return implode('/', array_map('rawurlencode', $segments));
    }

}
