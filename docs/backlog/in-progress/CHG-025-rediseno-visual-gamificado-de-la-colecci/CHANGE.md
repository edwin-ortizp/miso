# CHG-025 · Rediseño visual gamificado de la Colección
**Estado:** En verificación
**Nivel:** M
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-09 · **Aprobado:** 2026-11-09 · **Cerrado:** —
**Relacionado:** CHG-024 (va antes; este reutiliza su estructura), CHG-019, CHG-020, CHG-021

## 0. En palabras simples
- Qué vas a ver distinto: el juego se ve como juego.
  - **Equipo:** cada integrante es una tarjeta con su foto grande, nivel, **Poder**, barra de experiencia, tipos con color y afinidad.
  - **Colección:** cada tarjeta tiene un marco según su rareza y el color de su tipo.
  - **Ficha:** una cabecera con el color del tipo y la imagen grande, estadísticas en barras, la cadena evolutiva con imágenes y un historial como línea de tiempo.
  - **Balls:** las pendientes se ven como balls que puedes tocar. Al abrirlas, la ball tiembla, se abre con un destello y aparece la criatura, con la cinta "¡Nueva!" o la experiencia ganada.
- Cómo comprobarlo tú: abre Semana y mira las tarjetas del equipo. Abre una ficha. Abre una ball y mira la secuencia completa. Activa "reducir movimiento" en tu sistema: todo aparece sin animaciones bruscas.

## 1. Resumen
Rediseño visual sobre la estructura de CHG-024, sin cambiar las reglas del juego. Agrega el "Poder" de cada ejemplar, calculado desde sus estadísticas base y su nivel, y una paleta por tipo y rareza como variables CSS del módulo. El diseño es original de MiSo: no imita cartas, logos ni marcos de ningún juego comercial.

## 2. Producto (product-manager)
- Problema / usuario: la interfaz funciona, pero se siente plana y poco motivadora.
- Incluye:
  - **Poder** = `round(total_estadísticas_base × (1 + (nivel − 1) × 0,05))`, factor configurable. Si no hay estadísticas, se usa 300 como base. Se muestra en las tarjetas del equipo, la ficha y la colección (solo en las descubiertas).
  - **Paleta por tipo:** cada tipo del catálogo tiene un color (en español, como trae el JSON: fuego, agua, planta…). Un tipo sin color usa el neutro.
  - **Marcos de rareza:** común neutro, poco común verde, rara azul, legendaria dorado. Se distinguen también por un ícono y un texto, no solo por el color.
  - **Tarjeta de equipo:** imagen de 120 px, nombre, nivel, Poder, barra de xp hacia el siguiente nivel, tipos y afinidad.
  - **Tarjeta de colección:** marco de rareza, color del tipo principal y número; las no descubiertas, en silueta y sin color.
  - **Ficha:**
    - Cabecera de color sólido del tipo principal con la imagen de 200 px.
    - Poder y nivel destacados.
    - Estadísticas en barras (escala de 0 a 255).
    - Cadena evolutiva con miniaturas.
    - Historial como línea de tiempo con íconos por tipo de evento.
  - **Balls:** lista en cuadrícula de balls tocables, con origen y fecha debajo. Al abrir:
    1. temblor (3 sacudidas, unos 900 ms);
    2. destello;
    3. aparición con el color de la rareza.
    - Lleva la cinta "¡Nueva!" o "+{xp} xp". Si hubo evolución, va la secuencia de CHG-020.
    - El resultado ya está guardado antes de animar, como siempre.
  - **Aviso global (CHG-023):** usa la misma ball rediseñada.
- No incluye: sonidos, efectos de partículas pesados, ni reglas nuevas.
- Criterios de aceptación:
  - Las tarjetas del equipo muestran imagen, nombre, nivel, Poder, barra de xp, tipos y afinidad.
  - El Poder sube con el nivel y nunca baja.
  - Rareza y tipo se distinguen sin depender solo del color.
  - La apertura sigue la secuencia temblor → destello → aparición; con movimiento reducido, solo un fundido.
  - Si la animación falla o recargas a mitad, la ball ya está abierta y su resultado se ve en la colección.
  - Contraste AA en textos sobre los colores de tipo.
  - `UiConformanceTest` y `CssLayerContractTest` sin deuda visual nueva.

