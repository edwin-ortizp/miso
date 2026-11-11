# Colección (juego)

Juego de colección que reconoce avances reales: completar tareas y grupos de hábitos genera **balls** que se abren cuando quieras y revelan especies. Principio: **cada acción suma; ninguna ausencia resta**. Nada se pierde por desmarcar, descansar, dejar de usar MiSo o apagar el juego.

Pantallas: menú **Productividad → Colección** (`/game`), con las pestañas Balls, Colección, Semana, Temporada y Ajustes.

- **Contadores:** balls por abrir (también en el menú lateral), descubiertas/total, misiones cumplidas y días que le quedan a la temporada.
  - Se declaran en `config/modules.php` con `'counter' => 'Clase@método'` y los resuelve `App\Support\Ui\NavCounter`.
  - Los del juego están en `App\Support\Game\TabCounters`.
- **Colección:**
  - Búsqueda por número, o por nombre solo entre las descubiertas.
  - Filtros de estado (todas, descubiertas, por descubrir, mi equipo), tipo y rareza.
  - 60 por página; `?species={id}` abre la ficha.
- **Enlaces:**
  - Cada misión lleva a sus tareas (`/tasks/list?category[]=…`) o a los hábitos.
  - Cada integrante del equipo y la revelación de una ball abren la ficha.
  - La ficha indica si está en el equipo de la semana.
- **Ajustes:** todos en una pestaña, en tres grupos: Juego, Director y Temporadas.

## Cómo se ganan balls

| Fuente | Regla | Clave de idempotencia |
|---|---|---|
| Tarea completada | Cualquier vía: lista, kanban o cambio de estado, recurrentes (cada ocurrencia), calendario, salud, relaciones, panel, CalDAV, MCP. Se detecta con `App\Observers\GameTaskObserver` | `task:{id}:{fecha}` |
| Grupo de hábitos | Al completar todos los hábitos de un momento del día (mañana, tarde, noche, cualquier momento) | `habit-group:{momento}:{fecha}` |
| Foco | Los minutos de pomodoro completados del día alcanzan la meta del módulo Pomodoro (semana/fin de semana). Una por día, no por pomodoro | `focus:{fecha}` |
| Progreso de meta | Registrar una entrada de texto o numérica en una meta. Una por meta y día | `goal-progress:{meta}:{fecha}` |
| Meta cumplida | La meta pasa a `completed`. **Especial**: no cuenta ni se limita por el máximo diario; una sola vez por meta | `goal-done:{meta}` |
| Agua del día | El total hidratado del día alcanza tu meta de agua | `water:{fecha}` |
| Ejercicio del día | Los minutos del día alcanzan tu meta de ejercicio (30 por defecto) | `exercise:{fecha}` |

- Todas cuentan igual desde la app o el MCP: se detectan en los modelos (`app/Observers/GameTaskObserver.php` y `app/Observers/Game/*`) y otorgan tras confirmar la transacción.
  - Los efectos del juego que dependen de datos escritos en la misma transacción (p. ej. la serie de una tarea recurrente) se ejecutan en `DB::afterCommit`. Si se otorgan antes, la ocurrencia recurrente no se reconoce en las misiones (CHG-026).
- **Ámbitos** (`config('game.scopes')`): `habits` (Hábitos), `focus` (Foco), `goals` (Metas) y `health` (Salud), además de las categorías de tarea. Cada uno puede ser afinidad del equipo, cuenta en el progreso de variedad y se admite en los bonos de temporada. Las nuevas misiones `category_tasks` requieren una categoría real con pendientes elegibles.
- En Ajustes puedes apagar cada fuente: tareas, hábitos, foco, metas y salud, y las de constancia (vehículo, bienestar, personas y hogar).

### Constancia (micro-xp, CHG-042)

El primer registro del día de cada fuente da **+1 xp** (`consistency_xp`) a cada integrante del equipo de la semana. No da balls, no avanza misiones ni variedad, no tiene afinidad ni bono de temporada y no consume el máximo diario. Cuenta el día en que se registra (no la fecha del registro), así cargar días pasados no multiplica la xp. Nunca se lee el contenido.

| Fuente | Registros | Clave |
|---|---|---|
| Vehículo (`vehicle`) | `VehicleEnergyLog`, `VehicleExpense`, `VehicleMaintenanceLog` | `micro:vehicle:{fecha}` |
| Bienestar (`wellbeing`) | `JournalEntry`, `MoodEntry`, `EnergyEntry` | `micro:wellbeing:{fecha}` |
| Personas (`people`) | `RelationshipEvent`, `PlanVisit` | `micro:people:{fecha}` |
| Hogar (`home`) | `MealPreparation`, `PreparedMeal`, `Purchase` | `micro:home:{fecha}` |
| Tareas personales (`personal_tasks`) | Tareas completadas de categoría exacta `personal` | `micro:personal_tasks:{fecha}` |

