# TASKS — teatromuseo-web

> Trabajo abierto de este repositorio. Programa cross-repo:
> [`../TASKS.md`](../TASKS.md). Cierres históricos:
> [`TASKS_ARCHIVE.md`](TASKS_ARCHIVE.md).
>
> Las tareas cerradas antes del corte BFF-only del 2026-08-15 conservan la
> evidencia de su implementación anterior; sus nombres de clases/endpoints
> pueden no existir ya en el código vigente.
>
> El saneamiento posterior al corte BFF-only se gestiona en
> [`../docs/plan/2026-08-15-plan-robustez-web-bff.md`](../docs/plan/2026-08-15-plan-robustez-web-bff.md).

## ✅ Robustez de contrato localizada

- [x] **WEB-BFF-CONTRACT-01 — Paridad de rutas públicas.** Cerrada
  2026-08-15. `docs/contracts/public-routes.json` es el artefacto canónico
  del Web; `PublicPaths` y el adaptador BFF exportan contratos deterministas.
  Ambos CI los comparan en PHP 8.2 usando la rama `dev`, sin dependencia de
  runtime compartido.
- [x] **WEB-BFF-CONTRACT-02 — Slugs completos de detalle.** Cerrada
  2026-08-15. Event y Catalog cargan todos los locales en la proyección de
  detalle, incluso con `fields=[]`; los listados mantienen el filtro de
  locale/fallback. La consulta real se verifica con fixtures SQLite
  desechables y cobertura Web/BFF del envelope.
- [x] **WEB-BFF-CONTRACT-03 — Compatibilidad de plataforma.** Cerrada
  2026-08-15. La matriz CI ya cubría PHP 8.2–8.5 y ahora ambos workflows
  ejecutan `composer check-platform-reqs --no-dev` después de instalar
  dependencias.

## ✅ Completadas

- [x] **WEB-PAGE-01 — Home vía `page-resolve`.** Cerrada 2026-08-14.
  `SynchronousPageDeliveryAdapter` consume el endpoint nuevo únicamente para
  `PageDeliveryRequest::home()` y mapea su envelope a `PageDeliveryResponse`;
  `PageDelivery`/snapshots no cambian. Verificado con 4 tests / 29 assertions
  focales del adaptador, 7 tests / 34 assertions de rutas, quality completo
  (465 tests, 5 skipped, PHPStan 0 errores, CS-Fixer/i18n/fixture-policy
  verdes) y smoke HTTP real con `WEB_PAGE_DELIVERY_ENABLED=true`:
  `/es/` y `/es/inicio` `200`, inexistente `404`, con una sola llamada
  registrada a `public-read/es/page-resolve/home`.

- [x] **WEB-PAGE-02 — Páginas CMS simples vía `page-resolve`.** Cerrada
  2026-08-14. Se agregó la allow-list explícita
  `WEB_PAGE_DELIVERY_BFF_ROUTES`, independiente del manifest, para habilitar
  rutas solo después de su verificación. `/es/contacto` pasó a consumir una
  única llamada `public-read/es/page-resolve/contacto`; el pipeline anterior
  quedó fuera del proceso BFF. Smoke real: ambos caminos devolvieron `200`,
  `49.759` bytes y SHA-256 idéntico
  (`e399bd5e260056486c90c3de5996fedbe88204fd0343b9b9e88e508502890a77`).
  Quality: 467 tests, 1.727 assertions, 5 skipped, PHPStan 0 errores,
  CS-Fixer/i18n/fixture-policy verdes. Commit `66abba2`.

