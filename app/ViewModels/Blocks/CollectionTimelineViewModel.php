<?php

declare(strict_types=1);

namespace App\ViewModels\Blocks;

use App\Services\SiteCollectionService;
use App\Services\SiteEntryService;

final class CollectionTimelineViewModel extends AbstractBlockViewModel
{
    public function vars(): array
    {
        $collectionKey = trim($this->configString('collection_key'));
        $categoryId = max(0, $this->configInt('category_id', 0));
        $limit = max(1, min(100, $this->configInt('items_limit', 100)));
        $direction = strtolower($this->configString('order_direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $entries = $this->entries($collectionKey, $categoryId, $limit, $direction);

        return [
            'sectionTitle' => $this->dataString('section_title'),
            'description' => $this->dataString('description'),
            'emptyMessage' => $this->dataString('empty_message') ?: 'No hay publicaciones disponibles todavía.',
            'documentLabel' => $this->dataString('document_label') ?: 'Descargar documento',
            'entryLabel' => $this->dataString('entry_label') ?: 'Ver ficha',
            'layout' => in_array($this->configString('layout', 'alternating'), ['alternating', 'left_aligned'], true)
                ? $this->configString('layout', 'alternating')
                : 'alternating',
            'showExcerpt' => $this->configBool('show_excerpt', true),
            'showDocuments' => $this->configBool('show_documents', true),
            'showEntryLink' => $this->configBool('show_entry_link', false),
            'openInNewTab' => $this->configBool('open_in_new_tab', true),
            'cssClass' => trim($this->configString('css_class')),
            'entries' => $entries,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function entries(string $collectionKey, int $categoryId, int $limit, string $direction): array
    {
        $entryService = $this->contextService('siteEntryService', SiteEntryService::class);
        if ($entryService === null || $collectionKey === '') {
            return [];
        }

        try {
            $query = [
                'per_page' => $limit,
                'order_by' => 'published_at',
                'order_direction' => $direction,
                'include' => 'listing_content',
            ];
            if ($categoryId > 0) {
                $query['category_id'] = $categoryId;
            }

            $prefetched = $this->prefetchedEntries();
            if ($prefetched !== null) {
                $entries = $prefetched;
            } elseif (($this->context['block_prefetch_complete'] ?? false) === true) {
                $entries = [];
            } else {
                $result = $entryService->list($this->lang, $collectionKey, $query);
                $entries = is_array($result['data'] ?? null) ? $result['data'] : [];
            }
            $listingUrl = $this->collectionListingUrl($collectionKey);

            $normalized = [];
            foreach ($entries as $entry) {
                if (! is_array($entry)) {
                    continue;
                }
                $localized = is_array($entry['localized'] ?? null) ? $entry['localized'] : [];
                $entry['title'] = (string) ($localized['title'] ?? $entry['title'] ?? '');
                $entry['slug'] = (string) ($localized['slug'] ?? $entry['slug'] ?? '');
                $entry['excerpt'] = (string) ($entry['excerpt'] ?? $entry['summary'] ?? '');
                $listingContent = is_array($entry['listing_content'] ?? null) ? $entry['listing_content'] : [];
                $entry['display_date'] = $this->firstNonEmpty([
                    $listingContent['publication_date'] ?? null,
                    $entry['published_at'] ?? null,
                    $entry['created_at'] ?? null,
                ]);
                $entry['display_year'] = $this->yearLabel($entry['display_date'], $entry['title']);
                $entry['documents'] = $this->documents($listingContent['documents'] ?? []);
                $entry['entry_url'] = $listingUrl !== '' && $entry['slug'] !== ''
                    ? rtrim($listingUrl, '/') . '/' . ltrim($entry['slug'], '/')
                    : '';
                $normalized[] = $entry;
            }

            usort($normalized, static function (array $left, array $right) use ($direction): int {
                $leftDate = self::sortValue((string) ($left['display_date'] ?? ''));
                $rightDate = self::sortValue((string) ($right['display_date'] ?? ''));
                $comparison = $leftDate <=> $rightDate;
                return $direction === 'asc' ? $comparison : -$comparison;
            });

            return $normalized;
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return list<array<string, mixed>>|null */
    private function prefetchedEntries(): ?array
    {
        $blockPath = (string) ($this->context['blockPath'] ?? '');
        $allPrefetched = $this->context['block_prefetch'] ?? null;
        if ($blockPath === '' || ! is_array($allPrefetched) || ! is_array($allPrefetched[$blockPath] ?? null)) {
            return null;
        }

        $entries = $allPrefetched[$blockPath]['data'] ?? [];
        if (! is_array($entries)) {
            return [];
        }

        return array_values(array_filter($entries, 'is_array'));
    }

    /** @param list<mixed> $values */
    private function firstNonEmpty(array $values): string
    {
        foreach ($values as $value) {
            $normalized = $this->normalizeDateValue($value);
            if ($normalized !== '') {
                return $normalized;
            }
        }

        return '';
    }

    private function normalizeDateValue(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_array($value)) {
            foreach (['value', 'date', 'datetime', 'display_date'] as $key) {
                if (array_key_exists($key, $value)) {
                    $normalized = $this->normalizeDateValue($value[$key]);
                    if ($normalized !== '') {
                        return $normalized;
                    }
                }
            }

            foreach ($value as $nestedValue) {
                $normalized = $this->normalizeDateValue($nestedValue);
                if ($normalized !== '') {
                    return $normalized;
                }
            }

            return '';
        }

        if (is_scalar($value)) {
            return trim((string) $value);
        }

        return '';
    }

    private function yearLabel(string $date, string $title): string
    {
        preg_match_all('/\b(?:19|20)\d{2}\b/', $date . ' ' . $title, $matches);
        $years = array_values(array_unique($matches[0]));
        if ($years !== []) {
            return count($years) === 1
                ? $years[0]
                : $years[0] . '–' . $years[count($years) - 1];
        }

        return $date !== '' ? $date : '—';
    }

    /** @return list<array<string, mixed>> */
    private function documents(mixed $value): array
    {
        $documents = is_array($value) ? $value : [];
        $normalized = [];
        foreach ($documents as $index => $document) {
            if (! is_array($document)) {
                continue;
            }
            $url = trim((string) ($document['url'] ?? ''));
            if ($url === '') {
                continue;
            }

            $title = trim((string) ($document['title'] ?? ''));
            $meta = $this->documentTypeFromUrl($url);
            $normalized[] = [
                ...$document,
                'url' => $url,
                'doc_type' => $meta['docType'],
                'extension' => $meta['ext'],
                'semester_label' => $this->semesterLabel($title, (int) $index),
            ];
        }

        usort($normalized, static function (array $left, array $right): int {
            $leftRank = self::semesterRank((string) ($left['semester_label'] ?? ''));
            $rightRank = self::semesterRank((string) ($right['semester_label'] ?? ''));

            return ($leftRank <=> $rightRank);
        });

        return $normalized;
    }

    private static function semesterRank(string $label): int
    {
        $normalized = strtolower($label);
        if (preg_match('/primer|first|premier|primeiro/', $normalized) === 1) {
            return 1;
        }
        if (preg_match('/segundo|second|deuxième|segundo/', $normalized) === 1) {
            return 2;
        }

        return 99;
    }

    private function semesterLabel(string $title, int $index): string
    {
        if ($title !== '' && preg_match('/semestre|semester|semestre/i', $title) === 1) {
            return $title;
        }

        $labels = match ($this->lang) {
            'en' => ['First semester', 'Second semester'],
            'fr' => ['Premier semestre', 'Deuxième semestre'],
            'pt' => ['Primeiro semestre', 'Segundo semestre'],
            default => ['Primer semestre', 'Segundo semestre'],
        };

        return $labels[$index] ?? ($labels[0] . ' ' . ($index + 1));
    }

    private static function sortValue(string $date): int
    {
        if (preg_match('/\b(?:19|20)\d{2}\b/', $date, $match) === 1) {
            return (int) $match[0];
        }

        return strtotime($date) ?: 0;
    }

    private function collectionListingUrl(string $collectionKey): string
    {
        $collectionService = $this->contextService('siteCollectionService', SiteCollectionService::class);
        if ($collectionService === null) {
            return '';
        }

        try {
            foreach ($collectionService->getAll($this->lang) as $collection) {
                if ((string) ($collection['collection_key'] ?? '') !== $collectionKey) {
                    continue;
                }
                $indexPage = is_array($collection['index_page'] ?? null) ? $collection['index_page'] : [];
                $urls = is_array($indexPage['localized_urls'] ?? null) ? $indexPage['localized_urls'] : [];
                return (string) ($urls[$this->lang] ?? '');
            }
        } catch (\Throwable) {
        }

        return '';
    }
}
