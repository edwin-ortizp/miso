# CHG-045 · Temporadas tematicas por calendario
**Estado:** Cerrado
**Nivel:** S
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-09 · **Aprobado:** 2026-11-09 · **Cerrado:** 2026-11-10
**Relacionado:** CHG-022 (director y temporadas), CHG-029 (temporada ilustrada), CHG-034 (celebraciones)

## 0. En palabras simples
- Qué vas a ver distinto: cuando se acerca Navidad, el inicio de año, Halloween, Amor y amistad o tu cumpleaños, el director puede proponer una temporada con ese tema. La pestaña Temporada se viste con sus colores e ícono, salen más especies de 1-2 tipos relacionados (por ejemplo hielo en diciembre) y aparece una celebración propia (nieve, calabazas, corazones, velas…).
- Cómo comprobarlo tú: con una temporada "navidad" activa, la pestaña Temporada se ve navideña y al ganar una ball puede salir la celebración de nieve.

## 1. Resumen
El contexto del director incluye fechas temáticas próximas (festividades fijas y el día/mes del cumpleaños, nunca el año). `SeasonValidator` acepta `theme` (catálogo cerrado) y `featured_types` (hasta 2 tipos, peso ×1,5 dentro de su rareza). Cada tema trae paleta, ícono y una celebración que se suma al azar solo mientras la temporada esté activa. Sube a S con QA por usar la fecha de nacimiento (dato personal): solo se expone mes y día.

## 3. UX/UI
### 3.1 Experiencia (ux-designer)
- Plantilla: la pestaña Temporada existente (CHG-029); solo cambia su tema.
```
┌ 🎄 Temporada · Luces de diciembre ─────────────┐   ← paleta del tema
│ Narrativa breve del director…                  │
│ Tipos destacados: [Hielo] [Hada]               │
│ Reglas en lenguaje simple (como hoy)           │
└────────────────────────────────────────────────┘
```
- Sin tema, todo queda como hoy. La celebración temática respeta Ajustes → "Mostrar celebraciones" y movimiento reducido; no aparece en la lista "Cuáles pueden salir" (es de temporada).

### 3.2 Visual (ui-designer)
- Clase `game-season--theme-{clave}` en la pestaña Temporada con variables `--game-season-accent` y `--game-season-on-accent` por tema en `_game.css`, contraste AA. Íconos Bootstrap Icons existentes. Chips de tipo con `game-type--{slug}`.

### 3.3 Escritura (ux-writer)
- Temas: "Navidad", "Año nuevo", "Halloween", "Amor y amistad", "Tu cumpleaños". Rótulo "Tipos destacados". Regla en simple: "Salen más especies de tipo {tipos}."

## 4. Diseño técnico
- `config/game.php` → `season_themes`: por clave (`navidad`, `ano-nuevo`, `halloween`, `amor-amistad`, `cumpleanos`): `label`, `icon`, `celebration` (`snow`, `sparkles`, `pumpkins`, `hearts`, `candles`), `window` (fechas fijas: 1-31 dic, 1-31 ene, 15-31 oct, 1-30 sep; cumpleaños: ±7 días) y `suggested_types`.
- `get-game-director-context-tool`: `calendar_hints` = temas cuya ventana empieza en las próximas 6 semanas, con `theme`, `starts_on`, `suggested_types`. Para `cumpleanos` usa `users.birth_date` solo como mes-día; no expone año ni edad.
- `SeasonValidator`: claves opcionales `theme` (en catálogo) y `featured_types` (≤ 2, tipos existentes en `game_species.types`); errores en español como los demás.
- `EncounterRoller`: si la temporada tiene `featured_types`, peso ×1,5 a especies con ese tipo dentro de la rareza elegida (se combina con `featured_line`). Semilla registrada como hoy.
- `GameNotifier::celebrate`: si la temporada activa tiene tema, su celebración entra al sorteo junto al conjunto del usuario.
- `resources/js/game-celebrations.js`: 5 funciones nuevas en el catálogo JS (mismo patrón y duración).
- Vista `game-season.blade.php`: clase de tema, ícono y chips.
- Archivos: config/game.php, app/Mcp/Tools/Game/GetGameDirectorContextTool.php, app/Services/Game/SeasonValidator.php, app/Services/Game/EncounterRoller.php, app/Services/Game/GameRules.php, app/Livewire/Game/GameNotifier.php, resources/js/game-celebrations.js, resources/views/livewire/game/game-season.blade.php, resources/css/m3/modules/_game.css.
- Riesgo: dato personal (fecha de nacimiento) → solo mes-día, solo para el usuario autenticado, test que lo garantiza.

## 5. Pruebas
- `SeasonValidatorTest`: tema válido/inválido, `featured_types` > 2 o tipo inexistente → rechazo.
- `GetGameDirectorContextToolTest`: `calendar_hints` incluye Navidad en noviembre; cumpleaños aparece sin año; sin `birth_date` no aparece.
- `EncounterRollerTest`: con semilla fija, `featured_types` aumenta la proporción del tipo.
- `GameNotifierTest`: con temporada temática, la celebración del tema puede salir; con celebraciones apagadas, no.
- Comando: `php artisan test tests/Feature/Game tests/Unit/Game`.

