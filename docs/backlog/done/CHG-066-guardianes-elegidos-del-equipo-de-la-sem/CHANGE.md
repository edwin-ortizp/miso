# CHG-066 · Guardianes elegidos del equipo de la semana
**Estado:** Cerrado
**Nivel:** S
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-11 · **Aprobado:** 2026-11-11 · **Cerrado:** 2026-11-11
**Relacionado:** —

## 0. En palabras simples
<!-- Para el usuario: sin jerga. Si no se entiende en 30 segundos, reescríbelo. -->
- Qué vas a ver distinto: el guardián de cada módulo ahora es un integrante de tu equipo de la semana (el de tipo más afín) y su tarjeta dice "Equipo de la semana · habilidad". Cada lunes, al rotar el equipo, rotan los guardianes.
- Cómo comprobarlo tú: abre Tareas o Hábitos y verifica que el guardián aparece en Juego → Semana.

## 1. Resumen
El equipo de la semana solo se veía en la vista del juego y los guardianes salían de toda la colección. Unificamos: los guardianes se eligen entre los integrantes del equipo semanal (afinidad de tipo de `game.module_guardians`; si no hay afín, el de mayor nivel del equipo; sin equipo, el comportamiento anterior). Así el equipo se ve en toda la app durante la semana.

## 7. Tareas
- [x] T1 · El guardián sale del equipo de la semana — Resp: developer — Modelo: estandar — Archivos: app/Services/Game/ModuleGuardian.php — Hecho cuando: pick() prioriza integrantes del equipo con afinidad, luego el de mayor nivel del equipo, y sin equipo cae al comportamiento previo.
- [x] T2 · La tarjeta del guardián dice que es del equipo — Resp: developer — Modelo: estandar — Archivos: resources/views/livewire/game/guardian-card.blade.php — Hecho cuando: se ve "Equipo de la semana · {habilidad}" cuando aplica.
- [x] T3 · El compañero de la rutina de hábitos es el guardián de Hábitos — Resp: developer — Modelo: estandar — Archivos: app/Livewire/Habit/HabitTracker.php — Hecho cuando: routineCompanion() usa ModuleGuardian::pick(…, 'habits').
- [x] T4 · Pruebas y documentación — Resp: developer — Modelo: estandar — Archivos: tests/Feature/Game/ModuleGuardianTest.php, docs/game.md — Hecho cuando: hay pruebas de equipo afín, equipo sin afín y rotación semanal; docs actualizados.

## 8. Bitácora de implementación (developer)
- 2026-11-11 — `ModuleGuardian::pick()` filtra por integrantes del equipo semanal (fallback a la colección) y expone `team_ability`; etiqueta en la tarjeta; `HabitTracker::routineCompanion()` usa el guardián de Hábitos; 3 pruebas nuevas; docs/game.md actualizado. Fuera de alcance: mostrar en Juego → Semana qué módulos cuida cada integrante (propuesto como seguimiento).

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-11 — LECCIÓN(developer): los assets compilados (`public/css`, `public/js`) viven en el repo; todo cambio de CSS/JS exige `npm run build`. Cerrado sin ejecutar las pruebas en la nube (PHP 8.3 vs 8.4): verificar en local.
- 2026-11-11 — Creado en to-do/ (Borrador, nivel S)
- 2026-11-11 — Aprobado por el usuario (Compuerta 2)
- 2026-11-11 — Movido a in-progress/ (En desarrollo)
- 2026-11-11 — Implementación terminada → En verificación
- 2026-11-11 — Cerrado por el usuario (Compuerta 3) → done/
