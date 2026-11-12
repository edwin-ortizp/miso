# CHG-084 · Selector visual de color y chips coherentes
**Estado:** Cerrado
**Nivel:** M
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-11 · **Aprobado:** 2026-11-11 · **Cerrado:** 2026-11-11
**Relacionado:** —

## 0. En palabras simples
El campo Color permite ver cada etiqueta antes de elegirla: fondo suave y texto e ícono contrastantes. Las vistas muestran la misma categoría con su ícono.
Comprueba Ajustes de Tareas, Lista, Planificación, Kanban, Planes y gastos de Vehículos.

## 1. Resumen
Plan y boceto aprobados explícitamente por el usuario el 2026-11-11, con instrucción de implementar sin nueva aprobación. Un campo Color con muestra integrada, paleta actual y opción Predeterminado con énfasis del módulo. Sin migraciones, cambios de API ni datos reales.

## 2. Producto (product-manager)
- Usuario: propietario de sus categorías y estados.
- Incluye selector compartido de categorías/estados, chips completos de Tareas y etiquetas existentes de Planes/Vehículos.
- Excluye nuevos campos en otros catálogos, cambios de gráficas/filtros, despliegue y datos reales.
- Dado un color e ícono configurados, al guardar y reabrir se conservan y las vistas muestran la misma etiqueta.
- Dado Predeterminado, la etiqueta usa el énfasis del módulo sin cambiar comportamiento del estado.

## 3. UX/UI
### 3.1 Experiencia (ux-designer)
Plantilla: ajustes existentes con diálogo de formulario; listados existentes.
Abrir Color, elegir opción, guardar. Un único campo y sin vista previa separada. Flechas, Enter, Escape, Tab, clic y tacto; errores asociados y foco visible. Las muestras siguen nombre e ícono actuales. Sin nombre se muestra Vista previa.

### 3.2 Visual (ui-designer)
Reutilizar campo/listado canónicos y catalog-chip, paleta pastel y texto 4,5:1 en claro/oscuro. Menú limitado al viewport y sin recorte por el diálogo; comprobar después de Livewire.
```
Color
+-----------------------------------------+
| [Persona Personal] · Azul             v |
+-----------------------------------------+
  Predeterminado   [Persona Personal]
  Gris             [Persona Personal]
  Azul             [Persona Personal]

Lista / Planificación / Kanban
[Persona Personal]  [Reloj En curso]
```
Conservar el orden de los demás campos. Planes/Vehículos usan ícono almacenado y énfasis del módulo. Calendario conserva sus eventos tonales; gráficas y filtros no se rediseñan.

### 3.3 Escritura (ux-writer)
No aplica redacción nueva: conservar Color, Predeterminado, Vista previa y nombres actuales de paleta/estados/categorías. Los nombres de colores permanecen visibles y accesibles.

## 4. Diseño técnico (software-architect)
Archivos: resources/views/components/ui/{catalog-color-select,catalog-chip}.blade.php; resources/css/m3/primitives/{_chips,_text-fields}.css y resources/css/m3/modules/_vehicles.css; resources/js/catalog-color-select.js y resources/js/app.js si se extrae comportamiento; app/Livewire/Task/Concerns/LoadsTaskCategories.php; vistas afectadas en resources/views/livewire/{task,plan,vehicle}; consultas de Planes para precargar planCategory cuando corresponda; tests/Feature/{TaskCatalogColorsTest,PlanModuleTest,VehicleExpensesTest}.php y tests/Feature/Ui/UiCatalogTest.php; tests/Browser/catalog-colors.interaction.spec.js y su fixture aislada.
Mantener interfaz existente del selector y claves/nulos almacenados. Variante explícita accent de catalog-chip sin alterar tonos semánticos de consumidores ajenos. Metadata de categorías protegida y limitada al usuario autenticado; fallback bi-tag y nombre histórico. Reutilizar patrones de Alpine/Livewire sin modificar el select nativo global.
Qué NO tocar: migraciones, dependencias, taxonomía, permisos, comportamiento de estados, gráficos y filtros.

## 5. Pruebas (qa-tester)
- PHP focalizado: colores, iconos, fallback, hidratación y aislamiento; regresión de estados, vistas y etiquetas de Planes/Vehículos.
- Navegador con SQLite exclusiva: crear/editar/restablecer/reabrir categorías y estados; teclado, foco, Escape, Tab, clic/tacto; menú anclado tras Livewire; nombres largos; 375/768/1280 px en claro/oscuro; contraste >=4,5:1; sin desbordamiento.
- Comando para verificar: php artisan test --filter='TaskCatalogColorsTest|TaskViewsTest|TaskStatusesTest|PlanModuleTest|VehicleModuleTest|UiCatalogTest'
- npm run build; php artisan ui:conformance; npx playwright test tests/Browser/catalog-colors.interaction.spec.js --project=interaction --workers=1; git diff --check.

## 6. Documentación (tech-writer)
Actualizar contrato en docs/design-system.md y evidencia en esta bitácora. No crear lineamientos globales ausentes para este ajuste.