- Se detecta en `App\Observers\Game\ConsistencySourceObserver` (app y MCP) y lo otorga `App\Services\Game\ConsistencyXp`.
- Aviso de experiencia sin celebración: "Constancia · {Fuente} · Gotin +1 · …".
- Cada fuente se apaga en Ajustes → Constancia.
- Las tareas de categoría exacta `personal` dan constancia en lugar de ball y no avanzan misiones ni consumen el límite diario. No incluye subcategorías. El interruptor es «Tareas personales (categoría Personal)». Al apagarlo, vuelven al flujo normal de recompensas por tareas, sujeto a sus interruptores y límites. Esta fuente se deriva en `RewardGranter::forTask`, no en `ConsistencySourceObserver`.

- Máximo diario configurable (por defecto **5**). Al alcanzarlo, la actividad se registra sin ball y no se muestra error.
- La especie se decide **al conceder** la ball, con una semilla registrada. Solo salen especies de etapa 1, por rareza ponderada.
- Protección: tras **10** balls seguidas sin una línea nueva, la siguiente garantiza una especie no descubierta.
- **Avisos y celebraciones (CHG-034):** `GameNotifier` vive en el layout y reemplaza a `BallNotifier`. Lee los eventos nuevos (`GameFeed`) y muestra hasta 3 avisos apilados, que duran 5 s y se pueden cerrar:
  - ball ganada, con su origen y el botón Abrir;
  - experiencia del equipo, agrupada por actividad, con la foto de cada Pokémon que la ganó (por ejemplo "Gotin +9 (afinidad)"; CHG-050);
  - subida de nivel y evolución;
  - misión (al 50 % y cumplida);
  - meta cumplida.
- **Presentación de los avisos (CHG-038):** tarjetas de logro arriba a la derecha, bajo la barra superior (en móvil, a todo el ancho). Cada tipo tiene su color: ball roja, experiencia, nivel verde, misión azul, meta y evolución doradas. Llevan ícono grande animado, título en versalitas, el botón "Abrir ball" y una barra de tiempo de 5 s que se pausa al pasar el mouse o al enfocar. Ninguna barra inferior (Debug Bar, navegación móvil) las tapa.
- **Cuándo se entera (CHG-041):** si una acción en la app crea un evento del juego del usuario, la respuesta trae la cabecera `X-Game-Event: 1` (`AddGameEventHeader` + `RequestGameActivity`) y solo entonces el navegador pide `game-check`; las acciones sin novedad no generan una segunda petición. También escucha los eventos de tareas y hábitos, y consulta cada 60 s mientras la pestaña está visible (`wire:poll.60s.visible`) para lo que llega por MCP. No repite lo ocurrido antes de cargar la página.
- **Celebraciones:** en los momentos importantes (ball, nivel, evolución, misión o meta cumplida) sale una al azar del catálogo, sin repetir la anterior: confeti, gatitos, estrellas fugaces, globos, fuegos artificiales, burbujas, lluvia de balls y pétalos (`resources/js/game-celebrations.js`).
  - Se configuran en Ajustes → Celebraciones: mostrar sí o no, y cuáles.
  - Con movimiento reducido solo hay avisos.
  - Para agregar una: su clave en `config('game.celebration_catalog')` y su función en el catálogo JS.
- **Sonidos (CHG-044):** sonidos cortos sintetizados con Web Audio (`resources/js/game-sounds.js`), sin archivos, a volumen bajo fijo: `ball` al ganar una ball, `open` al abrir (también con movimiento reducido), `level` en nivel o evolución y `mission` en misión o meta cumplida. Por tanda suena solo el momento más importante y la experiencia sola no suena.
  - Ajustes → Celebraciones → "Sonidos" (encendido por defecto) y "Probar sonido". Es independiente de las animaciones.
  - El notificador despacha `game-sound` y expone el ajuste en `data-game-sound`. Solo suena después del primer clic o tecla en la página y nunca con la pestaña oculta.

## Abrir, experiencia, niveles y evolución

- Abrir es idempotente: abrir de nuevo devuelve el mismo resultado. Si la pantalla falla, la ball sigue guardada.
- **Línea nueva:** se crea el ejemplar (uno por línea evolutiva) y recibe +5 xp de bienvenida.
- **Repetida:** +10 xp al ejemplar de esa línea, en la etapa en que esté. Nunca se crea un segundo ejemplar.
- **Nivel:** `1 + floor(xp / 40)`, con tope 100. Nunca baja.
- **Evolución:** la etapa 2 llega en el nivel 10 y la etapa 3 en el nivel 25, salvo que la especie defina su propio `evolve_level`. Si hay un solo destino, evoluciona sola. Si hay ramas, la ficha muestra "Listo para evolucionar" y el usuario elige.
- Las etapas alcanzadas quedan en `game_discoveries`, así que las previas siguen descubiertas.
- La ficha (tocar una especie) muestra la bio, los tipos y las estadísticas, el ejemplar y el historial "Creció gracias a".

## Semana: equipo y misiones

Pestaña **Semana** (`/game/week`). La semana va de lunes a domingo (America/Bogota) y se arma sola **la primera vez que se consulta o que llega una actividad** de esa semana; no hay scheduler (`WeekPlanner`).

