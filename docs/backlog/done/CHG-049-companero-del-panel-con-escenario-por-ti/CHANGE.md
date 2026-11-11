# CHG-049 · Compañero del panel con escenario por tipo y reacciones
**Estado:** Cerrado
**Nivel:** M
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-10 · **Aprobado:** 2026-11-10 · **Cerrado:** 2026-11-10
**Relacionado:** CHG-043 (integrante del día)

## 0. En palabras simples
- Qué vas a ver distinto: la columna del compañero en el panel se convierte en un pequeño escenario con los colores de su tipo (fuego con brasas, agua con olas, planta con hojas…). El compañero reacciona a cómo va tu día: celebra cuando avanzas y te anima, sin culpa, si el día va lento. Si tienes balls sin abrir, te lo dice con un botón. Y con "Otra frase" cambias la frase sin recargar.
- Cómo comprobarlo tú: abre el panel con un compañero de fuego → fondo cálido. Completa varios hábitos → al actualizarse el panel cambia a "¡Vas volando!". Gana una ball → aparece "Tienes 1 ball". Toca "Otra frase" → cambia la frase.

## 1. Resumen
Hoy la columna (CHG-043) es casi blanca y solo enlaza a la ficha. Se le da un escenario CSS por familia de tipo, una reacción según el avance del día (hábitos y tareas del panel), un aviso de balls por abrir y un botón para pedir otra frase. El compañero sigue siendo el mismo hasta recargar la página; lo que se recalcula con el refresco automático es la reacción y el aviso de balls.

## 2. Producto (product-manager)
- Problema / usuario: Alex siente la tarjeta plana y pasiva; quiere que tenga temática y que haga algo.
- Incluye: escenario por familia de tipo; 4 reacciones; aviso de balls por abrir; botón "Otra frase".
- No incluye: animaciones complejas o arte nuevo de especies; cambiar de compañero sin recargar; reacciones con texto generado por IA.
- Criterios de aceptación (Dado/Cuando/Entonces):
  1. Dado un compañero de tipo fuego (o agua, planta…), entonces la columna usa el escenario de su familia; un tipo sin familia usa el escenario neutro.
  2. Dado el avance del día (hábitos hechos + tareas hechas sobre el total), cuando el panel se carga o se refresca, entonces la reacción es: ≥ 70 % "¡Vas volando!" (celebra), ≥ 30 % "Buen ritmo" (contento), < 30 % antes de las 15:00 "Hoy empezamos juntos" (saluda), < 30 % desde las 15:00 "Un pasito y ya cuenta" (anima). Sin hábitos ni tareas hoy: "Día libre, disfrútalo".
  3. Dado que tengo balls sin abrir, entonces aparece "Tienes {n} ball(s)" que lleva a Balls; sin balls no aparece.
  4. Cuando toco "Otra frase", entonces cambia la frase (distinta de la actual si hay más de una) sin cambiar de compañero ni recargar.
  5. Con movimiento reducido, sin animaciones; los textos siguen ahí.
  6. El compañero no cambia con el refresco automático (se conserva CHG-043).

## 3. UX/UI
### 3.1 Experiencia (ux-designer)
- Plantilla: columna de "Próximos días" del panel de pared (sin cambiar la estructura de la página).
- Flujo: 0 pasos para ver; 1 toque para otra frase; 1 toque para ir a Balls o a la ficha.
- Estados: con balls / sin balls; 5 reacciones; arte o marcador.
- Accesibilidad: "Otra frase" es botón con texto; la reacción es texto real (`role="status"` no, para no anunciar en cada refresco); escenario decorativo `aria-hidden`.

```
┌──── Compañero ──────────┐   escenario fuego: degradado rojo-naranja,
│ ¡Vas volando!        🔥 │   brasas (puntos) y suelo oscuro
│ ░▒▓  · ˙ ·  ˙  ▓▒░      │
│      (arte Magmar)      │   el arte "salta" al celebrar,
│ ▓▓▓▓▓▓ suelo ▓▓▓▓▓▓▓▓   │   flota suave en los demás estados
│ Magmar · Nv 1           │
│ “Nada se pierde por     │
│  empezar despacio.”     │
│ [↻ Otra frase]          │
│ [● Tienes 2 balls  →]   │   solo si hay balls sin abrir
└─────────────────────────┘
```

