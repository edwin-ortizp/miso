# CHG-053 · Barra de tiempo fluida en los avisos del juego
**Estado:** En desarrollo
**Nivel:** XS
**Modelo sugerido:** rapido
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-10 · **Aprobado:** 2026-11-10 · **Cerrado:** —
**Relacionado:** —

## 0. En palabras simples
<!-- Para el usuario: sin jerga. Si no se entiende en 30 segundos, reescríbelo. -->
- Qué vas a ver distinto: Barra de tiempo fluida en los avisos del juego
- Cómo comprobarlo tú: revisarlo en la pantalla afectada

## 7. Tareas
<!-- Primero lo que cambia para el usuario; el detalle técnico después. Formato:
- [ ] T1 · qué cambia (en simple) — Resp: developer — Modelo: perfil — Archivos: … — Hecho cuando: … -->
- [ ] T1 · Barra de tiempo fluida en los avisos del juego — Resp: developer — Hecho cuando: el usuario lo confirma
## 8. Bitácora de implementación (developer)
- 2026-11-10 — La barra usaba setInterval de 100 ms + estilo inline `--game-toast-left`; el morph de Livewire borraba el estilo (saltos) y el intervalo derivaba (titileo). Ahora es un `<span class="game-toast__timer">` con animación CSS de 5 s (pausa con `animation-play-state`) y cierre en `animationend`. Archivos: `resources/views/livewire/game/game-notifier.blade.php`, `resources/css/m3/modules/_game.css`. Build OK; tests GameNotifier 11/11.

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-10 — Cambio rápido (XS) aprobado por solicitud directa del usuario → in-progress/
