<?php

declare(strict_types=1);

namespace App\ViewModels\Blocks;

/**
 * Base class for block view models.
 *
 * A block view model receives the raw block payload from the Domain CMS and
 * prepares every derived value the template needs (parsing, validation,
 * defaults, URL building), so block views only print variables. Registered in
 * BlockRenderer::VIEW_MODELS; the returned vars() are merged into the view
 * data before rendering.
 *
 * `$context` carries the current request and the page-delivery envelope.
 * Dynamic data is always resolved before rendering; ViewModels never receive
 * domain services and cannot initiate remote I/O.
 */
abstract class AbstractBlockViewModel
{
    /**
     * @param array<string, mixed> $block   Raw block payload (block_key, block_config, block_data, children)
     * @param array<string, mixed> $context Render-pass extras: formDefinition,
     *                                      request and prefetched block results
     */
    public function __construct(
        protected readonly array $block,
        protected readonly string $lang,
        protected readonly array $context = [],
    ) {
    }

    protected function contextRequest(): ?\CodeIgniter\HTTP\IncomingRequest
    {
        $value = $this->context['request'] ?? null;

        return $value instanceof \CodeIgniter\HTTP\IncomingRequest ? $value : null;
    }

    /**
     * Variables to expose to the block template.
     *
     * @return array<string, mixed>
     */
    abstract public function vars(): array;

