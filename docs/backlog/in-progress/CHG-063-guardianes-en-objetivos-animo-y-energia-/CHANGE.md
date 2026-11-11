# CHG-063 · Guardianes en Objetivos, Ánimo y energía, Hábitos a evitar y Diario
**Estado:** En verificación
**Nivel:** M
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-10 · **Aprobado:** 2026-11-10 · **Cerrado:** —
**Relacionado:** CHG-057, CHG-058 (consejos del guardián), CHG-062 (ficha en modal)

## 0. En palabras simples
- Qué vas a ver distinto: Objetivos, Ánimo y energía, Hábitos a evitar y Diario estrenan un panel derecho con su guardián y consejos sacados de tus datos. En el Diario puede recordarte una frase corta de una entrada anterior; en Hábitos a evitar celebra tus días limpios y, si hubo una recaída, te acompaña sin juzgar.
- Cómo comprobarlo tú: abre cada módulo en escritorio → panel derecho con el guardián del tipo afín; toca el botón de recargar → otro consejo. En el Diario, con entradas de hace un mes, puede aparecer "Hace un mes escribiste: …".

## 1. Resumen
Cuatro proveedores nuevos para `GuardianAdvisor` y un panel lateral nuevo (solo con el guardián) en la página principal de cada módulo, activado en `config/modules.php`.

## 2. Producto (product-manager)
- Problema / usuario: Alex quiere guardianes en todos sus módulos diarios.
- Incluye: rail con guardián (y franja arriba en < 1200 px) en `/goals`, `/mood`, `/negative-habits`, `/journal`; afinidades; consejos por módulo; Diario puede citar fragmentos; Hábitos a evitar puede mencionar recaídas.
- No incluye: otras vistas de esos módulos (resúmenes, calendario de vida, ajustes); otros widgets en el panel nuevo.
- Criterios de aceptación (Dado/Cuando/Entonces):
  1. Cada módulo muestra al ejemplar de mayor nivel de sus tipos (Objetivos dragón/volador; Ánimo hada/normal; Diario psíquico/fantasma; Hábitos a evitar siniestro/lucha); sin afinidad, el de mayor nivel.
  2. Objetivos: meta activa con fecha límite en ≤ 14 días (días que faltan y avance); meta activa sin registros hace ≥ 7 días; hito registrado en los últimos 7 días (celebra).
  3. Ánimo y energía: si hoy no hay registro de ánimo, lo sugiere; tendencia de ánimo de 7 días vs los 7 previos (sube/baja, con tono cálido); franja del día con mejor energía en 14 días.
  4. Hábitos a evitar: días desde el último registro de cada hábito (el mayor, si ≥ 2); mejor racha histórica si la actual está cerca (≥ 80 %); si hubo un registro en las últimas 48 h, mensaje de apoyo que lo nombra sin culpa.
  5. Diario: días desde la última entrada si ≥ 2; racha de días escribiendo (≥ 3); "Hace un mes escribiste: “…”" con un fragmento de ≤ 80 caracteres de una entrada de hace 28-35 días (o de hace un año), cortado por palabra y escapado.
  6. El panel nuevo aparece solo en la página principal de cada módulo y solo contiene al guardián; sin guardián (juego apagado o sin ejemplares) el módulo se ve como hoy (sin panel).
  7. Botón de otro consejo, refresco cada 5 min y ficha en modal funcionan igual que en los demás módulos.

## 3. UX/UI
### 3.1 Experiencia (ux-designer)
- Plantilla: módulo con panel contextual (rail), como Hidratación.
- Flujo y cantidad de pasos: 0 pasos para ver el consejo; 1 clic para otro o para la ficha.
- Qué contiene cada pantalla y qué es lo más importante: el contenido del módulo sigue siendo lo principal; el panel solo tiene la tarjeta "Tu guardián".
- Estados (vacío / carga / error / éxito / validaciones): con guardián; sin guardián → sin panel (layout actual).
- Información necesaria y momento de presentación: ninguna adicional.
- Accesibilidad de uso (teclado, foco, errores): igual que CHG-057/062.

```
┌─ Diario ─────────────────────────────┬─ ✨ Tu guardián ──────────┐
│ (entradas, igual que hoy)            │░ (◕‿◕) Gastly · Nv 5    ░│
│                                      │░ "Hace un mes escribiste:░│
│                                      │░  'hoy por fin terminé…'"░│
│                                      │░                   [↻]  ░│
│                                      └──────────────────────────┘
```

