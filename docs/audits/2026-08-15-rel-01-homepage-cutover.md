# Auditoría REL-01: activación controlada de homepage

## Objetivo

Validar en `beta.teatromuseo.cl` que la homepage puede operar con snapshot-first,
backend compartido y regeneración protegida antes de considerar cerrada la
activación controlada de `REL-01`.

## Entorno

- Web: `https://beta.teatromuseo.cl`
- BFF: `https://bff.teatromuseo.cl`
- Ruta bajo prueba: `/es/`
- Límite de concurrencia de la prueba: 1, 2, 3 y 4 solicitudes simultáneas.
- No se registran claves, tokens ni valores de configuración sensibles.

## Criterios

- Respuesta HTTP `200` para todas las solicitudes válidas.
- Cero respuestas `508` o `5xx` durante la matriz.
- Backend de snapshots compartido y con retención acotada.
- Una regeneración protegida por identidad cuando se pruebe un miss.
- La invalidación debe preceder a la medición cold y no debe dejar el sitio sin
  una respuesta pública válida.

## Registro inicial

- `GET /health` de Web y BFF: `200`.
- `GET /es`, `/es/contacto` y `/es/cartelera`: `200` después de la invalidación
  post-deploy.
- `GET /cache/status`: `200` con `snapshot_backend.enabled=true`,
  `shared=true`, backend `file`, compresión `gzip`, máximo `5.242.880` bytes y
  retención `3`.

## Proceso

1. Se invalidó únicamente `pages` para locale `es` y ruta `home`; el endpoint
   respondió `200` y reportó un snapshot invalidado y una respuesta HTML
   eliminada.
2. Se midió una regeneración HTTP con cuatro solicitudes simultáneas y luego
   se ejecutaron 20 rondas para cada concurrencia 1, 2, 3 y 4.
3. Se repitió la lectura de la homepage y se compararon bytes, hash y título.

Las observaciones se separan de las causas confirmadas; una respuesta `200` por
sí sola no prueba que el filesystem sea compartido, por lo que el estado
protegido del backend y la ausencia de errores bajo concurrencia se registran
por separado.

### Intentos descartados del arnés

- El primer arnés no esperó correctamente los procesos hijos y superó la
  concurrencia objetivo; produjo `508`, por lo que esa salida no se considera
  evidencia de la aplicación.
- El segundo arnés usó múltiples URLs con `curl --parallel` y mezcló cuerpos
  HTML con sus métricas; también se descartó.
- La matriz final usa `xargs -P`, un archivo por solicitud y concurrencia
  estrictamente limitada; es la única salida cuantitativa válida de esta
  ventana.

## Evidencia final

- Invalidación cold: `200`; snapshot `es/home` invalidado.
- Regeneración a concurrencia 4: `4/4` respuestas `200`, `0` errores.
- Caliente, concurrencia 1: `20/20` `200`, TTFB promedio `77,5 ms`, máximo
  `113,0 ms`.
- Caliente, concurrencia 2: `40/40` `200`, TTFB promedio `80,5 ms`, máximo
  `187,5 ms`.
- Caliente, concurrencia 3: `60/60` `200`, TTFB promedio `112,4 ms`, máximo
  `155,6 ms`.
- Caliente, concurrencia 4: `80/80` `200`, TTFB promedio `153,8 ms`, máximo
  `403,8 ms`.
- Total de matriz válida: `204/204` respuestas `200`, `0` respuestas `5xx` y
  `0` respuestas `508`.
- Dos lecturas consecutivas de `/es` entregaron `87.361` bytes con el mismo
  SHA-256 `f84647556579f17a57e85bdd11ff3f242be7dd508aabe724e67998a9f239e002`.
- El título canónico fue `Inicio | TeatroMuseo`.
- El estado protegido confirmó backend `file`, `shared=true`, compresión gzip,
  máximo `5.242.880` bytes y retención `3`.
- Smoke final: Web `/health`, `/es`, `/es/inicio`, `/es/contacto` y
  `/es/cartelera`: todos `200`.

## Limitaciones y pendientes

- La matriz HTTP no puede leer los contadores EP del cPanel; no se observaron
  `508` en las 204 solicitudes válidas, pero el contador de Entry Processes
  requiere revisión en el panel del hosting.
- FTP no ofrece ejecución remota de `php spark`; queda confirmar manualmente
  que el cron de `cache:warmup --locale es --route home` exista, tenga permisos
  sobre `/home3/cte70303/teatromuseo-data/page-snapshots` y ejecute con el PHP
  de producción.
- `REL-01` queda técnicamente preflight-validado y estable por HTTP, pero no se
  marca cerrado hasta confirmar esos dos controles operativos del hosting.
