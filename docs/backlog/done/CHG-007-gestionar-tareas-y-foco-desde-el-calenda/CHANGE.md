# CHG-007 · Gestionar tareas y foco desde el calendario del mockup
**Estado:** Cerrado
**Nivel:** S
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-04 · **Aprobado:** 2026-11-04 · **Cerrado:** 2026-11-10
**Relacionado:** CHG-003, CHG-006

## 0. En palabras simples
Desde el calendario puedes leer y completar tareas, iniciar un temporizador o registrar foco. Las tareas sin fecha se gestionan en una tabla. Hoy y Ahora permanecen juntos y visibles.

## 1. Resumen
Cambio autorizado por el usuario tras elegir descripción/datos, completar, estados y Editar; tabla con búsqueda y acciones por fila; ambas opciones de foco con duración sugerida editable. Solo mockup en memoria.

## 3. UX/UI
Diálogo amplio con descripción legible, datos y estado, Completar principal, Foco desplegable y Editar discreto. Foco ofrece iniciar temporizador y registrar tiempo trabajado, sin mezclar notas y descripción de tarea. Sugerir duración por intervalo horario, luego estimación; sin duración pedirla. Recurrentes requieren confirmación antes de avanzar. Tabla Tarea, Categoría, Prioridad, Estado, Acciones, con búsqueda y Programar/Completar/Editar. Responsive, encabezado/cierre/pie visibles, foco y Escape.

## 4. Diseño técnico
Archivos: docs/mockup-system/mockups/tasks/screens/tasks-calendar.html.jinja, docs/mockup-system/mockups/tasks/scripts/task-calendar.js, docs/mockup-system/project-assets/mockups.css, docs/mockup-system/dist/tasks.html. Reutilizar clases y macro dialog y tabla de gestión. Todos los registros y temporizador locales a la pantalla; no backend, API, migración, persistencia ni cambios a otros módulos. La relación tarea-foco solo se simula. No completar tarea al guardar foco. Registro manual de tiempo pasado, máximo 16 horas como la app. Conservar drag, capas, vistas y FAB.

## 5. Pruebas
Compilar tasks con el motor; recorridos Playwright a 390/768/1440: Hoy/Ahora, descripción, completar/reabrir, recurrente, edición, foco manual/temporizador, sugerencia, validación, búsqueda y programar desde tabla. Teclado/Escape/foco, sin desbordamiento y regresión de pantallas de Tareas. ui:conformance y diff --check. Sin suites PHP ni build Vite porque solo cambia mockup.

## 6. Documentación
Bitácora en este CHG; capturas y script de verificación locales en storage/framework/testing. Documentos generales por rol ausentes; se aplica design-system.md y criterios UX/QA consultados en la sesión.

## 7. Tareas
- [x] T1 · Gestionar tareas, foco y navegación desde el calendario — Resp: dev-implementer — Archivos: docs/mockup-system/mockups/tasks/screens/tasks-calendar.html.jinja, docs/mockup-system/mockups/tasks/scripts/task-calendar.js, docs/mockup-system/project-assets/mockups.css — Hecho cuando: decisiones del usuario funcionales en memoria y accesibles.
- [x] T2 · Compilar y verificar las interacciones — Resp: dev-implementer — Archivos: docs/mockup-system/dist/tasks.html — Hecho cuando: build y recorridos a tres tamaños pasan.

## 8. Bitácora de implementación (dev-implementer)
- 2026-11-04 — Diálogo de tarea ampliado con descripción/datos, completar directo, cambiar estado, Editar y Foco (temporizador/registro manual). Foco separado de la descripción; relación local por ID y resumen de registros. Duración por intervalo horario, luego estimación; día completo usa estimación, sin estimación pide minutos. Temporizador registra tiempo real al finalizar, mínimo un minuto; cancelar descarta el temporizador local. No completa automáticamente la tarea.
- 2026-11-04 — Sin fecha ahora es tabla de gestión con búsqueda, categoría, prioridad, estado y Programar/Completar/Editar. Programar exige fecha y mueve la tarea al calendario conservando sus datos. En móvil las filas se apilan. Hoy y Ahora visibles y adyacentes, con navegación compacta que puede envolver para conservar objetivos de 44 px.
- 2026-11-04 — Playwright específico pasó a 390/768/1440: completar/reabrir, sugerencia 90 min para tarea horaria, foco manual y temporizador, filtros de búsqueda, programación, estados, edición, otras cuatro pantallas, sin desbordamiento ni errores de página. Recorrido adicional: recurrente confirmar/cancelar, estimación de 45 min en todo el día, duración desconocida vacía, rechazo de fecha futura, mínimo un minuto y finalización del temporizador con reloj simulado. Capturas calendar-management-dialog/table-* y scripts calendar-management*.verify.mjs en storage/framework/testing.
- 2026-11-04 — ui:conformance: sin deuda nueva, 345 inventariadas y 259 desviaciones previas. Diff --check correcto. Compilador verificó cuatro vistas; los recorridos específicos cubren además Calendario. Dos intentos iniciales fallaron por candidato modificado durante verificación al coexistir con un watcher --watch; se recompiló con éxito sin detener el watcher ni modificar el motor. Una compilación adicional aisló su candidato mediante contexto temporal.
- 2026-11-04 — No se modificó app real ni datos, dependencias, API o migraciones. El registro y asociación de foco se simulan, se pierden al salir/recargar. Sin commits ni publicación externa.

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-04 — Usuario autorizó ajustar el mockup con todas las decisiones de la conversación. Sin cambios de app real.
- 2026-11-04 — Aprobado por el usuario (Compuerta 2)
- 2026-11-04 — Movido a in-progress/ (En desarrollo)
- 2026-11-04 — Implementación terminada → En verificación
- 2026-11-10 — Cerrado por el usuario (Compuerta 3) → done/
