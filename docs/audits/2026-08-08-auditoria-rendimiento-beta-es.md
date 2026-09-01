# Auditoría de rendimiento — beta.teatromuseo.cl/es

## 1. Objetivo

Probar las pantallas públicas navegables de `https://beta.teatromuseo.cl/es`, medir tiempos de carga y detectar prioridades para mejorar rendimiento percibido, coste de renderizado y uso de CPU en servidor.

La medición externa permite observar TTFB, tiempos de navegación, recursos, payloads y errores. No permite observar directamente CPU, memoria, consultas SQL ni saturación de workers del servidor; esas conclusiones se marcarán como hipótesis hasta contrastarlas con telemetría del entorno beta/producción.

## 2. Entorno

- Fecha de auditoría: 2026-08-08.
- URL base: `https://beta.teatromuseo.cl/es`.
- Navegador: navegador controlado de Codex, viewport por defecto del entorno.
- Código relacionado: `teatromuseo-web` y sus clientes públicos de CMS/Catálogo/Eventos.
- Estado inicial del repositorio: ya existían cambios locales previos no realizados por esta auditoría; no se modifican.

## 3. Proceso log

### Inicio

- Se leyó la documentación del repositorio web y sus instrucciones operativas.
- Se verificó el estado Git antes de intervenir.
- Se conectó la navegación al sitio beta.
- Se recorrieron 58 URLs internas descubiertas desde la portada y desde las
  páginas de listados. No se enviaron formularios con efecto externo; sólo se
  probaron búsquedas GET/AJAX y controles de navegación.
- Para cada URL se midieron tiempos de navegación hasta `commit`,
  `DOMContentLoaded`, `load` y `networkidle` desde el navegador. `commit` es
  una aproximación de la espera de respuesta inicial de la navegación, no un
  TTFB de servidor medido directamente.
- Se repitieron 12 rutas representativas dos veces para comparar caché fría y
  caliente, y se probó un viewport móvil de 390×844 px.
- Se inspeccionaron DOM, imágenes, scripts, hojas de estilo, URLs internas,
  errores de consola visibles y el código de `teatromuseo-web`.

## 4. Findings

### 4.1 Tiempos observados

Los valores siguientes son milisegundos. `1ª` corresponde a la primera
navegación de la serie y `2ª` a la repetición inmediata en la misma sesión.

| Pantalla | commit 1ª | commit 2ª | load 1ª | load 2ª | Observación |
|---|---:|---:|---:|---:|---|
| `/es` | 60 | 32 | 2.911 | 668 | La respuesta HTML es rápida; el hero/recursos eager domina el primer `load`. |
| `/es/cartelera` | 38 | 36 | 53 | 53 | Lista de 12 eventos; HTML observado ≈103 KB. |
| `/es/festivales` | 5.465 | 79 | 5.486 | 95 | Primer acceso bloqueado por una dependencia/cache fría; listado vacío. |
| `/es/exposiciones` | 5.989 | 98 | 6.017 | 117 | Mismo patrón; listado vacío. |
| `/es/teatroescuela` | 5.531 | 72 | 5.546 | 88 | Mismo patrón; listado vacío. |
| `/es/noticias` | 5.491 | 63 | 5.506 | 78 | Mismo patrón; listado vacío en esa primera serie. |
| `/es/videos` | 546 | 61 | 562 | 77 | El contenido cambió entre recorridos; en algunas lecturas hubo 12 tarjetas y en otras estado vacío. |
| `/es/quienes-somos` | 799 | 87 | 1.005 | 293 | Página editorial con HTML ≈74 KB. |
| `/es/historia` | 235 | 238 | 250 | 253 | En esa serie devolvió 404; en una repetición posterior volvió a mostrar la página válida. |
| `/es/museo/coleccion` | 1.153 | 57 | 1.169 | 71 | Catálogo vacío en las lecturas finales. |
| `/es/cartelera/laika-y-los-misterios-del-espacio` | 33 | 30 | 60 | 46 | Detalle de evento válido y rápido cuando la API/cache responde. |
| `/es/contacto` | 231 | 194 | 244 | 209 | 404 reproducible. |

En una revalidación posterior, ya con cachés parcialmente vencidas, volvieron a
aparecer esperas altas: `/es/cartelera` 4.947 ms, `/es/festivales` 5.770 ms y
`/es/editorial` 5.604 ms. Por tanto, el resultado caliente no representa el
peor caso que enfrentará el primer visitante después de una expiración.

### 4.2 Inventario y funcionalidad

- Se descubrieron 58 URLs internas en la navegación pública.
- 31 terminaron mostrando la pantalla 404: `/es/contacto`, siete rutas legales,
  doce detalles de compañías y doce detalles de videos.
- `/es/inicio` y `/es/` terminan redirigiendo a `/public/es`, mientras que la
  portada `/es` declara como canonical `/es/inicio`. El enlace de Inicio y el
  canonical no forman una cadena consistente.
- `/es/contacto` es enlazado desde el menú, hero, portada y footer, pero no
  existe como pantalla válida.