### 3.2 Visual (ui-designer)
- Distribución: `x-context-widget title="Tu guardián" icon="bi-stars"` con `GuardianCard variant="rail"` dentro; `variant="top"` arriba del contenido en < 1200 px.
- Componentes existentes a reutilizar (ruta): `x-context-widget`, `livewire:game.guardian-card`, `.game-guardian*`.
- Jerarquía, color, tipografía y espaciado (tokens): los de CHG-055/057; cita del diario en cursiva dentro del globo.
- Responsive y contraste: igual que los demás módulos.

### 3.3 Escritura (ux-writer)
- Ubicación y estado: globo del guardián.
- Textos finales (config `game.guardian_advice`):
  - goals.due: "A {goal} le quedan {days} días y vas en {percent} %."
  - goals.idle: "{goal} lleva {days} días sin avances; ¿un paso pequeño hoy?"
  - goals.milestone: "¡Hito en {goal}! Eso merece celebrarse."
  - mood.missing: "Aún no anotas cómo te sientes hoy; ¿cómo vas?"
  - mood.up: "Tu ánimo de esta semana va mejor que la anterior."
  - mood.down: "Esta semana ha pesado un poco más; aquí estoy contigo."
  - mood.energy: "Tu energía suele estar más alta en la {slot}."
  - negative.clean: "{days} días sin {habit}. Cada día cuenta."
  - negative.record: "Estás a {left} días de tu mejor racha sin {habit}."
  - negative.support: "Lo de {habit} no borra tu camino; hoy empiezas de nuevo."
  - journal.gap: "Llevas {days} días sin escribir; ¿qué te ronda hoy?"
  - journal.streak: "{days} días seguidos escribiendo. Qué buena constancia."
  - journal.memory: "{when} escribiste: “{quote}”"
- Reglas de docs/UX-WRITING.md aplicadas: tuteo, cálido, sin culpa; ≤ 90 caracteres (la cita se recorta a 80); sin palabras de `MissionProposalValidator::penaltyWord`.
- Criterios de aceptación de los textos: test de plantillas existente ampliado a los 4 módulos.

## 4. Diseño técnico (software-architect)
- Archivos a crear / modificar:
  - `app/Services/Game/Guardian/GoalAdvice.php` (`Goal` status/due_date/numeric_goal, `GoalEntry` date/is_milestone), `MoodAdvice.php` (`MoodEntry` date/value, `EnergyEntry` date/level/time), `NegativeHabitAdvice.php` (`NegativeHabitDefinition`, `NegativeHabitLog` timestamp), `JournalAdvice.php` (`JournalEntry` date/text). ≤ 3 consultas cada uno.
  - `app/Services/Game/Guardian/GuardianAdvisor.php`: registrar `goals`, `mood`, `negative-habits`, `journal`.
  - `config/game.php`: afinidades y textos.
  - `config/modules.php`: `rail => true` y `rail_routes` = ruta principal en goals, mood, journal, negative-habits.
  - Vistas: `goal/goal-index`, `mood/mood-tracker`, `journal/journal-entries`, `negative-habit/negative-habit-weekly` → `<x-slot:rail>` con el widget y `variant="top"`; el rail se renderiza solo si `GuardianCard::visibleFor()` (si no, sin slot → layout actual).
- Enfoque y patrones a seguir: contrato `candidates()` + trait `Advice`; la cita se pasa como variable escapada (nunca HTML del usuario) y recortada con `Str::limit` por palabra.
- Riesgos y mitigación: contenido personal del diario → se muestra solo al propio usuario dentro de la app, no sale a servicios externos; recaídas → solo plantillas de apoyo validadas; rail vacío → condicionado a `visibleFor`.
- Qué NO tocar: lógica de metas, ánimo, diario y hábitos a evitar.

## 5. Pruebas (qa-tester)
| Caso | Tipo | Nivel | Test |
|---|---|---|---|
| Reglas de Objetivos, Ánimo, Hábitos a evitar y Diario (criterios 2-5) | Feature | Alto | tests/Feature/Game/GuardianAdviceTest.php |
| Cita del diario recortada y escapada | Feature | Alto | GuardianAdviceTest |
| Afinidades y montaje del card en las 4 páginas; sin guardián no hay panel (criterios 1, 6) | Feature | Alto | tests/Feature/Game/ModuleGuardianTest.php |
| Plantillas ≤ 90 y sin palabras prohibidas | Feature | Bajo | GuardianAdviceTest |
- Comando para verificar: `php artisan test tests/Feature/Game/GuardianAdviceTest.php tests/Feature/Game/ModuleGuardianTest.php`
- Regresión a vigilar: tests de Goals, Mood, Journal, NegativeHabit y ModuleExperience/ProtectedScreenContract.

