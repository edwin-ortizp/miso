# CHG-112 · Claridad móvil de personajes, Hábitos y Calendario
**Estado:** En verificación
**Nivel:** M
**Modelo sugerido:** estandar
**Creado:** 2026-11-15 · **Aprobado:** 2026-11-15 · **Cerrado:** —
**Relacionado:** CHG-058, CHG-086, CHG-016

## 0. En palabras simples
Los compañeros hablan hacia el personaje situado a la derecha. Hábitos muestra progreso compacto y grupos delimitados; Calendario separa sus controles por función.
Comprueba ambas pantallas en móvil y escritorio, cambia una vista y marca un hábito.

## 1. Resumen
Primer bloque del plan aprobado por el usuario el 2026-11-15, con instrucción expresa de implementar y verificar. Conservar identidad, funciones y cambios ajenos. Los demás módulos se abordan en bloques posteriores; los guardianes compartidos reciben la corrección transversal.

## 2. Producto (product-manager)
- Problema: controles móviles saturados y relación visual incoherente entre personaje y mensaje.
- Incluye: guardianes compartidos y foco; registro de Hábitos; controles de Calendario.
- No incluye: funciones nuevas, migraciones, Android nativo, datos reales, commits ni despliegue.
- Aceptación: personaje a la derecha en composición horizontal; progreso de la fecha seleccionada actualizado al marcar hábitos; separadores visibles; navegación, filtros y selección de vistas conservan comportamiento.

## 3. UX/UI
### 3.1 Experiencia (ux-designer)
Se conservan los flujos. Acción principal visible; solo se agrupan secundarias si ya existen. Acordeones mantienen estado y semántica de teclado. Progreso accesible sin depender del rail que baja bajo la lista.
### 3.2 Visual (ui-designer)
Reutilizar x-module-shell, x-ui.select, x-ui.action, x-ui.popover, icon-action y progress linear. Tokens M3 de superficie, outline-variant, tipografía e identidad de módulo. Un único DOM responsive.
```text
MÓVIL: HÁBITOS                 MÓVIL: CALENDARIO
Fecha                          Fecha              Vista
Mensaje → Personaje            ────────────────────────
Progreso compacto              ‹ › Ahora        Filtros
Mañana       2/3    ⌄           ────────────────────────
Noche        0/2    ⌃           Tipos de elemento ×
  Empezar rutina               Calendario
  ────────────────────
  ○ Hábito       Hora
  ────────────────────
  ○ Hábito       Hora
ESCRITORIO
Lista de hábitos | Guardián → Personaje
                 | Progreso y Constancia
Calendario: fecha/vista y navegación agrupadas, filtros activos debajo.
```
Controles de 44 px; textos largos envuelven; sin scroll horizontal de página a 390/768/1440. Composiciones verticales de foco mantienen punta hacia el arte.
### 3.3 Escritura (ux-writer)
Conservar textos y consejos actuales. Etiquetas de progreso reutilizan «Progreso del día» y conteos; no agregar promesas ni acciones tomadas de las imágenes.

## 4. Diseño técnico (software-architect)
- Archivos: app/Livewire/Game/GuardianCard.php; resources/views/livewire/game/guardian-card.blade.php; resources/views/components/game/focus-companion.blade.php; resources/views/components/game/daily-buddy.blade.php; resources/views/livewire/habit/habit-tracker.blade.php; resources/views/livewire/task/task-calendar.blade.php; resources/css/m3/modules/{_game,_habits,_tasks,_pomodoro}.css; tests/Feature/HabitTimeAccordionTest.php; tests/Browser/mobile-clarity*; tests/Browser/support/mobile-clarity-fixture.php; pruebas afectadas de GuardianCard/Hábitos.
- Progreso opcional reactivo pasado desde el padre a GuardianCard para no recalcular ni confundir fecha seleccionada con hoy. Sin cambiar Advisor ni permisos.
- Estructura de calendario con grid para separar grupos sin duplicar controles.
- Riesgos: morph de hijos Livewire, variante de foco vertical, menús y textos largos. Cubrir con fixture aislada y pruebas existentes.
- No tocar: trabajo ajeno de Objetivos/chat, esquemas ni datos reales. Assets generados únicamente por build.