## 3. UX/UI
### 3.1 Experiencia (ux-designer)
- Plantilla: la misma de CHG-024 (módulo con pestañas).
- Flujo: sin cambios; solo cambia la presentación.
- Estados: los mismos; los vacíos llevan una ilustración de ball en línea.
- Accesibilidad: `prefers-reduced-motion`; la rareza va con texto; las tarjetas son botones o enlaces con nombre accesible completo.

```
Semana · Tu equipo
┌────────────────┐ ┌────────────────┐ ┌────────────────┐
│ ▓▓ fuego ▓▓▓▓▓ │ │ ▓▓ agua ▓▓▓▓▓▓ │ │ ▓ planta ▓▓▓▓▓ │
│    [ IMG 120 ] │ │    [ IMG 120 ] │ │    [ IMG 120 ] │
│ Nombre    Nv 7 │ │ Nombre    Nv 3 │ │ Nombre   Nv 12 │
│ ⚡ Poder 412   │ │ ⚡ Poder 330   │ │ ⚡ Poder 610   │
│ [█████░░░] xp  │ │ [██░░░░░░] xp  │ │ [███████░] xp  │
│ ◆ Afinidad Hogar│ │ ◆ Afinidad Salud│ │ ◆ Afin. Hábitos│
└────────────────┘ └────────────────┘ └────────────────┘

Ficha
┌──────────────────────────────────────────┐
│████████ color sólido del tipo ███████████│
│ #006 Nombre          [ IMAGEN 200 ]      │
│ ★ Rara · fuego · volador                 │
├──────────────────────────────────────────┤
│ ⚡ Poder 612        Nivel 12  [████░░] xp │
│ Bio…                                      │
│ PS        ███████░░░  78                  │
│ Ataque    ████████░░  84                  │
│ …                                         │
│ Evoluciones  [img]#004 → [img]#005 → [img]#006 │
│ Línea de tiempo                           │
│ ● 9 nov  Lo conociste · Pagar arriendo    │
│ ▲ 12 nov Subió al nivel 2                 │
└──────────────────────────────────────────┘

Balls (3 por abrir)
  (◓)        (◓)        (◓)
 Tarea      Hábitos    Misión
 hoy        ayer       lun
Apertura: (◓)~~ tiembla ~~ ✦ destello ✦ → [IMG] «¡Nueva!»
```

### 3.2 Visual (ui-designer)
- Tokens: la paleta de tipo y rareza va como variables CSS del módulo (`--game-type-fuego`, `--game-rarity-rare`…) en `_game.css`, con colores sólidos y sin degradados. El texto sobre el color usa `on-*` calculado para AA, con texto oscuro o claro por tipo definido en la tabla.
- Componentes: `card`, `progress`, `badge`, `dialog`, `list`; la ball es un componente nuevo `resources/views/components/game/ball.blade.php`, en CSS puro y con tamaños sm/md/lg.
- Responsive: tarjetas de equipo en 3 columnas en escritorio, 2 en tablet y 1 en móvil; ficha a pantalla completa en móvil.
- Rendimiento: animaciones solo con `transform` y `opacity`; imágenes con `loading="lazy"` y tamaño fijo.

### 3.3 Escritura (ux-writer)
- Textos: "Poder", "Nv {n}", "¡Nueva!", "+{xp} xp", "Común", "Poco común", "Rara", "Legendaria", "Línea de tiempo", "Lo conociste", "Subió al nivel {n}", "Evolucionó a {nombre}", "Ganó {xp} xp", "Afinidad {ámbito}", "Toca una ball para abrirla".
- Reglas: tuteo, etiquetas breves.
- Criterios: las rarezas siempre llevan texto, no solo color.

