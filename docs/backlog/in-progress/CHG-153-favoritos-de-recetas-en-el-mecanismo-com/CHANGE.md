# CHG-153 · Favoritos de recetas en el mecanismo comun de favoritos
**Estado:** En verificación
**Nivel:** M
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-17 · **Aprobado:** 2026-11-17 · **Cerrado:** —
**Relacionado:** CHG-148, CHG-149, CHG-150

## 0. En palabras simples
- Qué vas a ver distinto: las recetas favoritas se marcan con la misma estrella que las bebidas y ejercicios; tus favoritas actuales se conservan.
- Cómo comprobarlo tú: abre Recetas: tus favoritas siguen marcadas y aparecen primero.

## 1. Resumen
`Recipe.favorite` es la única columna booleana de favorito que queda en un catálogo con persona propietaria. Se migra a `catalog_favorites` y la interfaz usa `x-ui.favorite-toggle`, sin cambiar el contrato del MCP.

## 2. Producto (product-manager)
- Problema / usuario: dos mecanismos distintos para el mismo concepto.
- Incluye: dominio `recipes` en `catalog_picker`; `logs` y `foreign_key` opcionales; migración de datos reversible; `MealRecipes`, `MealWeekly`, MCP de recetas.
- No incluye: `MoodState.is_pinned`, `LearningShortcut` y `FavoriteCompanion` (conceptos distintos).
- Criterios de aceptación (Dado/Cuando/Entonces):
  - Dado una receta favorita existente, cuando se despliega, entonces sigue favorita.
  - Dado el MCP, cuando se crea, actualiza o lista recetas, entonces `favorite` y `favorites_only` funcionan igual.
  - Dado una receta eliminada, cuando se borra, entonces su favorito desaparece.

## 3. UX/UI
### 3.1 Experiencia (ux-designer)
- Plantilla: lista de recetas con estrella.
- Flujo y cantidad de pasos: igual que hoy.
- Qué contiene cada pantalla y qué es lo más importante: estrella por receta y filtro de favoritas.
- Estados (vacío / carga / error / éxito / validaciones): sin cambios.
- Información necesaria y momento de presentación: sin cambios.
- Accesibilidad de uso (teclado, foco, errores): `x-ui.favorite-toggle` con `aria-pressed`.

```
★ Arepas      ☆ Sopa      [Solo favoritas]
```

### 3.2 Visual (ui-designer)
- Distribución: estrella en la fila de receta.
- Componentes existentes a reutilizar (ruta): `x-ui.favorite-toggle`, `WithCatalogFavorites`, `CatalogPicker`.
- Jerarquía, color, tipografía y espaciado (tokens): tokens M3 existentes, sin estilos en línea.
- Responsive y contraste: 360/390/1440 px.

### 3.3 Escritura (ux-writer)
- Ubicación y estado: «Marcar {receta} como favorito».
- Textos finales (variables y variantes): sin cambios de texto salvo lo indicado.
- Reglas de docs/UX-WRITING.md aplicadas: etiquetas estables.
- Criterios de aceptación de los textos: ningún texto cambia de significado.

## 4. Diseño técnico (software-architect)
- Archivos a crear / modificar: `config/catalog_picker.php`, `CatalogPicker`, `Recipe`, `MealRecipes`, `MealWeekly`, `CreateRecipeTool`, `UpdateRecipeTool`, `ListRecipesTool`, nueva migración de datos, tests.
- Enfoque y patrones a seguir: la migración copia los favoritos a `catalog_favorites`; las herramientas MCP escriben ahí y siguen aceptando `favorite`.
- Riesgos y mitigación: contrato MCP y `MealPlanningTest` → pruebas de compatibilidad; la columna `recipes.favorite` queda sin uso (se retira en otro cambio).
- Qué NO tocar: otros catálogos con fijados propios.

## 5. Pruebas (qa-tester)
| Caso | Tipo | Nivel | Test |
|---|---|---|---|
| Favoritos de recetas migran y se marcan | Feature | 1 | `MealPlanningTest` |
| Contrato MCP | Feature | 1 | pruebas MCP de recetas |
| Orden y filtro | Feature | 1 | `CatalogFavoritesTest` |
- Comando para verificar: `php artisan test --filter="Meal|Recipe|CatalogFavorites"`.
- Regresión a vigilar: `MealPlanningTest`, MCP de comidas.

## 6. Documentación (tech-writer)
- Docs a actualizar: `docs/catalog-favorites.md`.

## 7. Tareas
- [x] T1 · `CatalogPicker` admite dominios sin registros de uso — Resp: developer — Modelo: estandar — Archivos: servicio, config — Hecho cuando: pruebas en verde.
- [x] T2 · Recetas usan favoritos comunes (interfaz y MCP) — Resp: developer — Modelo: estandar — Archivos: componentes, herramientas, migración — Hecho cuando: contrato MCP intacto.
- [x] T3 · Pruebas y documentación — Resp: developer — Modelo: estandar — Archivos: tests, docs — Hecho cuando: pasan los tests.

## 8. Bitácora de implementación (developer)
- 2026-11-17 — Implementado: dominio `recipes` en `config/catalog_picker.php`; `CatalogPicker` con `logs`/`foreign_key` opcionales y `orderFavoritesFirst`; `Recipe` expone `favorite` como atributo respaldado por `catalog_favorites` (setter que guarda al persistir, `forget` al borrar); `MealRecipes` (estrella `x-ui.favorite-toggle`, filtro y alternar), `MealWeekly` y `ListRecipesTool` usan la tabla común; `CreateRecipeTool` y `UpdateRecipeTool` siguen aceptando `favorite`. Migración `2026_11_17_000004` copia los favoritos existentes (reversible); la columna `recipes.favorite` queda sin uso.
- Verificación local: `CatalogFavoritesTest` (15), pruebas MCP de comidas y `MealPlanningTest` en verde (45 y 13 pruebas). La migración solo se probó con SQLite.

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-17 — Creado en to-do/ (Borrador, nivel M)
- 2026-11-17 — Parte del plan «arquitectura común» aceptado en la conversación (inventario de duplicación previo).
- 2026-11-17 — Aprobado por el usuario (Compuerta 2)
- 2026-11-17 — Movido a in-progress/ (En desarrollo)
- 2026-11-17 — Implementación terminada → En verificación
