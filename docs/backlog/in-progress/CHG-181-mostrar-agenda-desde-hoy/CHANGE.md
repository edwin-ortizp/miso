# CHG-181 · Mostrar Agenda desde hoy
**Estado:** En verificación
**Nivel:** S
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-18 · **Aprobado:** 2026-11-18 · **Cerrado:** —
**Relacionado:** —

## 0. En palabras simples
Agenda muestra los eventos de hoy en adelante. Compruébalo al abrir Agenda a mitad de semana.

## 1. Resumen
Limitar el historial en Agenda con el rango nativo de FullCalendar.

## 3. UX/UI
Plantilla: herramienta de trabajo, en el shell de Calendario.

```text
[‹ deshabilitado]  Hoy–domingo  [›]
Hoy
  Próximos eventos
```

## 4. Diseño técnico
Aplicar el límite solo a listWeek y sincronizar el periodo visible y la flecha anterior con el rango activo.

## 5. Pruebas
Navegación y datos de Agenda en móvil y escritorio, compilación y conformidad.

## 7. Tareas
- [x] T1 · Mostrar solo fechas desde hoy en Agenda — Resp: developer — Archivos: resources/js/task-calendar.js, resources/views/livewire/task/task-calendar.blade.php, tests/Browser/task-calendar.interaction.spec.js, tests/Browser/support/tasks-calendar-fixture.php — Hecho cuando: la semana actual empieza en hoy y las otras vistas conservan el historial.

## 8. Bitácora de implementación (developer)

- Se aplicó el rango válido desde medianoche local a la vista Agenda. La cabecera muestra el rango visible y deshabilita Retroceder en la semana actual.
- Se añadieron pruebas con reloj fijo en móvil y escritorio para los eventos de hoy, los planes en curso, la navegación futura, el historial en Semana y el botón Ahora.
- Compilación, 9 escenarios Chromium, 20 pruebas JavaScript y 17 pruebas PHP correctas. La conformidad de UI señala tres desviaciones previas en Juego e Inicio.

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-18 — Aprobado por el usuario (Compuerta 2)
- 2026-11-18 — Movido a in-progress/ (En desarrollo)
- LECCIÓN: ninguna.
- 2026-11-18 — Implementación terminada → En verificación