- **Equipo:** hasta 6 ejemplares, elegidos al azar con preferencia por los que han estado menos veces (peso `1 / (1 + apariciones)`, semilla registrada). Si la semana empezó con huecos, se completan al descubrir nuevos ejemplares. Los equipos anteriores quedan guardados.
- **Habilidades:** afinidad con una categoría de tarea del usuario o con "Hábitos". Cada actividad elegible da **+3 xp** a todo el equipo y **+6 extra** al integrante afín, aunque se haya alcanzado el límite diario de balls. En el historial aparece como "Equipo de la semana · {origen}".
- **Misiones:** hasta 3 por semana, con metas base de 2 a 4: tareas de una categoría, grupos de hábitos de un momento del día (días distintos) y variedad de ámbitos. Al 50 % se recibe **1 ball** y al 100 % **2 más**, una sola vez. Estas balls no cuentan para el límite diario. Si una misión queda incompleta no pasa nada y la semana siguiente trae otras.
- **Pendientes elegibles (CHG-040):** tareas propias sin completar, no privadas, no recurrentes y fuera del historial de recurrencias. El generador elige categorías por cantidad de estos pendientes, de mayor a menor y sin repetir; el foco de temporada solo desempata cantidades iguales y luego decide la semilla semanal. La meta por categoría no supera los pendientes disponibles y puede ser **1**. Para generar variedad se cuentan solo esas categorías y el ámbito de hábitos si existe. Después de crear la misión, las tareas recurrentes siguen sumando progreso de categoría, igual que antes. Las semanas existentes no se regeneran.
- **Misiones del director (CHG-026):** cada lunes el director puede proponer las misiones de la semana actual con `propose-game-week-missions-tool`.
  - Incluye el tipo `specific_tasks`: "Cierra n de estas m tareas", con 2 a 6 tareas abiertas no privadas. Cuenta una vez por tarea; las recurrentes cuentan por su ocurrencia.
  - Solo reemplaza misiones sin avance; si alguna ya avanzó, responde `ya_con_avance` y nada cambia.
  - La propuesta inicial sigue limitada a una por semana (`ya_propuestas`); para ajustes solicitados por el usuario se utiliza la herramienta separada siguiente.
  - Los títulos no pueden usar "debes", "obligatorio", "atrasado", "fallaste" ni "castigo".
  - En Semana, esas misiones llevan "Propuesta por el director" y sus tareas enlazadas, con ✓ cuando ya están completadas.
  - Si el director no propone nada, quedan las misiones automáticas.
- **Ajustes conversacionales (CHG-040):** `update-game-week-missions-tool` recibe `missions`, una lista de 1 a 3 sustituciones completas `{id, type, title, target, task_ids?, category?, period?}`. Obtén los IDs de `current_week.missions` y cambia solo `editable=true` (progreso cero, sin recompensas). Conserva el ID, no aumenta el cupo y permite cambios aunque otras misiones estén avanzadas o completadas. Valida toda la solicitud antes de guardar; un ID ajeno, de otra semana, repetido o una propuesta inválida rechaza el conjunto sin cambios. Responde `applied`, `unchanged` en reintentos idénticos o error `rejected` con motivos. No otorga recompensas ni elimina contribuciones. La transacción bloquea primero al usuario y después las misiones, igual que la ruta de progreso. Requiere el director activo y no debe invocarse desde el schedule para regenerar la semana automáticamente.

## Integrante del día (CHG-043)

Aparece un ejemplar **al azar** del usuario con una frase (`x-game.daily-buddy`). En el panel de pared ocupa la 5.ª columna de "Próximos días" (4 días + compañero, `variant="wall"`, con el marco de un día), con el arte, el nivel y la frase. En el dashboard es una tarjeta. Se elige al cargar la página (`DailyBuddy::pick`) y se guarda en la propiedad `buddy`, así que las actualizaciones automáticas del panel no lo cambian. Tocarlo abre su ficha. Sin ejemplares, el dashboard invita a abrir la primera ball y el panel conserva sus 5 días. Con el juego apagado no aparece.

- **Columna del panel (CHG-049):**
  - Escenario según la familia del tipo principal (`config('game.stage_families')`): fuego, agua, planta, eléctrico, místico, tierra y cielo, que también es el neutro. Todo es CSS, sin imágenes.
  - Reacción al avance del día: hábitos + tareas de hoy hechos sobre el total, con `config('game.buddy_moods')`:

    | Avance | Reacción |
    |---|---|
    | ≥ 70 % | "¡Vas volando!" (salta) |
    | ≥ 30 % | "Buen ritmo" |
    | < 30 %, antes de las 15:00 | "Hoy empezamos juntos" |
    | < 30 %, desde las 15:00 | "Un pasito y ya cuenta" |
    | Sin hábitos ni tareas | "Día libre, disfrútalo" |

  - Aviso "Tienes n balls", que lleva a Balls.
  - Botón "Otra frase" (`WallPanel::anotherQuote`): cambia la frase sin cambiar de compañero.
  - La reacción y las balls se recalculan en cada refresco del panel; el compañero, no.
