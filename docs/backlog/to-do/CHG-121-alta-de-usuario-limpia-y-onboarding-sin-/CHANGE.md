# CHG-121 · Alta de usuario limpia y onboarding sin catálogos ajenos
**Estado:** Borrador
**Nivel:** M
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-16 · **Aprobado:** — · **Cerrado:** —
**Relacionado:** UX-008, UX-009, UX-010, UX-011, UX-012, UX-014 (docs/backlog/UX-DESIGN.md); CHG-091–094, CHG-116 (bienvenida guiada). UX-013 queda fuera por decisión del usuario.

## 0. En palabras simples
- Qué vas a ver distinto: una cuenta nueva ya no trae listas de módulos que aún no usas (ánimo, ejercicios, bebidas, vehículos…). Cada lista aparece completa la primera vez que abres ese módulo. Nadie recibe la rutina personal del autor ni hábitos a evitar de ejemplo. En la bienvenida puedes elegir «Hacerlo después» en vez de crear un hábito. Las cuentas nuevas ven estados de tarea en español sencillo («Por hacer», «Listo para empezar», «En curso», «Hecho») y menos círculos de relaciones.
- Cómo comprobarlo tú: crea una cuenta nueva → solo ves Hábitos, Objetivos y Tareas, y en Tareas hay 11 categorías → abre Ejercicio y aparece su catálogo → en Hábitos no hay botón «Restaurar hábitos predeterminados». Tu cuenta actual no cambia en nada.

## 1. Resumen
El alta de usuario hoy siembra datos en dos sitios (`User::booted` y `RegisterController`), crea ~170 filas de catálogos de módulos aún no activos, ofrece restaurar una rutina personal de 22 hábitos, deja un seeder con la cuenta real del autor y plantillas de mantenimiento con categorías inconsistentes. Se unifica el alta en una acción idempotente, se siembra cada catálogo al abrir su módulo, se retira la rutina personal de producción, se arreglan seeder y plantillas, se permite omitir el hábito en la bienvenida y se limpia el vocabulario. **Las cuentas existentes no cambian** (ningún catálogo se recrea ni se borra).

## 2. Producto (product-manager)
- Problema / usuario: una persona nueva debe empezar con un espacio limpio, suyo y sin datos que no pidió; hoy ve catálogos ajenos o llenos de módulos que no eligió y no puede salir de la bienvenida sin crear un hábito.
- Incluye: UX-008, UX-009, UX-010, UX-011, UX-012, UX-014.
- No incluye: UX-013 (recuperación de contraseña, zona horaria, aviso legal); cambios a cuentas existentes; nuevos módulos; reducir los catálogos por contenido más allá de lo indicado en T2.
- Criterios de aceptación:
  - Dado un registro nuevo, Cuando termina, Entonces existen solo las categorías de Tareas (11) y los estados de tarea; no hay filas de ejercicio, bebidas, ánimo, vehículo, círculos, compras ni `user_categories`.
  - Dado esa cuenta, Cuando abre por primera vez un módulo (web o MCP), Entonces su catálogo queda sembrado una sola vez; si luego borra un valor, no reaparece.
  - Dada una cuenta existente, Cuando se despliega, Entonces no se agrega ni se quita ningún catálogo.
  - Dado «Hábitos» sin registros, Entonces no hay acción para restaurar predeterminados y se ofrece crear el primero.
  - Dado el paso 3 de la bienvenida, Cuando elige «Hacerlo después», Entonces la guía se completa sin hábito y entra a Mi día.
  - Dado un entorno de producción, Entonces `db:seed` no crea ningún usuario.

## 3. UX/UI
### 3.1 Experiencia (ux-designer)
- Flujo y cantidad de pasos: la bienvenida sigue en 4 pasos; el paso 3 añade una salida secundaria. El resto es invisible (siembra al abrir el módulo).
- Qué contiene cada pantalla y qué es lo más importante: paso 3 conserva ideas y «Crear el mío»; «Hacerlo después» es acción de texto, no compite con la primaria. Resumen sin hábito explica dónde crear el primero.
- Estados: Hábitos vacío → mensaje + «Crear mi primer hábito» (FAB canónico). Sin errores nuevos.
- Información necesaria y momento de presentación: ninguna decisión adicional en el registro.
- Accesibilidad de uso: «Hacerlo después» alcanzable con teclado, foco visible, orden lógico tras las ideas.

