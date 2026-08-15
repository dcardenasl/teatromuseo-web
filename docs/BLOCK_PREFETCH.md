# Block prefetch

## Current status

The Web-side block prefetch pipeline was retired on 2026-08-15. Public pages
are resolved by the BFF `public-read/{locale}/page-resolve/{route}` contract,
which composes routing, layout and dynamic block context before the Web starts
rendering. The Web renderer consumes that context and performs no domain HTTP
fallback during a page render.

The BFF remains the single composition boundary for dynamic blocks. Its
response keeps results keyed by stable block paths (`0`, `1`, `2.0`, …), with
an explicit envelope for successful and failed reads. This preserves partial
failure behavior without reintroducing hidden requests in ViewModels.

See [PageDelivery](PAGE_DELIVERY.md), [ADR 008](adr/008-bff-full-page-resolution.md)
and [ADR 0003](adr/0003-single-block-prefetch-before-render.md) for the current
contract and historical rationale.