- [x] **WEB-PAGE-03 — Páginas CMS con bloques dinámicos vía `page-resolve`.**
  Cerrada 2026-08-14. La ruta canónica `/es/teatroescuela`, con
  `collection_listing` y dependencias de entradas, devolvió `200`, 100.866
  bytes y el mismo SHA-256 (`6ea691c070ede2346d14fd0913c1d0f3c87e73e08a62003cea5acec17645cd3b`)
  que el pipeline anterior. El BFF resolvió el árbol y el contexto en una
  única llamada `page-resolve`; el camino anterior necesitó `pages`, `layout`
  y `entries`. La prueba feature valida el `block_prefetch` y cero llamadas
  secundarias del Web. Quality: 468 tests, 1.733 assertions, 5 skipped,
  PHPStan 0 errores, CS-Fixer/i18n/fixture-policy verdes.

- [x] **WEB-PAGE-04 — Entradas de colección vía `page-resolve`.** Cerrada
  2026-08-14. `PageController` selecciona la vista `collection/show` para
  `page_type=collection_entry`, usa el `collection` y los
  `related_entries` incluidos por el BFF, y conserva el prefetched layout y
  block context sin volver a consultar Web. Smoke real de
  `/es/noticias/lanzamiento-del-libro-los-horribles`: ambos caminos devolvieron
  `200`, 53.353 bytes y SHA-256 idéntico
  (`75a078db0e1f366b2252442f23708cc21f99b26fa03c72d2a7ba573a3d057598`). El
  BFF usó una llamada `page-resolve`; el legacy mantuvo su rollback con
  `entries`, `related` y `layout`. Quality: 469 tests, 1.738 assertions,
  5 skipped, PHPStan 0 errores, CS-Fixer/i18n/fixture-policy verdes.

- [x] **WEB-PAGE-05 — Índice de colección de respaldo.** Cerrada
  2026-08-14. `renderFallbackCollectionIndex()` consume
  `page.page_type === 'collection_fallback_index'`; además del renderer y el
  contrato herméticos, el smoke real Web→BFF devolvió `200` y título de una
  colección activa sin página CMS dedicada. Se usó un fixture QA local,
  transaccional y con prefijo único; fue eliminado al terminar y la misma ruta
  quedó en `404` después de la limpieza.

- [x] **WEB-PAGE-06 — Preview de borradores, extremo a extremo.** Cerrada
  2026-08-14. El smoke real Web→BFF devolvió `200` para una entrada `draft`
  con firma HMAC válida (`page_type=collection_entry`) y `404` con firma
  inválida, sin exponer el borrador. El fixture QA temporal se eliminó en una
  transacción y se verificaron cero filas residuales en colección, entrada y
  traducciones.

- [x] **WEB-BFF-04 — Retirar el camino de lectura legacy del Web.** Cerrada
  después de la ventana de verificación de `WEB-BFF-03`: se eliminaron los
  factories `webApiClient()`, `catalogWebApiClient()` y
  `eventWebApiClient()`, junto con sus configuraciones y env vars de lectura.
  Todos los servicios públicos y el diagnóstico interno usan únicamente
  `bffWebApiClient()`. El diagnóstico consulta una sola vez `/health` del BFF;
  no conserva llamadas HTTP directas a dominios. La escritura de analytics se
  mantiene separada mediante `WEB_TRACKING_API_BASE_URL` hacia CMS, porque es
  una operación de escritura y no puede pasar por el BFF read-only. Verificado:
  `composer quality` verde, 461 tests, 1.684 assertions, 5 skipped, PHPStan 0
  errores y CS-Fixer limpio.

- [x] **WEB-BFF-03 — Rollout por tipo de página + verificación de contrato.**
  Cerrada con Fase 1 del BFF verificada y el Web consumiendo una sola base URL.
  El smoke real contra `start-dev.sh` devolvió `200` en home, `/es/nosotros`,
  `/es/cartelera`, `/es/museo/coleccion`, detalle de evento y
  `/es/teatroescuela`; la URL inexistente devolvió `404`. Comparaciones reales
  contra los dominios: CMS, Catalog y Event coinciden byte a byte después de
  excluir únicamente `meta.generated_at`, incluido Event listado/detalle y
  Catalog vacío/404. Validaciones inválidas devuelven `422`. Los logs
  `web_api_request` confirman `remote_endpoint` en `127.0.0.1:8188` para las
  lecturas BFF. Se corrigieron además los adaptadores legacy de Catalog/Event
  para conservar un rollback sano. `WEB-BFF-04` y Fase 3 siguen esperando la
  ventana de estabilidad requerida; no se borraron factories ni HTTP de dominio.

