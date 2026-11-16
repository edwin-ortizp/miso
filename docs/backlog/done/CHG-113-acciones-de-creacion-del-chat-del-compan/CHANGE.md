# CHG-113 · Acciones de creación del chat del compañero siempre abren su formulario
**Estado:** Cerrado
**Nivel:** S
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-15 · **Aprobado:** 2026-11-15 · **Cerrado:** 2026-11-16
**Relacionado:** CHG-100, CHG-111

## 0. En palabras simples
<!-- Para el usuario: sin jerga. Si no se entiende en 30 segundos, reescríbelo. -->
- Qué vas a ver distinto: al pulsar una acción de crear o registrar en el chat del compañero, siempre se abre su formulario; ya no queda un clic sin respuesta en Agua, Pomodoro, Vehículos, Ánimo, Ejercicio, Objetivos, Planes, Comidas, Relaciones ni en sus ajustes.
- Cómo comprobarlo tú: abre el chat en esos módulos y pulsa la primera acción; el modal aparece sobre la pantalla. Repítelo con filtros aplicados, después de un registro rápido y navegando entre módulos sin recargar.

## 1. Resumen
El host del chat se mueve a `<body>` para sobrevivir a los renders de Livewire y deja de tener un ancestro `[wire:id]`. Las acciones de tipo método se pintaban con `wire:click`, que Livewire resuelve por DOM; sin componente, la llamada se pierde en silencio. Las acciones de tipo `event` no dependían del componente y por eso sí funcionaban (Salud, Tareas, Compras). El enlace de acciones estaba duplicado entre el FAB y el chat; al centralizarlo, el chat usa la referencia capturada por el controlador y avisa si no puede resolverla.

## 4. Diseño técnico
- `app/Support/Ui/ActionBinding.php`: única fuente de verdad para clasificar una acción (`href`, `event`, `method`), su etiqueta y sus atributos en el FAB y en el chat. En el chat, un método se invoca con `act(() => wire.metodo()) && close(...)`.
- `resources/views/components/ui/fab.blade.php`: usa `ActionBinding` en lugar de las clausuras locales; deja de pasar `binding`/`tag` al chat.
- `resources/views/components/ui/companion-chat.blade.php`: usa `ActionBinding::chat()` y `ActionBinding::tag()`; elimina los props `binding` y `tag` (solo los usaba el FAB).
- `resources/js/companion-chat.js`: `wireAvailable()` detecta la referencia muerta y `act()` no la invoca, registra un error de consola una vez y devuelve `false` para no cerrar el chat.
- `tests/Browser/support/companion-chat-fixture.php`: crea un vehículo y una relación para ejercitar las pantallas de detalle.
- `tests/Browser/companion-chat.interaction.spec.js`: recorre los módulos con acción de método y exige el diálogo visible, sin errores de página.
- `tests/JavaScript/companion-chat.test.js`: cubre `act()` disponible, referencia muerta y excepción.

Sin cambios de rutas, datos ni endpoints.

## 7. Tareas
<!-- Primero lo que cambia para el usuario; el detalle técnico después. Formato:
- [ ] T1 · qué cambia (en simple) — Resp: developer — Modelo: perfil — Archivos: … — Hecho cuando: … -->
- [x] T1 · Un solo lugar decide cómo se enlaza una acción de creación — Resp: developer — Modelo: estandar — Archivos: app/Support/Ui/ActionBinding.php, resources/views/components/ui/fab.blade.php, resources/views/components/ui/companion-chat.blade.php — Hecho cuando: FAB y chat comparten la clasificación del enlace, el chat no usa `wire:click`, y `php artisan view:clear` + una pantalla con compañero renderizan sin errores.
- [x] T2 · El chat avisa cuando no puede invocar la acción — Resp: developer — Modelo: estandar — Archivos: resources/js/companion-chat.js, tests/JavaScript/companion-chat.test.js — Hecho cuando: `act()` devuelve `false` y registra el aviso con una referencia muerta, invoca el método con referencia válida, captura excepciones y las pruebas JavaScript pasan.
- [x] T3 · Prueba de navegador que exige el formulario en cada módulo — Resp: developer — Modelo: estandar — Archivos: tests/Browser/companion-chat.interaction.spec.js, tests/Browser/support/companion-chat-fixture.php — Hecho cuando: con datos de vehículo y relación, Agua, Pomodoro, Vehículos, su detalle, combustible, Relaciones y su detalle abren su diálogo; sin errores de página; `npx playwright test -c tests/Browser/companion-chat.config.js` pasa.
- [x] T4 · Barrido manual con datos en detalle de vehículo y de relación — Resp: developer — Modelo: estandar — Archivos: — — Hecho cuando: se comprueba en navegador real que las acciones del detalle abren su modal y queda registrado en la bitácora.

## 8. Bitácora de implementación (developer)
- 2026-11-15 — Causa confirmada: el host del chat se mueve a `body` y pierde el ancestro `[wire:id]`; `wire:click` no encontraba componente y la magia `$wire` devolvía una función vacía, sin error. Las acciones de tipo `event` sí funcionaban porque solo despachan al navegador.
- 2026-11-15 — `app/Support/Ui/ActionBinding.php` centraliza la clasificación y los atributos de cada acción. `fab.blade.php` lo usa para el FAB y deja de pasar `binding`/`tag`; `companion-chat.blade.php` usa `ActionBinding::chat()` y ya no aplica `wire:click`.
- 2026-11-15 — `companion-chat.js`: `wireAvailable()` + `act()` evitan invocar una referencia muerta, avisan una sola vez por consola y devuelven `false` para no cerrar el chat; una excepción de la acción también queda registrada.
- 2026-11-15 — Fixture con vehículo (`automovil`/`gasolina`) y relación para ejercitar detalles. Barrido en navegador real (1280 px): Agua, Pomodoro, Vehículos, detalle, combustible, gastos, mantenimiento, Relaciones, detalle, historial, tareas y planes abren su diálogo, sin errores de página.
- 2026-11-15 — Validación: `node --test tests/JavaScript/companion-chat.test.js` 21/21; `npx playwright test -c tests/Browser/companion-chat.config.js` 8/8; `npx playwright test tests/Browser/water-form.interaction.spec.js --project=interaction` 3/3 (FAB tradicional intacto); `php artisan test --filter=CompanionVoiceTest` 17/17.
- 2026-11-15 — `npm run build` publicado (`public/js/app.js`). `php artisan ui:conformance` informa 222 desviaciones ajenas a estos archivos; ninguna menciona `companion-chat` ni `fab`. `git diff --check` aprobado.

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-15 — Creado en to-do/ (Borrador, nivel S)
- Decisión: centralizar el enlace en `App\Support\Ui\ActionBinding` porque la duplicación entre FAB y chat fue el origen del fallo; el chat no puede reutilizar `wire:click` al vivir fuera del componente.
- 2026-11-15 — Implementación terminada → En verificación
- 2026-11-15 — Aprobado por el usuario (Compuerta 2)
- 2026-11-15 — Movido a in-progress/ (En desarrollo)
- 2026-11-15 — Implementación terminada → En verificación
- 2026-11-16 — Cerrado por el usuario (Compuerta 3) → done/