- `/es/historia` y `/es/videos` no fueron deterministas durante la auditoría:
  alternaron entre contenido correcto, 404 o listado vacío en navegaciones
  consecutivas. Esto requiere correlación con logs/cache del dominio antes de
  atribuirlo sólo al frontend.
- Las búsquedas de listados sí actualizaron la URL mediante AJAX, pero una
  búsqueda sin resultados mostró literalmente `Site.collection_listing_empty`
  en vez de una traducción. La petición AJAX trae y parsea HTML completo de la
  página.
- En móvil 390×844 no se observó overflow horizontal; el menú abrió y el
  submenú de “Nosotros” mostró “Quiénes Somos” e “Historia”.

### 4.3 Recursos y carga del cliente

- En todas las pantallas, el logo de cabecera y footer apuntó a
  `http://localhost:8180/uploads/2026/08/03/cade68a2af89-a23c5ca0825edd09.gif`.
  El navegador lo marcó como completado pero con dimensiones 0×0. Es un asset
  imposible desde beta y explica dos recursos rotos por página.
- El hero de la portada también se observó con dimensiones 0×0 en una de las
  lecturas (`/files/{id}/view`), por lo que debe verificarse el `Content-Type`,
  tamaño y caché del proxy de medios.
- Las tarjetas de eventos cargaron imágenes externas de 1080×1350 px mediante
  `<img>` directo y sin `srcset`; en desktop se muestran mucho más pequeñas.
  El coste de decodificación y transferencia puede reducirse con variantes
  del API/CDN y `sizes`.
- Los assets propios no parecen ser el cuello de botella principal: CSS
  generado ≈109,6 KB sin comprimir (≈18,3 KB gzip), Alpine ≈44,8 KB (≈16,2 KB
  gzip) y `site.js` ≈12,5 KB (≈4,5 KB gzip). La página de videos usa thumbnails
  de YouTube lazy y no crea iframes hasta activar el video.

### 4.4 Causas probables de latencia y CPU de servidor

1. **Timeout de API/cache fría — prioridad P0.** La aplicación tiene timeout de
   dominio de 5 s. Las esperas observadas de 5,4–6,0 s coinciden con ese valor.
   En rutas dinámicas, `PageController` intenta redirección y página, luego
   carga todas las colecciones y puede consultar entradas antes de decidir si
   es detalle, índice o 404. Un worker PHP queda ocupado mientras espera al
   dominio aunque las llamadas independientes se agrupen con `curl_multi`.

2. **Resolución de 404 costosa — prioridad P0/P1.** La resolución de una ruta
   desconocida recorre todas las colecciones y consulta el slug de entrada de
   cada una. Esto explica por qué algunos 404 de videos llegaron a ~1,9 s y
   por qué los detalles de compañías/videos fallidos consumen CPU y llamadas
   externas. Hace falta un índice de rutas/colecciones o una resolución por
   prefijo, no una búsqueda secuencial por colección.

3. **Listados CMS con payload/render pesado — prioridad P1.** Los listados de
   12 tarjetas llegaron a ≈98–109 KB de HTML. El origen CMS solicita
   `include=listing_content`; cuando no hay proyección de campos, se puede
   transportar y normalizar más contenido del necesario. Además se generan
   tarjetas y textos largos en PHP aunque la tarjeta sólo necesita título,
   resumen corto, fecha, imagen y slug.

4. **Cache compartida y observabilidad — prioridad P1.** El código depende de
   caché de respuestas de API y de HTML completo durante 300 s. El patrón
   caliente <100 ms frente a frío >5 s sólo será estable si el backend usa un
   almacén compartido entre workers y se monitorizan hit/miss, expiraciones,
   stale fallback y duración por endpoint. Debe confirmarse la configuración
   real de beta; el repositorio declara `file` como opción por defecto.

5. **Filtro AJAX con render completo — prioridad P1.** Cada búsqueda hace
   `fetch()` de una página HTML completa, la parsea con `DOMParser` y reemplaza
   sólo la grilla. Eso mantiene el coste de layout, footer, menús, SEO y
   serialización para una actualización que podría devolver sólo tarjetas y
   paginación.

6. **Llamadas globales repetidas — prioridad P2.** El layout precarga cuatro
   recursos globales en una cache miss (tres menús y settings) y el footer
   vuelve a invocar `SocialLinksService` para leer settings. Con cache caliente
   el coste es menor, pero en cold start agrega trabajo a cada worker. Conviene
   pasar los social links ya precargados al layout y medir la necesidad real de
   los cuatro menús en cada petición.

## 5. Corrections Applied

- `WEB-PERF-01`: `WebApiClient` registra eventos JSON `[web-api]` con ruta
  navegada, endpoint remoto, duración, status, scope, cache hit/miss, stale y
  timeout. La bandera `WEB_API_TELEMETRY` habilita estos eventos en beta sin
  cambiar el umbral normal de producción.
- `WEB-PERF-02`: `PageController` construye un índice local por prefijo y sólo
  consulta la colección cuyo prefijo coincide. Una ruta desconocida verificada
  en beta generó cero llamadas al scope `entries`.
- Las dos correcciones fueron desplegadas a beta el 2026-08-09 UTC.

