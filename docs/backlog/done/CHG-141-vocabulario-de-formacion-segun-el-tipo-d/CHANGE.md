# CHG-141 · Vocabulario de Formación según el tipo de estudio
**Estado:** Cerrado
**Nivel:** M
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-17 · **Aprobado:** 2026-11-17 · **Cerrado:** 2026-11-17
**Relacionado:** CHG-138, CHG-096
**Depende de:** CHG-138 (En verificación: cerrarlo antes de implementar; ambos tocan `page.blade.php`, `editor.blade.php`, `LearningPage.php`)

## 0. En palabras simples
- Qué vas a ver distinto: en Formación, los nombres cambian según lo que estudias. En un pregrado verás «semestres» con «materias»; en un curso, «módulos» y «lecciones», sin semestres. Los textos de botones, títulos, avisos y listas vacías usan esas palabras (en Colombia, sin traducciones raras).
- Cómo comprobarlo tú: crea un pregrado y agrega un semestre y una materia; luego crea un curso y verifica que ofrece módulos y no semestres. Entra a los ajustes de Formación y cambia una palabra (por ejemplo «materia» por «curso») para ver que se actualiza todo.

## 1. Resumen
Hoy los tipos de formación son: carrera universitaria, especialización, maestría, doctorado, curso y ruta personal. La jerarquía ya cambia por tipo (`LearningRecords::children()`), pero los rótulos son fijos («Periodo», «Materia», «Módulo»…) y viven en las constantes `LearningUnit::KINDS` y `LearningFormation::TYPES`. Se introduce un **vocabulario por tipo de formación**: defaults en config con jerga colombiana y la posibilidad de que cada persona renombre los términos. No cambia el esquema ni las reglas de jerarquía: solo las palabras (singular, plural, género).

## 2. Producto (product-manager)
- Problema / usuario: la app es para un grupo de amigos en Colombia; «periodo» y «unidad» suenan genéricos y un curso no tiene semestres. Hablar como estudian refuerza que la app es suya.
- Incluye:
  - Vocabulario por tipo con claves estables de unidad (`period`, `subject`, `module`, `topic`, `stage`, `course`) y por tipo de formación: singular, plural, artículo (género) y etiqueta del propio tipo.
  - Defaults propuestos: pregrado → «semestre» → «materia» → «tema»; especialización → «semestre» → «materia»; maestría → «semestre» → «materia»; doctorado → «semestre» → «materia»; curso → «módulo» → «tema»; ruta personal → «etapa» → «curso» → «tema».
  - Aplicarlo a todos los textos de Formación: botones, títulos de diálogos, migas, avisos de validación, estados vacíos, chips, acceso directo y encabezados de tabla.
  - Ajustes de Formación para renombrar cada término (override por persona).
  - Concordancia de género y número (un/una, el/la, plural).
- No incluye: cambiar la jerarquía ni los tipos; esquema de BD; nuevos tipos; Mi día, notificaciones, MCP o Android (no mencionan Formación); i18n general del repo.
- Criterios de aceptación (Dado/Cuando/Entonces):
  - Dado un pregrado, cuando agrego contenido, entonces los botones dicen «Agregar semestre» y «Agregar materia», y el vacío dice «Aún no tienes semestres».
  - Dado un curso, cuando abro sus opciones, entonces no se ofrece «semestre» y se habla de módulos.
  - Dado un renombrado propio («materia» → «curso»), cuando navego, entonces todos los textos de esa formación lo usan con el género correcto.
  - Dado un usuario que no ha personalizado nada, cuando entra, entonces ve los defaults colombianos.
  - Dado otro usuario, cuando entra, entonces no ve mis renombrados.
  - Dado el contenido existente, cuando se despliega, entonces no se pierden ni se mueven datos.

## 3. UX/UI
### 3.1 Experiencia (ux-designer)
- Plantilla: pestaña «Ajustes» del módulo (arquetipo `settings`, patrón de `plans.settings`); no existe `## Anatomía` para ajustes.
- Flujo y cantidad de pasos: sin pasos nuevos en el uso diario. Personalizar: Formación → Ajustes → «Vocabulario» → editar palabras → guardar (3-4 pasos).
- Qué contiene cada pantalla y qué es lo más importante: una tabla de términos agrupada por tipo de formación (singular, plural), con vista previa de cómo se leerá («Agregar materia»).
- Estados (vacío / carga / error / éxito / validaciones): valor vacío → vuelve al default; texto demasiado largo (máx. 30) o repetido dentro del mismo tipo → error en línea; guardado → «Vocabulario guardado.»; botón «Restaurar valores».
- Información necesaria y momento de presentación: el vocabulario no se pregunta al crear; se ve al usar y se ajusta en Ajustes.
- Accesibilidad de uso: campos con etiqueta, errores asociados, foco al primer error.

