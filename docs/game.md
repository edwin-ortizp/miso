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
- **Ámbitos** (`config('game.scopes')`): `habits` (Hábitos), `focus` (Foco), `goals` (Metas) y `health` (Salud), además de las categorías de tarea. Cada uno puede ser afinidad del equipo, cuenta en la variedad y se admite en los bonos de temporada y en las misiones de ámbito del director.
- En Ajustes puedes apagar cada fuente: tareas, hábitos, foco, metas y salud.

- Máximo diario configurable (por defecto **5**). Al alcanzarlo, la actividad se registra sin ball y no se muestra error.
- La especie se decide **al conceder** la ball, con una semilla registrada. Solo salen especies de etapa 1, por rareza ponderada.
- Protección: tras **10** balls seguidas sin una línea nueva, la siguiente garantiza una especie no descubierta.
- Cuando llega una ball aparece el aviso "¡Ganaste una ball!" (`BallNotifier` en el layout).

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
- **Misiones:** 3 por semana, con metas de 2 a 4: tareas de una categoría, grupos de hábitos de un momento del día (días distintos) y variedad de ámbitos (categorías distintas, donde hábitos cuenta como un ámbito). Al 50 % se recibe **1 ball** y al 100 % **2 más**, una sola vez. Estas balls no cuentan para el límite diario. Si una misión queda incompleta no pasa nada y la semana siguiente trae otras.
- **Misiones del director (CHG-026):** cada lunes el director puede proponer las misiones de la semana actual con `propose-game-week-missions-tool`.
  - Incluye el tipo `specific_tasks`: "Cierra n de estas m tareas", con 2 a 6 tareas abiertas no privadas. Cuenta una vez por tarea; las recurrentes cuentan por su ocurrencia.
  - Solo reemplaza misiones sin avance; si alguna ya avanzó, responde `ya_con_avance` y nada cambia.
  - Los títulos no pueden usar "debes", "obligatorio", "atrasado", "fallaste" ni "castigo".
  - En Semana, esas misiones llevan "Propuesta por el director" y sus tareas enlazadas, con ✓ cuando ya están completadas.
  - Si el director no propone nada, quedan las misiones automáticas.

## Director y temporadas

El director IA **no corre dentro de MiSo**: es un schedule de Paseo que usa el conector MCP de MiSo. Lee los datos de producción **solo por MCP**: agregados del juego y, para las misiones, la lista acotada de tareas abiertas no privadas. MiSo no integra un cliente LLM ni usa el scheduler del servidor. Todo cálculo de recompensas sigue siendo determinista en el backend.

- `get-game-director-context-tool` devuelve:
  - la participación de las últimas 8 semanas (actividades, actividad por ámbito, balls y misiones);
  - las categorías, el tamaño de la colección, las temporadas activa, programada y recientes;
  - los límites y `proposal_due`;
  - `open_tasks`: hasta 40 tareas abiertas **no privadas**, con id, código, título, categoría, vencimiento, si está vencida, tamaño, prioridad y días abierta. Lo aprobó Alex para las misiones contextuales;
  - `habit_periods` y `current_week` (misiones actuales y si se pueden reemplazar).
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
   - otra que equilibre: un ámbito poco atendido (category_tasks) o hábitos (habit_groups, period de habit_periods);
   - una variety para premiar el equilibrio.
   Títulos breves, cálidos y sin culpa (prohibido "debes", "obligatorio", "atrasado", "fallaste", "castigo").
   Si current_week.director_proposed es true, no propongas misiones. Si responde rejected, corrige según "errors"
   y reintenta una vez. Si responde ya_propuestas o ya_con_avance, no insistas.
3. TEMPORADA: si proposal_due es false, termina y resume en una línea lo observado. Si toca, diseña una temporada de 4 semanas que:
   - celebre lo que sí se hizo y nunca castigue la baja actividad;
   - impulse con bonos (no con exigencias) 1-2 ámbitos poco atendidos según activities_by_scope;
   - mantenga metas modestas (2-4) si la participación fue baja, y hasta 5 solo si las misiones se completaron;
   - use un nombre y una narrativa breves en español, cálidos y sin culpa;
   - respete exactamente los límites de "limits"; usa solo claves de "categories" o "habits".
4. Llama propose-game-season-tool con {"season": {name, narrative, weeks, missions, team_bonus, rarity_weights?, featured_line?, notes?}}.
5. Si responde rejected, corrige según "errors" y reintenta una sola vez. Si vuelve a fallar, termina e informa.
No pidas ni leas descripciones de tareas, tareas privadas, diario ni datos de salud o contactos. No crees ni modifiques tareas.
```

## Presentación

- **Poder** = `round(suma de estadísticas base × (1 + (nivel − 1) × 0,05))`. Si la especie no tiene estadísticas, la base es 300 (`power_level_factor` y `power_default_base` en `config/game.php`). El Poder nunca baja.
- **Tipos con color:** `config('game.type_palette')` lista los tipos con color propio, en forma de slug (`psíquico` → `psiquico`). El color se define en `_game.css` con `--game-type-{slug}` y `--game-on-type-{slug}`, y se aplica con la clase `game-type--{slug}`. Un tipo sin entrada usa el neutro. Para agregar uno, añade su slug a la lista y sus dos variables al CSS.
- **Rarezas:** cada una tiene marco y acento de color (`game-rarity--*`), y siempre texto e ícono (`x-game.rarity-tag`).
- **Componentes:** `x-game.ball`, `x-game.species-card`, `x-game.companion-card` y `x-game.rarity-tag`.
- **Apertura:** la ball tiembla, destella y aparece la criatura (Alpine en `game-balls.blade.php`). El resultado ya está guardado antes de la animación. Con movimiento reducido, solo hay un fundido.

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

Servicios en `app/Services/Game/`: `RewardGranter`, `EncounterRoller`, `EncounterOpener`, `CompanionProgress`, `WeekPlanner`, `ActivityEffects`, `GameRules`, `SeasonValidator`, `SeasonManager`, `GameSettings`, `SpeciesImageProvider`.

Pruebas: `php artisan test tests/Feature/Game tests/Unit/Game`.

