# CHG-077 · Reto del día
**Estado:** Cerrado
**Nivel:** M
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-11 · **Aprobado:** 2026-11-11 · **Cerrado:** 2026-11-11
**Relacionado:** CHG-076 (racha y base de bonos), CHG-075 (hero del Inicio), CHG-069 (check-in), CHG-057/058/063/064 (consejos del guardián)

## 0. En palabras simples
- Qué vas a ver distinto: cada día hay **un solo reto pequeño** pensado para lo que de verdad tienes pendiente hoy ("cierra 2 tareas de Trabajo", "completa los hábitos de la mañana", "registra agua 2 veces"). Aparece como una línea en el bloque de bienvenida del Inicio y en Colección → Balls, muestra tu avance y al cumplirlo te da una ball. Si no lo haces, no pasa nada: mañana hay otro y el de ayer desaparece sin dejar rastro.
- Cómo comprobarlo tú: abre el dashboard en la mañana → ves "Reto de hoy" con su barra. Haz lo que pide → la barra avanza y al completarse llega la ball. Al día siguiente, el reto es otro.

## 1. Resumen
Un **reto diario determinista** (sin IA, sin scheduler) elegido entre plantillas medibles que ya tienen datos en MiSo: tareas, hábitos, agua, ánimo, diario, ejercicio, comidas, relaciones y foco. Se elige una vez por día y usuario con una semilla `usuario + fecha`, se guarda en `game_daily_challenges` y su progreso se calcula desde los datos del día. Al cumplirlo se entrega **una ball especial** (fuera del límite diario) una sola vez. Es la pieza de cadencia diaria que hoy falta entre el guardián (que aconseja) y las misiones semanales (que son grandes).

## 2. Producto (product-manager)
- Problema / usuario: el guardián dice qué hacer pero no hay una meta diaria concreta, y las misiones semanales son demasiado grandes para dar una razón de abrir MiSo hoy. Alex quiere un reto pequeño y contextual, con recompensa y sin culpa.
- Incluye:
  - 1 reto por día por usuario, elegido entre plantillas disponibles según sus datos;
  - progreso automático con las acciones de hoy (app y MCP);
  - 1 ball especial al cumplirlo, fuera del límite diario, con aviso;
  - línea en el hero del Inicio (CHG-075) y bloque en Colección → Balls;
  - interruptor en Colección → Ajustes;
  - sin reroll ni costo: el reto del día no se cambia.
- No incluye: retos con IA generativa; cadena de varios retos al día; rachas de retos (eso es CHG-066); retos que creen, muevan o completen datos por sí solos; notificaciones push o correo; comparación con otras personas.
- Criterios de aceptación (Dado/Cuando/Entonces):
  1. Dado un día sin reto elegido, cuando abro el dashboard por primera vez, entonces se elige un reto entre las plantillas disponibles y se mantiene todo el día.
  2. Dado que el reto es "cierra 2 tareas", cuando completo 2 tareas hoy, entonces la barra llega al 100 % y recibo la ball una sola vez.
  3. Dado el mismo reto, cuando registro acciones por MCP o por otra pantalla, entonces el progreso también avanza.
  4. Dado el día siguiente, entonces hay un reto nuevo y el anterior ya no se muestra ni resta nada.
  5. Dado que no hay datos para una plantilla (sin hábitos en ese momento, sin pendientes en la categoría, check-in ya hecho), entonces ese reto no se elige.
  6. Dado que apago el reto en Ajustes, entonces la tarjeta no aparece y no se elige ni se premia.
  8. Dado que el reto es de tareas, entonces solo cuentan las tareas creadas antes de hoy o con fecha; una tarea creada y cerrada hoy mismo no avanza el reto.
  9. Dado que esta semana un área está más descuidada (menos registros que su meta), entonces el reto prioriza esa área entre las plantillas disponibles.
  7. Un reto cumplido no se premia dos veces aunque se recargue o se abra en dos dispositivos.
