# TASKS — teatromuseo-web

> Fuente de verdad para trabajo abierto en este repositorio.
> Seguimiento cross-repo: [`../TASKS.md`](../TASKS.md).
> Creado el 2026-08-05: esta era la única app activa sin `TASKS.md`, pese a que la convención
> documentada en el `CLAUDE.md` raíz obliga a leerlo antes de escribir código.

---

## 🔴 En progreso

- [ ] **CACHE-01 — Snapshots públicos: verificación de despliegue pendiente.**
  CACHE-02, CACHE-03 y CACHE-04 están completadas en código y pruebas; falta
  verificar filesystem compartido con dos workers y QA-03 antes de activar.
  La implementación secuencial de backend compartido, almacenamiento atómico,
  invalidación segura y warm-up quedó realizada en esta sesión:
  `FileSnapshotStore`, `SnapshotBuilder`, lock recuperable, invalidación por
  scope/locale/ruta y `PublicSnapshotManifest`. Falta la verificación externa de
  filesystem compartido con dos workers y la medición QA-03; la activación
  pública sigue bloqueada por esos criterios.

---

## 🟡 Próximo

### Programa cross-repo — PublicRead/PageDelivery/Snapshots

Fuente de verdad: [plan de entrega pública](../docs/plan/2026-08-09-entrega-publica-read-model-page-delivery.md) y
[tracker raíz](../TASKS.md). Las tareas deben ejecutarse en el orden cross-repo y
con los criterios de aceptación de la sección 8 del plan.

- [ ] **PUB-00** — Baseline HTTP ejecutado; la capacidad beta queda pendiente de
  métricas del hosting (PHP-FPM, MySQL, caché y upstream 508) en el tracker raíz.
- [x] **PUB-01/PUB-02** — Contratos, gobierno, observabilidad y budgets completados
  en el tracker raíz antes de modificar el camino público.
- [ ] **CACHE-01** — Backend compartido y política de caché. Código y política
  implementados; requiere configurar `WEB_PAGE_SNAPSHOT_DIR` compartido,
  `WEB_PAGE_SNAPSHOT_SHARED=true` y probar dos workers en el hosting.
- [x] **CACHE-02** — Builder y almacenamiento atómico. Código implementado con
  envelope validado, source/snapshot revisions, puntero activo, límites,
  retención y single-flight; depende de la verificación de CACHE-01.
- [x] **CACHE-03** — Invalidación y regeneración asíncrona. Web usa markers stale,
  webhook por scope/locale/ruta y warm-up fuera del request; CMS, Catalog y
  Events registran outbox posterior al commit y disponen de dispatcher con
  lease/reintentos. La operación productiva requiere programar el comando cron.
- [x] **CACHE-04** — Manifest y warm-up controlado. Manifest explícito de locales
  y rutas, ejecución serial idempotente y reporte atómico implementados; la
  corrida contra upstream real queda dentro de la verificación operativa de
  CACHE-01/QA-03.
- [ ] **QA-01/QA-03/QA-04** — Contratos, carga y paridad cross-repo.
- [ ] **REL-01/REL-02/CLEAN-01** — Cutover progresivo y retirada del camino
  anterior.

### Auditoría de rendimiento beta — 2026-08-08

Referencia: [`docs/audits/2026-08-08-auditoria-rendimiento-beta-es.md`](docs/audits/2026-08-08-auditoria-rendimiento-beta-es.md)

- [x] **`WEB-PERF-01` — Instrumentar timeouts y caché (P0)**
  - Registrar por petición `path`, endpoint remoto, duración, status, cache hit/miss, `stale` y timeout.
  - Separar métricas de `teatromuseo-web`, CMS, catálogo y eventos; publicar p50/p95/p99 y tasa de timeout.
  - Verificar qué rutas coinciden con el timeout actual de 5 s y si el backend de caché es compartido entre workers.
  - **Aceptación:** cada navegación lenta de la auditoría puede correlacionarse con un endpoint y una causa concreta en logs/APM.
  - **Verificado 2026-08-09:** deploy en beta, eventos `[web-api]` visibles en `writable/logs`, p50/p95/p99 calculados y tasa de timeout medida. Beta mantiene respuestas `508` de los dominios/hosting que deben resolverse aparte.

