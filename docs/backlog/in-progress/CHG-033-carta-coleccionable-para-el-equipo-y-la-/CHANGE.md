# CHG-033 · Carta coleccionable para el equipo y la revelación
**Estado:** En verificación
**Nivel:** S
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-09 · **Aprobado:** 2026-11-09 · **Cerrado:** —
**Relacionado:** CHG-025 (tarjetas), CHG-031 (apertura de balls; este cambio se monta sobre su versión actual)

## 0. En palabras simples
- Qué vas a ver distinto:
  - En Semana, cada Pokémon del equipo es una **carta coleccionable independiente**, ya no tarjetas dentro de una sola tarjeta grande.
  - La carta es vertical (5:7) y tiene:
    - marco del color de su tipo y borde según la rareza;
    - ventana de arte grande;
    - nombre y nivel arriba;
    - estadísticas en mini barras;
    - barra de experiencia, Poder y afinidad abajo.
  - Al pasar el mouse, la carta se inclina un poco con un reflejo. Las legendarias tienen un brillo sutil permanente.
  - Cuando una ball trae una especie **nueva**, el resultado se presenta con esa misma carta, que se voltea.
- Cómo comprobarlo tú: abre Colección → Semana y pasa el mouse sobre una carta. Abre una ball que traiga una especie nueva y mira la carta voltearse. Con "reducir movimiento" activado, todo aparece sin inclinación ni volteo.

## 1. Resumen
Componente `x-game.collectible-card`, de diseño original de MiSo: no reproduce el formato, los íconos ni la distribución de cartas comerciales. Se usa en el equipo semanal, en lugar de `companion-card`, y en el resultado de la revelación cuando la especie es nueva, sobre la apertura de CHG-031. Solo cambia la presentación.

## 2. Producto (product-manager)
- Problema / usuario: el equipo se ve como una lista dentro de una tarjeta; Alex quiere cartas coleccionables individuales, más vistosas.
- Incluye:
  - Componente de carta vertical.
  - Equipo de la semana en una cuadrícula de cartas independientes (4 columnas en escritorio, 2 en tablet, 1 en móvil), con la cabecera de la semana y las misiones como secciones aparte.
  - Carta en la revelación de una especie nueva.
  - Inclinación y reflejo al pasar el mouse; brillo en las legendarias; volteo al revelar.
  - Movimiento reducido: sin efectos.
- No incluye: cambiar la colección completa ni la ficha (CHG-032).
- Criterios de aceptación:
  - Cada integrante del equipo es un elemento independiente con su propio marco; ya no hay una tarjeta contenedora del equipo.
  - La carta muestra nombre, nivel, número, rareza con texto, arte, tipos, hasta 3 estadísticas (PS, Ataque, Velocidad), barra de xp, Poder y afinidad.
  - La carta es un enlace a la ficha, con nombre accesible completo.
  - Una especie nueva en la revelación se muestra como carta; una repetida conserva la presentación de CHG-031.
  - Con `prefers-reduced-motion`, sin inclinación, brillo ni volteo.
  - Sin deuda visual nueva (UiConformance).

## 3. UX/UI
### 3.1 Experiencia (ux-designer)
- Plantilla: pestaña Semana; la cuadrícula de cartas reemplaza la tarjeta del equipo.
- Accesibilidad: el efecto de inclinación es decorativo, el foco es visible y el enlace tiene nombre completo.

```
Semana del 9 al 15 nov
Tu equipo
╭──────────────────╮ ╭──────────────────╮ ╭──────────────────╮ ╭──────────────────╮
│ Nombre      Nv 7 │ │ Nombre      Nv 3 │ │ Nombre     Nv 12 │ │ Nombre      Nv 1 │
│ #004 · ★ Rara    │ │ #007 · Común     │ │ #001 · ◆ Poco c. │ │ #025 · Común     │
│╭────────────────╮│ │╭────────────────╮│ │╭────────────────╮│ │╭────────────────╮│
││   [ ARTE ]     ││ ││   [ ARTE ]     ││ ││   [ ARTE ]     ││ ││   [ ARTE ]     ││
│╰────────────────╯│ │╰────────────────╯│ │╰────────────────╯│ │╰────────────────╯│
│ fuego · volador  │ │ agua             │ │ planta · veneno  │ │ eléctrico        │
│ PS  ▇▇▇▇▁  78    │ │ PS  ▇▇▇▁▁  44    │ │ …                │ │ …                │
│ ATQ ▇▇▇▇▇  84    │ │ ATQ ▇▇▇▁▁  48    │ │                  │ │                  │
│ VEL ▇▇▇▁▁  60    │ │ VEL ▇▇▁▁▁  43    │ │                  │ │                  │
│ [█████░░░] xp    │ │ [██░░░░░░] xp    │ │                  │ │                  │
│ ⚡ Poder 412     │ │ ⚡ Poder 330     │ │                  │ │                  │
│ ◆ Afinidad Hogar │ │ ◆ Afinidad Salud │ │                  │ │                  │
╰──────────────────╯ ╰──────────────────╯ ╰──────────────────╯ ╰──────────────────╯
Misiones de la semana (sección aparte)
```

