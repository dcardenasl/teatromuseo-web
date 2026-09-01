<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Selects an optimized public image source without fabricating asset URLs.
 *
 * The Hub is the authority for variant URLs. The selector only chooses from
 * the metadata already delivered in the public-read payload. `sd` is accepted
 * for installations that expose that variant name; the current image
 * processor calls the equivalent 400px variant `sm`.
 */
final class ImageVariantSelector
{
    /** @var list<string> */
    private const PUBLIC_CARD_VARIANTS = ['sd', 'sm', 'md', 'lg', 'thumb'];

    /**
     * @param mixed $variants
     * @return array{src: string, srcset: string, sizes: string}
     */
    public static function resolve(
        string $originalUrl,
        mixed $variants,
        ?string $preferredVariant = null,
        ?int $maxVariantWidth = null,
    ): array {
        $candidates = self::candidates($variants);
        $source = $originalUrl;

        if ($preferredVariant !== null && trim($preferredVariant) !== '') {
            foreach (self::preferredKeys($preferredVariant) as $key) {
                if (isset($candidates[$key])) {
                    $source = $candidates[$key]['url'];
                    break;
                }
            }
        }

        $srcsetCandidates = self::limitCandidates($candidates, $maxVariantWidth);
        $srcsetItems = [];
        $widths = [];
        foreach ($srcsetCandidates as $candidate) {
            if ($candidate['width'] === null) {
                continue;
            }

            $srcsetItems[] = $candidate['url'] . ' ' . $candidate['width'] . 'w';
            $widths[] = $candidate['width'];
        }

        if ($srcsetItems === []) {
            return [
                'src' => $source,
                'srcset' => '',
                'sizes' => '',
            ];
        }

        sort($widths);

        return [
            'src' => $source,
            'srcset' => implode(', ', $srcsetItems),
            'sizes' => '(max-width: 640px) 100vw, (max-width: 1024px) 50vw, ' . max($widths) . 'px',
        ];
    }

    /**
     * @param mixed $variants
     * @return array<string, array{url: string, width: int|null}>
     */
    private static function candidates(mixed $variants): array
    {
        if (is_string($variants) && trim($variants) !== '') {
            $decoded = json_decode($variants, true);
            $variants = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($variants)) {
            return [];
        }

        $candidates = [];
        foreach ($variants as $key => $variant) {
            $url = '';
            $width = null;

            if (is_string($variant)) {
                $url = trim($variant);
            } elseif (is_array($variant)) {
                $url = is_scalar($variant['url'] ?? null) ? trim((string) $variant['url']) : '';
                $width = is_numeric($variant['width'] ?? null) && (int) $variant['width'] > 0
                    ? (int) $variant['width']
                    : null;
            }

            if ($url === '' || self::isUnresolvedFileRoute($url)) {
                continue;
            }

            $candidates[strtolower(trim((string) $key))] = [
                'url' => $url,
                'width' => $width,
            ];
        }

        return $candidates;
    }

    /**
     * Keep bounded card candidates out of the source set for full-size images.
     * When no candidate fits the bound, retain the smallest available one.
     *
     * @param array<string, array{url: string, width: int|null}> $candidates
     * @return array<string, array{url: string, width: int|null}>
     */
    private static function limitCandidates(array $candidates, ?int $maxVariantWidth): array
    {
        if ($maxVariantWidth === null || $maxVariantWidth <= 0) {
            return $candidates;
        }

        $bounded = array_filter(
            $candidates,
            static fn (array $candidate): bool => $candidate['width'] === null || $candidate['width'] <= $maxVariantWidth,
        );
        if ($bounded !== []) {
            return $bounded;
        }

        $smallestKey = null;
        $smallestWidth = null;
        foreach ($candidates as $key => $candidate) {
            if ($candidate['width'] === null) {
                continue;
            }
            if ($smallestWidth === null || $candidate['width'] < $smallestWidth) {
                $smallestKey = $key;
                $smallestWidth = $candidate['width'];
            }
        }

        return $smallestKey !== null ? [$smallestKey => $candidates[$smallestKey]] : [];
    }

    /** @return list<string> */
    private static function preferredKeys(?string $preferredVariant): array
    {
        $keys = [];
        $preferredVariant = strtolower(trim((string) $preferredVariant));
        if ($preferredVariant !== '') {
            $keys[] = $preferredVariant;
        }

        foreach (self::PUBLIC_CARD_VARIANTS as $key) {
            if (! in_array($key, $keys, true)) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    private static function isUnresolvedFileRoute(string $url): bool
    {
        return preg_match('#(?:^|/)files/\d+/view(?:[/?]|$)#i', $url) === 1;
    }
}
