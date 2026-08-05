<?php

declare(strict_types=1);

namespace App\Services;

class SocialLinksService extends BaseSiteService
{
    private const CACHE_TTL = 3600; // 1 hour

    /**
     * Social media networks configuration.
     * Order determines display order in footer.
     *
     * @var array<int, array<string, string>>
     */
    private const SOCIAL_NETWORKS = [
        ['key' => 'social_facebook', 'label' => 'Facebook', 'domain' => 'facebook.com'],
        ['key' => 'social_instagram', 'label' => 'Instagram', 'domain' => 'instagram.com'],
        ['key' => 'social_youtube', 'label' => 'YouTube', 'domain' => 'youtube.com|youtu.be'],
    ];

    /**
     * Get all active social media links with validation.
     *
     * @return array<int, array<string, string>>
     */
    public function getActiveLinks(): array
    {
        $settings = $this->fetchData('public/settings', [], self::CACHE_TTL, 'settings') ?? [];
        $active = [];

        foreach (self::SOCIAL_NETWORKS as $network) {
            $url = $settings[$network['key']] ?? '';

            // Skip if empty, placeholder, or invalid
            if (empty($url) || !$this->isValidSocialUrl($url)) {
                continue;
            }

            $active[] = [
                'key' => $network['key'],
                'label' => $network['label'],
                'url' => $url,
            ];
        }

        return $active;
    }

    /**
     * Get all configured social networks (even inactive ones).
     *
     * @return array<int, array<string, string|bool>>
     */
    public function getAllNetworks(): array
    {
        $settings = $this->fetchData('public/settings', [], self::CACHE_TTL, 'settings') ?? [];
        $networks = [];

        foreach (self::SOCIAL_NETWORKS as $network) {
            $url = $settings[$network['key']] ?? '';
            $isActive = !empty($url) && $this->isValidSocialUrl($url);

            $networks[] = [
                'key' => $network['key'],
                'label' => $network['label'],
                'url' => $url,
                'is_active' => $isActive,
            ];
        }

        return $networks;
    }

    /**
     * Validate if URL is a valid social media link (not placeholder, valid URL format).
     */
    private function isValidSocialUrl(string $url): bool
    {
        // Skip placeholders
        if (str_starts_with($url, '[') || str_ends_with($url, ']')) {
            return false;
        }

        // Must start with http:// or https://
        if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
            return false;
        }

        // Validate URL format
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        // Must not be a default base domain URL (e.g., must contain a profile path)
        $path = parse_url($url, PHP_URL_PATH);
        if (!is_string($path) || trim($path, '/') === '') {
            return false;
        }

        return true;
    }
}
