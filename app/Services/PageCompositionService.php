<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Single composition seam for public HTML. Layout, forms and dynamic blocks
 * are planned before the view tree starts; detail seeds are passed through so
 * a controller-loaded entity is never fetched again by a template block.
 */
final class PageCompositionService
{
    public function __construct(
        private readonly LayoutDataPrefetchService $layout,
        private readonly BlockPrefetchService $blocks,
    ) {
    }

    /**
     * @param list<array<string, mixed>> $blockDefinitions
     * @param array<string, mixed> $layoutContext
     * @param array<string, list<array<string, mixed>>> $seededItems
     * @return array{layout: array<string, mixed>, block_context: array<string, mixed>}
     */
    public function compose(
        array $blockDefinitions,
        string $locale,
        array $layoutContext = [],
        array $seededItems = [],
    ): array {
        return [
            'layout' => $this->layout->prefetchLayoutData($layoutContext),
            'block_context' => $this->blocks->prefetchContext($blockDefinitions, $locale, $seededItems),
        ];
    }
}
