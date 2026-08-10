<?php

declare(strict_types=1);

namespace App\Commands;

use App\PageDelivery\PublicSnapshotManifest;
use App\PageDelivery\SnapshotBuildResult;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Services;
use JsonException;

/**
 * Warm only the explicit public snapshot manifest.
 *
 * This command is intentionally serial. It is suitable for a deployment hook
 * or a single cron invocation on the current shared-hosting plan.
 */
final class CacheWarmup extends BaseCommand
{
    protected $group = 'Cache';
    protected $name = 'cache:warmup';
    protected $description = 'Build the explicit public snapshot manifest serially';
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
        $locale = $this->option('locale', $params);
        $route = $this->option('route', $params);
        $force = CLI::getOption('force') !== null || isset($params['force']);
        $requests = (new PublicSnapshotManifest())->requests($locale !== '' ? $locale : null, $route !== '' ? $route : null);

        CLI::write(sprintf('Snapshot warm-up: %d manifest entries (serial)', count($requests)), 'cyan');
        if ($requests === []) {
            CLI::error('The selected locale/route is not present in the configured manifest.');
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