    /**
     * @return array<string, mixed>
     */
    protected function config(): array
    {
        return is_array($this->block['block_config'] ?? null) ? $this->block['block_config'] : [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function data(): array
    {
        return is_array($this->block['block_data'] ?? null) ? $this->block['block_data'] : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function children(): array
    {
        $children = $this->block['children'] ?? [];

        return is_array($children) ? array_values(array_filter($children, 'is_array')) : [];
    }

    protected function configString(string $key, string $default = ''): string
    {
        $value = $this->config()[$key] ?? $default;

        return is_scalar($value) ? (string) $value : $default;
    }

    protected function dataString(string $key, string $default = ''): string
    {
        $value = $this->data()[$key] ?? $default;

        return is_scalar($value) ? (string) $value : $default;
    }

    /**
     * @param array<mixed> $default
     * @return array<mixed>
     */
    protected function configArray(string $key, array $default = []): array
    {
        $value = $this->config()[$key] ?? $default;

        return is_array($value) ? $value : $default;
    }

    /**
     * @param array<mixed> $default
     * @return array<mixed>
     */
    protected function dataArray(string $key, array $default = []): array
    {
        $value = $this->data()[$key] ?? $default;

        return is_array($value) ? $value : $default;
    }

    /**
     * Attach the localized detail URL contract to a listing entry.
     *
     * The listing navigation is already resolved for the active locale by
     * the CMS block serializer. The entry slug follows the same rule: use the
     * locale-specific API projection when available, otherwise the canonical
     * slug returned by the source.
     *
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    protected function withEntryNavigation(array $entry, string $listingUrl): array
    {
        $localized = is_array($entry['localized'] ?? null) ? $entry['localized'] : [];
        $localizedSlugs = is_array($entry['localized_slugs'] ?? null) ? $entry['localized_slugs'] : [];
        $slug = trim((string) ($localized['slug'] ?? $localizedSlugs[$this->lang] ?? $entry['slug'] ?? ''));
        $listingUrl = rtrim(trim($listingUrl), '/');
        $url = $listingUrl !== '' && $slug !== ''
            ? $listingUrl . '/' . ltrim($slug, '/')
            : '';

        $entry['navigation'] = [
            'status' => $url !== '' ? 'resolved' : 'slug_not_available',
            'target_type' => 'entry_detail',
            'target_id' => is_numeric($entry['id'] ?? null) ? (int) $entry['id'] : null,
            'slug' => $slug !== '' ? $slug : null,
            'url' => $url !== '' ? $url : null,
        ];

        return $entry;
    }

    /** @param array<string, mixed> $navigation */
    protected function navigationUrl(array $navigation, string $fallback = ''): string
    {
        $routePath = \App\Support\PublicPaths::routePath(
            (string) ($navigation['route_key'] ?? ''),
            $this->lang,
        );
        if ($routePath !== null) {
            return lang_url($routePath, $this->lang);
        }

        $url = trim((string) ($navigation['url'] ?? ''));
        return $url !== '' ? $url : $fallback;
    }

    /**
     * Resolve the public listing URL when an older or partially migrated
     * block payload has no serialized navigation target. Domain-backed
     * listings use the centralized locale-aware route map; CMS collections
     * keep their stable collection key as the public path fallback.
     */
    protected function defaultListingUrl(string $sourceType, string $collectionKey): string
    {
        $path = match ($sourceType) {
            'event_items' => \App\Support\PublicPaths::eventsSegment($this->lang),
            'catalog_items' => \App\Support\PublicPaths::catalogSegment($this->lang),
            default => trim($collectionKey, '/'),
        };

        return $path !== '' ? lang_url('/' . $path, $this->lang) : '';
    }

    protected function publicUrl(string $url, string $fallback = ''): string
    {
        $url = trim($url);
        if ($url === '' || $url === '#') {
            return $fallback;
        }

        if (preg_match('/^(https?:)?\/\//', $url)) {
            return $url;
        }

        $canonicalPath = \App\Support\PublicPaths::canonicalPath($url, $this->lang);
        if ($canonicalPath !== null) {
            $query = parse_url($url, PHP_URL_QUERY);
            $fragment = parse_url($url, PHP_URL_FRAGMENT);
            $suffix = $query !== null ? '?' . $query : '';
            $suffix .= $fragment !== null ? '#' . $fragment : '';

            return lang_url($canonicalPath . $suffix, $this->lang);
        }

        return lang_url($url, $this->lang);
    }

    /**
     * @return array{source_kind: string, file_id: int|null, url: string, variants: array<string, array<string, mixed>>|null}
     */
    protected function configMediaReference(string $key): array
    {
        return $this->mediaReferenceFromPayload($this->config(), $key);
    }

    /**
     * @return string
     */
    protected function configMediaReferenceUrl(string $key): string
    {
        return $this->configMediaReference($key)['url'];
    }

    /**
     * @param mixed $value
     * @return array{source_kind: string, file_id: int|null, url: string, variants: array<string, array<string, mixed>>|null}
     */
    protected function normalizeMediaReference(mixed $value): array
    {
        if (is_int($value) || (is_string($value) && ctype_digit(trim($value)))) {
            $value = [
                'source_kind' => 'hub_file',
                'file_id'     => (int) $value,
                'url'         => '',
            ];
        } elseif (is_string($value) && trim($value) !== '') {
            $value = [
                'source_kind' => 'external_url',
                'file_id'     => null,
                'url'         => trim($value),
            ];
        }

        if (! is_array($value)) {
            return $this->emptyMediaReference();
        }

        $sourceKind = is_string($value['source_kind'] ?? null)
            ? strtolower(trim($value['source_kind']))
            : '';
        $fileId = is_numeric($value['file_id'] ?? null) && (int) $value['file_id'] > 0
            ? (int) $value['file_id']
            : null;
        $url = is_string($value['url'] ?? null) ? trim($value['url']) : '';
        $variants = $this->normalizeMediaVariants($value['variants'] ?? null);

        // Older CMS payloads used `file` for the same Hub-owned media
        // reference that is now called `hub_file`. Keep the alias at this
        // boundary so views can consume either version without fabricating a
        // `/files/{id}/view` URL.
        if ($sourceKind === 'file') {
            $sourceKind = 'hub_file';
        }
        if ($sourceKind === '' && $fileId !== null) {
            $sourceKind = 'hub_file';
        }
        if ($sourceKind === '' && $url !== '') {
            $sourceKind = 'external_url';
        }
        if ($sourceKind === 'hub_file' && preg_match('#(?:^|/)files/\d+/view(?:[/?]|$)#i', $url) === 1) {
            $url = '';
        }

        if (($sourceKind === 'hub_file' && $fileId === null)
            || ($sourceKind === 'external_url' && $url === '')
            || ! in_array($sourceKind, ['hub_file', 'external_url'], true)) {
            return $this->emptyMediaReference();
        }

        return [
            'source_kind' => $sourceKind,
            'file_id' => $fileId,
            'url' => $url,
            'variants' => $variants,
        ];
    }

    /**
     * Resolve a canonical nested media reference from a payload.
     *
     * @param array<string, mixed> $payload
     * @return array{source_kind: string, file_id: int|null, url: string, variants: array<string, array<string, mixed>>|null}
     */
    protected function mediaReferenceFromPayload(array $payload, string $key): array
    {
        $value = $payload[$key] ?? null;

        if ($value !== null) {
            $normalized = $this->normalizeMediaReference($value);
            if ($normalized['file_id'] !== null || $normalized['url'] !== '') {
                return $normalized;
            }
        }

        // Before media_reference became canonical, some public payloads
        // exposed the relational fields beside the media field. Accept both
        // the generic `{key}_*` spelling and the entry-era
        // `featured_file_id`/`featured_image_url` spelling.
        $legacyPrefix = str_ends_with($key, '_image')
            ? substr($key, 0, -6)
            : $key;
        $fileId = $payload[$key . '_file_id'] ?? $payload[$legacyPrefix . '_file_id'] ?? null;
        $url = $payload[$key . '_url'] ?? $payload[$legacyPrefix . '_image_url'] ?? null;

        if ($fileId !== null || (is_string($url) && trim($url) !== '')) {
            return $this->normalizeMediaReference([
                'source_kind' => is_numeric($fileId) && (int) $fileId > 0 ? 'hub_file' : 'external_url',
                'file_id'     => $fileId,
                'url'         => is_scalar($url) ? trim((string) $url) : '',
            ]);
        }

        return $this->emptyMediaReference();
    }

    /**
     * @return array{source_kind: string, file_id: null, url: string, variants: null}
     */
    private function emptyMediaReference(): array
    {
        return [
            'source_kind' => 'external_url',
            'file_id'     => null,
            'url'         => '',
            'variants'    => null,
        ];
    }

    /**
     * Keep only usable variant metadata already supplied by the public source.
     * The Web app never derives a variant URL from a file ID or an original URL.
     *
     * @return array<string, array<string, mixed>>|null
     */
    private function normalizeMediaVariants(mixed $value): ?array
    {
        if (is_string($value) && trim($value) !== '') {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($value)) {
            return null;
        }

        $variants = [];
        foreach ($value as $key => $variant) {
            if (is_string($variant)) {
                $variant = ['url' => $variant];
            }
            if (! is_array($variant)) {
                continue;
            }

            $variantUrl = is_scalar($variant['url'] ?? null) ? trim((string) $variant['url']) : '';
            if ($variantUrl === '' || preg_match('#(?:^|/)files/\d+/view(?:[/?]|$)#i', $variantUrl) === 1) {
                continue;
            }

            $variant['url'] = $variantUrl;
            foreach (['width', 'height'] as $dimension) {
                if (is_numeric($variant[$dimension] ?? null) && (int) $variant[$dimension] > 0) {
                    $variant[$dimension] = (int) $variant[$dimension];
                }
            }
            $variants[strtolower(trim((string) $key))] = $variant;
        }

        return $variants !== [] ? $variants : null;
    }

    /**
     * @param array<string, mixed> $payload
     * @return string
     */
    protected function mediaReferenceUrlFromPayload(array $payload, string $key): string
    {
        return $this->mediaReferenceFromPayload($payload, $key)['url'];
    }

    /**
     * Infer a document type from a resolved URL.
     *
     * @return array{docType: string, ext: string}
     */
    protected function documentTypeFromUrl(string $url): array
    {
        $path = parse_url($url, PHP_URL_PATH);
        $ext = strtolower(pathinfo(is_string($path) ? $path : '', PATHINFO_EXTENSION));

        $docType = 'generic';
        if (in_array($ext, ['pdf'], true)) {
            $docType = 'pdf';
        } elseif (in_array($ext, ['doc', 'docx', 'odt', 'rtf'], true)) {
            $docType = 'word';
        } elseif (in_array($ext, ['xls', 'xlsx', 'ods', 'csv'], true)) {
            $docType = 'excel';
        } elseif (in_array($ext, ['ppt', 'pptx', 'odp'], true)) {
            $docType = 'powerpoint';
        } elseif (in_array($ext, ['zip', 'rar', 'tar', 'gz', '7z'], true)) {
            $docType = 'archive';
        }

        return [
            'docType' => $docType,
            'ext' => $ext !== '' ? strtoupper($ext) : 'DOC',
        ];
    }

    protected function configBool(string $key, bool $default): bool
    {
        if (! array_key_exists($key, $this->config())) {
            return $default;
        }

        $parsed = filter_var($this->config()[$key], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        return $parsed ?? $default;
    }

    protected function configInt(string $key, int $default): int
    {
        $value = $this->config()[$key] ?? null;

        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * True while rendering the isolated block-preview page (`/blocks/preview`),
     * which entry-driven blocks use to substitute mock data when there's
     * nothing real to show yet.
     */
    protected function isPreviewRequest(): bool
    {
        return str_contains($this->contextRequest()?->getUri()->getPath() ?? '', 'blocks/preview');
    }

    /**
     * Find the first collection matching a predicate in an already-fetched
     * collections list. Both collection_grid and collection_listing need to
     * look a collection up (by key or by id, respectively) before resolving
     * its canonical URL via the global `localized_collection_url_path()`
     * helper — sharing the lookup here keeps that a single source of truth
     * instead of two independently-maintained copies (see the 2026-07-15
     * dead-link fix for what letting those drift apart costs in practice).
     *
     * @param array<array<string, mixed>> $collections
     * @param callable(array<string, mixed>): bool $matcher
     * @return array<string, mixed>|null
     */
    protected function findCollection(array $collections, callable $matcher): ?array
    {
        foreach ($collections as $collection) {
            if (is_array($collection) && $matcher($collection)) {
                return $collection;
            }
        }

        return null;
    }
}
