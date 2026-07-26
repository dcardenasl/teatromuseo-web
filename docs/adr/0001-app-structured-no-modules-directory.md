# 1. Stay app-structured; no `Modules/` directory

Date: 2026-07-18 (retroactively documenting a decision already in force — see
`CLAUDE.md` "Common Pitfalls")

## Status

Accepted

## Context

`ci4-website-builder-admin` organizes feature code under a `Modules/{Name}/`
tree inside its own `app/` (Controllers, Services, Requests, Language, Config
per module) because it has
many independent, permission-gated CRUD surfaces that benefit from that
isolation. `ci4-website-builder-web` is structurally different: it is a
single public-facing rendering pipeline (`PageController::resolve()` → block
rendering → ViewModels), not a collection of independent admin resources.
Copying the admin's module convention here would add directory indirection
without a matching need for per-feature isolation — there is really one
"feature" (rendering CMS content publicly), organized by technical layer
(Controllers, Services, ViewModels, Views/blocks) rather than by business
resource.

## Decision

Keep `ci4-website-builder-web` app-structured: `app/Controllers/`,
`app/Services/`, `app/ViewModels/Blocks/`, `app/Views/blocks/`, etc. Do not
introduce a `Modules/` tree here, even as the block/ViewModel count grows.

## Consequences

- **Positive:** One consistent place to look for each technical layer; no
  decision needed about "which module does this belong to" for
  cross-cutting concerns like `PageController`'s path resolution, which
  isn't naturally scoped to one resource anyway.
- **Negative:** As the ViewModel list grows (currently 11 mapped block keys
  in `BlockRenderer::VIEW_MODELS`), `app/ViewModels/Blocks/` will keep
  growing flat rather than being partitioned. Acceptable trade-off as long as
  each ViewModel stays small and focused (`AbstractBlockViewModel::vars()`).
- **Guardrail:** if a future session proposes adding a `Modules/` tree here
  "for consistency with admin," that's solving a problem this repo doesn't
  have — check this ADR first.
