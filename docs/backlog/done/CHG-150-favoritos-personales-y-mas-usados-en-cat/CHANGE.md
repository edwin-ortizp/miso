# CHG-150 · Favoritos personales y más usados en catálogos de bebidas y ejercicios
**Estado:** Cerrado
**Nivel:** M
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-17 · **Aprobado:** 2026-11-17 · **Cerrado:** 2026-11-17
**Relacionado:** CHG-148, CHG-149

## 0. En palabras simples
- Qué vas a ver distinto: en Ajustes de Hidratación y de Ejercicio cada bebida o ejercicio tiene una estrella para marcarlo como favorito, y un bloque «Sugeridos por tu uso» con lo que más registras, que puedes agregar a favoritos con un toque. Al registrar (formulario, widget y chat), tus favoritos salen primero; el resto sigue disponible buscando o desplegando la lista.
- Cómo comprobarlo tú: marca Agua y Café como favoritos; abre el chat de Hidratación y verifica que esas dos son las primeras; busca otra bebida y confirma que sigue ahí.

## 1. Resumen
Los catálogos de bebidas y ejercicios son grandes y cada usuario usa pocos elementos. Se añade un mecanismo genérico de favoritos personales (tabla propia por usuario y catálogo, sin tocar los catálogos) y un cálculo de «más usados» a partir de los registros. Favoritos (manual) y más usados (automático) son conceptos distintos: los más usados solo se sugieren, nunca se vuelven favoritos solos. Los selectores ponen primero los favoritos y conservan el catálogo completo.

## 2. Producto (product-manager)
- Problema / usuario: elegir entre decenas de bebidas o ejercicios estorba, sobre todo desde el chat.
- Incluye: favoritos por usuario para bebidas y ejercicios (marcar y desmarcar); sugerencias de más usados al agregar favoritos; prioridad de favoritos en formularios, agregar rápido y chat (Ejercicio gana chips rápidos en el chat); bebida por defecto de «+1 vaso» preferente entre favoritos; herramienta MCP para listar y cambiar favoritos; diseño reutilizable para otros catálogos.
- No incluye: migrar `Recipe.favorite`, `MoodState.is_pinned` ni los accesos directos de Formación; favoritos compartidos entre usuarios; cambiar los catálogos.
- Criterios de aceptación (Dado/Cuando/Entonces):
  - Dado un usuario, cuando marca o desmarca una bebida o ejercicio, entonces se guarda solo para él y el catálogo y otros usuarios no cambian.
  - Dado favoritos marcados, cuando abre un selector o el chat, entonces los favoritos aparecen primero y el resto sigue disponible por búsqueda o lista.
  - Dado un elemento muy registrado que no es favorito, cuando se muestra el bloque de sugeridos, entonces aparece como sugerencia y no como favorito hasta que el usuario lo agrega.
  - Dado un usuario sin favoritos, cuando registra, entonces todo se comporta como hoy.

## 3. UX/UI
### 3.1 Experiencia (ux-designer)
- Plantilla: tabla/lista de gestión existente de Ajustes con una acción por fila; selectores existentes con grupo «Favoritos».
- Flujo y cantidad de pasos: marcar = un toque en la estrella; agregar sugerido = un toque.
- Qué contiene cada pantalla y qué es lo más importante: estrella por fila, filtro «Solo favoritos», orden «Favoritos primero», bloque de sugeridos.
- Estados (vacío / carga / error / éxito / validaciones): sin sugerencias, el bloque no se muestra; sin favoritos, el orden es el actual.
- Información necesaria y momento de presentación: sugeridos solo si hay registros suficientes.
- Accesibilidad de uso (teclado, foco, errores): botón con `aria-pressed`, nombre accesible «Marcar X como favorita», objetivo de 44 px.

```
┌ 🥤 Tipos de bebida ─────────────────────┐
│ Sugeridos por tu uso:  [☆ Té] [☆ Jugo] │
│ ★ 💧 Agua      ×1,00   120 regs   ⋮     │
│ ★ ☕ Café      ×0,80    80 regs   ⋮     │
│ ☆ 🍵 Té        ×0,90    40 regs   ⋮     │
└─────────────────────────────────────────┘
Selector:  Favoritas: Agua · Café  │ Todas ▾ (buscar)
```

### 3.2 Visual (ui-designer)
- Distribución: estrella a la izquierda del nombre; sugeridos como chips sobre la tabla.
- Componentes existentes a reutilizar (ruta): `x-ui.icon-action`, `x-ui.chip`, `combobox`, `x-ui.management-card`; nuevo `x-ui.favorite-toggle` en `/ui-catalog`.
- Jerarquía, color, tipografía y espaciado (tokens): tokens M3; estrella rellena del color de acento.
- Responsive y contraste: 360/390/1440.

### 3.3 Escritura (ux-writer)
- Ubicación y estado: ajustes, selectores y chat.
- Textos finales (variables y variantes): «Favoritos», «Solo favoritos», «Sugeridos por tu uso», «Agregar a favoritos», «Quitar de favoritos»; «Marcar {nombre} como favorita/favorito».
- Reglas de docs/UX-WRITING.md aplicadas: términos estables; sin culpar ni presionar.
- Criterios de aceptación de los textos: se distingue «favoritos» (los eliges) de «más usados» (el sistema sugiere).

