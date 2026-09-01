# Auditoría: detalles localizados y enlaces por idioma

## Objetivo

Evaluar si la implementación de los enlaces de detalle y cambio de idioma es
robusta, mantenible, escalable y consistente con el contrato vigente
`page-resolve` de Web/BFF, sin introducir un pipeline paralelo ni deuda técnica
innecesaria.

## Entorno

- Alcance: `teatromuseo-web` + `teatromuseo-bff`.
- Rutas: detalles de eventos y catálogo; índices y tarjetas que generan esos
  enlaces.
- Contrato: `page-resolve/{locale}/{route}`.
- Runtime local: PHP 8.5.5; el proyecto declara PHP mínimo 8.2.
- Estado inicial: cambios locales no comiteados; se conservaron cambios
  preexistentes fuera de este alcance.

## Registro del proceso

### Inicio — revisión estática

- Se revisaron `CONTEXT.md`, ADR 0001, ADR 0002, ADR 0003 y
  `docs/PAGE_DELIVERY.md`.
- La implementación se contrastó con el envelope BFF, `PageResolver`, los
  lectores públicos de eventos/catálogo y `BasePublicWebController`.
- Confirmado: `PageResolver` solicita la proyección de detalle por defecto
  (`fields=[]`); los lectores BFF cargan allí el mapa completo por locale y
  los listados conservan el filtro locale solicitado + fallback.
- Confirmado: `PageResolver` entrega `page.localized_slugs` con la ruta pública
  localizada más el slug del ítem.
- Confirmado: Web reconoce `cms_page` + `source_page_type` para distinguir
  detalles de evento y catálogo de índices de dominio.

### Cobertura añadida

- Se agregó cobertura específica para detalle de catálogo, slugs provenientes
  de traducciones y mapas localizados completos de evento/catálogo.
- Se confirmó que `PageResolver` invoca los detalles con `fields=[]`. Esa
  proyección por defecto también debe traer todos los slugs; se añadió una
  regresión SQL sobre la conexión SQLite efímera de pruebas y una suite
  MySQL 8 opt-in de CI para ambos lectores.

### Calidad y regresión visual

- Web `composer quality`: 385 tests, 1420 assertions, 5 skipped, PHPStan sin
  errores, CS-Fixer/i18n/fixture policy correctos.
- BFF `composer quality`: 180 tests, 532 assertions, 1 skipped, PHPStan y
  arquitectura sin errores, CS-Fixer correcto.
- Las suites reportan una deprecación de PHPUnit y skips ya existentes; no son
  fallos introducidos por esta implementación.
- La prueba visual del navegador confirmó el flujo inglés → menú `fr` →
  `/fr/programmation/...`, título francés y consola sin errores.

## Hallazgos

### Hallazgo 1 — contrato de slug incompleto en el lector de detalle — cerrado

- Síntoma: el cambio de idioma podía reutilizar el slug del locale actual.
- Causa: el lector BFF limitaba `slugs` a locale solicitado + fallback, aunque
  el detalle necesita el mapa completo para construir sus enlaces alternos.
- Corrección: los lectores de eventos y catálogo solo aplican ese filtro para
  listados; un detalle obtiene el mapa completo incluso con `fields=[]`.

### Hallazgo 2 — prefijo de ruta incorrecto en Web — cerrado

- Síntoma: francés se generaba como `/fr/programming/...` y terminaba en 404.
- Causa: el envelope normaliza detalles como `cms_page` y conserva el tipo
  fuente; Web no lo reconocía como detalle de dominio y no reconstruía el
  prefijo público por locale.
- Corrección: Web usa `PublicPaths` para reconstruir la ruta y conserva el
  slug configurado del locale destino.

### Hallazgo 3 — cobertura incompleta del contrato — cerrado

- Antes solo había una regresión de evento y casos unitarios parciales.
- Se añadieron pruebas de catálogo, traducciones, fallback de slug y mapas
  completos en `PageResolver`.

### Hallazgo 4 — seam de rutas duplicado entre Web y BFF — cerrado con contrato

- Web mantiene la política de URL pública y BFF conserva una copia de solo
  lectura para resolver `page-resolve`, tal como documentan
  `PublicPaths`/`PublicPagePaths` y el ADR vigente.
- Esto es un seam arquitectónico explícito entre repositorios, no una segunda
  pipeline de render. Web mantiene el artefacto canónico
  `docs/contracts/public-routes.json`; ambos adaptadores exportan la misma
  matriz versionada y los workflows de Web y BFF la comparan en PHP 8.2.
