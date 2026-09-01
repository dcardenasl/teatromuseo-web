<?php

declare(strict_types=1);

namespace App\ViewModels\Blocks\Listing;

use App\ViewModels\Blocks\VideoPlayerViewModel;

/**
 * Normalizes the optional video slot used by collection cards.
 *
 * The listing and grid blocks share the same CMS payload, so the provider
 * validation and derived URLs live here instead of being reimplemented in
 * either view. Invalid or incomplete video data is deliberately treated as
 * absent; the card can then use its normal image and entry link.
 */
final class ListingVideoPresentation
{
    /**
     * @param array<string, mixed>|null $video
     * @return array{provider: string, id: string, url: string, embed_url: string, poster_url: string}|null
     */
    public static function normalize(?array $video): ?array
    {
        if ($video === null) {
            return null;
        }

        $provider = strtolower(trim((string) ($video['provider'] ?? '')));
        $id = trim((string) ($video['id'] ?? ''));
        $url = trim((string) ($video['url'] ?? ''));

        if ($provider === 'youtube') {
            if (preg_match('/^[A-Za-z0-9_-]{11}$/', $id) !== 1) {
                return null;
            }

            // Build provider URLs from the validated ID instead of trusting
            // an arbitrary CMS URL. This keeps the iframe origin and poster
            // in lockstep even when legacy payloads contain a stale URL.
            $canonicalUrl = 'https://www.youtube.com/watch?v=' . $id;

            return [
                'provider' => $provider,
                'id' => $id,
                'url' => $canonicalUrl,
                'embed_url' => VideoPlayerViewModel::embedUrl($canonicalUrl, false, false),
                'poster_url' => 'https://i.ytimg.com/vi/' . $id . '/hqdefault.jpg',
            ];
        }

        if ($provider !== 'vimeo' || $id === '' || preg_match('#^https://#i', $url) !== 1) {
            return null;
        }

        $embedUrl = VideoPlayerViewModel::embedUrl($url, false, false);
        if ($embedUrl === '') {
            return null;
        }

        return [
            'provider' => $provider,
            'id' => $id,
            'url' => $url,
            'embed_url' => $embedUrl,
            'poster_url' => '',
        ];
    }
}
