# CHG-173 · Presentar el listado de tareas como tarjetas en desktop
**Estado:** Cerrado
**Nivel:** XS
**Modelo sugerido:** rapido
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-18 · **Aprobado:** 2026-11-18 · **Cerrado:** 2026-11-18
**Relacionado:** —

## 0. En palabras simples
<!-- Para el usuario: sin jerga. Si no se entiende en 30 segundos, reescríbelo. -->
- Qué vas a ver distinto: Presentar el listado de tareas como tarjetas en desktop
- Cómo comprobarlo tú: revisarlo en la pantalla afectada

## 7. Tareas
<!-- Primero lo que cambia para el usuario; el detalle técnico después. Formato:
- [ ] T1 · qué cambia (en simple) — Resp: developer — Modelo: perfil — Archivos: … — Hecho cuando: … -->
- [x] T1 · Presentar el listado de tareas como tarjetas en desktop — Resp: developer — Hecho cuando: el listado muestra tarjetas independientes y conserva el comportamiento móvil
## 8. Bitácora de implementación (developer)

- 2026-11-18 · Se ajustaron el Blade y CSS del listado y se compiló el CSS publicado. Pasaron 13 pruebas del listado. Chromium verificó el separador, la distribución y el menú por teclado a 390, 768, 1024 y 1440 px, y el cambio de columnas a 679/680 px. El control de conformidad reportó tres incidencias en vistas ajenas al cambio.

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-18 — Cambio rápido (XS) aprobado por solicitud directa del usuario → in-progress/
- LECCIÓN: ninguna.
- 2026-11-18 — Implementación terminada → En verificación
- 2026-11-18 — Cerrado por el usuario (Compuerta 3) → done/
