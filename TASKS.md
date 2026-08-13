# TASKS — teatromuseo-web

> Trabajo abierto de este repositorio. Programa cross-repo:
> [`../TASKS.md`](../TASKS.md). Cierres históricos:
> [`TASKS_ARCHIVE.md`](TASKS_ARCHIVE.md).

## ✅ Completadas

- [x] **WEB-PERF-10 — Lock single-flight en WebApiClient::get()** — cerrada
  2026-08-13. Implementa lock single-flight en `WebApiClient::get()` utilizando
  la primitiva `SingleFlightLock` (bloqueo exclusivo flock) con re-chequeo
  de cache tras desbloqueo. Se agregaron tests unitarios dedicados
  (`SingleFlightLockTest.php`) cubriendo cache hit, cache miss, exclusión y timeout.
  Verificado: 377/377 tests, PHPStan 0 errores, CS-Fixer limpio. Evidencia:
  [`../docs/audits/2026-08-12-auditoria-parte2-rendimiento-listados-publicos.md`](../docs/audits/2026-08-12-auditoria-parte2-rendimiento-listados-publicos.md).

- [x] **WEB-PERF-09 — Alerta de payload_bytes en WebApiClient** — cerrada
  2026-08-13. Ejecuta la mitad "de bajo riesgo" de §2.E de
  [`../docs/audits/2026-08-12-auditoria-parte2-rendimiento-listados-publicos.md`](../docs/audits/2026-08-12-auditoria-parte2-rendimiento-listados-publicos.md):
  `recordTelemetry()` ya registraba `payload_bytes` por request; se agregó
  un log `warning` cuando supera 200KB (umbral documentado, no un cap duro —
  con §2.A-§2.C ya acotando el peor caso estructural, esto es una señal de
  regresión, no algo que deba truncar silenciosamente). **No** implementado:
  el lock single-flight (colapsar requests concurrentes en cache-miss) —
  requiere una primitiva de lock bloqueante+polling nueva (lo único similar
  en el repo, `FileRegenerationLock`, es "intenta una vez y si no, sigue sin
  bloquear", semántica distinta) y diseño/tests dedicados que no debían
  apurarse bajo presión de tiempo; queda como WEB-PERF-10 explícito en vez de
  dejarlo implícito. Verificado: 373/373 tests, PHPStan 0 errores.

- [x] **WEB-PERF-04 — Reducir payload/render de listados** — cerrada
  2026-08-13. `SiteCatalogService`/`SiteEventService` exponen `GRID_FIELDS`
  (subset mínimo card) además de `LIST_FIELDS`; `ListingQuery::$fields`
  permite que `CollectionGridViewModel` lo pida vía
  `CatalogItemsSource`/`EventItemsSource`. Del lado CMS,
  `include=listing_content` (blob completo) se reemplazó por selección de
  sub-claves real (`listing_content.<sub>`, soportado ahora por cms-domain —
  ver PERF-01 de ese repo) en los tres consumidores:
  `CollectionGridViewModel` (`fields` únicamente),
  `CollectionTimelineViewModel` (`publication_date,documents` — el peor caso,
  `items_limit` default 100), `CmsCollectionSource`/`collection_listing`
  (todo menos `documents`, confirmado no leído en la vista). `BlockPrefetchService::listQuery()`
  actualizado en paralelo para no perder la paridad de caché con estas
  queries (si difieren, el prefetch cachea la query vieja y el ViewModel pide
  una distinta — regresión cubierta por
  `testCmsListBlocksRequestOnlyTheListingContentSubKeysEachOneConsumes`).
  Verificado: 373/373 tests, PHPStan 0 errores, CS-Fixer limpio. Evidencia:
  [`../docs/audits/2026-08-12-auditoria-parte2-rendimiento-listados-publicos.md`](../docs/audits/2026-08-12-auditoria-parte2-rendimiento-listados-publicos.md).

- [x] **QA-01 — Contract tests y OpenAPI** — cerrada 2026-08-10. Contrato
  hermético, gate integrado de los tres dominios y estados de entrega
  verificados. Ver evidencia en [`../docs/audits/2026-08-10-qa-01-contractos-openapi.md`](../docs/audits/2026-08-10-qa-01-contractos-openapi.md).
- [x] **QA-03 — Carga fría/caliente/degradada y single-flight** — cerrada
  2026-08-10. Stale para transporte/`5xx` y `508`, `4xx` autoritativos,
  lock por identidad, carrera de publicación corregida, smoke localhost y
  concurrencia 1–4 sin errores. Ver evidencia en
  [`../docs/audits/2026-08-10-qa-03-cache-concurrency.md`](../docs/audits/2026-08-10-qa-03-cache-concurrency.md).
- [x] **QA-04 — Paridad y shadow comparison** — cerrada 2026-08-10. Rutas,
  contenido, enlaces, imágenes, paginación, estados, SEO y sitemap verificados;
  aliases redireccionados eliminados de la salida pública. Ver evidencia en
  [`../docs/audits/2026-08-10-qa-04-paridad-shadow.md`](../docs/audits/2026-08-10-qa-04-paridad-shadow.md).

## 🔴 En progreso

- [ ] **REL-01 — Activación controlada de homepage** — pendiente de ventana de
  cutover, baseline/shadow y telemetría del runtime anterior.

## 🟡 Próximo

### Plan vigente — PublicRead/PageDelivery/Snapshots (2026-08-09)

`PUB-00`, `PUB-01/02`, `WEB-01..04` y `CACHE-01..04` están cerradas y
archivadas. `beta.teatromuseo.cl` es el sitio nuevo vigente; el eventual
dominio principal queda fuera de ese cierre.

Orden local, alineado con el tracker raíz:

1. [ ] **REL-01** — Activación controlada de homepage.
2. [ ] **REL-02** — Migración gradual de páginas, listados, detalles y preview.
3. [ ] **CLEAN-01** — Retirada posterior del camino anterior.

### Auditoría de rendimiento beta — prioridad 2

Estas tareas no se ejecutan como una segunda iniciativa: sus criterios se
absorben en QA cuando se solapan con el plan nuevo.

- [ ] **WEB-PERF-03** — Consolidar caché API/HTML, hit rate, expiraciones e
  invalidaciones. Solapa con `CACHE-03` y `QA-03`.
- [ ] **WEB-PERF-05** — Reparar URLs, canonical y pantallas 404. Solapa con
  `QA-04`.
- [ ] **WEB-PERF-06** — Corregir assets, `localhost`, `srcset` y bytes. Solapa
  con `QA-04`.
- [ ] **WEB-PERF-07** — Convertir filtros AJAX a parciales; mejora independiente
  posterior al cutover.
- [ ] **WEB-PERF-08** — Automatizar crawl/smoke/performance budget. Su resultado
  debe alimentar `QA-03/QA-04`, no reemplazarlos.

### Backlog heredado del saneamiento 2026-08-05 — prioridad 3

- [ ] **CFG-02/05/06/07/08**, **HYG-02** — entorno, PHPStan, hooks, compose,
  Dependabot y protección de `.env.*`.
- [ ] **FRONT-02**, **FRONT-03a..d**, **DEAD-02a..c**, **FRONT-BUILD** y
  **DOC-01** — contratos de caché, señal stale, ViewModels, slugs, previews,
  fallbacks de assets, aliases, build y documentación.

### Dependencias y conflictos

- No retirar el camino público anterior ni cambiar fallbacks mientras `QA-03`,
  `QA-04` y la ventana de `REL-01` estén abiertas.
- `WEB-PERF-03..06` no deben crear soluciones paralelas a snapshots, fieldsets o
  comparación de paridad; registrar sus evidencias en QA y reabrirlos solo si
  queda trabajo fuera de los criterios del plan.
- `WEB-PERF-07` es independiente, pero se posterga para no cambiar navegación,
  SEO o accesibilidad durante el cutover.
- Las tareas heredadas de configuración/build pueden avanzar solo si no cambian
  contratos públicos ni el comportamiento snapshot-first.

## 🏗️ Contratos de arquitectura

- Controladores delgados; acceso a dominios únicamente mediante `WebApiClient` y
  servicios.
- Ningún I/O desde vistas, ViewModels o bloques durante el render.
- Stale solo para fallos de transporte/5xx; un `404` no se enmascara.
- Snapshots versionados, atómicos, compartidos y con single-flight.