- [x] **`WEB-PERF-02` — Resolver rutas desconocidas sin recorrer todas las colecciones (P0)**
  - Optimizar `PageController::resolve()` para resolver por prefijo/índice de colección antes de consultar slugs.
  - Evitar consultas secuenciales a cada colección para detalles de compañías, videos y rutas inexistentes.
  - Añadir tests para rutas válidas, aliases y 404, incluyendo presupuesto de llamadas HTTP.
  - **Aceptación:** un 404 no dispara una consulta por cada colección y queda por debajo de 500 ms en caché fría local.
  - **Verificado 2026-08-09:** ruta inexistente en beta respondió `404` en `253 ms` y registró cero requests al scope `entries`.

- [ ] **`WEB-PERF-03` — Consolidar caché de API y HTML (P1)**
  - Confirmar la configuración efectiva de beta para backend de caché, TTL, `cacheQueryString` y stale fallback.
  - Revisar claves por locale, query string y variante de contenido; evitar duplicación entre workers.
  - Definir invalidación para publicaciones y cambios de menú/media.
  - **Aceptación:** hit rate, expiraciones e invalidaciones son visibles y las rutas de colección no vuelven a bloquear 5 s tras una expiración normal.

- [ ] **`WEB-PERF-04` — Reducir payload y render de listados (P1)**
  - Auditar `include=listing_content` y aplicar fieldsets mínimos para tarjetas: título, resumen corto, fecha, imagen y slug.
  - Reducir el HTML de listados de aproximadamente 98–109 KB sin perder contenido visible ni SEO.
  - Medir consultas, serialización y tiempo de render en `CollectionListingViewModel` y sus fuentes.
  - **Aceptación:** payload y tiempo p95 quedan documentados antes/después; el listado conserva paginación, imágenes y accesibilidad.

- [ ] **`WEB-PERF-05` — Reparar contratos de URLs y pantallas 404 (P1)**
  - Corregir enlaces y resolución de contacto, legales, compañías y videos.
  - Alinear `/es`, `/es/`, `/es/inicio` y `/public/es` con una única URL canónica navegable.
  - Regenerar menú, sitemap y cachés después de la corrección.
  - **Aceptación:** el crawl público no encuentra enlaces internos rotos y el canonical responde sin redirección inconsistente.

- [ ] **`WEB-PERF-06` — Corregir y optimizar assets publicados (P1)**
  - Eliminar URLs `localhost` de logo, hero y cualquier media serializada en settings/CMS.
  - Verificar que `/files/{id}/view` devuelve `200`, `Content-Type` de imagen, `Cache-Control` y dimensiones válidas.
  - Añadir variantes `srcset`/`sizes` para tarjetas e imágenes hero, especialmente las de 1080×1350.
  - **Aceptación:** cero assets con `localhost`, cero imágenes rotas y reducción medida de bytes transferidos/decodificados.

- [ ] **`WEB-PERF-07` — Convertir filtros AJAX a respuestas parciales (P2)**
  - Evitar descargar y parsear el layout HTML completo cuando sólo cambian grilla y paginación.
  - Mantener URL, historial, SEO progresivo, accesibilidad y fallback sin JavaScript.
  - Traducir estados vacíos para que no se publiquen claves `Site.*`.
  - **Aceptación:** la respuesta de filtro contiene sólo el fragmento necesario y no incluye menús, footer ni scripts globales.

- [ ] **`WEB-PERF-08` — Automatizar smoke test y presupuesto de rendimiento (P2)**
  - Añadir crawl de enlaces públicos, validación de canonical y detección de `localhost`, 404 y claves i18n sin traducir.
  - Medir rutas en frío/caliente con presupuesto de HTML, `load`, imágenes y timeout rate.
  - Ejecutar el control en CI o en una tarea periódica contra beta.
  - **Aceptación:** una regresión de rutas, assets o tiempos bloquea el gate o genera una alerta accionable.

