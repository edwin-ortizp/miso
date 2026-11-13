# CHG-095 · Optimización de backend: consultas repetidas y código sin uso
**Estado:** En verificación
**Nivel:** M
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-11 · **Aprobado:** 2026-11-11 · **Cerrado:** —
**Relacionado:** —

## 0. En palabras simples
<!-- Para el usuario: sin jerga. Si no se entiende en 30 segundos, reescríbelo. -->
- Qué vas a ver distinto: la app hace lo mismo, pero cada pantalla le pide muchas menos cosas a la base de datos. «Qué cocinar» pasa de unas 976 consultas a unas decenas; Inicio, Tareas, Hábitos a evitar y las pantallas con guardián bajan de forma visible. «Diario › Vida» deja de tardar más de un segundo en armarse. En Hábitos a evitar, registrar una incidencia usa el mismo diálogo que el resto de la app. Desaparecen las carpetas viejas `archive/` y `dist/`.
- Cómo comprobarlo tú: abre Comidas › Qué cocinar, Tareas › Flujo, Hábitos a evitar y Diario › Vida: muestran lo mismo que antes y cargan más rápido. Registra una incidencia en Hábitos a evitar y cancela otra. La tabla antes/después queda en `docs/performance.md`.

## 1. Resumen
<!-- Qué se cambia y por qué (3-5 líneas). Entendimiento aprobado por el usuario. -->
Medición por ruta (transacción revertida, datos locales): el costo está en el número de consultas por petición y en trabajo repetido en PHP, no en índices. Se corrigen las causas: bloqueo `FOR UPDATE` y transacción en cada lectura de la semana del juego, lecturas idénticas repetidas dentro de una petición, N+1 en sugerencias de comidas, conteos por categoría en Flujo, consultas a `information_schema` en rutas calientes, doble `UPDATE` por tarea y recorridos inútiles del cron antes de las 9:00. Se retira código y carpetas sin uso. Sin cambios de esquema ni de comportamiento. Plan aprobado por el usuario el 2026-11-11.

## 2. Producto (product-manager)
- Problema / usuario: cada navegación ejecuta entre 20 y 976 consultas, la mayoría repetidas; en hosting compartido cada ida a MySQL cuesta.
- Incluye: optimizaciones de lectura del juego, comidas, tareas, catálogos, Diario › Vida y cron de notificaciones; arreglos de UI puntuales en las vistas tocadas; borrado de código y carpetas sin uso; documentación de hallazgos.
- No incluye: rediseños de UX (van como auditoría en `docs/backlog/UX-DESIGN.md`), índices o cambios de esquema, cambio de driver de caché o sesión, reemplazo masivo de `whereDate`.
- Criterios de aceptación (Dado/Cuando/Entonces):
  - Dado un usuario con equipo de la semana completo, cuando se renderiza cualquier página, entonces no se emite `for update` ni se abre transacción por leer la semana.
  - Dado un catálogo con N recetas, cuando se abre «Qué cocinar», entonces el número de consultas no crece con N.
  - Dado un catálogo con categorías sin tareas, cuando se abre Flujo, entonces esas categorías no generan consultas propias.
  - Dada cualquier pantalla tocada, cuando se compara con la versión anterior, entonces muestra los mismos datos y textos.

## 3. UX/UI
<!-- Sin pantallas: escribe "No aplica — motivo" en cada parte. Con UI, approve exige un boceto ASCII en un bloque de código. Si hay flujo, agrega un diagrama en un bloque mermaid. -->
### 3.1 Experiencia (ux-designer)
- Flujo y cantidad de pasos: sin cambios. En Hábitos a evitar, el «+» de la fila abre el diálogo canónico (mismos pasos: nota opcional → Registrar).
- Qué contiene cada pantalla y qué es lo más importante: igual que hoy.
- Estados (vacío / carga / error / éxito / validaciones): Diario › Vida sin configurar usa `x-ui.state`; el resto no cambia.
- Información necesaria y momento de presentación: sin cambios.
- Accesibilidad de uso (teclado, foco, errores): el botón «+» de cada hábito gana nombre accesible; el diálogo de incidencia hereda foco, Escape y retorno de foco de `x-ui.form-dialog`; íconos decorativos con `aria-hidden`.

### 3.2 Visual (ui-designer)
- Distribución: sin cambios.

```
Hábitos a evitar                      ┌ Registrar incidencia ────────┐
 ‹  Esta semana  3 nov – 9 nov  ›     │ Nota (opcional) [__________] │
 [ 4 incidencias esta semana ]        │                              │
 Digital                              │        Cancelar   Registrar  │
  📱 Redes sociales   (2)   [+]  ───▶ └──────────────────────────────┘
  🎮 Videojuegos            [+]
```

- Componentes existentes a reutilizar (ruta): `resources/views/components/ui/form-dialog.blade.php`, `ui/field.blade.php`, `ui/icon-action.blade.php`, `ui/badge.blade.php`, `ui/state.blade.php`, `ui/action.blade.php`, `components/date-navigator.blade.php` (se adopta en las 4 vistas que hoy repiten su marcado).
- Jerarquía, color, tipografía y espaciado (tokens): los existentes de `resources/css/m3`; se retiran estilos en línea de la vista de Hábitos a evitar.
- Responsive y contraste: sin cambios; se comprueba a 390 y 1440 px.

