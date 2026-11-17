# CHG-149 · Ajustes con secciones y categorías en Ejercicio, Ánimo, Vehículos, Hábitos a evitar y Relaciones
**Estado:** Borrador
**Nivel:** L
**Modelo sugerido:** razonamiento
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-17 · **Aprobado:** — · **Cerrado:** —
**Relacionado:** CHG-147, CHG-148

## 0. En palabras simples
- Qué vas a ver distinto: desaparece el botón perdido «Gestionar categorías» en Ejercicio, Ánimo, Vehículos, Hábitos a evitar y Relaciones. Cada módulo tiene su pestaña «Ajustes» con una barra lateral y una sección «Categorías», igual que Tareas y Comidas.
- Cómo comprobarlo tú: entra a cada módulo, abre Ajustes, cambia de sección con la barra y crea una categoría.

## 1. Resumen
Seis pantallas repiten un botón de texto suelto que saca al usuario a `/categories/{dominio}`. Solo Ejercicio y Ánimo tienen una página de ajustes; Vehículos, Hábitos a evitar y Relaciones no. Con la base compartida de CHG-148 (trait y parcial de categorías, CSS genérico de ajustes) se crean o amplían los ajustes de los cinco módulos con barra lateral y sección Categorías, y se retira el botón suelto. No cambian los catálogos, las categorías ni el MCP.

## 2. Producto (product-manager)
- Problema / usuario: las categorías se gestionan fuera de contexto y de forma distinta en cada módulo; en tres módulos ni siquiera hay ajustes.
- Incluye:
  - Ejercicio y Ánimo: secciones del catálogo y «Categorías».
  - Vehículos: nuevos ajustes con «Plantillas de mantenimiento» y «Categorías»; `vehicles.catalog` redirige a ellos.
  - Hábitos a evitar: nuevos ajustes con «Categorías» y pestañas Semana y Ajustes.
  - Relaciones: nuevos ajustes con «Categorías de personas» y «Categorías de acontecimientos».
  - Retirar el botón suelto en las seis vistas.
- No incluye: cambios en catálogos, reglas de categorías (dos niveles, en uso no se elimina), MCP, ajustes nuevos distintos a categorías (en Hábitos a evitar y Relaciones, por ahora).
- Criterios de aceptación (Dado/Cuando/Entonces):
  - Dado cada módulo, cuando se abre Ajustes, entonces hay una barra lateral con la sección activa marcada.
  - Dado la sección Categorías, cuando se crea, edita o elimina una categoría, entonces funciona como el catálogo genérico (en uso no se elimina).
  - Dado una sección inexistente, cuando se visita, entonces responde 404.
  - Dado cualquiera de las seis vistas, cuando se abre, entonces no aparece «Gestionar categorías».
  - Dado un enlace antiguo a `/vehicles/maintenance-catalog` o `/categories/{dominio}`, cuando se abre, entonces sigue funcionando.

## 3. UX/UI
### 3.1 Experiencia (ux-designer)
- Plantilla: ajustes con barra lateral y secciones (como `meal-settings` y `task-settings`).
- Flujo y cantidad de pasos: Módulo → Ajustes → sección (un clic para cambiar de sección).
- Qué contiene cada pantalla y qué es lo más importante: la card de gestión de la sección activa; Ejercicio mantiene «Meta diaria» en su panel lateral.
- Estados (vacío / carga / error / éxito / validaciones): los del catálogo y de las categorías actuales.
- Información necesaria y momento de presentación: la sección «Categorías» explica solo lo necesario (dos niveles, en uso no se elimina).
- Accesibilidad de uso (teclado, foco, errores): nav con `aria-current`, enlaces con `wire:navigate`.

```
Ajustes de Vehículos
┌ 🔧 Plantillas de mantenimiento   │ Plantillas  ◀
│  tabla de gestión                │ Categorías
└──────────────────────────────────┘
Ajustes de Relaciones
┌ 🏷 Categorías de personas        │ Personas  ◀
│  tabla de gestión                │ Acontecimientos
└──────────────────────────────────┘
```

### 3.2 Visual (ui-designer)
- Distribución: grid contenido + nav lateral con la clase genérica de CHG-148.
- Componentes existentes a reutilizar (ruta): `x-ui.management-card`, `x-ui.form-dialog`, parcial de categorías de CHG-148; referencia `resources/views/livewire/meal/meal-settings.blade.php`.
- Jerarquía, color, tipografía y espaciado (tokens): tokens M3.
- Responsive y contraste: 360/390/1440; el nav colapsa en móvil.

