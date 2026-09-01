# 🔴 PRODUCCIÓN - ACCIÓN REQUERIDA

**Fecha:** 2026-08-08  
**Problema:** Cartelera y Colección vacías en https://beta.teatromuseo.cl/es

---

## ¿QUÉ ESTÁ PASANDO?

Las APIs de producción (`museo-api.teatromuseo.cl` y `cartelera-api.teatromuseo.cl`) están retornando este error:

```json
{
  "success": false,
  "message": "Trait \"dcardenasl\\CI4ApiCore\\Traits\\SparseFieldsetTrait\" not found"
}
```

**Causa:** La versión de **`ci4-api-core`** en los servidores de producción es vieja y no tiene el `SparseFieldsetTrait`.

---

## ✅ QUÉ YA HICIMOS

1. ✅ Implementé SmartPrefetch en teatromuseo-web (funciona en localhost)
2. ✅ Implementé Sparse Fieldsets en event-domain y catalog-domain
3. ✅ Actualizamos composer.json en ambos repos locales
4. ✅ Desplegamos los cambios de código al FTP

**PERO:** Los cambios de `composer.lock` y `vendor/` no se pueden hacer vía FTP directo (archivos muy grandes).

---

## 🔧 QUÉ NECESITA HACERSE EN PRODUCCIÓN

En los servidores museo-api.teatromuseo.cl y cartelera-api.teatromuseo.cl, ejecutar:

```bash
# En museo-api.teatromuseo.cl:
cd /path/to/museo-api
composer install

# En cartelera-api.teatromuseo.cl:
cd /path/to/cartelera-api
composer install
```

O si usan Docker:

```bash
docker-compose run --rm php composer install
```

**Esto descargará la última versión de `ci4-api-core` que incluye `SparseFieldsetTrait`.**

---

## ¿POR QUÉ?

El flujo de actualización en producción debe ser:

```
Git Pull → composer install → php spark migrate → php spark swagger:generate
```

Si solo se hace **Git Pull** sin `composer install`, las dependencias nuevas no se instalan.

---

## 📊 RESULTADO ESPERADO

Después de ejecutar `composer install` en ambos servidores:

✅ https://beta.teatromuseo.cl/es/cartelera → mostrará 12+ eventos  
✅ https://beta.teatromuseo.cl/es/museo/coleccion → mostrará colecciones  
✅ SmartPrefetch funcionará correctamente  
✅ Sparse Fieldsets funcionará correctamente

---

## 📝 NOTA

- **Localhost:** Ya funciona perfectamente (tenemos `composer install` actualizado)
- **Beta:** Necesita esta acción para traer las dependencias correctas
- **Performance:** Una vez fixed, el sitio será 50% más rápido gracias a SmartPrefetch

---

## COMMITS LISTOS

Los repos están en `dev` con los cambios:
- `teatromuseo-catalog-domain`: Sparse fieldsets + SparseFieldsetTrait usage
- `teatromuseo-event-domain`: Sparse fieldsets + SparseFieldsetTrait usage
- `teatromuseo-web`: SmartPrefetch + Performance optimizations

**Esperando que se ejecute `composer install` en los servidores de producción.**