### 3.2 Visual (ui-designer)
- Componentes: el nuevo `x-game.collectible-card`, más `x-game.rarity-tag`, `x-ui.progress` y las variables `--game-type-*` / `--game-rarity-*` de CHG-025.
- Marco: borde exterior con el color de la rareza (grosor 3 px; legendaria con brillo animado), interior con el color sólido del tipo y la ventana de arte en la superficie del sistema.
- Efectos: inclinación con `transform: perspective rotateX/Y` limitada a ±6° (Alpine, puntero), reflejo con un pseudo-elemento de opacidad baja y volteo con `rotateY` 180° → 0°. Solo `transform` y `opacity`.

### 3.3 Escritura (ux-writer)
- Textos: "Tu equipo", "Nv {n}", "Poder {n}", "Afinidad {ámbito}"; estadísticas "PS", "ATQ", "VEL"; nombre accesible: "{nombre}, nivel {n}, poder {p}, afinidad {a}".

## 4. Diseño técnico (software-architect)
- Archivos: `resources/views/components/game/collectible-card.blade.php`, `resources/views/livewire/game/game-week.blade.php`, `resources/views/livewire/game/game-balls.blade.php` (resultado de especie nueva), `resources/css/m3/modules/_game.css` y `tests/Feature/Game/GameVisualTest.php`.
- Enfoque: `companion-card` queda disponible, pero Semana usa la nueva carta. En la revelación solo se cambia el bloque del resultado nuevo, respetando la estructura y los timers de CHG-031 (se lee la vista vigente antes de editar).
- Riesgos: chocar con CHG-031 o CHG-032 (en verificación). Mitigación: no se toca su lógica, solo el bloque del resultado nuevo.
- Qué NO tocar: la apertura, sus timers, la ficha ni las reglas.

## 5. Pruebas (qa-tester)
| Caso | Tipo | Nivel | Test |
|---|---|---|---|
| Equipo: una carta independiente por integrante con todos los datos | Feature | HTTP | GameVisualTest |
| Revelación nueva usa la carta; repetida no | Feature | Livewire | GameVisualTest |
| Sin deuda visual | Feature | Contrato | UiConformanceTest |
- Comando: `php artisan test tests/Feature/Game`

## 6. Documentación (tech-writer)
- `docs/game.md`, sección Presentación: carta coleccionable.

## 7. Tareas
- [x] T1 · Carta coleccionable en el equipo de la semana — Resp: developer — Archivos: resources/views/components/game/collectible-card.blade.php, resources/views/livewire/game/game-week.blade.php, resources/css/m3/modules/_game.css, tests/Feature/Game/GameVisualTest.php — Hecho cuando: los tests de equipo pasan y no hay deuda visual
- [x] T2 · Carta que se voltea al revelar una especie nueva — Resp: developer — Archivos: resources/views/livewire/game/game-balls.blade.php, resources/css/m3/modules/_game.css, tests/Feature/Game/GameVisualTest.php, docs/game.md — Hecho cuando: el test de revelación pasa y el juego sigue en verde
- [ ] T3 · Verificar criterios de aceptación — Resp: qa-tester — Hecho cuando: todos pasan

## 8. Bitácora de implementación (developer)
- 2026-11-09 — Inicio con `start --force`. En paralelo, otro agente tenía CHG-029/031/032 en verificación sobre las mismas vistas; leí la versión vigente antes de editar.
- 2026-11-09 — T1: componente `x-game.collectible-card`.
  - La inclinación se aplica con `style.setProperty` desde Alpine, solo con mouse, para no dejar estilos en línea en el HTML.
  - Semana: el equipo pasa a ser una cuadrícula `game-collection-cards` de cartas independientes (4/3/2/1 columnas), fuera de la tarjeta contenedora.
  - CSS con reflejo, brillo de legendarias, volteo y alternativa para movimiento reducido.
- 2026-11-09 — T2: en la revelación (versión de CHG-031), solo el bloque de especie nueva (`is_new` sin evolución) usa la carta con `flip`. La repetida y la evolución quedan como estaban. El Poder se calcula en un `@php` de la vista.
- 2026-11-09 — Pruebas: GameVisualTest con 2 casos nuevos (cartas independientes; volteo solo en especie nueva) y su prueba de tarjeta adaptada. Total del juego: 152 en verde con `APP_URL=http://localhost`. UiConformance en verde; las 2 fallas de CssLayer ya existían. `npm run build` ejecutado. docs/game.md actualizado.
- ⚠️ Hallazgo fuera de alcance: `.env` tiene ahora `APP_URL=http://localhost:8080/miso/public` (instalación local bajo subruta) y `phpunit.xml` no fija `APP_URL`. Con eso, **toda** prueba HTTP o Livewire falla con 404 o "Invalid Livewire snapshot". Propuesta: añadir `<env name="APP_URL" value="http://localhost"/>` a phpunit.xml (requiere aprobación de Alex).

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-09 — Creado en to-do/ (Borrador, nivel S)
- 2026-11-09 — Decisiones (Alex): carta vertical con brillo; también en la revelación de una ball nueva. Diseño original (no imita cartas comerciales).
- 2026-11-09 — Aprobado por el usuario (Compuerta 2)
- 2026-11-09 — Movido a in-progress/ (En desarrollo)
- LECCIÓN(developer): las pruebas deben fijar APP_URL en phpunit.xml; si dependen del .env local, un cambio de instalación (subruta) rompe toda la suite con 404 — evidencia: 51 fallas tras cambiar APP_URL, 0 al fijarlo · destino sugerido: docs/TESTING.md (cuando exista)
- 2026-11-09 — Implementación terminada → En verificación
