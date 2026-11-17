# CHG-147 · Formación: vista de detalle, acciones y ajustes con categorías
**Estado:** En verificación
**Nivel:** M
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-17 · **Aprobado:** 2026-11-17 · **Cerrado:** —
**Relacionado:** CHG-138, CHG-141

## 0. En palabras simples
- Qué vas a ver distinto: al abrir una formación ya no hay migas de pan ni una elipsis perdida en una línea. Arriba ves las etiquetas (tipo, estado, institución) juntas y una sola elipsis con «Agregar semestre/materia…», editar, fijar y eliminar. En Ajustes de Formación hay una barra lateral con «Vocabulario» y «Categorías», y las categorías vienen con valores por defecto.
- Cómo comprobarlo tú: abre una formación, pulsa la elipsis y agrega un semestre sin usar el botón flotante; ve a Ajustes > Categorías y crea una.

## 1. Resumen
La vista de detalle usa unas migas artesanales no aprobadas, una elipsis suelta que solo lleva a «Categorías», un menú duplicado y datos dispersos (la institución va como texto aparte). Crear contenido solo es evidente desde el botón flotante. Los ajustes solo tienen el vocabulario y las categorías no tienen sección ni valores por defecto. Se alinea con el sistema de diseño (flecha de regreso en la barra, acciones en el ⋯) y con el patrón de ajustes con barra lateral.

## 2. Producto (product-manager)
- Problema / usuario: no se encuentra cómo crear categorías, el detalle se ve desordenado y quien no ve el botón flotante no sabe cómo agregar contenido.
- Incluye:
  - Quitar migas y la elipsis suelta; un único menú ⋯ de acciones con «Agregar …», Editar, Fijar y Eliminar.
  - «Agregar …» también en el ⋯ de la card «Contenido» y en el estado vacío.
  - Cabecera con chips de tipo, estado e institución.
  - Ajustes con secciones laterales (Vocabulario, Categorías), gestión de categorías de Formación y categorías por defecto (nuevas y para usuarios existentes).
- No incluye: cambios de modelo de contenido, MCP de Formación, el resto de módulos.
- Criterios de aceptación (Dado/Cuando/Entonces):
  - Dado el detalle de una formación, cuando se abre, entonces no hay migas y el regreso es la flecha de la barra.
  - Dado el ⋯ del encabezado, cuando se abre, entonces ofrece «Agregar» por cada tipo permitido, Editar, Fijar y Eliminar, y «Agregar» abre el formulario.
  - Dado un usuario nuevo o existente, cuando entra a Ajustes > Categorías, entonces ve las categorías por defecto y puede crear, editar y eliminar (no si están en uso).
  - Dado Ajustes de Formación, cuando se abre, entonces hay una barra lateral con las dos secciones y la activa está marcada.

## 3. UX/UI
### 3.1 Experiencia (ux-designer)
- Plantilla: detalle con acciones en ⋯ (como `goal-detail`) y ajustes con barra lateral (como `task-settings` / `meal-settings`).
- Flujo y cantidad de pasos: agregar contenido en 2 pasos (⋯ → formulario).
- Qué contiene cada pantalla y qué es lo más importante: chips y descripción, tarjeta de contenido, acciones; en ajustes, la sección activa.
- Estados (vacío / carga / error / éxito / validaciones): estado vacío con acción directa; categoría en uso no se elimina y lo explica.
- Información necesaria y momento de presentación: institución, fechas y créditos como metadatos junto a los chips.
- Accesibilidad de uso (teclado, foco, errores): menús con foco y `aria-label`; nav lateral con `aria-current`.

```
← Formación · Ingeniería de sistemas                ⋮
[Pregrado] [En curso] [UNAL]                  📌 ⋮
┌ 🎓 Contenido · 0                       ⋮ ┐
│  Sin contenido todavía                   │
│  [ Agregar semestre ]                    │
└──────────────────────────────────────────┘
Ajustes:  contenido │ Vocabulario
                    │ Categorías  (activa)
```

### 3.2 Visual (ui-designer)
- Distribución: chips en `.learning-summary`, acciones a la derecha en la misma fila; ajustes en grid `1fr 260px` con nav lateral.
- Componentes existentes a reutilizar (ruta): `x-ui.chip`, `x-ui.menu`, `x-module-actions`, `x-ui.management-card`, `x-ui.form-dialog`; patrón `resources/views/livewire/task/task-settings.blade.php`.
- Jerarquía, color, tipografía y espaciado (tokens): tokens M3; CSS en `_learning.css`.
- Responsive y contraste: 360/390/1440; el nav lateral colapsa como en Tareas.

