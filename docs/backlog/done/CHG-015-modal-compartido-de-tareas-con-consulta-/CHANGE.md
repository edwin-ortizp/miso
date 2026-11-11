# CHG-015 · Modal compartido de tareas con consulta y edición en la misma instancia
**Estado:** Cerrado
**Nivel:** M
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-04 · **Aprobado:** 2026-11-04 · **Cerrado:** 2026-11-10
**Relacionado:** CHG-006 a CHG-014 (mockup); CHG-016, CHG-017

## 0. En palabras simples
Todas las pantallas que abren una tarea usarán el mismo modal: consultar, pulsar el lápiz para editar el contenido y guardar sin abrir otra ventana. Las propiedades de la derecha siempre serán editables, pero nada se aplicará hasta Guardar. La creación múltiple seguirá aparte.
Cómo comprobarlo: abrir desde Lista, Calendario, Gantt, Flujo y Planificación; cambiar propiedades, pasar a edición y recorrer las cinco secciones. Cancelar no cambia datos. Guardar vuelve a consulta en la misma instancia.

## 1. Resumen
Primer incremento aprobado para trasladar la experiencia final del mockup a Laravel/Livewire. Base de CHG-016 y CHG-017. Reutilizar TaskEditor y el partial compartido, no crear otro diálogo del calendario ni copiar los datos o simplificaciones del prototipo. Foco se incorpora funcionalmente en CHG-017; no mostrar un botón inerte durante esta fase.

## 2. Producto (product-manager)
- Propietario autenticado: consultar, editar, crear y programar con una sola experiencia y un único borrador.
- Incluye consumidores de TaskEditor y partial individual. Flujo y el formulario separado de Planificación pasan a abrir TaskEditor, conservando carriles, filtros y reglas externas al editor. No añadir entradas nuevas a pantallas que no abren tareas.
- Excluye rediseñar creación múltiple, cambiar permisos o reemplazar Markdown, relaciones, recordatorios y reglas de recurrencia.
- Dada tarea existente, abrir presenta consulta y propiedades editables; el lápiz activa el contenido sin petición de recarga, reinicio de borrador ni sustitución de nodo/capa.
- Dados cambios de propiedades o de las cinco secciones, no se altera BD ni asociaciones hasta Guardar. Cambiar sección, ampliar o editar no guarda ni pierde el borrador.
- Guardar válido aplica el conjunto y vuelve a consulta del resultado real dentro del mismo modal. Cancelar, X, overlay y Escape descartan y cierran. Escape atiende primero controles/confirmaciones internos.
- Completar/reabrir mediante Estado conserva XP, historial, duración y recurrencia. Usar IDs/tipos/etiquetas personalizados reales, no los literales del mockup.

## 3. UX/UI (ux-designer)
- Cinco secciones reales navegables también en consulta: Información básica, Privacidad, Programación, Recordatorios y Relacionado, en ese orden; menú lateral canónico en móvil.
- Información básica conserva Markdown y sus controles Preview/Edit. Existentes comienzan en consulta/preview; nuevas en edición. No reconstruir el modal al cambiar modo.
- Columna derecha persistente: categoría jerárquica, estado, prioridad, tamaño y estimación siempre editables, con divisor y fondo sutil de tokens, sin tarjeta interior aislada. Apilar con divisor horizontal en móvil.
- Cabecera canónica con lápiz opcional antes de ampliar/cerrar. Icono con etiqueta/title, título en una línea truncado y accesible. Pie predeterminado: Cancelar text izquierda y Guardar filled derecha; sin Completar/Reabrir/Editar ni mensajes en el pie. No inventar split sin acciones adicionales.
- Pendientes/errores en contenido. Validación abre sección y enfoca campo afectado; error conserva borrador. Bloquear envío duplicado y mostrar carga/error de apertura.
- Mantener header/cierre/pie fuera del scroll, foco de retorno, teclado, safe area, visualViewport y objetivos de 44 px. Reutilizar x-ui.form-dialog/category-select/select/combobox y Markdown. Extensiones opcionales no alteran Salud u otros consumidores.

