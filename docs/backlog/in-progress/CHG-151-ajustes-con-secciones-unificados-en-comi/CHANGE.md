# CHG-151 · Ajustes con secciones unificados en Comidas, Tareas y Planes
**Estado:** En verificación
**Nivel:** M
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-17 · **Aprobado:** 2026-11-17 · **Cerrado:** —
**Relacionado:** CHG-148, CHG-149, CHG-150

## 0. En palabras simples
- Qué vas a ver distinto: los ajustes de Comidas, Tareas y Planes se ven igual que los de Hidratación o Ejercicio; Planes ahora tiene una dirección propia para cada sección.
- Cómo comprobarlo tú: entra a Ajustes de cada módulo, cambia de sección y recarga la página: te quedas en la misma sección.

## 1. Resumen
Comidas y Tareas copian a mano la navegación y el CSS de las secciones de ajustes, y Planes cambia de sección sin dirección propia ni 404. Se llevan los tres al componente `x-settings-sections` y a las clases `module-settings-*`, y Planes a rutas por sección. No cambia ninguna función.

## 2. Producto (product-manager)
- Problema / usuario: código duplicado y comportamientos distintos entre módulos (Planes no conserva la sección al recargar).
- Incluye: `MealSettings` (incluye la vista de horarios), `TaskSettings` y `PlanSettings` con `SECTIONS` como mapa, `x-settings-sections`, `module-settings-*`; rutas `plans.settings` y `plans.settings.categories`; retirar CSS duplicado de `_meals.css`, `_tasks.css` y `_plans.css`.
- No incluye: `GameSettingsPage`, `SettingsPage`, `HabitSettings`, `PomodoroSettings`, `NotificationSettings` (otro patrón o página única).
- Criterios de aceptación (Dado/Cuando/Entonces):
  - Dado Ajustes de Comidas, Tareas o Planes, cuando se abre, entonces muestra la barra lateral con la sección activa marcada.
  - Dado Planes, cuando se visita `/plans/settings/categories` o se recarga, entonces se mantiene la sección; una inválida responde 404.
  - Dado cualquier sección existente, cuando se usa, entonces funciona igual que hoy.

## 3. UX/UI
### 3.1 Experiencia (ux-designer)
- Plantilla: ajustes con barra lateral y secciones (como `meal-settings`).
- Flujo y cantidad de pasos: igual que hoy.
- Qué contiene cada pantalla y qué es lo más importante: la card de la sección activa y la barra lateral.
- Estados (vacío / carga / error / éxito / validaciones): sin cambios.
- Información necesaria y momento de presentación: sin cambios.
- Accesibilidad de uso (teclado, foco, errores): nav con `aria-current`, enlaces con `wire:navigate`.

```
Ajustes de Planes
┌ Etiquetas ...            │ Etiquetas ◀
│                          │ Categorías
└──────────────────────────┘
```

### 3.2 Visual (ui-designer)
- Distribución: grid contenido + nav lateral con `module-settings-workspace`.
- Componentes existentes a reutilizar (ruta): `resources/views/components/settings-sections.blade.php`, CSS `module-settings-*` de `_settings.css`.
- Jerarquía, color, tipografía y espaciado (tokens): tokens M3 existentes, sin estilos en línea.
- Responsive y contraste: 360/390/1440 px.

### 3.3 Escritura (ux-writer)
- Ubicación y estado: nombres de sección actuales.
- Textos finales (variables y variantes): sin cambios de texto salvo lo indicado.
- Reglas de docs/UX-WRITING.md aplicadas: etiquetas estables.
- Criterios de aceptación de los textos: ningún texto cambia de significado.

## 4. Diseño técnico (software-architect)
- Archivos a crear / modificar: `app/Livewire/Meal/MealSettings.php`, `app/Livewire/Task/TaskSettings.php`, `app/Livewire/Plan/PlanSettings.php`, sus vistas, `routes/web.php`, `config/modules.php`, `_meals.css`, `_tasks.css`, `_plans.css`, specs de navegador de compras, tareas y planes.
- Enfoque y patrones a seguir: mismo patrón que `WaterSettings`: `SECTIONS` mapa, `#[Locked] $section`, `mount` con 404.
- Riesgos y mitigación: tests que hacen `set('section', …)` sobre Planes y selectores de specs de navegador → reescribir con `['section' => …]` y actualizar selectores.
- Qué NO tocar: lógica de catálogos y categorías propias (TaskCategory, MealCategory, PlanCategory).

## 5. Pruebas (qa-tester)
| Caso | Tipo | Nivel | Test |
|---|---|---|---|
| Secciones, rutas y 404 de Planes | Feature | 1 | `PlanModuleTest`, `CategoryHierarchyTest` |
| Comidas y Tareas siguen igual | Feature | 1 | `MealSettingsTest`, `TaskSettingsTest` |
| Sin infracciones nuevas | Feature | 2 | conformidad de UI |
- Comando para verificar: `php artisan test --filter="MealSettings|TaskSettings|Plan|CategoryHierarchy"`, `php artisan ui:conformance`, `npm run build`.
- Regresión a vigilar: `ModuleExperienceTest`, `ScreenArchetypeTest`.

## 6. Documentación (tech-writer)
- Docs a actualizar: `docs/design-system.md` (patrón «ajustes con secciones»).

## 7. Tareas
- [x] T1 · Comidas y Tareas con `x-settings-sections` y CSS común — Resp: developer — Modelo: estandar — Archivos: componentes, vistas, CSS — Hecho cuando: sin CSS duplicado y tests verdes.
- [x] T2 · Planes con rutas por sección — Resp: developer — Modelo: estandar — Archivos: `PlanSettings`, vista, rutas — Hecho cuando: 404 y URL por sección.
- [x] T3 · Pruebas y documentación — Resp: developer — Modelo: estandar — Archivos: tests, docs — Hecho cuando: pasan los tests.

## 8. Bitácora de implementación (developer)
- 2026-11-17 — Implementado: Comidas (incluida la vista de horarios), Tareas y Planes usan `x-settings-sections` con `SECTIONS` como mapa y las clases `module-settings-*`; se retira el CSS duplicado de `_meals.css`, `_tasks.css` y `_plans.css`. Planes pasa a rutas por sección (`plans.settings`, `plans.settings.categories`) con `#[Locked] $section` y 404 si no existe; ya no se cambia con `wire:click`. Tests de Planes y `CategoryHierarchyTest` reescritos con `['section' => …]`; selectores de las specs de navegador de compras, tareas y planes actualizados.
- Verificación local: `PlanModuleTest` (con una prueba nueva de rutas y 404), `CategoryHierarchyTest`, Comidas, Tareas y `ModuleExperienceTest` con los mismos fallos preexistentes que la línea base (ningún fallo nuevo); `npm run build` correcto; capturas de Planes y Comidas revisadas. Las specs de navegador no se ejecutaron en este entorno.

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-17 — Creado en to-do/ (Borrador, nivel M)
- 2026-11-17 — Parte del plan «arquitectura común» aceptado en la conversación (inventario de duplicación previo).
- 2026-11-17 — Aprobado por el usuario (Compuerta 2)
- 2026-11-17 — Movido a in-progress/ (En desarrollo)
- 2026-11-17 — Implementación terminada → En verificación
