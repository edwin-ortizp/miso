# MiSo: instrucciones compartidas

MiSo ("mi socio") es un asistente de crecimiento personal con Laravel, Livewire, Alpine.js y MySQL. La interfaz web usa CSS propio basado en Material Design 3 y Bootstrap Icons (`bi-*`). El entorno local habitual es Windows con PowerShell y Laragon. Consulta `composer.json` y `package.json` cuando necesites versiones o scripts; son la fuente vigente.

## Contexto según la tarea

Lee únicamente los archivos necesarios para resolver la solicitud y sus dependencias relevantes. Estas convenciones de Laravel y UI corresponden a la aplicación web.

- **Funcionalidad web:** componentes en `app/Livewire`, vistas en `resources/views/livewire` y modelos en `app/Models`. Para navegación, consulta `routes/web.php` y `config/modules.php`: las vistas de módulo tienen rutas propias y la configuración declara los tabs y parámetros preservados.
- **Interfaz web:** consulta las secciones pertinentes de [docs/design-system.md](docs/design-system.md) y los componentes involucrados en `resources/views/components/ui`. Reutiliza `x-ui.*`, el shell del módulo y los tokens de `resources/css/m3`. Mantén un único layout responsive gobernado por CSS. El rail contextual depende de la configuración del módulo; no lo añadas por defecto.
- **Acciones de creación (regla transversal):** toda acción cuyo fin principal sea crear, registrar o agregar un elemento usa el FAB canónico (`x-ui.fab`, o `x-module-actions`, que lo renderiza) y abre `x-ui.form-dialog` sobre la pantalla actual, sin navegar. Varias opciones de creación se despliegan desde el mismo FAB (`'create' => true`). Aplica igual en producción y en mockups (macros `fab` y `dialog` de `life-shell.html.jinja`). Detalle en la sección «Acciones de creación» de [docs/design-system.md](docs/design-system.md).
- **Mockups:** trabaja con la configuración y fuentes de `docs/mockup-system`, empezando por `mockup-system.project.json` dentro de esa carpeta. Usa el compilador correspondiente para generar `dist`; no edites su salida a mano ni traslades automáticamente convenciones Blade a las plantillas del mockup.
- **Android:** consulta [Android/AGENTS.md](Android/AGENTS.md) únicamente para trabajo dentro de `Android/`.
- **OpenSpec:** consulta las skills y artefactos pertinentes cuando la solicitud corresponda a ese flujo. Su presencia no exige crear una propuesta para cualquier cambio.

Si una referencia contradice el código o la configuración vigente, contrasta solo el punto afectado; no conviertas la tarea en una migración general.

## Convenciones y autonomía

Preserva el aislamiento de datos por usuario en consultas y escrituras. `app/Models/Traits/BelongsToUser.php` aplica un scope cuando hay usuario autenticado; no asumas que cubre contextos sin autenticación. Sigue las convenciones de identificadores del modelo afectado: no todos usan UUID ni ese trait.

Una solicitud de implementación autoriza los cambios locales, reversibles y seguros necesarios, sus validaciones y las correcciones relacionadas. Resuelve decisiones rutinarias con el contexto disponible, sin pedir aprobación en cada paso. Conserva los cambios ajenos y limita el trabajo al alcance solicitado.

Consulta cuando falte una decisión que cambie materialmente el resultado, o antes de acciones destructivas o con efectos externos que no estén autorizadas. Distingue esos casos de un fallo local que puedes diagnosticar y corregir.

## Validación y cierre

Una implementación termina cuando la solución está completa y supera las validaciones razonables para el cambio:

- Comportamiento PHP: pruebas afectadas mediante `php artisan test --filter=...`.
- Assets web: `npm run build`; pruebas JavaScript pertinentes según `package.json`.
- UI web: `php artisan ui:conformance` y comprobaciones de interacción, accesibilidad, responsive o regresión visual que correspondan a la superficie modificada; consulta el contrato de UI aplicable.
- Documentación: referencias, coherencia y `git diff --check` sobre los archivos editados.

Corrige los fallos causados por tu cambio y vuelve a validar lo afectado sin detenerte tras el primer intento. No ejecutes todas las suites por defecto ni amplíes las pruebas después de pasar sin una razón concreta. Informa brevemente qué cambió, qué validaste y qué quedó bloqueado o pendiente; distingue fallos preexistentes de regresiones propias y no declares terminado lo que no pudiste comprobar.

<!-- team:start -->
## Cómo trabaja este repo (equipo de skills)
- Lineamientos: `docs/PRODUCT.md`, `docs/ARCHITECTURE.md`, `docs/UX-DESIGN.md`, `docs/UI-DESIGN.md`, `docs/UX-WRITING.md`, `docs/TESTING.md`, `docs/DOCUMENTATION.md`. Respétalos siempre.
- Hallazgos por rol: `docs/backlog/*.md`. Cambios a implementar: `docs/backlog/{to-do,in-progress,done,archived}/CHG-NNN-*/CHANGE.md`.
- Punto de entrada para el usuario: la skill `team` (Claude Code: `/team` · Codex: `$team`) elige el rol.
- Todo cambio de código pasa por un CHG aprobado: la skill `team` lo especifica y `developer` lo implementa (Claude Code: `/nombre` · Codex: `$nombre` · Gemini: pide usar la skill por su nombre).
- Estados y numeración SOLO con `node .claude/team.mjs` (`help` para ver todo). `loop` y `critique` son opcionales y solo los inicia el usuario. Modelos por perfil: `docs/TEAM_MODELS.md`.
- Regla para cualquier agente: no edites archivos fuera de `docs/` si no hay un CHG "En desarrollo" (`node .claude/team.mjs status`). En Claude Code lo impone un hook (`.claude/hooks/team-guard.mjs`); donde no hay hook (Cowork, Codex, Gemini) ejecuta `node .claude/team.mjs status` antes de editar fuera de `docs/` y, sin un CHG En desarrollo, no edites. Nunca la desactives sin permiso del usuario.
- Si el perfil dice `workspace: cowork` (`node .claude/team.mjs profile --json`), la persona no tiene terminal: nunca le pidas ejecutar comandos; hazlo tú o indícale el botón del panel (`team ui`).
<!-- team:end -->
