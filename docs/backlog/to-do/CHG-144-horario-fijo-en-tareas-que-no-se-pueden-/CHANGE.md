# CHG-144 · Horario fijo en tareas que no se pueden mover
**Estado:** Aprobado
**Nivel:** M
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-17 · **Aprobado:** 2026-11-17 · **Cerrado:** —
**Relacionado:** CHG-142, CHG-143, CHG-145, CHG-052, CHG-072
**Depende de:** ninguno técnico; se integra con CHG-142 (los fijos cuentan como ocupados y no movibles) y CHG-145 (el MCP no los mueve).

## 0. En palabras simples
- Qué vas a ver distinto: puedes marcar una tarea como **«Horario fijo»** (por ejemplo, una reunión). Esa tarea no se mueve sola ni con los atajos («Mover para hoy/mañana», arrastrar en el calendario), y se ve con un candado.
- Cómo comprobarlo tú: crea una tarea «Reunión con cliente» de 10:00 a 11:00, márcala como horario fijo y comprueba que no se deja arrastrar ni mover para mañana; desmárcala y vuelve a moverse.

## 1. Resumen
Algunas tareas ocupan la agenda en un momento que no se puede cambiar. Hoy todas las tareas con fecha se mueven igual. Se añade un indicador booleano por tarea (`schedule_locked`, nombre a confirmar) que bloquea el cambio de fecha y hora por los caminos de UI y lo expone para que huecos y MCP lo respeten. Requiere una columna nueva en `tasks`.

## 2. Producto (product-manager)
- Problema / usuario: reuniones y compromisos con hora fija se mezclan con tareas flexibles; al reorganizar la agenda no puedo permitir que se muevan.
- Incluye:
  - Interruptor «Horario fijo» en el editor de tarea (sección Programación) y en la creación.
  - Indicador visual (candado) en lista, calendario y tarjetas.
  - Bloqueo de movimiento en: arrastre y redimensionado del calendario (`moveTask`), «Mover para hoy/mañana» (`moveToDay`, `QuickEditor` snooze, `TaskPlanning`), `ShiftTasksToDate` (cierre del día) y la acción «Buscar un hueco» (no se ofrece).
  - Editar la fecha de una tarea fija sigue siendo posible desde el editor, de forma deliberada (y desmarcando o confirmando).
  - Exposición del campo en las lecturas (`ListTasksTool`, `GetTaskTool`) y en la creación/edición MCP (`CreateTaskTool`, `UpdateTaskTool`).
- No incluye: tareas de varios participantes ni asistentes; reglas por categoría («todas las reuniones son fijas»); CalDAV (se conserva el campo pero no se mapea); lógica de reorganización (CHG-145).
- Criterios de aceptación (Dado/Cuando/Entonces):
  - Dada una tarea fija, cuando intento arrastrarla en el calendario, entonces no se mueve y veo un aviso breve.
  - Dada una tarea fija, cuando uso «Mover para mañana» o el cierre del día, entonces se omite y se me informa.
  - Dada una tarea fija, cuando abro su ⋯, entonces no aparece «Buscar un hueco».
  - Dada una tarea fija, cuando la edito en el editor y cambio su fecha, entonces el sistema pide confirmar o desmarcar primero.
  - Dada una tarea existente, cuando se despliega el cambio, entonces ninguna queda fija por defecto.
  - Dado otro usuario, cuando consulta tareas, entonces solo ve las suyas.

## 3. UX/UI
### 3.1 Experiencia (ux-designer)
- Plantilla: sección «Programación» del diálogo de tarea (`edit-task-dialog`); no existe `## Anatomía` para ese diálogo.
- Flujo y cantidad de pasos: editar tarea → Programación → interruptor «Horario fijo» → guardar (1 paso extra, opcional).
- Qué contiene cada pantalla y qué es lo más importante: el interruptor junto a fecha y hora; en lista y calendario, un candado discreto junto al título.
- Estados (vacío / carga / error / éxito / validaciones): un intento de mover una tarea fija muestra aviso «Esta tarea tiene horario fijo. Quita el candado para moverla.» con acción «Quitar candado».
- Información necesaria y momento de presentación: el interruptor se muestra solo si la tarea tiene fecha y hora.
- Accesibilidad de uso: el candado tiene nombre accesible («Horario fijo») y no depende solo del ícono.

```
Programación
 Inicio [mié 19 · 10:00]   Fin [mié 19 · 11:00]
 Duración (15m)(30m)(●1h)(2h)
 [ ⬤ ] Horario fijo   No se mueve con los atajos ni al reorganizar
```

### 3.2 Visual (ui-designer)
- Distribución: interruptor al final de la sección Programación; candado `bi-lock` pequeño tras el título.
- Componentes existentes a reutilizar: `x-ui.switch`, `partials/schedule-fields.blade.php`, `x-ui.snackbar` con acción, íconos Bootstrap Icons existentes.
- Jerarquía, color, tipografía y espaciado (tokens): candado con color secundario; sin estilos en línea.
- Responsive y contraste: 360/390/1440; interruptor de 44 px.

