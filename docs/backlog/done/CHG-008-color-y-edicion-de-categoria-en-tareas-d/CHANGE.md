# CHG-008 · Color y edición de categoría en tareas del calendario mockup
**Estado:** Cerrado
**Nivel:** S
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-04 · **Aprobado:** 2026-11-04 · **Cerrado:** 2026-11-10
**Relacionado:** CHG-007

## 0. En palabras simples
Las tareas del calendario usan el color de su categoría. Puedes cambiarla desde el diálogo y ver el cambio al instante, también en Sin fecha.

## 1. Resumen
Usuario aprobó empezar solo por tareas en el mockup. Fondos suaves y borde de categoría, neutro sin categoría; prioridad mediante indicador sin sustituir el color. El resto de capas conserva colores y comportamiento.

## 3. UX/UI
Selector Categoría visible, etiquetado y accesible dentro del diálogo, con Sin categoría y categorías de ejemplo. Actualización inmediata en memoria; se mantiene el diálogo abierto y se anuncia el cambio. Conservar iconos de tipo, completadas y recurrentes. Objetivos de 44 px y contraste de texto legible.

## 4. Diseño técnico
Archivos: docs/mockup-system/mockups/tasks/scripts/task-calendar.js, docs/mockup-system/mockups/tasks/screens/tasks-calendar.html.jinja, docs/mockup-system/project-assets/mockups.css, docs/mockup-system/dist/tasks.html. Mapa local de categorías y colores ficticios, sin cambiar catálogo compartido. Reutilizar clases FullCalendar para fondos/bordes/texto y selector nativo. Actualizar extendedProps y clases al cambiar categoría, renderizar tabla si procede. Sin backend, persistencia, API, migraciones ni cambios a otras capas.

## 5. Pruebas
Build tasks con candidato aislado del watcher existente. Playwright a 390/768/1440: cambiar categoría y limpiar, reabrir, cambiar estado sin perder color, editar/programar sin perder categoría, Sin fecha sincronizado, prioridad como icono, Mes/Semana/Día/Agenda, capas no tarea sin cambios, cero errores y sin desbordamiento. ui:conformance y diff --check. No build Vite ni suites PHP porque solo cambia el mockup.

## 6. Documentación
Bitácora de evidencias en este CHG. La app dispone de color en TaskCategory; la selección y el mapa local del prototipo no cambian el catálogo real ni el mockup compartido de categorías.

## 7. Tareas
- [x] T1 · Colorear y cambiar categoría desde el diálogo — Resp: dev-implementer — Archivos: docs/mockup-system/mockups/tasks/scripts/task-calendar.js, docs/mockup-system/mockups/tasks/screens/tasks-calendar.html.jinja, docs/mockup-system/project-assets/mockups.css — Hecho cuando: color por categoría, neutro y edición inmediata funcionando.
- [x] T2 · Compilar y comprobar comportamiento responsive — Resp: dev-implementer — Archivos: docs/mockup-system/dist/tasks.html — Hecho cuando: build, pruebas a tres tamaños y diff pasan.

## 8. Bitácora de implementación (dev-implementer)
- 2026-11-04 — Categorías de ejemplo Personal (violeta), Trabajo (azul), Trabajo / Proyectos (turquesa), Finanzas (verde) y Sin categoría (neutro). Fondo suave/borde/texto para eventos de rejilla; Agenda usa el punto de categoría. Colores limitados a tareas. Prioridad urgente/importante usa bandera con nombre accesible y conserva el color de categoría.
- 2026-11-04 — Selector nativo etiquetado en diálogo, objetivo de 44 px, actualización inmediata de extendedProps y clases de FullCalendar. Actualiza Sin fecha sin cerrar el diálogo y anuncia el resultado mediante role=status. Conserva categoría al editar/programar y al completar/reabrir. Catálogo compartido y app real intactos; mapa de colores solo ficticio en memoria.
- 2026-11-04 — Build aislado de watcher: Playwright passed 4 isolated views, published. Prueba específica calendar-category.verify.mjs a 390/768/1440: color calculado, neutral, categoría al reabrir, estados, vistas, tabla, programar, planes conservan color, sin desbordamiento y cero errores. Revisión visual móvil y capturas calendar-category*.png en storage/framework/testing. Agenda comprobada con punto de categoría.
- 2026-11-04 — Regresión de gestión/foco con scripts calendar-management.verify.mjs y calendar-management-edge.verify.mjs. ui:conformance sin deuda nueva (345 inventariadas, 259 desviaciones previas). Sin cambios backend ni persistencia; no se ejecutaron suites PHP ni build Vite.

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-04 — Usuario confirmó tareas inicialmente, dentro del trabajo actual del mockup. Conserva el alcance solo local.
- 2026-11-04 — Aprobado por el usuario (Compuerta 2)
- 2026-11-04 — Movido a in-progress/ (En desarrollo)
- 2026-11-04 — Implementación terminada → En verificación
- 2026-11-10 — Cerrado por el usuario (Compuerta 3) → done/
