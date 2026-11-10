# CHG-012 · Propiedades siempre editables con guardado único en el diálogo de tarea
**Estado:** Cerrado
**Nivel:** S
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-04 · **Aprobado:** 2026-11-04 · **Cerrado:** 2026-11-10
**Relacionado:** CHG-010, CHG-011

## 0. En palabras simples
Toda la columna derecha se puede editar siempre. Nada cambia en la tarea hasta Guardar; Cancelar descarta tanto propiedades como contenido. Editar solo activa el contenido central sin reiniciar lo que ya elegiste a la derecha.

## 1. Resumen
Usuario eligió explícitamente cambios pendientes hasta Guardar también en consulta. Sustituye el guardado inmediato anterior de categoría/estado dentro del diálogo. Solo mockup.

## 3. UX/UI
Mismos selectores Categoría/Estado/Prioridad/Tamaño y campo Tiempo estimado siempre visibles. Guardar/Cancelar en ambos modos, indicador Cambios pendientes. Consulta mantiene Editar y Completar/Reabrir (prepara Estado, requiere Guardar). Editar revela centro conservando propiedades. Cancelar edición descarta todo y vuelve a consulta; Cancelar consulta/X/Escape cierran descartando. Foco sigue disponible y guarda registros independientes, no confirma el borrador. Confirmación recurrente antes de aplicar cualquier cambio, cancelar confirmación conserva borrador sin aplicar nada.

## 4. Diseño técnico
Archivos: docs/mockup-system/components/task-dialog.html.jinja, docs/mockup-system/mockups/tasks/scripts/task-calendar.js, docs/mockup-system/project-assets/mockups.css, docs/mockup-system/dist/tasks.html. Borrador único poblado al abrir; snapshot para pendientes; quitar split/lecturas duplicadas de estado/prioridad y listeners de guardado inmediato. Save en consulta usa misma tarea/ID y programación cargada, confirmación recurrente transaccional antes de cualquier mutación. Mantener morph y Foco con borrador actual. No modificar aplicación, datos ni componentes globales.

## 5. Pruebas
Compilar tasks en candidato aislado. Playwright 390/768/1440: todos campos habilitados en consulta, ninguna mutación antes de Guardar, cancelar/reabrir conserva original, morph conserva borrador, save actualiza categoría/color/tabla/estado, guardar sin duplicar ni alterar fechas, Foco no borra ni guarda propiedades. Confirmación recurrente cancelada no aplica ningún campo; validaciones conservan borrador. Regresión filtros, creación/programación, foco/recurrencia, sidebar responsive, sin errores ni overflow. ui:conformance y diff --check.

## 6. Documentación
Decisión sustituye explícitamente guardado inmediato de CHG-008/010. Bitácora, capturas y scripts locales en storage/framework/testing.

## 7. Tareas
- [x] T1 · Unificar el borrador y los controles siempre editables — Resp: dev-implementer — Archivos: docs/mockup-system/components/task-dialog.html.jinja, docs/mockup-system/mockups/tasks/scripts/task-calendar.js, docs/mockup-system/project-assets/mockups.css — Hecho cuando: todos cambios requieren Guardar, sin perder el morph ni Foco.
- [x] T2 · Compilar y comprobar guardado/cancelación — Resp: dev-implementer — Archivos: docs/mockup-system/dist/tasks.html — Hecho cuando: build y recorridos pasan.

## 8. Bitácora de implementación (dev-implementer)
- 2026-11-04 — Columna derecha siempre editable con un único juego de campos Categoría/Estado/Prioridad/Tamaño/Estimación. Quitadas lecturas y split duplicados. Guardar/Cancelar en consulta y edición; snapshot y feedback de pendientes. Editar contenido solo revela campos centrales, no recarga ni pierde borrador. Guardar sin cambios deshabilitado en tareas existentes.
- 2026-11-04 — Todos los campos de tarea se aplican únicamente con Guardar. Completar/Reabrir solo cambia estado del borrador y anuncia Estado pendiente de guardar. Cancelar consulta cierra descartando; Cancelar edición descarta todo y vuelve a consulta limpia con feedback. Foco sigue independiente, usa valores actuales del borrador y vuelve sin guardarlos ni borrarlos.
- 2026-11-04 — Guardado recurrente validado y confirmado antes de modificar cualquier campo/evento. Cancelar confirmación deja borrador intacto y no aplica categoría/estado/fechas. Confirmar aplica edición y avanza la repetición. No cambia la aplicación real ni hay persistencia.
- 2026-11-04 — task-properties-draft.verify.mjs pasó a 390/768/1440: cinco controles visibles/habilitados en consulta, colores/tabla sin cambios hasta Guardar, Cancelar/reabrir originales, morph conserva borrador/identidad, guardar y completar/reabrir pendientes, Foco independiente y sin desbordamiento/errores. Capturas task-properties-draft-* en storage/framework/testing.
- 2026-11-04 — task-properties-draft-edge.verify.mjs pasó: atomicidad de confirmación recurrente, cancelar/save, validación de estimación en consulta, crear/programar, temporizador con reloj simulado conserva borrador y foco confirmado, otras cuatro pantallas. Filtros regresión a tres tamaños pasó con chg012-calendar-filters.verify.mjs. Build tasks aislado: Playwright passed 4 isolated views, published. ui:conformance sin deuda nueva (345 inventariadas, 259 desviaciones previas), diff --check correcto.

- 2026-11-04 — UX independiente revisó código/capturas de CHG-012: sin bloqueantes, guardado único y retorno de Foco coherentes; confirmación recurrente antes de mutar verificada.

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-04 — Usuario eligió mantener todos los cambios pendientes hasta Guardar, también desde consulta. Se conserva alcance mockup en memoria.
- 2026-11-04 — Aprobado por el usuario (Compuerta 2)
- 2026-11-04 — Movido a in-progress/ (En desarrollo)
- 2026-11-04 — Implementación terminada → En verificación
- 2026-11-10 — Cerrado por el usuario (Compuerta 3) → done/