```
Formación › Ajustes › Vocabulario
┌ Pregrado ────────────────────────────────┐
│ Periodo     Singular [semestre ]  Plural [semestres]│
│ Materia     Singular [materia  ]  Plural [materias ]│
│ Tema        Singular [tema     ]  Plural [temas    ]│
│ Vista previa:  Agregar semestre · Agregar materia   │
│                                  [Restaurar] [Guardar]│
└──────────────────────────────────────────┘
```

### 3.2 Visual (ui-designer)
- Distribución: pestaña «Ajustes» del módulo (arquetipo `settings`, patrón de `plans.settings`), una tarjeta por tipo de formación en una columna (móvil) o dos (escritorio).
- Componentes existentes a reutilizar: `x-ui.field`, `md-field-pair`, shell de módulo con `tabs` (`config/modules.php`), `GameSettings` como patrón de guardado.
- Jerarquía, color, tipografía y espaciado (tokens): tokens M3 existentes, sin estilos en línea.
- Responsive y contraste: 360/390/768/1440 px; objetivos de 44 px.

### 3.3 Escritura (ux-writer)
- Ubicación y estado: Formación completa (page, editor, avisos) y Ajustes → Vocabulario.
- Textos finales (variables y variantes): plantillas con `{artículo} {término}`: «Agregar {un/una} {término}», «Editar {término}», «Aún no tienes {plural}», «Solo {plural con artículo} admiten créditos»; Ajustes: «Vocabulario», «Cómo llamas a cada parte de tu formación», «Vocabulario guardado.», «Restaurar valores».
- Reglas de docs/UX-WRITING.md aplicadas: l.9 pide español neutro; se registra **una excepción documentada** para jerga colombiana en Formación («semestre», «materia»). Se mantiene la regla de nombres estables por tipo (l.43, l.65): el vocabulario es estable por formación, no aleatorio.
- Criterios de aceptación de los textos: ningún texto de Formación conserva «periodo»/«materia» fijo; todos los avisos concuerdan en género y número.

## 4. Diseño técnico (software-architect)
- Archivos a crear / modificar:
  - Crear `config/learning.php` (vocabulario por defecto por tipo) y `app/Services/Learning/LearningVocabulary.php` (resuelve término: defaults + override de `ModuleSetting` con `module='learning'`, memo por petición, patrón de `GameSettings`).
  - Modificar `app/Models/LearningUnit.php` y `LearningFormation.php` (las constantes quedan como claves y fallback), `app/Services/Learning/LearningRecords.php` (mensajes l.58-138, `shortcuts()` l.195-196), `app/Livewire/Learning/LearningPage.php`, `resources/views/livewire/learning/page.blade.php`, `editor.blade.php`, `config/modules.php` (pestaña Ajustes y ruta `learning.settings` en `routes/web.php`), nuevo componente Livewire de ajustes.
  - Actualizar tests acoplados a textos: `LearningModuleTest` (l.32-82), `tests/Browser/learning.interaction.spec.js` (l.68, 81, 108, 110).
- Enfoque y patrones a seguir: claves internas estables (`period`, `subject`…), solo cambia la presentación; override JSON por persona sin migración; las validaciones mantienen sus claves y solo cambian los textos.
- Riesgos y mitigación: cadenas con género y número → plantillas con artículo en el vocabulario; colisión con CHG-138 en los mismos blades → implementar después de cerrarlo; romper tests de texto → actualizarlos en la misma tarea.
- Qué NO tocar: migraciones y tablas, jerarquía (`children()`), `learning_shortcuts`, Mi día, MCP.

