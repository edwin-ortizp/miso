# CHG-044 · Sonido sutil al ganar y abrir balls
**Estado:** En verificación
**Nivel:** S
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-09 · **Aprobado:** 2026-11-09 · **Cerrado:** —
**Relacionado:** CHG-034 (celebraciones), CHG-038 (avisos)

## 0. En palabras simples
- Qué vas a ver distinto: al ganar una ball, abrirla, subir de nivel, evolucionar o cumplir una misión o meta suena un sonido muy corto y suave. Viene encendido a volumen bajo y se apaga en Colección → Ajustes → Celebraciones, donde también hay un botón para probarlo.
- Cómo comprobarlo tú: completa una tarea que dé ball → campanita suave. Abre la ball → "pop" con destello. Apaga "Sonidos" → silencio.

## 1. Resumen
Sonidos sintetizados con Web Audio (sin archivos ni derechos de terceros) en los 4 tipos de momento importante, con interruptor en Ajustes (encendido por defecto, volumen bajo fijo). Solo suenan después de una interacción del usuario en la página (política de autoplay del navegador); si el navegador lo bloquea, no pasa nada.

## 3. UX/UI
### 3.1 Experiencia (ux-designer)
- Plantilla: ajustes (sección Celebraciones existente).
- Se agrega un interruptor "Sonidos" y la acción "Probar sonido" en Celebraciones.
```
┌ Celebraciones ──────────────────────────────┐
│ [●] Mostrar celebraciones                   │
│ [●] Sonidos            [ Probar sonido ]    │
│     Suaves y cortos, al ganar o abrir balls.│
│ Cuáles pueden salir: …                      │
└─────────────────────────────────────────────┘
```
- El sonido es independiente de las animaciones: con movimiento reducido o celebraciones apagadas, el sonido sigue según su interruptor.

### 3.2 Visual (ui-designer)
- `x-ui.switch` y `x-ui.action variant="text"` existentes; ayuda en `md-body-small`. Sin CSS nuevo.

### 3.3 Escritura (ux-writer)
- Textos finales: interruptor "Sonidos"; ayuda "Suaves y cortos, al ganar o abrir balls."; acción "Probar sonido".

## 4. Diseño técnico
- `resources/js/game-sounds.js` (nuevo): un `AudioContext` perezoso creado/reanudado en el primer `pointerdown`/`keydown` de la página. Catálogo `ball` (2 notas senoidales ascendentes, ~250 ms), `open` (pop corto + brillo agudo, ~300 ms), `level` (3 notas ascendentes), `mission` (acorde breve). Ganancia máxima 0.08 con envolvente de ataque/caída. `play(name)` no hace nada si no hay contexto activo, si `document.hidden`, o si el ajuste está apagado.
- El ajuste llega al cliente como `data-game-sound="1|0"` en el elemento de `game-notifier` (o en el `body` del layout) y se actualiza al guardar Ajustes.
- `GameNotifier`: el despacho `game-celebrate` incluye `sound` (`ball`, `level`, `mission`) según el tipo del aviso de mayor importancia; si las celebraciones visuales están apagadas, igual despacha `game-sound`.
- Apertura de ball: el componente de apertura llama `play('open')` al iniciar la secuencia (también con "Mostrar ahora" y con movimiento reducido).
- `config/game.php`: `defaults.sounds => true`. `GameSettings`, `GameSettingsPage`, `game-settings.blade.php`.
- Archivos: resources/js/game-sounds.js, resources/js/game-celebrations.js, resources/js/app.js, app/Livewire/Game/GameNotifier.php, resources/views/livewire/game/game-notifier.blade.php, resources/views/components/game/opening-ball.blade.php, config/game.php, app/Services/Game/GameSettings.php, app/Livewire/Game/GameSettingsPage.php, resources/views/livewire/game/game-settings.blade.php.
- Riesgo: autoplay bloqueado → silencio sin error (se atrapa la promesa de `resume()`).

## 5. Pruebas
- Feature: `GameSettingsPageTest` guarda y lee `sounds`; `GameNotifierTest` despacha `sound` correcto por tipo y no lo despacha con `sounds = false`.
- Unit JS (si el repo tiene runner, como `pomodoro-timer`): `play` no hace nada sin contexto, oculto o apagado.
- Manual: escuchar los 4 sonidos y "Probar sonido"; volumen bajo con el volumen del sistema al 50 %.
- Comando: `php artisan test tests/Feature/Game`.

## 7. Tareas
- [x] T1 · Suena una campanita suave al ganar, abrir, subir de nivel o cumplir misión/meta — Resp: developer — Archivos: resources/js/game-sounds.js, resources/js/game-celebrations.js, resources/js/app.js, app/Livewire/Game/GameNotifier.php, resources/views/livewire/game/game-notifier.blade.php, resources/views/components/game/opening-ball.blade.php, tests/Feature/Game/GameNotifierTest.php — Hecho cuando: los 4 momentos suenan y el test de despacho pasa
- [x] T2 · Interruptor "Sonidos" y "Probar sonido" en Ajustes, encendido por defecto — Resp: developer — Archivos: config/game.php, app/Services/Game/GameSettings.php, app/Livewire/Game/GameSettingsPage.php, resources/views/livewire/game/game-settings.blade.php, tests/Feature/Game/GameSettingsPageTest.php — Hecho cuando: apagar silencia todo y el test de ajustes pasa
- [ ] T3 · Verificar criterios — Resp: qa-tester — Hecho cuando: prueba manual y suite Game en verde
- [x] T4 · Documentar sonidos en docs/game.md (Celebraciones) — Resp: tech-writer — Archivos: docs/game.md — Hecho cuando: docs al día

## 8. Bitácora de implementación (developer)
- 2026-11-10 — Inicio (start --force).
- T1 — `game-sounds.js` (4 sonidos Web Audio, ganancia 0.08, desbloqueo en el primer pointerdown/keydown, no suena con `document.hidden` ni con el ajuste apagado). `GameNotifier` despacha `game-sound` con el momento más importante de la tanda y expone `data-game-sound`; `game-reveal.js` suena `open` en `begin()`. La apertura vive en `game-reveal.js`, no en `opening-ball.blade.php` (sin cambios ahí).
- T2 — `sounds` en config/GameSettings/GameSettingsPage; interruptor y "Probar sonido" en Celebraciones; al guardar se despacha `game-settings-saved` y el notificador actualiza su ajuste.
- T4 — docs/game.md: sonidos en Celebraciones.
- Tests: GameNotifierTest +3 (11 pasan); `npm run test:js` 42/42; suite Game 196/197 (falla previa ajena). No se agregó test JS propio de game-sounds (exigía editar package.json, fuera de §4).

## 9. Preguntas abiertas, decisiones e historial
- LECCIÓN(developer): los imports entre módulos de resources/js deben llevar extensión `.js`, porque `npm run test:js` los carga con Node ESM sin Vite y falló con ERR_MODULE_NOT_FOUND · destino sugerido: docs/TESTING.md
- 2026-11-09 — Creado en to-do/ (Borrador, nivel S)
- 2026-11-09 — Decisiones (Alex): sonidos generados con Web Audio; en ganar ball, abrir, nivel/evolución y misión/meta cumplida; encendidos por defecto a volumen bajo.
- 2026-11-09 — Aprobado por el usuario (Compuerta 2)
- 2026-11-10 — Movido a in-progress/ (En desarrollo)
- 2026-11-10 — Implementación terminada → En verificación
