# CHG-011 · Foco permanente en la columna lateral del diálogo de tarea
**Estado:** En verificación
**Nivel:** S
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-04 · **Aprobado:** 2026-11-04 · **Cerrado:** —
**Relacionado:** CHG-010

## 0. En palabras simples
Foco vive en la columna derecha de la tarea y permanece disponible al editar. La columna tiene fondo sutil y separador, en lugar de parecer una tarjeta aislada.

## 1. Resumen
Usuario autorizó mover Foco a la derecha y mantenerlo disponible en todos los modos. Solo mockup; preservar morph y borrador al usar Foco.

## 3. UX/UI
Mover resumen y acción Foco al pie de la columna de propiedades, fuera del footer de consulta. Misma ubicación al consultar/editar/programar/crear. Fondo de superficie del sistema y divisor vertical en escritorio; apilar con divisor horizontal en móvil. Conservar header/cierre/footer y objetivos de 44 px. Abrir/cerrar/registrar foco devuelve al modo, sección y borrador anteriores sin cambiar propiedades. Sugerencia según programación/estimación actual del borrador. En creación requiere título; reservar ID local para asociar foco al guardar. Cancelar creación no guarda tarea: registros de foco ya confirmados se conservan separados y se avisa que quedan sin tarea.

## 4. Diseño técnico
Archivos: docs/mockup-system/components/task-dialog.html.jinja, docs/mockup-system/mockups/tasks/scripts/task-calendar.js, docs/mockup-system/project-assets/mockups.css, docs/mockup-system/dist/tasks.html. Reubicar nodos existentes, conservar componente canónico y formularios de foco independientes. backToTask solo renderiza propiedades en consulta, sin sobreescribir categoría ni valores editables del borrador. Preservar scroll y foco; mantener IDs y registros. No cambios de aplicación, backend, dependencias ni componentes globales.

## 5. Pruebas
Compilador tasks aislado de watcher. Playwright a 390/768/1440: sidebar con fondo/divisor, Foco consulta/editar/crear/programar; cancelar y registrar foco preservan borrador/categoría/sección/scroll, sugerencia desde borrador, Guardar/Cancelar tarea mantienen separación de datos; creación con título y sin título, asociación o desvinculación al cancelar. Responsive/foco y regresión de morph/categorías/filtros/recurrencia. ui:conformance y diff --check.

## 6. Documentación
Bitácora y capturas locales en storage/framework/testing. Prototipo solo en memoria, no cambia reglas reales de Pomodoro.

## 7. Tareas
- [x] T1 · Mantener Foco en la columna de propiedades — Resp: dev-implementer — Archivos: docs/mockup-system/components/task-dialog.html.jinja, docs/mockup-system/mockups/tasks/scripts/task-calendar.js, docs/mockup-system/project-assets/mockups.css — Hecho cuando: acción persistente y retorno sin pérdida de borrador.
- [x] T2 · Compilar y validar consulta y edición responsive — Resp: dev-implementer — Archivos: docs/mockup-system/dist/tasks.html — Hecho cuando: build y recorridos pasan.

## 8. Bitácora de implementación (dev-implementer)
- 2026-11-04 — Movidos resumen y acción Foco al aside de propiedades sin condicionarlos al modo. Columna completa con token md-surface-low y divisor vertical, sin borde redondeado de tarjeta. En móvil apila con divisor horizontal. Footer de consulta conserva Editar/Completar; edición mantiene Cancelar/Guardar.
- 2026-11-04 — Retorno desde foco conserva modo, campos, categoría, sección, scroll y foco sin renderizar datos guardados encima del borrador. Sugerencia usa horario/estimación editados. Nuevas tareas reservan ID local y requieren título para Foco; guardar conserva asociación. Cancelar creación no guarda tarea y desvincula registros ya confirmados, con aviso visible; otro borrador no hereda registros.
- 2026-11-04 — task-focus-sidebar.verify.mjs pasó a 390/768/1440: ubicación/estilo, Foco en consulta/editar/programar/crear, cancelación y registro manual preservan borrador/categoría/sección, duración de borrador, asociación al guardar, validación título y sin desbordamiento/errores. Prueba adicional 1280x720 pasó: menú y registro accesibles, cancelar creación no guarda tarea, aviso visible y aislamiento entre borradores. Capturas task-focus-sidebar-view/edit-* en storage/framework/testing.
- 2026-11-04 — Regresiones morph (incluye identidad DOM/capa), recurrencia/temporizador, filtros y colores pasaron con scripts chg011-* a los tres tamaños. UX independiente revisó código y capturas, sin bloqueantes. ui:conformance sin deuda nueva (345 inventariadas, 259 desviaciones previas), diff --check correcto. Build aislado del watcher: Playwright passed 4 isolated views, published. No app real, backend, datos, commits ni publicación externa.

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-04 — Usuario pidió Foco a la derecha incluso en edición, columna con separador/fondo sutil. Se conservan decisiones previas de diálogo único y foco manual/temporizador.
- 2026-11-04 — Aprobado por el usuario (Compuerta 2)
- 2026-11-04 — Movido a in-progress/ (En desarrollo)
- 2026-11-04 — Implementación terminada → En verificación