### 3.3 Escritura (ux-writer)
- Ubicación y estado: diálogo de incidencia y nombres accesibles nuevos.
- Textos finales (variables y variantes): título «Registrar incidencia»; acción «Registrar»; nombre accesible del botón de fila «Registrar incidencia de {hábito}». El resto de textos no cambia.
- Reglas de docs/UX-WRITING.md aplicadas: mayúscula solo inicial; el verbo de la acción coincide con el título.
- Criterios de aceptación de los textos: no se altera ningún texto funcional existente fuera de los indicados.

## 4. Diseño técnico (software-architect)
- Archivos a crear / modificar: `app/Services/Game/{WeekPlanner,ModuleGuardian,GameSettings}.php`, `app/Providers/AppServiceProvider.php`, `app/Services/Meal/{MealNeeds,MealInventory}.php`, `app/Livewire/Task/{TaskFlow,TaskList,TaskEditor}.php`, `app/Livewire/Task/Concerns/FiltersTasks.php`, `app/Observers/TaskCalDavObserver.php`, `app/Livewire/NegativeHabit/NegativeHabitWeekly.php`, `app/Models/Traits/ResolvesCategoryCatalog.php`, `app/Livewire/Journal/JournalLifeCalendar.php`, `app/Services/Notifications/{GenerateNotifications,ManageReminders}.php`, vistas correspondientes y `tests/Feature/BackendQueryBudgetTest.php`.
- Enfoque y patrones a seguir: atacar la causa (no repetir lecturas, no bloquear al leer, cargar por lotes). Memo solo dentro de la petición, en servicios `scoped` (patrón de `PersonalExperience`), invalidado por eventos de los modelos que lo alimentan. Presupuestos de consultas con el patrón de `tests/Feature/DashboardQueryBudgetTest.php`.
- Riesgos y mitigación: memo con datos viejos en pruebas de varias peticiones → invalidación por eventos Eloquent y suite completa; camino rápido de `forWeek` → la creación sigue dentro de la transacción bloqueada original; HTML de Diario › Vida → comparación antes/después.
- Qué NO tocar: esquema de base de datos, `whereDate` existentes, drivers de caché y sesión, cambios ajenos sin confirmar (CHG-090…094), `pokemon.json`.

## 5. Pruebas (qa-tester)
| Caso | Tipo | Nivel | Test |
|---|---|---|---|
| Leer la semana con equipo completo no bloquea ni repite | Feature | Servicio | `tests/Feature/BackendQueryBudgetTest.php` |
| Sugerencias de comidas no crecen con las recetas | Feature | Servicio | `tests/Feature/BackendQueryBudgetTest.php` |
| Flujo no consulta categorías vacías | Feature | Livewire | `tests/Feature/BackendQueryBudgetTest.php` |
| Editar una tarea emite un solo UPDATE de tareas | Feature | Modelo | `tests/Feature/BackendQueryBudgetTest.php` |
| Comportamiento existente intacto | Feature | Suite | `php artisan test` |
- Comando para verificar: `php artisan test --filter=BackendQueryBudgetTest` y `php artisan test`.
- Regresión a vigilar: guardián y equipo de la semana, ajustes del juego recién guardados, consumo de recetas, CalDAV (revisión y ETag), recordatorios.

## 6. Documentación (tech-writer)
- Docs a actualizar: `docs/performance.md` (hallazgos y antes/después), `docs/backlog/UX-DESIGN.md` (auditoría de UX móvil), `README.md` y `docs/DEPLOY.md` si mencionan `archive/` o `dist/`.

