# CHG-100 · Chat del compañero Pokémon reemplaza el FAB
**Estado:** En verificación
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
- Persistencia final (decisión registrada en §8): `favorite_companion_id` y `creation_style` en `GameSettings`, sin columnas nuevas ni migración. Piezas creadas: `app/Services/Game/FavoriteCompanion.php` (favorito → DailyBuddy → null); `resources/views/components/ui/companion-chat.blade.php`; `resources/js/companion-chat.js`; `resources/css/m3/patterns/_companion-chat.css`.
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
- [x] T4 · Verificar accesibilidad, rendimiento y documentar — Resp: developer — Modelo: estandar — Archivos: tests, docs — Hecho cuando: pruebas de la sección 5 pasan y capturas revisadas.

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

## 10. Handoff original · flujos guiados en Tareas (aprobado 2026-11-12, implementado 2026-11-13)

**Estado al entregar:** T1–T3 hechas; chat abierto entre envíos verificado (Pest `tests/Feature/CompanionChatTest.php` 13 ✔, `node --test tests/JavaScript/companion-chat.test.js` 10 ✔, Playwright `npx playwright test -c tests/Browser/companion-chat.config.js` ✔). Sin commit. Algunos archivos del chat cambiaron en disco después de la última sesión: **léelos antes de editar**.

**Piezas existentes (no rehacer):**
- `resources/views/components/ui/fab.blade.php` → con compañero renderiza `x-ui.companion-chat` dentro de `wire:ignore`; `companion-chat.js` (`adoptHost`) lo mueve una vez a `<body>` con registro único `'page'` y guarda `this.wire` (no usar `$wire` dentro del chat: tras moverlo no resuelve el componente).
- Acciones `quick` por módulo: `compose` (texto → método Livewire que devuelve `{ok, count|message}`), `tap` (`call`, `args`, `reply` con `{total}` etc., `group`, `then`, `thenSay`), `step` (`then`, `say`). Los chips se filtran por `step === group` (default `start`).
- Textos en `config/companion_chat.php`; voz en `docs/UX-WRITING.md`.

**Qué construir (Tareas):**
1. Saludo con chips: `Crear tarea` · `Varias` · `Ver tareas` · `Ordenar` · `¿Qué sigue?` (los dos primeros ya existen como compose).
2. `Ver tareas` (step → grupo `view`, "¿Cuáles quieres ver?"): taps `Hoy`, `Vencidas`, `Próximas`, `Sin fecha`, `Todas` → nuevo `TaskList::chatFilter(string $date)` que fija `dateFilter` con las claves de `FiltersTasks::FILTER_DATES` (`hoy`, `vencidas`, `proximas`, `sin-fecha`; vacío = todas), resetea paginación y devuelve `{ok, total}`; reply "Te muestro las de hoy: tienes {total}." → grupo `after-view` con `Ordenar`, `Quitar filtro`, `Crear otra`, `↩ Inicio`. Los chips de filtros aplicados de la lista deben reflejarlo (son el mismo estado).
3. `Ordenar` (grupo `sort`): taps sobre `TaskList::$sort` (revisar valores válidos en TaskList/`render`) → `chatSort(string $sort)` con lista blanca.
4. `¿Qué sigue?`: `chatNext()` devuelve la tarea pendiente más urgente (vencida/hoy, luego prioridad) `{ok, title, id}`; reply "Tu siguiente: {title}." + chips `✓ Completar` (reutilizar el método de completar de `HandlesTaskRowActions`) y `Abrir` (evento `task-editor` con `{action:'openForm', id}`). Requiere que `tap` acepte args dinámicos del resultado previo: guardar `lastResult` en el chat y permitir `args: ['{id}']`.
5. Autocompletado sin IA: al escribir en el composer mostrar hasta 3 sugerencias por coincidencia de palabras clave (definir `keywords` por ítem en config, normalizar tildes/mayúsculas); Enter sobre sugerencia la ejecuta; sin coincidencia sigue creando la tarea.
6. Chip `↩ Inicio` (step `then: 'start'`) en todos los grupos no iniciales; Escape cierra.
7. Fuera de alcance inicial: "Mover vencidas a hoy" (acción masiva, necesita confirmación) → dejar para después.

