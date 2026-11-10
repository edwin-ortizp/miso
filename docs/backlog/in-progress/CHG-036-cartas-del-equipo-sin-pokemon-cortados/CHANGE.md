# CHG-036 · Cartas del equipo sin Pokémon cortados
**Estado:** En verificación
**Nivel:** S
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-09 · **Aprobado:** 2026-11-09 · **Cerrado:** —
**Relacionado:** CHG-033

## 0. En palabras simples
- Qué vas a ver distinto: en las cartas del equipo, cada Pokémon se ve completo y grande, sobresaliendo un poco por arriba de su ventana (efecto 3D), pero nunca tapado por los tipos ni por las estadísticas. Al pasar el mouse, sube un poquito más.
- Cómo comprobarlo tú: abre Colección → Semana. Magmar, Tangela, Fennekin y Ralts se ven enteros, apoyados en el fondo de su ventana y asomándose sobre la banda de color.

## 1. Resumen
La carta tenía altura fija (5:7) y la fila del arte quedaba en unos 90 px. La imagen se desbordaba hacia abajo y la tapaban los bloques opacos. Ahora la carta crece según su contenido, la ventana de arte mide 120 px y el Pokémon (150 px) se apoya en su base y sobresale solo hacia arriba. Es un cambio solo de CSS, aprobado por Alex en el plan.

## 2. Producto (product-manager)
- Problema / usuario: varios Pokémon se ven cortados en las cartas.
- Incluye: el arte se ve completo con desborde controlado hacia arriba, una sombra suave y un leve realce al pasar el mouse.
- No incluye: cambios de datos ni de la apertura (CHG-031).
- Criterios de aceptación:
  - Ningún Pokémon queda tapado por los tipos, las estadísticas o el pie.
  - El Pokémon sobresale solo hacia arriba y no tapa el nombre ni la rareza.
  - La inclinación, el reflejo y el volteo siguen funcionando; con movimiento reducido no hay animaciones.

## 3. UX/UI
### 3.1 Experiencia (ux-designer)
- Plantilla: pestaña Semana (sin cambios de flujo).

```
Antes                         Después
╭──────────────╮              ╭──────────────╮
│ Tangela  Nv1 │              │ Tangela  Nv1 │
│ #114 · ◆     │              │ #114 · ◆     │
│╭────────────╮│              │    ▲▲▲▲▲▲    │  ← sobresale por arriba
││   ▲▲▲▲▲▲   ││              │╭───████████─╮│
│╰───██████───╯│              ││  ████████  ││
│ Planta ████  │ ← cortado    │╰────────────╯│
│ PS ▇▇ ███    │              │ Planta       │
╰──────────────╯              │ PS ▇▇▇▁  65  │
                              ╰──────────────╯
```

### 3.2 Visual (ui-designer)
- Ventana de 120 px; imagen de 150 px anclada abajo y centrada, con `drop-shadow` suave. La carta usa `overflow: visible` y el reflejo se recorta en su propio pseudo-elemento.

### 3.3 Escritura (ux-writer)
No aplica: no cambian textos.

## 4. Diseño técnico (software-architect)
- Archivos: `resources/css/m3/modules/_game.css` (bloque de la carta coleccionable), `tests/Feature/Game/GameVisualTest.php` y `docs/game.md`.
- Qué NO tocar: la lógica, la apertura de CHG-031 ni las demás reglas de `_game.css`.

## 5. Pruebas (qa-tester)
| Caso | Tipo | Nivel | Test |
|---|---|---|---|
| La imagen va dentro de la ventana de arte | Feature | HTTP | GameVisualTest |
| Sin deuda visual | Feature | Contrato | UiConformanceTest |
- Comando: `php artisan test tests/Feature/Game`
- Revisión visual a 1440, 1024, 768 y 390 px.

## 6. Documentación (tech-writer)
- `docs/game.md`, Presentación: arte que sobresale por arriba.

## 7. Tareas
- [x] T1 · Pokémon completo y sobresaliendo solo por arriba en la carta — Resp: developer — Archivos: resources/css/m3/modules/_game.css, tests/Feature/Game/GameVisualTest.php, docs/game.md — Hecho cuando: los tests pasan, el build compila y la revisión visual no muestra cortes
- [ ] T2 · Verificar criterios de aceptación — Resp: qa-tester — Hecho cuando: todos pasan

## 8. Bitácora de implementación (developer)
- 2026-11-09 — Inicio con `start --force`; el CLI no pudo reescribir `docs/TEAM_PLAN.md` en ese momento (archivo bloqueado por otro proceso) y la sincronización se repitió al pasar a verificación.
- 2026-11-09 — T1: en `.game-collectible` se quitaron `aspect-ratio` y el recorte (`overflow: visible`), y `__inner` usa filas naturales.
  - Ventana `__art` de 120 px con `margin-top: 2.25rem`.
  - La imagen mide 150 px, va en posición absoluta y se apoya en la base (`bottom: 6px`, centrada), con `drop-shadow`. Sube 4 px y escala ×1,04 al pasar el mouse.
  - El reflejo se recorta en su propio `::after` (`border-radius: inherit`) y se anima con `background-position` en lugar de `transform`, para no salirse de la carta.
  - Con movimiento reducido no hay efectos.
- 2026-11-09 — Pruebas: GameVisualTest verifica que el arte va dentro de su ventana. Juego: 163 en verde; UiConformance en verde; build OK.
- Pendiente: revisión visual en el navegador a 1440, 1024, 768 y 390 px (sin sesión de navegador en este entorno).

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-09 — Creado en to-do/ (Borrador, nivel S)
- 2026-11-09 — Alex aprobó el plan (captura con Tangela, Fennekin y Ralts cortados): conservar el desborde 3D, pero solo hacia arriba.
- 2026-11-09 — Aprobado por el usuario (Compuerta 2)
- 2026-11-09 — Movido a in-progress/ (En desarrollo)
- LECCIÓN: ninguna
- 2026-11-09 — Implementación terminada → En verificación
