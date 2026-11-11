# CHG-016 · Calendario compacto con colores por categoría y gestión de tareas sin fecha
**Estado:** Cerrado
**Nivel:** M
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-04 · **Aprobado:** 2026-11-04 · **Cerrado:** 2026-11-10
**Relacionado:** Depende de CHG-015; referencias CHG-006, CHG-008, CHG-009, CHG-014; CHG-017

## 0. En palabras simples
El calendario tendrá cabecera discreta con Ahora/Filtros, tareas coloreadas por su categoría y tabla para gestionar las que no tienen fecha. Todo abre el mismo editor de CHG-015.
Cómo comprobarlo: navegar las cuatro vistas, filtrar tipos, cambiar categoría y guardar, buscar una tarea sin fecha y programarla sin salir del calendario.

## 1. Resumen
Segundo incremento aprobado. Trasladar composición final del mockup a FullCalendar real conservando preferencias, consultas por usuario y comportamiento de eventos. Depende de CHG-015; no copiar datos, paleta ficticia ni simulaciones del prototipo.

## 2. Producto (product-manager)
- Incluye cabecera compacta, cuatro vistas, Ahora visible, filtros estándar de tipos, preferencias de navegador, color de categorías solo para tareas y tabla Sin fecha con búsqueda y Programar/Completar/Editar.
- Excluye colores por categoría para otras capas, nuevas taxonomías, selección múltiple de filas, exportaciones o filtros de tabla no acordados.
- Ahora vuelve a día/hora actuales: conserva Semana/Día en rejilla horaria y abre Día desde Mes/Agenda. Sin Hoy ni menú Actualizar redundante; conservar refetch automático tras escrituras/cambios de rango.
- Cambiar selección provisional no altera calendario ni storage hasta Filtrar. Cerrar/cancelar restaura filtro aplicado. Todos = sin restricción/badge; cero = vacío explícito válido. Quitar chip o limpiar filtro aplicado restaura todas las capas.
- Tarea con categoría usa su color real; ausencia/color inutilizable usa neutral. Cambiar categoría en borrador no cambia el calendario hasta Guardar.
- Programar abre editor en Programación y exige confirmar una fecha válida para esa intención. La fila solo sale de Sin fecha tras guardar una fecha real. Completar/Editar conservan reglas y propiedad del usuario.

## 3. UX/UI (ux-designer)
- Cabecera: periodo, flechas, Ahora, Mes/Semana/Día/Agenda y Filtros. Única composición responsive; vistas/filtros compactan según patrón canónico móvil, Ahora siempre visible. No añadir rail.
- Filtros con x-ui.popover y selección múltiple estándar; hoja móvil del sistema, sin variante Mostrar 4. Grupo Tipos de elemento: Tareas, Planes, Cumpleaños, Comidas. Limpiar prepara todas; Filtrar aplica. Chip aplicado eliminable y badge de un criterio solo al restringir.
- Fondo suave/borde por categoría y texto legible. Icono distingue tipo; prioridad usa indicador independiente, sin sobrescribir color. Conservar completada/recurrente y contraste de categorías extremas en temas existentes. Agenda conserva indicador de categoría.
- Sin fecha es sección de gestión separada, no management-card envolviendo FullCalendar. Reutilizar x-ui.management-card/tabla para esta lista. Columnas: Tarea/código, Categoría, Prioridad, Estado, Acciones. Estado antes de Acciones; búsqueda por título/código/categoría, carga/error/vacíos.
- Usar componentes de acciones por fila. Programar/Editar abren TaskEditor; Completar conserva confirmación recurrente y XP. FAB canónico y hueco del calendario crean sobre pantalla sin navegar.
- Conservar vista/filtros/posición al guardar/cerrar. Si desaparece una fila, devolver foco a un control estable. Teclado/Escape, foco visible, 44 px y sin overflow horizontal de página.

