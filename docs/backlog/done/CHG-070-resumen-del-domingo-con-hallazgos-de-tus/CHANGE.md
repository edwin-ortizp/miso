# CHG-070 · Resumen del domingo con hallazgos de tus datos
**Estado:** Cerrado
**Nivel:** M
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-11 · **Aprobado:** 2026-11-11 · **Cerrado:** 2026-11-11
**Relacionado:** —

## 0. En palabras simples
<!-- Para el usuario: sin jerga. Si no se entiende en 30 segundos, reescríbelo. -->
- Qué vas a ver distinto: cada domingo, un resumen con 2–3 hallazgos sobre tu propia vida, p. ej. "los días que haces ejercicio tu energía sube 1,2" o "los martes avanzas más tareas".
- Cómo comprobarlo tú: con al menos 3 semanas de datos, abre el resumen del domingo.

## 1. Resumen
Los datos registrados no le devuelven nada al usuario, así que registrar se siente administrativo. Calculamos correlaciones simples entre fuentes (ejercicio, sueño/energía, ánimo, agua, tareas, hábitos) y mostramos solo las que superan un mínimo de datos y diferencia. Sin IA en la primera versión; lenguaje cauto ("tiende a", no causalidad).

## 2. Producto (product-manager)
- Problema / usuario: registrar no le devuelve nada al usuario; no ve para qué sirven sus datos.
- Incluye: cálculo semanal (domingo) de hasta 3 hallazgos sobre las últimas 8 semanas; tarjeta en Inicio domingo y lunes que se puede cerrar; sección fija "Lo que dicen tus datos" en Estadísticas; aviso push el domingo en la franja de la tarde (tipo `weekly_insights`, activable en Ajustes).
- Hallazgos v1 (sin IA): (a) días con ejercicio vs sin ejercicio → energía media; (b) ídem → ánimo medio; (c) días con meta de agua cumplida vs no → energía; (d) día de la semana con más tareas completadas; (e) días con todos los hábitos vs no → ánimo.
- Reglas: un hallazgo solo se muestra si cada grupo tiene ≥ 6 días con dato y la diferencia es ≥ 0,5 puntos (escala 1–5) o ≥ 25 % (tareas); se ordenan por tamaño de la diferencia; lenguaje prudente ("tiende a").
- No incluye: IA, correlaciones entre más de dos variables, recomendaciones médicas.
- Criterios de aceptación:
  - Dado 8 semanas con ejercicio y energía registrados y una diferencia clara, cuando es domingo, entonces veo "Los días que haces ejercicio, tu energía tiende a ser 1,2 puntos más alta".
  - Dado pocos datos, entonces la tarjeta dice "Sigue registrando: con unas semanas más te mostraré qué patrones aparecen" y no hay aviso push.
  - Dado que calculo dos veces la misma semana, entonces hay un solo resultado y un solo aviso.
  - Dado que cierro la tarjeta, no vuelve esa semana; Estadísticas la sigue mostrando.

## 3. UX/UI
<!-- Sin pantallas: escribe "No aplica — motivo" en cada parte. Con UI, approve exige un boceto ASCII en un bloque de código. Si hay flujo, agrega un diagrama en un bloque mermaid. -->
### 3.1 Experiencia (ux-designer)
- Flujo y cantidad de pasos: aviso del domingo → Inicio (tarjeta) → "Ver en Estadísticas". Cero pasos para generarlo.
- Qué contiene cada pantalla y qué es lo más importante: título "Lo que dicen tus datos", 1 a 3 frases con un número grande y el periodo ("últimas 8 semanas"). Lo más importante es la frase, no el gráfico.
- Estados: sin datos suficientes → mensaje de ánimo a seguir registrando; con hallazgos → lista; cerrada → no se muestra en Inicio.
- Información necesaria y momento de presentación: domingo y lunes en Inicio; siempre en Estadísticas.
- Accesibilidad de uso: lista semántica; el número también va en el texto, no solo en color.

### 3.2 Visual (ui-designer)
- Distribución:
```
┌ Lo que dicen tus datos · últimas 8 semanas ── [x] ┐
│ +1,2  Los días que haces ejercicio, tu energía     │
│       tiende a ser más alta.                       │
│ Mar   Los martes es cuando más tareas completas.   │
│                          [Ver en Estadísticas]     │
└────────────────────────────────────────────────────┘
```
- Componentes existentes a reutilizar (ruta): `x-ui.card`, `x-ui.metric`, `x-ui.section`; estilos de tarjetas de Inicio.
- Jerarquía, color, tipografía y espaciado (tokens): número en `md-headline-small`, frase en `md-body-large`; sin rojo para diferencias negativas.
- Responsive y contraste: una columna en móvil; AA.

### 3.3 Escritura (ux-writer)
- Ubicación y estado: Inicio (dom–lun), Estadísticas (siempre), aviso push (domingo tarde).
- Textos finales (variables y variantes): título "Lo que dicen tus datos"; (a) "Los días que haces ejercicio, tu energía tiende a ser {d} puntos más {alta|baja}."; (b) "…tu ánimo tiende a ser…"; (c) "Los días que cumples tu meta de agua, tu energía tiende a ser…"; (d) "Los {día} es cuando más tareas completas."; (e) "Los días que completas todos tus hábitos, tu ánimo tiende a ser…"; vacío "Sigue registrando: con unas semanas más te mostraré qué patrones aparecen."; aviso push título "Tu resumen de la semana", cuerpo = primer hallazgo.
- Reglas de docs/UX-WRITING.md aplicadas: tuteo, sin causalidad ("tiende a"), sin culpa.
- Criterios de aceptación de los textos: pasan `penaltyWord`; ninguno afirma causa; el cuerpo del aviso ≤ 120 caracteres.