- **Frases** (`game_quotes`): 30 semilla (`user_id` null, se insertan con la migración desde `GameQuote::SEED`) siempre elegibles, más las del director, que dejan de salir a los 60 días (`config('game.quotes')`).
- **`propose-game-quotes-tool`** recibe `{ "quotes": [ … ] }`: 7 a 14 frases de hasta 140 caracteres, sin repetir y sin palabras de culpa (`MissionProposalValidator::PENALTY_WORDS`). Todo o nada. Una propuesta por semana (`ya_propuestas`); con el director en pausa responde `director_paused`. El contexto del director trae `quotes_due` y `quotes_limits`.

## Guardianes de módulo (CHG-055)

Cada módulo tiene un guardián que asoma en la esquina de una tarjeta de su panel lateral, con una frase según el avance del día.

- **Dónde:** Tareas → tarjeta "Hoy"; Salud → tarjeta "Próximo cuidado".
- **Quién (CHG-066):** se elige entre los integrantes del **equipo de la semana**, así el equipo se ve en toda la app y rota cada lunes; la tarjeta muestra "Equipo de la semana · {habilidad}". Sin equipo se usa toda la colección. Dentro de ese grupo: el ejemplar de mayor nivel con un tipo afín al módulo (`game.module_guardians`: Tareas = psíquico/acero, Salud = hada/normal); empate → el más antiguo; sin afinidad → el de mayor nivel. Se elige al montar la página y no cambia con los re-renders.
- **Frase:** `ModuleGuardian::line()` usa los umbrales de `DailyBuddy::mood` y los textos de `game.guardian_lines`. Tareas: completadas/planificadas hoy. Salud: si hay un evento en seguimiento (tipo de evolución sin fecha de fin) dice la frase `care`; si no, completados en 7 días / (esos + pendientes de salud).
- **Oculto** con el juego apagado o sin ejemplares.
- **Ficha (CHG-062):** tocar al guardián abre su ficha en un modal dentro del módulo, en solo lectura (sin evolucionar ni "Ver más"), con "Ver colección" para ir a la Colección. La ficha la arma `SpeciesSheet::build()`, compartido con la Colección, y solo se calcula al abrir.
- **Agregar otro módulo:** añadir sus tipos en `module_guardians` y sus frases en `guardian_lines`, elegir el guardián en `mount()` con `ModuleGuardian::pick($userId, 'modulo')`, y poner `<livewire:game.guardian-card>` (ver Consejos del guardián).

### Consejos del guardián (CHG-057)

El guardián es un componente Livewire aislado (`<livewire:game.guardian-card module="tasks|health" variant="top|rail" />`). Su globo muestra un consejo calculado con reglas sobre tus datos (sin IA); el botón "Otro consejo" pide uno distinto y se renueva solo cada `game.guardian_advice.refresh_seconds` (300 s) con la pestaña visible, sin re-renderizar la lista.

