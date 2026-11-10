# CHG-051 · Iniciar enfoque desde la lista de tareas
**Estado:** En desarrollo
**Nivel:** S
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-10 · **Aprobado:** 2026-11-10 · **Cerrado:** —
**Relacionado:** CHG-017 (Foco vinculado a tareas desde el editor compartido)

## 0. En palabras simples
- Qué vas a ver distinto: en el menú ▾ de cada tarea pendiente aparecen «Iniciar enfoque» y «Registrar tiempo trabajado». Iniciar enfoque abre el mismo modal de foco del panel de pared y arranca el temporizador con la tarea cargada.
- Cómo comprobarlo tú: abre Tareas, despliega el ▾ de una tarea pendiente, elige «Iniciar enfoque» y verifica que el modal muestra la tarea y el tiempo corriendo. Con «Registrar tiempo trabajado» se abre el formulario manual con esa tarea vinculada.

## 1. Resumen
Hoy el foco con tarea se alcanza desde el editor de tareas o el panel de pared. La lista de tareas ya tiene el Split Button por fila, pero no ofrece foco. Se reutiliza el host global de foco (`home.panel.focus-timer` en la barra de estado) y sus eventos `task-focus-timer` / `task-focus-manual`; no se crea otro temporizador ni se modifica el motor Pomodoro.

## 2. Producto (product-manager)
- Incluye dos acciones de foco por fila en tareas pendientes: iniciar el ciclo con la tarea y registrar tiempo ya trabajado.
- Iniciar enfoque arranca de inmediato, igual que «Iniciar foco» del panel de pared. Registrar tiempo abre el formulario manual con fecha, inicio, fin y notas.
- La duración sugerida replica la regla del editor: diferencia válida entre inicio y fin (1–1440 min); en su defecto, tiempo estimado; si no hay, la duración por defecto del temporizador.
- Excluye completar la tarea al registrar foco, crear otro motor, cambiar metas o pausas y reasignar un ciclo activo.
- Si hay un ciclo activo de otra tarea, se muestra su contexto con el aviso existente, sin iniciar otro ni cambiar la asociación.

## 3. UX/UI (ux-designer)
Menú ▾ de una fila pendiente:

```
Completar
Mover para hoy
Mover para mañana
────────────────
Iniciar enfoque
Registrar tiempo trabajado
────────────────
Editar
────────────────
Eliminar
```

- Las dos acciones de foco solo aparecen en tareas pendientes y cuando existe la columna `pomodoro_sessions.task_id`.
- Se reutiliza el diálogo fullscreen «Foco de hoy» del panel y el formulario manual de foco con tarea visible.

## 4. Diseño técnico (software-architect)
- `resources/views/livewire/task/task-list.blade.php`: agregar los dos `x-ui.menu-item` dentro del bloque de tareas no completadas, disparando `task-focus-timer` con `{ taskId, durationMinutes, start: true }` y `task-focus-manual` con `{ taskId, durationMinutes }`.
- `app/Livewire/Task/TaskList.php`: helper de sugerencia de minutos y paso de `focusAvailable` a la vista; guard con `Schema::hasColumn('pomodoro_sessions', 'task_id')`, igual que `TaskEditor`.
- Reutilizar el host existente en `status-bar/status-bar.blade.php` (`:task-host="true"`), que escucha ambos eventos y muestra el modal del panel de pared. No duplicar temporizadores.
- Los IDs se interpolan con `Js::from` para evitar inyección en el atributo Alpine.

## 5. Pruebas (qa-tester)
- Extender `tests/Feature/TaskListQuickActionsTest.php`: la lista pendiente muestra ambas acciones; la de completadas no; el HTML incluye los eventos de foco con el ID de la tarea.
- Comprobación manual/browser en 390/768/1440: abrir menú, abrir modal con tarea, iniciar, registrar tiempo, caso de ciclo activo, teclado y foco.
- `php artisan test --filter=TaskListQuickActionsTest`, `php artisan ui:conformance` y `git diff --check`. Sin cambios de assets.

## 6. Documentación (tech-writer)
- Actualizar `docs/integrations/task-focus.md` con el nuevo punto de entrada desde la lista.

## 7. Tareas
- [x] T1 · Agregar «Iniciar enfoque» y «Registrar tiempo trabajado» al menú de la fila — Resp: developer — Modelo: estandar — Archivos: resources/views/livewire/task/task-list.blade.php, app/Livewire/Task/TaskList.php — Hecho cuando: la fila pendiente dispara ambos eventos con la tarea y la duración sugerida, y la fila completada no los muestra.
- [ ] T2 · Verificar acciones, modal y ciclo activo — Resp: qa-tester — Modelo: estandar — Archivos: tests/Feature/TaskListQuickActionsTest.php, tests/Browser/layout.interaction.spec.js — Hecho cuando: pruebas y comprobación responsive pasan sin doble ciclo ni reasignación.
- [x] T3 · Documentar el acceso desde la lista — Resp: tech-writer — Modelo: estandar — Archivos: docs/integrations/task-focus.md — Hecho cuando: la guía menciona las dos acciones y su comportamiento con ciclo activo.

## 8. Bitácora de implementación (developer)
- 2026-11-10 — T1: menú de fila con «Iniciar enfoque» (`start: true`) y «Registrar tiempo trabajado», visibles solo en tareas pendientes y con la columna de vínculo disponible. Duración sugerida por diferencia inicio/fin (1–1440 min) o estimación; el cálculo vive en `TaskList::focusSuggestionMinutes`. T3: `docs/integrations/task-focus.md` actualizado.
- 2026-11-10 — Validación: `TaskListQuickActionsTest` 6/6 (76 aserciones) y `php artisan ui:conformance` sin deuda nueva. La suite de navegador `layout.interaction.spec.js` no arranca en este entorno: el inicio de sesión del fixture agota el tiempo y falla antes de llegar a la vista (problema preexistente del arnés, no de este cambio). Queda T2 para revisión visual/responsive.

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-10 — Creado en to-do/ (Borrador, nivel S).
- 2026-11-10 — Usuario decidió: iniciar arranca de inmediato y se incluye también el registro manual de tiempo.
- 2026-11-10 — Aprobado por el usuario (Compuerta 2)
- 2026-11-10 — Movido a in-progress/ (En desarrollo)