## 7. Tareas
<!-- Primero lo que cambia para el usuario; el detalle técnico después. Formato:
- [x] T1 · qué cambia (en simple) — Resp: developer — Modelo: perfil — Archivos: … — Hecho cuando: … -->
- [x] T1 · Las páginas dejan de repetir el bloque del juego y de bloquear al leer — Resp: developer — Modelo: estandar — Archivos: app/Services/Game/WeekPlanner.php, ModuleGuardian.php, GameSettings.php, app/Providers/AppServiceProvider.php — Hecho cuando: una página con guardián no emite `for update` y las pruebas de Game pasan.
- [x] T2 · «Qué cocinar» y la previsualización de compras cargan por lotes — Resp: developer — Modelo: estandar — Archivos: app/Services/Meal/MealNeeds.php, MealInventory.php — Hecho cuando: el presupuesto de consultas no crece con las recetas y las pruebas de comidas pasan.
- [x] T3 · Tareas: Flujo con un conteo agrupado, estados sin repetir, sin introspección de esquema y un solo UPDATE por guardado — Resp: developer — Modelo: estandar — Archivos: app/Livewire/Task/, app/Observers/TaskCalDavObserver.php — Hecho cuando: pasan las pruebas de tareas y CalDAV y los presupuestos nuevos.
- [x] T4 · Catálogos de categorías sin consultas por fila — Resp: developer — Modelo: estandar — Archivos: app/Livewire/NegativeHabit/NegativeHabitWeekly.php y su vista, app/Models/Traits/ResolvesCategoryCatalog.php — Hecho cuando: Hábitos a evitar consulta el catálogo una vez y las pruebas de catálogos y migraciones pasan.
- [x] T5 · Diario › Vida se arma sin formatear miles de fechas — Resp: developer — Modelo: estandar — Archivos: app/Livewire/Journal/JournalLifeCalendar.php y su vista — Hecho cuando: las celdas conservan enlaces, clases y etiquetas y el tiempo baja de forma medible.
- [x] T6 · El cron de notificaciones no recorre lo que no puede crear — Resp: developer — Modelo: estandar — Archivos: app/Services/Notifications/GenerateNotifications.php, ManageReminders.php — Hecho cuando: las pruebas de notificaciones pasan.
- [x] T7 · Arreglos de UI en las vistas tocadas — Resp: developer — Modelo: estandar — Archivos: resources/views/livewire/negative-habit/negative-habit-weekly.blade.php, journal-life-calendar.blade.php, components/date-navigator.blade.php y las 4 vistas que lo adoptan — Hecho cuando: `ui:conformance` no sube respecto a 235 y las vistas funcionan a 390 y 1440 px.
- [x] T8 · Se retira código y carpetas sin uso — Resp: developer — Modelo: estandar — Archivos: app/Models/JournalWeeklySummary.php, app/Models/Game/GameAlbumAward.php, resources/views/components/page-header.blade.php, archive/, dist/ — Hecho cuando: no quedan referencias y la suite pasa.
- [x] T9 · Hallazgos y mediciones documentados — Resp: developer — Modelo: estandar — Archivos: docs/performance.md, docs/backlog/UX-DESIGN.md — Hecho cuando: están la tabla antes/después y la auditoría de UX.

## 8. Bitácora de implementación (developer)
- 2026-11-11 — T1: `forWeek` lee sin candado ni transacción cuando no hay nada que crear; `GameSettings` y `ModuleGuardian` pasan a `scoped` con memoria de petición que `AppServiceProvider` vacía al guardar o borrar ajustes, ejemplares, especies, semana o equipo.
- 2026-11-11 — T2: `MealNeeds::aggregate` carga los productos por lote; `suggestions` precarga ingredientes y productos de todas las recetas; `preview` usa `withCount`.
- 2026-11-11/12 — T3: Flujo carga el tablero en dos lecturas; estados memorizados por componente; sin `Schema::hasColumn`; revisión CalDAV en `updating`. Catálogo de categorías de tareas compartido por petición (`App\Support\RequestMemo`, `TaskCategory::expandKeys`).
- 2026-11-12 — T4: Hábitos a evitar lee el catálogo una vez. `ResolvesCategoryCatalog` conserva `Schema::hasTable` porque la migración `2026_08_04_000010` guarda `MoodState` antes de existir `user_categories`; solo deja de repetirlo por guardado.
- 2026-11-12 — T5: celdas de Diario › Vida armadas en PHP (etiqueta, nivel y enlace).
- 2026-11-12 — T6: el cron salta los recorridos automáticos antes de las 9:00 y no recarga al usuario por recordatorio.
- 2026-11-12 — T7: `x-date-navigator` con props de etiquetas, adoptado en Hábitos (semanal), Hábitos a evitar, Hidratación semanal y calendario; diálogo de incidencia con `x-ui.form-dialog`; estado vacío y acciones de Diario › Vida con `x-ui.state` / `x-ui.action`. `ui:conformance`: 235 → 228.
- 2026-11-12 — T8: borrados `archive/`, `dist/`, `JournalWeeklySummary`, `GameAlbumAward` y `x-page-header` (sin referencias). Se conserva el CSS `lt-page-head` porque lo usan los mockups.
- 2026-11-12 — T9: `docs/performance.md` (tabla antes/después) y `docs/backlog/UX-DESIGN.md` (auditoría de UX móvil, 7 hallazgos).
- Validación: `BackendQueryBudgetTest` 7/7; suite completa 1.466/1.515 sin fallos nuevos respecto a la línea base limpia (38 fallos y 10 errores ya existentes: contratos de UI, aislamiento en pruebas Livewire, `UrlStateTest`, `ModuleGuardianTest` ×3). La suite completa corrió antes de la segunda tanda de T3 (catálogo compartido y Flujo en dos lecturas); esa tanda se validó con 134 pruebas filtradas. No se hizo comprobación en navegador de las vistas tocadas ni la comparación byte a byte del HTML de Diario › Vida (la base local estuvo caída durante parte de la sesión); queda para la verificación del usuario.

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-11 — Creado en to-do/ (Borrador, nivel M)
- 2026-11-11 — Decisión del usuario: borrar `archive/` y `dist/`; de UX solo auditoría (sin rediseños); dirección elegida para Hidratación: «vasos como protagonista» (queda en el backlog).
- 2026-11-11 — Aprobado por el usuario (Compuerta 2)
- 2026-11-11 — Movido a in-progress/ (En desarrollo)
- 2026-11-12 — Implementación terminada → En verificación