## 7. Tareas
- [x] T1 · Elegir colores viendo la etiqueta dentro del campo — Resp: developer — Archivos: componentes catalog-color-select/catalog-chip y CSS/JS relacionados — Hecho cuando: selector accesible integrado y chips con variante de énfasis.
- [x] T2 · Mostrar categorías completas en las vistas — Resp: developer — Archivos: LoadsTaskCategories y vistas/consultas de Tareas, Planes, Vehículos — Hecho cuando: ícono y color coherentes, fallback y aislamiento conservados.
- [x] T3 · Verificar comportamiento y presentación — Resp: developer — Archivos: pruebas PHP y Browser afectadas — Hecho cuando: pruebas focalizadas, build, conformance y comprobaciones responsive ejecutadas y resultados registrados.
- [x] T4 · Documentar el contrato y la entrega — Resp: developer — Archivos: docs/design-system.md y CHANGE.md — Hecho cuando: contrato actualizado, evidencia y límites registrados.

## 8. Bitácora de implementación (developer)
- 2026-11-11 — Inicio autorizado por el usuario; se registra fielmente el plan aprobado. CHG-081 ajeno en desarrollo: coexistencia independiente, sin modificar sus archivos. Lineamientos PRODUCT/DOCUMENTATION/UX-WRITING ausentes; se conservan textos existentes y design-system vigente.

- 2026-11-11 — T1: campo visual con combobox select-only, Popover API para escapar el recorte manteniendo propiedad DOM/foco, vista seleccionada y opciones con catalog-chip. Variante accent explícita, paleta existente y nombre/ícono reactivos. Build correcto; accesibilidad y teclado/tacto del primer recorrido móvil correctos.
- 2026-11-11 — T2: metadata protegida de categorías con guardia sin autenticación, fallback histórico y bi-tag; Lista/Planificación/Kanban corregidos. Planes (listado, detalle y ajustes) y gastos de Vehículos (tabla, tarjetas y gestor) usan chip compartido, relaciones precargadas. Diálogos reciben module explícito para conservar énfasis durante peticiones Livewire. 12 pruebas focalizadas, 84 aserciones, pasan.
- 2026-11-11 — Base previa: selección inicial de 100 pruebas dio 94 pasan, 5 fallas y 1 error. PlanModuleTest ya falla en vencido, protección de visitas/catálogos ajenos y fechas inválidas; TaskViewsTest falla al buscar HTML del editor; UiCatalogTest falla por ejemplos ausentes. No se modifican esas funcionalidades ajenas.

- 2026-11-11 — T3: Playwright 3/3 pasan (375, 768, 1280 px, 2,7 min). SQLite exclusiva, guardia APP_ENV/testing y ruta de base; creación/edición/reapertura/reset de categorías y estados; nombre largo e ícono vivo, teclado y tacto, Escape sin cerrar diálogo, Tab, menú anclado y dentro de pantalla. Axe sin infracciones en selector; todas las opciones de paleta comprobadas en ambos temas a 1280 px, chips de Planes/Vehículos contrastados en ambos temas y tres anchos. Capturas revisadas a 375/768/1280 px en storage/framework/testing/catalog-*.png. Se corrigió una regla móvil de Vehículos que imponía color a todos los span: ahora excluye catalog-chip; se repitieron y pasaron los tres recorridos completos. Sin validación en dispositivos físicos.
- 2026-11-11 — Regresión final focalizada: 152 pruebas, 146 pasan, 1047 aserciones; 5 fallas y 1 error coinciden exactamente con los seis resultados previos ya identificados. Evidencia: storage/framework/testing/catalog-final-php.json. Pruebas específicas: 12/12, 84 aserciones (catalog-specific.json).
- 2026-11-11 — npm run build correcto y assets publicados localmente (catalog-build.txt). ui:conformance ejecutado: reporta deuda previa (catalog-conformance.txt); comparación de tally actual contra vistas de HEAD en snapshot aislado confirma new_violations_vs_HEAD = []. No se modifica baseline. git diff --check correcto.
- 2026-11-11 — T4: contrato documentado en docs/design-system.md; comportamiento, cobertura y límites registrados. Cambios exclusivamente locales; sin migraciones sobre datos reales, commits, push ni despliegue. Listo para revisión visual del usuario y cierre posterior autorizado.

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-11 — Plan, alcance y boceto aprobados en conversación. Sin preguntas abiertas.
- LECCIÓN(developer): pasar el módulo explícitamente a diálogos que muestran etiquetas con énfasis; resolverlo solo desde la ruta puede perder el contexto al abrir o actualizar mediante Livewire — evidencia: CHG-084 agrega module="tasks" y module="vehicles", y el navegador verifica data-module tras abrir · destino sugerido: docs/ARCHITECTURE.md.
- 2026-11-11 — Aprobado por el usuario (Compuerta 2)
- 2026-11-11 — Movido a in-progress/ (En desarrollo)
- 2026-11-11 — Implementación terminada → En verificación
- 2026-11-11 — Cerrado por el usuario (Compuerta 3) → done/