## 5. Pruebas (qa-tester)
- PHP: HabitTimeAccordionTest, HabitRoutineTest, HabitGamificationTest, ModuleGuardianTest y pruebas de calendario afectadas.
- Browser: base SQLite dedicada, usuarios sintéticos con/sin compañero, 390/768/1440; posición y controles, toggle, acordeón, filtros y vistas, capturas y axe en superficies modificadas.
- Comandos: php artisan test --filter="HabitTimeAccordionTest|HabitRoutineTest|HabitGamificationTest|ModuleGuardianTest|TaskCalendar"; npm run build; node --test tests/JavaScript/task-calendar.test.js tests/JavaScript/focus-companion.test.js; npx playwright test --config=tests/Browser/mobile-clarity.config.js; php artisan ui:conformance; git diff --check.

## 6. Documentación (tech-writer)
Actualizar docs/design-system.md sobre composición de guardianes y agrupación del calendario. Registrar resultados y pendientes aquí.

## 7. Tareas
- [x] T1 · Corregir orientación de personajes y globos — Resp: developer — Archivos: resources/css/m3/modules/_game.css, resources/css/m3/modules/_pomodoro.css, resources/views/components/game/focus-companion.blade.php, resources/views/components/game/daily-buddy.blade.php — Hecho cuando: composición horizontal y vertical coherentes y sin regresión de foco.
- [x] T2 · Clarificar progreso y grupos de Hábitos — Resp: developer — Archivos: app/Livewire/Game/GuardianCard.php, resources/views/livewire/game/guardian-card.blade.php, resources/views/livewire/habit/habit-tracker.blade.php, resources/css/m3/modules/_habits.css — Hecho cuando: progreso reactivo de fecha seleccionada y filas delimitadas.
- [x] T3 · Ordenar controles del calendario — Resp: developer — Archivos: resources/views/livewire/task/task-calendar.blade.php, resources/css/m3/modules/_tasks.css — Hecho cuando: controles agrupados y filtros funcionales en móvil y escritorio.
- [x] T4 · Verificar y documentar primer bloque — Resp: developer — Archivos: tests/Browser/mobile-clarity.config.js, tests/Browser/mobile-clarity.interaction.spec.js, tests/Browser/support/mobile-clarity-fixture.php, docs/design-system.md — Hecho cuando: pruebas afectadas, build, conformance y comprobaciones visuales pasan; limitaciones documentadas.

- [x] T5 · Completar acabado de la cabecera del calendario según referencia — Resp: developer — Archivos: resources/views/livewire/task/task-calendar.blade.php, resources/css/m3/modules/_tasks.css, tests/Browser/mobile-clarity.interaction.spec.js, docs/design-system.md — Hecho cuando: fecha, selector, filtro tonal y badge circular comprobados en 390/768/1440, filtros y vistas siguen operando y build/conformidad focal pasan.

- [x] T6 · Compactar calendario y alinear herramientas del marco móvil — Resp: developer — Archivos: resources/views/livewire/task/task-calendar.blade.php, resources/css/m3/modules/_tasks.css, resources/views/layouts/app.blade.php, resources/css/m3/archetypes/_app-frame.css, resources/css/m3/primitives/_buttons.css, resources/js/task-calendar.js, tests/JavaScript/task-calendar.test.js, tests/Browser/mobile-clarity.interaction.spec.js, docs/design-system.md — Hecho cuando: superficies compactas con toque 44 px, capas ocultas explícitas y ningún filtro por defecto, cuatro herramientas con centros equidistantes y menús/búsqueda operativos; capturas a 320/390/768/1440.