## 4. Diseño técnico (software-architect)
- Archivos:
  - `config/game.php`: `power_level_factor` (0,05), `power_default_base` (300), `type_palette` (tipo → clave de color) y `rarity_labels`.
  - `app/Services/Game/CompanionProgress.php`: `power(GameCompanion)`.
  - Componentes: `resources/views/components/game/ball.blade.php`, `resources/views/components/game/species-card.blade.php` y `resources/views/components/game/companion-card.blade.php`.
  - Vistas: `resources/views/livewire/game/{game-balls,game-collection,game-week,ball-notifier}.blade.php` y `resources/views/livewire/game/partials/species-sheet.blade.php`.
  - `resources/css/m3/modules/_game.css` y `resources/js/game-reveal.js` (secuencia con Alpine; importado en `resources/js/app.js`).
  - Livewire: `app/Livewire/Game/GameWeekView.php` y `app/Livewire/Game/GameCollection.php` (pasan el Poder y la xp del nivel).
  - Pruebas: `tests/Feature/Game/GameVisualTest.php`.
- Enfoque: la secuencia de apertura es solo de presentación: `open()` sigue guardando antes de animar. Alpine recibe el resultado y aplica las clases en orden. Con movimiento reducido se salta al estado final.
- Riesgos: las reglas de conformidad de UI (colores directos). Mitigación: todos los colores en variables del módulo, verificadas con `UiConformanceTest` y `CssLayerContractTest`.
- Qué NO tocar: reglas, servicios de otorgamiento o progreso (salvo el cálculo de solo lectura `power`).

## 5. Pruebas (qa-tester)
| Caso | Tipo | Nivel | Test |
|---|---|---|---|
| Poder: fórmula, sin stats, sube con el nivel | Unit/Feature | Servicio | GameVisualTest |
| Tarjeta de equipo muestra nivel, Poder, xp, tipos y afinidad | Feature | Livewire | GameVisualTest |
| Rareza con texto y clase de marco | Feature | Livewire | GameVisualTest |
| Ficha: barras de estadísticas y cadena con imágenes | Feature | Livewire | GameVisualTest |
| Apertura: resultado guardado antes de la animación (recarga) | Feature | Livewire | GameUiTest (regresión) |
| Sin deuda visual nueva | Feature | Contrato | UiConformanceTest, CssLayerContractTest |
- Comando: `php artisan test tests/Feature/Game tests/Unit/Game`
- Regresión: `php artisan test --filter="UiConformance|CssLayer"`. Las fallas que ya existían en comidas no cuentan.

## 6. Documentación (tech-writer)
- `docs/game.md`: Poder, paleta por tipo (cómo agregar un tipo nuevo) y rarezas.

## 7. Tareas
- [x] T1 · Poder de cada ejemplar y paleta de tipos y rarezas — Resp: developer — Archivos: config/game.php, app/Services/Game/CompanionProgress.php, resources/css/m3/modules/_game.css, tests/Feature/Game/GameVisualTest.php — Hecho cuando: los tests de Poder pasan
- [x] T2 · Tarjetas de equipo y de colección con foto grande, rareza y tipo — Resp: developer — Archivos: resources/views/components/game, resources/views/livewire/game/game-week.blade.php, resources/views/livewire/game/game-collection.blade.php, app/Livewire/Game/GameWeekView.php, app/Livewire/Game/GameCollection.php, tests/Feature/Game/GameVisualTest.php — Hecho cuando: los tests de tarjetas pasan y no hay deuda visual nueva
- [x] T3 · Ficha con cabecera de color, barras, cadena con imágenes y línea de tiempo — Resp: developer — Archivos: resources/views/livewire/game/partials/species-sheet.blade.php, resources/css/m3/modules/_game.css, tests/Feature/Game/GameVisualTest.php — Hecho cuando: los tests de ficha pasan
- [x] T4 · Balls tocables y apertura con temblor, destello y aparición — Resp: developer — Archivos: resources/views/components/game/ball.blade.php, resources/views/livewire/game/game-balls.blade.php, resources/views/livewire/game/ball-notifier.blade.php, resources/js/game-reveal.js, resources/js/app.js, resources/css/m3/modules/_game.css — Hecho cuando: GameUiTest sigue en verde y la secuencia se ve en el navegador
- [ ] T5 · Verificar criterios de aceptación — Resp: qa-tester — Hecho cuando: todos pasan
- [x] T6 · Actualizar docs/game.md — Resp: tech-writer — Archivos: docs/game.md — Hecho cuando: docs al día