- Plantillas v1 (5, todas medibles con datos existentes):
  1. `tasks_done`: "Cierra {n} tareas" (n 2–3).
  2. `category_tasks`: "Cierra {n} de {categoría}" (categoría con ≥ n pendientes).
  3. `habit_period`: "Completa los hábitos de {momento}" (con hábitos en ese momento).
  4. `water_logs`: "Registra agua {n} veces" (n 2–3).
  5. `checkin`: "Haz tu check-in de hoy" (reutiliza el check-in de CHG-069; "Ir" abre el `checkin-sheet`).
- Fuera de la v1 (siguiente iteración si funciona): diario, ejercicio, comida del plan, relación y foco.

## 3. UX/UI
### 3.1 Experiencia (ux-designer)
- Plantilla: línea dentro del hero del Inicio (CHG-075), debajo de la racha; bloque en Colección → Balls; no hay pantalla nueva.
- Flujo y cantidad de pasos: 0 pasos para verlo; 1 clic en "Ir" para hacerlo (lleva a la pantalla del módulo).
- Qué contiene cada pantalla y qué es lo más importante:
  - título "Reto de hoy", el texto del reto y la barra "1 de 2";
  - el botón "Ir" hacia el módulo implicado;
  - al cumplirlo: check, "Cumplido" y la ball ya entregada;
  - en Balls, una línea dentro del resumen con el reto del día.
- Estados (vacío / carga / error / éxito / validaciones):
  - sin reto elegido todavía: se elige al renderizar; si no hay ninguna plantilla disponible, no se muestra la tarjeta;
  - en curso: barra y "te falta 1";
  - cumplido: check y "Mañana hay otro";
  - ayer sin cumplir: no se muestra (no hay estado de fallo);
  - juego apagado o reto apagado: no se renderiza;
  - error al calcular progreso: se muestra el reto sin barra y sin premio, nunca un error.
- Información necesaria y momento de presentación: sin explicación previa; una línea pequeña "Uno al día" bajo el título.
- Accesibilidad de uso (teclado, foco, errores): tarjeta con `aria-live="polite"` al cambiar de estado; el botón "Ir" es un enlace real con texto (no solo ícono); la barra tiene `role="progressbar"` con `aria-valuenow`.

```
┌─ Reto de hoy ─────────────────────────┐
│ Cierra 2 tareas de Trabajo            │
│ ▓▓▓▓▓▓▓▓▓░░░░░░░░  1 de 2            │
│                            [Ir →]     │
└───────────────────────────────────────┘
```

```mermaid
flowchart LR
  A[Abrir dashboard] --> B{¿Hay reto hoy?}
  B -- no --> C[Elegir plantilla disponible<br/>semilla usuario+fecha]
  B -- sí --> D[Calcular progreso de hoy]
  C --> D
  D --> E{¿Meta alcanzada?}
  E -- sí --> F[1 ball + aviso, una vez]
  E -- no --> G[Mostrar avance]
```

### 3.2 Visual (ui-designer)
- Distribución: línea compacta dentro del hero de CHG-075 (texto, barra fina e "Ir"); en Balls, una línea bajo el resumen de racha.
- Componentes existentes a reutilizar (ruta): `x-ui.card`, `x-ui.progress`, `x-ui.badge`, `x-ui.button` (variante texto con icono `bi-arrow-right`), estilos `.game-*` de `_game.css`.
- Jerarquía, color, tipografía y espaciado (tokens): el texto del reto en `md-title-small`, la barra con `primary`, el estado cumplido con el verde de nivel; sin nuevos colores.
- Responsive y contraste: una tarjeta por fila en móvil; texto del reto hasta 2 líneas con elipsis y `title` completo; contraste AA.

