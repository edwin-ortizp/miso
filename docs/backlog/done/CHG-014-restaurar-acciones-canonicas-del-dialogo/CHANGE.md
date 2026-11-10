# CHG-014 · Restaurar acciones canónicas del diálogo de tarea
**Estado:** Cerrado
**Nivel:** S
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-04 · **Aprobado:** 2026-11-04 · **Cerrado:** 2026-11-10
**Relacionado:** CHG-010, CHG-012, CHG-013

## 0. En palabras simples
Editar pasa a ser un icono en la cabecera junto a ampliar y cerrar. El estado se cambia únicamente en la columna derecha. El pie vuelve al diseño habitual: Cancelar a la izquierda y Guardar a la derecha.

## 1. Resumen
Aplicar corrección del usuario y contrato docs/design-system.md:146. No hacen falta acciones adicionales ahora; no se inventa un split de Guardar sin acciones reales.

## 3. UX/UI
Icono lápiz etiquetado Editar contenido en cabecera, visible en consulta, activa el mismo morph. Eliminar botones Completar/Reabrir duplicados del estado. Pie canónico con solo Cancelar/Guardar; feedback de borrador en columna derecha, fuera del pie. Conservar cinco secciones, propiedades siempre editables, Foco y guardado único. Acciones adicionales futuras deben ir al split de Guardar, no ocupar el footer.

## 4. Diseño técnico
Archivos: docs/mockup-system/components/life-shell.html.jinja, docs/mockup-system/components/task-dialog.html.jinja, docs/mockup-system/mockups/tasks/scripts/task-calendar.js, docs/mockup-system/project-assets/mockups.css, docs/mockup-system/dist/tasks.html. Macro dialog admite slot de cabecera opcional, compatible por defecto. La composición task_dialog reutiliza footer estándar de macro (sin custom_footer); cuerpo y cabecera comparten caller por zonas solo al solicitar slots. Eliminar handlers/selectores de Complete y estilos del pie personalizado. Selector de Estado conserva Done/Ready y confirmación recurrente. Sin cambios de app ni componentes Blade.

## 5. Pruebas
Build tasks aislado del watcher. Playwright 390/768/1440: orden Editar/Ampliar/Cerrar en cabecera, Editar morph sin cerrar, footer solo Cancelar/Guardar con geometría izquierda/derecha, sin Completar/Reabrir, guardado/cancelación, cinco secciones y Foco; completar/reabrir vía Estado, confirmación recurrente y atomicidad. Compatibilidad por defecto de macro y regresión campos/filtros/otras pantallas, cero errores/overflow. ui:conformance y diff --check.

## 6. Documentación
Bitácora y evidencia local en storage/framework/testing; no cambios al contrato global de diseño, ya especifica el comportamiento pedido.

## 7. Tareas
- [x] T1 · Restaurar cabecera y pie canónicos — Resp: dev-implementer — Archivos: docs/mockup-system/components/life-shell.html.jinja, docs/mockup-system/components/task-dialog.html.jinja, docs/mockup-system/mockups/tasks/scripts/task-calendar.js, docs/mockup-system/project-assets/mockups.css — Hecho cuando: solo Cancelar/Guardar en pie y edición contextual arriba.
- [x] T2 · Compilar y validar disposición y acciones — Resp: dev-implementer — Archivos: docs/mockup-system/dist/tasks.html — Hecho cuando: build, pruebas responsive y checks pasan.

## 8. Bitácora de implementación (dev-implementer)
- 2026-11-04 — Editar contenido es lápiz etiquetado en slot opcional de cabecera, antes de ampliar/cerrar. Task_dialog deja de usar custom_footer y reutiliza el pie predeterminado de macro dialog. Solo Cancelar izquierda y Guardar derecha. Eliminados botones/handlers/selectores Completar/Reabrir duplicados; Estado mantiene ese flujo con guardado pendiente y confirmación recurrente. Feedback del borrador fuera del pie, dentro de columna de propiedades. Sin split inventado, no hay acciones extra que lo necesiten.
- 2026-11-04 — task-canonical-actions.verify.mjs pasó en 390/768/1440: lápiz/orden/44px, solo dos botones en footer, geometría izquierda/derecha, morph mismo nodo y borrador, cancelar, completar/reabrir vía selector Estado, crear y sin overflow/errores. Capturas task-canonical-actions-* en storage/framework/testing.
- 2026-11-04 — Regresiones chg014-task-tabs-alignment.verify.mjs, chg014-task-properties-draft-edge.verify.mjs y chg014-calendar-standard-filters.verify.mjs pasaron: cinco secciones, privacidad/recordatorios/relaciones, validación, recurrencia atómica, Foco/temporizador, crear/programar, otras pantallas y filtros a tres tamaños. Jinja confirmó compatibilidad macro default, custom_footer previo y custom_header opcional.
- 2026-11-04 — Compilador aislado: Playwright passed 4 isolated views, published; hashes candidato/dist iguales. ui:conformance sin deuda nueva (345 inventariadas, 259 desviaciones previas); diff --check correcto. UX independiente revisó código y capturas a tres tamaños, sin bloqueantes. Sin cambios de app real, datos, dependencias, commits ni publicación externa.

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-04 — Usuario pidió Editar como icono junto a ampliar/cerrar, quitar Completar/Reabrir redundantes, Cancelar izquierda y no inventar acciones en footer; split Guardar solo si hacen falta acciones extra.
- 2026-11-04 — Aprobado por el usuario (Compuerta 2)
- 2026-11-04 — Movido a in-progress/ (En desarrollo)
- 2026-11-04 — Implementación terminada → En verificación
- 2026-11-10 — Cerrado por el usuario (Compuerta 3) → done/