## 4. Diseño técnico (software-architect)
### Archivos
- app/Livewire/Task/TaskCalendar.php; resources/views/livewire/task/task-calendar.blade.php; resources/js/task-calendar.js; resources/css/m3/modules/_tasks.css.
- app/Models/TaskCategory.php solo si necesita helper reutilizable de color/consulta, sin cambiar claves/jerarquía/propiedad. Reutilizar componentes x-ui sin cambios globales de estilo.
- Pruebas de §5 y docs/design-system.md.
### Contratos y datos
- Mantener events(start,end), IDs, rangos y extremos de fechas de las cuatro capas. Solo tareas admiten drag/resize. No sustituir feed ni detailUrl de otros elementos.
- Cargar categorías del propietario una vez por petición/rango, resolver key de tarea a nombre/color sin N+1. Añadir metadatos conservando los existentes; solo color validado según contrato real se convierte a estilo seguro. Neutral ante datos ausentes/antiguos, sin categorías ficticias.
- Estilos de categoría con tokens/contraste del tema; prioridad/completada/recurrente independientes tras guardar/refetch. No reemplazar colores de planes/cumpleaños/comidas ni su editabilidad.
- Conservar clave miso.task-calendar.layers.v1 y preferencias previas, incluido conjunto vacío. Separar appliedLayers/draftLayers; storage bloqueado/inválido no rompe UI. Filtrar escribe preferencia, cancelar no. Toggle provisional no provoca escrituras BD.
- Retirar popover y split de estado antiguos solo tras conectar eventClick a TaskEditor. dateClick conserva prefill fecha/hora, FAB crea sin ID, parámetro edit y clientes existentes siguen funcionando.
- Sin fecha: ambas fechas null, no completada y usuario autenticado explícito. Búsqueda correctamente agrupada y asociaciones eager-loaded. Usar paginación de gestión existente si requiere volumen; conservar orden reciente actual y contexto de consulta al guardar. Revalidar tarea/estado al programar o completar.
- Actualizar tabla/eventos con eventos existentes tras éxito. No refetch por cada campo del borrador ni recrear FullCalendar. Error drag/save revierte representación local y no comunica éxito falso.
### Fuera de alcance
Sin migración ni cambios en taxonomía, reglas de MealSchedule/planes/personas, global shell, otras capas o bulk. Actualizar pruebas a nuevos controles sin eliminar cobertura de comportamientos conservados.

## 5. Pruebas (qa-tester)
- PHP: capas/rangos/propiedad actuales, categorías por propietario, neutral/color ajeno y consultas sin N+1; búsqueda Sin fecha agrupada y acotada, exclusión de fechadas/completadas/ajenas y acciones autorizadas.
- JS: Ahora desde cuatro vistas; borrador/aplicar/cancelar/reset/chip/ninguno; storage ausente/corrupto/antiguo; refetch/parche sin duplicar instancia; color conserva prioridad/estado/recurrencia.
- Browser aislado 390/768/1440: cabecera/hoja filtros, teclado/foco, colores en cuatro vistas, drag/resize, apertura sin popover extra, tabla/búsqueda/vacíos/programación, FAB/hueco, errores y contexto. Dos usuarios antes de afirmar aislamiento UI.
- Regresión TaskCalendarLayersTest, TaskCalendarStatusTest, TaskCalendarRecurrenceSuggestionTest y editor CHG-015. Nuevos TaskCalendarColorsTest/TaskCalendarUnscheduledTest y JS/Browser acotados.
- Ejecutar php artisan test con filtros pertinentes, JS de calendario, Browser afectado, npm run build, php artisan ui:conformance y git diff --check. BD desechable/datos ficticios, sin suites ajenas por defecto.

## 6. Documentación (tech-writer)
Actualizar docs/design-system.md: cabecera Ahora/Filtros, preferencias aplicadas/persistidas, categoría de tareas y gestión Sin fecha. Enlazar CHG-015/017 sin duplicar reglas de dominio. Evidencia, fallos previos y pendientes en §8.

