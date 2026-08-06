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

---

## ✅ Completadas

- **CFG-01 — Puerto canónico de web (2026-08-05):** `.env.example` ahora usa `8184`, coherente
  con `start-dev.sh`. Composer quality ✅ (250 tests / 825 assertions, 5 skips).
