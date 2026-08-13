<?php

declare(strict_types=1);

namespace App\Services\BlockPrefetch;

/**
 * Reads the current HTTP request's query string safely. Every prefetch
 * collaborator that needs `?page=`, `?preview=1`, etc. goes through this
 * seam instead of calling `service('request')` directly, so the "request
 * may not exist" case (CLI, queued jobs) is handled once.
 */
final class RequestQueryReader
{
    public function value(string $key, string $default = ''): string
    {
        try {
            $value = service('request')->getGet($key);

            return is_scalar($value) ? trim((string) $value) : $default;
        } catch (\Throwable) {
            return $default;
        }
    }

    public function isPreview(): bool
    {
        return in_array(strtolower($this->value('preview')), ['1', 'true', 'yes'], true);
    }

    /** @return array<string, string> */
    public function previewQuery(): array
    {
        if (! $this->isPreview()) {
            return [];
        }

        $query = ['preview' => '1'];
        foreach (['preview_expires', 'preview_sig'] as $key) {
            $value = $this->value($key);
            if ($value !== '') {
                $query[$key] = $value;
            }
        }

        return $query;
    }
}
