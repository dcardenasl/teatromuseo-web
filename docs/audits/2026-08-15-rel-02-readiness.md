# Auditoría REL-02 — preparación de migración pública

**Fecha:** 2026-08-15  
**Alcance:** `teatromuseo-web` + `teatromuseo-bff`  
**Estado:** implementación preparada; rollout pendiente de cierre operativo de REL-01

## Resultado

La implementación vigente ya cubre los tipos de ruta definidos por REL-02 a
través de `page-resolve`, sin reintroducir un pipeline paralelo en Web:

| Tipo de ruta | Contrato | Estado de implementación |
|---|---|---|
| Homepage | `page-resolve/{locale}/home` | Verificado |
| Página CMS simple | `page-resolve/{locale}/{route}` | Verificado |
| Página CMS con bloques | `page-resolve/{locale}/{route}` | Verificado |
| Entrada de colección | `page-resolve/{locale}/{collection}/{entry}` | Verificado |
| Índice de fallback | `page-resolve/{locale}/{collection}` | Verificado |
| Detalle de evento | `page-resolve/{locale}/{route}` | Verificado |
| Detalle de catálogo | `page-resolve/{locale}/{route}` | Verificado |
| Preview firmado | mismo contrato con parámetros HMAC | Verificado |

Los detalles de evento y catálogo se resuelven dentro del envelope del BFF y
no vuelven a abrir lecturas de dominio desde Web. Los bloques dinámicos,
entradas relacionadas y fallos aislados permanecen dentro del mismo contrato.

## Gate local reproducible

Se ejecutaron inicialmente únicamente pruebas focales, sin cambios en hosting:

- Web: `23/23` tests, `86` assertions, resultado `OK`.
- BFF: `28/28` tests, `135` assertions, resultado `OK`; PHPUnit reportó una
  deprecación conocida, sin fallos.

Cobertura focal:

- resolución de páginas, detalles, listados y preview en Web;
- `BlockTreeResolver`, aliases, redirects, entradas, relacionados, fallback y
  preview en BFF;
- validación de fechas, rangos y fieldsets del PublicRead.

## Canarios seriales de observación

El 2026-08-15 entre 20:53 y 20:54 UTC se hizo una sola navegación por cada
ruta, en serie. Siete rutas usaron una variante `cold_probe` inédita para
evitar que el HTML ya cacheado ocultara el camino de composición; `/es/nosotros`
corresponde a la navegación inicial de la misma sesión, sin esa variante.
Todas las respuestas fueron `200` y la consola no reportó errores:

| Ruta | Primer byte | `load` completo |
|---|---:|---:|
| `/es/nosotros` | 658 ms | 1.079 s |
| `/es/inicio` | 579 ms | 0.858 s |
| `/es/cartelera` | 87 ms | 0.215 s |
| `/es/museo/coleccion` | 123 ms | 0.151 s |
| `/es/contacto` | 967 ms | 1.002 s |
| `/es/teatroescuela` | 573 ms | 0.718 s |
| `/es/historia` | 351 ms | 0.761 s |
| `/es/noticias/lanzamiento-del-libro-los-horribles` | 789 ms | 1.186 s |

La variante solo evita el hit de HTML de esa URL; no es una prueba de carga y
las navegaciones fueron estrictamente seriales. En esta observación no se
reprodujeron los 15 segundos. El episodio sigue siendo compatible con una
espera transitoria de workers/Entry Processes durante la ventana de saturación
de cPanel, pero la causa histórica no puede demostrarse solo con estas
métricas.

## Bloqueo operativo

REL-02 depende de cerrar REL-01. La captura de cPanel del 2026-08-15 muestra
CPU e I/O limitados y `451` límites de Entry Processes. La primera matriz de
carga de esta ventana tuvo un error de arnés y probablemente contribuyó al
pico; por eso esta auditoría no ejecuta nuevas pruebas HTTP ni intenta
atribuir el contador al código de producción.

El cron de warm-up permanece sin cambios: su log confirmó `16/16 successful or
skipped`, por lo que no está regenerando snapshots innecesariamente.

## Siguiente gate

1. Esperar una ventana limpia de cPanel sin tráfico sintético.
2. Revisar CPU, I/O y Entry Processes en `Current usage`/`Snapshot`.
3. Repetir como máximo un canario individual por ruta después de esa ventana
   limpia y comparar con esta matriz.
4. Cerrar REL-01 y registrar REL-02 como rollout validado solo si la alerta no
   reaparece.
5. Dejar `CLEAN-01` para después de una ventana de estabilidad aprobada.

No se requieren nuevas rutas, excepciones de página, cron adicionales ni otra
capa de composición.
