# CHG-135 · Auditoría integral: consultas repetidas, suite de pruebas y pulido
**Estado:** Cerrado
**Nivel:** M
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-16 · **Aprobado:** 2026-11-16 · **Cerrado:** 2026-11-16
**Relacionado:** —

## 0. En palabras simples
<!-- Para el usuario: sin jerga. Si no se entiende en 30 segundos, reescríbelo. -->
- Qué vas a ver distinto: la app hace lo mismo con menos idas a la base de datos (Compras baja de 87 a unas 25 consultas; todas las pantallas dejan de leer dos veces los ajustes de Pomodoro; los catálogos de categorías se leen una vez por pantalla). Las fechas salen en español aunque la instalación no lo configure («lunes 16 de noviembre», no «Monday 16 de November»). En el celular, las tarjetas de resumen (Objetivos y otras 7 pantallas) van de dos en dos en vez de apiladas, y en Salud el título del evento ya no se corta a 4 letras. Las pruebas automáticas vuelven a poder ejecutarse.
- Cómo comprobarlo tú: abre Comidas › Compras, Vehículos › Catálogo, Hidratación y Pomodoro: muestran lo mismo que antes. En el celular abre Objetivos y Salud. La tabla antes/después queda en `docs/performance.md`.

## 1. Resumen
<!-- Qué se cambia y por qué (3-5 líneas). Entendimiento aprobado por el usuario. -->
Auditoría pedida por el usuario el 2026-11-16 («analiza toda la aplicación e implementa con autonomía las mejoras de mayor impacto, sin cambiar funcionalidad, reglas, APIs ni datos»). Se midió cada ruta GET con datos sintéticos (consultas, duplicados y tamaño) y se revisaron 21 pantallas a 390 y 1440 px. Se corrigen las causas medidas: un N+1 en Compras, lecturas idénticas repetidas (ajustes de Pomodoro, catálogos de categorías, ubicaciones, tipo de bebida por fila), un error fatal que impide ejecutar toda la suite de pruebas, el idioma por defecto en inglés y dos problemas de aprovechamiento del espacio en móvil. Sin cambios de esquema.

## 2. Producto (product-manager)
- Problema / usuario: la suite de pruebas no arranca (error fatal de PHP en un archivo de pruebas), así que ningún cambio puede validarse; varias pantallas repiten consultas; una instalación nueva muestra fechas mitad en inglés; en el celular el resumen empuja la lista fuera de la primera pantalla.
- Incluye: arreglo de la suite; idioma español por defecto; servicio único para los ajustes de Pomodoro; carga por lotes en la vista previa de Compras; memoria por petición del catálogo de categorías; carga anticipada del tipo de bebida y de ubicaciones; rejilla de métricas a dos columnas en móvil; resumen del evento de Salud en móvil; documentación de mediciones y hallazgos no implementados.
- No incluye: cambios de esquema o índices, caché persistente, dependencias nuevas, rediseños de pantallas, cambios en reglas de negocio o en herramientas MCP, las pruebas que ya fallaban por otras causas (se documentan).
- Criterios de aceptación (Dado/Cuando/Entonces):
  - Dado el repositorio limpio, cuando se ejecuta `php artisan test`, entonces la suite corre completa sin error fatal.
  - Dada una instalación sin `APP_LOCALE`, cuando se abre Mi día, entonces la fecha aparece en español.
  - Dadas N comidas planificadas con receta, cuando se abre Compras, entonces el número de consultas no crece con N.
  - Dada cualquier página, cuando se renderiza, entonces los ajustes de Pomodoro se leen como mucho una vez.
  - Dado un catálogo de categorías usado varias veces en una pantalla, cuando se renderiza, entonces se consulta una vez; y tras crear, renombrar o borrar una categoría se ve el dato nuevo.
  - Dada cualquier pantalla tocada, cuando se compara con la versión anterior, entonces muestra los mismos datos y textos.

## 3. UX/UI
<!-- Sin pantallas: escribe "No aplica — motivo" en cada parte. Con UI, approve exige un boceto ASCII en un bloque de código. Si hay flujo, agrega un diagrama en un bloque mermaid. -->
### 3.1 Experiencia (ux-designer)
- Plantilla: no aplica una plantilla nueva; se conservan el shell de módulo y las pantallas existentes (`## Anatomía` de docs/UX-DESIGN.md no define estas páginas).
- Flujo y cantidad de pasos: sin cambios.
- Qué contiene cada pantalla y qué es lo más importante: igual que hoy; en móvil la lista sube porque el resumen ocupa una fila en vez de dos, y en Salud lo primero que se lee es el título completo del evento.
- Estados (vacío / carga / error / éxito / validaciones): sin cambios.
- Información necesaria y momento de presentación: sin cambios; ningún dato se oculta.
- Accesibilidad de uso (teclado, foco, errores): sin cambios de marcado ni de orden de foco; solo CSS.

