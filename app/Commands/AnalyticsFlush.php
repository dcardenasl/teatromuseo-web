<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Services;

final class AnalyticsFlush extends BaseCommand
{
    protected $group = 'Analytics';
    protected $name = 'analytics:flush';
    protected $description = 'Send queued public page views to the CMS';
    protected $usage = 'php spark analytics:flush [--limit 100]';
    protected $arguments = [];
    protected $options = [
        '--limit' => 'Maximum number of queued events to process',
    ];

    /** @param array<string, mixed> $params */
    public function run(array $params = []): void
    {
        $rawLimit = CLI::getOption('limit');
        if (! is_string($rawLimit)) {
            $rawLimit = is_scalar($params['limit'] ?? null) ? (string) $params['limit'] : '';
        }

        $configuredLimit = config('App')->trackingQueueBatchSize;
        $limit = is_numeric($rawLimit) && (int) $rawLimit > 0
            ? (int) $rawLimit
            : $configuredLimit;

        $report = Services::analyticsQueue()->flush($limit);
        if ($report->locked) {
            CLI::write('Analytics queue is already being flushed by another process.', 'yellow');
            return;
        }

        CLI::write(sprintf(
            'Analytics queue: sent=%d retrying=%d failed=%d deferred=%d remaining=%d',
            $report->sent,
            $report->retrying,
            $report->failed,
            $report->deferred,
            $report->remaining,
        ), $report->failed === 0 ? 'green' : 'yellow');
    }
}
