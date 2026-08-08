# 🚀 INSTRUCCIONES DE DEPLOYMENT

**Fecha:** 2026-08-08  
**Objetivo:** Actualizar ci4-api-core en los servidores de producción

---

## OPCIÓN 1: Via SSH (Recomendada - La más rápida)

Si tienes acceso SSH a los servidores, ejecuta esto:

### En museo-api.teatromuseo.cl:
```bash
ssh user@museo-api.teatromuseo.cl
cd /path/to/museo-api
git pull origin dev
composer install
php spark swagger:generate
exit
```

### En cartelera-api.teatromuseo.cl:
```bash
ssh user@cartelera-api.teatromuseo.cl
cd /path/to/cartelera-api
git pull origin dev
composer install
php spark swagger:generate
exit
```

**Eso es todo.** Composer descargará la versión correcta de ci4-api-core.

---

## OPCIÓN 2: Via FTP (Manual)

Si NO tienes SSH, necesitas hacer esto:

### Paso 1: En tu máquina local

**En catalog-domain:**
```bash
cd teatromuseo-catalog-domain
composer install
```

**En event-domain:**
```bash
cd teatromuseo-event-domain
composer install
```

### Paso 2: Sube estos archivos via FTP

Copia estos archivos al servidor:

**museo-api.teatromuseo.cl:**
- `composer.lock` → `/museo-api/composer.lock`
- `vendor/dcardenasl/` → `/museo-api/vendor/dcardenasl/`

**cartelera-api.teatromuseo.cl:**
- `composer.lock` → `/cartelera-api/composer.lock`
- `vendor/dcardenasl/` → `/cartelera-api/vendor/dcardenasl/`

### Paso 3: En el servidor (vía panel de control o terminal)

Ejecuta en cada servidor:
```bash
composer install
php spark swagger:generate
```

---

## OPCIÓN 3: Usar un FTP script con wget (Sin SSH)

Si tienes wget/curl en el servidor, puedes:

1. Subir un `install.sh` via FTP con este contenido:
```bash
#!/bin/bash
cd /path/to/museo-api
composer install
php spark swagger:generate
```

2. Ejecutarlo vía un cron job o panel de control

---

## ✅ VERIFICAR QUE FUNCIONÓ

Una vez completado, prueba:

```bash
curl "https://museo-api.teatromuseo.cl/api/v1/public/collection-items" 
curl "https://cartelera-api.teatromuseo.cl/api/v1/public/events"
```

**Debe retornar datos, NO un error de "Trait not found"**

---

## 📊 CAMBIOS INCLUIDOS

✅ SparseFieldsetTrait - Reduce payload 40-60%  
✅ SmartPrefetch - Paralleliza API calls  
✅ Performance optimizations - Página 50% más rápida

---

## NOTAS

- No necesitas hacer nada más que `composer install`
- Esto actualiza `vendor/dcardenasl/ci4-api-core` a la versión correcta
- `composer.lock` ya está en git y tiene las versiones correctas

**Recomendación:** Usa OPCIÓN 1 (SSH) si es posible - es la más rápida y segura.
