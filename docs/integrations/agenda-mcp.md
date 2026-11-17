# Agenda y reorganización con el MCP

CHG-142, CHG-144 y CHG-145. Permite pedirle a Claude que encuentre huecos y detecte cruces en la agenda.

## Herramientas (módulo Tareas, solo lectura)

- `find-task-slots-tool`: huecos libres para una tarea (`task_id`, id o código) o una duración (`minutes`, 5-480), en un rango de hasta 14 días (`from`, `to`), con `part` (any, morning, afternoon) y `limit`. Respeta el **horario para planificar** de la persona (por defecto lunes a viernes 8:00-18:00 y sábados 9:00-12:00, editable en Tareas → Ajustes) y un margen entre bloques.
- `list-schedule-conflicts-tool`: grupos de bloques con hora que se solapan e incluyen al menos una tarea. Cada bloque trae `source` (task, meal, medication), su hora y `movable`.

## Qué ocupa la agenda

Tareas con hora no completadas, comidas planificadas y tomas de medicamentos (como bloques de 15 minutos; en los cruces aparecen como «Toma de medicamento» sin nombre). Las citas de salud y los planes no tienen hora y no bloquean. Todo en hora de Bogotá (`America/Bogota`).

## Tareas con horario fijo

`schedule_locked` (CHG-144) marca tareas como reuniones. No se mueven con los atajos de la app ni con `update-task-tool`: para cambiar su fecha primero hay que enviar `schedule_locked=false`. Las comidas y las tomas tampoco se mueven (`movable=false`).

## Flujo recomendado

1. `list-schedule-conflicts-tool` para ver qué se cruza.
2. `find-task-slots-tool` con el `task_id` de cada tarea con `movable=true`.
3. Proponer a la persona y, solo con su confirmación, `update-task-tool` con `start_date` y `end_date` (`YYYY-MM-DD HH:MM`).

La lógica vive en `App\Services\Task\TaskSlotFinder` y `PlanningHours`, compartida con la ventana «Buscar un hueco» de la app.
