<?php

declare(strict_types=1);

namespace App\Filters;

use App\Support\RequestContext;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

final class RequestTelemetryFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null): RequestInterface
    {
        return $request;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): ResponseInterface
    {
        $summary = RequestContext::outboundSummary();
        $payload = [
            'component'          => 'teatromuseo-web',
            'event'              => 'web_request',
            'request_id'         => RequestContext::requestId(),
            'path'               => $request->getUri()->getPath(),
            'locale'             => service('request')->getLocale(),
            'duration_ms'        => round(RequestContext::elapsedMilliseconds(), 2),
            'status'             => $response->getStatusCode(),
            'response_bytes'     => strlen((string) $response->getBody()),
            'outbound_count'     => $summary['count'],
            'outbound_duration_ms' => $summary['duration_ms'],
            'outbound_payload_bytes' => $summary['payload_bytes'],
            'cache_hits'         => $summary['cache_hits'],
            'stale_count'        => $summary['stale'],
            'timeout_count'      => $summary['timeouts'],
            'source_revisions'   => $summary['source_revisions'],
            'snapshot_revisions' => $summary['snapshot_revisions'],
        ];

        log_message(
            'info',
            '[web-request] ' . (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        );

        return $response;
    }
}