- [x] T7 · Integrar tarjeta y navegación diaria de Hábitos — Resp: developer — Archivos: app/Livewire/Game/GuardianCard.php, resources/views/livewire/game/guardian-card.blade.php, resources/views/livewire/habit/habit-tracker.blade.php, resources/css/m3/modules/_game.css, resources/css/m3/modules/_habits.css, tests/Feature/Game/ModuleGuardianTest.php, tests/Feature/HabitTimeAccordionTest.php, tests/Browser/mobile-clarity.interaction.spec.js, docs/design-system.md — Hecho cuando: fecha centrada con flechas sin Hoy, un globo, Otro consejo en encabezado, progreso integrado al pie, resumen neutro para otras fechas y pruebas/capturas a 320/390/768/1440 pasan sin regresiones propias.

## 8. Bitácora de implementación (developer)
- T1: mensaje antes del arte en DOM y punta derecha para guardianes, foco horizontal y bienvenida. Variante vertical de Pomodoro mantiene arte arriba y punta superior. Acciones migradas a x-ui.action; objetivos táctiles de 44 px. Imágenes no se espejan ni se regeneran.
- T2: GuardianCard recibe displayProgress reactivo del padre. x-ui.progress muestra la fecha consultada y se actualiza al marcar. Acordeones con encabezado y divisores; rutina dentro del contenido expandido. active-route="habits" conserva navegación y rail durante actualizaciones Livewire (antes desaparecían al pasar a livewire.update).
- T3: grid de fecha/vista, fila delimitada de navegación/filtros y bloque de filtro aplicado. Se conservan handlers, persistencia de filtros y editor compartido.
- T4: npm run build pasa (advertencia existente de chunk grande). 21/21 pruebas JS de foco/calendario pasan. Pruebas PHP afectadas: 95 ejecutadas, 91 pasan y 4 fallan; tres fallos de selección de equipo reproducidos antes de editar (ModuleGuardianTest: afinidad, equipo sin afinidad y cambio de semana); HomeDashboardTest espera md-module-shell aunque dashboard.blade.php, sin modificar, usa lt-page. No se alteró esa lógica.
- Browser: cinco pruebas pasan con base SQLite exclusiva mobile-clarity-browser.sqlite y usuarios ficticios. Interacciones a 390/768/1440, guardianes después de morph, toggle/acordeón, filtros, vistas, foco ocultable, ausencia de compañero y progreso en fechas distintas. Axe sin infracciones en grupos de hábitos/guardianes y toolbar del calendario. Capturas en storage/framework/testing/playwright/mobile-clarity/. Fixture usa placeholder neutro de imagen, no una colección personal.
- Conformidad global ejecutada: sigue fallando por deuda fuera del bloque (day-tile-info, task-list, entre otras). Escáner limitado a las cinco vistas modificadas: ninguna infracción por encima del inventario; no se regeneró baseline. git diff --check de archivos propios pasa.
- Pendientes: validación visual del usuario y revisión de bloques posteriores; no se certifica toda la interfaz móvil, Android físico ni despliegue.

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-15 — Creado por CLI. Usuario aprobó el plan y pidió implementación sin nueva ronda de aprobación.
- CLI local sin context/activity: lectura directa de lineamientos, evidencia manual en este archivo. docs/DOCUMENTATION.md no existe.
- 2026-11-15 — Aprobado por el usuario (Compuerta 2)
- 2026-11-15 — Movido a in-progress/ (En desarrollo)

- Compañero de bienvenida: aplicar la misma composición horizontal al hero reutilizable de Mi día; sin cambios en la distribución de sus estadísticas. Cubierto por comprobación geométrica y captura en los tres anchos.

- LECCIÓN(developer): en módulos con rail_routes, pasar activeRoute explícito desde la pantalla evita perder el contexto al actualizar Livewire — evidencia: rail de Hábitos desaparecía después de toggleHabit y la nueva prueba verifica su conservación · destino sugerido: docs/UI-DESIGN.md.
- 2026-11-15 — Implementación terminada → En verificación
- 2026-11-15 — Reabierto: Revisión visual del usuario: completar acabado de filtros, fecha y selector compacto del calendario

