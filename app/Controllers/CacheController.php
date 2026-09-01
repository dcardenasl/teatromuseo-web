<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;

class CacheController extends BaseController
{
    /**
     * POST /cache/invalidate
     *
     * Body:    {"scopes": ["pages", "menus"], "locales": ["es"], "routes": ["home"]}
     * Auth:    X-Invalidate-Key header (shared secret, never logged)
     */
    public function invalidate(): ResponseInterface
    {
        $expectedKey = (string) env('CACHE_INVALIDATE_KEY', '');

        if ($expectedKey === '') {
            log_message('error', '[CacheController] CACHE_INVALIDATE_KEY is not configured.');

            return $this->response
                ->setStatusCode(ResponseInterface::HTTP_INTERNAL_SERVER_ERROR)
                ->setJSON(['ok' => false, 'message' => 'Cache invalidation not configured.']);
        }

        $receivedKey = $this->request->getHeaderLine('X-Invalidate-Key');

        if (! hash_equals($expectedKey, $receivedKey)) {
            log_message('warning', '[CacheController] Invalid X-Invalidate-Key from IP: '
                . $this->request->getIPAddress());

            return $this->response
                ->setStatusCode(ResponseInterface::HTTP_UNAUTHORIZED)
                ->setJSON(['ok' => false, 'message' => 'Unauthorized.']);
        }

        $body = $this->request instanceof \CodeIgniter\HTTP\IncomingRequest
            ? $this->request->getJSON(true)
            : null;
        $rawScopes = is_array($body) && is_array($body['scopes'] ?? null) ? $body['scopes'] : [];
        $scopes    = array_values(array_filter($rawScopes, 'is_string'));
        $locales = is_array($body) && is_array($body['locales'] ?? null)
            ? array_values(array_filter($body['locales'], 'is_string'))
            : [];
        $routes = is_array($body) && is_array($body['routes'] ?? null)
            ? array_values(array_filter($body['routes'], 'is_string'))
            : [];

        if (empty($scopes)) {
            return $this->response
                ->setStatusCode(ResponseInterface::HTTP_UNPROCESSABLE_ENTITY)
                ->setJSON(['ok' => false, 'message' => 'scopes must be a non-empty array of strings.']);
        }

        $source = $this->request->getHeaderLine('X-Cache-Invalidation-Source');
        $result = service('cacheInvalidator')->invalidate(
            $scopes,
            $source !== '' ? $source : 'remote',
            $locales,
            $routes,
        );

        return $this->response->setJSON([
            'ok'          => true,
            'invalidated' => $result['invalidated'],
            'deleted'     => $result['deleted'],
            'snapshots_invalidated' => $result['snapshots_invalidated'] ?? 0,
            'response_cache_deleted' => $result['response_cache_deleted'] ?? 0,
            'locales' => $locales,
            'routes' => $routes,
        ]);
    }

    /**
     * GET /cache/status
     *
     * Auth: X-Invalidate-Key header (shared secret, never logged)
     */
    public function status(): ResponseInterface
    {
        if (! $this->hasValidKey()) {
            return $this->response
                ->setStatusCode(ResponseInterface::HTTP_UNAUTHORIZED)
                ->setJSON(['ok' => false, 'message' => 'Unauthorized.']);
        }

        return $this->response->setJSON([
            'ok' => true,
            'data' => service('cacheInvalidator')->status(),
        ]);
    }

    private function hasValidKey(): bool
    {
        $expectedKey = (string) env('CACHE_INVALIDATE_KEY', '');
        $receivedKey = $this->request->getHeaderLine('X-Invalidate-Key');

        return $expectedKey !== '' && hash_equals($expectedKey, $receivedKey);
    }
}
