<?php

declare(strict_types=1);

namespace App\Services;

use App\Libraries\WebApiClientInterface;

/**
 * Aggregates application-visible diagnostics for the public-read capacity audit.
 *
 * Provider-only limits are deliberately represented as unavailable instead of
 * being inferred from application behavior.
 */
final class PublicReadDiagnosticsService
{
    public function __construct(
        private readonly WebApiClientInterface $cmsClient,
        private readonly WebApiClientInterface $catalogClient,
        private readonly WebApiClientInterface $eventClient,
    ) {
    }

    /** @return array<string, mixed> */
    public function report(): array
    {
        return [
            'schema'               => 'public-read-diagnostics.v1',
            'generated_at'         => gmdate('c'),
            'web'                  => [
                'application' => $this->runtime(),
                'cache'       => $this->cache(),
            ],
            'domains'              => [
                'cms'     => $this->probe('cms', $this->cmsClient),
                'catalog' => $this->probe('catalog', $this->catalogClient),
                'events'  => $this->probe('events', $this->eventClient),
            ],
            'provider_visibility'  => [
                'php_fpm_pool' => [
                    'status' => 'available_if_fpm_status_is_enabled',
                    'source' => 'fpm_get_status',
                ],
                'mysql_global_status' => [
                    'status' => 'available_from_domain_database_users',
                    'source' => 'SHOW GLOBAL STATUS',
                ],
                'cache_topology' => [
                    'status' => 'not_verifiable_from_application',
                    'source' => 'provider_configuration_required',
                ],
                'upstream_508_limits' => [
                    'status' => 'not_verifiable_from_application',
                    'source' => 'provider_configuration_required',
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function probe(string $name, WebApiClientInterface $client): array
    {
        $startedAt = hrtime(true);
        $result = $client->get('diagnostics/public-read', [], 0, 'diagnostics');
        $data = is_array($result['data'] ?? null) ? $result['data'] : null;
        $status = $result['ok'] ? 'healthy' : 'unavailable';

        if ($status === 'healthy') {
            $database = is_array($data['database'] ?? null) ? $data['database'] : [];
            $cache = is_array($data['cache'] ?? null) ? $data['cache'] : [];
            if (($database['status'] ?? null) === 'degraded' || ($cache['probe'] ?? null) === 'failed') {
                $status = 'degraded';
            }
        }

        return [
            'name'             => $name,
            'status'           => $status,
            'http_status'      => $result['status'],
            'response_time_ms' => round((hrtime(true) - $startedAt) / 1_000_000, 2),
            'data'             => $data,
            'error'            => $result['ok'] ? null : 'diagnostics_unavailable',
        ];
    }

    /** @return array<string, mixed> */
    private function runtime(): array
    {
        $load = function_exists('sys_getloadavg') ? sys_getloadavg() : false;

        return [
            'php_version'        => PHP_VERSION,
            'sapi'               => PHP_SAPI,
            'memory_limit'       => (string) ini_get('memory_limit'),
            'memory_usage_bytes' => memory_get_usage(true),
            'peak_memory_bytes'  => memory_get_peak_usage(true),
            'max_execution_time' => (int) ini_get('max_execution_time'),
            'load_average'       => is_array($load) ? $load : null,
            'extensions'         => $this->loadedExtensions(),
            'fpm'                => $this->fpmStatus(),
        ];
    }

    /** @return list<string> */
    private function loadedExtensions(): array
    {
        $known = ['curl', 'intl', 'mysqli', 'opcache', 'pdo', 'redis', 'memcached'];

        return array_values(array_filter(
            $known,
            static fn (string $extension): bool => extension_loaded($extension),
        ));
    }

    /** @return array<string, mixed> */
    private function fpmStatus(): array
    {
        if (! function_exists('fpm_get_status')) {
            return [
                'status' => 'unavailable',
                'reason' => 'fpm_get_status_not_available',
            ];
        }

        $raw = call_user_func('fpm_get_status');
        if (! is_array($raw)) {
            return [
                'status' => 'unavailable',
                'reason' => 'fpm_status_not_enabled',
            ];
        }

        $keys = [
            'accepted_conn',
            'listen_queue',
            'active_processes',
            'total_processes',
            'max_active_processes',
            'max_children_reached',
            'slow_requests',
        ];
        $metrics = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $raw)) {
                $metrics[$key] = is_numeric($raw[$key]) ? (int) $raw[$key] : $raw[$key];
            }
        }

        return [
            'status'  => 'available',
            'metrics' => $metrics,
        ];
    }

    /** @return array<string, mixed> */
    private function cache(): array
    {
        try {
            $cache = \Config\Services::cache();
            $key = 'public_read_diagnostics_probe_' . bin2hex(random_bytes(8));
            $saved = $cache->save($key, 'ok', 5);
            $read = $cache->get($key) === 'ok';
            $deleted = $cache->delete($key);

            return [
                'configured_handler' => (string) config('Cache')->handler,
                'active_handler'     => get_class($cache),
                'probe'              => $saved && $read && $deleted ? 'passed' : 'degraded',
                'topology'           => 'not_verifiable_from_application',
            ];
        } catch (\Throwable) {
            return [
                'configured_handler' => (string) config('Cache')->handler,
                'active_handler'     => 'unavailable',
                'probe'              => 'failed',
                'topology'           => 'not_verifiable_from_application',
            ];
        }
    }
}
