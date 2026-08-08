<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Services;

class CacheWarmup extends BaseCommand
{
    protected $group       = 'Cache';
    protected $name        = 'cache:warmup';
    protected $description = 'Pre-warm public site API cache for global settings, menus, and canonical pages';
    protected $usage       = 'php spark cache:warmup [options]';
    protected $arguments   = [];
    protected $options     = [
        '--locale' => 'Specific locale to pre-warm (defaults to all supported locales)',
    ];

    public function run(array $params = []): void
    {
        CLI::write('🔥 Starting public site API cache warmup...', 'cyan');
        CLI::newLine();

        $supportedLocales = config('App')->supportedLocales;
        $targetLocale     = is_string($params['locale'] ?? null) ? strtolower(trim((string) $params['locale'])) : '';
        $locales          = ($targetLocale !== '' && in_array($targetLocale, $supportedLocales, true))
            ? [$targetLocale]
            : $supportedLocales;

        $apiClient = Services::webApiClient();

        // Prepare multiGet requests list for core resources
        $requests = [
            ['path' => 'public/settings', 'scope' => 'settings'],
            ['path' => 'public/social-links', 'scope' => 'settings'],
            ['path' => 'public/redirects/resolve', 'query' => ['path' => ''], 'scope' => 'redirects'],
        ];

        foreach ($locales as $locale) {
            $requests[] = ['path' => 'public/' . $locale . '/menus/main-menu', 'scope' => 'menus'];
            $requests[] = ['path' => 'public/' . $locale . '/collections', 'scope' => 'collections'];
            $requests[] = ['path' => 'public/' . $locale . '/pages/by-slug/home', 'scope' => 'pages'];
        }

        CLI::write(sprintf('🌐 Dispatching batch warming request for %d core endpoints...', count($requests)), 'yellow');
        $results = $apiClient->multiGet($requests);

        $successCount = 0;
        foreach ($results as $index => $res) {
            $reqPath = $requests[$index]['path'] ?? 'unknown';
            if ($res['ok']) {
                $successCount++;
                CLI::write(sprintf('  ✅ Pre-warmed: %s (Status: %d)', $reqPath, $res['status']), 'green');
            } else {
                CLI::write(sprintf('  ⚠️ Warning for %s: Status %d', $reqPath, $res['status']), 'yellow');
            }
        }

        CLI::newLine();
        CLI::write(sprintf('✨ Cache warmup completed! (%d/%d resources cached)', $successCount, count($requests)), 'green');
        CLI::newLine();
    }
}
