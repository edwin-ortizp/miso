# CHG-009 · Cabecera de calendario con filtros estándar y Ahora
**Estado:** Cerrado
**Nivel:** S
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-04 · **Aprobado:** 2026-11-04 · **Cerrado:** 2026-11-10
**Relacionado:** CHG-006, CHG-007, CHG-008

## 0. En palabras simples
El calendario conserva Ahora y elimina Hoy. Los tipos se eligen en Filtros con el panel habitual del sistema. Desaparecen Mostrar 4 y el menú de Actualizar.

## 1. Resumen
Usuario aprobó aplicar la revisión real del agente UX/UI: simplificar controles y reutilizar patrón de filtros. Solo mockup, sin modificar app ni componentes globales.

## 3. UX/UI
Cabecera: flechas, Ahora, vistas y Filtros. Reusar lt-filter-button, lt-popover con título/cierre/acciones, casillas nativas en filas lt-list-item y lt-applied-filters. En móvil el popover es hoja inferior del sistema. Grupo Tipos de elemento con Tareas, Planes, Cumpleaños y Comidas. Todas seleccionadas = sin restricciones, sin badge ni chip. Restricción = un filtro activo, chip de tipos con acción quitar. Permitir cero tipos con mensaje explícito. Selecciones provisionales se aplican con Filtrar; Limpiar restaura todas. Escape/exterior cancela borrador, devuelve foco al cerrar. Ahora conserva comportamiento existente. No usar manage_card para envolver calendario.

## 4. Diseño técnico
Archivos: docs/mockup-system/mockups/tasks/screens/tasks-calendar.html.jinja, docs/mockup-system/mockups/tasks/scripts/task-calendar.js, docs/mockup-system/project-assets/mockups.css, docs/mockup-system/dist/tasks.html. Reutilizar estilos estándar sin variantes nuevas de control; CSS específico solo composición/objetivos táctiles. Eliminar controles/handlers/estilos obsoletos de Mostrar/Hoy/Actualizar en cabecera. Mantener menú de Foco y comportamiento de tareas, colores, creación y tabla. Estado local en memoria.

## 5. Pruebas
Build tasks con candidato aislado del watcher. Playwright a 390/768/1440: controles ausentes, Ahora visible y funcionando desde las cuatro vistas, filtros provisional/aplicar/limpiar/quitar/cancelar, ninguno/todos, badge, chip, Escape/exterior/foco/teclado y hoja móvil, sin desbordamiento y cero errores. Regresión de edición/categoría/completar/foco/tabla y pantallas hermanas. ui:conformance y diff --check. Sin pruebas PHP ni build Vite porque solo cambia mockup.

## 6. Documentación
Registrar revisión UX independiente y validaciones en bitácora. Capturas/scripts locales en storage/framework/testing. No cambiar documentos generales ausentes ni componentes globales.

## 7. Tareas
- [x] T1 · Simplificar cabecera y reutilizar filtros estándar — Resp: dev-implementer — Archivos: docs/mockup-system/mockups/tasks/screens/tasks-calendar.html.jinja, docs/mockup-system/mockups/tasks/scripts/task-calendar.js, docs/mockup-system/project-assets/mockups.css — Hecho cuando: revisión UX aplicada y filtros funcionales/accesibles.
- [x] T2 · Compilar y verificar la revisión — Resp: dev-implementer — Archivos: docs/mockup-system/dist/tasks.html — Hecho cuando: compilación y recorridos responsive pasan.

## 8. Bitácora de implementación (dev-implementer)
- 2026-11-04 — Aplicada revisión independiente UX: Ahora único y visible, eliminados Hoy/Mostrar 4/menú Actualizar; selector de vistas conservado. Filtros usa lt-filter-button, lt-popover con encabezado/cierre/acciones/handle, casillas nativas en filas lt-list-item y lt-applied-filters. No se agregó manage_card al calendario ni se modificaron componentes compartidos.
- 2026-11-04 — Tipos como borrador: Filtrar aplica, Limpiar selecciona todos, Escape/exterior/cierre descartan borrador. Todas = sin badge/chip; restricciones = badge de un criterio y chip con tipos; cero = Ninguno y mensaje claro. Quitar filtro restaura todo. Teclado/foco, objetivos 44 px; móvil hoja estándar con scrim que no sombrea su contenido. Altura del panel compartida al pseudo-elemento mediante variable CSS local.
- 2026-11-04 — Primera prueba detectó que usar una opción multiselect fuera del panel requerido disparaba un handler del shell; corregido reutilizando fila estándar con checkbox nativo. Sin cambios al shell global. Candidato de build aislado del watcher existente.
- 2026-11-04 — Playwright específico pasó a 390/768/1440: navegación Ahora desde las cuatro vistas, controles eliminados ausentes, filtro provisional/aplicar/reset/quitar/ninguno, badge/chip, Escape, foco, cierre exterior/scrim móvil, geometría de hoja y ausencia de desbordamiento; cero errores de página. Regresión de gestión/foco/recurrencia/edición y colores pasó en las tres medidas con scripts locales chg009-calendar-*. Capturas calendar-standard-* y script calendar-standard-filters.verify.mjs en storage/framework/testing.
- 2026-11-04 — ui:conformance sin deuda nueva (345 inventariadas, 259 desviaciones previas); diff --check correcto. Solo prototipo, sin backend/datos/commits/publicación externa.

- 2026-11-04 — Segunda revisión read-only de ux_calendar_review confirmó Ahora único, Filtros estándar, sin Mostrar 4/⋮, indicador solo al restringir y casillas compatibles. Señaló la excepción que mostraba ✕ en móvil contra design-system.md:171; retirada y comprobado el cierre estándar con scrim/Escape. Compilación final: Playwright passed 4 isolated views, published.

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-04 — Revisión independiente del agente ux_calendar_review: Hoy/Ahora redundantes para esta superficie; Mostrar 4 inconsistente con patrón estándar; ⋮ solo presenta toast sin acción real. Usuario autorizó aplicar la revisión.
- 2026-11-04 — Aprobado por el usuario (Compuerta 2)
- 2026-11-04 — Movido a in-progress/ (En desarrollo)
- 2026-11-04 — Implementación terminada → En verificación
- 2026-11-10 — Cerrado por el usuario (Compuerta 3) → done/
