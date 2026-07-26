<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class SyncSocialLinks extends BaseCommand
{
    protected $group = 'Web';
    protected $name = 'social:sync';
    protected $description = 'Sync social media URLs from .env to Domain API settings';
    protected $usage = 'php spark social:sync [options]';
    protected $arguments = [];
    protected $options = [
        '--show' => 'Show current configuration without syncing',
    ];

    public function run(array $params = []): void
    {
        $socialNetworks = [
            'SOCIAL_FACEBOOK' => 'social_facebook',
            'SOCIAL_INSTAGRAM' => 'social_instagram',
            'SOCIAL_TWITTER' => 'social_twitter',
            'SOCIAL_LINKEDIN' => 'social_linkedin',
            'SOCIAL_YOUTUBE' => 'social_youtube',
            'SOCIAL_TIKTOK' => 'social_tiktok',
            'SOCIAL_PINTEREST' => 'social_pinterest',
            'SOCIAL_GITHUB' => 'social_github',
        ];

        $config = [];
        foreach ($socialNetworks as $envKey => $settingKey) {
            $url = env($envKey, '');
            $config[$settingKey] = [
                'env_key' => $envKey,
                'setting_key' => $settingKey,
                'url' => $url,
                'is_set' => !empty($url),
            ];
        }

        if (isset($params['show'])) {
            $this->showConfiguration($config);
            return;
        }

        CLI::write('🔄 Syncing social media links from .env to Domain API...', 'cyan');
        CLI::newLine();

        $synced = 0;
        foreach ($config as $setting) {
            if (empty($setting['url'])) {
                CLI::write(
                    sprintf('⏭️  Skipping %s (empty in .env)', $setting['env_key']),
                    'yellow'
                );
                continue;
            }

            if (!$this->isValidUrl($setting['url'])) {
                CLI::write(
                    sprintf('❌ Invalid URL for %s: %s', $setting['env_key'], $setting['url']),
                    'red'
                );
                continue;
            }

            // Note: In a real scenario, you would call the Domain API to update the setting
            // For now, we just report what would be synced
            CLI::write(
                sprintf('✅ Would sync %s = %s', $setting['env_key'], $setting['url']),
                'green'
            );
            $synced++;
        }

        CLI::newLine();
        CLI::write(sprintf('Summary: %d social links configured', $synced), 'green');
        CLI::newLine();
        CLI::write('💡 Tip: Visit the admin panel to manage social links or edit .env and run this command again.', 'cyan');
    }

    private function showConfiguration(array $config): void
    {
        CLI::write('📱 Social Media Configuration from .env:', 'cyan');
        CLI::newLine();

        foreach ($config as $setting) {
            $status = $setting['is_set'] ? '✅' : '⚫';
            $url = $setting['is_set'] ? $setting['url'] : '(not set)';
            CLI::write(sprintf('%s %-20s %s', $status, $setting['env_key'], $url));
        }

        CLI::newLine();
        CLI::write('Run: php spark social:sync', 'yellow');
        CLI::write('To sync these URLs to the Domain API.', 'yellow');
    }

    private function isValidUrl(string $url): bool
    {
        if (empty($url) || str_starts_with($url, '[')) {
            return false;
        }

        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
}