## 6. Evidence

- Datos de navegación: 58 URLs recorridas; 31 respuestas visuales 404.
- Datos de carga: serie de 12 rutas con dos repeticiones y revalidación de
  rutas con cache fría/caliente.
- Datos de cliente: viewport móvil 390×844 sin overflow horizontal; menú y
  submenú móvil operativos; assets observados con `pageAssets` y DOM.
- Correlación de código: `BasePublicWebController`, `PageController`,
  `WebApiClient`, `LayoutDataPrefetchService`, `CollectionListingViewModel`,
  `collectionFilters.js` y vistas de imágenes/layout.

## 7. Pending Work

- Correlacionar los `508` devueltos por CMS/Catalog/Event con los límites de
  procesos del hosting; la instrumentación web ya identifica `path`, status,
  duración, cache hit/miss y `timeout` por scope.
- Medir TTFB real y CPU por request desde reverse proxy/APM, separando web,
  CMS-domain, catalog-domain y event-domain.
- Verificar configuración efectiva de beta: `WEB_API_TIMEOUT`, backend de
  caché, TTL de HTML, `cacheQueryString`, compresión y CDN de medios.
- Reparar el contrato de URLs/slug para contacto, legales, historia,
  compañías y videos; regenerar menú, sitemap y caches después del cambio.
- Verificar que ningún asset publicado contenga `localhost` y que cada media
  proxy responda con `200`, `Content-Type` de imagen, `Cache-Control` y variante
  optimizada.

## 8. Automation Opportunities

- Añadir un smoke test de crawl desde la portada que falle si un enlace interno
  devuelve 404, si el canonical no es navegable o si contiene `localhost`.
- Añadir una prueba de performance por ruta con presupuesto: HTML/TTFB,
  `load`, número de imágenes y tamaño de payload, separando cold/warm cache.
- Instrumentar `WebApiClient` con métricas por endpoint y cache state; exportar
  percentiles p50/p95/p99 y timeout rate.
- Probar automáticamente filtros/paginación en modo AJAX y validar que las
  respuestas parciales no incluyan el layout completo.
- Añadir validación de contenido que detecte claves i18n sin traducir como
  `Site.*` en HTML publicado.

## 9. Final Summary

El mayor riesgo de rendimiento no está en los bundles del navegador, sino en
las rutas dinámicas cuando la cache está fría o una API de dominio tarda: se
observaron 5,4–6,0 s en varias colecciones, contra <100 ms en la repetición
caliente. La primera prioridad es instrumentar y eliminar la espera de 5 s,
reducir la búsqueda secuencial de colecciones en rutas desconocidas y asegurar
una caché compartida con invalidación observable.

En paralelo hay bloqueos funcionales que afectan directamente el uso: 31 URLs
internas terminan en 404, el canonical de la portada apunta a una URL que
redirige a `/public/es`, los assets del logo apuntan a localhost y el contenido
de historia/videos no fue estable durante la prueba. Es necesario corregir
estos contratos antes de usar los tiempos como una línea base definitiva.

## 10. Validación post-deploy de WEB-PERF-01/02 — 2026-08-09 UTC

Se desplegaron `app/Controllers/PageController.php`,
`app/Libraries/WebApiClient.php` y el ajuste de logging de beta. La muestra
secuencial realizó 44 solicitudes: una variante fría por ruta y tres
navegaciones calientes sin concurrencia.

| Ruta | Fría ms | Caliente p50/p95/p99 ms | Resultado |
|---|---:|---:|---|
| `/es` | 8048 (timeout) | 78/82/82 | 200 caliente |
| `/es/cartelera` | 250 | 93/98/98 | 200 |
| `/es/festivales` | 6173 | 82/89/90 | 200 |
| `/es/exposiciones` | 6170 | 88/91/91 | 200 |
| `/es/teatroescuela` | 5462 | 81/83/83 | 200 |
| `/es/noticias` | 5446 | 77/88/89 | 200 |
| `/es/videos` | 531 | 99/105/105 | 200 |
| `/es/quienes-somos` | 553 | 104/443/473 | 200 |
| `/es/historia` | 275 | 733/1281/1330 | 404/200 |
| `/es/museo/coleccion` | 1853 | 80/131/136 | 200 |
| `/es/contacto` | 242 | 77/87/87 | 200 |

Agregado: p50 `91.83 ms`, p95 `6063.73 ms`, p99 `7241.98 ms`, un timeout
de 44 (`2.27%`) y cuatro respuestas HTTP erróneas (`9.09%`, incluyendo
`404`/`508`). La muestra concurrente inicial fue descartada como baseline
porque provocó saturación `508` del hosting compartido.

Los logs de beta correlacionaron los casos lentos: `entries` tuvo p50 cercano a
5 s y dos timeouts; `pages`, `events`, `taxonomies` y `languages` devolvieron
`508` en distintos probes, mientras `menus`, `settings` y `collections` fueron
mayoritariamente cache hits sub-milisegundo. La ruta inexistente
`/es/web-perf-unknown-route-20260809` respondió `404` en `253 ms` y registró
cero llamadas `entries`.
