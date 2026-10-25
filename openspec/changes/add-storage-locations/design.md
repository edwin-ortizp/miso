# Design

## Context

El stock vive en `shopping_items` (un registro por producto, con variantes aparte). Ya existe un catálogo jerárquico análogo: `MealCategory` con `parent_id` y el trait `HasCategoryHierarchy` (`expandSelection`, `path_label`), administrado en `Meal/MealSettings` con secciones por ruta (`meals.settings`, `meals.settings.categories`) y filtrado en `Meal/MealIngredients` mediante chips.

## Goals / Non-Goals

**Goals:**
- Reutilizar los patrones de categorías (jerarquía, sección de ajustes, filtro por chips) para que Ubicaciones sea consistente.
- Modelo de datos que soporte el conteo por ubicación en la fase siguiente sin migraciones adicionales.

**Non-Goals:**
- Stock por ubicación (un mismo producto en varios lugares).
- Más de dos niveles en la UI.

## Decisions

- **Tabla `storage_locations`**: `id` UUID (como `meal_categories`), `user_id`, `parent_id` nullable (FK compuesta `(parent_id, user_id)` a sí misma), `name`, `normalized_name`, `archived_at` nullable, timestamps. Se ordena por nombre, como categorías; icono y orden manual quedan fuera. Índice `(user_id, parent_id, normalized_name)`. La unicidad entre hermanas activas se valida en la aplicación porque MySQL no tiene índices parciales con `archived_at`. Usa `BelongsToUser`. Las relaciones `parent`/`children` no filtran por usuario (la FK compuesta lo garantiza) para que el eager loading funcione.
  - Alternativa: reutilizar `meal_categories` con un `domain`. Se descarta: las categorías clasifican qué es un producto, mientras las ubicaciones dicen dónde está, y mezclarlas complica ambos filtros.
- **Jerarquía**: modelo propio `StorageLocation` (no `HasCategoryHierarchy`, acoplado a categorías y con borrado). Valida dos niveles, nombre normalizado y unicidad entre hermanas activas en el evento `saving`, así aplica también fuera de HTTP.
- **`shopping_items.storage_location_id`**: FK compuesta nullable `(storage_location_id, user_id)` con `restrictOnDelete` (las ubicaciones no se borran). El modelo valida que la ubicación pertenezca al mismo usuario, igual que hoy con `category_id`.
- **Archivar**: se usa `archived_at` en lugar de `SoftDeletes`, para que la relación siga resolviendo el nombre en productos ya asignados. Archivar un padre archiva sus hijas; restaurar una hija restaura su padre si estaba archivado.
- **UI de ajustes**: nueva sección `locations` en `MealSettings` con ruta `meals.settings.locations` y entrada en la navegación lateral (`bi-geo-alt`). Muestra las ubicaciones archivadas con un filtro o separador y acciones Archivar/Restaurar en el menú del elemento. La creación usa el FAB canónico y `x-ui.form-dialog`.
- **Formulario de producto**: un select de ubicación agrupado por padre (opciones `Padre › Hija`), que admite elegir un padre y ofrece solo ubicaciones activas, más la actual del producto si está archivada.
- **Filtro**: `locationFilter` (array) en `MealIngredients`, con el valor especial `none` para productos sin ubicación. Se combina con `whereIn` expandido y `orWhereNull` cuando incluye `none`, y se muestra como chip removible igual que la categoría.

## Risks / Trade-offs

- [Lógica de árbol duplicada con categorías] → aceptado: es pequeña y las reglas difieren (archivar vs. borrar).
- [Productos existentes sin ubicación] → el filtro "Sin ubicación" facilita asignarlas poco a poco.

## Migration Plan

Las migraciones solo agregan tabla y columna nullable. Para revertir basta con `migrate:rollback`, que elimina la columna y la tabla sin afectar al stock.
