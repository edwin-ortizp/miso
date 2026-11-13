# CHG-100 · Chat del compañero Pokémon reemplaza el FAB
**Estado:** En desarrollo
**Nivel:** M
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-12 · **Aprobado:** 2026-11-12 · **Cerrado:** —
**Relacionado:** CHG-099 (prototipo, archivado), CHG-097, CHG-043

## 0. En palabras simples
- Qué vas a ver distinto: en lugar del botón flotante "+" aparece tu Pokémon favorito. Al tocarlo se abre un chat con los colores de su tipo; te "escribe" un saludo y te ofrece acciones rápidas (anotar una tarea, sumar un vaso de agua, registrar tu ánimo). Si necesitas más detalle, "Más opciones" abre el formulario de siempre.
- Cómo comprobarlo tú: elegir favorito en tu perfil o en la colección, entrar a Tareas, Agua y Ánimo y registrar algo desde el chat; cambiar el perfil a "Tradicional" y ver que vuelve el botón de siempre.

## 1. Resumen
Llevar a la app real, sin pasar más por el prototipo, la idea de CHG-099: un widget de chat al estilo de los chatbots clásicos (sin IA) que reemplaza al FAB en los módulos. Incluye Pokémon favorito persistente, tema visual por tipo, animación de escritura, acciones rápidas en Tareas, Agua y Ánimo, y en el resto de módulos las mismas acciones de hoy presentadas como opciones del chat que abren sus modales. La prioridad es la calidad visual y la fluidez de las transiciones.

## 2. Producto (product-manager)
- Problema / usuario: registrar algo rápido exige abrir un modal completo; el juego de compañeros no acompaña el uso diario.
- Incluye: favorito persistente (perfil y colección), ajuste Tradicional / Con mi Pokémon, widget de chat en todos los módulos con FAB, registro rápido en Tareas (una o varias), Agua (vasos) y Ánimo (estados frecuentes y energía).
- No incluye: IA o lenguaje natural, registros rápidos en otros módulos (solo abren su modal), cambios en Android.
- Criterios de aceptación:
  - Dado un favorito y estilo Pokémon, cuando abro un módulo con FAB, entonces veo el avatar del favorito en lugar del FAB.
  - Dado el chat abierto, cuando aparece el saludo, entonces veo "escribiendo…" y puedo teclear de inmediato.
  - Dado Tareas, cuando escribo un título y confirmo, entonces la tarea se crea sin abrir modal y el compañero lo confirma en el hilo.
  - Dado un borrador, cuando toco "Más opciones", entonces el modal abre con lo escrito.
  - Dado estilo Tradicional o sin colección, entonces veo el FAB actual sin cambios.
  - Sin favorito pero con colección, se usa el compañero del día.

## 3. UX/UI
### 3.1 Experiencia (ux-designer)
- Flujo: tocar avatar → panel abre (≈220 ms) con saludo tipeándose y chips → chip rápido → mini formulario o registro de un toque → confirmación en el hilo. 1–2 pasos.
- Contenido: encabezado (avatar, nombre, tipo), hilo de mensajes, chips de acción, entrada contextual, "Más opciones".
- Estados: vacío (saludo + chips) · guardando (botón bloqueado, anti doble envío) · error (burbuja de error + campo marcado) · éxito (burbuja de confirmación, chips de nuevo).
- Información: frases de `CompanionVoice`; la entrada aparece solo al elegir una acción que la requiere.
- Accesibilidad: panel no modal, Escape cierra y devuelve foco al launcher, hilo `role="log"`, nombre accesible "Hablar con {Pokémon}", teclado completo, movimiento reducido sin typing ni morph.