- `GuardianAdvisor` pide candidatos al proveedor del módulo y elige al azar ponderado sin repetir el actual; sin candidatos vuelve a la frase por avance.
- **Tareas** (`TaskAdvice`): próxima tarea con hora; carga del día (estimado de pendientes de hoy contra lo que queda hasta las 23:59); ≥ 3 vencidas de una categoría con más de 7 días; racha (completadas hoy ≥ 2 y por encima del promedio del mismo día de la semana en las 4 semanas previas); día de una sola categoría raíz (≥ 3 tareas).
- **Salud** (`HealthAdvice`): seguimiento de eventos abiertos (día n); días sin registro (≥ 3); próximo cuidado en ≤ 2 días; mes actual frente al mes con más eventos del año.
- Textos en `game.guardian_advice.{módulo}.{clave}`; los valores se escapan y los nombres van en negrita.
- **Hidratación** (`WaterAdvice`): lo que falta para la meta; ritmo frente al promedio a la misma hora de los 7 días previos; ≥ 3 h sin registrar (7:00-22:00); racha de días con meta (≥ 2).
- **Ejercicio** (`ExerciseAdvice`): minutos que faltan; ≥ 2 días sin registro; tipo sin hacer en 14 días con ≥ 3 registros antes (75 días); semana actual vs anterior si vas por encima.
- **Pomodoro** (`PomodoroAdvice`): foco que falta (meta entre semana / fin de semana, `PomodoroAdvice::goalFor`); tarea con más foco en 7 días; mejor franja (mañana/tarde/noche) en 14 días si estás en ella; bloque para la tarea agendada a esta hora sin foco hoy.
- **Hábitos** (`HabitAdvice`): el mensaje del coach (`HabitGamificationService::coachCard`) con la acción "Ir al hábito sugerido"; pendientes del momento del día con el más corto; racha ≥ 3 días aún sin completar hoy; hábito más olvidado del mes (≥ 5 días). Con guardián, la tarjeta coach de arriba desaparece y el guardián va en "Constancia" (primera tarjeta del panel); sin guardián, vuelve el coach.
- **Afinidades** (`game.module_guardians`): Tareas psíquico/acero, Salud hada/normal, Hábitos fuego/planta, Hidratación agua/hielo, Ejercicio lucha/roca, Pomodoro eléctrico/psíquico.
- **Objetivos** (`GoalAdvice`): meta activa que vence en ≤ 14 días (con avance si es numérica); meta sin registros hace ≥ 7 días; hito en los últimos 7 días.
- **Ánimo y energía** (`MoodAdvice`): sin registro de ánimo hoy; tendencia de 7 días vs los 7 previos (diferencia ≥ 0,5); franja con más energía en 14 días.
- **Hábitos a evitar** (`NegativeHabitAdvice`): días limpios (≥ 2); cerca de la mejor racha (≥ 80 %); apoyo si hubo registro en las últimas 48 h (lo nombra sin culpa).
- **Diario** (`JournalAdvice`): días sin escribir (≥ 2); racha (≥ 3); recuerdo "Hace un mes/un año escribiste: “…”" con una cita de ≤ 80 caracteres, sin HTML y escapada. La cita solo se muestra al propio usuario dentro de la app.
- Estos cuatro módulos estrenan panel lateral (`rail_routes` en su ruta principal) con la tarjeta "Tu guardián"; solo se muestra si hay guardián. Sin consejos usan `game.guardian_default_line`.
- **Afinidades CHG-063:** Objetivos dragón/volador, Ánimo hada/normal, Diario psíquico/fantasma, Hábitos a evitar siniestro/lucha.
- **Comidas** (`MealAdvice`): próxima comida planificada hoy; comida de hoy sin marcar una hora después de su horario; días restantes de la semana sin plan (≥ 2).
- **Relaciones** (`RelationshipAdvice`): cumpleaños en ≤ 7 días (apodo o nombre); persona con ≥ 2 eventos y sin registros hace ≥ 60 días.
- **Planes** (`PlanAdvice`): plan agendado en ≤ 7 días; ≥ 3 planes pendientes sin visitas (sugiere uno al azar); última visita hace ≥ 30 días.
- **Vehículos** (`VehicleAdvice`): mantenimiento vencido o que vence en ≤ 14 días por fecha (`VehicleMaintenanceStatus::forPlan`); ≥ 21 días sin registrar carga.
- Relaciones y Planes muestran al guardián dentro de su panel actual; Comidas (`meals.weekly`) y Vehículos estrenan panel con "Tu guardián" (solo con guardián). La lista de Vehículos ahora usa `x-module-shell`.
- **Afinidades CHG-064:** Comidas planta/bicho, Relaciones hada/normal, Planes volador/tierra, Vehículos acero/eléctrico.
- Un candidato puede traer `action` (`label`, `target` = id del elemento a enfocar).
- **Nuevo proveedor:** clase con `candidates(CarbonInterface $now)` que use el trait `Advice`, registrarla en `GuardianAdvisor::PROVIDERS` y agregar sus plantillas.

## Director y temporadas

El director IA **no corre dentro de MiSo**: es un schedule de Paseo que usa el conector MCP de MiSo. Lee los datos de producción **solo por MCP**: agregados del juego y, para las misiones, la lista acotada de tareas abiertas no privadas. MiSo no integra un cliente LLM ni usa el scheduler del servidor. Todo cálculo de recompensas sigue siendo determinista en el backend.

- `get-game-director-context-tool` devuelve:
  - la participación de las últimas 8 semanas (actividades, actividad por ámbito, balls y misiones);
  - las categorías, el tamaño de la colección, las temporadas activa, programada y recientes;
  - los límites y `proposal_due`;
  - `open_tasks`: hasta 40 tareas abiertas **no privadas**, con id, código, título, categoría, vencimiento, si está vencida, tamaño, prioridad y días abierta. Lo aprobó Alex para las misiones contextuales;
  - `pending_tasks_by_category`: conteos completos de pendientes elegibles, sin el límite de 40 de `open_tasks`;
  - `habit_periods` y `current_week` (misiones actuales con `id`, `params`, `editable`, además de `replaceable` para la propuesta inicial).
  - No incluye descripciones de tareas, tareas privadas, diario, salud ni contactos.
- `propose-game-season-tool` recibe `{ "season": { … } }`. `SeasonValidator` valida estrictamente:
  - rechaza claves desconocidas;
  - nombre ≤ 60 caracteres y narrativa ≤ 400;
  - 2 a 6 semanas;
  - metas de misión de 2 a 5 y hasta 2 categorías de enfoque;
  - `team_bonus` de ×1,0 a ×1,5 en hasta 2 ámbitos (categoría o `habits`);
  - pesos de rareza de 0 a 100, con legendary ≤ 3;
  - `featured_line`, que debe existir (peso ×2 dentro de su rareza).
- **Resultados de la propuesta:**
  - Si es válida y toca proponer, **se activa sola**: ahora si no hay temporada, o al terminar la actual si le quedan 7 días o menos.
  - Si es inválida, queda `rejected` con sus motivos y el juego sigue igual.
  - Con el director en pausa, se rechaza.
  - Si aún no toca, responde `not_due` sin crear nada.
