# Single block prefetch before render

Date: 2026-08-08

## Status

Accepted — superseded for public page delivery on 2026-08-15 by the BFF
`page-resolve` contract (see ADR 008). The decision below remains as the
historical rationale for moving composition out of Web rendering.

## Context

The public web had two overlapping prefetch contracts: `SmartPrefetchService`
returned resources by type/ID, while `BlockPrefetchService` returned list data
by block path. They could both run for the same page, and a missing block-level
prefetch caused ViewModels to perform sequential HTTP calls during rendering.
`collection_listing` also needs request-specific entries, pagination, facets and
CMS collection metadata.

## Decision

Use one block-prefetch pipeline for every dynamic block. A single planner builds
source-owned requests from the block instance, locale, preview state and
current query; the executor deduplicates identical requests and runs independent
CMS, catalog and event requests concurrently through a shared transport. Each
block path receives an explicit result before `BlockRenderer` starts, including
empty/error results. ViewModels consume those results and never perform remote
fallbacks during rendering. `LayoutDataPrefetchService` remains a separate
pre-render concern for global layout data.

## Consequences

- `SmartPrefetchService`, its interface and the separate ID/type contract are
  removed after the consolidated pipeline is in place.
- Cache identity includes locale, preview state and the complete normalized
  query; stale data is allowed only for transport/5xx failures, never 4xx.
- The transport layer must preserve per-request base URLs, cache scopes,
  timeouts and telemetry while executing one concurrent batch.
- The pipeline becomes more involved than a per-domain `multiGet`, but it
  removes hidden render-time calls and avoids summing latency across domains.