### 3.2 Visual (ui-designer)
- Distribución:
```text
Escritorio                               Móvil (hoja inferior)
                ┌──────────────────────┐   ┌────────────────────────┐
                │ (◉) Pikachu · Eléctr.│   │ (◉) Pikachu        ✕   │
                │  ● ● ●  (escribiendo)│   │ ¡Hola! ¿Qué anotamos?  │
                │ ¡Hola! ¿Qué anotamos?│   │ [Una tarea][Varias]    │
                │ [Una tarea] [Varias] │   │ ┌────────────────────┐ │
                │ ┌──────────────┐ [➤] │   │ │ Título…        [➤] │ │
                │ └──────────────┘     │   │ └────────────────────┘ │
                │        Más opciones  │   │ Más opciones           │
                └──────────────────────┘   └────────────────────────┘
                                   (◉)  ← launcher con anillo de tipo
```
- Reutilizar: `resources/views/components/ui/fab.blade.php`, `components/module-actions.blade.php`, tokens `--game-type-*` de `resources/css/m3/modules/_game.css`, `SpeciesImageProvider`, `CompanionProgress::typeClass`, lógica `visualViewport` y validación del prototipo (`docs/mockup-system/mockups/learning/scripts/task-creation*.js`).
- Tokens: `--chat-accent/--chat-on-accent/--chat-surface` derivados del tipo; burbujas con radio 18 px, gradiente sutil en encabezado, tipografía Nunito existente.
- Responsive y contraste: 400 px en escritorio; hoja inferior en móvil sobre el teclado; contraste AA de texto sobre cada color de tipo (`--game-on-type-*`).

### 3.3 Escritura (ux-writer)
- Ubicación: saludo, chips, confirmaciones y errores en el hilo.
- Textos: "¡Hola! ¿Qué anotamos hoy?" · "Una tarea" / "Varias tareas" · "¡Anotada! Ya tiene su lugar." · "¡Listas! {n} tareas en tu lista." · "+{ml} ml, ¡bien hidratado!" · "Registrado: {estado}." · "Más opciones" · "Hablar con {nombre}" · Perfil: "Presentación de creación: Tradicional / Con mi Pokémon".
- Reglas de docs/UX-WRITING.md: tono cercano, frases breves, sin jerga.
- Criterios: ningún texto supera dos líneas en móvil.

## 4. Diseño técnico (software-architect)
- Archivos a crear: migración `users.favorite_companion_id` (FK nullable a `game_companions`, nullOnDelete) y `users.creation_style` (default `pokemon`); `app/Services/Game/FavoriteCompanion.php` (favorito → DailyBuddy → null); `resources/views/components/ui/companion-chat.blade.php`; `resources/js/companion-chat.js`; `resources/css/m3/patterns/_companion-chat.css`.
- Modificar: `components/ui/fab.blade.php` (elige chat o FAB), `module-actions.blade.php` (prop `quick`), `livewire/task/task-list.blade.php` + `app/Livewire/Task/TaskEditor.php` (creación rápida), `water-daily.blade.php` (`quickAdd`), vista de ánimo, perfil, ficha/colección de compañeros.
- Enfoque: Alpine + Livewire; panel teleport a body; animaciones CSS/FLIP sin bloquear el foco; la decisión chat/FAB vive en el componente para que todos los módulos lo hereden.
- Riesgos: rendimiento en móvil (solo transform/opacity), conflicto con modales existentes (z-index, foco), imágenes nulas (fallback a FAB).
- Qué NO tocar: Android, prototipo, lógica de dominio de los módulos.

## 5. Pruebas (qa-tester)
| Caso | Tipo | Nivel | Test |
|---|---|---|---|
| Favorito, fallback y sin colección | Unit | Pest | tests/Unit/FavoriteCompanionTest.php |
| Ajuste de perfil y elegir favorito | Feature | Pest | tests/Feature/CompanionChatTest.php |
| Creación rápida Tareas/Agua/Ánimo y FAB tradicional | Feature | Pest | tests/Feature/CompanionChatTest.php |
| Validación y borradores | Unit | node --test | tests/JavaScript/companion-chat.test.js |
| Abrir, escribir durante animación, Más opciones, Escape/foco | E2E | Playwright | tests/Browser/companion-chat.interaction.spec.js |
| axe, responsive, reduced motion | E2E | Playwright | projects accessibility/responsive/visual |
- Comando para verificar: `php artisan test --filter=Companion`, `node --test tests/JavaScript/companion-chat.test.js`, `npx playwright test companion-chat`.
- Regresión a vigilar: FAB en módulos no piloto, editores de tareas.

## 6. Documentación (tech-writer)
- Docs a actualizar: docs/UI-DESIGN.md (patrón chat del compañero y movimiento), catálogo UI (`resources/views/ui/catalog/entries/fab.blade.php`).