## 8. Bitácora de implementación (developer)
- 2026-11-09 — Inicio con `start --force` (ya había otros CHG En desarrollo; trabajo aprobado por Alex).
- 2026-11-09 — T1: Poder y paleta.
  - `CompanionProgress::power()` y `CompanionProgress::typeClass()` (slug sin tildes; un tipo desconocido usa el neutro).
  - En config: `power_level_factor`, `power_default_base` y `type_palette` con los 18 tipos del catálogo de Alex.
  - `_game.css` reescrito por completo como sistema del módulo:
    - Variables `--game-type-*` / `--game-on-type-*` y `--game-rarity-*`.
    - La ball con capas sólidas (`::before`/`::after`) en lugar del degradado anterior.
    - Movimiento reducido en todas las animaciones.
- 2026-11-09 — T2: componentes `x-game.ball`, `x-game.rarity-tag`, `x-game.species-card` y `x-game.companion-card`.
  - Tarjeta de equipo: banda de tipo, imagen de 120 px, nombre, nivel, Poder, barra de xp, tipos con color, afinidad y marco de rareza.
  - Tarjeta de colección: marco de rareza, franja de tipo y rareza con texto; las ocultas van en silueta, con borde punteado y sin color.
  - `x-game.rarity-tag` no estaba listado en §4: se agregó para no repetir la rareza con texto e ícono en tres lugares.
- 2026-11-09 — T3: ficha con cabecera de color sólido del tipo, imagen de 200 px en círculo, rareza y tipos, Poder y nivel destacados, estadísticas en barras (escala 0–255, con la propiedad dinámica permitida `--md-progress-value`), cadena evolutiva con miniaturas y línea de tiempo con íconos por tipo de evento. `GameCollection` pasa el `power`.
- 2026-11-09 — T4: balls en cuadrícula tocable (Tarea / Hábitos / Misión, origen y fecha); el aviso global usa la misma ball.
  - Apertura: la ball tiembla 900 ms, luego destello del color de la rareza y luego aparición con la cinta "¡Nueva!", "+{xp} xp" o "¡Evolución!".
  - DESVIACIÓN menor: la secuencia va en Alpine dentro de `game-balls.blade.php`, en lugar de `resources/js/game-reveal.js`; son tres pasos y no justifican un módulo JS. No se tocó `resources/js/app.js`.
  - El resultado se guarda antes de animar, como antes.
- 2026-11-09 — Pruebas:
  - GameVisualTest: 5 pasan (fórmula del Poder, clases de tipo, tarjeta de equipo, tarjetas de colección y ficha).
  - Textos actualizados en GameUiTest ("¡Nueva!"), GameWeekViewTest ("Afinidad") y SpeciesSheetTest.
  - Total del juego: 106 en verde. UiConformance en verde; las 2 fallas de CssLayer ya existían (comidas y text-fields).
  - `npm run build` ejecutado.
- 2026-11-09 — T6: docs/game.md, sección Presentación.
- Pendiente para QA: revisar en el navegador el contraste de los 18 colores de tipo con sus textos, y la secuencia de apertura en móvil.

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-09 — Creado en to-do/ (Borrador, nivel M)
- 2026-11-09 — Decisión (Alex): el rediseño va directo en la app, sin prototipo previo; se ajusta sobre lo real.
- 2026-11-09 — Decisión de diseño: estilo original de MiSo (sin imitar cartas, logos ni marcos de juegos comerciales); la métrica se llama "Poder".
- 2026-11-09 — Aprobado por el usuario (Compuerta 2)
- 2026-11-09 — Movido a in-progress/ (En desarrollo)
- LECCIÓN: ninguna
- 2026-11-09 — Implementación terminada → En verificación
