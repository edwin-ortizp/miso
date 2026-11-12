# CHG-089 · Explicar racha y reto del día en Tu día
**Estado:** Cerrado
**Nivel:** S
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-11 · **Aprobado:** 2026-11-11 · **Cerrado:** 2026-11-11
**Relacionado:** CHG-076, CHG-077, CHG-080

## 0. En palabras simples
<!-- Para el usuario: sin jerga. Si no se entiende en 30 segundos, reescríbelo. -->
- Qué vas a ver distinto: en "Tu día", la racha y el reto tienen cada uno un ícono ⓘ. Al pasar el mouse o tocarlo, explica cómo funciona y que son dos cosas separadas. El reto cambia de un día a otro y deja de salir "Haz tu check-in" como reto nuevo.
- Cómo comprobarlo tú: en el Inicio, pasa el mouse por cada ⓘ y lee la explicación. Durante varios días, revisa que el reto cambie (tareas, hábitos o agua) y que en los días en que no hay ninguno posible no aparezca el mosaico.

## 1. Resumen
La racha (umbral de acciones diarias en al menos 2 áreas, CHG-076) y el reto del día (una meta que da una ball extra, CHG-077) se muestran juntos sin explicar en qué se diferencian. La persona creía que el reto era siempre el check-in. Este cambio agrega una ayuda contextual a cada mosaico y confirma que el check-in no salga como reto nuevo, porque se retiró en CHG-080.

## 3. UX/UI
### 3.1 Experiencia
Plantilla: tarjeta del Inicio. Cambia solo la zona "Tu día" del compañero. El ⓘ se abre al pasar el mouse, con un toque y con el teclado (Enter/Espacio), y se cierra con Esc o al tocar fuera. Reutiliza `x-ui.popover`.

```
┌ Tu día ─────────────────────────────┐
│ 🔥 Racha · 4 días            ⓘ      │
│    1 de 3 acciones hoy              │
│ 🎯 Cierra 2 tareas · 0/2     ⓘ      │
└─────────────────────────────────────┘
        ⓘ → ┌──────────────────────────────┐
             │ Cada día hay un reto distinto │
             │ …                             │
             └──────────────────────────────┘
```

### 3.3 Escritura
- ⓘ de la racha: "Tu racha sube cuando haces {umbral} acciones en al menos 2 áreas distintas (tareas, hábitos, foco…)". `{umbral}` sale de `streak_threshold`, y el 2 de `game.streak.min_areas`.
- ⓘ del reto: "Cada día hay un reto distinto según lo que tienes pendiente. Si lo cumples, ganas una ball extra. Es aparte de la racha, aunque lo que hagas también suma a la racha."
- Etiqueta accesible del botón: "Cómo funciona la racha" / "Cómo funciona el reto del día".

## 4. Diseño técnico
- Vistas: `resources/views/components/game/streak-line.blade.php` y `resources/views/components/game/daily-challenge.blade.php` usan `x-ui.popover`.
- Servicio: verificar con un test que `DailyChallenge::pick` nunca elige `checkin` cuando no está en `config('game.daily_challenge.templates')`. `challengeFor` ya descarta un reto de check-in sin cumplir. Si se encuentra otra vía por la que aparece, corregirla en `app/Services/Game/DailyChallenge.php`.

## 5. Pruebas
- `php artisan test --filter=DailyChallenge`
- Vista: los dos ⓘ se muestran con sus textos y el umbral configurado.
- Servicio: con las plantillas por defecto, durante 30 fechas seguidas no sale nunca `checkin` y sale más de una plantilla distinta.

## 7. Tareas
- [x] T1 · Revisar por qué aparece "Haz tu check-in" como reto y asegurar que no salga como reto nuevo — Resp: developer — Modelo: estandar — Archivos: app/Services/Game/DailyChallenge.php, tests/Feature/Game/DailyChallengeTest.php — Hecho cuando: el test de 30 fechas pasa y en la bitácora queda la causa encontrada
- [x] T2 · Agregar el ⓘ con explicación a la racha y al reto — Resp: developer — Modelo: estandar — Archivos: resources/views/components/game/streak-line.blade.php, resources/views/components/game/daily-challenge.blade.php, resources/css/m3/modules/_game.css, tests/Feature/Game/DailyChallengeViewTest.php — Hecho cuando: los dos ⓘ se abren con mouse, toque y teclado, y el test de vista pasa
- [x] T3 · Verificar criterios de aceptación — Resp: qa-tester — Hecho cuando: todos pasan

## 8. Bitácora de implementación (developer)
- 2026-11-11 — Inicio con `start --force` (CHG-070 sigue En desarrollo; aprobado por el usuario).
- T1 — Causa: el único registro en `game_daily_challenges` es un `checkin` del 2026-11-11, creado a las 17:35 antes de CHG-080 y cumplido a las 20:09. `challengeFor` lo conserva a propósito. No hace falta cambiar código. Test nuevo `test_default_templates_vary_and_never_pick_checkin` (30 fechas): en verde.
- T2 — ⓘ con `x-ui.popover`: se abre con hover (solo dispositivos con hover), con un clic o toque y con el teclado; Esc lo cierra. Archivos: `streak-line.blade.php`, `daily-challenge.blade.php` (solo el mosaico del Inicio), `_game.css` y el componente nuevo `components/game/day-tile-info.blade.php`. El componente se creó para no duplicar el bloque en los dos mosaicos; es una desviación menor del listado de §4. Test `test_home_tiles_explain_streak_and_challenge`. `php artisan test --filter=DailyChallenge` → 18 pasan; `npm run build` OK.
- Suite completa: 1434/1481 pasan y 37 fallan. Ninguno de los fallos corresponde a archivos de este cambio: contratos CSS ya existentes (10 overrides heredados en `_game.css`, `_text-fields.css`), `focus-companion`, `catalog-chip`, Plan/Relationship/EditorPerformance, y en GameDashboardTest el texto "¿Cómo funciona?", que ya no está en ninguna vista. El override `.md-popover` que había agregado se quitó para no sumar deuda.
- Reapertura — El ⓘ quedaba recortado por el `overflow: hidden` del hero. Ahora la ayuda se teletransporta al `body` (`x-teleport`) con un ancla fija bajo el botón, que se recalcula al hacer scroll o cambiar el tamaño. En móvil sigue siendo una hoja inferior. Además, el mosaico reserva espacio a la derecha para que el ⓘ no tape el texto. `--filter=DailyChallenge` en verde; los contratos CSS no cambian (`_game.css` sigue en 10 heredados).
- Reapertura 2 — El ⓘ no respondía al pasar el mouse justo encima. `.game-day-tile__title` ocupa todo el ancho y su `opacity: 0.9` crea un contexto de apilamiento que lo pinta sobre el botón. Se arregló con `z-index: 1` en `.game-day-tile__info`. Nota: la edición del CSS se hizo un momento antes de ejecutar `reopen`, con el CHG En verificación.

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-11 — Creado en to-do/ (Borrador, nivel S)
- 2026-11-11 — Aprobado por el usuario (Compuerta 2)
- 2026-11-11 — Movido a in-progress/ (En desarrollo)
- LECCIÓN: ninguna
- 2026-11-11 — Implementación terminada → En verificación
- 2026-11-11 — Reabierto: El ⓘ queda recortado por la tarjeta del hero
- 2026-11-11 — Implementación terminada → En verificación
- 2026-11-11 — Reabierto: El ⓘ no responde al pasar el mouse justo encima: el título lo tapa
- 2026-11-11 — Implementación terminada → En verificación
- 2026-11-11 — Cerrado por el usuario (Compuerta 3) → done/