## 7. Tareas
- [x] T1 · Guardar el Pokémon favorito y elegir la presentación en el perfil — Resp: developer — Modelo: estandar — Archivos: migración, FavoriteCompanion, perfil, colección — Hecho cuando: el favorito persiste y el ajuste funciona con fallback.
- [x] T2 · Ver el chat con el tema del Pokémon en lugar del FAB — Resp: developer — Modelo: estandar — Archivos: companion-chat (blade/js/css), fab, module-actions — Hecho cuando: todos los módulos con FAB muestran el chat con animaciones y abren sus modales.
- [x] T3 · Registrar rápido en Tareas, Agua y Ánimo — Resp: developer — Modelo: estandar — Archivos: task-list, TaskEditor, water-daily, ánimo — Hecho cuando: los registros rápidos y "Más opciones" con borrador funcionan.
- [ ] T4 · Verificar accesibilidad, rendimiento y documentar — Resp: developer — Modelo: estandar — Archivos: tests, docs — Hecho cuando: pruebas de la sección 5 pasan y capturas revisadas.

## 8. Bitácora de implementación (developer)
- 2026-11-12 — Favorito y presentación guardados en los ajustes del juego (`GameSettings`: `favorite_companion_id`, `creation_style`) en lugar de columnas nuevas en `users`: no hace falta migración y sigue el patrón de ajustes existente. El selector está en Ajustes de la colección y «Hacer favorito» en la ficha del compañero.
- 2026-11-12 — `FavoriteCompanion` (scoped, se limpia con los cambios del juego): favorito → compañero estable del día → null. `x-ui.fab` decide chat o FAB; `x-ui.companion-chat` + `resources/js/companion-chat.js` + `_companion-chat.css`.
- 2026-11-12 — Rápidas: Tareas (`TaskList::quickCreateTasks`, una/varias, categoría, «Más opciones» con `prefill`), Agua (`quickAdd` 250 ml), Ánimo (`saveMood` estados prioritarios). Resto de módulos: sus acciones como chips.
- 2026-11-12 — Revisión del usuario: (1) pista sutil las 3 primeras visitas + etiqueta al pasar el mouse + badge «+»; (2) espacio superior en burbujas por `white-space: pre-line` sobre nodos en blanco → solo en el texto; (3) el chat se cerraba al enviar porque el render de Livewire reemplazaba el bloque teletransportado → `wire:ignore` + `wire:key` estable, el chat sigue abierto con «¿Algo más?»; (4) categoría con `x-ui.category-select` (combobox con búsqueda) y «¿Para cuándo?» Hoy/Mañana + hora, plegables en «Detalles»; (5) conversación por pasos: Ánimo → energía 1–5 (`MoodTracker::quickEnergy`) → cierre; Agua responde con el total del día; (6) voz del compañero en `config/companion_chat.php` y docs/UX-WRITING.md.
- 2026-11-12 — El chat seguía cerrándose: Livewire volvía a clonar el bloque teletransportado aun con wire:ignore. Ahora el chat se mueve una sola vez a <body> desde JS (registro único por página) y las copias que Livewire repinta se descartan; conserva la conexión con el componente. «Detalles» inicia plegado para dar espacio a la conversación. Prueba de navegador `tests/Browser/companion-chat.interaction.spec.js` (crear dos tareas seguidas con el chat abierto) ✔.
- 2026-11-12 — Pruebas tras la revisión: CompanionChatTest 13 ✔, companion-chat.test.js 10 ✔, build ✔.
- 2026-11-12 — Pruebas: `tests/Feature/CompanionChatTest.php` (9 ✔), `tests/JavaScript/companion-chat.test.js` (6 ✔), `npm run build` ✔. `ModuleExperienceTest` tiene 2 fallos previos ajenos a este cambio (fallan igual sin las vistas tocadas). Pendiente T4: Playwright/axe y capturas en dispositivo.

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-12 — Decisiones del usuario: favorito persistente; piloto Tareas/Agua/Ánimo; CHG-099 archivado; ajuste Tradicional/Pokémon en perfil.
- 2026-11-12 — Creado en to-do/ (Borrador, nivel M)
- 2026-11-12 — Aprobado por el usuario (Compuerta 2)
- 2026-11-12 — Movido a in-progress/ (En desarrollo)
