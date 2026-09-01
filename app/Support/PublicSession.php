<?php

declare(strict_types=1);

namespace App\Support;

use CodeIgniter\Session\SessionInterface;

final class PublicSession
{
    /**
     * Return the session only when the browser already has a session cookie.
     *
     * Anonymous GETs must remain fully cacheable and should not pay the cost of
     * session_start(), which also emits no-cache headers and a Set-Cookie.
     */
    public static function current(): ?SessionInterface
    {
        $request = service('request');
        $cookieName = trim((string) config('Session')->cookieName);

        if ($cookieName === '' || ! method_exists($request, 'getCookie')) {
            return null;
        }

        $cookie = $request->getCookie($cookieName);
        if (! is_string($cookie) || $cookie === '') {
            return null;
        }

        return session();
    }
}
