# CHG-157 · Recordar filtros de Tareas y agregar Mañana
**Estado:** En verificación
**Nivel:** S
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-17 · **Aprobado:** 2026-11-17 · **Cerrado:** —
**Relacionado:** —

## 0. En palabras simples
<!-- Para el usuario: sin jerga. Si no se entiende en 30 segundos, reescríbelo. -->
- Qué vas a ver distinto: cada vista de Tareas recuerda sus filtros durante la sesión y ofrece Mañana.
- Cómo comprobarlo tú: elige Mañana en Lista, visita Calendario y vuelve. Debe seguir aplicado.

## 1. Resumen
<!-- Qué y por qué, 2-3 líneas. Agrega solo la sección del rol afectado (## 3. UX/UI, ## 4. Diseño técnico o ## 5. Pruebas) si hace falta. Si hay UI, §3 lleva un boceto ASCII de la zona que cambia. -->

Los filtros explícitos de la URL tienen prioridad al abrir un enlace. La sesión se guarda por usuario y vista al cambiar los filtros. Limpiar restablece los filtros y conserva la búsqueda. Hoy y Mañana se calculan al consultar.

## 3. UX/UI

Plantilla: lista de gestión y tableros existentes. Se reutiliza el selector Cuándo.

```text
Cuándo
  □ Hoy
  □ Mañana
  □ Vencidas
  □ Próximas
  □ Sin fecha
```

## 7. Tareas

- [x] T1 · Recordar filtros por vista y ofrecer Mañana — Resp: developer — Archivos: app/Livewire/Task/Concerns/FiltersTasks.php, config/modules.php, tests/Feature/TaskListFiltersTest.php — Hecho cuando: navegación, URL, limpieza y fechas relativas pasan las pruebas.

<!-- Primero lo que cambia para el usuario; el detalle técnico después. Formato:
- [x] T1 · qué cambia (en simple) — Resp: developer — Modelo: perfil — Archivos: … — Hecho cuando: … -->

## 8. Bitácora de implementación (developer)

- 2026-11-17 — T1 implementada. Pasaron 15 pruebas focalizadas con 123 aserciones. La batería ampliada reprodujo un fallo preexistente en la vista previa de descripciones. La conformidad detectó deuda en las plantillas existentes. Chromium verificó el selector Mañana y su persistencia al pasar por Kanban a 1440 y 390 px.

## 9. Preguntas abiertas, decisiones e historial
- LECCIÓN: ninguna.
- 2026-11-17 — Creado en to-do/ (Borrador, nivel S)
- 2026-11-17 — Aprobado por el usuario (Compuerta 2)
- 2026-11-17 — Movido a in-progress/ (En desarrollo)
- 2026-11-17 — Implementación terminada → En verificación