- **Efectos de la temporada:**
  - las misiones de las semanas que empiezan dentro de ella;
  - el multiplicador de xp de equipo;
  - los pesos y la línea destacada de las balls.
  - Cada evento guarda `season_id`. Nada es retroactivo.
- **Pestaña Temporada** (`/game/season`):
  - la temporada activa con sus reglas en lenguaje simple y la programada;
  - Revertir o Cancelar, que termina la temporada ahora sin tocar lo ganado;
  - los interruptores "Director activo" y "Juego de temporadas";
  - el historial.
- `notes` (≤ 1000 caracteres) guarda ideas del director para revisar; nada se instala automáticamente.
- **Temporadas temáticas (CHG-045):** la propuesta admite `theme` (catálogo cerrado `config('game.season_themes')`: `navidad`, `ano-nuevo`, `halloween`, `amor-amistad`, `cumpleanos`) y `featured_types` (1 o 2 tipos que existan en el catálogo, guardados en slug).
  - Los tipos destacados pesan ×1,5 dentro de su rareza y se combinan con `featured_line`.
  - Cada tema trae color e ícono en la pestaña Temporada (`game-season--theme-{clave}`) y una celebración propia: nieve, destellos, calabazas, corazones o velas. Esa celebración entra al sorteo solo mientras dure la temporada y respeta "Mostrar celebraciones" y el movimiento reducido.
  - El contexto incluye `calendar_hints`: los temas vigentes o que empiezan en las próximas 6 semanas, con `starts_on`/`ends_on` (mes-día) y `suggested_types`. Del cumpleaños solo se usa el mes y el día de `users.birth_date` (±7 días); nunca se expone el año ni la edad.

### Idempotencia (schedule que corre dos veces o reintentos)

| Herramienta / proceso | Segunda corrida |
|---|---|
| `get-game-director-context-tool` | Solo lee. |
| `propose-game-season-tool` | Responde `not_due`, porque ya hay una temporada vigente o programada. El bloqueo por usuario evita duplicados aunque las dos corran a la vez. |
| `propose-game-week-missions-tool` | Responde `ya_propuestas`: solo se acepta **una propuesta del director por semana** (CHG-028). El contexto lo muestra con `current_week.director_proposed`. |
| Armado de la semana | Se crea una sola vez (índice único `user_id + week_start` y bloqueo). |
| Balls, xp, avance de misiones | Clave única por efecto (`task:{id}:{fecha}`, `focus:{fecha}`, `team:{evento}:{ejemplar}`, `mission:{id}:half:1`…). |
| Propuestas inválidas | Cada rechazo queda en el historial de Temporada; no cambia el juego. |

### Schedule de Paseo sugerido

Frecuencia: **semanal** (lunes, temprano). Prompt:

```
Eres el director del juego de Colección de MiSo. Usa SOLO el conector MCP de MiSo.
1. Llama get-game-director-context-tool. Si director_enabled es false, termina.
2. MISIONES DE LA SEMANA (cada lunes): si current_week.replaceable es true, propone 3 misiones con
   propose-game-week-missions-tool pensadas en open_tasks:
   - una specific_tasks con 3-5 tareas concretas (prioriza vencidas y las que llevan más días abiertas),
     con una meta alcanzable (la mitad o un poco más);
   - otra que equilibre: una categoría con pending_tasks_by_category > 0 (category_tasks, meta de 1 a 5 sin superar ese conteo) o hábitos (habit_groups, period de habit_periods);
   - una variety para premiar el equilibrio.
   Títulos breves, cálidos y sin culpa (prohibido "debes", "obligatorio", "atrasado", "fallaste", "castigo").
   Si current_week.director_proposed es true, no propongas misiones. Si responde rejected, corrige según "errors"
   y reintenta una vez. Si responde ya_propuestas o ya_con_avance, no insistas.
   No uses update-game-week-missions-tool desde este schedule: se reserva para ajustes pedidos por el usuario.
3. FRASES: si quotes_due es true, llama propose-game-quotes-tool con 7-14 frases breves, cálidas y generales
   (sin datos personales ni culpa). Si responde ya_propuestas, no insistas.
4. TEMPORADA: si proposal_due es false, termina y resume en una línea lo observado. Si toca, diseña una temporada de 4 semanas que:
   - celebre lo que sí se hizo y nunca castigue la baja actividad;
   - impulse con bonos (no con exigencias) 1-2 ámbitos poco atendidos según activities_by_scope;
   - mantenga metas modestas (2-4) si la participación fue baja, y hasta 5 solo si las misiones se completaron;
   - use un nombre y una narrativa breves en español, cálidos y sin culpa;
   - respete exactamente los límites de "limits"; usa solo claves de "categories" o "habits".
   - si calendar_hints trae un tema, puedes usar theme y hasta 2 featured_types (de sus suggested_types).
5. Llama propose-game-season-tool con {"season": {name, narrative, weeks, missions, team_bonus, rarity_weights?, featured_line?, theme?, featured_types?, notes?}}.
6. Si responde rejected, corrige según "errors" y reintenta una sola vez. Si vuelve a fallar, termina e informa.
No pidas ni leas descripciones de tareas, tareas privadas, diario ni datos de salud o contactos. No crees ni modifiques tareas.
```