### Fase 3 — Prefetch único antes del render (✅ COMPLETADA 2026-08-09)

La implementación vigente está documentada en [`docs/BLOCK_PREFETCH.md`](docs/BLOCK_PREFETCH.md)
y en [ADR 0003](docs/adr/0003-single-block-prefetch-before-render.md). El planner único
cubre `collection_grid`, `collection_listing`, `collection_timeline` y bloques de detalle,
con routing por dominio, deduplicación, transporte concurrente cross-domain y envelopes
explícitos por path. Los servicios `SmartPrefetchService` y `BlockAnalyzerService` quedaron
eliminados; las tareas históricas más abajo no son instrucciones de implementación.

Ver [`../TASKS.md`](../TASKS.md) para el estado cross-repo.

---

## ✅ Completadas

### Fase 2 — Entrega de páginas en Web (2026-08-09) ✅ COMPLETADA

- [x] **WEB-01** — Interface, request/respuesta tipadas, adapters síncrono y
  snapshot-first, configuración y lock de regeneración.
- [x] **WEB-02** — Homepage con composición bounded, prefetch único de bloques y
  cero I/O durante render; desactivada para tráfico normal por configuración.
- [x] **WEB-03** — Identidad de instancia, query/preview, filtros, orden,
  paginación, facetas y estados `fresh/stale/unavailable`.
- [x] **WEB-04** — Idiomas, settings, layout y social links consolidados sin
  lecturas duplicadas ni llamadas desde las vistas.

Verificación: `composer quality`, contratos, policy de fixtures e i18n verdes;
292 tests, 983 assertions y 5 skipped.

### Task #10 — Verificación Final (2026-08-08) ✅ COMPLETADA

**Estado:** ✅ COMPLETADA

Verificado:
- ✅ `composer test:unit` y `composer test:feature` verdes
- ✅ `BlockPrefetchService` con unit tests de routing, deduplicación, facets, detalles y errores
- ✅ `collection_listing` resuelve filtros, paginación, facets y preview antes del render
- ✅ Transporte concurrente cross-domain preservando caché, stale fallback y telemetría
- ✅ `ParallelAliasResolver` y sparse fieldsets existentes conservados
- ✅ Documentación y ADR alineados con el contrato vigente

---

### Task #8 — ParallelAliasResolver con interface + tests (2026-08-08) ✅ COMPLETADA

**Estado:** ✅ COMPLETADA

Implementación:
- Archivo: `app/Services/ParallelAliasResolver.php` (50+ líneas)
- Interfaz: `app/Interfaces/AliasResolverInterface.php`
- Métodos:
  - `resolveAlias(string $alias, string $type): ?string` — resuelve un alias individual
  - `resolveBatch(array $aliases, string $type): array` — resuelve múltiples aliases en paralelo
- Endpoints soportados: `collection_items`, `events`
- Caching: TTL configurable (default 3600s)
- Validación: filtra aliases vacíos/inválidos

Tests:
- `tests/unit/Services/ParallelAliasResolverTest.php`
- Unit tests con WebApiClient mockeado
- Casos: single alias, batch, missing, cache hits, error handling

Integración:
- Inyectado via `Services::parallelAliasResolver()`
- Compatible con PageController para alias en URLs

---

### Task #7 — Integración en PageController (2026-08-08) ✅ SUPERSEDED

**Estado:** ✅ COMPLETADA

Nota histórica: esta integración fue reemplazada por `prefetchBlockContext()` y el
planner único `BlockPrefetchService`. No usar el flujo histórico de analyzer/context holder.

Flujo:
1. Cargar página CMS + blocks
2. BlockAnalyzer analiza blocks → detecta requirements
3. SmartPrefetch → paralleliza llamadas por tipo
4. ContextHolder::inject() → viewmodels acceden datos sin API calls

No requiere cambios en routes, filtros, o middleware.

---

### Task #6 — SmartPrefetchService con interface + tests (2026-08-08) ✅ SUPERSEDED

**Estado:** archivada; el servicio y su interfaz fueron eliminados.

