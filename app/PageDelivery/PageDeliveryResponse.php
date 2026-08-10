<?php

declare(strict_types=1);

namespace App\PageDelivery;

/**
 * Stable, versioned delivery envelope consumed by the public renderer.
 */
final readonly class PageDeliveryResponse
{
    /**
     * @param array<string, mixed>|null $page
     * @param array<string, mixed>      $layout
     * @param array<string, mixed>      $blockContext
     * @param array<string, mixed>      $meta
     * @param array<string, mixed>      $source
     * @param list<string>               $messages
     */
    public function __construct(
        public int $status,
        public ?array $page,
        public array $layout,
        public array $blockContext,
        public array $meta,
        public array $source,
        public array $messages = [],
    ) {
    }

    /**
     * @param array<string, mixed> $page
     * @param array<string, mixed> $layout
     * @param array<string, mixed> $blockContext
     * @param array<string, mixed> $meta
     * @param array<string, mixed> $source
     */
    public static function success(
        array $page,
        array $layout,
        array $blockContext,
        array $meta,
        array $source = [],
    ): self {
        return new self(
            status: 200,
            page: $page,
            layout: $layout,
            blockContext: $blockContext,
            meta: array_merge(['version' => 1], $meta),
            source: array_merge([
                'domain' => 'web',
                'state' => 'fresh',
                'stale' => false,
            ], $source),
        );
    }

    /**
     * @param list<string> $messages
     * @param array<string, mixed> $meta
     */
    public static function failure(int $status, array $messages, array $meta = []): self
    {
        return new self(
            status: $status,
            page: null,
            layout: [],
            blockContext: [],
            meta: array_merge(['version' => 1], $meta),
            source: [
                'domain' => 'web',
                'state' => 'unavailable',
                'stale' => false,
            ],
            messages: $messages,
        );
    }

    public function isAvailable(): bool
    {
        return $this->status >= 200 && $this->status < 300 && $this->page !== null;
    }

    /** @return array<string, mixed> */
    public function envelope(): array
    {
        return [
            'version' => 1,
            'ok' => $this->isAvailable(),
            'data' => $this->isAvailable()
                ? [
                    'page' => $this->page,
                    'layout' => $this->layout,
                    'block_context' => $this->blockContext,
                ]
                : null,
            'meta' => $this->meta,
            'source' => $this->source,
            'messages' => $this->messages,
        ];
    }

    /**
     * @param array<string, mixed> $envelope
     */
    public static function fromEnvelope(array $envelope): ?self
    {
        if ((int) ($envelope['version'] ?? 0) !== 1 || ! is_array($envelope['data'] ?? null)) {
            return null;
        }

        $data = $envelope['data'];
        $page = is_array($data['page'] ?? null) ? $data['page'] : null;
        if ($page === null) {
            return null;
        }

        $layout = is_array($data['layout'] ?? null) ? $data['layout'] : [];
        $blockContext = is_array($data['block_context'] ?? null) ? $data['block_context'] : [];
        $meta = is_array($envelope['meta'] ?? null) ? $envelope['meta'] : [];
        $source = is_array($envelope['source'] ?? null) ? $envelope['source'] : [];
        $messages = is_array($envelope['messages'] ?? null)
            ? array_values(array_filter($envelope['messages'], 'is_string'))
            : [];

        return new self(
            status: ($envelope['ok'] ?? true) === true ? 200 : 502,
            page: $page,
            layout: $layout,
            blockContext: $blockContext,
            meta: $meta,
            source: $source,
            messages: $messages,
        );
    }
}