## 5. Pruebas (qa-tester)
| Caso | Tipo | Nivel | Test |
|---|---|---|---|
| Resolución de términos por tipo y fallback a default | Unit/Feature | 1 | nuevo `LearningVocabularyTest` |
| Override propio y aislamiento entre usuarios | Feature | 1 | `LearningVocabularyTest` |
| Validación de ajustes (vacío, largo, repetido) | Feature | 1 | `LearningVocabularyTest` |
| Textos de pregrado y curso en la página | Feature | 2 | `LearningModuleTest` |
| Concordancia de género y número en avisos | Feature | 2 | `LearningVocabularyTest` |
| Ajustes de vocabulario en 360/390/1440 | Browser | 3 | `learning.interaction.spec.js` |
- Comando para verificar: `php artisan test --filter=Learning`, `php artisan ui:conformance`, `npm run build`, Playwright de Formación, suite contra MySQL con `timeout`, `git diff --check`.
- Regresión a vigilar: `LearningDomainTest`, `CategoryHierarchyTest`, `PersonalExperienceTest`, `AppShellTest`.

## 6. Documentación (tech-writer)
- Docs a actualizar: `docs/learning.md` (vocabulario por tipo), `docs/UX-WRITING.md` (excepción de jerga colombiana en Formación y entradas en la tabla de vocabulario, l.51-63 y «Decisiones y lecciones»).

## 7. Tareas
- [x] T1 · Vocabulario por tipo con defaults colombianos y override por persona — Resp: developer — Modelo: estandar — Archivos: `config/learning.php`, `LearningVocabulary.php` — Hecho cuando: resuelve singular, plural y género con fallback y aislamiento por usuario.
- [x] T2 · Aplicar el vocabulario a todos los textos de Formación — Resp: developer — Modelo: estandar — Archivos: `LearningRecords.php`, `LearningPage.php`, `page.blade.php`, `editor.blade.php`, modelos — Hecho cuando: no queda rótulo fijo de unidad y los avisos concuerdan.
- [x] T3 · Pestaña «Ajustes» con edición del vocabulario — Resp: developer — Modelo: estandar — Archivos: `config/modules.php`, `routes/web.php`, componente de ajustes — Hecho cuando: se cumple el boceto, con vista previa y restaurar.
- [x] T4 · Pruebas y documentación — Resp: developer — Modelo: estandar — Archivos: tests, `docs/learning.md`, `docs/UX-WRITING.md` — Hecho cuando: la verificación de la sección 5 pasa.

## 8. Bitácora de implementación (developer)
- 2026-11-17 — Implementado sin migración: `config/learning.php` (tipos y términos por familia), `LearningVocabulary` (término, etiqueta, artículo indefinido, demostrativo, plural con artículo, lista «a, b y c», guardado validado de los que difieren del valor por defecto, restaurar; registrado `scoped`), `LearningSettings` (ruta `learning.settings`, pestaña «Ajustes» y pestaña «Formaciones» en `config/modules.php`), y vocabulario aplicado en `page.blade.php` (acciones, chips, tabla, filtros, estados vacíos), `editor.blade.php` (títulos y texto de ayuda), `LearningRecords` (error de créditos y accesos directos). «Carrera universitaria» pasó a «Pregrado» (modelo y config). Los términos propios infieren el género por la terminación (`guessGender`).
- Decisiones: la personalización es por tipo de formación. Las claves internas no cambian; los mensajes generales de módulo («Formación guardada.», «Elemento eliminado.») se conservan. Documentado en `docs/learning.md` y la excepción de jerga en `docs/UX-WRITING.md`.
- Verificación local (PHP 8.4 en chroot): `LearningVocabularyTest` (7 pruebas), `LearningModuleTest`, `LearningDomainTest`, `PersonalExperienceTest`, Ui y shell con los mismos fallos preexistentes de la rama base; el spec Playwright `learning.interaction.spec.js` pasa a 375/768/1440 y en la cuenta vacía (se actualizó «Editar pregrado»). Captura de Ajustes a 390 px revisada. `npm run build` correcto.
- Nota: CHG-138 sigue En verificación; se implementó sobre sus archivos sin conflictos.

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-17 — Creado en to-do/ (Borrador, nivel M) a partir de la exploración de Formación.
- 2026-11-17 — Decisión del usuario: en especialización, maestría y doctorado las unidades bajo el semestre se llaman «materia». El doctorado también usa semestres. El tipo «Carrera universitaria» pasa a llamarse «Pregrado».
- 2026-11-17 — Decisión delegada por el usuario («identifica tú la mejor manera»): el vocabulario se personaliza por tipo de formación.
- 2026-11-17 — Aprobado por el usuario (Compuerta 2)
- 2026-11-17 — Movido a in-progress/ (En desarrollo)
- 2026-11-17 — Implementación terminada → En verificación
- 2026-11-17 — Cerrado por el usuario (Compuerta 3) → done/
- LECCIÓN: ninguna
