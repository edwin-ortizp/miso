# CHG-140 · Modales de Salud con el patrón visual de la app
**Estado:** Borrador
**Nivel:** M
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-17 · **Aprobado:** — · **Cerrado:** —
**Relacionado:** CHG-108, CHG-126, CHG-129, CHG-130, CHG-133, CHG-137, CHG-139

## 0. En palabras simples
- Qué vas a ver distinto: los formularios de Medicamentos, Ciclo, Sueño y Mediciones se ven como los de Hábitos o Tareas: ícono del módulo, panel de secciones, campos con aire entre ellos y botones fáciles de tocar. Los días de la semana y los síntomas pasan a ser botones redondeados que se marcan con un toque, no casillas pegadas.
- Cómo comprobarlo tú: en el teléfono abre «Registrar medicamento», elige días y verifica que cada día se toca sin errar; abre Ciclo y marca síntomas; revisa que lo importante (por ejemplo, ocultar al asistente) esté a la vista.

## 1. Resumen
La auditoría mostró que los cuatro modales usan `x-ui.form-dialog` solo como contenedor: sin `sections`, sin `icon`, campos apilados con 6 px de separación, `<details>` nativos que esconden errores y el interruptor de privacidad, y casillas crudas de unos 16 px para días y síntomas. Se llevan al patrón de `habit-dialog` y `health-editor` y se crea un selector de opciones tipo chip reutilizable (días de la semana, síntomas). No cambia la lógica de guardado ni las validaciones.

## 2. Producto (product-manager)
- Problema / usuario: formularios funcionales pero incómodos en pantallas táctiles y distintos al resto de la app; la consistencia reduce errores y esfuerzo.
- Incluye:
  - Medicamentos (medicamento, toma puntual), Ciclo, Sueño (registro y meta) y Mediciones.
  - Componente `x-ui.*` de selector de opciones (chips con `aria-pressed`, mínimo 44 px) usado para días y síntomas, publicado en `/ui-catalog`.
  - Interruptor de privacidad visible (sección, no escondido).
  - Botones «Tomado/Omitir/Deshacer» de Medicamentos y días del calendario de Ciclo a 44 px.
  - Horarios de Medicamentos: ver pregunta abierta.
- No incluye: cambios de reglas de negocio, validaciones, textos médicos o avisos fijos; el editor de eventos de salud (ya sigue el patrón, salvo la indentación de `health-editor.blade.php` L84-86); nuevas funciones (campo «evento de salud» en Medicamentos); funciones apagables (CHG-139).
- Criterios de aceptación (Dado/Cuando/Entonces):
  - Dado cualquiera de los cuatro modales, cuando se abre en 360, 390 y 1440 px, entonces muestra ícono, secciones (franja en móvil, panel en escritorio) y campos con separación uniforme.
  - Dado el selector de días, cuando se toca un día, entonces se marca o desmarca con un objetivo de al menos 44 px y se anuncia como botón con estado.
  - Dado un error de validación en un campo de una sección secundaria, cuando se intenta guardar, entonces la sección muestra el error en el menú y el foco va al campo.
  - Dado el modal de Ciclo, cuando se abre, entonces la opción de ocultar al asistente es visible sin desplegar nada.
  - Dado guardar o cancelar, cuando se usa cada modal, entonces se comporta igual que hoy (datos, validaciones, mensajes).

## 3. UX/UI
### 3.1 Experiencia (ux-designer)
- Flujo y cantidad de pasos: igual que hoy (abrir → completar → guardar). Lo esencial en la primera sección; lo opcional en la segunda, sin esconderlo.
- Qué contiene cada pantalla y qué es lo más importante:
  - Medicamentos: «Medicamento» (nombre, dosis y unidad en par, frecuencia, días, horarios) y «Detalles» (inicio, fin, notas, privacidad).
  - Ciclo: «Registro» (fecha, flujo, síntomas) y «Detalles» (notas, privacidad).
  - Sueño: «Sueño» (dormir, despertar, duración visible) y «Detalles» (calidad, notas, privacidad).
  - Mediciones: «Medición» (tipo, valor/par sistólica-diastólica, momento) y «Detalles» (pulso, examen relacionado, notas, privacidad).
  - Modales pequeños (omitir toma, meta de horas): sin secciones, con ícono y espaciado.
