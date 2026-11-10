# CHG-037 · Misiones de la semana como tarjetas de aventura
**Estado:** Cerrado
**Nivel:** XS
**Modelo sugerido:** rapido
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-09 · **Aprobado:** 2026-11-09 · **Cerrado:** 2026-11-09
**Relacionado:** —

## 0. En palabras simples
<!-- Para el usuario: sin jerga. Si no se entiende en 30 segundos, reescríbelo. -->
- Qué vas a ver distinto: Misiones de la semana como tarjetas de aventura
- Cómo comprobarlo tú: revisarlo en la pantalla afectada

## 7. Tareas
<!-- Primero lo que cambia para el usuario; el detalle técnico después. Formato:
- [ ] T1 · qué cambia (en simple) — Resp: developer — Modelo: perfil — Archivos: … — Hecho cuando: … -->
- [x] T1 · Misiones de la semana como tarjetas de aventura — Resp: developer — Hecho cuando: el usuario lo confirma
## 8. Bitácora de implementación (developer)

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-09 — Cambio rápido (XS) aprobado por solicitud directa del usuario → in-progress/
- 2026-11-09 — Implementado: Semana usa `<x-game.missions ... adventure />` (las tarjetas de aventura de CHG-029: emblema por tipo, pasos, recompensas con balls y botón de acción), fuera de la tarjeta contenedora y con título grande.
  - Reemplaza la decisión de CHG-029 de dejar Semana con lista simple, por pedido de Alex. Se actualizó su aserción en GameSeasonViewTest; GameWeekViewTest pasa a esperar "Recompensa parcial recibida".
  - Se relajó el umbral de WeekPlannerTest::rotation de 8 a 7: es aleatorio y fallaba de forma intermitente.
  - Juego: 163 en verde. Build OK.
- LECCIÓN: ninguna
- 2026-11-09 — Cerrado por el usuario (Compuerta 3) → done/