### 3.3 Escritura (ux-writer)
- Ubicación y estado: menú ⋯, estado vacío, ajustes.
- Textos finales (variables y variantes): «Agregar {semestre/materia…}» con `LearningVocabulary`; estado vacío «Aún no hay contenido. Agrega {semestre}.»; secciones «Vocabulario» y «Categorías».
- Reglas de docs/UX-WRITING.md aplicadas: términos de la guía, verbos concretos, sin mencionar el botón flotante.
- Criterios de aceptación de los textos: ningún texto remite a «botón flotante».

## 4. Diseño técnico (software-architect)
- Archivos a crear / modificar: `resources/views/livewire/learning/page.blade.php`, `app/Livewire/Learning/LearningPage.php`, `LearningSettings.php` y vistas `learning/settings*.blade.php`, `routes/web.php`, `config/modules.php`, `app/Support/CategoryCatalog.php` (defaults), migración de categorías por defecto, `_learning.css`, `docs/learning.md`, `docs/category-catalogs.md`.
- Enfoque y patrones a seguir: `LearningSettings` con `$section` bloqueada y rutas `learning.settings` / `learning.settings.categories`; categorías sobre `user_categories` dominio `learning` reutilizando la lógica de `Category\CategorySettings` (trait compartido si hace falta); sembrado sin tocar categorías existentes.
- Riesgos y mitigación: ruta `categories.settings/learning` sigue funcionando; CHG-138 comparte vistas → no cambiar contratos de `openUnit`.
- Qué NO tocar: modelos de Formación, MCP, otros módulos.

## 5. Pruebas (qa-tester)
| Caso | Tipo | Nivel | Test |
|---|---|---|---|
| Menú ⋯ con «Agregar» y sin migas | Feature | 1 | `LearningVocabularyTest` / `LearningPageTest` |
| Categorías por defecto y CRUD | Feature | 1 | nuevo `LearningCategoriesTest` |
| Ajustes con secciones y 404 | Feature | 1 | `LearningVocabularyTest` |
| Sin infracciones nuevas | Feature | 2 | `UiConformanceTest` |
| Flujo en 390/1440 | Browser | 3 | spec de Formación existente |
- Comando para verificar: `php artisan test --filter=Learning`, `php artisan ui:conformance`, `npm run build`, capturas a 390 y 1440.
- Regresión a vigilar: `CategoryCatalog` tests, `ScreenArchetypeTest`.

## 6. Documentación (tech-writer)
- Docs a actualizar: `docs/learning.md`, `docs/category-catalogs.md`, `docs/UX-WRITING.md` (excepción de migas).

## 7. Tareas
- [x] T1 · Detalle de formación sin migas ni ⋯ suelto, con «Agregar» en menús y estado vacío — Resp: developer — Modelo: estandar — Archivos: `page.blade.php`, `LearningPage.php`, `_learning.css` — Hecho cuando: cumple el boceto.
- [x] T2 · Ajustes con barra lateral y sección Categorías — Resp: developer — Modelo: estandar — Archivos: `LearningSettings.php`, vistas, rutas, `config/modules.php` — Hecho cuando: se crean, editan y eliminan categorías.
- [x] T3 · Categorías por defecto para nuevos y existentes — Resp: developer — Modelo: estandar — Archivos: `CategoryCatalog.php`, migración — Hecho cuando: sembradas sin duplicar.
- [x] T4 · Pruebas y documentación — Resp: developer — Modelo: estandar — Archivos: tests, docs — Hecho cuando: pasan los tests y capturas a 390/1440.

## 8. Bitácora de implementación (developer)
- 2026-11-17 — Implementado: detalle sin migas (`nav.learning-path` y su CSS retirados), sin elipsis suelta ni «Categorías» en los menús; el ⋯ del encabezado y el de la tarjeta «Contenido» ofrecen «Agregar …» (vía `openUnit`), Editar y Eliminar; chips de tipo, estado, institución, créditos y fechas; estado vacío sin «botón flotante». Ajustes de Formación con barra lateral (Vocabulario y Categorías, rutas `learning.settings` y `learning.settings.categories`) usando la base de CHG-148; categorías por defecto en `CategoryCatalog::defaults('learning')` y migración `2026_11_17_000002_seed_default_learning_categories` para usuarios existentes. Docs: `docs/learning.md`.
- Verificación local: `LearningSettingsSectionsTest` (5), `LearningModuleTest`, `LearningVocabularyTest` y `CategoryHierarchyTest` en verde; spec `learning.interaction.spec.js` actualizada (sin migas) y no ejecutada en este entorno; capturas a 390 y 1440 px revisadas. La migración de sembrado solo se probó con SQLite.

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-17 — Creado en to-do/ (Borrador, nivel M)
- 2026-11-17 — Especificado a partir del plan de pulido aceptado en la conversación; pendiente la Compuerta 2 (aprobación del usuario).
- 2026-11-17 — Aprobado por el usuario (Compuerta 2)
- 2026-11-17 — Movido a in-progress/ (En desarrollo)
- 2026-11-17 — Implementación terminada → En verificación