- Estados (vacío / carga / error / éxito / validaciones): errores en línea y marca de error por sección en el menú; sin cambios de mensajes de éxito.
- Información necesaria y momento de presentación: sin cambios de contenido; la privacidad pasa a verse desde el inicio donde ya es relevante.
- Accesibilidad de uso: `fieldset/legend` por grupo de chips, `aria-pressed`, objetivos de 44 px, foco visible, orden de tabulación por sección, no depender solo del color para el estado marcado.

```
┌ 💊 Registrar medicamento ───────────── ⤢ ✕ ┐
│ ▸ Medicamento │  Nombre  [______________]   │
│   Detalles    │  Dosis [50]   Unidad [mg ▾] │
│               │  Días                       │
│               │  (L) (M) (X) (J) (V) (S) (D)│
│               │  Horarios  [08:00] [＋ hora]│
├──────────────────────────────────────────────┤
│ Cancelar                    [Guardar]        │
└──────────────────────────────────────────────┘
 móvil: franja de secciones arriba, días en una o dos filas con ≥44 px
```

### 3.2 Visual (ui-designer)
- Distribución: `x-ui.form-dialog` con `icon`, `:sections` y `:submit` (etiqueta propia por acción); campos en `d-flex flex-column gap-3`, `md-field-pair` para dosis/unidad y dormir/despertar, `md-form-dialog__grid` donde haya varios campos.
- Componentes existentes a reutilizar: `resources/views/components/ui/form-dialog.blade.php`, `form-dialog-section.blade.php`, `x-ui.field`, `x-ui.select`, `x-ui.switch`, `x-ui.chip` y `.md-chip-filter`; referencias `livewire/habit/habit-dialog.blade.php` y `livewire/health/health-editor.blade.php`; peldaños 44 px de `.md-mood-intensity__step` (`resources/css/m3/modules/_mood.css`).
- Jerarquía, color, tipografía y espaciado (tokens): tokens M3 existentes, sin colores directos ni estilos en línea (`ui:conformance`); estado marcado con relleno y borde, más ícono de check.
- Responsive y contraste: 360/390/768/1440 px; chips con `flex-wrap`; contraste AA del estado marcado.

### 3.3 Escritura (ux-writer)
- Ubicación y estado: títulos, nombres de sección y botones de los cuatro modales.
- Textos finales (variables y variantes): secciones «Medicamento», «Registro», «Sueño», «Medición» y «Detalles». Botones: «Guardar medicamento» / «Actualizar», «Registrar toma», «Guardar registro», «Guardar medición», «Guardar meta». Se conservan etiquetas, ayudas y avisos médicos actuales.
- Reglas de docs/UX-WRITING.md aplicadas: verbos concretos, sin humor en acciones de salud, avisos fijos intactos.
- Criterios de aceptación de los textos: ningún texto médico o de privacidad cambia de significado.

## 4. Diseño técnico (software-architect)
- Archivos a crear / modificar:
  - Crear `resources/views/components/ui/choice-chips.blade.php` (nombre a confirmar), su entrada en `app/Support/Ui/ComponentCatalog.php` y `resources/views/ui/catalog/entries/`, y CSS `md-*` en `resources/css/m3/` (capa y contrato `CssLayerContractTest`).
  - Modificar las vistas: `livewire/health/medications.blade.php` (L169-225 y L54-59), `cycle.blade.php` (L78, L153-188), `sleep.blade.php` (L117-144), `health-measurements.blade.php` (L116-148).
  - Ajuste menor en `health-editor.blade.php` L84-86.