## 4. Diseño técnico (software-architect)
- Archivos a crear / modificar: migración `catalog_favorites` e índices de uso en `drink_logs` y `exercise_logs`; modelo `CatalogFavorite`; `config/catalog_picker.php`; `app/Services/Catalog/CatalogPicker.php`; trait `WithCatalogFavorites`; componente `x-ui.favorite-toggle` y su entrada de catálogo; `WaterSettings`, `ExerciseSettings`, `WaterDaily`, `ExerciseDaily`, `Dashboard`, `ResolvesDrinkType`, `ResolvesExerciseType`, nueva `ManageCatalogFavoritesTool`, `ToolCatalog`, `LifeTrackerServer`, `config/companion_chat.php`.
- Enfoque y patrones a seguir: tabla como `learning_shortcuts` (uuid, `user_id`, `catalog_domain`, `item_id`, `position`, único por usuario/dominio/ítem); servicio por dominio configurable; huérfanos ignorados al leer y limpieza al borrar el ítem; más usados calculado al vuelo (últimos 60 días).
- Riesgos y mitigación: presupuesto de consultas (`BackendQueryBudgetTest`) → memoizar por petición; cambios de orden en varios puntos → un único método `ordered()`.
- Qué NO tocar: tablas `drink_types` y `exercise_types`, `Recipe.favorite`, `MoodState.is_pinned`, `LearningShortcut`.

## 5. Pruebas (qa-tester)
| Caso | Tipo | Nivel | Test |
|---|---|---|---|
| Favoritos personales, únicos y de un solo usuario | Feature | 1 | `CatalogFavoritesTest` |
| Orden favoritos primero, catálogo completo disponible | Feature | 1 | `CatalogFavoritesTest` |
| Más usados cuenta registros y no crea favoritos | Feature | 1 | `CatalogFavoritesTest` |
| Ajustes de agua y ejercicio (estrella, filtro, sugeridos) | Feature | 1 | `CatalogFavoritesTest` |
| Chat y agregar rápido con favoritos | Feature | 1 | `CatalogFavoritesTest` |
| Herramienta MCP | Feature | 1 | `LifeTrackerCatalogFavoritesMcpTest` |
| Esquema multiusuario y conformidad | Feature | 2 | `MultitenancySchemaTest`, `UiConformanceTest` |
- Comando para verificar: `php artisan test --filter="Favorites|Water|Exercise"`, `php artisan ui:conformance`, `npm run build`.
- Regresión a vigilar: `WaterDailyProgressTest`, `ExerciseDailyProgressTest`, `BackendQueryBudgetTest`, `HomeDashboardTest`.

## 6. Documentación (tech-writer)
- Docs a actualizar: `docs/category-catalogs.md` (o `docs/catalog-favorites.md`), `docs/companion-chat.md`, `docs/integrations/agenda-mcp.md` si aplica.

## 7. Tareas
- [x] T1 · Base de favoritos y más usados (tabla, servicio, configuración) — Resp: developer — Modelo: estandar — Archivos: migración, modelo, `CatalogPicker` — Hecho cuando: pruebas de aislamiento, orden y sugerencias en verde.
- [x] T2 · Estrella, filtro y sugeridos en Ajustes de Hidratación y Ejercicio — Resp: developer — Modelo: estandar — Archivos: componentes y vistas de ajustes, `x-ui.favorite-toggle` — Hecho cuando: cumple el boceto.
- [x] T3 · Prioridad de favoritos en selectores, agregar rápido y chat — Resp: developer — Modelo: estandar — Archivos: `WaterDaily`, `ExerciseDaily`, `Dashboard`, chat — Hecho cuando: favoritos primero y catálogo completo disponible.
- [x] T4 · MCP, pruebas y documentación — Resp: developer — Modelo: estandar — Archivos: tools, tests, docs — Hecho cuando: pasan los tests.

## 8. Bitácora de implementación (developer)
- 2026-11-17 — Implementado: tabla `catalog_favorites` (por usuario, única por dominio e ítem) e índices de uso en `drink_logs` y `exercise_logs`; `CatalogPicker` (favoritos, `ordered`, `quick`, `mostUsed` de los últimos 60 días con mínimo 2, `suggestions` sin favoritos, `defaultWater`) configurado por dominio en `config/catalog_picker.php`; trait `WithCatalogFavorites` y componente `x-ui.favorite-toggle` en `/ui-catalog`. Ajustes de Hidratación (orden «Favoritas primero», filtro «Solo favoritas», sugeridas) y Ejercicio (grupo «Favoritos», filtro, sugeridos); favoritos primero (con ★) en el registro de agua y ejercicio, agregar rápido, dashboard, panel de pared, acciones de hábitos, chat de Hidratación y nuevo chat de Ejercicio (`quickAdd`); «+1 vaso» prefiere agua entre favoritos; MCP: `manage-catalog-favorites-tool` y desempate por favorito en los resolvers. Docs: `docs/catalog-favorites.md` y `docs/companion-chat.md`.
- Verificación local: `CatalogFavoritesTest` (12) y `LifeTrackerCatalogFavoritesMcpTest` (4) en verde; regresión de Agua, Ejercicio, Home, MCP y `BackendQueryBudgetTest` con los mismos fallos preexistentes que la línea base (calendarios por fecha, dashboard, presupuesto de comidas, `catalog-chip`, cascada de cuenta social); `npm run build` correcto; captura de Ajustes de Hidratación revisada. La migración solo se probó con SQLite y no se ejecutó la spec de navegador del chat.

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-17 — Creado en to-do/ (Borrador, nivel M)
- 2026-11-17 — Plan aceptado en la conversación: tabla propia, más usados al vuelo (60 días), sugerencias sin conversión automática.
- 2026-11-17 — Aprobado por el usuario (Compuerta 2)
- 2026-11-17 — Movido a in-progress/ (En desarrollo)
- 2026-11-17 — Implementación terminada → En verificación
- 2026-11-17 — Cerrado por el usuario (Compuerta 3) → done/
- LECCIÓN: ninguna