### 3.3 Escritura (ux-writer)
- Ubicación y estado: nombres de secciones y pestañas «Ajustes».
- Textos finales (variables y variantes): «Tipos de ejercicio», «Catálogo de emociones», «Plantillas», «Personas», «Acontecimientos», «Categorías».
- Reglas de docs/UX-WRITING.md aplicadas: etiquetas estables; «Ajustes» como pestaña.
- Criterios de aceptación de los textos: no queda «Gestionar categorías» en las vistas.

## 4. Diseño técnico (software-architect)
- Archivos a crear / modificar: `app/Livewire/Exercise/ExerciseSettings.php`, `Mood/MoodSettings.php`, nuevos `Vehicle/VehicleSettings.php`, `NegativeHabit/NegativeHabitSettings.php`, `Relationship/RelationshipSettings.php`; vistas correspondientes; `routes/web.php`; `config/modules.php`; vistas `vehicle-catalog`, `negative-habit-weekly`, `relationship-index`, `relationship-events` (quitar botón); tests.
- Enfoque y patrones a seguir: `#[Locked] $section` con 404, rutas por sección con `->defaults('section', …)`, trait `ManagesUserCategories` y parcial de CHG-148; Relaciones con dos dominios.
- Riesgos y mitigación: `/vehicles/settings` y `/relationships/settings` antes de las rutas con parámetro; pestañas nuevas afectan `ModuleExperienceTest`, `ScreenArchetypeTest`, `UrlStateTest`; `VehicleCatalog` se mueve a una sección sin perder sus pruebas.
- Qué NO tocar: `CategoryCatalog`, `UserCategory`, MCP, datos.

## 5. Pruebas (qa-tester)
| Caso | Tipo | Nivel | Test |
|---|---|---|---|
| Secciones renderizan y 404 inválida (5 módulos) | Feature | 1 | tests de ajustes por módulo |
| CRUD de categorías en cada módulo | Feature | 1 | `CategoryHierarchyTest` y nuevos |
| Sin «Gestionar categorías» en vistas | Feature | 1 | prueba de recorrido |
| Pestañas nuevas | Feature | 2 | `ModuleExperienceTest`, `ScreenArchetypeTest`, `UrlStateTest` |
| Sin infracciones nuevas | Feature | 2 | `UiConformanceTest` |
- Comando para verificar: `php artisan test --filter="Exercise|Mood|Vehicle|NegativeHabit|Relationship|Category"`, `php artisan ui:conformance`, `npm run build`, capturas a 390 y 1440.
- Regresión a vigilar: `VehicleModuleTest`, `RelationshipModuleTest`, `MoodSettingsTest`, `ExerciseDailyProgressTest`.

## 6. Documentación (tech-writer)
- Docs a actualizar: `docs/category-catalogs.md`, `docs/design-system.md` (patrón «ajustes con secciones»).

## 7. Tareas
- [ ] T1 · Ajustes de Ejercicio y Ánimo con secciones y categorías — Resp: developer — Modelo: razonamiento — Archivos: componentes, vistas, rutas, `config/modules.php` — Hecho cuando: sin botón suelto y tests verdes.
- [ ] T2 · Ajustes nuevos de Vehículos — Resp: developer — Modelo: razonamiento — Archivos: `VehicleSettings`, vistas, rutas — Hecho cuando: plantillas y categorías en secciones, redirección del catálogo antiguo.
- [ ] T3 · Ajustes nuevos de Hábitos a evitar y Relaciones — Resp: developer — Modelo: razonamiento — Archivos: componentes nuevos, vistas, rutas, pestañas — Hecho cuando: categorías gestionables y botón retirado.
- [ ] T4 · Pruebas y documentación — Resp: developer — Modelo: razonamiento — Archivos: tests, docs — Hecho cuando: pasan los tests y capturas a 390/1440.

## 8. Bitácora de implementación (developer)

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-17 — Creado en to-do/ (Borrador, nivel L)
- 2026-11-17 — Especificado a partir del plan de pulido; pendiente la Compuerta 2 (aprobación del usuario).
