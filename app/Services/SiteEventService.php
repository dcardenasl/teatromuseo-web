<?php

declare(strict_types=1);

namespace App\Services;

use App\Libraries\WebApiClientInterface;

class SiteEventService extends BaseSiteService
{
    private const CACHE_TTL_DETAIL = 300;
    private const CACHE_TTL_LIST = 180;

    public function __construct(WebApiClientInterface $apiClient)
    {
        parent::__construct($apiClient);
    }

    /**
     * Get a single event/show by slug or ID.
     *
     * @return array<string, mixed>|null
     */
    public function getEvent(string $lang, string $idOrSlug): ?array
    {
        return $this->fetchData(
            "public/events/{$idOrSlug}",
            [],
            self::CACHE_TTL_DETAIL,
            'events'
        );
    }

    /**
     * List events/shows.
     *
     * @param array<string, mixed> $queryParams
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    public function listEvents(string $lang, array $queryParams = []): array
    {
        $response = $this->apiClient->get('public/events', $queryParams, self::CACHE_TTL_LIST, 'events');

        if (! ($response['ok'] ?? false)) {
            return ['data' => [], 'meta' => []];
        }

        $pagination = $this->normalizePagination(
            is_array($response['meta'] ?? null) ? $response['meta'] : [],
            isset($queryParams['page']) ? (int) $queryParams['page'] : 1,
            isset($queryParams['per_page']) ? (int) $queryParams['per_page'] : 20
        );

        return [
            'data' => is_array($response['data'] ?? null) ? $response['data'] : [],
            'meta' => ['pagination' => $pagination],
        ];
    }
}
