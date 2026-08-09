# Auditoría funcional — beta vs localhost

## 1. Objetivo

Determinar por qué `https://beta.teatromuseo.cl/es` presenta 404, contenido
vacío e imágenes ausentes aunque localhost use la misma base de datos.

La prueba se centró en separar datos de base de datos, respuesta efectiva de
las APIs, dependencias desplegadas, configuración de entorno, caché y URLs de
medios.

## 2. Entorno

- Fecha: 2026-08-08.
- Beta: `https://beta.teatromuseo.cl/es`.
- Local: `http://localhost:8184/es`.
- Aplicaciones relacionadas: `teatromuseo-web`, CMS domain, catalog domain,
  event domain y Hub.
- Navegador: navegador controlado de Codex.
- No se enviaron formularios ni se modificaron datos de usuario.
- No se modificó código de aplicación. Sólo se creó este registro.

## 3. Proceso log

1. Se cargó la portada en beta y localhost.
2. Se compararon los mismos listados, páginas editoriales y detalles de
   eventos.
3. Se inspeccionaron DOM, títulos, encabezados, imágenes y dimensiones
   naturales de los recursos.
4. Se revisaron las rutas y los contratos de resolución de páginas, eventos y
   archivos.
5. Se revisó el documento de incidente de producción y el script FTP para
   comprobar si las dependencias y variables de entorno llegan realmente a
   beta.

## 4. Findings

### 4.1 La base de datos no es la diferencia determinante

Las mismas rutas producen resultados distintos:

| Caso | Beta | Localhost |
|---|---|---|
| `/es/contacto` | 404 | Página `Contacto` con formulario |
| `/es/quienes-somos` | 404 | Página válida |
| `/es/historia` | 404 | Página válida |
| `/es/aviso-legal` | 404 | Página válida |
| `/es/cartelera/laika-y-los-misterios-del-espacio` | 404 | Detalle válido |
| `/es/cartelera/quedate-una-comedia-en-tres-actos` | 404 | Detalle válido |
| `/es/cartelera/historia-de-gatitos-de-colores` | 404 | Detalle válido |

En la portada local aparecen cursos y noticias publicados; en beta aparecen
los estados vacíos correspondientes. Por lo tanto, que las bases sean iguales
no garantiza que las aplicaciones entreguen la misma respuesta: los procesos
pueden usar distinto código, `vendor`, variables, clave de API o caché.

**Clasificación:** confirmado; divergencia de runtime/API/configuración, no
ausencia demostrada de registros en la base.

### 4.2 Dependencias de producción desfasadas — causa confirmada para API

El propio repositorio documenta que las APIs de producción estaban devolviendo:

```text
Trait "dcardenasl\\CI4ApiCore\\Traits\\SparseFieldsetTrait" not found
```

El documento identifica como causa un `ci4-api-core` antiguo en producción.
Los cambios de código se subieron por FTP, pero `vendor/` y `composer.lock`
quedan fuera de ese despliegue. Local sí tiene la dependencia instalada.

Esto explica de forma directa los listados vacíos y es compatible con los 404 de
detalles: la web trata una respuesta fallida o sin entidad como `null` y luego
renderiza 404.

**Clasificación:** causa confirmada por documentación y coherente con la
comparación; debe revalidarse en los endpoints activos después de corregir
dependencias.

Evidencia: [PRODUCTION_FIX_REQUIRED.md](/Users/davidcardenas/Developer/PHP/teatromuseo/teatromuseo-web/.performance/PRODUCTION_FIX_REQUIRED.md:10)
 y [deploy.py](/Users/davidcardenas/Developer/PHP/teatromuseo/teatromuseo-web/.deploy/deploy.py:17).

### 4.3 Beta tampoco está recibiendo el mismo contenido CMS

La web solicita una página mediante `public/{lang}/pages/{slug}`. Si la API no
devuelve la página, `PageController` termina en 404. La ruta local funciona con
el mismo slug, así que en beta hay que comprobar la respuesta efectiva del CMS
(host, clave, estado HTTP y caché), no cambiar primero los slugs del frontend.

**Clasificación:** divergencia confirmada; causa exacta pendiente de verificar
en la configuración/logs del servidor beta. Candidatos: `WEB_API_BASE_URL`
incorrecto, `WEB_API_KEY` no válida para el CMS, CMS domain desactualizado o
caché persistida de respuestas vacías/errores.