## Presentación

- **Poder** = `round(suma de estadísticas base × (1 + (nivel − 1) × 0,05))`. Si la especie no tiene estadísticas, la base es 300 (`power_level_factor` y `power_default_base` en `config/game.php`). El Poder nunca baja.
- **Tipos con color:** `config('game.type_palette')` lista los tipos con color propio, en forma de slug (`psíquico` → `psiquico`). El color se define en `_game.css` con `--game-type-{slug}` y `--game-on-type-{slug}`, y se aplica con la clase `game-type--{slug}`. Un tipo sin entrada usa el neutro. Para agregar uno, añade su slug a la lista y sus dos variables al CSS.
- **Rarezas:** cada una tiene marco y acento de color (`game-rarity--*`), y siempre texto e ícono (`x-game.rarity-tag`).
- **Componentes:** `x-game.ball`, `x-game.species-card`, `x-game.companion-card` y `x-game.rarity-tag`.
- **Carta coleccionable** (`x-game.collectible-card`, CHG-033): carta vertical 5:7 de diseño original. Lleva marco del color del tipo y borde según la rareza, arte, PS/ATQ/VEL en mini barras, xp, Poder y afinidad. Se inclina con el mouse y tiene reflejo; las legendarias tienen brillo. Se usa en el equipo de la semana (una carta por integrante) y en la revelación de una especie nueva, donde se voltea. Con movimiento reducido no tiene efectos. El arte (150 px) se apoya en la base de su ventana (120 px) y sobresale solo hacia arriba, sin quedar tapado (CHG-036).
- **Apertura:** cartas independientes con la actividad completa. La Poké Ball se balancea, abre sus mitades y presenta la criatura. “Mostrar ahora” salta la secuencia; con movimiento reducido el resultado es inmediato. El resultado ya está guardado antes de animar. Ver ficha y Abrir otra viven en el footer fijo del diálogo.

## Catálogo de especies

El repositorio **no incluye** especies ni arte de terceros. El catálogo se importa desde un JSON:

```bash
php artisan game:import-species ruta/al/catalogo.json
```

Es una lista de objetos y se actualiza por `number`. Si una fila es inválida, no se guarda nada.

| Campo | Obligatorio | Notas |
|---|---|---|
| `number` | sí | Entero único |
| `name` | sí | |
| `line_key` | sí | Agrupa las etapas de una línea (`[a-z0-9_-]`) |
| `stage` | no (1) | 1, 2, 3… |
| `rarity` | no (`common`) | `common`, `uncommon`, `rare`, `legendary` |
| `image_key` | no | Ruta relativa o URL completa |
| `active` | no (true) | |
| `description` | no | Bio de la ficha |
| `types` | no | Lista de textos |
| `base_stats` | no | `hp`, `attack`, `defense`, `special_attack`, `special_defense`, `speed` |
| `evolves_from` | no | Nombre o número de la etapa previa (para las ramas) |
| `evolve_level` | no | Nivel requerido para llegar a esta especie |

Los demás campos se ignoran. Para agregar especies, añade filas y vuelve a importar.

**Imágenes:** con `GAME_IMAGE_BASE_URL` en `.env` se usa la URL `{base}/{image_key}`. Sin ese valor se muestra un marcador neutro. Después de cambiarlo, ejecuta `php artisan config:clear`.

**Derechos:** quien importa el catálogo y configura las imágenes es responsable de contar con autorización para usar ese contenido.

## Configuración

- **Por usuario** (`ModuleSetting` módulo `game`): juego activo, fuentes (tareas, hábitos) y máximo de balls por día de 0 a 50 (Colección → Ajustes); director activo y juego de temporadas (Colección → Temporada).
- **Global** (`config/game.php`): pesos de rareza (60/30/9/1), umbral de protección, xp de repetida y de bienvenida, xp por nivel, nivel máximo, niveles por etapa, tamaño del equipo, xp de equipo y de afinidad, misiones por semana, rango de metas, recompensas y tipos de misión.

## Datos

| Tabla | Contenido |
|---|---|
| `game_species` | Catálogo global |
| `game_events` | Registro polimórfico (`source_type`/`source_id`, `source_label` legible aunque se borre el origen, `kind`, `effect`, `config_snapshot`, `idempotency_key` única por usuario) |
| `game_encounters` | Balls: especie, semilla, `granted_at`, `opened_at`, `result` |
| `game_companions` | Un ejemplar por usuario y línea: especie actual, xp, nivel |
| `game_discoveries` | Especies alcanzadas por usuario |
| `game_weeks`, `game_team_members`, `game_missions`, `game_mission_contributions` | Semana, equipo, misiones y aportes únicos a cada misión |
| `game_seasons` | Temporadas: estado, configuración validada, propuesta original, motivos de rechazo, fechas |
| `game_quotes` | Frases del integrante del día: semilla (`user_id` null) y del director (`week_start`) |

