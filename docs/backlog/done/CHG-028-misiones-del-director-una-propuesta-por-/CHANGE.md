# CHG-028 · Misiones del director: una propuesta por semana (idempotencia)
**Estado:** Cerrado
**Nivel:** S
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-09 · **Aprobado:** 2026-11-09 · **Cerrado:** 2026-11-10
**Relacionado:** CHG-026

## 0. En palabras simples
- Qué vas a ver distinto: si tu schedule de Paseo corre dos veces el mismo lunes (o Claude reintenta), tus misiones de la semana ya no cambian. La segunda llamada responde "ya_propuestas" y no toca nada.
- Cómo comprobarlo tú: pide dos veces a Claude, con el conector MiSo, "propón las misiones de esta semana". En Semana siguen las misiones de la primera vez.

## 1. Resumen
`propose-game-week-missions-tool` aceptaba reemplazar las misiones del director mientras no tuvieran avance, así que una segunda corrida del schedule las cambiaba. Ahora se acepta una sola propuesta del director por semana. Las demás herramientas programadas ya eran idempotentes: el contexto solo lee, la temporada responde `not_due`, la semana se arma una sola vez y las recompensas usan claves únicas.

## 2. Producto (product-manager)
- Problema / usuario: una corrida doble del schedule cambia las misiones sin que Alex lo pida.
- Incluye:
  - Si la semana actual ya tiene misiones con `source=director`, la herramienta responde `ya_propuestas` con un mensaje claro y no cambia nada.
  - El contexto expone `current_week.director_proposed`, para que el agente no lo intente.
  - El prompt sugerido en docs/game.md lo indica.
- No incluye: reemplazo explícito (`replace`) ni dejar de registrar los rechazos de temporada en el historial (no afectan el juego).
- Criterios de aceptación:
  - Dada una propuesta aplicada, cuando llega otra la misma semana (igual o distinta), entonces responde `ya_propuestas` y las misiones no cambian.
  - Con misiones automáticas sin avance, la primera propuesta del director se aplica como antes.
  - La semana siguiente acepta una nueva propuesta.
  - El contexto muestra `director_proposed: true` tras aplicar.

## 3. UX/UI
### 3.1 Experiencia (ux-designer)
No aplica: no cambia pantallas; solo la respuesta de una herramienta MCP.

### 3.2 Visual (ui-designer)
No aplica: sin cambios visuales.

### 3.3 Escritura (ux-writer)
- Mensaje de la herramienta: "Ya hay misiones del director para esta semana; se conservan."

## 4. Diseño técnico (software-architect)
- Archivos: `app/Services/Game/SeasonManager.php` (`proposeWeekMissions` y `currentWeekSummary`), `app/Mcp/Tools/Game/ProposeGameWeekMissionsTool.php` (descripción), `tests/Feature/Game/WeekMissionsProposalTest.php` y `docs/game.md`.
- Enfoque: dentro de la transacción con bloqueo del usuario, antes de validar el avance, se revisa si existe alguna misión `SOURCE_DIRECTOR` en la semana. Si existe, se responde `ya_propuestas`. El bloqueo evita la carrera entre dos corridas simultáneas.
- Riesgos: ninguno sobre el progreso, porque solo se dejan de hacer reemplazos.
- Qué NO tocar: la validación ni las recompensas.

## 5. Pruebas (qa-tester)
| Caso | Tipo | Nivel | Test |
|---|---|---|---|
| Segunda propuesta en la misma semana → ya_propuestas, sin cambios | Feature | MCP | WeekMissionsProposalTest |
| Semana siguiente acepta propuesta | Feature | Servicio | WeekMissionsProposalTest |
| Contexto: director_proposed | Feature | MCP | WeekMissionsProposalTest |
- Comando: `php artisan test tests/Feature/Game`

## 6. Documentación (tech-writer)
- `docs/game.md`: regla de una propuesta por semana y respuesta `ya_propuestas`; tabla de idempotencia del director.

## 7. Tareas
- [x] T1 · Una sola propuesta de misiones del director por semana — Resp: developer — Archivos: app/Services/Game/SeasonManager.php, app/Mcp/Tools/Game/ProposeGameWeekMissionsTool.php, tests/Feature/Game/WeekMissionsProposalTest.php, docs/game.md — Hecho cuando: los tests nuevos pasan y el juego sigue en verde
- [x] T2 · Verificar criterios de aceptación — Resp: qa-tester — Hecho cuando: todos pasan

## 8. Bitácora de implementación (developer)
- 2026-11-09 — Inicio con `start --force` (ya había otros CHG En desarrollo).
- 2026-11-09 — T1:
  - `SeasonManager::proposeWeekMissions` revisa `directorProposed()` dentro de la transacción con bloqueo y antes de validar: si la semana ya tiene misiones del director, responde `ya_propuestas`.
  - El contexto agrega `current_week.director_proposed`, y `replaceable` queda en false en ese caso.
  - Se actualizó la descripción de la herramienta.
  - Pruebas nuevas: reintento idéntico y propuesta distinta, sin cambios en ninguno de los dos casos; la semana siguiente acepta una propuesta. Las 133 pruebas del juego pasan.
  - docs/game.md: tabla de idempotencia y prompt actualizado.


- Revisión QA 2026-11-10 sobre HEAD 62351bb865ea8b1233ef8757b59feed2870a8c5e. Alex confirma funcionamiento en producción (aceptación funcional del usuario, no ejecución técnica contra producción). Suite focal PHP: 301/302; el fallo es CHG-027. Regresión adicional: 54/58, cuatro fallos existentes de shell/preview informados aparte. JS 44/44 y form-editor 3/3. Build correcto con aviso de chunk; conformance global falla por CHG-058 y deuda de CHG-054. CHG-059 concurrente excluido; no se alteró código funcional.
- Recorrido sintético juego/guardianes: 37 respuestas HTTP 200, sin errores JavaScript, guardianes visibles y 19 acciones Otro consejo respondieron. Geometría final 33/33 a 390/768/1440 tras estabilizar transiciones. Imágenes sustituidas por figuras sintéticas: no certifica arte externo. Ver logs closure-qa-game-browser-final.jsonl y closure-qa-geometry-final.json en storage/framework/testing.

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-09 — Creado en to-do/ (Borrador, nivel S)
- 2026-11-09 — Decisión (Alex): una propuesta del director por semana; sin reemplazo explícito. La elección de esta opción con su descripción se toma como aprobación del cambio S.
- 2026-11-09 — Aprobado por el usuario (Compuerta 2)
- 2026-11-09 — Movido a in-progress/ (En desarrollo)
- LECCIÓN(team): toda herramienta MCP pensada para schedules debe definir su respuesta ante una segunda corrida (idempotencia explícita) desde la especificación — evidencia: propose-game-week-missions reemplazaba misiones al correr dos veces (CHG-026 → CHG-028) · destino sugerido: docs/ARCHITECTURE.md (cuando exista) → descartada: no relevante
- 2026-11-09 — Implementación terminada → En verificación
- 2026-11-10 — Cerrado por el usuario (Compuerta 3) → done/
