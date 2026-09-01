<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\PageCache as BasePageCache;
use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;

/**
 * CI4's native PageCache::before() looks up the shared ResponseCache by
 * method+URI alone, with no regard for cookies, and returns a hit before the
 * controller (and therefore BasePublicWebController::render()'s own
 * session-vs-cache decision) ever runs. A stale, session-less entry cached
 * from an earlier anonymous visit would then be replayed to a visitor who
 * just submitted a form and has visitor-specific flashdata waiting — the
 * exact case render() was made to stop *writing*, but it never runs to
 * prevent the *read*.
 *
 * A session cookie only exists on this site after a form POST engaged the
 * CSRF/session machinery (see PublicSession::current()), so its presence
 * here is a reliable signal to skip the cache lookup and let the request
 * reach the controller for a fresh, uncached render.
 */
final class SessionAwarePageCache extends BasePageCache
{
    /**
     * @param array<mixed>|null $arguments
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        assert($request instanceof CLIRequest || $request instanceof IncomingRequest);

        if ($request instanceof IncomingRequest && $this->hasSessionCookie($request)) {
            return null;
        }

        return parent::before($request, $arguments);
    }

    private function hasSessionCookie(IncomingRequest $request): bool
    {
        $cookieName = trim((string) config('Session')->cookieName);
        if ($cookieName === '') {
            return false;
        }

        $cookie = $request->getCookie($cookieName);

        return is_string($cookie) && $cookie !== '';
    }
}
