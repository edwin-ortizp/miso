# CHG-010 · Unificar diálogo de tarea con consulta y edición en el mismo modal
**Estado:** Cerrado
**Nivel:** S
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-04 · **Aprobado:** 2026-11-04 · **Cerrado:** 2026-11-10
**Relacionado:** CHG-007, CHG-008, CHG-009

## 0. En palabras simples
Una única ventana de tarea cambia entre consulta y edición sin cerrarse. Editar activa campos; Guardar aplica y vuelve a lectura; Cancelar descarta el borrador. Crear y Programar usan esa misma ventana.

## 1. Resumen
Usuario aprobó un morph mediante JavaScript, reutilizando el diálogo existente en vez de inventar otro. Solo mockup. El agente UX/UI confirmó modo explícito, foco/contexto preservados y separación de acciones inmediatas y borrador.

## 3. UX/UI
Composición de tarea Jinja basada en macro dialog canónica y patrón del partial edit-task-dialog de la app: contenido/descripción y panel de propiedades, secciones Información básica y Programación para campos soportados por el mockup. Consulta muestra datos/acciones; edición muestra título, descripción, categoría, estado, prioridad, tamaño, estimación y programación. Mismo nodo de diálogo/capa durante el morph. Pie de consulta Completar/Reabrir, Foco, Editar; pie editable solo Cancelar/Guardar. Categoría/estado inmediatos en consulta, borrador en edición. Escape cancela edición y vuelve a lectura; crear cancela/cierra. Preservar tarea/ID y foco asociado. Formularios de foco/confirmación recurrente son operaciones distintas y pueden seguir separados, según UX.

## 4. Diseño técnico
Archivos: docs/mockup-system/components/life-shell.html.jinja, docs/mockup-system/components/task-dialog.html.jinja (nuevo), docs/mockup-system/mockups/tasks/screens/tasks-calendar.html.jinja, docs/mockup-system/mockups/tasks/scripts/task-calendar.js, docs/mockup-system/project-assets/mockups.css, docs/mockup-system/dist/tasks.html. Extender macro dialog con slot de pie opcional compatible por defecto; composición compartida de tarea invoca esa macro. Eliminar modal manual lt-cal-detail y formulario reducido task-calendar-form. Un controlador de modos consulta/editar/crear/programar, copia de borrador, actualizar tarea/evento conservando ID/propiedades/capas. Mantener foco, categorías, fechas y recurrencia no editada. No importar Blade, no simular funciones de backend, no ampliar a otros módulos ni modificar app real.

## 5. Pruebas
Build tasks aislado del watcher. Playwright a 390/768/1440: identidad del mismo nodo/capa durante Editar/Guardar/Cancelar, valores y foco, descartar borrador sin revertir cambios inmediatos previos, descripción/categoría/estado/prioridad/tamaño/estimación/programación, validación de título y fechas, crear desde FAB/hueco y programar desde tabla. Preservar ID, duración, foco, recurrentes, colores, capas y filtros. Revisar teclado/scroll/objetivos44 y responsive; regresión de otras vistas y de macro dialog por defecto. ui:conformance y diff --check. Solo build mockup; sin suites PHP/Vite.

## 6. Documentación
Bitácora y evidencia local storage/framework/testing. No cambios de backend ni datos reales ni nuevas funcionalidades fuera del diálogo contextual.

## 7. Tareas
- [x] T1 · Unificar la ventana de tarea con modos y borrador — Resp: dev-implementer — Archivos: docs/mockup-system/components/life-shell.html.jinja, docs/mockup-system/components/task-dialog.html.jinja, docs/mockup-system/mockups/tasks/screens/tasks-calendar.html.jinja, docs/mockup-system/mockups/tasks/scripts/task-calendar.js, docs/mockup-system/project-assets/mockups.css — Hecho cuando: mismo modal en consulta/edición/crear/programar y acciones preservadas.
- [x] T2 · Compilar y validar el morph y las regresiones — Resp: dev-implementer — Archivos: docs/mockup-system/dist/tasks.html — Hecho cuando: build, recorridos a tres tamaños y checks pasan.

## 8. Bitácora de implementación (dev-implementer)
- 2026-11-04 — Composición task-dialog.html.jinja reutiliza macro dialog y secciones canónicas; custom_footer opcional permite acciones por modo sin duplicar cabecera/capa/pie. Compatibilidad del caller original comprobada con Jinja y formularios Foco/recurrente. Eliminados lt-cal-detail manual y task-calendar-form separado. Una instancia #task-dialog para consulta/editar/crear/programar; campos muestran/ocultan y se deshabilitan al salir de edición.
- 2026-11-04 — Morph preserva identidad DOM/capa, tarea/ID, foco y estado previo. Edición con borrador para título/descripción/categoría/estado/prioridad/tamaño/estimación/inicio/fin. Guardar aplica, vuelve a consulta y actualiza calendario/tabla; Cancelar/Escape/X descartan edición y vuelven a consulta (crear cierra). Las acciones inmediatas previas de consulta no se revierten. Completar/Reabrir y Foco disponibles en consulta; pie editable solo Cancelar/Guardar. Programar abre sección Programación. Recurrencia no editada se conserva y exige fecha antes de guardar.
- 2026-11-04 — Playwright task-morph.verify.mjs pasó a 390/768/1440: mide identidad del mismo nodo role=dialog y capa, cero mutaciones hidden durante consulta/editar/cancelar/guardar; prueba campos, borrador, categoría inmediata, foco asociado por ID tras editar, guardar/validar, completar/reabrir, FAB y tabla, otras cuatro pantallas y sin desbordamiento/errores. Capturas task-morph-view/edit-* en storage/framework/testing.
- 2026-11-04 — task-morph-edge.verify.mjs pasó: confirmación/cancelación de recurrente desde edición, fechas inválidas conservan borrador, duración todo-el-día conservada, temporizador finalizado con reloj simulado, creación desde hueco y edición sin fecha. Regresiones de filtros y categoría a tres tamaños con scripts chg010-calendar-*. Sin errores de página.
- 2026-11-04 — UX independiente confirmó composición/morph y encontró bloqueo al borrar fechas de recurrente antes de completar. Se corrigió antes de cualquier mutación; probado con Done y Ready, Cancelar conserva original, luego completar funciona. Se corrigió también el cierre prematuro por propagación de clic de FullCalendar hacia el shell canónico, sin cambiar el shell global.
- 2026-11-04 — Build tasks con candidato aislado: Playwright passed 4 isolated views, published. ui:conformance sin deuda nueva (345 inventariadas, 259 desviaciones previas). Diff --check correcto; hash de candidato y salida compilada coincidente. Sin app real, backend, datos, commits ni publicación externa.

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-04 — Usuario aprobó reutilización y cambio en el mismo modal sin abrir otros para editar. UX independiente confirmó el enfoque y alcance de campos soportados; foco/confirmación pueden seguir separados.
- 2026-11-04 — Aprobado por el usuario (Compuerta 2)
- 2026-11-04 — Movido a in-progress/ (En desarrollo)
- 2026-11-04 — Implementación terminada → En verificación
- 2026-11-10 — Cerrado por el usuario (Compuerta 3) → done/