## 6. Documentación (tech-writer)
- Docs a actualizar: `docs/game.md` → "Consejos del guardián": 4 módulos nuevos, afinidades y uso de contenido del diario.

## 7. Tareas
- [x] T1 · Guardián con consejos en Objetivos y Ánimo y energía, con su panel derecho — Resp: developer — Archivos: app/Services/Game/Guardian/GoalAdvice.php, app/Services/Game/Guardian/MoodAdvice.php, app/Services/Game/Guardian/GuardianAdvisor.php, config/game.php, config/modules.php, resources/views/livewire/goal/goal-index.blade.php, resources/views/livewire/mood/mood-tracker.blade.php, tests/Feature/Game/GuardianAdviceTest.php, tests/Feature/Game/ModuleGuardianTest.php — Hecho cuando: criterios 1-3, 6 y 7 pasan en esos módulos
- [x] T2 · Guardián con consejos en Hábitos a evitar y Diario (citas y recaídas con tono de apoyo) — Resp: developer — Archivos: app/Services/Game/Guardian/NegativeHabitAdvice.php, app/Services/Game/Guardian/JournalAdvice.php, app/Services/Game/Guardian/GuardianAdvisor.php, config/game.php, config/modules.php, resources/views/livewire/negative-habit/negative-habit-weekly.blade.php, resources/views/livewire/journal/journal-entries.blade.php, tests/Feature/Game/GuardianAdviceTest.php, tests/Feature/Game/ModuleGuardianTest.php — Hecho cuando: criterios 4-7 pasan y el build compila
- [ ] T3 · Verificar criterios de aceptación — Resp: qa-tester — Hecho cuando: los 7 criterios pasan
- [x] T4 · Actualizar documentación — Resp: tech-writer — Archivos: docs/game.md — Hecho cuando: docs al día

## 8. Bitácora de implementación (developer)
- 2026-11-10 — Inicio (start --force: había otros CHG En desarrollo).
- T1 — `GoalAdvice` (2 consultas; usa `GoalProgress::calculate`; si la meta no es numérica usa `goals.due_plain`) y `MoodAdvice` (2 consultas). Rail en `/goals` y `/mood` (`rail_routes`), condicionado a `GuardianCard::visibleFor()`, con `x-context-widget` "Tu guardián"; `variant="top"` arriba.
- T2 — `NegativeHabitAdvice` (2 consultas; timestamps Unix) y `JournalAdvice` (3 consultas; cita con `strip_tags` + `Str::limit` por palabra, escapada por `Advice`). Rail en `/negative-habits` y `/journal`.
- Extra necesario: `ModuleGuardian::line` usa `game.guardian_default_line` ("Aquí estoy contigo.") para módulos sin frases propias, y `GuardianCard::progress()` ya no cae en los datos de Tareas para módulos ajenos (antes un módulo nuevo sin consejos habría dicho "Día libre…"). Textos nuevos `goals.due_plain` y `guardian_default_line` (no estaban en §3.3).
- T4 — docs/game.md actualizado.
- Tests: GuardianAdviceTest 18 + ModuleGuardianTest 14 → 32/32. Game + Goal/Mood/Journal/Negative + Ui/ModuleExperience: 426/436; las 10 fallas existen igual sin este cambio (NewSources, ModuleExperience ×2, ProtectedScreenContract, CssLayerContract ×2, UiCatalog, UiConformance ×2, GoalDetail). Build OK.

## 9. Preguntas abiertas, decisiones e historial
- LECCIÓN: ninguna
- 2026-11-10 — Decisiones (Alex): crear panel derecho en los módulos que no lo tienen; afinidades temáticas propuestas; el Diario puede citar fragmentos; Hábitos a evitar puede mencionar recaídas con tono cálido y sin juzgar.
- 2026-11-10 — Creado en to-do/ (Borrador, nivel M)
- 2026-11-10 — Aprobado por el usuario (Compuerta 2)
- 2026-11-10 — Movido a in-progress/ (En desarrollo)
- 2026-11-10 — Implementación terminada → En verificación
