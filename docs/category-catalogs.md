# Categorías por módulo

Los catálogos son personales e independientes. Admiten dos niveles: raíz (`parent_id = null`) e hija. El padre debe pertenecer al mismo usuario y catálogo, y no puede tener padre. Una categoría con hijas no puede convertirse en hija ni eliminarse. Reorganizar las hijas no cambia la asignación de los registros.

Tareas, planes, ingredientes y gastos de vehículos conservan sus tablas y referencias. Bebidas, ejercicios, ánimo, hábitos a evitar, contactos, eventos de relaciones y mantenimiento usan `user_categories`, separado mediante `domain`. Sus registros conservan claves de texto estables; renombrar una categoría cambia su etiqueta, no su clave. Las plantillas globales de mantenimiento conservan su categoría original y cada usuario dispone de su propio catálogo.

La gestión de los catálogos nuevos está en `/categories/{domain}`, enlazada desde las pantallas de cada módulo. Las categorías existentes se gestionan en sus ajustes actuales. Crear y editar usan el diálogo canónico; el campo padre solo ofrece raíces del catálogo. Los selectores con más de diez opciones incorporan búsqueda.

## Filtros y MCP

En tareas, `category` acepta el enlace antiguo con un valor y la selección múltiple `category[0]=...&category[1]=...`. Las categorías se combinan mediante OR. `__none__` incluye registros sin categoría y puede combinarse con otras selecciones; un array vacío muestra todas. Seleccionar un padre incluye sus hijas, sin marcar sus checkboxes. Los demás filtros de categorías también incluyen descendientes.

`list-task-categories-tool` devuelve `id` y `parent_id` además de los campos existentes. `manage-task-category-tool` acepta `parent_id`: omitirlo en una actualización conserva el padre; `null` lo elimina. `manage-category-catalog-tool` ofrece `list`, `create`, `update` y `delete` para los siete dominios nuevos. El servidor valida usuario, dominio, jerarquía y uso antes de eliminar. Las claves existentes siguen siendo válidas en las herramientas de registros.

## Migración y validación

La migración `2026_10_13_000001_add_category_hierarchies` añade padres e inicializa los catálogos con valores predeterminados y valores existentes, todos como raíces. Las claves foráneas de padres comprueban propietario y, en `user_categories`, dominio. Las nuevas cuentas reciben sus catálogos automáticamente.

Al revertir, los valores predeterminados intactos conservan sus claves; las etiquetas personalizadas o renombradas se copian al campo de texto del registro para conservar su lectura en el esquema anterior. Las plantillas globales no se modifican. La estructura jerárquica desaparece al revertir.

Validación focal: `php artisan test --filter=CategoryHierarchyTest`. Las pruebas cubren jerarquía, aislamiento, gestión web, filtros, MCP, migración y reversión en SQLite. El despliegue requiere ejecutar las migraciones antes de servir las nuevas pantallas; las comprobaciones locales no ejecutan migraciones sobre datos personales.

Los mockups contienen catálogos de demostración independientes por dominio y conservan sus cambios durante la sesión del navegador. Sus fuentes están en `docs/mockup-system`; `dist` se genera con el compilador compartido.

## Gestión desde los ajustes del módulo (CHG-148)
Las categorías de un catálogo se gestionan dentro de **Ajustes del módulo**, en una sección «Categorías» con barra lateral, y no con un botón suelto que sale a `/categories/{dominio}`. El CRUD es el trait `App\Livewire\Concerns\ManagesUserCategories` (propiedades con prefijo `category*`, acciones `openCategoryForm`, `saveCategory` y `deleteCategory`) y el parcial `livewire/category/partials/section.blade.php`; la navegación lateral es `x-settings-sections` con las clases `module-settings-*` de `_settings.css`. Bebidas ya lo usa (`/water/settings/categories`). La ruta `categories.settings` se mantiene para el resto de dominios y los enlaces antiguos.
