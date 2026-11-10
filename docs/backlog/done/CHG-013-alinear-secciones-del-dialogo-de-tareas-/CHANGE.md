# CHG-013 · Alinear secciones del diálogo de tareas del mockup con la app
**Estado:** Cerrado
**Nivel:** S
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-04 · **Aprobado:** 2026-11-04 · **Cerrado:** 2026-11-10
**Relacionado:** CHG-010, CHG-011, CHG-012

## 0. En palabras simples
El modal del mockup tendrá las mismas cinco secciones de la app: Información básica, Privacidad, Programación, Recordatorios y Relacionado, en ese orden. Mantiene el mismo diálogo y la columna derecha editable.

## 1. Resumen
Alinear estructura y contenido del modal de tareas con el partial edit-task-dialog y TaskEditor vigentes. En consulta se pueden recorrer todas las secciones; Editar activa sus campos sin perder el borrador. Solo mockup con datos ficticios.

## 3. UX/UI
Reutilizar nav y secciones del diálogo canónico, incluido menú lateral móvil. Privacidad: Tarea privada. Programación: fechas/horas, duración 15/30/60/120 min y recurrencia con intervalo de días. Recordatorios: texto de anticipación/9am Bogotá, filas de cantidad/unidad, Añadir/Quitar. Relacionado: filas módulo y relación con búsqueda nativa, módulos Personas/Metas/Salud como la app. Consulta presenta valores/estados vacíos y edición usa controles; cinco secciones y propiedades/Foco siempre accesibles. Guardar/Cancelar aplica o descarta todos los campos.

## 4. Diseño técnico
Archivos: docs/mockup-system/components/task-dialog.html.jinja, docs/mockup-system/mockups/tasks/scripts/task-calendar.js, docs/mockup-system/project-assets/mockups.css, docs/mockup-system/dist/tasks.html. Draft serializa checks/intervalo/recordatorios/relaciones para pendientes, se carga/clona sin compartir arrays entre tareas. Persistencia solo en objetos en memoria. Reminder units reales minutes/hours/days/weeks/months/years; duplicados y recordatorio sin fecha muestran aviso local, sin enviar notificaciones. Relaciones ficticias con elección dependiente de módulo, cambio limpia destino y al guardar deduplica. Duración usa inicio o ahora, aplica fin+estimación como InteractsWithTaskSchedule. Recurrencia simple respeta intervalo elegido; reglas nativas existentes se preservan hasta Cambiar. Mantener estado previo en cancelaciones/confirmaciones. No tocar Laravel, Blade, servicios reales ni taxonomías.

## 5. Pruebas
Build tasks aislado. Playwright a 390/768/1440: cinco secciones/orden accesibles consulta/editar/crear, misma capa, privacidad, filas recordatorios/relaciones, validación, guardar/cancelar/reabrir sin fuga, duración/intervalo recurrencia, columna/Foco, menú móvil, teclado/Escape y sin overflow/errores. Regresión guardado único, filtros, otras pantallas. ui:conformance/diff --check. No suites PHP ni build Vite porque solo mockup.

## 6. Documentación
Fuentes: resources/views/livewire/task/partials/edit-task-dialog.blade.php, schedule-fields.blade.php, notifications/reminder-fields.blade.php; TaskEditor.php, InteractsWithTaskRelations, ManageReminders e InteractsWithTaskSchedule. No pretende enviar recordatorios ni asociar registros reales. Bitácora y capturas locales.

## 7. Tareas
- [x] T1 · Completar secciones del modal según la app — Resp: dev-implementer — Archivos: docs/mockup-system/components/task-dialog.html.jinja, docs/mockup-system/mockups/tasks/scripts/task-calendar.js, docs/mockup-system/project-assets/mockups.css — Hecho cuando: cinco secciones funcionales en memoria con borrador único.
- [x] T2 · Compilar y validar alineación responsive — Resp: dev-implementer — Archivos: docs/mockup-system/dist/tasks.html — Hecho cuando: build y recorridos pasan.

## 8. Bitácora de implementación (dev-implementer)
- 2026-11-04 — Alineadas cinco secciones en orden con edit-task-dialog/TaskEditor: Información básica, Privacidad, Programación, Recordatorios y Relacionado. Navegación visible en consulta/edición/crear; menú canónico móvil. Mismo nodo/capa y columna lateral/Foco, sin composición nueva. Editar conserva la sección elegida.
- 2026-11-04 — Privacidad con Tarea privada. Programación añade duración 15/30/60/120, aplica inicio o ahora/fin/estimación y muestra intervalo de recurrencia. Intervalo participa en confirmación/avance; reglas nativas se conservan y el mockup no simula completarlas silenciosamente. Recordatorios con cantidades desde cero, seis unidades reales, validación de duplicados/sin fecha. Relaciones Personas/Metas/Salud con opciones ficticias dependientes del módulo y búsqueda nativa; cambiar módulo limpia destino y guardar deduplica.
- 2026-11-04 — Nuevos campos/filas forman parte del snapshot y borrador único. Guardar/Cancelar, Foco y cambio de sección no filtran datos entre tareas. Consulta muestra resúmenes/estados vacíos; validaciones abren la sección afectada y activan edición si hace falta. Errores obsoletos se limpian al corregir o navegar. Tamaños usan etiquetas de la app.
- 2026-11-04 — task-tabs-alignment.verify.mjs pasó a 390/768/1440: cinco entradas/orden/consulta/editar, privacidad, recordatorios/relaciones, validación y cancelación, Foco preserva datos, recurrencia cada 3 días y duración. Regresiones chg013-task-properties-draft*.verify.mjs y chg013-calendar-standard-filters.verify.mjs pasaron: guardado único, confirmación recurrente atómica, creación/programación, foco/temporizador, otras cuatro pantallas, cero errores y sin desbordamiento. Capturas task-tabs-* en storage/framework/testing; captura final Relacionado regenerada desde HTML publicado sin mensaje residual.
- 2026-11-04 — Compilador tasks aislado de watcher: Playwright passed 4 isolated views, published. Hash de candidato y dist coinciden. ui:conformance sin deuda nueva (345 inventariadas, 259 desviaciones previas), diff --check correcto. UX independiente confirmó fuentes/código/HTML y validación en navegador, sin bloqueantes. Sin app real, datos reales, notificaciones, backend, commits ni publicación externa.

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-04 — Usuario pidió alinear las pestañas faltantes con app real. TaskEditor sí provee las relaciones, por eso se incluyen las cinco secciones.
- 2026-11-04 — Aprobado por el usuario (Compuerta 2)
- 2026-11-04 — Movido a in-progress/ (En desarrollo)
- 2026-11-04 — Implementación terminada → En verificación
- 2026-11-10 — Cerrado por el usuario (Compuerta 3) → done/