### Revisión visual solicitada: acabado del calendario
Fecha con bi-calendar4 y peso 700; divisor vertical entre fecha y vista; selector Vista: integrado en pastilla tonal de 44 px, sin etiqueta flotante sobre el borde. Filtros como acción outlined con lavado tonal y x-ui.badge circular interno. Ahora tonal, flechas independientes, controles de 44 px y filas con menos padding. Filtros aplicados con etiqueta visible y chip outlined removible. Usar tokens del módulo, sin cambios de lógica, backend o controles globales.
```
[calendario] 15 Nov 2026  |  [Vista: Día ▾]
─────────────────────────────────────────
[‹] [›] [Ahora]             [Filtros (1)]
[Filtros aplicados:  Tipos de elemento ×]
```


- T5 verificada: fecha con icono/negrita, divisor central, selector nativo compacto con etiqueta inline, botones tonal/outlined y contador circular mediante badge canónico; etiqueta y chip de filtros aplicados. Superficies separadas con menos padding, manteniendo 44 px táctiles. Los controles globales no se modificaron.
- Validación T5: npm run build pasa; 14 pruebas PHP de TaskCalendar (94 aserciones) pasan; 3 pruebas Playwright a 390/768/1440 pasan, incluido axe de toolbar y comprobación de badge circular, icono, negrita y selector 44 px. Capturas after-calendar-{390,768,1440}.png inspeccionadas. Escáner focal sin deuda nueva; conformidad global sigue bloqueada por deuda ajena (222 infracciones totales). git diff --check pasa. LECCIÓN: ninguna adicional.
- 2026-11-15 — Implementación terminada → En verificación
- 2026-11-15 — Reabierto: Feedback: compactar superficies móviles del calendario, explicar capas ocultas y uniformar herramientas del header compartido

### Revisión adicional: densidad y consistencia móvil
Autorizada por feedback directo del usuario. Reutilizar cabecera compartida y select actual; no cambiar controles globales de otros formularios. Agrupar búsqueda con las otras tres herramientas, celdas iguales de 44 px, sin separaciones acumuladas y avatar visual de 28 px. Calendario: superficies de 32 px dentro de controles táctiles de 44 px, icono fecha 18 px, padding lateral 12 px, Vista compacta de 132 px. Ocultar texto redundante «Filtros aplicados» en móvil, mantener icono y chip explícito «Ocultos: …». Conservar preferencias guardadas y permitir limpiar; no activar ninguna exclusión por defecto.
```
☰ [módulo] Título      [⌕][⚙][♧][perfil]
[fecha 18px] Fecha     | [Vista: Día ▾]
[‹] [›] [Ahora]             [Filtros ①]
[embudo] [Ocultos: Comidas ×]  (solo si aplica)
```

- Diagnóstico T6: el tamaño lg del botón de icono heredaba el padding de un botón de texto (32 px por lado), lo que forzaba 64 px de ancho incluso al declarar 44 px. Corrección canónica: padding cero en variantes sm/lg de md-btn-icon; no cambia sus dimensiones declaradas, colores ni comportamiento.

