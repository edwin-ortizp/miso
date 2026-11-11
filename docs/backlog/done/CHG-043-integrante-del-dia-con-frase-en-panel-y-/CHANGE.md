# CHG-043 · Integrante del dia con frase en panel y dashboard
**Estado:** Cerrado
**Nivel:** M
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-09 · **Aprobado:** 2026-11-09 · **Cerrado:** 2026-11-10
**Relacionado:** CHG-026 (misiones del director vía MCP), CHG-033 (carta coleccionable)

## 0. En palabras simples
- Qué vas a ver distinto: al abrir el panel de pared o el dashboard aparece uno de tus Pokémon descubiertos, elegido al azar, con su nivel y una frase cálida. Cada vez que recargas la página sale otro. Las frases las renueva el director IA cada lunes.
- Cómo comprobarlo tú: recarga el panel 3 veces → cambia el integrante (si tienes más de uno). Espera la actualización automática del panel → no cambia. Toca la tarjeta → se abre su ficha.

## 1. Resumen
Una tarjeta "compañero" en el panel de pared (franja Ahora) y en el dashboard, con un ejemplar aleatorio del usuario y una frase de un catálogo en BD. El director agrega frases nuevas cada lunes vía una herramienta MCP; las viejas se retiran solas.

## 2. Producto (product-manager)
- Problema / usuario: la colección solo se ve entrando a `/game`; Alex quiere verla a diario en el tablero con un mensaje motivador que no se repita siempre.
- Incluye: tarjeta en panel y dashboard; tabla de frases con 30 semilla; herramienta MCP `propose-game-quotes-tool`; retiro automático de frases del director con más de 60 días; estado vacío.
- No incluye: frases personalizadas con datos del usuario, elegir favorito, cambiar el integrante con la actualización automática del panel.
- Criterios de aceptación (Dado/Cuando/Entonces):
  1. Dado que tengo ejemplares, cuando cargo panel o dashboard, entonces veo uno al azar con arte, nombre, nivel y una frase vigente.
  2. Cuando el panel se actualiza solo, entonces el integrante y la frase no cambian.
  3. Dado que no tengo ejemplares, entonces veo "Abre tu primera ball para conocer a tu compañero." con enlace a Balls.
  4. Con el juego apagado, la tarjeta no aparece.
  5. Cuando el director envía 7 a 14 frases válidas, se guardan como vigentes; con otra cantidad, longitud > 140 o palabras prohibidas ("debes", "obligatorio", "atrasado", "fallaste", "castigo"), se rechaza todo con motivos.
  6. Una segunda propuesta la misma semana responde `ya_propuestas` y no guarda nada.
  7. Las frases del director con más de 60 días no se eligen; las semilla siempre son elegibles.
  8. Tocar la tarjeta abre la ficha (`/game?species={id}`).

## 3. UX/UI
### 3.1 Experiencia (ux-designer)
- Flujo y cantidad de pasos: 0 pasos para verla; 1 clic para abrir la ficha.
- Contenido: arte (o marcador neutro), nombre, "Nv {n}", frase entre comillas. Lo más importante: la frase.
- Estados: con ejemplar; vacío (invitación); juego apagado (oculto). Sin carga visible: se resuelve en el render.
- Información necesaria: ninguna adicional.
- Accesibilidad: la tarjeta es un enlace con `aria-label="Ver ficha de {nombre}"`; la frase es texto real.

Panel de pared — versión final: columna propia en Próximos días (4 días + compañero):
```
┌─ Hoy ─┬─ mié ─┬─ jue ─┬─ vie ─┬──── Compañero ────┐
│ …     │ …     │ …     │ …     │   (arte 160 px)   │
│       │       │       │       │  Magmar · Nv 1    │
│       │       │       │       │ “Nada se pierde…” │
└───────┴───────┴───────┴───────┴───────────────────┘
```
Segunda versión (descartada, choca con los anillos):
```
┌ Ahora ──────────────────────────────────────────────┐
│ 08:41                         ◯  ◯  ◯  ◯            │
│ Martes 10 nov · Mañana                              │
│                  ╭───────────────────────╮          │
│                  │ Paso a paso también…  │   (🦊)  │
│ ─ línea de tiempo ────────────────────────  ╱▔▔▔▔   │
└─────────────────────────────────────────────────────┘
```
Versión inicial (descartada):
```
┌ Ahora ──────────────────────────────────────────────┐
│ 07:42  lun 9 nov · Mañana        ◯ ◯ ◯ ◯  anillos   │
│                                ┌──────────────────────┐│
│                                │ [arte] Gotin · Nv 12 ││
│                                │ "Paso a paso también ││
│                                │  se llega lejos."    ││
│                                └──────────────────────┘│
└─────────────────────────────────────────────────────┘
```
Dashboard (primera fila de la cuadrícula, ancho completo en móvil):
```
┌───────────────────────────────────────────────┐
│ [arte]  Gotin · Nv 12                          │
│         "Paso a paso también se llega lejos."  │
└───────────────────────────────────────────────┘
Vacío:
┌───────────────────────────────────────────────┐
│ [ball]  Abre tu primera ball para conocer a    │
│         tu compañero.            [Ir a Balls]  │
└───────────────────────────────────────────────┘
```