### 3.3 Escritura (ux-writer)
- Ubicación y estado: tarjeta del dashboard, línea en Balls, interruptor en Ajustes.
- Textos finales (variables entre llaves):
  - `challenge.title`: "Reto de hoy"
  - `challenge.subtitle`: "Uno al día"
  - `challenge.tasks_done`: "Cierra {n} tareas"
  - `challenge.category_tasks`: "Cierra {n} de {category}"
  - `challenge.habit_period`: "Completa los hábitos de {period}"
  - `challenge.water_logs`: "Registra agua {n} veces"
  - `challenge.checkin`: "Haz tu check-in de hoy"
  - `challenge.progress`: "{done} de {target}"
  - `challenge.left`: "Te falta {left}"
  - `challenge.done`: "Cumplido. Mañana hay otro."
  - `challenge.action`: "Ir"
  - `challenge.notice`: "¡Reto del día cumplido! Ganaste una ball."
  - `settings.challenge`: "Reto del día"
  - `settings.challenge_help`: "Un reto pequeño cada día, con una ball al cumplirlo."
- Reglas de docs/UX-WRITING.md aplicadas: tuteo, frases cortas, sin culpa ni "debes"; los nombres de categoría y momento del día se insertan escapados.
- Criterios de aceptación de los textos: prueba que valide plantillas, ≤ 90 caracteres con valores típicos y ausencia de palabras de `MissionProposalValidator::PENALTY_WORDS`.

## 4. Diseño técnico (software-architect)
```mermaid
sequenceDiagram
  participant D as Dashboard / GameBalls
  participant C as DailyChallenge
  participant T as game_daily_challenges
  participant R as RewardGranter
  D->>C: forToday(userId)
  C->>T: buscar por user_id+fecha
  alt no existe
    C->>C: plantillas disponibles + semilla usuario+fecha
    C->>T: crear (key, params, target)
  end
  C->>C: progreso con datos de hoy
  C->>R: grantDailyChallengeBall si cumple
  C-->>D: key, texto, done, target, done_at
```
- Archivos a crear / modificar:
  - `database/migrations/2026_11_11_000004_create_game_daily_challenges_table.php`: `game_daily_challenges` (`user_id`, `date`, `key`, `module`, `params` json, `target`, `completed_at` nullable, `rewarded_at` nullable, timestamps, unique `user_id+date`).
  - `app/Models/Game/GameDailyChallenge.php` (`BelongsToUser`).
  - `app/Services/Game/DailyChallenge.php`: `forToday()` (crea con `firstOrCreate`), `progress()`, `templates()` y `available()`; semilla `crc32(user_id.'|'.$date)` para elegir sin `random_int` (estable y repetible).
  - `app/Services/Game/DailyChallengeTemplates.php` (o métodos privados): una función por plantilla con su consulta de disponibilidad y progreso, ≤ 2 consultas por render.
  - `app/Services/Game/RewardGranter.php`: usar `grantBonusBall()` de CHG-076 con clave `daily-challenge:{fecha}` (sujeto al tope semanal de bonos).
  - `app/Services/Game/GameFeed.php`: reutilizar el aviso `bonus` de CHG-076.
  - `app/Services/Game/GameSettings.php` + `app/Livewire/Game/GameSettingsPage.php` + vista: interruptor `daily_challenge`.
  - `resources/views/components/game/daily-challenge.blade.php` (componente Blade, sin Livewire propio): se pinta en el hero de CHG-075 y en `game-balls.blade.php`. El avance se refresca con el re-render del Inicio; no se agrega otro `wire:poll`.
  - `config/game.php`: `daily_challenge` (plantillas habilitadas, pesos, metas por plantilla).
  - `resources/css/m3/modules/_game.css`: tarjeta y barra.
  - Pruebas: `tests/Feature/Game/DailyChallengeTest.php`, `DailyChallengeViewTest.php`.