Implementación:
- Archivo: `app/Services/SmartPrefetchService.php` (175 líneas)
- Interfaz: `app/Interfaces/SmartPrefetchInterface.php`
- Métodos:
  - `prefetch(array $requirements, string $locale = 'es'): array` — ejecuta batch paralelo
  - `prefetchBatch(array $ids, string $type): array` — prefetch single resource type
- Endpoints: `catalog/collection-items`, `events/events`, `catalog/categories`, `catalog/techniques`
- Sparse fieldsets: automático con `?fields=...`
- Caching: respeta WebApiClient (300s default, stale fallback)

Tests:
- `tests/unit/Services/SmartPrefetchServiceTest.php`
- Unit tests con WebApiClient mockeado
- Casos: single batch, multi-batch, partial failures, cache hits, field selection

Integración:
- Inyectado via `Services::smartPrefetchService()`
- Compatible con BlockAnalyzer output

---

### Task #5 — BlockAnalyzerService con interface + tests (2026-08-08) ✅ SUPERSEDED

**Estado:** archivada; el servicio y su interfaz fueron eliminados.

Implementación:
- Archivo: `app/Services/BlockAnalyzerService.php` (240 líneas)
- Interfaz: `app/Interfaces/BlockAnalyzerInterface.php`
- Método: `analyze(array $blocks): array` — detecta requirements de todos los blocks

Blocks soportados:
- `collection_grid` → `collection_items` (IDs de config)
- `collection_listing` → `collection_items`
- `collection_timeline` → `collection_items`
- `event_item_header`, `event_item_details`, `event_item_content` → `events`
- `card_carousel`, `cards_grid` → `collection_items` o `events`
- Fallback: blocks sin config devuelven empty

Campos automáticos por bloque:
- Cards: `id, uuid, name, slug, cover_file_id, cover_url, summary`
- Timeline: agrega `period`
- Detail: agrega `description, localized, translations`

Output: `{ collection_items: { ids: [...], fields: [...] }, events: { ... }, ... }`

Tests:
- `tests/unit/Services/BlockAnalyzerServiceTest.php`
- Unit tests (no DB, sin HTTP)
- Casos: single block, multi-block, deduplication, unknown blocks, empty config

Integración:
- Inyectado via `Services::blockAnalyzerService()`
- Usado en PageController::resolve()

---

### Task #4 — Sparse Fieldsets en Event Domain (2026-08-08) ✅ COMPLETADA

**Estado:** ✅ COMPLETADA

Implementación en `teatromuseo-event-domain/app/Controllers/Api/V1/Events/PublicEventController.php`:

Constantes de campos:
```php
private const LISTING_FIELDS = [
    'id', 'uuid', 'name', 'slug', 'slugs', 'cover_file_id', 'cover_url',
    'start_date', 'end_date', 'venue', 'event_type', 'summary'
];
private const DETAIL_FIELDS = [
    'id', 'uuid', 'name', 'slug', 'slugs', 'cover_file_id', 'cover_url',
    'gallery_file_ids', 'start_date', 'end_date', 'venue', 'description',
    'event_type', 'summary', 'localized', 'translations', 'created_at', 'updated_at'
];
```

Uso del trait `SparseFieldsetTrait`:
- `index()`: `$fields = $this->parseFieldsParam(self::LISTING_FIELDS)`
- `show()`: `$fields = $this->parseFieldsParam(self::DETAIL_FIELDS)`
- Filtrado: `$this->sparseFilter($data, $fields)`

Query param:
```
GET /api/v1/public/es/events?fields=id,name,slug,cover_url
```

Validación:
- Rechaza campos fuera del whitelist (400 Bad Request)
- Default a LISTING_FIELDS si `?fields=` no se envía

Tests:
- Tests de sparse fieldsets en integración
- Verificación de payload reduction (~60%)
- Error cases (invalid fields)

---

### Task #9 — Documentación de Sparse Fieldsets y prefetch (2026-08-08) ✅ SUPERSEDED

**Estado:** archivada; los documentos de los contratos eliminados fueron reemplazados
por `BLOCK_PREFETCH.md`, `EXAMPLES.md` y el ADR 0003.

