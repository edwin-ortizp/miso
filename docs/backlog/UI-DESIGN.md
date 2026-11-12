# Backlog de UI — MiSo

### UI-001 · Pomodoro: redundancias y compañero perdido
- **Prioridad:** P2
- **Tipo:** Objetivo
- **Estado:** Planificado
- **Evidencia:** auditoría 2026-11-11, capturas y resultados en storage/framework/testing/ui-review-2026-11-11/. Fecha repetida, dos métricas superiores y escenario de 954 × 555 px con compañero de 64 px.
- **Solución aprobada:** Una fecha y una composición compacta de compañero, reloj y controles; meta accesible también en móvil.
- **CHG relacionado:** CHG-086
- **Historial:** 2026-11-11 — Hallazgo y propuesta aprobados por el usuario para implementar.

### UI-002 · Contraste del grito eléctrico
- **Prioridad:** P2
- **Tipo:** Objetivo
- **Estado:** Planificado
- **Evidencia:** auditoría 2026-11-11, capturas y resultados en storage/framework/testing/ui-review-2026-11-11/. Contraste medido de 4,19:1 para texto pequeño en el compañero eléctrico.
- **Solución aprobada:** Usar un color de texto que alcance 4,5:1 conservando la temática.
- **CHG relacionado:** CHG-086
- **Historial:** 2026-11-11 — Hallazgo y propuesta aprobados por el usuario para implementar.

### UI-003 · Cabeceras y campos de Ajustes
- **Prioridad:** P2
- **Tipo:** Objetivo
- **Estado:** Planificado
- **Evidencia:** auditoría 2026-11-11, capturas y resultados en storage/framework/testing/ui-review-2026-11-11/. Icono aislado ocupa una fila; campos consecutivos sin separación uniforme.
- **Solución aprobada:** Integrar icono/título y separar campos dentro de formularios de ancho legible.
- **CHG relacionado:** CHG-087
- **Historial:** 2026-11-11 — Hallazgo y propuesta aprobados por el usuario para implementar.

### UI-004 · Objetivos táctiles de navegación
- **Prioridad:** P2
- **Tipo:** Objetivo
- **Estado:** Planificado
- **Evidencia:** auditoría 2026-11-11, capturas y resultados en storage/framework/testing/ui-review-2026-11-11/. settings-nav__link tiene min-height:36px.
- **Solución aprobada:** Mínimo de 44px en navegación de Ajustes.
- **CHG relacionado:** CHG-087
- **Historial:** 2026-11-11 — Hallazgo y propuesta aprobados por el usuario para implementar.

### UI-005 · Datos físicos sin agrupación
- **Prioridad:** P2
- **Tipo:** Objetivo
- **Estado:** Planificado
- **Evidencia:** auditoría 2026-11-11, capturas y resultados en storage/framework/testing/ui-review-2026-11-11/. Formulario y tarjeta de tres referencias se tocan.
- **Solución aprobada:** Un bloque con campos y referencias guardadas diferenciadas; estados ausentes comprensibles.
- **CHG relacionado:** CHG-087
- **Historial:** 2026-11-11 — Hallazgo y propuesta aprobados por el usuario para implementar.

### UI-006 · Preferencias y confirmación de guardado
- **Prioridad:** P2
- **Tipo:** Objetivo
- **Estado:** Planificado
- **Evidencia:** auditoría 2026-11-11, capturas y resultados en storage/framework/testing/ui-review-2026-11-11/. Interruptores fluyen en línea y el primer Guardar confirma dentro del segundo bloque.
- **Solución aprobada:** Filas alineadas y un guardado único con confirmación próxima; activación del dispositivo independiente.
- **CHG relacionado:** CHG-088
- **Historial:** 2026-11-11 — Hallazgo y propuesta aprobados por el usuario para implementar.

### UI-007 · Franjas de notificaciones ausentes
- **Prioridad:** P2
- **Tipo:** Objetivo
- **Estado:** Planificado
- **Evidencia:** auditoría 2026-11-11, capturas y resultados en storage/framework/testing/ui-review-2026-11-11/. Cuándo queda vacío aunque render recibe morning/afternoon/night y existe un dispositivo sintético.
- **Solución aprobada:** Mostrar las tres franjas con etiquetas y conservar las preferencias guardadas.
- **CHG relacionado:** CHG-088
- **Historial:** 2026-11-11 — Hallazgo y propuesta aprobados por el usuario para implementar.

