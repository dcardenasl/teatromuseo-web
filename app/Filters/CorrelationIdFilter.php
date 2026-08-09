<?php

declare(strict_types=1);

namespace App\Filters;

use App\Support\RequestContext;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

final class CorrelationIdFilter implements FilterInterface
{
    private const HEADER = 'X-Request-ID';

    public function before(RequestInterface $request, $arguments = null): RequestInterface
    {
        $incoming = trim($request->getHeaderLine(self::HEADER));
        $requestId = $this->isWellFormed($incoming) ? $incoming : $this->generateUuidV4();

        $request->setHeader(self::HEADER, $requestId);
        RequestContext::begin($requestId);

        return $request;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): ResponseInterface
    {
        $requestId = RequestContext::requestId();
        if ($requestId !== null) {
            $response->setHeader(self::HEADER, $requestId);
        }

        return $response;
    }

    private function isWellFormed(string $candidate): bool
    {
        return $candidate !== '' && preg_match('/^[A-Za-z0-9._:+\-]{8,128}$/', $candidate) === 1;
    }

    private function generateUuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }
}