### 3.2 Visual (ui-designer)
- Familias de escenario (clase `game-stage--{familia}`, colores con contraste AA para texto claro):
  fuego (fuego, dragón): rojo→naranja, brasas · agua (agua, hielo): azul→turquesa, olas · planta (planta, bicho, veneno): verde→lima, hojas · eléctrico (eléctrico, acero): violeta oscuro→amarillo, chispas · místico (psíquico, hada, fantasma, siniestro): índigo→rosa, estrellas · tierra (roca, tierra, lucha): ocre→marrón, piedras · cielo (volador, normal y neutro): celeste→blanco azulado, nubes.
- Todo en CSS (degradados y `radial-gradient` para partículas); sin imágenes nuevas.
- Texto claro sobre el escenario (en "cielo", texto oscuro). Botones `x-ui.action` tonales con fondo translúcido.
- Animaciones: flotar 4 s (normal), saltar 1,2 s (celebra); ninguna con movimiento reducido.

### 3.3 Escritura (ux-writer)
- Reacciones: "¡Vas volando!", "Buen ritmo", "Hoy empezamos juntos", "Un pasito y ya cuenta", "Día libre, disfrútalo".
- Botones: "Otra frase"; "Tienes 1 ball" / "Tienes {n} balls".
- Sin culpa ni obligación.

## 4. Diseño técnico (software-architect)
- Archivos a crear / modificar:
  - `config/game.php`: `stage_families` (tipo slug → familia) y `buddy_moods` (umbrales y textos).
  - `app/Services/Game/DailyBuddy.php`: `stageFamily(?string $type)`, `mood(int $done, int $total, CarbonInterface $now): array{key, text}`, y `quote(int $userId, ?string $except = null)`; `pick()` añade `stage`.
  - `app/Livewire/Home/WallPanel.php`: método `anotherQuote()` (actualiza `buddy['quote']`); en `render()` calcula `buddyMood` con los hábitos/tareas de hoy que ya entrega `WallPanelData` y `unopenedBalls` (`GameEncounter` sin abrir del usuario; reusar la consulta de `App\Support\Game\TabCounters`).
  - `resources/views/livewire/home/wall-panel.blade.php`: pasa `mood` y `balls` al componente.
  - `resources/views/components/game/daily-buddy.blade.php`: variante wall con escenario, reacción, botones.
  - `resources/css/m3/modules/_game.css`: `.game-stage--*`, animaciones.
- Enfoque: el compañero sigue en la propiedad `buddy` (no cambia con el refresco); mood y balls se calculan en cada render. Sin consultas nuevas pesadas: un `count()` de balls.
- Riesgos: el refresco del panel recalcula `render()` — mood y balls deben ser baratos. "Otra frase" con una sola frase disponible devuelve la misma.
- Qué NO tocar: dashboard (sigue con su tarjeta), lógica del juego, `WallPanelData`.

## 5. Pruebas (qa-tester)
| Caso | Tipo | Nivel | Test |
|---|---|---|---|
| Familia de escenario por tipo y neutro | Unit | Medio | tests/Unit/Game/DailyBuddyMoodTest.php |
| Reacciones por umbral, hora y día sin pendientes | Unit | Alto | DailyBuddyMoodTest |
| Aviso de balls con 0, 1 y 2 | Feature | Medio | tests/Feature/Game/DailyBuddyTest.php |
| "Otra frase" cambia la frase y conserva el compañero | Feature | Alto | DailyBuddyTest |
| Refresco conserva compañero y recalcula reacción | Feature | Medio | DailyBuddyTest |
- Comando para verificar: `php artisan test tests/Feature/Game/DailyBuddyTest.php tests/Unit/Game tests/Feature/WallPanelTest.php tests/Feature/Ui`
- Regresión a vigilar: refresco del panel, columna de 4 días, dashboard.

## 6. Documentación (tech-writer)
- Docs a actualizar: `docs/game.md` → "Integrante del día": escenarios, reacciones, balls y "Otra frase".