```
Paso 3 de 4 · Tu primer hábito
┌────────────────────────────────────┐
│ ( ) Tomar agua                     │
│ ( ) Hacer una pausa breve          │
│ [Ver más ideas]                    │
│                                    │
│ [Preparar hábito]  (primaria)      │
│ Hacerlo después     (texto)        │
└────────────────────────────────────┘
```

### 3.2 Visual (ui-designer)
- Distribución: ver boceto; «Hacerlo después» debajo de la acción primaria.
- Componentes existentes a reutilizar: `resources/views/livewire/onboarding/welcome.blade.php`, `x-ui.action` (variant text), empty state de `habit-settings.blade.php`, `x-ui.fab`.
- Jerarquía, color, tipografía y espaciado: tokens existentes; sin estilos nuevos.
- Responsive y contraste: mismo layout responsive de la bienvenida; contraste de variante texto ya validado.

### 3.3 Escritura (ux-writer)
- Ubicación y estado: paso 3, resumen, estado vacío de Hábitos, estados de tarea, círculos.
- Textos finales: «Hacerlo después»; resumen sin hábito: «Cuando quieras, crea tu primer hábito desde Hábitos.»; vacío: «Aún no tienes hábitos» / «Crear mi primer hábito»; estados «Por hacer», «Listo para empezar», «En curso», «Hecho»; círculos «Pareja», «Familia», «Amigos», «Trabajo» solo si se confirma (ver §9).
- Reglas de docs/UX-WRITING.md aplicadas: tono sin culpa, verbo claro, sin jerga (WIP, Ready, Done).
- Criterios de aceptación de los textos: ningún anglicismo en estados nuevos; ningún texto presiona a crear el hábito.

## 4. Diseño técnico (software-architect)
- Archivos a crear / modificar:
  - Nuevo `app/Actions/ProvisionNewUser.php` (T1) y `app/Services/Catalogs/ModuleCatalogs.php` (T2) con mapa módulo → provisionadores (`DefaultExerciseTypes::createFor`, bebidas extraídas de `RegisterController` a `DefaultDrinkTypes`, `DefaultMoodStates`, `DefaultVehicleExpenseCategories`, `SeedDefaultCircles`, `SeedDefaultMealCategories`, `CategoryCatalog::seedFor` por dominio).
  - `app/Models/User.php` (quitar siembra del `created`), `app/Http/Controllers/Auth/RegisterController.php`, nuevo middleware `EnsureModuleCatalogs` en el grupo autenticado (resuelve el módulo con `config/modules.php` → `patterns`), punto común de herramientas MCP (clase base de `app/Mcp/Tools`), `database/seeders/DatabaseSeeder.php`, `DemoSeeder` (nuevo), `HabitDefinitionSeeder`/`NegativeHabitDefinitionSeeder`, `DefaultHabitDefinitions`, `HabitSettings` y su vista, `Welcome` y su vista, `TaskStatus::DEFAULTS`, `SeedDefaultCircles::NAMES`, `DefaultTaskCategories::LEGACY_NAMES`, `DefaultMoodStates`/`DefaultExerciseTypes` (núcleo).
  - Migraciones nuevas: (a) marcar todos los usuarios existentes como catálogos ya sembrados; (b) plantillas globales de mantenimiento + normalización de categorías; (c) crear las categorías de tarea legadas que un usuario use sin tener categoría.
- Enfoque y patrones a seguir:
  - Alta: `ProvisionNewUser` en transacción, idempotente; registro la llama junto a `PersonalExperience::begin`; solo siembra Tareas (categorías). Estados de tarea siguen perezosos (`ensureFor`).
  - Siembra perezosa: bandera por usuario en `module_settings` (clave `catalogs`, lista de módulos sembrados) para no consultar en cada petición. La migración (a) marca todos los módulos para los usuarios existentes: sin ella, quien borró predeterminados los vería reaparecer. «Restaurar predeterminados» manuales siguen igual.
  - Dominios de `user_categories` se siembran con su módulo (mapa `DOMAINS` ya trae el módulo): `drinks`→water, `exercise`, `mood`, `negative-habits`, `contacts`/`relationship-events`→relationships, `maintenance`→vehicles.
  - Núcleo más corto (solo cuentas nuevas): ánimo = 18 emociones base (`nuanced()` queda como ideas de «Restaurar»); ejercicio sin duplicados (quitar «Correr» o «Trotar» y «Pesas de mano»; decidir en implementación sin tocar claves existentes).
  - Hábitos: eliminar `restoreDefaults` y su menú; mover las listas del autor a `DemoSeeder` (solo `local`); `DatabaseSeeder` crea usuario ficticio (`demo@example.test`) y solo si `app()->environment('local')`.
  - Plantillas de mantenimiento: la migración (b) hace `updateOrCreate` de las globales (como el seeder actual, que se elimina), unifica categorías a claves sin tilde y con etiqueta legible (`seguridad`, `motor`, `motor y fluidos`, `filtros`, `frenos`, `ruedas`, `direccion y suspension`, `transmision`, `electrico`, `general`); reapunta plantillas propias y fusiona duplicados en `user_categories` (`transmisión`→`transmision`, `eléctrico`→`electrico`, `dirección y suspensión`→`direccion y suspension`) con nombre visible legible.
  - Omitir hábito: `Welcome::skipHabit()` marca `state['habit_skipped']` y `completed_at`; `finish()` acepta hábito o `habit_skipped`; evento `events.skipped_habit`. `experience:report` lo cuenta aparte.
  - Vocabulario: nuevos nombres solo para cuentas sin estados (`ensureFor` ya crea desde `DEFAULTS`); revisar código que resuelva estados por nombre literal (`Ready`, `Done`, `WIP`) y usar el `type`.
