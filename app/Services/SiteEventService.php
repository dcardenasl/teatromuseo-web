<?php

declare(strict_types=1);

namespace App\Services;

use App\Libraries\WebApiClientInterface;

class SiteEventService extends BaseSiteService
{
    private const CACHE_TTL_DETAIL = 300;
    private const CACHE_TTL_LIST = 180;
    private const CACHE_TTL_TYPES = 600;

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
     * @return array{data: list<array<string, mixed>>, meta: array<string, mixed>}
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
            'data' => is_array($response['data'] ?? null) ? array_values($response['data']) : [],
            'meta' => ['pagination' => $pagination],
        ];
    }

    /**
     * Return the event types currently present in the public catalogue.
     *
     * The listing UI must not maintain a second, hard-coded event-type
     * catalogue. This intentionally reads the published records exposed by
     * the domain and lets the caller decide whether to display the facet.
     *
     * @return list<array{slug: string, name: string, sort_order: int}>
     */
    public function listEventTypes(string $lang): array
    {
        $response = $this->apiClient->get('public/events/types', [], self::CACHE_TTL_TYPES, 'event_types');

        if (! ($response['ok'] ?? false)) {
            return [];
        }

        $data = is_array($response['data'] ?? null) ? $response['data'] : [];
        $types = [];
        foreach ($data as $type) {
            if (! is_array($type)) {
                continue;
            }

            $slug = trim((string) ($type['slug'] ?? ''));
            if ($slug === '') {
                continue;
            }

            $localized = is_array($type['localized'] ?? null) ? $type['localized'] : [];
            $types[] = [
                'slug' => $slug,
                'name' => trim((string) ($localized['name'] ?? $type['name'] ?? $slug)),
                'sort_order' => (int) ($type['sort_order'] ?? 0),
            ];
        }

        return $types;
    }
}