- [x] **WEB-PERF-13 — Reutilizar conexión cURL entre llamadas secuenciales al
  mismo cliente** — cerrada 2026-08-13. Diagnosticado en producción
  (`beta.teatromuseo.cl`): con datos genuinamente fríos (slugs nunca
  consultados en la sesión, para evitar el sesgo de buffer pool de MySQL ya
  caliente), `page-bootstrap`/`layout` medidos *directo contra el dominio CMS*
  ya eran ~2x más rápidos que la suma de los endpoints que reemplazan — el
  endpoint compuesto (WEB-PERF-12) funciona. Pero la carga completa por Web
  seguía sin bajar, y `/health` (que no llama a CMS) respondía rápido — la
  brecha estaba específicamente en la conexión Web→CMS. Causa: cada
  `WebApiClient::get()` hace `curl_init()`/`curl_close()` nuevo — sin
  `CURLOPT_SHARE`, cada llamada paga su propia negociación TCP+TLS completa
  aunque `PageResolverService` y `LayoutDataPrefetchService` compartan la
  misma instancia de `WebApiClient` (`getSharedInstance()`, una por request) y
  llamen al mismo host segundos aparte. Se agregó un `CurlShareHandle` lazy
  por instancia (`CURL_LOCK_DATA_CONNECT` + `CURL_LOCK_DATA_SSL_SESSION` +
  `CURL_LOCK_DATA_DNS`), adjuntado en los 3 puntos donde la clase crea un
  handle (`execute()`, el `curl_multi` de `multiGet()`, el `curl_multi`
  estático de `multiGetAcross()`) — la segunda llamada de una página
  reutiliza la conexión/sesión TLS/caché DNS de la primera en vez de
  renegociar desde cero. Verificado: 461/461 tests, PHPStan 0 errores,
  CS-Fixer limpio (invisible a los tests unitarios, que mockean
  `WebApiClientInterface` y nunca tocan la capa de transporte cURL real —
  pendiente confirmar la mejora real en producción tras el deploy).

- [x] **WEB-PERF-12 — Consumir los endpoints compuestos `layout` y
  `page-bootstrap` del CMS domain** — cerrada 2026-08-13. Enmienda ADR 004
  §1/§6 vía
  [`../docs/adr/006-public-read-composite-bootstrap-endpoints.md`](../docs/adr/006-public-read-composite-bootstrap-endpoints.md):
  bajo hosting con concurrencia efectiva de 1, agrupar llamadas en un lote no
  ahorra nada — solo el número de llamadas distintas importa. Se agregaron
  dos endpoints agregados en `teatromuseo-cms-domain` (PERF-04 de ese repo) y
  este lado los consume: `LayoutDataPrefetchService::prefetchLayoutData()`
  pasa de un lote de 3 (`navigation`+`collections`+`settings`) a 1 llamada a
  `public-read/{locale}/layout`, y `PageResolverService` (renombrado
  `parallelResolveRedirectAndPage()` → `resolveRedirectAndPage()`, ya no es
  paralelo) pasa de un lote de 2 (`public/redirects/{path}`+`pages/{path}`) a
  1 llamada a `public-read/{locale}/page-bootstrap/{path}`. `/es/nosotros`
  pasa de 5 a 2 llamadas de red en frío; `/es/teatroescuela` de 7 a 3.
  `LayoutDataPrefetchService` ahora resuelve el slug de colección del menú
  contra la lista de `collections` ya incluida en el bootstrap en vez de
  `BaseSiteService::resolveCollectionSlug()` (que sigue existiendo, sin
  cambios, para otros llamadores sin bootstrap a mano). `CacheInvalidator`
  gana un mapa de alias de scope (`SCOPE_ALIASES`): invalidar
  `settings`/`menus`/`collections` también invalida `layout` (nuevo scope
  válido, 13→14), e invalidar `redirects` también invalida `pages` — sin eso,
  el caché del endpoint compuesto quedaría stale hasta su propio TTL aunque
  el scope individual se invalidara. `DeterministicDomainAdapter` (fixture
  hermético compartido por las pruebas de feature) gana soporte para ambos
  paths compuestos, componiéndolos desde la misma resolución que ya usan los
  paths individuales — los `fakeGet()` existentes con paths individuales
  siguen funcionando sin cambios. Verificado: 461/461 tests (450 previos + 11
  nuevos/reescritos), PHPStan 0 errores, CS-Fixer limpio, fixture-policy e
  i18n-check verdes.