Servicios en `app/Services/Game/`: `RewardGranter`, `EncounterRoller`, `EncounterOpener`, `CompanionProgress`, `WeekPlanner`, `ActivityEffects`, `GameRules`, `SeasonValidator`, `SeasonManager`, `GameSettings`, `SpeciesImageProvider`.

Pruebas: `php artisan test tests/Feature/Game tests/Unit/Game`.


### Completar descripciones españolas

La revelación y la ficha leen la misma descripción del catálogo; abrir una ball no consulta servicios externos.

```bash
php artisan game:sync-species-descriptions --dry-run
php artisan game:sync-species-descriptions
php artisan game:sync-species-descriptions --number=25 --dry-run
```

El comando consulta PokéAPI por número de especie y completa únicamente descripciones nulas, vacías o con espacios. Conserva las existentes, también si cambian mientras se consulta. Selecciona la primera entrada española no vacía ordenada por identificador de versión y normaliza espacios y saltos de línea. Sin español, con respuesta inválida o error de red, omite la especie e informa el resultado.

Las solicitudes son secuenciales, con timeout de 10 segundos (conexión 5), dos intentos y caché de siete días en el store local file. El dry-run no cambia el catálogo y puede llenar esa caché para reanudar. Los errores de red no se cachean. La salida distingue completadas (previstas en simulación), conservadas, sin español y errores; si hubo errores devuelve código de fallo, aunque conserva las actualizaciones correctas para poder reintentar.

No se modifica ningún otro atributo de las especies. La sincronización no corre automáticamente al navegar ni al abrir balls.

### Organización de la ficha

La ficha usa el diálogo estándar ancho (hasta 1200 px), con las secciones Ficha e Historial en la navegación izquierda compartida. Historial solo aparece cuando hay un ejemplar descubierto. La imagen, descripción y cadena evolutiva viven en Ficha; los eventos conservan su orden y el botón Ver más en Historial.

La experiencia del ejemplar actual aparece en una tarjeta central bajo el Pokémon: XP total acumulada, nivel, avance y XP restante para subir. Al alcanzar el nivel máximo se conserva la experiencia y no se anuncia un siguiente nivel. El panel Características reúne poder y estadísticas, junto a los datos del ejemplar y sus opciones de evolución. Permanece a la derecha en escritorio y pasa debajo del contenido en pantallas menores. Cabecera y footer permanecen accesibles; la navegación se colapsa en móvil con el control Secciones. No se revelan datos de especies sin descubrir.

## Compañero de foco (CHG-059)
- Mientras usas el temporizador (página Pomodoro y pantalla completa "Foco de hoy"), el guardián de Pomodoro (`ModuleGuardian::pick(…, 'pomodoro')`) aparece quieto junto al reloj ("Foco de hoy": bajo el reloj con su globo; Pomodoro: bajo el dial). Cada frase empieza con su grito (`App\Support\Game\SpeciesCry`, p. ej. "¡Pika-pika!"). "Foco de hoy" toma el tema de su tipo (`focus-themed game-stage--*`).
- Su frase cambia solo al cambiar de etapa: listo, inicio, mitad (50 %), tramo final (80 % o últimos 5 min), en pausa, terminado y descanso. Textos en `config/game.php` → `focus_companion`.
- Para no distraer: quieto mientras corre un ciclo de trabajo, sin sonido; un único salto al terminar (nada con movimiento reducido). Se oculta con su botón y la preferencia queda en el navegador (`life-tracker:focus-companion-hidden:{userId}`).
- La etapa se calcula en el navegador (`resources/js/focus-companion.js`) sobre el estado de `pomodoroTimer`; no hace consultas durante la sesión. Con el juego apagado o sin ejemplares no se muestra.

## Medición de recurrencia (CHG-067)

- `user_active_days`: una fila por usuario y día con `first_channel` (`direct`, `push`, `checkin`, `close`, `shortcut`, `return`, leído de `?via=`) y `records_count` (actividades del juego de ese día, incluidas las del MCP).
- El middleware `TrackActiveDay` escribe una sola vez por día gracias a la caché.
- `php artisan miso:retention --days=30` muestra D1/D7/D30, canales de entrada y registros por día activo.
- Con el juego apagado no hay actividades, así que `records_count` queda en 0.

## Volver sin culpa (CHG-072)

- Si el último día activo anterior a hoy (`user_active_days`) fue hace 3 días o más (`config/engagement.php`), Inicio muestra `Home\WelcomeBack` una sola vez por regreso (`welcome_backs`, único usuario + fecha).
- Ofrece mover lo atrasado a hoy, a otra fecha o dejarlo sin fecha (con Deshacer), un enlace para marcar hábitos de los días ausentes y +1 vaso de agua.
- Usuarios sin historial nunca la ven; las ausencias anteriores a CHG-067 no se detectan.