- Enfoque y patrones a seguir:
  - **Antifarmeo:** las tareas solo cuentan si se crearon antes de hoy o tenían fecha (`due_date`/`scheduled`); una tarea creada y cerrada el mismo día sigue dando su recompensa normal pero no alimenta este sistema.
  - **Prioridad por área descuidada:** el peso de cada plantilla se multiplica por el déficit semanal del área (mismos datos que los proveedores de consejo del guardián); la semilla elige entre las de mayor peso, de modo que el reto empuja lo pendiente y no lo más fácil.
  - **Plantillas medibles**: cada una sabe (a) si está disponible hoy y (b) cuánto progreso hay hoy, con las mismas consultas que ya usan los consejos del guardián y las misiones.
  - Elección con semilla estable y `firstOrCreate` por `user_id+date`: dos pestañas eligen el mismo reto.
  - El progreso se **deriva**, no se acumula: no hay eventos nuevos por cada acción; se cuenta lo del día con índices existentes.
  - `completed_at` se guarda cuando el progreso alcanza la meta; `rewarded_at` garantiza el premio único.
- Riesgos y mitigación:
  - *Consultas por render*: máximo 2 y solo de la plantilla elegida.
  - *Cambio de datos a mitad del día*: si la meta deja de ser alcanzable (p. ej. se borra una tarea), el reto sigue con su objetivo original y no se premia; no se regenera.
  - *Zona horaria*: fecha en America/Bogota, igual que la racha y la constancia.
  - *Doble premio*: unique `user_id+date` + `rewarded_at` + clave de idempotencia del evento.
  - *Juego apagado*: `forToday()` devuelve null y la tarjeta no se renderiza.
- Qué NO tocar: misiones semanales, consejos del guardián, cálculo de balls existente, temporadas, `WeekPlanner`.

## 5. Pruebas (qa-tester)
| Caso | Tipo | Nivel | Test |
|---|---|---|---|
| Se elige un reto disponible y se mantiene el mismo durante el día | Feature | Alto | tests/Feature/Game/DailyChallengeTest.php |
| Plantillas no disponibles no se eligen (sin hábitos, sin pendientes en la categoría, check-in ya hecho) | Feature | Alto | DailyChallengeTest |
| Progreso por tareas, categoría, hábitos, agua y check-in | Feature | Alto | DailyChallengeTest |
| Tarea creada y cerrada hoy no avanza el reto | Feature | Alto | DailyChallengeTest |
| El área con más déficit semanal se prioriza | Feature | Medio | DailyChallengeTest |
| Cumplir entrega 1 ball una sola vez y no consume el límite diario | Feature | Alto | DailyChallengeTest |
| Día siguiente genera otro reto y el anterior no resta | Feature | Medio | DailyChallengeTest |
| Interruptor apagado: sin reto, sin premio | Feature | Medio | DailyChallengeTest |
| La línea muestra progreso, estado cumplido y enlace (o abre el check-in) | Componente | Medio | tests/Feature/Game/DailyChallengeViewTest.php |
| Plantillas de texto sin palabras prohibidas y ≤ 90 caracteres | Feature | Bajo | DailyChallengeTest |
- Comando para verificar: `php artisan test tests/Feature/Game/DailyChallengeTest.php tests/Feature/Game/DailyChallengeViewTest.php`
- Regresión a vigilar: `GameNotifierTest`, `DashboardTest`, `GameBallsTest`, `GuardianAdviceTest` (los proveedores de consejo no cambian), `WaterDailyProgressTest` y `HabitGamificationTest`.

## 6. Documentación (tech-writer)
- Docs a actualizar: `docs/game.md` → nueva sección "Reto del día" (plantillas, elección, progreso, premio, zona horaria, ajuste y datos); tabla de datos con `game_daily_challenges`.

