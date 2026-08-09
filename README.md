# ci4-website-builder-web

Sitio público de Website Builder construido con CodeIgniter 4. Renderiza páginas,
colecciones, entradas, formularios y bloques dinámicos consumiendo el API público
de `ci4-website-builder-domain`.

## Stack

- CodeIgniter 4.7
- PHP 8.2+
- Tailwind CSS 4
- esbuild + ESLint para JavaScript modular
- PHPUnit + PHPStan level 8 + PHP CS Fixer

## Puertos Locales

| Servicio | Puerto | URL |
| :--- | :--- | :--- |
| API Hub | `8180` | `http://localhost:8180/` |
| Admin | `8182` | `http://localhost:8182/` |
| Sitio público | `8184` | `http://localhost:8184/` |
| Domain CMS | `8190` | `http://localhost:8190/` |

Usa `localhost` para navegar. No mezcles `localhost` y `127.0.0.1` durante
pruebas con sesión/CSRF.

## Arranque

Desde la raíz del monorepo:

```bash
cd ci4-website-builder-api && php spark serve --port 8180
cd ci4-website-builder-domain && php spark serve --port 8190
cd ci4-website-builder-admin && php spark serve --port 8182
cd ci4-website-builder-web && php spark serve --port 8184
```

Para trabajar en assets del sitio público:

```bash
npm run dev:css
npm run dev:js
```

## Configuración

Variables principales:

```dotenv
app.baseURL=http://localhost:8184/
app.defaultLocale=es
WEB_API_BASE_URL=http://localhost:8190
WEB_API_KEY=web_api_test_key
WEB_API_TIMEOUT=5
WEB_API_STALE_TTL=86400
CSP_IMAGE_SRC="self http: https: data:"
CSP_FRAME_SRC="self http: https:"
CSP_MEDIA_SRC="self http: https:"
CSP_OBJECT_SRC="self http: https:"
CACHE_INVALIDATE_KEY=change-me
cache.handler=file
```

`CACHE_INVALIDATE_KEY` debe estar configurado en producción. El webhook de
invalidación rechaza claves vacías o incorrectas.

`CSP_*` se deja abierto por defecto en el starter para que los seeders puedan
cargar imágenes, documentos y embeds remotos durante la puesta en marcha. Si
quieres endurecer la política después, cambia esos valores a `self` o a una
lista de hosts concretos en `.env`.

## Arquitectura

- `PageController` resuelve rutas públicas dinámicas: colección, entrada, página
  CMS, redirect y 404.
- `FormController` valida campos, honeypot y CAPTCHA antes de enviar al Domain.
- `WebApiClientInterface` permite fakear el cliente HTTP en tests.
- `WebApiClient` guarda caché fresca y copia stale para tolerar caídas del
  Domain en errores de red o `5xx`.
- `BaseSiteService` centraliza el patrón de servicios API.
- `BlockRenderer` usa ViewModels para bloques con lógica no trivial.
- `src/js/` contiene el código fuente JavaScript; `public/assets/js/site.js` es
  un artefacto generado y versionado por `filemtime()`.

## Bloques Con ViewModel

- `hero_slider`
- `cards_slider`
- `video_player`
- `form_embed`
- `collection_grid`
- `metrics_grid`

Para agregar un ViewModel, extiende
`app/ViewModels/Blocks/AbstractBlockViewModel.php`, registra el bloque en
`BlockRenderer::VIEW_MODELS` y cubre normalización/defaults con tests unitarios.

## Comandos De Calidad

```bash
composer test
composer test:unit
composer test:feature
composer analyse
composer format:check
composer quality

npm run lint:js
npm run build:all
```

La política de PHPStan baseline es decreasing-only: no agregues deuda nueva al
baseline para cerrar cambios ordinarios.

## Smoke Manual

Con los cuatro servidores activos:

1. Abrir `http://localhost:8184/` y confirmar redirección/localización.
2. Abrir `http://localhost:8184/es`.
3. Abrir una colección publicada.
4. Abrir una entrada publicada.
5. Enviar un formulario CMS válido.
6. Invalidar caché con `POST /cache/invalidate` y un `X-Invalidate-Key` válido.
7. Probar stale cache: cargar una página, detener Domain, recargar y confirmar
   que el sitio sigue renderizando desde caché stale.

## Reglas De Mantenimiento

- No devolver lógica pesada a las vistas de bloques.
- No editar `public/assets/js/site.js` directamente; edita `src/js/` y rebuild.
- No servir stale cache para respuestas `4xx`.
- No reintroducir archivos de ejemplo del starter de CodeIgniter.
- No bajar PHPStan ni crecer el baseline como atajo.