- T6: superficies móviles del calendario de 32 px dentro de hit areas de 44 px, fecha con icono 18 px y padding lateral 12 px, selector más estrecho y espaciado equilibrado. Filtros con fondo tonal 12%; sin texto «Filtros aplicados» en móvil. Chip explícito «Ocultos: {tipos}» solo ante exclusiones. Se preservan preferencias guardadas y limpiar las restablece.
- T6: cuatro herramientas contiguas/equidistantes de 44 px en el header compartido; avatar visual 28 px. Se corrige en el primitivo el padding heredado de icon buttons sm/lg que ensanchaba la campana a 64 px. No se ocultan acciones ni se alteran rutas.
- Validaciones T6: build pasa, 19 pruebas JS de calendario pasan, 14 pruebas PHP de TaskCalendar pasan (94 aserciones). Header a 320/360/390/430/768/1440: búsqueda con foco y resultados, perfil y notificaciones abren/cierran, menú contenido en viewport, anchura/altura 44 px y centros equidistantes en móvil. Calendario: estado inicial sin filtro, exclusiones explícitas, limpiar + recargar, controles y axe a 390/768/1440. Capturas normales y filtradas separadas en storage/framework/testing/playwright/mobile-clarity/; resultados del runner en results/ para no borrar capturas al ejecutar una selección.
- Conformidad global sigue fallando por deuda previa: 222 desviaciones; escáner focal sin deuda nueva. git diff --check de archivos modificados pasa. Datos usados exclusivamente sintéticos en SQLite aislado.
- LECCIÓN(developer): los tamaños sm/lg de botones de icono deben excluir el padding de botones de texto — evidencia: campana con width nominal 44 px pero anchura real 64 px antes de corregir padding; comprobación geométrica posterior de 44 px · destino sugerido: docs/UI-DESIGN.md.
- 2026-11-15 — Implementación terminada → En verificación
- 2026-11-15 — Reabierto: Implementar composición y navegación de Hábitos aprobadas por el usuario

### Ajuste de Hábitos aprobado
Usuario aprobó e indicó continuar. Composición: anterior · icono + fecha centrada/negrita · siguiente; sin Hoy ni selector emergente. Otro consejo en esquina superior derecha; un solo globo con arte a la derecha; progreso canónico al pie y ancho completo, sin caja blanca independiente. Para fechas distintas de hoy: resumen neutro del cumplimiento de la fecha, sin Otro consejo ni sugerencia de hoy. Reutilizar acciones existentes, mantener URL, ficha del compañero y variante rail de escritorio. No cambiar las composiciones de guardianes de otros módulos.

- T7 implementada: cabecera diaria con dos flechas y fecha centrada/negrita, sin Hoy ni picker nuevo; mantiene selectedDate y URL. GuardianCard admite displayDate reactivo opcional. Hábitos presenta encabezado con Otro consejo, un globo/arte y pie de progreso integrado a ancho completo. No se cambia la composición de otros módulos; wrapper de identidad usa display:contents fuera de Hábitos.
- Otras fechas: resumen neutro derivado del progreso del padre; no genera ni ofrece consejos para hoy. Al entrar directamente en otra fecha y regresar a hoy, se inicializa el consejo. El acceso a la ficha se conserva. Se corrige además la etiqueta del rail a «Progreso del día», pues sus valores corresponden a la fecha consultada.
- T7 PHP: 24 pruebas focales nuevas/afectadas pasan; grupo ampliado de GuardianAdvice/ModuleGuardian/Hábitos: 64 ejecutadas, 61 pasan y únicamente los mismos 3 fallos previos de selección del equipo semanal. No se modificó esa lógica.
- T7 build pasa; conformance global sigue en deuda ajena (222 infracciones); escáner focal sin deuda nueva, sin actualizar baseline. git diff --check de archivos editados pasa. La fixture sigue usando SQLite exclusivo y cuentas ficticias, sin datos personales ni migraciones reales.

- T7 navegador: 6 pruebas focales/regresión pasan. Nueva comprobación de navegación, pie integrado, consejo anclado, resumen y retorno a hoy a 320/390/768/1440; axe sin infracciones en fecha/guardián y sin desbordamiento. Se conservan actualización de progreso, acordeones, ausencia de compañero y composiciones de foco/calendario compartidas. Capturas inspeccionadas: after-habits-integrated-{320,390,768,1440}.png y after-habits-other-date-{320,390,768,1440}.png.
- T7 LECCIÓN: ninguna adicional a las propuestas previas. CHG queda para revisión visual; no se hicieron commits ni despliegue.
- 2026-11-15 — Implementación terminada → En verificación
