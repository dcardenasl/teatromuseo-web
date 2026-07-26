# 2. Stale-cache fallback covers transport/5xx failures only, never 404

Date: 2026-07-18 (retroactively documenting a decision already in force — see
`CLAUDE.md` "API Client And Services" and "Common Pitfalls")

## Status

Accepted

## Context

`WebApiClient` caches Domain responses under `web_api_v{N}_{scope}_{md5}`
(fresh) and `web_api_stale_v{N}_{scope}_{md5}` (stale-but-usable) keys, and
falls back to the stale copy when Domain is unreachable — this keeps the
public site up during a Domain outage instead of 500ing every page. The
question this ADR answers: should that same stale fallback also cover a
`404` from Domain (e.g. "this page/entry doesn't exist")?

It's tempting to treat any non-2xx as "Domain trouble, serve what we have,"
but a `404` is not a transport failure — it is Domain correctly telling Web
that the resource is genuinely gone or was never there (unpublished,
deleted, slug changed without a redirect). Silently serving a stale cached
copy of content that no longer exists would show visitors — and worse,
search engines — content the site owner just removed.

## Decision

The stale-cache fallback in `WebApiClient` triggers **only** for transport
failures (`status 0`, e.g. connection refused/timeout) and upstream `5xx`
responses. A `4xx` from Domain, `404` included, is treated as an authoritative
answer and passed through as-is — `PageController::resolve()`'s normal
not-found path handles it, with no cache fallback in between.

## Consequences

- **Positive:** The public site never shows removed/unpublished content just
  because it happens to still be cached. SEO and content-owner trust are
  protected.
- **Negative:** A brief window right after deleting a Page/Entry in the admin
  — before `CacheInvalidator` clears the corresponding key — could show a
  cached 200 for content that was just deleted, if the delete didn't route
  through the invalidation webhook. This is a cache-invalidation correctness
  concern, not something the stale-fallback policy should try to paper over.
- **Guardrail:** any change to `WebApiClient`'s stale-fallback condition must
  keep `4xx` (not just `404` specifically) out of the fallback path — see the
  "Manual Smoke Checks" stale-fallback scenario in `CLAUDE.md` for how this is
  verified by hand.
