# CHG-006 · Calendario con máxima discreción en el mockup
**Estado:** En verificación
**Nivel:** S
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-04 · **Aprobado:** 2026-11-04 · **Cerrado:** —
**Relacionado:** CHG-003

## 0. En palabras simples
El calendario deja más espacio a los eventos: fecha visible, controles discretos y capas dentro de Mostrar. Abre tasks.html → Calendario para comprobarlo.

## 1. Resumen
Plan aprobado por el usuario en esta conversación. Solo mockup, sin cambios en la aplicación ni persistencia. Una variante de máxima discreción.

## 3. UX/UI
Fecha a la izquierda; Hoy, flechas, vistas, Mostrar con contador y menú de acciones a la derecha. Ahora y Actualizar dentro de ⋮. Capas con casillas y puntos de color, selección múltiple sin cierre y cero capas permitido. En móvil fecha arriba y una fila compacta de controles abajo. Objetivos de 44 px, foco visible, Escape y devolución de foco. Criterio UX y QA consultado mediante sus skills; documentos generales por rol ausentes.

## 4. Diseño técnico
Archivos: docs/mockup-system/mockups/tasks/screens/tasks-calendar.html.jinja, docs/mockup-system/mockups/tasks/scripts/task-calendar.js, docs/mockup-system/project-assets/mockups.css, docs/mockup-system/dist/tasks.html.
Reutilizar FullCalendar y menú existente, una sola cabecera responsive. Conservar eventos, arrastre, edición, estados y FAB. Sin cambios de API, datos reales, backend, dependencias ni otras pantallas. Dist solo mediante compilador.

## 5. Pruebas
Compilar tasks con python ../mockup-system/build.py --project docs/mockup-system tasks. Navegador aislado a 390, 768 y 1440: navegación, cuatro vistas, todas/ninguna capa, teclado, Escape, foco, cierre exterior, FAB y vista rápida. Regresión de Lista, Kanban, Planificación y Categorías; cero errores y sin desbordamiento. git diff --check sobre archivos afectados. No ejecutar suites Laravel ni build Vite porque no cambia la aplicación.

## 6. Documentación
Evidencia y decisiones en este CHG. La cabecera discreta es una divergencia intencional respecto a la fidelidad original de CHG-003.

## 7. Tareas
- [x] T1 · Compactar cabecera y organizar capas y acciones — Resp: dev-implementer — Archivos: docs/mockup-system/mockups/tasks/screens/tasks-calendar.html.jinja, docs/mockup-system/mockups/tasks/scripts/task-calendar.js, docs/mockup-system/project-assets/mockups.css — Hecho cuando: cabecera responsive accesible y todas las interacciones conservadas.
- [x] T2 · Compilar y verificar el prototipo — Resp: dev-implementer — Archivos: docs/mockup-system/dist/tasks.html — Hecho cuando: build, recorridos a tres tamaños y comprobación de diff pasan.

## 8. Bitácora de implementación (dev-implementer)
- 2026-11-04 — Cabecera única responsive: fecha, Hoy, flechas, vistas y Mostrar con cuatro casillas/contador; Ahora y Actualizar en ⋮. Selección múltiple sin cierre, cero capas, cierre exterior, Escape/foco y ArrowDown. En móvil Mostrar usa icono con nombre accesible para evitar truncar Semana.
- 2026-11-04 — Prueba Playwright específica a 1440, 768 y 390 px: cuatro vistas, navegación, filtros todas/ninguna, teclado, retorno de foco, acciones, vista rápida, FAB y ausencia de desbordamiento. Regresión de Lista, Kanban, Planificación y Categorías; cero errores de página. Script y capturas locales en storage/framework/testing/calendar-discreet*. No se añadió una suite permanente para este ajuste de mockup.
- 2026-11-04 — Compilador tasks: Playwright passed 4 isolated views, published. La verificación específica anterior cubre Calendario explícitamente, porque el motor anuncia cuatro vistas. ui:conformance: sin deuda nueva (345 inventariadas, 259 desviaciones preexistentes). Se corrigió un cierre prematuro del desplegable detectado en la primera prueba.
- 2026-11-04 — start --force permite este CHG concurrente sin cambiar los estados ni archivos de CHG-003/CHG-005. Se preservaron cambios previos del mockup y del resto del repositorio. Sin build Vite ni tests PHP: solo fuentes del prototipo.

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-04 — Usuario aprobó el plan completo y ordenó implementarlo. Sin preguntas pendientes.
- 2026-11-04 — Aprobado por el usuario (Compuerta 2)
- 2026-11-04 — Movido a in-progress/ (En desarrollo)
- 2026-11-04 — Implementación terminada → En verificación
