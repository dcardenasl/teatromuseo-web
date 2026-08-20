<?php

declare(strict_types=1);

namespace App\Commands;

use App\Libraries\CommandLock;
use App\PageDelivery\PageDeliveryRequest;
use App\PageDelivery\PublicSnapshotManifest;
use App\PageDelivery\SnapshotBuildResult;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Services;
use JsonException;

/**
 * Warm the explicit public snapshot manifest or synchronous BFF delivery.
 *
 * This command is intentionally serial. It is suitable for a deployment hook
 * or a single cron invocation on the current shared-hosting plan.
 */
final class CacheWarmup extends BaseCommand
{
    protected $group = 'Cache';
    protected $name = 'cache:warmup';
    protected $description = 'Warm public snapshots or BFF delivery serially';
    protected $usage = 'php spark cache:warmup [--locale es] [--route home] [--force]';
    protected $arguments = [];
    protected $options = [
        '--locale' => 'Specific locale from the configured manifest',
        '--route' => 'Specific route from the configured manifest',
        '--force' => 'Rebuild even when the active snapshot is still fresh',
    ];

    /** @param array<string, mixed> $params */
    public function run(array $params = []): void
    {
        $lock = new CommandLock(WRITEPATH . 'cache/locks/public-cache-warmup.lock');
        if (! $lock->acquire()) {
            CLI::write('Public cache warm-up is already running; this invocation was skipped.', 'yellow');

            return;
        }

        try {
            $this->runLocked($params);
        } finally {
            $lock->release();
        }
    }

    /** @param array<string, mixed> $params */
    private function runLocked(array $params): void
    {
        $locale = $this->option('locale', $params);
        $route = $this->option('route', $params);
        $force = CLI::getOption('force') !== null || isset($params['force']);
        $requests = (new PublicSnapshotManifest())->requests($locale !== '' ? $locale : null, $route !== '' ? $route : null);

        CLI::write(sprintf('Snapshot warm-up: %d manifest entries (serial)', count($requests)), 'cyan');
        if ($requests === []) {
            CLI::error('The selected locale/route is not present in the configured manifest.');
            return;
        }

        if ((Services::pageSnapshotStore()->status()['enabled'] ?? false) !== true) {
            $this->warmSynchronousDelivery($requests);
            return;
        }

        $startedAt = microtime(true);
        $builder = Services::snapshotBuilder();
        $report = [];
        $successful = 0;

        foreach ($requests as $request) {
            $result = $builder->build($request, $force);
            if ($result->isSuccessful()) {
                $successful++;
            }
            $report[] = [
                'locale' => $request->locale,
                'route' => $request->route,
                'state' => $result->state,
                'revision' => $result->revision,
                'message' => $result->message,
            ];
            $this->writeResult($request->locale, $request->route, $result);
        }

        $report['summary'] = [
            'generated_at' => gmdate(DATE_ATOM),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'total' => count($requests),
            'successful_or_skipped' => $successful,
            'force' => $force,
        ];
        $this->writeReport($report);

        CLI::newLine();
        CLI::write(sprintf('Warm-up completed: %d/%d successful or skipped.', $successful, count($requests)), $successful === count($requests) ? 'green' : 'yellow');
    }