### 3.2 Visual (ui-designer)
- Distribución (solo por debajo de 600 px / 768 px):

```
Objetivos (390 px)                    Salud (390 px)
antes            después              antes                    después
┌────────────┐   ┌─────┐ ┌─────┐      16 ┌ ⚕ Even… [Cita médica] ⌄┐   16 ┌ ⚕ Evento de salud     ⌄┐
│ Activos  8 │   │Act 8│ │Com 0│      NOV└────────────────────────┘   NOV│   Cabeza · 16 nov      │
└────────────┘   └─────┘ └─────┘                                          │   [Cita médica]        │
┌────────────┐   ┌ Objetivos (8) ┐                                        └────────────────────────┘
│ Complet. 0 │   │ …             │
└────────────┘
```

- Componentes existentes a reutilizar (ruta): `resources/views/components/ui/metric-grid.blade.php` y `metric.blade.php` (sin cambios de marcado); `resources/views/livewire/health/health-index.blade.php` (sin cambios de marcado).
- Jerarquía, color, tipografía y espaciado (tokens): los existentes; el hueco de la rejilla pasa a `--md-sys-space-sm` en móvil.
- Responsive y contraste: una tercera métrica impar ocupa el ancho completo; se comprueban a 390 px las 8 pantallas que usan `x-ui.metric-grid`. Contraste sin cambios.

### 3.3 Escritura (ux-writer)
- Ubicación y estado: No aplica — no se agrega ni cambia ningún texto.
- Textos finales (variables y variantes): No aplica — las fechas las produce Carbon en el idioma de la app.
- Reglas de docs/UX-WRITING.md aplicadas: No aplica — sin textos nuevos.
- Criterios de aceptación de los textos: ningún texto funcional cambia.

## 4. Diseño técnico (software-architect)
- Archivos a crear / modificar: `tests/Feature/Social/FriendMilestonesCommandTest.php`; `config/app.php`, `.env.example`, `phpunit.xml`; `app/Support/PomodoroSettings.php` (nuevo) y sus 7 consumidores (`StatusBar`, `PomodoroTimer`, `PomodoroSettings` Livewire, `WallPanelData`, `PomodoroAdvice`, `FocusSourceObserver`, `ListFocusSessionsTool`); `app/Providers/AppServiceProvider.php`; `app/Services/Meal/{MealNeeds,MealInventory}.php`; `app/Support/CategoryCatalog.php`; `app/Livewire/Water/WaterDaily.php`; `app/Livewire/Meal/MealIngredients.php`; `resources/css/m3/patterns/_metrics.css`, `resources/css/m3/modules/_health.css` y los CSS publicados; `tests/Feature/BackendQueryBudgetTest.php`.
- Enfoque y patrones a seguir: los de CHG-095. Memoria solo dentro de la petición en servicios `scoped`, vaciada por los eventos Eloquent del modelo de origen (`ModuleSetting`, `UserCategory`); carga por lotes con `with()` donde quien llama ya tiene la colección; presupuestos de consultas en `BackendQueryBudgetTest`. Un solo lugar para los valores por defecto de Pomodoro (hoy repetidos en 7 archivos).
- Riesgos y mitigación: dato viejo en la memoria → invalidación por eventos y prueba explícita; `CategoryCatalog::seedFor` escribe con `DB::table` (sin eventos) → vacía la memoria a mano; cambio de idioma por defecto → `APP_LOCALE` sigue mandando si está definido; CSS móvil → capturas antes/después de las 8 pantallas.
- Qué NO tocar: esquema de base de datos, `whereDate`, drivers de caché y sesión, `composer.lock`, lógica de negocio del inventario, pruebas que fallan por causas ajenas a este cambio.

## 5. Pruebas (qa-tester)
| Caso | Tipo | Nivel | Test |
|---|---|---|---|
| La suite carga sin error fatal | Feature | Suite | `php artisan test` |
| Compras no crece con las comidas planificadas | Feature | Servicio | `tests/Feature/BackendQueryBudgetTest.php` |
| Ajustes de Pomodoro: una lectura por petición y nunca viejos tras guardar | Feature | Servicio | `tests/Feature/BackendQueryBudgetTest.php` |
| Catálogo de categorías: una lectura y fresco tras cambios | Feature | Servicio | `tests/Feature/BackendQueryBudgetTest.php` |
| Hidratación no consulta el tipo de bebida por fila | Feature | Livewire | `tests/Feature/BackendQueryBudgetTest.php` |
| Comportamiento existente intacto | Feature | Suite | `php artisan test` |
- Comando para verificar: `php artisan test --filter=BackendQueryBudgetTest` y `php artisan test`.
- Regresión a vigilar: meta de foco entre semana/fin de semana, ajustes de Pomodoro recién guardados, vista previa y generación de compras, catálogos tras editar categorías.

