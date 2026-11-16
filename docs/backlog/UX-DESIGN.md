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

## Auditoría del recorrido de usuario nuevo (2026-11-16)

Lectura de código (registro, alta, catálogos, bienvenida); **sin ejecutar la app ni capturas** (el entorno no tiene `vendor`). Confirmar cada hallazgo en una cuenta nueva antes de abrir su CHG. Orden sugerido: UX-008 → UX-009 → UX-010 → UX-011 → resto.

Qué recibe hoy una cuenta nueva: 6 círculos, 14 categorías de compras, ~80 filas en `user_categories` (7 dominios), 20 tipos de ejercicio, 10 bebidas, 28 estados de ánimo, 7 categorías de gastos de vehículo y 11 de tareas. Los módulos visibles al inicio son solo Hábitos, Objetivos y Tareas. Hábitos y hábitos a evitar no se siembran en el registro (correcto).

### UX-008 · Alta de usuario repartida en dos sitios
- **Prioridad:** P1
- **Tipo:** Deuda / Objetivo
- **Estado:** Resuelto en CHG-121 (en verificación)
- **Evidencia:** `User::booted()` siembra círculos, categorías de compras y `user_categories`; `RegisterController::seedDefaultUserData()` siembra ejercicios, bebidas (lista duplicada a mano, distinta de `CategoryCatalog::defaults('drinks')`), ánimo, gastos de vehículo y categorías de tareas. Cualquier otra vía de creación (factory, seeder, futura invitación, OAuth) deja la cuenta a medias.
- **Propuesta:** una sola acción `ProvisionNewUser` (idempotente, en transacción) que llame a todos los `Default*::createFor`, usada por el registro y por los seeders. Eliminar la lista de bebidas del controlador y que el controlador solo cree el usuario y llame a la acción. Test que cree el usuario por ambas vías y compare conteos.
- **Tamaño:** S
- **CHG relacionado:** CHG-121
- **Historial:** 2026-11-16 — Hallazgo.

### UX-009 · Catálogos sembrados de módulos que la persona aún no usa
- **Prioridad:** P1
- **Tipo:** Objetivo
- **Estado:** Resuelto en CHG-121 (en verificación)
- **Evidencia:** el registro crea ~170 filas de catálogos para Ejercicio, Hidratación, Ánimo, Vehículos, Relaciones y Comidas aunque `docs/first-experience.md` fija solo Hábitos, Objetivos y Tareas como módulos iniciales. Quien los active después los verá ya llenos y sin haberlos elegido (28 estados de ánimo, 20 ejercicios con calorías estimadas).
- **Propuesta:** sembrar cada catálogo la primera vez que se activa/abre su módulo (el patrón ya existe: `TaskStatus::ensureFor`, botones «restaurar predeterminados»). En el registro, solo lo de Tareas. Mantener el contrato «nunca modifica ni borra lo existente». Reducir los catálogos a un núcleo corto: ánimo con las 18 emociones base (las 10 «matizadas» como opción), ejercicios sin duplicados (Trotar/Correr, Pesas de mano/Entrenamiento de fuerza).
- **Tamaño:** M
- **CHG relacionado:** CHG-121
- **Historial:** 2026-11-16 — Hallazgo.

### UX-010 · «Restaurar hábitos predeterminados» inyecta la rutina personal del autor
- **Prioridad:** P1
- **Tipo:** Defecto
- **Estado:** Resuelto en CHG-121 (en verificación)
- **Evidencia:** `HabitSettings::restoreDefaults()` → `DefaultHabitDefinitions::createPositiveFor()` crea 22 hábitos con horas fijas (Ducha fría 08:15, Seda dental ×3, Botar algo que no sirva, Siesta…). `DatabaseSeeder` y `NegativeHabitDefinitionSeeder` crean además 29 hábitos a evitar con ítems íntimos o en primera persona («Masturbación» 🍆, «Celos compulsivos», «Ser grosero/estresarme», «Dejar en visto»). Contradice «las cuentas nuevas siguen sin recibir una rutina personal copiada». Íconos copiados (Ejercicio 🎾, «Exceso de trabajo» 🍽️), «Ducha fria» sin tilde.
- **Propuesta:** quitar el botón de la cuenta nueva (o cambiarlo por el mismo catálogo de ideas de la bienvenida, `OnboardingChoices::habits()`); mover la rutina del autor a un seeder de demo (`DemoSeeder`) que no se ejecute en producción; si se conservan hábitos a evitar de ejemplo, ofrecerlos como ideas elegibles, no preinstaladas, con lenguaje neutro y revisadas.
- **Tamaño:** S
- **CHG relacionado:** CHG-121
- **Historial:** 2026-11-16 — Hallazgo.

