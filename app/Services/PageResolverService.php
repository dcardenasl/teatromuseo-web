<?php

declare(strict_types=1);

namespace App\Services;

class PageResolverService extends BaseSiteService
{
    /**
     * Resolve the redirect check and the page-by-path lookup in one request
     * using the composite `page-bootstrap` PublicRead endpoint (ADR 006 in
     * the CMS domain) — these used to be two separate calls in one
     * `multiGet()` batch; they're now one HTTP round trip. Returns both
     * results; caller decides which takes precedence (redirect first).
     *
     * @return array{redirect: ?array<string, mixed>, page: ?array<string, mixed>}
     */
    public function resolveRedirectAndPage(
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

        $result = $this->fetchData(
            "public-read/{$lang}/page-bootstrap/{$path}",
            $query,
            $preview ? 0 : 300,
            'pages',
        ) ?? [];

        $redirect = is_array($result['redirect'] ?? null) ? $result['redirect'] : null;
        $page = is_array($result['page'] ?? null) ? $result['page'] : null;

        return ['redirect' => $redirect, 'page' => $page];
    }
}
