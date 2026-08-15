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

Se ejecutaron únicamente pruebas focales, sin tráfico hacia beta ni cambios en
hosting:

- Web: `23/23` tests, `86` assertions, resultado `OK`.
- BFF: `28/28` tests, `135` assertions, resultado `OK`; PHPUnit reportó una
  deprecación conocida, sin fallos.

Cobertura focal:

- resolución de páginas, detalles, listados y preview en Web;
- `BlockTreeResolver`, aliases, redirects, entradas, relacionados, fallback y
  preview en BFF;
- validación de fechas, rangos y fieldsets del PublicRead.

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
3. Si la ventana es limpia, ejecutar solo canarios HTTP individuales de las
   rutas de la matriz, sin pruebas de concurrencia.
4. Cerrar REL-01 y registrar REL-02 como rollout validado.
5. Dejar `CLEAN-01` para después de una ventana de estabilidad aprobada.

No se requieren nuevas rutas, excepciones de página, cron adicionales ni otra
capa de composición.