## 7. Tareas
- [x] T1 · Elegir un reto disponible por día y calcular su progreso desde tus datos — Resp: developer — Archivos: migración, app/Models/Game/GameDailyChallenge.php, app/Services/Game/DailyChallenge.php, config/game.php, tests/Feature/Game/DailyChallengeTest.php — Hecho cuando: criterios 1, 2, 3, 5, 7, 8 y 9 pasan
- [x] T2 · Premio de 1 ball al cumplirlo y aviso, una sola vez (vía `grantBonusBall`) — Resp: developer — Archivos: app/Services/Game/DailyChallenge.php, tests/Feature/Game/DailyChallengeTest.php — Hecho cuando: criterios 2, 4 y 7 pasan
- [x] T3 · Línea en el hero del Inicio y en Balls, con interruptor en Ajustes — Resp: developer — Archivos: resources/views/components/game/daily-challenge.blade.php, resources/views/components/game/daily-buddy.blade.php, app/Livewire/Home/Dashboard.php, resources/views/livewire/game/game-balls.blade.php, app/Services/Game/GameSettings.php, app/Livewire/Game/GameSettingsPage.php, resources/views/livewire/game/game-settings.blade.php, resources/css/m3/modules/_game.css, tests/Feature/Game/DailyChallengeViewTest.php — Hecho cuando: criterio 6 pasa y la tarjeta se ve en 390/768/1440 px con el build compilado
- [x] T4 · Verificar criterios de aceptación — Resp: qa-tester — Hecho cuando: los 9 criterios pasan y las suites afectadas están en verde (salvo fallas previas conocidas)
- [x] T5 · Actualizar documentación — Resp: tech-writer — Archivos: docs/game.md — Hecho cuando: la sección del reto explica plantillas, datos y ajuste

## 8. Bitácora de implementación (developer)
- 2026-11-11 — T1: migración `2026_11_11_000007` (`game_daily_challenges`), modelo `GameDailyChallenge` y servicio `DailyChallenge`. Plantillas v1 con su disponibilidad, área descuidada de la semana (`weekly_per_day`) y semilla `crc32(usuario|fecha)`. Avance derivado; las tareas creadas hoy sin fecha ni recurrencia no cuentan. Ante una carrera por la clave única se reutiliza el reto ya creado.
- 2026-11-11 — T2: premio con `grantBonusBall` (`daily-challenge:{fecha}`, aviso `bonus`), con `completed_at` y `rewarded_at`.
- 2026-11-11 — T3: `x-game.daily-challenge` en el hero (bajo la racha; con check-in, «Ir» abre la hoja) y en Balls; interruptor «Reto del día» en Ajustes (`#reto`). Sin componente Livewire ni `wire:poll` propio.
- 2026-11-11 — T4 (QA): `DailyChallengeTest` (11) y `DailyChallengeViewTest` (3) en verde; cubren los criterios 1–9 y los textos. Regresión de Game, Inicio, Engagement y Ui: 408/419, y las 11 fallas son previas (mismo listado conocido).
- 2026-11-11 — T5: `docs/game.md` → «Reto del día»; tabla de datos y servicios actualizadas.

## 9. Preguntas abiertas, decisiones e historial
- LECCIÓN: ninguna
- 2026-11-11 — Decisiones (usuario) antifarmeo: las tareas creadas y cerradas el mismo día no cuentan; el reto prioriza el área más descuidada de la semana.
- 2026-11-11 — Revisión: v1 con 5 plantillas (tareas, categoría, hábitos, agua, check-in); sin componente Livewire ni poll propio; va en el hero de CHG-075; premio con `grantBonusBall` de CHG-076. Depende de: CHG-076. El cierre del día (CHG-070) podrá mostrar el reto.
- 2026-11-11 — Decisiones (Alex): un reto al día, elegido entre lo que de verdad hay disponible; sin reroll; premio en ball fuera del límite; sin culpa por no hacerlo.
- 2026-11-11 — Creado en to-do/ (Borrador, nivel M)
- 2026-11-11 — Renumerado de CHG-069 a CHG-077 (choque con CHG-069 cerrado).
- 2026-11-11 — Aprobado por el usuario (Compuerta 2)
- 2026-11-11 — Movido a in-progress/ (En desarrollo)
- 2026-11-11 — Implementación terminada → En verificación
- 2026-11-11 — Cerrado por el usuario (Compuerta 3) → done/