### 3.2 Visual (ui-designer)
- Distribución: componente nuevo `x-game.daily-buddy` con variantes `wall` (compacta, texto grande legible a distancia) y `card` (dashboard).
- Componentes a reutilizar: `SpeciesImageProvider` para el arte, colores de tipo `game-type--{slug}` como acento del borde (resources/css/m3/modules/_game.css), `md-card-elevated`.
- Jerarquía: frase `md-body-large` (panel: `md-title-medium`); nombre y nivel `md-label-large`. Arte 64 px en dashboard, 96 px en panel.
- Responsive y contraste: en el panel no desplaza los anillos (se apila debajo en anchos < 1200 px); contraste AA sobre el acento de tipo.

### 3.3 Escritura (ux-writer)
- Ubicación y estado: tarjeta en panel y dashboard.
- Textos finales: "{Nombre} · Nv {nivel}"; frase entre comillas tipográficas “…”; vacío "Abre tu primera ball para conocer a tu compañero." con acción "Ir a Balls"; aria-label "Ver ficha de {nombre}".
- Reglas aplicadas: tuteo, cálido, sin culpa; frases ≤ 140 caracteres.
- Criterios de aceptación de los textos: las 30 frases semilla cumplen el validador.

## 4. Diseño técnico (software-architect)
```mermaid
sequenceDiagram
  participant D as Director (Paseo)
  participant T as propose-game-quotes-tool
  participant Q as game_quotes
  participant V as Panel / Dashboard
  D->>T: 7-14 frases (lunes)
  T->>Q: valida y guarda (una vez por semana)
  V->>Q: frase vigente al azar al montar
```
- Archivos a crear / modificar:
  - Migración `create_game_quotes_table`: `id`, `user_id` (nullable = semilla global), `text` (≤140), `source` (`seed`|`director`), `week_start` (nullable), timestamps; índice `(user_id, created_at)`.
  - Seeder `GameQuoteSeeder` con 30 frases semilla (`user_id` null).
  - `app/Models/Game/GameQuote.php`.
  - `app/Services/Game/DailyBuddy.php`: `pick(int $userId): ?array` → ejemplar aleatorio (`GameCompanion` del usuario, `inRandomOrder`) + frase aleatoria vigente (semilla o del usuario con `created_at >= now-60d`). Devuelve null si el juego está apagado.
  - `app/Services/Game/QuoteValidator.php` (cantidad 7-14, ≤140, palabras prohibidas compartidas con la validación de misiones; reusar la lista si ya existe en código).
  - `app/Mcp/Tools/Game/ProposeGameQuotesTool.php`, registrada en `app/Mcp/Servers/LifeTrackerServer.php`. Respuestas: `ok`, `rejected` (errores), `ya_propuestas` (ya hay frases del director con `week_start` de esta semana), `director_paused`. Bloqueo por usuario como en `ProposeGameWeekMissionsTool`.
  - `get-game-director-context-tool`: agrega `quotes_due` (bool) y `quotes_limits`.
  - `resources/views/components/game/daily-buddy.blade.php`.
  - `app/Livewire/Home/WallPanel.php` y `resources/views/livewire/home/wall-panel.blade.php`: elegir en `mount()` y guardar en propiedad pública `$buddy` (las actualizaciones automáticas no la recalculan).
  - `app/Livewire/Home/Dashboard.php` y `resources/views/livewire/home/dashboard.blade.php`: igual.
