# Chat del compañero · CHG-100

La aplicación real permite presentar el FAB como el Pokémon favorito. La preferencia y el favorito usan los ajustes del juego existentes; sin colección o en modo tradicional se mantiene el FAB. No requiere una migración nueva.

## Revisar el recorrido

1. Abrir Tareas con la presentación Pokémon: el campo admite escritura durante el saludo.
2. Crear una tarea o varias y confirmar: el chat permanece abierto, conserva el hilo y actualiza la lista. «Más opciones» transfiere el borrador al editor canónico.
3. Elegir «Ver tareas» y Hoy, Vencidas, Próximas, Sin fecha o Todas. Solo cambia el filtro de fecha; se conservan búsqueda, categoría y estado. Los chips y el total de la lista reflejan el mismo estado.
4. Elegir «Ordenar» y un orden existente. «Inicio» regresa a los accesos iniciales.
5. «¿Qué sigue?» propone una pendiente dentro de la lista filtrada: vencidas, luego hoy, después el resto; prioridad y orden cronológico dentro de cada grupo. Abrir y Completar revalidan la tarea. Una recurrencia abre su confirmación sin anunciar éxito prematuro.
6. Escribir «ver tareas» muestra una sugerencia: flechas y Enter la seleccionan/ejecutan. Enter sin selección sigue creando el texto como tarea. No hay IA ni interpretación automática de instrucciones.
7. Comprobar Agua y Ánimo: un vaso conserva el chat abierto; ánimo continúa a energía. Sin colección, verificar el FAB tradicional.

## Contratos y aislamiento

- `TaskList` usa `InteractsWithTaskChat`: `chatFilter(date)` y `chatSort(sort)` aceptan solo claves vigentes y devuelven `{ok,total,label}`. `chatNext()` devuelve `{ok,id,title}` o `{ok,empty,message}`.
- `chatOpen(id)` y `chatComplete(id)` vuelven a consultar la tarea del usuario, pendiente y dentro de los filtros. Completar reutiliza la acción existente y su transacción; una recurrencia devuelve `pending` y mantiene el formulario de confirmación habitual.
- El controlador conserva el montaje único en `body` y la conexión Livewire. `lastResult` permite argumentos exactos `{id}`; un marcador sin resultado nunca se envía al servidor. Cambiar de contexto invalida la propuesta anterior.
- `quick` admite `compose`, `tap` y `step`; `group/then` organizan las opciones. `keywords` son configuradas y las sugerencias se limitan a tres. Los textos viven en `config/companion_chat.php`.

## Validación reproducible

```powershell
php artisan test --filter=CompanionChatTest
node --test tests/JavaScript/companion-chat.test.js
npm run build
npx playwright test -c tests/Browser/companion-chat.config.js
php artisan ui:conformance
git diff --check
```

Playwright usa una cuenta ficticia y una base SQLite desechable con ruta comprobada antes de migrar; no usa datos personales. Capturas y trazas locales en `storage/framework/testing/playwright/companion-chat`. El viewport reducido prueba acceso al campo y foco; no sustituye una prueba en un dispositivo físico con teclado nativo.

## Resultado local · 2026-11-13

- 19 pruebas PHP (113 assertions), 17 pruebas JavaScript y build aprobados.
- 7 pruebas de navegador aprobadas; revisión ampliada de viewport reducido también aprobada.
- Axe sin violaciones en chat/sugerencias a 375, 768 y 1280 px; comprobados foco, Escape, movimiento reducido, escritura durante el saludo, Más opciones y recurrencia.
- En 375×500 se pudo escribir/enviar una tarea con el botón visible. Sigue pendiente una comprobación en dispositivo físico con teclado nativo.
- Capturas locales revisadas. La fixture usa placeholders de arte; no certifica la descarga de imágenes externas.
- `ui:conformance` mantiene 232 desviaciones de la aplicación ajenas a estos cambios; no se declara conformidad global. `git diff --check` aprobado.


## Formularios de Objetivos y propietario del chat · CHG-111

Las acciones de formulario del chat usan la referencia Livewire capturada por el controlador, porque el host se mueve a `body` y deja de tener un ancestro Livewire. El host se identifica por el ID del propietario, estable durante renders aunque el contexto de ruta no esté disponible; la navegación Livewire destruye los hosts y sus listeners de viewport antes de montar el siguiente módulo.

Objetivos conserva el creador del índice y ofrece en el detalle crear/vincular tareas y hábitos, registrar avances y mediciones cuando hay indicador. Crear tarea usa TaskEditor; crear hábito reutiliza el formulario y persistencia de HabitSettings mediante HabitEditor. Ambos reciben un objetivo bloqueado y revalidan su usuario al guardar dentro de la transacción. El historial de hábitos se consulta para hoy y seis días anteriores; su cumplimiento se registra en Hábitos y no modifica el indicador del objetivo.

Regresión reproducible: `npx playwright test -c tests/Browser/goals.config.js` y `npx playwright test -c tests/Browser/companion-chat.config.js`. Las fixtures verifican su base SQLite desechable antes de migrar. No certifican un teclado físico, Android ni datos de producción.

## Enlace único de acciones del chat · CHG-113

El host del chat se mueve a `body`, así que no puede usar `wire:click`: Livewire resuelve el componente por ancestro y, sin él, el clic se pierde sin error. `App\Support\Ui\ActionBinding` clasifica la acción (`href`, `event` o método) y produce los atributos del FAB (`wire:click`) y del chip del chat (`act(() => wire.metodo())`); es la única fuente para ambos.

`act()` comprueba que la referencia Livewire capturada siga viva, avisa por consola una sola vez y no cierra el chat cuando no puede invocarla, para no dejar al usuario sin formulario. El chip de un método no usa `wire:click` en ningún caso.

La prueba de navegador recorre Agua, Pomodoro, Vehículos (índice, detalle, combustible, gastos y mantenimiento) y Relaciones (índice, detalle, historial, tareas y planes) con datos de fixture, y exige el diálogo visible sin errores de página: `npx playwright test -c tests/Browser/companion-chat.config.js`. Las pruebas unitarias del controlador cubren referencia válida, referencia muerta y excepción: `node --test tests/JavaScript/companion-chat.test.js`.
