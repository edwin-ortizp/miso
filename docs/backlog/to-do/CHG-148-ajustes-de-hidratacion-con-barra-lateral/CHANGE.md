# CHG-148 · Ajustes de Hidratación con barra lateral de secciones
**Estado:** Borrador
**Nivel:** M
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-17 · **Aprobado:** — · **Cerrado:** —
**Relacionado:** CHG-147

## 0. En palabras simples
- Qué vas a ver distinto: los ajustes de Hidratación tienen una barra lateral con «Bebidas», «Meta diaria» y «Categorías», como Tareas o Comidas. Desaparece el botón perdido «Gestionar categorías».
- Cómo comprobarlo tú: entra a Hidratación > Ajustes, cambia de sección con la barra y crea una categoría de bebida.

## 1. Resumen
Los ajustes de agua son una sola página con una card de bebidas, un botón suelto «Gestionar categorías» que sale del módulo y la meta diaria en un panel lateral. Se llevan al patrón de ajustes con secciones y barra lateral usado en Tareas y Comidas, con rutas por sección. No cambia la lógica de bebidas ni de meta.

## 2. Producto (product-manager)
- Problema / usuario: ajustes inconsistentes con el resto de módulos; las categorías se gestionan fuera del contexto.
- Incluye: secciones Bebidas, Meta diaria y Categorías (dominio `drinks`) con rutas propias, barra lateral y eliminación del botón suelto.
- No incluye: otros módulos con el mismo botón suelto (ejercicio, ánimo, vehículos, hábitos negativos, relaciones: CHG aparte), recordatorios o unidades de agua.
- Criterios de aceptación (Dado/Cuando/Entonces):
  - Dado Ajustes de Hidratación, cuando se abre, entonces muestra la sección Bebidas con la barra lateral y la activa marcada.
  - Dado la sección Meta diaria, cuando se guarda un valor válido, entonces actualiza la meta como hoy.
  - Dado la sección Categorías, cuando se crea, edita o elimina una, entonces funciona igual que el catálogo genérico (no se elimina si está en uso).
  - Dado una sección inexistente, cuando se visita, entonces responde 404.

## 3. UX/UI
### 3.1 Experiencia (ux-designer)
- Plantilla: ajustes con barra lateral (como `meal-settings`).
- Flujo y cantidad de pasos: un clic para cambiar de sección.
- Qué contiene cada pantalla y qué es lo más importante: Bebidas (card de gestión), Meta diaria (formulario con equivalente en vasos), Categorías (card de gestión).
- Estados (vacío / carga / error / éxito / validaciones): sin cambios en bebidas; meta con validación 500–10000 ml.
- Información necesaria y momento de presentación: meta sugerida por peso visible en su sección.
- Accesibilidad de uso (teclado, foco, errores): nav con `aria-current`, enlaces con `wire:navigate`.

```
Ajustes de Hidratación
┌ 🥤 Tipos de bebida ...        │ Bebidas  ◀
│  tabla de gestión             │ Meta diaria
│                               │ Categorías
└───────────────────────────────┘
```

### 3.2 Visual (ui-designer)
- Distribución: grid contenido + nav lateral, igual que Comidas.
- Componentes existentes a reutilizar (ruta): `x-ui.management-card`, `x-ui.form-dialog`, `x-ui.category-select`; `resources/views/livewire/meal/meal-settings.blade.php`.
- Jerarquía, color, tipografía y espaciado (tokens): tokens M3; CSS de nav lateral de Comidas/Tareas reutilizado.
- Responsive y contraste: 360/390/1440; el nav colapsa en móvil.

### 3.3 Escritura (ux-writer)
- Ubicación y estado: nombres de secciones y títulos de cards.
- Textos finales (variables y variantes): «Bebidas», «Meta diaria», «Categorías».
- Reglas de docs/UX-WRITING.md aplicadas: etiquetas estables y breves.
- Criterios de aceptación de los textos: se elimina «Gestionar categorías» como botón suelto.

## 4. Diseño técnico (software-architect)
- Archivos a crear / modificar: `app/Livewire/Water/WaterSettings.php`, `resources/views/livewire/water/water-settings.blade.php` y parciales, `routes/web.php`, `config/modules.php`, CSS del módulo, `tests/Feature/WaterSettingsManagementTest.php`.
- Enfoque y patrones a seguir: `#[Locked] $section`, `mount` con 404, rutas `water.settings`, `water.settings.goal`, `water.settings.categories`; categorías reutilizando la lógica de `Category\CategorySettings` para el dominio `drinks`.
- Riesgos y mitigación: el panel lateral de meta (`rail`) desaparece → actualizar test existente y el `'rail'` del módulo; la meta también se edita en Ajustes generales, no tocar.
- Qué NO tocar: `DrinkType`, registro de agua, `WaterGoal`, MCP.

## 5. Pruebas (qa-tester)
| Caso | Tipo | Nivel | Test |
|---|---|---|---|
| Tres secciones renderizan y 404 inválida | Feature | 1 | `WaterSettingsManagementTest` |
| Guardar meta | Feature | 1 | `WaterProgressViewsTest` |
| Categorías de bebidas | Feature | 1 | `WaterSettingsManagementTest` |
| Sin infracciones nuevas | Feature | 2 | `UiConformanceTest` |
- Comando para verificar: `php artisan test --filter=Water`, `php artisan ui:conformance`, `npm run build`, capturas a 390 y 1440.
- Regresión a vigilar: `ModuleExperienceTest`, `ScreenArchetypeTest`.

## 6. Documentación (tech-writer)
- Docs a actualizar: `docs/category-catalogs.md` (ubicación de la gestión de categorías de bebidas).

## 7. Tareas
- [ ] T1 · Ajustes de Hidratación con secciones y barra lateral — Resp: developer — Modelo: estandar — Archivos: `WaterSettings.php`, vistas, rutas, `config/modules.php` — Hecho cuando: cumple el boceto y se quita el botón suelto.
- [ ] T2 · Sección Categorías de bebidas — Resp: developer — Modelo: estandar — Archivos: `WaterSettings.php`, vista — Hecho cuando: se crean, editan y eliminan categorías.
- [ ] T3 · Pruebas y documentación — Resp: developer — Modelo: estandar — Archivos: tests, docs — Hecho cuando: pasan los tests y capturas a 390/1440.

## 8. Bitácora de implementación (developer)

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-17 — Creado en to-do/ (Borrador, nivel M)
- 2026-11-17 — Especificado a partir del plan de pulido aceptado en la conversación; pendiente la Compuerta 2 (aprobación del usuario).