4 archivos markdown creados en `teatromuseo-web/docs/`:

1. **`SPARSE_FIELDSETS.md`** (340 líneas)
   - Protocolo `?fields=` y format de query
   - Implementación en catalog-domain como referencia
   - Patrón para agregar sparse fieldsets
   - Casos de uso (cards, detail, bulk fetch)
   - Uso desde teatromuseo-web  
   - API del trait `SparseFieldsetTrait`
   - Manejo de errores
   - Performance & caching
   - Tests unitarios e integration

2. **`SMART_PREFETCH.md`** (380 líneas)
   - Problem statement: N+1 sobre HTTP
   - Data flow: BlockAnalyzer → SmartPrefetch → ContextHolder
   - Integración en PageController
   - API calls con sparse fieldsets
   - Error handling & fallbacks
   - 3-level caching strategy
   - Monitoring & observability
   - Performance targets
   - Roadmap de Fase 1-5

3. **`BLOCK_REQUIREMENTS.md`** (420 líneas)
   - Contrato `BlockRequirementProviderInterface`
   - DTO `BlockRequirement`
   - 4 ejemplos detallados (Collection Grid, Event Carousel, Category Listing, Multi-source)
   - Cómo registrar providers en `Config\BlockRequirements`
   - Integración con `BlockAnalyzerService`
   - Integración con ViewModels
   - Field selection guidelines
   - Caching behavior
   - Tests (unit + feature)
   - Migration checklist
   - Common pitfalls

4. **`EXAMPLES.md`** (500+ líneas)
   - 6 ejemplos end-to-end funcionales:
     1. Listing page con sparse fieldsets (10KB → 2KB savings)
     2. Detail page tradicional (3 calls secuenciales)
     3. Detail page con SmartPrefetch (1430ms → 700ms, 50% faster)
     4. Timeline con múltiples fuentes de datos
     5. Error handling & graceful degradation
     6. Cache invalidation webhook
   - Performance comparison (tabla antes/después)
   - Summary metrics
   - Tests completos

**Métricas:**
- Total: ~1,640 líneas de documentación markdown
- 40+ bloques de código
- 12 ejemplos de tests
- 8 tablas de referencia
- 3 diagramas ASCII

**Verificación:**
- Archivos creados con éxito en `/docs/`
- Contenido coherente con la arquitectura actual
- Ejemplos basados en código real (catalog-domain, teatromuseo-web)
- Referencias cruzadas entre documentos

---

## ✅ Historial previo

> Saneamiento arquitectónico — auditoría del 2026-08-05.
> **Contexto, evidencia y rutas exactas:** [`../docs/plan/2026-08-05-saneamiento-arquitectonico.md`](../docs/plan/2026-08-05-saneamiento-arquitectonico.md)
> Orden y dependencias cross-repo: [`../TASKS.md`](../TASKS.md)
>
> Nota: la capa de servicios de esta app está **bien** — los 13 `Site*Service` extienden
> `BaseSiteService` sin excepciones, no hay llamadas a la API desde vistas, el respaldo stale de
> `WebApiClient.php:99` está correctamente acotado (solo status `0` o `>= 500`, nunca enmascara un
> 404) y el orden de `PageController::resolve()` está documentado y es correcto. Lo de abajo son
> huecos puntuales, no problemas estructurales.

### Fase 2 — Configuración y CI

- [ ] **CFG-02 — 12 variables leídas y no documentadas:** `CATALOG_DOMAIN_API_BASE_URL`,
  `EVENT_DOMAIN_API_BASE_URL`, `CATALOG_API_BASE_URL`, `EVENT_API_BASE_URL`, `RECAPTCHA_SITE_KEY`,
  `CSP_*`, `WEB_API_TIMEOUT`, `WEB_API_STALE_TTL`.
  Sacar además `TEAM_MEDIA_BASE_URL = https://teatromuseo.cl/images/team/` del `.env.example`:
  es una URL de **producción** incrustada en el ejemplo de desarrollo.