- Riesgos y mitigación: (1) resucitar catálogos borrados → migración (a) y test; (2) herramientas MCP/API que listan catálogos sin pasar por web → punto común MCP y test; (3) lógica que busque estados por nombre → buscar por `type`; (4) renombrar claves de mantenimiento rompe registros → conservar la clave existente cuando haya uso y fusionar solo duplicados; migración reversible o documentada como no reversible.
- Qué NO tocar: datos de cuentas existentes, reglas del juego, preferencias de módulos, UX-013, `ui:conformance` baseline.

## 5. Pruebas (qa-tester)
| Caso | Tipo | Nivel | Test |
|---|---|---|---|
| Registro crea solo categorías de tarea | PHP | Feature | `ProvisionNewUserTest` |
| Misma acción por registro y factory/seeder da el mismo resultado | PHP | Feature | `ProvisionNewUserTest` |
| Abrir cada módulo siembra una vez y no restaura borrados | PHP | Feature | `ModuleCatalogsTest` |
| Herramienta MCP de catálogo siembra al primer uso | PHP | Feature | `ModuleCatalogsTest` |
| Usuarios existentes no reciben ni pierden catálogos tras migrar | PHP | Migración | `ModuleCatalogsMigrationTest` |
| Hábitos sin botón de restaurar; vacío ofrece crear | PHP/Livewire | Feature | `HabitSettingsTest` |
| `db:seed` en producción no crea usuarios; en local crea demo ficticio | PHP | Feature | `DatabaseSeederTest` |
| Plantillas globales existen tras migrar; categorías sin duplicados | PHP | Migración | `MaintenanceTemplateMigrationTest` |
| «Hacerlo después» completa la guía; recarga conserva estado | PHP + navegador | Feature/E2E | `PersonalExperienceTest`, Playwright `personal-experience` |
| Estados nuevos en cuenta nueva; existentes sin cambio | PHP | Feature | `TaskStatusTest` |
- Comando para verificar: `php artisan test --filter='ProvisionNewUser|ModuleCatalogs|PersonalExperience|HabitSettings|CategoryHierarchy|TaskStatus|MaintenanceTemplate|DatabaseSeeder'`, `npx playwright test -c tests/Browser/personal-experience.config.js`, `php artisan ui:conformance`, `git diff --check`.
- Regresión a vigilar: `CategoryHierarchyTest` (migración 2026_10_13), pruebas que asumen catálogos creados al registrar, MCP de tareas por nombre de estado.

## 6. Documentación (tech-writer)
- Docs a actualizar: `docs/first-experience.md` (alta limpia, omitir hábito), `docs/category-catalogs.md` (siembra perezosa; «Las nuevas cuentas reciben sus catálogos automáticamente» deja de ser cierto), `docs/DEPLOY.md` (plantillas ya no dependen del seeder), y marcar UX-008–012 y 014 en `docs/backlog/UX-DESIGN.md`.

