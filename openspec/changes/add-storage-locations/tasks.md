# Tasks

## 1. Datos y modelo

- [x] 1.1 Crear la migración de `storage_locations` y la de `shopping_items.storage_location_id` (FK compuesta nullable); verificar `php artisan migrate` y `migrate:rollback` sin errores
- [x] 1.2 Crear el modelo `StorageLocation` (BelongsToUser, parent/children, scope `active`, `path_label`, expansión de selección a hijas) y la relación `storageLocation` en `ShoppingItem` con validación de pertenencia al usuario; verificar con pruebas unitarias/feature de aislamiento y expansión
- [x] 1.3 Implementar archivar y restaurar en cascada (archivar el padre archiva las hijas; restaurar una hija restaura su padre); verificar con pruebas

## 2. Catálogo en ajustes

- [x] 2.1 Agregar la ruta `meals.settings.locations` y la sección `locations` en `MealSettings` (listado con conteo de productos, crear/editar con padre de primer nivel, nombre único normalizado entre hermanas, validación de dos niveles, archivar/restaurar); verificar con `php artisan test --filter=StorageLocation`
- [x] 2.2 Actualizar la vista de ajustes (navegación lateral, FAB, form-dialog, menú del elemento, vista de archivadas) siguiendo `docs/design-system.md`; verificar con `php artisan ui:conformance` y revisión manual

## 3. Asignación y filtro en productos

- [x] 3.1 Agregar el select de ubicación habitual al formulario de producto en `MealIngredients` (activas más la actual si está archivada), con guardado y validación; verificar con pruebas de asignar, quitar y rechazar una ubicación ajena o archivada
- [x] 3.2 Agregar `locationFilter` con la opción "Sin ubicación", chips removibles y limpieza en el reset de filtros; verificar con pruebas de filtro por padre (incluye hijas), por hija y sin ubicación

## 4. Integración

- [x] 4.1 Ejecutar `npm run build`, `php artisan ui:conformance` y las pruebas de Meal afectadas (`--filter=Meal`); verificar que pasan sin regresiones en categorías