### UX-011 · Seeder con la cuenta real del autor y plantillas globales fuera del alta
- **Prioridad:** P1
- **Tipo:** Defecto
- **Estado:** Resuelto en CHG-121 (en verificación)
- **Evidencia:** `DatabaseSeeder` crea un usuario con nombre y correo reales. `MaintenanceTemplateSeeder` es el único origen de las plantillas globales de mantenimiento (`user_id` null): si el despliegue no lo ejecuta, la cuenta nueva ve el catálogo vacío. Sus categorías (`motor y fluidos`, `transmision`/`transmisión`, `electrico`/`eléctrico`) son claves minúsculas inconsistentes y `CategoryCatalog::seedFor` las usa como nombre visible, con duplicados por tilde.
- **Propuesta:** `DatabaseSeeder` con datos ficticios y solo en entorno local; plantillas globales por migración o paso de despliegue documentado en `docs/DEPLOY.md`; normalizar claves y dar etiquetas legibles (`Motor y fluidos`, `Transmisión`, `Eléctrico`) con migración que unifique duplicados.
- **Tamaño:** S
- **CHG relacionado:** CHG-121
- **Historial:** 2026-11-16 — Hallazgo.

### UX-012 · La bienvenida obliga a crear un hábito y no ofrece salida
- **Prioridad:** P2
- **Tipo:** Objetivo
- **Estado:** Resuelto en CHG-121 (en verificación)
- **Evidencia:** `Welcome::next()` en el paso 3 lanza «Prepara o elige un hábito para continuar»; `RequireOnboarding` redirige a `/bienvenida` desde todas las rutas salvo ajustes/perfil. No hay «Ahora no». Quien llega para probar Tareas queda atrapado en 4 pasos.
- **Propuesta:** «Hacerlo después» en el paso 3 que marque la guía como completada sin hábito; el resumen y Mi día explican cómo crear el primero. Medir el abandono por paso con los eventos `v2_step_*` ya existentes.
- **Tamaño:** S
- **CHG relacionado:** CHG-121
- **Historial:** 2026-11-16 — Hallazgo.

### UX-013 · Registro sin recuperación de contraseña, zona horaria ni aviso legal
- **Prioridad:** P2
- **Tipo:** Objetivo
- **Estado:** Propuesto
- **Evidencia:** `routes/web.php` no define restablecer contraseña; `users` no guarda zona horaria (los hitos D1/D1–7 de `experience:report` usan la de la app); el formulario no enlaza términos ni privacidad. Validación de contraseña solo `min:8`.
- **Propuesta:** decidir producto: recuperación por correo (bloqueante antes de abrir a terceros), zona horaria detectada en el navegador con confirmación en el perfil, y aviso legal si se publica.
- **Tamaño:** M
- **CHG relacionado:** —
- **Historial:** 2026-11-16 — Hallazgo.

### UX-014 · Vocabulario y nombres específicos del autor en catálogos
- **Prioridad:** P3
- **Tipo:** Defecto
- **Estado:** Resuelto en CHG-121 (en verificación)
- **Evidencia:** estados de tarea «Borrador / Ready / WIP / Done» mezclan idiomas y jerga (`TaskStatus::DEFAULTS`); `DefaultTaskCategories::LEGACY_NAMES` fija Siigo, EntreAgiles, Gesthor, CertMind y Unicauca en código; círculos «Familia muy cercana» y «Familia» se solapan; «Pareja» se impone como círculo.
- **Propuesta:** estados «Por hacer / Listo para empezar / En curso / Hecho» para cuentas nuevas (las existentes no cambian); mover los nombres legados a una migración de datos; reducir los círculos a Pareja (opcional), Familia, Amigos y Trabajo. Revisar con `docs/UX-WRITING.md`.
- **Tamaño:** S
- **CHG relacionado:** CHG-121
- **Historial:** 2026-11-16 — Hallazgo.