    /** @param list<PageDeliveryRequest> $requests */
    private function warmSynchronousDelivery(array $requests): void
    {
        $config = config('App');
        $previousMode = $config->pageDeliveryMode;
        $language = service('language');
        $previousLanguageLocale = $language->getLocale();
        $previousIntlLocale = \Locale::getDefault();
        $successful = 0;
        $report = [];

        CLI::write(sprintf('BFF warm-up: composing %d route(s) synchronously', count($requests)), 'cyan');

        // Reuse the production composition seam so the warm-up follows the
        // exact homepage dependency graph (layout, forms and dynamic blocks)
        // instead of maintaining a second, inevitably stale endpoint list.
        $config->pageDeliveryMode = 'sync';
        try {
            foreach ($requests as $request) {
                $locale = $request->locale;
                // CLIRequest has no setLocale(). Keep the translation service
                // and ICU's default locale aligned; CLIRequest::getLocale()
                // reads the latter and downstream services rely on both.
                $language->setLocale($locale);
                \Locale::setDefault($locale);

                try {
                    $delivery = Services::pageDelivery(false)->deliver($request);
                } catch (\Throwable $exception) {
                    $report[] = [
                        'locale' => $locale,
                        'route' => $request->route,
                        'state' => 'failed',
                        'message' => $exception->getMessage(),
                    ];
                    CLI::write(sprintf('  WARN %s/%s composition failed: %s', $locale, $request->route, $exception->getMessage()), 'yellow');
                    continue;
                }

                if ($delivery->isAvailable() && ($delivery->source['stale'] ?? false) !== true) {
                    $successful++;
                    $report[] = [
                        'locale' => $locale,
                        'route' => $request->route,
                        'state' => 'synchronous_warmed',
                        'status' => $delivery->status,
                    ];
                    CLI::write(sprintf('  OK %s/%s (HTTP %d)', $locale, $request->route, $delivery->status), 'green');
                    continue;
                }

                $report[] = [
                    'locale' => $locale,
                    'route' => $request->route,
                    'state' => 'stale_or_failed',
                    'status' => $delivery->status,
                ];
                CLI::write(sprintf('  WARN %s/%s (HTTP %d)', $locale, $request->route, $delivery->status), 'yellow');
            }
        } finally {
            $config->pageDeliveryMode = $previousMode;
            $language->setLocale($previousLanguageLocale);
            \Locale::setDefault($previousIntlLocale);
        }

        $report['summary'] = [
            'generated_at' => gmdate(DATE_ATOM),
            'total' => count($requests),
            'successful' => $successful,
            'mode' => 'sync',
        ];
        $this->writeReport($report);

        CLI::write(sprintf('Warm-up completed: %d/%d routes composed.', $successful, count($requests)), $successful === count($requests) ? 'green' : 'yellow');
    }

    /** @param array<string, mixed> $params */
    private function option(string $name, array $params): string
    {
        $option = CLI::getOption($name);
        if (is_string($option)) {
            return strtolower(trim($option, " /\t\n\r\0\x0B"));
        }

        return is_string($params[$name] ?? null) ? strtolower(trim((string) $params[$name])) : '';
    }

    private function writeResult(string $locale, string $route, SnapshotBuildResult $result): void
    {
        $label = sprintf('%s/%s: %s', $locale, $route, $result->state);
        if ($result->isSuccessful()) {
            CLI::write('  OK ' . $label, 'green');
            return;
        }

        CLI::write('  WARN ' . $label . ($result->message !== null ? ' — ' . $result->message : ''), 'yellow');
    }

    /** @param array<int|string, mixed> $report */
    private function writeReport(array $report): void
    {
        $directory = (string) config('App')->pageSnapshotDirectory;
        $directory = $directory !== '' ? $directory . '/reports' : WRITEPATH . 'cache/snapshot-warmup';
        if (! is_dir($directory) && ! @mkdir($directory, 0750, true) && ! is_dir($directory)) {
            CLI::write('  WARN report directory could not be created.', 'yellow');
            return;
        }

        try {
            $contents = json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        } catch (JsonException) {
            CLI::write('  WARN warm-up report could not be serialized.', 'yellow');
            return;
        }

        $path = $directory . '/latest.json';
        $temporary = @tempnam($directory, '.warmup-');
        if ($temporary === false || @file_put_contents($temporary, $contents, LOCK_EX) !== strlen($contents) || ! @rename($temporary, $path)) {
            if (is_string($temporary) && is_file($temporary)) {
                @unlink($temporary);
            }
            CLI::write('  WARN warm-up report could not be published atomically.', 'yellow');
        }
    }
}