### 3.3 Escritura (ux-writer)
- Ubicación y estado: editor, lista, calendario, avisos.
- Textos finales (variables y variantes): «Horario fijo» · «No se mueve con los atajos ni al reorganizar.» · «Esta tarea tiene horario fijo. Quita el candado para moverla.» · «Quitar candado» · cierre del día: «{n} tareas con horario fijo se quedaron donde estaban.»
- Reglas de docs/UX-WRITING.md aplicadas: tuteo, verbos concretos, sin culpa.
- Criterios de aceptación de los textos: el término «horario fijo» es el mismo en todas las superficies y en el MCP.

## 4. Diseño técnico (software-architect)
- Archivos a crear / modificar:
  - Migración nueva en `tasks` (columna booleana, por defecto falso, con índice si hace falta, según `docs/ARCHITECTURE.md` para MySQL) y `Task.php` (`$fillable`, cast).
  - Bloqueo en `TaskCalendar::moveTask`, `HandlesTaskRowActions::moveToDay`, `QuickEditor` snooze, `TaskPlanning`, `ShiftTasksToDate`; `task-calendar.js` (`editable: false` por evento fijo vía `extendedProps`); `toEvent()`.
  - UI: `partials/schedule-fields.blade.php`, `TaskEditor` (validación y borrador), lista/calendario (candado).
  - MCP: `InteractsWithTaskFields`, `CreateTaskTool`, `UpdateTaskTool`, `GetTaskTool`, `ListTasksTool` (campo y descripción del esquema).
- Enfoque y patrones a seguir: una sola comprobación centralizada (método en `Task` o servicio) usada por todos los caminos; el editor es el único lugar donde se puede cambiar la fecha de una tarea fija.
- Riesgos y mitigación: caminos de escritura repartidos (hay seis) → test que recorre cada uno; CalDAV sobrescribe fechas desde apps externas → decisión abierta; MySQL en producción → probar la migración en MySQL (`docs/TESTING.md`).
- Qué NO tocar: recurrencia, jornada, servicio de huecos (lo consume el CHG-142).

## 5. Pruebas (qa-tester)
| Caso | Tipo | Nivel | Test |
|---|---|---|---|
| Campo se guarda y lee (`$fillable`, valor leído de BD) | Feature | 1 | `TaskFixedScheduleTest` |
| Cada camino de movimiento omite la tarea fija | Feature | 1 | `TaskFixedScheduleTest`, `TaskCalendarActionsTest` |
| Editor exige confirmar para cambiar la fecha | Feature | 2 | `TaskEditorDraftTest` |
| MCP lee y escribe el campo | Feature | 2 | `LifeTrackerTaskMcpTest` |
| Aislamiento entre usuarios | Feature | 2 | `TaskFixedScheduleTest` |
| Candado y arrastre bloqueado | Browser | 3 | `task-calendar.interaction.spec.js` |
- Comando para verificar: `php artisan test --filter=Task`, suite contra MySQL con `timeout`, `php artisan ui:conformance`, `npm run build`, `git diff --check`.
- Regresión a vigilar: `TaskViewsTest`, `TaskStartDateShiftTest`, `TaskCalendarRecurrenceSuggestionTest`.

## 6. Documentación (tech-writer)
- Docs a actualizar: documentación de Tareas (concepto de horario fijo) y `docs/UX-WRITING.md` (término).

## 7. Tareas
- [ ] T1 · Guardar si una tarea tiene horario fijo — Resp: developer — Modelo: estandar — Archivos: migración, `Task.php` — Hecho cuando: el campo existe, es falso por defecto y se prueba en MySQL.
- [ ] T2 · Interruptor «Horario fijo» y candado visible — Resp: developer — Modelo: estandar — Archivos: `schedule-fields.blade.php`, `TaskEditor`, lista y calendario — Hecho cuando: se ve y se guarda según el boceto.
- [ ] T3 · Bloquear el movimiento por todos los caminos — Resp: developer — Modelo: estandar — Archivos: `TaskCalendar`, `HandlesTaskRowActions`, `QuickEditor`, `TaskPlanning`, `ShiftTasksToDate`, `task-calendar.js` — Hecho cuando: ningún camino mueve una tarea fija y se avisa.
- [ ] T4 · Exponer el campo en el MCP de tareas — Resp: developer — Modelo: estandar — Archivos: `app/Mcp/Tools/Task/*` — Hecho cuando: se puede leer y escribir.
- [ ] T5 · Pruebas y documentación — Resp: developer — Modelo: estandar — Archivos: tests, docs — Hecho cuando: pasa la sección 5.

## 8. Bitácora de implementación (developer)

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-17 — Creado a partir de la decisión del usuario: hay tareas con agenda bloqueada (como una reunión) que no se pueden mover libremente.
- 2026-11-17 — Decisión delegada por el usuario («identifica tú la mejor manera»): nombre visible «Horario fijo»; el MCP rechaza cambios de fecha de una tarea fija salvo que antes se envíe `schedule_locked=false`; CalDAV puede moverla (el origen externo manda) y no se bloquea.
- 2026-11-17 — Aprobado por el usuario (Compuerta 2)