- [ ] **CFG-05 — `phpstan-baseline.neon` está vacío (0 bytes) pero `phpstan.neon` lo incluye.**
  Un include de neon totalmente vacío es frágil. Es además la única app con `scanFiles:` y sin el
  bloque `parallel:`. PHPStan aquí está en 2.2.5, la versión más adelantada de la flota.
- [ ] **CFG-06 — El `pre-push` está instalado pero muerto.** `core.hooksPath = .husky/_` hace que
  git ignore `.git/hooks/pre-push`, y existe `.husky/_/pre-push` como shim **sin `.husky/pre-push`
  detrás**. Solo hay `.husky/pre-commit`.
- [ ] **CFG-07 — Falta `docker-compose.yml`.** Hay `Dockerfile`, así que la app se puede construir
  pero no levantar en el stack local documentado.
- [ ] **CFG-08 — Falta `dependabot.yml`**, pese a tener `composer.lock` **y** `package-lock.json`.
  Es la única app con dependencias npm sin actualizaciones automatizadas (solo admin cubre npm).
- [ ] **HYG-02 — Falta el glob `.env.*` en `.gitignore`.** Solo esta app y el tótem no lo tienen;
  un `.env.local`, `.env.production` o un `.env.bak.<timestamp>` (que ya aparecieron sueltos en bff
  y event) se commitearía.

### Fase 6 — Frontend

- [ ] **FRONT-02 — Ámbitos de invalidación de caché sin contrato compartido.**
  `app/Libraries/CacheInvalidator.php` tiene la lista canónica como constante `VALID_SCOPES`, pero
  el admin usa literales sueltos y su `normalizeScopes()` **no valida**. Una errata en el admin
  produce un no-op silencioso: aquí se registra `Unknown scope requested` y **se devuelve `ok`
  igual**. Compartir la lista (candidata a `ci4-api-core`) y decidir si un ámbito desconocido debe
  responder error en vez de `ok`.
- [ ] **FRONT-03a — La señal `meta['stale']` no la consume nadie.**
  `app/Libraries/WebApiClient.php:109` marca `$staleResult['meta']['stale'] = true`, y no se lee en
  `app/Services`, `app/Controllers`, `app/ViewModels` ni `app/Views`. El sitio no puede avisar —
  ni a un visitante ni a un monitor — de que está sirviendo contenido degradado.
- [ ] **FRONT-03b — 39 de 70 vistas de bloque no tienen ViewModel** y llevan la lógica en línea:
  `blocks/gallery.php` (454 líneas, 35 etiquetas PHP), `contact_info.php` (151/56),
  `faq_accordion.php` (147/19), `anchor_nav.php` (112/9), `cards_grid.php` (94/18),
  `map_embed.php` (79/30). Migrarlas al patrón ViewModel que las otras 31 ya usan.
- [ ] **FRONT-03c — Slugs filtrándose fuera de `PublicPaths`.** `PublicPaths` es la abstracción
  correcta y `Routes.php`/`PublicListingPageBuilder` la usan, pero:
  `ViewModels/Blocks/CollectionGridViewModel.php:105` hace un `match` sobre literales crudos
  (`'cartelera'`, `'events'`, `'eventos'`, `'obras'`, `'noticias'`…) para elegir la relación de
  aspecto, y repite la misma lista en `:433`; `'teatroescuela'` se compara como cadena cruda en
  `:108` y en `BlockRenderer.php:127`; y `app/Common.php:487` usa la clave `'teatroescuela_ficha'`.
- [ ] **FRONT-03d — `POST blocks/preview` está expuesto solo con `throttle:10,60`**, sin secreto
  compartido, mientras su endpoint hermano `cache/invalidate` sí exige `X-Invalidate-Key`. El único
  llamador es el admin (`BlockPreviewController`). Añadir el secreto compartido en ambos extremos.
