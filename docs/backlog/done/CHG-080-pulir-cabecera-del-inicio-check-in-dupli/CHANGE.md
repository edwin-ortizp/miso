# CHG-080 · Pulir cabecera del Inicio: check-in duplicado, contraste y sombra
**Estado:** Cerrado
**Nivel:** XS
**Modelo sugerido:** rapido
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-11 · **Aprobado:** 2026-11-11 · **Cerrado:** 2026-11-11
**Relacionado:** —

## 0. En palabras simples
<!-- Para el usuario: sin jerga. Si no se entiende en 30 segundos, reescríbelo. -->
- Qué vas a ver distinto: Pulir cabecera del Inicio: check-in duplicado, contraste y sombra
- Cómo comprobarlo tú: revisarlo en la pantalla afectada

## 7. Tareas
<!-- Primero lo que cambia para el usuario; el detalle técnico después. Formato:
- [ ] T1 · qué cambia (en simple) — Resp: developer — Modelo: perfil — Archivos: … — Hecho cuando: … -->
- [x] T1 · Pulir cabecera del Inicio: check-in duplicado, contraste y sombra — Resp: developer — Hecho cuando: el usuario lo confirma
## 8. Bitácora de implementación (developer)
- 2026-11-11 — Sin plantilla `checkin` en el reto del día. En el hero: racha, reto, «Ir» y barra heredan el blanco (pista translúcida y etiqueta «Avance del reto» oculta a la vista, pero accesible). La elipse del suelo de `.game-buddy-stage__scene` va bajo el arte (80 px; 48 px en móvil) en vez de en el centro. Pruebas del reto e Inicio en verde; Ui sin fallas nuevas; build ok.

## 9. Preguntas abiertas, decisiones e historial
- LECCIÓN(developer): un escenario decorativo con posiciones en % debe revisarse cuando su contenedor cambia de forma (panel → hero) — la elipse del suelo al 50 % cayó sobre el texto · destino sugerido: docs/UI-DESIGN.md
- 2026-11-11 — Cambio rápido (XS) aprobado por solicitud directa del usuario → in-progress/
- 2026-11-11 — Cerrado por el usuario (Compuerta 3) → done/
