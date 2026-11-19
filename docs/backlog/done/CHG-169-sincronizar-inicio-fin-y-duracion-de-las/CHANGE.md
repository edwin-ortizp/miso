# CHG-169 · Sincronizar inicio fin y duración de las tareas
**Estado:** Cerrado
**Nivel:** S
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-18 · **Aprobado:** 2026-11-18 · **Cerrado:** 2026-11-18
**Relacionado:** CHG-118, CHG-158

## 0. En palabras simples
- En Nueva tarea y Editar tarea, cambia el inicio de un bloque de 90 minutos de las 21:00 a las 23:00. El fin pasa a las 00:30 del día siguiente.
- Cambia los minutos para mover el fin, o edita el fin para recalcular los minutos. Guardar avisa si el fin quedó antes del inicio.

## 1. Resumen
Ajustar la programación en el editor de tareas. La fecha de inicio seguía una regla diferente de la hora y los minutos escritos no actualizaban el fin. Conservar los rangos sin horas, las tareas con una sola fecha y los atajos de duración existentes.

## 3. UX/UI
Plantilla: formulario modal canónico existente. Se actualizan los valores tras salir del campo editado. Se usa el mensaje de validación junto a la fecha de fin.

```text
Programación · Fecha inicio [18/11] Hora [23:00] · Fecha fin [19/11] Hora [00:30]
Propiedades · Tiempo estimado (min) [90]
```

## 4. Diseño técnico
Centralizar las reglas en InteractsWithTaskSchedule. Procesar los campos actualizados por Livewire en conjunto para respetar el fin escrito en el mismo envío. Reusar la validación al guardar. Aplicar a Nueva tarea y Editar tarea, incluido el modo Programar del editor compartido. Sin migraciones, cambios de API ni cambios al diálogo rápido de solo fechas de la lista.

## 5. Pruebas
Livewire y navegador para creación, edición, duración explícita o derivada, cruce de medianoche e intervalo rechazado. Regresión de tareas sin horas, horario fijo, atajos y programación. Compilar assets y ejecutar conformidad UI.

## 7. Tareas
- [x] T1 · Mantener sincronizados el inicio, el fin y los minutos — Resp: developer — Archivos: app/Livewire/Concerns/InteractsWithTaskSchedule.php, app/Livewire/Task/TaskList.php, app/Livewire/Task/TaskEditor.php, resources/views/livewire/task/partials/schedule-fields.blade.php, resources/views/livewire/task/partials/edit-task-dialog.blade.php, tests/Feature/TaskStartDateShiftTest.php, tests/Browser/task-editor.interaction.spec.js — Hecho cuando: se verifica §5.

## 8. Bitácora de implementación (developer)

- 2026-11-18 — 78 pruebas SQLite y 44 pruebas MariaDB aprobadas. Dos pruebas adicionales fallaban también con la implementación original, en la vista previa Markdown y la estructura del modal en Flujo.
- 2026-11-18 — 5 pruebas Playwright aprobadas, entre ellas creación y edición a 390 y 1440 px. También pasaron 20 pruebas JS del calendario, npm run build y git diff --check. La conformidad UI reportó cuatro vistas ajenas a los cambios. El CLI local no ofrece context ni activity. Se consultaron los lineamientos disponibles y se registró aquí la evidencia.

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-18 — Creado en to-do/ (Borrador, nivel S)
- 2026-11-18 — Aprobado por el usuario (Compuerta 2)
- 2026-11-18 — Movido a in-progress/ (En desarrollo)
- LECCIÓN(developer): los avisos de borradores que sincronizan campos antes de Guardar deben reflejar los cambios del formulario, ya que wire:dirty indica cambios pendientes de enviar a Livewire. Se verificó el aviso antes y después de guardar en navegador. Destino sugerido: docs/design-system.md.
- 2026-11-18 — Implementación terminada → En verificación
- 2026-11-18 — Cerrado por el usuario (Compuerta 3) → done/