- [ ] **DEAD-02a — Respaldos a fotos de stock que el equipo creyó eliminados.**
  `app/Libraries/PublicListingPageBuilder.php:121` y `:137` todavía inyectan URLs de Unsplash como
  `fallback_image_url`. Hoy queda neutralizado porque `CollectionListingViewModel` lo sobrescribe
  con `''` (l.231-234, con el comentario que explica que es a propósito), así que es configuración
  muerta que **parece** viva. El builder sí está en producción: `MuseumController:21` y
  `EventController:21` lo invocan cuando falta la página índice del CMS.
  Mismo patrón en `ViewModels/Blocks/CollectionGridViewModel.php:214,224,234` y
  `ViewModels/Blocks/Listing/Sources/CmsCollectionSource.php:261,271`.
- [ ] **DEAD-02b — URL localhost incrustada en datos de ejemplo.**
  `app/Controllers/BlockPreviewController.php:118` —
  `http://localhost:8184/uploads/reporte_anual_2025.pdf`.
- [ ] **DEAD-02c — Seis vistas alias byte-idénticas** (`compania_ficha.php`, `exposicion_ficha.php`,
  `festival_ficha.php`, `obra_ficha.php`, `persona_ficha.php`, `video_ficha.php`), cada una una sola
  línea delegando en `blocks/domain_ficha`. Y `curso_ficha.php` hace lo mismo **y además** está
  mapeado en `BlockRenderer.php:45` a `TeatroEscuelaViewModel` — aliasado por dos mecanismos
  distintos. Unificar en un solo mecanismo de alias.
- [ ] **FRONT-BUILD — Limpiar la cadena de build.** Sobra el stack PostCSS (`@tailwindcss/postcss`,
  `postcss ^8.5.15`, `autoprefixer ^10.5.0`): Tailwind 4 no lo necesita y ya se usa su propio CLI.
  El CSS se compila **desde dentro del webroot** (`public/assets/css/app.css` →
  `public/assets/css/compiled.css`), así que la fuente se sirve públicamente; el admin usa
  correctamente `src/css/`. eslint está en **9.x** mientras el admin está en **10.x**.
- [ ] **DOC-01 — Deriva documental:** 3 menciones a `ci4-website-builder*` en `CLAUDE.md`.
  Crear el `AGENTS.md` que falta en este repo.

### Fase 7 — Incidente de producción (hosting compartido, límite de procesos)

**Causa raíz:** `PageController::resolve()` hace 5–8 llamadas HTTP síncronas secuenciales por carga
de página (CMS/Catalog/Event + hub), agotando el límite de procesos concurrentes del hosting
compartido. Se evaluó y descartó agregar un agregador en `teatromuseo-bff` porque su `aggregate()`
también es secuencial (y BFF está congelado, fuera de alcance). Confirmado como incidente real
(2026-08-07).

- [x] ~~PERF-01~~ — **completado, pendiente de medir en producción (2026-08-07).**
  `SiteCollectionService::CACHE_TTL` subido de 600s a 3600s (1h), igualando el TTL ya usado por
  `SiteRedirectService`/`SiteSettingsService`/`SocialLinksService` para contenido "muy estable" —
  las ediciones siguen invalidando de inmediato vía `CacheInvalidator` sin importar el TTL. Menor
  riesgo de la cadena PERF, hecho primero para medir antes de seguir.
- [x] **PERF-02 — Batch concurrente de datos dinámicos (completado 2026-08-08).**
  `PageController::resolve()` conserva su cadena de decisión secuencial, pero el render de bloques
  ahora trabaja en dos fases: `BlockPrefetchService` recolecta las consultas de grids/timelines,
  `WebApiClient::multiGet()` las ejecuta agrupadas por dominio y `BlockRenderer` reparte los
  resultados a cada ViewModel. El análisis recorre bloques anidados y los ViewModels reutilizan el
  contexto precargado, evitando una llamada HTTP por bloque.
- [ ] **PERF-03 — Agregación cross-domain en `teatromuseo-cms-domain`.** Solo si PERF-01+02 juntos
  no bastan. No diseñado todavía.

---

## ✅ Completadas

- **CFG-01 — Puerto canónico de web (2026-08-05):** `.env.example` ahora usa `8184`, coherente
  con `start-dev.sh`. Composer quality ✅ (250 tests / 825 assertions, 5 skips).
