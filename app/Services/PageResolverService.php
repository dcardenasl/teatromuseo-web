<?php

declare(strict_types=1);

namespace App\Services;

class PageResolverService extends BaseSiteService
{
    /**
     * Resolve redirect and page in parallel using multiGet.
     * Returns both results; caller decides which takes precedence (redirect first).
     *
     * @return array{redirect: ?array<string, mixed>, page: ?array<string, mixed>}
     */
    public function parallelResolveRedirectAndPage(
        string $path,
        string $lang,
        bool $preview,
        ?string $previewExpires,
        ?string $previewSig
    ): array {
        $query = [];
        if ($preview) {
            $query['preview'] = '1';
            if ($previewExpires !== null) {
                $query['preview_expires'] = $previewExpires;
            }
            if ($previewSig !== null) {
                $query['preview_sig'] = $previewSig;
            }
        }

        $results = $this->apiClient->multiGet([
            ['path' => "public/redirects/{$path}", 'cacheTtl' => 3600, 'scope' => 'redirects'],
            ['path' => "public-read/{$lang}/pages/{$path}", 'query' => $query, 'cacheTtl' => $preview ? 0 : 300, 'scope' => 'pages'],
        ]);

        $redirect = null;
        if (($results[0]['ok'] ?? false) && is_array($results[0]['data'] ?? null)) {
            $redirect = $results[0]['data'];
        }

        $page = null;
        if (($results[1]['ok'] ?? false) && is_array($results[1]['data'] ?? null)) {
            $page = $results[1]['data'];
        }

        return ['redirect' => $redirect, 'page' => $page];
    }
}
