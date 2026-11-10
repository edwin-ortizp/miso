# CHG-035 · Fijar APP_URL de pruebas en phpunit.xml
**Estado:** Cerrado
**Nivel:** XS
**Modelo sugerido:** rapido
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-09 · **Aprobado:** 2026-11-09 · **Cerrado:** 2026-11-09
**Relacionado:** —

## 0. En palabras simples
<!-- Para el usuario: sin jerga. Si no se entiende en 30 segundos, reescríbelo. -->
- Qué vas a ver distinto: Fijar APP_URL de pruebas en phpunit.xml
- Cómo comprobarlo tú: revisarlo en la pantalla afectada

## 7. Tareas
<!-- Primero lo que cambia para el usuario; el detalle técnico después. Formato:
- [x] T1 · qué cambia (en simple) — Resp: developer — Modelo: perfil — Archivos: … — Hecho cuando: … -->
- [x] T1 · Fijar APP_URL de pruebas en phpunit.xml — Resp: developer — Hecho cuando: el usuario lo confirma
## 8. Bitácora de implementación (developer)

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-09 — Cambio rápido (XS) aprobado por solicitud directa del usuario → in-progress/
- 2026-11-09 — Implementado: phpunit.xml fija APP_URL=http://localhost; las pruebas ya no dependen del .env local (instalación bajo subruta). Juego: 163 pasan sin variables manuales.
- LECCIÓN: ver CHG-033 (las pruebas deben fijar APP_URL).
- 2026-11-09 — Cerrado por el usuario (Compuerta 3) → done/
