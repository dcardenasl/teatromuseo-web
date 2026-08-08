<?php

declare(strict_types=1);

namespace App\Interfaces;

/**
 * Analyzes CMS page blocks to determine what external data they need.
 *
 * Used in Fase 4 (Smart Prefetch) to avoid N+1 queries during block rendering
 * by pre-fetching all required data in parallel before render.
 */
interface BlockAnalyzerInterface
{
    /**
     * Analyze blocks and return their data requirements.
     *
     * Returns an array keyed by resource type with structure:
     *   [
     *     'collection_items' => ['ids' => [1,2,3], 'fields' => ['id', 'name']],
     *     'events' => ['ids' => [10,20], 'fields' => ['id', 'slug']],
     *     'catalog_slugs' => ['slugs' => ['payaso', 'museo']],
     *   ]
     *
     * @param array<array<string, mixed>> $blocks Array of block data from CMS API
     * @param string $locale Current render locale (es, en, etc.)
     * @return array<string, array<string, mixed>> Requirements map by resource type
     */
    public function analyze(array $blocks, string $locale = 'es'): array;

    /**
     * Get requirements for a single block by its type.
     *
     * @param string $blockType Block type key (e.g., 'collection_grid')
     * @param array<string, mixed> $blockData Block payload from API
     * @param string $locale Current render locale
     * @return array<string, mixed> Requirements for this block, or empty array if none
     */
    public function getBlockRequirements(string $blockType, array $blockData, string $locale = 'es'): array;
}
