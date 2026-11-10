# CHG-038 · Avisos del juego como tarjetas de logro arriba a la derecha
**Estado:** En verificación
**Nivel:** S
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-09 · **Aprobado:** 2026-11-09 · **Cerrado:** —
**Relacionado:** CHG-034

## 0. En palabras simples
- Qué vas a ver distinto: los avisos del juego aparecen **arriba a la derecha**, debajo de la barra superior, como tarjetas de logro:
  - un color según el tipo: ball roja, experiencia, nivel, misión y meta dorada;
  - un ícono grande animado;
  - el título en mayúsculas pequeñas;
  - el botón "Abrir ball";
  - una barra de tiempo, que se pausa al pasar el mouse.
  - Ya no quedan tapados por barras inferiores como la Debug Bar.
- Cómo comprobarlo tú: con la Debug Bar activa, completa los hábitos de un momento del día. Los avisos entran desde la derecha, arriba, y se cierran solos a los 5 s, salvo que tengas el mouse encima.

## 1. Resumen
Rediseño visual y de ubicación de los avisos de CHG-034. No cambia la lógica (`GameFeed`, `GameNotifier`): solo la vista del notificador y el CSS de la pila y de cada aviso. Aprobado por Alex en el plan, con la opción "arriba a la derecha, como tarjetas de logro".

## 2. Producto (product-manager)
- Problema / usuario: los avisos se ven escondidos, poco de juego y quedan bajo la Debug Bar.
- Incluye: ubicación arriba a la derecha (en móvil, a todo el ancho bajo la barra superior), diseño de logro por tipo, barra de tiempo con pausa, entrada y salida animadas y un brillo en los momentos importantes.
- No incluye: cambios en qué se anuncia ni en las celebraciones.
- Criterios de aceptación:
  - Los avisos aparecen bajo la barra superior a la derecha y nunca los tapan barras inferiores.
  - Cada tipo tiene su color e ícono; la ball tiene el botón "Abrir ball".
  - Se cierran a los 5 s; pasar el mouse o enfocar pausa el cierre y la barra.
  - Con movimiento reducido, sin animaciones.
  - Siguen con `role="status"` y sin contenedor con transform.

## 3. UX/UI
### 3.1 Experiencia (ux-designer)
- Plantilla: capa global del layout (como CHG-034).

```
                         ╭───────────────────────────╮
                         │ (◓)  ¡GANASTE UNA BALL!    │
                         │ ↻    Hábitos de la mañana  │
                         │      [ Abrir ball ]     ✕  │
                         │ ▔▔▔▔▔▔▔▔▔▔▔▔▔▔▔▔░░░░░░░░░  │
                         ╰───────────────────────────╯
                         ╭───────────────────────────╮
                         │ ✦  TU EQUIPO GANÓ XP       │
                         │    Magmar +9 ◆ · Tangela +3│
                         ╰───────────────────────────╯
```

### 3.2 Visual (ui-designer)
- Superficie `surface-container-high`, borde izquierdo de 6 px y círculo del ícono del color del tipo, sombra elevada y ancho de 22 rem.
- Animaciones solo con transform/opacity/background-position.

### 3.3 Escritura (ux-writer)
- Textos: "Abrir ball"; el resto de títulos no cambia ("¡Ganaste una ball!", "Tu equipo ganó experiencia"…), que se muestran en versalitas por CSS.

## 4. Diseño técnico (software-architect)
- Archivos: `resources/views/livewire/game/game-notifier.blade.php`, `resources/css/m3/modules/_game.css` (bloque de avisos), `tests/Feature/Game/GameNotifierTest.php` y `docs/game.md`.
- Qué NO tocar: `GameFeed`, la lógica de `GameNotifier` ni las celebraciones.

## 5. Pruebas (qa-tester)
| Caso | Tipo | Nivel | Test |
|---|---|---|---|
| Aviso con "Abrir ball", pila sin transform | Feature | Livewire/HTTP | GameNotifierTest |
- Comando: `php artisan test tests/Feature/Game`

## 6. Documentación (tech-writer)
- `docs/game.md`: ubicación y estilo de los avisos.

## 7. Tareas
- [x] T1 · Avisos arriba a la derecha con diseño de logro, barra de tiempo y pausa — Resp: developer — Archivos: resources/views/livewire/game/game-notifier.blade.php, resources/css/m3/modules/_game.css, tests/Feature/Game/GameNotifierTest.php, docs/game.md — Hecho cuando: los tests pasan y el build compila
- [ ] T2 · Verificar criterios de aceptación — Resp: qa-tester — Hecho cuando: todos pasan

## 8. Bitácora de implementación (developer)
- 2026-11-09 — Inicio con `start --force`.
- 2026-11-09 — T1:
  - Ubicación: pila `game-toasts` fija arriba a la derecha (`top: --lt-topbar-height + 12px`, ancho de 22 rem; en móvil, a todo el ancho), sin transform.
  - Aviso: tarjeta de logro en `surface-container-high`, con borde izquierdo y halo del color del tipo (`--game-toast-color`). Lleva un círculo con el ícono o la ball de 36 px (animación de giro y rebote), título en versalitas del color del tipo y el botón tonal "Abrir ball".
  - Barra de tiempo `::after` con `scaleX(--game-toast-left)`, que actualiza Alpine cada 100 ms. Pasar el mouse o enfocar pausa el cierre y la barra.
  - Brillo de entrada en ball, meta, evolución y misión. Entrada deslizándose desde la derecha. Con movimiento reducido no hay animaciones.
  - La lógica del notificador no cambió.
- 2026-11-09 — Pruebas: GameNotifierTest verifica el botón "Abrir ball" y la clase por tipo. Juego: 163 en verde; UiConformance en verde; build OK. docs/game.md actualizado.
- Pendiente: revisión en el navegador con la Debug Bar activa y en móvil.

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-09 — Creado en to-do/ (Borrador, nivel S)
- 2026-11-09 — Decisión (Alex): arriba a la derecha, como tarjetas de logro (captura con avisos tapados por la Debug Bar).
- 2026-11-09 — Aprobado por el usuario (Compuerta 2)
- 2026-11-09 — Movido a in-progress/ (En desarrollo)
- LECCIÓN: ninguna
- 2026-11-09 — Implementación terminada → En verificación
