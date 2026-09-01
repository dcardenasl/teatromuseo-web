# TASKS_ARCHIVE — teatromuseo-web

> Cierres históricos del tracker de Web.

## ✅ QA-01 — Contract tests y OpenAPI — cerrado 2026-08-10

Contrato Web→CMS/Catalog/Events verificado con gate hermético e integrado,
incluyendo autenticación, envelope versionado y estados de entrega.

## ✅ QA-03 — Caché, concurrencia y single-flight — cerrado 2026-08-10

Se verificaron stale, `4xx` autoritativos, `508`, concurrencia local 1–4 y
single-flight de snapshots. Se corrigió la carrera de relectura después de una
publicación concurrente. Evidencia en
[`../docs/audits/2026-08-10-qa-03-cache-concurrency.md`](../docs/audits/2026-08-10-qa-03-cache-concurrency.md).

## ✅ QA-04 — Paridad y shadow comparison — cerrado 2026-08-10

Se verificaron las rutas, contenido, navegación, assets, paginación, estados,
SEO y sitemap en localhost. La normalización central evita publicar aliases de
homepage redireccionados, incluidos bloques anidados. Evidencia:
[`../docs/audits/2026-08-10-qa-04-paridad-shadow.md`](../docs/audits/2026-08-10-qa-04-paridad-shadow.md).

## ✅ PublicRead/PageDelivery — fases 0–3 cerradas 2026-08-10

- `PUB-00`, `PUB-01/02`, `WEB-01..04` y `CACHE-01..04` quedaron completadas.
- La verificación en beta confirmó filesystem compartido, Cron, invalidación,
  warm-up, single-flight, estados `built`/`busy` y entrega HTTP 200.
- El dominio principal no forma parte del cierre; el cutover y QA permanecen en
  [`TASKS.md`](TASKS.md).

## ✅ Optimización de rendimiento — cerrada 2026-08-09

- `WEB-PERF-01`: instrumentación de timeouts/caché y métricas p50/p95/p99.
- `WEB-PERF-02`: resolución de rutas desconocidas sin recorrer todas las
  colecciones; 404 verificado en beta con cero requests a `entries`.
- `PERF-01/02`: TTL de colección y prefetch batch/concurrente de datos dinámicos.

## ✅ Prefetch y plantillas dinámicas — cierres previos

La implementación del planner único, fieldsets, resolución de aliases y las
plantillas dinámicas de catálogo/eventos quedó documentada en los ADR y docs de
Web existentes. Los servicios históricos sustituidos no deben reintroducirse.
