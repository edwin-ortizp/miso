# CHG-005 · Buscador en los tres catálogos de comidas
**Estado:** Cerrado
**Nivel:** S
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-04 · **Aprobado:** 2026-11-04 · **Cerrado:** 2026-11-10
**Relacionado:** —

## 0. En palabras simples
Los campos Ingrediente, Receta y Preparación tendrán buscador en el diálogo semanal. Escribe para filtrar y selecciona con ratón o teclado.

## 1. Resumen
Cambio aprobado por el usuario: reutilizar x-ui.combobox para los tres catálogos, conservando Tipo y Unidad. Perfil estándar; sin proveedores nuevos.

## 3. UX/UI
Reutilizar búsqueda sin tildes/mayúsculas, Escape sin cerrar el diálogo, etiquetas y estados vacíos. Verificar a 375, 768 y 1280 px. Revisión basada en código y captura; sin ampliar a otros módulos.

## 4. Diseño técnico
Archivos: resources/views/livewire/meal/meal-weekly.blade.php, resources/views/components/ui/combobox.blade.php, tests/Browser/meal-combobox.interaction.spec.js, tests/Browser/support/meal-combobox-fixture.php, tests/Feature/MealPlanningTest.php, tests/Browser/purchases.interaction.spec.js.
Conservar consultas por usuario, orden, favoritos, porciones libres y exclusión de recetas duplicadas. Actualización inmediata Livewire, opciones dinámicas y claves por fila/tipo/índice. Mantener bloqueo por fieldset para comidas consumidas. Sin cambios de API, migraciones o inventario.

## 5. Pruebas
Pruebas de comidas afectadas; navegador aislado con SQLite y datos ficticios: selección y guardado, unidad/calorías inmediatas, teclado, Escape, tildes, sin resultados, filas, duplicados, reapertura, consumidas y responsive. Ejecutar npm run build, php artisan ui:conformance y git diff --check; separar fallos previos. No ejecutar suites completas ajenas al cambio.

## 6. Documentación
El contrato existente en docs/design-system.md ya cubre la interacción. Registrar evidencias en este CHG. Los documentos de lineamientos por rol no existen; se usa el sistema de diseño vigente.

## 7. Tareas
- [x] T1 · Buscar en los tres catálogos con selección inmediata — Resp: dev-implementer — Archivos: resources/views/livewire/meal/meal-weekly.blade.php, resources/views/components/ui/combobox.blade.php — Hecho cuando: opciones dinámicas, bloqueo y valores dependientes conservados.
- [x] T2 · Verificar interacción y regresiones — Resp: dev-implementer — Archivos: tests/Browser/meal-combobox.interaction.spec.js, tests/Browser/support/meal-combobox-fixture.php — Hecho cuando: pruebas afectadas y controles de UI documentados.

## 8. Bitácora de implementación (dev-implementer)
- T1: tres catálogos migrados al combobox compartido; .live respetado; opciones actualizadas desde atributo data-options mediante observador con desconexión al destruir; clave por fila, tipo e índice para evitar enlaces antiguos tras reindexar.
- PHP: 29 pruebas, 149 aserciones correctas (MealPlanningTest, MealScreensRedesignTest, MealScheduleTest, HabitActionPromptTest).
- npm run build correcto; advertencias de tamaño de bundles y tiempos de plugins. ui:conformance correcto: 345 deudas inventariadas, 259 desviaciones, sin deuda visual nueva.
- Deuda previa detectada: fila de composición móvil/tableta excede el ancho por sus mínimos CSS en resources/css/m3/modules/_meals.css:284; el desplegable permanece dentro del viewport. No se modificó CSS de esa superficie.
- Deuda previa de contraste: etiqueta enfocada con verde del módulo #558b2f sobre blanco, 4.1:1 frente a 4.5:1. El test distingue exclusivamente esa combinación existente y exige ausencia de otras infracciones de accesibilidad.
- Adaptación necesaria de pruebas existentes: antes buscaban option/value y selectOption; ahora comprobarán los catálogos del combobox y seleccionarán opciones visibles. Sin cambiar los escenarios.

- T2: prueba de navegador final correcta en 375, 768 y 1280 px (3/3): selección táctil/ratón/teclado, búsqueda sin tildes y mayúsculas, Escape y Tab, vacío, exclusión de recetas en un catálogo ya renderizado, cambio de tipo, eliminación/reindexación, guardado/reapertura, bloqueo al consumir y apertura de otra comida sin valores previos. Sin errores JavaScript. Base SQLite dedicada y datos ficticios; sin tocar datos reales.
- Revisadas capturas storage/framework/testing/meal-combobox-{375,768,1280}.png. Desplegables dentro del viewport. Contraste heredado identificado explícitamente en prueba; sin otras infracciones axe en los combobox.
- git diff --check y sintaxis PHP/JS correctos. Sin commits ni despliegue. Implementación completa; deudas previas de layout/contraste pendientes fuera de este cambio.

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-04 — Usuario eligió los tres catálogos y aprobó el plan completo y su implementación. Sin preguntas pendientes.
- 2026-11-04 — Aprobado por el usuario (Compuerta 2)
- 2026-11-04 — Movido a in-progress/ (En desarrollo)
- 2026-11-04 — Implementación terminada → En verificación
- 2026-11-10 — Cerrado por el usuario (Compuerta 3) → done/
