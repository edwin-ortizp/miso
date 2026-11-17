# CHG-142 · Horario para planificar y cálculo de huecos libres
**Estado:** Aprobado
**Nivel:** M
**Modelo sugerido:** razonamiento
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-17 · **Aprobado:** 2026-11-17 · **Cerrado:** —
**Relacionado:** CHG-133, CHG-135, CHG-139, CHG-143, CHG-144, CHG-145
**Dividido en:** CHG-142 (base, este), CHG-143 (ventana «Buscar un hueco»), CHG-144 (horario fijo), CHG-145 (MCP). Orden: 142 → 144 → 143 → 145.

## 0. En palabras simples
- Qué vas a ver distinto: en Tareas → Ajustes aparece **«Horario para planificar»**, donde defines en qué días y horas quieres que se te propongan tareas. Por defecto: lunes a viernes de 8:00 a 18:00 y sábados de 9:00 a 12:00. Detrás queda el cálculo que sabe qué horas tienes libres; la ventana, el horario fijo y el MCP lo usan.
- Cómo comprobarlo tú: cambia el sábado a 10:00–13:00, guarda, recarga y verifica que se conserva; apaga el miércoles y verifica que queda como «Sin planificar».

## 1. Resumen
No existe nada de disponibilidad, horario hábil ni zona horaria por persona. Esta base crea el ajuste por persona (días y horas, margen entre bloques) y el servicio `TaskSlotFinder`, que calcula huecos libres y cruces a partir de lo que ocupa la agenda con hora: tareas, comidas y tomas de medicamentos. Es la pieza común de la ventana (CHG-143), el horario fijo (CHG-144) y el MCP (CHG-145).

## 2. Producto (product-manager)
- Problema / usuario: cada persona tiene su propia jornada; sin ella no se pueden sugerir horarios útiles.
- Incluye:
  - Ajuste «Horario para planificar» por persona y por día de la semana (hora de inicio y fin, o día sin planificar) y margen entre bloques (por defecto 10 min). Defaults: lunes a viernes 8:00–18:00, sábado 9:00–12:00, domingo sin planificar.
  - Servicio `TaskSlotFinder`: huecos libres para una duración y rango, con preferencia de momento del día, y cruces (tareas con hora que se solapan) con una marca `movable` preparada para el CHG-144.
  - Ocupación: tareas con hora (`*_is_date=false`), comidas (`MealSchedule::events()`), tomas de medicamentos (`MedicationSchedule`). Citas de salud y planes sin hora no bloquean.
  - Zona horaria de la app (`config('app.timezone')`, America/Bogota).
- No incluye: ventana y accesos (CHG-143); horario fijo (CHG-144); MCP (CHG-145); calendarios externos; zona horaria por persona.
- Criterios de aceptación (Dado/Cuando/Entonces):
  - Dado un usuario sin ajustes, cuando se calcula, entonces usa L-V 8:00–18:00 y sábado 9:00–12:00.
  - Dado un horario propio, cuando se guarda, entonces se conserva y no afecta a otro usuario.
  - Dado un rango, cuando se piden huecos de N minutos, entonces solo salen huecos dentro de la jornada, sin cruzar lo ocupado, con margen y sin pasado.
  - Dado un día sin planificar, cuando se calcula, entonces no devuelve huecos para ese día.
  - Dadas dos tareas con hora solapadas, cuando se piden cruces, entonces se devuelven agrupadas.
  - Dada una hora de fin anterior o igual a la de inicio, cuando se guarda, entonces se rechaza con mensaje claro.

## 3. UX/UI
### 3.1 Experiencia (ux-designer)
- Plantilla: pestaña de ajustes del módulo Tareas (arquetipo `settings`); no existe `## Anatomía` para ajustes.
- Flujo y cantidad de pasos: Tareas → Ajustes → «Horario para planificar» → ajustar días → Guardar. 3-4 pasos.
- Qué contiene cada pantalla y qué es lo más importante: siete filas (lunes a domingo) con interruptor «Planificar este día» y horas de inicio y fin; margen entre bloques; «Restaurar valores». Lo central: ver de un vistazo cuándo estás disponible.
- Estados (vacío / carga / error / éxito / validaciones): fin ≤ inicio → error en línea; guardado → «Horario guardado.»; todos los días apagados → aviso «No se propondrán horarios mientras todos los días estén sin planificar».
- Información necesaria y momento de presentación: nota de que no incluye citas de salud ni planes sin hora.
- Accesibilidad de uso: campos con etiqueta por día, errores asociados, foco al primer error.

```
Tareas › Ajustes › Horario para planificar
 Lun  [⬤] [08:00] a [18:00]
 Mar  [⬤] [08:00] a [18:00]
 ...
 Sáb  [⬤] [09:00] a [12:00]
 Dom  [ ] Sin planificar
 Margen entre bloques [10 min ▾]
              [Restaurar valores] [Guardar]
```