## 7. Tareas
- [x] T1 · Organizar cabecera y filtros estándar — Resp: dev-implementer — Modelo: estandar — Archivos: resources/views/livewire/task/task-calendar.blade.php, resources/js/task-calendar.js, resources/css/m3/modules/_tasks.css, tests/JavaScript/task-calendar.test.js — Hecho cuando: Ahora/vistas/filtros funcionan, preferencias solo al confirmar y no quedan controles redundantes.
- [x] T2 · Colorear tareas por categorías reales — Resp: dev-implementer — Modelo: estandar — Archivos: app/Livewire/Task/TaskCalendar.php, app/Models/TaskCategory.php, resources/js/task-calendar.js, resources/css/m3/modules/_tasks.css, tests/Feature/TaskCalendarColorsTest.php — Hecho cuando: sin N+1, neutral seguro, contraste y prioridad independiente; color solo cambia tras guardar.
- [x] T3 · Gestionar Sin fecha y abrir editor unificado — Resp: dev-implementer — Modelo: estandar — Archivos: app/Livewire/Task/TaskCalendar.php, resources/views/livewire/task/task-calendar.blade.php, resources/js/task-calendar.js, tests/Feature/TaskCalendarUnscheduledTest.php — Hecho cuando: tabla/búsqueda/acciones/FAB/hueco/evento usan contexto correcto y Programar actualiza tabla/feed después de guardar.
- [x] T4 · Verificar calendario y responsive — Resp: qa-tester — Archivos: tests/Feature/TaskCalendarLayersTest.php, tests/Feature/TaskCalendarStatusTest.php, tests/Feature/TaskCalendarRecurrenceSuggestionTest.php, tests/Feature/TaskCalendarColorsTest.php, tests/Feature/TaskCalendarUnscheduledTest.php, tests/Browser/task-calendar.interaction.spec.js, tests/Browser/support/task-calendar-fixture.php — Hecho cuando: §5 pasa con aislamiento, cuatro vistas y regresión documentada.
- [x] T5 · Documentar calendario y gestión Sin fecha — Resp: tech-writer — Archivos: docs/design-system.md — Hecho cuando: contrato coincide con código y referencias a editor/Foco.

## 8. Bitácora de implementación (dev-implementer)
- 2026-11-04 — T1: cabecera propia con Ahora, vistas y filtros provisionales aplicados solo al confirmar; se conserva la preferencia anterior. Pruebas JS de calendario: 19 aprobadas. Navegador y regresión ampliada quedan en T4.

- 2026-11-04 — T2: categorías del propietario cargadas una vez, clave validada de la paleta compartida, fallback neutro y prioridad independiente. TaskCalendarLayersTest: 5 pruebas / 35 aserciones. Contraste y navegador pendientes de T4.

- 2026-11-04 — T3: tabla paginada Sin fecha con búsqueda y acciones; eventos de tarea abren el editor. Tests dirigidos: Unscheduled 1/4, Status 4/25, RecurrenceSuggestion 1/16 y JS calendario 16 aprobados. T5: contrato actualizado. Navegador y pruebas adicionales pendientes de T4.

- 2026-11-04 — Corregidos selector y tabla para usar el patrón canónico; ui:conformance sin deuda nueva.


- Revisión QA 2026-11-10 sobre HEAD 62351bb865ea8b1233ef8757b59feed2870a8c5e. Alex confirma funcionamiento en producción (aceptación funcional del usuario, no ejecución técnica contra producción). Suite focal PHP: 301/302; el fallo es CHG-027. Regresión adicional: 54/58, cuatro fallos existentes de shell/preview informados aparte. JS 44/44 y form-editor 3/3. Build correcto con aviso de chunk; conformance global falla por CHG-058 y deuda de CHG-054. CHG-059 concurrente excluido; no se alteró código funcional.
- TaskCalendar y JS afectados correctos. Suite actual task-calendar.interaction.spec.js: 3/3 a 390/768/1440, controles/menú/teclado/filtros/navegación. Suite tasks-calendar anterior detenida por selectores reemplazados, sin alterar tests para ocultarlo.

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-04 — Creado en to-do/ (Borrador, nivel M)
- 2026-11-04 — Usuario aprobó el plan consolidado y registro como tres CHG, sin implementar. Orden: CHG-015, CHG-016, CHG-017.
- Team Lead aplicó skills de Producto/Arquitectura/QA/Documentación y consultó UX/UI mediante agente independiente. Lineamientos generales/backlogs por rol ausentes; fuentes: código vigente y design-system.
- Perfil estandar de implementación, sin seleccionar proveedor ni modificar TEAM_MODELS.md.
- 2026-11-04 — Aprobado por el usuario (Compuerta 2)
- 2026-11-04 — Movido a in-progress/ (En desarrollo)
- 2026-11-04 — Implementación terminada → En verificación
- 2026-11-04 — Reabierto: Corregir dos desviaciones de conformidad UI detectadas después de la integración.
- 2026-11-04 — Implementación terminada → En verificación
- 2026-11-04 — Reabierto: La cabecera real no reproduce la composición aprobada; corregir controles y validar responsive.
- 2026-11-10 — Implementación terminada → En verificación
- 2026-11-10 — Cerrado por el usuario (Compuerta 3) → done/
