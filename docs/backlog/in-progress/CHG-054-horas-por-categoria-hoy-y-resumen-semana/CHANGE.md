# CHG-054 · Horas por categoría hoy y resumen semanal por categoría en tareas
**Estado:** En verificación
**Nivel:** S
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-10 · **Aprobado:** 2026-11-10 · **Cerrado:** —
**Relacionado:** —

## 0. En palabras simples
- Qué vas a ver distinto: en la columna derecha de Tareas, «Completadas hoy» pasa a ser «Hoy por categoría»: cuántas tareas y cuántas horas te toma cada categoría hoy. Debajo aparece «Esta semana», con una barra de colores por categoría y un selector Tareas / Horas.
- Cómo comprobarlo tú: abre Tareas, mira las horas de cada categoría de hoy y cambia entre Tareas y Horas en la tarjeta de la semana.

## 1. Resumen
La barra lateral solo cuenta las completadas de hoy por categoría. Se agregan las horas (intervalo con hora o, si no hay, tiempo estimado, con la regla de `TaskList::focusSuggestionMinutes`) para las tareas de hoy y un resumen semanal (lunes-domingo) con conteo y horas por categoría.

## 3. UX/UI
```
┌ Hoy por categoría ───────────┐   ┌ Esta semana ─────────────────┐
│ 5 h 30 min  en 8 tareas      │   │ 24 tareas · 18 h             │
│ ● Trabajo   2/4   3 h        │   │ [Tareas | Horas]             │
│ ▓▓▓▓▓▓▓▓░░░░                 │   │ ▓▓▓▓▓▓▒▒▒▒░░░ (apilada)      │
│ ● Personal  1/2   1 h 30 min │   │ ● Trabajo   12 · 10 h  ▓▓▓▓  │
│ 2 tareas sin estimar         │   │ ● Personal   8 ·  6 h  ▓▓    │
└──────────────────────────────┘   └──────────────────────────────┘
```
- Color estable por categoría; el valor siempre aparece en texto (no depende solo del color).
- Sin tareas: texto vacío breve.

## 4. Diseño técnico
- `app/Livewire/Task/TaskList.php`: una consulta por periodo (tareas planificadas en el rango o completadas en el rango), agregación en PHP por categoría `{count, done, minutes, unestimated}`, ordenada por minutos. Reemplaza `categoryBreakdown`.
- `resources/views/livewire/task/task-list.blade.php`: dos `x-panel`; el selector Tareas/Horas es Alpine (sin ida al servidor).

## 5. Pruebas
- Prueba Livewire: horas y conteos de hoy y de la semana; no cuentan tareas de otra semana.

## 7. Tareas
- [x] T1 · Horas por categoría de hoy y tarjeta semanal — Resp: developer — Modelo: estandar — Archivos: app/Livewire/Task/TaskList.php, resources/views/livewire/task/task-list.blade.php — Hecho cuando: ambas tarjetas muestran conteos y horas correctos.
- [x] T2 · Prueba de la agregación — Resp: developer — Modelo: estandar — Archivos: tests/Feature/TaskListCategoryStatsTest.php — Hecho cuando: la prueba pasa.

## 8. Bitácora de implementación (developer)
- 2026-11-10 — `TaskList::categoryStats()` agrega por categoría (planificadas o completadas en el rango); minutos con la regla del foco; colores del catálogo. Paneles «Hoy por categoría» y «Esta semana» con selector Alpine. Prueba `TaskListCategoryStatsTest` + suites TaskList en verde (15/15).

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-10 — Creado en to-do/ (Borrador, nivel S)
- 2026-11-10 — Aprobado por el usuario (Compuerta 2)
- 2026-11-10 — Movido a in-progress/ (En desarrollo)
- 2026-11-10 — Implementación terminada → En verificación
