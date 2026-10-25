# Proposal

## Why

Los productos del inventario no tienen un lugar físico asociado, así que no se puede consultar "qué hay en la nevera" ni preparar una actualización de inventario por lugar. Cada producto vive normalmente en el mismo sitio, por lo que basta con registrar su ubicación habitual.

## What Changes

- Nuevo catálogo administrable **Ubicaciones** en los ajustes de Comida, jerárquico (p. ej. Cocina › Nevera, Cocina › Alacena 1, Patio › Bodega), por usuario.
- Las ubicaciones se archivan en lugar de eliminarse para no dejar productos huérfanos.
- Cada producto (comida, limpieza, mascotas y cualquier otro) puede tener una **ubicación habitual** opcional, editable desde el formulario del producto.
- El catálogo de productos admite **filtrar por ubicación**: seleccionar una ubicación padre incluye a sus hijas, y existe la opción "Sin ubicación".
- Fuera de alcance (fase siguiente): conteo/actualización de inventario por ubicación, herramientas MCP y mostrar la ubicación en compras. Regla ya acordada para el conteo: un producto sin valor no cambia su stock; dejarlo en cero exige una acción explícita, porque el conteo puede ser parcial.

## Capabilities

### New Capabilities
- `storage-locations`: catálogo jerárquico de ubicaciones de almacenamiento por usuario, asignación de una ubicación habitual a cada producto y filtrado del catálogo de productos por ubicación.

### Modified Capabilities

## Impact

- Base de datos: nueva tabla `storage_locations`; columna nullable `storage_location_id` en `shopping_items`.
- Modelos: nuevo `StorageLocation`; relación en `ShoppingItem`.
- Livewire: `Meal/MealSettings` (nueva sección), `Meal/MealIngredients` (campo y filtro) y sus vistas.
- Sin cambios en APIs/MCP en esta entrega.
