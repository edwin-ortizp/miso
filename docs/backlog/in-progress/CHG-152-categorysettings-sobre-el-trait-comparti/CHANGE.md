# CHG-152 · CategorySettings sobre el trait compartido de categorias
**Estado:** En verificación
**Nivel:** M
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-17 · **Aprobado:** 2026-11-17 · **Cerrado:** —
**Relacionado:** CHG-148, CHG-149, CHG-150

## 0. En palabras simples
- Qué vas a ver distinto: nada visible: la página antigua de categorías usa el mismo código que las secciones de Ajustes de cada módulo.
- Cómo comprobarlo tú: abre `/categories/drinks`: sigue funcionando igual.

## 1. Resumen
`Category\CategorySettings` duplica casi exactamente el trait `ManagesUserCategories` y su vista duplica el parcial de categorías. Se reconstruye sobre ambos y se conserva la ruta por compatibilidad.

## 2. Producto (product-manager)
- Problema / usuario: lógica duplicada en dos sitios que pueden divergir.
- Incluye: `CategorySettings` con el trait y el parcial; ruta `categories.settings` intacta; reescribir los tests que usan los nombres antiguos.
- No incluye: categorías con tablas propias (`TaskCategory`, `MealCategory`, `PlanCategory`), `ManageCategoryCatalogTool`.
- Criterios de aceptación (Dado/Cuando/Entonces):
  - Dado cualquier dominio de `CategoryCatalog::DOMAINS`, cuando se abre `/categories/{dominio}`, entonces responde 200 y gestiona categorías.
  - Dado un duplicado o una categoría en uso, cuando se guarda o elimina, entonces muestra el mismo mensaje que hoy.

## 3. UX/UI
### 3.1 Experiencia (ux-designer)
- Plantilla: ajustes de un solo catálogo (sin barra lateral).
- Flujo y cantidad de pasos: igual que hoy.
- Qué contiene cada pantalla y qué es lo más importante: card de categorías.
- Estados (vacío / carga / error / éxito / validaciones): sin cambios.
- Información necesaria y momento de presentación: sin cambios.
- Accesibilidad de uso (teclado, foco, errores): igual que el parcial compartido.

```
Categorías · Bebidas
┌ tabla de categorías ──┐
└───────────────────────┘
```

### 3.2 Visual (ui-designer)
- Distribución: el parcial compartido dentro de `x-module-shell`.
- Componentes existentes a reutilizar (ruta): `ManagesUserCategories`, `livewire/category/partials/section`.
- Jerarquía, color, tipografía y espaciado (tokens): tokens M3 existentes, sin estilos en línea.
- Responsive y contraste: 360/390/1440 px.

### 3.3 Escritura (ux-writer)
- Ubicación y estado: títulos «Categorías · {catálogo}».
- Textos finales (variables y variantes): sin cambios de texto salvo lo indicado.
- Reglas de docs/UX-WRITING.md aplicadas: etiquetas estables.
- Criterios de aceptación de los textos: ningún texto cambia de significado.

## 4. Diseño técnico (software-architect)
- Archivos a crear / modificar: `app/Livewire/Category/CategorySettings.php`, `resources/views/livewire/category/category-settings.blade.php`, `tests/Feature/CategoryHierarchyTest.php`, `LearningModuleTest`, `LearningSettingsSectionsTest`.
- Enfoque y patrones a seguir: usar el trait (`useCategoryDomain`) y `categoryViewData()`; mantener `mount(string $domain)`.
- Riesgos y mitigación: nombres de acciones cambian (`openCategoryForm`, `saveCategory`, `deleteCategory`) → ajustar `CategoryHierarchyTest`.
- Qué NO tocar: MCP, modelos, `CategoryCatalog`.

## 5. Pruebas (qa-tester)
| Caso | Tipo | Nivel | Test |
|---|---|---|---|
| Gestión por dominio con la ruta antigua | Feature | 1 | `CategoryHierarchyTest` |
| Aprendizaje y módulos | Feature | 1 | `LearningModuleTest`, `ModuleCategorySettingsTest` |
- Comando para verificar: `php artisan test --filter="Category|Learning"`.
- Regresión a vigilar: `ModuleCategorySettingsTest`.

## 6. Documentación (tech-writer)
- Docs a actualizar: `docs/category-catalogs.md`.

## 7. Tareas
- [x] T1 · `CategorySettings` sobre trait y parcial — Resp: developer — Modelo: estandar — Archivos: componente y vista — Hecho cuando: sin lógica duplicada.
- [x] T2 · Ajustar pruebas y documentación — Resp: developer — Modelo: estandar — Archivos: tests, docs — Hecho cuando: pasan los tests.

## 8. Bitácora de implementación (developer)
- 2026-11-17 — Implementado: `Category\CategorySettings` usa el trait `ManagesUserCategories` y el parcial de categorías (su vista propia quedó en un envoltorio de shell, FAB y navegación entre catálogos del módulo); la ruta `categories.settings` se conserva. `CategoryHierarchyTest` se reescribió con `openCategoryForm`, `saveCategory`, `deleteCategory`, `categoryName` y `categoryParentId`.
- Verificación local: `CategoryHierarchyTest`, `LearningModuleTest`, `LearningSettingsSectionsTest` y `ModuleCategorySettingsTest` (32 pruebas) en verde.

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-17 — Creado en to-do/ (Borrador, nivel M)
- 2026-11-17 — Parte del plan «arquitectura común» aceptado en la conversación (inventario de duplicación previo).
- 2026-11-17 — Aprobado por el usuario (Compuerta 2)
- 2026-11-17 — Movido a in-progress/ (En desarrollo)
- 2026-11-17 — Implementación terminada → En verificación