## 4. Diseño técnico (software-architect)
### Archivos y componentes
- app/Livewire/Task/TaskEditor.php; app/Services/TaskEditorSaveService.php (nuevo si se extrae el comando individual); app/Livewire/Task/TaskList.php solo para reutilización compatible sin cambiar bulk.
- resources/views/livewire/task/task-editor.blade.php y partials/edit-task-dialog.blade.php; resources/views/components/ui/form-dialog.blade.php; resources/js/form-editor.js; resources/css/m3/modules/_tasks.css.
- Entradas en resources/views/livewire/task/{task-list,task-calendar,task-gantt,task-flow,task-planning}.blade.php y componentes TaskFlow.php, TaskPlanning.php, TaskCalendar.php para delegar/recibir refrescos.
- HandlesRecurringTaskCompletion.php solo mediante extensión compatible; reutilizar TaskGamificationService y TaskRecurrenceService. Pruebas de §5 y docs/design-system.md.
### Contratos y persistencia
- Mantener evento task-editor y acción openForm. Metadatos opcionales de modo/sección/intención: con ID abre consulta, sin ID creación; Editar/Programar pueden pedir edición/schedule. Clientes antiguos siguen funcionando. Cambiar modo no vuelve a llamar openEditor/openForm.
- Extender borrador existente con modo, snapshot/pendientes y resultado de guardado. Cargar crea una sola copia. Guardar devuelve ID/código/propiedades/estado/fechas confirmados y actualiza la copia sin cerrar.
- Servidor valida autenticación/propiedad de tarea, estado, categoría, recordatorios y destinos. No confiar en campos ocultos ni en BelongsToUser sin autenticación. Preservar código inmutable, fechas solo-fin válidas y límites actuales.
- El editor actual excluye Done y hereda save que cierra: adaptar el flujo individual para todos los estados propios. Completar/reabrir pasa por servicios, no por asignación directa de completed/XP. Preservar ID del estado elegido y bulk independiente.
- Validar borrador completo. Si se completa recurrente, calcular propuesta con TaskRecurrenceService sobre datos propuestos sin persistir. Confirmación conserva borrador; cancelarla no modifica campos/relaciones/recordatorios.
- Confirmar revalida/relee con bloqueo y aplica datos, asociaciones, recordatorios y transición en una transacción. Respetar orden de bloqueo de TaskCodeService/recurrencias. Reintento/no-op no duplica XP ni historial. Rechazar confirmación obsoleta si cambió estado/recurrencia.
- Conservar RRULE, sugerencia de próxima fecha e historial reales. No copiar avance fijo o bloqueo de reglas avanzadas del mockup. Tras completar recurrente mostrar estado/fecha efectivos y aviso para navegar a próxima repetición.
- Refrescar contenedores mediante eventos existentes solo tras éxito, conservando filtros, búsqueda y vista. Retirar formularios/popovers redundantes solo cuando su sustitución funcione; no tocar acciones rápidas externas ni TaskBulkEditor.