**Textos** (añadir a `config/companion_chat.php` → `tasks`): `view_question` "¿Cuáles quieres ver?", `view_reply` "Te muestro {label}: tienes {total}.", `view_empty` "No hay tareas aquí. ¿Creamos una?", `sort_question` "¿Cómo las ordeno?", `sort_reply` "Listo, ordenadas por {label}.", `next_reply` "Tu siguiente: {title}.", `next_empty` "¡Nada pendiente! Disfruta 🎉".

**Pruebas a agregar:** Pest (`chatFilter`/`chatSort` lista blanca y totales, `chatNext` orden y vacío, usuario ajeno); node (args dinámicos, sugerencias por palabras clave); Playwright en `companion-chat.interaction.spec.js` ("Ver tareas → Hoy" filtra la lista y el chat sigue abierto).

**Cierre:** marcar T4 (accesibilidad axe y capturas 375/768/1280 del spec) y luego `node .claude/team.mjs verify CHG-100`. Tests previos que ya fallaban y no son de este cambio: 2 en `tests/Feature/ModuleExperienceTest.php`.


## 11. Continuación y validación · 2026-11-13

- El usuario confirmó continuar en la app real (CHG-100), sin retomar CHG-099. Para «¿Qué sigue?» eligió la lista actual, respetando búsqueda y filtros.
- Implementados `chatFilter`, `chatSort`, `chatNext`, `chatOpen` y `chatComplete` en `InteractsWithTaskChat`. Lista blanca de claves existentes, totales y paginación del mismo estado del listado. Propuesta: vencidas → hoy → resto; prioridad, cronológico e ID como desempate.
- Abrir/completar vuelven a comprobar usuario, filtros y estado pendiente. Completar reutiliza la acción existente y su transacción; las recurrencias abren su confirmación y no reciben un mensaje de éxito anticipado.
- Acciones por grupos con regreso a Inicio. `lastResult` resuelve identidades dinámicas, se limpia al cambiar de contexto y rechaza marcadores sin valor antes de llamar al servidor. Sugerencias por palabras clave en config: hasta tres, normalizadas; Enter solo ejecuta una sugerencia seleccionada. Sin selección conserva creación literal.
- Se mantiene el montaje único en body y la conexión Livewire. Corregida semántica ARIA del indicador de escritura (`role=img`) detectada por Axe. Catálogo y contrato de diseño documentan la variante canónica del FAB y sus registros rápidos aprobados.
- PHP: CompanionChatTest 19 pruebas y 113 assertions aprobadas, incluyendo aislamiento entre usuarios, filtros, órdenes válidos, propuesta vacía/priorizada, cambios posteriores y recurrencia.
- JavaScript: companion-chat.test.js 17 pruebas aprobadas (identidades, contexto, sugerencias/teclado, borradores y ausencia de éxito prematuro).
- Build Vite aprobado. Una nueva compilación integró las fuentes compartidas de otra sesión (navigation-guard) que habían cambiado mientras se validaba; no se editaron esos archivos ni Android.
- Playwright: 7/7 aprobadas en una ejecución completa; comprobación responsive ampliada después y aprobada de nuevo. Filtrar/ordenar con chat abierto, abrir/completar, recurrencia/cancelación, sugerencias, transferencia al modal, registros de Agua/Ánimo y FAB sin colección.
- Axe WCAG A/AA y 2.1 AA: sin violaciones en el chat y sus sugerencias a 375/768/1280. Escritura durante saludo comprobada con movimiento normal; movimiento reducido y Escape/foco comprobados. En viewport 375×500 se escribió y envió una tarea manteniendo el control de envío visible. No equivale a teclado nativo en dispositivo físico.
- Capturas inspeccionadas de Tareas en móvil/escritorio, viewport reducido y Agua. Imágenes externas desactivadas en la fixture: se certifican composición, tema e interacción con placeholder, no el proveedor de arte.
- ui:conformance sigue fallando con 232 desviaciones de la aplicación; no se amplió el alcance para corregir deuda visual ajena. TaskList conserva sus 17 estilos inline anteriores. git diff --check aprobado en todos los archivos de este trabajo.
- Guía final: docs/companion-chat.md. Evidencia local: storage/framework/testing/playwright/companion-chat y companion-chat-keyboard. No se hizo commit, despliegue ni migración de datos reales.
- LECCIÓN: ninguna adicional a los contratos ya documentados.
- 2026-11-13 — Implementación terminada → En verificación
