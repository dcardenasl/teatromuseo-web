<?php

declare(strict_types=1);

namespace App\PageDelivery;

/**
 * Immutable identity of a public page delivery.
 *
 * The request contains every public variant that can affect composition. It is
 * therefore safe to use as the cache identity without allowing an arbitrary
 * query string to create unbounded public variants. Public snapshots are
 * intentionally limited to the canonical, no-query request until a route
 * declares an explicit variant policy and warm-up coverage.
 */
final readonly class PageDeliveryRequest
{
    /** @var list<string> */
    private const VARIANT_QUERY_KEYS = [
        'category',
        'filter_by',
        'filter_operator',
        'filter_value',
        'limit',
        'order_by',
        'order_direction',
        'page',
        'per_page',
        'q',
        'search',
        'tag',
    ];

    /**
     * @param array<string, mixed> $query
     */
    public readonly string $locale;
    public readonly string $route;
    public readonly bool $preview;
    public readonly ?string $previewExpires;
    public readonly ?string $previewSignature;
    public readonly bool $snapshotEligibleByRoute;
    public readonly bool $useBff;

    /** @var array<string, string> */
    public readonly array $query;

    /** @param array<string, mixed> $query */
    public function __construct(
        string $locale,
        string $route,
        bool $preview = false,
        ?string $previewExpires = null,
        ?string $previewSignature = null,
        array $query = [],
        bool $snapshotEligible = true,
        bool $useBff = false,
    ) {
        $this->locale = strtolower(trim($locale));
        $this->route = trim($route, '/');
        $this->preview = $preview;
        $this->previewExpires = $previewExpires;
        $this->previewSignature = $previewSignature;
        $this->snapshotEligibleByRoute = $snapshotEligible;
        $this->useBff = $useBff;
        $this->query = self::normalizeQuery($query);
    }

    /** @param array<string, mixed> $query */
    public static function home(
        string $locale,
        bool $preview = false,
        ?string $previewExpires = null,
        ?string $previewSignature = null,
        array $query = [],
    ): self {
        return new self(
            locale: $locale,
            route: 'home',
            preview: $preview,
            previewExpires: $previewExpires,
            previewSignature: $previewSignature,
            query: $query,
        );
    }

    /** @return array<string, string> */
    public function previewQuery(): array
    {
        if (! $this->preview) {
            return [];
        }

        $query = ['preview' => '1'];
        if ($this->previewExpires !== null && $this->previewExpires !== '') {
            $query['preview_expires'] = $this->previewExpires;
        }
        if ($this->previewSignature !== null && $this->previewSignature !== '') {
            $query['preview_sig'] = $this->previewSignature;
        }

        return $query;
    }

    /**
     * Only the canonical no-query request can use a public snapshot.
     *
     * Keeping this policy here makes it impossible for a new query parameter
     * to silently create snapshot files. Supporting variants later requires a
     * route-specific allow-list, bounded values, warm-up entries and matching
     * invalidation coverage instead of relying on a generic key-name guess.
     */
    public function isSnapshotEligible(): bool
    {
        if (! $this->snapshotEligibleByRoute) {
            return false;
        }

        return $this->query === [];
    }

    /**
     * Return the stable identity used by snapshot stores and regeneration locks.
     */
    public function cacheKey(): string
    {
        $identity = [
            'version' => 1,
            'locale' => $this->locale,
            'route' => $this->route,
            'preview' => $this->preview,
            'preview_expires' => $this->previewExpires,
            'preview_signature' => $this->previewSignature,
            'query' => $this->query,
        ];

        return 'page_delivery_v1_' . hash('sha256', (string) json_encode($identity, JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, string>
     */
    private static function normalizeQuery(array $query): array
    {
        $normalized = [];
        foreach (self::VARIANT_QUERY_KEYS as $key) {
            $value = $query[$key] ?? null;
            if (is_scalar($value) && trim((string) $value) !== '') {
                $normalized[$key] = trim((string) $value);
            }
        }

        ksort($normalized);

        return $normalized;
    }
}