## 7. Tareas
- [x] T1 · El compañero sabe su escenario y cómo reaccionar a tu día — Resp: developer — Archivos: config/game.php, app/Services/Game/DailyBuddy.php, tests/Unit/Game/DailyBuddyMoodTest.php — Hecho cuando: los tests de familia y reacciones pasan
- [x] T2 · La columna muestra el escenario, la reacción, el aviso de balls y "Otra frase" — Resp: developer — Archivos: app/Livewire/Home/WallPanel.php, resources/views/livewire/home/wall-panel.blade.php, resources/views/components/game/daily-buddy.blade.php, resources/css/m3/modules/_game.css, tests/Feature/Game/DailyBuddyTest.php — Hecho cuando: los criterios 1-6 pasan y el build está OK
- [x] T3 · Verificar criterios de aceptación — Resp: qa-tester — Hecho cuando: todos pasan y se revisó el panel en el navegador
- [x] T4 · Actualizar documentación — Resp: tech-writer — Archivos: docs/game.md — Hecho cuando: docs al día

## 8. Bitácora de implementación (developer)
- 2026-11-10 — Inicio (start --force).
- T1 — `config/game.php` (`stage_families`, `buddy_moods`); `DailyBuddy::stageFamily`, `DailyBuddy::mood`, `quote($userId, $except)`; `pick()` añade `stage`. tests/Unit/Game/DailyBuddyMoodTest.php → 2 pasan.
- T2 — `WallPanel::anotherQuote()` y cálculo de `buddyMood`/`unopenedBalls` en `render()` (con datos que ya entrega WallPanelData + 1 count). Variante wall con escenario, reacción, "Otra frase" (`x-ui.action`, exigido por el contrato de UI) y aviso de balls. CSS `.game-stage--*` con degradados y partículas; animación flotar/saltar sin movimiento reducido. Tests: +2 en DailyBuddyTest; Game/DailyBuddy+Unit/Game+WallPanel+Ui: solo las 4 fallas previas de Ui. Build OK.
- T4 — docs/game.md: columna del panel.
- 2026-11-10 — Corrección: la columna salía blanca porque `.wall-day` de _wall-panel.css (cargado después) pisaba el fondo; ahora el selector es `.wall-day.game-buddy-stage`. Suelo reducido (elipse 55 % × 7 %).
- Pendiente para QA: revisión visual en el navegador (contraste del texto blanco en cada escenario).


- Revisión QA 2026-11-10 sobre HEAD 62351bb865ea8b1233ef8757b59feed2870a8c5e. Alex confirma funcionamiento en producción (aceptación funcional del usuario, no ejecución técnica contra producción). Suite focal PHP: 301/302; el fallo es CHG-027. Regresión adicional: 54/58, cuatro fallos existentes de shell/preview informados aparte. JS 44/44 y form-editor 3/3. Build correcto con aviso de chunk; conformance global falla por CHG-058 y deuda de CHG-054. CHG-059 concurrente excluido; no se alteró código funcional.
- Recorrido sintético juego/guardianes: 37 respuestas HTTP 200, sin errores JavaScript, guardianes visibles y 19 acciones Otro consejo respondieron. Geometría final 33/33 a 390/768/1440 tras estabilizar transiciones. Imágenes sustituidas por figuras sintéticas: no certifica arte externo. Ver logs closure-qa-game-browser-final.jsonl y closure-qa-geometry-final.json en storage/framework/testing.

## 9. Preguntas abiertas, decisiones e historial
- LECCIÓN: ninguna
- 2026-11-10 — Creado en to-do/ (Borrador, nivel M)
- 2026-11-10 — Decisiones (Alex): escenario según el tipo; reaccionar a tu día; avisar balls por abrir; "Otra frase". Sin barra de xp.
- 2026-11-10 — Aprobado por el usuario (Compuerta 2)
- 2026-11-10 — Movido a in-progress/ (En desarrollo)
- 2026-11-10 — Implementación terminada → En verificación
- 2026-11-10 — Cerrado por el usuario (Compuerta 3) → done/