## 7. Tareas
- [x] T1 · El director ve las fechas temáticas próximas y puede proponer tema y tipos destacados — Resp: developer — Archivos: config/game.php, app/Mcp/Tools/Game/GetGameDirectorContextTool.php, app/Services/Game/SeasonValidator.php, tests/Unit/Game/SeasonValidatorTest.php, tests/Feature/Game/GetGameDirectorContextToolTest.php — Hecho cuando: los tests de validador y contexto (incluido cumpleaños sin año) pasan
- [x] T2 · Durante la temporada salen más especies de los tipos destacados — Resp: developer — Archivos: app/Services/Game/EncounterRoller.php, app/Services/Game/GameRules.php, tests/Unit/Game/EncounterRollerTest.php — Hecho cuando: el test con semilla fija pasa
- [x] T3 · La pestaña Temporada se viste con el tema y aparece su celebración propia — Resp: developer — Archivos: resources/views/livewire/game/game-season.blade.php, resources/css/m3/modules/_game.css, resources/js/game-celebrations.js, app/Livewire/Game/GameNotifier.php, tests/Feature/Game/GameNotifierTest.php — Hecho cuando: con una temporada "navidad" la pestaña cambia y la celebración de nieve puede salir
- [x] T4 · Verificar criterios, incluido que no se expone el año de nacimiento — Resp: qa-tester — Hecho cuando: suite Game en verde y revisión del contexto MCP
- [x] T5 · Actualizar docs/game.md (temas, límites del validador y prompt del director) — Resp: tech-writer — Archivos: docs/game.md — Hecho cuando: docs al día

## 8. Bitácora de implementación (developer)
- 2026-11-10 — Inicio (start --force).
- T1 — `config('game.season_themes')` y límites en `game.season`. `SeasonValidator` acepta `theme` y `featured_types` (slugs que existan en `game_species.types`). `calendar_hints` se arma en `SeasonManager::calendarHints`, porque es quien construye el contexto; la herramienta MCP no cambió. Del cumpleaños solo sale mes-día.
- T2 — `EncounterRoller::roll` recibe `featuredTypes`; pesos enteros (base 2, tipo 3, línea ×featuredWeight). Sin línea ni tipos se conserva el camino anterior, así que las semillas de las pruebas existentes no cambian. `GameRules` expone `theme`/`featured_types` y `RewardGranter` los pasa.
- T3 — Pestaña Temporada con clase de tema, ícono y chips (reusa `.game-type-chip`). 5 celebraciones temáticas en JS con CSS que reutiliza keyframes existentes. `GameNotifier` suma la del tema al sorteo y no celebra si las celebraciones están apagadas. `celebration_catalog` no cambia: no aparecen en "Cuáles pueden salir".
- T5 — docs/game.md: temas, `calendar_hints` y ajuste del prompt del director.
- Tests: tests/Feature/Game/SeasonThemeTest.php → 7 pasan. Un solo archivo en lugar de los 4 de §5, con los mismos casos. Game+Ui: solo fallas previas ajenas. `npm run test:js` 42/42. Build OK.


- Revisión QA 2026-11-10 sobre HEAD 62351bb865ea8b1233ef8757b59feed2870a8c5e. Alex confirma funcionamiento en producción (aceptación funcional del usuario, no ejecución técnica contra producción). Suite focal PHP: 301/302; el fallo es CHG-027. Regresión adicional: 54/58, cuatro fallos existentes de shell/preview informados aparte. JS 44/44 y form-editor 3/3. Build correcto con aviso de chunk; conformance global falla por CHG-058 y deuda de CHG-054. CHG-059 concurrente excluido; no se alteró código funcional.
- Recorrido sintético juego/guardianes: 37 respuestas HTTP 200, sin errores JavaScript, guardianes visibles y 19 acciones Otro consejo respondieron. Geometría final 33/33 a 390/768/1440 tras estabilizar transiciones. Imágenes sustituidas por figuras sintéticas: no certifica arte externo. Ver logs closure-qa-game-browser-final.jsonl y closure-qa-geometry-final.json en storage/framework/testing.

## 9. Preguntas abiertas, decisiones e historial
- LECCIÓN(developer): en Blade, `@php(...)` inline con corchetes y `??` anidados rompe el compilador ("unexpected endforeach"); usar el bloque `@php … @endphp` · destino sugerido: docs/ARCHITECTURE.md (convenciones de vistas) → descartada: no relevante
- 2026-11-09 — Creado en to-do/ (Borrador, nivel S)
- 2026-11-09 — Decisiones (Alex): festividades fijas y cumpleaños; efecto = tema visual + tipos destacados + celebración propia.
- 2026-11-09 — Aprobado por el usuario (Compuerta 2)
- 2026-11-10 — Movido a in-progress/ (En desarrollo)
- 2026-11-10 — Implementación terminada → En verificación
- 2026-11-10 — Cerrado por el usuario (Compuerta 3) → done/