## 7. Tareas
- [ ] T1 · Un alta única y limpia: el registro solo crea lo de Tareas, con una sola acción reutilizable — Resp: developer — Modelo: estandar — Archivos: app/Actions/ProvisionNewUser.php (nuevo), app/Support/DefaultDrinkTypes.php (nuevo), app/Models/User.php, app/Http/Controllers/Auth/RegisterController.php, tests/Feature/ProvisionNewUserTest.php — Hecho cuando: registro, factory y seeder producen la misma cuenta; no queda lista de bebidas duplicada; no hay filas de otros módulos al registrarse.
- [ ] T2 · Cada catálogo aparece al abrir su módulo, una sola vez, sin tocar cuentas existentes — Resp: developer — Modelo: estandar — Archivos: app/Services/Catalogs/ModuleCatalogs.php (nuevo), app/Http/Middleware/EnsureModuleCatalogs.php (nuevo), bootstrap/app.php o routes/web.php, app/Mcp/Tools (base común), database/migrations/*_mark_existing_catalogs_provisioned.php, app/Support/DefaultMoodStates.php, app/Support/DefaultExerciseTypes.php, tests/Feature/ModuleCatalogsTest.php — Hecho cuando: abrir el módulo (web y MCP) siembra una vez; borrar un valor no lo trae de vuelta; usuarios existentes quedan intactos.
- [ ] T3 · Sin rutina personal ni seeder con datos reales — Resp: developer — Modelo: estandar — Archivos: app/Support/DefaultHabitDefinitions.php, app/Livewire/Habit/HabitSettings.php, resources/views/livewire/habit/habit-settings.blade.php, database/seeders/DatabaseSeeder.php, database/seeders/DemoSeeder.php (nuevo), HabitDefinitionSeeder.php, NegativeHabitDefinitionSeeder.php, tests/Feature/HabitSettingsTest.php, tests/Feature/DatabaseSeederTest.php — Hecho cuando: no existe «Restaurar hábitos predeterminados»; el vacío ofrece crear; el seeder base solo crea datos ficticios en local; la rutina del autor vive en DemoSeeder.
- [ ] T4 · Plantillas de mantenimiento fiables y categorías legibles — Resp: developer — Modelo: estandar — Archivos: database/seeders/MaintenanceTemplateSeeder.php, database/migrations/*_seed_maintenance_templates_and_normalize_categories.php, app/Support/CategoryCatalog.php, tests/Feature/MaintenanceTemplateMigrationTest.php — Hecho cuando: las plantillas globales existen sin ejecutar seeders; no hay categorías duplicadas por tilde; los nombres visibles son legibles y los registros conservan su categoría.
- [ ] T5 · «Hacerlo después» en la bienvenida — Resp: developer — Modelo: estandar — Archivos: app/Livewire/Onboarding/Welcome.php, resources/views/livewire/onboarding/welcome.blade.php, app/Services/Experience/PersonalExperience.php, app/Console/Commands (experience:report), tests/Feature/PersonalExperienceTest.php, tests/Browser/personal-experience.spec.js — Hecho cuando: se puede completar la guía sin hábito, el estado persiste, el informe lo cuenta aparte y axe/teclado pasan a 390/768/1440 px.
- [ ] T6 · Vocabulario sencillo y sin nombres del autor — Resp: developer — Modelo: estandar — Archivos: app/Models/TaskStatus.php, app/Actions/SeedDefaultCircles.php, app/Support/DefaultTaskCategories.php, database/migrations/*_backfill_legacy_task_categories.php, tests/Feature/TaskStatusTest.php — Hecho cuando: cuentas nuevas ven los estados y círculos nuevos; las existentes no cambian; ninguna lógica depende del nombre literal de un estado; no quedan Siigo/EntreAgiles/Gesthor/CertMind/Unicauca en código.
- [ ] T7 · Documentación al día — Resp: tech-writer — Modelo: estandar — Archivos: docs/first-experience.md, docs/category-catalogs.md, docs/DEPLOY.md, docs/backlog/UX-DESIGN.md — Hecho cuando: refleja el comportamiento nuevo y marca los hallazgos como resueltos.

## 8. Bitácora de implementación (developer)

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-16 — Creado en to-do/ (Borrador, nivel M)
- 2026-11-16 — Decisión del usuario: corregir UX-008, 009, 010, 011, 012 y 014; UX-013 queda fuera.
- ABIERTA: círculos para cuentas nuevas. Propuesta: Pareja, Familia, Amigos, Trabajo (hoy 6, con solapes). ¿Se conserva «Pareja» como círculo inicial?
- 2026-11-16 — Decisión: los estados nuevos aplican solo a cuentas nuevas (los flujos externos que usan «Ready»/«Borrador» por texto no se tocan). Antes: estados de tarea nuevos cambian nombres que algunos flujos externos (skills de MiSo) usan por texto («Ready», «Borrador»). Propuesta: solo cuentas nuevas; la cuenta del autor no cambia.
- Nota de entorno: el entorno cloud tiene PHP 8.3 y `composer.lock` exige 8.4; las pruebas pueden no poder ejecutarse aquí (lección de CHG-117).
