# CHG-179 · Mejorar la lectura de Agenda móvil
**Estado:** Cerrado
**Nivel:** S
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-18 · **Aprobado:** 2026-11-18 · **Cerrado:** 2026-11-18
**Relacionado:** —

## 0. En palabras simples
- En móvil, el calendario abre en Agenda y cada evento tiene espacio para leer su título y horario.
- Abre Calendario en un teléfono y prueba un título largo y el menú de una tarea.

## 1. Resumen
Adaptar solo el cuerpo móvil de la Agenda semanal de FullCalendar. La cabecera conserva su diseño y comportamiento.

## 3. UX/UI
Plantilla: herramienta de trabajo, en el shell existente de Calendario.

```text
Lunes · 16 nov
  13:30–14:20                 ✓  [⋮]
  Título que puede continuar
  en varias líneas
```

## 4. Diseño técnico
Usar el breakpoint actual y el plugin listWeek. Conservar la vista manual en memoria durante la navegación interna y reutilizar las acciones, fechas, estados y fuentes de eventos existentes.

## 5. Pruebas
Compilación, interacción del calendario, conformidad de UI y revisión del diff.

## 7. Tareas
- [x] T1 · Dar espacio a títulos y horarios en la Agenda móvil — Resp: developer — Archivos: resources/js/task-calendar.js, resources/css/m3/modules/_tasks.css, resources/views/livewire/task/task-calendar.blade.php, tests/Browser/task-calendar.interaction.spec.js, tests/Browser/tasks-calendar.interaction.spec.js, tests/Browser/support/tasks-calendar-fixture.php — Hecho cuando: Agenda abre en móvil y las filas son legibles sin cambiar la cabecera ni perder acciones.

## 8. Bitácora de implementación (developer)

- Agenda conserva la agrupación semanal de FullCalendar 6.1.22. En móvil, el horario y el título tienen líneas propias. Los estados, colores y acciones usan los datos existentes.
- La selección manual se conserva en memoria por cuenta durante la navegación interna. La cabecera conserva posición, textos y comportamiento.
- Pasaron siete escenarios de Chromium en dos ejecuciones dirigidas. Cubren Agenda a 320 y 390 px, navegación y menús a 390, 768 y 1440 px, y filtros y reservas a 390 y 1440 px. Pasaron la compilación, 20 casos JavaScript, 17 casos PHP y `git diff --check`. La conformidad de UI vuelve a señalar tres desviaciones existentes en Juego e Inicio.

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-18 — Creado en to-do/ (Borrador, nivel S)
- 2026-11-18 — Aprobado por el usuario (Compuerta 2)
- 2026-11-18 — Movido a in-progress/ (En desarrollo)
- LECCIÓN: ninguna.
- 2026-11-18 — Implementación terminada → En verificación
- 2026-11-18 — Cerrado por el usuario (Compuerta 3) → done/