## 5. Pruebas (qa-tester)
- Nuevos tests TaskEditorDraftTest: sin escritura al abrir/mutar/cancelar; guardado conjunto, cinco secciones, campos ocultos, privacidad/relaciones/recordatorios, validaciones y dos usuarios con categorías/estados/destinos distintos. Bloquear actor sin sesión y UUID ajeno.
- Regresión TaskEditorRelationsTest, TaskStatusesTest, TaskCalendarStatusTest y TaskCalendarRecurrenceSuggestionTest. Completar/reabrir con estados personalizados, no-op/reintento sin doble XP; recurrentes simples/avanzadas; cancelar confirmación deja BD igual, confirmar conserva hora/duración/historial.
- JS form-editor: modo sin close/open/reload, snapshot, cancelación total, carga asíncrona obsoleta, fallo/reintento y aislamiento entre tareas. Regresión bulk y consumidores no-tarea.
- Browser aislado a 390/768/1440 y altura baja: identidad DOM, columna editable, lápiz/footer, secciones, foco/Escape, scroll/expansión/teclado virtual; cada consumidor y creación múltiple. Probar aislamiento antes de afirmar resultados de UI.
- Ejecutar php artisan test con filtros afectados/nuevos, JS pertinente de package.json, Browser acotado, npm run build, php artisan ui:conformance y git diff --check. BD desechable/datos ficticios, sin migrar BD habitual ni suites ajenas por defecto.

## 6. Documentación (tech-writer)
Actualizar docs/design-system.md con modos de tarea, propiedades en borrador, lápiz/cabecera y Guardar que vuelve a consulta. Cancelar/X/overlay/Escape cierran descartando; bulk separado. Enlazar mockup final como evidencia visual, no backend. Registrar pruebas/fallos previos/consumidores en §8; no crear lineamientos generales de rol ausentes.

## 7. Tareas
- [x] T1 · Guardar tareas con sus reglas y confirmación atómica — Resp: dev-implementer — Modelo: estandar — Archivos: app/Livewire/Task/TaskEditor.php, app/Services/TaskEditorSaveService.php, app/Livewire/Task/TaskList.php, app/Livewire/Concerns/HandlesRecurringTaskCompletion.php, tests/Feature/TaskEditorDraftTest.php, tests/Feature/TaskEditorRelationsTest.php — Hecho cuando: guardado único validado, estados/gamificación correctos y cancelar confirmación no escribe.
- [x] T2 · Consultar y editar en el mismo modal canónico — Resp: dev-implementer — Modelo: estandar — Archivos: resources/views/livewire/task/task-editor.blade.php, resources/views/livewire/task/partials/edit-task-dialog.blade.php, resources/views/components/ui/form-dialog.blade.php, resources/js/form-editor.js, resources/css/m3/modules/_tasks.css, tests/JavaScript/form-editor.test.js — Hecho cuando: morph sin recarga, cinco secciones, columna en borrador, lápiz y pie canónicos, Guardar vuelve a consulta.
- [x] T3 · Unificar las entradas al editor — Resp: dev-implementer — Modelo: estandar — Archivos: resources/views/livewire/task/task-list.blade.php, resources/views/livewire/task/task-calendar.blade.php, resources/views/livewire/task/task-gantt.blade.php, resources/views/livewire/task/task-flow.blade.php, resources/views/livewire/task/task-planning.blade.php, app/Livewire/Task/TaskFlow.php, app/Livewire/Task/TaskPlanning.php, app/Livewire/Task/TaskCalendar.php — Hecho cuando: todas usan TaskEditor, contexto/refresco conservados y bulk sin cambios.
- [x] T4 · Verificar reglas y experiencia responsive — Resp: qa-tester — Archivos: tests/Feature/TaskEditorDraftTest.php, tests/Feature/TaskEditorRelationsTest.php, tests/Feature/TaskStatusesTest.php, tests/Feature/TaskCalendarStatusTest.php, tests/Feature/TaskCalendarRecurrenceSuggestionTest.php, tests/Browser/task-editor.interaction.spec.js, tests/Browser/support/task-editor-fixture.php — Hecho cuando: §5 pasa con aislamiento y evidencia de regresión/capturas.
- [x] T5 · Documentar contrato del editor — Resp: tech-writer — Archivos: docs/design-system.md — Hecho cuando: reglas actualizadas y §8 distingue validación de despliegue pendiente.

## 8. Bitácora de implementación (dev-implementer)
- 2026-11-04 — Inicio de implementación. Suite de base: 1.058 pruebas, 1.018 aprobadas y 28 fallidas; TaskEditorRelationsTest: 4 aprobadas. El usuario autorizó continuar y revisar pruebas después. CHG-003 permanece en desarrollo para el mockup; se inició este cambio con la opción oficial --force.

