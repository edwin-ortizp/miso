# CHG-040 · Misiones semanales ajustables y relevantes
**Estado:** Cerrado
**Nivel:** M
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-09 · **Aprobado:** 2026-11-09 · **Cerrado:** 2026-11-10
**Relacionado:** —

## 0. En palabras simples
<!-- Para el usuario: sin jerga. Si no se entiende en 30 segundos, reescríbelo. -->
- Qué vas a ver distinto: la guía podrá sustituir misiones sin avance por MCP; las automáticas priorizarán pendientes no recurrentes.
- Cómo comprobarlo tú: pedir cambiar una misión sin avance y comprobar que las avanzadas conservan progreso y recompensas.

## 1. Resumen
<!-- Qué se cambia y por qué (3-5 líneas). Entendimiento aprobado por el usuario. -->

Plan aprobado en la conversación: sustituciones por ID de la semana actual, atómicas, sin ampliar el cupo ni modificar misiones avanzadas; contexto con editabilidad y conteos. Categorías con pendientes no privados, no recurrentes ni históricos, ordenadas por cantidad; foco solo desempata y la semilla resuelve empates. Meta acotada al inventario, mínimo 1. Las recurrentes siguen sumando progreso. Sin regeneración de semanas existentes.

## 2. Producto (product-manager)
- Problema / usuario: misiones irrelevantes o imposibles por categorías sin pendientes.
- Incluye: herramienta MCP, contexto, consulta compartida, generador, validación y pruebas.
- No incluye: UI, migraciones, cambios a datos reales o recompensas.
- Criterios de aceptación (Dado/Cuando/Entonces): dada una semana con misiones avanzadas, al sustituir una sin avance solo cambia esa misión; dadas categorías con recurrentes, estas no inflan su prioridad ni meta.

## 3. UX/UI
<!-- Sin pantallas: escribe "No aplica — motivo" en cada parte. Con UI, approve exige un boceto ASCII en un bloque de código. Si hay flujo, agrega un diagrama en un bloque mermaid. -->
No aplica — herramientas MCP y servicios sin cambios visuales. Respuestas de error en español, sin revelar datos ajenos.

## 4. Diseño técnico (software-architect)
- Archivos a crear / modificar: servicios Game, herramientas MCP Game, LifeTrackerServer, pruebas Game y docs/game.md.
- Enfoque y patrones a seguir: consulta compartida, transacción y bloqueos compatibles con ActivityEffects; conservar propuesta inicial idempotente.
- Riesgos y mitigación: avance concurrente protegido con bloqueo de usuario y misiones; filtros explícitos de propietario.
- Qué NO tocar: temporadas, recompensas, progreso, cambios ajenos.

## 5. Pruebas (qa-tester)
| Caso | Tipo | Nivel | Test |
|---|---|---|---|
| Sustituciones, aislamiento, reintentos y contexto obsoleto | PHP/MCP | Feature | WeekMissionsUpdateTest |
| Ranking, exclusiones, foco, semilla y metas | PHP | Feature | WeekPlannerTest |
| Propuestas, recompensas y temporadas | PHP | Feature | WeekMissionsProposalTest, ActivityEffectsTest, SeasonManagerTest |
- Comando para verificar: php artisan test --filter="WeekPlannerTest|WeekMissionsProposalTest|WeekMissionsUpdateTest|ActivityEffectsTest|SeasonManagerTest"; git diff --check.
- Regresión a vigilar: idempotencia de propuestas y recompensas; recurrentes siguen sumando.

## 6. Documentación (tech-writer)
- Docs a actualizar: docs/game.md.

## 7. Tareas
<!-- Primero lo que cambia para el usuario; el detalle técnico después. Formato:
- [x] T1 · qué cambia (en simple) — Resp: developer — Modelo: perfil — Archivos: … — Hecho cuando: … -->

- [x] T1 · Ajustar misiones pendientes por MCP y exponer contexto — Resp: developer — Modelo: estandar — Archivos: app/Services/Game, app/Mcp — Hecho cuando: sustituciones atómicas, aisladas e idempotentes; avanzadas intactas.
- [x] T2 · Generar misiones con pendientes reales — Resp: developer — Modelo: estandar — Archivos: app/Services/Game — Hecho cuando: ranking no recurrente y metas viables, variedad elegible.
- [x] T3 · Validar y documentar — Resp: developer — Modelo: estandar — Archivos: tests/Feature/Game, docs/game.md — Hecho cuando: pruebas afectadas y diff check pasan.

## 8. Bitácora de implementación (developer)
- Implementada update-game-week-missions-tool y registrada en LifeTrackerServer; usa sustituciones completas por ID y validación compartida. Contexto con parámetros, editabilidad y conteos completos por categoría.
- Consulta MissionPendingTasks compartida; categorías sin pendientes elegibles fuera de generación y propuestas. Prioridad por cantidad, foco solo en empates, semilla reproducible, sin repetir categoría y meta máxima según inventario (mínimo 1).
- Transacción con bloqueo de usuario y misiones, compatible con RewardGranter/ActivityEffects. Prueba de contexto obsoleto confirma rechazo si una actividad avanzó antes de aplicar el cambio. No se probaron sesiones MySQL concurrentes: la suite usa SQLite en memoria.
- 46 pruebas / 156 aserciones correctas en las cinco clases afectadas. Pint pasa en archivos nuevos, herramientas Game, GameMission y pruebas modificadas. Persisten fallos de estilo preexistentes en MissionProposalValidator, SeasonManager, WeekPlanner y LifeTrackerServer, contrastados contra copias de HEAD; no se reformatean fuera de alcance. git diff --check correcto.
- Actualizado docs/game.md y el prompt recomendado del schedule: ajustes solo a petición del usuario. Sin cambios visuales/assets ni migraciones; no corresponde build ni pruebas de navegador.
- Pendiente verificación del usuario y uso del MCP desplegado; sin despliegue ni modificación de misiones reales. Conservados los cambios ajenos de pokemon.json, CHG-039 y TEAM_PLAN.

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-09 — Creado en to-do/ (Borrador, nivel M)

- Plan aprobado explícitamente por el usuario antes de iniciar la implementación.
- 2026-11-09 — Aprobado por el usuario (Compuerta 2)
- 2026-11-09 — Movido a in-progress/ (En desarrollo)
- 2026-11-09 — Implementación terminada → En verificación
- 2026-11-10 — Cerrado por el usuario (Compuerta 3) → done/