### 3.2 Visual (ui-designer)
- Distribución: tarjeta única con siete filas (arquetipo `settings`, como `plans.settings`); en móvil, horas apiladas bajo el día.
- Componentes existentes a reutilizar: `x-ui.switch`, `x-ui.field` (`type=time`), `md-field-pair`, `resources/views/livewire/meal/meal-schedules.blade.php`.
- Jerarquía, color, tipografía y espaciado (tokens): tokens M3 existentes; sin estilos en línea.
- Responsive y contraste: 360/390/1440; objetivos de 44 px.

### 3.3 Escritura (ux-writer)
- Ubicación y estado: Tareas → Ajustes.
- Textos finales (variables y variantes): «Horario para planificar» · «Cuándo quieres que te proponga horarios para tus tareas.» · «Planificar este día» · «Sin planificar» · «Margen entre bloques» · «Horario guardado.» · «La hora de fin debe ser posterior a la de inicio.» · «Restaurar valores».
- Reglas de docs/UX-WRITING.md aplicadas: tuteo, verbo concreto, errores sin culpa.
- Criterios de aceptación de los textos: queda claro que es una preferencia, no un bloqueo de la agenda.

## 4. Diseño técnico (software-architect)
- Archivos a crear / modificar: crear `app/Services/Task/TaskSlotFinder.php` y `app/Services/Task/PlanningHours.php` (defaults en config + override en `ModuleSetting('tasks')`, memo por petición, patrón de `GameSettings`/`MealSchedule`); modificar `app/Livewire/Task/TaskSettings.php` (sección nueva en `SECTIONS`), vista de ajustes, ruta `tasks.settings.planning` en `routes/web.php` y `config/modules.php`.
- Enfoque y patrones a seguir: servicio puro y testeable, sin Livewire; una consulta por fuente de ocupación para todo el rango (CHG-135); fechas en `config('app.timezone')`; validación `date_format:H:i` como `MealSettings`.
- Riesgos y mitigación: ocupación incompleta → nota de qué se considera; rendimiento → rango máximo de 14 días.
- Qué NO tocar: esquema, recurrencia, CalDAV, Mi día.

## 5. Pruebas (qa-tester)
| Caso | Tipo | Nivel | Test |
|---|---|---|---|
| Huecos respetan jornada, margen y pasado | Unit/Feature | 1 | `TaskSlotFinderTest` |
| Ocupación de tareas, comidas y medicamentos | Feature | 1 | `TaskSlotFinderTest` |
| Cruces agrupados | Feature | 1 | `TaskSlotFinderTest` |
| Sin huecos devuelve vacío | Feature | 1 | `TaskSlotFinderTest` |
| Defaults, override y aislamiento entre usuarios | Feature | 1 | `PlanningHoursTest` |
| Validación (fin > inicio, formato) | Feature | 2 | `PlanningHoursTest` |
| Presupuesto de consultas | Feature | 2 | `BackendQueryBudgetTest` |
| Pantalla de ajustes en 360/390/1440 | Browser | 3 | spec de ajustes de Tareas |
- Comando para verificar: `php artisan test --filter=Task`, `php artisan ui:conformance`, `npm run build`, suite contra MySQL con `timeout`, `git diff --check`.
- Regresión a vigilar: `MealScheduleTest`, `TaskViewsTest`, `ScreenArchetypeTest`.

## 6. Documentación (tech-writer)
- Docs a actualizar: documentación de Tareas (horario para planificar y qué cuenta como ocupado).

## 7. Tareas
- [ ] T1 · Horario para planificar con valores por defecto y ajuste por persona — Resp: developer — Modelo: estandar — Archivos: `PlanningHours.php`, config — Hecho cuando: defaults, override por usuario y validación pasan.
- [ ] T2 · Pantalla «Horario para planificar» en Tareas → Ajustes — Resp: developer — Modelo: estandar — Archivos: `TaskSettings.php`, vista, ruta, `config/modules.php` — Hecho cuando: se cumple el boceto y los textos.
- [ ] T3 · Servicio de huecos libres y cruces — Resp: developer — Modelo: razonamiento — Archivos: `TaskSlotFinder.php` — Hecho cuando: pasan jornada, ocupación, margen, vacío y cruces, con presupuesto de consultas.
- [ ] T4 · Pruebas y documentación — Resp: developer — Modelo: estandar — Archivos: tests, docs — Hecho cuando: pasa la sección 5.

## 8. Bitácora de implementación (developer)

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-17 — Creado en to-do/ (Borrador) a partir de la exploración de agenda y menú de tareas.
- 2026-11-17 — Decisión del usuario: jornada por defecto lunes a viernes 8:00–18:00 y sábados 9:00–12:00 (domingo sin planificar); cada persona modifica sus propios horarios.
- 2026-11-17 — Decisión del usuario: dividir el CHG original en varios de nivel M; el MCP debe poder encontrar huecos y cruces; las tareas con agenda bloqueada (reuniones) no se mueven libremente. Dividido en: CHG-143, CHG-144, CHG-145.
- 2026-11-17 — Citas de salud y planes sin hora no bloquean el día (propuesta por defecto, revisable).
- 2026-11-17 — Aprobado por el usuario (Compuerta 2)