## 6. Documentación (tech-writer)
- Docs a actualizar: `docs/performance.md` (medición, antes/después, estado de la suite y hallazgos no implementados, incluidas las máquinas de estado implícitas) y `docs/backlog/UX-DESIGN.md` (UX-008 y UX-009).

## 7. Tareas
<!-- Primero lo que cambia para el usuario; el detalle técnico después. Formato:
- [x] T1 · qué cambia (en simple) — Resp: developer — Modelo: perfil — Archivos: … — Hecho cuando: … -->
- [x] T1 · Las pruebas vuelven a ejecutarse y las fechas salen en español por defecto — Resp: developer — Modelo: estandar — Archivos: tests/Feature/Social/FriendMilestonesCommandTest.php, config/app.php, .env.example, phpunit.xml — Hecho cuando: `php artisan test` corre completo y bajan los fallos por idioma.
- [x] T2 · Los ajustes de Pomodoro se leen una vez por pantalla desde un solo lugar — Resp: developer — Modelo: estandar — Archivos: app/Support/PomodoroSettings.php y sus 7 consumidores, app/Providers/AppServiceProvider.php — Hecho cuando: ninguna página repite esa lectura y pasan las pruebas de Pomodoro, barra de estado y panel.
- [x] T3 · Compras carga las recetas planificadas por lotes — Resp: developer — Modelo: estandar — Archivos: app/Services/Meal/MealNeeds.php, MealInventory.php — Hecho cuando: el presupuesto no crece con las comidas y pasan las pruebas de comidas.
- [x] T4 · Catálogos, ubicaciones y tipo de bebida sin lecturas repetidas — Resp: developer — Modelo: estandar — Archivos: app/Support/CategoryCatalog.php, app/Livewire/Water/WaterDaily.php, app/Livewire/Meal/MealIngredients.php — Hecho cuando: los presupuestos nuevos pasan y las pruebas de catálogos, agua e ingredientes siguen igual.
- [x] T5 · En el celular el resumen va de dos en dos y Salud muestra el título completo — Resp: developer — Modelo: estandar — Archivos: resources/css/m3/patterns/_metrics.css, resources/css/m3/modules/_health.css, resources/css/m3/modules/_statistics.css, 9 vistas con fechas, CSS publicados — Hecho cuando: capturas a 390 px sin desbordes y `ui:conformance` sin deuda nueva.
- [x] T6 · Mediciones y hallazgos documentados — Resp: developer — Modelo: estandar — Archivos: docs/performance.md, docs/backlog/UX-DESIGN.md — Hecho cuando: están la tabla antes/después y lo que se dejó sin tocar con su motivo.

## 8. Bitácora de implementación (developer)
- 2026-11-16 — T1: `count()` → `notices()` en la prueba social (era `final` en PHPUnit: error fatal al cargar). Idioma por defecto `es` en `config/app.php`, `.env.example` y `phpunit.xml`.
- 2026-11-16 — T2: `App\Support\PomodoroSettings` (scoped) sustituye siete lecturas con los mismos valores por defecto copiados.
- 2026-11-16 — T3/T4: carga por lotes en la vista previa de compras, memoria por petición del catálogo de categorías, ubicaciones y tipo de bebida. Compras 87 → 25 consultas; tabla completa en `docs/performance.md`.
- 2026-11-16 — T5: métricas a dos columnas y estado del evento de Salud en segunda línea en móvil. Encontrado al verificar: Ejercicio › Estadísticas desbordaba 138 px en horizontal a 390 px (ya ocurría antes); la serie de barras ya no ensancha la página y rotula una de cada cinco columnas. Doce fechas de vistas pasan a `translatedFormat()`.
- 2026-11-16 — Validación: suite sin Browser 1.799 pruebas, 38 fallos (46 antes), los mismos 38 con y sin los cambios; `BackendQueryBudgetTest` sin fallos; `npm run test:js` 59/59; `ui:conformance` 20 hallazgos antes y después; capturas a 390 y 1440 px de las pantallas tocadas sin desborde.
- 2026-11-16 — Desviación de proceso: `start` rechazó el cambio porque ya hay seis CHG «En desarrollo» y pide `--force`; no se forzó. El CHG queda **Aprobado** con las tareas marcadas: falta que el usuario lo inicie (`start 135 --force`) y lo pase a verificación.
- 2026-11-16 — Entorno: el contenedor tiene PHP 8.3 y `composer.lock` exige 8.4; se probó con Symfony 7.4 instalado solo en `vendor/` (el lock no se modificó).

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-16 — Creado en to-do/ (Borrador, nivel M)
- 2026-11-16 — Alcance decidido por medición; el usuario pidió implementar con autonomía. Sin preguntas abiertas.
- 2026-11-16 — Aprobado por el usuario (Compuerta 2)
- 2026-11-16 — Movido a in-progress/ (En desarrollo)
- 2026-11-16 — Implementación terminada → En verificación
- 2026-11-16 — Cerrado por el usuario (Compuerta 3) → done/