- No se agrega una librería compartida ni una llamada HTTP de configuración:
  romperían la independencia de despliegue para una política estática.

### Hallazgo 5 — cobertura SQL directa del nuevo filtro — cerrado en alcance

- La suite BFF declara deliberadamente que no tiene una base de datos de
  aplicación ni migraciones. Se añadieron fixtures desechables fuera del
  runtime: SQLite para la regresión local rápida y MySQL 8 en
  `composer test:integration` para validar el dialecto real. Ambos ejecutan
  los builders SQL de Event y Catalog y prueban `show(..., fields=[])` con
  ES/EN/FR/PT.
- La suite MySQL no duplica migraciones productivas: crea únicamente las
  tablas/columnas mínimas del contrato y corre en un servicio efímero de CI.
  La paridad completa del esquema sigue perteneciendo a los repositorios de
  dominio y sus smoke/contratos.

### Hallazgo 6 — runtime de calidad distinto al mínimo declarado — cerrado en CI

- Las gates locales se ejecutaron con PHP 8.5.5 y el proyecto declara PHP
  mínimo 8.2. Los dos workflows ya tienen matriz explícita 8.2–8.5 y ahora
  ejecutan `composer check-platform-reqs --no-dev` después de instalar.
- CS-Fixer mantiene una advertencia informativa sobre la versión local, pero
  no un fallo. El gate mínimo efectivo queda en CI/PHP 8.2.

## Correcciones aplicadas

- Se amplió el contrato BFF para entregar todos los slugs locales en detalle.
- Se normalizaron las rutas de detalle en Web con `PublicPaths`.
- Se mantuvo el fallback de slug únicamente para compatibilidad con payloads
  antiguos o incompletos.
- Se agregó cobertura de evento, catálogo, traducciones y rutas localizadas.
- Se documentó el contrato en `docs/PAGE_DELIVERY.md`.
- Se versionó el manifiesto de rutas públicas y se añadió comparación
  cross-repository en CI.
- Se añadió `composer test:integration` con MySQL 8 efímero en CI para la
  proyección SQL de slugs.
- El Dockerfile valida `composer check-platform-reqs --no-dev` dentro de la
  imagen final PHP 8.2; el `--ignore-platform-reqs` queda limitado al stage
  aislado de Composer.
- Se añadió `CONTEXT.md` y ADR local al BFF para conservar la decisión fuera
  del workspace monorepo.

## Evidencia

- Web: `composer quality` terminó con código 0 y 385 tests / 1420 assertions.
- BFF: `composer quality` terminó con código 0 y 180 tests / 532 assertions.
- `git diff --check` no reporta errores.
- `composer check-platform-reqs --no-dev` pasó localmente en ambos
  repositorios.
- El export de rutas Web/BFF es idéntico y el manifiesto canónico está
  actualizado.
- La suite de integración MySQL se descubrió correctamente y queda protegida
  por `RUN_PUBLIC_READ_INTEGRATION=1`; no se ejecutó localmente porque este
  entorno no tiene un servidor MySQL ni un daemon Docker disponible.
- El build de imagen debe ejecutar ahora la validación de plataforma contra el
  runtime final de PHP 8.2 en CI.
- Navegador: inglés → menú `fr` llegó a `/fr/programmation/...`, renderizó el
  título francés y reportó `logs: []`.

## Trabajo pendiente

- No hay defectos funcionales conocidos bloqueando el flujo.
- Las fixtures SQL cubren el filtro condicional y el contrato de slugs; no
  simulan el esquema productivo completo, por diseño del BFF stateless.
- Si se agregan locales o se cambian rutas públicas, actualizar el manifiesto
  canónico y ambos adaptadores en el mismo cambio; CI bloqueará la deriva.

## Automatización futura

- Mantener la comprobación de paridad de rutas entre `PublicPaths` y
  `PublicPagePaths` en ambos CI, sin compartir runtime entre repositorios.
- Añadir un smoke localizado por cada locale publicado al checklist de
  despliegue.
- Medir la consulta de slugs completa en índices de producción; el detalle
  trae todas las variantes por diseño, mientras el listado sigue acotado.

## Resumen final

La implementación es consistente con la arquitectura actual y está validada
funcionalmente. La deriva de rutas, la regresión SQL de `fields=[]` y la
verificación de plataforma ya tienen controles automatizados. No hay deuda
funcional conocida ni un pipeline paralelo nuevo. La suite SQL deliberadamente
no duplica el esquema MySQL de los dominios; la paridad de ese esquema sigue
siendo responsabilidad de los repositorios de dominio y sus smoke/contratos.
