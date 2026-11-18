# CHG-158 · Editar fechas desde la lista de tareas
**Estado:** En verificación
**Nivel:** S
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-17 · **Aprobado:** 2026-11-17 · **Cerrado:** —
**Relacionado:** —

## 0. En palabras simples
<!-- Para el usuario: sin jerga. Si no se entiende en 30 segundos, reescríbelo. -->
- Qué vas a ver distinto: la fecha abre un diálogo con Inicio y Fin.
- Cómo comprobarlo tú: toca «Sin fecha», elige un día y guarda.

## 1. Resumen
<!-- Qué y por qué, 2-3 líneas. Agrega solo la sección del rol afectado (## 3. UX/UI, ## 4. Diseño técnico o ## 5. Pruebas) si hace falta. Si hay UI, §3 lleva un boceto ASCII de la zona que cambia. -->

La Lista permite cambiar las fechas sin abrir todos los campos de la tarea.

## 3. UX/UI

### 3.1 Experiencia
Plantilla: lista de gestión de Tareas existente.

```text
[Fecha o Sin fecha] → Cambiar fechas
                      Inicio  [fecha]
                      Fin     [fecha]
                      [Cancelar] [Guardar]
```

## 4. Diseño técnico
Guardar solo fechas y sus indicadores con bloqueo de fila y verificación de cambios concurrentes. Conservar horas, respetar horario fijo y recordatorios.

## 5. Pruebas
Ejecutar pruebas de acciones de Lista y revisar foco y filtros.

## 7. Tareas
- [x] T1 · Cambiar fechas desde la fila — Resp: developer — Archivos: app/Livewire/Task/TaskList.php, resources/views/livewire/task/task-list.blade.php, tests/Feature/TaskListQuickActionsTest.php, tests/Feature/TaskOverdueTest.php — Hecho cuando: el diálogo guarda Inicio y Fin y protege horarios fijos y recordatorios.

<!-- Primero lo que cambia para el usuario; el detalle técnico después. Formato:
- [x] T1 · qué cambia (en simple) — Resp: developer — Modelo: perfil — Archivos: … — Hecho cuando: … -->

## 8. Bitácora de implementación (developer)

- Editor implementado. Pasaron 25 pruebas dirigidas y git diff --check. Conformidad UI conserva hallazgos previos. Pendiente probar foco e interacción en navegador.

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-17 — Creado en to-do/ (Borrador, nivel S)
- 2026-11-17 — Aprobado por el usuario (Compuerta 2)
- 2026-11-17 — Movido a in-progress/ (En desarrollo)
- LECCIÓN: ninguna
- 2026-11-17 — Implementación terminada → En verificación
