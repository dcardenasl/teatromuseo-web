# Composición pública de bloques

Este contexto describe cómo el sitio público compone páginas CMS que combinan contenido editorial con datos de catálogo y eventos.

## Lenguaje

**Bloque dinámico**:
Un bloque editorial cuyo contenido visible depende de datos remotos de una colección CMS, del catálogo o de eventos.
_Evitar_: bloque con API, bloque inteligente

**Instancia de bloque**:
Una aparición concreta de un bloque dentro del árbol de una página, con su propia configuración, filtros y estado de consulta.
_Evitar_: tipo de bloque cuando se habla de una aparición concreta

**Datos precargados**:
Datos remotos resueltos para cada instancia de bloque antes de iniciar el renderizado de la página.
_Evitar_: datos lazy, consulta del ViewModel

## Relaciones

- Una **página pública** contiene una o más **instancias de bloque**.
- Una **instancia de bloque** puede ser estática o dinámica.
- Un **bloque dinámico** recibe sus **datos precargados** antes de renderizarse.
- El renderizado de una instancia dinámica no inicia llamadas remotas adicionales.

## Decisión vigente

La página pública usa un único mecanismo de precarga para todas las instancias dinámicas. La precarga se organiza por instancia, no sólo por tipo de recurso, para conservar la configuración, paginación, filtros y facetas propias de cada bloque.

En un **collection listing**, la precarga representa una instantánea de la petición actual: página, límite, búsqueda, filtros, orden y facetas habilitadas forman parte de la respuesta de esa instancia.

Si una fuente no responde, la instancia conserva datos stale cuando la política de caché los permite; en otro caso entrega un estado vacío o de preview. El render no reintenta la fuente de forma lazy.

La precarga debe aprovechar concurrencia real entre CMS, catálogo y eventos cuando existan solicitudes independientes. La eficiencia se mide por el tiempo del lote completo, no por declarar paralelismo dentro de cada dominio mientras los dominios se esperan en serie.

Las solicitudes idénticas pueden compartir una respuesta; una diferencia en filtros, orden, página o límite define una instancia distinta. Los bloques de detalle pueden compartir una proyección unificada de campos y después recibir el resultado común según su necesidad.

Cada instancia dinámica recibe un resultado explícito, también cuando la fuente falla. La ausencia de una entrada no representa un fallo remoto: representa un error del planificador o un bloque que no declara dependencia.

Las reglas de consulta se definen una sola vez en el planificador de requerimientos; los ViewModels consumen el resultado y no construyen llamadas remotas.

La precarga de bloques y la precarga global del layout son responsabilidades distintas: ambas deben concluir antes del render, pero sólo la primera se organiza por instancia de bloque.

La concurrencia entre fuentes con distintas bases URL debe resolverse en un transporte común, conservando por solicitud la política de caché, stale, timeout y telemetría.

El ownership es explícito: `cms_collection`, `catalog_items` y `event_items` apuntan a un único dominio. `auto` sólo resuelve aliases conocidos; las claves no reconocidas mantienen compatibilidad CMS y generan diagnóstico.

Un listado CMS incluye también la metadata de su colección; esa metadata puede compartirse entre instancias del mismo locale y se resuelve antes del render junto con entradas, paginación y facetas.

El modo **preview** propaga sus credenciales de forma opaca al planificador, participa en la identidad de la petición y no reutiliza caché pública. Un fallo de preview degrada a fixture local o estado vacío, nunca a una llamada durante el render.

## Ejemplo de diálogo

> **Dev:** “Hay dos bloques `collection_listing` en la misma página. ¿Podemos compartir la respuesta?”
> **Experto de contenido:** “Sólo si sus filtros y estado de consulta son iguales; cada instancia representa un listado independiente y debe recibir sus propios datos precargados.”

## Ambigüedades resueltas

- “SmartPrefetch” y “BlockPrefetch” nombraban dos mecanismos de implementación para la misma necesidad. El término canónico del contexto es **datos precargados** y existe un único mecanismo de precarga.
