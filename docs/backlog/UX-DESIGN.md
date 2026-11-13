# Backlog de UX — MiSo

Auditoría de experiencia móvil del 2026-11-12 (CHG-095). Se hizo leyendo vistas, componentes y CSS del shell; **no incluye capturas en dispositivo**: antes de abrir cada CHG conviene confirmar el hallazgo a 390 px. Orden sugerido: UX-001 → UX-002 → UX-003 → el resto.

Patrón de fondo: los módulos de registro diario se diseñaron como «tabla + panel lateral». Por debajo de 1100 px el panel (`.md-context-rail`) deja de ser lateral y cae **debajo** de la tabla (`resources/css/m3/archetypes/_module-shell.css`), así que en el celular lo más útil —avance del día y registro rápido— queda al final de la pantalla, y lo primero que se ve es el historial.

### UX-001 · Hidratación: vasos como protagonista
- **Prioridad:** P1
- **Tipo:** Objetivo
- **Estado:** Dirección aprobada, sin planificar
- **Evidencia:** `resources/views/livewire/water/water-daily.blade.php`. En móvil la pantalla abre con la tabla «Registro del día»; «Objetivo de hoy» y «Agregar rápido» están en el panel, debajo. Registrar exige FAB → combobox con búsqueda → campo numérico de mililitros → hora. «Agregar rápido» solo ofrece 250 ml.
- **Solución aprobada:** la meta del día dibujada como vasos que se llenan, arriba; tocar un vaso registra 250 ml de la bebida habitual (igual que el «+1 vaso» del aviso push); «Registrar» abre un selector visual con bebidas en cuadrícula de íconos y recipientes (vaso 250, taza 200, botella 500, «otra cantidad») en vez de escribir mililitros; la hora queda por defecto en «ahora» y se edita solo si hace falta; el historial va debajo como lista. Escritorio conserva tabla y panel.
- **Notas técnicas:** reutiliza `App\Actions\LogDrink` y `quickAdd`; el tamaño del vaso y la bebida habitual pueden vivir en `module_settings` (`water`), sin migración. Deshacer el último vaso desde un snackbar.
- **Tamaño:** M
- **CHG relacionado:** —
- **Historial:** 2026-11-12 — Dirección «vasos como protagonista» elegida por el usuario.

### UX-002 · Registros diarios: en móvil el avance y la acción rápida van primero
- **Prioridad:** P1
- **Tipo:** Objetivo
- **Estado:** Propuesto
- **Evidencia:** regla `@media` del shell que apila el panel bajo el contenido; afecta a Hidratación, Ejercicio, Ánimo y energía, Hábitos, Diario, Pomodoro y Comidas (todos usan `x-slot:rail` para el progreso del día).
- **Propuesta:** definir en `docs/UX-DESIGN.md` una anatomía para el arquetipo `daily-log` en compacto: (1) avance del día, (2) registro rápido, (3) historial. Implementarlo en el shell con un slot «resumen» que en compacto se pinta antes del contenido y en escritorio sigue en el panel, para no duplicar marcado por módulo.
- **Tamaño:** M (shell) + S por módulo que adopte el slot
- **CHG relacionado:** —
- **Historial:** 2026-11-12 — Hallazgo.

### UX-003 · Ejercicio: registrar sin teclado numérico
- **Prioridad:** P2
- **Tipo:** Objetivo
- **Estado:** Propuesto
- **Evidencia:** `resources/views/livewire/exercise/exercise-daily.blade.php`: 7 campos numéricos y 3 selects en el formulario; no hay registro rápido.
- **Propuesta:** tipos de ejercicio frecuentes como chips/tarjetas con ícono; duración con pasos de 5/10/15 min y presets (15, 30, 45, 60); «repetir el de ayer»; los campos avanzados (distancia, series, peso) plegados en una sección «Más detalle».
- **Tamaño:** M
- **CHG relacionado:** —
- **Historial:** 2026-11-12 — Hallazgo.

### UX-004 · Historiales como tabla apilada en el celular
- **Prioridad:** P2
- **Tipo:** Objetivo
- **Estado:** Propuesto
- **Evidencia:** `md-table--stack` en Hidratación, Ejercicio, Pomodoro y Combustible: cada registro repite etiquetas de columna y ocupa mucha altura; las acciones quedan en un menú por fila.
- **Propuesta:** en compacto, una lista (`x-ui.list-item`) de una línea por registro: ícono, título, valor a la derecha y hora como texto de apoyo; editar al tocar la fila y eliminar desde el menú. Agrupar por momento del día cuando haya más de ~6 registros.
- **Tamaño:** S por módulo
- **CHG relacionado:** —
- **Historial:** 2026-11-12 — Hallazgo.

### UX-005 · Hábitos a evitar: registrar la incidencia en un toque y poder deshacer
- **Prioridad:** P2
- **Tipo:** Objetivo
- **Estado:** Propuesto
- **Evidencia:** `resources/views/livewire/negative-habit/negative-habit-weekly.blade.php`: cada incidencia abre un diálogo solo para una nota opcional; la fila mezcla el registro con un selector de categoría (tarea de configuración); no hay vista por día de la semana aunque la pantalla es semanal.
- **Propuesta:** el «+» registra de inmediato y muestra un snackbar con «Deshacer» y «Añadir nota»; mover el cambio de categoría a Ajustes; mostrar los 7 días de la semana como puntos por hábito para ver el patrón.
- **Tamaño:** S–M
- **CHG relacionado:** — (CHG-095 ya pasó el diálogo al componente canónico y dio nombre accesible al «+»)
- **Historial:** 2026-11-12 — Hallazgo.

### UX-006 · Comidas (semana): planear desde el celular
- **Prioridad:** P3
- **Tipo:** Objetivo
- **Estado:** Propuesto
- **Evidencia:** `resources/views/livewire/meal/meal-weekly.blade.php`: 6 campos numéricos, 3 combobox y 5 selects en el flujo de planear/consumir.
- **Propuesta:** vista de un día a la vez con deslizamiento entre días; «marcar como comido» en un toque desde la tarjeta; porciones con stepper; el formulario completo solo al editar.
- **Tamaño:** L (dividir)
- **CHG relacionado:** —
- **Historial:** 2026-11-12 — Hallazgo.

### UX-007 · Diario › Vida: mapa de miles de celdas en pantalla táctil
- **Prioridad:** P3
- **Tipo:** Objetivo
- **Estado:** Propuesto
- **Evidencia:** `journal-life-calendar.blade.php` pinta ~4.700 celdas enlazadas; en móvil cada celda queda por debajo del tamaño táctil mínimo y la etiqueta solo existe como `title`.
- **Propuesta:** en compacto, agrupar por año con una fila tocable que expande sus semanas a tamaño táctil; mantener el mapa completo en escritorio.
- **Tamaño:** M
- **CHG relacionado:** — (CHG-095 redujo su costo de render)
- **Historial:** 2026-11-12 — Hallazgo.