- 2026-11-04 — Avance parcial T1–T3: guardado transaccional del editor, confirmación recurrente, lápiz y propiedad lateral, y apertura compartida desde Flujo y Planificación. TaskEditorDraftTest: 3 pruebas / 17 aserciones; TaskEditorRelationsTest: 4 / 11; TaskCalendarStatusTest: 4 / 25. Build y ui:conformance correctos. Faltan validaciones y criterios de las tres tareas; no se marcan completadas.

- 2026-11-04 — T1: guardado individual y transiciones con bloqueo/transacción, confirmación recurrente sin escritura previa y rechazo de confirmación obsoleta. TaskEditorDraftTest: 7 pruebas / 31 aserciones; TaskEditorRelationsTest: 4 / 11. Quedan pruebas ampliadas en T4.

- 2026-11-04 — T2–T3: modal compartido con lápiz, panel editable, confirmación recurrente y entradas de Flujo/Planificación; actualización de consumidores. Pruebas JS dirigidas: 3 aprobadas; prueba de vista Flujo: 1 aprobada. La validación de navegador y regresión ampliada está pendiente en T4 por instrucción del usuario. T5: contrato actualizado en docs/design-system.md.

- 2026-11-04 — Intención Programar exige fecha antes de guardar y abre Programación; TaskEditorDraftTest: 8 pruebas / 37 aserciones.


- Revisión QA 2026-11-10 sobre HEAD 62351bb865ea8b1233ef8757b59feed2870a8c5e. Alex confirma funcionamiento en producción (aceptación funcional del usuario, no ejecución técnica contra producción). Suite focal PHP: 301/302; el fallo es CHG-027. Regresión adicional: 54/58, cuatro fallos existentes de shell/preview informados aparte. JS 44/44 y form-editor 3/3. Build correcto con aviso de chunk; conformance global falla por CHG-058 y deuda de CHG-054. CHG-059 concurrente excluido; no se alteró código funcional.
- TaskEditorDraftTest (8) y TaskEditorRelationsTest (4) correctos; form-editor JS 3/3. Navegador: crear borrador, cambiar a Programación y cancelar en 390/768/1440. TaskViewsTest conserva una aserción previa sobre HTML de preview que falla; no se declara verde la regresión global.

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-04 — Creado en to-do/ (Borrador, nivel M)
- 2026-11-04 — Usuario aprobó el plan consolidado y su registro como tres CHG, sin implementar. Modal común en todas sus pantallas; creación múltiple aparte; nuevas tareas deben guardarse antes de Foco.
- Revisión: Team Lead aplicó skills de Producto, Arquitectura, QA y Documentación; UX/UI consultado además mediante agente independiente. No existen todavía documentos generales/backlogs por rol; fuente vigente: código y design-system.
- Perfil estandar para implementación y razonamiento para diseño, sin seleccionar proveedores ni cambiar TEAM_MODELS.md.
- 2026-11-04 — Aprobado por el usuario (Compuerta 2)
- 2026-11-04 — Movido a in-progress/ (En desarrollo)
- 2026-11-04 — Implementación terminada → En verificación
- 2026-11-04 — Reabierto: Falta implementar la intención Programar del contrato aprobado, necesaria para CHG-016.
- 2026-11-04 — Implementación terminada → En verificación
- 2026-11-04 — Reabierto: Registrar y comprobar el contrato Programar incorporado para CHG-016.
- 2026-11-04 — Implementación terminada → En verificación
- 2026-11-04 — Reabierto: Revisar navegación de Lista tras la regresión reportada.
- 2026-11-10 — Implementación terminada → En verificación
- 2026-11-10 — Cerrado por el usuario (Compuerta 3) → done/