## 4. Diseño técnico (software-architect)
- Archivos a crear / modificar: `app/Services/Insights/WeeklyInsights.php`, tabla `weekly_insights` (único `user_id + week_start`, JSON de hallazgos, `dismissed_at`), componente `app/Livewire/Home/WeeklyInsightsCard.php` en Inicio, sección en `app/Livewire/Statistics/StatisticsDashboard.php`, generación del aviso en `GenerateNotifications` (tipo `weekly_insights`, origen = usuario no aplica: origen `insights` con UUID v5 de la semana), preferencia `push_weekly_insights` en `NotificationPreferences`, filtro en la campana.
- Enfoque y patrones a seguir: consultas agregadas por día (una por fuente, no por día) sobre `EnergyEntry`, `MoodEntry`, `ExerciseLog`, `DrinkLog`, `HabitCompletion`, `Task`, usando los scopes del usuario; `firstOrCreate` por semana para idempotencia; el aviso usa el índice único de `notifications`.
- Riesgos y mitigación: conclusiones engañosas con pocos datos (umbrales mínimos y lenguaje prudente); costo de cálculo (una vez por semana y en caché); `source_id` no nulo (UUID v5 como en CHG-067).
- Qué NO tocar: los gráficos existentes de Estadísticas.

## 5. Pruebas (qa-tester)
| Caso | Tipo | Nivel | Test |
|---|---|---|---|
| Datos sintéticos producen el hallazgo esperado | Feature | Alto | `tests/Feature/Engagement/WeeklyInsightsTest` |
| Pocos datos → sin hallazgos | Feature | Alto | ídem |
| Calcular dos veces la semana → una fila | Feature | Alto | ídem |
| Aviso del domingo una sola vez y respeta preferencia | Feature | Alto | ídem |
| Tarjeta cerrada no vuelve; Estadísticas la muestra | Feature | Medio | ídem |
| Textos sin culpa ni causalidad | Unit | Medio | ídem |
- Comando para verificar: `php artisan test --filter=WeeklyInsights`
- Regresión a vigilar: `DashboardQueryBudgetTest`, pruebas de Estadísticas y de notificaciones.

## 6. Documentación (tech-writer)
- Docs a actualizar: `docs/notifications.md` (tipo `weekly_insights`), `docs/game.md` (recurrencia).

## 7. Tareas
- [x] T1 · Cálculo semanal de hallazgos — Resp: developer — Modelo: estandar — Archivos: `WeeklyInsights`, migración — Hecho cuando: produce hasta 3 hallazgos con umbrales y es idempotente por semana.
- [x] T2 · Tarjeta en Inicio domingo y lunes — Resp: developer — Modelo: estandar — Archivos: `WeeklyInsightsCard` — Hecho cuando: se ve, se cierra y no vuelve esa semana.
- [x] T3 · Sección en Estadísticas — Resp: developer — Modelo: estandar — Archivos: `StatisticsDashboard` — Hecho cuando: muestra la última semana calculada.
- [x] T4 · Aviso push del domingo — Resp: developer — Modelo: estandar — Archivos: `GenerateNotifications`, `NotificationPreferences`, campana — Hecho cuando: se crea una vez por semana y sale por push si está activado.
- [x] T5 · Pruebas y docs — Resp: developer — Modelo: estandar — Archivos: `tests/Feature/Engagement/WeeklyInsightsTest.php`, docs — Hecho cuando: pasan los casos de §5.

## 8. Bitácora de implementación (developer)
- 2026-11-11 — `WeeklyInsights` (8 semanas, umbrales ≥ 6 días por grupo y ≥ 0,5 puntos / ≥ 25 % en tareas, máx. 3), tabla `weekly_insights` (único usuario+semana; semana lunes–domingo), tarjeta `Home\WeeklyInsightsCard` (domingo y lunes, se cierra), sección "Lo que dicen tus datos" en Estadísticas (última semana cerrada), comando `miso:weekly-insights` programado domingos 14–21 h Bogotá, aviso tipo `weekly_insights` (push por defecto activo), filtro en la campana para `guardian_nudge` y `weekly_insights`. Pruebas en `tests/Feature/Engagement/WeeklyInsightsTest.php`. Pruebas no ejecutadas en la nube (PHP 8.3 vs 8.4).

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-11 — LECCIÓN(developer): los assets compilados (`public/css`, `public/js`) viven en el repo; todo cambio de CSS/JS exige `npm run build`. Cerrado sin ejecutar las pruebas en la nube (PHP 8.3 vs 8.4): verificar en local.
- Pregunta: ¿mostrarlo en Inicio, en Estadísticas o como aviso push del domingo? → 2026-11-11 Decisión (usuario): en los tres.
- Necesita: datos de CHG-066 para medir si aumenta la recurrencia.
- 2026-11-11 — Creado en to-do/ (Borrador, nivel M)
- 2026-11-11 — Aprobado por el usuario (Compuerta 2)
- 2026-11-11 — Movido a in-progress/ (En desarrollo)
- 2026-11-11 — Implementación terminada → En verificación
- 2026-11-11 — Cerrado por el usuario (Compuerta 3) → done/
