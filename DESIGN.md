# Design System - ci4-website-builder-web

## Tema: Moderno y Limpio | Azul + Gris

### Paleta de Colores

#### Primarios
- **Primary Blue**: `#0369a1` — Color principal, botones, links, acentos
- **Primary Light**: `#06b6d4` — Hover states, secondary actions
- **Primary Dark**: `#164e63` — Footer, backgrounds oscuros
- **Accent (Sky Blue)**: `#0ea5e9` — Highlights, especiales

#### Neutros
- **Gray (Secondary)**: `#64748b` — Textos secundarios
- **Background**: `#f8fafc` — Fondo principal (gris muy claro)
- **White**: `#ffffff` — Fondos de cards, componentes

#### Textos
- **Primary Text**: `#0f172a` — Títulos, textos principales
- **Secondary Text**: `#475569` — Párrafos, descripciones
- **Muted Text**: `#94a3b8` — Metadata, información secundaria

### Tipografía

- **Font Stack**: `Inter, -apple-system, BlinkMacSystemFont, Segoe UI, sans-serif`
- **Tamaños**: `xs`, `sm`, `base`, `lg`, `xl`, `2xl`, `3xl`, `4xl`
- **Weights**: `regular (400)`, `medium (500)`, `semibold (600)`, `bold (700)`

#### Escalas tipográficas
```
h1: 36px (2.25rem)  — Títulos principales
h2: 30px (1.875rem) — Secciones
h3: 24px (1.5rem)   — Subsecciones
h4: 20px (1.25rem)  — Encabezados
p:  16px (1rem)     — Cuerpo
```

### Componentes Reutilizables

#### Botones
```php
<!-- Primary -->
<button class="btn-primary">Botón Primario</button>

<!-- Secondary -->
<button class="btn-secondary">Botón Secundario</button>

<!-- Outline -->
<button class="btn-outline">Botón Outline</button>

<!-- Ghost (sin fondo) -->
<button class="btn-ghost">Botón Ghost</button>

<!-- Tamaños -->
<button class="btn-primary btn-sm">Pequeño</button>
<button class="btn-primary">Normal</button>
<button class="btn-primary btn-lg">Grande</button>
```

#### Cards
```php
<!-- Card estándar -->
<div class="card">
  <h3>Título</h3>
  <p>Contenido...</p>
</div>

<!-- Card compacta -->
<div class="card-compact">
  <p>Contenido reducido</p>
</div>
```

#### Containers
```php
<!-- Contenedor base (max 1280px) -->
<div class="container-base">
  <!-- Contenido -->
</div>

<!-- Contenedor narrow (max 768px) -->
<div class="container-narrow">
  <!-- Contenido -->
</div>

<!-- Contenedor wide (max 1152px) -->
<div class="container-wide">
  <!-- Contenido -->
</div>
```

#### Badges
```php
<!-- Primary badge -->
<span class="badge">Nuevo</span>

<!-- Secondary badge -->
<span class="badge-secondary">Destacado</span>
```

#### Text Utilities
```php
<!-- Texto mutado -->
<p class="text-muted">Texto secundario...</p>

<!-- Texto subtle -->
<p class="text-subtle">Información adicional...</p>

<!-- Link estándar -->
<a href="#" class="link">Enlace subrayado</a>

<!-- Link sin subrayar -->
<a href="#" class="link-plain">Enlace simple</a>
```

### Espaciado (Vertical Sections)

```php
<!-- Sección normal (48-80px) -->
<section class="section">
  <div class="container-base">
    <!-- Contenido -->
  </div>
</section>

<!-- Sección pequeña (32-40px) -->
<section class="section-sm">
  <!-- Contenido -->
</section>

<!-- Sección grande (64-96px) -->
<section class="section-lg">
  <!-- Contenido -->
</section>
```

### Grillas

#### Para blogs/colecciones
```php
<div class="grid-cols-blog">
  <!-- Automáticamente: 1 col mobile, 2 cols tablet, 3 cols desktop -->
  <!-- gap: 24px -->
  <div class="card">...</div>
  <div class="card">...</div>
  <div class="card">...</div>
</div>
```

### Sombras

- **sm**: Sombra muy sutil `shadow-sm`
- **md**: Sombra normal `shadow-md`
- **lg**: Sombra elevada `shadow-lg`

### Transiciones y Animaciones

```php
<!-- Transition suave -->
<a href="#" class="transition-colors">Link con transición</a>

<!-- Fade in animation -->
<div class="animate-fade-in">Contenido que aparece</div>
```

### Ejemplos de Uso

#### Hero Section
```php
<section class="section-lg bg-gradient-to-r from-primary to-primary-light text-white">
  <div class="container-base text-center">
    <h1 class="mb-4">Título Principal</h1>
    <p class="text-lg mb-8">Descripción...</p>
    <button class="btn-primary">Acción</button>
  </div>
</section>
```

#### Blog Card
```php
<article class="card">
  <img src="..." alt="..." class="w-full h-48 object-cover rounded-lg mb-4">
  <div class="mb-3">
    <span class="badge">Categoría</span>
  </div>
  <h3 class="mb-2">Título del artículo</h3>
  <p class="text-muted text-sm mb-4">12 de junio, 2024</p>
  <a href="#" class="link">Leer más</a>
</article>
```

#### Contact Form
```php
<form class="container-narrow">
  <div class="form-group">
    <label class="form-label">Nombre</label>
    <input type="text" class="form-input" placeholder="Tu nombre">
  </div>
  
  <div class="form-group">
    <label class="form-label">Mensaje</label>
    <textarea class="form-input" rows="4" placeholder="Tu mensaje..."></textarea>
  </div>
  
  <button type="submit" class="btn-primary">Enviar</button>
</form>
```

### Responsive Design

El tema incluye breakpoints estándar de Tailwind:
- `sm`: 640px
- `md`: 768px
- `lg`: 1024px
- `xl`: 1280px
- `2xl`: 1536px

```php
<!-- Ejemplo responsive -->
<div class="text-base md:text-lg lg:text-xl">
  Texto que crece en pantallas más grandes
</div>

<div class="grid-cols-1 md:grid-cols-2 lg:grid-cols-3">
  <!-- 1 columna mobile, 2 tablet, 3 desktop -->
</div>
```

### Compilar CSS

Después de hacer cambios en `tailwind.config.js` o `app.css`:

```bash
# Desarrollo (con watch)
npm run dev:css

# Producción (minificado)
npm run build:css
```

### Personalizar el Tema

Para cambiar colores, edita `tailwind.config.js` en la sección `colors`:

```javascript
colors: {
  primary: '#TU_COLOR_AQUI',
  // ...
}
```

Para agregar nuevos componentes, edita `public/assets/css/app.css`:

```css
@layer components {
  .my-component {
    @apply flex items-center justify-between px-4 py-2 bg-white rounded-lg;
  }
}
```

### Accesibilidad

- Todos los botones tienen `focus:ring-2 focus:ring-offset-2` para teclado
- Colores cumplen WCAG AA (ratio de contraste ≥4.5:1)
- Textos mantienen `line-height` óptimo (1.5-1.75) para legibilidad
- Formas tienen labels asociados correctamente

### Dark Mode (Futuro)

El tema está preparado para dark mode. Para activarlo:

```javascript
// tailwind.config.js
export default {
  darkMode: 'class', // o 'media'
  // ...
}
```

Luego usar: `dark:bg-gray-900 dark:text-white`
