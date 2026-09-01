# AGENTS.md — Convenciones para `teatromuseo-web`

## Propósito y Límites

Esta es la aplicación **Web Pública** del Teatro Museo (servidor de renderizado público en el puerto `8184`). Consume contenidos de la API pública de CMS, Catálogo y Eventos.

- **Completamente Stateless:** No posee base de datos local, migraciones ni modelos locales.
- **Acceso Gated por Web App Key:** No maneja sesiones de usuario ni autenticación JWT. Consume endpoints públicos (`/api/v1/public/*`) utilizando `WEB_API_KEY` (`webappkey` filter en las APIs).
- **Caching Fresh & Stale:** Implementa una política de caché agresiva en `WebApiClient` para responder de forma inmediata, con soporte para servir datos obsoletos si los servicios externos caen (5xx o error de red).

---

## Estructura y Capas de la Aplicación

```text
Routes/PageController::resolve() → Services (Site*Service) → WebApiClientInterface → WebApiClient
```

*   `app/Controllers/` — Controladores extremadamente delgados (extienden de `BasePublicWebController` o `BaseController`). Se encargan únicamente de estructurar la respuesta y pasar variables a las vistas.
*   `app/Services/` — Clientes encapsulados (extienden de `BaseSiteService`) que orquestan las llamadas al cliente API tipado y devuelven arrays o nulos. No deben lanzar excepciones ante caídas de la API.
*   `app/ViewModels/` — Clases de transformación que toman respuestas JSON de la API y las transforman en objetos fuertemente tipados listos para renderizar en las vistas (evitando lógica compleja en las plantillas).
*   `app/Libraries/WebApiClient.php` — Implementación del cliente HTTP con manejo de timeouts, Normalización de sobres JSON, y política de caché fresh/stale.

---

## Guardrails de Consistencia (Tests de Arquitectura)

Las políticas arquitectónicas se verifican automáticamente en `composer quality` bajo `tests/unit/Architecture/`:

1.  **`StatelessArchitectureTest` (Cero Base de Datos/Modelos):** Bloquea la adición accidental de código que dependa de modelos locales (`App\Models\`, `CodeIgniter\Model`) o conexiones directas de DB (`Database::connect()`).
2.  **`ControllerServiceDependencyTest` (Separación Controlador-Servicio):** Valida que ningún controlador llame a `webApiClient` de bajo nivel de forma directa. Todas las llamadas deben abstraerse en un Service que herede de `BaseSiteService`.

---

## Anti-patrones Prohibidos

1.  **Crear base de datos local:** Prohibido crear tablas locales, modelos de datos de CI4 o correr migraciones locales.
2.  **Llamar a la API directamente en la vista:** Queda estrictamente prohibido realizar llamadas HTTP o consumir `webApiClient` dentro de los archivos de vistas o con scripts JS cliente para contenido principal.
3.  **Lanzar excepciones no controladas en servicios:** Los servicios del sitio público deben fallar silenciosamente y retornar null o arrays vacíos ante caídas del backend, de modo que el sitio web pueda renderizar estados alternativos o usar la caché obsoleta.
4.  **Bypassear los ViewModels:** Lógica compleja de negocio o de transformación de datos debe vivir en los ViewModels (`app/ViewModels/`), no en los archivos de plantillas PHP.
