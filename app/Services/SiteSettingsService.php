<?php

declare(strict_types=1);

namespace App\Services;

class SiteSettingsService extends BaseSiteService
{
    private const CACHE_TTL = 3600; // 1 hour

    /**
     * Get all public settings as a key-value array.
     *
     * @return array<string, mixed>
     */
    public function getAll(): array
    {
        return $this->fetchData('public/settings', [], self::CACHE_TTL, 'settings') ?? [];
    }

    /**
     * Get a single setting by key with optional default value.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->getAll();

        return $all[$key] ?? $default;
    }

    /**
     * Get contact form defaults from public settings.
     *
     * @return array{
     *     contact_admin_email: string,
     *     contact_from_email: string,
     *     contact_site_name: string,
     *     contact_autoreply_message: string
     * }
     */
    public function getContactDefaults(): array
    {
        $all = $this->getAll();

        return [
            'contact_admin_email' => (string) ($all['contact_admin_email'] ?? ''),
            'contact_from_email' => (string) ($all['contact_from_email'] ?? ''),
            'contact_site_name' => (string) ($all['contact_site_name'] ?? ''),
            'contact_autoreply_message' => (string) ($all['contact_autoreply_message'] ?? ''),
        ];
    }
}
