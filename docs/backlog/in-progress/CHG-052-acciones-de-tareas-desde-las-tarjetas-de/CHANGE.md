# CHG-052 · Acciones de tareas desde las tarjetas del calendario
**Estado:** En verificación
**Nivel:** S
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-10 · **Aprobado:** 2026-11-10 · **Cerrado:** —
**Relacionado:** —

## 0. En palabras simples
<!-- Para el usuario: sin jerga. Si no se entiende en 30 segundos, reescríbelo. -->
- Qué vas a ver distinto: menú de acciones en cada tarea del calendario.
- Cómo comprobarlo tú: pasar el cursor o enfocar una tarjeta y pulsar los tres puntos.

## 1. Resumen
Añadir ⋮ en tarjetas de tareas de las cuatro vistas, visible con hover/foco y siempre en táctil. Menú único fuera del recorte: avance, completar, hoy, mañana, editar y eliminar con confirmación. Compartir reglas con lista, preservar recurrencia, horarios, contexto y propiedad.

## 3. UX/UI
```text
[hora       ...]
[Avanzar / Completar / Hoy / Manana / Editar / Eliminar]
```
Escape/exterior/cambio de vista cierran; foco vuelve al disparador. Otras capas sin cambios.
<!-- Qué y por qué, 2-3 líneas. Agrega solo la sección del rol afectado (## 3. UX/UI, ## 4. Diseño técnico o ## 5. Pruebas) si hace falta. Si hay UI, §3 lleva un boceto ASCII de la zona que cambia. -->

## 7. Tareas
<!-- Primero lo que cambia para el usuario; el detalle técnico después. Formato:
- [x] T1 · qué cambia (en simple) — Resp: developer — Modelo: perfil — Archivos: … — Hecho cuando: … -->

- [x] T1 · Compartir acciones y conectar menú accesible del calendario — Resp: developer — Modelo: estandar — Hecho cuando: acciones y visibilidad siguen el plan aprobado.
- [x] T2 · Validar PHP, JS, navegador, build y conformidad — Resp: developer — Modelo: estandar — Hecho cuando: pruebas afectadas pasan o bloqueos quedan documentados.

## 8. Bitácora de implementación (developer)
- Implementado menú único teletransportado, trigger por tarea y lógica compartida de acciones. Aislamiento explícito, confirmación, errores reintentables y bloqueo durante petición.
- PHP: 28 pruebas / 218 aserciones de TaskCalendar y TaskList aprobadas; tras devolver al componente un método de foco incorporado concurrentemente, 20 pruebas / 170 aserciones afectadas aprobadas.
- JS: 18 pruebas aprobadas, incluidos error de servidor, bloqueo de doble petición y cancelación de eliminación.
- Browser: 3 pruebas aprobadas a 390/768/1440, recorriendo las cuatro vistas con Enter/flechas/Escape y límites del menú. Corregida propagación de Enter hacia FullCalendar. Inspección visual de captura móvil.
- npm run build aprobado (aviso de tamaño de chunk preexistente). git diff --check en archivos afectados aprobado.
- ui:conformance inicialmente sin deuda nueva. Última ejecución: desviaciones concurrentes en task-list.blade.php (estilos de estadísticas ajenos a este CHG); ninguna del calendario. No se modificaron esos estilos.
- No migraciones, commits, publicación ni cambios de datos personales. Pruebas con BD aislada.


## 9. Preguntas abiertas, decisiones e historial
- 2026-11-10 — Creado en to-do/ (Borrador, nivel S)

- Usuario aprobó el plan completo el 2026-11-10 y solicitó implementarlo.
- 2026-11-10 — Aprobado por el usuario (Compuerta 2)
- 2026-11-10 — Movido a in-progress/ (En desarrollo)
- 2026-11-10 — Implementación terminada → En verificación