- [x] **REL-01-HARDEN-01 — Cerrar 2 gaps de robustez en PageDelivery
  Fases 2-3** — cerrada 2026-08-13. Revisión de código pedida explícitamente
  tras cerrar las Fases 2-3 encontró dos gaps concretos, ambos cerrados:
  1. **Redirects ignorados en rutas del manifest**: `deliverConfiguredPageRoute()`
     nunca consultaba `public/redirects/{route}` — un redirect creado sobre
     una ruta del manifest (`home`/`events`/`catalog`, y cualquier slug CMS
     que `REL-02` agregue después) quedaba silenciosamente inerte. Fix:
     `SynchronousPageDeliveryAdapter::deliver()` ahora consulta el redirect
     (vía `SiteRedirectService`, ya existente pero sin usar) **antes** de
     buscar la página, con la misma precedencia que ya usa el resolver
     legacy. Se ubicó deliberadamente ahí y no en `deliverConfiguredPageRoute()`
     para que un **snapshot HIT siga sin tocar el dominio** — la
     comprobación solo corre en preview, modo sync, y el build síncrono que
     refresca un snapshot (ya cacheado 3600s como el resolver legacy). Se
     extrajo `PublicPaths::resolveRedirectTarget()` compartido entre
     `PageController::resolve()` y el nuevo camino — una sola normalización
     externa/interna, no dos que puedan divergir. Nuevo
     `PageDeliveryResponse::redirect()`/`isRedirect()`;
     `SnapshotBuilder`/`FileSnapshotStore` no requirieron cambios (una
     respuesta de redirect ya tiene `page=null`, tratada igual que un 404 —
     mismo tradeoff de staleness ya aceptado, y el scope `redirects` ya
     estaba en `pageSnapshotScopes`/`CacheInvalidator::VALID_SCOPES`, así que
     la invalidación cross-repo ya existía).
  2. **Crecimiento no acotado de snapshots vía búsqueda libre**: `q`,
     `search`, `filter_value` (y `filter_by`, cuyo valor también es
     string libre) en `PageDeliveryRequest::VARIANT_QUERY_KEYS` generaban un
     `cacheKey()` nuevo por cada término de búsqueda real de un visitante —
     sin tope global en `FileSnapshotStore`, esto viola el invariante
     explícito del plan 2026-08-09 ("snapshots... deben tener tamaño y
     retención limitados"), y se vuelve alcanzable recién con Cartelera/
     Catálogo en el manifest (ambos con buscador real). Fix: nuevo
     `PageDeliveryRequest::isSnapshotEligible()` — `category`/`tag`/`page`/
     `per_page`/`order_by`/`order_direction`/`limit`/`filter_operator`
     (cardinalidad orgánica acotada) siguen siendo snapshot-eligible;
     `q`/`search`/`filter_value`/`filter_by` (texto libre, cardinalidad
     orgánica no acotada — cada término de búsqueda real es distinto) nunca
     lo son. `PageDeliveryService::deliver()` enruta esas requests por el
     mismo camino síncrono que preview — nunca tocan `SnapshotBuilder` ni
     escriben a disco. No se construyó una cuota/LRU global nueva: el fix
     ataca la causa raíz (por qué un campo de cardinalidad no acotada llega
     siquiera a ser candidato a snapshot) en vez de mitigar el síntoma con
     una pieza de infraestructura nueva y su propio riesgo de bugs.
  Tests nuevos: 4 feature (`PageDeliveryRouteTest`: redirect gana sobre
  contenido CMS/listado declarado, búsqueda libre nunca cachea — dos
  requests idénticas ambas tocan el dominio) + 8 unitarios
  (`PageDeliveryRequestTest`, `PageDeliveryResponseTest`, `PublicPathsTest`).
  Se corrigió además el test preexistente que afirmaba explícitamente el bug
  (`assertNotContains('public/redirects/about', ...)` → ahora
  `assertContains`, documentando el comportamiento correcto). Verificado:
  450/450 tests, PHPStan 114/114 sin errores, CS-Fixer limpio,
  `composer quality` completo verde.

- [x] **WEB-QUAL-01 — Descomponer BlockPrefetchService en colaboradores de
  responsabilidad única** — cerrada 2026-08-13. `BlockPrefetchService`
  (1223 líneas, una sola clase planificando + ejecutando + resolviendo
  dependencias + materializando resultados) se dividió en 8 clases bajo
  `app/Services/BlockPrefetch/`: `RequestQueryReader` (lectura segura de
  `?query`), `BlockPlanCollector` (recorrido del árbol de bloques),
  `ListQueryBuilder` (query por tipo de fuente), `PrefetchRequestQueue`
  (acumulación/dedup de requests — reemplaza el patrón de arrays pasados
  por referencia por un objeto real), `PrefetchRequestExecutor`
  (despacho paralelo/por cliente), `BlockRequestPlanner` (qué requests
  necesita cada plan), `BlockDependencyResolver` (segunda oleada:
  colección/categoría resueltas → request de listado) y
  `BlockResultMaterializer` (dar forma al resultado). `BlockPrefetchService`
  queda como fachada delgada (168 líneas) que conserva exactamente su API
  pública (`prefetchContext()`, `prefetch()`, constructor `array $clients`)
  — ningún consumidor (`PageCompositionService`,
  `SynchronousPageDeliveryAdapter`, `Config\Services`) cambió. Sin cambio de
  comportamiento: los 10 tests originales de `BlockPrefetchServiceTest`
  (end-to-end contra la fachada) pasan sin modificar ni una aserción; se
  sumaron 51 tests nuevos y focalizados por colaborador. Verificado:
  436/436 tests (1616 assertions), PHPStan 114/114 sin errores, CS-Fixer
  limpio, `test:fixture-policy` e `i18n-check` verdes, `git diff --check`
  limpio.

- [x] **WEB-PERF-11 — Batchear la resolución de collection_slug del menú**
  — cerrada 2026-08-13. `LayoutDataPrefetchService::normalizeMenuItems()`
  llamaba a `resolveCollectionSlug()` (`BaseSiteService.php`) dentro de un
  `foreach`, y esa función hacía su propio `$apiClient->get('public/{locale}/collections', ...)`
  — una tercera llamada de red, estrictamente secuencial, fuera del lote
  paralelo `navigation`+`settings`, disparada en **cada página** (el menú
  se renderiza en todas). Encontrado auditando en frío `/es/nosotros`
  (`docs/audits/2026-08-13-auditoria-carga-fria-web-domains.md`), que no
  tiene bloques dinámicos propios — el menú era casi todo el trabajo de
  red de esa página. Ahora `public/{locale}/collections` entra al mismo
  `multiGet()` que `navigation`/`settings`; como `WebApiClient` usa la
  misma clave de caché en `get()` y `multiGet()`, el fallback de
  `resolveCollectionSlug()` pasa a ser un cache hit en vez de un tercer
  round-trip. Verificado: 385/385 tests, PHPStan 106/106 sin errores,
  CS-Fixer limpio (evidencia previa a WEB-QUAL-01; ambos cierres
  comparten la misma sesión).

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

- [x] **Auditoría carga fría 2026-08-13 — Fase 1 (observabilidad por fase)**
  — cerrada 2026-08-13. Se instrumentó la infraestructura existente de
  correlación/telemetría: `RequestContext` mide resolución, composición y
  render; `PageCompositionService` cubre el camino normal y PageDelivery
  síncrono; `RequestTelemetryFilter` emite `page_render_phase` con
  `request_id`, ruta, status, `route_resolution_ms`, `composition_ms`,
  `view_render_ms`, `unattributed_ms`, `total_ms` y estado de snapshot. La
  traza no se emite para redirects-only y no cambia el contrato HTML. Añadidos
  tests de agregación y de no emisión para redirects. Verificado: 379 tests,
  1.479 assertions, 5 skips; PHPStan 106/106 sin errores y CS-Fixer limpio.
  Evidencia: [`../docs/audits/2026-08-13-cerrar-la-auditoria-de-carga-fria-(2026-08-13)-sin-deuda-tecnica.md`](../docs/audits/2026-08-13-cerrar-la-auditoria-de-carga-fria-(2026-08-13)-sin-deuda-tecnica.md).

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

- [ ] **REL-01 — Activación controlada de homepage** — ventana HTTP ejecutada
  el 2026-08-15. La configuración protegida de beta confirmó snapshot backend
  `file`, `shared=true`, gzip, máximo 5 MB y retención 3. La regeneración cold
  a concurrencia 4 respondió `4/4` `200`; la matriz caliente 1–4 × 20 respondió
  `204/204` `200`, cero `5xx` y cero `508`, con TTFB promedio de `77,5/80,5/
  112,4/153,8 ms`. Dos lecturas de `/es` entregaron 87.361 bytes con hash
  idéntico y el smoke final de Web/BFF permaneció en `200`. La evidencia
  reproducible está en
  [`docs/audits/2026-08-15-rel-01-homepage-cutover.md`](docs/audits/2026-08-15-rel-01-homepage-cutover.md).
  El log del cron confirmó `Snapshot warm-up: 16 manifest entries (serial)` y
  `Warm-up completed: 16/16 successful or skipped`, incluyendo `es/home`; no
  requiere cambios. La captura de cPanel posterior muestra CPU e I/O limitados
  y `451` eventos de Entry Processes; la primera prueba sintética de esta
  ventana tuvo un arnés defectuoso y probablemente contribuyó al pico. No se
  cierra REL-01 hasta observar una ventana limpia sin pruebas de carga.

## 🟡 Próximo

- [ ] **REL-02 — Preparación de migración gradual.** La auditoría local
  [`docs/audits/2026-08-15-rel-02-readiness.md`](docs/audits/2026-08-15-rel-02-readiness.md)
  confirma que el código vigente ya cubre home, páginas CMS, bloques,
  entradas, fallback, detalles, listados y preview mediante `page-resolve`.
  Las pruebas focales Web/BFF pasaron `23/23` (`86` assertions) y `28/28`
  (`135` assertions), respectivamente. Los canarios seriales de siete rutas
  con variantes inéditas, más la observación inicial de `/es/nosotros`,
  devolvieron `200`, sin errores de consola y con `load` entre `0,151` y
  `1,186` segundos; no reprodujeron los 15 segundos observados por el usuario.
  No se ejecuta rollout en producción hasta cerrar la ventana limpia de cPanel
  exigida por REL-01.

### El BFF resuelve la página pública completa (2026-08-14) — ver `../docs/plan/2026-08-14-plan-bff-page-resolution.md`

Extiende la lectura directa (abajo, ya cerrada) al objetivo final: una sola
llamada HTTP de Web al BFF por página, en vez de hasta 2 en paralelo. El
contrato de respuesta reutiliza `PageDeliveryResponse` — el sistema de
snapshots (`app/PageDelivery/**`, `REL-01`/`REL-02`) no cambia, solo
`SynchronousPageDeliveryAdapter` colapsa a un adaptador HTTP delgado.
La Fase 1 del BFF (`BFF-PAGE-03..08`) está verificada end-to-end y
`WEB-PAGE-01..07` ya están cerradas. El corte BFF-only queda sujeto a los
smoke tests de cada despliegue y a la invalidación del scope compuesto:
- [x] **WEB-PAGE-06-HARDEN — Política de cobertura BFF sin crecimiento de
  snapshots.** Cerrada 2026-08-15. Todas las rutas públicas localizadas se
  entregan mediante `page-resolve`; solo las rutas del manifest conservan
  snapshot-first y las demás se resuelven de forma síncrona sin persistirse.
  La decisión viaja en la request tipada y no existe un segundo allow-list de
  rollout. Verificado con canario final de 9 rutas públicas `200`, una llamada
  `page-resolve` por ruta, y muestra serial de sitemap de 32/32 `200` con
  32/32 resoluciones BFF.
- [x] **WEB-PAGE-06-DETAILS — Fichas de evento y catálogo vía `page-resolve`.**
  Cerrada 2026-08-15. `EventController::show()` y `MuseumController::show()`
  entregan la ruta completa al BFF; las fichas se renderizan con el contexto
  presembrado del envelope sin reabrir lecturas de dominio, categorías ni
  plantilla desde el Web. Tests focales y quality completo verdes; la ficha
  de evento real de beta respondió `200` con una sola llamada BFF.
- [x] **WEB-PAGE-07 — Fase 3: retiro del pipeline de resolución legacy.**
  Cerrada 2026-08-15 después de la ventana de estabilidad. Se retiraron el
  resolver/compositor/pre-fetch legacy, el listing builder, redirects locales
  y la API paralela del cliente HTTP. `PageController`, Event y Museum solo
  entregan el contrato BFF; el renderer recibe página, layout y
  `block_context` ya compuestos. Se añadió invalidación del nuevo scope
  `page-resolve` y se versionó el caché para no reutilizar payloads
  pre-cutover. Verificado: `composer quality` verde, 372 tests / 1.378
  assertions en Web, 170 tests / 504 assertions en BFF, y canarios beta
  previos 9/9 `200` con una llamada BFF por ruta.

### BFF de lectura directa (2026-08-13) — ver `../docs/plan/2026-08-13-plan-bff-completo.md`

Repunta el consumo de los 3 dominios (hoy 3 base URLs distintas) a una sola
base URL del BFF. La Fase 2 y la limpieza del Web ya están verificadas; queda
la ventana de estabilidad antes de retirar el HTTP público de los dominios.

- [x] **WEB-BFF-01 — Cliente único `bffWebApiClient()`.** Reemplazar los 3
  factories actuales (`webApiClient()`, `catalogWebApiClient()`,
  `eventWebApiClient()`) por uno solo apuntando a `BFF_API_BASE_URL` (nueva
  env var; reusar el valor actual de `WEB_API_KEY` para `BFF_API_KEY` en el
  corte inicial). Migrar todos los servicios que hoy usan alguno de los 3
  (`layoutDataPrefetchService`, `pageResolverService`, `siteCatalogService`,
  `siteEventService`, `siteCategoryService`, `siteTagService`,
  `blockPrefetchService`, etc.) al cliente único. No borrar los 3 factories
  viejos todavía (ver WEB-BFF-04).
- [x] **WEB-BFF-02 — Simplificar `BlockPrefetchService` a un solo cliente.**
  Su constructor recibe hoy `['cms'=>.., 'catalog'=>.., 'event'=>..]` porque
  son 3 hosts distintos; con un solo host colapsa a un único cliente
  inyectado. La lógica de ruteo por dominio en `BlockRequestPlanner`/
  `ListQueryBuilder` no cambia. `WebApiClient::multiGetAcross()` se mantiene
  sin cambios (sigue siendo válido para ráfagas paralelas contra un mismo
  host).
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
