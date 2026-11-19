# CHG-170 · Reorganizar información en las tarjetas de tareas
**Estado:** Cerrado
**Nivel:** S
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-18 · **Aprobado:** 2026-11-18 · **Cerrado:** 2026-11-18
**Relacionado:** —

## 0. En palabras simples
En la Lista de tareas, el código y el título se leen juntos, con el resumen de la descripción debajo. Se agrupan fecha, horario, duración y recurrencia. Los tags y el estado quedan abajo, separados por una línea suave. Kanban, Planificación y Flujo aplican la misma jerarquía a los campos de sus tarjetas.

## 1. Resumen
Ajuste visual del módulo Tareas en tamaños móvil y escritorio. Se conservan colores, acciones y datos de cada vista. Calendario y Gantt conservan sus formatos de agenda y cronograma.

## 3. UX/UI
Plantilla: lista de gestión y tarjetas compactas del módulo.
```text
Escritorio
│ MSO-001  Preparar informe        Hoy · 9:00 · 45 min    ⋯ │
│ Revisar cifras y enviar resumen                          │
│ ───────────────────────────────────────────────────────── │
│ Trabajo · Importante · 2/3 subtareas            En curso │

Móvil
│ MSO-001  Preparar informe              ⋯ │
│ Revisar cifras y enviar resumen        │
│ Hoy · 9:00 · 45 min                     │
│ ─────────────────────────────────────── │
│ Trabajo · Importante · 2/3 subtareas    │
│ En curso                                │
```
Conservar los chips y el token de borde. La fila inferior permite salto de línea y se omite si no lleva contenido. La descripción se muestra como vista previa en las tarjetas de tareas. Los controles de cada vista conservan sus acciones.

## 4. Diseño técnico
Actualizar las vistas Blade de Lista, Planificación, Kanban y Flujo. Compartir el tratamiento del borde desde el CSS actual del módulo. Usar el ancho disponible para colocar el grupo de planificación a la derecha en Lista. Preservar eventos y acciones Livewire.

## 5. Pruebas
Comprobar en navegador información, borde de ancho completo, flujo de elementos en móvil y escritorio, y apertura de la tarea, acciones y fechas rápidas. Correr pruebas de las vistas Tareas, compilar assets y ejecutar conformidad UI.

## 7. Tareas
- [x] T1 · Ordenar las tarjetas y conservar sus acciones — Resp: developer — Archivos: resources/views/livewire/task/task-list.blade.php, resources/views/livewire/task/task-planning.blade.php, resources/views/livewire/task/task-kanban.blade.php, resources/views/livewire/task/task-flow.blade.php, resources/css/m3/modules/_tasks.css, public/css/app.css generado, tests/Browser/task-editor.interaction.spec.js, tests/Browser/support/layout-fixture.php — Hecho cuando: las comprobaciones de §5 pasan.

## 8. Bitácora de implementación (developer)

- 2026-11-18 — Lista, Kanban, Planificación y Flujo tienen una jerarquía común. Se conservaron los eventos y acciones y se regeneró la hoja CSS pública.
- 2026-11-18 — Pasaron 73 pruebas SQLite y 34 MariaDB. La ejecución de referencia ya fallaba en la vista previa Markdown de TaskViewsTest, excluida del filtro.
- 2026-11-18 — Pasaron cinco pruebas Playwright, con geometría del separador, apertura, fechas rápidas y retorno de foco en Lista a 390 y 1440 px. Se revisaron ambas capturas. Pasaron el build y git diff --check. Conformidad UI conserva tres hallazgos previos en vistas ajenas y la Lista sale del reporte.
- 2026-11-18 — El CLI local no ofrece context ni activity. Se consultaron los lineamientos disponibles y se registró aquí la evidencia.

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-18 — Creado en to-do/ (Borrador, nivel S)
- 2026-11-18 — Aprobado por el usuario (Compuerta 2)
- 2026-11-18 — Movido a in-progress/ (En desarrollo)
- LECCIÓN: ninguna.
- 2026-11-18 — Implementación terminada → En verificación
- 2026-11-18 — Cerrado por el usuario (Compuerta 3) → done/
