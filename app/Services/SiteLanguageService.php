<?php

declare(strict_types=1);

namespace App\Services;

final class SiteLanguageService extends BaseSiteService
{
    // Language activation is configuration, not content. It must become
    // effective on the next request after an admin change, without waiting
    // for a public-content cache window to expire.
    private const CACHE_TTL = 0;

    /** @var list<array{code: string, name: string, native_name: string, is_default: bool}>|null */
    private ?array $activeLanguages = null;

    /** @return list<array{code: string, name: string, native_name: string, is_default: bool}> */
    public function getActive(): array
    {
        if ($this->activeLanguages !== null) {
            return $this->activeLanguages;
        }

        $languages = $this->fetchData('cms/public/languages', [], self::CACHE_TTL, 'languages');
        if ($languages === null) {
            $this->activeLanguages = [];

            return $this->activeLanguages;
        }

        $this->activeLanguages = array_values(array_filter(array_map(static function (mixed $language): ?array {
            if (! is_array($language)) {
                return null;
            }

            $code = strtolower(trim((string) ($language['code'] ?? '')));
            if ($code === '' || ! preg_match('/^[a-z]{2,3}(?:-[a-z]{2,4})?$/', $code)) {
                return null;
            }

            return [
                'code' => $code,
                'name' => (string) ($language['name'] ?? $code),
                'native_name' => (string) ($language['native_name'] ?? $code),
                'is_default' => (bool) ($language['is_default'] ?? false),
            ];
        }, $languages)));

        return $this->activeLanguages;
    }

    /** @return list<string> */
    public function getCodes(): array
    {
        return array_values(array_unique(array_map(
            static fn (array $language): string => $language['code'],
            $this->getActive()
        )));
    }

    public function getDefaultCode(): ?string
    {
        foreach ($this->getActive() as $language) {
            if ($language['is_default']) {
                return $language['code'];
            }
        }

        return $this->getCodes()[0] ?? null;
    }
}