- Enfoque y patrones a seguir: `wire:model` sobre los mismos arreglos (`weekdays`, `symptoms`) para no tocar los componentes Livewire ni las acciones; secciones con `error` por sección como `health-editor`; cuidar `wire:key` fijo y `x-dirty-guard` por defecto.
- Riesgos y mitigación: colisión de blades con CHG-137 (guardián en las mismas pestañas) y CHG-139 → tocar solo los bloques de `x-ui.form-dialog`; CHG-108 aún en desarrollo → no cambiar el contrato `sections/state/sectionEvent/focusErrors`; los chips con `wire:model` y arreglos → probar con Livewire y teclado.
- Qué NO tocar: `app/Actions/*` (`SaveMedication`, `LogCycle`, `LogSleep`, `LogHealthMeasurement`), modelos, migraciones, textos médicos, el servidor MCP.

## 5. Pruebas (qa-tester)
| Caso | Tipo | Nivel | Test |
|---|---|---|---|
| Los cuatro modales siguen guardando y validando igual | Feature | 1 | `MedicationsModuleTest`, `CycleModuleTest`, `SleepModuleTest`, `HealthMeasurementsTest` |
| Componente de chips en el catálogo | Feature | 2 | `UiCatalogTest`, `UiPrimitivesTest` |
| Sin infracciones nuevas | Feature | 2 | `UiConformanceTest` |
| Capas CSS y contrato | Feature | 2 | `CssLayerContractTest` |
| Modales en 360/390/1440, objetivos ≥44 px, teclado, axe | Browser | 3 | nuevo `tests/Browser/health-dialogs.interaction.spec.js` |
- Comando para verificar: `php artisan test --filter=Health` y los de arriba, `php artisan ui:conformance`, `npm run build`, Playwright del spec nuevo, suite contra MySQL con `timeout`, `git diff --check`.
- Regresión a vigilar: `health-guardian.interaction.spec.js` (CHG-137), `ScreenArchetypeTest`, `DashboardQueryBudgetTest`, formularios con secciones de CHG-108.

## 6. Documentación (tech-writer)
- Docs a actualizar: `docs/design-system.md` (nuevo componente de chips y regla de selectores de opciones táctiles) y la entrada del catálogo `/ui-catalog`.

## 7. Tareas
- [ ] T1 · Selector de opciones tipo chip reutilizable (días, síntomas) — Resp: developer — Modelo: estandar — Archivos: componente, CSS, catálogo — Hecho cuando: ≥44 px, `aria-pressed`, publicado en `/ui-catalog` y `ui:conformance` limpio.
- [ ] T2 · Modal de Medicamentos con secciones, días en chips y botones de 44 px — Resp: developer — Modelo: estandar — Archivos: `medications.blade.php` — Hecho cuando: cumple el boceto y su test sigue verde.
- [ ] T3 · Modal de Ciclo con secciones, síntomas en chips y privacidad visible — Resp: developer — Modelo: estandar — Archivos: `cycle.blade.php` — Hecho cuando: días del calendario de 44 px y test verde.
- [ ] T4 · Modales de Sueño y Mediciones con secciones y pares de campos — Resp: developer — Modelo: estandar — Archivos: `sleep.blade.php`, `health-measurements.blade.php` — Hecho cuando: sin `<details>` nativos y validaciones visibles.
- [ ] T5 · Pruebas de navegador y documentación — Resp: developer — Modelo: estandar — Archivos: spec nuevo, `docs/design-system.md` — Hecho cuando: pasan la matriz de tamaños, teclado y axe.

## 8. Bitácora de implementación (developer)

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-17 — Creado en to-do/ (Borrador, nivel M) a partir de la auditoría de los modales de Salud.
- Abierta: los horarios de Medicamentos son hoy texto libre «HH:MM, separados por coma» (CHG-133 bocetó «[08:00] ＋hora»). ¿Los cambiamos a horas individuales con «＋ hora» en este CHG? Recomendado: sí, es la parte más propensa a errores en el móvil.
- Abierta: ¿se incluye la franja «Sueño: calidad» como peldaños 1-5 en lugar de un selector? Recomendado: sí.