Evidencia: [SitePageService.php](/Users/davidcardenas/Developer/PHP/teatromuseo/teatromuseo-web/app/Services/SitePageService.php:20)
 y [PageController.php](/Users/davidcardenas/Developer/PHP/teatromuseo/teatromuseo-web/app/Controllers/PageController.php:138).

### 4.4 URLs de medios mezcladas entre entornos

En beta el logo apunta literalmente a:

```text
http://localhost:8180/uploads/2026/08/03/cade68a2af89-a23c5ca0825edd09.gif
```

En localhost esa URL carga y mide 250×200; desde beta queda en 0×0. El hero de
beta también llegó como `/files/{id}/view` y no produjo una imagen válida.

Hay dos fallos relacionados:

- La configuración CMS/settings contiene o devuelve una URL de Hub local.
- El frontend tiene un fallback que inventa `/files/{id}/view`, aunque ese no es
  un endpoint público de la web.

El contrato CMS exige que el backend devuelva la URL pública final y que el
frontend no invente rutas de archivos. Ver
 [FILES.md](/Users/davidcardenas/Developer/PHP/teatromuseo/teatromuseo-cms-domain/docs/architecture/FILES.md:15)
 y [AbstractBlockViewModel.php](/Users/davidcardenas/Developer/PHP/teatromuseo/teatromuseo-web/app/ViewModels/Blocks/AbstractBlockViewModel.php:231).

El CMS tiene correcciones locales para usar el `hub.url` en esos fallbacks,
pero beta debe tener tanto ese código como un `HUB_URL` público correcto y una
respuesta de Hub accesible desde el servidor. No conviene solucionar esto
agregando una ruta `/files` al frontend.

**Clasificación:** causa confirmada para el logo; causa confirmada en el
contrato para el hero; el origen exacto de cada registro debe corregirse en CMS,
Hub/configuración y cachés.

## 5. Correcciones aplicadas

- Ninguna corrección funcional ni de datos.
- Se creó este registro de auditoría.

## 6. Acción correctiva priorizada

### P0 — Sincronizar dependencias de los servicios API

En los hosts de CMS/Catálogo/Eventos que sirven beta:

1. desplegar el `composer.lock` correcto;
2. ejecutar `composer install --no-dev` según la política del hosting;
3. reiniciar PHP-FPM/worker si aplica;
4. verificar que ningún endpoint público responda `Trait ... not found`.

### P0 — Verificar la configuración efectiva de beta

Sin imprimir secretos, comparar en el proceso PHP de beta:

- `WEB_API_BASE_URL` y sus hosts reales;
- la identidad/longitud de `WEB_API_KEY` frente a la registrada en cada
  dominio;
- `HUB_URL` del CMS/domain y su conectividad desde el servidor;
- backend, TTL e invalidación de caché.

Después, limpiar sólo las cachés de aplicación de beta y recalentar una página
por scope. No basta con borrar la caché del navegador.

### P0 — Validar contratos de medios

Para settings, hero, cursos, noticias y eventos, cada respuesta pública debe
devolver una URL `https://...` accesible desde un navegador externo, con `200`,
`Content-Type` de imagen y dimensiones mayores que cero. Debe desaparecer todo
`localhost` del HTML publicado.

### P1 — Añadir una prueba de paridad

Automatizar, contra beta y local, una matriz de rutas que compruebe:

- estado HTTP y título;
- presencia de contenido no vacío;
- ausencia de `localhost` y `/files/{id}/view` en recursos públicos;
- carga real de cada imagen (`naturalWidth > 0`);
- ausencia de claves i18n sin traducir.

## 7. Pending Work

- Confirmar los estados HTTP reales de los endpoints API de producción después
  de instalar dependencias.
- Revisar el `.env` efectivo del servidor beta; el archivo local no representa
  automáticamente el entorno FTP.
- Invalidar las cachés de web y CMS después de corregir URLs y dependencias.
- Repetir el crawl completo y comprobar también `/en`, `/fr` y `/pt`, que en la
  prueba pública mostraron rutas de idioma no resueltas.

## 8. Final Summary

La diferencia no está en la base de datos: beta está ejecutando una combinación
distinta de dependencias, configuración/API y datos cacheados. El incidente de
`SparseFieldsetTrait` explica los fallos de Catálogo/Eventos; el CMS beta además
no está devolviendo las mismas páginas que localhost; y los medios contienen
URLs locales o fallbacks no públicos. El orden correcto es sincronizar
dependencias y configuración, limpiar cachés, corregir la resolución de URLs y
recién después volver a evaluar el frontend.
