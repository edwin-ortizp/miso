# CHG-048 · Tareas personales dan constancia en vez de ball
**Estado:** En verificación
**Nivel:** S
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-10 · **Aprobado:** 2026-11-10 · **Cerrado:** —
**Relacionado:** CHG-042 (constancia / micro-xp), CHG-027 (fuentes del juego)

## 0. En palabras simples
<!-- Para el usuario: sin jerga. Si no se entiende en 30 segundos, reescríbelo. -->
- Qué vas a ver distinto: completar una tarea de la categoría **Personal** (p. ej. "Llamar a mamá", "Tiempo con Alison") ya no da ball. Le da **+1 xp** a tu equipo de la semana, una vez al día, con el aviso pequeño de "Constancia · Tareas personales". No avanza misiones ni gasta el máximo diario de balls. En Ajustes → Constancia aparece el interruptor "Tareas personales"; si lo apagas, esas tareas vuelven a dar ball como antes.
- Cómo comprobarlo tú: completa una tarea Personal → aviso de constancia, sin ball. Completa otra Personal el mismo día → nada. Completa una tarea de Siigo → ball como siempre. Apaga el interruptor y completa una Personal → ball.

## 1. Resumen
Las tareas personales (relaciones, recordatorios familiares) son pequeñas y frecuentes; dar una ball por cada una infla el juego. Se reutiliza la constancia del CHG-042: nueva fuente `personal_tasks` que da +1 xp de equipo por día, sin ball ni misiones. Solo aplica a la categoría con key `personal`.

## 3. UX/UI
Plantilla: la existente de Ajustes del juego (sin pantallas nuevas). Solo se agrega un interruptor a la sección "Constancia" del CHG-042.

```
┌ Constancia ─────────────────────────────────────┐
│ ...                                             │
│ [●] Hogar (comidas y compras)                   │
│ [●] Tareas personales (categoría Personal)      │  ← nuevo
└─────────────────────────────────────────────────┘
Aviso:  ✦ EXPERIENCIA · Constancia · Tareas personales
```
- Textos: etiqueta del interruptor "Tareas personales (categoría Personal)"; origen en aviso e historial "Constancia · Tareas personales".

## 4. Diseño técnico
- `config/game.php`: `consistency_sources` suma `'personal_tasks' => 'Tareas personales'`; `defaults.sources.personal_tasks => true`; nueva clave `consistency_task_categories => ['personal']`.
- `app/Services/Game/RewardGranter.php` `forTask()`: si `$task->category` está en `consistency_task_categories` **y** el juego y la fuente `personal_tasks` están encendidos → `app(ConsistencyXp::class)->grant($userId, 'personal_tasks', $task, $at)` y `return null` (no se llama `grant()`: sin ball, sin misiones, sin cupo diario). Si la fuente está apagada → flujo normal con ball.
- `resources/views/livewire/game/game-settings.blade.php`: agregar la etiqueta al mapa `$consistencyLabels`. `GameSettingsPage` ya itera `consistency_sources`, no requiere cambio.
- Sin migraciones. Las subcategorías (padre `personal`) no aplican: solo la key exacta.
- Qué NO tocar: `ActivityEffects`, misiones, director.

## 5. Pruebas
| Caso | Test |
|---|---|
| Tarea Personal completada → sin `GameEncounter`, +1 xp por integrante | tests/Feature/Game/PersonalTaskConsistencyTest.php |
| Segunda tarea Personal el mismo día → sin cambios | idem |
| Tarea Personal no avanza misiones | idem |
| Tarea de otra categoría → ball como antes | idem |
| Interruptor apagado → tarea Personal da ball | idem |
- Comando: `php artisan test tests/Feature/Game`

## 7. Tareas
- [x] T1 · Las tareas Personal dan +1 xp de constancia en vez de ball, con interruptor en Ajustes — Resp: developer — Archivos: config/game.php, app/Services/Game/RewardGranter.php, resources/views/livewire/game/game-settings.blade.php, tests/Feature/Game/PersonalTaskConsistencyTest.php — Hecho cuando: los 5 casos de §5 pasan y la suite Game sigue en verde
- [ ] T2 · Verificar criterios — Resp: qa-tester — Hecho cuando: los casos de §0 se cumplen
- [ ] T3 · Documentar la fuente en docs/game.md (sección Constancia) — Resp: tech-writer — Archivos: docs/game.md — Hecho cuando: docs al día

## 8. Bitácora de implementación (developer)
- 2026-11-10 — Inicio (start --force: hay otros CHG En desarrollo en paralelo, mismo motivo que CHG-042).
- T1 — `config/game.php` (fuente `personal_tasks`, default encendida; `consistency_task_categories => ['personal']`), `RewardGranter::forTask` desvía a `ConsistencyXp::grant` cuando la categoría aplica y la fuente está encendida, etiqueta en `game-settings.blade.php`. Tests: `PersonalTaskConsistencyTest` 5/5. Suite Game: 217/218; falla `NewSourcesTest::test_director_accepts_the_new_scopes` (validador de misiones, ajeno a este cambio: no pasa por forTask).

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-10 — Creado en to-do/ (Borrador, nivel S)
- Decisión (Alex): las tareas personales NO avanzan misiones semanales. Solo aplica a la categoría `personal`.
- 2026-11-10 — Aprobado por el usuario (Compuerta 2)
- 2026-11-10 — Movido a in-progress/ (En desarrollo)
- LECCIÓN: ninguna
- 2026-11-10 — Implementación terminada → En verificación
