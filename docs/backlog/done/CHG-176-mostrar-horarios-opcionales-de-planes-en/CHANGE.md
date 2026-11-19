# CHG-176 · Mostrar horarios opcionales de planes en el calendario
**Estado:** Cerrado
**Nivel:** M
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-18 · **Aprobado:** 2026-11-18 · **Cerrado:** 2026-11-18
**Relacionado:** —

## 0. En palabras simples
- Qué vas a ver distinto: Registrar horas opcionales, validar el intervalo y proyectar el plan como evento horario conservando los planes de día completo.
- Cómo comprobarlo tú: Abre Planes o Calendario y prueba el recorrido descrito.

## 1. Resumen
Registrar horas opcionales, validar el intervalo y proyectar el plan como evento horario conservando los planes de día completo.

## 2. Producto
Entrega independiente del plan aprobado de mejoras de Planes y Calendario. Criterio de aceptación: Registrar horas opcionales, validar el intervalo y proyectar el plan como evento horario conservando los planes de día completo.

## 3. UX/UI
### 3.1 Experiencia
Plantilla: detalle y editor

Un plan puede registrarse sin horas, con hora de inicio, o con hora de inicio y fin. Si termina en otro día, se usa la fecha final.

### 3.2 Visual
```text
Fecha [      ] Inicio [      ]
Hasta [      ] Fin [         ]
```

### 3.3 Escritura
Usar las etiquetas del boceto y los textos del plan aprobado.

## 4. Diseño técnico
Las horas se guardan separadas de las fechas. El detalle presenta los valores registrados. El calendario proyecta los horarios como eventos locales con fin nulo si no hay fin preciso. La fecha final posterior sin hora mantiene el día inclusive. Los planes sin hora conservan el evento de día completo.

Archivos: database/migrations, app/Actions/SavePlan.php, app/Models/Plan.php, app/Livewire/Plan, app/Livewire/Task/TaskCalendar.php, resources/views/livewire/plan, tests/Feature/PlanDetailsAndCalendarTest.php, tests/Browser

## 5. Pruebas
Pruebas de rangos, horario de inicio solo, rango nocturno, día completo y vistas horaria y mensual en Playwright.

## 6. Documentación
No aplica — recorrido cubierto por la especificación y las pruebas.

## 7. Tareas
- [x] T1 · Registrar horas opcionales, validar el intervalo y proyectar el plan como evento horario conservando los planes de día completo — Resp: developer — Archivos: database/migrations, app/Actions/SavePlan.php, app/Models/Plan.php, app/Livewire/Plan, app/Livewire/Task/TaskCalendar.php, resources/views/livewire/plan, tests/Feature/PlanDetailsAndCalendarTest.php, tests/Browser — Hecho cuando: los criterios y las pruebas dirigidas estén verificados

## 8. Bitácora de implementación (developer)

- T1. Pasaron las pruebas del intervalo, los planes de día completo y la proyección de horas opcionales. Playwright confirmó el plan con inicio a las 19:00 en día, semana y mes. Se probó revertir y aplicar la migración en SQLite aislado.
- Validación común: 29 pruebas PHP dirigidas, 4 escenarios Playwright y npm run build. ui:conformance conserva tres hallazgos previos en Game y Mi día. El CLI instalado carece de context y activity, por lo que el registro de evidencia es manual.

## 9. Preguntas abiertas, decisiones e historial
- LECCIÓN: ninguna.
- 2026-11-18 — Creado en to-do/ (Borrador, nivel M)
- 2026-11-18 — Aprobado por el usuario (Compuerta 2)
- 2026-11-18 — Movido a in-progress/ (En desarrollo)
- 2026-11-18 — Implementación terminada → En verificación
- 2026-11-18 — Cerrado por el usuario (Compuerta 3) → done/
