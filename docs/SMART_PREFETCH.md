# Legacy prefetch note

This filename is retained as a compatibility pointer for old links. The
`SmartPrefetchService`, `BlockAnalyzerService`, and their interfaces were
removed because they described a second, incompatible prefetch contract.

Use [`BLOCK_PREFETCH.md`](BLOCK_PREFETCH.md) for the current implementation and
[`adr/0003-single-block-prefetch-before-render.md`](adr/0003-single-block-prefetch-before-render.md)
for the accepted migration decision.