- Enfoque y patrones: mismos patrones de validación estricta y respuestas idempotentes de `ProposeGameWeekMissionsTool`/`SeasonValidator`.
- Riesgos y mitigación: costo de `inRandomOrder` es trivial (≤ cientos de filas por usuario). La frase nunca incluye datos personales: el contexto del director no se amplía con datos nuevos.
- Qué NO tocar: lógica de recompensas, refresco del panel.

## 5. Pruebas (qa-tester)
| Caso | Tipo | Nivel | Test |
|---|---|---|---|
| Panel/dashboard muestran ejemplar y frase | Feature | Alto | tests/Feature/Game/DailyBuddyTest.php |
| Refresco del panel conserva integrante | Feature | Alto | DailyBuddyTest |
| Estado vacío y juego apagado | Feature | Medio | DailyBuddyTest |
| Frases del director > 60 días no se eligen; semilla sí | Unit | Medio | tests/Unit/Game/DailyBuddyServiceTest.php |
| Herramienta MCP: ok, rejected, ya_propuestas, director_paused | Feature | Alto | tests/Feature/Game/ProposeGameQuotesToolTest.php |
| Semilla pasa el validador | Unit | Bajo | tests/Unit/Game/QuoteValidatorTest.php |
- Comando para verificar: `php artisan test tests/Feature/Game tests/Unit/Game tests/Feature/Home`
- Regresión a vigilar: carga y refresco del panel de pared; dashboard.

## 6. Documentación (tech-writer)
- Docs a actualizar: `docs/game.md` → "Integrante del día" y la herramienta en "Director y temporadas", con el paso de frases en el prompt sugerido del schedule de Paseo.

