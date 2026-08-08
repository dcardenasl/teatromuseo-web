# TASKS — teatromuseo-web

> Fuente de verdad para trabajo abierto en este repositorio.
> Seguimiento cross-repo: [`../TASKS.md`](../TASKS.md).
> Creado el 2026-08-05: esta era la única app activa sin `TASKS.md`, pese a que la convención
> documentada en el `CLAUDE.md` raíz obliga a leerlo antes de escribir código.

---

## 🔴 En progreso

*(vacío)*

---

## 🟡 Próximo

*(vacío)*

### Fase 3 — Smart Prefetch & Block Analysis (✅ COMPLETADA)

Todas las tareas de optimización de performance (PERF-02, PERF-03) dependen de estas.
Ver [`../TASKS.md`](../TASKS.md) para el estado cross-repo.

---

## ✅ Completadas

### Task #10 — Verificación Final (2026-08-08) ✅ COMPLETADA

**Estado:** ✅ COMPLETADA

Verificado:
- ✅ `composer quality` verde en teatromuseo-web
- ✅ BlockAnalyzerService con unit tests ✅
- ✅ SmartPrefetchService con unit tests ✅
- ✅ ParallelAliasResolver con unit tests ✅
- ✅ Event-domain sparse fieldsets implementado
- ✅ Documentación completa (4 archivos markdown)
- ✅ Changelog actualizado

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

### Task #7 — Integración en PageController (2026-08-08) ✅ COMPLETADA

**Estado:** ✅ COMPLETADA

Integración en `app/Controllers/PageController.php`:
- `resolve()` method usa BlockAnalyzer para detectar requirements
- SmartPrefetch ejecuta batch paralelo de API calls
- ContextHolder inyecta datos en viewmodels
- Fallback gracioso si algún batch falla

Flujo:
1. Cargar página CMS + blocks
2. BlockAnalyzer analiza blocks → detecta requirements
3. SmartPrefetch → paralleliza llamadas por tipo
4. ContextHolder::inject() → viewmodels acceden datos sin API calls

No requiere cambios en routes, filtros, o middleware.

---

### Task #6 — SmartPrefetchService con interface + tests (2026-08-08) ✅ COMPLETADA

**Estado:** ✅ COMPLETADA

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

### Task #5 — BlockAnalyzerService con interface + tests (2026-08-08) ✅ COMPLETADA

**Estado:** ✅ COMPLETADA

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

### Task #9 — Documentación de Sparse Fieldsets y Smart Prefetch (2026-08-08)

**Estado:** ✅ COMPLETADA

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
- [ ] **PERF-02 — Paralelizar llamadas de `WebApiClient` con `curl_multi_init`.** Análisis
  (2026-08-07): `PageController::resolve()` **no** es paralelizable de forma trivial — es una
  cadena de decisión secuencial por diseño (redirect → prefijo de colección → página CMS → entrada
  → 404), cada paso condicionado al resultado del anterior. La oportunidad real está en
  `BlockRenderer::render()`: cada bloque de una página (`collection_grid`, `cards_slider`,
  `collection_timeline`, etc.) llama a su propio servicio de forma independiente entre sí. Requiere
  reestructurar el renderizado en dos fases (recolectar todas las peticiones necesarias → ejecutar
  en batch concurrente → repartir resultados a cada ViewModel) — no es un cambio de una línea.
  Diferido deliberadamente hasta medir el impacto real de PERF-01 en producción/staging.
- [ ] **PERF-03 — Agregación cross-domain en `teatromuseo-cms-domain`.** Solo si PERF-01+02 juntos
  no bastan. No diseñado todavía.

---

## ✅ Completadas

- **CFG-01 — Puerto canónico de web (2026-08-05):** `.env.example` ahora usa `8184`, coherente
  con `start-dev.sh`. Composer quality ✅ (250 tests / 825 assertions, 5 skips).
