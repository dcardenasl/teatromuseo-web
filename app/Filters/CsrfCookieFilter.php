<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Security;

/**
 * Exposes a cache-safe CSRF token copy to the public form JavaScript.
 *
 * CI4's native CSRF filter keeps the authoritative token in an HttpOnly
 * cookie. This filter emits a second, readable copy after PageCache has
 * persisted the response, so the token is never frozen into cached HTML or
 * cached response headers. The native filter still validates the request
 * against the authoritative cookie and rotates it after a successful POST.
 */
final class CsrfCookieFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null): RequestInterface
    {
        return $request;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): ResponseInterface
    {
        $token = service('security')->getHash();

        if (! is_string($token) || $token === '') {
            return $response;
        }

        /** @var Security $securityConfig */
        $securityConfig = config('Security');
        $cookieConfig   = config('Cookie');

        $response->setCookie(
            $securityConfig->readableCookieName,
            $token,
            $securityConfig->expires,
            '',
            '/',
            '',
            $cookieConfig->secure,
            false,
            $cookieConfig->samesite,
        );

        return $response;
    }
}