## 7. Tareas
- [x] T1 · Catálogo de frases con 30 semilla y elección de integrante + frase al azar — Resp: developer — Archivos: database/migrations/*_create_game_quotes_table.php, database/seeders/GameQuoteSeeder.php, app/Models/Game/GameQuote.php, app/Services/Game/DailyBuddy.php, app/Services/Game/QuoteValidator.php, tests/Unit/Game/DailyBuddyServiceTest.php, tests/Unit/Game/QuoteValidatorTest.php — Hecho cuando: los tests unitarios pasan y la semilla valida
- [x] T2 · La tarjeta aparece en el panel de pared y en el dashboard y no cambia con el refresco — Resp: developer — Archivos: resources/views/components/game/daily-buddy.blade.php, app/Livewire/Home/WallPanel.php, resources/views/livewire/home/wall-panel.blade.php, app/Livewire/Home/Dashboard.php, resources/views/livewire/home/dashboard.blade.php, resources/css/m3/modules/_game.css, tests/Feature/Game/DailyBuddyTest.php — Hecho cuando: los criterios 1-4 y 8 pasan
- [x] T3 · El director puede agregar frases cada lunes por MCP — Resp: developer — Archivos: app/Mcp/Tools/Game/ProposeGameQuotesTool.php, app/Mcp/Servers/LifeTrackerServer.php, app/Mcp/Tools/Game/GetGameDirectorContextTool.php, tests/Feature/Game/ProposeGameQuotesToolTest.php — Hecho cuando: los criterios 5-7 pasan
- [x] T4 · Verificar criterios de aceptación — Resp: qa-tester — Hecho cuando: los 8 criterios pasan y las suites están en verde
- [x] T5 · Actualizar documentación y prompt del director — Resp: tech-writer — Archivos: docs/game.md — Hecho cuando: docs al día

## 8. Bitácora de implementación (developer)
- 2026-11-10 — Inicio (start --force).
- T1 — Migración `game_quotes` con índices; las 30 semilla viven en `GameQuote::SEED` y las inserta la propia migración (en lugar de un seeder: así llegan a producción con `migrate`). `DailyBuddy` (ejemplar `inRandomOrder` + frase vigente, cae a SEED si la tabla está vacía) y `QuoteValidator` (reusa `MissionProposalValidator::penaltyWord`, ahora público y estático).
- T2 — `x-game.daily-buddy` (variantes wall/card, estado vacío), propiedad `buddy` elegida en `mount()` de WallPanel y Dashboard, estilos `.game-buddy*` en _game.css. Build OK.
- T3 — `ProposeGameQuotesTool` (ok/rejected/ya_propuestas/director_paused, bloqueo por usuario) registrada en LifeTrackerServer; `quotes_due` y `quotes_limits` se agregan en `SeasonManager::context` (es quien arma el contexto; la herramienta no cambió).
- T5 — docs/game.md: sección "Integrante del día", paso FRASES en el prompt del director y tabla de datos.
- Tests: tests/Feature/Game/DailyBuddyTest.php → 9 pasan (un archivo en lugar de los 4 listados en §5; cubre los mismos casos). Game+Home+Ui: solo fallas previas ajenas (NewSources, HomeDashboard, CssLayer, UiCatalog, ProtectedScreen).

- 2026-11-10 — Corrección: `x-game.daily-buddy variant="wall"` ahora es `.game-buddy-peek` (arte de 128 px con `position: absolute` en `.wall-now`, que tiene `overflow: hidden`, y globo hacia la izquierda). La línea de tiempo reserva la esquina con `padding-right`. En < 1200 px solo el arte; en < 768 px se oculta. Tests: +2 en DailyBuddyTest (ubicación y estado vacío); DailyBuddy+WallPanel+PanelFocus 31/31; Ui solo con fallas previas.
- 2026-11-10 — Columna propia: se revierte el "peek" (CSS y `.wall-now` vuelven a como estaban). La variante wall es un `article.wall-day.game-buddy-stage` dentro de `.wall-days.has-buddy` (4 días + 1,1fr); el rango del encabezado termina en el último día visible. Tests: 2 nuevos (4 días + compañero; 5 días sin ejemplar). DailyBuddy+WallPanel+PanelFocus 32/32; Ui solo con las 4 fallas previas.


- Revisión QA 2026-11-10 sobre HEAD 62351bb865ea8b1233ef8757b59feed2870a8c5e. Alex confirma funcionamiento en producción (aceptación funcional del usuario, no ejecución técnica contra producción). Suite focal PHP: 301/302; el fallo es CHG-027. Regresión adicional: 54/58, cuatro fallos existentes de shell/preview informados aparte. JS 44/44 y form-editor 3/3. Build correcto con aviso de chunk; conformance global falla por CHG-058 y deuda de CHG-054. CHG-059 concurrente excluido; no se alteró código funcional.
- Recorrido sintético juego/guardianes: 37 respuestas HTTP 200, sin errores JavaScript, guardianes visibles y 19 acciones Otro consejo respondieron. Geometría final 33/33 a 390/768/1440 tras estabilizar transiciones. Imágenes sustituidas por figuras sintéticas: no certifica arte externo. Ver logs closure-qa-game-browser-final.jsonl y closure-qa-geometry-final.json en storage/framework/testing.

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-10 — Reabierto otra vez (Alex): el personaje asomado choca con los anillos y la escena queda recargada. Decisión: sacrificar el 5.º día; "Próximos días" muestra 4 días y la 5.ª columna es el compañero. Sin ejemplares, 5 días.
- LECCIÓN(developer): en una vista Blade con `@php(...)` inline, un bloque `@php … @endphp` posterior se empareja mal ("Undefined variable"/"unexpected endforeach"); en esas vistas usar solo `@php(...)` inline · destino sugerido: docs/ARCHITECTURE.md (convenciones de vistas). Reemplaza la lección propuesta en CHG-045. → docs/ARCHITECTURE.md
- 2026-11-10 — Reabierto (Alex): en el panel, la tarjeta dentro de `.wall-now__side` agrandaba la columna, sacaba la línea de tiempo del bloque y se veía como una caja ajena. Decisión: personaje asomado en la esquina inferior derecha del bloque Ahora, recortado por el borde, con la frase en un globo; sin estado vacío en el panel.
- LECCIÓN: ninguna
- 2026-11-09 — Creado en to-do/ (Borrador, nivel M)
- 2026-11-09 — Decisiones (Alex): panel de pared y dashboard; cambia solo al recargar la página; en la franja Ahora; el director agrega 7-14 frases cada lunes y se retiran a los 60 días; frases generales sin datos personales.
- 2026-11-09 — Aprobado por el usuario (Compuerta 2)
- 2026-11-10 — Movido a in-progress/ (En desarrollo)
- 2026-11-10 — Implementación terminada → En verificación
- 2026-11-10 — Reabierto: Panel: la tarjeta rompe el bloque Ahora y no se integra; se rehace como personaje asomado en la esquina
- 2026-11-10 — Implementación terminada → En verificación
- 2026-11-10 — Reabierto: Panel: el personaje asomado choca con los anillos; pasa a columna propia en Próximos días
- 2026-11-10 — Implementación terminada → En verificación
- 2026-11-10 — Cerrado por el usuario (Compuerta 3) → done/
